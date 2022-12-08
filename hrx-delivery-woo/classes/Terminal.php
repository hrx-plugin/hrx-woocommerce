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
    public static function get_list( $country )
    {
        if ( empty($country) ) {
            $country = 'LT';
        }
        
        return Sql::get_multi_rows('delivery', array('country' => $country));
    }

    public static function add_info_to_list_elems( $terminals_list, $add_info = array(), $allow_override = false )
    {
        $changed_list = array();
        foreach ( $terminals_list as $elem_key => $elem_data ) {
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

    public static function update_delivery_locations()
    {
        Sql::update_multi_rows('delivery', array('active' => 0), array());

        try {
            $result = self::save_delivery_locations(1);
        } catch (\Exception $e) {
            $result = array(
                'status' => 'error',
                'msg' => __('An unexpected error occurred during the operation', 'hrx-delivery'),
            );
        }

        if ( $result['status'] == 'OK' ) {
            $current_time = current_time("Y-m-d H:i:s");
            Helper::update_hrx_option('last_sync_delivery_loc', $current_time);
        }

        return $result;
    }

    private static function save_delivery_locations( $page, $protector = 1)
    {
        $api = new Api();
        $response = $api->get_delivery_locations($page, 250);
        $available_countries = Helper::get_available_countries();
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
                if ( ! in_array($location_data['country'], $available_countries) ) {
                    continue;
                }
                if ( $location_data['latitude'] == '0.0' && $location_data['longitude'] == '0.0' ) {
                    continue;
                }
                $sql_data = array(
                    'country' => $location_data['country'],
                    'address' => $location_data['address'],
                    'city' => $location_data['city'],
                    'postcode' => $location_data['zip'],
                    'latitude' => $location_data['latitude'],
                    'longitude' => $location_data['longitude'],
                    'active' => 1,
                    'params' => json_encode(array(
                        'min_length' => $location_data['min_length_cm'] ?? '',
                        'min_width' => $location_data['min_width_cm'] ?? '',
                        'min_height' => $location_data['min_height_cm'] ?? '',
                        'min_weight' => $location_data['min_weight_kg'] ?? '',
                        'max_length' => $location_data['max_length_cm'] ?? '',
                        'max_width' => $location_data['max_width_cm'] ?? '',
                        'max_height' => $location_data['max_height_cm'] ?? '',
                        'max_weight' => $location_data['max_weight_kg'] ?? '',
                        'phone_prefix' => $location_data['recipient_phone_prefix'] ?? '',
                        'phone_regexp' => $location_data['recipient_phone_regexp'] ?? '',
                    )),
                );
                if ( ! empty(Sql::get_row('delivery', array('location_id' => $location_data['id']))) ) {
                    $result = Sql::update_row('delivery', $sql_data, array('location_id' => $location_data['id']) );
                    $count_updated++;
                } else {
                    $sql_data['location_id'] = $location_data['id'];
                    $result = Sql::insert_row('delivery', $sql_data);
                    $count_added++;
                }

                if ( $result === false ) {
                    $count_error++;
                }
                
                $count_all++;
            }

            if ( count($response['data']) == 250 ) {
                $next_page = self::save_delivery_locations($page + 1, $protector + 1);
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
