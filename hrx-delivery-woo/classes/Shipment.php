<?php
namespace HrxDeliveryWoo;

// Prevent direct access to this script
if ( ! defined('ABSPATH') ) {
    exit;
}

use HrxDeliveryWoo\Api;
use HrxDeliveryWoo\Sql;
use HrxDeliveryWoo\Core;
use HrxDeliveryWoo\Helper;
use HrxDeliveryWoo\Warehouse;
use HrxDeliveryWoo\Order;
use HrxDeliveryWoo\Label;
use HrxDeliveryWoo\WcOrder;
use HrxDeliveryWoo\WcTools;
use HrxDeliveryWoo\WcCustom;

class Shipment
{
    public static function register_order( $wc_order_id, $allow_regenerate = false )
    {
        $status = array(
            'status' => 'error',
            'status_code' => 'failed',
            'msg' => '',
        );
        $core = Core::get_instance();
        $wc = (object) array(
            'order' => new WcOrder(),
            'tools' => new WcTools(),
            'custom' => new WcCustom(),
        );
        $api = new Api();

        $wc_order = $wc->order->get_order($wc_order_id, true);
        if ( ! $wc_order ) {
            $status['msg'] = __('Failed to get order', 'hrx-delivery');
            return $status;
        }

        $hrx_data = $wc->order->get_hrx_data($wc_order->get_id());

        $hrx_order_id = $hrx_data->hrx_order_id;
        if ( ! empty($hrx_order_id) ) {
            if ( ! $allow_regenerate ) {
                $status['status'] = 'OK';
                $status['status_code'] = 'exist';
                $status['msg'] = __('Order already registered', 'hrx-delivery');
                return $status;
            }

            $result = $api->cancel_order($hrx_order_id);
            if ( $result['status'] == 'error' ) {
                // Not throw error when old order cancel will fail
            }
        }

        $receiver_email = $wc_order->get_billing_email();
        if ( empty($receiver_email) ) {
            $status['msg'] = __('Recipient email address is required', 'hrx-delivery');
            $wc->order->update_meta($wc_order->get_id(), $core->meta_keys->error_msg, $status['msg']);

            return $status;
        }

        $warehouse_id = $hrx_data->warehouse_id;
        if ( empty($warehouse_id) ) {
            $warehouse_id = Warehouse::get_default_id();
        }
        if ( empty($warehouse_id) ) {
            $status['msg'] = __('Failed to get the selected warehouse. Please check warehouse settings.', 'hrx-delivery');
            return $status;
        }

        $method = $hrx_data->method;
        if ( empty($method) ) {
            $status['msg'] = __('Failed to get selected shipping method', 'hrx-delivery');
            return $status;
        }
        if ( ! isset($core->methods[$method]) ) {
            $status['msg'] = __('Order shipping method is not from HRX', 'hrx-delivery');
            return $status;
        }

        $receiver_data = $wc->custom->get_order_address($wc_order);
        $receiver_name = $wc->custom->get_customer_company_with_name($wc_order);

        $receiver_phone = str_replace(' ', '', $receiver_data['phone']);
        $receiver_phone_prefix = '';
        $receiver_phone_regex = '';
        $receiver_postcode = str_replace(' ', '', $receiver_data['postcode']);;
        $receiver_postcode_prefix = '';
        $receiver_postcode_regex = '';

        $receiver_city = $receiver_data['city'];
        if ( ! empty($receiver_data['state']) ) {
            $receiver_city .= ', ' . $receiver_data['state'];
        }

        $shipment_dimensions = self::get_dimensions($wc_order->get_id());
        $shipment_dimensions = $wc->custom->convert_all_dimensions($shipment_dimensions, 'kg', 'cm');
     
        $dimensions_limitation = array(
            'min' => Helper::get_empty_dimensions_array(0),
            'max' => Helper::get_empty_dimensions_array(0),
        );

        $has_terminals = false;
        $terminal_id = '';

        if ( ! empty($core->methods[$method]['has_terminals']) ) {
            $has_terminals = true;
            $terminal_id = $hrx_data->terminal_id;
            if ( empty($terminal_id) ) {
                $status['msg'] = __('Failed to get HRX terminal from order', 'hrx-delivery');
                return $status;
            }

            $terminal_data = Sql::get_row('delivery', array('location_id' => $terminal_id));
            if ( empty($terminal_data) ) {
                $status['msg'] = __('The terminal specified in the order was not found', 'hrx-delivery');
                $meta_msg = __('Selected terminal not found', 'hrx-delivery');
                $wc->order->update_meta($wc_order->get_id(), $core->meta_keys->error_msg, $meta_msg);
                return $status;
            }

            $terminal_params = json_decode($terminal_data->params);

            $receiver_phone_prefix = $terminal_params->phone_prefix;
            $receiver_phone_regex = $terminal_params->phone_regexp;

            $dimensions_limitation['min']['weight'] = $terminal_params->min_weight;
            $dimensions_limitation['min']['length'] = $terminal_params->min_length;
            $dimensions_limitation['min']['width'] = $terminal_params->min_width;
            $dimensions_limitation['min']['height'] = $terminal_params->min_height;
            $dimensions_limitation['max']['weight'] = $terminal_params->max_weight;
            $dimensions_limitation['max']['length'] = $terminal_params->max_length;
            $dimensions_limitation['max']['width'] = $terminal_params->max_width;
            $dimensions_limitation['max']['height'] = $terminal_params->max_height;
        } else {
            $available_countries = $api->get_courier_delivery_locations();
            if ( $available_countries['status'] == 'error' ) {
                $status['msg'] = __('Failed to get available shipping countries', 'hrx-delivery') . '. ' . __('Error', 'hrx-delivery') . ': ' . $available_countries['msg'];
                return $status;
            }

            $selected_country_data = Helper::get_array_element_by_it_value($available_countries['data'], array('country' => $receiver_data['country']));

            $receiver_phone_prefix = $selected_country_data['recipient_phone_prefix'];
            $receiver_phone_regex = $selected_country_data['recipient_phone_regexp'];
            $receiver_postcode_prefix = '';
            $receiver_postcode_regex = $selected_country_data['delivery_location_zip_regexp'];

            $dimensions_limitation['min']['weight'] = $selected_country_data['min_weight_kg'];
            $dimensions_limitation['min']['length'] = $selected_country_data['min_length_cm'];
            $dimensions_limitation['min']['width'] = $selected_country_data['min_width_cm'];
            $dimensions_limitation['min']['height'] = $selected_country_data['min_height_cm'];
            $dimensions_limitation['max']['weight'] = $selected_country_data['max_weight_kg'];
            $dimensions_limitation['max']['length'] = $selected_country_data['max_length_cm'];
            $dimensions_limitation['max']['width'] = $selected_country_data['max_width_cm'];
            $dimensions_limitation['max']['height'] = $selected_country_data['max_height_cm'];
        }

        if ( ! Helper::check_regex($receiver_phone, $receiver_phone_prefix, $receiver_phone_regex) ) {
            $status['msg'] = sprintf(
                __('The recipient phone (%1$s) does not match the required format for country %2$s', 'hrx-delivery'),
                $receiver_phone,
                $wc->tools->get_country_name($receiver_data['country'])
            );
            $meta_msg = __('The phone does not match the required format', 'hrx-delivery');
            $wc->order->update_meta($wc_order->get_id(), $core->meta_keys->error_msg, $meta_msg);
            return $status;
        }
        $receiver_phone = Helper::remove_prefix($receiver_phone, $receiver_phone_prefix);

        if ( ! Helper::check_regex($receiver_postcode, $receiver_postcode_prefix, $receiver_postcode_regex) ) {
            $status['msg'] = sprintf(
                __('The recipient postcode (%1$s) does not match the required format for country %2$s (%3$s)', 'hrx-delivery'),
                $receiver_postcode,
                $wc->tools->get_country_name($receiver_data['country']),
                Helper::beautify_regex($receiver_postcode_regex)
            );
            $meta_msg = __('The postcode does not match the required format', 'hrx-delivery');
            $wc->order->update_meta($wc_order->get_id(), $core->meta_keys->error_msg, $meta_msg);
            return $status;
        }

        $check_dimensions = array('weight', 'length', 'width', 'height');
        $check_limitations = array('min', 'max');
        
        foreach ( $check_dimensions as $dim_key ) {
            foreach ( $check_limitations as $lim_key ) {
                if ( $dimensions_limitation[$lim_key][$dim_key] == '') {
                    continue;
                }
                $dimmension_check_msg = self::check_dimension(
                    $wc_order->get_id(),
                    $core->meta_keys->error_msg,
                    $dim_key,
                    (float)$shipment_dimensions[$dim_key],
                    (float)$dimensions_limitation[$lim_key][$dim_key],
                    Helper::get_compare_symbol($lim_key)
                );

                if ( ! empty($dimmension_check_msg) ) {
                    Debug::to_log(array(
                        'status' => $status,
                        'shipment_dimensions' => $shipment_dimensions,
                        'allowed_dimensions' => $dimensions_limitation,
                    ), 'register_order');
                    
                    $status['msg'] = $dimmension_check_msg;
                    return $status;
                }
            }
        }

        $prepared_receiver = array(
            'name' => $receiver_name,
            'email' => $receiver_email,
            'phone' => Helper::remove_prefix($receiver_phone, $receiver_phone_prefix),
            'phone_regex' => $receiver_phone_regex,
            'address' => $receiver_data['address_1'],
            'postcode' => $receiver_data['postcode'],
            'city' => $receiver_city,
            'country' => $receiver_data['country'],
        );

        $prepared_shipment = array(
            'reference' => 'WC_' . $wc_order->get_order_number(),
            'comment' => '',
            'length' => $shipment_dimensions['length'],
            'width' => $shipment_dimensions['width'],
            'height' => $shipment_dimensions['height'],
            'weight' => $shipment_dimensions['weight'],
        );

        $prepared_order = array(
            'pickup_id' => $warehouse_id,
            'delivery_id' => $terminal_id,
            'has_terminals' => $has_terminals,
        );

        $result = $api->create_order(array(
            'receiver' => $prepared_receiver,
            'shipment' => $prepared_shipment,
            'order' => $prepared_order,
        ));

        $wc->order->delete_meta($wc_order->get_id(), $core->meta_keys->error_msg);

        if ( $result['status'] == 'OK' ) {
            $wc->order->update_meta($wc_order->get_id(), $core->meta_keys->order_id, $result['data']['id']);
            $classOrder = new Order();
            $info = $classOrder->update_hrx_order_info($wc_order->get_id());

            $status['status'] = 'OK';
            $status['status_code'] = 'registered';
            $status['msg'] = $result['msg'];
        } else {
            $status['msg'] = __('Unable to register order due to error', 'hrx-delivery') . ":\n" . $result['msg'];
            $wc->order->update_meta($wc_order->get_id(), $core->meta_keys->error_msg, $result['msg']);
        }

        return $status;
    }

    public static function get_dimensions( $wc_order_id, $method = '' )
    {
        $core = Core::get_instance();
        $wc = (object) array(
            'order' => new WcOrder(),
        );

        $wc_order = $wc->order->get_order($wc_order_id, true);
        $hrx_data = $wc->order->get_hrx_data($wc_order->get_id());
        $method = (empty($method)) ? $hrx_data->method : $method;
        $order_weight = $wc->order->count_total_weight($wc_order->get_id());
        $shipment_dimensions = self::calc_total_dimension($wc->order->get_items($wc_order->get_id()));
       
        $order_dimensions = $hrx_data->dimensions;
        if ( ! empty($order_dimensions['length'])
          || ! empty($order_dimensions['width'])
          || ! empty($order_dimensions['height'])
        ) {
            $shipment_dimensions = array(
                'length' => (! empty($order_dimensions['length'])) ? $order_dimensions['length'] : 0,
                'width' => (! empty($order_dimensions['width'])) ? $order_dimensions['width'] : 0,
                'height' => (! empty($order_dimensions['height'])) ? $order_dimensions['height'] : 0,
            );
        }

        if ( ! empty($order_dimensions['weight']) ) {
            $shipment_dimensions['weight'] = $order_dimensions['weight'];
        }
        
        return Helper::use_current_or_default_dimmension($method, $shipment_dimensions);
    }

    private static function calc_total_dimension( $products )
    {
        $total_dimension = array(
            'length' => 0,
            'width' => 0,
            'height' => 0,
        );
        $total_weight = 0;

        foreach ( $products as $product ) {
            $total_weight += $product->weight * $product->quantity;
            for ( $i = 0; $i < $product->quantity; $i++ ) {
                $total_dimension = self::add_to_box($total_dimension, $product);
            }
        }
        $total_dimension['weight'] = $total_weight;
        
        return $total_dimension;
    }

    private static function add_to_box( $box, $product, $edge_thickness = 0 ) //TODO: Make box size calculation
    {
        $total_size = array(
            'length' => 0,
            'width' => 0,
            'height' => 0,
        );

        $shortest_edge_key = '';
        $shortest_edge_value = 0;
        foreach ( $box as $edge => $value ) {
            if ( $value <= $shortest_edge_value ) {
                $shortest_edge_value = $value;
                $shortest_edge_key = $edge;
            }
        }

        foreach ( $total_size as $edge => $value ) {
            $prod_value = $product->{$edge};
            if ( $edge == $shortest_edge_key ) {
                $total_size[$edge] += $prod_value;
            } else if ( $total_size[$edge] < $prod_value ) {
                $total_size[$edge] = $prod_value;
            }
        }

        foreach ( $total_size as $edge => $value ) {
            $total_size[$edge] += $edge_thickness;
        }

        return $total_size;
    }

    private static function check_dimension( $order_id, $meta_key, $dimmension_type, $current_value, $compare_value, $compare_symbol = '<' )
    {
        $units = Helper::get_empty_dimensions_array('cm');
        $units['weight'] = 'kg';

        $check_msg = false;
        $status_msg = '';

        if ( $compare_symbol == '<' ) {
            if ( $current_value < $compare_value ) {
                $check_msg = sprintf(
                    __('%1$s (%2$s) is less then %3$s'),
                    ucfirst($dimmension_type),
                    $current_value . ' ' . $units[$dimmension_type],
                    $compare_value . ' ' . $units[$dimmension_type]
                );
                $status_msg = sprintf(
                    __('The %1$s of the order (%2$s) is less than allowed (%3$s)', 'hrx-delivery'),
                    $dimmension_type,
                    $current_value . ' ' . $units[$dimmension_type],
                    $compare_value . ' ' . $units[$dimmension_type]
                );
            }
        }

        if ( $compare_symbol == '>' ) {
            if ( $current_value > $compare_value ) {
                $check_msg = sprintf(
                    __('%1$s (%2$s) is more then %3$s'),
                    ucfirst($dimmension_type),
                    $current_value . ' ' . $units[$dimmension_type],
                    $compare_value . ' ' . $units[$dimmension_type]
                );
                $status_msg = sprintf(
                    __('The %1$s of the order (%2$s) is more than allowed (%3$s)', 'hrx-delivery'),
                    $dimmension_type,
                    $current_value . ' ' . $units[$dimmension_type],
                    $compare_value . ' ' . $units[$dimmension_type]
                );
            }
        }

        if ( $compare_symbol == '!=' ) {
            if ( $current_value != $compare_value ) {
                $check_msg = sprintf(
                    __('%1$s (%2$s) is not equal to %3$s'),
                    ucfirst($dimmension_type),
                    $current_value . ' ' . $units[$dimmension_type],
                    $compare_value . ' ' . $units[$dimmension_type]
                );
                $status_msg = sprintf(
                    __('The %1$s of the order (%2$s) is not equal to required (%3$s)', 'hrx-delivery'),
                    $dimmension_type,
                    $current_value . ' ' . $units[$dimmension_type],
                    $compare_value . ' ' . $units[$dimmension_type]
                );
            }
        }

        if ( $check_msg ) {
            $wcOrder = new WcOrder();
            $wcOrder->update_meta($order_id, $meta_key, esc_attr($check_msg));
        }

        return $status_msg;
    }

    public static function get_label( $wc_order_id, $label_type = 'shipping' )
    {
        $status = array(
            'status' => 'error',
            'msg' => '',
            'label_url' => '',
            'label_path' => '',
        );
        $core = Core::get_instance();
        $wc = (object) array(
            'order' => new WcOrder(),
        );

        $wc_order = $wc->order->get_order($wc_order_id);
        if ( ! $wc_order ) {
            $status['msg'] = __('Failed to get order', 'hrx-delivery');
            return $status;
        }

        $hrx_data = $wc->order->get_hrx_data($wc_order_id);

        $hrx_order_id = $hrx_data->hrx_order_id;
        if ( empty($hrx_order_id) ) {
            $status['msg'] = __('Failed to get HRX order ID from order', 'hrx-delivery');
            return $status;
        }

        $api = new Api();

        if ( $label_type == 'shipping' ) {
            $label_status = $api->get_shipping_label($hrx_order_id);
        } else if ( $label_type == 'return' ) {
            $label_status = $api->get_return_label($hrx_order_id);
        } else {
            $status['msg'] = __('This type of label does not exist', 'hrx-delivery');
            return $status;
        }

        if ( $label_status['status'] == 'OK' ) {
            $label_location = Label::save_file($label_status['data']['file_name'], $label_status['data']['file_content'] , false);
            
            $status['status'] = 'OK';
            $status['label_url'] = $label_location['url'];
            $status['label_path'] = $label_location['path'];
        } else {
            $status['msg'] = __('Failed to get label', 'hrx-delivery') . ":\n" . $label_status['msg'];
        }

        return $status;
    }

    public static function ready_order( $wc_order_id, $unmark = false, $allow_status_change = true )
    {
        $core = Core::get_instance();
        $wc = (object) array(
            'order' => new WcOrder(),
        );
        $settings = $core->get_settings();
        $status = array(
            'status' => 'error',
            'msg' => '',
        );

        $wc_order = $wc->order->get_order($wc_order_id, true);
        if ( ! $wc_order ) {
            $status['msg'] = __('Failed to get order', 'hrx-delivery');
            return $status;
        }

        $hrx_order_id = $wc->order->get_meta($wc_order_id, $core->meta_keys->order_id);
        if ( empty($hrx_order_id) ) {
            $status['msg'] = __('Failed to get HRX order ID from order', 'hrx-delivery');
            
            return $status;
        }

        $api = new Api();

        if ( $unmark ) {
            $result = $api->ready_order($hrx_order_id, false);
            $change_wc_status = (! empty($settings['wc_status_off_ready'])) ? $settings['wc_status_off_ready'] : '';
        } else {
            $result = $api->ready_order($hrx_order_id, true);
            $change_wc_status = (! empty($settings['wc_status_on_ready'])) ? $settings['wc_status_on_ready'] : '';
        }

        if ( $result['status'] == 'OK' ) {
            $hrx_status = 'unknown';
            if ( ! empty($result['data']['status']) ) {
                $hrx_status = esc_attr($result['data']['status']);
            }
            if ( ! empty($change_wc_status) && $allow_status_change ) {
                $wc->order->update_status($wc_order_id, $change_wc_status, '<b>' . $core->title . ':</b> ');
            }
            $wc->order->update_meta($wc_order_id, $core->meta_keys->order_status, $hrx_status);

            $status['status'] = 'OK';
            $status['msg'] = __('Order status changed successfully', 'hrx-delivery');
            
            return $status;
        }

        $status['msg'] = __('Failed to change HRX order status', 'hrx-delivery') . ":\n" . $result['msg'];
        $classOrder = new Order();
        $classOrder->update_hrx_order_info($wc_order_id);

        return $status;
    }

    public static function get_status( $wc_order_id )
    {
        $core = Core::get_instance();
        $wc = (object) array(
            'order' => new WcOrder(),
        );

        $order_status = $wc->order->get_meta($wc_order_id, $core->meta_keys->order_status);

        if ( empty($order_status) ) {
            $classOrder = new Order();
            $order_data = $classOrder->update_hrx_order_info($wc_order_id);
            $order_status = $order_data['status'];
        }

        return $order_status;
    }

    public static function get_status_title( $status_key )
    {
        $status_titles = array(
            'new' => _x('New', 'HRX order status', 'hrx-delivery'),
            'ready' => _x('Ready', 'HRX order status', 'hrx-delivery'),
            'in_delivery' => _x('In delivery', 'HRX order status', 'hrx-delivery'),
            'in_return' => _x('In return', 'HRX order status', 'hrx-delivery'),
            'returned' => _x('Returned', 'HRX order status', 'hrx-delivery'),
            'delivered' => _x('Delivered', 'HRX order status', 'hrx-delivery'),
            'cancelled' => _x('Cancelled', 'HRX order status', 'hrx-delivery'),
            'error' => _x('Error', 'HRX order status', 'hrx-delivery'),
        );

        return $status_titles[$status_key] ?? $status_key;
    }

    public static function get_allowed_order_actions()
    {
        return array(
            'register_order' => array(
                'hrx' => array('unknown'),
                'hrx_not' => array(),
                'wc' => array(),
                'wc_not' => array('completed', 'cancelled', 'refunded', 'failed'),
            ),
            'regenerate_order' => array(
                'hrx' => array(),
                'hrx_not' => array('ready', 'unknown'),
                'wc' => array(),
                'wc_not' => array('completed', 'cancelled', 'refunded', 'failed'),
            ),
            'mark_ready' => array(
                'hrx' => array('new'),
                'hrx_not' => array(),
                'wc' => array(),
                'wc_not' => array('cancelled', 'refunded', 'failed'),
            ),
            'unmark_ready' => array(
                'hrx' => array('ready'),
                'hrx_not' => array(),
                'wc' => array(),
                'wc_not' => array('completed', 'cancelled', 'refunded', 'failed'),
            ),
            'ship_label' => array(
                'hrx' => array(),
                'hrx_not' => array('unknown', 'error'),
                'wc' => array(),
                'wc_not' => array(),
            ),
            'return_label' => array(
                'hrx' => array(),
                'hrx_not' => array('unknown', 'error'),
                'wc' => array(),
                'wc_not' => array(),
            ),
            /*'manifest' => array(
                'hrx' => array(),
                'hrx_not' => array(),
                'wc' => array(),
                'wc_not' => array(),
            ),*/
        );
    }

    public static function convert_allowed_order_action_from_mass( $mass_action )
    {
        if ( $mass_action == 'register_orders' ) {
            return 'register_order';
        }
        if ( $mass_action == 'regenerate_orders' ) {
            return 'regenerate_order';
        }

        return $mass_action;
    }

    public static function check_any_allowed_order_action( $hrx_status, $wc_status )
    {
        $allowed_actions = self::get_allowed_order_actions();

        foreach( $allowed_actions as $action => $statuses ) {
            if ( self::check_specific_allowed_order_action($action, $hrx_status, $wc_status) ) {
                return true;
            }
        }

        return false;
    }

    public static function check_specific_allowed_order_action( $action, $hrx_status, $wc_status )
    {
        $allowed_actions = self::get_allowed_order_actions();

        if ( ! isset($allowed_actions[$action]) ) {
            return true;
        }

        $allowed_action = $allowed_actions[$action];

        if ( ! empty($allowed_action['hrx_not']) && in_array($hrx_status, $allowed_action['hrx_not']) ) {
            return false;
        }
        if ( ! empty($allowed_action['wc_not']) && in_array($wc_status, $allowed_action['wc_not']) ) {
            return false;
        }

        if ( ! empty($allowed_action['hrx']) && ! in_array($hrx_status, $allowed_action['hrx']) ) {
            return false;
        }
        if ( ! empty($allowed_action['wc']) && ! in_array($wc_status, $allowed_action['wc']) ) {
            return false;
        }

        return true;
    }
}
