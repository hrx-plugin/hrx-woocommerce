<?php
namespace HrxDeliveryWoo;

// Prevent direct access to this script
if ( ! defined('ABSPATH') ) {
    exit;
}

class PagesHtml
{
    public static function build_page_title( $title, $image = false )
    {
        $html = '<h1 class="page-title">';
        if ( $image ) {
            $html .= '<img src="' . $image . '"/>';
        }
        $html .= $title . '</h1>';

        return $html;
    }

    public static function build_page_navigation( $tabs, $current_tab = '' )
    {
        if ( ! is_array($tabs) ) {
            return '';
        }

        ob_start();
        ?>
        <ul class="nav nav-tabs">
            <?php foreach ( $tabs as $tab_key => $tab_title ) : ?>
                <?php $tab_url = add_query_arg(array('tab' => $tab_key)); ?>
                <?php $tab_url = remove_query_arg(array('paged'), $tab_url); ?>
                <li class="nav-item">
                    <a class="nav-link <?php echo ($tab_key == $current_tab) ? 'active' : ''; ?>"
                        href="<?php echo esc_url($tab_url); ?>"><?php echo $tab_title; ?></a>
                </li>
            <?php endforeach; ?>
        </ul>
        <?php
        $html = ob_get_contents();
        ob_end_clean();

        return $html;
    }

    public static function build_pagination_links( $paginate_links )
    {
        if ( empty($paginate_links) ) {
            return '';
        }

        ob_start();
        ?>
        <div class="tablenav">
            <div class="tablenav-pages">
                <?php echo $paginate_links; ?>
            </div>
        </div>
        <?php
        $html = ob_get_contents();
        ob_end_clean();

        return $html;
    }

    public static function build_per_page_selection( $values, $current = 25 )
    {
        if ( empty($values) ) {
            return '';
        }

        ob_start();
        ?>
        <form id="hrx-per_page-form" class="page-pp" method="post">
            <?php _e('Show', 'hrx-delivery') ?>
            <select id="hrx-per_page" name="per_page">
              <?php foreach ($values as $pp) {
                echo '<option value="' . $pp . '"';
                echo ($current == $pp) ? 'selected' : '';
                echo '>' . $pp . '</option>';
              } ?>
            </select>
          </form>
        <?php
        $html = ob_get_contents();
        ob_end_clean();

        return $html;
    }

    public static function build_mass_buttons( $params )
    {
        $table_key = $params['key'] ?? '';
        $show_buttons = $params['show_buttons'] ?? array();

        if ( empty($show_buttons) ) {
            return '';
        }

        ob_start();
        ?>
        <div class="mass-container <?php echo 'hrx-mass-' . $table_key; ?>">
            <div class="mass-buttons">
                <?php if ( in_array('manifest', $show_buttons) ) : ?>
                    <button class="button action btn-mass-manifest" type="button" value="manifest" data-table="<?php echo 'hrx-table-' . $table_key; ?>"><?php echo __('Generate manifest', 'hrx-delivery'); ?></button>
                <?php endif; ?>
                <?php if ( in_array('ship_label', $show_buttons) ) : ?>
                    <button class="button action btn-mass-shipping_label" type="button" value="shipping_label" data-table="<?php echo 'hrx-table-' . $table_key; ?>"><?php echo __('Print shipment label', 'hrx-delivery'); ?></button>
                <?php endif; ?>
                <?php if ( in_array('return_label', $show_buttons) ) : ?>
                    <button class="button action btn-mass-return_label" type="button" value="return_label" data-table="<?php echo 'hrx-table-' . $table_key; ?>"><?php echo __('Print return label', 'hrx-delivery'); ?></button>
                <?php endif; ?>
            </div>
        </div>
        <?php
        $html = ob_get_contents();
        ob_end_clean();

        return $html;
    }

    public static function build_table( $params )
    {
        $form_action = $params['form_action'] ?? '';
        $nonce_id = $params['nonce_id'] ?? '';
        $table_key = $params['key'] ?? '';
        $columns = $params['columns'] ?? array();
        $data = $params['data'] ?? array();
        $data_selected = $params['selected'] ?? array();
        $filters_selected = $params['filters_selected'] ?? array();

        ob_start();
        ?>
        <div class="table-container <?php echo 'hrx-table-' . $table_key; ?>">
            <form id="filter-form" class="" action="<?php echo esc_html($form_action); ?>" method="POST">
                <?php wp_nonce_field($nonce_id, $nonce_id . '_nonce'); ?>
                <table id="hrx-table-<?php echo $table_key; ?>" class="wp-list-table widefat fixed striped posts">
                    <thead>
                        <?php echo self::build_table_filter($columns, array('selected' => $filters_selected)); ?>
                        <?php echo self::build_table_header($columns); ?>
                    </thead>
                    <?php echo self::build_table_body($columns, $data, $data_selected); ?>
                </table>
            </form>
        </div>
        <?php
        $html = ob_get_contents();
        ob_end_clean();

        return $html;
    }

    public static function build_info_row( $key, $title, $value, $value_class = '' )
    {
        $output = '<p class="info-' . $key . '">';
        if ( ! empty($title) ) {
            $output .= '<b>' . $title . ':</b> ';
        }
        $output .= '<span class="status-value ' . esc_html($value_class) . '">' . $value . '</span></p>';

        return $output;
    }

    public static function build_order_preview_link()
    {
        $link_title = __('Preview', 'hrx-delivery');
        $output = '<a href="#" class="hrx-open-modal " data-modal="order_preview" title="' . $link_title . '">' . $link_title . '</a>';

        return $output;
    }

    public static function build_order_preview_modal()
    {
        ob_start();
        ?>
        <div id="hrx-modal-order_preview" class="hrx-modal hrx-modal-order_preview" style="display:none;">
            <div class="modal-holder">
                <div class="modal-header">
                    <div class="modal-header-title">
                        <?php printf(__('WC Order #%s', 'hrx-delivery'), '<span class="modal-data-title"></span>'); ?>
                    </div>
                    <mark class="modal-data-status order-status"><span>Test</span></mark>
                    <button class="modal-close" onclick="hrxModal.close(this);"></button>
                </div>
                <div class="modal-content">
                    <div class="modal-content-billing">
                        <span class="modal-content-title"><?php echo __('Billing details', 'hrx-delivery'); ?></span>
                        <span class="modal-data-billing-name"></span>
                        <span class="modal-data-billing-address"></span>
                        <span class="modal-data-billing-city"></span>
                        <span class="modal-data-billing-postcode"></span>
                        <span class="modal-data-billing-country"></span>
                        <div class="modal-value-group">
                            <span class="modal-value-title"><?php echo __('Contacts', 'hrx-delivery'); ?></span>
                            <span class="modal-data-billing-email"></span>
                            <span class="modal-data-billing-phone"></span>
                        </div>
                        <div class="modal-value-group">
                            <span class="modal-value-title"><?php echo __('Payment method', 'hrx-delivery'); ?></span>
                            <span class="modal-data-billing-payment"></span>
                            <span class="modal-value-title"><?php echo __('Total order amount', 'hrx-delivery'); ?></span>
                            <span class="modal-value-inline">
                                <span class="modal-value-inline-title"><?php echo __('Products', 'hrx-delivery'); ?>:</span>
                                <span class="modal-data-billing-total-products"></span>
                            </span>
                            <span class="modal-value-inline">
                                <span class="modal-value-inline-title"><?php echo __('Shipping', 'hrx-delivery'); ?>:</span>
                                <span class="modal-data-billing-total-shipping"></span>
                            </span>
                            <span class="modal-value-inline">
                                <span class="modal-value-inline-title"><?php echo __('Tax', 'hrx-delivery'); ?>:</span>
                                <span class="modal-data-billing-total-tax"></span>
                            </span>
                            <span class="modal-value-inline">
                                <span class="modal-value-inline-title"><?php echo __('Total', 'hrx-delivery'); ?>:</span>
                                <span class="modal-data-billing-total"></span>
                            </span>
                        </div>
                    </div>
                    <div class="modal-content-shipping">
                        <span class="modal-content-title"><?php echo __('Shipping details', 'hrx-delivery'); ?></span>
                        <span class="modal-data-shipping-name"></span>
                        <span class="modal-data-shipping-address"></span>
                        <span class="modal-data-shipping-city"></span>
                        <span class="modal-data-shipping-postcode"></span>
                        <span class="modal-data-shipping-country"></span>
                        <div class="modal-value-group">
                            <span class="modal-value-title"><?php echo __('Shipping method', 'hrx-delivery'); ?></span>
                            <span class="modal-data-shipping-method"></span>
                            <span class="modal-value-title"><?php echo __('Parcel terminal', 'hrx-delivery'); ?></span>
                            <span class="modal-data-shipping-terminal">-</span>
                        </div>
                        <div class="modal-value-group">
                            <span class="modal-value-title"><?php echo __('Warehouse', 'hrx-delivery'); ?></span>
                            <span class="modal-data-shipping-warehouse">-</span>
                            <span class="modal-value-title"><?php echo __('Size', 'hrx-delivery'); ?></span>
                            <span class="modal-data-shipping-size"></span>
                        </div>
                        <div class="modal-value-group">
                            <span class="modal-value-title"><?php echo __('Tracking number', 'hrx-delivery'); ?></span>
                            <span class="modal-data-shipping-tracking">-</span>
                        </div>
                    </div>
                    <div class="modal-content-products">
                        <table class="modal-content-table modal-data-products">
                            <tr>
                                <th><?php echo __('Product', 'hrx-delivery'); ?></th>
                                <th><?php echo __('SKU', 'hrx-delivery'); ?></th>
                                <th><?php echo __('Price', 'hrx-delivery'); ?></th>
                                <th><?php echo __('Quantity', 'hrx-delivery'); ?></th>
                                <th><?php echo __('Total', 'hrx-delivery'); ?></th>
                            </tr>
                        </table>
                    </div>
                </div>
                <div class="modal-footer">
                </div>
            </div>
            <div class="modal-background" onclick="hrxModal.close(this);"></div>
        </div>
        <?php
        $html = ob_get_contents();
        ob_end_clean();
        
        return $html;
    }

    public static function build_message_modal()
    {
        ob_start();
        ?>
        <div id="hrx-modal-message" class="hrx-modal hrx-modal-message" style="display:none;">
            <div class="modal-holder">
                <div class="modal-header">
                    <div class="modal-header-title">
                        <span class="modal-data-title"></span>
                    </div>
                    <button class="modal-close" onclick="hrxModal.close(this);"></button>
                </div>
                <div class="modal-content">
                    <span class="modal-data-message"></span>
                </div>
            </div>
            <div class="modal-background" onclick="hrxModal.close(this);"></div>
        </div>
        <?php
        $html = ob_get_contents();
        ob_end_clean();
        
        return $html;
    }

    private static function build_table_filter( $columns, $params )
    {
        if ( ! is_array($columns) ) {
            return '';
        }

        $selected_filters = $params['selected'] ?? array();

        ob_start();
        ?>
        <tr class="hrx-table-filter">
            <?php foreach ( $columns as $col_id => $col_data ) : ?>
                <?php $classes = self::prepare_class_list_html($col_id, $col_data); ?>
                <td class="<?php echo $classes; ?>">
                    <?php if ( ! empty($col_data['filter']) ) : ?>
                        <?php
                        $placeholder = $col_data['filter_title'] ?? ($col_data['title'] ?? '');
                        $label = $col_data['filter_label'] ?? '';
                        $filter_key = $col_data['filter_key'] ?? $col_id;
                        $field_name = 'filter_' . $filter_key;
                        $field_value = $selected_filters[$filter_key] ?? '';

                        switch ($col_data['filter']) {
                            case 'checkbox': ?>
                                <input type="checkbox" class="check-all"/>
                            <?php break;
                            case 'text': ?>
                                <input type="text" class="d-inline" name="<?php echo $field_name; ?>"
                                    id="filter_<?php echo $col_id; ?>" value="<?php echo $field_value; ?>"
                                    placeholder="<?php echo $placeholder; ?>"
                                    aria-label="<?php echo (! empty($label)) ? $label : ''; ?>">
                            <?php break;
                            case 'select': ?>
                                <select class="d-inline" name="<?php echo $field_name; ?>"
                                    id="filter_<?php echo $col_id; ?>"
                                    aria-label="<?php echo (! empty($label)) ? $label : ''; ?>">
                                    <option value="">— <?php echo $placeholder; ?> —</option>
                                    <?php if ( isset($col_data['filter_options']) ) : ?>
                                        <?php foreach ( $col_data['filter_options'] as $option_value => $option_title ) : ?>
                                            <?php $selected = ($field_value == $option_value) ? 'selected' : ''; ?>
                                            <option value="<?php echo esc_html($option_value); ?>" <?php echo $selected; ?>><?php echo esc_html($option_title); ?></option>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                </select>
                            <?php break;
                            case 'actions': ?>
                                <div class="filter-actions">
                                    <button class="button action" type="submit"><?php echo __('Search', 'hrx-delivery'); ?></button>
                                    <button id="btn_clear_filter" class="button action" type="submit" name="clear_filters"><?php echo __('Clear', 'hrx-delivery'); ?></button>
                                </div>
                            <?php break;
                            default: ?> 
                            <?php
                        }
                        ?>
                    <?php endif; ?>
                </td>
            <?php endforeach; ?>
        </tr>
        <?php
        $html = ob_get_contents();
        ob_end_clean();

        return $html;
    }

    private static function build_table_header( $columns )
    {
        ob_start();
        ?>
        <tr class="hrx-table-header">
            <?php foreach ( $columns as $col_id => $col_data ) : ?>
                <?php $scope = (empty($col_data['hide_scope'])) ? 'scope="col"' : ''; ?>
                <?php $classes = self::prepare_class_list_html($col_id, $col_data); ?>
                <?php $title = $col_data['title'] ?? ''; ?>
                <td <?php echo $scope; ?> class="<?php echo $classes; ?>"><?php echo $title; ?></td>
            <?php endforeach; ?>
        </tr>
        <?php
        $html = ob_get_contents();
        ob_end_clean();

        return $html;
    }

    private static function build_table_body( $columns, $data, $data_selected )
    {
        if ( ! is_array($columns) || ! is_array($data) ) {
            return '';
        }

        ob_start();
        ?>
        <tbody>
            <?php foreach ( $data as $row_id => $row ) : ?>
                <tr class="data-row" data-id="<?php echo $row_id; ?>">
                    <?php foreach ( $columns as $col_id => $col_data ) : ?>
                        <?php
                        $classes = self::prepare_class_list_html($col_id, $col_data);
                        $order_data = $data_selected['actions'][$row_id];
                        $order_registered = (! empty($order_data['hrx_order_id'])) ? true : false;
                        $order_status = (! empty($order_data['hrx_order_status'])) ? $order_data['hrx_order_status'] : 'new';
                        $wc_order_status = (! empty($row['order_status'])) ? $row['order_status'] : 'processing';
                        ?>
                        <?php if ( $col_id == 'cb' ) : ?>
                            <th scope="row" class="<?php echo $classes; ?>">
                                <?php if ( $order_registered && $order_status != 'error' ) : ?>
                                    <input type="checkbox" name="col_<?php echo $col_id; ?>[]" value="<?php echo $row_id; ?>"/>
                                <?php endif; ?>
                            </th>
                        <?php elseif ( $col_id == 'order_id' ) : ?>
                            <td class="<?php echo $classes; ?>"><?php echo $row[$col_id] ?? ''; ?></td>
                        <?php elseif ( $col_id == 'selected' ) : ?>
                            <td class="<?php echo $classes; ?>">
                                <?php $checked = (isset($data_selected[$col_id]) && $data_selected[$col_id] == $row[$col_id]) ? 'checked' : ''; ?>
                                <label class="custom-cb">
                                    <input type="radio" name="col_<?php echo $col_id; ?>"
                                        value="<?php echo $row[$col_id] ?? ''; ?>" <?php echo $checked; ?>/>
                                    <span class="checkmark"></span>
                                </label>
                            </td>
                        <?php elseif ( $col_id == 'actions' ) : ?>
                            <td class="<?php echo $classes; ?>">
                                <?php
                                $hide_for_wc_status = array('completed', 'cancelled', 'refunded', 'failed');
                                $hide_btn = ($order_status == 'ready' || in_array($wc_order_status, $hide_for_wc_status)) ? 'display:none;' : '';
                                $btn_text = ($order_registered) ? __('Regenerate order', 'hrx-delivery') : __('Register order', 'hrx-delivery');
                                ?>
                                <button id="btn_create_order_<?php echo $row_id; ?>" class="button action btn-create_order" type="button" value="create_order" style="<?php echo $hide_btn; ?>"><?php echo $btn_text; ?></button>
                                <?php
                                $hide_for_wc_status = array('cancelled', 'refunded', 'failed');
                                $hide_btn = (! $order_registered || $order_status != 'new' || in_array($wc_order_status, $hide_for_wc_status)) ? 'display:none;' : '';
                                ?>
                                <button id="btn_ready_order_<?php echo $row_id; ?>" class="button action btn-ready_order" type="button" value="ready_order" style="<?php echo $hide_btn; ?>"><?php echo __('Mark as ready', 'hrx-delivery'); ?></button>
                                <?php
                                $hide_for_wc_status = array('completed', 'cancelled', 'refunded', 'failed');
                                $hide_btn = (! $order_registered || $order_status != 'ready' || in_array($wc_order_status, $hide_for_wc_status)) ? 'display:none;' : '';
                                ?>
                                <button id="btn_unready_order_<?php echo $row_id; ?>" class="button action btn-unready_order" type="button" value="unready_order" style="<?php echo $hide_btn; ?>"><?php echo __('Unmark ready', 'hrx-delivery'); ?></button>
                                <?php
                                $hide_btn = (! $order_registered || $order_status == 'error') ? 'display:none;' : '';
                                ?>
                                <button id="btn_shipment_label_<?php echo $row_id; ?>" class="button action btn-shipment_label" type="button" value="shipment_label" style="<?php echo $hide_btn; ?>"><?php echo __('Shipment label', 'hrx-delivery'); ?></button>
                                <?php
                                $hide_btn = (! $order_registered || $order_status == 'error') ? 'display:none;' : '';
                                ?>
                                <button id="btn_return_label_<?php echo $row_id; ?>" class="button action btn-return_label" type="button" value="return_label" style="<?php echo $hide_btn; ?>"><?php echo __('Return label', 'hrx-delivery'); ?></button>
                            </td>
                        <?php else : ?>
                            <td class="<?php echo $classes; ?>"><?php echo $row[$col_id] ?? ''; ?></td>
                        <?php endif; ?>
                    <?php endforeach; ?>
                </tr>
            <?php endforeach; ?>
            <?php if ( empty($data) ) : ?>
                <tr class="empty-table">
                    <td colspan="<?php echo count($columns); ?>"><?php echo __('No data found', 'woocommerce'); ?></td>
                </tr>
            <?php endif; ?>
        </tbody>
        <?php
        $html = ob_get_contents();
        ob_end_clean();

        return $html;
    }

    private static function prepare_class_list_html( $column_id, $column_data )
    {
        $class_list = array();

        if ( isset($column_data['manage']) && $column_data['manage'] == true ) {
            $class_list[] = 'manage-column';
        }

        $class_list[] = 'column-' . esc_html($column_id);

        if ( ! empty($column_data['class']) ) {
            $class_list[] = $column_data['class'];
        }

        return implode(' ', $class_list);
    }

    private static function get_paged()
    {
        if ( isset($_GET['paged']) ) {
            return filter_input(INPUT_GET, 'paged');
        }

        return 1;
    }

    private static function get_action( $default_action )
    {
        if ( isset($_GET['action']) ) {
            return filter_input(INPUT_GET, 'action');
        }

        return $default_action;
    }

    private static function get_filters( $all_filters_keys )
    {
        $filters = array();

        foreach( $all_filters_keys as $filter_key ) {
            if ( isset($_POST['filter_' . $filter_key]) && intval($_POST['filter_' . $filter_key]) !== -1 ) {
                $filters[$filter_key] = filter_input(INPUT_POST, 'filter_' . $filter_key);
            } else {
                $filters[$filter_key] = false;
            }
        }

        return $filters;
    }
}
