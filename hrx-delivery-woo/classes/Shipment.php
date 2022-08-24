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
        $order_weight = $classOrder->get_order_weight($wc_order);
        $receiver_data = $classOrder->get_order_address($wc_order);

        $receiver_phone = str_replace(' ', '', $receiver_data['phone']);
        $receiver_phone_prefix = '';
        $receiver_phone_regex = '';

        $receiver_city = $receiver_data['city'];
        if ( ! empty($receiver_data['state']) ) {
            $receiver_city .= ', ' . $receiver_data['state'];
        }

        $products_dimmensions = $classOrder->get_order_products_dimmensions($wc_order);
        //TODO: Make the calculation of the smallest possible box to fit all the goods

        $shipment_dimmensions = array(
            'length' => '', // TODO: If the initial size of the box will be obtained
            'width' => '',
            'height' => '',
            'weight' => (! empty($order_weight)) ? $order_weight : '',
        );
        $shipment_dimmensions = Helper::use_current_or_default_dimmension($method, $shipment_dimmensions);
     
        $dimmensions_limitation = array(
            'min' => Helper::get_empty_dimmensions_array(0),
            'max' => Helper::get_empty_dimmensions_array(0),
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

            $dimmensions_limitation['min']['weight'] = $terminal_params->min_weight;
            $dimmensions_limitation['min']['length'] = $terminal_params->min_length;
            $dimmensions_limitation['min']['width'] = $terminal_params->min_width;
            $dimmensions_limitation['min']['height'] = $terminal_params->min_height;
            $dimmensions_limitation['max']['weight'] = $terminal_params->max_weight;
            $dimmensions_limitation['max']['length'] = $terminal_params->max_length;
            $dimmensions_limitation['max']['width'] = $terminal_params->max_width;
            $dimmensions_limitation['max']['height'] = $terminal_params->max_height;
        } else {
            $available_countries = $api->get_courier_delivery_locations();
            if ( $available_countries['status'] == 'error' ) {
                $status['msg'] = __('Failed to get available shipping countries', 'hrx-delivery') . '. ' . __('Error', 'hrx-delivery') . ': ' . $available_countries['msg'];
                return $status;
            }

            $selected_country_data = Helper::get_array_element_by_it_value($available_countries['data'], array('country' => $receiver_data['country']));

            $receiver_phone_prefix = $selected_country_data['recipient_phone_prefix'];
            $receiver_phone_regex = $selected_country_data['recipient_phone_regexp'];

            $dimmensions_limitation['min']['weight'] = $selected_country_data['min_weight_kg'];
            $dimmensions_limitation['min']['length'] = $selected_country_data['min_length_cm'];
            $dimmensions_limitation['min']['width'] = $selected_country_data['min_width_cm'];
            $dimmensions_limitation['min']['height'] = $selected_country_data['min_height_cm'];
            $dimmensions_limitation['max']['weight'] = $selected_country_data['max_weight_kg'];
            $dimmensions_limitation['max']['length'] = $selected_country_data['max_length_cm'];
            $dimmensions_limitation['max']['width'] = $selected_country_data['max_width_cm'];
            $dimmensions_limitation['max']['height'] = $selected_country_data['max_height_cm'];
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

        $check_dimmensions = array('weight', 'length', 'width', 'height');
        $check_limitations = array('min', 'max');
        
        foreach ( $check_dimmensions as $dim_key ) {
            foreach ( $check_limitations as $lim_key ) {
                if ( $dimmensions_limitation[$lim_key][$dim_key] == '') {
                    continue;
                }
                $dimmension_check_msg = self::check_dimmension(
                    $wc_order->get_id(),
                    $core->meta_keys->error_msg,
                    $dim_key,
                    (float)$shipment_dimmensions[$dim_key],
                    (float)$dimmensions_limitation[$lim_key][$dim_key],
                    Helper::get_compare_symbol($lim_key)
                );

                if ( ! empty($dimmension_check_msg) ) {
                    Debug::to_log(array(
                        'status' => $status,
                        'shipment_dimmensions' => $shipment_dimmensions,
                        'allowed_dimmensions' => $dimmensions_limitation,
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
            'length' => $shipment_dimmensions['length'],
            'width' => $shipment_dimmensions['width'],
            'height' => $shipment_dimmensions['height'],
            'weight' => $shipment_dimmensions['weight'],
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

    private static function check_dimmension( $order_id, $meta_key, $dimmension_type, $current_value, $compare_value, $compare_symbol = '<' )
    {
        $units = Helper::get_empty_dimmensions_array('cm');
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
}
