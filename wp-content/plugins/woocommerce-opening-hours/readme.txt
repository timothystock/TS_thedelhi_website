=== WooCommerce Opening Hours ===
Tested up to: 5.0
Requires at least: 4.0
Author: DavidAnderson
License: GPLv3+

== Changelog ==

= 1.10.6 (05/Jan/2019) =

* TWEAK: Do not show a time in the customer's email if the customer check-out setting is "Outside of opening hours, forbid any customers to check-out"
* TWEAK: Update the WooCommerce compatibility library to the current release (0.3.0 to 0.3.1)
* TWEAK: Catch an exception from WooCommerce if the shipping zone database table is inconsistent

= 1.10.5 (20/Dec/2018) =

* TWEAK: Increase the width of the admin input field for editing a chosen/saved time
* TWEAK: Updated updater library to latest version (1.5.10)

= 1.10.4 (17/Nov/2018) =

* FEATURE: Add the ability for a shop manager to edit the chosen/saved time afterwards (on the WooCommerce order summary page)
* TWEAK: Updated updater library to latest version (1.5.8)
* TWEAK: Marked as supporting the forthcoming WordPress 5.0

= 1.10.2 (16/Oct/2018) =

* FIX: There was an error in the calculation of the next available time when holidays that applied to all shipping methods were active and upcoming (regression since 1.9.0)

= 1.10.1 (12/Oct/2018) =

* FIX: When quantities were changed at the checkout, because of how WooCommerce internally updates the page state, the current status needed to be re-checked, but was not.
* TRANSLATIONS: Updated bundled translations
* COMPATIBILITY: Marked as supporting WooCommerce 3.5, and requiring at least 3.0. There should be nothing to stop it continuing to work on WC 2.6 (nothing has been deliberately disabled), but it will no longer be tested, nor support provided for it.

= 1.10.0 (06/Sep/2018) =

* FIX: If a shop had multiple sets of opening hours on the same day (e.g. 00:00 - 01:00, then 19:00 - 24:00), then when calculating the next opening hour before either of these arrived (e.g. 23:15 the previous day), a correct result relied upon the order of the sets in the saved settings (if you listed the later hours earlier, then it would wrongly go for that one).
* TWEAK: There is a slight change in default behaviour for some users in this version (hence the minor version bump). The change only affects shop which have chosen to always show the user a time-picker at the check-out, and have a minimum order fulfilment time greater than zero. In previous releases, a customer would be informed via a notice at the cart and check-out if the present moment was an unavailable time (i.e. the fulfilment time was not consulted). In this release, this is changed so that the customer is informed if the time after the minimum fulfilment time is an available time. This is for two reasons: 1) If the soonest-possible fulfilment time is possible, then telling the customer that *now* is not available is irrelevant at best, confusing at worst and 2) Similarly for the converse situation where *now* would have been available if they'd ordered earlier, but that the shop's fulfilment times will end before any order they now place can be fulfilled.
* TWEAK: Up until now, the customer was not notified prior to attempting to place the order at the checkout if an item in the cart had a category restriction with a non-zero minimum fulfilment time, and if the time after the passage of that fulfilment time (starting from the current time) was not within the hours allowed for the category in the settings. Now, they are notified.
* TWEAK: In the next_opening_time() method, ensure that the return list members are all cast to integers, to prevent issues with output layers that perform entity modifications

= 1.9.2 (21/Aug/2018) =

* FIX: Fix a potential bug in calculating the shop open status upon order placement if no orders were permitted out of hours
* TWEAK: Fix wrong formatting in a notice about the next opening time
* TWEAK: Include the WP timezone settings in the 'settings' export.
* TWEAK: Marked as supporting WordPress 3.9+ (nothing has changed to stop it working on earlier versions, but this is our official support requirement)
* TWEAK: Fix a regression that prevented debug mode taking effect immediately on the checkout page
* TWEAK: Updated updater library to latest version (1.5.4)

= 1.9.1 (10/Jul/2018) =

* TWEAK: If the checkout has no shipping method information available discoverable on it, then use the default settings
* TWEAK: Tweak JavaScript debugging; use the openinghours_debug_mode global variable so that debugging can be activated from the developer console

= 1.9.0 (24/May/2018) =

* FEATURE: Holidays can now be selected to apply on a per-shipping-instance basis
* FEATURE: Non-standard shipping methods that have different identifiers on the front and back ends can now be handled using the filters openinghours_shipping_method_from_front_end and openinghours_shipping_method_for_front_end to translate between them
* TWEAK: Marked as supporting WooCommerce 3.4 (supports 2.6+)
* TWEAK: Updated updater library to latest version (1.5.3)
* TWEAK: Marked as supporting WordPress 3.8+ (nothing has changed to stop it working on earlier versions, but this is our official support requirement)
* TWEAK: Replace the obsolete jQuery.parseJSON() with JSON.parse()
* TWEAK: Removed some code only needed for obsolete WC versions

= 1.8.6 (23/Feb/2018) =

* TWEAK: Add a shortcode openinghours_time_chosen for use within 'WooCommerce PDF Packing Slips & Invoices' (WP Overnight) templates

= 1.8.5 (21/Feb/2018) =

* TWEAK: Preserve the relative ordering of the date format setting 'j. F, Y' in the front-end picker

= 1.8.3 (25/Jan/2018) =

* TWEAK: Marked as compatible with WC 3.3

= 1.8.2 (18/Dec/2017) =

* FIX: When using WooCommerce shipping zone instances, with instances that varied in their list of allowed days, the list used in the time-picker widget was not being calculated accurately and was using the list from the default shipping settings

= 1.8.1 (08/Dec/2017) =

* FIX: The fix for calculating the soonest allowed time on the timepicker widget in 1.7.23 had an implicit dependency on the browser timezone

= 1.8.0 (02/Dec/2017) =

* TWEAK: Now bundles version 1.6 series of the TimePicker library (from previous 1.5). If you encounter a problem, you can switch back to version 1.5 by using the openinghours_timepickerjs_version_series filter and returning the value '1.5'.
* TWEAK: A previously disabled (long ago) parameter that set the minimum and maximum hours on any slider is now re-enabled, with a work-around for the issue in the TimePicker library which led to it being disabled.

= 1.7.23 (23/Nov/2017) =

* FIX: When calculating the soonest allowed time, take into account the WordPress timezone

= 1.7.22 (07/Nov/2017) =

* FIX: When the plugin was activated, the user's billing email/phone was not present in the new order admin notification email
* TWEAK: Update the included emails/email-addresses.php template to the current version (from WC 3.2)

= 1.7.21 (06/Nov/2017) =

* TWEAK: Updated the bundled updater library to latest version (1.5.0)

= 1.7.20 (04/Nov/2017) =

* TWEAK: Update the included emails/plain/email-addresses.php template to the current version (from WC 3.2)
* TWEAK: Bundled version of yahnis-elsts/plugin-update-checker updated to current (4.3.1)

= 1.7.19 (10/Oct/2017) =

* TWEAK: Removed a couple of lines of debugging code
* TWEAK: Added filters to a couple of functions dealing with minimum order times to make them more customisable by developers
* TWEAK: Make the get_display_time_from_order() method public for easier developer use

= 1.7.18 (06/Oct/2017) =

* TWEAK: Update bundled updater to latest version (1.4.8)

= 1.7.17 (28/Sep/2017) =

* TWEAK: Tweak CSS to restore correct width of widgetry in admin area on forthcoming WooCommerce 3.2

= 1.7.16 (31/Aug/2017) =

* TWEAK: Add WooCommerce version headers (https://woocommerce.wordpress.com/2017/08/28/new-version-check-in-woocommerce-3-2/)

= 1.7.15 (12/Aug/2017) =

* PERFORMANCE/FIX: Time-availability AJAX lookups were being performed multiple times
* TWEAK: Update to the latest version of the updater library
* FIX: The default time entered on loading the checkout page now incorporates the results of category restriction minimum gaps based on cart contents
* FIX: The default time entered on loading the checkout page did not take notice of per-shipping-instance minimum gap configurations

= 1.7.14 (20/Jul/2017) =

* TWEAK: Update to ensure that the time is including on the printouts of WooCommerce PDF Invoices and Packing Slips 2.0+

= 1.7.11 (15/Jul/2017) =

* FEATURE: Add support for adding date/time information to WooCommerce Print Invoices/Packing Lists (https://www.woocommerce.com/products/print-invoices-packing-lists/)

= 1.7.10 (6/Jun/2017) =

* TWEAK: Provide filters openinghours_store_time, openinghours_store_time_format to allow the stored time to be customised/over-ridden
* TWEAK: Update the bundled WooCommerce compatibility library to the current version

= 1.7.9 (27/May/2017) =

* TWEAK: Prevent a couple of deprecation notices on WC 3.0
* TWEAK: In the email template, add extra checks on object existence
* TWEAK: Update the bundled updater to version 1.4.0

= 1.7.8 (23/May/2017) =

* FIX: Update the bundled updater to version 1.3.6, fixing a bug in it

= 1.7.7 (22/Apr/2017) =

* FIX: A wrongly-formatted value could be passed through to the widget for the minimum/maximum minute selection
* FIX: On some customised setups, the initial time would not be set
* TWEAK: Remove debugging lines that were in 1.7.6
* TWEAK: Updated bundled updater to version 1.3.3

= 1.7.6 (13/Apr/2017) =

* FIX: Prevent duplicate checkout notices on WooCommerce 3.0 when trying to place a disallowed order
* FIX: Category restrictions on product variations were not being applied on WooCommerce 3.0 (incorrect fix in 1.7.5 replaced)
* FIX: Fix a JavaScript error that could occur on checkout with a non-English translation

= 1.7.4 (25/Mar/2017) =

* COMPATIBILITY: Compatible with WooCommerce 3.0 (forthcoming)
* COMPATIBILITY: Remove remaining deprecated access of WC_Order::id property in plain email template
* FIX: Order times were not being recorded properly on WooCommerce 3.0+ in 1.7.3 (which was not publicly released)

= 1.7.3 (20/Feb/2017) =

* COMPATIBILITY: WooCommerce 2.7 (forthcoming) compatibility work
* FIX: Fix an issue with the select widget if the user chose a dropdown and entered a time past 24:00.
* FIX: Work around a bug in the jquery-ui-timepicker library with a dropdown in its handling of min/max times (https://github.com/trentrichardson/jQuery-Timepicker-Addon/issues/819)
* TWEAK: Update email template to not use deprecated methods on WC 2.7+
* TWEAK: Change a notice that implied delivery (so, inappropriate for a collection shipping method) to be more generic
* TWEAK: Load the un-minified version of jquery-ui-timepicker if in debug or SCRIPT_DEBUG modes
* TWEAK: Update the bundled updater to version 1.3, which allows the user to choose to automatically install updates
* TWEAK: Import woocommerce-compat library to help abstract away changes in WC 2.7
* TWEAK: Port all accesses of get_post_meta over to woocommerce-compat library
* TWEAK: Port all accesses of WC_Order::id and WC_Product::id over to woocommerce-compat library
* TWEAK: Add checkout-page compatibility with the plugin WooCommerce Shipping Method Display Style (which changes the internals of the 'Shipping Method' selector)
* TWEAK: Edit the "You have updated to WC 2.6" message to more accurately state the situation

= 1.6.18 (06/Dec/2016) =

* TWEAK: Work around an issue whereby translating the elements in the picker led to the initial time not displaying - the fix in 1.5.24 was incomplete
* TWEAK: Add a new openinghours_time_is_after_minimum_gap filter for allowing developers to customise the "is after minimum gap?" test

= 1.6.17 (17/Oct/2016) =

* FIX: Display of titles for zone instances using shipping methods in the "Rest of the World" zone were not being handled correctly.
* FIX: When using WC 2.6+ shipping zones, where an instance title had not been entered by the user, the default title was not being shown in the settings
* TWEAK: Show a visual warning of invalid input and guidance on how to achieve what is apparently desired if the user selects closing times after 24:00
* TWEAK: Do not show settings for zone-supporting shipping methods which are not used in any zone
* TRANSLATIONS: Updated Dutch translation

= 1.6.15 (29/Aug/2016) =;

* FIX: Fix a very long-standing issue which caused recurring holidays to be omitted from calculations in subsequent years - but only in informational messages (the holidays were still applied in terms of being removed from customer-selectable dates)

= 1.6.14 (27/Aug/2016) =

* FIX: Remove a typing error in 1.6.13 that prevented display of the selection widget on non-English sites

= 1.6.13 (23/Aug/2016) =

* TWEAK: Make the position that the time is added to the WooCommerce PDF Packing Slips and Invoices plugin filterable (filter: openinghours_wpo_wcpdf_template_position)

= 1.6.12 (06/Aug/2016) =

* TWEAK: Add a new filter, openinghours_check_cart_items, allowing the cart check to be manually over-ridden

= 1.6.11 (21/Jul/2016) =

* FIX: (Regression): Prevent fatal errors if WooCommerce was inactive

= 1.6.10 (6/Jul/2016) =

* TWEAK: Tweak the output sent to the WooCommerce Automatic Order Printing
plugin when plain-text format is indicated

= 1.6.9 (30/Jun/2016) =

* FIX: Fix an error in the 1.6 series on WooCommerce 2.6, which caused all times to be considered available if default times were set and no zone-specific times set.

= 1.6.8 (27/Jun/2016) =

* TWEAK: Add an extra check to a conditional block, to avoid triggering a bug in some other component on one customer's site

= 1.6.7 (7/Jun/2016) =

* FIX: Fix an error in the 1.6 series on WooCommerce < 2.6 that, with certain settings, caused an erroneous request for a time to be chosen contrary to the settings

= 1.6.6 (4/Jun/2016) =

* FEATURE: Introduce a new shortcode, [openinghours_conditional only_if="(open|closed)"]. Content contained inside the opening and shortcode tags will only render if the shop is open/closed, according to your default opening hours (i.e. it does not depend upon considerations of category, or minimum order fulfilment times). e.g. [openinghours_conditional only_if="closed"]The shop is closed now.[/openinghours_conditional]. Remember that you must exclude pages with conditional content from cacheing plugins. Further parameters are available to use the hours for a specified shipping method: shipping_method="(shipping_method_textual_id|default)", instance_id="(shipping_zone_instance_id)" (applies only on WC 2.6+).

= 1.6.5 (23/May/2016) =
* TWEAK: Correct a wrong link to the settings in the WC 2.6 upgrade notice
* TWEAK: Remove a stray line of text underneath the 'Export Settings' button
(introduced in 1.6.2)

= 1.6.4 (21/May/2016) =

* FIX: Fix a regression whereby when the shipping method was updated on the checkout page, the register of unavailable days was not being updated to reflect the change

= 1.6.3 (20/May/2016) =

* FIX: The spinner added in 1.6.0 was pointing to the wrong image file, and hence not visible
* FEATURE: Bundled a script for importing settings
* FEATURE: Allow the setting of negative minimum gap values, allowing the shop owner to limit selection of times in the past (in the case where the customer hovers on the checkout for a while)

= 1.6.2 (19/May/2016) =

* FEATURE: There is now an "Export Settings" button, which makes it much easier to reproduce problematic setups

= 1.6.1 (14/May/2016) =

* FEATURE: Enhance the "minimum order time" feature to be set per-shipping method (instead of a shop-wide setting)
* TWEAK: When in debug mode, debug messages were not operational on the cart page
* COMPATIBILITY: Updated for compatibility with the second (and current) beta of WooCommerce 2.6, which has undocumented incompatible internal changes to shipping zones handling; consequently, this version is no longer compatible with beta 1.

= 1.6.0 (13/May/2016) =

* FEATURE: Enhanced to take avantage of WooCommerce 2.6's (forthcoming) new shipping zones feature. You can set separate opening hours now for each shipping instance, not just each shipping method (so, flat rate delivery to Europe can have different times than available flat rate delivery to other places). Important: you *must* review and update your settings for this plugin after you update to WooCommerce 2.6 (and you should do this after you have created your shipping zones, so that the settings reflect their existence). A dashboard notice will prompt you to do this. The plugin remains compatible with earlier WooCommerce versions (2.1 onwards). Shipping zones are a major new feature of WooCommerce, and you are strongly advised to test them, and this plugin, out upon a development version of your site.
* FIX: Prevent a JavaScript error on the cart when changing shipping methods, and restore the showing/hiding of notices appropriately
* TWEAK: Re-organise use of term meta functions, to prepare for deprecation in a later WC release (2.7 or later)
* TWEAK: Introduce a visual spinner when looking up the time availability, to assist usability

= 1.5.25 (18/Apr/2016) =

* FIX: A previously un-noticed change in WP 4.5 prevented time settings being shown or altered on category edit pages (though, existing settings still worked)

= 1.5.24 (16/Apr/2016) =

* TWEAK: Work around an issue whereby translating the elements in the picker led to the initial time not displaying

= 1.5.23 (06/Apr/2016) =

* TWEAK: Suppress a PHP notice generated at checkout in some limited circumstances

= 1.5.22 (05/Apr/2016) =

* TWEAK: Updated German (de_DE) translations - thanks to Florian Moser
* Marked as compatible with WP 4.5

= 1.5.21 (18/Mar/2016) =

* TWEAK: Change some references to 'time' to 'date' when in date-only mode

= 1.5.20 (01/Jan/2016) =

* TWEAK: Add the chosen time to plain-text versions of emails (where in use), as well as HTML verions
* TRANSLATIONS: Partial Swedish translation, courtesy of Patrick Ribbsaeter
* Tested with WooCommerce 2.5

= 1.5.19 (12/Nov/2015) =

* FIX: If an unrecognised time format was entered in the WP settings, then the initial time on the checkout was not displaying properly

= 1.5.18 (20/Oct/2015) =

* FEATURE: Add new option when setting a minimum order time: allow the shop owner to lengthen the minimum order time if the current time (today) has gone past a certain point. This allows the setting of more sophisticated rules, including "next day collection is possible only if you order before a certain time".

= 1.5.16 (07/Oct/2015) =

* FIX: 1.5.15 was a bad update, for which we apologise. If you installed it and then visited WooCommerce admin pages, you may find that your opening hours settings are gone, and after updating you will need to re-create them.

= 1.5.15 (03/Oct/2015) =

* TWEAK: Add link to plugin FAQs in the settings
* FIX: "Apply to Virtual Goods" checkbox would always show as selected, regardless of how the option had been saved

= 1.5.14 (08/Sep/2015) =

* FIX: 1.5.13 broke the addition of the order time information in the default "WooCommerce PDF Packing Slips & Invoices" plugin.

= 1.5.13 (31/Aug/2015) =

* TWEAK: Add a link to the WordPress timezone settings from the plugin settings for convenience.
* FEATURE: Add an action openinghours_print_choice, for printing out the chosen time (e.g. in PDF template)
* TWEAK: Add a filter openinghours_wpo_wcpdf_footer, allowing the alteration or suppression of the PDF footer text (with the PDF Packing Slips + Invoices plugin)

= 1.5.12 (20/Aug/2015) =

* TWEAK: Pass through the initial value for the checkout field to the filter which allows developers to render their own customised version

= 1.5.11 (17/Aug/2015) =

* TRANSLATIONS: New French and Italian translations, courtesy of Marco Abittan
* FIX: Fix a further issue due to an incompatibility with an undocumented change in WooCommerce 2.4 (affecting users with category restrictions and checkouts where the user chose a time for later delivery).

= 1.5.9 (15/Aug/2015) =

* FEATURE: Add new CSS classes on items in the WooCommerce category list - category-open and category-closed - according to the category's status. You should, of course, not cache your category pages for long if using this (or make sure your cache is emptied at appropriate times).

= 1.5.8 (13/Aug/2015) =

* FIX: Fix an issue on WooCommerce 2.4 not discovered in previous testing (affecting users with the 'inform when closed' setting - all users with this setting should immediately upgrade).
* TWEAK: The date/time shown on PDFs/print-outs in the integrations with various plugins now uses the date/time format configured in the WP dashboard.

= 1.5.7 (01/Aug/2015) =

* COMPATIBILITY + FIX: WooCommerce 2.4 compatibility (upgrade required - changes in WC 2.4 mean that previous releases are not WC 2.4 compatible) (at the time of writing, WooCommerce 2.4 is not yet released)
* COMPATIBILITY: tested with WordPress 4.3 (release candidate 1)
* FIX: Fix incomplete implementation of tweak in 1.4.24: "Use hyphens when using European format dates (e.g. 08-06-2015), to conform with PHP's assumptions about date formatting (http://php.net/strtotime)".
* FIX: Fix issue where items with category restrictions that varied daily could make the wrong dates be unselectable in the datepicker

= 1.5.6 (25/Jul/2015) =

* FIX: The date/time shown on the 'Order received' page and order emails should use the date/time format configured in the WP dashboard.

= 1.5.4 (20/Jul/2015) =

* FIX: The 'Maximum Number of Days Ahead' setting is now also enforced for dates a customer types directly in as text. (Previously when enforced only via not being possible to select on the date-picker).

= 1.5.2 (15/Jul/2015) =

* TWEAK: New action (openinghours_archive_page) called on the shop archive page for convenience - allowing developers to easily take actions on that page depending on whether the shop is open or not.

= 1.5.1 (13/Jul/2015) =

* FEATURE: New capability to add a notice to products' pages out-of-hours (so, if you use this, you should make sure no cacheing plugins are cacheing your product pages)
* COMPATIBILITY: Neither this nor any future releases will be tested or supported on earlier WooCommerce versions than 2.1. i.e. WC 2.0 is no longer tested/supported; 2.1 onwards continue to be.
* TWEAK: In new installs, the default value of the "Apply To Virtual Goods" setting will be on. People wanting their settings to apply to non-tangible goods, but not spotting this setting, has probably been the #1 support request.

= 1.4.28 (30/Jun/2015) =

* TWEAK: When products are restricted due to being in multiple categories, it is now possible to control via filters how differing sets of hours interact (the default continues to be that any category that is currently available means that the category restriction test passes). Choices: product is available if any category is available; product not available if any category not available; depend upon category hierarchy (prefer parent or prefer child); and optionally ignore categories with no hours when making the calculation.

= 1.4.27 (27/Jun/2015) =

* TWEAK: When products are restricted due to being in multiple categories, it is now possible to control via filters the message which is displayed

= 1.4.26 (26/Jun/2015) =

* FEATURE: A new option on how to handle per-category restrictions for products in multiple categories has been added, allowing more flexibility
* FIX: A custom 'not available' message set for a time-restricted category product would not always show

= 1.4.25 (25/Jun/2015) =

* TWEAK: If the customer changes shipping method at the checkout, and the already-chosen time is also valid for the new shipping method, then the chosen time will not be changed to suggest the soonest available time on the new method - instead, it will remain at the previously-chosen time.

= 1.4.24 (05/Jun/2015) =
* TWEAK: Store the order date/time format in the postmeta at order time (possible future usefulness)
* TWEAK: Use hyphens when using European format dates (e.g. 08-06-2015), to conform with PHP's assumptions about date formatting (http://php.net/strtotime)

= 1.4.23 (28/May/2015) =
* FIX: When a per-category restriction existed and a relevant product was in the cart at a time when the shop was open but the category item unavailable, if the shop was set to only show the chooser sometimes, the chooser widget was not being shown.

= 1.4.22 (26/May/2015) =

* FEATURE: Restrict the minimum + maximum time across all days in the timepicker when using the 'dropdown' type (there's a bug in the current timepicker release when using slider)

= 1.4.21 (25/May/2015) =

* FIX: Update admin new order email template fragment for recent WC versions (was dropping shipping address)

= 1.4.20 (15/May/2015) =

* Remove "Now" button from datepicker (the plugin already selects a soon-as-possible default, and "Now" is often impossible because of other settings, e.g. minimum order time, or shop is not open, etc.)

= 1.4.19 (02/May/2015) =: 

* TWEAK: New filter: openinghours_datepickerformat_oneline - set to true (and set openinghours_timepickercontroltype to 'select') to use the timepicker's one-line style

= 1.4.18 (11/Apr/2015) =

* New filter: openinghours_frontend_initialvalue: set to 'inline' to change to inline date/time widget (instead of one that displays when the text field is clicked)

= 1.4.17 (02/Apr/2015) =
* Updated Dutch translation (thanks to Alex Kerklaan)

= 1.4.16 (31/Mar/2015) =
* TWEAK/FIX: Update timepicker library to 1.5.2, which also fixes a bug when using drop-downs that could prevent some minute options being shown
* TWEAK: Introduce openinghours_jquery_ui_url and openinghours_datetimepickerstepminute filters for developers

= 1.4.15 (21/Mar/2015) =

* FIX: Front-end messages did not have month name localised.

= 1.4.14 (17/Mar/2015) =

* FEATURE: Can now apply minimum order fulfiment time on a per-category basis. (The minimum time for the whole shopping cart will be the maximum time for the shop and any categories represented in the cart).
* FIX: When per-category restrictions existed, it was possible for some days to be disallowed from the date-picker which should have been allowed
* FIX: Set the alwaysSetTime timepicker option in a different way, to avoid a jQuery exception
* TWEAK: The minimum gap is now used when deciding whether to exclude dates on the date-picker - meaning that some dates which could not yield allowed times are now correctly prevented from being chosen
* TWEAK: Update timepicker library (to 1.4.6) and include non-minified version

= 1.4.12 (13/Mar/2015) =

* Fix bug in plugin updater that could cause white screens on some WP sites

= 1.4.11 (10/Mar/2015) =

* FEATURE: Added plugin updater - update availability can now be made known and updates can now be installed through the standard WP updating mechanism

= 1.4.10 (03/Mar/2015) =

* FIX: Fix bug in calculation of current time, when checking initial availability (since 1.4.2)
* TWEAK: Introduce openinghours_frontend_initialvalue_formethod filter for allowing developers to over-ride the initial value for different shipping methods
* TWEAK: Remove JavaScript debug alert accidentally left in a previous release

= 1.4.8 (27/Feb/2015) =

* Fix bug in the calculation/display of the next opening hours when forbidding out-of-hours checkout with shipping chosen but no per-shipping-method times.

= 1.4.6 (24/Feb/2015) =

* Prevent JavaScript notice being logged at cart/checkout (since 1.4.2)

= 1.4.5 (17/Feb/2015) =

* Remove debugging code inadvertantly left in 1.4.2
* Improve string-to-time processing when using {chosen_time} tag
* Make the {chosen_time} tag work with the "processing order" email

= 1.4.2 (13/Feb/2015) =

* New feature to restrict times for products by category (access via Products -> Category -> Edit, for an existing category) - e.g., have a lunch-time menu that cannot be ordered at other times
* FIX: Do not print the time chooser field, if the cart contents are all in shipping classes which are configured to not require any time selection
* TWEAK: Make {chosen_time} use WordPress's configured time display format
* Translation: Updated German translation (thanks to Christian Pagel)

= 1.3.3 (05/Feb/2015) =

* Add support for {chosen_time} tag in "Customer Note" email also.

= 1.3.2 (24/Jan/2015) =

* New feature: per-shipping-method over-rides for your shop's times. Have different hours for different shipping methods (e.g. local delivery available at some times; local pick-up available at others).
* Fix bug (lack of permissions checking) that potentially allowed any logged-in user with dashboard access to adjust opening hours settings.
* Various text strings changed to reflect fact that order fulfilment != delivery (i.e. do not assume that the plugin is being used for "delivery" times; could be "pick up", or something else).
* Show chosen time in the 'Shipping' column of the WooCommerce orders listing screen
* Add chosen time to the WooCommerce "Thank you. Your order has been received." page
* Replace {chosen_time} tag in WooCommerce email subject lines with the chosen time
* Tweak width of 'minimum order fulfilment' widget, to allow larger numbers to show more easily
* Introduce constant OPENINGHOURS_DATEONLY as an alternative to the filter openinghours_date_only (note that the filter, if set, will have the final say)
* Display information in the back-end when "date only" mode is set.

= 1.2.6 (12/Jan/2015) =

Fix bug that caused some unavailable dates to not be greyed-out in the datepicker. Add openinghours_next_opening_time filter to allow programmers to over-ride the next open time. Add openinghours_frontend_initialvalue filter to allow programmers to tweak the displayed initial value of the checkout field.

= 1.2.4 (07/Jan/2015) =

Fix two more bugs to do with a.m./p.m. and hour=12

= 1.2.3 (06/Jan/2015) =

FEATURE: Allow the shop owner to set a maximum number of days into the future that can be chosen. Fix bug with time decoding when time format contains "a.m." and hour=12 (i.e. just after midnight).

= 1.2.2 (22/Dec/2014) =

FIX: when date-only was activated (new feature in 1.2.1), it was possible to choose dates in the past

= 1.2.1 (20/Dec/2014) =

Can now drop the hour/minute from times in the front-end, and allow the user to choose a date only. Internationalisation/localisation (41 languages + 33 partial) now added for the front-end and back-end picker.

= 1.1.21 (18/Dec/2014) =

Add CSS tweak to prevent timepicker disappearing behind other layers on themes which over-write jQuery UI CSS

= 1.1.20 (05/Dec/2014) =

New Dutch (nl_NL) translation (thanks to Dave Heere)

= 1.1.19 (28/Nov/2014) =

Fix PHP notice for undefined constant

= 1.1.18 (24/Nov/2014) =

Previous bug-fix in the logic for the "minimum time before order fulfilment" feature was only on the cart page, and also needed applying to the checkout page. WordPress's "first day of the week" setting is now also respected. Remove the "Now" button from the timepicker (since it already defaults to the first available time, and does not take into account minimum order fulfilment times).

= 1.1.17 (04/Nov/2014) =

Integration with the "WooCommerce Print Orders", "WooCommerce Delivery Notes" and "WooCommerce PDF Invoices and Packing Slips" plugins (add time to their printouts)

= 1.1.16 (22/Oct/2014) =

Fix bug in the logic for the "minimum time before order fulfilment" feature. Introduce openinghours_timepickercontroltype filter to allow selection of the time picker control type (default is 'slider'; 'select' is also possible)

= 1.1.15 (11/Sep/2014) =

Fix case where time format (since 1.1.12) was incorrectly parsed

= 1.1.14 (11/Sep/2014) =

Add new option to allow non-shippable (i.e. virtual goods) to be subject to time restrictions too

= 1.1.13 (30/Aug/2014) =

Fix case where time format setting was not used, and restore WC 2.0 compatibility which was broken in 1.1.11

= 1.1.12 (28/Aug/2014) =

Use WordPress's time format setting (in Settings->General) in the front-end time-picker

= 1.1.11 (19/Aug/2014) =

Add setting to require products to be in specified shipping classes

= 1.1.10 (28/Jun/2014) =

German translation (thanks to Marcel Herrguth)

= 1.1.9 (17/Jun/2014) =

Add settings link. Add indication of current time at page load to settings.

= 1.1.8 (25/Apr/2014) =

New Spanish translation, and fix bug that prevented translations from loading

= 1.1.7 (20/Mar/2014) =

New option: if outside of set hours, then simply display a message (but do not prevent check-out, or make the user choose a time)

= 1.1.6 =

Add visual warning if the user attempts to set a closing time before the opening time

= 1.1.5 =

Update to settings saving on WC 2.1 (page name has changed)

= 1.1.4 =

Update to use new WC 2.1 notices API if available (remains compatible with prior versions)

= 1.1.1 (19/Dec/2013) =

Added Bulgarian translation (from Galin Dinov, http://tajmahal.bg/)
