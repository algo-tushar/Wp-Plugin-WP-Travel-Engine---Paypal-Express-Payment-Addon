<?php
namespace WTE_Addons\PayPal;

use PayPalCheckoutSdk\Core\PayPalHttpClient;
use PayPalCheckoutSdk\Core\ProductionEnvironment;
use PayPalCheckoutSdk\Core\SandboxEnvironment;

/**
 * The plugin's implementation of the PayPal SDK client.
 */
class Client {

	/**
	 * Returns PayPal HTTP client instance with environment which has access
	 * credentials context. This can be used invoke PayPal API's provided the
	 * credentials have the access to do so.
	 *
	 * @param string $mode The PayPal environment mode (`live` or `test`).
	 *
	 * @return PayPalHttpClient
	 */
	public static function client( $mode = '' ) {
		return new PayPalHttpClient( self::environment( $mode ) );
	}

	/**
	 * Setting up and Returns PayPal SDK environment with PayPal Access credentials.
	 *
	 * @param string $mode The PayPal environment mode (`live` or `test`).
	 *
	 * @return SandboxEnvironment|ProductionEnvironment
	 */
	public static function environment( $mode = '' ) {
		$wpte_setting = get_option( 'wp_travel_engine_settings', [] );
		$is_live = (isset($wpte_setting['paypal_exp_sandbox']) && $wpte_setting['paypal_exp_sandbox'] == "true") ? false : true;
		$is_live = $mode ? 'live' === $mode : $is_live;

		if ( $is_live ) {
			$client_id = isset($wpte_setting['paypal_exp_live_client']) ? $wpte_setting['paypal_exp_live_client'] : '';
			$client_secret = isset($wpte_setting['paypal_exp_live_secret']) ? $wpte_setting['paypal_exp_live_secret'] : '';
			$environment = new ProductionEnvironment( $client_id, $client_secret );
		} else {
			$client_id = isset($wpte_setting['paypal_exp_sandbox_client']) ? $wpte_setting['paypal_exp_sandbox_client'] : '';
			$client_secret = isset($wpte_setting['paypal_exp_sandbox_secret']) ? $wpte_setting['paypal_exp_sandbox_secret'] : '';
			$environment = new SandboxEnvironment( $client_id, $client_secret );
		}

		return $environment;
	}
}