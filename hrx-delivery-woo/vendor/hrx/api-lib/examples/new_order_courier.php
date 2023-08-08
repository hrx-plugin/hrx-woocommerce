<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <style type="text/css">
        pre {
            border: 1px solid #ccc;
            padding: 5px;
            background: #eee;
        }
    </style>
</head>
<body>
<?php

use HrxApi\API;
use HrxApi\Receiver;
use HrxApi\Shipment;
use HrxApi\Order;

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
ini_set('error_reporting', E_ALL);

require('../vendor/autoload.php');

$token = ''; // API token required for execution of this file

try {
    $api = new API();
    $api->setToken($token);
    $api->setTestMode(true);
    $api->setDebug(true);

    /*** Pickup locations ***/
    echo 'Getting pickup locations...';
    $pickup_locations = $api->getPickupLocations(1, 10);
    echo ' Done.<br/>';
    debug_element('Pickup locations', $pickup_locations);

    /*** Delivery locations ***/
    echo 'Getting delivery locations...';
    $delivery_locations = $api->getCourierDeliveryLocations();
    echo ' Done.<br/>';
    debug_element('Delivery locations', $delivery_locations);

    echo 'Preparing delivery location for receiver...';
    $receiver_country = 'LT';
    $receiver_delivery_location = array();
    foreach ( $delivery_locations as $delivery_location ) {
        if ( $delivery_location['country'] == $receiver_country ) {
            $receiver_delivery_location = $delivery_location;
        }
    }
    echo ' Done.<br/>';
    debug_element('Delivery location for receiver', $receiver_delivery_location);

    /*** Create order ***/
    echo 'Building receiver...';
    $receiver_country = 'LT';
    $receiver = new Receiver();
    $receiver->setName('Tester');
    $receiver->setEmail('test@test.ts');
    $receiver->setPhone('60000000', $receiver_delivery_location['recipient_phone_regexp']);
    $receiver->setAddress('Street 1');
    $receiver->setPostcode('46123');
    $receiver->setCity('Testuva');
    $receiver->setCountry($receiver_country);
    echo ' Done.<br/>';
    debug_element('Receiver', $receiver);

    echo 'Building shipment...';
    $shipment = new Shipment();
    $shipment->setReference('REF_' . date('H_i_s'));
    $shipment->setComment('Comment');
    $shipment->setLength(15);
    $shipment->setWidth(15);
    $shipment->setHeight(15);
    $shipment->setWeight(1); // kg
    echo ' Done.<br/>';
    debug_element('Shipment', $shipment);

    echo 'Building order...';
    $order = new Order();
    $order->setPickupLocationId($pickup_locations[0]['id']);
    $order->setDeliveryKind('courier');
    $order->setReceiver($receiver);
    $order->setShipment($shipment);
    $order_data = $order->prepareOrderData();
    echo ' Done.<br/>';
    debug_element('Prepared Order data', $order_data);

    echo 'Sending order...';
    $order_response = $api->generateOrder($order_data);
    $order_id = isset($order_response['id']) ? $order_response['id'] : false;
    echo ' Done.<br/>';
    debug_element('Registered Order', $order_response);

    if ( $order_id ) {
        /*** Get order label ***/
        echo 'Getting label...';
        $label = '';
        $sleep_time = 3;
        for ( $i = 0; $i < 20; $i++ ) {
            echo ' ' . ($i * $sleep_time);
            try {
                $label = $api->getLabel($order_id);
                break;
            } catch (Exception $e) {
                sleep($sleep_time);
            }
        }
        echo (empty($label)) ? ' Failed.<br/>' : ' Done.<br/>';
        debug_element('Single Order label', $label);

        /*** Cancel order ***/
        echo 'Canceling order...';
        $cancel_order = $api->cancelOrder($order_id);
        echo 'Done.<br/>';
        debug_element('Canceled Order', $cancel_order);
    }

} catch (Exception $e) {
    echo '<b>Error:</b> ' . $e->getMessage();
    if (isset($api)) {
        echo '</br><b>Debug data:</b> <pre>' . print_r($api->getDebugData(), true) . '</pre>';
    }
}

function debug_element($title, $element, $print_r = true)
{
    echo $title . ':<br/><pre>';
    echo ($print_r) ? print_r($element, true) : $element;
    echo '</pre>';
    echo '<span>----------------------------------------</span><br/>';
}

?>
</body>
</html>