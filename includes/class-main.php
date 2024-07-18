<?php
namespace WTE_Addons;

class Main {
	protected static $instance = null;

	public static function instance() {
		return null == self::$instance ? new self : self::$instance;
	}
	
	public function __construct() {
		register_activation_hook( WTE_ADDONS_FILE, [$this, 'activate'] );
		register_deactivation_hook( WTE_ADDONS_FILE, [$this, 'deactivate'] );
		add_filter( 'plugin_action_links_' . plugin_basename( WTE_ADDONS_FILE ), [$this, 'link_in_plugin'], 10, 1 );
		
		add_action( 'wp_enqueue_scripts', [$this, 'enqueue_scripts_styles'] );
		add_filter( 'wp_travel_engine_available_payment_gateways', [$this, 'paypal_express_gateway'], 10, 1 );
		add_filter( 'wpte_settings_get_global_tabs', [$this, 'add_settings_tab'], 10, 1 );
	}

	public function activate() {
		flush_rewrite_rules();
	}

	public function deactivate() {
		//
	}

	public function link_in_plugin( $links ) {
		$settings_link = '<a href="'.admin_url('edit.php?post_type=booking&page=class-wp-travel-engine-admin.php').'">' . __('Settings', 'wte-addons') . '</a>';
		array_push( $links, $settings_link );
		return $links;
	}
	
	public function enqueue_scripts_styles() {
		global $post;

		$activegateways = wp_travel_engine_get_active_payment_gateways();

		// Check if the shortcode is present in the post content and the PayPal Express Payment gateway is active.
		if ( has_shortcode($post->post_content, 'WP_TRAVEL_ENGINE_PLACE_ORDER') && is_array($activegateways) && isset($activegateways['paypal_express_payment']) ) {
			// Load the PayPal SDK
			$wpte_setting			= get_option( 'wp_travel_engine_settings', [] );
			$paypal_enable_sandbox	= (isset($wpte_setting['paypal_exp_sandbox']) && $wpte_setting['paypal_exp_sandbox'] == "true") ? true : false;
			$paypal_sandbox_client	= $wpte_setting['paypal_exp_sandbox_client'] ?? '';
			$paypal_live_client		= $wpte_setting['paypal_exp_live_client'] ?? '';
			$paypal_dis_funding		= (isset($wpte_setting['paypal_exp_dis_funding']) && is_array($wpte_setting['paypal_exp_dis_funding'])) ? array_filter($wpte_setting['paypal_exp_dis_funding']) : [];
			$client_id = $paypal_enable_sandbox ? $paypal_sandbox_client : $paypal_live_client;
			$env = $paypal_enable_sandbox ? 'sandbox' : 'production';

			// Enqueue the public script
			wp_enqueue_script( 'wte-addons-script', WTE_ADDONS_PLUGIN_URL . "/assets/js/script.js", ['jquery', 'wp-i18n'], WTE_PLUGIN_VER, true );
			wp_set_script_translations( 'wte-addons-script', 'wte-addons' );
			
			wp_localize_script( 'wte-addons-script', 'wte_addons', [
				'form_id' => 'wp-travel-engine-new-checkout-form',
				'ajax_url' =>  admin_url( 'admin-ajax.php' ),
				'approve_nonce' => wp_create_nonce('wte-addons-onapprove-js-ajax-nonce'),
				'create_nonce' => wp_create_nonce('wpec-create-order-js-ajax-nonce'),
				'cancel_nonce' => wp_create_nonce('wte-addons-oncancel-js-ajax-nonce'),
				'button_id' => 'wpte__checkout-info--paypal_express_payment',
				'sdk_config' => apply_filters( 'wte_addons_button_js_data', [
					'env' => $env,
					'client_id' => $client_id,
					'button' => [
						'height' => 45, // 25-55
						'shape'  => 'pill', // pill, rect
						'label'  => 'pay', // paypal, checkout, pay, buynow, installment
						'color'  => 'gold', // gold, blue, silver, black
						'tagline' => false, // true, false
						'layout' => 'vertical', // vertical, horizontal
					],
				]),
			]);

			// Paypal SDK args
			$sdk_args = [
				'client-id' => $client_id,
				'intent' => 'capture',
				'currency' => wp_travel_engine_get_currency_code( true ),
				'components' => implode(',', ['buttons', 'messages', 'funding-eligibility']),
				'enable-funding' => implode(',', ['paylater', 'venmo']),

			];
			if ( !empty($paypal_dis_funding) ) {
				$sdk_args['disable-funding'] = implode(',', $paypal_dis_funding);
			}
			$script_url = add_query_arg( apply_filters( 'wte_addons_paypal_sdk_args', $sdk_args ), 'https://www.paypal.com/sdk/js' );

			$inline_script = <<<EOT
			(function($){
				var script = document.createElement('script');
				script.type = 'text/javascript';
				script.async = true;
				script.src = '$script_url';
				script.onload = function() {
					jQuery(function($) {
						$(document).trigger('wte_addons_paypal_sdk_loaded');
					});
				};
				document.getElementsByTagName('head')[0].appendChild(script);
				$(document).on("wte_addons_paypal_sdk_loaded", function(){
					paypalExpHandler()
				});
			})(jQuery);
			EOT;
		
			wp_add_inline_script('wte-addons-script', $inline_script);
		}
	}

	public function paypal_express_gateway( $gateways_list ) { 
        $gateways_list['paypal_express_payment'] = [
            'label' => __( 'Paypal Express', 'wp-express-checkout' ),
            'input_class' => 'paypal-payment',
            'public_label' => __( 'Paypal Express', 'wp-express-checkout' ),
            'icon_url' => '',
            'info_text' => __( 'Paypal express payment.', 'wp-express-checkout' ),
        ];

        return $gateways_list;
    }

    public function add_settings_tab( $global_tabs ) {
        $content_path  = WTE_ADDONS_PLUGIN_PATH . "/templates/paypal-express-settings.php";
		
        $global_tabs['wpte-payment']['sub_tabs']['paypal-express'] = [
            'label'        => __( 'PayPal Express', 'wp-travel-engine' ),
            'content_path' => $content_path,
            'current'      => false,
        ];

        return $global_tabs;
    }
}
Main::instance();