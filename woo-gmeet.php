<?php

/*
Plugin Name: WooCommerce Google Meet Integration
Description: Integrates Google Meet with WooCommerce for creating and managing meetings.
Version: 1.0
Author: lucaf
License: GPL2
Requires Plugins: woocommerce
*/

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly.
}

define('WGM_PATH', plugin_dir_path(__FILE__));
define('WGM_URL', plugin_dir_url(__FILE__));

require_once WGM_PATH . 'vendor/autoload.php';
require_once WGM_PATH . 'includes/Settings.php';
require_once WGM_PATH . 'includes/GoogleClient.php';
require_once WGM_PATH . 'includes/Availability.php';
require_once WGM_PATH . 'includes/Checkout.php';

add_action('plugins_loaded', function () {
    \WGM\Settings::init();
    \WGM\GoogleClient::init();
    \WGM\Availability::init();
    \WGM\Checkout::init();
});