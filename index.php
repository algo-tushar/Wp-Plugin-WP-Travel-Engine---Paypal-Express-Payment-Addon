<?php
/**
 * Plugin Name: WP Travel Engine - Paypal Express Payment Addon
 * Version: 1.0.0
 * Description: Paypal express payment checkout Addon for wp-travel-engine
 * Author: Abubakar Wazih Tushar
 * Author URI: https://www.upwork.com/freelancers/~01634cdcebabdaed64
 * Requires at least: 5.6
 * Tested up to: 6.5.5
 * Requires PHP: 7.4
 *
 * Text Domain: wte-addons
 * Domain Path: /languages/
 *
 * @author Abubakar Wazih Tushar
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit; //Exit if accessed directly
}

if ( ! function_exists( 'WTE_paypal_express_payment') ) {
	function WTE_paypal_express_payment() {
		defined( 'WTE_PLUGIN_VER' )			|| define( 'WTE_PLUGIN_VER', '1.0.0' );
		defined( 'WTE_ADDONS_FILE' )		|| define( 'WTE_ADDONS_FILE', __FILE__ );
		defined( 'WTE_ADDONS_PLUGIN_URL' )	|| define( 'WTE_ADDONS_PLUGIN_URL', plugins_url( '', WTE_ADDONS_FILE ) );
		defined( 'WTE_ADDONS_PLUGIN_PATH' )	|| define( 'WTE_ADDONS_PLUGIN_PATH', plugin_dir_path( WTE_ADDONS_FILE ) );
		
		# load text domain
		add_action( 'plugins_loaded', function() {
			require_once( ABSPATH . 'wp-admin/includes/plugin.php' );
			
			if ( ! class_exists( 'Wp_Travel_Engine' ) ) {
				add_action( 'admin_notices', function() {
					echo '<div class="error"><p><strong>' . sprintf( esc_html__( '"WP Travel Engine - Paypal Express Payment Addons" requires WP Travel Engine to be installed and active. You can install %s here.', 'wpfinance' ), '<a href="https://wordpress.org/plugins/wp-travel-engine/" target="_blank">from</a>' ) . '</strong></p></div>';
				});
				deactivate_plugins( plugin_basename( WTE_ADDONS_FILE ) );
				return;
			}

			load_plugin_textdomain( 'wte-addons', false, dirname( plugin_basename( WTE_ADDONS_FILE ) ) . '/languages/' );

			# load the files
			require WTE_ADDONS_PLUGIN_PATH . '/vendor/autoload.php';
			require WTE_ADDONS_PLUGIN_PATH . '/includes/class-main.php';
			require WTE_ADDONS_PLUGIN_PATH . '/includes/class-wte-booking.php';
			require WTE_ADDONS_PLUGIN_PATH . '/includes/paypal-client/class-client.php';
			require WTE_ADDONS_PLUGIN_PATH . '/includes/paypal-client/class-request.php';
			require WTE_ADDONS_PLUGIN_PATH . '/includes/class-ajax-handler.php';
		});
	}
}
WTE_paypal_express_payment();