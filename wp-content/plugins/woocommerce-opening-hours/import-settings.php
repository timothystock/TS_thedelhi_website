<?php

// This script is for importing exported JSON settings. Run it from a current directory inside your WP install (i.e. wp-load.php is in your current directory or a parent).

if (empty($_SERVER['SHELL']) || isset($_SERVER['REMOTE_ADDR'])) die('This script is intended for command line use');

$debug = false;
$force = false;

if ($argc > 1) {
	foreach ( $argv as $key => $value) {
		if ($key == 0):
		elseif ($value == "--help" || $value == "-?" || $value == "-h"): {print_usage(); exit;}
		elseif ($value == "--debug"): { $debug=true; }
		elseif ($value == "--force"): { $force=true; }
		elseif ($key == 1): { $file = $value;}
		endif;
	}
}

function print_usage() {

        echo "Imports (most of) the indicated settings. Use the --force switch to import even if the settings are from a newer version.

Usage: import-settings.php </path/to/settings.json> [--force]

";

}

if (!isset($file)) { print_usage(); exit; }

if (!file_exists($file)) { die('File not found: '.$file); }

$json = file_get_contents($file);

if (null == ($settings = json_decode($json, true)) || empty($settings['versions']['opening_hours'])) die('Could not JSON-decode the file - apparently invalid data');

$import_version = $settings['versions']['opening_hours'];

$cwd = getcwd();
while ($cwd != '/' && !file_exists($cwd.'/wp-load.php')) {
	$cwd = dirname($cwd);
}

if (!file_exists($cwd.'/wp-load.php')) {
	die('WordPress not found');
}

require_once($cwd.'/wp-load.php');

global $woocommerce_opening_hours;
$installed_version = $woocommerce_opening_hours->version;

echo "Settings are from: WooCommerce/".$settings['versions']['wc'].", WordPress/".$settings['versions']['wp'].", Opening Hours/".$import_version."\n";

if (version_compare($import_version, $installed_version, '>')) {
	echo "The settings were exported by a version ($import_version) later than the installed version ($installed_version)\n";
	if (!$force) {
		die("Re-run with --force to force the import");
	}
}

if (!empty($settings['term_meta'])) {
	$count = 0;
	foreach ($settings['term_meta'] as $tm) {
		if (!empty($tm['meta_value']) && 'a:0:{}' != $tm['meta_value']) {
			$count++;
			echo "It is not yet possible to import category settings ($count)\n";
		}
	}
}

if (empty($settings['options'])) die('No settings were found in the export file');

if (update_option('openinghours_options', $settings['options'])) {
	echo "Import successful.\n";
} else {
	echo "Import failed.\n";
}
