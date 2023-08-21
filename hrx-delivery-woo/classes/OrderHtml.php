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
        $order_status = $params['status'] ?? '';
        $selected_terminal_id = $params['terminal_id'] ?? '';
        $selected_warehouse_id = $params['warehouse_id'] ?? '';
        $tracking_number = $params['tracking_number'] ?? '';
        $weight = $params['weight'] ?? 0;
        $size = $params['size'] ?? array();

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
        $size_w = (! empty($size['width'])) ? $size['width'] : 0;
        $size_h = (! empty($size['height'])) ? $size['height'] : 0;
        $size_l = (! empty($size['length'])) ? $size['length'] : 0;
        $formated_size = (float)$size_w . '×' . (float)$size_h . '×' . (float)$size_l;
        
        ob_start();
        ?>
        <div class="address hrx-address">
            <?php if ( ! empty($order_status) ) : ?>
                <p>
                    <strong class="title"><?php _e('Status', 'hrx-delivery'); ?>:</strong>
                    <span class="value"><?php echo $order_status; ?></span>
                </p>
            <?php endif; ?>
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
                <strong class="title"><?php _e('Package weight', 'hrx-delivery'); ?>:</strong>
                <span class="value"><?php echo $formated_weight; ?> kg</span>
            </p>
            <p>
                <strong class="title"><?php _e('Package size', 'hrx-delivery'); ?>:</strong>
                <span class="value"><?php echo $formated_size; ?> cm</span>
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
        $size = $params['size'] ?? array();
        $units = $params['units'] ?? (object)array('weight' => 'kg', 'dimension' => 'cm');
        $all_disabled = $params['all_disabled'] ?? false;

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
                        'disabled' => $all_disabled,
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
                    'disabled' => $all_disabled,
                )); ?>
            </p>
            <?php
            echo self::number_field(array(
                'value' => $weight,
                'name' => 'hrx_dimensions[weight]',
                'id' => 'hrx_weight',
                'title' => __('Weight', 'hrx-delivery') . ' (' . $units->weight . ')',
                'min' => 0,
                'step' => 0.001,
                'disabled' => $all_disabled,
            ));

            $dimensions_fields = array(
                'width' => __('Width', 'hrx-delivery'),
                'height' => __('Height', 'hrx-delivery'),
                'length' => __('Length', 'hrx-delivery'),
            );
            foreach ( $dimensions_fields as $dim_key => $dim_title ) {
                echo self::number_field(array(
                    'value' => (! empty($size[$dim_key])) ? $size[$dim_key] : 0,
                    'name' => 'hrx_dimensions[' . $dim_key . ']',
                    'id' => 'hrx_' . $dim_key,
                    'title' => $dim_title . ' (' . $units->dimension . ')',
                    'min' => 0,
                    'disabled' => $all_disabled,
                ));
            }
            ?>
        </div>
        <?php
        $html = ob_get_contents();
        ob_end_clean();

        return $html;
    }

    private static function number_field( $params )
    {
        $value = $params['value'] ?? '';
        $name = $params['name'] ?? '';
        $id = $params['id'] ?? $name;
        $title = $params['title'] ?? '';
        $min = $params['min'] ?? '';
        $max = $params['max'] ?? '';
        $step = $params['step'] ?? '';
        $class = $params['class'] ?? '';
        $class_field = $params['field_class'] ?? 'short';
        $class_label = $params['label_class'] ?? '';
        $style = $params['style'] ?? '';
        $field_disabled = $params['disabled'] ?? false;

        $disabled = ($field_disabled) ? 'disabled' : '';
        
        ob_start();
        ?>
        <p class="form-field <?php echo esc_attr($id); ?>_field <?php echo esc_attr($class); ?>">
            <label for="<?php echo esc_attr($id); ?>" class="<?php echo esc_attr($class_label); ?>"><?php echo $title; ?></label>
            <input type="number" class="<?php echo esc_attr($class_field); ?>" name="<?php echo esc_attr($name); ?>" id="<?php echo esc_attr($id); ?>" value="<?php echo esc_attr($value); ?>" min="<?php echo esc_attr($min); ?>" max="<?php echo esc_attr($max); ?>" step="<?php echo esc_attr($step); ?>" <?php echo $disabled; ?> style="<?php echo $style; ?>"/>
        </p>
        <?php
        $html = ob_get_contents();
        ob_end_clean();

        return $html;
    }
}
