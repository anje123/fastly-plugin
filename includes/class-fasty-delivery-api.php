<?php

/**
 *  Delivery API class
 *
 * @package MyPlugin
 */

 if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly.
}


class WC_Delivery_API
{
    /**
     * The request URL for the API.
     *
     * @var string
     */
    protected $request_url;

    /**
     * Constructor.
     *
     * @param array $settings Settings for the API, such as the mode (test or live).
     */

    public function __construct($settings = array())
    {
        $env = isset($settings['mode']) ? $settings['mode'] : 'test';

        $this->request_url = 'https://api.Fasty.ng/';
    }

    /**
     * Get order details from Fasty delivery API.
     *
     * @param array $request_params The request parameters to send to the API.
     * @return mixed|null Returns the response from the API. Returns null on API error.
     * @throws Exception If there is an issue connecting to the API.
     */
    public function get_order_infos($request_params)
    {
        $endpoint = 'api/developer/order_status';
        $response = $this->pushRequest($endpoint, $request_params);

        if (null === $response) {
            return null;
        }

        return $response;
    }


    /**
     * Create a task in Fasty delivery API.
     *
     * @param array $request_params The request parameters to send to the API.
     * @return mixed|null Returns the response from the API. Returns null on API error.
     * @throws Exception If there is an issue connecting to the API.
     */
    public function create_delivery_task($request_params)
    {
        $endpoint = 'api/developer/woocommerce_order_create';
        $response = $this->pushRequest($endpoint, $request_params);

        if (null === $response) {
            return null;
        }

        return $response;
    }

    /**
     * Cancel a Fasty delivery task.
     *
     * @param array $request_params The request parameters to send to the API.
     * @return mixed|null Returns the response from the API. Returns null on API error.
     * @throws Exception If there is an issue connecting to the API.
     */
    public function cancel_delivery_task($request_params)
    {
        $endpoint = 'api/developer/order_cancel';
        $response = $this->pushRequest($endpoint, $request_params);

        if (null === $response) {
            return null;
        }

        return $response;
    }


    /**
     * Calculate the pricing for a delivery task.
     *
     * @param array $request_params The request parameters to send to the API.
     * @return mixed|null Returns the response from the API. Returns null on API error.
     * @throws Exception If there is an issue connecting to the API.
     */
    public function determine_delivery_pricing($request_params)
    {
        $endpoint = 'api/developer/woocommerce_order_estimate';
        $response = $this->pushRequest($endpoint, $request_params);

        if (null === $response) {
            return null;
        }

        return $response;
    }


    /**
     * Sends an HTTP request to the delivery API
     *
     * @param string $path The API endpoint path
     * @param array $args The request arguments
     * @param string $method The HTTP request method
     *
     * @return mixed|null The API response, or null if there was an error
     *
     * @throws Exception If there was an issue connecting to the delivery API
     */
    public function pushRequest($path, $args = [], $method = 'post')
    {
        $uri = "{$this->request_url}{$path}";

        $arg_array = [
            'method' => strtoupper($method),
            'body' => $args,
            'headers' => $this->getHeaders()
        ];

        $res = wp_remote_request($uri, $arg_array);

        if (is_wp_error($res)) {
            throw new Exception(__('There was an HTTP error connecting to the delivery API'));
        } else {
            $body = wp_remote_retrieve_body($res);
            
            $json = json_decode($body, true);
            if (null !== $json) {
                return $json;
            } else {
                throw new Exception(__('There was an issue connecting to the delivery API. Please try again later.'));
            }
        }
    }

    public function get_api_headers()
    {
        return array(
            'Accept' => 'application/json',
        );
    }
}
