<?php
namespace HrxDeliveryWoo;

// Prevent direct access to this script
if ( ! defined('ABSPATH') ) {
    exit;
}

use HrxDeliveryWoo\Core;
use HrxDeliveryWoo\Sql;
use HrxDeliveryWoo\Api;
use HrxDeliveryWoo\Helper;

class LocationsDelivery
{
    public static function get_option_name( $option_key )
    {
        $all_options = array(
            'last_sync_terminal' => 'last_sync_delivery_loc_terminal',
            'last_sync_courier' => 'last_sync_delivery_loc_courier',
        );

        return $all_options[$option_key] ?? $option_key;
    }

    public static function get_methods()
    {
        $core = Core::get_instance();
        $methods = array();
        
        foreach ( $core->methods as $method_key => $method_data ) {
            $methods[] = $method_key;
        }

        return $methods;
    }

    public static function get_list( $type, $country )
    {
        if ( empty($country) ) {
            $country = 'LT';
        }
        
        $params = array('country' => $country);
        
        if ( ! empty($type) ) {
            $params['type'] = $type;
        }
        
        return Sql::get_multi_rows('delivery', $params);
    }

    public static function update( $page )
    {
        $type = 'terminal';
        if ( $page === false || $page === 1 ) {
            Sql::update_multi_rows('delivery', array('active' => 0), array('type' => $type));
            //Helper::delete_hrx_option('countries'); //Temporary fix (this line moved out from if). Problem: When executing this function, some subfunctions call Core, where the methods element is created. This results in a large number of reads, deletions and additions of records in the database. Need to fix or somehow cache data, while this function executing.
        }
        Helper::delete_hrx_option('countries');

        try {
            $start_page = ($page === false ) ? 1 : $page;
            $result = self::save_delivery_locations($type, $start_page, ($page === false));
        } catch (\Exception $e) {
            $result = array(
                'status' => 'error',
                'msg' => __('An unexpected error occurred during the operation', 'hrx-delivery'),
            );
        }

        if ( $result['status'] == 'OK' ) {
            $current_time = current_time("Y-m-d H:i:s");
            Helper::update_hrx_option(self::get_option_name('last_sync_' . $type), $current_time);
        }

        return $result;
    }

    private static function save_delivery_locations( $type, $page, $self_repeat = false, $protector = 1 )
    {
        $api = new Api();
        $response = $api->get_delivery_locations($page, 250);
        $status = array(
            'status' => 'OK',
            'total' => 0,
            'added' => 0,
            'updated' => 0,
            'failed' => 0,
            'msg' => '',
        );
        $max_cycles = 1000;

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
                if ( $location_data['latitude'] == '0.0' && $location_data['longitude'] == '0.0' ) {
                    continue;
                }
                $sql_data = array(
                    'type' => $type,
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

            if ( $self_repeat && count($response['data']) >= 250 ) {
                sleep(1);
                $next_page = self::save_delivery_locations($type, $page + 1, $self_repeat, $protector + 1);
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

    public static function update_couriers()
    {
        $type = 'courier';

        Sql::update_multi_rows('delivery', array('active' => 0), array('type' => $type));
        Helper::delete_hrx_option('countries');

        try {
            $result = self::save_delivery_locations_couriers($type);
        } catch (\Exception $e) {
            $result = array(
                'status' => 'error',
                'msg' => __('An unexpected error occurred during the operation', 'hrx-delivery'),
            );
        }

        if ( $result['status'] == 'OK' ) {
            $current_time = current_time("Y-m-d H:i:s");
            Helper::update_hrx_option(self::get_option_name('last_sync_' . $type), $current_time);
        }

        return $result;
    }

    private static function save_delivery_locations_couriers( $type, $protector = 1 )
    {
        $api = new Api();
        $response = $api->get_courier_delivery_locations();
        $status = array(
            'status' => 'OK',
            'total' => 0,
            'added' => 0,
            'updated' => 0,
            'failed' => 0,
            'msg' => '',
        );
        $max_cycles = 1000;

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
                    'type' => $type,
                    'country' => $location_data['country'],
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
                $id = $type . '-' . $location_data['country'];
                if ( ! empty(Sql::get_row('delivery', array('location_id' => $id))) ) {
                    $result = Sql::update_row('delivery', $sql_data, array('location_id' => $id) );
                    $count_updated++;
                } else {
                    $sql_data['location_id'] = $id;
                    $result = Sql::insert_row('delivery', $sql_data);
                    $count_added++;
                }

                if ( $result === false ) {
                    $count_error++;
                }
                
                $count_all++;
            }

            if ( $self_repeat && count($response['data']) >= 250 ) {
                sleep(1);
                $next_page = self::save_delivery_locations_couriers($type, $protector + 1);
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
