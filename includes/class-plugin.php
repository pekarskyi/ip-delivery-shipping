<?php
/**
 * Main plugin class.
 *
 * @package Delivery
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

/**
 * Main Delivery_Plugin Class.
 *
 * @class Delivery_Plugin
 */
final class Delivery_Plugin {
	/**
	 * Plugin instance.
	 *
	 * @var Delivery_Plugin
	 */
	protected static $_instance = null;

	/**
	 * Admin instance.
	 *
	 * @var Delivery_Admin
	 */
	public $admin = null;

	/**
	 * API instance.
	 *
	 * @var Delivery_API
	 */
	public $api = null;

	/**
	 * Ajax handler instance.
	 *
	 * @var Delivery_Ajax_Handler
	 */
	public $ajax_handler = null;

	/**
	 * Checkout instance.
	 *
	 * @var Delivery_Checkout
	 */
	public $checkout = null;

	/**
	 * Database handler instance.
	 *
	 * @var Delivery_DB
	 */
	public $db = null;

	/**
	 * Main Delivery_Plugin Instance.
	 *
	 * Ensures only one instance of Delivery_Plugin is loaded or can be loaded.
	 *
	 * @static
	 * @return Delivery_Plugin - Main instance.
	 */
	public static function instance() {
		if ( is_null( self::$_instance ) ) {
			self::$_instance = new self();
		}
		return self::$_instance;
	}

	/**
	 * Cloning is forbidden.
	 */
	public function __clone() {
		_doing_it_wrong( __FUNCTION__, esc_html__( 'Cloning is forbidden.', 'ip-delivery-shipping' ), '1.0.0' );
	}

	/**
	 * Unserializing instances of this class is forbidden.
	 */
	public function __wakeup() {
		_doing_it_wrong( __FUNCTION__, esc_html__( 'Unserializing instances of this class is forbidden.', 'ip-delivery-shipping' ), '1.0.0' );
	}

	/**
	 * Delivery_Plugin Constructor.
	 */
	public function __construct() {
		$this->define_constants();
		$this->init_hooks();
	}

	/**
	 * Define plugin constants.
	 */
	private function define_constants() {
		$this->define( 'DELIVERY_ABSPATH', dirname( DELIVERY_PLUGIN_FILE ) . '/' );
		$this->define( 'DELIVERY_PLUGIN_BASENAME', plugin_basename( DELIVERY_PLUGIN_FILE ) );
		$this->define( 'DELIVERY_VERSION', '1.0.0' );
	}

	/**
	 * Define constant if not already set.
	 *
	 * @param string $name  Constant name.
	 * @param mixed  $value Constant value.
	 */
	private function define( $name, $value ) {
		if ( ! defined( $name ) ) {
			define( $name, $value );
		}
	}

	/**
	 * Include required core files used in admin and frontend.
	 */
	public function includes() {
		// Включаємо клас бази даних тільки якщо він ще не включений
		if ( ! class_exists( 'Delivery_DB' ) ) {
			include_once DELIVERY_ABSPATH . 'includes/class-db.php';
		}
		
		// Включаємо тільки клас API, оскільки він не залежить від WooCommerce.
		include_once DELIVERY_ABSPATH . 'includes/class-api.php';
		
		// Переконуємося, що WooCommerce завантажений перед включенням файлів, які залежать від нього.
		if ( class_exists( 'WC_Shipping_Method' ) ) {
			include_once DELIVERY_ABSPATH . 'includes/class-shipping-method.php';
			include_once DELIVERY_ABSPATH . 'includes/class-checkout.php';
		}
		
		include_once DELIVERY_ABSPATH . 'includes/class-ajax-handler.php';
		include_once DELIVERY_ABSPATH . 'includes/class-admin.php';
	}

	/**
	 * Hook into actions and filters.
	 */
	private function init_hooks() {
		// Завантажуємо клас бази даних для реєстрації хуків активації
		include_once DELIVERY_ABSPATH . 'includes/class-db.php';
		// Реєструємо хук активації
		Delivery_DB::register_hooks();
		
		// Load plugin text domain.
		add_action( 'plugins_loaded', array( $this, 'load_plugin_textdomain' ) );
		
		// Перевіряємо активність WooCommerce та включаємо файли лише після його завантаження.
		add_action( 'plugins_loaded', array( $this, 'includes' ), 10 );
		
		// Declare compatibility with HPOS.
		add_action( 'before_woocommerce_init', array( $this, 'declare_hpos_compatibility' ) );
		
		// Ініціалізуємо класи тільки після включення всіх файлів.
		add_action( 'plugins_loaded', array( $this, 'init_classes' ), 20 );
		
		// Register shipping method.
		add_action( 'woocommerce_shipping_init', array( $this, 'woocommerce_shipping_init' ) );
		add_filter( 'woocommerce_shipping_methods', array( $this, 'add_delivery_shipping_method' ) );
	}

	/**
	 * Load Localisation files.
	 */
	public function load_plugin_textdomain() {
		load_plugin_textdomain( 'ip-delivery-shipping', false, dirname( DELIVERY_PLUGIN_BASENAME ) . '/lang/' );
	}

	/**
	 * Declare compatibility with High-Performance Order Storage.
	 */
	public function declare_hpos_compatibility() {
		if ( class_exists( '\Automattic\WooCommerce\Utilities\FeaturesUtil' ) ) {
			\Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility( 'custom_order_tables', DELIVERY_PLUGIN_FILE, true );
		}
	}

	/**
	 * Initialize plugin classes.
	 */
	public function init_classes() {
		// Ініціалізуємо класс бази даних
		$this->db = new Delivery_DB();
		
		// Ініціалізуємо API незалежно від WooCommerce.
		$this->api = new Delivery_API();
		$this->admin = new Delivery_Admin();
		$this->ajax_handler = new Delivery_Ajax_Handler();
		
		// Ініціалізуємо класи, що залежать від WooCommerce, тільки якщо WooCommerce активний.
		if ( class_exists( 'WC_Shipping_Method' ) ) {
			$this->checkout = new Delivery_Checkout();
		}
	}

	/**
	 * Initialize shipping method.
	 */
	public function woocommerce_shipping_init() {
		// Переконуємося, що клас Shipping_Method ще не включений, але WooCommerce активний.
		if ( ! class_exists( 'Delivery_Shipping_Method' ) && class_exists( 'WC_Shipping_Method' ) ) {
			include_once DELIVERY_ABSPATH . 'includes/class-shipping-method.php';
		}
	}

	/**
	 * Add shipping method.
	 *
	 * @param array $methods Shipping methods.
	 * @return array
	 */
	public function add_delivery_shipping_method( $methods ) {
		// Додаємо метод доставки тільки якщо відповідний клас існує.
		if ( class_exists( 'Delivery_Shipping_Method' ) ) {
			$methods[] = 'Delivery_Shipping_Method';
		}
		return $methods;
	}
} 