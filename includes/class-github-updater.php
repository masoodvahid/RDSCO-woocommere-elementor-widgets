<?php
/**
 * Serves plugin updates from GitHub Releases.
 *
 * - The plugin header declares `Update URI: https://github.com/...`, so WordPress
 *   (5.8+) never asks wordpress.org about this plugin and fires the
 *   `update_plugins_github.com` filter during its normal update checks instead.
 * - We answer with the latest GitHub release (cached 12 hours), using the
 *   release asset zip as the package.
 * - `plugins_api` feeds the "View details" modal with the release notes.
 * - `upgrader_source_selection` keeps the folder name stable if the package
 *   ever comes from GitHub's auto-generated zipball.
 *
 * Optional: define( 'RDSCO_WEW_GITHUB_TOKEN', '...' ) in wp-config.php to raise
 * the GitHub API rate limit (not needed for a public repository).
 */

namespace RDSCO\WEW;

use WP_Error;

defined( 'ABSPATH' ) || exit;

final class GitHub_Updater {

	private const CACHE_TTL     = 43200; // 12 hours.
	private const ERROR_TTL     = 3600;  // Back off for 1 hour after a failed check.
	private const ALLOWED_HOSTS = [ 'github.com', 'api.github.com', 'codeload.github.com' ];

	private string $basename;
	private string $slug;
	private string $cache_key;
	private ?array $release = null;
	private bool $resolved  = false;

	public function __construct(
		private string $file,
		private string $owner,
		private string $repo,
		private string $asset_name
	) {
		$this->basename  = plugin_basename( $file );
		$this->slug      = dirname( $this->basename );
		$this->cache_key = 'rdsco_wew_gh_' . md5( strtolower( $owner . '/' . $repo ) );
	}

	public function register(): void {
		add_filter( 'update_plugins_github.com', [ $this, 'check_update' ], 10, 3 );
		add_filter( 'plugins_api', [ $this, 'plugin_info' ], 20, 3 );
		add_filter( 'upgrader_source_selection', [ $this, 'fix_source_dir' ], 10, 4 );
		add_filter( 'plugin_row_meta', [ $this, 'row_meta' ], 10, 2 );
	}

	/**
	 * @param array|false $update
	 * @param array       $plugin_data
	 * @return array|false
	 */
	public function check_update( $update, $plugin_data, $plugin_file ) {
		if ( $plugin_file !== $this->basename ) {
			return $update;
		}

		$release = $this->get_release();
		if ( ! $release ) {
			return $update;
		}

		// WordPress compares `version` with the installed one and decides.
		return [
			'slug'         => $this->slug,
			'version'      => $release['version'],
			'url'          => $release['url'],
			'package'      => $release['package'],
			'requires'     => $release['requires'],
			'requires_php' => $release['requires_php'],
		];
	}

	/**
	 * "View details" modal on the Plugins screen.
	 *
	 * @param false|object|array $result
	 * @param string             $action
	 * @param object             $args
	 * @return false|object|array
	 */
	public function plugin_info( $result, $action, $args ) {
		if ( 'plugin_information' !== $action || ! is_object( $args ) || ( $args->slug ?? '' ) !== $this->slug ) {
			return $result;
		}

		if ( ! function_exists( 'get_plugin_data' ) ) {
			require_once ABSPATH . 'wp-admin/includes/plugin.php';
		}

		$plugin  = get_plugin_data( $this->file, false, false );
		$release = $this->get_release();
		$repo    = 'https://github.com/' . $this->owner . '/' . $this->repo;

		return (object) [
			'name'          => $plugin['Name'],
			'slug'          => $this->slug,
			'version'       => $release['version'] ?? $plugin['Version'],
			'author'        => $plugin['Author'],
			'homepage'      => $repo,
			'requires'      => $release['requires'] ?? $plugin['RequiresWP'],
			'requires_php'  => $release['requires_php'] ?? $plugin['RequiresPHP'],
			'last_updated'  => $release['published'] ?? '',
			'download_link' => $release['package'] ?? '',
			'sections'      => [
				'description' => wpautop( esc_html( $plugin['Description'] ) ),
				'changelog'   => $release && '' !== $release['notes']
					? wpautop( esc_html( $release['notes'] ) )
					: '<p><a href="' . esc_url( $repo . '/releases' ) . '">' . esc_html__( 'See releases on GitHub', 'rdsco-woocommerce-elementor-widgets' ) . '</a></p>',
			],
		];
	}

	/**
	 * Rename the extracted folder to our slug (a GitHub zipball extracts to
	 * "owner-repo-<sha>/"), so the plugin path and its activation are kept.
	 *
	 * @param string|WP_Error $source
	 * @return string|WP_Error
	 */
	public function fix_source_dir( $source, $remote_source, $upgrader, $hook_extra = [] ) {
		if ( is_wp_error( $source ) || ( $hook_extra['plugin'] ?? '' ) !== $this->basename ) {
			return $source;
		}

		global $wp_filesystem;

		$desired = trailingslashit( $remote_source ) . $this->slug . '/';
		if ( trailingslashit( $source ) === $desired ) {
			return $source;
		}

		if ( ! $wp_filesystem || ! $wp_filesystem->move( untrailingslashit( $source ), untrailingslashit( $desired ), true ) ) {
			return new WP_Error( 'rdsco_wew_update_folder', __( 'Could not prepare the plugin update folder.', 'rdsco-woocommerce-elementor-widgets' ) );
		}

		return $desired;
	}

	/**
	 * Adds a "Check for updates" link that bypasses the 12-hour cache.
	 *
	 * @param string[] $links
	 * @return string[]
	 */
	public function row_meta( $links, $plugin_file ) {
		if ( $plugin_file === $this->basename && current_user_can( 'update_plugins' ) ) {
			$links[] = '<a href="' . esc_url( self_admin_url( 'update-core.php?force-check=1' ) ) . '">' . esc_html__( 'Check for updates', 'rdsco-woocommerce-elementor-widgets' ) . '</a>';
		}
		return $links;
	}

	/* ---------------------------------------------------------------------
	 * GitHub
	 * ------------------------------------------------------------------ */

	/**
	 * @return array{version:string,url:string,package:string,notes:string,published:string,requires:string,requires_php:string}|null
	 */
	private function get_release(): ?array {
		// Several hooks may ask in the same request; hit GitHub at most once.
		if ( $this->resolved ) {
			return $this->release;
		}
		$this->resolved = true;

		if ( ! $this->is_force_check() ) {
			$cached = get_site_transient( $this->cache_key );
			if ( is_array( $cached ) ) {
				$this->release = empty( $cached['version'] ) ? null : $cached;
				return $this->release;
			}
		}

		$this->release = $this->fetch_release();
		set_site_transient( $this->cache_key, $this->release ?? [ 'version' => '' ], $this->release ? self::CACHE_TTL : self::ERROR_TTL );

		return $this->release;
	}

	private function fetch_release(): ?array {
		$response = wp_remote_get(
			sprintf( 'https://api.github.com/repos/%s/%s/releases/latest', rawurlencode( $this->owner ), rawurlencode( $this->repo ) ),
			[
				'timeout' => 10,
				'headers' => $this->api_headers(),
			]
		);

		if ( is_wp_error( $response ) || 200 !== (int) wp_remote_retrieve_response_code( $response ) ) {
			return null;
		}

		$data = json_decode( (string) wp_remote_retrieve_body( $response ), true );
		if ( ! is_array( $data ) || empty( $data['tag_name'] ) ) {
			return null;
		}

		$tag     = (string) $data['tag_name'];
		$version = ltrim( $tag, 'vV' );
		if ( ! preg_match( '/^\d+(?:\.\d+){0,3}(?:-[0-9A-Za-z.-]+)?$/', $version ) ) {
			return null;
		}

		// Prefer the built asset; fall back to the source zipball.
		$package = '';
		foreach ( (array) ( $data['assets'] ?? [] ) as $asset ) {
			if ( is_array( $asset ) && ( $asset['name'] ?? '' ) === $this->asset_name && ! empty( $asset['browser_download_url'] ) ) {
				$package = (string) $asset['browser_download_url'];
				break;
			}
		}
		if ( '' === $package ) {
			$package = (string) ( $data['zipball_url'] ?? '' );
		}
		if ( ! $this->is_allowed_url( $package ) ) {
			return null;
		}

		return [
			'version'   => $version,
			'url'       => esc_url_raw( (string) ( $data['html_url'] ?? 'https://github.com/' . $this->owner . '/' . $this->repo ) ),
			'package'   => esc_url_raw( $package ),
			'notes'     => wp_strip_all_tags( (string) ( $data['body'] ?? '' ) ),
			'published' => (string) ( $data['published_at'] ?? '' ),
		] + $this->fetch_requirements( $tag );
	}

	/**
	 * Reads "Requires at least" / "Requires PHP" from the released plugin
	 * header, so WordPress can block an update this server can't run.
	 *
	 * @return array{requires:string,requires_php:string}
	 */
	private function fetch_requirements( string $tag ): array {
		$out = [
			'requires'     => '',
			'requires_php' => '',
		];

		$response = wp_remote_get(
			sprintf(
				'https://raw.githubusercontent.com/%s/%s/%s/%s',
				rawurlencode( $this->owner ),
				rawurlencode( $this->repo ),
				rawurlencode( $tag ),
				rawurlencode( basename( $this->file ) )
			),
			[ 'timeout' => 10 ]
		);

		if ( is_wp_error( $response ) || 200 !== (int) wp_remote_retrieve_response_code( $response ) ) {
			return $out;
		}

		$head = substr( (string) wp_remote_retrieve_body( $response ), 0, 8192 );
		foreach ( [ 'requires' => 'Requires at least', 'requires_php' => 'Requires PHP' ] as $key => $label ) {
			if ( preg_match( '/^[ \t\/*#@]*' . preg_quote( $label, '/' ) . ':(.*)$/mi', $head, $m ) ) {
				$out[ $key ] = sanitize_text_field( trim( $m[1] ) );
			}
		}

		return $out;
	}

	/**
	 * @return array<string, string>
	 */
	private function api_headers(): array {
		$headers = [
			'Accept'               => 'application/vnd.github+json',
			'X-GitHub-Api-Version' => '2022-11-28',
		];

		if ( defined( 'RDSCO_WEW_GITHUB_TOKEN' ) && is_string( RDSCO_WEW_GITHUB_TOKEN ) && '' !== RDSCO_WEW_GITHUB_TOKEN ) {
			$headers['Authorization'] = 'Bearer ' . RDSCO_WEW_GITHUB_TOKEN;
		}

		return $headers;
	}

	private function is_allowed_url( string $url ): bool {
		return 'https' === wp_parse_url( $url, PHP_URL_SCHEME )
			&& in_array( wp_parse_url( $url, PHP_URL_HOST ), self::ALLOWED_HOSTS, true );
	}

	/**
	 * "Check again" on Dashboard → Updates (or our row link) skips the cache.
	 */
	private function is_force_check(): bool {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only flag, same as core's update-core.php.
		return is_admin() && ! empty( $_GET['force-check'] ) && current_user_can( 'update_plugins' );
	}
}
