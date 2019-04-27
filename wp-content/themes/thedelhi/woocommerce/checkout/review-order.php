<?php
/**
 * Review order table
 *
 * This template can be overridden by copying it to yourtheme/woocommerce/checkout/review-order.php.
 *
 * HOWEVER, on occasion WooCommerce will need to update template files and you
 * (the theme developer) will need to copy the new files to your theme to
 * maintain compatibility. We try to do this as little as possible, but it does
 * happen. When this occurs the version of the template file will be bumped and
 * the readme will list any important changes.
 *
 * @see 	    https://docs.woocommerce.com/document/template-structure/
 * @author 		WooThemes
 * @package 	WooCommerce/Templates
 * @version     3.3.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>
<!-- <table class="shop_table woocommerce-checkout-review-order-table">
	<thead>
		<tr>
			<th class="product-name"><?php _e( 'Product', 'woocommerce' ); ?></th>
			<th class="product-total"><?php _e( 'Total', 'woocommerce' ); ?></th>
		</tr>
	</thead> -->
	<div class="cell">
		<?php
			do_action( 'woocommerce_review_order_before_cart_contents' );

			
			foreach ( WC()->cart->get_cart() as $cart_item_key => $cart_item ) {
				$_product   = apply_filters( 'woocommerce_cart_item_product', $cart_item['data'], $cart_item, $cart_item_key );
				$product_id = apply_filters( 'woocommerce_cart_item_product_id', $cart_item['product_id'], $cart_item, $cart_item_key );

				if ( $_product && $_product->exists() && $cart_item['quantity'] > 0 && apply_filters( 'woocommerce_cart_item_visible', true, $cart_item, $cart_item_key ) ) {
					$product_permalink = apply_filters( 'woocommerce_cart_item_permalink', $_product->is_visible() ? $_product->get_permalink( $cart_item ) : '', $cart_item, $cart_item_key );
					?>
					<div class="grid-x grid-padding-x woocommerce-cart-form__cart-item <?php echo esc_attr( apply_filters( 'woocommerce_cart_item_class', 'cart_item', $cart_item, $cart_item_key ) ); ?>">

				
                        <div class="cell shrink product-quantity" data-title="<?php esc_attr_e( 'Quantity', 'woocommerce' ); ?>"><?php
						if ( $_product->is_sold_individually() ) {
							$product_quantity = sprintf( '1 <input type="hidden" name="cart[%s][qty]" value="1" />', $cart_item_key );
						} else {
							$product_quantity = woocommerce_quantity_input( array(
								'input_name'    => "cart[{$cart_item_key}][qty]",
								'input_value'   => $cart_item['quantity'],
								'max_value'     => $_product->get_max_purchase_quantity(),
								'min_value'     => '0',
								'product_name'  => $_product->get_name(),
							), $_product, false );
						}

						echo apply_filters( 'woocommerce_cart_item_quantity', $product_quantity, $cart_item_key, $cart_item );
						?></div>
                        
                        
						<div class="cell auto product-name" data-title="<?php esc_attr_e( 'Product', 'woocommerce' ); ?>">
                            <?php
                            echo '<h6><strong>';
							echo apply_filters( 'woocommerce_cart_item_name', $_product->get_name(), $cart_item, $cart_item_key ) . '&nbsp;';
                    echo apply_filters( 'woocommerce_cart_item_remove_link', sprintf(
									'</strong></h6><p style="line-height:1"><a href="%s" class="product-remove" aria-label="%s" data-product_id="%s" data-product_sku="%s">Remove</a></p>',
									esc_url( wc_get_cart_remove_url( $cart_item_key ) ),
									__( 'Remove this item', 'woocommerce' ),
									esc_attr( $product_id ),
									esc_attr( $_product->get_sku() )
								), $cart_item_key );  
					

						// Meta data.
						echo wc_get_formatted_cart_item_data( $cart_item );

						// Backorder notification.
						if ( $_product->backorders_require_notification() && $_product->is_on_backorder( $cart_item['quantity'] ) ) {
							echo '<p class="backorder_notification">' . esc_html__( 'Available on backorder', 'woocommerce' ) . '</p>';
						}
						?>



<!--             
						<span class="product-price" data-title="<?php esc_attr_e( 'Price', 'woocommerce' ); ?>">
							<?php
								echo apply_filters( 'woocommerce_cart_item_price', WC()->cart->get_product_price( $_product ), $cart_item, $cart_item_key ); // PHPCS: XSS ok.
							?>
						</span> -->

                              

                        
                        </div>
						

						<div class="cell shrink product-subtotal" data-title="<?php esc_attr_e( 'Total', 'woocommerce' ); ?>">
							<?php
								echo apply_filters( 'woocommerce_cart_item_subtotal', WC()->cart->get_product_subtotal( $_product, $cart_item['quantity'] ), $cart_item, $cart_item_key ); // PHPCS: XSS ok. 
							?>
						</div>
			 	       			 	       	       
                      	
                    </div>
					<?php
				}
			}
			

			do_action( 'woocommerce_review_order_after_cart_contents' );
		?>


		<div class="grid-x grid-padding-x cart-subtotal">
			<div class="cell auto"><?php _e( 'Subtotal', 'woocommerce' ); ?></div>
			<div class="cell shrink"><?php wc_cart_totals_subtotal_html(); ?></div>
		</div>

		<?php foreach ( WC()->cart->get_coupons() as $code => $coupon ) : ?>
			<div class="grid-x grid-padding-x cart-discount coupon-<?php echo esc_attr( sanitize_title( $code ) ); ?>">
				<div class="cell cell auto"><?php wc_cart_totals_coupon_label( $coupon ); ?></div>
				<div class="cell shrink"><?php wc_cart_totals_coupon_html( $coupon ); ?></div>
			</div>
		<?php endforeach; ?>

		<?php if ( WC()->cart->needs_shipping() && WC()->cart->show_shipping() ) : ?>
		<div class="grid-x grid-padding-x">
			<?php do_action( 'woocommerce_review_order_before_shipping' ); ?>
			<div class="cell auto">
				<?php wc_cart_totals_shipping_html(); ?>
			</div>
			<?php do_action( 'woocommerce_review_order_after_shipping' ); ?>
		</div>
		<?php endif; ?>

		<?php foreach ( WC()->cart->get_fees() as $fee ) : ?>
			<div class="row grid-x grid-padding-x fee">
				<div class="cell auto"><?php echo esc_html( $fee->name ); ?></div>
				<div class="cell shrink"><?php wc_cart_totals_fee_html( $fee ); ?></div>
		</div>
		<?php endforeach; ?>

		<?php if ( wc_tax_enabled() && ! WC()->cart->display_prices_including_tax() ) : ?>
			<?php if ( 'itemized' === get_option( 'woocommerce_tax_total_display' ) ) : ?>
				<?php foreach ( WC()->cart->get_tax_totals() as $code => $tax ) : ?>
					<div class="row grid-x grid-padding-x tax-rate tax-rate-<?php echo sanitize_title( $code ); ?>">
						<div class="cell auto"><?php echo esc_html( $tax->label ); ?></div>
						<div class="cell shrink"><?php echo wp_kses_post( $tax->formatted_amount ); ?></div>
		</div>
				<?php endforeach; ?>
			<?php else : ?>
				<div class="row grid-x grid-padding-x tax-total">
					<div class="cell auto"><?php echo esc_html( WC()->countries->tax_or_vat() ); ?></div>
					<div class="cell shrink"><?php wc_cart_totals_taxes_total_html(); ?></div>
		</div>
			<?php endif; ?>
		<?php endif; ?>

		<?php do_action( 'woocommerce_review_order_before_order_total' ); ?>

		<div class="row grid-x grid-padding-x order-total">
			<div class="cell auto"><?php _e( 'Total', 'woocommerce' ); ?></div>
			<div class="cell shrink"><?php wc_cart_totals_order_total_html(); ?></div>
		</div>

		<?php do_action( 'woocommerce_review_order_after_order_total' ); ?>


