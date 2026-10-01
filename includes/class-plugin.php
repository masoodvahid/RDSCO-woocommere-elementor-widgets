<?php
/**
 * Bootstraps the Elementor integration: widget category, widgets and assets.
 */

namespace RDSCO\WEW;

defined( 'ABSPATH' ) || exit;

final class Plugin {

	public const ASSET_HANDLE   = 'rdsco-wew-search';
	public const WIDGET_GROUP   = 'rdsco-widgets';
	private const MIN_ELEMENTOR = '3.20.0';
	private const SEARCH_DELAY  = 500; // ms after the last keystroke.

	public static function init(): void {
		Search_API::init();
		add_action( 'plugins_loaded', [ self::class, 'boot' ], 20 );
	}

	public static function boot(): void {
		$missing = self::missing_requirements();
		if ( $missing ) {
			add_action(
				'admin_notices',
				static function () use ( $missing ): void {
					if ( ! current_user_can( 'activate_plugins' ) ) {
						return;
					}
					printf(
						'<div class="notice notice-warning"><p>%s</p></div>',
						esc_html(
							sprintf(
								/* translators: %s: list of required plugins. */
								__( 'RDSCO WooCommerce Elementor Widgets needs: %s.', 'rdsco-woocommerce-elementor-widgets' ),
								implode( ', ', $missing )
							)
						)
					);
				}
			);
			return;
		}

		add_action( 'wp_enqueue_scripts', [ self::class, 'register_assets' ], 1 );
		add_action( 'elementor/elements/categories_registered', [ self::class, 'register_category' ] );
		add_action( 'elementor/widgets/register', [ self::class, 'register_widgets' ] );
	}

	/**
	 * @return string[]
	 */
	private static function missing_requirements(): array {
		$missing = [];

		if ( ! class_exists( 'WooCommerce' ) ) {
			$missing[] = 'WooCommerce';
		}
		if ( ! did_action( 'elementor/loaded' ) || ! defined( 'ELEMENTOR_VERSION' ) ) {
			$missing[] = 'Elementor';
		} elseif ( version_compare( ELEMENTOR_VERSION, self::MIN_ELEMENTOR, '<' ) ) {
			$missing[] = 'Elementor ' . self::MIN_ELEMENTOR . '+';
		}

		return $missing;
	}

	/**
	 * Registered early; Elementor enqueues them only on pages that use the widgets.
	 */
	public static function register_assets(): void {
		wp_register_style(
			self::ASSET_HANDLE,
			RDSCO_WEW_URL . 'assets/css/rdsco-search.css',
			[],
			RDSCO_WEW_VERSION
		);

		wp_register_script(
			self::ASSET_HANDLE,
			RDSCO_WEW_URL . 'assets/js/rdsco-search.js',
			[],
			RDSCO_WEW_VERSION,
			[
				'in_footer' => true,
				'strategy'  => 'defer',
			]
		);

		$config = [
			'endpoint' => Search_API::endpoint(),
			'minChars' => Search_API::MIN_CHARS,
			'delay'    => self::SEARCH_DELAY,
			'i18n'     => [
				'loading'   => __( 'Searching…', 'rdsco-woocommerce-elementor-widgets' ),
				'none'      => __( 'No products found.', 'rdsco-woocommerce-elementor-widgets' ),
				'error'     => __( 'Search is unavailable right now. Please try again.', 'rdsco-woocommerce-elementor-widgets' ),
				'viewAll'   => __( 'View all results', 'rdsco-woocommerce-elementor-widgets' ),
				'sku'       => __( 'SKU', 'rdsco-woocommerce-elementor-widgets' ),
				'other'     => __( 'Other', 'rdsco-woocommerce-elementor-widgets' ),
				/* translators: %d: number of results. */
				'results'   => __( '%d results available.', 'rdsco-woocommerce-elementor-widgets' ),
				'allCats'   => __( 'All products', 'rdsco-woocommerce-elementor-widgets' ),
				/* translators: %d: number of selected categories. */
				'catsCount' => __( '%d selected', 'rdsco-woocommerce-elementor-widgets' ),
			],
		];

		// Logged-in users (editors, and everyone on a private/staging site) send
		// their session with a REST nonce. Visitors stay anonymous, so responses
		// remain cacheable and no nonce ends up in full-page caches.
		if ( is_user_logged_in() ) {
			$config['nonce'] = wp_create_nonce( 'wp_rest' );
			$config['debug'] = current_user_can( 'edit_theme_options' ); // Show the real error to site builders.
		}

		wp_add_inline_script( self::ASSET_HANDLE, 'window.rdscoSearchConfig = ' . wp_json_encode( $config ) . ';', 'before' );
	}

	/**
	 * @param \Elementor\Elements_Manager $elements_manager
	 */
	public static function register_category( $elements_manager ): void {
		$elements_manager->add_category(
			self::WIDGET_GROUP,
			[
				'title' => esc_html__( 'RDSCO Widgets', 'rdsco-woocommerce-elementor-widgets' ),
				'icon'  => 'eicon-woocommerce',
			]
		);
	}

	/**
	 * @param \Elementor\Widgets_Manager $widgets_manager
	 */
	public static function register_widgets( $widgets_manager ): void {
		require_once RDSCO_WEW_DIR . 'includes/widgets/trait-search-widget.php';
		require_once RDSCO_WEW_DIR . 'includes/widgets/class-live-search.php';
		require_once RDSCO_WEW_DIR . 'includes/widgets/class-category-search.php';

		$widgets_manager->register( new Widgets\Live_Search() );
		$widgets_manager->register( new Widgets\Category_Search() );
	}
}
