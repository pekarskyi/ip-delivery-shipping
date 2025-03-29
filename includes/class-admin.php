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
	}

	/**
	 * Add links to plugin action links.
	 *
	 * @param array $links Plugin action links.
	 * @return array
	 */
	public function add_plugin_action_links( $links ) {
		$action_links = array(
			'settings' => '<a href="' . esc_url( admin_url( 'admin.php?page=wc-settings&tab=shipping&section=delivery' ) ) . '">' . esc_html__( 'Settings', 'ip-delivery-shipping' ) . '</a>'
		);
		
		return array_merge( $action_links, $links );
	}

	/**
	 * Process cache clearing.
	 */
	public function process_cache_clear() {
		// Перевіряємо, чи ми на сторінці налаштувань Delivery і чи запитане очищення кешу.
		if ( isset( $_GET['page'] ) && sanitize_text_field( $_GET['page'] ) === 'wc-settings' && 
			isset( $_GET['tab'] ) && sanitize_text_field( $_GET['tab'] ) === 'shipping' && 
			isset( $_GET['section'] ) && sanitize_text_field( $_GET['section'] ) === 'delivery' && 
			isset( $_GET['clear_cache'] ) && sanitize_text_field( $_GET['clear_cache'] ) == '1' && 
			isset( $_GET['_wpnonce'] ) && wp_verify_nonce( sanitize_text_field( $_GET['_wpnonce'] ), 'delivery_clear_cache' ) ) {
			
			global $wpdb;
			
			// Видаляємо весь кеш, пов'язаний з Delivery.
			$wpdb->query( "DELETE FROM $wpdb->options WHERE option_name LIKE '_transient_delivery_%'" );
			$wpdb->query( "DELETE FROM $wpdb->options WHERE option_name LIKE '_transient_timeout_delivery_%'" );
			
			// Перенаправляємо назад на сторінку налаштувань без параметрів очищення кешу.
			wp_redirect( admin_url( 'admin.php?page=wc-settings&tab=shipping&section=delivery&cache_cleared=1' ) );
			exit;
		}
		
		// Показуємо повідомлення після очищення кешу.
		if ( isset( $_GET['page'] ) && sanitize_text_field( $_GET['page'] ) === 'wc-settings' && 
			isset( $_GET['tab'] ) && sanitize_text_field( $_GET['tab'] ) === 'shipping' && 
			isset( $_GET['section'] ) && sanitize_text_field( $_GET['section'] ) === 'delivery' && 
			isset( $_GET['cache_cleared'] ) && sanitize_text_field( $_GET['cache_cleared'] ) == '1' ) {
			add_action( 'admin_notices', array( $this, 'cache_cleared_notice' ) );
		}
	}

	/**
	 * Display cache cleared notice.
	 */
	public function cache_cleared_notice() {
		?>
		<div class="notice notice-success is-dismissible">
			<p><?php echo esc_html__( 'Delivery API cache has been cleared successfully.', 'ip-delivery-shipping' ); ?></p>
		</div>
		<?php
	}
} 