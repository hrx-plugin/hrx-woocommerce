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
use HrxDeliveryWoo\OrderHelper;
use HrxDeliveryWoo\Terminal;
use HrxDeliveryWoo\Warehouse;
use HrxDeliveryWoo\LocationsDelivery;
use HrxDeliveryWoo\Label;
use HrxDeliveryWoo\Shipment;
use HrxDeliveryWoo\Pdf;
use HrxDeliveryWoo\Debug;

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
    }

    /**
     * Save the selected terminal in the session data
     * @since 1.0.0
     */
    public static function front_add_terminal_to_session()
    {
        if ( ! empty($_POST['terminal_id']) ) {
            WC()->session->set(Core::get_instance()->id . '_terminal', $_POST['terminal_id']);
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
        $max_in_page = 250;
        $all_methods = LocationsDelivery::get_methods();

        $page = (int)esc_attr($_POST['page']);
        if ( $page < 1 ) $page = 1;

        Debug::to_log('Delivery locations update. Page: ' . $page, 'locations');

        $total_couriers = 0;
        if ( $page == 1 ) {
            $result = LocationsDelivery::update_couriers();
            $total_couriers = $result['total'];
        }
        
        $result = LocationsDelivery::update($page);

        $current_time = current_time("Y-m-d H:i:s");
        $output = self::get_location_result_output($result, $current_time);
        $output['repeat'] = false;
        $output['total'] = $result['total'] + $total_couriers;

        if ( $result['total'] >= $max_in_page ) {
            $output['repeat'] = true;
        }

        echo json_encode($output);
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
        $output = array();

        if ( $result['status'] == 'error' ) {
            $output['status'] = 'error';
            $msg = __('Request error', 'hrx-delivery') . ' - ' . $result['msg'];
            if ( ! empty($result['added']) || ! empty($result['updated']) ) {
                $msg = $current_time . '. ' . $msg;
            }
            $output['msg'] = $msg;
            Debug::to_log($result, 'locations');
        } else {
            $msg = $current_time;
            /*if ( $result['added'] > 0 ) {
                $msg .= '. ' . __('Total added', 'hrx-delivery') . ': ' . $result['added'];
            }
            if ( $result['updated'] > 0 ) {
                $msg .= '. ' . __('Total updated', 'hrx-delivery') . ': ' . $result['updated'];
            }
            if ( $result['errors'] > 0 ) {
                $msg .= '. ' . __('Total errors', 'hrx-delivery') . ': ' . $result['errors'];
            }*/
            $output['status'] = 'OK';
            $output['msg'] = $msg;
            Debug::to_log('Successful location request. Added: ' . $result['added'] . ' Updated: ' . $result['updated'] . ' Errors: ' . $result['failed'], 'locations');
        }

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
        $meta_keys = Core::get_instance()->meta_keys;

        if ( empty($_POST['order_id']) ) {
            self::output_status_on_error($status, __('Order ID not received', 'hrx-delivery'), true);
        }

        $wc_order = self::get_wc_order(esc_attr($_POST['order_id']));
        $mark_ready = (! empty($_POST['ready'])) ? filter_var(esc_attr($_POST['ready']), FILTER_VALIDATE_BOOLEAN) : true;

        $result = Shipment::ready_order($wc_order, (!$mark_ready));
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

        $meta_keys = Core::get_instance()->meta_keys;

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
            
            $wc_order = wc_get_order((int)$order_id);
            if ( ! $wc_order ) {
                unset($selected_orders[$key]);
                continue;
            }

            $hrx_order_status = Shipment::get_status($wc_order);
            $wc_order_status = OrderHelper::get_status($wc_order);

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
                $wc_order = self::get_wc_order($wc_order_id);
                $result = Shipment::ready_order($wc_order);
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
                $wc_order = self::get_wc_order($wc_order_id);
                $result = Shipment::ready_order($wc_order, true);
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

        $wc_order_id = esc_attr($_POST['wc_order_id']);
        $wc_order = wc_get_order($wc_order_id);

        if ( ! $wc_order ) {
            wp_die();
        }

        $meta_keys = Core::get_instance()->meta_keys;
        $hrx_method = $wc_order->get_meta($meta_keys->method);

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
        if ( Helper::method_has_terminals($hrx_method) ) {
            $terminal_id = $wc_order->get_meta($meta_keys->terminal_id);
            $terminal_title = Terminal::get_name_by_id($terminal_id);
        }
        $tracking_number = $wc_order->get_meta($meta_keys->track_number);
        if ( empty($tracking_number) ) {
            $tracking_number = '—';
        }
        $warehouse_id = $wc_order->get_meta($meta_keys->warehouse_id);

        $order_dimensions = Shipment::get_dimensions($wc_order);
        $decimal_separator = ( ! empty( wc_get_price_decimal_separator() ) ) ? wc_get_price_decimal_separator() : '.';
        $weight_text = number_format((float)$order_dimensions['weight'], 3, $decimal_separator, '') . ' kg';
        $dimensions_text = (float)$order_dimensions['width'] . '×'
            . (float)$order_dimensions['height'] . '×'
            . (float)$order_dimensions['length'] . ' cm';

        $products = array();
        foreach ( $wc_order->get_items() as $item_id => $item ) {
            $product = $item->get_product();
            $products[] = array(
                'id' => $item->get_product_id(),
                'name' => $item->get_name(),
                'sku' => $product->get_sku(),
                'price' => wc_price($product->get_price()),
                'qty' => $item->get_quantity(),
                'total' => wc_price($item->get_total()),
            );
        }

        $data = array(
            'wc_order' => array(
                'id' => $wc_order_id,
                'number' => $wc_order->get_order_number(),
                'status' => $wc_order->get_status(),
                'status_text' => wc_get_order_status_name($wc_order->get_status()),
                'payment' => array(
                    'title' => $wc_order->get_payment_method_title(),
                    'currency' => get_woocommerce_currency_symbol(),
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
                    'country_name' => \WC()->countries->countries[$wc_order->get_billing_country()],
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
                    'country_name' => \WC()->countries->countries[$wc_order->get_shipping_country()],
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

    /**
     * Get Woocommerce order by ID
     * @since 1.0.0
     * 
     * @param (integer) $order_id - WC Order ID
     * @return (object) - WC Order
     */
    private static function get_wc_order( $order_id )
    {
        $status = self::prepare_status();
        $wc_order = wc_get_order($order_id);

        if ( empty($wc_order) ) {
            self::output_status_on_error($status, __('Failed to get order information', 'hrx-delivery'), true);
        }

        return $wc_order;
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

    /**
     * Fix phone number
     * @since 1.0.0
     * 
     * @param (string) $phone - Phone number
     * @param (string) $prefix - Phone number prefix which need remove
     * @return (string) - Fixed phone number
     */
    private static function fix_phone( $phone, $prefix )
    {
        if ( substr($phone, 0, strlen($prefix)) === $prefix ) {
            $phone = substr($phone, strlen($prefix));
        }

        return $phone;
    }
}
