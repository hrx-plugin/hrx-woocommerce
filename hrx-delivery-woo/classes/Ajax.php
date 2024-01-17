<?php
namespace HrxDeliveryWoo;

// Prevent direct access to this script
if ( ! defined('ABSPATH') ) {
    exit;
}

use HrxDeliveryWoo\Api;
use HrxDeliveryWoo\Helper;
use HrxDeliveryWoo\Terminal;
use HrxDeliveryWoo\Warehouse;
use HrxDeliveryWoo\LocationsDelivery;
use HrxDeliveryWoo\Label;
use HrxDeliveryWoo\Shipment;
use HrxDeliveryWoo\Debug;
use HrxDeliveryWoo\WcOrder;
use HrxDeliveryWoo\WcTools;

class Ajax
{
    /**
     * Register Ajax functions
     * @since 1.0.0
     */
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
        add_action('wp_ajax_hrx_get_wc_order_data', $this_class . 'admin_hrx_get_wc_order_data');
        add_action('wp_ajax_hrx_dev_action_cron_delivery_locs', $this_class . 'admin_btn_execute_dev_tool_action');
    }

    /**
     * Save the selected terminal in the session data
     * @since 1.0.0
     */
    public static function front_add_terminal_to_session()
    {
        if ( ! empty($_POST['terminal_id']) ) {
            $wcTools = new WcTools();
            $wcTools->set_session('terminal', $_POST['terminal_id']);
        }

        wp_die();
    }

    /**
     * Check if API token is good
     * @since 1.0.0
     */
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

    /**
     * Update delivery locations
     * @since 1.0.0
     */
    public static function admin_btn_update_delivery_locations()
    {
        $action = (! empty($_POST['command'])) ? esc_attr($_POST['command']) : 'failed';

        $action_parts = explode('_', $action);

        if ( $action_parts[0] == 'failed' ) {
            echo json_encode(array(
                'status' => 'error',
                'next_action' => 'failed',
                'time' => current_time("Y-m-d H:i:s"),
                'msg' => __('Failed to get command', 'hrx-delivery'),
                'repeat' => false,
            ));
            wp_die();
        }

        if ( $action_parts[0] == 'init' ) {
            echo json_encode(array(
                'status' => 'OK',
                'next_action' => 'couriers_get',
                'time' => current_time("Y-m-d H:i:s"),
                'msg' => __('Downloading courier locations...', 'hrx-delivery') . ' 0',
                'repeat' => true,
            ));
            wp_die();
        }

        $total_couriers = 0;
        if ( $action_parts[0] == 'couriers' && $action_parts[1] == 'get' ) {
            $result = LocationsDelivery::update_couriers();

            $output = array(
                'status' => $result['status'],
                'next_action' => 'error',
                'time' => current_time("Y-m-d H:i:s"),
                'msg' => (! empty($result['msg'])) ? $result['msg'] : __('Downloading courier locations...', 'hrx-delivery'),
                'total' => 0,
                'repeat' => false,
            );

            if ( $result['status'] == 'OK' ) {
                $output['next_action'] = 'couriers_got';
                $output['total'] = $result['total'];
                $output['repeat'] = true;
            }

            echo json_encode($output);
            wp_die();
        }

        if ( $action_parts[0] == 'couriers' && $action_parts[1] == 'got' ) {
            echo json_encode(array(
                'status' => 'OK',
                'next_action' => 'couriers_save',
                'time' => current_time("Y-m-d H:i:s"),
                'msg' => __('Saving courier locations...', 'hrx-delivery') . ' 100%',
                'repeat' => true
            ));
            wp_die();
        }

        if ( $action_parts[0] == 'couriers' && $action_parts[1] == 'save' ) {
            echo json_encode(array(
                'status' => 'OK',
                'next_action' => 'terminals_get',
                'time' => current_time("Y-m-d H:i:s"),
                'msg' => __('Downloading parcel terminal locations...', 'hrx-delivery') . ' 0',
                'repeat' => true
            ));
            wp_die();
        }

        if ( $action_parts[0] == 'terminals' && $action_parts[1] == 'get' ) {
            $page = $action_parts[2] ?? 1;
            $result = LocationsDelivery::update($page);

            $output = array(
                'status' => $result['status'],
                'next_action' => 'error',
                'time' => current_time("Y-m-d H:i:s"),
                'msg' => (! empty($result['msg'])) ? $result['msg'] : __('Downloading parcel terminal locations...', 'hrx-delivery'),
                'total' => 0,
                'repeat' => false,
            );
            if ( $result['status'] == 'OK' ) {
                $next_action = ($result['total'] < LocationsDelivery::$download_per_page) ? 'terminals_got' : 'terminals_get_' . ($page + 1);
                $output['next_action'] = $next_action;
                $output['total'] = $result['total'] - $result['failed'];
                $output['repeat'] = true;
            }

            echo json_encode($output);
            wp_die();
        }

        if ( $action_parts[0] == 'terminals' && $action_parts[1] == 'got' ) {
            $result = LocationsDelivery::prepare_locations_save('terminal');

            echo json_encode(array(
                'status' => $result['status'],
                'next_action' => 'terminals_save',
                'time' => current_time("Y-m-d H:i:s"),
                'msg' => (! empty($result['msg'])) ? $result['msg'] : __('Saving parcel terminal locations...', 'hrx-delivery') . ' 0%',
                'repeat' => true
            ));
            wp_die();
        }

        if ( $action_parts[0] == 'terminals' && $action_parts[1] == 'save' ) {
            $page = $action_parts[2] ?? 1;
            $type = 'terminal';
            $total_locations = LocationsDelivery::calc_downloaded_locations($type);
            $result = LocationsDelivery::save_downloaded_locations($type, $page);
            
            $total_saved = LocationsDelivery::$save_per_page * ($page - 1);
            $total_saved += $result['added'] + $result['updated'];
            $percent = $total_saved / $total_locations * 100;

            if ( LocationsDelivery::$save_per_page * $page > $total_locations ) {
                echo json_encode(array(
                    'status' => 'OK',
                    'next_action' => 'finish',
                    'time' => current_time("Y-m-d H:i:s"),
                    'msg' => __('Saving parcel terminal locations...', 'hrx-delivery') . ' 100%',
                    'repeat' => true,
                ));
                wp_die();
            }

            echo json_encode(array(
                'status' => $result['status'],
                'next_action' => 'terminals_save_' . ($page + 1),
                'time' => current_time("Y-m-d H:i:s"),
                'msg' => (! empty($result['msg'])) ? $result['msg'] : __('Saving parcel terminal locations...', 'hrx-delivery') . ' ' . number_format((float) $percent, 2, '.', '') . '%',
                'repeat' => true,
            ));
            wp_die();
        }

        if ( $action_parts[0] == 'finish' ) {
            echo json_encode(array(
                'status' => 'OK',
                'next_action' => 'finish',
                'time' => current_time("Y-m-d H:i:s"),
                'msg' => __('The locations update is complete', 'hrx-delivery'),
                'repeat' => false,
            ));
            wp_die();
        }

        echo json_encode(array(
            'status' => 'error',
            'next_action' => 'failed',
            'time' => current_time("Y-m-d H:i:s"),
            'msg' => __('Neither command was suitable', 'hrx-delivery'),
            'repeat' => false
        ));
        wp_die();
    }

    /**
     * Update pickup locations (Warehouses)
     * @since 1.0.0
     */
    public static function admin_btn_update_pickup_locations()
    {
        Debug::to_log('Pickup locations update.');

        $result = Warehouse::update_pickup_locations();

        $current_time = current_time("Y-m-d H:i:s");
        $output = self::get_location_result_output($result, $current_time);
        $output['action'] = 'finish';
        $output['msg'] = sprintf(__('The update is complete in %s', 'hrx-delivery'), $current_time);

        echo json_encode($output);
        wp_die();
    }

    /**
     * Get the information about locations update in formatted text
     * @since 1.0.0
     * 
     * @param (array) $result - Statistic for updated data
     * @param (string) $current_time - Formatted current time
     * @return (string) - Formatted informational text
     */
    private static function get_location_result_output( $result, $current_time )
    {
        $output = array(
            'status' => 'error',
            'action' => '',
            'msg' => '',
        );

        if ( $result['status'] == 'error' ) {
            $msg = __('Request error', 'hrx-delivery') . ' - ' . $result['msg'];
            if ( ! empty($result['added']) || ! empty($result['updated']) ) {
                $msg = $current_time . '. ' . $msg;
            }
            Debug::to_log($result, 'locations');
        } else {
            $output['status'] = 'OK';
        }

        $output['msg'] = $msg;

        return $output;
    }

    /**
     * Change default warehouse
     * @since 1.0.0
     */
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

    /**
     * Register HRX order
     * @since 1.0.0
     */
    public static function admin_btn_create_hrx_order()
    {
        $status = self::prepare_status();

        if ( empty($_POST['order_id']) ) {
            self::output_status_on_error($status, __('Order ID not received', 'hrx-delivery'), true);
        }

        $status = Shipment::register_order(esc_attr($_POST['order_id']), true);
        
        echo json_encode($status);
        wp_die();
    }

    /**
     * Get label for HRX order
     * @since 1.0.0
     */
    public static function admin_btn_get_hrx_label()
    {
        $status = self::prepare_status();

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

    /**
     * Mark HRX order as Ready
     * @since 1.0.0
     */
    public static function admin_btn_ready_hrx_order()
    {
        $status = self::prepare_status();

        if ( empty($_POST['order_id']) ) {
            self::output_status_on_error($status, __('Order ID not received', 'hrx-delivery'), true);
        }

        $wc_order_id = esc_attr($_POST['order_id']);
        $mark_ready = (! empty($_POST['ready'])) ? filter_var(esc_attr($_POST['ready']), FILTER_VALIDATE_BOOLEAN) : true;

        $result = Shipment::ready_order($wc_order_id, (!$mark_ready));
        $status['status'] = $result['status'];
        $status['msg'] = $result['msg'];

        echo json_encode($status);
        wp_die();
    }

    /**
     * Execute mass action command
     * @since 1.0.0
     */
    public static function admin_btn_table_mass_action()
    {
        $status = self::prepare_status(array('file' => ''));
        $output_file = false;

        $wc = (object) array(
            'order' => new WcOrder(),
            'tools' => new WcTools(),
        );

        if ( empty($_POST['mass_action']) ) {
            self::output_status_on_error($status, __('Failed to get what action to perform', 'hrx-delivery'), true);
        }
        $action = esc_attr($_POST['mass_action']);

        if ( empty($_POST['selected_orders']) ) {
            self::output_status_on_error($status, __('Failed to get list of selected orders', 'hrx-delivery'));
        }
        $selected_orders = array_map('esc_attr', $_POST['selected_orders']);

        foreach ( $selected_orders as $key => $order_id ) {
            if ( empty((int)$order_id) ) {
                unset($selected_orders[$key]);
                continue;
            }
            
            $wc_order_id = (int)$order_id;
            $wc_order = $wc->order->get_order($wc_order_id, true);
            if ( ! $wc_order ) {
                unset($selected_orders[$key]);
                continue;
            }

            $hrx_order_status = Shipment::get_status($wc_order_id);
            $wc_order_status = $wc->order->get_status($wc_order_id);

            $converted_action = Shipment::convert_allowed_order_action_from_mass($action);
            if ( ! Shipment::check_specific_allowed_order_action($converted_action, $hrx_order_status, $wc_order_status) ) {
                unset($selected_orders[$key]);
                continue;
            }
        }
        $selected_orders = array_values($selected_orders); // Reorganize the array keys

        if ( empty($selected_orders) ) {
            self::output_status_on_error($status, __('Neither order is suitable for performing the desired action', 'hrx-delivery'));
        }

        $received_files = array();

        if ( $action == 'register_orders' ) {
            $successes = array();
            foreach ( $selected_orders as $wc_order_id ) {
                $result = Shipment::register_order($wc_order_id);
                if ( $result['status'] == 'OK' ) {
                    $successes[] = '#' . $wc_order_id;
                } else {
                    $status['multi_msg']['errors'][] = sprintf(__('Failed to register order #%1$s. Error: %2$s', 'hrx-delivery'), $wc_order_id, $result['msg']);
                }
            }
            if ( ! empty($successes) ) {
                $status['status'] = 'OK';
                $status['multi_msg']['successes'][] = sprintf(__('Successfully registered orders: %s.', 'hrx-delivery'), implode(', ', $successes));
            } else {
                $status['msg'] = __('Could not register any orders', 'hrx-delivery');
            }
        }

        if ( $action == 'regenerate_orders' ) {
            $successes = array();
            foreach ( $selected_orders as $wc_order_id ) {
                $result = Shipment::register_order($wc_order_id, true);
                $result = (rand(1,2) == 1) ? array('status'=>'OK') : array('status'=>'error','msg'=>'Testine klaida');
                if ( $result['status'] == 'OK' ) {
                    $successes[] = '#' . $wc_order_id;
                } else {
                    $status['multi_msg']['errors'][] = sprintf(__('Failed to regenerate order #%1$s. Error: %2$s', 'hrx-delivery'), $wc_order_id, $result['msg']);
                }
            }
            if ( ! empty($successes) ) {
                $status['status'] = 'OK';
                $status['multi_msg']['successes'][] = sprintf(__('Successfully regenerated orders: %s.', 'hrx-delivery'), implode(', ', $successes));
            } else {
                $status['msg'] = __('Could not regenerate any orders', 'hrx-delivery');
            }
        }

        if ( $action == 'mark_ready' ) {
            $successes = array();
            foreach ( $selected_orders as $wc_order_id ) {
                $result = Shipment::ready_order($wc_order_id);
                if ( $result['status'] == 'OK' ) {
                    $successes[] = '#' . $wc_order_id;
                } else {
                    $status['multi_msg']['errors'][] = sprintf(__('Failed to ready order #%1$s. Error: %2$s', 'hrx-delivery'), $wc_order_id, $result['msg']);
                }
            }
            if ( ! empty($successes) ) {
                $status['status'] = 'OK';
                $status['multi_msg']['successes'][] = sprintf(__('Successfully marked as ready orders: %s.', 'hrx-delivery'), implode(', ', $successes));
            } else {
                $status['msg'] = __('Could not ready any orders', 'hrx-delivery');
            }
        }

        if ( $action == 'unmark_ready' ) {
            $successes = array();
            foreach ( $selected_orders as $wc_order_id ) {
                $result = Shipment::ready_order($wc_order_id, true);
                if ( $result['status'] == 'OK' ) {
                    $successes[] = '#' . $wc_order_id;
                } else {
                    $status['multi_msg']['errors'][] = sprintf(__('Failed to remove ready status from order #%1$s. Error: %2$s', 'hrx-delivery'), $wc_order_id, $result['msg']);
                }
            }
            if ( ! empty($successes) ) {
                $status['status'] = 'OK';
                $status['multi_msg']['successes'][] = sprintf(__('Successfully removed ready status from orders: %s.', 'hrx-delivery'), implode(', ', $successes));
            } else {
                $status['msg'] = __('None of the orders could be removed the ready status', 'hrx-delivery');
            }
        }

        if ( $action == 'manifest' ) {
            //TODO: Manifest - Do it if need it
        }

        if ( $action == 'shipping_label' ) {
            $output_file = Label::get_merged_file($selected_orders, 'shipping', $action . 's.pdf');
        }

        if ( $action == 'return_label' ) {
            $output_file = Label::get_merged_file($selected_orders, 'return', $action . 's.pdf');
        }
        
        if ( $output_file ) {
            if ( $output_file['status'] == 'OK' ) {
                $status['status'] = 'OK';
                $status['file'] = $output_file['file']['url'];
            } else {
                $status['msg'] = $output_file['msg'];
            }
        }

        echo json_encode($status);
        wp_die();
    }

    public static function admin_hrx_get_wc_order_data()
    {
        if ( empty($_POST['wc_order_id']) ) {
            wp_die();
        }

        $wc = (object) array(
            'order' => new WcOrder(),
            'tools' => new WcTools(),
        );

        $wc_order_id = esc_attr($_POST['wc_order_id']);
        $wc_order = $wc->order->get_order($wc_order_id, true);
        if ( ! $wc_order ) {
            wp_die();
        }

        $hrx_data = $wc->order->get_hrx_data($wc_order_id);
        $units = $wc->tools->get_units();

        $billing_address = $wc_order->get_billing_address_1();
        if ( ! empty($wc_order->get_billing_address_2()) ) {
            $billing_address .= ' - ' . $wc_order->get_billing_address_2();
        }
        $billing_city = $wc_order->get_billing_city();
        if ( ! empty($wc_order->get_billing_state()) ) {
            $billing_city .= ', ' . $wc_order->get_billing_state();
        }
        $shipping_address = $wc_order->get_shipping_address_1();
        if ( ! empty($wc_order->get_shipping_address_2()) ) {
            $shipping_address .= ' - ' . $wc_order->get_shipping_address_2();
        }
        $shipping_city = $wc_order->get_shipping_city();
        if ( ! empty($wc_order->get_shipping_state()) ) {
            $shipping_city .= ', ' . $wc_order->get_shipping_state();
        }

        $terminal_title = '—';
        if ( Helper::method_has_terminals($hrx_data->method) ) {
            $terminal_id = $hrx_data->terminal_id;
            $terminal_title = Terminal::get_name_by_id($terminal_id);
        }
        $tracking_number = $hrx_data->track_number;
        if ( empty($tracking_number) ) {
            $tracking_number = '—';
        }
        $warehouse_id = $hrx_data->warehouse_id;

        $order_dimensions = Shipment::get_dimensions($wc_order_id);
        $decimal_separator = $wc->tools->get_price_decimal_separator();
        $weight_text = number_format((float)$order_dimensions['weight'], 3, $decimal_separator, '') . ' ' . $units->weight;
        $dimensions_text = (float)$order_dimensions['width'] . '×'
            . (float)$order_dimensions['height'] . '×'
            . (float)$order_dimensions['length'] . ' ' . $units->dimension;

        $products = array();
        foreach ( $wc->order->get_items($wc_order_id) as $item_id => $item_data ) {
            $products[] = array(
                'id' => $item_data->product_id,
                'name' => $item_data->title,
                'sku' => $item_data->sku,
                'price' => wc_price($item_data->price_product),
                'qty' => $item_data->quantity,
                'total' => wc_price($item_data->price_total),
            );
        }

        $data = array(
            'wc_order' => array(
                'id' => $wc_order_id,
                'number' => $wc_order->get_order_number(),
                'status' => $wc_order->get_status(),
                'status_text' => $wc->tools->get_status_title($wc_order->get_status()),
                'payment' => array(
                    'title' => $wc_order->get_payment_method_title(),
                    'currency' => $units->currency_symbol,
                    'products' => wc_price($wc_order->get_subtotal()),
                    'shipping' => wc_price($wc_order->get_shipping_total()),
                    'tax' => wc_price($wc_order->get_total_tax()),
                    'total' => wc_price($wc_order->get_total()),
                ),
                'billing' => array(
                    'fullname' => $wc_order->get_formatted_billing_full_name(),
                    'company' => $wc_order->get_billing_company(),
                    'address' => $billing_address,
                    'city' => $billing_city,
                    'postcode' => $wc_order->get_billing_postcode(),
                    'country' => $wc_order->get_billing_country(),
                    'country_name' => $wc->tools->get_country_name($wc_order->get_billing_country()),
                    'email' => $wc_order->get_billing_email(),
                    'phone' => $wc_order->get_billing_phone(),
                ),
                'shipping' => array(
                    'fullname' => $wc_order->get_formatted_shipping_full_name(),
                    'company' => $wc_order->get_shipping_company(),
                    'address' => $shipping_address,
                    'city' => $shipping_city,
                    'postcode' => $wc_order->get_shipping_postcode(),
                    'country' => $wc_order->get_shipping_country(),
                    'country_name' => $wc->tools->get_country_name($wc_order->get_shipping_country()),
                    'phone' => $wc_order->get_shipping_phone(),
                ),
                'shipment' => array(
                    'method_title' => $wc_order->get_shipping_method(),
                    'terminal_title' => $terminal_title,
                    'tracking_number' => $tracking_number,
                    'warehouse_title' => Warehouse::get_name_by_id($warehouse_id),
                    'weight' => $weight_text,
                    'dimensions' => $dimensions_text,
                ),
                'products' => $products,
            )
        );

        echo json_encode($data);
        wp_die();
    }

    public static function admin_btn_execute_dev_tool_action()
    {
        $data = array(
            'status' => 'OK',
            'msg' => __('Action in progress', 'hrx-delivery')
        );

        Debug::launch_cron_manualy('update_delivery_locs');

        echo json_encode($data);
        wp_die();
    }

    /**
     * Prepear status object
     * @since 1.0.0
     * 
     * @param (array) $add_additional - Additional array elements for status object
     * @return (array) - Status object
     */
    private static function prepare_status( $add_additional = false )
    {
        $status = array(
            'status' => 'error',
            'msg' => '',
            'multi_msg' => array(
                'errors' => array(),
                'successes' => array(),
            ),
        );

        if ( is_array($add_additional) ) {
            foreach ( $add_additional as $key => $value ) {
                $status[$key] = $value;
            }
        }

        return $status;
    }

    /**
     * Print error message and stop execute AJAX request
     * @since 1.0.0
     * 
     * @param (array) $status - Status object
     * @param (string) $message - Message text
     * @param (boolean) $add_programmer_note - Whether to add additional text to the message
     */
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
}
