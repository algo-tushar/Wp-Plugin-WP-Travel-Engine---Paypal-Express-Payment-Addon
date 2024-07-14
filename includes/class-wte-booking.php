<?php
namespace WTE_Addons;

use WPTravelEngine\Core\Booking;
use WPTravelEngine\Core\Cart\Cart;
use WPTravelEngine\Validator\Checkout;
use WPTravelEngine\Core\Booking_Inventory;
use WPTravelEngine\Core\Trip;

class BookingProcess extends Booking {
    protected static $instance = null;

	public static function instance() {
		return null == self::$instance ? new self : self::$instance;
	}
    
    public function __construct() {
        parent::__construct();
        global $wte_cart;
		$this->cart = $wte_cart;
    }

    /**
	 * Prepares Billing Info.
	 *
	 * @return array Billing info array.
	 */
	protected function new_prepare_billing_info($request): array {

		$current_billing_fields = apply_filters( 'wp_travel_engine_booking_fields_display', \WTE_Default_Form_Fields::booking() );

		$sanitized_data = $this->validator->sanitized();
		$billing = isset( $sanitized_data[ 'booking' ] ) ? $sanitized_data[ 'booking' ] : array();

		foreach ( array_keys( $current_billing_fields ) as $index ) {
			if ( isset( $request[ $index ] ) ) {
				$billing[ $index ] = wte_clean( wp_unslash( $request[ $index ] ) );
			}
		}

		return $billing;
	}

    protected function new_prepare_legacy_order_metas($request) {
		$cart_total = $this->cart->get_total();

		$payment_mode = isset( $_POST[ 'wp_travel_engine_payment_mode' ] ) ? sanitize_text_field( wp_unslash( $_POST[ 'wp_travel_engine_payment_mode' ] ) ) : 'full_payment';
		$due          = 'partial' === $payment_mode ? + ( $cart_total[ 'total' ] - $cart_total[ 'total_partial' ] ) : 0;
		$total_paid   = 'partial' === $payment_mode ? + ( $cart_total[ 'total_partial' ] ) : + ( $cart_total[ 'total' ] );
		$tax          = isset( $cart_total[ 'tax_amount' ] ) ? $cart_total[ 'tax_amount' ] : '';

		$cart_items = $this->cart->getItems();

		$cart_item = array_shift( $cart_items );
		$package   = get_post( $cart_item[ 'price_key' ] );

		$order_metas          = array();
		$cart_pricing_options = $cart_item[ 'pricing_options' ];

		if ( ! is_null( $cart_item ) ) {
			$pax  = isset( $cart_item[ 'pax' ] ) ? $cart_item[ 'pax' ] : array();
			$trip = get_post( $cart_item[ 'trip_id' ] );

			$order_metas = array(
				'place_order' => array(
					'traveler'     => esc_attr( array_sum( $pax ) ),
					'cost'         => esc_attr( $total_paid ),
					'due'          => esc_attr( $due ),
					'tid'          => esc_attr( $cart_item[ 'trip_id' ] ),
					'tname'        => esc_attr( $trip->post_title ),
					'datetime'     => esc_attr( $cart_item[ 'trip_date' ] ),
					'datewithtime' => esc_attr( $cart_item[ 'trip_time' ] ),
					'booking'      => $this->new_prepare_billing_info($request),
					'tax'          => esc_attr( $tax ),
				),
			);

			// Getting trip metas to store trip duration and end date in order_metas.
			$trip_metas = get_post_meta( $cart_item[ 'trip_id' ], 'wp_travel_engine_setting', true );
			if ( isset( $trip_metas[ 'trip_duration' ] ) && isset( $trip_metas[ 'trip_duration_unit' ] ) ) {
				$order_metas[ 'place_order' ][ 'tduration' ] = $trip_metas[ 'trip_duration' ] . ' ' . $trip_metas[ 'trip_duration_unit' ];
				if ( $trip_metas[ 'trip_duration_unit' ] == 'days' ) {
					$end_date                                   = date( 'Y-m-d', strtotime( $cart_item[ 'trip_date' ] . '+' . $trip_metas[ 'trip_duration' ] . $trip_metas[ 'trip_duration_unit' ] ) );
					$order_metas[ 'place_order' ][ 'tenddate' ] = $end_date;
				}
			}

			foreach ( $cart_pricing_options as $pricing_detail ) {
				$order_metas[ 'place_order' ][ $pricing_detail[ 'categoryInfo' ][ 'label' ] ] = esc_attr( $pricing_detail[ 'pax' ] );
			}
			if ( $package ) {
				$order_metas[ 'place_order' ][ 'trip_package' ] = esc_attr( $package->post_title );
			}
			if ( isset( $cart_item[ 'trip_extras' ] ) ) {
				$cart_extra_services                              = $cart_item[ 'trip_extras' ];
				$order_metas[ 'place_order' ][ 'extra_services' ] = $cart_extra_services;
			}
		}

		$order_metas = array_merge_recursive( $order_metas, array( $this->booking->ID ) );

		$posted_data = wte_clean( wp_unslash( $_POST ) ); // phpcs:ignore WordPress.Security.NonceVerification.Missing

		foreach (
			array(
				'wp_travel_engine_booking_setting',
				'action',
				'_wp_http_referer',
				'wpte_checkout_paymnet_method',
				'wp_travel_engine_nw_bkg_submit',
				'wp_travel_engine_new_booking_process_nonce',
				'wp_travel_engine_payment_mode',
			) as $key
		) {
			// Unset metas.
			if ( isset( $posted_data[ $key ] ) ) {
				unset( $posted_data[ $key ] );
			}
		}

		$order_metas[ 'additional_fields' ] = $posted_data;

		update_post_meta( $this->booking->ID, 'additional_fields', $posted_data );

		/**
		 * @hook wte_booking_meta
		 *
		 * @since 3.0.7
		 */
		$this->legacy_order_metas = apply_filters( 'wte_before_booking_meta_save', $order_metas, $this->booking->ID );

		return $this->legacy_order_metas;
	}

    public function new_booking_process($request) {
		if ( defined( 'WTE_BOOKING_PROCESSING' ) ) {
			return;
		}

		// Start booking process.
		define( 'WTE_BOOKING_PROCESSING', true );

		if ( empty( $this->cart->getItems() ) ) {
			return; // Nothing to process.
		}

        // Insert booking first as a reference.
		$this->trip_booking = new Trip\Booking();

		$this->trip_booking->create();

		if ( ! $this->trip_booking->ID ) {
			return; // Couldn't create reference booking post.
		}
		$booking_id = $this->trip_booking->ID;
		/**
		 * @action_hook wte_created_user_booking
		 *
		 * @since 2.2.0
		 */
		do_action( 'wte_after_booking_created', $booking_id );

		$this->booking = $this->trip_booking->post;

        $payment_method = ( ! empty( $_POST[ 'wpte_checkout_paymnet_method' ] ) ) ? sanitize_text_field( wp_unslash( $_POST[ 'wpte_checkout_paymnet_method' ] ) ) : '';

        $payment_method = apply_filters( 'wptravelengine_checkout_payment_method', $payment_method, $booking_id );

		$billing = $this->new_prepare_billing_info($request);

        $this->update_billing_info( $billing );

        $this->update_order_items();

		if ( ! is_array( $billing ) || ! isset( $billing[ 'fname' ] ) || ! isset( $billing[ 'lname' ] ) ) {
			$billing = array(
				'fname' => '',
				'lname' => '',
			);
		}
		wp_update_post(
			array(
				'ID'          => $this->booking->ID,
				'post_title'  => "{$billing['fname']} {$billing['lname']} #{$this->booking->ID}",
				'post_status' => 'publish',
			)
		);

        $this->update_payment_info();

        $this->new_prepare_legacy_order_metas($request);

        $payment_mode = ( isset( $request[ 'wp_travel_engine_payment_mode' ] ) ) ? sanitize_text_field( wp_unslash( $request[ 'wp_travel_engine_payment_mode' ] ) ) : 'full_payment';

		$this->trip_booking->update_legacy_order_meta( $this->legacy_order_metas );

        // Save Customer.
		$wte_order_confirmation_instance = new \Wp_Travel_Engine_Order_Confirmation();
		$wte_order_confirmation_instance->insert_customer( $this->legacy_order_metas );

        // Set Payment.
		$payment_id = self::create_payment(
			$this->booking->ID,
			array(
				'booking_id'      => $this->booking->ID,
				'payment_gateway' => $payment_method,
			),
			$payment_mode
		);

		$this->trip_booking->update_booking_meta( 'payments', array( $payment_id ) );
        
        // Maybe update Coupon
		$discounts = $this->cart->get_discounts();
		if ( is_array( $discounts ) ) {
			foreach ( $discounts as $discount ) {
				$coupon_id = \WPTravelEngine\Modules\CouponCode::coupon_id_by_code( $discount[ 'name' ] );
				if ( $coupon_id ) {
					\WPTravelEngine\Modules\CouponCode::update_usage_count( $coupon_id );
				}
			}
		}
        
		$inventory = new Booking_Inventory();

		$inventory->update_inventory_by_booking( $this->booking );

		// Send Notification Emails.
		self::send_emails( $payment_id );

        do_action( "wte_payment_gateway_{$payment_method}", $payment_id, $payment_mode, $payment_method );

        return [
            'payment_id' => $payment_id,
            'payment_mode' => $payment_mode,
            'payment_method' => $payment_method,
        ];
	}

    public function process_pe_request($request) {
        $this->validator->validate($request);
		if ( ! $this->validator->has_errors() ) {
            try {
                return $this->new_booking_process($request);
            } catch ( \Exception $e ) {
                return new \WP_Error( 'validation_error', $e->getMessage() );
            }
		} else {
            return new \WP_Error( 'validation_error', $this->validator->get_errors() );
		}
    }
}

$GLOBALS[ 'wte_addons' ] = BookingProcess::instance();