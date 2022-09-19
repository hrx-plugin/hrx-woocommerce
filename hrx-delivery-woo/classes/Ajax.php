<?php
namespace HrxDeliveryWoo;

// Prevent direct access to this script
if ( ! defined('ABSPATH') ) {
    exit;
}

use HrxDeliveryWoo\Api;
use HrxDeliveryWoo\Sql;
use HrxDeliveryWoo\Helper;
use HrxDeliveryWoo\Core;
use HrxDeliveryWoo\Order;
use HrxDeliveryWoo\Terminal;
use HrxDeliveryWoo\Warehouse;
use HrxDeliveryWoo\Label;
use HrxDeliveryWoo\Shipment;
use HrxDeliveryWoo\Pdf;
use HrxDeliveryWoo\Debug;

class Ajax
{
    public static function init()
    {
        $this_class = __NAMESPACE__ . '\Ajax::';

        add_action('wp_ajax_hrx_add_terminal', $this_class . 'front_add_terminal_to_session');
        add_action('wp_ajax_hrx_check_token', $this_class . 'admin_btn_check_token');
        add_action('wp_ajax_hrx_update_delivery_locations', $this_class . 'admin_btn_update_delivery_locations');
        add_action('wp_ajax_hrx_update_pickup_locations', $this_class . 'admin_btn_update_pickup_locations');
        add_action('wp_ajax_hrx_change_default_warehouse', $this_class . 'admin_btn_change_default_warehouse');
        add_action('wp_ajax_hrx_create_order', $this_class . 'admin_btn_create_hrx_order');
        add_action('wp_ajax_hrx_get_label', $this_class . 'admin_btn_get_hrx_label');
        add_action('wp_ajax_hrx_ready_order', $this_class . 'admin_btn_ready_hrx_order');
        add_action('wp_ajax_hrx_table_mass_action', $this_class . 'admin_btn_table_mass_action');
    }

    public static function front_add_terminal_to_session()
    {
        if ( ! empty($_POST['terminal_id']) ) {
            WC()->session->set(Core::get_instance()->id . '_terminal', $_POST['terminal_id']);
        }

        wp_die();
    }

    public static function admin_btn_check_token()
    {
        $api = new Api();
        $response = $api->get_orders(1,1);

        if ( $response['status'] == 'error' ) {
            echo json_encode(array(
                'status' => 'error',
                'msg' => __('Request error', 'hrx-delivery') . ' - ' . $response['msg']
            ));
        } else {
            echo json_encode(array(
                'status' => 'OK',
                'msg' => __('Key is good', 'hrx-delivery')
            ));
        }

        wp_die();
    }

    public static function admin_btn_update_delivery_locations()
    {
        $result = Terminal::update_delivery_locations();

        $current_time = current_time("Y-m-d H:i:s");
        $output = self::get_location_result_output($result, $current_time);

        echo json_encode($output);
        wp_die();
    }

    public static function admin_btn_update_pickup_locations()
    {
        $result = Warehouse::update_pickup_locations();

        $current_time = current_time("Y-m-d H:i:s");
        $output = self::get_location_result_output($result, $current_time);

        echo json_encode($output);
        wp_die();
    }

    private static function get_location_result_output( $result, $current_time )
    {
        $output = array();

        if ( $result['status'] == 'error' ) {
            $output['status'] = 'error';
            $msg = __('Request error', 'hrx-delivery') . ' - ' . $result['msg'];
            if ( ! empty($result['added']) || ! empty($result['updated']) ) {
                $msg = $current_time . '. ' . $msg;
            }
            $output['msg'] = $msg;
        } else {
            $msg = $current_time;
            if ( $result['added'] > 0 ) {
                $msg .= '. ' . __('Total added', 'hrx-delivery') . ': ' . $result['added'];
            }
            if ( $result['updated'] > 0 ) {
                $msg .= '. ' . __('Total updated', 'hrx-delivery') . ': ' . $result['updated'];
            }
            if ( $result['errors'] > 0 ) {
                $msg .= '. ' . __('Total errors', 'hrx-delivery') . ': ' . $result['errors'];
            }
            $output['status'] = 'OK';
            $output['msg'] = $msg;
        }

        return $output;
    }

    public static function admin_btn_change_default_warehouse()
    {
        if ( empty($_POST['warehouse']) ) {
            wp_die();
        }

        $result = Helper::update_hrx_option('default_warehouse', $_POST['warehouse']);

        if ( $result ) {
            echo json_encode(__('The default warehouse has been successfully changed', 'hrx-delivery'));
        } else {
            echo json_encode(__('Failed to change default warehouse', 'hrx-delivery'));
        }

        wp_die();
    }

    public static function admin_btn_create_hrx_order()
    {
        $status = array(
            'status' => 'error',
            'msg' => '',
        );

        if ( empty($_POST['order_id']) ) {
            self::output_status_on_error($status, __('Order ID not received', 'hrx-delivery'), true);
        }

        $status = Shipment::register_order(esc_attr($_POST['order_id']), true);
        
        echo json_encode($status);
        wp_die();
    }

    public static function admin_btn_get_hrx_label()
    {
        $status = array(
            'status' => 'error',
            'msg' => '',
        );

        if ( empty($_POST['order_id']) ) {
            self::output_status_on_error($status, __('Order ID not received', 'hrx-delivery'), true);
        }

        $label_type = (! empty($_POST['label_type'])) ? esc_attr($_POST['label_type']) : 'shipping';

        $label = Shipment::get_label(esc_attr($_POST['order_id']), $label_type);

        $status['status'] = $label['status'];
        $status['msg'] = $label['msg'];
        $status['label'] = $label['label_url'];
      
        echo json_encode($status);
        wp_die();
    }

    public static function admin_btn_ready_hrx_order()
    {
        $status = array(
            'status' => 'error',
            'msg' => '',
        );
        $meta_keys = Core::get_instance()->meta_keys;

        if ( empty($_POST['order_id']) ) {
            self::output_status_on_error($status, __('Order ID not received', 'hrx-delivery'), true);
        }

        $wc_order = self::get_wc_order(esc_attr($_POST['order_id']));

        $hrx_order_id = $wc_order->get_meta($meta_keys->order_id);
        if ( empty($hrx_order_id) ) {
            self::output_status_on_error($status, __('Failed to get HRX order ID from order', 'hrx-delivery'), true);
        }

        $mark_ready = (! empty($_POST['ready'])) ? filter_var(esc_attr($_POST['ready']), FILTER_VALIDATE_BOOLEAN) : true;

        $api = new Api();
        
        if ( $mark_ready ) {
            $result = $api->ready_order($hrx_order_id, true);
        } else {
            $result = $api->ready_order($hrx_order_id, false);
        }

        if ( $result['status'] == 'OK' ) {
            if ( ! empty($result['data']['status']) ) {
                update_post_meta($wc_order->get_id(), $meta_keys->order_status, esc_attr($result['data']['status']));
            } else {
                update_post_meta($wc_order->get_id(), $meta_keys->order_status, 'unknown');
            }

            $status['status'] = 'OK';
            $status['msg'] = __('Order status changed successfully', 'hrx-delivery');
        } else {
            $status['msg'] = __('Failed to change HRX order status', 'hrx-delivery') . ":\n" . $result['msg'];
            $classOrder = new Order();
            $classOrder->update_hrx_order_info($wc_order);
        }

        echo json_encode($status);
        wp_die();
    }

    public static function admin_btn_table_mass_action()
    {
        $status = array(
            'status' => 'error',
            'msg' => '',
            'file' => '',
        );
        $meta_keys = Core::get_instance()->meta_keys;

        if ( empty($_POST['mass_action']) ) {
            self::output_status_on_error($status, __('Failed to get what action to perform', 'hrx-delivery'), true);
        }
        $action = esc_attr($_POST['mass_action']);

        if ( empty($_POST['selected_orders']) ) {
            self::output_status_on_error($status, __('Failed to get list of selected orders', 'hrx-delivery'));
        }
        $selected_orders = array_map('esc_attr', $_POST['selected_orders']);

        $received_files = array();

        if ( $action == 'manifest' ) {
            //TODO: Manifest - Do it if need it
        }

        if ( $action == 'shipping_label' ) {
            $output_file = Label::get_merged_file($selected_orders, 'shipping', $action . 's.pdf');
        }

        if ( $action == 'return_label' ) {
            $output_file = Label::get_merged_file($selected_orders, 'return', $action . 's.pdf');
        }
        
        if ( $output_file['status'] == 'OK' ) {
            $status['status'] = 'OK';
            $status['file'] = $output_file['file']['url'];
        } else {
            $status['msg'] = $output_file['msg'];
        }

        echo json_encode($status);
        wp_die();
    }

    private static function get_wc_order( $order_id )
    {
        $wc_order = wc_get_order($order_id);

        if ( empty($wc_order) ) {
            self::output_status_on_error($status, __('Failed to get order information', 'hrx-delivery'), true);
        }

        return $wc_order;
    }

    private static function output_status_on_error( $status, $message = '', $add_programmer_note = false )
    {
        if ( $status['status'] != 'error' ) {
            $status['status'] = 'error';
        }

        if ( ! empty($message) ) {
            $need_programmer_note = ".\n" . __('Need a programmer to check this problem', 'hrx-delivery') . '.';
            if ( $add_programmer_note ) {
                $message .= $need_programmer_note;
            }
            
            $status['msg'] = $message;
        }
            
        echo json_encode($status);
        wp_die();
    }

    private static function fix_phone( $phone, $prefix )
    {
        if ( substr($phone, 0, strlen($prefix)) === $prefix ) {
            $phone = substr($phone, strlen($prefix));
        }

        return $phone;
    }
}
