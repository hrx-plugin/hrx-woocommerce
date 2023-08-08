<?php
namespace HrxDeliveryWoo;

// Prevent direct access to this script
if ( ! defined('ABSPATH') ) {
    exit;
}

use HrxDeliveryWoo\Core;
use HrxDeliveryWoo\Pages;

class WcTools
{
    public function get_all_statuses()
    {
        return wc_get_order_statuses();
    }

    public function get_status_title( $status )
    {
        return wc_get_order_status_name($status);
    }

    public function get_units()
    {
        return (object) array(
            'weight' => get_option('woocommerce_weight_unit'),
            'dimension' => get_option('woocommerce_dimension_unit'),
            'currency' => get_option('woocommerce_currency'),
            'currency_symbol' => get_woocommerce_currency_symbol(),
        );
    }

    public function get_price_html( $price, $args )
    {
        return wc_price($price, $args);
    }

    public function get_price_decimal_separator()
    {
        $separator = wc_get_price_decimal_separator();
        
        return (! empty($separator)) ? $separator : '.';
    }

    public function clean( $value )
    {
        return wc_clean($value);
    }

    public function convert_weight( $value, $to_unit, $from_unit = '' )
    {
        return wc_get_weight($value, $to_unit, $from_unit);
    }

    public function get_current_screen_id()
    {
        $screen = get_current_screen();

        return $screen->id ?? false;
    }

    public function get_all_screen_ids( $group_key )
    {
        if ( empty($group_key) ) {
            return false;
        }
        $classPages = new Pages();
        
        $all_screen_ids = array(
            'admin_order_edit' => array('shop_order', 'woocommerce_page_wc-orders'),
            'admin_hrx_pages' => array(),
        );

        foreach ( $classPages->get_subpages('', true) as $section => $pages ) {
            foreach ( $pages as $page_key => $page ) {
                $all_screen_ids['admin_hrx_pages'][] = 'woocommerce_page_' . $classPages->build_page_id($page_key);
            }
        }

        return $all_screen_ids[$group_key] ?? false;
    }

    public function is_available_screen( $group_key, $current_screen_id = '' )
    {
        if ( empty($current_screen_id) ) {
            $current_screen_id = $this->get_current_screen_id();
        }

        $allowed_ids = $this->get_all_screen_ids($group_key);
        if ( empty($allowed_ids) ) {
            return false;
        }

        return (in_array($current_screen_id, $allowed_ids));
    }

    public function get_all_countries()
    {
        $countries = new \WC_Countries();

        return $countries->get_countries();
    }

    public function get_country_name( $country_code )
    {
        $countries = \WC()->countries->countries;
        return $countries[$country_code] ?? $country_code;
    }

    public function set_session( $id, $value, $use_prefix = true )
    {
        \WC()->session->set($this->session_prefix($use_prefix) . $id, $value);
    }

    public function get_session( $id, $use_prefix = true )
    {
        return \WC()->session->get($this->session_prefix($use_prefix) . $id);
    }

    private function session_prefix( $use_prefix = true )
    {
        if ( $use_prefix ) {
            return Core::get_instance()->id . '_';
        }

        return '';
    }

    public function add_notice( $message, $message_type = 'notice' )
    {
        if ( wc_has_notice($message, $message_type) ) {
            return;
        }

        wc_add_notice($message, $message_type);
    }
}
