<?php
namespace HrxDeliveryWoo;

// Prevent direct access to this script
if ( ! defined('ABSPATH') ) {
    exit;
}

use HrxDeliveryWoo\Sql;
use HrxDeliveryWoo\Helper;
use HrxDeliveryWoo\Terminal;
use HrxDeliveryWoo\Warehouse;

class OrderHtml
{
    public static function build_order_block_title( $params )
    {
        $title = $params['title'] ?? '';

        ob_start();
        ?>
        <div class="hrx-title">
            <br class="clear"/>
            <hr style="margin-top:20px;">
            <?php if ( ! empty($title) ) : ?>
                <h4><?php echo $title; ?></h4>
            <?php endif; ?>
        </div>
        <?php
        $html = ob_get_contents();
        ob_end_clean();

        return $html;
    }

    public static function build_order_block_preview( $params )
    {
        $method_key = $params['method'] ?? '';
        $method_has_terminal = $params['has_terminals'] ?? false;
        $selected_terminal_id = $params['terminal_id'] ?? '';
        $selected_warehouse_id = $params['warehouse_id'] ?? '';
        $tracking_number = $params['tracking_number'] ?? '';
        $weight = $params['weight'] ?? 0;

        if ( empty($method_key) ) {
            return '';
        }

        $selected_terminal_name = '—';
        if ( ! empty($selected_terminal_id) ) {
            $terminal_data = Sql::get_row('delivery', array('location_id' => $selected_terminal_id));
            if ( ! empty($terminal_data) ) {
                $selected_terminal_name = Terminal::build_name($terminal_data);
            }
        }

        $selected_warehouse_name = '—';
        if ( ! empty($selected_warehouse_id) ) {
            $warehouse_data = Sql::get_row('pickup', array('location_id' => $selected_warehouse_id));
            if ( ! empty($warehouse_data) ) {
                $selected_warehouse_name = $warehouse_data->name;
            }
        }

        $formated_weight = number_format((float)$weight, 3, '.', '');
        
        ob_start();
        ?>
        <div class="address hrx-address">
            <?php if ( $method_has_terminal ) : ?>
                <p>
                    <strong class="title"><?php _e('Parcel terminal', 'hrx-delivery'); ?>:</strong>
                    <span class="value"><?php echo $selected_terminal_name; ?></span>
                </p>
            <?php endif; ?>
            <p>
                <strong class="title"><?php _e('Pickup warehouse', 'hrx-delivery'); ?>:</strong>
                <span class="value"><?php echo $selected_warehouse_name; ?></span>
            </p>
            <p>
                <strong class="title"><?php _e('Tracking number', 'hrx-delivery'); ?>:</strong>
                <span class="value"><?php echo (! empty($tracking_number)) ? $tracking_number : '—'; ?></span>
            </p>
            <p>
                <strong class="title"><?php _e('Weight', 'hrx-delivery'); ?>:</strong>
                <span class="value"><?php echo $formated_weight; ?> kg</span>
            </p>
        </div>
        <?php
        $html = ob_get_contents();
        ob_end_clean();

        return $html;
    }

    public static function build_order_block_edit( $params )
    {
        $method_key = $params['method'] ?? '';
        $country = $params['country'] ?? '';
        $method_has_terminal = $params['has_terminals'] ?? false;
        $selected_terminal_id = $params['selected_terminal'] ?? '';
        $all_terminals = $params['all_terminals'] ?? array();
        $selected_warehouse_id = $params['selected_warehouse'] ?? '';
        $all_warehouses = $params['all_warehouses'] ?? array();
        $tracking_number = $params['tracking_number'] ?? '';
        $weight = $params['weight'] ?? 0;

        if ( empty($method_key) ) {
            return '';
        }

        ob_start();
        ?>
        <div class="edit_address hrx-address">
            <input type="hidden" id="hrx_country" value="<?php echo $country; ?>">
            <?php if ( $method_has_terminal ) : ?>
                <p class="form-field-wide">
                    <label for="hrx_terminal"><?php _e('Parcel terminal', 'hrx-delivery'); ?>:</label>
                    <?php echo Terminal::build_select_field(array(
                        'all_terminals' => $all_terminals,
                        'selected_id' => $selected_terminal_id,
                        'name' => 'hrx_terminal',
                        'id' => 'hrx_terminal',
                        'class' => 'select short',
                    )); ?>
                </p>
            <?php endif; ?>
            <p class="form-field-wide">
                <label for="hrx_warehouse"><?php _e('Parcel warehouse', 'hrx-delivery'); ?>:</label>
                <?php echo Warehouse::build_select_field(array(
                    'all_warehouses' => $all_warehouses,
                    'selected_id' => $selected_warehouse_id,
                    'name' => 'hrx_warehouse',
                    'id' => 'hrx_warehouse',
                    'class' => 'select short',
                )); ?>
            </p>
        </div>
        <?php
        $html = ob_get_contents();
        ob_end_clean();

        return $html;
    }
}
