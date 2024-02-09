<?php
namespace HrxApi;

use HrxApi\Helper;

class API
{
    /* Main variables */
    protected $token;
    protected $timeout = 15;

    /* Class variables */
    private $url_test;
    private $url_live;
    private $debug_mode = false;
    private $test_mode = false;
    private $debug_data = [];

    /**
     * Constructor
     * @since 1.0.0
     * 
     * @param (string|boolean) $token - API token
     * @param (boolean) $test_mode - Toggle API URL
     * @param (boolean) $api_debug_mode - Echo every API call
     */
    public function __construct( $token = false, $test_mode = false, $api_debug_mode = false )
    {
        if ( $token ) {
            $this->setToken($token);
        }
        $this->setDebug($api_debug_mode);
        $this->setTestMode($test_mode);

        $this->setTestUrl("https://woptest.hrx.eu");
        $this->setLiveUrl("https://wop.hrx.eu");
    }

    /**
     * Set API request timeout value
     * @since 1.0.3
     * 
     * @param (integer) $timeout - Timeout value in seconds
     * @return (object) - This class
     */
    public function setTimeout( $timeout )
    {
        $this->timeout = $timeout;

        return $this;
    }

    /**
     * Activate debug mode
     * @since 1.0.3
     * 
     * @param (boolean) $activate_debug - Debug mode enable/disable command
     * @return (object) - This class
     */
    public function setDebug( $activate_debug = true )
    {
        $this->debug_mode = $activate_debug;

        return $this;
    }

    /**
     * Check debug mode
     * @since 1.0.3
     * 
     * @return (boolean) - Debug mode status
     */
    public function isDebug()
    {
        return (bool) $this->debug_mode;
    }

    /**
     * Save debug data (work only if debug is enabled)
     * @since 1.0.3
     * 
     * @param (mixed) $debug_data - Data to be saved
     */
    private function setDebugData( $debug_data )
    {
        if ( ! $this->isDebug() ) {
            return;
        }

        $this->debug_data = $debug_data;
    }

    /**
     * Get debug data
     * @since 1.0.3
     * 
     * @return (mixed) - Saved debug data
     */
    public function getDebugData()
    {
        return $this->debug_data;
    }

    /**
     * Activate test mode
     * @since 1.0.3
     * 
     * @param (boolean) $activate_test_mode - Test mode enable/disable command
     * @return (object) - This class
     */
    public function setTestMode( $activate_test_mode = true )
    {
        $this->test_mode = $activate_test_mode;

        return $this;
    }

    /**
     * Check test mode
     * @since 1.0.3
     * 
     * @return (boolean) - Test mode status
     */
    public function isTestMode()
    {
        return (bool) $this->test_mode;
    }

    /**
     * Set API token
     * @since 1.0.3
     * 
     * @param (string) $token - API token
     * @return (object) - This class
     */
    public function setToken( $token )
    {
        if ( ! $token) {
            Helper::throwError("User Token is required");
        }

        $this->token = $token;

        return $this;
    }

    /**
     * Set API test URL
     * @since 1.0.3
     * 
     * @param (string) $url - API URL
     * @return (object) - This class
     */
    public function setTestUrl( $url )
    {
        $this->url_test = $url;

        return $this;
    }

    /**
     * Set API live URL
     * @since 1.0.3
     * 
     * @param (string) $url - API URL
     * @return (object) - This class
     */
    public function setLiveUrl( $url )
    {
        $this->url_live = $url;

        return $this;
    }

    /**
     * Get API URL
     * @since 1.0.3
     * 
     * @return (string) $url - API URL
     */
    protected function getUrl( $add_subdirectory = '' )
    {
        return ($this->isTestMode()) ? $this->url_test . $add_subdirectory : $this->url_live . $add_subdirectory;
    }

    /**
     * Get pickup locations
     * @since 1.0.0
     * 
     * @param (integer) $page - Locations page number
     * @param (integer) $per_page - Locations number in one page
     * @return (array) - One page of locations
     */
    public function getPickupLocations( $page = 1, $per_page = 100 )
    {
        return $this->callApi($this->getUrl('/api/v1/pickup_locations'), array(), array(
            'page' => $page,
            'per_page' => $per_page
        ));
    }

    /**
     * Get delivery locations for terminals
     * @since 1.0.0
     * 
     * @param (integer) $page - Locations page number
     * @param (integer) $per_page - Locations number in one page
     * @return (array) - One page of locations
     */
    public function getDeliveryLocations( $page = 1, $per_page = 100 )
    {
        return $this->callApi($this->getUrl('/api/v1/delivery_locations'), array(), array(
            'page' => $page,
            'per_page' => $per_page
        ));
    }

    /**
     * Get countries of terminal delivery locations
     * @since 1.0.6
     * 
     * @return (array) - List of countries to which terminal shipping is available
     */
    public function getDeliveryLocationsCountries()
    {
        return $this->callApi($this->getUrl('/api/v2/delivery_locations'));
    }

    /**
     * Get delivery locations of the country terminals
     * @since 1.0.6
     * 
     * @param (string) $country - Country code (e.g. DE)
     * @param (integer) $page - Locations page number
     * @param (string) $endpoint - If want to use the API request endpoint received with the list of countries
     * @return (array) - One page of locations
     */
    public function getDeliveryLocationsForCountry( $country, $page = 1, $endpoint = '' )
    {
        if ( empty($endpoint) ) {
            $endpoint = '/api/v2/delivery_locations/' . $country;
        }

        return $this->callApi($this->getUrl($endpoint), array(), array(
            'page' => $page,
        ));
    }

    /**
     * Get delivery locations for courier
     * @since 1.0.2
     * 
     * @return (array) - Locations for every country
     */
    public function getCourierDeliveryLocations()
    {
        return $this->callApi($this->getUrl('/api/v1/courier_delivery_locations'));
    }

    /**
     * Create new Order
     * @since 1.0.0
     * 
     * @param (array) $order_data - Prepared Order data
     * @return (array) - Generated Order data from API
     */
    public function generateOrder( $order_data )
    {
        return $this->callApi($this->getUrl('/api/v1/orders'), $order_data);
    }

    /**
     * Get Orders list
     * @since 1.0.0
     * 
     * @param (integer) $page - Orders page number
     * @param (integer) $per_page - Orders number in one page
     * @return (array) - Orders list
     */
    public function getOrders( $page = 1, $per_page = 100 )
    {
        return $this->callApi($this->getUrl('/api/v1/orders'), array(), array(
            'page' => $page,
            'per_page' => $per_page
        ));
    }

    /**
     * Get single Order by ID
     * @since 1.0.0
     * 
     * @param (string) $order_id - Order ID
     * @return (array) - Detailed Order information
     */
    public function getOrder( $order_id )
    {
        return $this->callApi($this->getUrl('/api/v1/orders/' . $order_id));
    }

    /**
     * Update order ready state
     * @since 1.0.2
     * 
     * @param (string) $order_id - Order ID
     * @param (boolean) $is_ready - Whether order is ready
     * @return (array) - Detailed Order information
     */
    public function changeOrderReadyState( $order_id, $is_ready )
    {
        return $this->callApi($this->getUrl('/api/v1/orders/' . $order_id . '/update_ready_state'), array('id' => $order_id, 'ready' => $is_ready));
    }

    /**
     * Cancel order
     * @since 1.0.1
     * 
     * @param (string) $order_id - Order ID
     * @return (array) - Detailed Order information
     */
    public function cancelOrder( $order_id )
    {
        return $this->callApi($this->getUrl('/api/v1/orders/' . $order_id .  '/cancel'), array('id' => $order_id));
    }

    /**
     * Get label for Order
     * @since 1.0.0
     * 
     * @param (string) $order_id - Order ID
     * @return (array) - PDF file information and content
     */
    public function getLabel( $order_id )
    {
        return $this->callApi($this->getUrl('/api/v1/orders/' . $order_id . '/label'));
    }

    /**
     * Get return label for Order
     * @since 1.0.1
     * 
     * @param (string) $order_id - Order ID
     * @return (array) - PDF file information and content
     */
    public function getReturnLabel( $order_id )
    {
        return $this->callApi($this->getUrl('/api/v1/orders/' . $order_id . '/return_label'));
    }

    /**
     * Get tracking events for Order
     * @since 1.0.0
     * 
     * @param (string) $order_id - Order ID
     * @return (array) - List of events registered for the Order
     */
    public function getTrackingEvents( $order_id )
    {
        return $this->callApi($this->getUrl('/api/v1/orders/' . $order_id . '/tracking'));
    }

    /**
     * Get public tracking information
     * @since 1.0.0
     * 
     * @param (string) $tracking_number - Tracking number
     * @return (array) - Public tracking information
     */
    public function getTrackingInformation( $tracking_number )
    {
        return $this->callApi($this->getUrl('/api/v1/public/orders/' . $tracking_number), array(), array(), false);
    }

    /**
     * Send request to API
     * @since 1.0.0
     * 
     * @param (string) $url - API request URL
     * @param (array) $data - Prepared data for POST element
     * @param (array) $url_params - Prepared data for URL params (GET element)
     * @param (boolean) $use_token - Execute as a public or as a private request
     * @return (mixed) - Received response to the request
     */
    private function callApi( $url, $data = [], $url_params = [], $use_token = true, $retry = 0 )
    {
        $ch = curl_init();

        $headers = array();
        if ( $use_token ) {
            $headers[] = "Authorization: Bearer " . $this->token;
        }

        if ( ! empty($url_params) ) {
            $url .= '?' . http_build_query($url_params);
        }

        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, $this->timeout);

        if ( $data ) {
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($data));
        }

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $errorNo = curl_errno($ch);
        $errorMsg = ($errorNo) ? curl_error($ch) : '';

        curl_close($ch);

        $this->setDebugData(array(
            'Method' => debug_backtrace()[1]['class'] . '::' . debug_backtrace()[1]['function'] . '()',
            'Token' => $this->token,
            'Use token' => ($use_token) ? 'Yes' : 'No',
            'Post data' => json_encode($data, JSON_PRETTY_PRINT),
            'URL params' => json_encode($url_params, JSON_PRETTY_PRINT),
            'URL' => $url,
            'Response code' => $httpCode,
            'Response data' => json_encode(json_decode($response), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE),
            'Response error' => $errorMsg,
        ));

        if ( $retry < 5 && ($errorNo == 56 || ($httpCode != 200 && $httpCode != 201)) ) {
            return $this->callApi($url, $data, $url_params, $use_token, $retry + 1);
        }

        return $this->handleApiResponse($response, $httpCode, $errorMsg);
    }

    /**
     * Verification of the received response
     * @since 1.0.0
     * 
     * @param (mixed) $response - Response from API request
     * @param (integer) $httpCode - Response status code
     * @return (mixed) - Object of acceptable response
     */
    private function handleApiResponse( $response, $httpCode, $errorMsg )
    {
        if ( ! empty($errorMsg) ) {
            Helper::throwError('CURL error: ' . $errorMsg);
        }

        if ( ! Helper::isJson($response) ) {
            Helper::throwError('The response is not in the correct format');
        }

        $respObj = json_decode($response, true);

        if ( $httpCode == 200 || $httpCode == 201 ) {
            return $respObj;
        }

        if ( isset($respObj['error']) ) {
            Helper::throwError($respObj['error']);
        }

        Helper::throwError('Unknown error' . Helper::printDebug(array(
            'Response' => json_encode($respObj, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)
        ), false));
    }
}
