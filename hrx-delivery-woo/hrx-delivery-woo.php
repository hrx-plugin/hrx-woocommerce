<?php
/**
 * Plugin Name: HRX delivery
 * Description: The official HRX delivery plugin for WooCommerce
 * Author: Mijora
 * Author URI: https://www.mijora.dev/
 * Plugin URI: https://www.hrx.eu
 * Version: 1.1.1
 * 
 * Domain Path: /languages
 * Text Domain: hrx-delivery
 * 
 * Requires at least: 5.1
 * Tested up to: 6.1.1
 * WC requires at least: 5.0.0
 * WC tested up to: 7.1.1
 * Requires PHP: 7.2
 */

// Prevent direct access to this script
if ( ! defined('ABSPATH') ) {
  exit;
}

require 'vendor/autoload.php';

use HrxDeliveryWoo\Main;
use HrxDeliveryWoo\Helper;

/* Version */
$hrx_params = array(
    'version' => '1.1.1',
    'custom_changes' => array(), // If plugin have custom changes, add changes descriptions to this array
);

/* Shipping methods */
$hrx_params['methods'] = array(
    'courier' => array(
        'title' => __('Courier', 'hrx-delivery'),
        'front_title' => _x('HRX courier', 'Shipping method title in checkout', 'hrx-delivery'),
    ),
    'terminal' => array(
        'title' => __('Parcel terminal', 'hrx-delivery'),
        'front_title' => _x('HRX parcel terminal', 'Shipping method title in checkout', 'hrx-delivery'),
        'has_terminals' => true,
    ),
);

/* Identification */
$hrx_params['title'] = __('HRX delivery', 'hrx-delivery');
$hrx_params['id'] = 'hrx_delivery';
$hrx_params['option_prefix'] = 'hrx';

/* Update */
$hrx_params['update'] = array(
    'check_url' => 'https://api.github.com/repos/hrx-plugin/hrx-woocommerce/releases/latest',
    'download_url' => 'https://github.com/hrx-plugin/hrx-woocommerce/releases/latest/download/hrx-delivery-woo.zip',
);

/* Init */
if (Helper::check_woocommerce()) {
    $hrx_params['main_file'] = __FILE__;
    new Main($hrx_params);
} else {
    Helper::show_admin_message(sprintf(
        __('For the %1$s plugin to work, must have the following plugins activated: %2$s.', 'hrx-delivery'),
        '<b>' . $hrx_params['title'] . '</b>',
        '<b>WooCommerce</b>'
    ), 'error');
}
