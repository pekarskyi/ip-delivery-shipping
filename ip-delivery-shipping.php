<?php
/**
 * Plugin Name: Delivery for WooCommerce
 * Description: Ukrainian delivery service "Delivery" for WooCommerce
 * Version: 1.0.1
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

$github_username = 'pekarskyi'; // Вказуємо ім'я користувача GitHub
$repo_name = 'ip-delivery-shipping'; // Вказуємо ім'я репозиторію GitHub, наприклад ip-wp-github-updater
$prefix = 'ip_delivery_shipping'; // Встановлюємо унікальний префікс плагіну, наприклад ip_wp_github_updater

// Ініціалізуємо систему оновлення плагіну з GitHub
if ( function_exists( 'ip_github_updater_load' ) ) {
    // Завантажуємо файл оновлювача з нашим префіксом
    ip_github_updater_load($prefix);
    
    // Формуємо назву функції оновлення з префіксу
    $updater_function = $prefix . '_github_updater_init';   
    
    // Після завантаження наша функція оновлення повинна бути доступна
    if ( function_exists( $updater_function ) ) {
        call_user_func(
            $updater_function,
            __FILE__,       // Plugin file path
            $github_username, // Your GitHub username
            '',              // Access token (empty)
            $repo_name       // Repository name (на основі префіксу)
        );
    }
}

// Підключаємо файл для керування полями адреси в чекауті
require_once plugin_dir_path( __FILE__ ) . 'includes/class-address-fields.php'; 