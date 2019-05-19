var openinghours_current_shipping_method = 'default';
var openinghours_which_no_opening_hours = [];
var openinghours_hols = [];
var no_opening_hours;
var holiday_shipping_methods = [];

jQuery(document).ready(function($) {
	
	if ('undefined' == typeof openinghours_debug_mode) { openinghours_debug_mode = false; }
	
	try {
		if ('undefined' !== typeof openinghourslion) {
			openinghours_hols = JSON.parse(openinghourslion.holidays);
			no_opening_hours = JSON.parse(openinghourslion.days_with_no_opening_hours);
			$('label[for="openinghours_time"]').append('<img id="openinghours_time_spinner" src="'+openinghourslion.wp_inc+'images/spinner.gif" style="float:right; width:18px; height: 18px; margin-top: 8px; display:none;">');
			holiday_shipping_methods = JSON.parse(openinghourslion.holiday_shipping_methods);
		}
	} catch (err) {
		console.log(err);
	}
	
	if ($('#openinghours_time').length > 0 || $('#order_review #shipping_method, #order_review #shipping_method_0').length > 0) {
		openinghours_update_ui_for_shipping_method(true, false);
	} else if ($('.cart_totals #shipping_method, .cart_totals #shipping_method_0').length > 0) {
		openinghours_update_ui_for_shipping_method(true, true);
	}
	
	// The display-none test is for WC 2.1, which doesn't allow the style attribute to be set directly
	if ($('#openinghours_time').length > 0 && (!$('#openinghours_time').is(':visible') || $('p.openinghours-initial-display-none').length > 0)) {
		$('#openinghours_time_field').hide();
		$('#openinghours_time').prop('disabled', true);
	}
	
	// Cart page
	$('.woocommerce').on('input change', '.cart_totals select.shipping_method, .cart_totals input[name^=shipping_method]', function() {
		openinghours_update_ui_for_shipping_method(false, true);
	});
	
	// Added Sep 2018. This is triggered on the cart page (among other things) when you update quantities. Nothing else is triggering that will update our display of notices.
	$(document.body).on('updated_wc_div', function() {
		if (openinghours_debug_mode) { console.log("OpeningHours: updated_wc_div event triggered; will call openinghours_update_ui_for_shipping_method()"); }
		// Is page load: false. Is cart: true.
		openinghours_update_ui_for_shipping_method(false, true);
	});
		
	// Nothing that follows is needed, or should be loaded, for the cart page; so, return if that is where we are.
	if ($('.cart-collaterals #shipping_method').length > 0) {
		return;
	}
	
	function update_mingap_visibility(obj) {
		var shipping_method_id = $(obj).data('shipping_method');
		var is_default = $('#mingap_usedefault_'+shipping_method_id+'_yes').is(':checked');
		if (is_default) {
			$(obj).parent('.mingap_usedefault_container').siblings('.openinghours-mingap-method-settings').slideUp();
		} else {
			$(obj).parent('.mingap_usedefault_container').siblings('.openinghours-mingap-method-settings').slideDown();
		}
	}
	
	$('#openinghours-rules .openinghours-mingap-rules .mingap_usedefault_yes').each(function(ind, obj) {
		update_mingap_visibility(obj);
	});
	
	$('#openinghours-rules .openinghours-mingap-rules .mingap_usedefault').change(function() {
		update_mingap_visibility(this);
	});
	
	$('#openinghours-shipping-methods a.nav-tab').click(function() {
		$('#openinghours-shipping-methods a.nav-tab').removeClass('nav-tab-active');
		$(this).addClass('nav-tab-active');
		var id = $(this).attr('id');
		if (openinghours_debug_mode) { console.log("OpeningHours: activate tab: "+id); }
		if ('openinghours-shipping-methods-navtab-' == id.substring(0, 37)) {
			$('div.openinghours-shipping-methods-navtab-content').hide();
			$('#openinghours-shipping-methods-navtab-'+id.substring(37)+'-content').show();
		}
		return false;
	});
	
	// Checkout page only, and only if our widget is shown
	if (jQuery('#openinghours_time').length > 0) {

		// Checkout page
		// Used to have 'input' as a tracked event alongside 'change' - but I cannot now (Aug/17) work out why
		$('.woocommerce #order_review, form.checkout').first().on('change', 'select.shipping_method, input[name^=shipping_method]', function(e) {
			openinghours_update_ui_for_shipping_method(false, false);
		});

		// Can use pickerTimeFormat, not timeFormat, because otherwise the localized time string (specifically, the a.m. / p.m.) cannot be parsed on the back-end - however, we use a different method, making sure that a or p is always the first character in the localizations (we presume the Latin is universal enough)
		// No need to check for date_only on setting the options, as unsupported options are harmless
		var datepicker_options = {
			// Rule out non-available days
			beforeShowDay: datetimepicker_beforeShowDay,
			stepMinute: parseInt(openinghourslion.datetimepickerstepminute),
			// When something selected, display a "is OK" / "is not OK" message
			onClose: datetimepicker_onClose,
			dateFormat: openinghourslion.datepickerdateformat,
			timeFormat: openinghourslion.datepickertimeformat,
			minDateTime: new Date(),
			firstDay: openinghourslion.firstday,
			controlType: openinghourslion.timepickercontroltype,
			showSecond: false,
			showMillisec: false,
			showMicrosec: false,
			showTimezone: false,
			oneLine: false
		};
		
		if (openinghourslion.hasOwnProperty('mintime')) { datepicker_options.minTime = openinghourslion.mintime; }
		if (openinghourslion.hasOwnProperty('maxtime')) { datepicker_options.maxTime = openinghourslion.maxtime; }
		
		if (parseInt(openinghourslion.datepickeroneline)) { datepicker_options.oneLine = true; }
		
		// A div, means that the picker is in-line (rather than a text field)
		// 1.6.19 - changed to a span, for valid HTML (prevents the DOM tree being broken). The div selector is kept in case anyone was relying on it with a custom timepicker field (which the code allows - see time_chooser() in the main plugin file)
		if (jQuery('div#openinghours_time, span#openinghours_time').length > 0) {
			datepicker_options.altField = '#openinghours_time_result';
			datepicker_options.altFormat = openinghourslion.datepickerdateformat;
			datepicker_options.onSelect = datetimepicker_onClose;
			if (openinghourslion.date_only == 0) {
				datepicker_options.altTimeFormat = openinghourslion.datepickertimeformat;
				datepicker_options.altFieldTimeOnly = false;
			}
		}
		
		var current_date = false;
		var i18n_used = false;

		if (openinghourslion.date_only > 0) {
			datepicker_options.minDate = new Date();
			if (typeof openinghourslion.maxdate != 'undefined' && '' != openinghourslion.maxdate) {
				datepicker_options.maxDate = openinghourslion.maxdate;
			}
			if (typeof openinghourslion.mindate != 'undefined' && '' != openinghourslion.mindate) {
				datepicker_options.minDate = openinghourslion.mindate;
			}
			
			console.log(datepicker_options);
			
			jQuery('#openinghours_time').datepicker(datepicker_options);
		} else {
			var timenow = new Date;
			if (typeof openinghourslion.maxdate != 'undefined' && '' != openinghourslion.maxdate) {
				var end_of_day = new Date(timenow.getFullYear(), timenow.getMonth(), timenow.getDate(), 23, 59, 59, 999);
				var end_of_max_day = end_of_day.getTime() + openinghourslion.maxdate*86400000;
				datepicker_options.maxDate = new Date(end_of_max_day);
			}

			var gap_ms = 0;
			
			if (typeof openinghourslion.mingap != 'undefined' && '' != openinghourslion.mingap) {
				gap_ms = parseInt(openinghourslion.mingap);
			}

			datepicker_options.minDateTime = new Date(Date.now()+gap_ms);
			
			datepicker_options.alwaysSetTime = false;
			
			jQuery('#openinghours_time').datetimepicker(datepicker_options);
			if (openinghourslion.timepicker_lang != '') {
				current_date = openinghours_get_current_datepicker_val();
				jQuery('#openinghours_time').datetimepicker('option', jQuery.timepicker.regional[openinghourslion.timepicker_lang]);
				i18n_used = true;
			}
		}
		
		console.log(datepicker_options);
		
		if (openinghourslion.datepicker_lang != '') {
			if (!current_date) { current_date = openinghours_get_current_datepicker_val(); }
			jQuery('#openinghours_time').datepicker('option', jQuery.datepicker.regional[openinghourslion.datepicker_lang]);
			i18n_used = true;
		}
		
		// The i18n calls above can result in the time disappearing from the input
		if (i18n_used) {
			var new_date = openinghours_get_current_datepicker_val();
			if (('undefined' == typeof new_date || new_date.indexOf(" ") == -1) && current_date && current_date.indexOf(' ') !=-1) {
				console.log("It looks like adding translations for the picker has caused the time to be lost; re-setting");
				console.log(new_date);
				console.log(current_date);
				openinghours_set_current_datepicker_val(current_date);
			}
		}
	}
	
	/*
	beforeShowDay: function_name - "A function that takes a date as a parameter and must return an array with:
	[0]: true/false indicating whether or not this date is selectable
	[1]: a CSS class name to add to the date's cell or "" for the default presentation
	[2]: an optional popup tooltip for this date
	The function is called for each day in the datepicker before it is displayed.

	onSelect : function to call when date is chosen or time has changed (parameters: datetimeText, datepickerInstance)
	*/

	// N.B. instance not used - if changing that, then check the callers
	function datetimepicker_onClose(text, instance) {
		
		jQuery('#openinghours_time_spinner').show();
		
		jQuery.get(openinghourslion.ajaxurl, {
			action: 'openinghours_ajax',
			subaction: 'checktime',
			nonce: openinghourslion.ajaxnonce,
			shipping_method: openinghours_current_shipping_method,
			text: text 
		}, function(response) {
			jQuery('#openinghours_time_spinner').hide();
			try {
				resp = JSON.parse(response);
				if (openinghours_debug_mode) { console.log("openinghours_ajax: checktime(shipping_method="+openinghours_current_shipping_method+"): result follows"); console.log(resp); }
				jQuery('#openinghours_time_field_feedback').remove();
				jQuery('#openinghours_time_field label').after('<span id="openinghours_time_field_feedback">'+resp.m+'</span>');
			} catch(err) {
				console.log("Unexpected response (checktime): "+response);
				console.log(err);
			}
		});
	}

	function datetimepicker_beforeShowDay(date) {
		
		var datewithoutyear = (1+date.getMonth())+'-'+date.getDate();
		var datewithyear = date.getFullYear()+'-'+datewithoutyear;
		
		var dateweekday = date.getDay();
		
		if (typeof openinghours_which_no_opening_hours != 'undefined' && openinghours_which_no_opening_hours.hasOwnProperty(dateweekday)) {
			return [false, '', openinghourslion.isclosedday];
		}
		
		if (typeof openinghours_hols == 'undefined') {
			return [true];
		}
		
		var was_matched = false;
		
		$.each(openinghours_hols, function(index, value) {
			
			if (was_matched || value != datewithyear && value != datewithoutyear) { return; }
			
			if (typeof holiday_shipping_methods != 'undefined' && typeof holiday_shipping_methods[index] != 'undefined') {
				
				var shipping_id = holiday_shipping_methods[index];
				
				// If there is no colon, then this means "apply on all instances"; therefore, do not compare instances
				var shop_shipping_method = openinghours_current_shipping_method;
				
				// Manual assistance (via a filter) for misbehaving/non-standard shipping methods
				// if ('undefined' !== shipping_translations && shipping_translations.hasOwnProperty(shop_shipping_method)) {
				// 	shop_shipping_method = shipping_translations.shop_shipping_method;
				// }
				
				if (-1 == shipping_id.indexOf(':') && -1 != shop_shipping_method.indexOf(':')) {
					shop_shipping_method = shop_shipping_method.substring(0, shop_shipping_method.indexOf(':'));
				}
				
				if (openinghours_debug_mode) { console.log("datetimepicker_beforeShowDay("+datewithyear+"): shipping_id for this method is "+shipping_id+" and the current shipping method for comparison is "+shop_shipping_method); }
					
				// We don't need to parse this further into shipping method and zone because the formats of the two variables match
				if ('all' == shipping_id || shop_shipping_method == shipping_id) {
					// Shipping method matched the configuration
					was_matched = true;
				}
			} else {
				if (openinghours_debug_mode) {
					console.log("datetimepicker_beforeShowDay(): holiday_shipping_methods not defined or this index not defined; so, treating as applying to all");
				}
				was_matched = true;
			}
			
		});
		
		if (was_matched) {
			return [false, '', openinghourslion.isholiday];
		}
		
		return [true];
		
	}
	
	jQuery('#openinghours-rules .openinghours-addnew').click(function(e) {
		e.preventDefault();
		var which_shipping_method = jQuery(this).data('whichsm');
		var which_instance = ('default' == which_shipping_method) ? null : jQuery(this).data('which_instance');
		var rule_prefix = (which_instance) ? which_shipping_method+'__I-'+which_instance : which_shipping_method;
		
		openinghours_addrule(1, 9, 0, 17, 0, rule_prefix);
	});
	
	jQuery('#openinghours-rules .openinghours-addnewdefault').click(function(e) {
		jQuery(this).fadeOut().remove();
		e.preventDefault();
		var which_shipping_method = jQuery(this).data('whichsm');
		var which_instance = ('default' == which_shipping_method) ? null : jQuery(this).data('which_instance');
		var rule_prefix = (which_instance) ? which_shipping_method+'__I-'+which_instance : which_shipping_method;
		for (var i=1; i<7; i++) {
			openinghours_addrule(i, 9, 0, 17, 0, rule_prefix);
		}
	});
	
	jQuery('#openinghours-rules').on('click', '.openinghours-row-delete', function(e) {
		e.preventDefault();
		var prow = jQuery(this).parent('.openinghours-row');
		jQuery(prow).slideUp().delay(400).remove();
	});

	$('#openinghours-rules').on('change', '.openinghours-minute, .openinghours-hour', function() {
		var prow = $(this).parent('.openinghours-row');
		var openhour =  parseInt($(prow).find('.openinghours-hour-open').val());
		var openmin =  parseInt($(prow).find('.openinghours-minute-open').val());
		var closehour =  parseInt($(prow).find('.openinghours-hour-close').val());
		var closemin =  parseInt($(prow).find('.openinghours-minute-close').val());
		$(prow).find('.openinghours-timewarning').remove();
		if (closehour < openhour || (closehour == openhour && closemin < openmin)) {
			$(prow).append('<span class="openinghours-timewarning">'+openinghourslion.timewarning+'</span>');
		}
		if ((closehour == 24 && closemin > 0) || (openhour == 24 && openmin >0)) {
			$(prow).append('<span class="openinghours-timewarning">'+openinghourslion.after_midnight_warning+'</span>');
		}
	});
	
	$('#openinghours-holidays').on('click', '.openinghours-holiday-row-delete', function(e) {
		e.preventDefault();
		var prow = $(this).parent('.openinghours-holiday-row');
		$(prow).slideUp().delay(400).remove();
	});
	
	$('#openinghours-addnew-holiday').click(function(e) {
		e.preventDefault();
		openinghours_addholiday();
	});
	
	$('#openinghours-export-settings').click(function(e) {
		e.preventDefault();
		$('#openinghours_export_spinner').show();
		$.post(openinghourslion.ajaxurl, {
			action: 'openinghours_ajax',
			subaction: 'export_settings',
			nonce: openinghourslion.ajaxnonce,
		}, function(response) {
			$('#openinghours_export_spinner').hide();
			try {
				resp = JSON.parse(response);
				if (openinghours_debug_mode) { console.log("openinghours_ajax: export_settings: result follows"); console.log(resp); }
				
				mime_type = 'application/json';
				var stuff = response;
				var link = document.body.appendChild(document.createElement('a'));
				link.setAttribute('download', 'openinghours-export-settings.json');
				link.setAttribute('style', "display:none;");
				link.setAttribute('href', 'data:' + mime_type  +  ';charset=utf-8,' + encodeURIComponent(stuff));
				link.click(); 

			} catch(err) {
				console.log("Unexpected response (checktime 2): "+response);
				console.log(err);
			}
		});
	});
	
});

if (typeof openinghourslion != 'undefined') {
	var openinghours_days = [ openinghourslion.sunday, openinghourslion.monday, openinghourslion.tuesday, openinghourslion.wednesday, openinghourslion.thursday, openinghourslion.friday, openinghourslion.saturday ];
	var openinghours_nextid = 0;
}

function openinghours_pad(num, size) {
	var s = "000000000" + num;
	return s.substr(s.length-size);
}

function openinghours_dayselector(id, selected) {
	var ret = '<select class="openinghours-day" id="'+id+'-day" name="'+id+'-day">';
	
	for (var i=0; i<openinghours_days.length; i++) {
		var sel = (i == selected) ? ' selected="selected"' : '';
		ret += '<option value="'+i+'"'+sel+'>'+openinghours_days[i]+'</option>';
	}
	
	ret += '</select>';
	return ret;
}

function openinghours_hourselector(id, selected, extraclass) {
	var ret = '<select class="openinghours-hour '+extraclass+'" id="'+id+'-hour" name="'+id+'-hour">';
	
	// Note: We do include 24 here, as we want to allow being open until midnight (not just until 23:55)
	var lasthour = (id.indexOf('close') !== -1) ? 24 : 23;
	
	for (var i=0; i<=lasthour; i++) {
		var sel = (i == selected) ? ' selected="selected"' : '';
		ret += '<option value="'+i+'"'+sel+'>'+openinghours_pad(i, 2)+'</option>';
	}
	
	ret += '</select>';
	return ret;
}

function openinghours_minuteselector(id, selected, extraclass) {
	var ret = '<select class="openinghours-minute '+extraclass+'" id="'+id+'-minute" name="'+id+'-minute">';
	
	for (var i=0; i<60; i=i+5) {
		var sel = (i == selected) ? ' selected="selected"' : '';
		ret += '<option value="'+i+'"'+sel+'>'+openinghours_pad(i, 2)+'</option>';
	}
	
	ret += '</select>';
	return ret;
}

function openinghours_dateselector(idb, date, everyyear, shipping_id) {
	
	var ret = '<input type="text" style="width:150px;" placeholder="'+openinghourslion.choosedate+'" id="'+idb+'-date" name="'+idb+'-date" value="'+((typeof date != 'undefined') ? date : '')+'">';
	ret += ' <input type="checkbox" '+((everyyear) ? ' checked="checked"' : '')+'name="'+idb+'-date-repeat" id ="'+idb+'-date-repeat" value="1">';
	ret += ' <label for="'+idb+'-date-repeat">'+openinghourslion.repeat+'</label> ';
	ret += openinghours_shipping_method_selector(idb, shipping_id);
	return ret;
	
}

// shipping_id, for our purposes, is just the DOM ID to use - we do not parse it for anything else
function openinghours_shipping_method_selector(idb, shipping_id) {
	
	ret = '<select id="'+idb+'-shipping_method" name="'+idb+'-shipping_method" style="width: 250px;">';
	
	ret += '<option value="all"';
	
	if ('all' == shipping_id) { ret += ' selected="selected"'; }
	
	ret += '>'+openinghourslion.all_shipping_methods+'</option>';
	
	if ('undefined' != typeof openinghours_shipping_method_labels) {
	
		jQuery.each(openinghours_shipping_method_labels, function(index, value) {
			if ('default' == index) { return; }
			ret += '<option value="'+index+'"';
			if (index == shipping_id) { ret += ' selected="selected"'; }
			ret += '>'+value+'</option>';
		});
		
	}
	
	ret += '</select>';
	
	return ret;
	
}

function openinghours_addholiday(date, everyyear, shipping_id) {
	var idb = 'openinghours-holiday-'+openinghours_nextid;
	openinghours_nextid++;
	
	jQuery('#openinghours-holidays').append(
		'<div class="openinghours-holiday-row" style="display:none;" id="'+idb+'-row">'+
		openinghours_dateselector(idb, date, everyyear, shipping_id)
		+' <span title="'+openinghourslion.delete+'" class="openinghours-holiday-row-delete">X</span></div>'
	);
	jQuery('#'+idb+'-date').datepicker({
		dateFormat : 'yy-mm-dd'
	});
	jQuery('#'+idb+'-row').slideDown();
}


function openinghours_addrule(daysel, hoursel, minsel, hourselclose, minselclose, setprefix) {

	if ('undefined' !== typeof openinghours_debug_mode && openinghours_debug_mode) {
		console.log("openinghours_addrule(daysel="+daysel+", hoursel="+hoursel+", minsel="+minsel+", hourselclose="+hourselclose+", minselclose="+minselclose+", setprefix="+setprefix+")");
	}
	
	// For backwards-compatibility, the individual rows for default settings are not prefixed
	if (typeof setprefix == 'undefined' || setprefix == '' || setprefix == 'default') {
		var rule_prefix = '';
		var setprefix = 'default';
	} else {
		var rule_prefix = '-shipmethod-'+setprefix;
	}
	
	var idb = 'openinghours-openinghours'+rule_prefix+'-'+openinghours_nextid;
	openinghours_nextid++;
	
	jQuery('#openinghours-rules-'+setprefix+' .openinghours-rules-rulediv').append(
		'<div class="openinghours-row" style="display:none;" id="'+idb+'-row">'+
		openinghours_dayselector(idb, daysel)+' '+openinghourslion.openfrom+' '+openinghours_hourselector(idb, hoursel,'openinghours-hour-open')+' : '+openinghours_minuteselector(idb, minsel, 'openinghours-minute-open')+' '+openinghourslion.until+' '+openinghours_hourselector(idb+'-close', hourselclose, 'openinghours-hour-close')+' : '+openinghours_minuteselector(idb+'-close', minselclose, 'openinghours-minute-close')
		+' <span title="'+openinghourslion.delete+'" class="openinghours-row-delete">X</span></div>'
	);
	
	jQuery('#'+idb+'-row').slideDown();
}

/**
 * @param Boolean is_page_load
 * @param Boolean is_cart
 */
function openinghours_update_ui_for_shipping_method(is_page_load, is_cart) {
	
	var $ = jQuery;
	
	if (openinghours_debug_mode) { console.log("OpeningHours: openinghours_update_ui_for_shipping_method(is_page_load="+is_page_load+", is_cart="+is_cart+")"); }
	
	if ('undefined' === typeof openinghours_is_open_data) {
		console.log("No opening hours data found in page: presumably, not relevant to this page.");
		return;
	}
	
	// #shipping_method_0, added by some plugin, first seen Feb 2017
	var shipping_method_element = '#shipping_method';
	if (0 == $(shipping_method_element).length && $(shipping_method_element+'_0').length > 0) {
		shipping_method_element = '#shipping_method_0';
	}
	
	if (0 == $(shipping_method_element).length) {
		console.log("OpeningHours: no #shipping_method(_0)? element found in the DOM ('default' will be used)");
		shipping_method_element = false;
	}
	
	openinghours_current_shipping_method = openinghours_get_shipping_method_from_selector(shipping_method_element);
	
	// This part needs to happen on checkout too, not just is_cart
	
	if (openinghours_debug_mode) {
		console.log("OpeningHours: openinghours_current_shipping_method="+openinghours_current_shipping_method);
	}
	
	// 1) If loading the page, if not open with this shipping method but open with others,then switch
	// 2) Decide whether to show the #openinghours-notpossible info box (if it even exists)
	var is_open = true;
	
	if (openinghours_is_open_data.hasOwnProperty(openinghours_current_shipping_method)) {
		is_open = openinghours_is_open_data[openinghours_current_shipping_method];
	} else if (openinghours_is_open_data.hasOwnProperty('default')) {
		is_open = openinghours_is_open_data.default;
	}
	
	var is_open_after_mingap = null;
	if (openinghours_is_open_after_mingap_data.hasOwnProperty(openinghours_current_shipping_method)) {
		is_open_after_mingap = openinghours_is_open_after_mingap_data[openinghours_current_shipping_method];
	} else if (openinghours_is_open_after_mingap_data.hasOwnProperty('default')) {
		is_open_after_mingap = openinghours_is_open_after_mingap_data.default;
	}
	
	if (openinghours_debug_mode) {
		console.log('OpeningHours: is_open='+is_open+', openinghours_current_shipping_method='+openinghours_current_shipping_method);
	}
	
	if (!is_open && is_page_load) {
		for (var method in openinghours_is_open_data) {
			var value = openinghours_is_open_data[method];
			if (false !== shipping_method_element && value) {
				$(shipping_method_element+' input.shipping_method[value="'+method+'"]').click();
				$(shipping_method_element+' input.shipping_method[value="'+method+'"]').change();
				// $(shipping_method_element+' input.shipping_method[value="'+method+'"]').prop('checked', true);
				// Verify it worked
				new_openinghours_current_shipping_method = openinghours_get_shipping_method_from_selector(shipping_method_element);
				if (new_openinghours_current_shipping_method != openinghours_current_shipping_method) {
					console.log('Opening hours: change: '+new_openinghours_current_shipping_method+' '+openinghours_current_shipping_method+' is_cart='+is_cart);
					openinghours_current_shipping_method = new_openinghours_current_shipping_method;
					is_open = 1;
					if (!is_cart) {
						$('body').trigger('update_checkout');
						
					} else if (typeof wc_cart_params !== 'undefined') {
						// Not sure why this doesn't work - much better to call WC's code rather than duplicate it
						//$('select.shipping_method, input[name^=shipping_method]').trigger('change');
						openinghours_update_shipping_methods();
					}
				}
			}
		}
	}
	
	// We set this date on every method, even if nothing else updated - because the default date may not be valid for the default shipping method
	var current_date = openinghours_get_current_datepicker_val();
	
	// Is that date available in the new method?
	if (is_page_load || ('undefined' !== typeof openinghourslion && parseInt(openinghourslion.choose_soonest_time_on_shipping_method_switch))) {
		// No need for AJAX on page load - we already know the outcome
		var new_initial_date = '';
		var new_initial_date_raw = false;
		if (openinghours_date_with_gap.hasOwnProperty(openinghours_current_shipping_method)) {
			new_initial_date = openinghours_date_with_gap[openinghours_current_shipping_method];
			if (openinghours_date_with_gap_raw.hasOwnProperty(openinghours_current_shipping_method)) {
				new_initial_date_raw = JSON.parse(openinghours_date_with_gap_raw[openinghours_current_shipping_method]);
			}
		} else if (openinghours_date_with_gap.hasOwnProperty('default')) {
			new_initial_date = openinghours_date_with_gap.default;
			if (openinghours_date_with_gap_raw.hasOwnProperty('default')) {
				new_initial_date_raw = JSON.parse(openinghours_date_with_gap_raw.default);
			}
		}
		if (new_initial_date) {
			console.log("New time chosen on page load: "+new_initial_date);
			openinghours_set_current_datepicker_val(new_initial_date, new_initial_date_raw);
		}
	} else if ('undefined' !== typeof openinghourslion) {
		$.get(openinghourslion.ajaxurl, {
			action: 'openinghours_ajax',
			subaction: 'checktime',
			nonce: openinghourslion.ajaxnonce,
			shipping_method: openinghours_current_shipping_method,
			text: openinghours_get_current_datepicker_val() 
		}, function(response) {
			try {
				resp = JSON.parse(response);
				if (openinghours_debug_mode) {
					console.log("openinghours_ajax: checktime: result follows");
					console.log(resp);
				}
				var available = resp.a;
				// Is the *already chosen time* available? If so, leave it be. Otherwise, change it to the next one.
				$('#openinghours_time_field_feedback').remove();
				if (!available) {
					var new_initial_date = '';
					var new_initial_date_raw = false;
					if (openinghours_date_with_gap.hasOwnProperty(openinghours_current_shipping_method)) {
						new_initial_date = openinghours_date_with_gap[openinghours_current_shipping_method];
						if (openinghours_date_with_gap_raw.hasOwnProperty(openinghours_current_shipping_method)) {
							new_initial_date_raw = JSON.parse(openinghours_date_with_gap_raw[openinghours_current_shipping_method]);
						}
					} else if (openinghours_date_with_gap.hasOwnProperty('default')) {
						new_initial_date = openinghours_date_with_gap.default;
						if (openinghours_date_with_gap_raw.hasOwnProperty('default')) {
							new_initial_date_raw = JSON.parse(openinghours_date_with_gap_raw.default);
						}
					}
					if (new_initial_date) {
						openinghours_set_current_datepicker_val(new_initial_date, new_initial_date_raw);
					}
				} else {
					// Is available - show the re-assuring message
					$('#openinghours_time_field label').after('<span id="openinghours_time_field_feedback">'+resp.m+'</span>');
				}
			} catch(err) {
				console.log("Unexpected response (checktime 2): "+response);
				console.log(err);
			}
		});
	}
	
	var next_open = '';
	if (openinghours_next_open_data.hasOwnProperty(openinghours_current_shipping_method)) {
		next_open = openinghours_next_open_data[openinghours_current_shipping_method];
	} else if (openinghours_next_open_data.hasOwnProperty('default')) {
		next_open = openinghours_next_open_data.default;
	}
	if (openinghours_shipping_method_labels.hasOwnProperty(openinghours_current_shipping_method)) {
		shipping_method_label = openinghours_shipping_method_labels[openinghours_current_shipping_method];
	} else if (openinghours_shipping_method_labels.hasOwnProperty('default')) {
		shipping_method_label = openinghours_next_open_data.default;
	}
	if (shipping_method_label) {
		shipping_method_label = shipping_method_label+': ';
	}
	var choice_status = 1;
	if (openinghours_choice_status.hasOwnProperty(openinghours_current_shipping_method)) {
		choice_status = openinghours_choice_status[openinghours_current_shipping_method];
	} else if (openinghours_choice_status.hasOwnProperty('default')) {
		choice_status = openinghours_choice_status.default;
	}
	
	if (choice_status) {
		// show() here because it is the field itself that is hidden from the back-end (because of lack of access to the containing paragraph through the Woo form API)
		$('#openinghours_time').prop('disabled', false).show();
		$('#openinghours_time_field').show();
	} else {
		$('#openinghours_time_field').hide();
		$('#openinghours_time').prop('disabled', true);
	}
	
	$('.openinghours_shippingmethod').html(shipping_method_label);
	$('#openinghours_next_opening_time').html(next_open);
	
	// If the customer always has to make a choice and the shop is currently 
	if ((is_cart || (openinghourslion.hasOwnProperty('customer_picker_choice') && 'alwayschoose' == openinghourslion.customer_picker_choice)) && null !== is_open_after_mingap) {
		// Show the message if the shop is going to close before fulfilment is possible
		if (openinghours_debug_mode) { console.log("OpeningHours: Because the shop owner has chosen to always show a picker is shown to the customer, showing/hiding the 'not possible right now' notice will be based whether the time after the fulfilment interval is possible (rather than the time now)"); }
		if (is_open_after_mingap) {
			$('#openinghours-notpossible').hide();
		} else {
			$('#openinghours-notpossible').show();
		}
	} else {
		if (is_open) {
			$('#openinghours-notpossible').hide();
		} else {
			$('#openinghours-notpossible').show();
		}
	}
	
	// Checkout page only
	if (!is_cart) {
		if (no_opening_hours.hasOwnProperty(openinghours_current_shipping_method)) {
			openinghours_which_no_opening_hours = no_opening_hours[openinghours_current_shipping_method];
			if (openinghours_debug_mode) {
				console.log("OpeningHours: changing list of days with no opening hours to those of shipping method ("+openinghours_current_shipping_method+")");
				console.log(openinghours_which_no_opening_hours);
			}
		} else {
			openinghours_which_no_opening_hours = no_opening_hours.default;
			if (openinghours_debug_mode) {
				console.log("OpeningHours: changing list of days with no opening hours to those of default shipping method (none are listed for the current shipping method ("+openinghours_current_shipping_method+"))");
				console.log(openinghours_which_no_opening_hours);
			}
		}
		
		if (!is_page_load && $('#openinghours_time').length > 0) {
			datetimepicker_onClose(openinghours_get_current_datepicker_val());
		}
	}
}

/**
 * @param String shipping_method_element
 *
 * @return String - the value
 */
function openinghours_get_shipping_method_from_selector(shipping_method_element) {
	
	if (false === shipping_method_element) return 'default';
	
	if (jQuery(shipping_method_element+' input.shipping_method:checked').length > 0) {
		return jQuery(shipping_method_element+' input.shipping_method:checked').val();
	} else {
		// Assume it is a select
		return jQuery(shipping_method_element).val();
	}
}

function openinghours_update_shipping_methods() {
	
	var $ = jQuery;
	
	// From woocommerce/assets/frontend/cart.js
	var shipping_methods = [];
	
	$( 'select.shipping_method, input[name^=shipping_method][type=radio]:checked, input[name^=shipping_method][type=hidden]' ).each( function( index, input ) {
		shipping_methods[ $( this ).data( 'index' ) ] = $( this ).val();
	} );
	
	$('div.cart_totals').block({ message: null, overlayCSS: { background: '#fff url(' + wc_cart_params.ajax_loader_url + ') no-repeat center', backgroundSize: '16px 16px', opacity: 0.6 } });
	
	var data = {
		action: 'woocommerce_update_shipping_method',
		security: wc_cart_params.update_shipping_method_nonce,
		shipping_method: shipping_methods
	};
	
	$.post(wc_cart_params.ajax_url, data, function( response ) {
		
		$('div.cart_totals').replaceWith(response);
		
		if (openinghours_debug_mode) {
			console.log("openinghours_update_shipping_methods: trigger updated_shipping_method");
		}
		
		$('body').trigger('updated_shipping_method');
		
	});
}

/**
 * An abstraction function to abstract away implementation details
 *
 * @return String
 */
function openinghours_get_current_datepicker_val() {
	return jQuery('input[name="openinghours_time"]').val();
}

/**
 * An abstraction function to abstract away implementation details
 *
 * @param val String
 * @param val_raw String - unused
 */
function openinghours_set_current_datepicker_val(val, val_raw) {
	return jQuery('input[name="openinghours_time"]').val(val);
}

