<?php
/**
 * Email Addresses
 *
 * @author 		WooThemes
 * @package 	WooCommerce/Templates/Emails
 * @version     1.6.4
 */
?><table cellspacing="0" cellpadding="0" style="width: 100%; vertical-align: top;" border="0">

	<tr>

		<td valign="top" width="50%">

			<h3><?php _e('Billing address', 'woocommerce'); ?></h3>

			<p><?php echo $order->get_formatted_billing_address(); ?></p>

		</td>

		<td valign="top" width="50%">

		<?php if ( get_option( 'woocommerce_ship_to_billing_address_only' ) == 'no' ) : ?>

			<h3><?php _e('Shipping address', 'woocommerce'); ?></h3>

			<p><?php echo $order->get_formatted_shipping_address(); ?></p>

		<?php endif; ?>

		<?php if ($time = get_post_meta($order->id, '_openinghours_time', true)) {
			echo '<p id="openinghours_adminlabel"><strong>'.apply_filters('openinghours_adminlabel', __('Time chosen', 'openinghours')).":</strong> $time</p>";
		}
		?>

		</td>

	</tr>

</table>
