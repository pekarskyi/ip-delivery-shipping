<?php
/**
 * Ajax Handler class.
 *
 * @package Delivery
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

/**
 * Delivery_Ajax_Handler Class.
 */
class Delivery_Ajax_Handler {
	/**
	 * Initialize the class and set its properties.
	 */
	public function __construct() {
		// Add AJAX handlers.
		add_action( 'wp_ajax_delivery_get_areas', array( $this, 'get_areas_callback' ) );
		add_action( 'wp_ajax_nopriv_delivery_get_areas', array( $this, 'get_areas_callback' ) );
		
		add_action( 'wp_ajax_delivery_get_cities', array( $this, 'get_cities_callback' ) );
		add_action( 'wp_ajax_nopriv_delivery_get_cities', array( $this, 'get_cities_callback' ) );
		
		add_action( 'wp_ajax_delivery_get_warehouses', array( $this, 'get_warehouses_callback' ) );
		add_action( 'wp_ajax_nopriv_delivery_get_warehouses', array( $this, 'get_warehouses_callback' ) );
	}

	/**
	 * Get areas callback.
	 */
	public function get_areas_callback() {
		// Перевіряємо, чи існує клас Delivery_API.
		if ( ! class_exists( 'Delivery_API' ) ) {
			wp_send_json_error( __( 'Delivery API class not found.', 'ip-delivery-shipping' ) );
			wp_die();
		}
		
		$delivery_api = new Delivery_API();
		$areas = $delivery_api->get_areas();
		
		if ( ! empty( $areas ) ) {
			wp_send_json_success( $areas );
		} else {
			wp_send_json_error( __( 'Failed to get regions list. Check API keys.', 'ip-delivery-shipping' ) );
		}
		wp_die();
	}

	/**
	 * Get cities callback.
	 */
	public function get_cities_callback() {
		// Перевіряємо, чи існує клас Delivery_API.
		if ( ! class_exists( 'Delivery_API' ) ) {
			wp_send_json_error( __( 'Delivery API class not found.', 'ip-delivery-shipping' ) );
			wp_die();
		}
		
		if ( ! isset( $_POST['area_id'] ) ) {
			wp_send_json_error( __( 'Region ID not specified', 'ip-delivery-shipping' ) );
			wp_die();
		}
		
		// Санітизація вхідних даних.
		$area_id = sanitize_text_field( $_POST['area_id'] );
		
		$delivery_api = new Delivery_API();
		$cities = $delivery_api->get_cities( $area_id );
		
		if ( ! empty( $cities ) ) {
			wp_send_json_success( $cities );
		} else {
			wp_send_json_error( __( 'Failed to get cities list', 'ip-delivery-shipping' ) );
		}
		wp_die();
	}

	/**
	 * Get warehouses callback.
	 */
	public function get_warehouses_callback() {
		// Перевіряємо, чи існує клас Delivery_API.
		if ( ! class_exists( 'Delivery_API' ) ) {
			wp_send_json_error( __( 'Delivery API class not found.', 'ip-delivery-shipping' ) );
			wp_die();
		}
		
		if ( ! isset( $_POST['city_id'] ) ) {
			wp_send_json_error( __( 'City ID not specified', 'ip-delivery-shipping' ) );
			wp_die();
		}
		
		// Санітизація вхідних даних.
		$city_id = sanitize_text_field( $_POST['city_id'] );
		
		$delivery_api = new Delivery_API();
		$warehouses = $delivery_api->get_warehouses( $city_id );
		
		if ( ! empty( $warehouses ) ) {
			wp_send_json_success( $warehouses );
		} else {
			wp_send_json_error( __( 'Failed to get warehouses list', 'ip-delivery-shipping' ) );
		}
		wp_die();
	}
} 