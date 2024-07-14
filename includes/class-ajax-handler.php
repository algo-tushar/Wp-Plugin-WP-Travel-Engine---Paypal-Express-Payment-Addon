<?php
namespace WTE_Addons;

use WTE_Addons\PayPal\Request;
use WTE_Addons\PayPal\Client;

class Ajax_Handler {
	protected static $instance = null;

	public static function instance() {
		return null == self::$instance ? new self : self::$instance;
	}

	public function __construct()
	{
		add_action( 'wp_ajax_wte_addons_create_order', [$this, 'create_order'] );
		add_action( 'wp_ajax_nopriv_wte_addons_create_order', [$this, 'create_order'] );
		
		add_action( 'wp_ajax_wte_addons_capture_order', [$this, 'capture_order'] );
		add_action( 'wp_ajax_nopriv_wte_addons_capture_order', [$this, 'capture_order'] );

		add_action( 'wp_ajax_wte_addons_cancel_order', [$this, 'cancel_order'] );
		add_action( 'wp_ajax_nopriv_wte_addons_cancel_order', [$this, 'cancel_order'] );
	}

	public function create_order(){
		global $wte_addons, $WTEB;
		$process_pay = $wte_addons->process_pe_request($_POST);
		if ( is_wp_error($process_pay) ) {
			wp_send_json_error([
				'message' => $process_pay->get_error_message(),
			]);
		} else {
			if ( isset($process_pay['payment_id']) ) {
				$payment_id = $process_pay['payment_id'];
				$booking_id = get_post_meta( $payment_id, 'booking_id', true );
				
				$pe_order_data = $this->prepare_paypal_order( $booking_id, $payment_id );
				if ( is_wp_error($process_pay) ) {
					wp_send_json_error([
						'message' => $pe_order_data->get_error_message(),
					]);
				}

				$paypal_orderid = $this->paypal_order_api_call($pe_order_data);
				if ( is_wp_error($paypal_orderid) ) {
					wp_send_json_error([
						'message' => $paypal_orderid->get_error_message(),
					]);
				}

				if ( !empty($paypal_orderid) ) {
					update_post_meta( $payment_id, '_wte_addons_paypal_exp_order_id', $paypal_orderid ); // Save the paypal order id for future reference
				}

				wp_send_json_success([
					'message' => __('Paypal order id created', 'wp-travel-engine'),
					'order_id' => $paypal_orderid,
				]);
			} else {
				wp_send_json_error([
					'message' => __('Payment id not found', 'wp-travel-engine'),
				]);
			}
		}
		wp_die();
    }
	
	public function capture_order(){
		$json_pp_bn_data = isset( $_POST['data'] ) ? stripslashes_deep( $_POST['data'] ) : '{}';
		$array_pp_bn_data = json_decode( $json_pp_bn_data, true );
		$order_id = isset( $array_pp_bn_data['order_id'] ) ? sanitize_text_field($array_pp_bn_data['order_id']) : '';

		if ( empty( $order_id ) ) {
			wp_send_json_error([
				'message' => __('Error! Empty order ID received for PayPal capture order request.', 'wp-travel-engine'),
			]);
		}

		if ( ! check_ajax_referer( 'wte-addons-onapprove-js-ajax-nonce', '_wpnonce', false ) ) {
			wp_send_json_error([
				'message' => __('Nonce check failed. The page was most likely cached. Please reload the page and try again.', 'wp-travel-engine'),
			]);
		}

		// Capture the order using the PayPal API
		$txn_data = $this->paypal_capture_order_api_call($order_id);
		if ( is_wp_error($txn_data) ) {
			wp_send_json_error([
				'message' => $txn_data->get_error_message(),
			]);
		}

		$process_res = $this->process_payment($txn_data, $order_id);
		if ( is_wp_error($process_res) ) {
			wp_send_json_error([
				'message' => $process_res->get_error_message(),
			]);
		}

		wp_send_json_success([
			'message' => __('Paypal order captured', 'wp-travel-engine'),
			'result' => $process_res
		]);

		wp_die();
	}

	protected function prepare_paypal_order( $booking_id, $payment_id ) {
		$wpte_setting			= get_option( 'wp_travel_engine_settings', [] );
		$paypal_enable_sandbox	= (isset($wpte_setting['paypal_exp_sandbox']) && $wpte_setting['paypal_exp_sandbox'] == "true") ? true : false;
		$paypal_sandbox_client	= $wpte_setting['paypal_exp_sandbox_client'] ?? '';
		$paypal_live_client		= $wpte_setting['paypal_exp_live_client'] ?? '';
		$paypal_dis_funding		= (isset($wpte_setting['paypal_exp_dis_funding']) && is_array($wpte_setting['paypal_exp_dis_funding'])) ? $wpte_setting['paypal_exp_dis_funding'] : [];
		$client_id = $paypal_enable_sandbox ? $paypal_sandbox_client : $paypal_live_client;
		$env = $paypal_enable_sandbox ? 'sandbox' : 'production';

		if ( empty($client_id) ) {
			return new \WP_Error( 'WTE_BOOKING_ERROR', __( 'Couldn\'t proceed - paypal client ID not found', 'wp-travel-engine' ) );
		}

		$booking = get_post( $booking_id );

		// TODO: Do some stuff like update booking.
		if ( is_null( $booking ) || 'booking' !== $booking->post_type ) {
			return new \WP_Error( 'WTE_BOOKING_ERROR', __( 'Invalid booking id', 'wp-travel-engine' ) );
		}

		$payment = get_post( $payment_id );

		if ( is_null( $payment ) ) {
			return new \WP_Error( 'WTE_BOOKING_ERROR', __( 'Invalid booking or payment id', 'wp-travel-engine' ) );
		}

		$currency_code = $payment->payable['currency'];
		$amount = $payment->payable['amount'];
		$order_data = [];

		if ( count( $booking->order_trips ) > 0 ) {
			$paypal_items = [];
			foreach ( $booking->order_trips as $cart_id => $item ) {
				$item = (object) $item;
				$paypal_items[] = [
					"name" => $item->title ?? $item->ID,
					"quantity" => 1,
					"unit_amount" => [
						"value" => (string) $amount,
						"currency_code" => $currency_code,
					]
				];
				break;
			}

			$order_data = [
				"intent" => "CAPTURE",
				"payment_source" => [
					"paypal" => [
						"experience_context" => [
							"payment_method_preference" => "IMMEDIATE_PAYMENT_REQUIRED",
							"brand_name" => !empty(get_bloginfo('name')) ? get_bloginfo('name') : 'Abubakar Wazih Tushar',
							"shipping_preference" => 'NO_SHIPPING',
							"user_action" => "PAY_NOW",
						]
					]
				],
				"purchase_units" => [
					[
						"amount" => [
							"value" => $amount,
							"currency_code" => $currency_code,
							"breakdown" => [
								"item_total" => [
									"currency_code" => $currency_code,
									"value" => (string) $amount,
								]
							]
						],
						"items" => $paypal_items
					]
				]
			];
		} else {
			return new \WP_Error( 'WTE_BOOKING_ERROR', __( 'Cart is empty.', 'wp-travel-engine' ) );
		}

		return apply_filters( 'wp_travel_engine_paypal_express_request_args', $order_data );
	}

	protected function process_payment($payment, $order_id) {
		if ( is_array($payment) && !empty($payment) && !empty($order_id) ) {
			$paymentsqry = new \WP_Query([
				'post_type' => 'wte-payments',
				'publish' => 'publish',
				'posts_per_page' => 1,
				'meta_key' => '_wte_addons_paypal_exp_order_id',
				'meta_value' => $order_id,
				'order' => 'ASC'
			]);

			$payment_id = null;
			if ($paymentsqry->have_posts()) {
				while ($paymentsqry->have_posts()) {
					$paymentsqry->the_post();
					$payment_id = get_the_ID();
				}
				wp_reset_postdata();
			}

			if ( empty($payment_id) ) {
				return new \WP_Error( 'payment_process_error', __( 'Invalid booking payment ID.', 'wp-travel-engine' ) );
			}

			$booking_id = get_post_meta( $payment_id, 'booking_id', true );
			$booking = get_post( $booking_id );
			if ( is_null( $booking ) || 'booking' !== $booking->post_type ) {
				return new \WP_Error( 'payment_process_error', __( 'Invalid booking id', 'wp-travel-engine' ) );
			}

			// Update the booking and payment post meta.
			$payment_meta_input = ['gateway_response' => $payment];
			$status = isset($payment['status']) ? strtoupper($payment['status']) : '';
			//$tranxs_id = isset($payment['id']) ? $payment['id'] : '';
			$tranxs_id = $payment['purchase_units'][0]['payments']['captures'][0]['id'] ?? "";

			if ( $status !== 'COMPLETED' ) {
				return new \WP_Error( 'payment_process_error', sprintf(__('Payment is not approved. Status: %s', 'wp-express-checkout'), $status) );
			}
			
			$payment_meta_input['payment_status'] = $status;
			if ( isset($payment['purchase_units'][0]['payments']['captures'][0]) ) {
				$payment_res = $payment['purchase_units'][0]['payments']['captures'][0];
				$amount = $payment_res['amount']['value'] ?? 0;
				$currency = $payment_res['amount']['currency_code'] ?? '';

				// Update the booking and payment post meta.
				\WTE_Booking::update_booking(
					$booking_id,
					[
						'meta_input' => [
							'paid_amount' => +$booking->paid_amount + +$amount,
							'due_amount'  => +$booking->due_amount - +$amount,
							'wp_travel_engine_booking_status' => 'booked',
						],
					]
				);

				$payment_meta_input['payment_amount'] = [
					'value'    => $amount,
					'currency' => $currency,
				];
			}
			
			\WTE_Booking::update_booking(
				$payment_id,
				array(
					'meta_input' => $payment_meta_input,
				)
			);

			// Update Pay Details
			$payment_type = 'full_payment'; //(floatval($payment->payable['amount']) == floatval($amount)) ? 'full_payment' : 'partial_payment';
			$payment_values = get_post_meta($booking_id, 'wp_travel_engine_booking_setting', true);
			$payment_values['place_order']['payment']['txn_id'] = $tranxs_id;
			$payment_values['place_order']['payment']['payment_type'] = $payment_type;
			$payment_values['place_order']['payment']['payer_status'] = $status;
			update_post_meta($booking_id, 'wp_travel_engine_booking_setting', $payment_values);

			global $WTEB;
			$WTEB::send_emails( $payment_id, 'order_confirmation', 'all' );

			do_action( 'wte_booking_cleanup', $payment_id, 'notification' );

			return [
				'redir_url' => \WTE_Booking::get_return_url( $booking_id, $payment_id, 'paypal_express_payment' ),
			];
		} else {
			return new \WP_Error( 'payment_process_error', __( 'Invalid payment data or order ID.', 'wp-travel-engine' ) );
		}
		
		return null;
	}

    protected function paypal_order_api_call($order_data_array)
    {
		$json_encoded_pp_api_order_data = wp_json_encode($order_data_array);
		$request = new Request( '/v2/checkout/orders', 'POST' );
		$request->body = $json_encoded_pp_api_order_data;
		$client = Client::client();

		try {
        	$response = $client->execute($request);
			return $response->result->id ?? '';
		}
		catch( \Exception $e ) {
			$exception_msg = json_decode($e->getMessage());
			if ( is_array($exception_msg->details) && count($exception_msg->details) > 0 ) {
				$error_string = $exception_msg->details[0]->issue.". ".$exception_msg->details[0]->description;
				return new \WP_Error(2002, $error_string);
			}
			return new \WP_Error(2002, __( 'Something went wrong, the PayPal create-order API call failed!', 'wp-express-checkout' ));
		}
		
		return null;
    }

	protected function paypal_capture_order_api_call($order_id) {
		$endpoint = "/v2/checkout/orders/{$order_id}/capture";
		$request = new Request($endpoint, 'POST');
		$request->body = json_encode(['order_id' => $order_id]);
		
		$client = Client::client();

		try{
			$response = $client->execute($request);
			$txn_data = $response->result ?? [];
			return json_decode(json_encode($txn_data), true);
		}
		catch( \Exception $e ) {
			$exception_msg = json_decode($e->getMessage());
			if(is_array($exception_msg->details) && sizeof($exception_msg->details)>0){
				$error_string = $exception_msg->details[0]->issue.". ".$exception_msg->details[0]->description;
				return new \WP_Error(2002, $error_string);
			}
		}
		
		return null;
	}

	public function cancel_order(){
		$order_id = isset($_POST['order_id']) ? sanitize_text_field($_POST['order_id']) : '';
		if ( empty( $order_id ) ) {
			wp_send_json_error([
				'message' => __('Error! Empty order ID received for PayPal capture order request.', 'wte-addons'),
			]);
		}

		if ( ! check_ajax_referer( 'wte-addons-oncancel-js-ajax-nonce', '_wpnonce', false ) ) {
			wp_send_json_error([
				'message' => __('Nonce check failed. The page was most likely cached. Please reload the page and try again.', 'wte-addons'),
			]);
		}

		$paymentsqry = new \WP_Query([
			'post_type' => 'wte-payments',
			'publish' => 'publish',
			'posts_per_page' => 1,
			'meta_key' => '_wte_addons_paypal_exp_order_id',
			'meta_value' => $order_id,
			'order' => 'ASC'
		]);

		$payment_id = $booking_id = null;
		$args = ['_gateway' => 'paypal_express_payment'];
		if ($paymentsqry->have_posts()) {
			while ($paymentsqry->have_posts()) {
				$paymentsqry->the_post();
				$payment_id = get_the_ID();
				$args['pid'] = $payment_id;
			}
			wp_reset_postdata();
		}

		if ( !empty($payment_id) ) {
			$booking_id = get_post_meta( $payment_id, 'booking_id', true );
			$booking = get_post( $booking_id );
			if ( !empty($booking) && 'booking' === $booking->post_type ) {
				$args['bid'] = $booking_id;
				//wp_delete_post( $booking_id, true );
			}
			//wp_delete_post( $payment_id, true );
		}

		$cancel_url = \WTE_Booking::get_tokened_url('cancel', $args);
		wp_send_json_success([
			'message' => __('Paypal order cancelled', 'wte-addons'),
			'redir_url' => $cancel_url
		]);

		wp_die();
	}
}
Ajax_Handler::instance();