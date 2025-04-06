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
		$this->method_title       = __( 'Delivery', 'ip-delivery-shipping' );  
		$this->method_description = ''; 

		// Availability & Countries
		$this->availability = 'including';
		$this->countries = array( 'UA' );

		// Завантажуємо налаштування з нашої бази даних
		$this->load_settings_from_db();
		
		// Підключення стилів для фронтенду
		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_frontend_styles' ) );
	}
	
	/**
	 * Завантажуємо налаштування з нашої бази даних
	 */
	private function load_settings_from_db() {
		global $wpdb;
		$table_name = $wpdb->prefix . 'ip_delivery_settings';
		
		// Перевіряємо чи існує таблиця
		if($wpdb->get_var("SHOW TABLES LIKE '$table_name'") != $table_name) {
			// Якщо таблиці немає, використовуємо значення за замовчуванням
			$this->enabled = 'yes';
			$this->title = __( 'Delivery', 'ip-delivery-shipping' );
			$this->public_key = '';
			$this->secret_key = '';
			return;
		}
		
		$settings = array();
		$results = $wpdb->get_results("SELECT setting_key, setting_value FROM $table_name", ARRAY_A);
		
		if($results) {
			foreach($results as $row) {
				$value = $row['setting_value'];
				// Перевіряємо чи це серіалізовані дані
				if(@unserialize($value) !== false) {
					$value = unserialize($value);
				}
				$settings[$row['setting_key']] = $value;
			}
		}
		
		// Встановлюємо значення з налаштувань
		$this->enabled = isset($settings['enabled']) ? $settings['enabled'] : 'yes';
		
		// Перевіряємо, щоб поле title не було порожнім
		$default_title = __( 'Delivery', 'ip-delivery-shipping' );
		$this->title = isset($settings['title']) && !empty(trim($settings['title'])) 
						? $settings['title'] 
						: $default_title;
		
		$this->public_key = isset($settings['public_key']) ? $settings['public_key'] : '';
		$this->secret_key = isset($settings['secret_key']) ? $settings['secret_key'] : '';
	}

	/**
	 * Define settings field for this shipping
	 * 
	 * @return void 
	 */
	public function init_form_fields() { 
		// Порожні налаштування, оскільки всі параметри керуються через окрему сторінку
		$this->form_fields = array();
	}
	
	/**
	 * Отримуємо значення налаштування з бази даних
	 *
	 * @param string $key Ключ налаштування
	 * @param mixed $empty_value Значення за замовчуванням
	 * @return mixed
	 */
	public function get_option( $key, $empty_value = null ) {
		// Отримуємо налаштування з нашої БД
		global $wpdb;
		$table_name = $wpdb->prefix . 'ip_delivery_settings';
		
		// Перевіряємо чи існує таблиця
		if($wpdb->get_var("SHOW TABLES LIKE '$table_name'") != $table_name) {
			// Якщо таблиці немає, використовуємо стандартний механізм WooCommerce
			return parent::get_option( $key, $empty_value );
		}
		
		$result = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT setting_value FROM $table_name WHERE setting_key = %s LIMIT 1",
				$key
			)
		);
		
		if($result !== null) {
			// Перевіряємо чи це серіалізовані дані
			if(@unserialize($result) !== false) {
				$result = unserialize($result);
			}
			return $result;
		}
		
		// Значення за замовчуванням
		if ( ! is_null( $empty_value ) ) {
			return $empty_value;
		}
		
		// Повертаємо стандартні значення
		if ( $key === 'enabled' ) {
			return 'yes';
		} elseif ( $key === 'title' ) {
			return __( 'Delivery', 'ip-delivery-shipping' );
		} elseif ( $key === 'base_cost' ) {
			return '0';
		}
		
		return '';
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
			'region_id' => isset( $_POST['delivery'] ) ? sanitize_text_field( $_POST['delivery'] ) : '',
			'region' => isset( $_POST['delivery_delivery_name'] ) ? sanitize_text_field( $_POST['delivery_delivery_name'] ) : '',
			'city_id' => isset( $_POST['city'] ) ? sanitize_text_field( $_POST['city'] ) : '',
			'city' => isset( $_POST['delivery_city_name'] ) ? sanitize_text_field( $_POST['delivery_city_name'] ) : '',
			'warehouse_id' => isset( $_POST['warehouses'] ) ? sanitize_text_field( $_POST['warehouses'] ) : '',
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

	/**
	 * Приховуємо налаштування методу доставки в WooCommerce
	 *
	 * @return bool
	 */
	public function has_settings() {
		return false;
	}

	/**
	 * Виводимо кнопку налаштувань в таблиці методів доставки WooCommerce
	 *
	 * @param string $instance_id ID екземпляру методу доставки
	 * @return string
	 */
	public function get_instance_form_html( $instance_id = '' ) {
		return sprintf(
			'<p><a href="%s" class="button">%s</a></p>',
			esc_url( admin_url( 'admin.php?page=ip-delivery-settings' ) ),
			esc_html__( 'Settings', 'ip-delivery-shipping' )
		);
	}
} 