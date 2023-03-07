<?php

if (!defined('ABSPATH')) exit; // Exit if accessed directly

/**
 * Main Fasty Delivery Class.
 *
 * @class    WC_Fasty_Delivery
 * @version  1.3.2
 */
class WC_Fasty_Delivery
{
    /** 
     * The version number of the plugin. 
     *
     * @var string 
     */
    const VERSION = '1.3.2';

    /**
     * The API object for this plugin.
     *
     * @var \WC_Delivery_API
     */
    public $api;

    /**
     * The plugin settings.
     *
     * @var array
     */
    public $settings;

    /**
     * The order statuses for this plugin.
     *
     * @var array
     */
    public $statuses = [
        'estimate'                  => 'ESTIMATE',
        'pending-driver'            => 'PENDING PILOT',
        'driver-assigned'           => 'PILOT PICKUP ARRIVED',
        'driver-pickup-complete'    => 'PILOT PICKUP COMPLETE',
        'driver-dropoff-complete'   => 'PILOT DROPOFF COMPLETE',
        'driver-delivery-problem'   => 'PILOT DELIVERY PROBLEM',
        'no-drivers-found'          => 'NO PILOT FOUND',
    ];

    /**
     * The single instance of this plugin.
     *
     * @var \WC_Fasty_Delivery
     */
    protected static $instance;

    /**
     * Initialize the plugin.
     *
     * @since 1.0
     */
    public function __construct() {
        $this->settings = maybe_unserialize(get_option('woocommerce_Fasty_delivery_settings'));

        $this->init_plugin();

        $this->init_hooks();
    }

    /**
     * Initialize the plugin.
     *
     * @internal
     *
     * @since 2.4.0
     */
    public function init_plugin() {
        $this->includes();

        if (is_admin()) {
            $this->admin_includes();
        }
    }

    /**
     * Include the necessary files.
     *
     * @since 1.0.0
     */
    public function includes() {
        require_once $this->get_plugin_path() . 'includes/class-wc-gok-api.php';
        require_once $this->get_plugin_path() . 'includes/class-wc-gok-shipping-method.php';
    }

    /**
     * Include the necessary admin files.
     *
     * @since 1.0.0
     */
    public function admin_includes() {
        require_once $this->get_plugin_path() . 'includes/class-wc-gok-orders.php';
    }


    /**
     * Update an order status by fetching the order details from Fasty Delivery.
     *
     * @since 1.0.0
     *
     * @param int $order_id
     */
    public function update_order_shipping_status($order_id)
    {
        if ($this->settings['mode'] == 'test' && !strpos($this->settings['test_api_key'], 'test')) {
			wc_add_notice('Fasty Error: Production API Key used in Test mode', 'error');
			return;
        }
        
        $order = wc_get_order($order_id);
        $key = $this->settings['mode'] == 'test' ? $this->settings['test_api_key'] : $this->settings['live_api_key'];

        $Fasty_order_id = $order->get_meta('Fasty_delivery_order_id');
        if ($Fasty_order_id) {
            $res = $this->get_api()->get_order_infos( array(
                'api_key'    => $key,
                'order_id'   =>  $Fasty_order_id
            ));

            $order_status = $this->statuses[$res['status']];

            update_post_meta($order_id, 'Fasty_order_status', $order_status);

            if ($order_status !== 'ESTIMATE' && $order_status !== 'PENDING PILOT' && $order_status !== 'PILOT DROPOFF COMPLETE') {
                $order->add_order_note("Fasty Delivery: $order_status");
            } elseif ($order_status == 'PILOT DROPOFF COMPLETE') {
                $order->update_status('completed', 'Fasty Delivery: Order completed successfully');
            }
                
            // update_post_meta($order_id, 'Fasty_delivery_order_details_response', $res);
        }
    }

    /**
     * Adds the tracking information to the View Order page.
     *
     * @internal
     *
     * @since 2.0.0
     *
     * @param int|\WC_Order $order the order object
     */
    public function add_view_order_tracking($order)
    {
        if ($this->settings['enabled'] == 'no') {
            return;
        }
        $order = wc_get_order($order);

        $pickup_tracking_url = $order->get_meta('Fasty_delivery_pickup_tracking_url');
        $delivery_tracking_url = $order->get_meta('Fasty_delivery_delivery_tracking_url');

        if ($pickup_tracking_url) {
            ?>
                <p class="wc-Fasty-delivery-track-pickup">
                    <a href="<?php echo esc_url($pickup_tracking_url); ?>" class="button" target="_blank">Track Fasty Pickup</a>
                </p>

            <?php
        }
        if ($delivery_tracking_url) {
            ?>
                <p class="wc-Fasty-delivery-track-delivery">
                    <a href="<?php echo esc_url($delivery_tracking_url); ?>" class="button" target="_blank">Track Fasty Delivery</a>
                </p>
            <?php
        }
        if (!$pickup_tracking_url) {
            ?>
                 <p>Please Check Back for Fasty Delivery Tracking Information</p>
            <?php
        }
    }

    public function edit_checkout_fields($fields)
    {
        $fields['billing']['billing_city']['required'] = false;
        $fields['billing']['billing_city']['type'] = 'hidden';
        $fields['billing']['billing_city']['label'] = '';

        $fields['billing']['billing_address_2']['type'] = 'hidden';
        $fields['shipping']['shipping_address_2']['type'] = 'hidden';

        return $fields;
    }

    /**
     * Load Shipping method.
     *
     * Load the WooCommerce shipping method class.
     *
     * @since 1.0.0
     */
    public function load_shipping_method()
    {
        $this->shipping_method = new WC_Fasty_Delivery_Shipping_Method;
    }

    /**
     * Add shipping method.
     *
     * Add shipping method to the list of available shipping method..
     *
     * @since 1.0.0
     */
    public function add_shipping_method($methods)
    {
        if (class_exists('WC_Fasty_Delivery_Shipping_Method')) :
            $methods['Fasty_delivery'] = 'WC_Fasty_Delivery_Shipping_Method';
        endif;

        return $methods;
    }

    /**
     * Initializes the and returns Fasty Delivery API object.
     *
     * @since 1.0
     *
     * @return \WC_Delivery_API instance
     */
    public function get_api()
    {
        // return API object if already instantiated
        if (is_object($this->api)) {
            return $this->api;
        }

        $Fasty_delivery_settings = $this->settings;

        // instantiate API
        return $this->api = new \WC_Delivery_API($Fasty_delivery_settings);
    }

    public function get_plugin_path()
    {
        return plugin_dir_path(__FILE__);
    }

    /**
     * Returns the main Fasty Delivery Instance.
     *
     * Ensures only one instance is/can be loaded.
     *
     * @since 1.0.0
     *
     * @return \WC_Fasty_Delivery
     */
    public static function instance()
    {
        if (is_null(self::$instance)) {
            self::$instance = new self();
        }

        return self::$instance;
    }

    /**
     * Refresh Woocommerce Checkout on update of shipping totals
     *
     * @internal
     *
     * @since 2.0.0
     *
     * @param int|\number sender/receiver phone number
     */
    public function update_woocommerce_delivery_fee_on_change(){
        if ( function_exists('is_checkout') && is_checkout() ) {
            ?>
            <script>
                window.addEventListener('load', function(){
                    var el = document.getElementById("billing_address_1_field");
                    el.className += ' update_totals_on_change';
                });
            </script>
            <?php 
        }
    }

    /**
     * Normalizes phone number to required format by endpoint.
     *
     * @internal
     *
     * @since 2.0.0
     *
     * @param int|\number sender/receiver phone number
     */
    public static function normalize_number($number) {
        if (empty($number)) {
            return;
        }

        $phone_number_build = "";
        $phone_number_raw = str_replace([' ','-','(',')'], [''], $number);
        
        if(substr($phone_number_raw, 0, 5) == '+2340') {
            $phone_number_raw = substr($phone_number_raw, 5);
        } else if(substr($phone_number_raw, 0, 4) == '2340') {
            $phone_number_raw = substr($phone_number_raw, 4);
        } else if($phone_number_raw[0] == '0') {
            $phone_number_raw = substr($phone_number_raw, 1);
        }

        // check : +234
        $phone_cc_check = substr($phone_number_raw, 0, 4);
        if($phone_cc_check == '+234') {
            $phone_number_build = $phone_number_raw;
        }

        // check : 234
        $phone_cc_check = substr($phone_number_raw, 0, 3);
        if($phone_cc_check == '234') {
            $phone_number_build = '+' . $phone_number_raw;
        }

        if($phone_number_build == "") {
            $phone_number_raw = str_replace(array('+1','+'), '', $phone_number_raw);
            $phone_number_build = "+234" . $phone_number_raw;
        }
        
        return $phone_number_build;
    }

    public function script_load($where) {
        wp_enqueue_style('Fasty-woocommerce', plugin_dir_url(__FILE__ ) . '/assets/css/Fasty-woocommerce.css');
        wp_enqueue_script('Fasty-woocommerce', plugin_dir_url( __FILE__ ) . '/assets/js/Fasty-woocommerce.js', array( 'jquery' ));
        wp_localize_script('Fasty-woocommerce', 'obj', $this->script_data());
    }
    
    public function admin_script_load($where){
        if ($where != 'woocommerce_page_wc-settings') {
            return;
        }

        wp_enqueue_style('Fasty-woocommerce', plugin_dir_url(__FILE__ ) . '/assets/css/Fasty-woocommerce.css');
        wp_enqueue_script('Fasty-woocommerce', plugin_dir_url( __FILE__ ) . '/assets/js/Fasty-woocommerce-admin.js', array( 'jquery' ));
        wp_localize_script('Fasty-woocommerce', 'obj', $this->script_data());
    }

    public function get_autocomplete_results() {
        $query = sanitize_text_field($_POST['query']);
        $url = 'https://api.Fasty.ng/api/v1/promo/autocomplete?q=' . urlencode($query) . '&context=pickup&lat=0&lng=0&session=' . date('ymdHis');

        $res = wp_remote_request($url);

        if (is_wp_error($res)) {
            throw new \Exception(__('You had an HTTP error connecting to Fasty delivery'));
        } else {
            $body = wp_remote_retrieve_body($res);
            
            if (null !== ($json = json_decode($body, true))) {
                wp_send_json_success($json);
            } else // Un-decipherable message
                throw new Exception(__('There was an issue connecting to Fasty delivery. Try again later.'));
        }

        return false;
    }

    public function script_data() {
        $data = array(
            'ajax_url' => admin_url('admin-ajax.php')
        );

        return $data;
    }
}


/**
 * Returns the Fasty Delivery instance.
 *
 * @since 1.0.0
 *
 * @return \WC_Fasty_Delivery
 */
function wc_fasty_delivery()
{
    return \WC_Fasty_Delivery::instance();
}
