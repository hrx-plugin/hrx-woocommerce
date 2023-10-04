<?php
namespace HrxDeliveryWoo;

// Prevent direct access to this script
if ( ! defined('ABSPATH') ) {
    exit;
}

use HrxDeliveryWoo\Core;
use HrxDeliveryWoo\WcTools;
use HrxDeliveryWoo\Debug;

class WcOrder
{
    public $_order;
    protected $_tools;

    public function __construct()
    {
        $this->_tools = new WcTools();
    }

    public function get_orders( $args )
    {
        return wc_get_orders($args);
    }

    public function get_order( $wc_order_id, $set_tmp = false )
    {
        if ( $this->_order ) {
            return $this->_order;
        }

        if ( empty($wc_order_id) ) {
            Debug::to_log('Got empty value when expected to get ID. ' . print_r(debug_backtrace(2, 3), true), 'wc-order');
            return false;
        }

        if ( is_object($wc_order_id) ) {
            Debug::to_log('Got object when expected to get ID. ' . print_r(debug_backtrace(2, 3), true), 'wc-order');
            return false;
        }

        $wc_order = wc_get_order($wc_order_id);
        if ( ! $wc_order ) {
            Debug::to_log('Failed to get order. ' . print_r(debug_backtrace(2, 3), true), 'wc-order');
            return false;
        }

        if ( $set_tmp ) {
            $this->set_tmp_order($wc_order);
        }

        return $wc_order;
    }

    public function set_tmp_order( $order = null )
    {
        $this->_order = $order;
        
        return $this;
    }

    public function load_order( $wc_order_id, $set_tmp = false )
    {
        if ( is_object($wc_order_id) ) {
            Debug::to_log('Got object when expected to get ID. ' . print_r(debug_backtrace(2, 3), true), 'wc-order');
            return false;
        }

        if ( $this->_order && $this->_order->get_id() == $wc_order_id ) {
            return $this->_order;
        }

        return $this->get_order($wc_order_id, $set_tmp);
    }

    public function get_items( $wc_order_id )
    {
        $all_items = array();

        $wc_order = $this->load_order($wc_order_id);
        if ( ! $wc_order ) {
            return $all_items;
        }

        foreach ( $wc_order->get_items() as $item_id => $item ) {
            $product = $item->get_product();
            $all_items[$item_id] = (object)array(
                'product_id' => (int)$item->get_product_id(),
                'quantity' => (int)$item->get_quantity(),
                'weight' => (!empty($product->get_weight())) ? (float)$product->get_weight() : 0,
                'length' => (!empty($product->get_length())) ? (float)$product->get_length() : 0,
                'width' => (!empty($product->get_width())) ? (float)$product->get_width() : 0,
                'height' => (!empty($product->get_height())) ? (float)$product->get_height() : 0,
                'title' => $item->get_name(),
                'sku' => $product->get_sku(),
                'price_product' => $product->get_price(),
                'price_total' => $item->get_total(),
            );
        }

        return $all_items;
    }

    public function get_shipping_methods( $wc_order_id )
    {
        $shipping_methods = array();

        $wc_order = $this->load_order($wc_order_id);
        if ( ! $wc_order ) {
            return $shipping_methods;
        }

        foreach ( $wc_order->get_items('shipping') as $item_id => $shipping_item_obj ) {
            $shipping_methods[] = $shipping_item_obj->get_method_id();
        }

        return $shipping_methods;
    }

    public function get_meta( $wc_order_id, $meta_key )
    {
        $wc_order = $this->load_order($wc_order_id);
        if ( ! $wc_order ) {
            return false;
        }

        return $wc_order->get_meta($meta_key, true);
    }

    public function update_meta( $wc_order_id, $meta_key, $value, $clean = false )
    {
        $wc_order = $this->load_order($wc_order_id);
        if ( ! $wc_order ) {
            return false;
        }

        if ( $clean ) {
            $value = $this->_tools->clean($value);
        }

        $wc_order->update_meta_data($meta_key, $value);
        $wc_order->save();

        return true;
    }

    public function delete_meta( $wc_order_id, $meta_key, $delete_value = '' )
    {
        $wc_order = $this->load_order($wc_order_id);
        if ( ! $wc_order ) {
            return false;
        }

        $wc_order->delete_meta_data($meta_key, $delete_value);
        $wc_order->save();

        return true;
    }

    private function get_hrx_data_keys()
    {
        $meta_keys = Core::get_instance()->meta_keys;

        return array(
            'method' => $meta_keys->method,
            'terminal_id' => $meta_keys->terminal_id,
            'warehouse_id' => $meta_keys->warehouse_id,
            'dimensions' => $meta_keys->dimensions,
            'hrx_order_id' => $meta_keys->order_id,
            'hrx_order_status' => $meta_keys->order_status,
            'track_number' => $meta_keys->track_number,
            'track_url' => $meta_keys->track_url,
            'error' => $meta_keys->error_msg,
        );
    }

    public function get_hrx_data( $wc_order_id )
    {
        $wc_order = $this->load_order($wc_order_id);
        if ( ! $wc_order ) {
            return false;
        }

        $data_keys = $this->get_hrx_data_keys();
        $data = array();
        foreach ( $data_keys as $key => $meta_key ) {
            $data[$key] = $wc_order->get_meta($meta_key);
        }

        return (object) $data;
    }

    public function update_hrx_data( $wc_order_id, $values = array() )
    {
        if ( ! is_array($values) ) {
            return false;
        }

        $wc_order = $this->load_order($wc_order_id);
        if ( ! $wc_order ) {
            return false;
        }

        $data_keys = $this->get_hrx_data_keys();
        foreach ( $values as $key => $value ) {
            if ( isset($data_keys[$key]) ) {
                $wc_order->update_meta_data($data_keys[$key], $value);
            }
        }
        $wc_order->save();

        return true;
    }

    public function add_note( $wc_order_id, $note )
    {
        $wc_order = $this->load_order($wc_order_id);
        if ( ! $wc_order ) {
            return false;
        }

        $wc_order->add_order_note($note);

        return true;
    }

    public function get_status( $wc_order_id )
    {
        $wc_order = $this->load_order($wc_order_id);
        if ( ! $wc_order ) {
            return false;
        }

        return $wc_order->get_status();
    }

    public function update_status( $wc_order_id, $status, $note_msg = '' )
    {
        if ( empty($status) ) {
            return false;
        }

        $wc_order = $this->load_order($wc_order_id);
        if ( ! $wc_order ) {
            return false;
        }

        $wc_order->update_status($status, $note_msg);
        $wc_order->save();

        return true;
    }

    public function count_total_weight( $wc_order_id, $weight_unit = 'kg' )
    {
        $total_weight = 0;
        $items = $this->get_items($wc_order_id);

        foreach ( $items as $item ) {
            $total_weight += floatval($item->weight * $item->quantity);
        }

        return $this->_tools->convert_weight($total_weight, $weight_unit);
    }
}
