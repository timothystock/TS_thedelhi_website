<?php 

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

/**
 * Set WooCommerce image dimensions upon theme activation
 */
// Remove each style one by one
add_filter( 'woocommerce_enqueue_styles', 'jk_dequeue_styles' );
function jk_dequeue_styles( $enqueue_styles ) {
	unset( $enqueue_styles['woocommerce-general'] );	// Remove the gloss
	unset( $enqueue_styles['woocommerce-layout'] );		// Remove the layout
	unset( $enqueue_styles['woocommerce-smallscreen'] );	// Remove the smallscreen optimisation
	return $enqueue_styles;
}


add_filter ( 'wc_add_to_cart_message', 'wc_add_to_cart_message_filter', 10, 2 );
function wc_add_to_cart_message_filter($message, $product_id = null) {
    $titles[] = get_the_title( $product_id );

    $titles = array_filter( $titles );
    $added_text = sprintf( _n( '%s has been added to your order.', '%s have been added to your order.', sizeof( $titles ), 'woocommerce' ), wc_format_list_of_items( $titles ) );

    $message = sprintf( '%s ',
                    esc_html( $added_text ),
                    esc_url( wc_get_page_permalink( 'checkout' ) ),
                    esc_html__( 'Checkout', 'woocommerce' ),
                    esc_url( wc_get_page_permalink( 'cart' ) ),
                    esc_html__( 'View Cart', 'woocommerce' ));

    return $message;
}

function prefix_add_discount_line( $cart ) {
  $chosen_methods = WC()->session->get( 'chosen_shipping_methods' );
  $chosen_shipping_no_ajax = $chosen_methods[0];
  if ( 0 === strpos( $chosen_shipping_no_ajax, 'local_pickup' ) ) {

    $discount = $cart->subtotal * 0.1;
    $cart->add_fee( __( 'Collection discount applied', 'yourtext-domain' ) , -$discount );
  }
}
add_action( 'woocommerce_cart_calculate_fees', 'prefix_add_discount_line');

//CHECK POST CODE

//add_action( 'woocommerce_cart_coupon', array(&$this, 'new_woocommerce_cart_coupon'), 10, 0 );
//add_action( 'template_redirect', array(&$this, 'new_post_code_cart_button_handler') );

//function new_woocommerce_cart_coupon() {
//
//        <br/><br/><p>Enter your postcode</p><label for="post_code">Post Code</label> <input type="text" name="post_code" class="input-text" id="post_code" value="" /> <input type="submit" class="button" name="apply_post_code" value="Check Post Code" />
//   
//}

function new_post_code_cart_button_handler() {
    if( is_cart() && isset( $_POST['post_code'] ) && $_SERVER['REQUEST_METHOD'] == "POST" && !empty( $_POST['post_code'] ) ) {
      //validate post code here
    }
}

/**
 * Set a minimum order amount for checkout
 */
// add_action( 'woocommerce_checkout_process', 'wc_minimum_order_amount' );
// add_action( 'woocommerce_before_cart' , 'wc_minimum_order_amount' );
 
function wc_minimum_order_amount() {
    // Set this variable to specify a minimum order value
    $minimum = 10;

    if ( WC()->cart->total < $minimum ) {

        if( is_cart() ) {

            wc_print_notice( 
                sprintf( 'Your current order total is %s — you must have an order with a minimum of %s to place your order ' , 
                    wc_price( WC()->cart->total ), 
                    wc_price( $minimum )
                ), 'error' 
            );

        } else {

            wc_add_notice( 
                sprintf( 'Your current order total is %s — you must have an order with a minimum of %s to place your order' , 
                    wc_price( WC()->cart->total ), 
                    wc_price( $minimum )
                ), 'error' 
            );

        }
    }
}

// $instance = WC_Checkout::instance();
// remove_action( 'woocommerce_checkout_billing', array( $instance, 'checkout_form_billing' ) );

// ORDER DELIVERY TIMES OVERRIDES - this doesn't work. not sure why. Directly editing the plugin works, so will have to resort to javascript.
// add_filter('openinghours_chooser_position', 'delhi_openinghours_chooser_position');
// function delhi_openinghours_chooser_position() { 
//     $c = "woocommerce_checkout_before_customer_details";
//     return $c; 

// }

// openinghours_timepickercontroltype
add_filter('openinghours_timepickercontroltype', 'delhi_openinghours_timepickercontroltype');
function delhi_openinghours_timepickercontroltype($t) { 
//     controlType
    return "select";
}

add_filter('openinghours_frontendtext_choicelabel', 'my_openinghours_frontendtext_choicelabel');
function my_openinghours_frontendtext_choicelabel($message) { 
    
    $message = "Choose when you'd like to order for"; 
    return $message;
}


remove_action( 'woocommerce_before_single_product_summary', 'woocommerce_show_product_images', 20 );


/**
 * Changes the redirect URL for the Return To Shop button in the cart.
 *
 * @return string
 */
function wc_empty_cart_redirect_url() {
	return '/menu/';
}
add_filter( 'woocommerce_return_to_shop_redirect', 'wc_empty_cart_redirect_url' );