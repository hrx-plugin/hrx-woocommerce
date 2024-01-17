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
use HrxDeliveryWoo\Debug;

class LocationsDelivery
{
    public static $download_per_page = 10000;
    public static $save_per_page = 5000;

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
            Sql::clear_table('delivery_temp');
        }

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

        Helper::delete_hrx_option('countries');
        
        return $result;
    }

    private static function save_delivery_locations( $type, $page, $self_repeat = false, $protector = 1 )
    {
        $max_cycles = 1000;

        $debug = Debug::is_enabled();
        $debug_file = 'locations_update_terminals';

        if ($debug) Debug::to_log('Executing update (page ' . $page . ')...', $debug_file);

        if ( $protector > $max_cycles ) {
            if ($debug) Debug::to_log('Max number of cycles (' . $protector . ' > ' . $max_cycles . ')', $debug_file);
            return array(
                'status' => 'error',
                'msg' => __('Reached maximum number of cycles', 'hrx-delivery') . ' (' . $max_cycles . ')',
            );
        }

        $api = new Api();
        $response = $api->get_delivery_locations($page, self::$download_per_page);
        $status = array(
            'status' => 'OK',
            'total' => 0,
            'failed' => 0,
            'msg' => '',
        );

        if ( $response['status'] != 'error' ) {
            $count_all = 0;
            $count_error = 0;
            foreach ( $response['data'] as $location_data ) {
                if ( $location_data['latitude'] == '0.0' && $location_data['longitude'] == '0.0' ) {
                    $count_error++;
                    $count_all++;
                    continue;
                }
                $sql_data = array(
                    'location_id' => $location_data['id'],
                    'type' => $type,
                    'country' => $location_data['country'],
                    'address' => $location_data['address'],
                    'city' => $location_data['city'],
                    'postcode' => $location_data['zip'],
                    'latitude' => $location_data['latitude'],
                    'longitude' => $location_data['longitude'],
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

                $result = Sql::insert_row('delivery_temp', $sql_data);
                if ( $result === false ) {
                    $count_error++;
                }
                
                $count_all++;
            }

            if ($debug) Debug::to_log(sprintf('Completed. Total: %1$s, Error: %2$s.', $count_all, $count_error), $debug_file);

            if ( $self_repeat && count($response['data']) >= self::$download_per_page ) {
                $response = NULL; // Frees up memory as this variable will no longer be needed (fixed memory leak)
                sleep(1);
                $next_page = self::save_delivery_locations($type, $page + 1, $self_repeat, $protector + 1);
                if ( $next_page['status'] == 'error' ) {
                    $status['status'] = 'error';
                    $status['msg'] = $next_page['msg'];
                } else {
                    $count_all += $next_page['total'];
                    $count_error += $next_page['failed'];
                }
            }

            $status['total'] = $count_all;
            $status['failed'] = $count_error;

            return $status;
        }

        if ($debug) {
            Debug::to_log('Failed. Error: ' . $response['msg'] . PHP_EOL . 'Response: ' . PHP_EOL . print_r($response['debug'], true), $debug_file);
        }
        return array(
            'status' => 'error',
            'msg' => $response['msg']
        );
    }

    public static function calc_downloaded_locations( $type )
    {
        $temp_locations_total = Sql::get_row('delivery_temp', array('type' => $type), 'COUNT(*) as total');
        if ( empty($temp_locations_total) || ! $temp_locations_total->total ) {
            return 0;
        }

        return (int) $temp_locations_total->total;
    }

    public static function prepare_locations_save( $type )
    {
        $debug = Debug::is_enabled();
        $debug_file = 'locations_update_terminals';

        if ($debug) Debug::to_log('Calculating downloaded locations...', $debug_file);
        $total_locations = self::calc_downloaded_locations($type);
        if ( ! $total_locations ) {
            if ($debug) Debug::to_log('Failed to get records from temporary table', $debug_file);
            return array(
                'status' => 'error',
                'msg' => __('Failed to save received locations', 'hrx-delivery')
            );
        }
        if ($debug) Debug::to_log('Total: ' . $total_locations, $debug_file);

        if ($debug) Debug::to_log('Disabling active locations...', $debug_file);
        Sql::update_multi_rows('delivery', array('active' => 0), array('type' => $type));

        return array(
            'status' => 'OK',
            'msg' => '',
            'total' => $temp_locations_total->total,
        );
    }

    public static function save_downloaded_locations( $type, $page )
    {
        $debug = Debug::is_enabled();
        $debug_file = 'locations_update_terminals';

        if ($debug) Debug::to_log('Copying locations from temporary table (page ' . $page . ')...', $debug_file);

        $result = self::move_locations_to_active($type, ($page - 1) * self::$save_per_page, self::$save_per_page);

        $count_added = $result['added'];
        $count_updated = $result['updated'];

        if ( $count_added + $count_updated == 0 ) {
            return array(
                'status' => 'error',
                'msg' => __('Failed to save received locations', 'hrx-delivery'),
                'added' => $count_added,
                'updated' => $count_updated,
            );
        }

        if ($debug) Debug::to_log('Locations successfully saved. Added ' . $count_added . ', updated ' . $count_updated . '.', $debug_file);

        return array(
            'status' => 'OK',
            'msg' => '',
            'added' => $count_added,
            'updated' => $count_updated,
        );
    }

    public static function update_active_locations()
    {
        $type = 'terminal';
        Sql::update_multi_rows('delivery', array('active' => 0), array('type' => $type));

        $debug = Debug::is_enabled();
        $debug_file = 'locations_update_terminals';

        if ($debug) Debug::to_log('Copying locations from temporary table...', $debug_file);

        $temp_locations_total = Sql::get_row('delivery_temp', array('type' => $type), 'COUNT(*) as total');
        if ( empty($temp_locations_total) || ! $temp_locations_total->total ) {
            if ($debug) Debug::to_log('Failed to get records from temporary table', $debug_file);
            return array(
                'status' => 'error',
                'msg' => __('Failed to save received locations', 'hrx-delivery')
            );
        }

        $per_page = 1000;
        $count_added = 0;
        $count_updated = 0;

        for ( $i = 0; $i < $temp_locations_total->total / $per_page; $i++ ) {
            if ($debug) Debug::to_log('Saving locations (page ' . ($i + 1) . ')...', $debug_file);
            $result = self::move_locations_to_active($type, $i * $per_page, $per_page);
            $count_added += $result['added'];
            $count_updated += $result['updated'];
        }

        if ( $count_added + $count_updated < $temp_locations_total->total ) {
            if ($debug) Debug::to_log('Some locations not saved. Received locations: ' . $temp_locations_total->total . '. Total added: ' . $count_added . '. Total updated: ' . $count_updated . '.', $debug_file);
            return array(
                'status' => 'error',
                'msg' => sprintf(__('Only %1$s out of %2$s locations were successfully saved', 'hrx-delivery'), $count_added + $count_updated, $temp_locations_total->total),
            );
        }

        if ($debug) Debug::to_log('Locations successfully saved. Added ' . $count_added . ', updated ' . $count_updated . '.', $debug_file);

        return array(
            'status' => 'OK',
            'msg' => '',
            'added' => $count_added,
            'updated' => $count_updated,
        );
    }

    private static function move_locations_to_active( $type, $get_from = 0, $get_per_page = false )
    {
        $limit = ($get_per_page) ? array((int)$get_from, (int)$get_per_page) : false;
        $temp_locations = Sql::get_multi_rows('delivery_temp', array('type' => $type), '*', 'AND', $limit);

        $count_added = 0;
        $count_updated = 0;

        foreach ( $temp_locations as $location ) {
            $location_data = (array)$location;
            $location_data['active'] = 1;
            unset($location_data['id']);

            if ( ! empty(Sql::get_row('delivery', array('location_id' => $location_data['location_id']))) ) {
                $result = Sql::update_row('delivery', $location_data, array('location_id' => $location_data['location_id']) );
                $count_updated++;
            } else {
                $result = Sql::insert_row('delivery', $location_data);
                $count_added++;
            }
        }

        return array(
            'added' => $count_added,
            'updated' => $count_updated,
        );
    }

    public static function update_couriers()
    {
        $type = 'courier';

        Sql::update_multi_rows('delivery', array('active' => 0), array('type' => $type));

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

        Helper::delete_hrx_option('countries');

        return $result;
    }

    private static function save_delivery_locations_couriers( $type, $self_repeat = false, $protector = 1 )
    {
        $max_cycles = 20;

        $debug = Debug::is_enabled();
        $debug_file = 'locations_update_couriers';

        if ($debug) Debug::to_log('Executing update...', $debug_file);

        if ( $protector > $max_cycles ) {
            if ($debug) Debug::to_log('Max number of cycles (' . $protector . ' > ' . $max_cycles . ')', $debug_file);
            return array(
                'status' => 'error',
                'msg' => __('Reached maximum number of cycles', 'hrx-delivery') . ' (' . $max_cycles . ')',
            );
        }

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
                        'zip_prefix' => '',//$location_data[''] ?? '',
                        'zip_regex' => $location_data['delivery_location_zip_regexp'] ?? '',
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

            if ($debug) Debug::to_log(sprintf('Completed. Added: %1$s, Updated: %2$s, Error: %3$s.', $count_added, $count_updated, $count_error), $debug_file);

            if ( $self_repeat ) {
                sleep(1);
                $next_page = self::save_delivery_locations_couriers($type, $self_repeat, $protector + 1);
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

        if ($debug) {
            Debug::to_log('Failed. Error: ' . $response['msg'] . PHP_EOL . 'Response: ' . PHP_EOL . print_r($response['debug'], true), $debug_file);
        }
        return array(
            'status' => 'error',
            'msg' => __('Request error', 'hrx-delivery') . ' - ' . $response['msg']
        );
    }
}
