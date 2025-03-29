<?php
/**
 * Checkout fields template
 *
 * @package Delivery
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}
?>

<div id="delivery-fields">
	<h3><?php esc_html_e( 'Delivery Service', 'ip-delivery-shipping' ); ?></h3>
	
	<p class="form-row" id="delivery_field">
		<label for="delivery"><?php esc_html_e( 'Region', 'ip-delivery-shipping' ); ?></label>
		<select name="delivery" id="delivery" class="input-select">
			<?php foreach ( $areas_options as $value => $label ) : ?>
				<option value="<?php echo esc_attr( $value ); ?>" <?php selected( $delivery_region, $value ); ?>>
					<?php echo esc_html( $label ); ?>
				</option>
			<?php endforeach; ?>
		</select>
	</p>
	
	<p class="form-row" id="city_field">
		<label for="city"><?php esc_html_e( 'City', 'ip-delivery-shipping' ); ?></label>
		<select name="city" id="city" class="input-select" <?php echo empty( $delivery_region ) ? 'disabled' : ''; ?>>
			<?php foreach ( $cities_options as $value => $label ) : ?>
				<option value="<?php echo esc_attr( $value ); ?>" <?php selected( $delivery_city, $value ); ?>>
					<?php echo esc_html( $label ); ?>
				</option>
			<?php endforeach; ?>
		</select>
	</p>
	
	<p class="form-row" id="warehouses_field">
		<label for="warehouses"><?php esc_html_e( 'Warehouse', 'ip-delivery-shipping' ); ?></label>
		<select name="warehouses" id="warehouses" class="input-select" <?php echo empty( $delivery_city ) ? 'disabled' : ''; ?>>
			<?php foreach ( $warehouses_options as $value => $label ) : ?>
				<option value="<?php echo esc_attr( $value ); ?>" <?php selected( $delivery_warehouse, $value ); ?>>
					<?php echo esc_html( $label ); ?>
				</option>
			<?php endforeach; ?>
		</select>
	</p>
	
	<input type="hidden" name="delivery_delivery_name" class="delivery_name" id="delivery_name" value="<?php echo esc_attr( $delivery_region_name ); ?>">
	<input type="hidden" name="delivery_city_name" class="delivery_city_name" id="delivery_city_name" value="<?php echo esc_attr( $delivery_city_name ); ?>">
	<input type="hidden" name="delivery_warehouses_name" class="delivery_warehouses_name" id="delivery_warehouses_name" value="<?php echo esc_attr( $delivery_warehouse_name ); ?>">
</div> 