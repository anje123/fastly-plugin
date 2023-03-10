<?php

if (!defined('ABSPATH')) exit; // Exit if accessed directly

/**
 * Fasty Delivery Shipping Method Class
 *
 * Provides real-time shipping rates from Fasty delivery and handle order requests
 *
 * @since 1.0
 * @extends \WC_Shipping_Method
 */
class WC_Fasty_Delivery_Shipping_Method extends WC_Shipping_Method
{
	/**
	 * Constructor.
	 *
	 * @since 1.0.0
	 */
	public function __construct($instance_id = 0)
	{
		$this->id                 = 'Fasty_delivery';
		$this->instance_id 		  = absint($instance_id);
		$this->method_title       = __('Fasty Delivery');
		$this->method_description = __('Get your parcels delivered better, cheaper and quicker via Fasty Delivery');

		$this->supports  = array(
			'settings',
			'shipping-zones',
		);

		$this->init();

		$this->title = 'Fasty Delivery';

		$this->enabled = $this->get_option('enabled');
	}

	/**
	 * Init.
	 *
	 * Initialize Fasty Delivery shipping method.
	 *
	 * @since 1.0.0
	 */
	public function init()
	{
		$this->init_form_fields();
		$this->init_settings();

		// Save settings in admin if you have any defined
		add_action('woocommerce_update_options_shipping_' . $this->id, array($this, 'process_admin_options'));
	}

	/**
	 * Init fields.
	 *
	 * Add fields to the Fasty Delivery settings page.
	 *
	 * @since 1.0.0
	 */
	public function init_form_fields()
	{
		$pickup_state_code = WC()->countries->get_base_state();
		$pickup_country_code = WC()->countries->get_base_country();

		$pickup_state = WC()->countries->get_states($pickup_country_code)[$pickup_state_code];
		$pickup_base_address = WC()->countries->get_base_address();

		$this->form_fields = array(
			'enabled' => array(
				'title' 	=> __('Enable/Disable'),
				'type' 		=> 'checkbox',
				'label' 	=> __('Enable this shipping method'),
				'default' 	=> 'no',
			),
			'mode' => array(
				'title'       => 	__('Mode'),
				'hidden'		  => 'true',
				'type'        => 	'select',
				'description' => 	__('Default is (Test), choose (Live) when your ready to start processing orders via Fasty Delivery'),
				'default'     => 	'test',
				'options'     => 	array('test' => 'Test', 'live' => 'Live'),
            ),
            'live_api_key' => array(
                'title'       => 	__('Live API Key'),
				'type'        => 	'password',
				'description'   => __( '<a href="https://business.Fasty.ng/" target="_blank">Get your Fasty Developer API key</a>'),
				'default'     => 	__('')
			),
            'test_api_key' => array(
                'title'       => 	__('Test API Key'),
                'type'        => 	'password',
                'default'     => 	__('')
            ),
			'shipping_is_scheduled_on' => array(
				'title'        =>	__('Schedule shipping task'),
				'type'         =>	'select',
				'description'  =>	__('Select when the delivery will be created.'),
				'default'      =>	__('order_submit'),
				'desc_tip'          => false,
				'options'      =>	array(
                                        'payment_submit' => 'When payment is complete (should be used with online payment methods)',
                                        'order_submit' => 'When order status is changed to Complete',
                                        'manual_submit' => 'Manually create deliveries from admin dashboard'
                ),
			),
			'pickup_delay_same' => array(
				'title'       => 	__('Enter pickup delay time in hours (Auto Delivery only)'),
				'type'        => 	'text',
				'description' => 	__("Number of hours to delay pickup time by. Defaults to 0"),
				'default'     => 	__('0')
			),
			'scheduled_submit'       => array(
				'title'       => __( 'Scheduled Deliveries' ),
				'type'        => 'checkbox',
				'description' => __( 'Schedule Deliveries allows you to schedule a daily time to submit all pending orders' ),
				'default'     => 'no',
				'desc_tip'    => true,
			),
			'pickup_schedule_time' => array(
				'title'       => 	__('Enter daily pickup time (For Scheduled Delivery)'),
				'type'        => 	'time',
				'default'	  =>     '14:00',
				'description' => 	__("Allows you to specify daily time at which Fasty pickup should be made. Every Fasty order created after scheduled time gets shifted to the scheduled time next day"),
			),
			'shipping_handling_fee' => array(
				'title'       => 	__('Additional handling fee applied'),
				'type'        => 	'text',
				'description' => 	__("Additional handling fee applied"),
				'default'     => 	__('0')
			),
			'shipping_payment_method' => array(
				'title'        =>	__('Payment method for delivery'),
				'type'         =>	'select',
				'description'  =>	__('Select payment method.'),
				'default'      =>	__('1'),
				'options'      =>	array('1' => 'Wallet payment')
			),
			'pickup_delay_same' => array(
				'title'       => 	__('Enter pickup delay time in hours (Auto Delivery only)'),
				'type'        => 	'text',
				'description' => 	__("Number of hours to delay pickup time by. Defaults to 0"),
				'default'     => 	__('0')
			),
			'pickup_schedule_time' => array(
				'title'       => 	__('Enter daily pickup time in hours (Scheduled Delivery only)'),
                'type'        => 	'time',
            ),
			'pickup_country' => array(
				'title'       => 	__('Pickup Country'),
				'type'        => 	'select',
				'description' => 	__('Fasty Delivery/Pickup is only available for Nigeria'),
				'default'     => 	'NG',
				'options'     => 	array("NG" => "Nigeria", "" => "Please Select"),
			),
			'pickup_state' => array(
				'title'       => 	__('Pickup State'),
				'type'        => 	'text',
				'description' => 	__('Service available in Lagos Only'),
				'default'     => 	__('Lagos')
			),
			'pickup_base_address' => array(
				'title'       => 	__('Pickup Address'),
				'type'        => 	'hidden',
				'description' => 	__('The address where the parcel will be picked up.'),
				'default'     => 	__($pickup_base_address)
            ),
			'sender_name' => array(
				'title'       => 	__('Sender Name'),
				'type'        => 	'text',
				'description' => 	__("Sender Name"),
				'default'     => 	__('')
			),
			'sender_phone_number' => array(
				'title'       => 	__('Sender Phone Number'),
				'type'        => 	'text',
				'description' => 	__('Must be a valid phone number'),
				'default'     => 	__('')
			),
			'sender_email' => array(
				'title'       => 	__('Sender Email'),
				'type'        => 	'text',
				'description' => 	__('Must be a valid email address'),
				'default'     => 	__('')
            ),
		);
	}

	/**
	 * Calculate shipping by sending destination/items to Fasty and parsing returned rates
	 *
	 * @since 1.0
	 * @param array $package
	 */
	public function calculate_shipping($package = array())
	{
        // return;
		if ($this->get_option('enabled') == 'no') {
			return;
		}

        if ($this->get_option('mode') == 'test' && !strpos($this->get_option('test_api_key'), 'test')) {
			wc_add_notice('Fasty Error: Production API Key used in Test mode', 'error');
			return;
		}

		// country required for all shipments
		if (!$package['destination']['country'] && 'NG' !== $package['destination']['country']) {
			return;
        }
        
        $api = wc_Fasty_delivery()->get_api();

		$delivery_country_code = $package['destination']['country'];
		$delivery_state_code = $package['destination']['state'];
        $delivery_base_address = $package['destination']['address'];
        $delivery_state = WC()->countries->get_states($delivery_country_code)[$delivery_state_code];
        $delivery_country = WC()->countries->get_countries()[$delivery_country_code];

        if ('Lagos' !== $delivery_state) {
			wc_add_notice('Fasty Delivery only available within Lagos', 'error');
			return;
		}

		$pickup_state = $this->get_option('pickup_state');
        $pickup_base_address = $this->get_option('pickup_base_address');
        $pickup_country = WC()->countries->get_countries()[$this->get_option('pickup_country')];
        
        $key = $this->get_option('mode') == 'test' ? $this->get_option('test_api_key') : $this->get_option('live_api_key');

		$params = array(
			'api_key' => $key,
			'pickup_address' => "$pickup_base_address, $pickup_state, $pickup_country",
			'delivery_address' => "$delivery_base_address, $delivery_state, $delivery_country",
        );

        $res = $api->determine_delivery_pricing($params);

		if (!$res['fare']) {
			wc_add_notice(__($res['message']), 'error');
			return;
		} else {
			$data = $res;
			$handling_fee = $this->get_option('shipping_handling_fee');

			if ($handling_fee < 0) {
				$handling_fee = 0;
			}

			$cost = wc_format_decimal($data['fare']) + wc_format_decimal($handling_fee);
			
			$this->add_rate(array(
				'id'    	=> $this->id . $this->instance_id,
				'label' 	=> $this->title,
				'cost'  	=> $cost,
			));
		}
	}
}
