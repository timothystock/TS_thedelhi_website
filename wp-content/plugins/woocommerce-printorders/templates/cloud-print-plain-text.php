<head>

<!--

This template implements a basic, all-purpose plain-text print-out of the customer's order and delivery details. It is used if the 'plain text' option is selected in the plugin (available since version 2.3.0). You may wish to customise it to suit your needs.

Note that there is a tiny amount of pseudo-markup in this file; only the body section is used for the output. So, you can put anything in this head section (e.g. comments, like these).

IMPORTANT: If editing this file, then do not edit it directly within the plugin - if you do, then you will lose all of your changes when you update the plugin. Instead, copy it, and place it as templates/cloud-print.php within the folder of either your child theme or your theme. Alternatively, use the filter woocommerce_printorders_printtemplate to indicate a different location.

Available variables: $order (WooCommerce order object) and (redundant / for convenience - can be gained from $order), $order_id and $order_items ( = $order->get_items()).

You can use a third parameter to get_detail() of 'billing_' to get billing addresses instead of shipping, if preferred.

Master template last edited: 23rd March 2018

-->

</head>
<body><?php

do_action('woocommerce_cloudprint_internaloutput_header', $order, 'text/plain');

echo $this->get_detail($order, 'first_name').' '.$this->get_detail($order, 'last_name');

$company = $this->get_detail($order, 'company');
if (!empty($company)) {
	?> (<?php echo $this->get_detail($order, 'company');?>)
<?php } ?>

<?php echo $this->get_detail($order, 'address_1');?>
<?php
$address2 = $this->get_detail($order, 'address_2');
$country = $this->get_detail($order, 'country');
if (!empty($address2)) echo ", ".$this->get_detail($order, 'address_2');
?>
, <?php echo $this->get_detail($order, 'city');?>, <?php echo $this->get_detail($order, 'state');?>, <?php echo $this->get_detail($order, 'postcode'); ?> <?php
if ($country) echo ", ".$country;	
?>

<?php
$phone = $this->get_detail($order, 'phone', 'billing_');
if (!empty($phone)) echo "\n".__('Phone', 'woocommerce').": ".$phone;
?>


<?php printf(__( 'Order number: %s', 'woocommerce'), $order->get_order_number());?>

<?php

$completed_date = $this->get_order_date($order);

echo sprintf(__('Order date: %s', 'woocommerce'), strip_tags(date_i18n(wc_date_format(), $completed_date)))."\n";

$customer_note = is_callable(array($order, 'get_customer_note')) ? $order->get_customer_note() : $order->customer_note;

if ($customer_note) {
	echo __('Customer Note:', 'woocommerce').' '.$customer_note."\n";
}

if (!is_array($order_items)) return;

$total_without_tax = 0;
$total_tax = 0;

$line_items = '';
global $woocommerce;

foreach ($order_items as $itemkey => $item) {

	// Interesting keys: name, type ( = 'line_item' for both single + variable products in our tests), qty, product_id, variation_id (present but empty for simple items), line_subtotal, line_total, line_tax, line_subtotal_tax
	// Then there are keys for the variations, e.g. Meat => Lamb
	// $customer_info .= '<pre>'.print_r($item, true).'</pre>';

	if ($item['type'] != 'line_item') {
		$line_items .= "Error: Item was not a line item: ".$itemkey."\n";
		continue;
	}

	$qty = $item['qty'];

	$product = $order->get_product_from_item($item);

	# Interesting keys: price, post->post_title, product_type=simple|variation, (if variation) variation_data => array('attribute_meta' => 'prawn')
	# Could be: WC_Product_Simple, WC_Product_Variation, etc.

	if (!$product->exists()) continue;

	$item_name = apply_filters('woocommerce_print_orders_item_name', $product->get_title(), $product, 'text/plain');

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

	$total_tax += $item['line_tax'];
	$total_without_tax += $item['line_total'];
	$cost = $item['line_total']+$item['line_tax'];
	$price = $cost/(max($qty, 1));

	$sku = $product->get_sku();
	if ($sku) $item_name .= " ($sku)";

	$line_items .= sprintf('%3dx %s @ %2.2f = %.2f', $qty, $item_name, $price, $cost)."\n";
	
	if (version_compare($woocommerce->version, '2.4', 'ge')) {
		$item_meta = new WC_Order_Item_Meta( $item );
	} else {
		$item_meta = new WC_Order_Item_Meta( $item['item_meta'] );
	}
	$imeta_output = $item_meta->display(true, true);
	if ($imeta_output) {
		$line_items .= apply_filters('woocommerce_print_orders_item_meta', nl2br($imeta_output), $item['item_meta'], 'text/plain')."\n";
	}
}

$order_tax = is_callable(array($order, 'get_cart_tax')) ? $order->get_cart_tax() : $order->order_tax;

if ($order_tax) {
	$tax_line = sprintf('Tax: %2.2f', $order_tax)."
\n";
	$line_items .= apply_filters('woocommerce_print_orders_tax_line', $tax_line, $order_tax, 'text/plain');
}

echo "\n";

$order_shipping = is_callable(array($order, 'get_shipping_total')) ? $order->get_shipping_total() : $order->order_shipping;
$order_shipping_tax = is_callable(array($order, 'get_shipping_tax')) ? $order->get_shipping_tax() : $order->order_shipping_tax;

if ($order_shipping) $line_items .= "\n".sprintf(apply_filters('woocommerce_print_orders_text_shipping', __('Shipping', 'woocommerce'), 'text/plain').': %2.2f', $order_shipping)."
\n";
if ($order_shipping_tax) $line_items .= "\n".sprintf(apply_filters('woocommerce_print_orders_text_shipping', __('Shipping', 'woocommerce'), 'text/plain').' Tax: %2.2f', $order_shipping_tax);

$fees = $order->get_fees();

foreach ($fees as $fee) {

	// WC 3.0+
	if (!is_callable(array($fee, 'get_name'))) continue;
	
	$line_items .= "\n".$fee->get_name().': '.sprintf('%2.2f', $fee->get_total());
	
	if ($fee->get_total_tax()) $line_items .= ' ('.__('Tax', 'woocommerce').': '.sprintf('%2.2f', $fee->get_total_tax()).')';
	
	$line_items .= "\n";

}

echo $line_items;

?>
<?php 

	$order_total = is_callable(array($order, 'get_total')) ? $order->get_total() : $order->order_total;

	_e('Total:', 'woocommerce');?> <?php echo $order_total;?>, <?php
	
	$payment_method_title = is_callable(array($order, 'get_payment_method_title')) ? $order->get_payment_method_title() : $order->payment_method_title;
	
	echo strip_tags($payment_method_title);

	$shipping_method_title = $order->get_shipping_method();
	if (!empty($shipping_method_title)) echo ', '.strip_tags($shipping_method_title);
?>

<?php do_action('woocommerce_cloudprint_internaloutput_footer', $order, 'text/plain'); ?>
</body>
