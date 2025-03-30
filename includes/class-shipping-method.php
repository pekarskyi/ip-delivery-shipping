<?php
/**
 * Shipping Method class.
 *
 * @package Delivery
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

/**
 * Delivery_Shipping_Method Class.
 */
class Delivery_Shipping_Method extends WC_Shipping_Method {
	/**
	 * Constructor for your shipping class
	 *
	 * @access public
	 * @return void
	 */
	public function __construct() {
		$this->id                 = 'delivery'; 
		$this->method_title       = __( 'Delivery Service', 'ip-delivery-shipping' );  
		$this->method_description = __( 'Delivery shipping method', 'ip-delivery-shipping' ); 

		// Availability & Countries
		$this->availability = 'including';
		$this->countries = array( 'UA' );

		$this->init();

		$this->enabled = isset( $this->settings['enabled'] ) ? $this->settings['enabled'] : 'yes';
		
		// Перевіряємо, щоб поле title не було порожнім
		$default_title = __( 'Delivery Service', 'ip-delivery-shipping' );
		$this->title = isset( $this->settings['title'] ) && ! empty( trim( $this->settings['title'] ) ) 
						? $this->settings['title'] 
						: $default_title;
						
		$this->public_key = isset( $this->settings['public_key'] ) ? $this->settings['public_key'] : '';
		$this->secret_key = isset( $this->settings['secret_key'] ) ? $this->settings['secret_key'] : '';
	}

	/**
	 * Init your settings
	 *
	 * @access public
	 * @return void
	 */
	public function init() {
		// Load the settings API
		$this->init_form_fields(); 
		$this->init_settings(); 

		// Save settings in admin if you have any defined
		add_action( 'woocommerce_update_options_shipping_' . $this->id, array( $this, 'process_admin_options' ) );
	}

	/**
	 * Define settings field for this shipping
	 * 
	 * @return void 
	 */
	public function init_form_fields() { 
		$this->form_fields = array(
			'enabled' => array(
				'title' => __( 'Enable', 'ip-delivery-shipping' ),
				'type' => 'checkbox',
				'description' => __( 'Enable this shipping method.', 'ip-delivery-shipping' ),
				'default' => 'yes'
			),

			'title' => array(
				'title' => __( 'Title', 'ip-delivery-shipping' ),
				'type' => 'text',
				'description' => __( 'Title to display on site', 'ip-delivery-shipping' ),
				'default' => __( 'Delivery Service', 'ip-delivery-shipping' )
			),
			 
			'base_cost' => array(
				'title' => __( 'Base Cost', 'ip-delivery-shipping' ),
				'type' => 'number',
				'description' => __( 'Base cost for delivery. Can be modified by filters.', 'ip-delivery-shipping' ),
				'default' => '0',
				'custom_attributes' => array(
					'min' => '0',
					'step' => '0.01'
				)
			),
			 
			'public_key' => array(
				'title' => __( 'API Public Key', 'ip-delivery-shipping' ),
				'type' => 'text',
				'description' => __( 'Public key for Delivery API access', 'ip-delivery-shipping' ),
				'default' => ''
			),
			 
			'secret_key' => array(
				'title' => __( 'API Secret Key', 'ip-delivery-shipping' ),
				'type' => 'password',
				'description' => __( 'Secret key for Delivery API access', 'ip-delivery-shipping' ),
				'default' => ''
			),
				  
			'api_key_link' => array(
				'title' => __( 'Get API Keys', 'ip-delivery-shipping' ),
				'type' => 'title',
				'description' => sprintf(
					__( 'To get API keys, please visit <a href="%s" target="_blank">Delivery API Key page</a>', 'ip-delivery-shipping' ),
					esc_url( 'https://www.delivery-auto.com/uk/Account/ApiKey' )
				),
			),
			 
			'cache_options' => array(
				'title' => __( 'Cache Options', 'ip-delivery-shipping' ),
				'type' => 'title',
				'description' => __( 'The plugin caches API responses for better performance. Cache lifetime: 24 hours.', 'ip-delivery-shipping' ) . 
				'&nbsp;&nbsp;&nbsp;<a href="' . esc_url( admin_url( 'admin.php?page=wc-settings&tab=shipping&section=delivery&clear_cache=1&_wpnonce=' . wp_create_nonce( 'delivery_clear_cache' ) ) ) . '" class="button-secondary" id="delivery-clear-cache-link">' . esc_html__( 'Clear Cache', 'ip-delivery-shipping' ) . '</a>',
			),
		);
	}

	/**
	 * This function is used to calculate the shipping cost. Within this function we can check for weights, dimensions and other parameters.
	 *
	 * @access public
	 * @param mixed $package Package data.
	 * @return void
	 */
	public function calculate_shipping( $package = array() ) {
		// Get base cost from settings
		$base_cost = $this->get_option( 'base_cost', 0 );
		
		// Prepare data for the filter
		$delivery_data = array(
			'package' => $package,
			'base_cost' => $base_cost,
			'instance_id' => $this->id,
			'region' => isset( $_POST['delivery_delivery_name'] ) ? sanitize_text_field( $_POST['delivery_delivery_name'] ) : '',
			'city' => isset( $_POST['delivery_city_name'] ) ? sanitize_text_field( $_POST['delivery_city_name'] ) : '',
			'warehouse' => isset( $_POST['delivery_warehouses_name'] ) ? sanitize_text_field( $_POST['delivery_warehouses_name'] ) : '',
		);
		
		// Apply filter for delivery cost
		$cost = apply_filters( 'woocommerce_delivery_calculate_shipping_cost', $base_cost, $delivery_data );

		$rate = array(
			'id' => $this->id,
			'label' => $this->title,
			'cost' => $cost
		);

		$this->add_rate( $rate );
	}
} 