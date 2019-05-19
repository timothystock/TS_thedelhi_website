<html>
<head>
<?php

/*

This template implements a basic, all-purpose print-out of the customer's order and delivery details. You may wish to customise it to suit your needs. If you only wish to make minor ammendments to this basic template, then you can also use WordPress filters. For basic formatting changes, you may only need to take note of the CSS classes and IDs used below and add a style-sheet.

IMPORTANT: If editing this file, then do not edit it directly within the plugin - if you do, then you will lose all of your changes when you update the plugin. Instead, copy it, and place it as templates/cloud-print.php within the folder of either your child theme or your theme. Alternatively, use the filter woocommerce_printorders_printtemplate to indicate a different location.

Available variables: $order (WooCommerce order object) and (redundant / for convenience - can be gained from $order), $order_id and $order_items ( = $order->get_items()) (which is filtered by woocommerce_printorders_print_order_items).

You can use a third parameter to get_detail() of 'billing_' to get billing addresses instead of shipping, if preferred.

Master template last edited: 23rd Mar 2018

*/

if (!defined('ABSPATH')) die('No direct access allowed');
?>
<style type="text/css">
	/* "When rendering with the core fonts dompdf only supports characters that are covered by the Windows ANSI encoding" - https://github.com/dompdf/dompdf/issues/626.
	So, if printing other characters, you should specify another font - as we do here (specifying DejaVu Serif)
	*/
	html, body { font-family: DejaVu Serif, sans-serif; margin-bottom: 20px; } 
	p.itemmeta { font-size: 90%; padding: 0 0 0 20px; margin: 1px; }
	<?php echo apply_filters('woocommerce_printorders_css', ''); ?>
</style>
</head>
<body>
<?php do_action('woocommerce_cloudprint_internaloutput_header', $order, 'text/html'); ?>
<p id="customer-details">
	<?php echo htmlspecialchars($this->get_detail($order, 'first_name').' '.htmlspecialchars($this->get_detail($order, 'last_name'))); ?>

	<?php
	$company = $this->get_detail($order, 'company');
	
	$completed_date = $this->get_order_date($order);
	
	$customer_note = is_callable(array($order, 'get_customer_note')) ? $order->get_customer_note() : $order->customer_note;
	
	if (!empty($company)) {
		?> (<?php echo htmlspecialchars($this->get_detail($order, 'company'));?>)
	<?php } ?>
	<br>
	<?php echo htmlspecialchars($this->get_detail($order, 'address_1'));?>
	<?php
	$address2 = $this->get_detail($order, 'address_2');
	if (!empty($address2)) echo ", ".htmlspecialchars($this->get_detail($order, 'address_2'));
	?>
	, <?php echo $this->get_detail($order, 'city');?>, <?php echo htmlspecialchars($this->get_detail($order, 'state'));?>, <?php echo htmlspecialchars($this->get_detail($order, 'postcode')); ?>

	<?php
	$country = $this->get_detail($order, 'country');
	if ($country) echo ", ".htmlspecialchars($country);
	?>

	<?php
	$phone = $this->get_detail($order, 'phone', 'billing_');
	if (!empty($phone)) echo "<br>\n".__('Phone', 'woocommerce').": ".htmlspecialchars($phone);
	?>
</p>

<p id="order-summary"><b><?php printf(__( 'Order number: %s', 'woocommerce'), '</b>'.htmlspecialchars($order->get_order_number()));?><br>
	<b><?php printf(__('Order date: %s', 'woocommerce'), '</b>'.get_date_from_gmt(gmdate('Y-m-d H:i:s', $completed_date), wc_date_format().' H:i:s'));?> <br>
	<?php if ($customer_note) { ?>
		<br><b><?php _e('Customer Note:', 'woocommerce');?></b> <?php echo htmlspecialchars($customer_note); ?>
	<?php } ?>
</p>

<?php

if (!is_array($order_items)) return;

$total_without_tax = 0;
$total_tax = 0;

$line_items = '';
global $woocommerce;

foreach ($order_items as $itemkey => $item) {

	// Interesting keys: name, type ( = 'line_item' for both single + variable products in our tests), qty, product_id, variation_id (present but empty for simple items), line_subtotal, line_total, line_tax, line_subtotal_tax
	// Then there are keys for the variations, e.g. Meat => Lamb
	// $customer_info .= '<pre>'.print_r($item, true).'</pre>';

	$line_items .= '<p class="line-item">';

	if ($item['type'] != 'line_item') {
		$line_items .= "Error: Item was not a line item: ".htmlspecialchars($itemkey)."</p>";
		continue;
	}

	$qty = $item['qty'];

	$product = $order->get_product_from_item($item);

	// Interesting keys: price, post->post_title, product_type=simple|variation, (if variation) variation_data => array('attribute_meta' => 'prawn')
	// Could be: WC_Product_Simple, WC_Product_Variation, etc.

	if (!$product->exists()) continue;

	$item_name = apply_filters('woocommerce_print_orders_item_name', $product->get_title(), $product, 'text/html');

	/*
	This section is redundant, since it appears in the item_meta->display() output
			$variations = '';
			if ($product->product_type == 'variation') {
				foreach ($product->variation_data as $attr => $choice) {
					if (strpos($attr, 'attribute_') === 0) {
						$att_text = ucfirst(substr($attr, 10)).": ".ucfirst($choice);
						$variations .= ($variations == '') ? $att_text : ", $att_text";
					}
				}
				if ($variations) $item_name .= " ($variations)";
			}
	*/

	# This approach doesn't work with product add-ons
	// $price = $product->price;
	// $cost = $price * $qty;
	$total_tax += $item['line_tax'];
	$total_without_tax += $item['line_total'];
	$cost = $item['line_total']+$item['line_tax'];
	$price = $cost/(max($qty, 1));

	$sku = $product->get_sku();
	if ($sku) $item_name .= " <span class=\"sku\">($sku)</span>";

	$line_items .= '<span class="line-item-firstline">'.sprintf('%3dx %s @ %2.2f = %.2f', $qty, $item_name, $price, $cost)."<br></span>\n";
	
	$line_items .= '<p class="itemmeta">';
	$imeta_output = false;
	
	// WC 3.0+ - this isn't suitable; see: https://github.com/woocommerce/woocommerce/issues/14623
	if (0 && function_exists('wc_display_item_meta')) {
	
		$imeta_output = wc_display_item_meta($item, array('echo' => false));
		
	} else {
	
		if (version_compare($woocommerce->version, '2.4', 'ge')) {
			$item_meta = new WC_Order_Item_Meta( $item );
		} else {
			$item_meta = new WC_Order_Item_Meta( $item['item_meta'] );
		}
		
		$imeta_output = $item_meta->display(true, true);
	
	}
	
	if ($imeta_output) {
		$line_items .= apply_filters('woocommerce_print_orders_item_meta', nl2br($imeta_output), $item, 'text/html');
	}
	
	$line_items .= "</p>\n";
}

$line_items .= '<p id="charges">';

$order_tax = is_callable(array($order, 'get_cart_tax')) ? $order->get_cart_tax() : $order->order_tax;

if ($order_tax) {
	$tax_line = sprintf('Tax: %2.2f', $order_tax)."<br>\n";
	$line_items .= apply_filters('woocommerce_print_orders_tax_line', $tax_line, $order_tax, 'text/html');
}

$order_shipping = is_callable(array($order, 'get_shipping_total')) ? $order->get_shipping_total() : $order->order_shipping;
$order_shipping_tax = is_callable(array($order, 'get_shipping_tax')) ? $order->get_shipping_tax() : $order->order_shipping_tax;

if ($order_shipping) $line_items .= sprintf(apply_filters('woocommerce_print_orders_text_shipping', __('Shipping', 'woocommerce'), 'text/html').': %2.2f', $order_shipping)."<br>\n";
if ($order_shipping_tax) $line_items .= sprintf(apply_filters('woocommerce_print_orders_text_shipping', __('Shipping', 'woocommerce'), 'text/html').' Tax: %2.2f', $order_shipping_tax)."\n";

$fees = $order->get_fees();

foreach ($fees as $fee) {

	// WC 3.0+
	if (!is_callable(array($fee, 'get_name'))) continue;
	
	$line_items .= "<br>".$fee->get_name().': '.sprintf('%2.2f', $fee->get_total());
	
	if ($fee->get_total_tax()) $line_items .= ' ('.__('Tax', 'woocommerce').': '.sprintf('%2.2f', $fee->get_total_tax()).')';
	
	$line_items .= "\n";

}

$line_items .= '</p>';

echo $line_items;

$order_total = is_callable(array($order, 'get_total')) ? $order->get_total() : $order->order_total;

?>

<p id="order-total">
	<b><?php _e('Total:', 'woocommerce');?></b>
	<span id="order-total-price"><?php echo $order_total;?></span>, 
	<?php
	
		$payment_method_title = is_callable(array($order, 'get_payment_method_title')) ? $order->get_payment_method_title() : $order->payment_method_title;
	
		echo strip_tags($payment_method_title);

		$shipping_method_title = $order->get_shipping_method();
		if (!empty($shipping_method_title)) echo ', '.strip_tags($shipping_method_title);
	?>
</p>

<?php do_action('woocommerce_cloudprint_internaloutput_footer', $order, 'text/html'); ?>
</body>
</html>
