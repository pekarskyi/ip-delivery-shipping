<?php
/**
 * Uninstall file for Delivery plugin.
 *
 * @package Delivery
 */

// If uninstall not called from WordPress, exit
if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

// Load main file to get constants
include_once 'delivery.php';

global $wpdb;

// Перевіряємо ключ delete_data в новій таблиці налаштувань
$should_delete_data = false;
$settings_table = $wpdb->prefix . 'ip_delivery_settings';

if($wpdb->get_var("SHOW TABLES LIKE '$settings_table'") === $settings_table) {
	$delete_data_setting = $wpdb->get_var("SELECT setting_value FROM $settings_table WHERE setting_key = 'delete_data'");
	$should_delete_data = ($delete_data_setting === 'yes');
}

// Якщо в новій таблиці налаштування не знайдено, перевіряємо стару опцію
if(!$should_delete_data) {
	$shipping_options = get_option( 'woocommerce_delivery_settings' );
	$should_delete_data = isset( $shipping_options['delete_data'] ) && 'yes' === $shipping_options['delete_data'];
}

// Only delete data if the option is enabled
if ( $should_delete_data ) {
	// Підключаємо клас бази даних, щоб використати його функції
	require_once dirname( __FILE__ ) . '/includes/class-db.php';
	$db = new Delivery_DB();
	
	// Видаляємо таблицю плагіна
	$db->drop_table();
	
	// Видаляємо таблицю налаштувань плагіна
	$settings_table = $wpdb->prefix . 'ip_delivery_settings';
	$wpdb->query( "DROP TABLE IF EXISTS {$settings_table}" );
	
	// Delete plugin settings
	delete_option( 'woocommerce_delivery_settings' );
	delete_option( 'delivery_db_version' );
	
	// Delete all transients/cache data
	$wpdb->query( "DELETE FROM {$wpdb->options} WHERE option_name LIKE '_transient_delivery_%'" );
	$wpdb->query( "DELETE FROM {$wpdb->options} WHERE option_name LIKE '_transient_timeout_delivery_%'" );
	
	// Delete order meta data related to delivery (для зворотної сумісності)
	$wpdb->query( "DELETE FROM {$wpdb->postmeta} WHERE meta_key LIKE 'delivery_%'" );
	$wpdb->query( "DELETE FROM {$wpdb->postmeta} WHERE meta_key = 'delivery'" );
	$wpdb->query( "DELETE FROM {$wpdb->postmeta} WHERE meta_key = 'city'" );
	$wpdb->query( "DELETE FROM {$wpdb->postmeta} WHERE meta_key = 'warehouses'" );
} 