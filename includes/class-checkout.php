<?php
/**
 * Checkout class.
 *
 * @package Delivery
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

/**
 * Delivery_Checkout Class.
 */
class Delivery_Checkout {
	/**
	 * Initialize the class and set its properties.
	 */
	public function __construct() {
		// Перевіряємо чи WooCommerce активний. Якщо ні - не додаємо хуки.
		if ( ! function_exists( 'WC' ) ) {
			return;
		}

		// Add frontend hooks.
		add_action( 'woocommerce_review_order_before_cart_contents', array( $this, 'validate_order' ), 10 );
		add_action( 'woocommerce_after_checkout_validation', array( $this, 'validate_order' ), 10 );
		add_action( 'woocommerce_checkout_process', array( $this, 'checkout_process' ) );
		add_action( 'woocommerce_after_checkout_billing_form', array( $this, 'checkout_field' ) );
		add_action( 'woocommerce_checkout_update_order_meta', array( $this, 'save_field' ), 25 );
		add_action( 'woocommerce_admin_order_data_after_shipping_address', array( $this, 'print_field_value' ), 25 );
		add_filter( 'woocommerce_get_order_item_totals', array( $this, 'field_in_email' ), 25, 2 );
	}

	/**
	 * Validate order.
	 *
	 * @param array $posted Posted data.
	 */
	public function validate_order( $posted ) {
		// Перевіряємо чи WooCommerce активний.
		if ( ! function_exists( 'WC' ) ) {
			return;
		}

		$packages = WC()->shipping->get_packages();
		$chosen_methods = WC()->session->get( 'chosen_shipping_methods' );
		 
		if ( is_array( $chosen_methods ) && in_array( 'delivery', $chosen_methods, true ) ) { 
			$settings = get_option( 'woocommerce_delivery_settings' );
			$public_key = isset( $settings['public_key'] ) ? sanitize_text_field( $settings['public_key'] ) : '';
			?>
			<script>
				jQuery(document).ready(function() {
					jQuery('#city').prop('disabled', true);
					jQuery('#warehouses').prop('disabled', true);
					
					// Get regions
					jQuery.ajax({
						url: '<?php echo admin_url( 'admin-ajax.php' ); ?>',
						type: 'POST',
						data: {
							action: 'delivery_get_areas',
						},
						success: function(response) {
							if (response.success) {
								let areas = response.data;
								jQuery('#delivery').find('option').remove();
								jQuery('#delivery').append(jQuery("<option></option>", {value: 0, text: '<?php echo esc_js( __( "Select Region", "ip-delivery-shipping" ) ); ?>'}));
								
								// Check different data formats
								let areasList = [];
								
								if (areas && typeof areas === 'object') {
									// Check data in different fields
									if (areas.data && Array.isArray(areas.data)) {
										areasList = areas.data;
									} else if (areas.Data && Array.isArray(areas.Data)) {
										areasList = areas.Data;
									} else if (Array.isArray(areas)) {
										areasList = areas;
									}
									
									// If regions array found
									if (areasList.length > 0) {
										for (let i = 0; i < areasList.length; i++) {
											const area = areasList[i];
											const id = area.Id || area.id || area.ID;
											const name = area.Name || area.name || area.NAME || area.Description || area.description;
											
											if (id && name) {
												jQuery('#delivery').append(jQuery("<option></option>", {
													value: id, 
													text: name
												}));
											}
										}
									} else {
										alert('<?php echo esc_js( __( "No regions found. Check plugin settings.", "ip-delivery-shipping" ) ); ?>');
									}
								} else {
									alert('<?php echo esc_js( __( "Invalid regions data format. Check plugin settings.", "ip-delivery-shipping" ) ); ?>');
								}
							} else {
								alert('<?php echo esc_js( __( "Error loading regions. Check plugin settings and API keys.", "ip-delivery-shipping" ) ); ?>');
							}
						},
						error: function(xhr, status, error) {
							alert('<?php echo esc_js( __( "Server connection error when loading regions.", "ip-delivery-shipping" ) ); ?>');
						}
					});
				});
				
				jQuery('#delivery').on('change', function() {
					if (this.value == 0) return;
					
					// Get cities by region
					jQuery.ajax({
						url: '<?php echo admin_url( 'admin-ajax.php' ); ?>',
						type: 'POST',
						data: {
							action: 'delivery_get_cities',
							area_id: this.value
						},
						success: function(response) {
							if (response.success) {
								let cities = response.data;
								jQuery('#city').prop('disabled', false);
								jQuery('#city').find('option').remove();
								jQuery('input[name="delivery_delivery_name"]').val(jQuery('#delivery').find('option:selected').text());
								jQuery('#city').append(jQuery("<option></option>", {value: 0, text: '<?php echo esc_js( __( "Select City", "ip-delivery-shipping" ) ); ?>'}));
								
								if (Array.isArray(cities)) {
									for (let i = 0; i < cities.length; i++) {
										jQuery('#city').append(jQuery("<option></option>", {
											value: cities[i].id || cities[i].Id || cities[i].ID, 
											text: cities[i].name || cities[i].Name || cities[i].NAME
										}));
									}
								} else if (cities && cities.data && Array.isArray(cities.data)) {
									for (let i = 0; i < cities.data.length; i++) {
										jQuery('#city').append(jQuery("<option></option>", {
											value: cities.data[i].id || cities.data[i].Id || cities.data[i].ID, 
											text: cities.data[i].name || cities.data[i].Name || cities.data[i].NAME
										}));
									}
								}
							}
						}
					});
				});
				
				jQuery('#city').on('change', function() {
					if (this.value == 0) return;
					
					// Get warehouses by city
					jQuery.ajax({
						url: '<?php echo admin_url( 'admin-ajax.php' ); ?>',
						type: 'POST',
						data: {
							action: 'delivery_get_warehouses',
							city_id: this.value
						},
						success: function(response) {
							if (response.success) {
								let warehouses = response.data;
								jQuery('#warehouses').prop('disabled', false);
								jQuery('#warehouses').find('option').remove();
								jQuery('input[name="delivery_city_name"]').val(jQuery('#city').find('option:selected').text());
								jQuery('#warehouses').append(jQuery("<option></option>", {value: 0, text: '<?php echo esc_js( __( "Select Warehouse", "ip-delivery-shipping" ) ); ?>'}));
								
								if (Array.isArray(warehouses)) {
									for (let i = 0; i < warehouses.length; i++) {
										let address = warehouses[i].address || warehouses[i].Address || warehouses[i].ADDRESS || '';
										let name = warehouses[i].name || warehouses[i].Name || warehouses[i].NAME || '';
										jQuery('#warehouses').append(jQuery("<option></option>", {
											value: warehouses[i].id || warehouses[i].Id || warehouses[i].ID, 
											text: name + ' (' + address + ')'
										}));
									}
								} else if (warehouses && warehouses.data && Array.isArray(warehouses.data)) {
									for (let i = 0; i < warehouses.data.length; i++) {
										let address = warehouses.data[i].address || warehouses.data[i].Address || warehouses.data[i].ADDRESS || '';
										let name = warehouses.data[i].name || warehouses.data[i].Name || warehouses.data[i].NAME || '';
										jQuery('#warehouses').append(jQuery("<option></option>", {
											value: warehouses.data[i].id || warehouses.data[i].Id || warehouses.data[i].ID, 
											text: name + ' (' + address + ')'
										}));
									}
								}
							}
						}
					});
				});
				
				jQuery('#warehouses').on('change', function() {
					jQuery('input[name="delivery_warehouses_name"]').val(jQuery(this).find('option:selected').text());
				});
				
				// Додаємо валідацію перед відправкою форми
				jQuery(document).on('checkout_place_order', function() {
					if(jQuery('input[name="shipping_method[0]"]:checked').val() === 'delivery') {
						var region = jQuery('#delivery').val();
						var city = jQuery('#city').val();
						var warehouse = jQuery('#warehouses').val();
						
						if(region == '0' || region == undefined) {
							alert('<?php echo esc_js( __( "Please select a region for Delivery shipping", "ip-delivery-shipping" ) ); ?>');
							jQuery('#delivery').focus();
							return false;
						}
						
						if(city == '0' || city == undefined) {
							alert('<?php echo esc_js( __( "Please select a city for Delivery shipping", "ip-delivery-shipping" ) ); ?>');
							jQuery('#city').focus();
							return false;
						}
						
						if(warehouse == '0' || warehouse == undefined) {
							alert('<?php echo esc_js( __( "Please select a warehouse for Delivery shipping", "ip-delivery-shipping" ) ); ?>');
							jQuery('#warehouses').focus();
							return false;
						}
					}
				});
			</script>
			<style>#delivery_checkout_field{display:block !important}</style>
			<input type="hidden" name="delivery_delivery_name" value="">
			<input type="hidden" name="delivery_city_name" value="">
			<input type="hidden" name="delivery_warehouses_name" value="">
			<?php 
		} 
	}

	/**
	 * Validate checkout process.
	 */
	public function checkout_process() {
		// Перевіряємо чи WooCommerce активний.
		if ( ! function_exists( 'WC' ) ) {
			return;
		}

		$chosen_methods = WC()->session->get( 'chosen_shipping_methods' );
		
		// Перевіряємо, чи вибрано метод доставки Delivery.
		if ( is_array( $chosen_methods ) && in_array( 'delivery', $chosen_methods, true ) ) {
			// Перевіряємо, чи заповнені обов'язкові поля.
			if ( empty( $_POST['delivery_delivery_name'] ) || $_POST['delivery'] == '0' ) {
				wc_add_notice( __( 'Please select a region for Delivery shipping', 'ip-delivery-shipping' ), 'error' );
			}
			
			if ( empty( $_POST['delivery_city_name'] ) || $_POST['city'] == '0' ) {
				wc_add_notice( __( 'Please select a city for Delivery shipping', 'ip-delivery-shipping' ), 'error' );
			}
			
			if ( empty( $_POST['delivery_warehouses_name'] ) || $_POST['warehouses'] == '0' ) {
				wc_add_notice( __( 'Please select a warehouse for Delivery shipping', 'ip-delivery-shipping' ), 'error' );
			}
		}
	}

	/**
	 * Display delivery fields in admin order page.
	 *
	 * @param WC_Order $order Order object.
	 */
	public function print_field_value( $order ) {
		if ( $method = $order->get_meta( 'delivery', true ) ) {
			echo '<p><strong>' . esc_html__( 'Region', 'ip-delivery-shipping' ) . ':</strong><br>' . esc_html( $method ) . '</p>';
		}
		if ( $method = $order->get_meta( 'city', true ) ) {
			echo '<p><strong>' . esc_html__( 'City', 'ip-delivery-shipping' ) . ':</strong><br>' . esc_html( $method ) . '</p>';
		}
		if ( $method = $order->get_meta( 'warehouses', true ) ) {
			echo '<p><strong>' . esc_html__( 'Delivery Warehouse', 'ip-delivery-shipping' ) . ':</strong><br>' . esc_html( $method ) . '</p>';
		}
	}

	/**
	 * Add delivery fields to order email.
	 *
	 * @param array    $rows Order fields.
	 * @param WC_Order $order Order object.
	 * @return array
	 */
	public function field_in_email( $rows, $order ) {
		if ( is_order_received_page() ) {
			return $rows;
		}
	 
		$rows['billing_delivery'] = array(
			'label' => esc_html__( 'Region', 'ip-delivery-shipping' ),
			'value' => esc_html( $order->get_meta( 'delivery', true ) )
		);
		$rows['billing_city'] = array(
			'label' => esc_html__( 'City', 'ip-delivery-shipping' ),
			'value' => esc_html( $order->get_meta( 'city', true ) )
		);
		$rows['billing_warehouses'] = array(
			'label' => esc_html__( 'Delivery Warehouse', 'ip-delivery-shipping' ),
			'value' => esc_html( $order->get_meta( 'warehouses', true ) )
		);
	 
		return $rows;
	}

	/**
	 * Save delivery fields to order meta.
	 *
	 * @param int $order_id Order ID.
	 */
	public function save_field( $order_id ) {
		$order = wc_get_order( $order_id );
	 
		if ( ! empty( $_POST['delivery_delivery_name'] ) ) {
			$order->update_meta_data( 'delivery', sanitize_text_field( $_POST['delivery_delivery_name'] ) );
		}
		if ( ! empty( $_POST['delivery_city_name'] ) ) {
			$order->update_meta_data( 'city', sanitize_text_field( $_POST['delivery_city_name'] ) );
		}
		if ( ! empty( $_POST['delivery_warehouses_name'] ) ) {
			$order->update_meta_data( 'warehouses', sanitize_text_field( $_POST['delivery_warehouses_name'] ) );
		}
		
		$order->save();
	}

	/**
	 * Add checkout fields.
	 *
	 * @param WC_Checkout $checkout Checkout object.
	 */
	public function checkout_field( $checkout ) {
		// Перевіряємо чи WooCommerce активний.
		if ( ! function_exists( 'WC' ) ) {
			return;
		}

		// Отримуємо заголовок з налаштувань.
		$settings = get_option( 'woocommerce_delivery_settings' );
		$default_title = __( 'Delivery Service', 'ip-delivery-shipping' );
		$title = isset( $settings['title'] ) && ! empty( trim( $settings['title'] ) ) 
				? $settings['title'] 
				: $default_title;
		
		echo '<div id="delivery_checkout_field" style="display: none;"><h2>' . esc_html( $title ) . '</h2>';
	
		woocommerce_form_field( 'delivery', array(
			'type' => 'select',
			'class' => array( 'delivery_region form-row-wide' ),
			'label' => __( 'Select Region', 'ip-delivery-shipping' ),
			'placeholder' => __( 'Select Region', 'ip-delivery-shipping' ),
			'required' => true,
			'options' => array(
				'0' => __( 'Select Region', 'ip-delivery-shipping' ),
			)
		), $checkout->get_value( 'delivery' ) );
		
		
		woocommerce_form_field( 'city', array(
			'type' => 'select',
			'class' => array( 'delivery_city form-row-wide' ),
			'label' => __( 'Select City', 'ip-delivery-shipping' ),
			'placeholder' => __( 'Select City', 'ip-delivery-shipping' ),
			'required' => true,
			'options' => array(
				'0' => __( 'Select City', 'ip-delivery-shipping' ),
			)
		), $checkout->get_value( 'city' ) );

		woocommerce_form_field( 'warehouses', array(
			'type' => 'select',
			'class' => array( 'delivery_warehouses form-row-wide' ),
			'label' => __( 'Select Warehouse', 'ip-delivery-shipping' ),
			'placeholder' => __( 'Select Warehouse', 'ip-delivery-shipping' ),
			'required' => true,
			'options' => array(
				'0' => __( 'Select Warehouse', 'ip-delivery-shipping' ),
			)
		), $checkout->get_value( 'warehouses' ) );
	
		echo '</div>';
	}
} 