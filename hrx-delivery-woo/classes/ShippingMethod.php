<?php
namespace HrxDeliveryWoo;

// Prevent direct access to this script
if ( ! defined('ABSPATH') ) {
  exit;
}

use HrxDeliveryWoo\Helper;
use HrxDeliveryWoo\Core;
use HrxDeliveryWoo\ShippingMethodHelper as ShipHelper;
use HrxDeliveryWoo\ShippingMethodHtml as Html;
use HrxDeliveryWoo\Debug;

if ( ! class_exists('\HrxDeliveryWoo\ShippingMethod') ) {

    class ShippingMethod extends \WC_Shipping_Method
    {
        private $core;

        public function __construct( $instance_id = 0 )
        {
            $this->core = $this->get_core();

            parent::__construct($instance_id);

            $this->load();
        }

        public function load()
        {
            $this->id = $this->core->id;
            $this->method_title = $this->core->title;
            $this->method_description = __('Shipping methods for HRX delivery', 'hrx-delivery');
            $this->supports = array(
                'settings',
            );

            $available_countries = array();
            foreach ( $this->core->methods as $method_params ) {
                foreach ( $method_params['countries'] as $country ) {
                    if ( ! in_array($country, $available_countries) ) {
                        $available_countries[] = $country;
                    }
                }
            }

            $this->availability = 'including';
            $this->countries = Helper::get_available_countries($this->core->methods);

            $this->init();

            $this->enabled = isset($this->settings['enabled']) ? $this->settings['enabled'] : 'yes';
            $this->title = isset($this->settings['title']) ? $this->settings['title'] : $this->core->title;
        }

        public function get_core()
        {
            return Core::get_instance();
        }

        public function init()
        {
            $this->init_form_fields();
            $this->init_settings();

            // Save settings in admin if you have any defined
            add_action('woocommerce_update_options_shipping_' . $this->id, array($this, 'process_admin_options'));
        }

        /**
         * Load settings form
         */
        public function admin_options()
        {
          ?>
          <h2><?php echo $this->method_title; ?></h2>
          <p><?php echo $this->method_description; ?></p>
          <table class="form-table hrx-settings">
            <?php $this->generate_settings_html(); ?>
          </table>
          <?php
        }

        public function init_form_fields()
        {
            $fields = array(
                'enable' => array(
                    'title' => __('Enable', 'hrx-delivery'),
                    'type' => 'checkbox',
                    'description' => sprintf(__('Enable %s delivery', 'hrx-delivery'), _x('this', 'This object', 'hrx-delivery')),
                    'default' => 'yes',
                    'class' => 'global-enable',
                )
            );

            $fields['hr_api'] = array(
                'type' => 'hr',
                'title' => __('API', 'hrx-delivery')
            );

            $fields['api_token'] = array(
                'title' => __('API key', 'hrx-delivery'),
                'type' => 'custom_textarea',
                'default' => '',
                'description' => __('The key, which you received from HRX', 'hrx-delivery'),
                'custom_attributes' => array(
                    'rows' => 2,
                ),
                'class' => 'textarea-static',
                'top_class' => 'apimode-toggle-live',
            );

            $fields['api_test_token'] = array(
                'title' => __('API key in test server', 'hrx-delivery'),
                'type' => 'custom_textarea',
                'default' => '',
                'description' => __('The key, which you received from HRX', 'hrx-delivery'),
                'custom_attributes' => array(
                    'rows' => 2,
                ),
                'class' => 'textarea-static',
                'top_class' => 'apimode-toggle-test',
                'hide' => true,
            );

            $fields['api_check_token'] = array(
                'type' => 'action_button',
                'label' => __('Check key', 'hrx-delivery'),
                'action' => 'check_token',
                'id' => 'check_token',
                'description' => __('Check available only after settings save', 'hrx-delivery'),
            );

            $fields['test_mode'] = array(
                'title' => __('Use API test server', 'hrx-delivery'),
                'type' => 'checkbox',
                'label' => __('Enable', 'hrx-delivery'),
                'description' => __('Once activated, all requests will run on the test server without touching real server data. The data used (orders, locations) may differ from real server. You also need the API key which is registered in the test server.', 'omnivalt'),
                'default' => 'no',
                'class' => 'apimode-toggle-cb',
            );

            $fields['api_upd_pickup_data'] = array(
                'title' => __('Pickup locations (Warehouses)', 'hrx-delivery'),
                'type' => 'action_button',
                'label' => __('Update locations', 'hrx-delivery'),
                'action' => 'update_pickup_locations',
                'id' => 'upd_pickup_loc',
                'message' => __('Last update', 'hrx-delivery') . ':',
                'default' => __('Never', 'hrx-delivery'),
                'value' => Helper::get_hrx_option('last_sync_pickup_loc', ''),
                'repeat' => '60 days',
            );

            $fields['api_upd_delivery_data'] = array(
                'title' => __('Delivery locations', 'hrx-delivery'),
                'type' => 'action_button',
                'label' => __('Update locations', 'hrx-delivery'),
                'action' => 'update_delivery_locations',
                'id' => 'upd_delivery_loc',
                'message' => __('Last update', 'hrx-delivery') . ':',
                'default' => __('Never', 'hrx-delivery'),
                'value' => Helper::get_hrx_option('last_sync_delivery_loc', ''),
                'repeat' => '30 days',
            );

            $fields['hr_methods'] = array(
                'type' => 'hr',
                'title' => __('Delivery methods', 'hrx-delivery')
            );

            $first = true;
            foreach ( $this->core->methods as $method_key => $method_params ) {
                if ( ! $first ) {
                    $fields[$method_key . '_hr'] = array('type' => 'hr');
                }
                
                $fields[$method_key . '_enable'] = array(
                    'title' => $method_params['title'],
                    'type' => 'checkbox',
                    'label' => __('Enable', 'hrx-delivery'),
                    'description' => sprintf(__('Enable %s delivery', 'hrx-delivery'), strtolower($method_params['title'])),
                    'default' => 'no',
                    'class' => 'hrx-method',
                    'custom_attributes' => array(
                        'data-key' => $method_key,
                    ),
                );

                $fields[$method_key . '_default_dimensions'] = array(
                    'title' => __('Default dimensions', 'hrx-delivery'),
                    'type' => 'dimensions',
                    'description' => __('Default shipment dimensions, if they are not specified in the Order', 'hrx-delivery'),
                    'top_class' => 'hrx-method-' . $method_key,
                );

                $fields[$method_key . '_default_price'] = array(
                    'title' => __('Default delivery price', 'hrx-delivery'),
                    'type' => 'price',
                    'description' => __('Use this price when the cart does not fit any range', 'hrx-delivery'),
                    'top_class' => 'hrx-method-' . $method_key,
                );

                $fields[$method_key . '_free_from'] = array(
                    'title' => __('Free delivery from price', 'hrx-delivery'),
                    'type' => 'price',
                    'description' => __('Cart amount from which this shipping becomes free', 'hrx-delivery') . '. ' . __('Leave blank to turn off', 'hrx-delivery') . '.',
                    'top_class' => 'hrx-method-' . $method_key,
                );

                $fields[$method_key . '_prices'] = array(
                    'title' => __('Specified price for countries', 'hrx-delivery'),
                    'type' => 'prices_for_countries',
                    'countries' => $method_params['countries'],
                    'top_class' => 'hrx-method-' . $method_key,
                );
                
                $first = false;
            }

            /*$fields['hr_settings'] = array( //TODO: Disabled because section is empty
                'type' => 'hr',
                'title' => __('Additional settings', 'hrx-delivery')
            );*/

            /*$fields['label_size'] = array( // The library has no option to do this
                'title' => __('Labels printing type', 'hrx-delivery'),
                'type'    => 'select',
                'options' => array(
                    '1' => __('Original size single label', 'hrx-delivery'),
                    '4' => __('4 labels on A4 size sheet', 'hrx-delivery')
                ),
                'default' => '1',
            );*/

            /*$fields['require_return_label'] = array( //TODO: Disabled because it's not clear what it's for
                'title' => __('Require return label', 'hrx-delivery'),
                'type' => 'checkbox',
                'label' => __('Require', 'hrx-delivery'),
                'default' => 'no',
            );*/

            $fields['hr_debug'] = array(
                'type' => 'hr',
                'title' => __('Plugin functionality checking', 'hrx-delivery')
            );

            $fields['debug_enable'] = array(
                'title' => __('Enable error checking mode', 'hrx-delivery'),
                'type' => 'checkbox',
                'label' => __('Enable', 'hrx-delivery'),
                'default' => 'no',
                'description' => sprintf(__('Enable plugin errors checking and logging. Log files are stored for %d days.', 'hrx-delivery'), 30),
                'class' => 'debug-toggle-cb',
            );

            $fields['debug_plugin'] = array(
                'title' => '',
                'type' => 'debug_plugin',
                'top_class' => 'debug-toggle',
            );

            $this->form_fields = $fields;
        }

        public function generate_hr_html( $key, $value )
        {
            return Html::build_hr($value);
        }

        public function generate_custom_textarea_html( $key, $value )
        {
            return Html::build_textarea($this->get_field_key($key), $value, $this->get_option($key));
        }

        public function generate_action_button_html( $key, $value )
        {
            return Html::build_action_button($this->get_field_key($key), $value);
        }

        public function generate_dimensions_html( $key, $value )
        {
            return Html::build_dimensions($this->get_field_key($key), $value, $this->get_option($key));
        }

        public function validate_dimensions_field( $key, $value ) {
            $values = wp_json_encode($value);
            return $values;
        }

        public function generate_price_html( $key, $value )
        {
            return Html::build_price($this->get_field_key($key), $value, $this->get_option($key));
        }

        public function generate_prices_for_countries_html( $key, $value )
        {
            $img_dir_url = $this->core->structure->url . $this->core->structure->img . 'flags/';
            return Html::build_prices_for_countries($this->get_field_key($key), $value, json_decode($this->get_option($key), true), $img_dir_url);
        }

        public function validate_prices_for_countries_field( $key, $value ) {
            $values = wp_json_encode($value);
            return $values;
        }

        public function generate_debug_plugin_html( $key, $value )
        {
            if ( $this->get_option('debug_enable', 'no') == 'no' ) {
                return '';
            }

            return Html::build_debug_plugin($value);
        }

        public function calculate_shipping($package = array())
        {
            $country = $package['destination']['country'];
            $cart_amount = ShipHelper::get_cart_amount();
            $cart_weigth = ShipHelper::get_cart_products_weight($package['contents']);

            /* Check if this plugin shipping methods is enabled */
            if ( $this->settings['enable'] != 'yes' ) {
                return;
            }

            foreach ( $this->core->methods as $method_key => $method_params ) {
                /* Check if shipping method is available in selected country */
                if ( ! in_array($country, $method_params['countries']) ) {
                    continue;
                }

                /* Check if shipping method is enabled */
                if ( $this->settings[$method_key . '_enable'] != 'yes') {
                    continue;
                }

                $prices = json_decode($this->settings[$method_key . '_prices'], true);

                /* Check if shipping method is enabled for selected country */
                if ( empty($prices[$country]['enable']) ) {
                    continue;
                }

                /* Get shipping price */
                $current_price = ShipHelper::get_price_by_weight($prices[$country]['prices'], $cart_weigth);
                if ( $current_price === false ) {
                    $current_price = (! empty($this->settings[$method_key . '_default_price'])) ? (float)$this->settings[$method_key . '_default_price'] : 0;
                }

                /* Check if shipping is free */
                if ( ! empty($this->settings[$method_key . '_free_from']) && $cart_amount >= $this->settings[$method_key . '_free_from'] ) {
                    $current_price = 0;
                }

                /* Build rate */
                $rate = array(
                    'id' => ShipHelper::get_rate_id($method_key),
                    'label' => $method_params['front_title'],
                    'cost' => $current_price,
                );

                $this->add_rate($rate);
            }
        }
    }

}
