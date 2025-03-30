<?php
/**
 * Plugin Name: Delivery for WooCommerce
 * Description: Ukrainian delivery service "Delivery" for WooCommerce
 * Version: 1.0.0
 * Author: InwebPress
 * Plugin URI: https://github.com/pekarskyi/ip-delivery-shipping
 * Author URI: https://inwebpress.com
 * Text Domain: ip-delivery-shipping
 * License URI: license.txt
 * Requires PHP: 7.4
 * Tested up to: 9.6.0
 * WC tested up to: 6.7.2
 * Domain Path: /lang
 * WC requires at least: 3.0
 * WooCommerce: true
*/

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

// Define DELIVERY_PLUGIN_FILE.
if ( ! defined( 'DELIVERY_PLUGIN_FILE' ) ) {
	define( 'DELIVERY_PLUGIN_FILE', __FILE__ );
}

// Include the main Delivery class.
if ( ! class_exists( 'Delivery_Plugin' ) ) {
	include_once dirname( __FILE__ ) . '/includes/class-plugin.php';
}

/**
 * Main instance of Delivery.
 *
 * Returns the main instance of Delivery to prevent the need to use globals.
 *
 * @since  1.0.0
 * @return Delivery_Plugin
 */
function Delivery() {
	return Delivery_Plugin::instance();
}

// Global for backwards compatibility.
$GLOBALS['delivery'] = Delivery();

// Adding update check via GitHub
require_once plugin_dir_path( __FILE__ ) . 'updates/github-updater.php';
if ( function_exists( 'ip_woo_cleaner_github_updater_init' ) ) {
    ip_woo_cleaner_github_updater_init(
        __FILE__,       // Plugin file path
        'pekarskyi',     // Your GitHub username
        '',              // Access token (empty)
        'ip-woo-cleaner' // Repository name (optional)
        // Other parameters are determined automatically
    );
} 