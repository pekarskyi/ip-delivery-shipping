<?php
/**
 * Database management class.
 *
 * @package Delivery
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

/**
 * Delivery_DB Class.
 */
class Delivery_DB {
	/**
	 * Назва таблиці для даних доставки
	 *
	 * @var string
	 */
	private $table_name;

	/**
	 * Поточна версія бази даних
	 * 
	 * @var string
	 */
	private $db_version = '1.1';
    
    /**
     * Реєстрація хуків активації
     * 
     * Цей метод має викликатися ззовні перед тим, як буде створений екземпляр класу
     */
    public static function register_hooks() {
        // Хуки для реєстрації дій при активації
        register_activation_hook( DELIVERY_PLUGIN_FILE, array( __CLASS__, 'activation_callback' ) );
    }
    
    /**
     * Статичний метод, який викликається при активації плагіна
     */
    public static function activation_callback() {
        $instance = new self();
        $instance->create_tables();
    }

	/**
	 * Ініціалізація класу бази даних
	 */
	public function __construct() {
		global $wpdb;
		$this->table_name = $wpdb->prefix . 'ip_delivery_data';
		
		// Перевірка версії бази даних на випадок оновлення
		add_action( 'plugins_loaded', array( $this, 'check_db_version' ) );
	}

	/**
	 * Створення таблиці бази даних
	 */
	public function create_tables() {
		global $wpdb;

		$charset_collate = $wpdb->get_charset_collate();

		$sql = "CREATE TABLE {$this->table_name} (
			id bigint(20) NOT NULL AUTO_INCREMENT,
			order_id bigint(20) NOT NULL,
			region_id varchar(50) NOT NULL,
			region_name varchar(255) NOT NULL,
			city_id varchar(50) NOT NULL,
			city_name varchar(255) NOT NULL,
			warehouse_id varchar(50) NOT NULL,
			warehouse_name varchar(255) NOT NULL,
			created_at datetime DEFAULT CURRENT_TIMESTAMP NOT NULL,
			updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP NOT NULL,
			PRIMARY KEY  (id),
			KEY order_id (order_id)
		) $charset_collate;";

		require_once( ABSPATH . 'wp-admin/includes/upgrade.php' );
		dbDelta( $sql );

		// Зберігаємо версію бази даних
		update_option( 'delivery_db_version', $this->db_version );
	}

	/**
	 * Перевірка версії бази даних і оновлення за потреби
	 */
	public function check_db_version() {
		global $wpdb;
		$current_db_version = get_option( 'delivery_db_version', '0' );
		
		if ( version_compare( $current_db_version, $this->db_version, '<' ) ) {
			// Створюємо нову таблицю
			$this->create_tables();
			
			// Оновлюємо версію бази даних
			update_option( 'delivery_db_version', $this->db_version );
		}
	}

	/**
	 * Додавання або оновлення даних доставки для замовлення
	 *
	 * @param int $order_id ID замовлення
	 * @param array $data Дані доставки
	 * @return int|false ID запису або false при помилці
	 */
	public function save_delivery_data( $order_id, $data ) {
		global $wpdb;

		// Перевіряємо чи існує запис для цього замовлення
		$existing_id = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT id FROM {$this->table_name} WHERE order_id = %d",
				$order_id
			)
		);

		// Дані для запису
		$record = array(
			'order_id'       => $order_id,
			'region_id'      => isset( $data['region_id'] ) ? $data['region_id'] : '',
			'region_name'    => isset( $data['region_name'] ) ? $data['region_name'] : '',
			'city_id'        => isset( $data['city_id'] ) ? $data['city_id'] : '',
			'city_name'      => isset( $data['city_name'] ) ? $data['city_name'] : '',
			'warehouse_id'   => isset( $data['warehouse_id'] ) ? $data['warehouse_id'] : '',
			'warehouse_name' => isset( $data['warehouse_name'] ) ? $data['warehouse_name'] : '',
		);

		// Формат даних
		$format = array(
			'%d', // order_id
			'%s', // region_id
			'%s', // region_name
			'%s', // city_id
			'%s', // city_name
			'%s', // warehouse_id
			'%s', // warehouse_name
		);

		if ( $existing_id ) {
			// Оновлюємо існуючий запис
			$result = $wpdb->update(
				$this->table_name,
				$record,
				array( 'id' => $existing_id ),
				$format,
				array( '%d' ) // формат для id
			);

			return $result !== false ? $existing_id : false;
		} else {
			// Додаємо новий запис
			$result = $wpdb->insert(
				$this->table_name,
				$record,
				$format
			);

			return $result ? $wpdb->insert_id : false;
		}
	}

	/**
	 * Отримання даних доставки для замовлення
	 *
	 * @param int $order_id ID замовлення
	 * @return array|false Дані доставки або false якщо не знайдено
	 */
	public function get_delivery_data( $order_id ) {
		global $wpdb;

		$data = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM {$this->table_name} WHERE order_id = %d",
				$order_id
			),
			ARRAY_A
		);

		return $data;
	}

	/**
	 * Видалення даних доставки для замовлення
	 *
	 * @param int $order_id ID замовлення
	 * @return int|false Кількість видалених рядків або false
	 */
	public function delete_delivery_data( $order_id ) {
		global $wpdb;

		return $wpdb->delete(
			$this->table_name,
			array( 'order_id' => $order_id ),
			array( '%d' )
		);
	}

	/**
	 * Видалення всіх даних плагіна
	 * 
	 * @return int|false Кількість видалених рядків або false
	 */
	public function delete_all_data() {
		global $wpdb;
		
		return $wpdb->query( "TRUNCATE TABLE {$this->table_name}" );
	}

	/**
	 * Видалення таблиці плагіна
	 */
	public function drop_table() {
		global $wpdb;
		
		$wpdb->query( "DROP TABLE IF EXISTS {$this->table_name}" );
		delete_option( 'delivery_db_version' );
	}

	/**
	 * Отримання назви таблиці
	 * 
	 * @return string Назва таблиці
	 */
	public function get_table_name() {
		return $this->table_name;
	}
} 