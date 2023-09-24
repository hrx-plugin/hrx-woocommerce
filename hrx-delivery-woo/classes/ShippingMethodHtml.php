<?php
namespace HrxDeliveryWoo;

// Prevent direct access to this script
if ( ! defined('ABSPATH') ) {
    exit;
}

use HrxDeliveryWoo\Helper;
use HrxDeliveryWoo\WcTools;
use HrxDeliveryWoo\Debug;

class ShippingMethodHtml
{
    public static function build_hr( $params )
    {
        $class = $params['class'] ?? '';
        $top_class = $params['top_class'] ?? '';
        $hide_row = $params['hide'] ?? false;
        $title = '';
        if ( ! empty($params['title']) ) {
            if ( ! empty($class) ) {
                $class .= ' ';
            }
            $class .= 'have_title';
            $title = '<span>' . $params['title'] . '</span>';
        }
        $row_style = ($hide_row) ? 'display:none;' : '';
      
        $html = '<tr valign="top ' . esc_html($top_class) . '" style="' . $row_style . '"><td colspan="2" class="section_title"><hr class="' . esc_html($class) . '">' . $title . '</td></tr>';
      
        return $html;
    }

    public static function build_textarea( $key, $params, $value )
    {
        $class = $params['class'] ?? '';
        $top_class = $params['top_class'] ?? '';
        $title = $params['title'] ?? '';
        $placeholder = $params['placeholder'] ?? '';
        $custom_attributes = $params['custom_attributes'] ?? array();
        $hide_row = $params['hide'] ?? false;
        if ( $value == '' ) {
            $value = $params['default'] ?? '';
        }

        $row_style = ($hide_row) ? 'display:none;' : '';

        ob_start();
        ?>
        <tr valign="top" class="<?php echo esc_html($top_class); ?>" style="<?php echo $row_style; ?>">
            <th scope="row" class="titledesc">
                <label><?php echo esc_html($title); ?></label>
            </th>
            <td class="forminp">
                <fieldset class="field-textarea">
                    <textarea name="<?php echo esc_html($key); ?>" id="<?php echo esc_html($key); ?>"
                        class="input-text wide-input <?php echo esc_html($class); ?>"
                        placeholder="<?php echo esc_html($placeholder); ?>"
                        <?php echo self::build_custom_atributes($custom_attributes); ?>
                    ><?php echo esc_html($value); ?></textarea>
                </fieldset>
                <?php if ( ! empty($params['description']) ) : ?>
                    <p class="description"><?php echo $params['description']; ?></p>
                <?php endif; ?>
            </td>
        </tr>
        <?php
        $html = ob_get_contents();
        ob_end_clean();

        return $html;
    }

    public static function build_action_button( $key, $params )
    {
        $class = $params['class'] ?? '';
        $top_class = $params['top_class'] ?? '';
        $title = $params['title'] ?? '';
        $id = $params['id'] ?? '';
        $label = $params['label'] ?? '';
        $action = $params['action'] ?? '';
        $message = $params['message'] ?? '';
        $default = $params['default'] ?? '';
        $value = $params['value'] ?? '';
        $repeat = $params['repeat'] ?? false;
        $hide_row = $params['hide'] ?? false;

        if ( empty($title) ) {
            $top_class = 'row-no-title ' . $top_class;
        }
        if ( empty($id) ) {
            $id = $key;
        }
        $need_repeat = false;
        if ( $repeat && ! empty($value) ) {
            if( strtotime($value) < strtotime('-' . $repeat, current_time('timestamp')) ) {
                $need_repeat = true;
            }
        }

        $row_style = ($hide_row) ? 'display:none;' : '';

        ob_start();
        ?>
        <tr valign="top" class="<?php echo esc_html($top_class); ?>" style="<?php echo $row_style; ?>">
            <th scope="row" class="titledesc">
                <label><?php echo esc_html($title); ?></label>
            </th>
            <td class="forminp">
                <fieldset class="button-action action-<?php echo esc_html($action); ?> <?php echo esc_html($class); ?>">
                    <button type="button" id="<?php echo esc_html($id); ?>_button" class="button button-primary" value="<?php echo esc_html($action); ?>">
                        <?php echo esc_html($label); ?>
                    </button>
                    <span class="action-txt-title"><?php echo $message; ?></span>
                    <?php $span_class = ($need_repeat) ? 'value-old' : ''; ?>
                    <?php $span_class = (empty($value)) ? 'value-empty' : $span_class; ?>
                    <?php $span_id = esc_html($id) . '_span'; ?>
                    <?php $span_content = (empty($value)) ? $default : $value; ?>
                    <span id="<?php echo $span_id; ?>" class="action-txt-value <?php echo $span_class; ?>"><?php echo $span_content; ?></span>
                    <?php if ( ! empty($params['description']) ) : ?>
                        <p class="description"><?php echo $params['description']; ?></p>
                    <?php endif; ?>
                </fieldset>
            </td>
        </tr>
        <?php
        $html = ob_get_contents();
        ob_end_clean();

        return $html;
    }

    public static function build_dimensions( $key, $params, $value )
    {
        $class = $params['class'] ?? '';
        $top_class = $params['top_class'] ?? '';
        $title = $params['title'] ?? '';
        $hide_row = $params['hide'] ?? false;

        $wcTools = new WcTools();
        $units = $wcTools->get_units();
        
        $dim_values = ($value !== '') ? $value : array();
        if ( is_string($dim_values) && Helper::is_json($dim_values) ) {
            $dim_values = json_decode($value, true);
        }

        $fields = array(
            'width' => array('title' => __('Width', 'hrx-delivery'), 'unit' => 'x'),
            'height' => array('title' => __('Height', 'hrx-delivery'), 'unit' => 'x'),
            'lenght' => array('title' => __('Lenght', 'hrx-delivery'), 'unit' => $units->dimension),
            'weight' => array('title' => __('Weight', 'hrx-delivery'), 'unit' => $units->weight),
        );

        $row_style = ($hide_row) ? 'display:none;' : '';

        ob_start();
        ?>
        <tr valign="top" class="<?php echo esc_html($top_class); ?>" style="<?php echo $row_style; ?>">
            <th scope="row" class="titledesc">
                <label><?php echo esc_html($title); ?></label>
            </th>
            <td class="forminp">
                <fieldset class="field-dimensions <?php echo esc_html($class); ?>">
                    <table>
                        <tr>
                            <?php foreach ( $fields as $field_key => $field_params ) : ?>
                                <td class="dim-<?php echo $field_key; ?>"><?php echo $field_params['title']; ?></td>
                            <?php endforeach; ?>
                        </tr>
                        <tr>
                            <?php $i = 0; ?>
                            <?php foreach ( $fields as $field_key => $field_params ) : ?>
                                <td class="dim-<?php echo $field_key; ?>">
                                    <?php echo self::field_number(array(
                                        'name' => $key . '[' . $i . ']',
                                        'id' => $key . '_' . $i,
                                        'value' => $dim_values[$i] ?? '',
                                        'step' => 0.001,
                                        'min' => 0.001,
                                    )); ?>
                                    <span><?php echo  $field_params['unit']; ?></span>
                                </td>
                                <?php $i++; ?>
                            <?php endforeach; ?>
                        </tr>
                    </table>
                    <?php if ( ! empty($params['description']) ) : ?>
                        <p class="description"><?php echo $params['description']; ?></p>
                    <?php endif; ?>
                </fieldset>
            </td>
        </tr>
        <?php
        $html = ob_get_contents();
        ob_end_clean();

        return $html;
    }

    public static function build_price( $key, $params, $value )
    {
        $class = $params['class'] ?? '';
        $top_class = $params['top_class'] ?? '';
        $title = $params['title'] ?? '';
        $min = $params['custom_attributes']['min'] ?? 0;
        $max = $params['custom_attributes']['max'] ?? '';
        $step = $params['custom_attributes']['step'] ?? 0.01;
        $show_symbol = $params['show_symbol'] ?? true;
        $hide_row = $params['hide'] ?? false;

        $wcTools = new WcTools();

        if ( $value == '' ) {
            $value = $params['default'] ?? '';
        }

        $row_style = ($hide_row) ? 'display:none;' : '';

        ob_start();
        ?>
        <tr valign="top" class="<?php echo esc_html($top_class); ?>" style="<?php echo $row_style; ?>">
            <th scope="row" class="titledesc">
                <label><?php echo esc_html($title); ?></label>
            </th>
            <td class="forminp">
                <fieldset class="field-price <?php echo $class; ?>">
                    <?php echo self::field_number(array(
                        'name' => $key,
                        'id' => $key,
                        'value' => $value,
                        'step' => $step,
                        'min' => $min,
                        'max' => $max,
                    )); ?>
                    <?php if ( $show_symbol ) : ?>
                        <span class="symbol"><?php echo $wcTools->get_units()->currency_symbol; ?></span>
                    <?php endif; ?>
                    <?php if ( ! empty($params['description']) ) : ?>
                        <p class="description"><?php echo $params['description']; ?></p>
                    <?php endif; ?>
                </fieldset>
            </td>
        </tr>
        <?php
        $html = ob_get_contents();
        ob_end_clean();

        return $html;
    }

    public static function build_custom_message_html( $text, $is_error = true )
    {
        $class = ($is_error) ? 'error' : 'updated';
        ob_start();
        ?>
        <div class="<?php echo $class; ?> inline">
            <p><?php echo $text; ?>
        </div>
        <?php
        $html = ob_get_contents();
        ob_end_clean();

        return $html;
    }

    public static function build_prices_for_countries( $key, $params, $values, $img_dir_url )
    {
        $class = $params['class'] ?? '';
        $top_class = $params['top_class'] ?? '';
        $title = $params['title'] ?? '';
        $countries = $params['countries'] ?? array();
        $hide_row = $params['hide'] ?? false;
        $empty_msg = $params['empty_msg'] ?? __('Could not get a list of countries to which this shipping method can be used', 'hrx-delivery');

        $wcTools = new WcTools();
        $units = $wcTools->get_units();

        $row_style = ($hide_row) ? 'display:none;' : '';

        ob_start();
        ?>
        <tr valign="top" class="<?php echo esc_html($top_class); ?>" style="<?php echo $row_style; ?>">
            <th scope="row" class="titledesc">
                <label><?php echo esc_html($title); ?></label>
            </th>
            <td class="forminp">
                <fieldset class="field-countries_prices <?php echo $class; ?>">
                    <?php if ( empty($countries) ) : ?>
                        <?php echo self::build_custom_message_html($empty_msg); ?>
                    <?php endif; ?>
                    <?php foreach ( $countries as $country_code ) : ?>
                        <?php $country_key = esc_html($key) . '_' . $country_code; ?>
                        <?php $country_name = esc_html($key) . '[' . $country_code . ']'; ?>
                        <?php
                        $country_values = $values[$country_code] ?? array();
                        if ( ! isset($country_values['other']) ) {
                            $country_values['other'] = array();
                        }
                        ?>
                        <div class="country_box">
                            <div class="box-header">
                                <div class="title">
                                    <img src="<?php echo $img_dir_url . strtolower($country_code) . '.png'; ?>" alt="[<?php echo $country_code; ?>]"/>
                                    <span><?php echo $wcTools->get_country_name($country_code); ?></span>
                                </div>
                                <?php echo self::build_switcher(array(
                                    'id' => $country_key . '_enable',
                                    'name' => $country_name . '[enable]',
                                    'title' => sprintf(__('Enable this method for %s', 'hrx-delivery'), $country_code),
                                    'class' => $country_code . '_enable',
                                    'show_label' => true,
                                    'type' => 'round',
                                    'checked' => (! empty($country_values['enable'])) ? true : false,
                                )); ?>
                            </div>
                            <div class="box-content">
                                <div class="section-price_by_weight">
                                    <?php $country_prices = $country_values['prices'] ?? array(array()); ?>
                                    <?php $country_prices = array_values($country_prices); //Fix array keys ?>
                                    <?php for ( $i = 0; $i < count($country_prices); $i++ ) : ?>
                                        <?php echo self::build_price_by_weight_row(array(
                                            'key' => $country_key . '_prices',
                                            'name' => $country_name . '[prices]',
                                            'row_number' => $i,
                                            'values' => $country_prices[$i],
                                            'hide_add' => (count($country_prices) > 1 && $i != count($country_prices) - 1),
                                        )); ?>
                                    <?php endfor; ?>
                                </div>
                                <div class="section-other">
                                    <?php echo self::build_sample_number_row(array(
                                        'title' => __('Free from', 'hrx-delivery'),
                                        'key' => $country_key . '_other_free_from',
                                        'name' => $country_name . '[other][free_from]',
                                        'value' => $country_values['other']['free_from'] ?? '',
                                        'step' => 0.01,
                                        'min' => 0,
                                        'unit' => $units->currency_symbol,
                                        'tip' => __('Make this shipping free if the cart amount is equal or more than this value. Leave blank if dont want use this.', 'hrx-delivery')
                                    ));
                                    ?>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </fieldset>
            </td>
        </tr>
        <?php
        $html = ob_get_contents();
        ob_end_clean();

        return $html;
    }

    public static function build_debug_plugin( $params )
    {
        $class = $params['class'] ?? '';
        $top_class = $params['top_class'] ?? '';
        $title = $params['title'] ?? '';
        $hide_row = $params['hide'] ?? false;
        
        $rows_titles = array(
            'id' => 'ID',
            'title' => __('Title', 'hrx-delivery'),
            'version' => __('Version', 'hrx-delivery'),
            'changes' => __('Custom changes', 'hrx-delivery'),
            'dirs' => __('Structure', 'hrx-delivery'),
            'dirs_js' => 'JS',
            'dirs_css' => 'CSS',
            'dirs_img' => __('Images', 'hrx-delivery'),
            'dirs_temp' => __('Temporary files', 'hrx-delivery'),
            'methods' => __('Shipping methods', 'hrx-delivery'),
            'locations' => __('Total locations', 'hrx-delivery'),
        );

        $check_status = Debug::check_plugin();

        $rows = array();
        $rows['id'] = $check_status['id'] ?? Debug::status_keywords('fail');
        $rows['title'] = $check_status['title'] ?? Debug::status_keywords('fail');
        $rows['version'] = $check_status['version'] ?? Debug::status_keywords('fail');
        $rows['changes'] = $check_status['changes'] ?? array();
        if ( empty($rows['changes']) ) {
            $rows['changes'] = Debug::status_keywords('not');
        }
        $rows['dirs'] = $check_status['dirs'] ?? array();
        $rows['methods'] = array();
        if ( isset($check_status['methods']) ) {
            foreach ( $check_status['methods'] as $method_key => $method_data ) {
                $rows['methods'][$method_key] = $method_data['title'] ?? 'Error';
                $rows['methods'][$method_key] .= '. Available in ';
                if ( ! empty($method_data['countries']) ) {
                    $rows['methods'][$method_key] .= implode(', ', $method_data['countries']);
                } else {
                    $rows['methods'][$method_key] .= Debug::status_keywords('empty');
                }
            }
        }
        $rows['locations'] = array();
        $rows['locations']['pickup'] = __('Warehouses', 'hrx-delivery') . ': ';
        $locations_pickup = Sql::get_columns_unique_values('pickup', 'COUNT(*) AS total_rows', array('active' => 1), false);
        if ( isset($locations_pickup[0]) && isset($locations_pickup[0]->total_rows) ) {
            $rows['locations']['pickup'] .= $locations_pickup[0]->total_rows;
        } else {
            $rows['locations']['pickup'] .= 'Error';
        }
        $locations_delivery = Sql::get_columns_unique_values('delivery', 'type, COUNT(*) AS total_rows', array('active' => 1), 'type');
        if ( empty($locations_delivery) ) {
            $rows['locations']['delivery'] = __('Delivery', 'hrx-delivery') . ': Error';
        } else {
            foreach ( $locations_delivery as $loc_type ) {
                if ( ! isset($loc_type->type) ) {
                    $rows['locations']['delivery_type'] .= __('Type', 'hrx-delivery') . ': Failed to get';
                    continue;
                }
                $rows['locations']['delivery_' . $loc_type->type] = ucfirst($loc_type->type) . ': ' . $loc_type->total_rows;
            }
        }

        $row_style = ($hide_row) ? 'display:none;' : '';

        ob_start();
        ?>
        <tr valign="top" class="row-no-title <?php echo esc_html($top_class); ?>" style="<?php echo $row_style; ?>">
            <th scope="row" class="titledesc">
                <label><?php echo esc_html($title); ?></label>
            </th>
            <td class="forminp">
                <fieldset class="debug-plugin <?php echo $class; ?>">
                    <table class="debug-table-main">
                        <?php foreach ( $rows as $row_key => $row_info ) : ?>
                            <tr>
                                <th><?php echo esc_html($rows_titles[$row_key]); ?></th>
                                <td>
                                    <?php if ( is_array($row_info) ) : ?>
                                        <table class="debug-table-array" cellspacing="0">
                                            <?php foreach ( $row_info as $info_key => $info_value ) : ?>
                                                <tr>
                                                    <?php if ( isset($rows_titles[$row_key . '_' . $info_key]) ) : ?>
                                                        <th>
                                                            <?php echo $rows_titles[$row_key . '_' . $info_key]; ?>
                                                        </th>
                                                        <td>
                                                    <?php else : ?>
                                                        <td colspan="2">
                                                    <?php endif; ?>
                                                        <?php echo self::catch_status_keywords(esc_html($info_value)); ?>
                                                    </td>
                                                </tr>
                                            <?php endforeach; ?>
                                        </table>
                                    <?php else : ?>
                                        <?php echo self::catch_status_keywords(esc_html($row_info)); ?>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </table>
                </fieldset>
            </td>
        </tr>
        <?php
        $html = ob_get_contents();
        ob_end_clean();

        return $html;
    }

    private static function build_custom_atributes( $attributes )
    {
        $custom_attributes = array();

        if ( ! empty($attributes) && is_array($attributes) ) {
            foreach ( $attributes as $attribute => $attribute_value ) {
                $custom_attributes[] = esc_attr($attribute) . '="' . esc_attr($attribute_value) . '"';
            }
        }

        return implode(' ', $custom_attributes);
    }

    private static function field_number( $params )
    {
        $class = $params['class'] ?? '';
        $name = $params['name'] ?? '';
        $id = $params['id'] ?? '';
        $value = $params['value'] ?? '';
        $step = $params['step'] ?? '';
        $min = $params['min'] ?? '';
        $max = $params['max'] ?? '';
        $disabled = $params['disabled'] ?? false;
        $readonly = $params['readonly'] ?? false;
        $data = $params['data'] ?? array();
        $custom = $params['custom'] ?? array();

        $data_html = '';
        foreach ( $data as $data_key => $data_value ) {
            $data_html .= ' ' . esc_html($data_key) . '="' . esc_html($data_value) . '"';
        }

        $custom_html = '';
        foreach ( $custom as $custom_key => $custom_value ) {
            $custom_html .= ' ' . esc_html($custom_key) . '="' . esc_html($custom_value) . '"';
        }

        ob_start();
        ?>
        <input class="input-text regular-input <?php echo esc_html($class); ?>" type="number"
            name="<?php echo esc_html($name); ?>" id="<?php echo esc_html($id); ?>"
            value="<?php echo esc_html($value); ?>" step="<?php echo esc_html($step); ?>"
            min="<?php echo esc_html($min); ?>" max="<?php echo esc_html($max); ?>"
            <?php echo ($disabled) ? 'disabled' : ''; ?> <?php echo ($readonly) ? 'readonly' : ''; ?>
            <?php echo $data_html . $custom_html; ?>>
        <?php
        $html = ob_get_contents();
        ob_end_clean();

        return $html;
    }

    private static function build_switcher( $params )
    {
        $id = $params['id'] ?? '';
        $name = $params['name'] ?? '';
        $class = $params['class'] ?? '';
        $title = $params['title'] ?? '';
        $show_label = $params['show_label'] ?? false;
        $type = $params['type'] ?? 'round';
        $checked = $params['checked'] ?? false;

        if ( empty($id) || empty($name) ) {
            return '';
        }

        ob_start();
        ?>
        <div class="hrx-switcher" title="<?php echo esc_html($title); ?>">
            <?php if ( $show_label ) : ?>
                <span class="label"
                    data-on="<?php _e('Turned on', 'hrx-delivery'); ?>"
                    data-off="<?php _e('Turned off', 'hrx-delivery'); ?>"></span>
            <?php endif; ?>
            <label class="switch">
                <input type="checkbox" class="<?php echo esc_html($class); ?>"
                    id="<?php echo esc_html($id); ?>" name="<?php echo esc_html($name); ?>"
                    value="1" <?php echo ($checked) ? 'checked' : ''; ?>>
                <span class="slider <?php echo esc_html($type); ?>"></span>
            </label>
        </div>
        <?php
        $html = ob_get_contents();
        ob_end_clean();

        return $html;
    }

    private static function build_sample_number_row( $params )
    {
        $key = $params['key'] ??  '';
        $name = $params['name'] ??  '';
        $title = $params['title'] ??  '';
        $value = $params['value'] ?? '';
        $description = $params['desc'] ?? '';
        $tip = $params['tip'] ?? '';
        $step = $params['step'] ?? '';
        $min = $params['min'] ?? '';
        $max = $params['max'] ?? '';
        $unit = $params['unit'] ?? '';

        if ( empty(esc_html($key)) ) {
            return '<b>' . __('Block row error', 'hrx-delivery') . '!</b> ' . __('Not received field key', 'hrx-delivery') . '.';
        }

        $wcTools = new WcTools();

        ob_start();
        ?>
        <div class="section-row row-sample row_key-<?php echo esc_attr($key); ?>">
            <div class="row_item-title <?php echo (!empty($tip)) ? 'has_tip' : ''; ?>">
                <label for="<?php echo esc_attr($key); ?>"><?php echo esc_html($title); ?></label>
                <?php if ( ! empty($tip) ) : ?>
                    <?php echo $wcTools->add_help_tip($tip); ?>
                <?php endif; ?>
            </div>
            <div class="row_item-value <?php echo (!empty($unit)) ? 'has_unit' : ''; ?>">
                <?php echo self::field_number(array(
                    'name' => esc_attr($name),
                    'id' => esc_attr($key),
                    'value' => $value,
                    'step' => $step,
                    'min' => $min,
                    'max' => $max,
                )); ?>
                <?php if ( ! empty($unit) ) : ?>
                    <span class="unit_value"><?php echo $unit; ?></span>
                <?php endif; ?>
                <?php if ( ! empty($description) ) : ?>
                    <p class="description"><?php echo $description; ?></p>
                <?php endif; ?>
            </div>
        </div>
        <?php
        $html = ob_get_contents();
        ob_end_clean();

        return $html;
    }

    private static function build_price_by_weight_row( $params )
    {
        $row_number = $params['row_number'] ?? 0;
        $key = $params['key'] ??  '';
        $name = $params['name'] ??  '';
        $values = $params['values'] ?? array();
        $hide_row_add_btn = $params['hide_add'] ?? false;

        if ( empty(esc_html($key)) ) {
            return '<b>' . __('Block error', 'hrx-delivery') . '!</b> ' . __('Not received fields key', 'hrx-delivery') . '.';
        }

        $wcTools = new WcTools();
        $units = $wcTools->get_units();

        ob_start();
        ?>
        <div class="prices_range <?php echo (! $row_number) ? 'first' : ''; ?>" data-key="<?php echo esc_html($key); ?>" data-name="<?php echo esc_html($name); ?>">
            <table>
                <tr class="range-row-price">
                    <td class="range-col-title"><?php _e('Price', 'hrx-delivery'); ?></td>
                    <td class="range-col-value">
                        <?php echo self::field_number(array(
                            'name' => esc_html($name) . '[' . $row_number . '][price]',
                            'id' => esc_html($key) . '_' . $row_number . '_price',
                            'value' => $values['price'] ?? '',
                            'step' => 0.01,
                            'min' => 0,
                        )); ?>
                    </td>
                    <td class="range-col-unit"><?php echo $units->currency_symbol; ?></td>
                </tr>
                <tr class="range-row-weight_range">
                    <td class="range-col-title"><?php _e('Weight range', 'hrx-delivery'); ?></td>
                    <td class="range-col-value">
                        <div class="fields_holder">
                        <?php echo self::field_number(array(
                            'name' => esc_html($name) . '[' . $row_number . '][w_from]',
                            'id' => esc_html($key) . '_' . $row_number . '_w_from',
                            'value' => $values['w_from'] ?? '',
                            'step' => 0.001,
                            'min' => 0,
                            'max' => $values['w_to'] ?? '',
                            'class' => 'field-w_from',
                        )); ?>
                        <span class="range-range_separate">-</span>
                        <?php echo self::field_number(array(
                            'name' => esc_html($name) . '[' . $row_number . '][w_to]',
                            'id' => esc_html($key) . '_' . $row_number . '_w_to',
                            'value' => $values['w_to'] ?? '',
                            'step' => 0.001,
                            'min' => $values['w_from'] ?? 0,
                            'class' => 'field-w_to',
                        )); ?>
                        </div>
                    </td>
                    <td class="range-col-unit"><?php echo $units->weight; ?></td>
                </tr>
                <tr class="range-row-actions">
                    <td class="range-col-title"></td>
                    <td class="range-col-value">
                        <div class="fields_holder">
                            <?php $btn_style = ($hide_row_add_btn) ? 'display:none;' : ''; ?>
                            <button type="button" name="<?php esc_html($key) . '_' . $row_number . '_add'; ?>" class="button button-secondary hrx-btn-add" value="add" style="<?php echo $btn_style; ?>"><span class="dashicons dashicons-plus-alt"></span><span class="btn-title"><?php _e('Add extra rule', 'hrx-delivery'); ?></span></button>
                            <button type="button" name="<?php esc_html($key) . '_' . $row_number . '_remove'; ?>" class="button button-secondary hrx-btn-remove" value="remove" data-id="<?php echo $row_number; ?>"><span class="dashicons dashicons-trash"></span></button>
                        </div>
                    </td>
                    <td class="range-col-unit"></td>
                </tr>
            </table>
        </div>
        <?php
        $html = ob_get_contents();
        ob_end_clean();

        return $html;
    }

    private static function catch_status_keywords( $text )
    {
        $keywords = Debug::status_keywords();

        foreach ( $keywords as $id => $word ) {
            $text = str_replace($word, '<span class="debug-status-' . $id . '">' . $word . '</span>', $text);
        }

        return $text;
    }
}
