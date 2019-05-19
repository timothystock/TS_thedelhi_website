<?php

die();

// This is an example of translating between front and back-end shipping identifiers with shipping extensions that do not have them consistent.

add_filter('openinghours_shipping_method_from_front_end', 'my_openinghours_shipping_method_from_front_end');
function my_openinghours_shipping_method_from_front_end($method) {
	return ('1748' == $method) ? 'advanced_shipping' : $method;
}

add_filter('openinghours_shipping_method_for_front_end', 'my_openinghours_shipping_method_for_front_end');
function my_openinghours_shipping_method_for_front_end($method) {
	return ('advanced_shipping' == $method) ? '1748' : $method;
}
