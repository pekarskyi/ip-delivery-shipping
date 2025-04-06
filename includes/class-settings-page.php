<?php
/**
 * Settings page class.
 *
 * @package Delivery
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

/**
 * Delivery_Settings_Page Class.
 */
class Delivery_Settings_Page {
	/**
	 * Settings values.
	 *
	 * @var array
	 */
	private $settings = array();
	
	/**
	 * Збережені повідомлення для виведення.
	 *
	 * @var array
	 */
	private $notices = array();

	/**
	 * Initialize the class and set its properties.
	 */
	public function __construct() {
		// Підключаємо хуки для додавання сторінки в меню
		add_action( 'admin_menu', array( $this, 'add_menu_item' ) );
		
		// Підключаємо хуки для реєстрації налаштувань
		add_action( 'admin_init', array( $this, 'register_settings' ) );
		
		// Завантажуємо збережені налаштування
		$this->load_settings();
		
		// Ініціалізуємо сесійні повідомлення, якщо вони є
		$this->init_notices();
	}
	
	/**
	 * Ініціалізує сесійні повідомлення
	 */
	private function init_notices() {
		if (!empty($_GET['delivery_notice']) && !empty($_GET['delivery_notice_type'])) {
			$message = sanitize_text_field(urldecode($_GET['delivery_notice']));
			$type = sanitize_text_field($_GET['delivery_notice_type']);
			$this->add_notice($message, $type);
		}
	}
	
	/**
	 * Додає повідомлення для виведення
	 *
	 * @param string $message Текст повідомлення
	 * @param string $type Тип повідомлення (success, error, warning, info)
	 */
	public function add_notice($message, $type = 'success') {
		$this->notices[] = array(
			'message' => $message,
			'type' => $type
		);
	}
	
	/**
	 * Перенаправляє з повідомленням
	 *
	 * @param string $url URL для перенаправлення
	 * @param string $message Текст повідомлення
	 * @param string $type Тип повідомлення
	 */
	public function redirect_with_notice($url, $message, $type = 'success') {
		$url = add_query_arg(array(
			'delivery_notice' => urlencode($message),
			'delivery_notice_type' => $type
		), $url);
		
		wp_redirect($url);
		exit;
	}
	
	/**
	 * Завантажуємо збережені налаштування з бази даних
	 */
	private function load_settings() {
		global $wpdb;
		$table_name = $wpdb->prefix . 'ip_delivery_settings';
		
		// Перевіряємо чи існує таблиця
		if($wpdb->get_var("SHOW TABLES LIKE '$table_name'") != $table_name) {
			return;
		}
		
		$results = $wpdb->get_results("SELECT setting_key, setting_value FROM $table_name", ARRAY_A);
		
		if($results) {
			foreach($results as $row) {
				$value = $row['setting_value'];
				// Перевіряємо чи це серіалізовані дані
				if(@unserialize($value) !== false) {
					$value = unserialize($value);
				}
				$this->settings[$row['setting_key']] = $value;
			}
		}
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
		
		// Зберігаємо кожне налаштування окремо, оновлюючи якщо воно існує
		foreach($settings as $key => $value) {
			$wpdb->replace(
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
	 * Додаємо пункт меню в адмін-панель
	 */
	public function add_menu_item() {
		add_menu_page(
			__( 'Delivery Settings', 'ip-delivery-shipping' ),
			__( 'IP Delivery', 'ip-delivery-shipping' ),
			'manage_options',
			'ip-delivery-settings',
			array( $this, 'render_settings_page' ),
			'dashicons-car',
			58 // Позиція в меню
		);
	}
	
	/**
	 * Реєструємо налаштування
	 */
	public function register_settings() {
		// Реєструємо групу налаштувань
		register_setting(
			'delivery_settings_group', // Група опцій
			'delivery_settings', // Назва опції
			array( $this, 'sanitize_settings' ) // Функція санітації
		);
		
		// Додаємо секції для налаштувань
		add_settings_section(
			'delivery_general_section',
			__( 'General Settings', 'ip-delivery-shipping' ),
			array( $this, 'general_section_callback' ),
			'ip-delivery-settings'
		);
		
		add_settings_section(
			'delivery_api_section',
			__( 'API Settings', 'ip-delivery-shipping' ),
			array( $this, 'api_section_callback' ),
			'ip-delivery-settings'
		);
		
		add_settings_section(
			'delivery_checkout_section',
			__( 'Checkout Options', 'ip-delivery-shipping' ),
			array( $this, 'checkout_section_callback' ),
			'ip-delivery-settings'
		);
		
		add_settings_section(
			'delivery_cache_section',
			__( 'Cache Options', 'ip-delivery-shipping' ),
			array( $this, 'cache_section_callback' ),
			'ip-delivery-settings'
		);
		
		add_settings_section(
			'delivery_cleanup_section',
			__( 'Cleanup Options', 'ip-delivery-shipping' ),
			array( $this, 'cleanup_section_callback' ),
			'ip-delivery-settings'
		);
		
		// Додаємо поля для секції "General Settings"
		add_settings_field(
			'enabled',
			__( 'Enable Delivery', 'ip-delivery-shipping' ),
			array( $this, 'checkbox_field_callback' ),
			'ip-delivery-settings',
			'delivery_general_section',
			array(
				'id' => 'enabled',
				'default' => 'yes'
			)
		);
		
		add_settings_field(
			'title',
			__( 'Title', 'ip-delivery-shipping' ),
			array( $this, 'text_field_callback' ),
			'ip-delivery-settings',
			'delivery_general_section',
			array(
				'id' => 'title',
				'default' => __( 'Delivery', 'ip-delivery-shipping' ),
				'description' => __( 'Title to display on site', 'ip-delivery-shipping' )
			)
		);
		
		add_settings_field(
			'base_cost',
			__( 'Base Cost', 'ip-delivery-shipping' ),
			array( $this, 'number_field_callback' ),
			'ip-delivery-settings',
			'delivery_general_section',
			array(
				'id' => 'base_cost',
				'default' => '0',
				'description' => __( 'Base cost for delivery. Can be modified by filters.', 'ip-delivery-shipping' ) . ' <a href="https://github.com/pekarskyi/ip-delivery-shipping" target="_blank">' . __( 'Learn more about filters', 'ip-delivery-shipping' ) . '</a>',
				'min' => '0',
				'step' => '0.01'
			)
		);
		
		// Додаємо поля для секції "API Settings"
		add_settings_field(
			'public_key',
			__( 'API Public Key', 'ip-delivery-shipping' ),
			array( $this, 'text_field_callback' ),
			'ip-delivery-settings',
			'delivery_api_section',
			array(
				'id' => 'public_key',
				'default' => '',
				'description' => __( 'Public key for Delivery API access', 'ip-delivery-shipping' )
			)
		);
		
		add_settings_field(
			'secret_key',
			__( 'API Secret Key', 'ip-delivery-shipping' ),
			array( $this, 'password_field_callback' ),
			'ip-delivery-settings',
			'delivery_api_section',
			array(
				'id' => 'secret_key',
				'default' => '',
				'description' => __( 'Secret key for Delivery API access', 'ip-delivery-shipping' )
			)
		);
		
		// Додаємо поля для секції "Checkout Options"
		add_settings_field(
			'two_column_fields',
			__( 'Display Phone and Email fields in two columns', 'ip-delivery-shipping' ),
			array( $this, 'checkbox_field_callback' ),
			'ip-delivery-settings',
			'delivery_checkout_section',
			array(
				'id' => 'two_column_fields',
				'default' => 'yes',
				'description' => __( 'Field display may be affected by your theme or other plugins. Please check how these fields are displayed.', 'ip-delivery-shipping' )
			)
		);
		
		add_settings_field(
			'hide_country_field',
			__( 'Hide Country field', 'ip-delivery-shipping' ),
			array( $this, 'checkbox_field_callback' ),
			'ip-delivery-settings',
			'delivery_checkout_section',
			array(
				'id' => 'hide_country_field',
				'default' => 'yes',
				'description' => __( 'Hide the Country field on checkout page. Uncheck to allow country selection.', 'ip-delivery-shipping' )
			)
		);
		
		// Додаємо поля для секції "Cleanup Options"
		add_settings_field(
			'delete_data',
			__( 'Delete data on uninstall', 'ip-delivery-shipping' ),
			array( $this, 'checkbox_field_callback' ),
			'ip-delivery-settings',
			'delivery_cleanup_section',
			array(
				'id' => 'delete_data',
				'default' => 'no',
				'description' => __( 'Delete all plugin data from database when the plugin is uninstalled.', 'ip-delivery-shipping' )
			)
		);
	}
	
	/**
	 * Callback для секції "General Settings"
	 */
	public function general_section_callback() {
		echo '';
	}
	
	/**
	 * Callback для секції "API Settings"
	 */
	public function api_section_callback() {
		echo '<p class="delivery-section-description">' . sprintf(
			__( 'To get API keys, please visit <a href="%s" target="_blank">Delivery API Key page</a>', 'ip-delivery-shipping' ),
			esc_url( 'https://www.delivery-auto.com/uk/Account/ApiKey' )
		) . '</p>';
	}
	
	/**
	 * Callback для секції "Checkout Options"
	 */
	public function checkout_section_callback() {
		echo '';
	}
	
	/**
	 * Callback для секції "Cache Options"
	 */
	public function cache_section_callback() {
		echo '<p class="delivery-section-description">' . esc_html__( 'The plugin caches API responses for better performance. Cache lifetime: 24 hours.', 'ip-delivery-shipping' ) . '</p>';
		echo '<a href="' . esc_url( admin_url( 'admin.php?page=ip-delivery-settings&clear_cache=1&_wpnonce=' . wp_create_nonce( 'delivery_clear_cache' ) ) ) . '" class="button button-secondary" id="delivery-clear-cache">' . esc_html__( 'Clear Cache', 'ip-delivery-shipping' ) . '</a>';
	}
	
	/**
	 * Callback для секції "Cleanup Options"
	 */
	public function cleanup_section_callback() {
		echo '';
	}
	
	/**
	 * Callback для текстового поля
	 */
	public function text_field_callback( $args ) {
		$id = $args['id'];
		$default = isset($args['default']) ? $args['default'] : '';
		$description = isset($args['description']) ? $args['description'] : '';
		$value = isset($this->settings[$id]) ? $this->settings[$id] : $default;
		
		echo '<input type="text" id="' . esc_attr($id) . '" name="delivery_settings[' . esc_attr($id) . ']" value="' . esc_attr($value) . '" class="delivery-text-input" />';
		if (!empty($description)) {
			echo '<p class="delivery-field-description">' . $description . '</p>';
		}
	}
	
	/**
	 * Callback для поля пароля
	 */
	public function password_field_callback( $args ) {
		$id = $args['id'];
		$default = isset($args['default']) ? $args['default'] : '';
		$description = isset($args['description']) ? $args['description'] : '';
		$value = isset($this->settings[$id]) ? $this->settings[$id] : $default;
		
		echo '<input type="password" id="' . esc_attr($id) . '" name="delivery_settings[' . esc_attr($id) . ']" value="' . esc_attr($value) . '" class="delivery-text-input" />';
		if (!empty($description)) {
			echo '<p class="delivery-field-description">' . $description . '</p>';
		}
	}
	
	/**
	 * Callback для числового поля
	 */
	public function number_field_callback( $args ) {
		$id = $args['id'];
		$default = isset($args['default']) ? $args['default'] : '';
		$description = isset($args['description']) ? $args['description'] : '';
		$min = isset($args['min']) ? $args['min'] : '';
		$max = isset($args['max']) ? $args['max'] : '';
		$step = isset($args['step']) ? $args['step'] : '';
		$value = isset($this->settings[$id]) ? $this->settings[$id] : $default;
		
		$attr = '';
		if ($min !== '') $attr .= ' min="' . esc_attr($min) . '"';
		if ($max !== '') $attr .= ' max="' . esc_attr($max) . '"';
		if ($step !== '') $attr .= ' step="' . esc_attr($step) . '"';
		
		echo '<input type="number" id="' . esc_attr($id) . '" name="delivery_settings[' . esc_attr($id) . ']" value="' . esc_attr($value) . '"' . $attr . ' class="delivery-text-input" />';
		if (!empty($description)) {
			echo '<p class="delivery-field-description">' . $description . '</p>';
		}
	}
	
	/**
	 * Callback для поля-прапорця
	 */
	public function checkbox_field_callback( $args ) {
		$id = $args['id'];
		$default = isset($args['default']) ? $args['default'] : 'no';
		$description = isset($args['description']) ? $args['description'] : '';
		$value = isset($this->settings[$id]) ? $this->settings[$id] : $default;
		$checked = $value === 'yes' ? 'checked' : '';
		
		// Використовуємо Toggle Switch замість звичайного чекбоксу
		echo '<div class="delivery-checkbox-container">';
		echo '<label class="delivery-toggle-switch" for="' . esc_attr($id) . '">';
		echo '<input type="checkbox" id="' . esc_attr($id) . '" name="delivery_settings[' . esc_attr($id) . ']" value="yes" ' . $checked . ' class="delivery-checkbox" />';
		echo '<span class="delivery-toggle-slider"></span>';
		echo '</label>';
		echo '<span class="delivery-enable-text">' . __( 'Enable', 'ip-delivery-shipping' ) . '</span>';
		echo '</div>';
		
		if (!empty($description)) {
			echo '<p class="delivery-field-description delivery-checkbox-description">' . $description . '</p>';
		}
	}
	
	/**
	 * Санітація налаштувань перед збереженням
	 */
	public function sanitize_settings( $input ) {
		$sanitized_input = array();
		
		// Для прапорців встановлюємо значення 'no', якщо вони не вибрані
		$checkboxes = array( 'enabled', 'two_column_fields', 'hide_country_field', 'delete_data' );
		foreach ( $checkboxes as $checkbox ) {
			$sanitized_input[$checkbox] = isset( $input[$checkbox] ) ? 'yes' : 'no';
		}
		
		// Санітизуємо текстові поля
		if ( isset( $input['title'] ) ) {
			$sanitized_input['title'] = sanitize_text_field( $input['title'] );
		}
		
		if ( isset( $input['public_key'] ) ) {
			$sanitized_input['public_key'] = sanitize_text_field( $input['public_key'] );
		}
		
		if ( isset( $input['secret_key'] ) ) {
			$sanitized_input['secret_key'] = sanitize_text_field( $input['secret_key'] );
		}
		
		// Санітизуємо числові поля
		if ( isset( $input['base_cost'] ) ) {
			$sanitized_input['base_cost'] = floatval( $input['base_cost'] );
		}
		
		// Зберігаємо санітизовані налаштування в нашій БД
		$this->save_settings_to_db( $sanitized_input );
		
		// Оновлюємо внутрішній масив налаштувань
		$this->settings = $sanitized_input;
		
		return $sanitized_input;
	}
	
	/**
	 * Рендеримо сторінку налаштувань
	 */
	public function render_settings_page() {
		// Перевіряємо, чи було успішне збереження налаштувань через стандартний механізм WordPress
		$is_updated = isset($_GET['settings-updated']) && $_GET['settings-updated'] === 'true';
		
		if ($is_updated) {
			$this->add_notice(__('Settings saved successfully.', 'ip-delivery-shipping'), 'success');
		}
		?>
		<div class="wrap delivery-settings-wrap">
			<h1 class="delivery-settings-title"><?php echo esc_html__( 'Delivery Settings', 'ip-delivery-shipping' ); ?></h1>
			
			<?php 
			// Виводимо всі повідомлення
			$this->display_notices();
			?>
			
			<form method="post" action="options.php" class="delivery-settings-form">
				<?php
				// Виводимо приховані поля
				settings_fields( 'delivery_settings_group' );
				
				// Виводимо секції налаштувань
				do_settings_sections( 'ip-delivery-settings' );
				
				// Додаємо власний клас до кнопки збереження
				echo '<div class="delivery-settings-submit">';
				submit_button( '', 'primary delivery-submit-button', 'submit', false );
				echo '</div>';
				?>
			</form>
		</div>
		
		<script type="text/javascript">
			jQuery(document).ready(function($) {
				// Додаємо унікальні класи до форми
				$('.delivery-settings-wrap .form-table').addClass('delivery-form-table');
				$('.delivery-settings-wrap h2').addClass('delivery-section-title');
				$('.delivery-settings-wrap th').addClass('delivery-field-label');
				$('.delivery-settings-wrap td').addClass('delivery-field-input');
				$('.delivery-settings-wrap input[type="text"], .delivery-settings-wrap input[type="password"], .delivery-settings-wrap input[type="number"]').addClass('delivery-text-input');
				$('.delivery-settings-wrap .description').addClass('delivery-field-description');
				
				// Додаємо клас для кнопки очищення кешу
				$('#delivery-clear-cache').addClass('delivery-clear-cache-button');
				
				// Обробник закриття повідомлення
				$('.delivery-settings-notice .close-btn').on('click', function() {
					$(this).closest('.delivery-settings-notice').fadeOut(300);
				});
				
				// Автоматичне закриття повідомлення через 5 секунд
				setTimeout(function() {
					$('.delivery-settings-notice').fadeOut(500);
				}, 5000);
			});
		</script>
		<?php
	}
	
	/**
	 * Виводить всі повідомлення
	 */
	private function display_notices() {
		if (empty($this->notices)) {
			return;
		}
		
		foreach ($this->notices as $notice) {
			$type = isset($notice['type']) ? sanitize_html_class($notice['type']) : 'success';
			$message = isset($notice['message']) ? wp_kses_post($notice['message']) : '';
			
			if (empty($message)) {
				continue;
			}
			
			echo '<div class="delivery-settings-notice ' . esc_attr($type) . '">';
			echo '<p>' . $message . '</p>';
			echo '<span class="close-btn">×</span>';
			echo '</div>';
		}
	}
}

// Ініціалізація класу
new Delivery_Settings_Page(); 