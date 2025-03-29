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
		
		// Показуємо повідомлення про очищення кешу
		add_action( 'admin_init', array( $this, 'check_cache_cleared' ) );
	}

	/**
	 * Add links to plugin action links.
	 *
	 * @param array $links Plugin action links.
	 * @return array
	 */
	public function add_plugin_action_links( $links ) {
		$action_links = array(
			'settings' => '<a href="' . esc_url( admin_url( 'admin.php?page=wc-settings&tab=shipping&section=ip_delivery' ) ) . '">' . esc_html__( 'Settings', 'ip-delivery-shipping' ) . '</a>'
		);
		
		return array_merge( $action_links, $links );
	}

	/**
	 * Check if cache was cleared and show message
	 */
	public function check_cache_cleared() {
		if ( isset( $_GET['delivery_cache_cleared'] ) && $_GET['delivery_cache_cleared'] === 'yes' ) {
			add_action( 'admin_notices', function() {
				echo '<div class="notice notice-success is-dismissible"><p>' 
					. esc_html__( 'Delivery API cache has been cleared successfully.', 'ip-delivery-shipping' ) 
					. '</p></div>';
			});
		}
	}
} 