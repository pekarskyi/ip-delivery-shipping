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
		$this->method_description = ''; 

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
		
		// Підключення стилів для фронтенду
		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_frontend_styles' ) );
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
	 * Перевизначаємо метод для збереження у власній таблиці БД
	 *
	 * @return bool
	 */
	public function process_admin_options() {
		$this->init_settings();

		$post_data = $this->get_post_data();

		foreach ( $this->get_form_fields() as $key => $field ) {
			if ( 'title' !== $this->get_field_type( $field ) ) {
				try {
					$this->settings[ $key ] = $this->get_field_value( $key, $field, $post_data );
				} catch ( Exception $e ) {
					$this->add_error( $e->getMessage() );
				}
			}
		}
		
		// Зберігаємо частину налаштувань у стандартному місці для сумісності з WooCommerce
		update_option( $this->get_option_key(), apply_filters( 'woocommerce_settings_api_sanitized_fields_' . $this->id, $this->settings ), 'yes' );
		
		// Зберігаємо налаштування в нашій БД
		$this->save_settings_to_db($this->settings);
		
		return update_option( $this->get_option_key(), apply_filters( 'woocommerce_settings_api_sanitized_fields_' . $this->id, $this->settings ), 'yes' );
	}
	
	/**
	 * Зберігаємо налаштування в нашій власній таблиці БД
	 *
	 * @param array $settings Налаштування для збереження
	 * @return bool Результат збереження
	 */
	protected function save_settings_to_db($settings) {
		global $wpdb;
		$table_name = $wpdb->prefix . 'ip_delivery_settings';
		
		// Перевіряємо чи існує таблиця, якщо ні - створюємо
		if($wpdb->get_var("SHOW TABLES LIKE '$table_name'") != $table_name) {
			$charset_collate = $wpdb->get_charset_collate();
			$sql = "CREATE TABLE $table_name (
				id mediumint(9) NOT NULL AUTO_INCREMENT,
				setting_key varchar(191) NOT NULL,
				setting_value longtext NOT NULL,
				PRIMARY KEY  (id),
				UNIQUE KEY setting_key (setting_key)
			) $charset_collate;";
			require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
			dbDelta($sql);
		}
		
		// Видаляємо всі попередні налаштування
		$wpdb->query("TRUNCATE TABLE $table_name");
		
		// Зберігаємо кожне налаштування окремо
		foreach($settings as $key => $value) {
			$wpdb->insert(
				$table_name,
				array(
					'setting_key' => $key,
					'setting_value' => is_array($value) ? serialize($value) : $value
				),
				array(
					'%s',
					'%s'
				)
			);
		}
		
		return true;
	}
	
	/**
	 * Отримуємо налаштування з нашої таблиці БД
	 *
	 * @return array Налаштування
	 */
	protected function get_settings_from_db() {
		global $wpdb;
		$table_name = $wpdb->prefix . 'ip_delivery_settings';
		
		// Перевіряємо чи існує таблиця
		if($wpdb->get_var("SHOW TABLES LIKE '$table_name'") != $table_name) {
			return array();
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
		
		return $settings;
	}
	
	/**
	 * Перевизначаємо метод для отримання налаштувань з власної таблиці БД
	 *
	 * @param string $key Ключ налаштування
	 * @param mixed $empty_value Значення за замовчуванням
	 * @return mixed
	 */
	public function get_option( $key, $empty_value = null ) {
		// Спочатку спробуємо отримати налаштування з нашої БД
		$settings = $this->get_settings_from_db();
		
		if(isset($settings[$key])) {
			return $settings[$key];
		}
		
		// Якщо не знайдено, використовуємо стандартний механізм WooCommerce
		if ( empty( $this->settings ) ) {
			$this->init_settings();
		}

		// Get option default if unset.
		if ( ! isset( $this->settings[ $key ] ) ) {
			$form_fields            = $this->get_form_fields();
			$this->settings[ $key ] = isset( $form_fields[ $key ] ) ? $this->get_field_default( $form_fields[ $key ] ) : '';
		}

		if ( ! is_null( $empty_value ) && '' === $this->settings[ $key ] ) {
			$this->settings[ $key ] = $empty_value;
		}

		return $this->settings[ $key ];
	}

	/**
	 * Define settings field for this shipping
	 * 
	 * @return void 
	 */
	public function init_form_fields() { 
		$this->form_fields = array(
			'general_settings_title' => array(
				'title' => __( 'General Settings', 'ip-delivery-shipping' ),
				'type' => 'title',
				'description' => '',
				'class' => 'delivery-settings-section'
			),
			
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
				'description' => __( 'Base cost for delivery. Can be modified by filters.', 'ip-delivery-shipping' ) . ' <br><a href="https://github.com/pekarskyi/ip-delivery-shipping" target="_blank">' . __( 'Learn more about filters', 'ip-delivery-shipping' ) . '</a>',
				'default' => '0',
				'custom_attributes' => array(
					'min' => '0',
					'step' => '0.01'
				)
			),
			
			'api_settings_title' => array(
				'title' => __( 'API Settings', 'ip-delivery-shipping' ),
				'type' => 'title',
				'description' => sprintf(
					__( 'To get API keys, please visit <a href="%s" target="_blank">Delivery API Key page</a>', 'ip-delivery-shipping' ),
					esc_url( 'https://www.delivery-auto.com/uk/Account/ApiKey' )
				),
				'class' => 'delivery-settings-section'
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
			
			'checkout_options' => array(
				'title' => __( 'Checkout Options', 'ip-delivery-shipping' ),
				'type' => 'title',
				'description' => '',
				'class' => 'delivery-settings-section'
			),
			
			'two_column_fields' => array(
				'title' => __( 'Display Phone and Email fields in two columns', 'ip-delivery-shipping' ),
				'type' => 'checkbox',
				'description' => __( 'Field display may be affected by your theme or other plugins. Please check how these fields are displayed.', 'ip-delivery-shipping' ),
				'default' => 'yes'
			),
			 
			'cache_options' => array(
				'title' => __( 'Cache Options', 'ip-delivery-shipping' ),
				'type' => 'title',
				'description' => __( 'The plugin caches API responses for better performance. Cache lifetime: 24 hours.', 'ip-delivery-shipping' ) . 
				'&nbsp;&nbsp;&nbsp;<a href="' . esc_url( admin_url( 'admin.php?page=wc-settings&tab=shipping&section=ip-delivery&clear_cache=1&_wpnonce=' . wp_create_nonce( 'delivery_clear_cache' ) ) ) . '" class="button ip-delivery yellow" id="delivery-clear-cache-link">' . esc_html__( 'Clear Cache', 'ip-delivery-shipping' ) . '</a>',
				'class' => 'delivery-settings-section'
			),
			
			'cleanup_options' => array(
				'title' => __( 'Cleanup Options', 'ip-delivery-shipping' ),
				'type' => 'title',
				'description' => '',
				'class' => 'delivery-settings-section'
			),
			
			'delete_data' => array(
				'title' => __( 'Delete data on uninstall', 'ip-delivery-shipping' ),
				'type' => 'checkbox',
				'description' => __( 'Delete all plugin data from database when the plugin is uninstalled.', 'ip-delivery-shipping' ),
				'default' => 'no'
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
} 