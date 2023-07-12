<?php
namespace HrxDeliveryWoo;

// Prevent direct access to this script
if ( ! defined('ABSPATH') ) {
    exit;
}

use HrxDeliveryWoo\Sql;
use HrxDeliveryWoo\Api;
use HrxDeliveryWoo\Helper;
use HrxDeliveryWoo\Core;

class Terminal
{
    public static function prepare_options( $terminals_list, $force_show_id = false )
    {
        $options = array();

        foreach ( $terminals_list as $terminal_data ) {
            if ( ! $terminal_data->active && $terminal_data->location_id != $force_show_id ) {
                continue;
            }
            $exploded_city = explode(', ', $terminal_data->city);
            
            $options[$exploded_city[0]][] = array(
                'id' => $terminal_data->location_id,
                'name' => self::build_name($terminal_data),
                'disabled' => (! $terminal_data->active) ? true : false,
            );
        }

        return self::sort_options($options);
    }

    public static function build_name( $terminal_data )
    {
        $name = (! empty($terminal_data->address)) ? $terminal_data->address : '—';
        $name .= ', ' . $terminal_data->city;
        if ( ! empty($terminal_data->postcode) ) {
            $name .= ', ' . $terminal_data->postcode;
        }
        $name .= ', ' . \WC()->countries->countries[$terminal_data->country];

        return $name;
    }

    public static function get_name_by_id( $terminal_id )
    {
        $fail_value = '—';
        $terminal_data = Sql::get_row('delivery', array('location_id' => $terminal_id));

        if ( empty($terminal_data) ) {
            return $fail_value;
        }

        return self::build_name($terminal_data);
    }

    public static function build_select_field( $params )
    {
        $terminals_list = $params['all_terminals'] ?? array();
        $selected_id = $params['selected_id'] ?? '';
        $field_name = $params['name'] ?? '';
        $field_id = $params['id'] ?? '';
        $field_class = $params['class'] ?? '';
        $field_disabled = $params['disabled'] ?? false;

        $terminals_options = self::prepare_options($terminals_list, $selected_id);
        $terminals_html = '<option>' . __('Select terminal', 'hrx-delivery') . '</option>';

        foreach ( $terminals_options as $options_group => $options ) {
            $terminals_html .= '<optgroup label="' . $options_group . '">';
            foreach ( $options as $terminal ) {
                $selected = ($terminal['id'] == $selected_id) ? 'selected' : '';
                $disabled = ($terminal['disabled']) ? 'disabled' : '';
                $terminals_html .= '<option value="' . esc_html($terminal['id']) . '" ' . $selected . ' ' . $disabled . '>' . esc_html($terminal['name']) . '</option>';
            }
            $terminals_html .= '</optgroup>';
        }

        $disabled = ($field_disabled) ? 'disabled' : '';
        $output = '<select name="' . esc_html($field_name) . '" id="' . esc_html($field_id) . '" class="' . esc_html($field_class) . '" ' . $disabled . '>' . $terminals_html . '</select>';

        return $output;
    }

    private static function sort_options( $options )
    {
        ksort($options);
        foreach ( $options as $group => $values ) {
            $sorted_values = $values;
            usort($sorted_values, function ($a, $b) {
                return $a['name'] > $b['name'];
            });
            $options[$group] = $sorted_values;
        }

        return $options;
    }

    public static function build_list_in_script( $params )
    {
        $method_key = $params['method'] ?? 'terminal';
        $country = $params['country'] ?? '';
        $terminals_list = $params['all_terminals'] ?? array();

        $terminals_script_data = self::prepare_script_data($terminals_list, $country);

        $output = '<script>';
        $output .= 'var hrx_' . $method_key . '_terminals = ' . json_encode($terminals_script_data) . ';';
        $output .= '</script>';

        return $output;
    }

    public static function prepare_script_data( $terminals_list, $country )
    {
        $core = Core::get_instance();
        $list = array();

        foreach ( $terminals_list as $terminal_data ) {
            $exploded_city = explode(', ', $terminal_data->city);
            $address = (! empty($terminal_data->address)) ? $terminal_data->address : '—';

            $list[] = array(
                'id' => $terminal_data->location_id,
                'name' => self::build_name($terminal_data),
                'city' => $exploded_city[0],
                'address' => $address,
                'coords' => array(
                    'lat' => $terminal_data->latitude,
                    'lng' => $terminal_data->longitude,
                ),
                'identifier' => $core->option_prefix . '_' . $country,
            );
        }

        return $list;
    }

    public static function get_list( $country )
    {
        trigger_error('Method ' . __METHOD__ . ' is deprecated', E_USER_DEPRECATED);
        
        return array();
    }

    public static function add_info_to_list_elems( $terminals_list, $add_info = array(), $allow_override = false )
    {
        trigger_error('Method ' . __METHOD__ . ' is deprecated', E_USER_DEPRECATED);
        
        return array();
    }

    public static function update_delivery_locations( $page )
    {
        trigger_error('Method ' . __METHOD__ . ' is deprecated', E_USER_DEPRECATED);
        
        return array(
            'status' => 'error',
            'msg' => __('Method ' . __METHOD__ . ' is deprecated', 'hrx-delivery'),
        );
    }
}
