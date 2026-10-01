<?php
/**
 * Plugin Name:       RDSCO WooCommerce Elementor Widgets
 * Plugin URI:        https://github.com/masoodvahid/RDSCO-woocommere-elementor-widgets
 * Description:       Elementor widgets for WooCommerce: live product search (name, SKU, description) and a category search popup.
 * Version:           1.0.0
 * Requires at least: 6.5
 * Requires PHP:      8.1
 * Requires Plugins:  woocommerce, elementor
 * License:           GPL-2.0-or-later
 * Text Domain:       rdsco-woocommerce-elementor-widgets
 * Update URI:        https://github.com/masoodvahid/RDSCO-woocommere-elementor-widgets
 */

defined( 'ABSPATH' ) || exit;

define( 'RDSCO_WEW_VERSION', '1.0.0' );
define( 'RDSCO_WEW_FILE', __FILE__ );
define( 'RDSCO_WEW_DIR', plugin_dir_path( __FILE__ ) );
define( 'RDSCO_WEW_URL', plugin_dir_url( __FILE__ ) );

require_once RDSCO_WEW_DIR . 'includes/class-github-updater.php';
require_once RDSCO_WEW_DIR . 'includes/class-search-api.php';
require_once RDSCO_WEW_DIR . 'includes/class-plugin.php';

// Updates from GitHub Releases (wp-admin → Plugins). Always loaded: update
// checks and upgrades also run from cron and REST, not only in wp-admin.
( new RDSCO\WEW\GitHub_Updater(
	RDSCO_WEW_FILE,
	'masoodvahid',
	'RDSCO-woocommere-elementor-widgets',
	'rdsco-woocommerce-elementor-widgets.zip'
) )->register();

RDSCO\WEW\Plugin::init();
