<?php
/**
 * WC_Product_Composite_Data_Store_CPT class
 *
 * @author   SomewhereWarm <info@somewherewarm.gr>
 * @package  WooCommerce Composite Products
 * @since    3.9.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * WC Composite Product Data Store class
 *
 * Composite data stored as Custom Post Type. For use with the WC 3.0+ CRUD API.
 *
 * @class    WC_Product_Composite_Data_Store_CPT
 * @version  3.15.1
 */
class WC_Product_Composite_Data_Store_CPT extends WC_Product_Data_Store_CPT {

	/**
	 * Data stored in meta keys, but not considered "meta" for the Composite type.
	 * @var array
	 */
	protected $extended_internal_meta_keys = array(
		'_bto_data',
		'_bto_scenario_data',
		'_bto_base_price',
		'_bto_base_regular_price',
		'_bto_base_sale_price',
		'_bto_shop_price_calc',
		'_bto_style',
		'_bto_add_to_cart_form_location',
		'_bto_edit_in_cart',
		'_bto_sold_individually',
		'_wc_sw_max_price'
	);

	/**
	 * Maps extended properties to meta keys.
	 * @var array
	 */
	protected $props_to_meta_keys = array(
		'price'                     => '_bto_base_price',
		'regular_price'             => '_bto_base_regular_price',
		'sale_price'                => '_bto_base_sale_price',
		'layout'                    => '_bto_style',
		'add_to_cart_form_location' => '_bto_add_to_cart_form_location',
		'shop_price_calc'           => '_bto_shop_price_calc',
		'editable_in_cart'          => '_bto_edit_in_cart',
		'sold_individually_context' => '_bto_sold_individually',
		'min_raw_price'             => '_price',
		'max_raw_price'             => '_wc_sw_max_price'
	);

	/**
	 * Callback to exclude composite-specific meta data.
	 *
	 * @param  object  $meta
	 * @return bool
	 */
	protected function exclude_internal_meta_keys( $meta ) {
		return parent::exclude_internal_meta_keys( $meta ) && ! in_array( $meta->meta_key, $this->extended_internal_meta_keys );
	}

	/**
	 * Reads all composite-specific post meta.
	 *
	 * @param  WC_Product_Composite  $product
	 */
	protected function read_product_data( &$product ) {

		parent::read_product_data( $product );

		$id           = $product->get_id();
		$props_to_set = array();

		foreach ( $this->props_to_meta_keys as $property => $meta_key ) {

			// Get meta value.
			$meta_value = get_post_meta( $id, $meta_key, true );

			// Back compat.
			if ( 'shop_price_calc' === $property && '' === $meta_value ) {
				if ( 'yes' === get_post_meta( $id, '_bto_hide_shop_price', true ) ) {
					$meta_value = 'hidden';
				} elseif ( '' !== $props_to_set[ 'layout' ] ) {
					$meta_value = 'min_max';
				}
			}

			// Add to props array.
			$props_to_set[ $property ] = $meta_value;
		}

		// Base prices are overridden by NYP min price.
		if ( $product->is_nyp() ) {
			$props_to_set[ 'price' ]      = $props_to_set[ 'regular_price' ] = get_post_meta( $id, '_min_price', true );
			$props_to_set[ 'sale_price' ] = '';
		}

		$product->set_props( $props_to_set );

		// Load component/scenario meta.
		$composite_meta = get_post_meta( $id, '_bto_data', true );
		$scenario_meta  = get_post_meta( $id, '_bto_scenario_data', true );

		$product->set_composite_data( $composite_meta );
		$product->set_scenario_data( $scenario_meta );
	}

	/**
	 * Writes all composite-specific post meta.
	 *
	 * @param  WC_Product_Composite  $product
	 * @param  boolean               $force
	 */
	protected function update_post_meta( &$product, $force = false ) {

		parent::update_post_meta( $product, $force );

		$id                 = $product->get_id();
		$meta_keys_to_props = array_flip( array_diff_key( $this->props_to_meta_keys, array( 'price' => 1, 'min_raw_price' => 1, 'max_raw_price' => 1 ) ) );
		$props_to_update    = $force ? $meta_keys_to_props : $this->get_props_to_update( $product, $meta_keys_to_props );

		foreach ( $props_to_update as $meta_key => $property ) {

			$property_get_fn = 'get_' . $property;

			// Get meta value.
			$meta_value = $product->$property_get_fn( 'edit' );

			// Sanitize it for storage.
			if ( 'editable_in_cart' === $property ) {
				$meta_value = wc_bool_to_string( $meta_value );
			}

			$updated = update_post_meta( $id, $meta_key, $meta_value );

			if ( $updated && ! in_array( $property, $this->updated_props ) ) {
				$this->updated_props[] = $property;
			}
		}

		// Save components/scenarios.
		update_post_meta( $id, '_bto_data', $product->get_composite_data( 'edit' ) );
		update_post_meta( $id, '_bto_scenario_data', $product->get_scenario_data( 'edit' ) );
	}

	/**
	 * Handle updated meta props after updating meta data.
	 *
	 * @param  WC_Product_Composite  $product
	 */
	protected function handle_updated_props( &$product ) {

		$id = $product->get_id();

		if ( in_array( 'date_on_sale_from', $this->updated_props ) || in_array( 'date_on_sale_to', $this->updated_props ) || in_array( 'regular_price', $this->updated_props ) || in_array( 'sale_price', $this->updated_props ) ) {
			if ( $product->is_on_sale( 'update-price' ) ) {
				update_post_meta( $id, '_bto_base_price', $product->get_sale_price( 'edit' ) );
				$product->set_price( $product->get_sale_price( 'edit' ) );
			} else {
				update_post_meta( $id, '_bto_base_price', $product->get_regular_price( 'edit' ) );
				$product->set_price( $product->get_regular_price( 'edit' ) );
			}
		}

		if ( in_array( 'stock_quantity', $this->updated_props ) ) {
			do_action( 'woocommerce_product_set_stock', $product );
		}

		if ( in_array( 'stock_status', $this->updated_props ) ) {
			do_action( 'woocommerce_product_set_stock_status', $product->get_id(), $product->get_stock_status(), $product );
		}

		// Trigger action so 3rd parties can deal with updated props.
		do_action( 'woocommerce_product_object_updated_props', $product, $this->updated_props );

		// After handling, we can reset the props array.
		$this->updated_props = array();
	}

	/**
	 * Writes composite raw price meta to the DB.
	 *
	 * @param  WC_Product_Composite  $product
	 */
	public function save_raw_prices( &$product ) {

		if ( defined( 'WC_CP_UPDATING' ) ) {
			return;
		}

		/**
		 * 'woocommerce_composite_update_price_meta' filter.
		 *
		 * Use this to prevent composite min/max raw price meta from being updated.
		 *
		 * @param  boolean               $update
		 * @param  WC_Product_Composite  $this
		 */
		$update_raw_price_meta = apply_filters( 'woocommerce_composite_update_price_meta', true, $product );

		if ( ! $update_raw_price_meta ) {
			return;
		}

		$id = $product->get_id();

		$updated_props   = array();
		$props_to_update = array_intersect( array_flip( $this->props_to_meta_keys ), array( 'min_raw_price', 'max_raw_price' ) );

		foreach ( $props_to_update as $meta_key => $property ) {

			$property_get_fn = 'get_' . $property;
			$meta_value      = $product->$property_get_fn( 'edit' );

			if ( update_post_meta( $id, $meta_key, $meta_value ) ) {
				$updated_props[] = $property;
			}
		}

		if ( ! empty( $updated_props ) ) {

			$sale_price_changed = false;

			if ( $product->is_on_sale( 'edit' ) ) {
				$sale_price_changed = update_post_meta( $id, '_sale_price', $product->get_min_raw_price( 'edit' ) );
			} else {
				$sale_price_changed = update_post_meta( $id, '_sale_price', '' );
			}

			if ( $sale_price_changed ) {
				delete_transient( 'wc_products_onsale' );
			}

			do_action( 'woocommerce_product_object_updated_props', $product, $updated_props );
		}
	}

	/**
	 * Calculates and returns:
	 *
	 * - The permutations that correspond to the minimum & maximum configuration price.
	 * - The minimum & maximum raw price.
	 *
	 * @param  WC_Product_Composite  $product
	 * @return array
	 */
	public function read_price_data( &$product ) {

		$components      = $product->get_components();
		$shop_price_calc = $product->get_shop_price_calc();

		$permutations = array(
			'min' => array(),
			'max' => array()
		);

		$component_option_prices = array();
		$component_options_count = 0;

		/**
		 * 'woocommerce_composite_price_data_permutation_vectors' filter.
		 *
		 * When searching for the permutations with the min/max price, use this filter to narrow down the initial search vectors and speed up the search.
		 * Typically you would use this filter to populate each Component vector only with product IDs that belong in the min/max price permutations.
		 * Of course this assumes that you know the min/max price permutations already.
		 *
		 * @param  array                 $vectors
		 * @param  WC_Product_Composite  $product
		 */
		$permutation_vectors         = apply_filters( 'woocommerce_composite_price_data_permutation_vectors', array(), $product );
		$permutation_vectors_calc    = array();
		$permutation_vector_data     = array();
		$permutations_calc_scenarios = apply_filters( 'woocommerce_composite_price_data_permutation_calc_scenarios', $product->scenarios()->exist() && function_exists( 'wc_cp_cartesian' ), $product );

		$has_conditional_component_scenarios = sizeof( $product->scenarios()->get_ids_by_action( 'conditional_components' ) );

		/*
		 * Set up permutation vectors.
		 */

		foreach ( $components as $component_id => $component ) {

			// Skip component if not priced individually.
			if ( $has_conditional_component_scenarios || $component->is_priced_individually() ) {

				if ( isset( $permutation_vectors_init[ $component_id ] ) ) {

					$component_options = (array) $permutation_vectors_init[ $component_id ];

				} else {

					$default_option = $component->get_default_option();

					if ( 'defaults' === $shop_price_calc ) {

						if ( $default_option ) {
							$component_options = array( $default_option );
						} elseif ( $component->is_optional() ) {
							$component_options = array( 0 );
						}

					} else {
						$component_options = $component->get_options();
					}
				}

				if ( ! empty( $component_options ) ) {

					// Add 0 to validate whether the component can be skipped.
					$component_options = in_array( 0, $component_options ) ? $component_options : array_merge( array( 0 ), $component_options );
					// Count.
					$component_options_count = $component_options_count * sizeof( $component_options );
					// Store data.
					$permutation_vector_data[ $component_id ][ 'option_ids' ]          = $component_options;
					$permutation_vector_data[ $component_id ][ 'parent_ids' ]          = $product->get_data_store()->get_expanded_component_options( $component_options, 'mapped' );
					$permutation_vector_data[ $component_id ][ 'expanded_option_ids' ] = $product->get_data_store()->get_expanded_component_options( $component_options, 'expanded' );

					// Set vectors.
					$permutation_vectors[ $component_id ] = $component_options;

					// Build expanded set.
					if ( $permutations_calc_scenarios ) {

						/**
						 * 'woocommerce_composite_price_data_permutation_search_accuracy_expand' filter.
						 *
						 * Expand the permutation search accuracy for this component to include variations?
						 *
						 * @since  3.14.0
						 *
						 * @param  bool  $expand
						 */
						$expand_permutation_search_accuracy = apply_filters( 'woocommerce_expand_composite_price_data_permutation_search_accuracy', true, $product, $component_id );

						if ( $expand_permutation_search_accuracy ) {

							$component_options_in_scenarios = array();

							foreach ( $product->scenarios()->get_scenarios() as $scenario ) {
								$component_options_in_scenarios = array_merge( $component_options_in_scenarios, $scenario->get_ids( $component_id ) );
							}

							if ( sizeof( array_diff( $component_options_in_scenarios, $component_options, array( 0, -1 ) ) ) ) {
								$permutation_vectors[ $component_id ] = $permutation_vector_data[ $component_id ][ 'expanded_option_ids' ];
							}
						}
					}
				}
			}
		}

		/*
		 * Set up prices.
		 */
		if ( ! empty( $permutation_vector_data ) ) {
			foreach ( $components as $component_id => $component ) {

				if ( ! isset( $permutation_vector_data[ $component_id ] ) ) {
					continue;
				}

				$component_option_prices[ $component_id ] = $product->get_data_store()->get_raw_component_option_prices( $permutation_vectors[ $component_id ] );

				if ( $permutations_calc_scenarios ) {
					$component_option_prices[ $component_id ][ 'min' ][ 0 ] = 0.0;
					$component_option_prices[ $component_id ][ 'max' ][ 0 ] = 0.0;
				}
			}

			/*
			 * Find cheapest/most expensive permutations taking scenarios into account.
			 */
			if ( $permutations_calc_scenarios ) {

				/**
				 * 'woocommerce_composite_permutations_search_time_limit' filter.
				 *
				 * Enter a min/max permutation search time limit. Default is 20 sec.
				 *
				 * @since  3.14.0
				 *
				 * @param  bool  $expand
				 */
				$search_time_limit = apply_filters( 'woocommerce_composite_permutations_search_time_limit', 20 );

				// Build a hash based on component option prices and products cache version, which should change when composite data is modified.
				$transient_hash = md5( json_encode( apply_filters( 'woocommerce_composite_permutations_transient_hash', array(
					$component_option_prices,
					$search_time_limit
				) ) ) );

				$transient_name   = 'wc_cp_permutation_data_' . $product->get_id();
				$permutation_data = get_transient( $transient_name );

				if ( ! is_array( $permutation_data ) ) {
					$permutation_data = WC_CP_Helpers::cache_get( $transient_name );
				}

				if ( ! defined( 'WC_CP_DEBUG_PERMUTATION_TRANSIENTS' ) && is_array( $permutation_data ) && isset( $permutation_data[ 'hash' ] ) && $permutation_data[ 'hash' ] === $transient_hash ) {

					$permutations[ 'min' ] = $permutation_data[ 'min' ];
					$permutations[ 'max' ] = $permutation_data[ 'max' ];

				} else {

					$min_price                = '';
					$max_price                = '';
					$start_time               = time();
					$permutations_count       = 1;
					$invalid_permutation_part = false;

					foreach ( wc_cp_cartesian( $permutation_vectors ) as $permutation ) {

						// Check the elapsed time every 10000 tests.
						if ( $permutations_count % 10000 === 9999 ) {
							if ( time() - $start_time > $search_time_limit ) {
								break;
							}
						}

						$permutations_count++;

						// Skip permutation if already found invalid.
						if ( is_array( $invalid_permutation_part ) ) {

							$validate_permutation = false;

							foreach ( $invalid_permutation_part as $invalid_permutation_part_key => $invalid_permutation_part_value ) {
								if ( $invalid_permutation_part_value !== $permutation[ $invalid_permutation_part_key ] ) {
									$validate_permutation = true;
									break;
								}
							}

							if ( ! $validate_permutation ) {
								continue;
							} else {
								$invalid_permutation_part = false;
							}
						}

						$configuration = array();

						foreach ( $permutation as $component_id => $component_option_id ) {

							// Is it a variation?
							if ( isset( $permutation_vector_data[ $component_id ][ 'parent_ids' ][ $component_option_id ] ) ) {
								$configuration[ $component_id ] = array(
									'product_id'   => $permutation_vector_data[ $component_id ][ 'parent_ids' ][ $component_option_id ],
									'variation_id' => $component_option_id
								);
							} else {
								$configuration[ $component_id ] = array(
									'product_id' => $component_option_id
								);
							}
						}

						$validation_result = $product->scenarios()->validate_configuration( $configuration );

						if ( is_wp_error( $validation_result ) ) {

							$error_data               = $validation_result->get_error_data( $validation_result->get_error_code() );
							$invalid_permutation_part = array();

							// Keep a copy of the invalid permutation up to the offending component.
							foreach ( $permutation as $component_id => $component_option_id ) {
								$invalid_permutation_part[ $component_id ] = $component_option_id;
								if ( $component_id === $error_data[ 'component_id' ] ) {
									break;
								}
							}

						} else {

							/*
							 * Find the permutation with the min/max price.
							 */
							$min_permutation_price = $max_permutation_price = 0.0;

							foreach ( $components as $component_id => $component ) {

								// Skip component if not relevant for price calculations.
								if ( ! isset( $permutation[ $component_id ] ) ) {
									continue;
								}

								$component_option_id = $permutation[ $component_id ];

								$component_option_price_min = 0.0;
								$component_option_price_max = 0.0;

								if ( $component_option_id > 0 ) {

									// Empty price.
									if ( ! isset( $component_option_prices[ $component_id ][ 'min' ][ $component_option_id ] ) ) {
										if ( $component->is_priced_individually() ) {
											continue 2;
										} else {
											continue;
										}
									}

									$component_option_price_min = $component_option_prices[ $component_id ][ 'min' ][ $component_option_id ];
									$component_option_price_max = $component_option_prices[ $component_id ][ 'max' ][ $component_option_id ];
								}

								$quantity_min = $component->get_quantity( 'min' );
								$quantity_max = $component->get_quantity( 'max' );

								$min_permutation_price += $quantity_min * (double) $component_option_price_min;

								if ( INF !== $max_permutation_price ) {
									if ( INF !== $component_option_price_max && '' !== $quantity_max ) {
										$max_permutation_price += $quantity_max * (double) $component_option_price_max;
									} else {
										$max_permutation_price = INF;
									}
								}
							}

							if ( $min_permutation_price < $min_price || '' === $min_price ) {
								$permutations[ 'min' ] = $permutation;
								$min_price             = $min_permutation_price;
							}

							if ( INF !== $max_permutation_price ) {
								if ( $max_permutation_price > $max_price || '' === $max_price ) {
									$permutations[ 'max' ] = $permutation;
									$max_price             = $max_permutation_price;
								}
							} else {
								$permutations[ 'max' ] = array();
							}
						}
					}

					$permutation_data = array(
						'min'  => $permutations[ 'min' ],
						'max'  => $permutations[ 'max' ],
						'hash' => $transient_hash
					);

					set_transient( $transient_name, $permutation_data, DAY_IN_SECONDS * 30 );
					WC_CP_Helpers::cache_set( $transient_name, $permutation_data );
				}

			/*
			 * Find cheapest/most expensive permutation without considering scenarios.
			 */
			} else {

				$has_inf_max_price = false;

				/*
				 * Use filtered prices to find the permutation with the min/max price.
				 */
				foreach ( $components as $component_id => $component ) {

					if ( ! isset( $permutation_vectors[ $component_id ] ) ) {
						continue;
					}

					if ( empty( $component_option_prices[ $component_id ] ) ) {
						continue;
					}

					$component_option_prices_min = $component_option_prices[ $component_id ][ 'min' ];
					asort( $component_option_prices_min );

					$component_option_prices_max = $component_option_prices[ $component_id ][ 'max' ];
					asort( $component_option_prices_max );

					$min_component_price = current( $component_option_prices_min );
					$max_component_price = end( $component_option_prices_max );

					$min_component_price_ids = array_keys( $component_option_prices_min );
					$max_component_price_ids = array_keys( $component_option_prices_max );

					$min_component_price_id  = current( $min_component_price_ids );
					$max_component_price_id  = end( $max_component_price_ids );

					$quantity_min = $component->get_quantity( 'min' );
					$quantity_max = $component->get_quantity( 'max' );

					$permutations[ 'min' ][ $component_id ] = $component->is_optional() || 0 === $quantity_min ? 0 : $min_component_price_id;

					if ( ! $has_inf_max_price ) {
						if ( INF !== $max_component_price && '' !== $quantity_max ) {
							$permutations[ 'max' ][ $component_id ] = $max_component_price_id;
						} else {
							$permutations[ 'max' ] = array();
							$has_inf_max_price     = true;
						}
					}
				}
			}
		}

		return array(
			'permutations' => $permutations,
			'raw_prices'   => $component_option_prices
		);
	}

	/**
	 * Get raw product prices straight from the DB.
	 *
	 * @param  array  $product_ids
	 * @return array
	 */
	public function get_raw_component_option_prices( $product_ids ) {

		global $wpdb;

		$expanded_ids = $this->get_expanded_component_options( $product_ids, 'expanded' );
		$parent_ids   = $this->get_expanded_component_options( $product_ids, 'mapped' );

		$results_cache_key = 'raw_component_option_prices_' . md5( json_encode( $product_ids ) );
		$results           = WC_CP_Helpers::cache_get( $results_cache_key );

		if ( null === $results ) {

			$results = $wpdb->get_results( "
				SELECT postmeta.post_id AS id, postmeta.meta_value as price FROM {$wpdb->postmeta} AS postmeta
				WHERE postmeta.meta_key = '_price'
				AND postmeta.post_id IN ( " . implode( ',', $expanded_ids ) . " )
			", ARRAY_A );

			WC_CP_Helpers::cache_set( $results_cache_key, $results );
		}

		$prices = array(
			'min' => array(),
			'max' => array()
		);

		if ( class_exists( 'WC_Name_Your_Price_Helpers' ) ) {

			$nyp_results_cache_key = $results_cache_key . '_nyp';
			$nyp_results           = WC_CP_Helpers::cache_get( $nyp_results_cache_key );

			if ( null === $nyp_results ) {

				$nyp_id_results = $wpdb->get_results( "
					SELECT postmeta.post_id AS id FROM {$wpdb->postmeta} AS postmeta
					WHERE postmeta.meta_key = '_nyp'
					AND postmeta.meta_value = 'yes'
					AND postmeta.post_id IN ( " . implode( ',', $expanded_ids ) . " )
				", ARRAY_A );

				$nyp_ids     = wp_list_pluck( $nyp_id_results, 'id' );
				$nyp_results = array();

				if ( ! empty( $nyp_ids ) ) {
					$nyp_results = $wpdb->get_results( "
						SELECT postmeta.post_id AS id, postmeta.meta_value AS min_price FROM {$wpdb->postmeta} AS postmeta
						WHERE postmeta.meta_key = '_min_price'
						AND postmeta.post_id IN ( " . implode( ',', $nyp_ids ) . " )
					", ARRAY_A );
				}

				WC_CP_Helpers::cache_set( $nyp_results_cache_key, $nyp_results );
			}

			foreach ( $nyp_results as $nyp_result ) {

				// Is it a variation?
				if ( isset( $parent_ids[ $nyp_result[ 'id' ] ] ) ) {

					// If it's included in the search vector, then add an entry.
					if ( in_array( $nyp_result[ 'id' ], $product_ids ) ) {
						$product_id = $nyp_result[ 'id' ];
					// Otherwise, add an entry for the parent.
					} else {
						$product_id = $parent_ids[ $nyp_result[ 'id' ] ];
					}

				} else {
					$product_id = $nyp_result[ 'id' ];
				}

				$price_min = '' === $nyp_result[ 'min_price' ] ? 0.0 : (double) $nyp_result[ 'min_price' ];
				$price_max = INF;

				$prices[ 'min' ][ $product_id ] = $price_min;
				$prices[ 'max' ][ $product_id ] = $price_max;
			}
		}

		// Multiple '_price' meta may exist.
		foreach ( $results as $result ) {

			if ( '' === $result[ 'price' ] ) {
				continue;
			}

			// Is it a variation?
			if ( isset( $parent_ids[ $result[ 'id' ] ] ) ) {

				// If it's included in the search vector, then add an entry.
				if ( in_array( $result[ 'id' ], $product_ids ) ) {
					$product_id = $result[ 'id' ];
				// Otherwise, add an entry for the parent.
				} else {
					$product_id = $parent_ids[ $result[ 'id' ] ];
				}

			} else {
				$product_id = $result[ 'id' ];
			}

			$price_min = isset( $prices[ 'min' ][ $product_id ] ) ? min( (double) $result[ 'price' ], $prices[ 'min' ][ $product_id ] ) : (double) $result[ 'price' ];
			$price_max = isset( $prices[ 'max' ][ $product_id ] ) ? max( (double) $result[ 'price' ], $prices[ 'max' ][ $product_id ] ) : (double) $result[ 'price' ];

			$prices[ 'min' ][ $product_id ] = $price_min;
			$prices[ 'max' ][ $product_id ] = $price_max;
		}

		return $prices;
	}

	/**
	 * Get expanded component options to include variations straight from the DB.
	 *
	 * @since  3.14.0
	 *
	 * @param  array  $product_ids
	 * @return array
	 */
	public static function get_expanded_component_options( $product_ids, $return = 'expanded' ) {

		global $wpdb;

		$results = array(
			'merged'   => array(),
			'expanded' => array(),
			'mapped'   => array()
		);

		if ( empty( $product_ids ) ) {
			return $results;
		}

		$results_cache_key = 'expanded_component_options_' . md5( json_encode( $product_ids ) );
		$cached_results    = WC_CP_Helpers::cache_get( $results_cache_key );

		if ( null === $cached_results ) {

			$query_results = $wpdb->get_results( "
				SELECT posts.ID AS id, posts.post_parent as parent_id FROM {$wpdb->posts} AS posts
				WHERE posts.post_type = 'product_variation'
				AND post_parent IN ( " . implode( ',', $product_ids ) . " )
				AND post_parent > 0
				AND posts.post_status = 'publish'
			", ARRAY_A );

			$results[ 'merged' ]   = array_merge( $product_ids, wp_list_pluck( $query_results, 'id' ) );
			$results[ 'expanded' ] = array_merge( array_diff( $product_ids, wp_list_pluck( $query_results, 'parent_id' ) ), wp_list_pluck( $query_results, 'id' ) );
			$results[ 'mapped' ]   = empty( $query_results ) ? array() : array_combine( wp_list_pluck( $query_results, 'id' ), wp_list_pluck( $query_results, 'parent_id' ) );

			$cached_results = $results;

			WC_CP_Helpers::cache_set( $results_cache_key, $cached_results );
		}

		return $cached_results[ $return ];
	}

	/**
	 * Use 'WP_Query' to preload product data from the 'posts' table.
	 * Useful when we know we are going to call 'wc_get_product' against a list of IDs.
	 *
	 * @since  3.13.2
	 *
	 * @param  array  $product_ids
	 * @return void
	 */
	public function preload_component_options_data( $product_ids ) {

		if ( empty( $product_ids ) ) {
			return;
		}

		$cache_key = 'wc_component_options_db_data_' . md5( json_encode( $product_ids ) );
		$data      = WC_CP_Helpers::cache_get( $cache_key );

		if ( null === $data ) {

			$data = new WP_Query( array(
				'post_type' => 'product',
				'nopaging'  => true,
				'post__in'  => $product_ids
			) );

			WC_CP_Helpers::cache_set( $cache_key, $data );
		}
	}

	/**
	 * Component option query handler.
	 *
	 * @since  3.14.0
	 *
	 * @param  array  $component_data
	 * @param  array  $query_args
	 * @return array
	 */
	public function query_component_options( $component_data, $query_args ) {

		$defaults = array(
			// Set to false when running raw queries.
			'orderby'              => false,
			// Use false to get all results -- set to false when running raw queries or dropdown-template queries.
			'per_page'             => false,
			// Page number to load, in effect only when 'per_page' is set.
			// When set to 'selected', 'load_page' will point to the page that contains the current option, passed in 'selected_option'.
			'load_page'            => 1,
			'post_ids'             => ! empty( $component_data[ 'assigned_ids' ] ) ? $component_data[ 'assigned_ids' ] : false,
			'query_type'           => ! empty( $component_data[ 'query_type' ] ) ? $component_data[ 'query_type' ] : 'product_ids',
			// ID of selected option, used when 'load_page' is set to 'selected'.
			'selected_option'      => '',
			'disable_cache'        => false,
			// Out of stock options included in results by default. Use 'woocommerce_composite_component_options_query_args_current' filter to set to true.
			'exclude_out_of_stock' => false
		);

		$query_args = wp_parse_args( $query_args, $defaults );
		$args       = array(
			'post_type'           => 'product',
			'post_status'         => 'publish',
			'ignore_sticky_posts' => 1,
			'nopaging'            => true,
			'order'               => 'desc',
			'fields'              => 'ids',
			'meta_query'          => array()
		);

		$use_transients_cache = false;

		/*-----------------------------------------------------------------------------------*/
		/*  Prepare query for product IDs.                                                   */
		/*-----------------------------------------------------------------------------------*/

		if ( 'product_ids' === $query_args[ 'query_type' ] ) {

			if ( $query_args[ 'post_ids' ] ) {
				$args[ 'post__in' ] = array_values( $query_args[ 'post_ids' ] );
			} else {
				$args[ 'post__in' ] = array( '0' );
			}
		}

		/*-----------------------------------------------------------------------------------*/
		/*  Sort results.                                                                    */
		/*-----------------------------------------------------------------------------------*/

		$orderby = $query_args[ 'orderby' ];

		if ( $orderby ) {

			$orderby_value = explode( '-', $orderby );
			$orderby       = esc_attr( $orderby_value[0] );
			$order         = ! empty( $orderby_value[1] ) ? $orderby_value[1] : '';

			switch ( $orderby ) {

				case 'default' :
					if ( 'product_ids' === $query_args[ 'query_type' ] ) {
						$args[ 'orderby' ] = 'post__in';
					}
				break;

				case 'menu_order' :
					if ( 'product_ids' === $query_args[ 'query_type' ] ) {
						$args[ 'orderby' ] = 'menu_order title';
						$args[ 'order' ]   = $order == 'desc' ? 'desc' : 'asc';
					}
				break;

				case 'rand' :
					$args[ 'orderby' ]  = 'rand';
				break;

				case 'date' :
					$args[ 'orderby' ]  = 'date';
				break;

				case 'price' :
					$args[ 'orderby' ]  = 'meta_value_num';
					$args[ 'meta_key' ] = '_price';
					$args[ 'order' ]    = $order == 'desc' ? 'desc' : 'asc';
				break;

				case 'popularity' :
					$args[ 'orderby' ]  = 'meta_value_num';
					$args[ 'meta_key' ] = 'total_sales';
				break;

				case 'rating' :
					// Sorting handled later though a hook
					add_filter( 'posts_clauses', array( $this, 'query_order_by_rating_post_clauses' ) );
				break;

				case 'title' :
					$args[ 'orderby' ] = 'title';
					$args[ 'order' ]   = $order == 'desc' ? 'desc' : 'asc';
				break;

			}

		// In effect for back-end queries and queries carried out during sync().
		} else {

			// Make ids appear in the sequence they are saved
			if ( 'product_ids' === $query_args[ 'query_type' ] ) {
				$args[ 'orderby' ] = 'post__in';
			}
		}

		/*-----------------------------------------------------------------------------------*/
		/*	Remove out-of-stock results in front-end queries.
		/*-----------------------------------------------------------------------------------*/

		if ( false !== $query_args[ 'orderby' ] || false !== $query_args[ 'per_page' ] ) {

			if ( 'yes' === get_option( 'woocommerce_hide_out_of_stock_items' ) || ( isset( $query_args[ 'exclude_out_of_stock' ] ) && $query_args[ 'exclude_out_of_stock' ] ) ) {

				$product_visibility_terms = wc_get_product_visibility_term_ids();

				$args[ 'tax_query' ][] = array(
					'taxonomy'      => 'product_visibility',
					'field'         => 'term_taxonomy_id',
					'terms'         => $product_visibility_terms[ 'outofstock' ],
					'operator'      => 'NOT IN'
				);
			}
		}

		/*-----------------------------------------------------------------------------------*/
		/*  Pagination.                                                                      */
		/*-----------------------------------------------------------------------------------*/

		$load_selected_page = false;

		// Check if we need to find the page that contains the current selection -- 'load_page' must be set to 'selected' and all relevant parameters must be provided.

		if ( 'selected' === $query_args[ 'load_page' ] ) {

			if ( $query_args[ 'per_page' ] && $query_args[ 'selected_option' ] !== '' ) {
				$load_selected_page = true;
			} else {
				$query_args[ 'load_page' ] = 1;
			}
		}

		// Otherwise, just check if we need to do a paginated query -- note that when looking for the page that contains the current selection, we are running an unpaginated query first.

		if ( $query_args[ 'per_page' ] && false === $load_selected_page ) {

			$args[ 'nopaging' ]       = false;
			$args[ 'posts_per_page' ] = $query_args[ 'per_page' ];
			$args[ 'paged' ]          = $query_args[ 'load_page' ];
		}

		/*-----------------------------------------------------------------------------------*/
		/*  Optimize 'raw' queries.                                                          */
		/*-----------------------------------------------------------------------------------*/

		if ( false === $query_args[ 'orderby' ] && false === $query_args[ 'per_page' ] ) {

			$args[ 'update_post_term_cache' ] = false;
			$args[ 'update_post_meta_cache' ] = false;
			$args[ 'cache_results' ]          = false;

			if ( false === $query_args[ 'disable_cache' ] && ! empty( $component_data[ 'component_id' ] ) && ! empty( $component_data[ 'composite_id' ] ) && ! defined( 'WC_CP_DEBUG_QUERY_TRANSIENTS' ) ) {
				$use_transients_cache = true;
			}
		}

		/*-----------------------------------------------------------------------------------*/
		/*  Filtering attributes?                                                            */
		/*-----------------------------------------------------------------------------------*/

		if ( ! empty( $query_args[ 'filters' ] ) && ! empty( $query_args[ 'filters' ][ 'attribute_filter' ] ) ) {

			$attribute_filters = $query_args[ 'filters' ][ 'attribute_filter' ];

			$args[ 'tax_query' ][ 'relation' ] = 'AND';

			foreach ( $attribute_filters as $taxonomy_attribute_name => $selected_attribute_values ) {

				$args[ 'tax_query' ][] = array(
					'taxonomy' => $taxonomy_attribute_name,
					'terms'    => $selected_attribute_values,
					'operator' => 'IN'
				);
			}
		}

		/*-----------------------------------------------------------------------------------*/
		/*  Querying by category?                                                            */
		/*-----------------------------------------------------------------------------------*/

		if ( 'category_ids' === $query_args[ 'query_type' ] ) {

			$args[ 'tax_query' ][] = array(
				'relation' => 'AND',
				array(
					'taxonomy' => 'product_cat',
					'terms'    => ! empty( $component_data[ 'assigned_category_ids' ] ) ? array_values( $component_data[ 'assigned_category_ids' ] ) : array( '0' ),
					'operator' => 'IN'
				),
				array(
					'taxonomy' => 'product_type',
					'field'    => 'name',
					'terms'    => apply_filters( 'woocommerce_composite_products_supported_types', array( 'simple', 'variable', 'bundle' ) ),
					'operator' => 'IN'
				)
			);

		}

		/*-----------------------------------------------------------------------------------*/
		/*  Modify query and apply filters by hooking at this point.                         */
		/*-----------------------------------------------------------------------------------*/

		/**
		 * Filter args passed to WP_Query.
		 *
		 * @param  array  $wp_query_args
		 * @param  array  $cp_query_args
		 * @param  array  $component_data
		 */
		$args = apply_filters( 'woocommerce_composite_component_options_query_args', $args, $query_args, $component_data );

		/*-----------------------------------------------------------------------------------*/
		/*  Go for it.                                                                       */
		/*-----------------------------------------------------------------------------------*/

		$query                = false;
		$cached_results       = false;
		$cached_results_array = false;
		$component_id         = $use_transients_cache ? $component_data[ 'component_id' ] : '';
		$transient_name       = $use_transients_cache ? 'wc_cp_query_results_' . $component_data[ 'composite_id' ] : '';
		$cached_results_array = $use_transients_cache ? get_transient( $transient_name ) : false;

		// Is it an array indexed by component ID?
		if ( is_array( $cached_results_array ) && ! isset( $cached_results_array[ 'version' ] ) ) {
			// Does it contain cached query results for this component?
			if ( isset( $cached_results_array[ $component_id ] ) && is_array( $cached_results_array[ $component_id ] ) ) {
				// Are the results up-to-date?
				if ( isset( $cached_results_array[ $component_id ][ 'version' ] ) && $cached_results_array[ $component_id ][ 'version' ] === WC_Cache_Helper::get_transient_version( 'product' ) ) {
					$cached_results = $cached_results_array[ $component_id ];
				}
			}
		}

		if ( false === $cached_results ) {

			$query   = new WP_Query( $args );
			$results = array(
				'query_args'        => $query_args,
				'pages'             => $query->max_num_pages,
				'current_page'      => $query->get( 'paged' ),
				'component_options' => $query->posts
			);

			if ( $use_transients_cache ) {

				if ( is_array( $cached_results_array ) && ! isset( $cached_results_array[ 'version' ] ) ) {
					$cached_results_array[ $component_id ] = array_merge( $results, array( 'version' => WC_Cache_Helper::get_transient_version( 'product' ) ) );
				} else {
					$cached_results_array = array(
						$component_id => array_merge( $results, array( 'version' => WC_Cache_Helper::get_transient_version( 'product' ) ) )
					);
				}

				set_transient( $transient_name, $cached_results_array, DAY_IN_SECONDS * 7 );
			}

		} else {
			$results = $cached_results;
		}

		/*-----------------------------------------------------------------------------------------------------------------------------------------------*/
		/*  When told to do so, use the results of the 1st query to find the page that contains the current selection.                                   */
		/*-----------------------------------------------------------------------------------------------------------------------------------------------*/

		if ( $load_selected_page && $query_args[ 'per_page' ] && $query_args[ 'per_page' ] < $query->found_posts ) {

			$results               = ! empty( $results[ 'component_options' ] ) ? $results[ 'component_options' ] : array();
			$selected_option_index = array_search( $query_args[ 'selected_option' ], $results ) + 1;
			$selected_option_page  = ceil( $selected_option_index / $query_args[ 'per_page' ] );

			// Sorting and filtering has been done, so now just run a simple query to paginate the results.
			if ( ! empty( $results ) ) {

				$selected_args = array(
					'post_type'           => 'product',
					'post_status'         => 'publish',
					'ignore_sticky_posts' => 1,
					'nopaging'            => false,
					'posts_per_page'      => $query_args[ 'per_page' ],
					'paged'               => $selected_option_page,
					'order'               => 'desc',
					'orderby'             => 'post__in',
					'post__in'            => $results,
					'fields'              => 'ids',
				);

				$query = new WP_Query( $selected_args );

				$results = array(
					'query_args'        => $query_args,
					'pages'             => $query->max_num_pages,
					'current_page'      => $query->get( 'paged' ),
					'component_options' => $query->posts
				);
			}
		}

		return $results;
	}

	/**
	 * Sorts results by rating.
	 *
	 * @since  3.14.0
	 *
	 * @param  array $args
	 * @return array
	 */
	public function query_order_by_rating_post_clauses( $args ) {

		global $wpdb;

		$args[ 'fields' ] .= ", AVG( $wpdb->commentmeta.meta_value ) as average_rating ";
		$args[ 'where' ]  .= " AND ( $wpdb->commentmeta.meta_key = 'rating' OR $wpdb->commentmeta.meta_key IS null ) ";
		$args[ 'join' ]   .= "
			LEFT OUTER JOIN $wpdb->comments ON($wpdb->posts.ID = $wpdb->comments.comment_post_ID)
			LEFT JOIN $wpdb->commentmeta ON($wpdb->comments.comment_ID = $wpdb->commentmeta.comment_id)
		";

		$args[ 'orderby' ] = "average_rating DESC, $wpdb->posts.post_date DESC";
		$args[ 'groupby' ] = "$wpdb->posts.ID";

		remove_filter( 'posts_clauses', array( $this, 'query_order_by_rating_post_clauses' ) );

		return $args;
	}
}
