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
        $api = new Api();

        $wc_order = wc_get_order($wc_order_id);
        if ( empty($wc_order) ) {
            $status['msg'] = __('Failed to get order', 'hrx-delivery');
            return $status;
        }

        $hrx_order_id = $wc_order->get_meta($core->meta_keys->order_id);
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
            update_post_meta($wc_order->get_id(), $core->meta_keys->error_msg, $status['msg']);
            return $status;
        }

        $warehouse_id = $wc_order->get_meta($core->meta_keys->warehouse_id);
        if ( empty($warehouse_id) ) {
            $warehouse_id = Warehouse::get_default_id();
        }
        if ( empty($warehouse_id) ) {
            $status['msg'] = __('Failed to get the selected warehouse. Please check warehouse settings.', 'hrx-delivery');
            return $status;
        }

        $method = $wc_order->get_meta($core->meta_keys->method);
        if ( empty($method) ) {
            $status['msg'] = __('Failed to get selected shipping method', 'hrx-delivery');
            return $status;
        }
        if ( ! isset($core->methods[$method]) ) {
            $status['msg'] = __('Order shipping method is not from HRX', 'hrx-delivery');
            return $status;
        }

        $classOrder = new Order();
        $receiver_data = $classOrder->get_order_address($wc_order);

        $receiver_phone = str_replace(' ', '', $receiver_data['phone']);
        $receiver_phone_prefix = '';
        $receiver_phone_regex = '';

        $receiver_city = $receiver_data['city'];
        if ( ! empty($receiver_data['state']) ) {
            $receiver_city .= ', ' . $receiver_data['state'];
        }

        $shipment_dimensions = self::get_dimensions($wc_order);
     
        $dimensions_limitation = array(
            'min' => Helper::get_empty_dimensions_array(0),
            'max' => Helper::get_empty_dimensions_array(0),
        );

        $has_terminals = false;
        $terminal_id = '';

        if ( ! empty($core->methods[$method]['has_terminals']) ) {
            $has_terminals = true;
            $terminal_id = $wc_order->get_meta($core->meta_keys->terminal_id);
            if ( empty($terminal_id) ) {
                $status['msg'] = __('Failed to get HRX terminal from order', 'hrx-delivery');
                return $status;
            }

            $terminal_data = Sql::get_row('delivery', array('location_id' => $terminal_id));
            if ( empty($terminal_data) ) {
                $status['msg'] = __('The terminal specified in the order was not found', 'hrx-delivery');
                $meta_msg = __('Selected terminal not found', 'hrx-delivery');
                update_post_meta($wc_order->get_id(), $core->meta_keys->error_msg, $meta_msg);
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

            $dimensions_limitation['min']['weight'] = $selected_country_data['min_weight_kg'];
            $dimensions_limitation['min']['length'] = $selected_country_data['min_length_cm'];
            $dimensions_limitation['min']['width'] = $selected_country_data['min_width_cm'];
            $dimensions_limitation['min']['height'] = $selected_country_data['min_height_cm'];
            $dimensions_limitation['max']['weight'] = $selected_country_data['max_weight_kg'];
            $dimensions_limitation['max']['length'] = $selected_country_data['max_length_cm'];
            $dimensions_limitation['max']['width'] = $selected_country_data['max_width_cm'];
            $dimensions_limitation['max']['height'] = $selected_country_data['max_height_cm'];
        }

        if ( ! Helper::check_phone($receiver_phone, $receiver_phone_prefix, $receiver_phone_regex) ) {
            $status['msg'] = sprintf(
                __('The recipient phone (%1$s) does not match the required format for country %2$s', 'hrx-delivery'),
                $receiver_phone,
                \WC()->countries->countries[$receiver_data['country']]
            );
            $meta_msg = __('The phone does not match the required format', 'hrx-delivery');
            update_post_meta($wc_order->get_id(), $core->meta_keys->error_msg, $meta_msg);
            return $status;
        }
        $receiver_phone = Helper::remove_phone_prefix($receiver_phone, $receiver_phone_prefix);

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
            'name' => $receiver_data['first_name'] . ' ' . $receiver_data['last_name'],
            'email' => $receiver_email,
            'phone' => Helper::remove_phone_prefix($receiver_phone, $receiver_phone_prefix),
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

        delete_post_meta($wc_order->get_id(), $core->meta_keys->error_msg);

        if ( $result['status'] == 'OK' ) {
            update_post_meta($wc_order->get_id(), $core->meta_keys->order_id, $result['data']['id']);
            $wc_order = wc_get_order($wc_order_id);
            $info = $classOrder->update_hrx_order_info($wc_order);

            $status['status'] = 'OK';
            $status['status_code'] = 'registered';
            $status['msg'] = $result['msg'];
        } else {
            $status['msg'] = __('Unable to register order due to error', 'hrx-delivery') . ":\n" . $result['msg'];
            update_post_meta($wc_order->get_id(), $core->meta_keys->error_msg, $result['msg']);
        }

        return $status;
    }

    public static function get_dimensions( $wc_order, $method = '' )
    {
        $core = Core::get_instance();
        $method = (empty($method)) ? $wc_order->get_meta($core->meta_keys->method) : $method;
        
        $classOrder = new Order();
        $order_weight = $classOrder->get_order_weight($wc_order);
        $products_dimensions = $classOrder->get_order_products_dimensions($wc_order); // TODO: Make box size calculation
        
        $order_dimensions = $wc_order->get_meta($core->meta_keys->dimensions);
        if ( ! empty($order_dimensions['weight']) ) {
            $order_weight = $order_dimensions['weight'];
        }

        $shipment_dimensions = array(
            'length' => (! empty($order_dimensions['length'])) ? $order_dimensions['length'] : '',
            'width' => (! empty($order_dimensions['width'])) ? $order_dimensions['width'] : '',
            'height' => (! empty($order_dimensions['height'])) ? $order_dimensions['height'] : '',
            'weight' => (! empty($order_weight)) ? $order_weight : '',
        );
        
        return Helper::use_current_or_default_dimmension($method, $shipment_dimensions);
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
            update_post_meta($order_id, $meta_key, esc_attr($check_msg));
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

        $wc_order = wc_get_order($wc_order_id);
        if ( empty($wc_order) ) {
            $status['msg'] = __('Failed to get order', 'hrx-delivery');
            return $status;
        }

        $hrx_order_id = $wc_order->get_meta($core->meta_keys->order_id);
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

    public static function ready_order( $wc_order, $unmark = false )
    {
        $core = Core::get_instance();
        $settings = $core->get_settings();
        $status = array(
            'status' => 'error',
            'msg' => '',
        );

        $hrx_order_id = $wc_order->get_meta($core->meta_keys->order_id);
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
            if ( ! empty($change_wc_status) ) {
                $wc_order->update_status($change_wc_status, '<b>' . $core->title . ':</b> ');
            }
            $wc_order->update_meta_data($core->meta_keys->order_status, $hrx_status);
            $wc_order->save();

            $status['status'] = 'OK';
            $status['msg'] = __('Order status changed successfully', 'hrx-delivery');
            
            return $status;
        }

        $status['msg'] = __('Failed to change HRX order status', 'hrx-delivery') . ":\n" . $result['msg'];
        $classOrder = new Order();
        $classOrder->update_hrx_order_info($wc_order);

        return $status;
    }

    public static function get_status( $wc_order )
    {
        $core = Core::get_instance();

        $order_status = $wc_order->get_meta($core->meta_keys->order_status);

        if ( empty($order_status) ) {
            $classOrder = new Order();
            $order_data = $classOrder->update_hrx_order_info($wc_order);
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
