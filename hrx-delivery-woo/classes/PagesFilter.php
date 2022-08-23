<?php
namespace HrxDeliveryWoo;

// Prevent direct access to this script
if ( ! defined('ABSPATH') ) {
    exit;
}

use HrxDeliveryWoo\Core;

class PagesFilter
{
    public static function get_current_values( $filters )
    {
        foreach ( $filters as $filter_key => $filter_value ) {
            if ( isset($_POST['filter_' . $filter_key]) && intval($_POST['filter_' . $filter_key]) !== -1 ) {
                $filters[$filter_key] = filter_input(INPUT_POST, 'filter_' . $filter_key);
            }
        }

        return $filters;
    }

    public static function remove_not_available_options( $filter_options, $available_options )
    {
        foreach ( $filter_options as $key => $text ) {
            if ( ! in_array($key, $available_options) ) {
                unset($filter_options[$key]);
            }
        }

        return $filter_options;
    }

    public static function change_args_by_filters( $args, $active_filters )
    {
        $core = Core::get_instance();

        if ( ! empty($active_filters['client']) ) {
            $args['customer_fullname'] = $active_filters['client'];
        }

        if ( ! empty($active_filters['status']) ) {
            $args['status'] = array($active_filters['status']);
        }

        if ( ! empty($active_filters['warehouse']) ) {
            $args[$core->meta_keys->warehouse_id] = array($active_filters['warehouse']);
        }

        if ( ! empty($active_filters['track_no']) ) {
            $args[$core->meta_keys->track_number] = array($active_filters['track_no']);
        }

        return $args;
    }

    public static function check_warehouse( $warehouse, $active_filters )
    {
        if ( ! empty($active_filters['id']) ) {
            if ( stripos($warehouse->location_id, $active_filters['id']) === false ) {
                return false;
            }
        }

        if ( ! empty($active_filters['name']) ) {
            if ( stripos($warehouse->name, $active_filters['name']) === false ) {
                return false;
            }
        }

        if ( ! empty($active_filters['country']) ) {
            if ( $warehouse->country != $active_filters['country'] ) {
                return false;
            }
        }

        if ( ! empty($active_filters['city']) ) {
            if ( stripos($warehouse->city, $active_filters['city']) === false ) {
                return false;
            }
        }

        if ( ! empty($active_filters['zip']) ) {
            if ( stripos($warehouse->postcode, $active_filters['zip']) === false ) {
                return false;
            }
        }

        if ( ! empty($active_filters['address']) ) {
            if ( stripos($warehouse->address, $active_filters['address']) === false ) {
                return false;
            }
        }

        return true;
    }
}
