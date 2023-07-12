<?php
namespace HrxDeliveryWoo;

// Prevent direct access to this script
if ( ! defined('ABSPATH') ) {
    exit;
}

use HrxDeliveryWoo\Helper;
use HrxDeliveryWoo\OrderHtml as Html;
use HrxDeliveryWoo\OrderHelper as WcHelper;
use HrxDeliveryWoo\Api;
use HrxDeliveryWoo\Terminal;
use HrxDeliveryWoo\Warehouse;
use HrxDeliveryWoo\Label;
use HrxDeliveryWoo\Shipment;

class Order
{
    private $core;

    public function __construct()
    {
        $this->core = Core::get_instance();
    }

    public function init()
    {
        add_action('woocommerce_admin_order_data_after_shipping_address', array($this, 'display_admin_order_block'), 10, 2);
        add_action('save_post', array($this, 'save_admin_order_block'));
        add_action('admin_notices', array($this, 'show_bulk_actions_notice'), 10);
        add_action('woocommerce_order_details_after_order_table', array($this, 'display_terminal_information'), 10, 1);
        add_action('woocommerce_email_after_order_table', array($this, 'display_terminal_information'), 10, 1);

        add_filter('bulk_actions-edit-shop_order', array($this, 'register_bulk_actions'), 20);
        add_filter('handle_bulk_actions-edit-shop_order', array($this, 'handle_bulk_actions'), 20, 3);
    }

    public function display_admin_order_block( $order )
    {
        $method_key = $this->check_hrx_shipping_method($order);
        if ( ! $method_key ) {
            return;
        }

        echo Html::build_order_block_title(array(
            'title' => $this->core->title
        ));

        $has_terminal = $this->core->methods[$method_key]['has_terminals'] ?? false;
        $terminal_id = '';
        if ( $has_terminal ) {
            $terminal_id = get_post_meta($order->get_id(), $this->core->meta_keys->terminal_id, true);
        }

        $current_warehouse_id = get_post_meta($order->get_id(), $this->core->meta_keys->warehouse_id, true);
        if ( empty($current_warehouse_id) ) {
            $current_warehouse_id = Warehouse::get_default_id();
        }

        $order_weight = $this->get_order_weight($order);
        $order_size = Shipment::get_dimensions($order);

        $tracking_number = $this->get_track_number($order);
        $hrx_order_status = Shipment::get_status($order);
        $hrx_order_status_text = Shipment::get_status_title($hrx_order_status);

        $no_more_editable = false;
        if ( $hrx_order_status == 'ready' || $hrx_order_status == 'in_delivery' || $hrx_order_status == 'in_return' ) {
            $no_more_editable = true;
        }

        echo Html::build_order_block_preview(array(
            'method' => $method_key,
            'status' => $hrx_order_status_text,
            'has_terminals' => $has_terminal,
            'terminal_id' => $terminal_id,
            'warehouse_id' => $current_warehouse_id,
            'tracking_number' => $tracking_number,
            'weight' => (! empty($order_size['weight'])) ? $order_size['weight'] : $order_weight,
            'size' => $order_size,
        ));

        echo Html::build_order_block_edit(array(
            'method' => $method_key,
            'has_terminals' => $has_terminal,
            'selected_terminal' => $terminal_id,
            'all_terminals' => LocationsDelivery::get_list($method_key, $this->get_order_country($order)),
            'selected_warehouse' => $current_warehouse_id,
            'all_warehouses' => Warehouse::get_list(),
            'tracking_number' => $tracking_number,
            'weight' => (! empty($order_size['weight'])) ? $order_size['weight'] : $order_weight,
            'size' => $order_size,
            'all_disabled' => $no_more_editable,
        ));
    }

    public function save_admin_order_block( $post_id )
    {
        if ( ! WcHelper::is_order_page() ) {
            return $post_id;
        }

        if ( isset($_POST['hrx_terminal']) ) {
            update_post_meta($post_id, $this->core->meta_keys->terminal_id, wc_clean($_POST['hrx_terminal']));
        }

        if ( isset($_POST['hrx_warehouse']) ) {
            update_post_meta($post_id, $this->core->meta_keys->warehouse_id, wc_clean($_POST['hrx_warehouse']));
        }

        if ( isset($_POST['hrx_dimensions']) ) {
            update_post_meta($post_id, $this->core->meta_keys->dimensions, $_POST['hrx_dimensions']);
        }
    }

    public function register_bulk_actions( $bulk_actions )
    {
        global $wp_version;

        $grouped = (version_compare($wp_version, '5.6.0', '>=')) ? true : false;
        $actions = array(
            'ship_labels' => __('Print shipping labels', 'omnivalt'),
            'return_labels' => __('Print return labels', 'omnivalt'),
        );

        foreach ( $actions as $action_key => $action_title ) {
            if ( $grouped ) {
                $bulk_actions[$this->core->title][$this->core->id . '_' . $action_key] = $action_title;
            } else {
                $bulk_actions[$this->core->id . '_' . $action_key] = $this->core->title . ': ' . $action_title;
            }
        }
        
        return $bulk_actions;
    }

    public function handle_bulk_actions( $redirect_to, $action, $ids )
    {
        $redirect_to = remove_query_arg('hrx_error', $redirect_to);
        $redirect_to = remove_query_arg('hrx_ids', $redirect_to);

        if ( $action == $this->core->id . '_ship_labels' || $action == $this->core->id . '_return_labels' ) {
            $labels_type = ($action == $this->core->id . '_return_labels') ? 'return' : 'shipping';
            $result = Label::get_merged_file($ids, $labels_type, 'shipping_labels');

            if ( $result['status'] == 'OK' ) {
                header('Content-Description: File Transfer');
                header('Content-Type: text/plain');
                header('Content-Disposition: attachment; filename=' . $result['file']['name']); 
                header('Expires: 0');
                header('Cache-Control: must-revalidate');
                header('Pragma: public');
                ob_clean();
                flush();
                readfile($result['file']['url']);
                exit;
                return 0;
            } else {
                $url_params = array(
                    'hrx_error' => 'print_labels'
                );
                if ( ! empty($result['data']) ) {
                    $url_params['hrx_ids'] = implode(',', $result['data']);
                }
                return add_query_arg($url_params, $redirect_to);
            }
        }

        return $redirect_to;
    }

    public function show_bulk_actions_notice()
    {
        if ( empty($_REQUEST['hrx_error']) ) return;

        $message = __('Unknown error', 'hrx-delivery');

        if ( $_REQUEST['hrx_error'] == 'print_labels' ) {
            $message = __('Failed to print labels', 'hrx-delivery') . '. ' . sprintf(__('You can get more information about the errors on the "%s" page', 'hrx-delivery'), _x('HRX delivery', 'Page title', 'hrx-delivery')) . '.';
            if ( ! empty($_REQUEST['hrx_ids']) ) {
                $message .= '<br/>' . __('Problems with orders', 'hrx-delivery') . ': ' . str_replace(',', ', ', esc_html($_REQUEST['hrx_ids']));
            }
        }

        echo Helper::build_admin_message($message, 'error', $this->core->title);
    }

    public function get_order_weight( $order )
    {
        $total_weight = 0;

        foreach ( $order->get_items() as $item_id => $item ) {
            $qty = (int)$item->get_quantity();
            $prod = $item->get_product();
            $prod_weight = (float)$prod->get_weight();
            $total_weight += floatval($prod_weight * $qty);
        }

        return wc_get_weight($total_weight, 'kg');
    }

    public function get_order_products_dimensions( $order )
    {
        $products_dimensions = array();

        $counter = 0;
        foreach ( $order->get_items() as $item_id => $item ) {
            $counter++;
            $qty = (int)$item->get_quantity();
            $prod = $item->get_product();

            $prod_dims = array(
                'length' => 0,
                'width' => 0,
                'height' => 0,
                'weight' => wc_get_weight((float)$prod->get_weight(), 'kg'),
            );

            if ( $prod->has_dimensions() ) {
                $prod_dims['length'] = (float)$prod->get_length();
                $prod_dims['width'] = (float)$prod->get_width();
                $prod_dims['height'] = (float)$prod->get_height();
            }

            $products_dimensions[$counter . '_' . $item_id] = $prod_dims;
        }

        return $products_dimensions;
    }

    public function get_billing_fullname( $order )
    {
        return $order->get_billing_first_name() . ' ' . $order->get_billing_last_name();
    }

    public function get_shipping_fullname( $order )
    {
        return $order->get_shipping_first_name() . ' ' . $order->get_shipping_last_name();
    }

    public function get_order_country( $order, $default = 'LT' )
    {
        $country = $order->get_shipping_country();
        if ( empty($country) ) {
            $country = $order->get_billing_country();
        }

        return (! empty($country)) ? $country : $default;
    }

    public function get_order_address( $order )
    {
        $address = $order->get_address('shipping');
        if ( empty($address['address_1']) && empty($address['city']) && empty($address['postcode']) ) {
            $address = $order->get_address();
        }

        if ( empty($address['phone']) && ! empty($order->get_billing_phone()) ) {
            $address['phone'] = $order->get_billing_phone();
        }

        return $address;
    }

    public function get_track_number( $order, $tracking_type = 'shipping' )
    {
        $hrx_track_number = '';
        $hrx_track_url = '';

        if ( $tracking_type == 'shipping' ) {
            $hrx_track_number = $order->get_meta($this->core->meta_keys->track_number);
            
            if ( empty($hrx_track_number) ) {
                $updated_order = $this->update_hrx_order_info($order);
                $hrx_track_number = $updated_order['track_number'];
            }
        }

        if ( $tracking_type == 'return' ) {
            //TODO: Do if there will be
        }

        return $hrx_track_number;
    }

    public function update_hrx_order_info( $order )
    {
        $hrx_order_data = array(
            'track_number' => '',
            'track_url' => '',
            'status' => 'unknown',
        );

        $hrx_order_id = $order->get_meta($this->core->meta_keys->order_id);

        if ( ! empty($hrx_order_id) ) {
            $api = new Api();
            $hrx_order = $api->get_order($hrx_order_id);

            if ( $hrx_order['status'] == 'OK' ) {
                if ( ! empty($hrx_order['data']['tracking_number']) ) {
                    $hrx_order_data['track_number'] = $hrx_order['data']['tracking_number'];
                }
                if ( ! empty($hrx_order['data']['track_url']) ) {
                    $hrx_order_data['track_url'] = $hrx_order['data']['tracking_url'];
                }
                if ( ! empty($hrx_order['data']['status']) ) {
                    $hrx_order_data['status'] = $hrx_order['data']['status'];
                }

                update_post_meta($order->get_id(), $this->core->meta_keys->track_number, wc_clean($hrx_order_data['track_number']));
                update_post_meta($order->get_id(), $this->core->meta_keys->track_url, esc_url($hrx_order_data['track_url']));
                update_post_meta($order->get_id(), $this->core->meta_keys->order_status, wc_clean($hrx_order_data['status']));
            }
        }

        return $hrx_order_data;
    }

    public function get_track_url( $order )
    {
        return $order->get_meta($this->core->meta_keys->track_url);
    }

    public function get_formated_status( $order )
    {
        $order_status = $order->get_status();
        $order_status_name = wc_get_order_status_name($order_status);

        return '<mark class="order-status status-' . $order_status . '"><span>' . $order_status_name . '</span></mark>';
    }

    public function display_terminal_information( $order )
    {
        $html_rows = array();

        $method_key = $this->check_hrx_shipping_method($order);
        if ( ! $method_key ) {
            return;
        }

        $has_terminal = $this->core->methods[$method_key]['has_terminals'] ?? false;
        $terminal_id = '';
        if ( $has_terminal ) {
            $terminal_id = get_post_meta($order->get_id(), $this->core->meta_keys->terminal_id, true);
        }

        if ( ! empty($terminal_id) ) {
            $html_rows[] = '<b>' . sprintf(__('Selected %s', 'hrx-delivery'), $this->core->methods[$method_key]['front_title']) . ':</b><br/>' . Terminal::get_name_by_id($terminal_id);
        }

        $tracking_number = $this->get_track_number($order);
        $tracking_url = $this->get_track_url($order);

        if ( ! empty($tracking_number) ) {
            $tracking_html = '<b>' . __('You can track your shipment with this number', 'hrx-delivery') . ':</b><br/>';
            if ( ! empty($tracking_url) ) {
                $tracking_html .= '<a href="' . esc_html($tracking_url) . '" target="_blank">' . $tracking_number . '</a>';
            } else {
                $tracking_html .= $tracking_number;
            }
            $html_rows[] = $tracking_html;
        }

        echo (! empty($html_rows)) ? '<p class="hrx-info">' . implode('<br/>', $html_rows) . '</p>' : '';
    }

    private function check_hrx_shipping_method( $admin_order )
    {
        $all_shipping_methods = array();
        $wc_order = wc_get_order((int)$admin_order->get_id());

        foreach ( $wc_order->get_items('shipping') as $item_id => $shipping_item_obj ) {
            $all_shipping_methods[] = $shipping_item_obj->get_method_id();
        }

        $shipping_method = Helper::get_first_value_from_array($all_shipping_methods);

        if ( $shipping_method != $this->core->id ) {
            return false;
        }

        return get_post_meta($admin_order->get_id(), $this->core->meta_keys->method, true);
    }
}
