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
		$this->includes();
		$this->init_hooks();
		
		add_filter( 'woocommerce_shipping_methods', array( $this, 'add_delivery_shipping_method' ) );
	}

	/**
	 * Define plugin constants.
	 */
	private function define_constants() {
		$this->define( 'DELIVERY_ABSPATH', dirname( DELIVERY_PLUGIN_FILE ) . '/' );
		$this->define( 'DELIVERY_PLUGIN_BASENAME', plugin_basename( DELIVERY_PLUGIN_FILE ) );
		
		// Отримуємо версію з заголовка файлу плагіна
		if ( ! function_exists( 'get_plugin_data' ) ) {
			require_once ABSPATH . 'wp-admin/includes/plugin.php';
		}
		$plugin_data = get_plugin_data( DELIVERY_PLUGIN_FILE );
		$plugin_version = $plugin_data['Version'] ? $plugin_data['Version'] : '1.1.0';
		
		$this->define( 'DELIVERY_VERSION', $plugin_version );
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
		add_action( 'plugins_loaded', array( $this, 'on_plugins_loaded' ) );
		
		// Перевіряємо та створюємо uninstall.php при активації, якщо потрібно
		add_action( 'admin_init', array( $this, 'check_uninstall_script' ) );
		
		// Load plugin text domain.
		add_action( 'plugins_loaded', array( $this, 'load_plugin_textdomain' ) );
		
		// Перевіряємо активність WooCommerce та включаємо файли лише після його завантаження.
		add_action( 'plugins_loaded', array( $this, 'includes' ), 10 );
		
		// Declare compatibility with HPOS.
		add_action( 'before_woocommerce_init', array( $this, 'declare_hpos_compatibility' ) );
		
		// Ініціалізуємо класи тільки після включення всіх файлів.
		add_action( 'plugins_loaded', array( $this, 'init_classes' ), 20 );
		
		// Register shipping method.
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
	 * Init classes.
	 *
	 * @return void
	 */
	public function init_classes() {
		// Перевіряємо чи WooCommerce активний і ініціалізований
		if ( ! function_exists( 'WC' ) ) {
			add_action( 'admin_notices', array( $this, 'woocommerce_not_active_notice' ) );
			return;
		}

		// Ініціалізуємо API клас із перевіркою
		if ( ! isset( $this->api ) ) {
			// Перевіряємо, чи WooCommerce повністю ініціалізований
			if ( function_exists( 'WC' ) && WC() && is_a( WC()->countries, 'WC_Countries' ) ) {
				$this->api = new Delivery_API();
			} else {
				// Якщо не ініціалізований, відкладаємо ініціалізацію API
				add_action( 'woocommerce_init', array( $this, 'init_api' ) );
			}
		}

		// Ініціалізуємо інші класи, які не потребують повної ініціалізації WooCommerce
		if ( ! isset( $this->admin ) ) {
			$this->admin = new Delivery_Admin();
		}
		
		if ( ! isset( $this->ajax_handler ) ) {
			$this->ajax_handler = new Delivery_Ajax_Handler();
		}

		if ( ! isset( $this->checkout ) && class_exists( 'WC_Shipping_Method' ) ) {
			$this->checkout = new Delivery_Checkout();
		}
	}
	
	/**
	 * Ініціалізує API клас після того, як WooCommerce повністю завантажений
	 *
	 * @return void
	 */
	public function init_api() {
		// Перевіряємо, що WooCommerce повністю ініціалізований
		if ( ! function_exists( 'WC' ) || ! WC() || ! is_a( WC()->countries, 'WC_Countries' ) ) {
			return;
		}
		
		if ( ! isset( $this->api ) ) {
			$this->api = new Delivery_API();
		}
	}
	
	/**
	 * Показує повідомлення, якщо WooCommerce не активний
	 *
	 * @return void
	 */
	public function woocommerce_not_active_notice() {
		?>
		<div class="notice notice-error">
			<p>
				<?php
				printf(
					/* translators: %s: WooCommerce link */
					esc_html__( 'IP Delivery Shipping requires %s to be installed and active.', 'ip-delivery-shipping' ),
					'<a href="' . esc_url( admin_url( 'plugin-install.php?tab=search&s=woocommerce' ) ) . '">WooCommerce</a>'
				);
				?>
			</p>
		</div>
		<?php
	}

	/**
	 * Add the Delivery shipping method to WooCommerce.
	 *
	 * @param array $methods Shipping methods.
	 * @return array
	 */
	public function add_delivery_shipping_method( $methods ) {
		// Додаємо метод доставки тільки якщо WooCommerce активний
		if ( class_exists( 'WC_Shipping_Method' ) ) {
			// Переконуємося, що клас Delivery_Shipping_Method доступний
			if ( ! class_exists( 'Delivery_Shipping_Method' ) ) {
				// Якщо клас ще не включений, включаємо його
				include_once DELIVERY_ABSPATH . 'includes/class-shipping-method.php';
			}
			
			$methods['ip_delivery'] = 'Delivery_Shipping_Method';
			
			// Додаємо налаштування за замовчуванням, якщо вони ще не існують
			$this->maybe_create_default_settings();
		}
		return $methods;
	}
	
	/**
	 * Create default settings if they don't exist yet.
	 */
	private function maybe_create_default_settings() {
		// Перевіряємо, чи існують налаштування
		$settings = get_option( 'woocommerce_ip_delivery_settings' );
		
		// Якщо налаштувань ще немає, створюємо їх
		if ( false === $settings ) {
			// Базові налаштування
			$default_settings = array(
				'public_key'    => '',
				'secret_key'    => '',
				'cache_time'    => '24',
			);
			
			// Зберігаємо налаштування
			update_option( 'woocommerce_ip_delivery_settings', $default_settings );
		}
	}

	/**
	 * Перевіряє та створює файл uninstall.php, якщо вибрана опція видалення даних.
	 */
	public function check_uninstall_script() {
		// Отримуємо налаштування
		$settings = get_option( 'woocommerce_ip_delivery_settings', array() );
		
		// Визначаємо шлях до файлу uninstall.php
		$uninstall_file = plugin_dir_path( DELIVERY_PLUGIN_FILE ) . 'uninstall.php';
		
		// Якщо опція видалення даних увімкнена, але файлу немає - створюємо його
		if ( isset( $settings['delete_data'] ) && 'yes' === $settings['delete_data'] && ! file_exists( $uninstall_file ) ) {
			$this->create_uninstall_script( $uninstall_file );
		}
		
		// Якщо опція вимкнена, але файл існує - видаляємо його
		if ( isset( $settings['delete_data'] ) && 'yes' !== $settings['delete_data'] && file_exists( $uninstall_file ) ) {
			@unlink( $uninstall_file );
		}
	}
	
	/**
	 * Створює файл uninstall.php для автоматичного видалення даних при видаленні плагіну.
	 *
	 * @param string $file_path Шлях до файлу uninstall.php.
	 */
	private function create_uninstall_script( $file_path ) {
		$content = '<?php
/**
 * Uninstall script for IP Delivery Shipping plugin.
 * This file is automatically generated when the "Delete data" option is enabled.
 *
 * @package Delivery
 */

// If uninstall is not called from WordPress, exit.
if ( ! defined( "WP_UNINSTALL_PLUGIN" ) ) {
	exit;
}

// Check if we should delete data
$settings = get_option( "woocommerce_ip_delivery_settings", array() );
if ( ! isset( $settings["delete_data"] ) || "yes" !== $settings["delete_data"] ) {
	return; // Don\'t delete data if the option is not enabled
}

// Clean up all plugin data
global $wpdb;

// Delete global settings
delete_option( "woocommerce_ip_delivery_settings" );

// Delete shipping zone settings
$wpdb->query( "DELETE FROM {$wpdb->options} WHERE option_name LIKE \'woocommerce_ip_delivery_%_settings\'" );

// Delete API cache
$wpdb->query( "DELETE FROM {$wpdb->options} WHERE option_name LIKE \'_transient_ip_delivery_%\'" );
$wpdb->query( "DELETE FROM {$wpdb->options} WHERE option_name LIKE \'_transient_timeout_ip_delivery_%\'" );

// Log to error log when in debug mode
if ( defined( "WP_DEBUG" ) && WP_DEBUG ) {
	error_log( "IP Delivery Shipping plugin data was completely removed from the database by uninstall script." );
}
';
		
		// Записуємо вміст у файл
		@file_put_contents( $file_path, $content );
	}

	/**
	 * Видаляє всі дані плагіну з бази даних.
	 */
	public function delete_plugin_data() {
		global $wpdb;
		
		// Видаляємо глобальні налаштування методу доставки
		delete_option( 'woocommerce_ip_delivery_settings' );
		
		// Видаляємо налаштування зон доставки
		$wpdb->query( "DELETE FROM {$wpdb->options} WHERE option_name LIKE 'woocommerce_ip_delivery_%_settings'" );
		
		// Видаляємо кеш даних API
		$wpdb->query( "DELETE FROM {$wpdb->options} WHERE option_name LIKE '_transient_ip_delivery_%'" );
		$wpdb->query( "DELETE FROM {$wpdb->options} WHERE option_name LIKE '_transient_timeout_ip_delivery_%'" );
		
		// Записуємо в журнал, якщо увімкнено
		if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
			error_log( 'IP Delivery Shipping plugin data was completely removed from the database.' );
		}
	}

	/**
	 * Callback for 'plugins_loaded' action.
	 * Це місце для дій, які повинні відбуватися після завантаження всіх плагінів.
	 */
	public function on_plugins_loaded() {
		// Додаткова ініціалізація, яка може знадобитися після завантаження всіх плагінів
		// Наприклад, перевірка версій або взаємодія з іншими плагінами
		do_action( 'ip_delivery_plugin_loaded' );
	}
} 