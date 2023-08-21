<?php
namespace HrxDeliveryWoo;

// Prevent direct access to this script
if ( ! defined('ABSPATH') ) {
    exit;
}

use HrxDeliveryWoo\Core;
use HrxDeliveryWoo\Helper;
use HrxDeliveryWoo\OrderHtml as Html;
use HrxDeliveryWoo\Api;
use HrxDeliveryWoo\Terminal;
use HrxDeliveryWoo\Warehouse;
use HrxDeliveryWoo\Label;
use HrxDeliveryWoo\Shipment;
use HrxDeliveryWoo\WcOrder;
use HrxDeliveryWoo\WcTools;
use HrxDeliveryWoo\WcCustom;

class Order
{
    private $core;
    private $wc;

    public function __construct()
    {
        $this->core = Core::get_instance();
        $this->wc = (object) array(
            'order' => new WcOrder(),
            'tools' => new WcTools(),
            'custom' => new WcCustom(),
        );
    }

    public function init()
    {
        add_action('woocommerce_admin_order_data_after_shipping_address', array($this, 'display_admin_order_block'), 10, 2);
        add_action('save_post', array($this, 'save_admin_order_block'));
        add_action('woocommerce_update_order', array($this, 'save_admin_order_block_hpos'));
        add_action('admin_notices', array($this, 'show_bulk_actions_notice'), 10);
        add_action('woocommerce_order_details_after_order_table', array($this, 'display_terminal_information'), 10, 1);
        add_action('woocommerce_email_after_order_table', array($this, 'display_terminal_information'), 10, 1);

        add_filter('bulk_actions-edit-shop_order', array($this, 'register_bulk_actions'), 20);
        add_filter('handle_bulk_actions-edit-shop_order', array($this, 'handle_bulk_actions'), 20, 3);
        add_filter('bulk_actions-woocommerce_page_wc-orders', array($this, 'register_bulk_actions'), 20); //HPOS
        add_filter('handle_bulk_actions-woocommerce_page_wc-orders', array($this, 'handle_bulk_actions'), 20, 3); //HPOS
    }

    public function display_admin_order_block( $order )
    {    
        $method_key = $this->check_hrx_shipping_method($order->get_id());
        if ( ! $method_key ) {
            return;
        }

        $this->wc->order->set_tmp_order($order); //Cache the order to avoid unnecessary database access

        $hrx_data = $this->wc->order->get_hrx_data($order->get_id());

        echo Html::build_order_block_title(array(
            'title' => $this->core->title
        ));

        $has_terminal = $this->core->methods[$method_key]['has_terminals'] ?? false;
        $terminal_id = '';
        if ( $has_terminal ) {
            $terminal_id = $hrx_data->terminal_id;
        }

        $current_warehouse_id = $hrx_data->warehouse_id;
        if ( empty($current_warehouse_id) ) {
            $current_warehouse_id = Warehouse::get_default_id();
        }

        $order_weight = $this->wc->order->count_total_weight($order->get_id());
        $order_size = Shipment::get_dimensions($order->get_id());
        $preview_order_weight = $this->wc->tools->convert_weight($order_weight, 'kg');
        $preview_order_size = $this->wc->custom->convert_all_dimensions($order_size, 'kg', 'cm');

        $tracking_number = $this->get_track_number($order->get_id());
        $hrx_order_status = Shipment::get_status($order->get_id());
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
            'weight' => (! empty($preview_order_size['weight'])) ? $preview_order_size['weight'] : $preview_order_weight,
            'size' => $preview_order_size,
        ));

        echo Html::build_order_block_edit(array(
            'method' => $method_key,
            'has_terminals' => $has_terminal,
            'selected_terminal' => $terminal_id,
            'all_terminals' => LocationsDelivery::get_list($method_key, $this->wc->custom->get_order_country($order)),
            'selected_warehouse' => $current_warehouse_id,
            'all_warehouses' => Warehouse::get_list(),
            'tracking_number' => $tracking_number,
            'weight' => (! empty($order_size['weight'])) ? $order_size['weight'] : $order_weight,
            'size' => $order_size,
            'units' => $this->wc->tools->get_units(),
            'all_disabled' => $no_more_editable,
        ));

        $this->wc->order->set_tmp_order(); //Clear order cache
    }

    public function save_admin_order_block( $post_id )
    {
        if ( ! is_admin() || ! $this->wc->tools->is_available_screen('admin_order_edit') ) {
            return $post_id;
        }

        if ( isset($_POST['hrx_terminal']) ) {
            $this->wc->order->update_meta($post_id, $this->core->meta_keys->terminal_id, $_POST['hrx_terminal'], true);
        }

        if ( isset($_POST['hrx_warehouse']) ) {
            $this->wc->order->update_meta($post_id, $this->core->meta_keys->warehouse_id, $_POST['hrx_warehouse'], true);
        }

        if ( isset($_POST['hrx_dimensions']) ) {
            $this->wc->order->update_meta($post_id, $this->core->meta_keys->dimensions, $_POST['hrx_dimensions']);
        }
    }

    public function save_admin_order_block_hpos( $post_id )
    {
        if ( ! is_admin() || ! $this->wc->tools->is_available_screen('admin_order_edit') ) {
            return $post_id;
        }

        remove_action('woocommerce_update_order', array($this, 'save_admin_order_block_hpos')); //Temporary fix to avoid infinity loop

        $this->save_admin_order_block($post_id);

        add_action('woocommerce_update_order', array($this, 'save_admin_order_block_hpos')); //Restore hook
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

    public function get_track_number( $order_id, $tracking_type = 'shipping' )
    {
        $hrx_track_number = '';
        $hrx_track_url = '';

        if ( $tracking_type == 'shipping' ) {
            $hrx_track_number = $this->wc->order->get_meta($order_id, $this->core->meta_keys->track_number);
            
            if ( empty($hrx_track_number) ) {
                $updated_order = $this->update_hrx_order_info($order_id);
                $hrx_track_number = $updated_order['track_number'];
            }
        }

        if ( $tracking_type == 'return' ) {
            //TODO: Do if there will be
        }

        return $hrx_track_number;
    }

    public function update_hrx_order_info( $order_id )
    {
        $hrx_order_data = array(
            'track_number' => '',
            'track_url' => '',
            'status' => 'unknown',
        );

        $this->wc->order->load_order($order_id, true); //Cache the order to avoid unnecessary database access
     
        $hrx_order_id = $this->wc->order->get_meta($order_id, $this->core->meta_keys->order_id);

        if ( ! empty($hrx_order_id) ) {
            $api = new Api();
            $hrx_order = $api->get_order($hrx_order_id);

            if ( $hrx_order['status'] == 'OK' ) {
                if ( ! empty($hrx_order['data']['tracking_number']) ) {
                    $hrx_order_data['track_number'] = $hrx_order['data']['tracking_number'];
                }
                if ( ! empty($hrx_order['data']['tracking_url']) ) {
                    $hrx_order_data['track_url'] = $hrx_order['data']['tracking_url'];
                }
                if ( ! empty($hrx_order['data']['status']) ) {
                    $hrx_order_data['status'] = $hrx_order['data']['status'];
                }

                $this->wc->order->update_hrx_data($order_id, array(
                    'track_number' => $this->wc->tools->clean($hrx_order_data['track_number']),
                    'track_url' => esc_url($hrx_order_data['track_url']),
                    'hrx_order_status' => $this->wc->tools->clean($hrx_order_data['status']),
                ));
            }
        }

        $this->wc->order->set_tmp_order(); //Clear order cache

        return $hrx_order_data;
    }

    public function display_terminal_information( $order )
    {
        $this->wc->order->set_tmp_order($order); //Cache the order to avoid unnecessary database access
        $html_rows = array();

        $method_key = $this->check_hrx_shipping_method($order->get_id());
        if ( ! $method_key ) {
            return;
        }

        $hrx_data = $this->wc->order->get_hrx_data($order->get_id());

        $has_terminal = $this->core->methods[$method_key]['has_terminals'] ?? false;
        $terminal_id = '';
        if ( $has_terminal ) {
            $terminal_id = $hrx_data->terminal_id;
        }

        if ( ! empty($terminal_id) ) {
            $html_rows[] = '<b>' . sprintf(__('Selected %s', 'hrx-delivery'), $this->core->methods[$method_key]['front_title']) . ':</b><br/>' . Terminal::get_name_by_id($terminal_id);
        }

        $tracking_number = $this->get_track_number($order->get_id());
        $tracking_url = $hrx_data->track_url;

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

        $this->wc->order->set_tmp_order(); //Clear order cache
    }

    private function check_hrx_shipping_method( $order_id )
    {
        $all_shipping_methods = $this->wc->order->get_shipping_methods($order_id);
        $shipping_method = Helper::get_first_value_from_array($all_shipping_methods);

        if ( $shipping_method != $this->core->id ) {
            return false;
        }

        return $this->wc->order->get_meta($order_id, $this->core->meta_keys->method);
    }

    public function get_order_weight( $order )
    {
        trigger_error('Method ' . __METHOD__ . ' is deprecated', E_USER_DEPRECATED);
        return 0;
    }

    public function get_order_products_dimensions( $order )
    {
        trigger_error('Method ' . __METHOD__ . ' is deprecated', E_USER_DEPRECATED);
        return array();
    }
}
