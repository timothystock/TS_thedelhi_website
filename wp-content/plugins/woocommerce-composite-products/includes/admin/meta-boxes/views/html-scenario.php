<?php
/**
 * Admin Add Scenario markup
 *
 * @version 3.9.0
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

?>
<div class="bto_scenario wc-metabox <?php echo $toggle; ?>" rel="<?php echo isset( $scenario_data[ 'position' ] ) ? $scenario_data[ 'position' ] : ''; ?>">
	<h3 class="bto_scenario_handle">
		<strong class="scenario_name">
			<?php
				if ( ! empty( $scenario_data[ 'title' ] ) ) {
					echo $scenario_data[ 'title' ];
				}
			?>
		</strong>
		<div class="handle">
			<?php
				if ( ! empty( $scenario_data[ 'scenario_id' ] ) ) {
					?><input type="hidden" name="bto_scenario_data[<?php echo $id; ?>][scenario_id]" class="scenario_id" value="<?php echo $scenario_data[ 'scenario_id' ]; ?>"/><?php
				}
			?>
			<div class="handle-item toggle-item" aria-label="<?php _e( 'Click to toggle', 'woocommerce' ); ?>"></div>
			<div class="handle-item sort-item" aria-label="<?php esc_attr_e( 'Drag and drop to set order', 'woocommerce-composite-products' ); ?>"></div>
			<a href="#" class="remove_row delete"><?php echo __( 'Remove', 'woocommerce' ); ?></a>
		</div>
	</h3>
	<div class="bto_scenario_data wc-metabox-content">
		<div class="options_group"><?php

			/**
			 * Action 'woocommerce_composite_scenario_admin_info_html'.
			 *
			 * @param  string  $scenario_id
			 * @param  array   $scenario_data
			 * @param  array   $composite_data
			 * @param  string  $composite_id
			 */
			do_action( 'woocommerce_composite_scenario_admin_info_html', $id, $scenario_data, $composite_data, $composite_id );

			?><div class="hr-section"><?php echo __( 'Configuration', 'woocommerce-composite-products' ); ?></div><?php

			/**
			 * Action 'woocommerce_composite_scenario_admin_config_html'.
			 *
			 * @param  string  $scenario_id
			 * @param  array   $scenario_data
			 * @param  array   $composite_data
			 * @param  string  $composite_id
			 */
			do_action( 'woocommerce_composite_scenario_admin_config_html', $id, $scenario_data, $composite_data, $composite_id );

			?><div class="hr-section"><?php echo __( 'Actions', 'woocommerce-composite-products' ); ?></div><?php

			/**
			 * Action 'woocommerce_composite_scenario_admin_actions_html'.
			 *
			 * @param  string  $scenario_id
			 * @param  array   $scenario_data
			 * @param  array   $composite_data
			 * @param  string  $composite_id
			 */
			do_action( 'woocommerce_composite_scenario_admin_actions_html', $id, $scenario_data, $composite_data, $composite_id );

		?></div>
	</div>
</div>
