<?php
namespace HrxDeliveryWoo;

// Prevent direct access to this script
if ( ! defined('ABSPATH') ) {
    exit;
}

use HrxApi\API as HrxLib_Api;
use HrxApi\Receiver as HrxLib_Receiver;
use HrxApi\Shipment as HrxLib_Shipment;
use HrxApi\Order as HrxLib_Order;

class Api
{
    private $config;

    public function __construct( $config = array() )
    {
        $this->config = $this->prepare_config($config);
    }

    public function get_pickup_locations( $page = 1, $per_page = 100 )
    {
        $output = $this->prepare_output();

        try {
            $api = $this->load_api();
            $output['data'] = $api->getPickupLocations($page, $per_page);
        } catch (\Exception $e) {
            $output['status'] = 'error';
            $output['msg'] = $this->convert_error_msg($e->getMessage());
        }

        return $output;
    }

    public function get_delivery_locations( $page = 1, $per_page = 100 )
    {
        $output = $this->prepare_output();

        try {
            $api = $this->load_api();
            $output['data'] = $api->getDeliveryLocations($page, $per_page);
        } catch (\Exception $e) {
            $output['status'] = 'error';
            $output['msg'] = $this->convert_error_msg($e->getMessage());
        }

        return $output;
    }

    public function get_courier_delivery_locations()
    {
        $output = $this->prepare_output();

        try {
            $api = $this->load_api();
            $output['data'] = $api->getCourierDeliveryLocations();
        } catch (\Exception $e) {
            $output['status'] = 'error';
            $output['msg'] = $this->convert_error_msg($e->getMessage());
        }

        return $output;
    }

    public function get_orders( $page = 1, $per_page = 100 )
    {
        $output = $this->prepare_output();

        try {
            $api = $this->load_api();
            $output['data'] = $api->getOrders($page, $per_page);
        } catch (\Exception $e) {
            $output['status'] = 'error';
            $output['msg'] = $this->convert_error_msg($e->getMessage());
        }

        return $output;
    }

    public function create_order( $order_params )
    {
        $output = $this->prepare_output();
        $order_params = $this->prepare_order_params($order_params);

        try {
            $api = $this->load_api();

            $receiver = new HrxLib_Receiver();
            $receiver
                ->setName($order_params['receiver']['name'])
                ->setEmail($order_params['receiver']['email'])
                ->setPhone($order_params['receiver']['phone'], $order_params['receiver']['phone_regex'])
                ->setAddress($order_params['receiver']['address'])
                ->setPostcode($order_params['receiver']['postcode'])
                ->setCity($order_params['receiver']['city'])
                ->setCountry($order_params['receiver']['country']);

            $shipment = new HrxLib_Shipment();
            $shipment
                ->setReference($order_params['shipment']['reference'])
                ->setComment($order_params['shipment']['comment'])
                ->setLength($order_params['shipment']['length'])
                ->setWidth($order_params['shipment']['width'])
                ->setHeight($order_params['shipment']['height'])
                ->setWeight($order_params['shipment']['weight']);

            $delivery_kind = (! empty($order_params['order']['has_terminals'])) ? 'delivery_location' : 'courier';
            $order = new HrxLib_Order();
            $order
                ->setPickupLocationId($order_params['order']['pickup_id'])
                ->setDeliveryKind($delivery_kind)
                ->setDeliveryLocation($order_params['order']['delivery_id'])
                ->setReceiver($receiver)
                ->setShipment($shipment);

            $order_data = $order->prepareOrderData();
            $order_response = $api->generateOrder($order_data);

            if ( isset($order_response['status']) && $order_response['status'] == 'error' ) {
                $output['status'] = 'error';
                $output['msg'] = $order_response['msg'];
                $output['data'] = print_r($order_response, true);
            } else {
                $output['msg'] = sprintf(__('Order "%s" successfully created', 'hrx-delivery'), $order_response['sender_reference']);
                $output['data'] = $order_response['id'];
            }
        } catch (\Exception $e) {
            $output['status'] = 'error';
            $output['msg'] = $this->convert_error_msg($e->getMessage());
        }

        return $output;
    }

    public function get_order( $order_id )
    {
        $output = $this->prepare_output();

        try {
            $api = $this->load_api();
            $output['data'] = $api->getOrder($order_id);
        } catch (\Exception $e) {
            $output['status'] = 'error';
            $output['msg'] = $this->convert_error_msg($e->getMessage());
        }

        return $output;
    }

    public function ready_order( $order_id, $mark_ready )
    {
        $output = $this->prepare_output();

        try {
            $api = $this->load_api();
            $output['data'] = $api->changeOrderReadyState($order_id, $mark_ready);
        } catch (\Exception $e) {
            $output['status'] = 'error';
            $output['msg'] = $this->convert_error_msg($e->getMessage());
        }

        return $output;
    }

    public function cancel_order( $order_id )
    {
        $output = $this->prepare_output();

        try {
            $api = $this->load_api();
            $output['data'] = $api->cancelOrder($order_id);
        } catch (\Exception $e) {
            $output['status'] = 'error';
            $output['msg'] = $this->convert_error_msg($e->getMessage());
        }

        return $output;
    }

    public function get_shipping_label( $order_id )
    {
        $output = $this->prepare_output();

        try {
            $api = $this->load_api();
            $output['data'] = $api->getLabel($order_id);
        } catch (\Exception $e) {
            $output['status'] = 'error';
            $output['msg'] = $this->convert_error_msg($e->getMessage());
        }

        return $output;
    }

    public function get_return_label( $order_id )
    {
        $output = $this->prepare_output();

        try {
            $api = $this->load_api();
            $output['data'] = $api->getReturnLabel($order_id);
        } catch (\Exception $e) {
            $output['status'] = 'error';
            $output['msg'] = $this->convert_error_msg($e->getMessage());
        }
        
        return $output;
    }

    private function prepare_config( $config )
    {
        $test_mode = (Core::get_instance()->get_settings('test_mode') == 'yes') ? true : false;
        $token = ($test_mode) ? Core::get_instance()->get_settings('api_test_token') : Core::get_instance()->get_settings('api_token');
        
        return (object) array(
            'token' => $config['token'] ?? $token,
            'test_mode' => $config['test_mode'] ?? $test_mode,
            'debug' => $config['debug'] ?? false,
        );
    }

    private function load_api()
    {
        return new HrxLib_Api($this->config->token, $this->config->test_mode, $this->config->debug);
    }

    private function prepare_output()
    {
        return array(
            'status' => 'OK',
            'msg' => '',
            'data' => [],
        );
    }

    private function prepare_order_params( $params )
    {
        $default_params = array(
            'receiver' => array(
                'name' => '',
                'email' => '',
                'phone' => '',
                'phone_regex' => '',
            ),
            'shipment' => array(
                'reference' => '',
                'comment' => '',
                'length' => 0,
                'width' => 0,
                'height' => 0,
                'weight' => 0,
            ),
            'order' => array(
                'pickup_id' => '',
                'delivery_id' => '',
            ),
        );

        foreach ( $default_params as $group_key => $group ) {
            foreach ( $group as $param_key => $param_value ) {
                $params[$group_key][$param_key] = $params[$group_key][$param_key] ?? $default_params[$group_key][$param_key];
            }
        }

        return $params;
    }

    private function convert_error_msg( $error_msg )
    {
        $values = array(
            '401 Unauthorized' => __('Wrong key', 'hrx-delivery'),
        );

        return $values[substr($error_msg,0,50)] ?? $error_msg;
    }
}
