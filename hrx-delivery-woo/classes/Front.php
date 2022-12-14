<?php
namespace HrxDeliveryWoo;

// Prevent direct access to this script
if ( ! defined('ABSPATH') ) {
    exit;
}

use HrxDeliveryWoo\Sql;
use HrxDeliveryWoo\Core;
use HrxDeliveryWoo\Helper;
use HrxDeliveryWoo\Terminal;
use HrxDeliveryWoo\ShippingMethodHelper as ShipHelper;

class Front
{
    private $core;
    private $terminal_field;

    public function __construct()
    {
        $this->core = Core::get_instance();
        $this->terminal_field = $this->core->id . '_terminal';
    }

    public function init()
    {
        add_action('woocommerce_after_shipping_rate', array($this, 'after_rate_show_terminals'));
        add_action('woocommerce_checkout_update_order_meta', array($this, 'save_terminal_to_order'));
        add_action('woocommerce_review_order_before_cart_contents', array($this, 'validate_order'), 10);
        add_action('woocommerce_after_checkout_validation', array($this, 'validate_order'), 10);
    }

    public function after_rate_show_terminals( $method )
    {
        /* Not execute if is not this plugin shipping */
        if ( $method->get_method_id() != $this->core->id ) {
            return;
        }

        $method_key = $this->remove_id_from_method_name($method->get_id());
        
        /* Not execute if this method not exists in plugin methods list */
        if ( ! isset($this->core->methods[$method_key]) ) {
            return;
        }

        /* Not execute if this method not have terminals */
        if ( empty($this->core->methods[$method_key]['has_terminals']) ) {
            return;
        }

        /* Not execute if selectedd method is not this */
        if ( Helper::get_first_value_from_array(\WC()->session->get('chosen_shipping_methods')) != $method->get_id() ) {
            return;
        }

        $country = $this->get_customer_shipping_country();
        $terminals_list = Terminal::get_list($country);

        //echo $this->build_terminals_select($terminals_list, $method_key); // Not using
        echo $this->build_terminals_script($terminals_list, $country, $method_key);
    }

    public function save_terminal_to_order( $order_id )
    {
        if ( ! $order_id ) {
            return;
        }

        if ( isset($_POST[$this->terminal_field]) ) {
            update_post_meta($order_id, $this->core->meta_keys->terminal_id, esc_attr($_POST[$this->terminal_field]));
        }

        if ( isset($_POST['shipping_method']) ) {
            $selected_method = Helper::get_first_value_from_array($_POST['shipping_method']);
            update_post_meta($order_id, $this->core->meta_keys->method, $this->remove_id_from_method_name($selected_method));
        }
    }

    public function validate_order( $fields )
    {
        if ( empty($fields['shipping_method']) ) {
            $chosen_methods = \WC()->session->get('chosen_shipping_methods');
        } else {
            $chosen_methods = $fields['shipping_method'];
        }

        if ( ! is_array($chosen_methods) ) {
            return;
        }

        foreach ( $chosen_methods as $chosen_method ) {
            if ( ! ShipHelper::is_hrx_rate($chosen_method) ) {
                continue;
            }

            $hrx_method_key = ShipHelper::get_method_from_rate_id($chosen_method);
            if ( ! isset($this->core->methods[$hrx_method_key]) ) {
                continue;
            }

            if ( empty($this->core->methods[$hrx_method_key]['has_terminals']) ) {
                continue;
            }

            if ( empty($_POST[$this->terminal_field]) ) {
                $message = sprintf(__('Please choose %s', 'hrx-delivery'), '<b>' . $this->core->methods[$hrx_method_key]['front_title'] . '</b>');
                $messageType = 'error';

                if ( ! empty($fields) && ! wc_has_notice($message, $messageType) ) {
                    wc_add_notice($message, $messageType);
                }
            }
        }
    }

    private function get_customer_shipping_country()
    {
        $country = false;
        $customer = WC()->session->get('customer');

        if ( ! empty($customer['shipping_country']) ) {
            $country = $customer['shipping_country'];
        } elseif ( ! empty($customer['country']) ) {
            $country = $customer['country'];
        }

        return $country;
    }

    private function remove_id_from_method_name( $method_name )
    {
        return str_replace($this->core->id . '_', '', $method_name);
    }

    private function build_terminals_select( $terminals_list, $method_key )
    {
        $output = '<div class="hrx-terminal-container hrx-show-dropdown hrx-method-' . $method_key . '">';
        $output .= Terminal::build_select_field(array(
            'name' => $this->core->id . '_' . $method_key,
            'all_terminals' => $terminals_list,
            'selected_id' => WC()->session->get($this->core->id . '_terminal'),
        ));
        $output .= '</div>';

        return $output;
    }

    private function build_terminals_script( $terminals_list, $country, $method_key )
    {
        $output = '<div class="hrx-terminal-container hrx-show-map hrx-method-' . $method_key . '">';
        $output .= '<input type="hidden" id="hrx-method-' . $method_key . '-selected" name="' . $this->core->id . '_' . $method_key . '" value="' . WC()->session->get($this->core->id . '_terminal') . '"/>';
        $output .= '<div id="hrx-method-' . $method_key . '-map" class="hrx-map map-' . $method_key . '" data-method="' . $method_key .'" data-country="' . $country . '"></div>';
        $output .= Terminal::build_list_in_script(array(
            'method' => $method_key,
            'country' => $country,
            'all_terminals' => $terminals_list,
        ));
        $output .= '</div>';

        return $output;
    }
}
