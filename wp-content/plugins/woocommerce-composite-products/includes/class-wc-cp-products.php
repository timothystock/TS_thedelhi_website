<?php
/**
 * WC_CP_Products class
 *
 * @author   SomewhereWarm <info@somewherewarm.gr>
 * @package  WooCommerce Composite Products
 * @since    3.7.0
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * API functions to support product modifications when contained in Composites.
 *
 * @class    WC_CP_Products
 * @version  3.14.0
 */
class WC_CP_Products {

	/**
	 * Composited product being filtered - @see 'add_filters'.
	 * @var WC_CP_Product|false
	 */
	public static $filtered_component_option = false;

	/**
	 * Setup hooks.
	 */
	public static function init() {

		// Reset CP query cache + price sync cache when clearing product transients.
		add_action( 'woocommerce_delete_product_transients', array( __CLASS__, 'flush_cp_cache' ) );

		// Reset CP query cache + price sync cache during post status transitions.
		add_action( 'delete_post', array( __CLASS__, 'post_status_transition' ) );
		add_action( 'wp_trash_post', array( __CLASS__, 'post_status_transition' ) );
		add_action( 'untrashed_post', array( __CLASS__, 'post_status_transition' ) );

		// Delete meta reserved to the composite/bundle types.
		add_action( 'woocommerce_before_product_object_save', array( __CLASS__, 'delete_reserved_price_meta' ) );
	}

	/*
	|--------------------------------------------------------------------------
	| API Methods.
	|--------------------------------------------------------------------------
	*/

	/**
	 * Add filters to modify products when contained in Composites.
	 *
	 * @param  WC_CP_Product  $product
	 * @return void
	 */
	public static function add_filters( $component_option ) {

		self::$filtered_component_option = $component_option;

		add_filter( 'woocommerce_product_get_price', array( __CLASS__, 'filter_show_product_get_price' ), 16, 2 );
		add_filter( 'woocommerce_product_get_sale_price', array( __CLASS__, 'filter_show_product_get_sale_price' ), 16, 2 );
		add_filter( 'woocommerce_product_get_regular_price', array( __CLASS__, 'filter_show_product_get_regular_price' ), 16, 2 );
		add_filter( 'woocommerce_product_variation_get_price', array( __CLASS__, 'filter_show_product_get_price' ), 16, 2 );
		add_filter( 'woocommerce_product_variation_get_sale_price', array( __CLASS__, 'filter_show_product_get_sale_price' ), 16, 2 );
		add_filter( 'woocommerce_product_variation_get_regular_price', array( __CLASS__, 'filter_show_product_get_regular_price' ), 16, 2 );

		add_filter( 'woocommerce_get_price_html', array( __CLASS__, 'filter_show_product_get_price_html' ), 5, 2 );

		add_filter( 'woocommerce_variation_prices', array( __CLASS__, 'filter_get_variation_prices' ), 16, 2 );
		add_filter( 'woocommerce_available_variation', array( __CLASS__, 'filter_available_variation' ), 10, 3 );
		add_filter( 'woocommerce_show_variation_price', array( __CLASS__, 'filter_show_variation_price' ), 10, 3 );

		/**
		 * Product Bundles compatibility.
		 */

		add_filter( 'woocommerce_bundles_update_price_meta', array( __CLASS__, 'filter_show_product_bundles_update_price_meta' ), 10, 2 );

		add_filter( 'woocommerce_bundle_contains_priced_items', array( __CLASS__, 'filter_bundle_contains_priced_items' ), 10, 2 );
		add_filter( 'woocommerce_bundled_item_is_priced_individually', array( __CLASS__, 'filter_bundled_item_is_priced_individually' ), 10, 2 );

		add_filter( 'woocommerce_bundle_contains_shipped_items', array( __CLASS__, 'filter_bundle_contains_shipped_items' ), 10, 2 );
		add_filter( 'woocommerce_bundled_item_is_shipped_individually', array( __CLASS__, 'filter_bundled_item_is_shipped_individually' ), 10, 2 );

		add_filter( 'woocommerce_bundled_item_raw_price_cart', array( __CLASS__, 'filter_bundled_item_raw_price_cart' ), 10, 4 );

		/**
		 * NYP compatibility.
		 */

		add_filter( 'woocommerce_nyp_html', array( __CLASS__, 'filter_show_product_get_nyp_price_html' ), 15, 2 );

		/**
		 * Action 'woocommerce_composite_products_apply_product_filters'.
		 *
		 * @param  WC_Product            $product
		 * @param  string                $component_id
		 * @param  WC_Product_Composite  $composite
		 */
		do_action( 'woocommerce_composite_products_apply_product_filters', $component_option->get_product(), $component_option->get_component_id(), $component_option->get_composite() );
	}

	/**
	 * Remove filters - @see 'add_filters'.
	 *
	 * @return void
	 */
	public static function remove_filters() {

		/**
		 * Action 'woocommerce_composite_products_remove_product_filters'.
		 */
		do_action( 'woocommerce_composite_products_remove_product_filters' );

		self::$filtered_component_option = false;

		remove_filter( 'woocommerce_product_get_price', array( __CLASS__, 'filter_show_product_get_price' ), 16, 2 );
		remove_filter( 'woocommerce_product_get_sale_price', array( __CLASS__, 'filter_show_product_get_sale_price' ), 16, 2 );
		remove_filter( 'woocommerce_product_get_regular_price', array( __CLASS__, 'filter_show_product_get_regular_price' ), 16, 2 );
		remove_filter( 'woocommerce_product_variation_get_price', array( __CLASS__, 'filter_show_product_get_price' ), 16, 2 );
		remove_filter( 'woocommerce_product_variation_get_sale_price', array( __CLASS__, 'filter_show_product_get_sale_price' ), 16, 2 );
		remove_filter( 'woocommerce_product_variation_get_regular_price', array( __CLASS__, 'filter_show_product_get_regular_price' ), 16, 2 );

		remove_filter( 'woocommerce_get_price_html', array( __CLASS__, 'filter_show_product_get_price_html' ), 5, 2 );

		remove_filter( 'woocommerce_variation_prices', array( __CLASS__, 'filter_get_variation_prices' ), 16, 2 );
		remove_filter( 'woocommerce_available_variation', array( __CLASS__, 'filter_available_variation' ), 10, 3 );
		remove_filter( 'woocommerce_show_variation_price', array( __CLASS__, 'filter_show_variation_price' ), 10, 3 );

		/**
		 * Product Bundles compatibility.
		 */

		remove_filter( 'woocommerce_bundles_update_price_meta', array( __CLASS__, 'filter_show_product_bundles_update_price_meta' ), 10, 2 );

		remove_filter( 'woocommerce_bundle_contains_priced_items', array( __CLASS__, 'filter_bundle_contains_priced_items' ), 10, 2 );
		remove_filter( 'woocommerce_bundled_item_is_priced_individually', array( __CLASS__, 'filter_bundled_item_is_priced_individually' ), 10, 2 );

		remove_filter( 'woocommerce_bundle_contains_shipped_items', array( __CLASS__, 'filter_bundle_contains_shipped_items' ), 10, 2 );
		remove_filter( 'woocommerce_bundled_item_is_shipped_individually', array( __CLASS__, 'filter_bundled_item_is_shipped_individually' ), 10, 2 );

		remove_filter( 'woocommerce_bundled_item_raw_price_cart', array( __CLASS__, 'filter_bundled_item_raw_price_cart' ), 10, 4 );

		/**
		 * NYP compatibility.
		 */

		remove_filter( 'woocommerce_nyp_html', array( __CLASS__, 'filter_show_product_get_nyp_price_html' ), 15, 2 );
	}

	/**
	 * Returns the incl/excl tax coefficients for calculating prices incl/excl tax on the client side.
	 *
	 * @since  3.13.6
	 *
	 * @param  WC_Product  $product
	 * @return array
	 */
	public static function get_tax_ratios( $product ) {

		WC_CP_Helpers::extend_price_display_precision();

		$ref_price      = 1000.0;
		$ref_price_incl = wc_get_price_including_tax( $product, array( 'qty' => 1, 'price' => $ref_price ) );
		$ref_price_excl = wc_get_price_excluding_tax( $product, array( 'qty' => 1, 'price' => $ref_price ) );

		WC_CP_Helpers::reset_price_display_precision();

		return array(
			'incl' => $ref_price_incl / $ref_price,
			'excl' => $ref_price_excl / $ref_price
		);
	}

	/**
	 * Calculates product prices.
	 *
	 * @since  3.12.0
	 *
	 * @param  WC_Product  $product
	 * @param  array       $args
	 * @return mixed
	 */
	public static function get_product_price( $product, $args ) {

		$defaults = array(
			'price' => '',
			'qty'   => 1,
			'calc'  => ''
		);

		$args  = wp_parse_args( $args, $defaults );
		$price = $args[ 'price' ];
		$qty   = $args[ 'qty' ];
		$calc  = $args[ 'calc' ];

		if ( $price ) {

			if ( 'display' === $calc ) {
				$calc = 'excl' === wc_cp_tax_display_shop() ? 'excl_tax' : 'incl_tax';
			}

			if ( 'incl_tax' === $calc ) {
				$price = wc_get_price_including_tax( $product, array( 'qty' => $qty, 'price' => $price ) );
			} elseif ( 'excl_tax' === $calc ) {
				$price = wc_get_price_excluding_tax( $product, array( 'qty' => $qty, 'price' => $price ) );
			} else {
				$price = $price * $qty;
			}
		}

		return $price;
	}

	/**
	 * Discounted price getter.
	 *
	 * @param  mixed  $price
	 * @param  mixed  $discount
	 * @return mixed
	 */
	public static function get_discounted_price( $price, $discount ) {

		$discounted_price = $price;

		if ( ! empty( $price ) && ! empty( $discount ) ) {
			$discounted_price = round( ( double ) $price * ( 100 - $discount ) / 100, wc_cp_price_num_decimals() );
		}

		return $discounted_price;
	}


	/*
	|--------------------------------------------------------------------------
	| Hooks.
	|--------------------------------------------------------------------------
	*/

	/**
	 * Filter get_variation_prices() calls to include discounts when displaying composited variable product prices.
	 *
	 * @param  array                $prices_array
	 * @param  WC_Product_Variable  $product
	 * @return array
	 */
	public static function filter_get_variation_prices( $prices_array, $product ) {

		$filtered_component_option = self::$filtered_component_option;

		if ( ! empty( $filtered_component_option  ) ) {

			$prices         = array();
			$regular_prices = array();
			$sale_prices    = array();

			$discount           = $filtered_component_option->get_discount();
			$priced_per_product = $filtered_component_option->is_priced_individually();

			// Filter regular prices.
			foreach ( $prices_array[ 'regular_price' ] as $variation_id => $regular_price ) {

				if ( $priced_per_product ) {
					$regular_prices[ $variation_id ] = '' === $regular_price ? $prices_array[ 'price' ][ $variation_id ] : $regular_price;
				} else {
					$regular_prices[ $variation_id ] = 0;
				}
			}

			// Filter prices.
			foreach ( $prices_array[ 'price' ] as $variation_id => $price ) {

				if ( $priced_per_product ) {
					if ( false === $filtered_component_option->is_discount_allowed_on_sale_price() ) {
						$regular_price = $regular_prices[ $variation_id ];
					} else {
						$regular_price = $price;
					}
					$price                   = empty( $discount ) ? $price : round( ( double ) $regular_price * ( 100 - $discount ) / 100, wc_cp_price_num_decimals() );
					$prices[ $variation_id ] = apply_filters( 'woocommerce_composited_variation_price', $price, $variation_id, $discount, $filtered_component_option );
				} else {
					$prices[ $variation_id ] = 0;
				}
			}

			// Filter sale prices.
			foreach ( $prices_array[ 'sale_price' ] as $variation_id => $sale_price ) {

				if ( $priced_per_product ) {
					$sale_prices[ $variation_id ] = empty( $discount ) ? $sale_price : $prices[ $variation_id ];
				} else {
					$sale_prices[ $variation_id ] = 0;
				}
			}

			if ( false === $filtered_component_option->is_discount_allowed_on_sale_price() ) {
				asort( $prices );
			}

			$prices_array = array(
				'price'         => $prices,
				'regular_price' => $regular_prices,
				'sale_price'    => $sale_prices
			);
		}

		return $prices_array;
	}


	/**
	 * Filters variation data in the show_product function.
	 *
	 * @param  mixed                 $variation_data
	 * @param  WC_Product            $bundled_product
	 * @param  WC_Product_Variation  $bundled_variation
	 * @return mixed
	 */
	public static function filter_available_variation( $variation_data, $product, $variation ) {

		$filtered_component_option = self::$filtered_component_option;

		if ( ! empty( $filtered_component_option  ) ) {

			// Add/modify price data.

			$variation_data[ 'price' ]         = $variation->get_price();
			$variation_data[ 'regular_price' ] = $variation->get_regular_price();

			$variation_data[ 'price_tax' ] = self::get_tax_ratios( $variation );

			$variation_data[ 'min_qty' ] = self::$filtered_component_option->get_quantity_min();
			$variation_data[ 'max_qty' ] = self::$filtered_component_option->get_quantity_max( true, $variation );

			// Add/modify availability data.
			$variation_data[ 'availability_html' ] = $filtered_component_option->get_availability_html( $variation );

			if ( ! $variation->is_in_stock() || ! $variation->has_enough_stock( $variation_data[ 'min_qty' ] ) ) {
				$variation_data[ 'is_in_stock' ] = false;
			}

			// Add flag for 3-p code.
			$variation_data[ 'is_composited' ] = true;

			// Modify variation images as we don't want the single-product sizes here.

			$variation_thumbnail_size = self::$filtered_component_option->get_selection_thumbnail_size();

			if ( ! in_array( $variation_thumbnail_size, array( 'single', 'shop_single', 'woocommerce_single' ) ) ) {

				if ( $variation_data[ 'image' ][ 'src' ] ) {

					$src = wp_get_attachment_image_src( $variation_data[ 'image_id' ], $variation_thumbnail_size );

					$variation_data[ 'image' ][ 'src' ]    = $src[0];
					$variation_data[ 'image' ][ 'src_w' ]  = $src[1];
					$variation_data[ 'image' ][ 'src_h' ]  = $src[2];
					$variation_data[ 'image' ][ 'srcset' ] = function_exists( 'wp_get_attachment_image_srcset' ) ? wp_get_attachment_image_srcset( $variation_data[ 'image_id' ], $variation_thumbnail_size ) : false;
					$variation_data[ 'image' ][ 'sizes' ]  = function_exists( 'wp_get_attachment_image_sizes' ) ? wp_get_attachment_image_sizes( $variation_data[ 'image_id' ], $variation_thumbnail_size ) : false;
				}
			}
		}

		return $variation_data;
	}

	/**
	 * Filter condition that allows WC to calculate variation price_html.
	 *
	 * @param  boolean               $show
	 * @param  WC_Product_Variable   $product
	 * @param  WC_Product_Variation  $variation
	 * @return boolean
	 */
	public static function filter_show_variation_price( $show, $product, $variation ) {

		if ( ! empty( self::$filtered_component_option ) ) {

			$show = false;

			if ( self::$filtered_component_option->is_priced_individually() && false === self::$filtered_component_option->get_component()->hide_selected_option_price() ) {
				$show = true;
			}
		}

		return $show;
	}

	/**
	 * Components discounts should not trigger bundle price updates.
	 *
	 * @param  boolean            $is
	 * @param  WC_Product_Bundle  $bundle
	 * @return boolean
	 */
	public static function filter_show_product_bundles_update_price_meta( $update, $bundle ) {
		return false;
	}

	/**
	 * Filter 'woocommerce_bundle_is_composited'.
	 *
	 * @param  boolean            $is
	 * @param  WC_Product_Bundle  $bundle
	 * @return boolean
	 */
	public static function filter_bundle_is_composited( $is, $bundle ) {
		return true;
	}

	/**
	 * If a component is not priced individually, this should force bundled items to return a zero price.
	 *
	 * @param  boolean          $is
	 * @param  WC_Bundled_Item  $bundled_item
	 * @return boolean
	 */
	public static function filter_bundled_item_is_priced_individually( $is_priced_individually, $bundled_item ) {

		if ( ! empty( self::$filtered_component_option ) ) {
			if ( ! self::$filtered_component_option->is_priced_individually() ) {
				$is_priced_individually = false;
			}
		}

		return $is_priced_individually;
	}

	/**
	 * If a component is not priced individually, this should force bundled items to return a zero price.
	 *
	 * @param  boolean            $is
	 * @param  WC_Product_Bundle  $bundle
	 * @return boolean
	 */
	public static function filter_bundle_contains_priced_items( $contains, $bundle ) {

		if ( ! empty( self::$filtered_component_option ) ) {
			if ( ! self::$filtered_component_option->is_priced_individually() ) {
				$contains = false;
			}
		}

		return $contains;
	}

	/**
	 * If a component is not shipped individually, this should force bundled items to comply.
	 *
	 * @since  3.14.0
	 *
	 * @param  boolean          $is
	 * @param  WC_Bundled_Item  $bundled_item
	 * @return boolean
	 */
	public static function filter_bundled_item_is_shipped_individually( $is_shipped_individually, $bundled_item ) {

		if ( ! empty( self::$filtered_component_option ) ) {
			if ( ! self::$filtered_component_option->is_shipped_individually() ) {
				$is_shipped_individually = false;
			}
		}

		return $is_shipped_individually;
	}

	/**
	 * If a component is not shipped individually, this should force bundled items to comply.
	 *
	 * @since  3.14.0
	 *
	 * @param  boolean            $is
	 * @param  WC_Product_Bundle  $bundle
	 * @return boolean
	 */
	public static function filter_bundle_contains_shipped_items( $contains, $bundle ) {

		if ( ! empty( self::$filtered_component_option ) ) {
			if ( ! self::$filtered_component_option->is_shipped_individually() ) {
				$contains = false;
			}
		}

		return $contains;
	}

	/**
	 * Filters get_price_html to include component discounts.
	 *
	 * @param  string      $price_html
	 * @param  WC_Product  $product
	 * @return string
	 */
	public static function filter_show_product_get_price_html( $price_html, $product ) {

		if ( ! empty( self::$filtered_component_option ) ) {

			// Tells NYP to back off.
			$product->is_filtered_price_html = 'yes';

			if ( ! self::$filtered_component_option->is_priced_individually() ) {

				$price_html = '';

			} else {

				$add_suffix = true;

				// Don't add /pc suffix to products in composited bundles (possibly duplicate).
				$filtered_product = self::$filtered_component_option->get_product();
				$product_id       = $product->is_type( 'variation' ) ? $product->get_parent_id() : $product->get_id();

				if ( $filtered_product->get_id() !== $product_id ) {
					$add_suffix = false;
				}

				if ( $add_suffix ) {
					$suffix     = self::$filtered_component_option->get_quantity_min() > 1 ? ' ' . __( '/ pc.', 'woocommerce-composite-products' ) : '';
					$price_html = $price_html . $suffix;
				}
			}

			$price_html = apply_filters( 'woocommerce_composited_item_price_html', $price_html, $product, self::$filtered_component_option->get_component_id(), self::$filtered_component_option->get_composite_id() );
		}

		return $price_html;
	}

	/**
	 * Filters get_price_html to hide nyp prices in static pricing mode.
	 *
	 * @param  string      $price_html
	 * @param  WC_Product  $product
	 * @return string
	 */
	public static function filter_show_product_get_nyp_price_html( $price_html, $product ) {

		if ( ! empty( self::$filtered_component_option ) ) {
			if ( ! self::$filtered_component_option->is_priced_individually() ) {
				$price_html = '';
			}
		}

		return $price_html;
	}

	/**
	 * Filters get_price to include component discounts.
	 *
	 * @param  double      $price
	 * @param  WC_Product  $product
	 * @return string
	 */
	public static function filter_show_product_get_price( $price, $product ) {

		if ( ! empty( self::$filtered_component_option ) ) {

			if ( '' === $price ) {
				return $price;
			}

			if ( ! self::$filtered_component_option->is_priced_individually() ) {
				return 0.0;
			}

			if ( false === self::$filtered_component_option->is_discount_allowed_on_sale_price() ) {
				$regular_price = $product->get_regular_price();
			} else {
				$regular_price = $price;
			}

			if ( $discount = self::$filtered_component_option->get_discount() ) {
				$price = empty( $regular_price ) ? $regular_price : self::get_discounted_price( $regular_price, $discount );
			}
		}

		return $price;
	}

	/**
	 * Filters get_regular_price to include component discounts.
	 *
	 * @param  double      $price
	 * @param  WC_Product  $product
	 * @return string
	 */
	public static function filter_show_product_get_regular_price( $price, $product ) {

		$filtered_component_option = self::$filtered_component_option;

		if ( ! empty( $filtered_component_option  ) ) {

			if ( ! self::$filtered_component_option->is_priced_individually() ) {
				return 0.0;
			}

			if ( empty( $price ) ) {
				self::$filtered_component_option = false;
				$price = $product->get_price();
				self::$filtered_component_option = $filtered_component_option;
			}
		}

		return $price;
	}

	/**
	 * Filters get_sale_price to include component discounts.
	 *
	 * @param  double      $price
	 * @param  WC_Product  $product
	 * @return string
	 */
	public static function filter_show_product_get_sale_price( $price, $product ) {

		if ( ! empty( self::$filtered_component_option ) ) {

			if ( ! self::$filtered_component_option->is_priced_individually() ) {
				return 0.0;
			}

			if ( '' === $price || false === self::$filtered_component_option->is_discount_allowed_on_sale_price() ) {
				$regular_price = $product->get_regular_price();
			} else {
				$regular_price = $price;
			}

			if ( $discount = self::$filtered_component_option->get_discount() ) {
				$price = empty( $regular_price ) ? $regular_price : self::get_discounted_price( $regular_price, $discount );
			}
		}

		return $price;
	}

	/**
	 * Filters 'woocommerce_bundled_item_raw_price_cart' to include component + bundled item discounts.
	 *
	 * @param  double           $price
	 * @param  WC_Product       $product
	 * @param  mixed            $bundled_discount
	 * @param  WC_Bundled_Item  $bundled_item
	 * @return string
	 */
	public static function filter_bundled_item_raw_price_cart( $price, $product, $bundled_discount, $bundled_item ) {

		if ( ! empty( self::$filtered_component_option ) ) {

			if ( '' === $price ) {
				return $price;
			}

			if ( ! self::$filtered_component_option->is_priced_individually() ) {
				return 0.0;
			}

			if ( false === self::$filtered_component_option->is_discount_allowed_on_sale_price() ) {
				$regular_price = $product->get_regular_price( 'edit' );
			} else {
				$regular_price = $price;
			}

			if ( $discount = self::$filtered_component_option->get_discount() ) {
				$price = empty( $regular_price ) ? $regular_price : round( (double) $regular_price * ( 100 - $discount ) / 100, wc_cp_price_num_decimals() );
			}
		}

		return $price;
	}

	/**
	 * Delete component options query cache + composite product price sync cache.
	 *
	 * @param  int  $post_id
	 * @return void
	 */
	public static function post_status_transition( $post_id ) {

		$post_type = get_post_type( $post_id );

		if ( 'product' === $post_type ) {
			self::flush_cp_cache();
		}
	}

	/**
	 * Delete component options query cache + composite product price sync cache.
	 *
	 * @param  int   $post_id
	 * @return void
	 */
	public static function flush_cp_cache( $post_id = 0 ) {
		if ( $post_id > 0 ) {
			delete_transient( 'wc_cp_query_results_' . $post_id );
			delete_transient( 'wc_cp_permutation_data_' . $post_id );
		} else {
			// Invalidate all CP query cache entries.
			WC_Cache_Helper::get_transient_version( 'product', true );
		}
	}

	/**
	 * Delete price meta reserved to bundles/composites (legacy).
	 *
	 * @param  int  $post_id
	 * @return void
	 */
	public static function delete_reserved_price_post_meta( $post_id ) {

		// Get product type.
		$product_type = WC_Product_Factory::get_product_type( $post_id );

		if ( false === in_array( $product_type, array( 'bundle', 'composite' ) ) ) {
			delete_post_meta( $post_id, '_wc_sw_max_price' );
			delete_post_meta( $post_id, '_wc_sw_max_regular_price' );
		}
	}

	/**
	 * Delete price meta reserved to bundles/composites.
	 *
	 * @param  WC_Product  $product
	 * @return void
	 */
	public static function delete_reserved_price_meta( $product ) {

		$product->delete_meta_data( '_wc_cp_composited_value' );
		$product->delete_meta_data( '_wc_cp_composited_weight' );

		if ( false === in_array( $product->get_type(), array( 'bundle', 'composite' ) ) ) {
			$product->delete_meta_data( '_wc_sw_max_price' );
			$product->delete_meta_data( '_wc_sw_max_regular_price' );
		}
	}

	/*
	|--------------------------------------------------------------------------
	| Deprecated methods.
	|--------------------------------------------------------------------------
	*/

	/**
	 * Calculates and returns:
	 *
	 * @deprecated  3.14.0
	 *
	 * - The permutations that correspond to the minimum & maximum configuration price.
	 * - The minimum & maximum raw price.
	 *
	 * @param  WC_Product_Composite  $product
	 * @return array
	 */
	public static function read_price_data( $product ) {
		_deprecated_function( __METHOD__ . '()', '3.14.0', 'WC_Product_Composite_Data_Store_CPT::read_price_data()' );
		return $product->get_data_store()->read_price_data( $product );
	}

	/**
	 * Get expanded component options to include variations straight from the DB.
	 *
	 * @deprecated  3.14.0
	 *
	 * @param  array $ids
	 * @return array
	 */
	public static function get_expanded_component_options( $ids ) {
		_deprecated_function( __METHOD__ . '()', '3.14.0', 'WC_Product_Composite_Data_Store_CPT::get_expanded_component_options()' );
		$data_store = WC_Data_Store::load( 'product-composite' );
		return $data_store->get_expanded_component_options( $ids );
	}

	/**
	 * Get raw product prices straight from the DB.
	 *
	 * @deprecated  3.14.0
	 *
	 * @param  array $ids
	 * @return array
	 */
	public static function get_raw_component_option_prices( $ids ) {
		_deprecated_function( __METHOD__ . '()', '3.14.0', 'WC_Product_Composite_Data_Store_CPT::get_raw_component_option_prices()' );
		$data_store = WC_Data_Store::load( 'product-composite' );
		return $data_store->get_raw_component_option_prices( $ids );
	}

	/**
	 * Calculates bundled product prices incl. or excl. tax depending on the 'woocommerce_tax_display_shop' setting.
	 *
	 * @deprecated  3.12.0
	 */
	public static function get_product_display_price( $product, $price, $qty = 1 ) {
		_deprecated_function( __METHOD__ . '()', '3.12.0', 'WC_CP_Products::get_product_price()' );
		return self::get_product_price( $product, array(
			'price' => $price,
			'qty'   => $qty,
			'calc'  => 'display'
		) );
	}
}

WC_CP_Products::init();
