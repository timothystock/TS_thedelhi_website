<?php

if (!defined('ABSPATH')) die('No direct access.');

if (!class_exists('Updraft_Manager_Updater_1_5')) require_once(dirname(__FILE__).'/vendor/davidanderson684/simba-plugin-manager-updater/class-udm-updater.php');

$openinghours_updater = new Updraft_Manager_Updater_1_5('https://www.simbahosting.co.uk/s3', 1, 'woocommerce-printorders/print-orders.php');

#$openinghours_updater->updater->debug = true;
