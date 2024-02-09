# HRX-API library

Its a library for HRX WOP API.

## Using HRX-API library

- `__PATH_TO_LIB__` is path to where HRX-API is placed. This will load HrxApi namespace
```php
require __PATH_TO_LIB__ . 'hrx-api/vendor/autoload.php';
```

Validations, checks, etc. throws Exception and calls to library classes should be wrapped in: blocks 
```php
try {
  // ...
} catch (Exception $e) {
  // ...
}
```

Any function starting with `add` or `set` returns its class so functions can be chained.

## Authentication

Uses supplied user `$token`. It is called during API object creation.
- Initialize new API library using: `$api = new API();`
- Set API token using: `$api->setToken($token);`

## API additional commands

After initializing the API class, some parameters can be changed.
- Change timeout value: `$api->setTimeout(60);`
- Enable debug data return: `$api->setDebug(true);`
- Enable connection to the test endpoint (only works with a testing token): `$api->setTestMode(true);`
- Change test endpoint: `$api->setTestUrl($url);`
- Change live endpoint: `$api->setLiveUrl($url);`

Simple example:
```php
$api = new API();
$api
    ->setToken($token) // Different tokens are used for test and live endpoints
    ->setTimeout(60) // If want change timeout value
    ->setDebug(true) // If want get debug data with function $api->getDebugData()
    ->setTestMode(false) // If want switch between Test/Live mode
    ->setTestUrl($url_test) // If want change test endpoint
    ->setLiveUrl($url_live); // If want change live endpoint
```

## Getting locations (v1) - Deprecated

```php
$pickup_locations = $api->getPickupLocations(1, 100); // Get pickup locations. First param - page number, second param - elements per page
$delivery_locations = $api->getDeliveryLocations(1, 100); // Get delivery locations for terminals. First param - page number, second param - elements per page
$courier_$delivery_locations = $api->getCourierDeliveryLocations(); // Get delivery locations for courier.
```

## Getting locations (v2)

```php
$pickup_locations = $api->getPickupLocations(1, 100); // Get pickup locations. First param - page number, second param - elements per page
$delivery_locations_countries = $api->getDeliveryLocationsCountries(); // Get countries list of terminal delivery locations.
foreach ( $delivery_locations_countries as $country ) {
  $country_code = $country['country'];
  $endpoint = $country['links']['locations'];
  $delivery_locations = $api->get_delivery_locations_for_country($country_code, 1, $endpoint); // Get delivery locations of country terminals. First param - country code (eg LT), second page number, third endpoint from which to get country terminals (if want to use the endpoint received with the list of countries)
}
$courier_$delivery_locations = $api->getCourierDeliveryLocations(); // Get delivery locations for courier.
```

## Creating Receiver

`use HrxApi\Receiver;` will allow creating Receiver object.

Minimum required setup:
```php
use HrxApi\Receiver;

$receiver = new Receiver();

$receiver
  ->setName('Tester') // Receiver name
  ->setEmail('test@test.ts') // Receiver email
  ->setPhone('58000000', "^5[0-9]{6,7}|8[0-9]{7}$"); // Phone number without code and a second parameter is for check the phone value according to the regex specified in delivery location information
```

When sending via courier:
```php
use HrxApi\Receiver;

$receiver = new Receiver();

$receiver
  ->setName('Tester') // Receiver name
  ->setEmail('test@test.ts') // Receiver email
  ->setPhone('58000000', "^5[0-9]{6,7}|8[0-9]{7}$") // Phone number without code and a second parameter is for check the phone value according to the regex specified in delivery location information
  ->setAddress('Street 1') // Receiver address
  ->setPostcode('46123') // Receiver postcode (zip code)
  ->setCity('Testuva') // Receiver city
  ->setCountry('EE'); // Receiver country code
```

## Creating shipment

`use HrxApi\Shipment;` will allow creating Shipment object.

Minimum required setup:
```php
use HrxApi\Shipment;

$shipment = new Shipment();

$shipment
  ->setReference('REF001') // Package ID or other identifier. Optional.
  ->setComment('Comment') // Comment for shipment. Optional.
  ->setLength(15) // Dimensions values in cm. Must be between the min and max values specified for the delivery location. If min or max value in delivery location is null, then value not have min/max limit
  ->setWidth(15)
  ->setHeight(15)
  ->setWeight(1); // kg
```

## Creating order

`use HrxApi\Order;` will allow creating Order object.

Minimum required setup:
```php
use HrxApi\Order;

$order = new Order();

$order
  ->setPickupLocationId('bcaac6c5-3a69-44e1-9e29-809b8150c997') // Pickup location ID retrieved from the API
  ->setDeliveryKind('delivery_location') // Shipping method. Can be one of: "delivery_location" or "courier".
  ->setDeliveryLocation('14fce476-f610-4ff8-a81e-9f6c653ac116') // Delivery location ID retrieved from the API
  ->setReceiver($receiver) // Receiver object
  ->setShipment($shipment); // Shipment object

$order_data = $order->prepareOrderData(); // Organized and prepared data for sending to API
```

## Generating order

All process syntax when sent to the delivery location:
```php
use HrxApi\API;
use HrxApi\Receiver;
use HrxApi\Shipment;
use HrxApi\Order;

$api = new API();
$api->setToken($token);

$pickup_locations = $api->getPickupLocations(1, 10);
$delivery_locations = $api->get_delivery_locations_for_country('LT', 1); // Required when shipping to delivery location

$receiver = new Receiver();

$receiver
  ->setName('Tester')
  ->setEmail('test@test.ts')
  ->setPhone('58000000', $delivery_locations[0]['recipient_phone_regexp']);

$shipment = new Shipment();

$shipment
  ->setReference('PACK-12345')
  ->setComment('Comment')
  ->setLength(15) // cm
  ->setWidth(15) // cm
  ->setHeight(15) // cm
  ->setWeight(1); // kg

$order = new Order();

$order
  ->setPickupLocationId($pickup_locations[0]['id'])
  ->setDeliveryKind('delivery_location') // Required when shipping to delivery location
  ->setDeliveryLocation($delivery_locations[0]['id']) // Required when shipping to delivery location
  ->setReceiver($receiver)
  ->setShipment($shipment);
$order_data = $order->prepareOrderData();

$order_response = $api->generateOrder($order_data); // Data sending to the API for shipment generation
```

All process syntax when sent to the receiver address via courier:
```php
use HrxApi\API;
use HrxApi\Receiver;
use HrxApi\Shipment;
use HrxApi\Order;

$api = new API();
$api->setToken($token);

$pickup_locations = $api->getPickupLocations(1, 10);
$delivery_locations = $api->getCourierDeliveryLocations();

$receiver_country = 'LT';
$receiver_delivery_location = array();
foreach ( $delivery_locations as $delivery_location ) {
    if ( $delivery_location['country'] == $receiver_country ) {
        $receiver_delivery_location = $delivery_location;
        break;
    }
}

$receiver = new Receiver();

$receiver
  ->setName('Tester')
  ->setEmail('test@test.ts')
  ->setPhone('60000000', $receiver_delivery_location['recipient_phone_regexp'])
  ->setAddress('Street 1') // Required when shipping via courier
  ->setPostcode('46123') // Required when shipping via courier
  ->setCity('Testuva') // Required when shipping via courier
  ->setCountry($receiver_country); // Required when shipping via courier

$shipment = new Shipment();

$shipment
  ->setReference('PACK-12345')
  ->setComment('Comment')
  ->setLength(15) // cm
  ->setWidth(15) // cm
  ->setHeight(15) // cm
  ->setWeight(1); // kg

$order = new Order();

$order
  ->setPickupLocationId($pickup_locations[0]['id'])
  ->setDeliveryKind('courier') // Required when shipping via courier
  ->setReceiver($receiver)
  ->setShipment($shipment);
$order_data = $order->prepareOrderData();

$order_response = $api->generateOrder($order_data); // Data sending to the API for shipment generation
```

## Getting all orders from API

```php
$orders_list = $api->getOrders(1, 100); // Get orders. First param - page number, second param - elements per page
```

## Getting single order data from API

Get order:
```php
$order = $api->getOrder('e161c889-782b-4ba2-a691-13dc4baf7b62'); // Order ID
```

Get label (when the tracking number is successfully generated):
```php
$label = $api->getLabel('e161c889-782b-4ba2-a691-13dc4baf7b62'); // Order ID
```

Get return label:
```php
$return_label = $api->getReturnLabel('e161c889-782b-4ba2-a691-13dc4baf7b62'); // Order ID
```

Get tracking events:
```php
$tracking_events = $api->getTrackingEvents('e161c889-782b-4ba2-a691-13dc4baf7b62'); // Order ID
```

## Getting public tracking information

- The tracking number is indicated in the order data received from the API, if the order was registered without errors
```php
$tracking_information = $api->getTrackingInformation('TRK0099999999'); // Tracking number
```

## Set order ready state

Ready state denotes whether parcel is packed, labelled and ready for pickup.
Can be called while order has `new`, `ready` status.
```php
$order_ready = $api->changeOrderReadyState('e161c889-782b-4ba2-a691-13dc4baf7b62', true); // Change ready state. First param - order ID, second param - if mark as ready
```

## Cancel order

```php
$canceled_order = $api->cancelOrder('e161c889-782b-4ba2-a691-13dc4baf7b62'); // Order ID
```

## Debug

If problems are encountered with API requests, debugging information can be obtained to determine the problem.
First need to activate debug mode after the initialization of the API class and after executing some query, debugging information can be obtained.
```php
$api = new API();
$api->setToken($token)
    ->setDebug(true); // Enable debug mode

try {
  $response = $api->getOrders(1, 100); // Any request
  $debug_data = $api->getDebugData(); // Debug data if response success
} catch (\Exception $e) {
  $debug_data = $api->getDebugData(); // Debug data if response fail
}
```

## Examples

Check **src/examples/** for this API library examples. The file **index.php** shows all the functions.
