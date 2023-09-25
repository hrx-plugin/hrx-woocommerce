<?php
// Prevent direct access to this script
if ( ! defined('ABSPATH') ) {
    exit;
}

use HrxDeliveryWoo\Debug;
use HrxDeliveryWoo\Pages;
use HrxDeliveryWoo\PagesHtml;
use HrxDeliveryWoo\PagesFilter;
use HrxDeliveryWoo\Helper;
use HrxDeliveryWoo\Terminal;
use HrxDeliveryWoo\Warehouse;
use HrxDeliveryWoo\Shipment;

/* Variables */
$page_params = $this->get_subpages('management', true);
$page_title = $page_params['title'];
$page_image = $this->core->structure->url . $this->core->structure->img . 'logo.svg';
$page_tabs = $page_params['tabs'];
$page_current_tab = 'new_orders';
$page_current_no = 1;
$page_current_filters = array();
$per_page = $this->get_current_per_page();
$per_page_options = $this->default_per_page_options();
$pagination_links = false;
$all_columns = $this->get_available_table_columns();
$show_mass_buttons = array();
$all_warehouses = Warehouse::get_list();

/* Get params from URL */
if ( isset($_GET['tab']) ) {
    $page_current_tab = filter_input(INPUT_GET, 'tab');
}

if ( isset($_GET['paged']) ) {
    $page_current_no = filter_input(INPUT_GET, 'paged');
}

/* Adapt columns to current tab */
if ( $page_current_tab == 'warehouses' ) {
    $columns = array('warehouse_id', 'warehouse_name', 'country', 'city', 'zip', 'address', 'selected');

    $all_columns['country']['filter_label'] = __('Warehouse country', 'hrx-delivery');
    $all_columns['city']['filter_label'] = __('Warehouse city', 'hrx-delivery');
    $all_columns['zip']['filter_label'] = __('Warehouse post code', 'hrx-delivery');
    $all_columns['address']['filter_label'] = __('Warehouse address', 'hrx-delivery');
} else {
    $columns = array('cb', 'order_id', 'customer', 'order_status_text', 'order_date', 'method', 'warehouse_name', 'hrx_order_status', 'actions');
    
    $all_columns['warehouse_name']['title'] = __('Warehouse', 'hrx-delivery');
    $all_columns['warehouse_name']['filter'] = 'select';
    $all_columns['warehouse_name']['filter_label'] = __('Order warehouse', 'hrx-delivery');
    $all_columns['warehouse_name']['filter_key'] = 'warehouse';
    $all_columns['warehouse_name']['filter_options'] = array();
    $all_columns['hrx_order_status']['filter_key'] = 'track_no';
    $all_columns['hrx_order_status']['filter_title'] = __('Tracking number', 'hrx-delivery');

    foreach ( Warehouse::prepare_options($all_warehouses) as $option_data ) {
        $all_columns['warehouse_name']['filter_options'][$option_data['id']] = $option_data['name'];
    }
}

$tab_columns = array();
foreach ( $columns as $col_id ) {
    $tab_columns[$col_id] = $all_columns[$col_id];
    if ( ! empty($all_columns[$col_id]['filter_key']) ) {
        $page_current_filters[$all_columns[$col_id]['filter_key']] = false;
    }
}

/* Get current filters */
if ( ! isset($_POST['clear_filters']) ) {
    $page_current_filters = PagesFilter::get_current_values($page_current_filters);
}

/* Load table data (rows) */
$tab_data = array();
$selected_values = array();
if ( $page_current_tab == 'warehouses' ) {
    $per_page_options = false;
    $current_warehouse = Warehouse::get_default_id();

    foreach ( $all_warehouses as $warehouse ) {
        if ( empty($current_warehouse) ) {
            Warehouse::set_default_id($warehouse->location_id);
            $current_warehouse = $warehouse->location_id;
        }
        if ( ! PagesFilter::check_warehouse($warehouse, $page_current_filters) ) {
            continue;
        }
        $tab_data[$warehouse->id] = array(
            'warehouse_id' => $warehouse->location_id,
            'warehouse_name' => $warehouse->name,
            'country' => $this->wc->tools->get_country_name($warehouse->country),
            'city' => $warehouse->city,
            'zip' => $warehouse->postcode,
            'address' => $warehouse->address,
            'selected' => $warehouse->location_id,
        );
        $selected_values['selected'] = $current_warehouse;
    }
} else if ( $page_current_tab == 'manifests' ) {
    $per_page_options = false;
    //TODO: Manifest - Do it if need it
} else {
    $show_mass_buttons = array(/*'manifest',*/'register_orders', 'mark_ready', 'ship_label', 'return_label');
    $args = array(
        'paginate' => true,
        'limit' => $per_page,
        'paged' => $page_current_no,
        'hrx_delivery_method' => array_keys($this->core->methods),
        'meta_query' => array(
            'relation' => 'AND',
            array(
                'key' => 'hrx_delivery_method',
                'value' => array_keys($this->core->methods),
                'compare' => 'IN'
            )
        ),
    );


    if ( $page_current_tab == 'new_orders' ) {
        $args['status'] = array('wc-processing', 'wc-on-hold', 'wc-pending');
        // Not show HRX statuses
        $args['not_' . $this->core->meta_keys->order_status] = array('ready', 'cancelled', 'in_delivery', 'delivered', 'in_return');
        $args['meta_query'] = Pages::build_meta_query($args['meta_query'], array(
            'not_' . $this->core->meta_keys->order_status => array('ready', 'cancelled', 'in_delivery', 'delivered', 'in_return'),
        ));
    }
    if ( $page_current_tab == 'send_orders' ) {
        $show_mass_buttons = array('unmark_ready', 'ship_label', 'return_label');
        $args['status'] = array('wc-processing', 'wc-on-hold', 'wc-pending');
        // Show HRX statuses
        $args[$this->core->meta_keys->order_status] = array('ready', 'in_delivery', 'delivered', 'in_return');
        $args['meta_query'] = Pages::build_meta_query($args['meta_query'], array(
            $this->core->meta_keys->order_status => array('ready', 'in_delivery', 'delivered', 'in_return'),
        ));
    }
    if ( $page_current_tab == 'cancelled_orders' ) {
        $show_mass_buttons = array('regenerate_orders', 'ship_label', 'return_label');
        $args['status'] = array('wc-processing', 'wc-on-hold', 'wc-pending', 'wc-completed');
        // Show HRX statuses
        $args[$this->core->meta_keys->order_status] = array('cancelled');
        $args['meta_query'] = Pages::build_meta_query($args['meta_query'], array(
            $this->core->meta_keys->order_status => array('cancelled'),
        ));
    }
    if ( $page_current_tab == 'completed_orders' ) {
        $show_mass_buttons = array('mark_ready', 'ship_label', 'return_label');
        $args['status'] = array('wc-completed');
        // Show all HRX statuses
    }

    if ( isset($tab_columns['order_status_text']['filter_options']) && ! empty($args['status']) ) {
        $tab_columns['order_status_text']['filter_options'] = PagesFilter::remove_not_available_options($tab_columns['order_status_text']['filter_options'], $args['status']);
    }

    $args = PagesFilter::change_args_by_filters($args, $page_current_filters);
    $results = false;

    if ( ! empty($page_current_filters['id']) ) {
        $filtered_order = $filtered_order = $this->wc->order->get_order((int)$page_current_filters['id']);
        $orders = (! empty($filtered_order)) ? array($filtered_order) : array();
    } else {
        $results = $this->wc->order->get_orders($args);
        $orders = $results->orders;
    }

    foreach ( $orders as $order ) {
        $this->wc->order->set_tmp_order($order);
        $hrx_order_status = Shipment::get_status($order->get_id());
        if ( ($page_current_tab == 'new_orders' && $hrx_order_status == 'ready')
            || ($page_current_tab == 'send_orders' && $hrx_order_status != 'ready') ) {
            continue;
        }

        $order_method = $this->wc->order->get_meta($order->get_id(), $this->core->meta_keys->method);

        $deliver_to = $this->get_shipping_address_text($order);
        if ( Helper::method_has_terminals($order_method) ) {
            $terminal_id = $this->wc->order->get_meta($order->get_id(), $this->core->meta_keys->terminal_id);
            $deliver_to = Terminal::get_name_by_id($terminal_id);
        }

        $warehouse_id = $this->wc->order->get_meta($order->get_id(), $this->core->meta_keys->warehouse_id);
        if ( empty($warehouse_id) ) {
            $warehouse_id = Warehouse::get_default_id();
            $this->wc->order->update_meta($order->get_id(), $this->core->meta_keys->warehouse_id, $warehouse_id, true);
        }

        $hrx_status_text = $this->build_hrx_status_text($order);

        $tab_data[$order->get_id()] = array(
            'order_id' => '<a href="' . $order->get_edit_order_url() . '">#' . $order->get_order_number() . '</a>' . PagesHtml::build_order_preview_link(),
            'customer' => $this->get_order_customer_fullname($order),
            'order_status' => $order->get_status(),
            'order_status_text' => $this->get_order_status_text($order),
            'order_date' => $order->get_date_created()->format('Y-m-d H:i:s'),
            'method' => $this->get_method_delivery_name($order_method, $deliver_to),
            'hrx_order_status' => (! empty($hrx_status_text)) ? $hrx_status_text : '—',
            'warehouse_name' => Warehouse::get_name_by_id($warehouse_id),
        );

        $selected_values['actions'][$order->get_id()] = array(
            'hrx_order_id' => $this->wc->order->get_meta($order->get_id(), $this->core->meta_keys->order_id),
            'hrx_order_status' => $hrx_order_status,
        );
    }

    if ($results) {
        $pagination_links = paginate_links(array(
            'base' => add_query_arg('paged', '%#%'),
            'format' => '?paged=%#%',
            'prev_text' => __('&laquo;', 'text-domain'),
            'next_text' => __('&raquo;', 'text-domain'),
            'total' => $results->max_num_pages,
            'current' => $page_current_no,
            'type' => 'plain'
        ));
    }
}

/* Template */
?>
<div class="wrap hrx-page hrx-page-management">
    <div class="table-script-elements">
        <?php echo PagesHtml::build_message_modal(); ?>
        <?php echo PagesHtml::build_order_preview_modal(); ?>
    </div>
    <?php echo PagesHtml::build_page_title($page_title, $page_image); ?>
    <?php echo PagesHtml::build_page_navigation($page_tabs, $page_current_tab); ?>
    <div class="table-header">
        <?php echo PagesHtml::build_mass_buttons(array(
            'key' => $page_current_tab,
            'show_buttons' => $show_mass_buttons,
        )); ?>
        <?php echo PagesHtml::build_per_page_selection($per_page_options, $per_page); ?>
        <?php echo PagesHtml::build_pagination_links($pagination_links); ?>
    </div>
    <?php echo PagesHtml::build_table(array(
        'key' => $page_current_tab,
        'columns' => $tab_columns,
        'data' => $tab_data,
        'selected' => $selected_values,
        'filters_selected' => $page_current_filters,
    )); ?>
    <div class="table-footer">
        <?php echo PagesHtml::build_pagination_links($pagination_links); ?>
        <?php echo PagesHtml::build_mass_buttons(array(
            'key' => $page_current_tab,
            'show_buttons' => $show_mass_buttons,
        )); ?>
    </div>
</div>
