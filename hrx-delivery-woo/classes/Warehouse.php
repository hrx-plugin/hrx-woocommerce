<?php
namespace HrxDeliveryWoo;

// Prevent direct access to this script
if ( ! defined('ABSPATH') ) {
    exit;
}

use HrxDeliveryWoo\Sql;
use HrxDeliveryWoo\Helper;
use HrxDeliveryWoo\Api;

class Warehouse
{
    public static function get_list()
    {    
        return Sql::get_multi_rows('pickup', array());
    }

    public static function get_name_by_id( $warehouse_id )
    {
        $fail_value = 'ID: ' . $warehouse_id;
        $warehouse_data = Sql::get_row('pickup', array('location_id' => $warehouse_id));

        if ( empty($warehouse_data) ) {
            return $fail_value;
        }

        return $warehouse_data->name;
    }

    public static function get_default_id()
    {
        return Helper::get_hrx_option('default_warehouse', '');
    }

    public static function set_default_id( $warehouse_id )
    {
        Helper::update_hrx_option('default_warehouse', $warehouse_id);
    }

    public static function build_select_field( $params )
    {
        $warehouses_list = $params['all_warehouses'] ?? array();
        $selected_id = $params['selected_id'] ?? '';
        $field_name = $params['name'] ?? '';
        $field_id = $params['id'] ?? '';
        $field_class = $params['class'] ?? '';
        $field_disabled = $params['disabled'] ?? false;

        $warehouses_options = self::prepare_options($warehouses_list, $selected_id);
        $warehouses_html = '';

        foreach ( $warehouses_options as $warehouse ) {
            $selected = ($warehouse['id'] == $selected_id) ? 'selected' : '';
            $disabled = ($warehouse['disabled']) ? 'disabled' : '';
            $warehouses_html .= '<option value="' . esc_html($warehouse['id']) . '" ' . $selected . ' ' . $disabled .'>' . esc_html($warehouse['name']) . '</option>';
        }

        $disabled = ($field_disabled) ? 'disabled' : '';
        $output = '<select name="' . esc_html($field_name) . '" id="' . esc_html($field_id) . '" class="' . esc_html($field_class) . '" ' . $disabled . '>' . $warehouses_html . '</select>';

        return $output;
    }

    public static function prepare_options( $warehouses_list, $force_show_id = false )
    {
        $options = array();

        foreach ( $warehouses_list as $warehouse_data ) {
            if ( ! $warehouse_data->active && $warehouse_data->location_id != $force_show_id ) {
                continue;
            }
            $options[$warehouse_data->id] = array(
                'id' => $warehouse_data->location_id,
                'name' => $warehouse_data->name,
                'disabled' => (! $warehouse_data->active) ? true : false,
            );
        }

        usort($options, function ($a, $b) {
            return $a['name'] > $b['name'];
        });

        return $options;
    }

    public static function update_pickup_locations()
    {
        Sql::update_multi_rows('pickup', array('active' => 0), array());

        try {
            $result = self::save_pickup_locations(1);
        } catch (\Exception $e) {
            $result = array(
                'status' => 'error',
                'msg' => __('An unexpected error occurred during the operation', 'hrx-delivery'),
            );
        }

        if ( $result['status'] == 'OK' ) {
            $current_time = current_time("Y-m-d H:i:s");
            Helper::update_hrx_option('last_sync_pickup_loc', $current_time);
        }

        return $result;
    }

    private static function save_pickup_locations($page, $protector = 1)
    {
        $api = new Api();
        $response = $api->get_pickup_locations($page, 250);
        $status = array(
            'status' => 'OK',
            'total' => 0,
            'added' => 0,
            'updated' => 0,
            'failed' => 0,
            'msg' => '',
        );
        $max_cycles = 200;

        if ( $protector > $max_cycles ) {
            return array(
                'status' => 'error',
                'msg' => __('Reached maximum number of cycles', 'hrx-delivery') . ' (' . $max_cycles . ')',
            );
        }

        if ( $response['status'] != 'error' ) {
            $count_all = 0;
            $count_error = 0;
            $count_added = 0;
            $count_updated = 0;

            foreach ( $response['data'] as $location_data ) {
                $sql_data = array(
                    'name' => $location_data['name'],
                    'country' => $location_data['country'],
                    'address' => $location_data['address'],
                    'city' => $location_data['city'],
                    'postcode' => $location_data['zip'],
                    'active' => 1,
                );
                if ( ! empty(Sql::get_row('pickup', array('location_id' => $location_data['id']))) ) {
                    $result = Sql::update_row('pickup', $sql_data, array('location_id' => $location_data['id']) );
                    $count_updated++;
                } else {
                    $sql_data['location_id'] = $location_data['id'];
                    $result = Sql::insert_row('pickup', $sql_data);
                    $count_added++;
                }

                if ( $result === false ) {
                    $count_error++;
                }
                
                $count_all++;
            }

            if ( count($response['data']) == 250 ) {
                $next_page = self::save_pickup_locations($page + 1, $protector + 1);
                if ( $next_page['status'] == 'error' ) {
                    $status['status'] = 'error';
                    $status['msg'] = $next_page['msg'];
                } else {
                    $count_all += $next_page['total'];
                    $count_added += $next_page['added'];
                    $count_updated += $next_page['updated'];
                    $count_error += $next_page['failed'];
                }
            }

            $status['total'] = $count_all;
            $status['added'] = $count_added;
            $status['updated'] = $count_updated;
            $status['failed'] = $count_error;

            return $status;
        }

        return array(
            'status' => 'error',
            'msg' => __('Request error', 'hrx-delivery') . ' - ' . $response['msg']
        );
    }
}
