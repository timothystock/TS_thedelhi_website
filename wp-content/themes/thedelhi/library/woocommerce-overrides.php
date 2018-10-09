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

