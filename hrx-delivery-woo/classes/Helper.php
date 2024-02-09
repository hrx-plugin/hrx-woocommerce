<?php
namespace HrxDeliveryWoo;

// Prevent direct access to this script
if ( ! defined('ABSPATH') ) {
    exit;
}

use HrxDeliveryWoo\Core;

class Helper
{
    public static function check_woocommerce()
    {
        return in_array('woocommerce/woocommerce.php', apply_filters('active_plugins', get_option('active_plugins')));
    }

    public static function build_admin_message( $message, $type = 'warning', $prefix = false, $dismissible = false )
    {
        $class = 'notice notice-' . $type;
        if ( $dismissible ) {
            $class .= ' is-dismissible';
        }
        if ( $prefix ) {
            $message = '<b>' . $prefix . ':</b> ' . $message;
        }

        return '<div class="' . $class . '"><p>' . $message . '</p></div>';
    }

    public static function show_admin_message( $message, $type = 'warning', $prefix = false, $dismissible = false )
    {
        $message = self::build_admin_message($message, $type, $prefix, $dismissible);
        add_action('admin_notices', function() use ( $message ) {
            echo $message;
        }, 10);
    }

    public static function get_plugin_information( $path_to_main_file )
    {
        require_once(ABSPATH . 'wp-admin/includes/plugin.php');

        return get_plugin_data($path_to_main_file);
    }

    public static function is_json( $string )
    {
        json_decode($string);
        
        return json_last_error() === JSON_ERROR_NONE;
    }

    public static function object_to_array_recursively( $object )
    {
        if ( is_object($object) || is_array($object) ) {
            $array = (array) $object;
            foreach( $array as &$item) {
                $item = self::object_to_array_recursively($item);
            }
            return $array;
        } else {
            return $object;
        }
    }

    public static function get_available_countries()
    {
        $core = Core::get_instance();

        $available_countries = array();
        foreach ( $core->methods as $method_params ) {
            foreach ( $method_params['countries'] as $country ) {
                if ( ! in_array($country, $available_countries) ) {
                    $available_countries[] = $country;
                }
            }
        }

        return $available_countries;
    }

    public static function get_hrx_option( $option_name, $fail_value = false )
    {
        $core = Core::get_instance();

        return get_option($core->option_prefix . '_' . $option_name, $fail_value);
    }

    public static function update_hrx_option( $option_name, $value )
    {
        $core = Core::get_instance();

        return update_option($core->option_prefix . '_' . $option_name, $value);
    }

    public static function delete_hrx_option( $option_name )
    {
        $core = Core::get_instance();

        return delete_option($core->option_prefix . '_' . $option_name);
    }

    public static function is_settings_checkbox_marked( $checkbox_key )
    {
        $core = Core::get_instance();

        if ( ! isset($core->settings[$checkbox_key]) ) {
            return null;
        }

        if ( $core->settings[$checkbox_key] == 'yes' ) {
            return true;
        }

        return false;
    }

    public static function get_first_value_from_array( $array )
    {
        if ( is_array($array) ) {
            foreach ( $array as $value ) {
                return $value;
            }
        }

        return $array;
    }

    public static function get_next_key_in_array( $array, $key )
    {
        $all_keys = array_keys($array);

        return $all_keys[array_search($key, $all_keys) + 1];
    }

    public static function method_has_terminals( $method_key )
    {
        $core = Core::get_instance();

        if ( ! isset($core->methods[$method_key]) ) {
            return false;
        }

        if ( empty($core->methods[$method_key]['has_terminals']) ) {
            return false;
        }

        return true;
    }

    public static function get_array_element_by_it_value( $array, $get_by )
    {
        foreach ( $array as $subarray ) {
            $get_this = true;
            
            foreach ( $get_by as $by_key => $by_value ) {
                if ( ! isset($subarray[$by_key]) || $subarray[$by_key] != $by_value ) {
                    $get_this = false;
                }
            }
            
            if ( $get_this ) {
                return $subarray;
            }
        }

        return false;
    }

    public static function get_compare_symbol( $symbol_key )
    {
        $all_symbols = array(
            'min' => '<',
            'max' => '>',
            'min_equal' => '<=',
            'max_equal' => '>=',
            'not_equal' => '!=',
        );

        return $all_symbols[$symbol_key] ?? false;
    }

    public static function get_empty_dimensions_array( $fill_with_value = '' )
    {
        return array(
            'width' => $fill_with_value,
            'height' => $fill_with_value,
            'length' => $fill_with_value,
            'weight' => $fill_with_value,
        );
    }

    public static function use_current_or_default_dimmension( $method_key, $current_dimensions )
    {
        $core = Core::get_instance();

        if ( empty($core->settings[$method_key . '_default_dimensions']) ) {
            return $current_dimensions;
        }

        $default_values = json_decode($core->settings[$method_key . '_default_dimensions']);
        if ( ! is_array($default_values) ) {
            return $current_dimensions;
        }

        $default_dimensions = array(
            'width' => (! empty($default_values[0])) ? $default_values[0] : 0,
            'height' => (! empty($default_values[1])) ? $default_values[1] : 0,
            'length' => (! empty($default_values[2])) ? $default_values[2] : 0,
            'weight' => (! empty($default_values[3])) ? $default_values[3] : 0,
        );

        if ( empty($current_dimensions['weight']) && $current_dimensions['weight'] !== '0' ) {
            $current_dimensions['weight'] = $default_dimensions['weight'];
        }

        if ( (empty($current_dimensions['width']) && $current_dimensions['width'] !== '0')
            || (empty($current_dimensions['height']) && $current_dimensions['height'] !== '0')
            || (empty($current_dimensions['length']) && $current_dimensions['length'] !== '0') ) {
            $current_dimensions['width'] = $default_dimensions['width'];
            $current_dimensions['height'] = $default_dimensions['height'];
            $current_dimensions['length'] = $default_dimensions['length'];
        }

        return $current_dimensions;
    }

    public static function check_regex( $org_value, $prefix, $regex )
    {
        $value = self::remove_prefix( $org_value, $prefix );

        if ( ! preg_match('/' . $regex . '/', $value) ) {
            return false;
        }

        if ( $org_value == $prefix . $value || $org_value == $value ) {
            return true;
        }

        return false;
    }

    public static function remove_prefix( $value, $prefix )
    {
        if ( substr($value, 0, strlen($prefix)) === $prefix ) {
            $value = substr($value, strlen($prefix));
        }

        return $value;
    }

    public static function beautify_regex( $regex )
    {
        $regex = str_replace('^', '', $regex);
        $regex = str_replace('$', '', $regex);

        if ( str_contains($regex, '\d') ) {
            preg_match_all('/d{(.*?)}/', $regex, $numbers);
            foreach ( $numbers[1] as $number ) {
                $regex = str_replace('\d{' . $number . '}', str_repeat('0',(int)$number), $regex);
            }
        }
        if ( str_contains($regex, '\s') ) {
            preg_match_all('/s{(.*?)}/', $regex, $spaces);
            foreach ( $spaces[1] as $space ) {
                $space_exploded = explode(',', $space);
                if ( count($space_exploded) != 2 ) {
                    continue;
                }
                $regex = str_replace('\s{' . $space . '}', str_repeat(' ',(int)$space_exploded[1] - $space_exploded[0]), $regex);
            }
        }

        return $regex;
    }

    public static function compare_values( $value_1, $value_2, $action = '=' )
    {
        if ( ! is_numeric($value_1) || ! is_numeric($value_2) ) {
            return false;
        }

        if ( $action == '=' && $value_1 == $value_2 ) {
            return true;
        }
        if ( $action == '!=' && $value_1 != $value_2 ) {
            return true;
        }
        if ( $action == '>=' && $value_1 >= $value_2 ) {
            return true;
        }
        if ( $action == '<=' && $value_1 <= $value_2 ) {
            return true;
        }
        if ( $action == '>' && $value_1 > $value_2 ) {
            return true;
        }
        if ( $action == '<' && $value_1 < $value_2 ) {
            return true;
        }

        return false;
    }
}
