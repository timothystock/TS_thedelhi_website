jQuery( function( $ ) {

	var enhanced_select_format_string = {
		'language' : {
			errorLoading: function() {
				// Workaround for https://github.com/select2/select2/issues/4355 instead of i18n_ajax_error.
				return wc_composite_admin_params.i18n_searching;
			},
			inputTooLong: function( args ) {
				var overChars = args.input.length - args.maximum;

				if ( 1 === overChars ) {
					return wc_composite_admin_params.i18n_input_too_long_1;
				}

				return wc_composite_admin_params.i18n_input_too_long_n.replace( '%qty%', overChars );
			},
			inputTooShort: function( args ) {
				var remainingChars = args.minimum - args.input.length;

				if ( 1 === remainingChars ) {
					return wc_composite_admin_params.i18n_input_too_short_1;
				}

				return wc_composite_admin_params.i18n_input_too_short_n.replace( '%qty%', remainingChars );
			},
			loadingMore: function() {
				return wc_composite_admin_params.i18n_load_more;
			},
			maximumSelected: function( args ) {
				if ( args.maximum === 1 ) {
					return wc_composite_admin_params.i18n_selection_too_long_1;
				}

				return wc_composite_admin_params.i18n_selection_too_long_n.replace( '%qty%', args.maximum );
			},
			noResults: function() {
				return wc_composite_admin_params.i18n_no_matches;
			},
			searching: function() {
				return wc_composite_admin_params.i18n_searching;
			}
		}
	};

	// Regular select2 fields init. Code duplicated to prevent expensive DOM searches when firing 'wc-enhanced-select-init'.
	$.fn.wc_cp_select2 = function() {

		// Regular select boxes.
		$( ':input.wc-enhanced-select', this ).filter( ':not(.enhanced)' ).each( function() {

			var $el          = $( this ),
				select2_args = $.extend( {
					minimumResultsForSearch: 10,
					allowClear:      $el.data( 'allow_clear' ) ? true : false,
					placeholder:     $el.data( 'placeholder' ),
					closeOnSelect:   true,
					dropdownParent:  $el.closest( '#bto_product_data, #bto_scenario_data' )
				}, enhanced_select_format_string );

			if ( 'yes' === wc_composite_admin_params.is_wc_version_gte_3_2 ) {
				$el.selectWoo( select2_args ).addClass( 'enhanced' );
			} else {
				$el.select2( select2_args ).addClass( 'enhanced' );
			}

			// Prevent opening on delete.
			$el
				.on( 'select2:unselecting', function() {
					$el.data( 'unselecting', true );
				} )

				.on( 'select2:opening', function( e ) {
					if ( $el.data( 'unselecting' ) ) {
						$el.removeData( 'unselecting' );
						e.preventDefault();
					}
				} );

			if ( $el.data( 'sortable' ) ) {

				var $list = $el.next( '.select2-container' ).find( 'ul.select2-selection__rendered' );

				$list.sortable( {
					placeholder : 'ui-state-highlight select2-selection__choice',
					forcePlaceholderSize: true,
					items       : 'li:not(.select2-search__field)',
					tolerance   : 'pointer',
					stop: function() {

						$( $list.find( '.select2-selection__choice' ).get().reverse() ).each( function() {

							var id     = $( this ).data( 'data' ).id,
								option = $el.find( 'option[value="' + id + '"]' )[0];

							$el.prepend( option );
						} );
					}
				} );
			}

		} );

		// Ajax product search box.
		$( ':input.wc-product-search', this ).filter( ':not(.enhanced)' ).each( function() {

			var $el          = $( this ),
				select2_args = {
				allowClear:         $el.data( 'allow_clear' ) ? true : false,
				placeholder:        $el.data( 'placeholder' ),
				minimumInputLength: $el.data( 'minimum_input_length' ) ? $el.data( 'minimum_input_length' ) : '3',
				escapeMarkup: function( m ) {
					return m;
				},
				ajax: {
					url:         wc_enhanced_select_params.ajax_url,
					dataType:    'json',
					delay:       250,
					data:        function( params ) {
						return {
							term:     params.term,
							action:   $el.data( 'action' ) || 'woocommerce_json_search_products_and_variations',
							security: wc_enhanced_select_params.search_products_nonce,
							exclude:  $el.data( 'exclude' ),
							include:  $el.data( 'include' ),
							limit:    $el.data( 'limit' )
						};
					},
					processResults: function( data ) {
						var terms = [];
						if ( data ) {
							$.each( data, function( id, text ) {
								terms.push( { id: id, text: text } );
							} );
						}
						return {
							results: terms
						};
					},
					cache: true
				}
			};

			select2_args = $.extend( select2_args, enhanced_select_format_string );

			if ( 'yes' === wc_composite_admin_params.is_wc_version_gte_3_2 ) {
				$el.selectWoo( select2_args ).addClass( 'enhanced' );
			} else {
				$el.select2( select2_args ).addClass( 'enhanced' );
			}

			// Prevent opening on delete.
			$el
				.on( 'select2:unselecting', function() {
					$el.data( 'unselecting', true );
				} )

				.on( 'select2:opening', function( e ) {
					if ( $el.data( 'unselecting' ) ) {
						$el.removeData( 'unselecting' );
						e.preventDefault();
					}
				} );

			if ( $el.data( 'sortable' ) ) {

				var $list = $( this ).next( '.select2-container' ).find( 'ul.select2-selection__rendered' );

				$list.sortable( {
					placeholder : 'ui-state-highlight select2-selection__choice',
					forcePlaceholderSize: true,
					items       : 'li:not(.select2-search__field)',
					tolerance   : 'pointer',
					stop: function() {

						$( $list.find( '.select2-selection__choice' ).get().reverse() ).each( function() {

							var id     = $( this ).data( 'data' ).id,
								option = $el.find( 'option[value="' + id + '"]' )[0];

							$el.prepend( option );
						} );

						if ( $el.hasClass( 'products_selector' ) ) {

							var $component_el   = $el.closest( '.bto_group' ),
								component_el_id = $component_el.data( 'component_metabox_id' ),
								component       = component_objects[ component_el_id ];

							component.products_changed();
						}
					}
				} );

			// Keep multiselects ordered alphabetically if they are not sortable.
			} else if ( $el.prop( 'multiple' ) ) {

				$el.on( 'change', function() {

					var $children = $el.children();

					$children.sort( function( a, b ) {

						var atext = a.text.toLowerCase(),
							btext = b.text.toLowerCase();

						if ( atext > btext ) {
							return 1;
						}

						if ( atext < btext ) {
							return -1;
						}

						return 0;
					} );

					$el.html( $children );
				} );
			}
		} );
	};

	// Custom select2 fields init.
	$.fn.wc_cp_select2_component_options = function() {

		$( ':input.wc-component-options-search', this ).filter( ':not(.enhanced)' ).each( function() {

			var $select      = $( this ),
				action       = $select.data( 'action' ),
				select2_args = {
				allowClear:         $select.data( 'allow_clear' ) ? true : false,
				placeholder:        $select.data( 'placeholder' ),
				minimumInputLength: $select.data( 'minimum_input_length' ) ? $( this ).data( 'minimum_input_length' ) : '3',
				escapeMarkup: function( m ) {
					return m;
				},
				ajax: {
					url:         wc_enhanced_select_params.ajax_url,
					dataType:    'json',
					quietMillis: 250,
					data: function( params ) {
						return {
							term:      params.term,
							action:    action,
							security:  wc_enhanced_select_params.search_products_nonce,
							exclude:   $select.data( 'exclude' ),
							include:   $select.data( 'include' ),
							limit:     $select.data( 'limit' )
						};
					},
					processResults: function( data ) {
						var terms = [];

						if ( 'yes' === $select.data( 'component_optional' ) ) {
							terms.push( { id: '-1', text: wc_composite_admin_params.i18n_none } );
						}

						terms.push( { id: '0', text: wc_composite_admin_params.i18n_all } );

						if ( data ) {
							$.each( data, function( id, text ) {
								terms.push( { id: id, text: text } );
							} );
						}

						return { results: terms };
					},

					cache: true
				}
			};

			select2_args = $.extend( select2_args, enhanced_select_format_string );

			if ( 'yes' === wc_composite_admin_params.is_wc_version_gte_3_2 ) {
				$( this ).selectWoo( select2_args ).addClass( 'enhanced' );
			} else {
				$( this ).select2( select2_args ).addClass( 'enhanced' );
			}

			// Prevent opening on delete.
			$select
				.on( 'select2:unselecting', function() {
					$select.data( 'unselecting', true );
				} )

				.on( 'select2:opening', function( e ) {
					if ( $select.data( 'unselecting' ) ) {
						$select.removeData( 'unselecting' );
						e.preventDefault();
					}
				} );

		} );
	};

	var $components_panel          = $( '#bto_product_data' ),
		$composite_price_calc      = $components_panel.find( '#_bto_shop_price_calc' ),
		$components_toggle_toolbar = $components_panel.find( '.bulk_toggle_wrapper' ),
		$components_container      = $( '.config_group', $components_panel ),
		$component_metaboxes       = $( '.bto_groups', $components_container ),
		$components                = $( '.bto_group', $component_metaboxes ),
		component_add_count        = $components.length,
		component_objects          = {},
		$scenarios_panel           = $( '#bto_scenario_data' ),
		$scenarios_toggle_toolbar  = $scenarios_panel.find( '.bulk_toggle_wrapper' ),
		$scenarios_container       = $( '.scenarios_config_group', $scenarios_panel ),
		$scenario_metaboxes        = $( '.bto_scenarios', $scenarios_panel ),
		$scenarios                 = $( '.bto_scenario', $scenario_metaboxes ),
		scenario_add_count         = $scenarios.length,
		scenario_objects           = {},
		component_data_dirty       = false,
		component_image_frame_data = {
			image_frame: false,
			$button:     false
		},
		block_params               = {
			message:    null,
			overlayCSS: {
				background: '#fff',
				opacity:    0.6
			}
		},
		tip_tip_params             = {
			'attribute': 'data-tip',
			'fadeIn':    50,
			'fadeOut':   50,
			'delay':     200
		};

	// Composite type move stock msg up.
	$( '.composite_stock_msg' ).appendTo( '._manage_stock_field .description' );

	// Hide the default "Sold Individually" field.
	$( '#_sold_individually' ).closest( '.form-field' ).addClass( 'hide_if_composite' );

	// Hide the "Grouping" field.
	$( '#linked_product_data .grouping.show_if_simple, #linked_product_data .form-field.show_if_grouped' ).addClass( 'hide_if_composite' );

	// Simple type options are valid for bundles.
	$( '.show_if_simple:not(.hide_if_composite)' ).addClass( 'show_if_composite' );

	if ( typeof woocommerce_admin_meta_boxes === 'undefined' ) {
		woocommerce_admin_meta_boxes = woocommerce_writepanel_params;
	}

	// Composite type specific options.
	$( 'body' ).on( 'woocommerce-product-type-change', function( event, select_val ) {

		if ( 'composite' === select_val ) {

			$( '.show_if_external' ).hide();
			$( '.show_if_composite' ).show();

			$( 'input#_manage_stock' ).change();
		}

	} );

	// Trigger product type change.
	$( 'select#product-type' ).change();

	// Downloadable support.
	$( 'input#_downloadable' ).change( function() {
		$( 'select#product-type' ).change();
	} );

	// Save initial 'Catalog Price' value.
	$composite_price_calc.data( 'val', $composite_price_calc.val() );

	/*
	 * Handle events in Components panel.
	 */
	$components_panel

		// Ignore other click events.
		.off( 'click' )

		// Layout selection.
		.on( 'click', '.bto_layout_label', function() {
			$( this ).closest( '.bto_layouts' ).find( '.selected' ).removeClass( 'selected' );
			$( this ).addClass( 'selected' );

		} )

		// Trigger event when 'Catalog Price' option changes.
		.on( 'change', '#_bto_shop_price_calc', function() {

			var new_calc            = $( this ).val(),
				unset_default_found = false;

			// Any components with an empty default?
			if ( 'defaults' === new_calc ) {

				$.each( component_objects, function( index, component_object ) {

					if ( ! component_object.is_optional() && ! component_object.get_default_option() ) {

						unset_default_found = true;

						// Alert user.
						window.alert( wc_composite_admin_params.i18n_defaults_unset );
						// Put back the old value.
						$composite_price_calc.val( $composite_price_calc.data( 'val' ) );
						return false;
					}
				} );
			}

			if ( ! unset_default_found ) {
				$composite_price_calc.data( 'val', new_calc );
			}
		} )

		// Remove onboarding elements when adding component.
		.one( 'wc-cp-component-added', function() {
			$components_container.removeClass( 'options_group--boarding' );
		} )

		// Update component DOM elements, menu order and toolbar state.
		.on( 'wc-cp-components-changed', function() {

			$component_metaboxes = $( '.bto_groups', $components_container );
			$components          = $( '.bto_group', $component_metaboxes );

			$components.each( function( index, el ) {
				$( '.group_position', el ).val( index );
				$( el ).attr( 'rel', index );
			} );

			// Component data must be saved before viewing the Scenarios panel, or adding new scenarios.
			set_component_data_dirty_state( true );

			update_components_toolbar_state();
		} );

	/*
	 * Handle events in Scenarios panel.
	 */

	// Update scenario DOM elements, menu order and toolbar state.
	$scenarios_panel

		.off( 'click' )

		// Remove onboarding elements when adding scenario.
		.one( 'wc-cp-scenario-added', function() {
			$scenarios_container.removeClass( 'options_group--boarding' );
		} )

		// Update toolbar state.
		.on( 'wc-cp-scenarios-changed', function() {

			$scenario_metaboxes = $( '.bto_scenarios', $scenarios_panel );
			$scenarios          = $( '.bto_scenario', $scenario_metaboxes );

			$scenarios.each( function( index, el ) {
				$( '.scenario_position', el ).val( index );
				$( el ).attr( 'rel', index );
			} );

			update_scenarios_toolbar_state();
		} );

	/*------------------------------------------*/
	/*  Components                              */
	/*------------------------------------------*/

	function Component( $el ) {

		var self = this;

		this.$el                          = $el;
		this.$content                     = $el.find( '.bto_group_data' );
		this.$metabox_title               = $el.find( 'h3 .group_name' );
		this.$section_links               = this.$content.find( '.subsubsub a' );
		this.$sections                    = this.$content.find( '.tab_group' );
		this.$discount                    = this.$content.find( '.group_discount' );
		this.$filters                     = this.$content.find( '.group_filters' );
		this.$display_prices              = this.$content.find( '.component_display_prices' );
		this.$pagination_style            = this.$content.find( '.component_pagination_style' );

		this.$query_type_containers       = this.$content.find( '.component_query_type_selector' );
		this.$query_type_selector         = this.$content.find( 'select.component_query_type' );
		this.$options_style_selector      = this.$content.find( 'select.options_style_selector' );

		this.$categories_selector         = this.$content.find( 'select.categories_selector' );
		this.$products_selector           = this.$content.find( 'select.products_selector' );

		this.$default_selectors_container = this.$content.find( '.default_selector_container' );
		this.$default_selectors           = this.$content.find( '.default_selector_wrapper' );

		this.$default_selector_categories = this.$content.find( 'select.default_selector_categories' );
		this.$default_selector_products   = this.$content.find( 'select.default_selector_products' );

		this.$optional_checkbox           = this.$content.find( 'input.component_optional' );

		this.$title_input                 = this.$content.find( 'input.group_title' );
		this.$priced_individually_input   = this.$content.find( '.group_priced_individually input' );
		this.$show_filters_input          = this.$content.find( '.group_show_filters input' );

		this.initialized_selects          = false;

		this.component_toggled = function() {

			self.component_poked();

			var $el         = this.$el,
				$el_content = this.$content;

			setTimeout( function() {
				$el.toggleClass( 'closed' ).toggleClass( 'open' );
				$el_content.stop().slideToggle();
			}, 50 );
		};

		this.component_poked = function() {

			if ( ! self.initialized_selects ) {
				self.initialize_selects();
			}
		};

		this.section_changed = function( $section_link ) {

			self.$section_links.removeClass( 'current' );
			$section_link.addClass( 'current' );

			self.$sections.addClass( 'tab_group_hidden' );
			self.$content.find( '.tab_group_' + $section_link.data( 'tab' ) ).removeClass( 'tab_group_hidden' );
		};

		this.title_changed = function() {

			self.$metabox_title.text( self.$title_input.val() );

			// Component data must be saved before viewing the Scenarios panel, or adding new scenarios.
			set_component_data_dirty_state( true );
		};

		this.query_type_changed = function() {

			self.$query_type_containers.hide();
			self.$default_selectors.hide();

			var query_type = self.$query_type_selector.val();

			self.$content.find( '.component_query_type_' + query_type ).show();

			if ( this.initialized_selects ) {

				if ( 'category_ids' === query_type ) {
					self.reinitialize_default_option_category_select();
					self.maybe_update_default_option_category_ids( true );
				} else {
					self.initialize_default_option_product_select();
					self.default_option_changed();
				}

				// Component data must be saved before viewing the Scenarios panel, or adding new scenarios.
				set_component_data_dirty_state( true );
			}
		};

		this.products_changed = function() {

			self.initialize_default_option_product_select();
			self.default_option_changed();

			// Component data must be saved before viewing the Scenarios panel, or adding new scenarios.
			set_component_data_dirty_state( true );
		};

		this.categories_changed = function() {

			var category_ids = self.get_category_ids();

			// Copy active category IDs on 'include' data attribute. Will be used when searching for a default.
			self.$default_selector_categories.data( 'include', category_ids.join() );

			// If needed, fetch the category IDs of the defult option and check if they are valid.
			self.maybe_update_default_option_category_ids( true );

			// Component data must be saved before viewing the Scenarios panel, or adding new scenarios.
			set_component_data_dirty_state( true );
		};

		this.optional_changed = function() {

			self.default_option_changed();

			// Component data must be saved before viewing the Scenarios panel, or adding new scenarios.
			set_component_data_dirty_state( true );
		};

		this.options_style_changed = function() {

			var supports = self.$options_style_selector.find( 'option[value="' + self.$options_style_selector.val() + '"]' ).data( 'supports' );

			if ( 'yes' === supports.pagination ) {
				self.$pagination_style.show();
			} else {
				self.$pagination_style.hide();
			}
		};

		this.priced_individually_input_changed = function() {

			if ( self.$priced_individually_input.is( ':checked' ) ) {
				self.$discount.show();
				self.$display_prices.show();
			} else {
				self.$discount.hide();
				self.$display_prices.hide();
			}
		};

		this.show_filters_input_changed = function() {

			if ( self.$show_filters_input.is( ':checked' ) ) {
				self.$filters.show();
			} else {
				self.$filters.hide();
			}
		};

		this.default_option_changed = function() {

			var query_type            = self.$query_type_selector.val(),
				default_option        = self.get_default_option(),
				default_option_html   = self.get_default_option_html(),
				new_default_option    = 'category_ids' === query_type ? self.$default_selector_categories.val() : self.$default_selector_products.val(),
				update_default_option = true,
				error                 = false;

			if ( ! new_default_option && ! self.is_optional() ) {

				if ( 'category_ids' === query_type ) {

					if ( 'defaults' === $composite_price_calc.val() && self.get_category_ids().length > 0 ) {
						error = 'set_defaults';
					}

				} else {

					var products_count = self.get_product_ids().length;

					if ( products_count > 0 ) {

						if ( 'defaults' === $composite_price_calc.val() ) {
							error = 'set_defaults';
						} else if ( products_count === 1 ) {
							error = 'set_defaults_static';
						}
					}
				}
			}

			if ( error ) {

				if ( ! self.has_error( self.$default_selectors_container ) ) {

					setTimeout( function() {
						self.add_error_tip( self.$default_selectors_container, error );
					}, 5 );

					if ( ! default_option ) {
						self.add_error( self.$default_selectors_container, error );
					}
				}

				if ( 'product_ids' === query_type ) {
					// Product selector: Set value.
					self.$default_selector_products.val( default_option ).triggerHandler( 'change' );
				} else {
					// Category selector: Put option back in.
					if ( default_option_html ) {
						self.$default_selector_categories.append( default_option_html ).val( default_option ).triggerHandler( 'change' );
					}
				}

				update_default_option = false;
			}

			if ( update_default_option ) {
				self.clear_error( self.$default_selectors_container );
				self.set_default_option( new_default_option );
			}
		};

		this.add_error_tip = function( $target, error ) {

			var offset = $target.position();

			if ( $target.parent().find( '.wc_error_tip' ).length === 0 ) {
				$target.after( '<div class="wc_error_tip">' + wc_composite_admin_params[ 'i18n_' + error ] + '</div>' );
				$target.parent().find( '.wc_error_tip' )
					.css( 'left', offset.left + $target.width() - ( $target.width() / 2 ) - ( $( '.wc_error_tip' ).width() / 2 ) )
					.css( 'top', offset.top + $target.height() )
					.fadeIn( '100' );
			}
		};

		this.has_error = function( $target ) {
			return $target.hasClass( 'selection_error' );
		};

		this.add_error = function( $target, error ) {
			$target.find( '.wc-cp-error-tip' ).attr( 'data-tip', wc_composite_admin_params[ 'i18n_' + error ] );
			$target.find( '.wc-cp-error-tip' ).tipTip( tip_tip_params );
			$target.addClass( 'selection_error' );
		};

		this.clear_error = function( $target ) {
			$target.removeClass( 'selection_error' );
		};

		this.initialize = function() {

			self.query_type_changed();
			self.options_style_changed();
			self.priced_individually_input_changed();
			self.show_filters_input_changed();
		};

		this.initialize_default_option_category_select = function() {

			if ( 'undefined' === typeof self.get_default_option_html() ) {
				self.save_default_option_html();
			}
		};

		this.reinitialize_default_option_category_select = function() {

			var default_option      = self.get_default_option(),
				default_option_html = self.get_default_option_html();

			if ( default_option_html ) {
				self.$default_selector_categories.append( default_option_html );
			}

			self.$default_selector_categories.val( default_option ).triggerHandler( 'change' );
		};

		this.initialize_default_option_product_select = function() {

			var $options       = self.$products_selector.find( ':selected' ).clone().removeAttr( 'selected' ),
				has_default    = self.get_default_option(),
				default_exists = has_default && $options.filter( '[value="' + self.get_default_option() + '"]' ).length > 0;

			self.$default_selector_products.find( 'option' ).remove();
			self.$default_selector_products.append( $options );

			if ( ! default_exists ) {
				var apply_default = ( ! self.is_optional() && $options.length > 0 && ( 'defaults' === $composite_price_calc.val() || 1 === $options.length ) ) ? $options.first().val() : 0;
				self.set_default_option( apply_default );
			}

			self.$default_selector_products.val( self.get_default_option() ).triggerHandler( 'change' );
		};

		this.initialize_selects = function() {

			var query_type = self.$query_type_selector.val();

			if ( 'category_ids' === query_type ) {
				self.initialize_default_option_category_select();
			} else {
				self.initialize_default_option_product_select();
			}

			self.initialize_select2s();
		};

		this.initialize_select2s = function() {

			var $product_search_lazy  = self.$content.find( '.wc-product-search-lazy' ),
				$enhanced_select_lazy = self.$content.find( '.wc-enhanced-select-lazy' );

			$product_search_lazy.addClass( 'wc-product-search' );
			$enhanced_select_lazy.addClass( 'wc-enhanced-select' );

			self.$content.wc_cp_select2();

			$product_search_lazy.removeClass( 'wc-product-search' );
			$enhanced_select_lazy.removeClass( 'wc-enhanced-select' );

			self.initialized_selects = true;
		};

		this.maybe_update_default_option_category_ids = function( check_on_complete ) {

			check_on_complete = check_on_complete || false;

			// Fetch categories if needed.
			if ( null === self.get_default_option_category_ids() ) {

				if ( self.update_default_option_category_ids_xhr ) {
					self.update_default_option_category_ids_xhr.abort();
				}

				var data = {
					action:     'woocommerce_get_product_categories',
					product_id: self.get_default_option(),
					security:   wc_composite_admin_params.get_product_categories_nonce
				};

				self.update_default_option_category_ids_xhr = $.post( woocommerce_admin_meta_boxes.ajax_url, data, function ( response ) {

					self.set_default_option_category_ids( 'success' === response.result ? response.category_ids : [] );

					if ( check_on_complete ) {
						self.check_default_option_in_category_ids();
					}
				} );

			} else if ( check_on_complete ) {
				self.check_default_option_in_category_ids();
			}
		};

		this.check_default_option_in_category_ids = function() {

			if ( ( self.get_default_option() && ! self.is_default_option_in_category_ids() ) || self.get_category_ids().length === 0 ) {
				self.set_default_option( 0 );
				self.$default_selector_categories.val( 0 ).trigger( 'change' );
			}
		};

		this.is_default_option_in_category_ids = function() {

			// Check if the current default is within the included categories.
			var selection_data  = self.get_selector_data( self.$categories_selector ),
				default_cat_ids = self.get_default_option_category_ids(),
				option_cat_ids  = [];

			$.each( selection_data, function( index, data ) {
				option_cat_ids.push( parseInt( data.id ) );
			} );

			var is_valid = false;

			$.each( default_cat_ids, function( default_cat_index, default_cat_id ) {
				if ( self.array_contains( parseInt( default_cat_id ), option_cat_ids ) ) {
					is_valid = true;
					return false;
				}
			} );

			return is_valid;
		};

		this.get_default_option_category_ids = function() {
			var data = this.$default_selectors_container.data( 'selected_data' );
			return data.default_option_category_ids;
		};

		this.set_default_option_category_ids = function( value ) {
			var data = this.$default_selectors_container.data( 'selected_data' );
			data.default_option_category_ids = value;
		};

		this.get_default_option_html = function() {
			var data = this.$default_selectors_container.data( 'selected_data' );
			return data.default_option_html;
		};

		this.save_default_option_html = function() {

			var data = this.$default_selectors_container.data( 'selected_data' );

			if ( ! self.get_default_option() ) {
				data.default_option_html = false;
			} else {
				// Clone option from the active selector.
				var query_type           = self.$query_type_selector.val(),
					$default_option_html = 'category_ids' === query_type ? self.$default_selector_categories.find( ':selected' ).clone() : self.$default_selector_products.find( ':selected' ).clone();

				data.default_option_html = $default_option_html;
			}
		};

		this.get_default_option = function() {
			var data = this.$default_selectors_container.data( 'selected_data' );
			return data.default_option_id || 0;
		};

		this.set_default_option = function( value ) {

			var data = this.$default_selectors_container.data( 'selected_data' );

			data.default_option_id = value || 0;

			self.save_default_option_html();

			// Clear the categories storage. Will be updated only if needed, that is if the chosen categories are changed.
			self.set_default_option_category_ids( data.default_option_id ? null : false );
			self.maybe_update_default_option_category_ids();
		};

		this.get_selector_data = function( $selector ) {
			return 'yes' === wc_composite_admin_params.is_wc_version_gte_3_2 ? $selector.selectWoo( 'data' ) : $selector.select2( 'data' );
		};

		this.get_category_ids = function() {

			var selection_data = self.get_selector_data( self.$categories_selector ),
				option_cat_ids = [];

			$.each( selection_data, function( index, data ) {
				option_cat_ids.push( parseInt( data.id ) );
			} );

			return option_cat_ids;
		};

		this.get_product_ids = function() {

			var selection_data = self.get_selector_data( self.$products_selector ),
				product_ids    = [];

			$.each( selection_data, function( index, data ) {
				product_ids.push( parseInt( data.id ) );
			} );

			return product_ids;
		};

		this.array_contains = function( el, arr ) {
			return $.inArray( el, arr ) !== -1;
		};

		this.is_optional = function() {
			return self.$optional_checkbox.is( ':checked' );
		};

		this.initialize();
	}

	function set_component_data_dirty_state( is_dirty ) {
		is_dirty = is_dirty ? true : false;
		component_data_dirty = is_dirty;
	}

	function update_components_toolbar_state() {

		if ( $components.length > 0 ) {
			$components_container.removeClass( 'no-components' );
			$components_toggle_toolbar.removeClass( 'disabled' );
		} else {
			$components_container.addClass( 'no-components' );
			$components_toggle_toolbar.addClass( 'disabled' );
		}
	}

	function init_component_event_handlers() {

		/*
		 * Component Handlers.
		 */

		$components_container

			// Expand all: selectWoo init.
			.on( 'click', '.expand_all', function() {

				if ( $( this ).hasClass( 'disabled' ) ) {
					return false;
				}

				$.each( component_objects, function( index, component_object ) {
					component_object.initialize_selects();
					setTimeout( function() {
						component_object.$el.addClass( 'open' ).removeClass( 'closed' );
						component_object.$content.show();
					}, 50 );

				} );

				return false;
			} )

			// Close all.
			.on( 'click', '.close_all', function() {

				if ( $( this ).hasClass( 'disabled' ) ) {
					return false;
				}

				$.each( component_objects, function( index, component_object ) {
					setTimeout( function() {
						component_object.$el.addClass( 'closed' ).removeClass( 'open' );
						component_object.$content.hide();
					}, 10 );

				} );

				return false;
			} )

			// selectWoo init.
			.on( 'mouseover', '.bto_group_handle', function() {

				var $el       = $( this ).closest( '.bto_group' ),
					el_id     = $el.data( 'component_metabox_id' ),
					component = component_objects[ el_id ];

				if ( typeof( component ) !== 'undefined' ) {
					component.component_poked();
				}
			} )

			// selectWoo init.
			.on( 'click', '.bto_group_handle', function() {

				var $el       = $( this ).closest( '.bto_group' ),
					el_id     = $el.data( 'component_metabox_id' ),
					component = component_objects[ el_id ];

				if ( typeof( component ) !== 'undefined' ) {
					component.component_toggled();
				}
			} )

			// Subsubsub navigation.
			.on( 'click', '.subsubsub a', function( e ) {

				var $section_link   = $( this ),
					$el             = $( this ).closest( '.bto_group' ),
					el_id           = $el.data( 'component_metabox_id' ),
					component       = component_objects[ el_id ];

				component.section_changed( $section_link );

				e.preventDefault();
			} )

			// Component Remove.
			.on( 'click', 'a.remove_row', function( e ) {

				var $el   = $( this ).closest( '.bto_group' ),
					el_id = $el.data( 'component_metabox_id' );

				$el.find( '*' ).off();
				$el.remove();

				delete component_objects[ el_id ];

				$components_panel.triggerHandler( 'wc-cp-components-changed' );

				e.preventDefault();
			} )

			// Add Component.
			.on( 'click', 'button.add_bto_group', function() {

				$components_panel.block( block_params );

				component_add_count++;

				var data = {
					action:   'woocommerce_add_composite_component',
					post_id:  woocommerce_admin_meta_boxes.post_id,
					id:       component_add_count,
					security: wc_composite_admin_params.add_component_nonce
				};

				setTimeout( function() {

					$.post( woocommerce_admin_meta_boxes.ajax_url, data, function ( response ) {

						$component_metaboxes.append( response );

						var $added    = $( '.bto_group', $component_metaboxes ).last(),
							added_obj = new Component( $added ),
							added_id  = 'component_' + component_add_count;

						$added.data( 'component_metabox_id', added_id );
						component_objects[ added_id ] = added_obj;

						$components_panel.triggerHandler( 'wc-cp-components-changed' );

						// selectWoo init.
						added_obj.initialize_selects();

						// Help-tips init.
						$added.find( '.woocommerce-help-tip' ).tipTip( tip_tip_params );

						$components_panel.triggerHandler( 'wc-cp-component-added', [ added_obj ] );
						$components_panel.unblock();

					} );

				}, 250 );

				return false;

			} )

			// Component Keyup.
			.on( 'keyup', 'input.group_title', function() {

				var $el             = $( this ).closest( '.bto_group' ),
					el_id           = $el.data( 'component_metabox_id' ),
					component       = component_objects[ el_id ];

				component.title_changed();
			} )

			// Query type.
			.on( 'change', 'select.component_query_type', function() {

				var $el             = $( this ).closest( '.bto_group' ),
					el_id           = $el.data( 'component_metabox_id' ),
					component       = component_objects[ el_id ];

				component.query_type_changed();
			} )

			// Categories.
			.on( 'change', 'select.categories_selector', function() {

				var $el             = $( this ).closest( '.bto_group' ),
					el_id           = $el.data( 'component_metabox_id' ),
					component       = component_objects[ el_id ];

				component.categories_changed();
			} )

			// Products.
			.on( 'change', 'select.products_selector', function() {

				var $el             = $( this ).closest( '.bto_group' ),
					el_id           = $el.data( 'component_metabox_id' ),
					component       = component_objects[ el_id ];

				component.products_changed();
			} )

			// Default category option.
			.on( 'change', 'select.default_selector_categories', function() {

				var $el             = $( this ).closest( '.bto_group' ),
					el_id           = $el.data( 'component_metabox_id' ),
					component       = component_objects[ el_id ];

				component.default_option_changed();
			} )

			// Default product option.
			.on( 'change', 'select.default_selector_products', function() {

				var $el             = $( this ).closest( '.bto_group' ),
					el_id           = $el.data( 'component_metabox_id' ),
					component       = component_objects[ el_id ];

				component.default_option_changed();
			} )

			// Optional.
			.on( 'change', 'input.component_optional', function() {

				var $el             = $( this ).closest( '.bto_group' ),
					el_id           = $el.data( 'component_metabox_id' ),
					component       = component_objects[ el_id ];

				component.optional_changed();
			} )

			// Options style.
			.on( 'change', 'select.options_style_selector', function() {

				var $el             = $( this ).closest( '.bto_group' ),
					el_id           = $el.data( 'component_metabox_id' ),
					component       = component_objects[ el_id ];

				component.options_style_changed();
			} )

			// Priced individually.
			.on( 'change', '.group_priced_individually input', function() {

				var $el             = $( this ).closest( '.bto_group' ),
					el_id           = $el.data( 'component_metabox_id' ),
					component       = component_objects[ el_id ];

				component.priced_individually_input_changed();
			} )

			// Filters.
			.on( 'change', '.group_show_filters input', function() {

				var $el             = $( this ).closest( '.bto_group' ),
					el_id           = $el.data( 'component_metabox_id' ),
					component       = component_objects[ el_id ];

				component.show_filters_input_changed();
			} )

			// Set Image.
			.on( 'click', '.upload_component_image_button', function( e ) {

				component_image_frame_data.$button = $( this );

				e.preventDefault();

				// If the media frame already exists, reopen it.
				if ( component_image_frame_data.image_frame ) {

					component_image_frame_data.image_frame.open();

				} else {

					// Create the media frame.
					component_image_frame_data.image_frame = wp.media( {

						// Set the title of the modal.
						title: wc_composite_admin_params.i18n_choose_component_image,
						button: {
							text: wc_composite_admin_params.i18n_set_component_image
						},
						states: [
							new wp.media.controller.Library( {
								title: wc_composite_admin_params.i18n_choose_component_image,
								filterable: 'all'
							} )
						]
					} );

					// When an image is selected, run a callback.
					component_image_frame_data.image_frame.on( 'select', function () {

						var attachment = component_image_frame_data.image_frame.state().get( 'selection' ).first().toJSON(),
							url        = attachment.sizes && attachment.sizes.thumbnail ? attachment.sizes.thumbnail.url : attachment.url;

						component_image_frame_data.$button.addClass( 'has_image' );
						component_image_frame_data.$button.closest( '.component_image' ).find( '.remove_component_image_button' ).addClass( 'has_image' );
						component_image_frame_data.$button.find( 'input' ).val( attachment.id ).change();
						component_image_frame_data.$button.find( 'img' ).eq( 0 ).attr( 'src', url );
					} );

					// Finally, open the modal.
					component_image_frame_data.image_frame.open();
				}

			} )

			// Remove Image.
			.on( 'click', '.remove_component_image_button', function( e ) {

				var $button         = $( this ),
					$option_wrapper = $button.closest( '.component_image' ),
					$upload_button  = $option_wrapper.find( '.upload_component_image_button' );

				e.preventDefault();

				$upload_button.removeClass( 'has_image' );
				$button.removeClass( 'has_image' );
				$option_wrapper.find( 'input' ).val( '' ).change();
				$upload_button.find( 'img' ).eq( 0 ).attr( 'src', wc_composite_admin_params.wc_placeholder_img_src );

			} );

	}

	function init_component_metaboxes() {

		// Component sorting.
		$component_metaboxes.sortable( {
			items: '.bto_group',
			cursor: 'move',
			axis: 'y',
			handle: '.sort-item',
			scrollSensitivity: 40,
			forcePlaceholderSize: true,
			helper: 'clone',
			opacity: 0.65,
			placeholder: 'wc-metabox-sortable-placeholder',
			start: function( event, ui ) {
				ui.item.css( 'background-color','#f6f6f6' );
			},
			stop: function( event, ui ) {
				ui.item.removeAttr( 'style' );
				$components_panel.triggerHandler( 'wc-cp-components-changed' );
			}
		} );

		update_components_toolbar_state();
	}

	function init_component_objects() {

		component_objects = {};

		// Create objects.
		$components.each( function( index ) {

			var $el   = $( this ),
				el_id = 'component_' + index;

			$el.data( 'component_metabox_id', el_id );
			component_objects[ el_id ] = new Component( $el );
		} );

		// Initialize metaboxes.
		init_component_metaboxes();
	}

	function init_components() {

		// Attach event handlers.
		init_component_event_handlers();

		// Create objects.
		init_component_objects();
	}

	init_components();


	/*--------------------------------------------------*/
	/*  Scenarios                                       */
	/*--------------------------------------------------*/

	function Scenario( $el ) {

		var self = this;

		this.$el                                   = $el;
		this.$content                              = $el.find( '.bto_scenario_data' );
		this.$metabox_title                        = $el.find( 'h3 .scenario_name' );

		this.$title_input                          = this.$content.find( 'input.scenario_title' );
		this.$conditional_components_action_input  = this.$content.find( '.toggle_conditional_components input' );

		this.$conditional_components_action_config = this.$content.find( '.scenario_action_conditional_components_group .action_components' );

		this.initialized_selects                   = false;

		this.scenario_toggled = function() {

			self.scenario_poked();

			var $el         = this.$el,
				$el_content = this.$content;

			setTimeout( function() {
				$el.toggleClass( 'closed' ).toggleClass( 'open' );
				$el_content.stop().slideToggle();
			}, 50 );
		};

		this.scenario_poked = function() {

			if ( ! self.initialized_selects ) {
				self.initialize_selects();
			}
		};

		this.title_changed = function() {

			self.$metabox_title.text( self.$title_input.val() );
		};

		this.match_component_changed = function( $input ) {

			if ( $input.is( ':checked' ) ) {
				$input.closest( '.bto_scenario_selector' ).find( '.bto_scenario_matching_conditions_wrapper' ).show();
			} else {
				$input.closest( '.bto_scenario_selector' ).find( '.bto_scenario_matching_conditions_wrapper' ).hide();
			}
		};

		this.conditional_components_action_input_changed = function() {

			if ( self.$conditional_components_action_input.is( ':checked' ) ) {
				self.$conditional_components_action_config.show();
			} else {
				self.$conditional_components_action_config.hide();
			}
		};

		this.initialize_selects = function() {

			var $component_options_search_lazy = self.$content.find( '.wc-component-options-search-lazy' ),
				$enhanced_select_lazy          = self.$content.find( '.wc-enhanced-select-lazy' );

			$component_options_search_lazy.addClass( 'wc-component-options-search' );
			$enhanced_select_lazy.addClass( 'wc-enhanced-select' );

			self.$content.wc_cp_select2();
			self.$content.wc_cp_select2_component_options();

			$component_options_search_lazy.removeClass( 'wc-component-options-search' );
			$enhanced_select_lazy.removeClass( 'wc-enhanced-select' );

			self.initialized_selects = true;
		};

		this.initialize = function() {
			// Emptiness.
		};

		this.initialize();
	}

	function update_scenarios_toolbar_state() {

		if ( $scenarios.length > 0 ) {
			$scenarios_toggle_toolbar.removeClass( 'disabled' );
		} else {
			$scenarios_toggle_toolbar.addClass( 'disabled' );
		}
	}

	function init_scenario_event_handlers() {

		$scenarios_container

			// Expand all: selectWoo init.
			.on( 'click', '.expand_all', function() {

				if ( $( this ).hasClass( 'disabled' ) ) {
					return false;
				}

				$.each( scenario_objects, function( index, scenario_object ) {
					scenario_object.initialize_selects();
					setTimeout( function() {
						scenario_object.$el.addClass( 'open' ).removeClass( 'closed' );
						scenario_object.$content.show();
					}, 50 );

				} );

				return false;
			} )

			// Close all.
			.on( 'click', '.close_all', function() {

				if ( $( this ).hasClass( 'disabled' ) ) {
					return false;
				}

				$.each( scenario_objects, function( index, scenario_object ) {
					setTimeout( function() {
						scenario_object.$el.addClass( 'closed' ).removeClass( 'open' );
						scenario_object.$content.hide();
					}, 10 );

				} );

				return false;
			} )

			// selectWoo init.
			.on( 'mouseover', '.bto_scenario_handle', function() {

				var $el      = $( this ).closest( '.bto_scenario' ),
					el_id    = $el.data( 'scenario_metabox_id' ),
					scenario = scenario_objects[ el_id ];

				if ( typeof( scenario ) !== 'undefined' ) {
					scenario.scenario_poked();
				}
			} )

			// selectWoo init.
			.on( 'click', '.bto_scenario_handle', function() {

				var $el      = $( this ).closest( '.bto_scenario' ),
					el_id    = $el.data( 'scenario_metabox_id' ),
					scenario = scenario_objects[ el_id ];

				if ( typeof( scenario ) !== 'undefined' ) {
					scenario.scenario_toggled();
				}
			} )

			// Scenario Remove.
			.on( 'click', 'a.remove_row', function( e ) {

				var $el   = $( this ).closest( '.bto_scenario' ),
					el_id = $el.data( 'scenario_metabox_id' );

				$el.find( '*' ).off();
				$el.remove();

				delete scenario_objects[ el_id ];

				$scenarios_panel.triggerHandler( 'wc-cp-scenarios-changed' );

				e.preventDefault();
			} )

			// Scenario Keyup.
			.on( 'keyup', 'input.scenario_title', function() {

				var $el      = $( this ).closest( '.bto_scenario' ),
					el_id    = $el.data( 'scenario_metabox_id' ),
					scenario = scenario_objects[ el_id ];

				scenario.title_changed();
			} )

			// Exclude option modifier.
			.on( 'change', 'input.bto_scenario_match_component', function() {

				var $el      = $( this ).closest( '.bto_scenario' ),
					el_id    = $el.data( 'scenario_metabox_id' ),
					scenario = scenario_objects[ el_id ];

				scenario.match_component_changed( $( this ) );
			} )

			// "Hide Components" scenario action.
			.on( 'change', '.toggle_conditional_components input', function() {

				var $el      = $( this ).closest( '.bto_scenario' ),
					el_id    = $el.data( 'scenario_metabox_id' ),
					scenario = scenario_objects[ el_id ];

				scenario.conditional_components_action_input_changed();
			} )

			// Add scenario.
			.on( 'click', 'button.add_bto_scenario', function () {

				$scenarios_panel.block( block_params );

				scenario_add_count++;

				var data = {
					action: 	'woocommerce_add_composite_scenario',
					post_id: 	woocommerce_admin_meta_boxes.post_id,
					id: 		scenario_add_count,
					security: 	wc_composite_admin_params.add_scenario_nonce
				};

				setTimeout( function() {

					$.post( woocommerce_admin_meta_boxes.ajax_url, data, function ( response ) {

						$scenario_metaboxes.append( response );

						var $added    = $( '.bto_scenario', $scenario_metaboxes ).last(),
							added_obj = new Scenario( $added ),
							added_id  = 'scenario_' + scenario_add_count;

						$added.data( 'scenario_metabox_id', added_id );
						scenario_objects[ added_id ] = added_obj;

						$scenarios_panel.triggerHandler( 'wc-cp-scenarios-changed' );

						// selectWoo init.
						added_obj.initialize_selects();

						// Help-tips init.
						$added.find( '.tips, .woocommerce-help-tip' ).tipTip( tip_tip_params );

						$scenarios_panel.triggerHandler( 'wc-cp-scenario-added', [ added_obj ] );
						$scenarios_panel.unblock();

					} );

				}, 250 );

				return false;
			} );
	}

	function init_scenario_metaboxes() {

		// Scenario ordering.
		$scenario_metaboxes.sortable( {
			items: '.bto_scenario',
			cursor: 'move',
			axis: 'y',
			handle: '.sort-item',
			scrollSensitivity: 40,
			forcePlaceholderSize: true,
			helper: 'clone',
			opacity: 0.65,
			placeholder: 'wc-metabox-sortable-placeholder',
			start: function( event, ui ) {
				ui.item.css( 'background-color','#f6f6f6' );
			},
			stop: function( event, ui ) {
				ui.item.removeAttr( 'style' );
				$scenarios_panel.triggerHandler( 'wc-cp-scenarios-changed' );
			}
		} );

		update_scenarios_toolbar_state();
	}

	function init_scenario_objects() {

		scenario_objects = {};

		// Create objects.
		$scenarios.each( function( index ) {

			var $el   = $( this ),
				el_id = 'scenario_' + index;

			$el.data( 'scenario_metabox_id', el_id );
			scenario_objects[ el_id ] = new Scenario( $el );
		} );

		// Initialize metaboxes.
		init_scenario_metaboxes();
	}

	function init_scenarios() {

		// Attach event handlers.
		init_scenario_event_handlers();

		// Create objects.
		init_scenario_objects();
	}

	init_scenarios();

	/*
	 * Reload Scenarios when the view is dirty.
	 */

	// Replace anchor with new one.
	var $scenarios_tab         = $( '.cp_scenarios_tab' ),
		$scenarios_tab_a       = $scenarios_tab.find( 'a' ),
		configuration_save_xhr = false;

	$scenarios_tab.prepend( '<a class="cp_scenarios_tab_inner" href="#bto_scenario_data"></a>' );

	var $scenarios_tab_a_new = $scenarios_tab.find( 'a.cp_scenarios_tab_inner' );

	$scenarios_tab_a_new.html( $scenarios_tab_a.html() );

	// Handle click events.
	$scenarios_tab_a_new.on( 'click', function() {

		if ( component_data_dirty && ! configuration_save_xhr ) {

			var reload_view = window.confirm( wc_composite_admin_params.i18n_scenarios_panel_blocked );

			if ( reload_view ) {

				$scenarios_tab_a.trigger( 'click' );

				setTimeout( function() {
					save_composite();
				}, 150 );
			}

		} else {
			$scenarios_tab_a.trigger( 'click' );
		}

		return false;
	} );

	/*
	 * Save data and update configuration options via ajax.
	 */
	function save_composite() {

		$components_panel.block( block_params );
		$scenarios_panel.block( block_params );

		$components.find( '*' ).off();

		var data = {
			post_id:  woocommerce_admin_meta_boxes.post_id,
			data:     $( '#bto_product_data, #bto_scenario_data' ).find( 'input, select, textarea' ).serialize(),
			action:   'woocommerce_bto_composite_save',
			security: wc_composite_admin_params.save_composite_nonce
		};

		setTimeout( function() {

			configuration_save_xhr = $.post( woocommerce_admin_meta_boxes.ajax_url, data, function( post_response ) {

				var this_page = window.location.toString();

				this_page = this_page.replace( 'post-new.php?', 'post.php?post=' + woocommerce_admin_meta_boxes.post_id + '&action=edit&' );

				$.get( this_page, function( response ) {

					var open_components               = [],
						open_scenarios                = [],
						$components_group             = $( '#bto_config_group_inner', $components_panel ),
						$scenarios_group              = $( '#bto_scenarios_inner', $scenarios_panel ),
						$components_group_in_response = $( response ).find( '#bto_config_group_inner' ),
						$components_in_response       = $components_group_in_response.find( '.bto_group' ),
						$scenarios_group_in_response  = $( response ).find( '#bto_scenarios_inner' ),
						$scenarios_in_response        = $scenarios_group_in_response.find( '.bto_scenario' );

					// Remember open/close state of Components.
					if ( $components.length === $components_in_response.length ) {

						// Make a list of open Components.
						$components.each( function() {

							var $el = $( this );

							if ( $el.hasClass( 'open' ) ) {
								var rel = $el.attr( 'rel' );
								open_components.push( rel );
							}
						} );
					}

					// Apply open/close state to Components in response.
					$components_in_response.each( function() {

						var $el = $( this ),
							rel = $el.attr( 'rel' );

						if ( $.inArray( rel, open_components ) !== -1 ) {
							$el.addClass( 'open' ).removeClass( 'closed' );
							$el.find( '.wc-metabox-content' ).show();
						} else {
							$el.find( '.wc-metabox-content' ).hide();
						}
					} );

					// Remember open/close state of Scenarios.
					if ( $scenarios.length === $scenarios_in_response.length ) {

						// Make a list of open Scenarios.
						$scenarios_group.find( '.bto_scenario' ).each( function() {

							var $el = $( this );

							if ( $el.hasClass( 'open' ) ) {
								var rel = $el.attr( 'rel' );
								open_scenarios.push( rel );
							}
						} );
					}

					// Apply open/close state to Scenarios in response.
					$scenarios_in_response.each( function() {

						var $el = $( this ),
							rel = $el.attr( 'rel' );

						if ( $.inArray( rel, open_scenarios ) !== -1 ) {
							$el.addClass( 'open' ).removeClass( 'closed' );
							$el.find( '.wc-metabox-content' ).show();
						} else {
							$el.find( '.wc-metabox-content' ).hide();
						}
					} );

					$components.find( '*' ).off();
					$scenarios.find( '*' ).off();

					$components_group.html( $components_group_in_response.html() );
					$scenarios_group.html( $scenarios_group_in_response.html() );

					$components_toggle_toolbar = $components_group.find( '.bulk_toggle_wrapper' );
					$scenarios_toggle_toolbar  = $scenarios_group.find( '.bulk_toggle_wrapper' );

					// Trigger change event.
					$components_panel.triggerHandler( 'wc-cp-components-changed' );

					// Create objects.
					init_component_objects();

					// Trigger change event.
					$scenarios_panel.triggerHandler( 'wc-cp-scenarios-changed' );

					// Create objects.
					init_scenario_objects();

					// Init helptips.
					$( '#bto_product_data .woocommerce-help-tip, #bto_scenario_data .woocommerce-help-tip, #bto_scenario_data .tips' ).tipTip( tip_tip_params );

					// selectWoo init - open components.
					$components.each( function() {

						var $el = $( this );

						if ( $el.hasClass( 'open' ) ) {

							var el_id     = $el.data( 'component_metabox_id' ),
								component = component_objects[ el_id ];

							component.initialize_selects();
						}
					} );

					// selectWoo init - open scenarios.
					$scenarios.each( function() {

						var $el = $( this );

						if ( $el.hasClass( 'open' ) ) {

							var el_id    = $el.data( 'scenario_metabox_id' ),
								scenario = scenario_objects[ el_id ];

							scenario.initialize_selects();
						}
					} );

					if ( post_response.length > 0 ) {
						$.each( post_response, function( index, part ) {
							window.alert( part );
						} );
					}

					$components_panel.unblock();
					$scenarios_panel.unblock();

					configuration_save_xhr = false;

					set_component_data_dirty_state( false );
				} );

			}, 'json' );

		}, 250 );

	}

	function init_nux() {

		if ( 'yes' === wc_composite_admin_params.is_first_composite ) {
			$( 'select#product-type' ).val( 'composite' ).change().focus();
			setTimeout( function() {
				$( '.composite_product_options a' ).trigger( 'click' );
			}, 500 );
		}
	}

	init_nux();

} );
