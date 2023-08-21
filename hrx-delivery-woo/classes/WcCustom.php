<?php
namespace HrxDeliveryWoo;

// Prevent direct access to this script
if ( ! defined('ABSPATH') ) {
    exit;
}

use HrxDeliveryWoo\WcOrder;
use HrxDeliveryWoo\WcTools;

class WcCustom
{
    private $_order;

    public function load_order( $wc_order_id )
    {
        $order = $wc_order_id;
        if ( ! is_object($wc_order_id) ) {
            $wcOrder = new WcOrder();
            $order = $wcOrder->get_order($wc_order_id);
        }

        if ( empty($order) ) {
            return $this;
        }
        
        $this->_order = $order;

        return $this;
    }

    private function get_order( $wc_order )
    {
        if ( ! $wc_order ) {
            if ( empty($this->_order) ) {
                return false;
            }
            return $this->_order;
        }

        return $wc_order;
    }

    public function get_billing_fullname( $wc_order = false )
    {
        $wc_order = $this->get_order($wc_order);
        if ( ! $wc_order ) {
            return '';
        }

        return $wc_order->get_billing_first_name() . ' ' . $wc_order->get_billing_last_name();
    }

    public function get_shipping_fullname( $wc_order = false )
    {
        $wc_order = $this->get_order($wc_order);
        if ( ! $wc_order ) {
            return '';
        }

        return $wc_order->get_shipping_first_name() . ' ' . $wc_order->get_shipping_last_name();
    }

    public function get_customer_fullname( $wc_order = false )
    {
        $fullname = $this->get_shipping_fullname($wc_order);
        if ( empty(str_replace(' ', '', $fullname)) ) {
            $fullname = $this->get_billing_fullname($wc_order);
        }

        return $fullname;
    }

    public function get_order_country( $wc_order = false, $default = 'LT' )
    {
        $wc_order = $this->get_order($wc_order);
        if ( ! $wc_order ) {
            return $default;
        }

        $country = $wc_order->get_shipping_country();
        if ( empty($country) ) {
            $country = $wc_order->get_billing_country();
        }

        return (! empty($country)) ? $country : $default;
    }

    public function get_order_address( $wc_order = false )
    {
        $wc_order = $this->get_order($wc_order);
        if ( ! $wc_order ) {
            return false;
        }

        $address = $wc_order->get_address('shipping');
        if ( empty($address['address_1']) && empty($address['city']) && empty($address['postcode']) ) {
            $address = $wc_order->get_address();
        }

        if ( empty($address['phone']) && ! empty($wc_order->get_billing_phone()) ) {
            $address['phone'] = $wc_order->get_billing_phone();
        }

        return $address;
    }

    public function get_formated_status( $wc_order = false )
    {
        $wc_order = $this->get_order($wc_order);
        if ( ! $wc_order ) {
            return false;
        }

        $wcTools = new WcTools();

        $order_status = $wc_order->get_status();
        $order_status_name = $wcTools->get_status_title($order_status);

        return '<mark class="order-status status-' . $order_status . '"><span>' . $order_status_name . '</span></mark>';
    }

    public function convert_all_dimensions( $dimensions, $to_unit_weight, $to_unit_dimension )
    {
        if ( ! is_array($dimensions) ) {
            return false;
        }
        
        $wcTools = new WcTools();

        foreach ( $dimensions as $dim_key => $dim_value ) {
            if ( $dim_key == 'weight' ) {
                $dimensions[$dim_key] = $wcTools->convert_weight($dimensions[$dim_key], $to_unit_weight);
            } else {
                $dimensions[$dim_key] = $wcTools->convert_dimension($dimensions[$dim_key], $to_unit_dimension);
            }
        }

        return $dimensions;
    }
}
