=== WooCommerce Print Orders ===
Contributors: David Anderson
Requires at least: 3.9
Tested up to: 5.0
License: MIT / GPLv2+

== Description ==

A plugin to automatically print off completed orders via Google Cloud Print.

The output format can be heavily customised. Please see the contents of the 'templates' directory. Many hooks and filters are used to make the output customisable by developers also.

== Installation ==

1. Make sure that your printer is set up with Google Cloud Print (https://www.google.com/cloudprint/).

2. Install and then activate this plugin via uploading it into WordPress's plugin installer (use the "Upload" tab). It is very possible that you will receive an error that your web hosting's limit for upload sizes is too small... if so, then try one of these two methods instead: a) Install the "Upload Larger Plugins" plugin, by searching for it in WordPress's plugin page or b) Unzip this plugin on your PC, and use FTP to transfer it to your website (into wp-content/plugins). Then visit the Plugins page in your WordPress dashboard to activate it.

3. Configure your Google Cloud Print settings in your WordPress dashboard, in Settings -> Google Cloud Print

4. Configure your printouts in your WordPress dashboard, in WooCommerce -> Settings

== Changelog ==

= 2.7.15 -2018/10/05 =

* FIX: Version 2.7.14 bundled a wrong version of the default simple print template
* TWEAK: Update bundled updater to latest version (1.5.7)
* TWEAK: Apply woocommerce_printorders_print_order_items filter on debug print-outs also

= 2.7.14 - 2018/10/23 =

* TWEAK: Mark as compatible with WC 3.5 (supporting from 3.0 upwards)
* TWEAK: Mark as officially supporting WP 3.9+
* TWEAK: Logging tweak to show if printing decision came from core code or a filter
* TWEAK: Update bundled updater library to latest (1.5.5)

= 2.7.13 - 2018/08/02 =

* TWEAK: Add the woocommerce_printorders_print_order_items filter to allow filtering or sorting of items before they get sent to the template

= 2.7.12 - 2018/06/27 =

* TWEAK: Update bundled GCPL library to 0.8.11

= 2.7.11 - 2018/05/24 =

* FIX: Update bundled GCPL library to 0.8.10, fixing a bug that prevented detection of printers

= 2.7.10 - 2018/05/21 =

* TWEAK: Mark as compatible with WC 3.4 (supporting from 2.6 upwards)
* TWEAK: Mark as officially supporting WP 3.8+
* TWEAK: Update bundled updater library to latest (1.5.3)
* TWEAK: Update bundled GCPL library to 0.8.9
* TWEAK: Always allow use of the cache for getting the printer list when register WooCommerce actions

= 2.7.9 - 2018/03/23 =

* TWEAK: Adjust the internal template to include details on fees (WC 3.0+)
* TWEAK: Register actions before AutomateWoo fires its actions
* TWEAK: Update default 'simple' template to include order time
* TWEAK: Updated bundled GCPL library to 0.8.8

= 2.7.8 - 2018/02/26 =

* TWEAK: Updated bundled GCPL library to 0.8.6, to allow cacheing 

= 2.7.7 - 2018/01/25 =

* TWEAK: Add libxml 2.9.7 to the list of versions known to be buggy and requiring a work-around

= 2.7.6 - 2018/01/24 =

* COMPATIBILITY: Now marked as supporting the forthcoming WooCommerce 3.3. In accordance with our one-in, one-out policy, WooCommerce 2.5+ is now required; i.e. support for WC 2.4 dropped (it probably still works, but if it doesn't, you will want to either down-grade this plugin, or (much better) upgrade WC).
* TWEAK: Update bundled GCPL library to version 0.8.4, to inherit DomPDF 0.8.2
* TWEAK: Update bundled updater libraries to latest versions

= 2.7.5 - 2017/10/18 =

* TWEAK: Update links for WooCommerce Delivery Notes settings

= 2.7.4 - 2017/10/06 =

* TWEAK: Update bundled GCPL library to version 0.8.3, trying to mitigate a LibXML 2.9.5/2.9.6 bug
* TWEAK: Update bundled updater library to latest version (1.4.8)

= 2.7.2 - 2017/10/03 =

* TWEAK: Update bundled DomPDF library to version 0.8.1

= 2.7.1 - 2017/09/19 =

* TWEAK: Set the 'init' parameter to true when calling wcpdf_get_document (WooCommerce PDF Invoices + Packing Slips) to prevent possible missing content

= 2.7.0 - 2017/09/14 =

* FEATURE: Beta support for printing the output of the woocommerce.com plugin "Print Invoices and Packing Slips". This plugin does not produce PDF output, and hence success relies upon the DomPDF engine being able to successfully convert the HTML output of your template into a PDF with the same layout. For this reason, support for this plugin is likely to always be classifed as beta. We cannot give any support for layout issues with the output of this plugin.

= 2.6.5 - 2017/09/11 =

* FEATURE: The 'Order Action' menu now shows a separate option for each print template as well as each destination.
* TWEAK: Update the updater library to the current version (1.4.7)

= 2.6.4 - 2017/08/31 =

* TWEAK: Add WooCommerce version headers (https://woocommerce.wordpress.com/2017/08/28/new-version-check-in-woocommerce-3-2/)

= 2.6.3 - 2017/08/26 =

* FEATURE: Add the ability to download the debug log file via a link from the settings section

= 2.6.2 - 2017/08/24 =

* FIX: Fix a compatibility issue with WooCommerce Packing Slips and PDF Invoices 2.0+ on multi-lingual set-ups

= 2.6.1 - 2017/08/17 =

* UPDATE: Updated/ported code to use the current DomPDF version, 0.8.0, via bundling version 0.8.0 of the GCPL library. DomPDF now claims better PHP 7.1+ compatibility.
* TWEAK: Update to the latest version of the bundled plugin updater

= 2.5.23 - 2017/08/16 =

* TWEAK: Work around another plugin bundling an incompatible version of DomPDF

= 2.5.22 - 2017/08/16 =

* TWEAK: Log now includes PHP version
* TWEAK: Thrown exceptions and (on PHP 7+) catchable errors are now caught and logged for better investigation of issues
* TWEAK: Also log PHP backtrace at start of print_go()
* TWEAK: All log messages (not just WP_Error-s) are now logged in the WC on-disk log	

= 2.5.20 - 2017/08/02 =

* FIX: On multisite, when checking active plugins, also check network-activated plugins

= 2.5.19 - 2017/08/01 =

* TWEAK: Update bundled updater to current version

= 2.5.18 - 2017/07/03 =

* COMPATIBILITY: The plugin was wrongly marked in the readme was only tested up to 4.7 (it is always tested on current versions, which in this case is 4.8)

= 2.5.17 - 2017/06/30 =

* COMPATIBILITY: Compatible with the forthcoming WooCommerce Packing Slips and PDF Invoices 2.0 release (due to incompatible changes in that plugin, previous versions will cause PHP fatal errors). Compatibility with previous versions is also retained.

= 2.5.16 - 2017/06/06 =

* TWEAK: Make it easier to filter which address is used
* TWEAK: If shipping is disabled on the store, default to using the billing address on internal templates

= 2.5.15 - 2017/05/25 =

* TWEAK: Add a woocommerce_print_orders_print_on_payment_complete filter to allow suppression of automatic printing on payment_complete events.

= 2.5.14 - 2017/05/24 =

* FIX: The internal format template was showing a null date on WooCommerce 3.0 since one of the recent 3.0.x point releases.

= 2.5.13 - 2017/05/23 =

* FIX: Update the bundled updater library, fixing a bug in it
* TWEAK: Add the Google Cloud Print printer ID to the order note

= 2.5.12 - 2017/05/19 =

* FIX: Selections in the 'Order Action' menu were using the pre-configured printer(s) instead of the chosen one

= 2.5.11 - 2017/05/17 =

* TWEAK: Make the actions corresponding to entries in the 'Order Action' menu triggerable via cron

= 2.5.10 - 2017/05/12 =

* TWEAK: Add an order note when printing is initiated
* TWEAK: Remove some more lines which provided WC 2.1 compatibility

= 2.5.9 - 2017/05/09 =

* FEATURE: Add entries for printing to particular printers to the 'Order Action' menu
* TWEAK: Remove a small number of lines for compatibility with no-longer-supported WC versions (2.1 and below)

= 2.5.8 - 2017/04/26 =

* TWEAK: Change the print job title to include the order number, not the order ID (these are the same by default, but other plugins can change your order numbering system)
* TWEAK: Updated bundled updater to version 1.3.3

= 2.5.7 - 2017/04/22 =

* TWEAK: When an authorisation token is not accepted, make sure information on this is logged

= 2.5.6 - 2017/04/12 =

* TWEAK: Make the parameters to wp_remote_post filterable, for easier customisation

= 2.5.5 - 2017/03/28 =

* COMPATIBILITY: WooCommerce 3.0+ support (tested on release candidate 2)

= 2.5.3 - 2017/02/10 =

* COMPATIBILITY: WooCommerce 2.7+ support, without deprecation notices
* COMPATIBILITY: Minimum supported version is now WooCommerce 2.2 (probably works with earlier; we just won't test/support)
* FIX: Fix a fatal error loading the updater class in the short-lived 2.5.2 release
* TWEAK: Convert the two internal simple templates for WooCommerce 2.7+
* TWEAK: Update updater version
* TWEAK: Update bundled GCPL library to latest version, permitting setting of the monochrome/colour option for the printer
* TWEAK: Update the bundled updater to version 1.3, which allows the user to choose to automatically install updates

= 2.4.7 - 2016/12/30 =

* Feature: Add support for PDFs output by the YITH WooCommerce PDF Invoice and Shipping List (Premium) plugin (https://yithemes.com/themes/plugins/yith-woocommerce-pdf-invoice/)
* Tweak: Update bundled GCP library to version 0.7.3, which avoids a harmless PHP notice

= 2.4.6 - 2016/10/20 =

* Tweak: Update bundled GCP library to version 0.7.2, which adds a couple of filters for easier developer customisation of printer-specific options

= 2.4.5 - 2016/08/11 =

* Feature: Update bundled GCP library to version 0.7.1, which implements more printer-specific options (page orientation and page margins)
* Tweak: Improve the UI for the internal template, making it clearer that what is being chosen is a PDF page size, not a printer page size, and that some options depend upon others.
* Fix: The "Debug internal format output" button did not operate correctly when using the simple template in plaintext mode
* Fix: The plain-text only format was not decoding HTML entities properly (affected non-English character sets)

= 2.4.1 - 2016/08/04 =

* Tweak: Add a filter making it easier to decide to print when the order status changes

= 2.4.0 - 2016/07/27 =

* Feature: Update bundled GCP library to version 0.7.0, which implements printer-specific options (currently: page size and fit-to-page, if available)

= 2.3.0 - 2016/07/06 =

* Feature: The 'simple internal template' (suitable for many thermal or narrow printers) now has a "plain text only" mode (i.e. no HTML or formating). A user-configurable template is available in the 'templates' directory.
* Tweak: Update bundled GCP library to version 0.6.0
* Tweak: Update bundled DOMPDF library to version 0.6.2
* Tweak: Fix invalid HTML p/div nesting in the default simple template

= 2.2.7 - 2016/04/29 =

* Tweak: Stop using a function that will be deprecated in future WC versions (not before 2.7 at least, probably 3.0)

= 2.2.6 - 2016/04/18 =

* Tweak: Pass the '&' parameter explicitly to http_build_query(), in case the PHP setup has an unhelpful configuration

= 2.2.5 - 2016/04/09 =

* Tweak: Pass the order_id along with the print options, so that it is available to subsequent filters (e.g. a filter that decides to only prints some orders to some printers)

= 2.2.4 - 2016/04/07 =

* Tweak: Also log all messages via WooCommerce's logger class (in addition to the debug email option)

= 2.2.3 - 2016/03/28 =

* Tweak: Add a filter to allow cancelling of print jobs before sending

= 2.2.2 - 2016/03/18 =

* Tweak: Slightly more logging concerning the output generated, prior to despatching it to the printer
* Tweak: Clearer message when no printers are set up

= 2.2.1 - 2016/02/06 =
* Tweak: Prevent printing the same order twice on the same run (useful for when the shop owner sets up manual filters to cause the printout to print in response to non-default events)

= 2.2.0 - 2015/09/28 =
* Feature: Introduced a new arbitrary 'paper size' setting for the internal template (defaults to US letter, the previous default)
* Tweak: Default internal template now explicitly sets small bottom margins

= 2.1.5 - 2015/08/24 =
* Tweak: Fix unbalanced HTML tags in the admin area, causing everything to be italicised

= 2.1.4 - 2015/08/20 =
* Tweak: Remove CSS class from admin notice that prevented it displaying with some themes

= 2.1.3 - 2015/08/10 =
* Tweak: Introduce woocommerce_print_orders_via_cron filter: set it to true to schedule printing via WP's cron mechanism, so that it gets backgrounded (requires your WP install to have the WP scheduler working, of course)

= 2.1.2 - 2015/08/01 =
* Compatibility: Tested on WordPress 4.3 (RC1) and WooCommerce 2.4 (RC1)
* Tweak: Default narrow template tweaked to avoid deprecation PHP notice on WC 2.4

= 2.1.1 - 2015/06/24 =
* Feature: Now allows the print job to be sent to multiple printers, rather than just one

= 2.1.0 - 2015/05/30 =
* Update: Now using OAuth for authentication with Google, as the previous ClientLogin method has been abolished by Google.

= 2.0.16 - 2015/05/01 =
* Tweak: In narrow print-out, call WC_Product::exists() before printing line items

= 2.0.15 - 2015/04/24 =
* Tweak: Add the delivery method to the default (narrow) format print-out
* Tweak: Show user's chosen order number on default (narrow) template if they were using a custom order-number plugin

= 2.0.14 - 2015/04/02 =
* Version 1.5.7 and onwards of the WooCommerce PDF Invoices & Packing Slips plugin now provides its own option to include phone/email. So, we will no longer automatically add those details. NOTE: If you wish them to be included, then after you update to WooCommerce PDF Invoices & Packing Slips 1.5.7 or later, you will need to visit its 'Templates' setting and tick the boxes to add them.
* Tested and compatible on forthcoming WP 4.2.

= 2.0.13 - 2015/03/13 =
* Fix bug in plugin updater that could cause white screens on some WP sites

= 2.0.12 - 2015/03/11 =
* Add updater - updates are now integrated with the standard WordPress dashboard updates mechanism

= 2.0.11 - 2015/03/07 =
* Change default font on internal format print-out to DejaVu Serif, which includes non-ANSI characters

= 2.0.10 - 2015/02/24 =
* Add SKU (if present) to default internal print-out
* No further testing or support will take place for WC 2.0, now that WC 2.3 has been released

= 2.0.9 - 2015/02/16 =
* Support for third-party "Pay in Store" payment method - https://wordpress.org/plugins/woocommerce-pay-in-store-gateway/
* Support for the third-party "Cash on Pickup" payment method - https://wordpress.org/plugins/wc-cash-on-pickup/ - i.e. instant print when someone orders using this payment method (like the existing "Cash on Delivery", "Cheque" and "BACS" - rather than waiting until payment completes, as with PayPal, etc.).
* Previously tested for compatibility with WP 4.1 and WC 2.3

= 2.0.7 - 2015/02/04 =
* Translation: French translation, courtesy of Pause Saumon

= 2.0.6 - 2015/01/30 =
* Tweak: Add notice with guidance for users affected by Google's recent changes to the ClientLogin method
* Tweak: Add a 'Send to Google Cloud Print' button in order screen (for easier testing)
* Tweak: Add a 'Print internal format' button in order screen (for easier testing)
* Tweak: Strip HTML tags from payment method titles on basic printout (found a payment method that added unwanted formatting)
* Tweak: Adjust internal format template to give a class to the variable items paragraph

= 2.0.3 - 2015/01/06 =
* Tweak: Add a small margin to the top of output from WooCommerce PDF Invoices and Packing Slips

= 2.0.2 - 2015/01/05 =
* Tweak: will now add the customer's email address + phone number to the billing address on output from the PDF Invoicing + Packing Slips plugin

= 2.0.0 - 2014/11/01 =
* Code re-organised. Google Cloud Print library integrated (no need for separate install - though is compatible with it).
* Internal summary split out into a separate, filterable, customisable template
* Settings page added
* Ability to use order summaries produced by other plugins added
* Compatible with WordPress 4.0 and WooCommerce 2.2 (latest point releases)

= 1.1.11 - 2014/03/25 =
* Tested on WordPress 3.9 and WooCommerce 2.1

= 1.1.6 =
* Allow printing multiple copies via woocommerce_print_orders_copies filter
* More CSS styling of different parts of the printout

= 1.1.3 =
* Added woocommerce_print_orders_printdefault_<entity> filters

= 1.1 =
* Added compatibility with the WooCommerce "Product Add-ons" plugin (i.e. the printed order will now include add-ons).

== License ==

Copyright 2013- David Anderson

MIT License:

Permission is hereby granted, free of charge, to any person obtaining
a copy of this software and associated documentation files (the
"Software"), to deal in the Software without restriction, including
without limitation the rights to use, copy, modify, merge, publish,
distribute, sublicense, and/or sell copies of the Software, and to
permit persons to whom the Software is furnished to do so, subject to
the following conditions:

The above copyright notice and this permission notice shall be
included in all copies or substantial portions of the Software.

THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND,
EXPRESS OR IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF
MERCHANTABILITY, FITNESS FOR A PARTICULAR PURPOSE AND
NONINFRINGEMENT. IN NO EVENT SHALL THE AUTHORS OR COPYRIGHT HOLDERS BE
LIABLE FOR ANY CLAIM, DAMAGES OR OTHER LIABILITY, WHETHER IN AN ACTION
OF CONTRACT, TORT OR OTHERWISE, ARISING FROM, OUT OF OR IN CONNECTION
WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN THE SOFTWARE.
