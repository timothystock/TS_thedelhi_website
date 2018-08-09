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
     <?php 

            
            
            $product_id = $product->get_id();
            $product_title = $product->get_name();
            $product_price = esc_attr( $product->get_price() );
            $product_description = $product->get_description();
    
    ?>
    <a href="#" data-open="reveal_<?php echo $product_id; ?>" style="border: 1px solid black;display:block">
        <h3 class="product-title" style="display:block; font-weight:bold; font-family: 'trocchi';"><?php echo $product_title; ?>
             <?php
                $producttags = get_the_terms( $product->get_id(), product_tag );
                if ($producttags) {
                  echo '<div class="tags" style="display:inline-block;">';
                  foreach($producttags as $tag) {
                    echo '<span  class="' .$tag->name. '">' .$tag->name. '</span>'; 
                  }
                  echo '</div>';
                }
            ?>
        
        
         
            <span class="price" style="display:inline-block;"><?php echo $product_price; ?></span></h3>
        <p><?php echo $product_description; ?></p>
        
    </a>
       <!--
                <button type="submit" data-quantity="1" data-product_id="<?php echo $product->id; ?>"
                    class="button alt ajax_add_to_cart add_to_cart_button product_type_simple">
                    <?php echo $label; ?>Add to order
                </button>
            -->
    <div id="reveal_<?php echo $product_id; ?>" class="reveal" data-reveal>
               <?php do_action( 'woocommerce_widget_product_item_start', $args ); ?>

                    <h3 class="product-title">
                        <?php echo $product_title; ?>
                        
                        <?php echo wc_get_product_tag_list( $product->get_id(), ', ', '<span class="tags">' . _n( '', '', count( $product->get_tag_ids() ), 'woocommerce' ) . ' ', '</span>' ); ?>

                        <span class="price"><?php echo $product_price; ?></span>
                    </h3>
          
                <p><?php echo $product_description; ?></p>

         
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
        </div>
</li>
