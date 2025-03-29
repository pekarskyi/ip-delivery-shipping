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
	 * Constructor
	 *
	 * @param int $instance_id Instance ID.
	 */
	public function __construct( $instance_id = 0 ) {
		parent::__construct( $instance_id );

		$this->id                 = 'ip_delivery';
		$this->instance_id        = absint( $instance_id );
		$this->method_title       = __( 'Delivery Service', 'ip-delivery-shipping' );
		$this->method_description = '';
		$this->supports           = array(
			'shipping-zones',
			'instance-settings',
			'settings',
		);

		// Це важливо, щоб WooCommerce знав, що це метод з підтримкою зон
		$this->instance_form_fields = array(); 
		$this->instance_settings = array();

		// Встановлюємо значення за замовчуванням для глобальних налаштувань
		$this->enabled              = 'yes'; // Метод завжди включений
		$this->tax_status           = 'taxable'; // Податки включені за замовчуванням
		
		// Для зонних екземплярів отримуємо заголовок з instance_settings
		if ( $this->instance_id > 0 ) {
			$this->title = $this->get_instance_option( 'title', __( 'Delivery Service', 'ip-delivery-shipping' ) );
		} else {
			// Для глобального екземпляра встановлюємо заголовок за замовчуванням
			$this->title = __( 'Delivery Service', 'ip-delivery-shipping' );
		}

		$this->init_form_fields();
		$this->init_settings();

		// Save settings for global instance
		add_action( 'woocommerce_update_options_shipping_' . $this->id, array( $this, 'process_admin_options' ) );
		
		// Save settings for zone instances 
		if ( $this->instance_id ) {
			add_action( 'woocommerce_update_options_shipping_' . $this->id . '_' . $this->instance_id, array( $this, 'process_admin_options' ) );
		}
	}

	/**
	 * Initialize form fields.
	 */
	public function init_form_fields() {
		// Важливо завжди мати instance_id в цьому методі
		$instance_id = $this->instance_id;

		// Глобальні налаштування (для instance_id = 0)
		if ( $this->instance_id === 0 ) {
			// Для глобального екземпляра повний набір налаштувань
			$this->form_fields = array(
				'public_key' => array(
					'title'       => __( 'Public API Key', 'ip-delivery-shipping' ),
					'type'        => 'text',
					'description' => __( 'Your public API key for Delivery Service API.', 'ip-delivery-shipping' ),
					'default'     => '',
					'desc_tip'    => true,
				),
				'secret_key' => array(
					'title'       => __( 'Secret API Key', 'ip-delivery-shipping' ),
					'type'        => 'password',
					'description' => __( 'Your secret API key for Delivery Service API.', 'ip-delivery-shipping' ),
					'default'     => '',
					'desc_tip'    => true,
				),
				'cache_time' => array(
					'title'       => __( 'Cache Time (hours)', 'ip-delivery-shipping' ),
					'type'        => 'number',
					'description' => __( 'Time to cache API responses (in hours). Minimum 1 hour.', 'ip-delivery-shipping' ),
					'default'     => '24',
					'desc_tip'    => true,
					'placeholder' => '24',
					'custom_attributes' => array(
						'min'  => '1',
						'max'  => '168',
						'step' => '1',
					),
				),
				'delete_data' => array(
					'title'       => __( 'Data Cleanup', 'ip-delivery-shipping' ),
					'type'        => 'checkbox',
					'label'       => __( 'Delete all plugin data when it is removed', 'ip-delivery-shipping' ),
					'default'     => 'no',
					'description' => __( 'If checked, all plugin settings, cached data, and configurations will be deleted from the database when the plugin is removed.', 'ip-delivery-shipping' ),
					'desc_tip'    => true,
				),
				'clear_cache' => array(
					'title' => '',
					'type' => 'title',
					'description' => '<a href="' . esc_url(wp_nonce_url(admin_url('admin-ajax.php?action=delivery_clear_cache&is_ajax=0'), 'delivery_clear_cache', 'security')) . '" class="button button-secondary">' . __('Clear Cache', 'ip-delivery-shipping') . '</a>',
					'desc_tip' => __('Clear the Delivery API cache', 'ip-delivery-shipping'),
				),
			);
		} else {
			// Для екземплярів методу тільки назва і базова вартість
			$this->instance_form_fields = array(
				'title' => array(
					'title'       => __( 'Title', 'ip-delivery-shipping' ),
					'type'        => 'text',
					'description' => __( 'This controls the title which the user sees during checkout.', 'ip-delivery-shipping' ),
					'default'     => __( 'Delivery Service', 'ip-delivery-shipping' ),
					'desc_tip'    => false,
				),
				'cost_base' => array(
					'title'       => __( 'Base Cost', 'ip-delivery-shipping' ),
					'type'        => 'price',
					'description' => __( 'Basic cost of delivery for this zone. Will be added to the calculated cost.', 'ip-delivery-shipping' ),
					'default'     => '0',
					'desc_tip'    => false,
					'placeholder' => '0',
				),
				'api_info' => array(
					'title' => __( 'API Settings Information', 'ip-delivery-shipping' ),
					'type' => 'title',
					'description' => '<div style="background: #f8f8f8; padding: 10px; border-left: 4px solid #2271b1; margin: 10px 0;">' .
						sprintf(
							__( 'API keys and other settings can be configured in <a href="%s"><strong>global settings</strong></a> of the delivery method.', 'ip-delivery-shipping' ),
							esc_url( admin_url( 'admin.php?page=wc-settings&tab=shipping&section=ip_delivery' ) )
						) . '</div>',
				),
			);
		}
	}

	/**
	 * Get setting form fields for instances of this shipping method within zones.
	 *
	 * @return array
	 */
	public function get_instance_form_fields() {
		return apply_filters( 'woocommerce_shipping_instance_form_fields_' . $this->id, $this->instance_form_fields );
	}

	/**
	 * Calculate shipping.
	 *
	 * @param array $package Package information.
	 */
	public function calculate_shipping( $package = array() ) {
		// Отримуємо базову вартість доставки
		$base_cost = 0;
		
		if ( $this->instance_id > 0 ) {
			// Отримуємо базову вартість з налаштувань зони
			$base_cost = $this->get_instance_option( 'cost_base', 0 );
		}
		
		$base_cost = floatval( $base_cost );
		
		// Отримуємо додаткові параметри з чекауту, якщо вони є
		$chosen_region_ref = WC()->session->get( 'ip_delivery_region' );
		$chosen_city_ref = WC()->session->get( 'ip_delivery_city' );
		$chosen_warehouse_ref = WC()->session->get( 'ip_delivery_warehouse' );
		
		// Розраховуємо вартість доставки
		$cost = $base_cost;
		
		// Можна додати розрахунок вартості на основі параметрів
		if ( !empty( $chosen_warehouse_ref ) && !empty( $chosen_city_ref ) ) {
			// Тут можна додати додаткову логіку розрахунку вартості
			// Наприклад, запит на API для отримання вартості
		}
		
		// Дозволяємо фільтрам змінити вартість
		$cost = apply_filters( 'delivery_calculated_cost', $cost, $package, $this->instance_id );
		
		// Додаємо метод доставки
		$rate = array(
			'id'    => $this->get_rate_id(),
			'label' => $this->title,
			'cost'  => $cost,
		);
		
		// Реєструємо метод
		$this->add_rate( $rate );
		
		// Дозволяємо додавати додаткові тарифи через фільтр
		do_action( 'woocommerce_' . $this->id . '_shipping_add_rate', $this, $rate, $package );
	}
	
	/**
	 * Get items in package.
	 *
	 * @param array $package Package of items.
	 * @return int
	 */
	public function get_package_item_qty( $package ) {
		$total_quantity = 0;
		foreach ( $package['contents'] as $item_id => $values ) {
			if ( $values['quantity'] > 0 && $values['data']->needs_shipping() ) {
				$total_quantity += $values['quantity'];
			}
		}
		return $total_quantity;
	}

	/**
	 * Generates a rate ID.
	 *
	 * @param string $suffix Optional suffix to append to rate ID.
	 * @return string
	 */
	public function get_rate_id( $suffix = '' ) {
		$rate_id = $this->id;
		
		if ( $this->instance_id ) {
			$rate_id .= ':' . $this->instance_id;
		}
		
		if ( '' !== $suffix ) {
			$rate_id .= ':' . $suffix;
		}
		
		return $rate_id;
	}

	/**
	 * Process admin options.
	 *
	 * @return bool was anything saved?
	 */
	public function process_admin_options() {
		$result = parent::process_admin_options();
		
		// Оновлюємо заголовок для екземплярів зон
		if ( $this->instance_id > 0 ) {
			$this->title = $this->get_instance_option( 'title', __( 'Delivery Service', 'ip-delivery-shipping' ) );
		}
		
		return $result;
	}
	
	/**
	 * Check if this method is available.
	 *
	 * @param array $package Package information.
	 * @return bool
	 */
	public function is_available( $package = array() ) {
		// Метод доступний за замовчуванням
		$is_available = true;
		
		// Перевіряємо глобальні налаштування для API ключів
		$global_settings = get_option( 'woocommerce_ip_delivery_settings', array() );
		
		if ( empty( $global_settings['public_key'] ) || empty( $global_settings['secret_key'] ) ) {
			$is_available = false;
		}
		
		return apply_filters( 'woocommerce_shipping_' . $this->id . '_is_available', $is_available, $package, $this );
	}
} 