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
		
		// Додаємо фільтри для зміни полів адреси і динамічного оновлення
		add_filter( 'woocommerce_checkout_fields', array( $this, 'modify_billing_fields' ), 99 );
		add_filter( 'woocommerce_update_order_review_fragments', array( $this, 'update_checkout_fragments' ), 99 );
		
		// Додаємо JS для перемикання методу доставки
		add_action( 'wp_footer', array( $this, 'delivery_checkout_js' ) );
	}

	/**
	 * Перевіряє, чи обрано метод доставки Delivery.
	 *
	 * @return bool
	 */
	public function is_delivery_method_selected() {
		if ( ! function_exists( 'WC' ) || ! isset( WC()->session ) ) {
			return false;
		}

		$chosen_methods = WC()->session->get( 'chosen_shipping_methods' );
		
		if ( ! is_array( $chosen_methods ) ) {
			return false;
		}
		
		foreach ( $chosen_methods as $chosen_method ) {
			// Для зон доставки формат буде "ip_delivery:N" де N - ID екземпляра
			if ( strpos( $chosen_method, 'ip_delivery' ) === 0 ) {
				// Зберігаємо обраний метод в сесії для подальшого використання
				WC()->session->set( 'ip_delivery_chosen_method', $chosen_method );
				return true;
			}
		}
		
		// Спробуємо також перевірити в $_POST, якщо в сесії не знайдено
		if ( isset( $_POST['shipping_method'] ) && is_array( $_POST['shipping_method'] ) ) {
			foreach ( $_POST['shipping_method'] as $method ) {
				if ( strpos( $method, 'ip_delivery' ) === 0 ) {
					return true;
				}
			}
		}
		
		return false;
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

		if ( ! $this->is_delivery_method_selected() ) {
			return;
		}
		 
		$settings = get_option( 'woocommerce_ip_delivery_settings' );
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
				// Перевіряємо, чи обрано метод доставки Delivery
				let selectedMethod = false;
				jQuery('input[name^="shipping_method["]').each(function() {
					if(jQuery(this).is(':checked') && jQuery(this).val().indexOf('ip_delivery') === 0) {
						selectedMethod = true;
					}
				});
				
				if(selectedMethod) {
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

	/**
	 * Validate checkout process.
	 */
	public function checkout_process() {
		// Перевіряємо чи WooCommerce активний.
		if ( ! function_exists( 'WC' ) ) {
			return;
		}

		if ( ! $this->is_delivery_method_selected() ) {
			return;
		}
		
		// Перевіряємо, чи заповнені обов'язкові поля.
		if ( empty( $_POST['delivery_delivery_name'] ) ) {
			wc_add_notice( __( 'Please select a region for Delivery shipping', 'ip-delivery-shipping' ), 'error' );
		}
		
		if ( empty( $_POST['delivery_city_name'] ) ) {
			wc_add_notice( __( 'Please select a city for Delivery shipping', 'ip-delivery-shipping' ), 'error' );
		}
		
		if ( empty( $_POST['delivery_warehouses_name'] ) ) {
			wc_add_notice( __( 'Please select a warehouse for Delivery shipping', 'ip-delivery-shipping' ), 'error' );
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
	 * Checkout field.
	 *
	 * @param WC_Checkout $checkout Checkout object.
	 */
	public function checkout_field( $checkout ) {
		// Перевіряємо, чи обрано метод доставки Delivery
		if ( ! $this->is_delivery_method_selected() ) {
			return;
		}

		$api = new Delivery_API();
		$areas = $api->get_areas();

		// Аналізуємо структуру відповіді API для розуміння правильних ключів
		if (!empty($areas)) {
			if (isset($areas['data']) && is_array($areas['data'])) {
				$areas = $areas['data']; // Використовуємо поле data, якщо існує
			} else if (isset($areas['Data']) && is_array($areas['Data'])) {
				$areas = $areas['Data']; // Або поле Data з великої літери
			}
		}

		$delivery_region = WC()->session->get( 'ip_delivery_region' );
		$delivery_city = WC()->session->get( 'ip_delivery_city' );
		$delivery_warehouse = WC()->session->get( 'ip_delivery_warehouse' );

		$delivery_region_name = WC()->session->get( 'ip_delivery_region_name' );
		$delivery_city_name = WC()->session->get( 'ip_delivery_city_name' );
		$delivery_warehouse_name = WC()->session->get( 'ip_delivery_warehouse_name' );

		// Set default area, cities and warehouses.
		$areas_options    = array( '' => __( 'Select Region', 'ip-delivery-shipping' ) );
		$cities_options   = array( '' => __( 'Select City', 'ip-delivery-shipping' ) );
		$warehouses_options = array( '' => __( 'Select Warehouse', 'ip-delivery-shipping' ) );

		// Get cities and warehouses for the selected area.
		$cities     = array();
		$warehouses = array();

		// Fill areas options.
		if ( is_array( $areas ) ) {
			foreach ( $areas as $area ) {
				// Перевіряємо структуру даних та типи
				if (is_array($area) && isset($area['id']) && isset($area['description'])) {
					// Переконуємося, що id можна використовувати як ключ масиву
					$area_id = (string) $area['id'];
					$areas_options[$area_id] = $area['description'];
				} else if (is_array($area) && isset($area['Id']) && isset($area['Description'])) {
					// Альтернативна структура з великими літерами
					$area_id = (string) $area['Id'];
					$areas_options[$area_id] = $area['Description'];
				}
			}
		}

		// Fill cities options if area is selected.
		if ( $delivery_region ) {
			$cities = $api->get_cities( $delivery_region );
			if ( is_array( $cities ) ) {
				foreach ( $cities as $city ) {
					// Перевіряємо структуру даних та типи
					if (is_array($city) && isset($city['id']) && isset($city['description'])) {
						$city_id = (string) $city['id'];
						$cities_options[$city_id] = $city['description'];
					} else if (is_array($city) && isset($city['Id']) && isset($city['Description'])) {
						// Альтернативна структура з великими літерами
						$city_id = (string) $city['Id'];
						$cities_options[$city_id] = $city['Description'];
					}
				}
			}
		}

		// Fill warehouses options if city is selected.
		if ( $delivery_city ) {
			$warehouses = $api->get_warehouses( $delivery_city );
			if ( is_array( $warehouses ) ) {
				foreach ( $warehouses as $warehouse ) {
					// Перевіряємо структуру даних та типи
					if (is_array($warehouse) && isset($warehouse['id']) && isset($warehouse['description'])) {
						$warehouse_id = (string) $warehouse['id'];
						$warehouses_options[$warehouse_id] = $warehouse['description'];
					} else if (is_array($warehouse) && isset($warehouse['Id']) && isset($warehouse['Description'])) {
						// Альтернативна структура з великими літерами
						$warehouse_id = (string) $warehouse['Id'];
						$warehouses_options[$warehouse_id] = $warehouse['Description'];
					}
				}
			}
		}

		// Include customized checkout fields template.
		$template_file = DELIVERY_ABSPATH . 'templates/checkout-fields.php';
		if (file_exists($template_file)) {
			include_once $template_file;
		} else {
			// Якщо шаблону немає, виводимо просту форму
			?>
			<div id="delivery-fields">
				<h3><?php esc_html_e( 'Delivery Service', 'ip-delivery-shipping' ); ?></h3>
				
				<p class="form-row" id="delivery_field">
					<label for="delivery"><?php esc_html_e( 'Region', 'ip-delivery-shipping' ); ?></label>
					<select name="delivery" id="delivery" class="input-select">
						<option value=""><?php esc_html_e( 'Select Region', 'ip-delivery-shipping' ); ?></option>
						<?php 
						if (is_array($areas)) {
							foreach ($areas as $area) {
								echo '<option value="' . esc_attr($area['id']) . '">' . esc_html($area['description']) . '</option>';
							}
						}
						?>
					</select>
				</p>
				
				<p class="form-row" id="city_field">
					<label for="city"><?php esc_html_e( 'City', 'ip-delivery-shipping' ); ?></label>
					<select name="city" id="city" class="input-select" disabled>
						<option value=""><?php esc_html_e( 'Select City', 'ip-delivery-shipping' ); ?></option>
					</select>
				</p>
				
				<p class="form-row" id="warehouses_field">
					<label for="warehouses"><?php esc_html_e( 'Warehouse', 'ip-delivery-shipping' ); ?></label>
					<select name="warehouses" id="warehouses" class="input-select" disabled>
						<option value=""><?php esc_html_e( 'Select Warehouse', 'ip-delivery-shipping' ); ?></option>
					</select>
				</p>
				
				<input type="hidden" name="delivery_delivery_name" class="delivery_name" id="delivery_name" value="">
				<input type="hidden" name="delivery_city_name" class="delivery_city_name" id="delivery_city_name" value="">
				<input type="hidden" name="delivery_warehouses_name" class="delivery_warehouses_name" id="delivery_warehouses_name" value="">
			</div>
			<?php
		}
	}

	/**
	 * Модифікуємо поля адреси на чекауті
	 *
	 * @param array $fields Поля чекауту
	 * @return array
	 */
	public function modify_billing_fields( $fields ) {
		// Поля, які потрібно вимкнути при виборі доставки Delivery
		$fields_to_remove = array(
			'billing_address_1',
			'billing_address_2',
			'billing_city',
			'billing_postcode',
			'billing_state',
			'billing_company'
		);
		
		// Перевіряємо, чи обрано метод доставки Delivery
		if ( $this->is_delivery_method_selected() ) {
			// Видаляємо поля з форми повністю
			foreach ( $fields_to_remove as $field_id ) {
				if ( isset( $fields['billing'][ $field_id ] ) ) {
					unset( $fields['billing'][ $field_id ] );
				}
			}
		}
		
		return $fields;
	}
	
	/**
	 * Додаємо JavaScript для динамічного перемикання полів
	 */
	public function delivery_checkout_js() {
		// Додаємо скрипт тільки на сторінці чекауту
		if ( ! is_checkout() ) {
			return;
		}
		?>
		<script type="text/javascript">
			jQuery(document).ready(function($) {
				// Функція для збереження значень полів
				function saveFieldValues() {
					var fieldValues = {};
					
					// Збираємо значення всіх полів форми
					$('.woocommerce-billing-fields__field-wrapper input, .woocommerce-billing-fields__field-wrapper select, .woocommerce-billing-fields__field-wrapper textarea').each(function() {
						var field = $(this);
						var id = field.attr('id');
						if (id) {
							fieldValues[id] = field.val();
						}
					});
					
					return fieldValues;
				}
				
				// Функція для відновлення значень полів
				function restoreFieldValues(values) {
					// Відновлюємо значення полів
					$.each(values, function(id, value) {
						$('#' + id).val(value);
					});
				}
				
				// При зміні методу доставки оновлюємо всю форму
				$(document.body).on('change', 'input[name^="shipping_method["]', function() {
					// Зберігаємо значення полів перед оновленням
					var values = saveFieldValues();
					
					// Блокуємо форму під час оновлення
					$('.woocommerce-billing-fields__field-wrapper').block({
						message: null,
						overlayCSS: {
							background: '#fff',
							'z-index': 1000000,
							opacity: 0.3
						}
					});
					
					// Оновлюємо чекаут
					$(document.body).trigger('update_checkout');
					
					// Запам'ятовуємо значення для відновлення після оновлення
					$(document.body).data('delivery_field_values', values);
				});
				
				// При оновленні чекауту відновлюємо збережені значення
				$(document.body).on('updated_checkout', function() {
					// Перевіряємо, чи є збережені значення
					var savedValues = $(document.body).data('delivery_field_values');
					if (savedValues) {
						// Відновлюємо значення полів
						setTimeout(function() {
							restoreFieldValues(savedValues);
							// Очищаємо збережені значення
							$(document.body).removeData('delivery_field_values');
							// Розблокуємо форму
							$('.woocommerce-billing-fields__field-wrapper').unblock();
						}, 100);
					}
				});
			});
		</script>
		<?php
	}

	/**
	 * Додаємо частину форми до фрагментів оновлення
	 *
	 * @param array $fragments Фрагменти
	 * @return array
	 */
	public function update_checkout_fragments( $fragments ) {
		// Отримуємо об'єкт чекауту
		$checkout = WC()->checkout();
		
		// Починаємо буферизацію виводу
		ob_start();
		
		// Виводимо обгортку полів
		echo '<div class="woocommerce-billing-fields__field-wrapper">';
		
		// Отримуємо поля білінгу після обробки нашим фільтром
		$fields = $checkout->get_checkout_fields( 'billing' );
		
		// Зберігаємо значення у змінних
		$values = array();
		foreach ( $fields as $key => $field ) {
			$values[$key] = $checkout->get_value( $key );
		}
		
		// Виводимо кожне поле
		foreach ( $fields as $key => $field ) {
			woocommerce_form_field( $key, $field, $values[$key] );
		}
		
		echo '</div>';
		
		// Отримуємо вміст буфера
		$billing_fields_html = ob_get_clean();
		
		// Додаємо фрагмент до масиву
		$fragments['.woocommerce-billing-fields__field-wrapper'] = $billing_fields_html;
		
		return $fragments;
	}
} 