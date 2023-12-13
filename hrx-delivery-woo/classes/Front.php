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
use HrxDeliveryWoo\LocationsHelper as LocHelper;
use HrxDeliveryWoo\WcOrder;
use HrxDeliveryWoo\WcTools;

class Front
{
    private $core;
    private $wc;
    private $terminal_field;

    public function __construct()
    {
        $this->core = Core::get_instance();
        $this->terminal_field = $this->core->id . '_terminal';
        $this->wc = (object) array(
            'order' => new WcOrder(),
            'tools' => new WcTools(),
        );
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
        if ( Helper::get_first_value_from_array($this->wc->tools->get_session('chosen_shipping_methods', false)) != $method->get_id() ) {
            return;
        }

        $country = $this->get_customer_shipping_country();
        $terminals_list = LocationsDelivery::get_list($method_key, $country);

        /* Search closest terminals in radius */
        $address = trim($this->get_customer_shipping_address(false, ''));
        $radius = 50;
        $show_selection = true;
        if ( empty($address) ) {
            $radius = 0.001;
            $show_selection = false;
        }
        $coordinates = LocHelper::get_coordinates_by_address($address, $country);
        if ( $coordinates ) {
            $terminals_list = $this->filter_terminals_by_radius($terminals_list, $coordinates['latitude'], $coordinates['longitude'], $radius);
        }

        /* Echo elements */
        if ( $show_selection ) {
            //echo $this->build_terminals_select($terminals_list, $method_key); // Not using
            echo $this->build_terminals_script($terminals_list, $country, $method_key);
        } else {
            echo $this->build_fake_terminals_select(__('Please enter an address', 'hrx-delivery'), $method_key);
        }
    }

    public function save_terminal_to_order( $order_id )
    {
        if ( ! $order_id ) {
            return;
        }

        if ( isset($_POST['shipping_method']) ) {
            $selected_method = Helper::get_first_value_from_array($_POST['shipping_method']);
        }

        if ( ! ShipHelper::is_hrx_rate($selected_method) ) {
            return;
        }

        $save_data = array();

        if ( isset($_POST[$this->terminal_field]) ) {
            $save_data['terminal_id'] = esc_attr($_POST[$this->terminal_field]);
        }

        if ( isset($_POST['shipping_method']) ) {
            $save_data['method'] = $this->remove_id_from_method_name($selected_method);
        }

        $this->wc->order->update_hrx_data($order_id, $save_data);
    }

    public function validate_order( $fields )
    {
        if ( empty($fields['shipping_method']) ) {
            $chosen_methods = $this->wc->tools->get_session('chosen_shipping_methods', false);
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
                $message_type = 'error';

                if ( ! empty($fields) ) {
                    $this->wc->tools->add_notice($message, $message_type);
                }
            }
        }
    }

    private function get_customer_shipping_country()
    {
        $country = false;
        $customer = $this->wc->tools->get_session('customer', false);

        if ( ! empty($customer['shipping_country']) ) {
            $country = $customer['shipping_country'];
        } elseif ( ! empty($customer['country']) ) {
            $country = $customer['country'];
        }

        return $country;
    }

    private function get_customer_shipping_address( $with_country = true, $separator = ',' )
    {
        $address = '';
        
        $address_array = $this->get_customer_shipping_address_shipping_array();
        if ( empty($address_array['address_1']) && empty($address_array['city']) && empty($address_array['postcode']) ) {
            $address_array = $this->get_customer_shipping_address_array();
        }

        $address_array['country'] = ($with_country) ? $separator . ' ' . $address_array['country'] : '';

        return $address_array['address_1'] . $separator . ' ' . $address_array['city'] . ' ' . $address_array['postcode'] . $address_array['country'];
    }

    private function get_customer_shipping_address_array()
    {
        $customer = $this->wc->tools->get_session('customer', false);

        return array(
            'address_1' => $customer['shipping_address_1'] ?? '',
            'address_2' => $customer['shipping_address_2'] ?? '',
            'city' => $customer['shipping_city'] ?? '',
            'state' => $customer['shipping_state'] ?? '',
            'postcode' => $customer['shipping_postcode'] ?? '',
            'country' => $customer['shipping_country'] ?? '',
        );
    }

    private function get_customer_shipping_address_shipping_array()
    {
        $customer = $this->wc->tools->get_session('customer', false);

        return array(
            'address_1' => $customer['address_1'] ?? '',
            'address_2' => $customer['address_2'] ?? '',
            'city' => $customer['city'] ?? '',
            'state' => $customer['state'] ?? '',
            'postcode' => $customer['postcode'] ?? '',
            'country' => $customer['country'] ?? '',
        );
    }

    private function remove_id_from_method_name( $method_name )
    {
        return str_replace($this->core->id . '_', '', $method_name);
    }

    private function build_fake_terminals_select( $text, $method_key )
    {
        $output = '<div class="hrx-terminal-container hrx-fake hrx-method-' . $method_key . '">';
        $output .= '<div class="hrx-map">';
        $output .= '<div class="tmjs-container">';
        $output .= '<div class="tmjs-selected-terminal">' . $text . '</div>';
        $output .= '<button class="tmjs-open-modal-btn disabled" disabled>' . __('Choose terminal', 'hrx-delivery') . '</button>';
        $output .= '</div></div></div>';

        return $output;
    }

    private function build_terminals_select( $terminals_list, $method_key )
    {
        $output = '<div class="hrx-terminal-container hrx-show-dropdown hrx-method-' . $method_key . '">';
        $output .= Terminal::build_select_field(array(
            'name' => $this->core->id . '_' . $method_key,
            'all_terminals' => $terminals_list,
            'selected_id' => $this->wc->tools->get_session('terminal'),
        ));
        $output .= '</div>';

        return $output;
    }

    private function build_terminals_script( $terminals_list, $country, $method_key )
    {
        $output = '<div class="hrx-terminal-container hrx-show-map hrx-method-' . $method_key . '">';
        $output .= '<input type="hidden" id="hrx-method-' . $method_key . '-selected" name="' . $this->core->id . '_' . $method_key . '" value="' . $this->wc->tools->get_session('terminal') . '"/>';
        $output .= '<div id="hrx-method-' . $method_key . '-map" class="hrx-map map-' . $method_key . '" data-method="' . $method_key .'" data-country="' . $country . '"></div>';
        $output .= Terminal::build_list_in_script(array(
            'method' => $method_key,
            'country' => $country,
            'all_terminals' => $terminals_list,
        ));
        $output .= '</div>';

        return $output;
    }

    private function filter_terminals_by_radius( $terminals_list, $center_lat = 0, $center_lon = 0, $radius = false )
    {
        if ( ! $radius ) {
            return $terminals_list;
        }

        $filtered_list = [];
        foreach ( $terminals_list as $terminal ) {
            $distance = LocHelper::calculate_distance_between_points($center_lat, $center_lon, $terminal->latitude, $terminal->longitude);
            if ( $distance <= $radius ) {
                $filtered_list[] = $terminal;
            }
        }

        return $filtered_list;
    }
}
