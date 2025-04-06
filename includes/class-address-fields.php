<?php
/**
 * Адреса полів класу.
 *
 * @package Delivery
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

/**
 * Delivery_Address_Fields Class.
 */
class Delivery_Address_Fields {
	/**
	 * Ініціалізація класу та його властивостей.
	 */
	public function __construct() {
		// Підключаємо фільтр для оновлення фрагментів форми
		add_filter( 'woocommerce_update_order_review_fragments', array( $this, 'update_form_billing' ), 99 );
		
		// Підключаємо фільтр для приховування полів
		add_filter( 'woocommerce_checkout_fields', array( $this, 'override_checkout_fields' ) );
		
		// Підключаємо скрипт для оновлення форми
		add_action( 'wp_footer', array( $this, 'script_update_shipping_method' ) );
	}

	/**
	 * Фільтр для оновлення фрагментів форми при зміні способу доставки.
	 *
	 * @param array $fragments Фрагменти.
	 * @return array
	 */
	public function update_form_billing( $fragments ) {
		$checkout = WC()->checkout();
		ob_start();

		echo '<div class="woocommerce-billing-fields__field-wrapper">';

		$fields = $checkout->get_checkout_fields( 'billing' );
		foreach ( $fields as $key => $field ) {
			if ( isset( $field['country_field'], $fields[ $field['country_field'] ] ) ) {
				$field['country'] = $checkout->get_value( $field['country_field'] );
			}
			woocommerce_form_field( $key, $field, $checkout->get_value( $key ) );
		}

		echo '</div>';

		$art_add_update_form_billing = ob_get_clean();
		$fragments['.woocommerce-billing-fields__field-wrapper'] = $art_add_update_form_billing;

		return $fragments;
	}

	/**
	 * Фільтр для приховування полів адреси при виборі доставки Delivery.
	 *
	 * @param array $fields Поля.
	 * @return array
	 */
	public function override_checkout_fields( $fields ) {
		// Отримуємо налаштування з класу доставки
		$shipping_method = $this->get_delivery_shipping_method();
		
		// Перевіряємо чи увімкнено опцію відображення полів у дві колонки
		if ( $shipping_method && 'yes' === $shipping_method->get_option( 'two_column_fields', 'yes' ) ) {
			// Розташовуємо поля телефону та електронної пошти на одній лінії
			$fields['billing']['billing_phone']['class'][0] = 'form-row-first';
			$fields['billing']['billing_email']['class'][0] = 'form-row-last';	
		}
		
		// Отримуємо обраний метод доставки
		$chosen_methods = WC()->session->get( 'chosen_shipping_methods' );
		
		// Перевіряємо, чи обрано Delivery
		if ( isset($chosen_methods[0]) && 'delivery' === $chosen_methods[0] ) {
			// Приховуємо поля адреси
			unset($fields['billing']['billing_company']);
			unset($fields['billing']['billing_address_1']);
			unset($fields['billing']['billing_address_2']);
			unset($fields['billing']['billing_city']);
			unset($fields['billing']['billing_postcode']);
			unset($fields['billing']['billing_state']);
		}
		
		return $fields;
	}

	/**
	 * Отримує об'єкт методу доставки Delivery
	 *
	 * @return Delivery_Shipping_Method|null
	 */
	private function get_delivery_shipping_method() {
		// Отримуємо всі методи доставки
		$shipping_methods = WC()->shipping->get_shipping_methods();
		
		// Перевіряємо, чи є метод доставки Delivery
		if ( isset( $shipping_methods['delivery'] ) ) {
			return $shipping_methods['delivery'];
		}
		
		return null;
	}

	/**
	 * Додаємо JavaScript для оновлення форми при зміні способу доставки.
	 */
	public function script_update_shipping_method() {
		if ( is_checkout() ) {
			// Отримуємо налаштування з класу доставки
			$shipping_method = $this->get_delivery_shipping_method();
			$hide_country = $shipping_method && 'yes' === $shipping_method->get_option( 'hide_country_field', 'yes' );
			
			// Додаємо CSS тільки якщо опція активована
			if ( $hide_country ) {
			?>
			<style>
				#billing_country_field {
					display: none !important;
				}
			</style>
			<?php
			}
			?>
			<script>
				jQuery(document).ready(function ($) {
					// Оновлення форми при зміні способу доставки
					$(document.body).on('updated_checkout', function (event, xhr, data) {
						// Додаємо обробник події для зміни способу доставки
						$('input[name^="shipping_method"]').on('change', function () {
							$('.woocommerce-billing-fields__field-wrapper').block({
								message: null,
								overlayCSS: {
									background: '#fff',
									'z-index': 1000000,
									opacity: 0.3
								}
							});
							
							// Оновлюємо фрагменти форми через AJAX
							$(document.body).trigger('update_checkout');
						});
						
						// Зберігаємо значення полів для відновлення після оновлення фрагментів
						var first_name = $('#billing_first_name').val(),
							last_name = $('#billing_last_name').val(),
							phone = $('#billing_phone').val(),
							email = $('#billing_email').val();

						// Оновлюємо вміст полів форми після оновлення фрагментів
						if (xhr.fragments && xhr.fragments[".woocommerce-billing-fields__field-wrapper"]) {
							$(".woocommerce-billing-fields__field-wrapper").html(xhr.fragments[".woocommerce-billing-fields__field-wrapper"]);
							$(".woocommerce-billing-fields__field-wrapper").find('input[name="billing_first_name"]').val(first_name);
							$(".woocommerce-billing-fields__field-wrapper").find('input[name="billing_last_name"]').val(last_name);
							$(".woocommerce-billing-fields__field-wrapper").find('input[name="billing_phone"]').val(phone);
							$(".woocommerce-billing-fields__field-wrapper").find('input[name="billing_email"]').val(email);
							$('.woocommerce-billing-fields__field-wrapper').unblock();
						}
					});
				});
			</script>
			<?php
		}
	}
}

// Ініціалізуємо клас для керування полями адреси
new Delivery_Address_Fields(); 