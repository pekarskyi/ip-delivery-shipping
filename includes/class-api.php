<?php
/**
 * API class for Delivery service.
 *
 * @package Delivery
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

/**
 * Class for working with Delivery API
 */
class Delivery_API {
	/**
	 * Public API key.
	 *
	 * @var string
	 */
	protected $public_key;

	/**
	 * Secret API key.
	 *
	 * @var string
	 */
	protected $secret_key;

	/**
	 * Culture for API requests.
	 *
	 * @var string
	 */
	public $culture = 'uk-UA';

	/**
	 * Cache lifetime in seconds (24 hours).
	 *
	 * @var int
	 */
	protected $cache_time = 86400;

	/**
	 * Constructor.
	 */
	public function __construct() {
		// Отримуємо налаштування із глобальних налаштувань методу доставки
		$settings = $this->get_global_settings();
		
		$this->public_key = isset( $settings['public_key'] ) ? sanitize_text_field( $settings['public_key'] ) : '';
		$this->secret_key = isset( $settings['secret_key'] ) ? sanitize_text_field( $settings['secret_key'] ) : '';

		// Отримуємо час кешування з налаштувань
		if ( isset( $settings['cache_time'] ) && is_numeric( $settings['cache_time'] ) ) {
			$cache_time_hours = floatval( $settings['cache_time'] );
			$this->cache_time = $cache_time_hours * 3600; // Конвертуємо години в секунди
		}

		// Перевіряємо, що час кешування не менше 1 години
		if ( $this->cache_time < 3600 ) {
			$this->cache_time = 3600; // Мінімум 1 година
		}

		// Застосування фільтра для часу кешування.
		$this->cache_time = apply_filters( 'delivery_cache_time', $this->cache_time );
		$this->cache_time = absint( $this->cache_time ); // Переконуємося, що значення ціле та позитивне.
	}
	
	/**
	 * Get global settings from the shipping method.
	 * 
	 * @return array
	 */
	protected function get_global_settings() {
		$settings = array();
		
		// Отримуємо налаштування з глобального методу доставки
		$global_settings = get_option( 'woocommerce_ip_delivery_settings', array() );
		
		if ( ! empty( $global_settings ) && 
			isset( $global_settings['public_key'] ) && ! empty( $global_settings['public_key'] ) &&
			isset( $global_settings['secret_key'] ) && ! empty( $global_settings['secret_key'] ) ) {
			
			return $global_settings;
		}
		
		// Якщо глобальні налаштування відсутні, шукаємо в зонах доставки
		global $wpdb;
		$delivery_settings = $wpdb->get_results( 
			"SELECT option_name, option_value FROM $wpdb->options WHERE option_name LIKE 'woocommerce_ip_delivery_%_settings'", 
			ARRAY_A 
		);
		
		// Перебираємо всі знайдені налаштування
		foreach ( $delivery_settings as $setting ) {
			$value = maybe_unserialize( $setting['option_value'] );
			if ( is_array( $value ) && isset( $value['public_key'] ) && ! empty( $value['public_key'] ) &&
				isset( $value['secret_key'] ) && ! empty( $value['secret_key'] ) ) {
				return $value;
			}
		}
		
		return $settings;
	}

	/**
	 * Get regions list.
	 *
	 * @return array
	 */
	public function get_areas() {
		// Try to get from cache first.
		$cache_key = 'ip_delivery_regions_' . $this->culture;
		$cached_data = get_transient( $cache_key );

		if ( false !== $cached_data ) {
			return $cached_data;
		}

		$url = 'https://www.delivery-auto.com/api/v4/Public/GetRegionList';
		$params = array(
			'culture' => $this->culture,
			'apiKey'  => $this->public_key,
		);

		$result = $this->curl_get_request( $url, $params );

		// Cache the result if it's not empty.
		if ( ! empty( $result ) ) {
			set_transient( $cache_key, $result, $this->cache_time );
		}

		return $result;
	}

	/**
	 * Get cities by region.
	 *
	 * @param string $area_id Region ID.
	 * @return array
	 */
	public function get_cities( $area_id ) {
		// Try to get from cache first.
		$cache_key = 'ip_delivery_cities_' . $this->culture . '_' . $area_id;
		$cached_data = get_transient( $cache_key );

		if ( false !== $cached_data ) {
			return $cached_data;
		}

		$url = 'https://www.delivery-auto.com/api/v4/Public/GetAreasList';
		$params = array(
			'culture'  => $this->culture,
			'apiKey'   => $this->public_key,
			'regionId' => $area_id,
		);

		$result = $this->curl_get_request( $url, $params );

		// Cache the result if it's not empty.
		if ( ! empty( $result ) ) {
			set_transient( $cache_key, $result, $this->cache_time );
		}

		return $result;
	}

	/**
	 * Get warehouses by city.
	 *
	 * @param string $city_id City ID.
	 * @return array
	 */
	public function get_warehouses( $city_id ) {
		// Try to get from cache first.
		$cache_key = 'ip_delivery_warehouses_' . $this->culture . '_' . $city_id;
		$cached_data = get_transient( $cache_key );

		if ( false !== $cached_data ) {
			return $cached_data;
		}

		$url = 'https://www.delivery-auto.com/api/v4/Public/GetWarehousesList';
		$params = array(
			'culture' => $this->culture,
			'apiKey'  => $this->public_key,
			'CityId'  => $city_id,
		);

		$result = $this->curl_get_request( $url, $params );

		// Cache the result if it's not empty.
		if ( ! empty( $result ) ) {
			set_transient( $cache_key, $result, $this->cache_time );
		}

		return $result;
	}

	/**
	 * Make GET request to API.
	 *
	 * @param string $url Request URL.
	 * @param array  $params Request parameters.
	 * @return mixed
	 */
	public function curl_get_request( $url, $params ) {
		$curl = curl_init();

		// Санітизація всіх параметрів запиту.
		foreach ( $params as $key => $value ) {
			$params[ $key ] = sanitize_text_field( $value );
		}

		$url = $url . '?' . http_build_query( $params );

		curl_setopt( $curl, CURLOPT_URL, $url );
		curl_setopt( $curl, CURLOPT_RETURNTRANSFER, true );
		curl_setopt( $curl, CURLOPT_SSL_VERIFYPEER, false );
		curl_setopt( $curl, CURLOPT_SSL_VERIFYHOST, false );

		$result = curl_exec( $curl );
		curl_close( $curl );

		return json_decode( $result, true );
	}

	/**
	 * Make POST request to API.
	 *
	 * @param string $url Request URL.
	 * @param array  $data Request data.
	 * @return mixed
	 */
	public function curl_request( $url, $data ) {
		$curl = curl_init();

		// Санітизація всіх параметрів запиту.
		foreach ( $data as $key => $value ) {
			if ( is_array( $value ) ) {
				foreach ( $value as $subkey => $subvalue ) {
					if ( is_string( $subvalue ) ) {
						$data[ $key ][ $subkey ] = sanitize_text_field( $subvalue );
					}
				}
			} elseif ( is_string( $value ) ) {
				$data[ $key ] = sanitize_text_field( $value );
			}
		}

		curl_setopt( $curl, CURLOPT_URL, $url );
		curl_setopt( $curl, CURLOPT_RETURNTRANSFER, true );
		curl_setopt( $curl, CURLOPT_POST, true );
		curl_setopt( $curl, CURLOPT_POSTFIELDS, http_build_query( $data ) );
		curl_setopt( $curl, CURLOPT_HTTPHEADER, array( 'Content-Type: application/x-www-form-urlencoded' ) );
		curl_setopt( $curl, CURLOPT_SSL_VERIFYPEER, false );
		curl_setopt( $curl, CURLOPT_SSL_VERIFYHOST, false );

		$result = curl_exec( $curl );
		curl_close( $curl );

		return json_decode( $result, true );
	}
} 