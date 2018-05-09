<?php
/**
 * Author: Ole Fredrik Lie
 * URL: http://olefredrik.com
 *
 * FoundationPress functions and definitions
 *
 * Set up the theme and provides some helper functions, which are used in the
 * theme as custom template tags. Others are attached to action and filter
 * hooks in WordPress to change core functionality.
 *
 * @link https://codex.wordpress.org/Theme_Development
 * @package FoundationPress
 * @since FoundationPress 1.0.0
 */

/** Various clean up functions */
require_once( 'library/cleanup.php' );

/** Required for Foundation to work properly */
require_once( 'library/foundation.php' );

/** Format comments */
require_once( 'library/class-foundationpress-comments.php' );

/** Register all navigation menus */
require_once( 'library/navigation.php' );

/** Add menu walkers for top-bar and off-canvas */
require_once( 'library/class-foundationpress-top-bar-walker.php' );
require_once( 'library/class-foundationpress-mobile-walker.php' );

/** Create widget areas in sidebar and footer */
require_once( 'library/widget-areas.php' );

/** Return entry meta information for posts */
require_once( 'library/entry-meta.php' );

/** Enqueue scripts */
require_once( 'library/enqueue-scripts.php' );

/** Add theme support */
require_once( 'library/theme-support.php' );

/** Add Nav Options to Customer */
require_once( 'library/custom-nav.php' );

/** Change WP's sticky post class */
require_once( 'library/sticky-posts.php' );

/** Configure responsive image sizes */
require_once( 'library/responsive-images.php' );

/** If your site requires protocol relative url's for theme assets, uncomment the line below */
// require_once( 'library/class-foundationpress-protocol-relative-theme-assets.php' );


//custom removal of actions
remove_action( 'woocommerce_after_shop_loop',     'woocommerce_pagination',                   10 );
remove_action( 'woocommerce_before_shop_loop',    'woocommerce_result_count',                 20 );
remove_action( 'woocommerce_before_shop_loop',    'woocommerce_catalog_ordering',             30 );


function delhi_products_in_subcategories( $args = array() ) {
     
    $parentid = get_queried_object_id();
         
    $args = array(
        'parent' => $parentid
    );

    $terms = get_terms( 'product_cat', $args );

    if ( $terms ) {

        echo '<ul class="product-cats">';

            foreach ( $terms as $term ) {

                echo '<li class="category">';                 

//                    woocommerce_subcategory_thumbnail( $term );

                    echo '<h2>';
                        echo '<a href="' .  esc_url( get_term_link( $term ) ) . '" class="' . $term->slug . '">';
                            echo $term->name;
                        echo '</a>';
                    echo '</h2>';
                    echo '<ul class="product-cats">';
                        $products = get_posts( 
                            array(
                                'post_type'             => 'product',
                                'post_status'           => 'publish',
                                'ignore_sticky_posts'   => 1,
                                'tax_query'             => array(
                                    array(
                                        'taxonomy'      => 'product_cat',
                                        'field' => 'term_id', //This is optional, as it defaults to 'term_id'
                                        'terms'         => $term,
                                        'operator'      => 'IN' // Possible values are 'IN', 'NOT IN', 'AND'.
                                    ),
                                    array(
                                        'taxonomy'      => 'product_visibility',
                                        'field'         => 'slug',
                                        'terms'         => 'exclude-from-catalog', // Possibly 'exclude-from-search' too
                                        'operator'      => 'NOT IN'
                                    )
                                )
                             )
                        );
                        foreach ( $products as $product ) {
                            echo '<li class="product">';
                                 echo '<a href="' .  esc_url( get_term_link( $term ) ) . '" class="' . $term->slug . '">';
                                    echo $term->name;
                                echo '</a>';
                            echo '</li>';
                        }
                
                    echo '</ul>';
                echo '</li>';


        }
     
        echo '</ul>';
 
    }
    
}
//add_action( 'woocommerce_before_shop_loop', 'tutsplus_product_subcategories', 50 );


add_filter( 'add_to_cart_text', 'woo_custom_single_add_to_cart_text' );                // < 2.1
add_filter( 'woocommerce_product_single_add_to_cart_text', 'woo_custom_single_add_to_cart_text' );  // 2.1 +
function woo_custom_single_add_to_cart_text() {
  
    return __( 'Add to order', 'woocommerce' );
  
}
add_filter( 'add_to_cart_text', 'woo_custom_product_add_to_cart_text' );            // < 2.1
add_filter( 'woocommerce_product_add_to_cart_text', 'woo_custom_product_add_to_cart_text' );  // 2.1 +
function woo_custom_product_add_to_cart_text() {
  
    return __( 'Add to order', 'woocommerce' );
  
}

//remove_action( 'woocommerce_single_product_summary', 'woocommerce_template_single_add_to_cart', 30 );
//add_action( 'woocommerce_single_product_summary', 'woocommerce_template_loop_add_to_cart', 30 );

/**
* customise Add to Cart link/button for product loop
* @param string $button
* @param object $product
* @return string
*/
function custom_woo_loop_add_to_cart_link($button, $product) {
    // not for variable, grouped or external products
    if (!in_array($product->product_type, array('variable', 'grouped', 'external'))) {
        // only if can be purchased
        if ($product->is_purchasable()) {
            // show qty +/- with button
            ob_start();
            woocommerce_simple_add_to_cart();
            $button = ob_get_clean();
 
            // modify button so that AJAX add-to-cart script finds it
            $replacement = sprintf('data-product_id="%d" data-quantity="1" $1 ajax_add_to_cart add_to_cart_button product_type_simple ', $product->id);
            $button = preg_replace('/(class="single_add_to_cart_button)/', $replacement, $button);
        }
    }
 
    return $button;
}
