<?php
namespace HrxDeliveryWoo;

// Prevent direct access to this script
if ( ! defined('ABSPATH') ) {
    exit;
}

use HrxDeliveryWoo\Helper;
use HrxDeliveryWoo\Core;
use HrxDeliveryWoo\Ajax;
use HrxDeliveryWoo\Pages;
use HrxDeliveryWoo\Order;
use HrxDeliveryWoo\Cronjob;

class Main
{
    private $core;

    public function __construct( $hrx_params )
    {
        $this->core = new Core($hrx_params);
        if ( ! $this->core->loaded ) {
            return false;
        }
        
        $this->load_init_hooks();
        $this->load_woo_hooks();

        //Helper::develop_debug($this->core->plugin->structure->dirname);
    }

    public function load_text_domain()
    {
        $plugin_information = Helper::get_plugin_information($this->core->structure->path . $this->core->structure->filename);

        load_plugin_textdomain($plugin_information['TextDomain'], false, $this->core->structure->dirname . $plugin_information['DomainPath'] );
    }

    public function add_link_to_settings( $links )
    {
        array_unshift($links, '<a href="' . admin_url( 'admin.php?page=wc-settings&tab=shipping&section=' . $this->core->id ) . '">' . __('Settings', 'hrx-delivery') . '</a>');
        return $links;
    }

    public function load_front_scripts()
    {
        $css_dir = $this->core->structure->url . $this->core->structure->css . 'front/';
        $img_dir = $this->core->structure->url . $this->core->structure->img;
        $js_dir = $this->core->structure->url . $this->core->structure->js . 'front/';

        wp_enqueue_script($this->core->id . '_front_ajax', $js_dir . 'ajax.js', array('jquery'), $this->core->version);
        wp_enqueue_script($this->core->id . '_front_global', $js_dir . 'global.js', array('jquery'), $this->core->version);
        wp_localize_script($this->core->id . '_front_global', 'hrxGlobalVars', array(
            'ajax_url' => admin_url('admin-ajax.php'),
            'img_url' => $img_dir,
            'txt' => array(
                'mapModal' => array(
                    'modal_header' => __('Terminal map', 'hrx-delivery'),
                    'terminal_list_header' => __('Terminal list', 'hrx-delivery'),
                    'seach_header' => __('Search around', 'hrx-delivery'),
                    'search_btn' => __('Find', 'hrx-delivery'),
                    'modal_open_btn' => __('Choose terminal', 'hrx-delivery'),
                    'geolocation_btn' => __('Use my location', 'hrx-delivery'),
                    'your_position' => __('Distance calculated from this point', 'hrx-delivery'),
                    'nothing_found' => __('Nothing found', 'hrx-delivery'),
                    'no_cities_found' => __('There were no cities found for your search term', 'hrx-delivery'),
                    'geolocation_not_supported' => __('Geolocation is not supported', 'hrx-delivery'),
                    'select_pickup_point' => __('Choose terminal', 'hrx-delivery'),
                    'search_placeholder' => __('Type address', 'hrx-delivery'),
                    'workhours_header' => __('Workhours', 'hrx-delivery'),
                    'contacts_header' => __('Contacts', 'hrx-delivery'),
                    'no_pickup_points' => __('No terminal to select', 'hrx-delivery'),
                    'select_btn' => __('Choose terminal', 'hrx-delivery'),
                    'back_to_list_btn' => __('Reset search', 'hrx-delivery'),
                    'no_information' => __('No information', 'hrx-delivery'),
                ),
            ),
        ));

        if ( is_cart() || is_checkout() ) {
            wp_enqueue_style($this->core->id . '_front_map', $css_dir . 'terminal-mapping.css', array(), $this->core->version);
            wp_enqueue_style($this->core->id . '_front_checkout', $css_dir . 'checkout.css', array(), $this->core->version);
            
            wp_enqueue_script($this->core->id . '_front_map', $js_dir . 'terminal-mapping.js', array('jquery'), $this->core->version);
            wp_enqueue_script($this->core->id . '_front_checkout', $js_dir . 'checkout.js', array('jquery'), $this->core->version);
            wp_enqueue_script($this->core->id . '_front_map_init', $js_dir . 'init-map.js', array('jquery'), $this->core->version);
        }
    }

    public function load_admin_scripts( $hook )
    {
        $css_dir = $this->core->structure->url . $this->core->structure->css . 'admin/';
        $js_dir = $this->core->structure->url . $this->core->structure->js . 'admin/';

        wp_enqueue_style($this->core->id . '_switcher', $css_dir . 'switcher.css', array(), $this->core->version);
        wp_enqueue_style($this->core->id . '_elements', $css_dir . 'elements.css', array(), $this->core->version);

        wp_enqueue_script($this->core->id . '_admin_global', $js_dir . 'global.js', array('jquery'), $this->core->version);
        wp_localize_script($this->core->id . '_admin_global', 'hrxGlobalVars', array(
            'ajax_url' => admin_url('admin-ajax.php'),
            'txt' => array(
                'request_error' => __('Request error', 'hrx-delivery'),
                'warehouse_change_error' => __('An error occurred while changing default warehouse', 'hrx-delivery'),
                'table_action_error' => __('An error occurred while executing action', 'hrx-delivery'),
                'alert_retry' => __('Do you want to try again?', 'hrx-delivery'),
                'label_download_fail' => __('The label was received but could not be opened', 'hrx-delivery'),
                'file_download_fail' => __('The file was received but could not be opened', 'hrx-delivery'),
                'reload_confirmation' => __('Do you want to reload the page to update the displayed data?', 'hrx-delivery'),
                'orders_not_selected' => __('You must choose at least one order', 'hrx-delivery'),
            ),
        ));

        if ( $hook == 'woocommerce_page_wc-settings' && isset($_GET['section']) && $_GET['section'] == $this->core->id ) {
            wp_enqueue_style($this->core->id . '_admin_settings', $css_dir . 'settings.css', array(), $this->core->version);

            wp_enqueue_script($this->core->id . '_admin_settings', $js_dir . 'settings.js', array('jquery'), $this->core->version);
        }
    }

    public function load_init_hooks()
    {
        add_action('init', array($this, 'load_text_domain'));

        add_filter('plugin_action_links_' . $this->core->structure->basename, array($this, 'add_link_to_settings'));

        Ajax::init();
    }

    public function load_woo_hooks()
    {
        if ( ! Helper::check_woocommerce() ) {
            return; // Skip hooks loading if WooCommerce not actived
        }

        add_action('wp_enqueue_scripts', array($this, 'load_front_scripts'));
        add_action('admin_enqueue_scripts', array($this, 'load_admin_scripts'));

        add_filter('woocommerce_shipping_methods', array($this, 'shipping_method_register'));

        $this->init_plugin_classes();
    }

    public function shipping_method_register( $methods )
    {
        $methods[$this->core->id] = 'HrxDeliveryWoo\ShippingMethod';
        return $methods;
    }

    private function init_plugin_classes()
    {
        $pages = new Pages();
        $pages->init();

        $front = new Front();
        $front->init();

        $order = new Order();
        $order->init();

        $cronjob = new Cronjob();
        $cronjob->init();
    }
}
