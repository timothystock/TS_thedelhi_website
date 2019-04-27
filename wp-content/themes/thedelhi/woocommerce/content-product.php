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
 * @version 3.4.0
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

            // $product = wc_get_product();
            $product_id = $product->get_id();
            $product_title = $product->get_name();
            
            // $product_price = esc_attr( $product->get_variation_regular_price( 'min', true ) );
            $is_variable = $product->is_type('variable');
            if($is_variable) {
                $product_price = "<i>from</i> " . esc_attr( $product->get_variation_regular_price( 'min', true ) );
            } else {
                $product_price = esc_attr( $product->get_price() );
            }
            $product_description = $product->get_description();
    
    ?>
    <!-- <a href="#" data-open="reveal_<?php echo $product_id; ?>" style="display:block"> -->
    <!-- <div> -->

        <?php if($product_description) { ?>

        <h3 class="product-title" style="display:block;"><?php echo $product_title; ?>
            <span class="tags"></span><span class="price" style="display:inline-block;"><?php echo $product_price; ?></span>
        </h3>
       
        <p> 
            <?php echo $product_description; ?>
             <?php
                $producttags = get_the_terms( $product->get_id(), 'product_tag' );
                if ($producttags) {
                  echo '<i class="tags" style="display:inline-block;">';
                  foreach($producttags as $tag) {
                    echo '<span  class="' .$tag->name. '">' .$tag->name. '</span>'; 
                  }
                  echo '</i>';
                }
            ?>
        </p>
        <?php } else {?>
         <h3 class="product-title" style="display:block;"><?php echo $product_title; ?>
              <?php
                $producttags = get_the_terms( $product->get_id(), 'product_tag' );
                if ($producttags) {
                  echo '<i class="tags" style="display:inline-block;">';
                  foreach($producttags as $tag) {
                    echo '<span  class="' .$tag->name. '">' .$tag->name. '</span>'; 
                  }
                  echo '</i>';
                }
            ?>
            <span class="price" style="display:inline-block;"><?php echo $product->get_price_html(); ?></span>
        </h3>
        <?php } ?>
       <!-- </div> -->
       <!--</a>
            <button type="submit" data-quantity="1" data-product_id="<?php echo $product->id; ?>"
                class="button alt ajax_add_to_cart add_to_cart_button product_type_simple">
                <?php echo $label; ?>Add to order
            </button>
            -->
    <div id="reveal_<?php echo $product_id; ?>" class="reveal addtocart " data-reveal>
        <div class="grid-x addtocart-content align-middle">
<!--        <div class="addtocart-content cell auto">-->
<!--            <div class="grid-x align-middle">-->
                <div class="cell">
            <button class="close-button" data-close aria-label="Close modal" type="button">
              <span aria-hidden="true">&times;</span>
            </button>
               <?php do_action( 'woocommerce_widget_product_item_start', $args ); ?>

                   
                    <h3 class="product-title" style="display:block;"><?php echo $product_title; ?>
                        <span class="tags"></span><span class="price" style="display:inline-block;"><?php echo $product_price; ?></span>
                    </h3>

                    <p> <?php if($product_description) { ?>
                        <?php echo $product_description; ?>
                         <?php } ?>
                         <?php
                            $producttags = get_the_terms( $product->get_id(), 'product_tag' );
                            if ($producttags) {
                              echo '<i class="tags" style="display:inline-block;">';
                              foreach($producttags as $tag) {
                                echo '<span  class="' .$tag->name. '">' .$tag->name. '</span>'; 
                              }
                              echo '</i>';
                            }
                        ?>
                    </p>
                    
                   
         
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
<!--                    </div>-->
<!--                </div>-->
            </div>
        </div>
    </div>
</li>
