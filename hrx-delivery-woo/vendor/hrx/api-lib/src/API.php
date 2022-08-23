<?php
namespace HrxApi;

use HrxApi\Helper;

class API
{
    /* Main variables */
    protected $url = "https://woptest.hrx.eu/api/v1/";
    protected $token;

    /* Class variables */
    private $timeout = 5;
    private $debug_mode = false;
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
        if ( ! $token) {
            Helper::throwError("User Token is required");
        }

        $this->token = $token;

        if ( ! $test_mode) {
            $this->url = "https://wop.hrx.eu/api/v1/";
        }

        if ( $api_debug_mode ) {
            $this->debug_mode = $api_debug_mode;
        }
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
        return $this->callApi($this->url . 'pickup_locations', array(), array(
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
        return $this->callApi($this->url . 'delivery_locations', array(), array(
            'page' => $page,
            'per_page' => $per_page
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
        return $this->callApi($this->url . 'courier_delivery_locations');
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
        return $this->callApi($this->url . 'orders', $order_data);
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
        return $this->callApi($this->url . 'orders', array(), array(
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
        return $this->callApi($this->url . 'orders/' . $order_id);
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
        return $this->callApi($this->url . 'orders/' . $order_id . '/update_ready_state', array('id' => $order_id, 'ready' => $is_ready));
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
        return $this->callApi($this->url . 'orders/' . $order_id .  '/cancel', array('id' => $order_id));
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
        return $this->callApi($this->url . 'orders/' . $order_id . '/label');
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
        return $this->callApi($this->url . 'orders/' . $order_id . '/return_label');
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
        return $this->callApi($this->url . 'orders/' . $order_id . '/tracking');
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
        return $this->callApi($this->url . 'public/orders/' . $tracking_number, array(), array(), false);
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
    private function callApi( $url, $data = [], $url_params = [], $use_token = true )
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

        curl_close($ch);

        if ( $this->debug_mode ) {
            Helper::printDebug(
                array(
                    'Method' => debug_backtrace()[1]['class'] . '::' . debug_backtrace()[1]['function'] . '()',
                    'Token' => $this->token,
                    'Use token' => ($use_token) ? 'Yes' : 'No',
                    'Post data' => json_encode($data, JSON_PRETTY_PRINT),
                    'URL params' => json_encode($url_params, JSON_PRETTY_PRINT),
                    'URL' => $url,
                    'Response code' => $httpCode,
                    'Response data' => json_encode(json_decode($response), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE),
                )
            );
        }

        return $this->handleApiResponse($response, $httpCode);
    }

    /**
     * Verification of the received response
     * @since 1.0.0
     * 
     * @param (mixed) $response - Response from API request
     * @param (integer) $httpCode - Response status code
     * @return (mixed) - Object of acceptable response
     */
    private function handleApiResponse( $response, $httpCode )
    {
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
