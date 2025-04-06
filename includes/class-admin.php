<?php
/**
 * Admin class.
 *
 * @package Delivery
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

/**
 * Delivery_Admin Class.
 */
class Delivery_Admin {
	/**
	 * Initialize the class and set its properties.
	 */
	public function __construct() {
		// Add plugin action links.
		$plugin = plugin_basename( DELIVERY_PLUGIN_FILE );
		add_filter( "plugin_action_links_{$plugin}", array( $this, 'add_plugin_action_links' ) );
		
		// Process cache clear.
		add_action( 'admin_init', array( $this, 'process_cache_clear' ) );
		
		// Підключаємо стилі для адмін-панелі
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_admin_styles' ) );
	}

	/**
	 * Add links to plugin action links.
	 *
	 * @param array $links Plugin action links.
	 * @return array
	 */
	public function add_plugin_action_links( $links ) {
		$action_links = array(
			'settings' => '<a href="' . esc_url( admin_url( 'admin.php?page=ip-delivery-settings' ) ) . '">' . esc_html__( 'Settings', 'ip-delivery-shipping' ) . '</a>'
		);
		
		return array_merge( $action_links, $links );
	}

	/**
	 * Підключення стилів для адмін-панелі.
	 */
	public function enqueue_admin_styles() {
		$screen = get_current_screen();
		
		// Підключаємо стилі на сторінці налаштувань плагіна та на сторінці налаштувань в WooCommerce
		if ( 
			(isset( $screen->id ) && $screen->id === 'toplevel_page_ip-delivery-settings') ||
			(isset( $screen->id ) && $screen->id === 'woocommerce_page_wc-settings' && 
			isset( $_GET['tab'] ) && sanitize_text_field( $_GET['tab'] ) === 'shipping' && 
			isset( $_GET['section'] ) && sanitize_text_field( $_GET['section'] ) === 'ip-delivery')
		) {
			wp_enqueue_style(
				'ip-delivery-admin',
				plugins_url( 'assets/css/ip-delivery-admin.css', DELIVERY_PLUGIN_FILE ),
				array(),
				filemtime( plugin_dir_path( DELIVERY_PLUGIN_FILE ) . 'assets/css/ip-delivery-admin.css' )
			);
		}
	}

	/**
	 * Process cache clearing.
	 */
	public function process_cache_clear() {
		// Перевіряємо, чи ми на сторінці налаштувань Delivery і чи запитане очищення кешу.
		if ( isset( $_GET['page'] ) && sanitize_text_field( $_GET['page'] ) === 'ip-delivery-settings' && 
			isset( $_GET['clear_cache'] ) && sanitize_text_field( $_GET['clear_cache'] ) == '1' && 
			isset( $_GET['_wpnonce'] ) && wp_verify_nonce( sanitize_text_field( $_GET['_wpnonce'] ), 'delivery_clear_cache' ) ) {
			
			global $wpdb;
			
			// Видаляємо весь кеш, пов'язаний з Delivery.
			$wpdb->query( "DELETE FROM $wpdb->options WHERE option_name LIKE '_transient_delivery_%'" );
			$wpdb->query( "DELETE FROM $wpdb->options WHERE option_name LIKE '_transient_timeout_delivery_%'" );
			
			// Перенаправляємо назад на сторінку налаштувань з повідомленням
			wp_redirect( add_query_arg(
				array(
					'page' => 'ip-delivery-settings',
					'delivery_notice' => urlencode(__('Delivery API cache has been cleared successfully.', 'ip-delivery-shipping')),
					'delivery_notice_type' => 'success'
				),
				admin_url('admin.php')
			));
			exit;
		}
	}
} 