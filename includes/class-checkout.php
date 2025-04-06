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
		
		// Підключаємо CSS файл для всіх сторінок оформлення замовлення
		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_scripts' ) );

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
			// Отримуємо публічний ключ із таблиці БД
			$public_key = $this->get_setting('public_key');
			
			// Підключаємо JS файл з обробниками
			wp_enqueue_script(
				'ip-delivery-checkout',
				plugins_url( 'assets/js/ip-delivery-checkout.js', DELIVERY_PLUGIN_FILE ),
				array( 'jquery' ),
				filemtime( plugin_dir_path( DELIVERY_PLUGIN_FILE ) . 'assets/js/ip-delivery-checkout.js' ),
				true
			);
			
			// Передаємо змінні до JavaScript
			wp_localize_script(
				'ip-delivery-checkout',
				'ipDelivery',
				array(
					'ajaxUrl' => admin_url( 'admin-ajax.php' ),
					'translations' => array(
						'selectRegion' => __( 'Select Region', 'ip-delivery-shipping' ),
						'selectCity' => __( 'Select City', 'ip-delivery-shipping' ),
						'selectWarehouse' => __( 'Select Warehouse', 'ip-delivery-shipping' ),
						'noRegions' => __( 'No regions found. Check plugin settings.', 'ip-delivery-shipping' ),
						'invalidData' => __( 'Invalid regions data format. Check plugin settings.', 'ip-delivery-shipping' ),
						'errorLoadingRegions' => __( 'Error loading regions. Check plugin settings and API keys.', 'ip-delivery-shipping' ),
						'serverError' => __( 'Server connection error when loading regions.', 'ip-delivery-shipping' ),
						'pleaseSelectRegion' => __( 'Please select a region for Delivery shipping', 'ip-delivery-shipping' ),
						'pleaseSelectCity' => __( 'Please select a city for Delivery shipping', 'ip-delivery-shipping' ),
						'pleaseSelectWarehouse' => __( 'Please select a warehouse for Delivery shipping', 'ip-delivery-shipping' ),
					)
				)
			);
			?>
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
	 * Print delivery field values on admin order page.
	 *
	 * @param WC_Order $order Order object.
	 */
	public function print_field_value( $order ) {
		// Отримуємо дані з нашої таблиці
		$delivery_data = Delivery()->db->get_delivery_data( $order->get_id() );

		if ( $delivery_data ) {
			if ( ! empty( $delivery_data['region_name'] ) ) {
				echo '<p><strong>' . esc_html__( 'Region', 'ip-delivery-shipping' ) . ':</strong><br>' . esc_html( $delivery_data['region_name'] ) . '</p>';
			}
			if ( ! empty( $delivery_data['city_name'] ) ) {
				echo '<p><strong>' . esc_html__( 'City', 'ip-delivery-shipping' ) . ':</strong><br>' . esc_html( $delivery_data['city_name'] ) . '</p>';
			}
			if ( ! empty( $delivery_data['warehouse_name'] ) ) {
				echo '<p><strong>' . esc_html__( 'Delivery Warehouse', 'ip-delivery-shipping' ) . ':</strong><br>' . esc_html( $delivery_data['warehouse_name'] ) . '</p>';
			}
		} else {
			// Для сумісності з попередніми версіями перевіряємо метадані
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
	 
		// Отримуємо дані з нашої таблиці
		$delivery_data = Delivery()->db->get_delivery_data( $order->get_id() );
		
		if ( $delivery_data ) {
			$rows['billing_delivery'] = array(
				'label' => esc_html__( 'Region', 'ip-delivery-shipping' ),
				'value' => ! empty( $delivery_data['region_name'] ) ? esc_html( $delivery_data['region_name'] ) : ''
			);
			$rows['billing_city'] = array(
				'label' => esc_html__( 'City', 'ip-delivery-shipping' ),
				'value' => ! empty( $delivery_data['city_name'] ) ? esc_html( $delivery_data['city_name'] ) : ''
			);
			$rows['billing_warehouses'] = array(
				'label' => esc_html__( 'Delivery Warehouse', 'ip-delivery-shipping' ),
				'value' => ! empty( $delivery_data['warehouse_name'] ) ? esc_html( $delivery_data['warehouse_name'] ) : ''
			);
		} else {
			// Для сумісності з попередніми версіями використовуємо метадані
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
		}
	 
		return $rows;
	}

	/**
	 * Save delivery fields to database.
	 *
	 * @param int $order_id Order ID.
	 */
	public function save_field( $order_id ) {
		// Перевіряємо безпеку та наявність даних
		if ( empty( $_POST['delivery_delivery_name'] ) && empty( $_POST['delivery_city_name'] ) && empty( $_POST['delivery_warehouses_name'] ) ) {
			return;
		}
		
		// Отримуємо ID вибраних пунктів
		$region_id = isset( $_POST['delivery'] ) ? sanitize_text_field( $_POST['delivery'] ) : '';
		$city_id = isset( $_POST['city'] ) ? sanitize_text_field( $_POST['city'] ) : '';
		$warehouse_id = isset( $_POST['warehouses'] ) ? sanitize_text_field( $_POST['warehouses'] ) : '';
		
		// Отримуємо назви вибраних пунктів
		$region_name = isset( $_POST['delivery_delivery_name'] ) ? sanitize_text_field( $_POST['delivery_delivery_name'] ) : '';
		$city_name = isset( $_POST['delivery_city_name'] ) ? sanitize_text_field( $_POST['delivery_city_name'] ) : '';
		$warehouse_name = isset( $_POST['delivery_warehouses_name'] ) ? sanitize_text_field( $_POST['delivery_warehouses_name'] ) : '';
		
		// Зберігаємо дані в нашій таблиці
		$data = array(
			'region_id'      => $region_id,
			'region_name'    => $region_name,
			'city_id'        => $city_id,
			'city_name'      => $city_name,
			'warehouse_id'   => $warehouse_id,
			'warehouse_name' => $warehouse_name,
		);
		
		// Зберігаємо в БД
		Delivery()->db->save_delivery_data( $order_id, $data );
		
		// Для сумісності з попередніми версіями зберігаємо також у метаданих
		$order = wc_get_order( $order_id );
	 
		if ( ! empty( $region_name ) ) {
			$order->update_meta_data( 'delivery', $region_name );
		}
		if ( ! empty( $city_name ) ) {
			$order->update_meta_data( 'city', $city_name );
		}
		if ( ! empty( $warehouse_name ) ) {
			$order->update_meta_data( 'warehouses', $warehouse_name );
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
		$title = $this->get_setting('title');
		if (empty($title)) {
			$title = __( 'Delivery Service', 'ip-delivery-shipping' );
		}
		
		echo '<div id="delivery_checkout_field" style="display: none;"><h3>' . esc_html( $title ) . '</h3>';
	
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

	/**
	 * Отримуємо налаштування з БД
	 *
	 * @param string $key Ключ налаштування
	 * @param mixed $default Значення за замовчуванням
	 * @return mixed Значення налаштування
	 */
	protected function get_setting($key, $default = '') {
		global $wpdb;
		$table_name = $wpdb->prefix . 'ip_delivery_settings';
		
		// Перевіряємо чи існує таблиця
		if($wpdb->get_var("SHOW TABLES LIKE '$table_name'") === $table_name) {
			// Отримуємо значення з нової таблиці
			$value = $wpdb->get_var($wpdb->prepare("SELECT setting_value FROM $table_name WHERE setting_key = %s", $key));
			
			if($value !== null) {
				// Перевіряємо чи це серіалізовані дані
				if(@unserialize($value) !== false) {
					return unserialize($value);
				}
				return $value;
			}
		}
		
		// Якщо не вдалося отримати значення з нової таблиці, використовуємо стару опцію
		$settings = get_option('woocommerce_delivery_settings');
		return isset($settings[$key]) ? $settings[$key] : $default;
	}
	
	/**
	 * Підключаємо скрипти та стилі для сторінки оформлення замовлення
	 */
	public function enqueue_scripts() {
		// Перевіряємо, чи ми на сторінці оформлення замовлення
		if ( is_checkout() ) {
			// Підключаємо CSS файл для стилізації
			wp_enqueue_style(
				'ip-delivery-frontend',
				plugins_url( 'assets/css/ip-delivery-frontend.css', DELIVERY_PLUGIN_FILE ),
				array(),
				filemtime( plugin_dir_path( DELIVERY_PLUGIN_FILE ) . 'assets/css/ip-delivery-frontend.css' )
			);
		}
	}
} 