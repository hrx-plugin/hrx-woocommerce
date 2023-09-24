<?php
namespace HrxDeliveryWoo;

// Prevent direct access to this script
if ( ! defined('ABSPATH') ) {
    exit;
}

use HrxDeliveryWoo\Core;
use HrxDeliveryWoo\Helper;
use HrxDeliveryWoo\Debug;
use HrxDeliveryWoo\Sql;
use HrxDeliveryWoo\PagesHtml as Html;
use HrxDeliveryWoo\Order;
use HrxDeliveryWoo\Shipment;
use HrxDeliveryWoo\WcOrder;
use HrxDeliveryWoo\WcTools;
use HrxDeliveryWoo\WcCustom;

class Pages
{
    private $core;
    private $wc;
    private $default_per_page = 25;

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
        if ( ! is_admin() ) {
            return;
        }

        add_action('admin_enqueue_scripts', array($this, 'load_scripts'));
        add_action('admin_menu', array($this, 'register_menu_pages'));

        add_filter('woocommerce_order_data_store_cpt_get_orders_query', array($this, 'handle_custom_query_vars'), 10, 2);
    }

    public function build_page_id( $page_key )
    {
        return $this->core->option_prefix . '-' . $page_key;
    }

    public function register_menu_pages()
    {
        foreach ( $this->get_subpages() as $section => $pages ) {
            foreach ( $pages as $page_key => $page ) {
                add_submenu_page(
                    $section, // Parent slug
                    $page['title'], // Page title
                    $page['title'], // Menu title
                    $page['rights'], // Capability
                    $this->build_page_id($page_key), // Menu_slug
                    $page['output'], // Callback
                    $page['position'] // Position
                );
            }
        }
    }

    public function get_subpages( $get_specific_page = '', $get_only_basic_info = false )
    {
        $subpages = array(
            'woocommerce' => array(
                'management' => array(
                    'title' => _x('HRX delivery', 'Page title', 'hrx-delivery'),
                    'position' => 10,
                    'rights' => 'manage_woocommerce',
                    'tabs' => array(
                        'all_orders' => __('All shipments', 'hrx-delivery'),
                        'new_orders' => __('New shipments', 'hrx-delivery'),
                        'send_orders' => __('Send shipments', 'hrx-delivery'),
                        'cancelled_orders' => __('Cancelled shipments', 'hrx-delivery'),
                        //'manifests' => __('Manifests', 'hrx-delivery'),
                        'completed_orders' => __('Completed orders', 'hrx-delivery'),
                        'warehouses' => __('Warehouses', 'hrx-delivery'),
                    ),
                    'output' => array($this, 'page_management'),
                    'menu_count' => $this->page_management_count(),
                ),
            ),
        );

        if ( $get_only_basic_info ) {
            foreach ( $subpages as $section => $section_pages ) {
                foreach ( $section_pages as $page_key => $page_data ) {
                    unset($subpages[$section][$page_key]['output']);
                    unset($subpages[$section][$page_key]['rights']);
                    unset($subpages[$section][$page_key]['menu_count']);
                }
            }
        }

        if ( ! empty($get_specific_page) ) {
            foreach ( $subpages as $section => $section_pages ) {
                foreach ( $section_pages as $page_key => $page_data ) {
                    if ( $page_key == $get_specific_page ) {
                        $subpages = $subpages[$section][$page_key];
                        break 2;
                    }
                }
            }
        }

        return $subpages;
    }

    public function load_scripts( $hook )
    {
        $css_dir = $this->core->structure->css . 'admin/';
        $js_dir = $this->core->structure->js . 'admin/';

        wp_enqueue_style($this->core->id . '_pages_global', $this->core->structure->url . $css_dir . 'pages-global.css', array(), $this->core->version);

        foreach ( $this->get_subpages() as $section => $pages ) {
            foreach ( $pages as $page_key => $page ) {
                if ( $hook == $section . '_page_' . $this->build_page_id($page_key) ) {
                    $css_file_name = 'page-' . $page_key . '.css';
                    $css_script_id = $this->core->id . '_page_' . $page_key;
                    if ( file_exists($this->core->structure->path . $css_dir . $css_file_name) ) {
                        wp_enqueue_style($css_script_id, $this->core->structure->url . $css_dir . $css_file_name, array(), $this->core->version);
                    }

                    $js_file_name = 'page-' . $page_key . '.js';
                    $js_script_id = $this->core->id . '_page_' . $page_key;
                    if ( file_exists($this->core->structure->path . $js_dir . $js_file_name) ) {
                        wp_enqueue_script($js_script_id, $this->core->structure->url . $js_dir . $js_file_name, array('jquery'), $this->core->version);
                    }
                }
            }
        }
    }

    public function show_submenu_count( $submenu_section, $item_id, $count_value )
    {
        global $submenu;

        if ( ! isset($submenu[$submenu_section]) ) {
            return;
        }

        foreach ( $submenu[$submenu_section] as $key => $menu_item ) {
            if ( strpos($menu_item[2], $item_id) === 0 && ! str_contains($submenu[$submenu_section][$key][0], 'processing-count') ) {
                $submenu[$submenu_section][$key][0] .= ' <span class="awaiting-mod update-plugins count-' . esc_attr($count_value) . '"><span class="processing-count">' . number_format_i18n($count_value) . '</span></span>'; // WPCS: override ok.
                break;
            }
        }
    }

    public function handle_custom_query_vars( $query, $query_vars )
    {
        $query['meta_query'] = Pages::build_meta_query(array(), $query_vars);
        
        return $query;
    }

    public static function build_meta_query( $meta_query, $query_vars )
    {
        foreach ( Core::get_instance()->meta_keys as $meta_key ) {
            if ( ! empty($query_vars[$meta_key]) ) {
                $query_var_values = (is_array($query_vars[$meta_key])) ? $query_vars[$meta_key] : array($query_vars[$meta_key]);
                $build_subquery = array();
                foreach ( $query_var_values as $query_var ) {
                    $build_subquery[] = array(
                        'key' => $meta_key,
                        'compare' => 'LIKE',
                        'value' => $query_var,
                    );
                }
                if ( count($build_subquery) > 1 ) {
                    $build_subquery['relation'] = 'OR';
                    $meta_query[] = $build_subquery;
                } else if ( count($build_subquery) == 1 ) {
                    $meta_query[] = $build_subquery[0];
                }
            }

            if ( isset($query_vars['not_' . $meta_key]) ) {
                $query_var_values = $query_vars['not_' . $meta_key];
                if ( ! is_array($query_var_values) ) {
                    $query_var_values = array($query_var_values);
                }
                $build_query = array(
                    'relation' => 'OR',
                    array(
                        'key' => $meta_key,
                        'compare' => 'NOT EXISTS',
                    ),
                );
                $build_subquery = array();
                foreach ( $query_var_values as $query_var ) {
                    $build_subquery[] = array(
                        'key' => $meta_key,
                        'compare' => '!=',
                        'value' => $query_var,
                    );
                }
                if ( count($build_subquery) > 1 ) {
                    $build_subquery['relation'] = 'AND';
                }
                $build_query[] = $build_subquery;
                $meta_query[] = $build_query;
            }
        }

        if ( ! empty($query_vars['customer_fullname']) ) { //TODO: May not work on HPOS
            $check_keys = array(
                'ship_first' => '_shipping_first_name',
                'ship_last' => '_shipping_last_name',
                'bill_first' => '_billing_first_name',
                'bill_last' => '_billing_last_name',
            );
            $words = explode(' ', $query_vars['customer_fullname']);
            $meta_params = array();
            if ( count($words) > 1 ) {
                $meta_params['relation'] = 'AND';
                $meta_params[] = array(
                    'relation' => 'OR',
                    array(
                        'key' => $check_keys['ship_first'],
                        'compare' => 'LIKE',
                        'value' => $words[0],
                    ),
                    array(
                        'key' => $check_keys['bill_first'],
                        'compare' => 'LIKE',
                        'value' => $words[0],
                    ),
                );
                $meta_params[] = array(
                    'relation' => 'OR',
                    array(
                        'key' => $check_keys['ship_last'],
                        'compare' => 'LIKE',
                        'value' => $words[1],
                    ),
                    array(
                        'key' => $check_keys['bill_last'],
                        'compare' => 'LIKE',
                        'value' => $words[1],
                    ),
                );
            } else {
                $meta_params['relation'] = 'OR';
                foreach ( $words as $word ) {
                    foreach ( $check_keys as $check_key ) {
                        $meta_params[] = array(
                            'key' => $check_key,
                            'compare' => 'LIKE',
                            'value' => $word,
                        );
                    }
                }
            }
            $meta_query[] = $meta_params;
        }

        if ( count($meta_query) > 1 ) {
            $meta_query['relation'] = 'AND';
        }

        return $meta_query;
    }

    public function get_available_table_columns()
    {
        return array(
            'cb' => array(
                'manage' => true,
                'class' => 'check-column',
                'hide_scope' => true,
                'filter' => 'checkbox',
            ),
            'order_id' => array(
                'title' => __('ID', 'hrx-delivery'),
                'filter' => 'text',
                'filter_label' => __('Order ID', 'hrx-delivery'),
                'filter_key' => 'id',
            ),
            'customer' => array(
                'title' => __('Client', 'hrx-delivery'),
                'manage' => true,
                'filter' => 'text',
                'filter_label' => __('Client full name', 'hrx-delivery'),
                'filter_key' => 'client',
            ),
            'order_status_text' => array(
                'title' => __('Status', 'hrx-delivery'),
                'filter' => 'select',
                'filter_label' => __('Order status', 'hrx-delivery'),
                'filter_key' => 'status',
                'filter_options' => wc_get_order_statuses(),
            ),
            'order_date' => array(
                'title' => __('Order date', 'hrx-delivery'),
            ),
            'hrx_order_status' => array(
                'title' => __('HRX shipment status', 'hrx-delivery'),
                'filter' => 'text',
                'filter_title' => __('Text in status', 'hrx-delivery'),
                'filter_label' => __('Text in status', 'hrx-delivery'),
                'filter_key' => 'hrx_status',
            ),
            'track_number' => array(
                'title' => __('Tracking number', 'hrx-delivery'),
                'manage' => true,
                'filter' => 'text',
                'filter_label' => __('Shipment tracking number', 'hrx-delivery'),
                'filter_key' => 'track_no',
            ),
            'method' => array(
                'title' => __('Delivery', 'hrx-delivery'),
                'manage' => true,
            ),
            'warehouse_name' => array(
                'title' => __('Title', 'hrx-delivery'),
                'manage' => true,
                'filter' => 'text',
                'filter_label' => __('Warehouse name', 'hrx-delivery'),
                'filter_key' => 'name',
            ),
            'warehouse_id' => array(
                'title' => __('ID', 'hrx-delivery'),
                'filter' => 'text',
                'filter_label' => __('Warehouse ID', 'hrx-delivery'),
                'filter_key' => 'id',
            ),
            'country' => array(
                'title' => __('Country', 'hrx-delivery'),
                'filter' => 'select',
                'filter_label' => __('Country', 'hrx-delivery'),
                'filter_key' => 'country',
                'filter_options' => $this->wc->tools->get_all_countries(),
            ),
            'city' => array(
                'title' => __('City', 'hrx-delivery'),
                'filter' => 'text',
                'filter_label' => __('City', 'hrx-delivery'),
                'filter_key' => 'city',
            ),
            'zip' => array(
                'title' => __('Post code', 'hrx-delivery'),
                'filter' => 'text',
                'filter_label' => __('Post code', 'hrx-delivery'),
                'filter_key' => 'zip',
            ),
            'address' => array(
                'title' => __('Address', 'hrx-delivery'),
                'filter' => 'text',
                'filter_label' => __('Address', 'hrx-delivery'),
                'filter_key' => 'address',
            ),
            'selected' => array(
                'title' => __('Default warehouse', 'hrx-delivery'),
                'filter' => 'actions',
            ),
            'actions' => array(
                'title' => __('Actions', 'hrx-delivery'),
                'filter' => 'actions',
            ),
        );
    }

    private function get_method_delivery_name( $method_key, $delivery_location = '' )
    {
        if ( ! isset($this->core->methods[$method_key]) ) {
            return __('Not the HRX delivery method', 'hrx-delivery');
        }

        $method = $this->core->methods[$method_key];

        $output = '<b>' . $method['title'] . ' ' . _x('to', 'To somewhere', 'hrx-delivery') . '</b><br/>';
        $output .= (! empty( $delivery_location)) ? $delivery_location : '—';

        return $output;
    }

    private function get_order_customer_fullname( $wc_order )
    {
        return $this->wc->custom->get_customer_fullname($wc_order);
    }

    private function get_order_status_text( $wc_order )
    {
        return $this->wc->custom->get_formated_status($wc_order);
    }

    private function get_shipping_address_text( $order )
    {
        $address = $this->wc->custom->get_order_address($order);

        $output = $address['address_1'] . ', ' . $address['city'];
        if ( ! empty($address['state']) ) {
            $output .= ', ' . $address['state'];
        }
        if ( ! empty($address['postcode']) ) {
            $output .= ', ' . $address['postcode'];
        }
        $output .= ', ' . $this->wc->tools->get_country_name($address['country']);
        
        return $output;
    }

    private function get_tracking_number_text( $order, $for = 'shipping', $on_empty = '—' )
    {
        $classOrder = new Order();
        $tracking_number = $classOrder->get_track_number($order->get_id(), $for);

        return (! empty($tracking_number)) ? $tracking_number : $on_empty;
    }

    private function build_hrx_status_text( $wc_order )
    {
        $output = '';
        $hrx_data = $this->wc->order->get_hrx_data($wc_order->get_id());

        $order_status = $hrx_data->hrx_order_status;
        if ( ! empty($order_status) ) {
            $output .= Html::build_info_row('status', __('Status', 'hrx-delivery'), Shipment::get_status_title($order_status), $order_status);
        }

        $order_dimensions = Shipment::get_dimensions($wc_order->get_id());
        $order_dimensions = $this->wc->custom->convert_all_dimensions($order_dimensions, 'kg', 'cm');
        if ( ! empty($order_dimensions) ) {
            $dims_text = (float)$order_dimensions['weight'] . ' kg<br/>'
                . (float)$order_dimensions['width'] . '×'
                . (float)$order_dimensions['height'] . '×'
                . (float)$order_dimensions['length'] . ' cm';
            $output .= Html::build_info_row('dims', __('Package', 'hrx-delivery'), $dims_text);
        }

        $shipping_number = $this->get_tracking_number_text($wc_order, 'shipping', '');
        if ( ! empty($shipping_number) ) {
            $output .= Html::build_info_row('track_no', __('Tracking number', 'hrx-delivery'), $shipping_number );
        }

        $error_msg = $hrx_data->error;
        if ( ! empty($error_msg) ) {
            $output .= Html::build_info_row('error', __('Error', 'hrx-delivery'), $error_msg );
        }

        return $output;
    }

    public function default_per_page_options()
    {
        $available_values = array(10, 25, 50, 100);
        
        if ( ! in_array($this->default_per_page, $available_values)) {
            $available_values[] = $this->default_per_page;
        }
        sort($available_values);

        return $available_values;
    }

    public function get_current_per_page()
    {
        return (isset($_GET['per_page'])) ? (int) $_GET['per_page'] : $this->default_per_page;
    }

    public function page_management()
    {
        include_once($this->core->structure->path . $this->core->structure->includes . 'page-management.php');
    }

    public function page_management_count()
    {
        $args = array(
            'limit' => -1,
            'hrx_delivery_method' => array_keys($this->core->methods),
            'status' => array('wc-processing', 'wc-on-hold', 'wc-pending'),
            'not_' . $this->core->meta_keys->order_status => array('ready', 'cancelled'),
        );
        $total_orders = count($this->wc->order->get_orders($args));
        
        $this->show_submenu_count('woocommerce', $this->build_page_id('management'), $total_orders);
    }
}
