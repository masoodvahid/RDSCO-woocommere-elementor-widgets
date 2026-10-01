<?php
/**
 * Plugin Name:       BB Product Search
 * Plugin URI:        https://github.com/masoodvahid/RDSCO-woo-smart-search
 * Description:       Lightweight live product search (title, content, SKU) via the [bb_product_search] shortcode.
 * Version:           1.1.0
 * Requires at least: 6.5
 * Requires PHP:      8.1
 * Requires Plugins:  woocommerce
 * License:           GPL-2.0-or-later
 * Text Domain:       bb-product-search
 * Update URI:        https://github.com/masoodvahid/RDSCO-woo-smart-search
 *
 * Usage: [bb_product_search placeholder="Search products…" limit="8"]
 */

defined( 'ABSPATH' ) || exit;

// Updates from GitHub Releases (wp-admin → Plugins). Always loaded: update
// checks and upgrades also run from cron and REST, not only is_admin().
require_once __DIR__ . '/includes/class-bbps-github-updater.php';
( new BBPS_GitHub_Updater( __FILE__, 'masoodvahid', 'RDSCO-woo-smart-search', 'bb-product-search.zip' ) )->register();

final class BBPS_Product_Search {

	public const VERSION     = '1.1.0';
	private const HANDLE     = 'bbps-product-search';
	private const REST_NS    = 'bbps/v1';
	private const MIN_CHARS  = 3;
	private const MAX_CHARS  = 64;
	private const MAX_WORDS  = 5;
	private const MAX_LIMIT  = 10;
	private const DELAY_MS   = 500;
	private const CACHE_GRP  = 'bbps';
	private const CACHE_TTL  = 3600; // Object-cache TTL; entries are also invalidated on any post change.
	private const HTTP_TTL   = 300;  // Browser/edge cache for the REST response.

	public static function init(): void {
		add_shortcode( 'bb_product_search', [ self::class, 'shortcode' ] );
		add_action( 'wp_enqueue_scripts', [ self::class, 'register_assets' ] );
		add_action( 'rest_api_init', [ self::class, 'register_route' ] );
	}

	/* ---------------------------------------------------------------------
	 * Front end
	 * ------------------------------------------------------------------ */

	public static function register_assets(): void {
		$base = plugin_dir_url( __FILE__ ) . 'assets/';

		wp_register_style( self::HANDLE, $base . 'bbps.css', [], self::VERSION );
		wp_register_script(
			self::HANDLE,
			$base . 'bbps.js',
			[],
			self::VERSION,
			[
				'in_footer' => true,
				'strategy'  => 'defer',
			]
		);

		$config = [
			'endpoint' => rest_url( self::REST_NS . '/search' ),
			'minChars' => self::MIN_CHARS,
			'delay'    => self::DELAY_MS,
			'i18n'     => [
				'loading' => __( 'Searching…', 'bb-product-search' ),
				'none'    => __( 'No products found.', 'bb-product-search' ),
				'error'   => __( 'Search is unavailable right now. Please try again.', 'bb-product-search' ),
				'viewAll' => __( 'View all results', 'bb-product-search' ),
				'sku'     => __( 'SKU', 'bb-product-search' ),
				/* translators: %d: number of results. */
				'results' => __( '%d results available.', 'bb-product-search' ),
			],
		];

		wp_add_inline_script( self::HANDLE, 'window.bbpsConfig = ' . wp_json_encode( $config ) . ';', 'before' );
	}

	/**
	 * @param array<string, string>|string $atts
	 */
	public static function shortcode( $atts ): string {
		if ( ! function_exists( 'wc_get_product' ) ) {
			return '';
		}

		$atts = shortcode_atts(
			[
				'placeholder' => __( 'Search products…', 'bb-product-search' ),
				'limit'       => 8,
			],
			$atts,
			'bb_product_search'
		);

		$limit   = max( 1, min( self::MAX_LIMIT, absint( $atts['limit'] ) ) );
		$uid     = wp_unique_id( 'bbps-' );
		$label   = __( 'Search products', 'bb-product-search' );
		$current = is_search() ? get_search_query( false ) : '';

		wp_enqueue_style( self::HANDLE );
		wp_enqueue_script( self::HANDLE );

		ob_start();
		?>
		<form class="bbps" role="search" method="get" action="<?php echo esc_url( home_url( '/' ) ); ?>" data-limit="<?php echo esc_attr( (string) $limit ); ?>">
			<label class="bbps__sr" for="<?php echo esc_attr( $uid ); ?>-input"><?php echo esc_html( $label ); ?></label>
			<div class="bbps__field">
				<input
					id="<?php echo esc_attr( $uid ); ?>-input"
					class="bbps__input"
					type="search"
					name="s"
					value="<?php echo esc_attr( $current ); ?>"
					placeholder="<?php echo esc_attr( $atts['placeholder'] ); ?>"
					maxlength="<?php echo esc_attr( (string) self::MAX_CHARS ); ?>"
					autocomplete="off"
					autocapitalize="off"
					spellcheck="false"
					enterkeyhint="search"
					role="combobox"
					aria-autocomplete="list"
					aria-expanded="false"
					aria-controls="<?php echo esc_attr( $uid ); ?>-list"
				>
				<input type="hidden" name="post_type" value="product">
			</div>
			<div class="bbps__panel" hidden>
				<ul id="<?php echo esc_attr( $uid ); ?>-list" class="bbps__list" role="listbox" aria-label="<?php echo esc_attr( $label ); ?>"></ul>
				<p class="bbps__message" hidden></p>
			</div>
			<div class="bbps__sr bbps__status" role="status" aria-live="polite"></div>
		</form>
		<?php
		return (string) ob_get_clean();
	}

	/* ---------------------------------------------------------------------
	 * REST endpoint: GET /wp-json/bbps/v1/search?q=...&limit=8
	 * ------------------------------------------------------------------ */

	public static function register_route(): void {
		register_rest_route(
			self::REST_NS,
			'/search',
			[
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => [ self::class, 'handle_request' ],
				'permission_callback' => '__return_true', // Public, read-only.
				'args'                => [
					'q'     => [
						'type'              => 'string',
						'required'          => true,
						'validate_callback' => static fn( $value ): bool => is_string( $value ) && mb_strlen( self::normalize_term( $value ) ) >= self::MIN_CHARS,
						'sanitize_callback' => [ self::class, 'normalize_term' ],
					],
					'limit' => [
						'type'              => 'integer',
						'default'           => 8,
						'minimum'           => 1,
						'maximum'           => self::MAX_LIMIT,
						'validate_callback' => 'rest_validate_request_arg',
						'sanitize_callback' => 'absint',
					],
				],
			]
		);
	}

	public static function handle_request( WP_REST_Request $request ): WP_REST_Response {
		if ( ! function_exists( 'wc_get_product' ) ) {
			return new WP_REST_Response( [ 'items' => [] ], 503 );
		}

		$term  = (string) $request->get_param( 'q' );
		$limit = max( 1, min( self::MAX_LIMIT, (int) $request->get_param( 'limit' ) ) );

		$response = new WP_REST_Response( [ 'items' => self::cached_search( $term, $limit ) ] );

		// Requests are sent without cookies, so the response is the same for everyone.
		$response->header( 'Cache-Control', 'public, max-age=' . self::HTTP_TTL );

		return $response;
	}

	public static function normalize_term( $value ): string {
		$value = is_string( $value ) ? wp_check_invalid_utf8( $value ) : '';
		$value = wp_strip_all_tags( $value );
		$value = preg_replace( '/\s+/u', ' ', $value ) ?? '';

		return mb_substr( trim( $value ), 0, self::MAX_CHARS );
	}

	/* ---------------------------------------------------------------------
	 * Search
	 * ------------------------------------------------------------------ */

	/**
	 * Uses the persistent object cache when available. The key includes the
	 * core "posts" last-changed stamp, so any product save invalidates it
	 * without extra writes. Without a persistent cache we skip server-side
	 * caching on purpose (transients would add a DB write per typed term).
	 *
	 * @return array<int, array<string, mixed>>
	 */
	private static function cached_search( string $term, int $limit ): array {
		if ( ! wp_using_ext_object_cache() ) {
			return self::search( $term, $limit );
		}

		$lower = function_exists( 'mb_strtolower' ) ? mb_strtolower( $term ) : strtolower( $term );
		$key   = 'q_' . md5( $lower . '|' . $limit . '|' . get_locale() ) . '_' . wp_cache_get_last_changed( 'posts' );
		$items = wp_cache_get( $key, self::CACHE_GRP );

		if ( ! is_array( $items ) ) {
			$items = self::search( $term, $limit );
			wp_cache_set( $key, $items, self::CACHE_GRP, self::CACHE_TTL );
		}

		return $items;
	}

	/**
	 * @return array<int, array<string, mixed>>
	 */
	private static function search( string $term, int $limit ): array {
		$ids = self::query_ids( $term, $limit );
		if ( ! $ids ) {
			return [];
		}

		// One query for posts + meta + terms of the matched IDs only.
		$query = new WP_Query(
			[
				'post_type'           => 'product',
				'post_status'         => 'publish',
				'post__in'            => $ids,
				'orderby'             => 'post__in',
				'posts_per_page'      => count( $ids ),
				'no_found_rows'       => true,
				'ignore_sticky_posts' => true,
				'suppress_filters'    => true,
			]
		);
		update_post_thumbnail_cache( $query );

		$items = [];
		foreach ( $query->posts as $post ) {
			$product = wc_get_product( $post );
			if ( ! $product ) {
				continue;
			}

			$image_id = $product->get_image_id();

			$items[] = [
				'id'    => $product->get_id(),
				'title' => html_entity_decode( wp_strip_all_tags( $product->get_name() ), ENT_QUOTES, 'UTF-8' ),
				'url'   => esc_url_raw( $product->get_permalink() ),
				'sku'   => (string) $product->get_sku(),
				'price' => wp_kses_post( $product->get_price_html() ),
				'image' => $image_id ? (string) wp_get_attachment_image_url( (int) $image_id, 'woocommerce_gallery_thumbnail' ) : '',
			];
		}

		return $items;
	}

	/**
	 * Single SQL query returning ranked product IDs.
	 *
	 * Text: every word must appear in title, excerpt or content.
	 * SKU:  full term matched against the indexed wc_product_meta_lookup
	 *       table, including variation SKUs (mapped to the parent product).
	 * Visibility: respects "Hidden from search" and the
	 *       "Hide out of stock items" WooCommerce setting.
	 *
	 * @return int[]
	 */
	private static function query_ids( string $term, int $limit ): array {
		global $wpdb;

		$lookup = $wpdb->prefix . 'wc_product_meta_lookup';
		$args   = [];

		// Words for text matching (skip 1-char noise, cap the count).
		$words = preg_split( '/\s+/u', $term, -1, PREG_SPLIT_NO_EMPTY ) ?: [];
		$words = array_values( array_filter( array_unique( $words ), static fn( string $w ): bool => mb_strlen( $w ) >= 2 ) );
		$words = array_slice( $words ?: [ $term ], 0, self::MAX_WORDS );

		$text_parts = [];
		foreach ( $words as $word ) {
			$like         = '%' . $wpdb->esc_like( $word ) . '%';
			$text_parts[] = '(p.post_title LIKE %s OR p.post_excerpt LIKE %s OR p.post_content LIKE %s)';
			array_push( $args, $like, $like, $like );
		}

		$like_term = '%' . $wpdb->esc_like( $term ) . '%';
		array_push( $args, $like_term, $like_term );

		$sql = "SELECT p.ID
			FROM {$wpdb->posts} p
			LEFT JOIN {$lookup} l ON l.product_id = p.ID
			WHERE p.post_type = 'product'
				AND p.post_status = 'publish'
				AND p.post_password = ''
				AND (
					( " . implode( ' AND ', $text_parts ) . " )
					OR l.sku LIKE %s
					OR p.ID IN (
						SELECT v.post_parent
						FROM {$wpdb->posts} v
						INNER JOIN {$lookup} lv ON lv.product_id = v.ID
						WHERE v.post_type = 'product_variation'
							AND v.post_status = 'publish'
							AND lv.sku LIKE %s
					)
				)";

		$exclude = self::excluded_visibility_terms();
		if ( $exclude ) {
			$sql .= " AND p.ID NOT IN (
				SELECT tr.object_id FROM {$wpdb->term_relationships} tr
				WHERE tr.term_taxonomy_id IN (" . implode( ',', array_fill( 0, count( $exclude ), '%d' ) ) . ')
			)';
			array_push( $args, ...$exclude );
		}

		// Ranking: exact SKU > title starts with > title contains > SKU contains > the rest.
		$sql .= ' ORDER BY CASE
				WHEN l.sku = %s THEN 0
				WHEN p.post_title LIKE %s THEN 1
				WHEN p.post_title LIKE %s THEN 2
				WHEN l.sku LIKE %s THEN 3
				ELSE 4
			END, p.post_title ASC
			LIMIT %d';
		array_push( $args, $term, $wpdb->esc_like( $term ) . '%', $like_term, $like_term, $limit );

		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.DirectDatabaseQuery -- Table names come from $wpdb; all values are prepared.
		$ids = $wpdb->get_col( $wpdb->prepare( $sql, $args ) );

		return array_map( 'intval', $ids ?: [] );
	}

	/**
	 * @return int[] term_taxonomy_ids to exclude.
	 */
	private static function excluded_visibility_terms(): array {
		if ( ! function_exists( 'wc_get_product_visibility_term_ids' ) ) {
			return [];
		}

		$terms   = wc_get_product_visibility_term_ids();
		$exclude = [ (int) ( $terms['exclude-from-search'] ?? 0 ) ];

		if ( 'yes' === get_option( 'woocommerce_hide_out_of_stock_items' ) ) {
			$exclude[] = (int) ( $terms['outofstock'] ?? 0 );
		}

		return array_values( array_filter( $exclude ) );
	}
}

BBPS_Product_Search::init();
