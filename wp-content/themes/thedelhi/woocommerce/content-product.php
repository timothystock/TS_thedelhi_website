<?php
/**
 * The template for displaying product content within loops
 *
 * This template can be overridden by copying it to yourtheme/woocommerce/content-product.php.
 *
 * HOWEVER, on occasion WooCommerce will need to update template files and you
 * (the theme developer) will need to copy the new files to your theme to
 * maintain compatibility. We try to do this as little as possible, but it does
 * happen. When this occurs the version of the template file will be bumped and
 * the readme will list any important changes.
 *
 * @see     https://docs.woocommerce.com/document/template-structure/
 * @author  WooThemes
 * @package WooCommerce/Templates
 * @version 3.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}

global $product;

// Ensure visibility
if ( empty( $product ) || ! $product->is_visible() ) {
	return;
}
?>
<li <?php post_class(); ?>>
    <?php do_action( 'woocommerce_widget_product_item_start', $args ); ?>

	<a href="<?php echo esc_url( $product->get_permalink() ); ?>">
		<?php //echo $product->get_image(); ?>
		<h3 class="product-title">
            <?php echo $product->get_name(); ?>
            <?php echo wc_get_product_tag_list( $product->get_id(), ', ', '<span class="tags">' . _n( '', '', count( $product->get_tag_ids() ), 'woocommerce' ) . ' ', '</span>' ); ?>
   
            <span class="price"><?php echo $product->get_price_html(); ?></span>
        </h3>
	</a>
    <p><?php echo $product->get_description(); ?></p>
	<?php if ( ! empty( $show_rating ) ) : ?>
		<?php //echo wc_get_rating_html( $product->get_average_rating() ); ?>
	<?php endif; ?>
    <button type="submit" data-quantity="1" data-product_id="<?php echo $product->id; ?>"
        class="button alt ajax_add_to_cart add_to_cart_button product_type_simple">
        <?php echo $label; ?>Add to order
    </button>
	<?php
		
//		do_action( 'woocommerce_simple_add_to_cart' );
//do_action( 'woocommerce_grouped_add_to_cart');
//do_action( 'woocommerce_variable_add_to_cart' );
//do_action( 'woocommerce_external_add_to_cart' );
//do_action( 'woocommerce_single_variation' );
//do_action( 'woocommerce_single_variation_add_to_cart_button' );

	?>
	

	<?php do_action( 'woocommerce_widget_product_item_end', $args ); ?>
	<?php
	/**
	 * woocommerce_before_shop_loop_item hook.
	 *
	 * @hooked woocommerce_template_loop_product_link_open - 10
	 */
	//do_action( 'woocommerce_before_shop_loop_item' );

	/**
	 * woocommerce_before_shop_loop_item_title hook.
	 *
	 * @hooked woocommerce_show_product_loop_sale_flash - 10
	 * @hooked woocommerce_template_loop_product_thumbnail - 10
	 */
	//do_action( 'woocommerce_before_shop_loop_item_title' );

	/**
	 * woocommerce_shop_loop_item_title hook.
	 *
	 * @hooked woocommerce_template_loop_product_title - 10
	 */
	//do_action( 'woocommerce_shop_loop_item_title' );

	/**
	 * woocommerce_after_shop_loop_item_title hook.
	 *
	 * @hooked woocommerce_template_loop_rating - 5
	 * @hooked woocommerce_template_loop_price - 10
	 */
	//do_action( 'woocommerce_after_shop_loop_item_title' );

	/**
	 * woocommerce_after_shop_loop_item hook.
	 *
	 * @hooked woocommerce_template_loop_product_link_close - 5
	 * @hooked woocommerce_template_loop_add_to_cart - 10
	 */
//	do_action( 'woocommerce_after_shop_loop_item' );
remove_action( 'woocommerce_single_product_summary', 'woocommerce_template_single_title', 5 );
remove_action( 'woocommerce_single_product_summary', 'woocommerce_template_single_price', 10 );
remove_action( 'woocommerce_single_product_summary', 'woocommerce_template_single_excerpt', 20 );
remove_action( 'woocommerce_single_product_summary', 'woocommerce_template_single_meta', 40 );
do_action( 'woocommerce_single_product_summary' ); 
	?>
</li>
