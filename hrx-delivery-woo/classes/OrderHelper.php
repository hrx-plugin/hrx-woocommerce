<?php
namespace HrxDeliveryWoo;

// Prevent direct access to this script
if ( ! defined('ABSPATH') ) {
    exit;
}

class OrderHelper
{
    public static function is_order_page()
    {
        global $post_type;

        if ( 'shop_order' == $post_type ) {
            return true;
        }

        return false;
    }

    public static function get_status( $order )
    {
        return $order->get_status();
    }
}
