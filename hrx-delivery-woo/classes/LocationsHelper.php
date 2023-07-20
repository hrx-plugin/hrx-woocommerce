<?php
namespace HrxDeliveryWoo;

// Prevent direct access to this script
if ( ! defined('ABSPATH') ) {
    exit;
}

class LocationsHelper
{
    public static function add_info_to_list_elems( $list, $add_info = array(), $allow_override = false )
    {
        $changed_list = array();
        foreach ( $list as $elem_key => $elem_data ) {
            $elem = (array)$elem_data;
            foreach ( $add_info as $info_key => $info_value ) {
                if ( ! $allow_override && isset($elem[$info_key]) ) {
                    continue;
                }
                $elem[$info_key] = $info_value;
            }
            $changed_list[$elem_key] = (object)$elem;
        }

        return $changed_list;
    }
}
