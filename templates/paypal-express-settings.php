<?php
/**
 * PayPal Standard Settings.
 */
$wp_travel_engine_settings = get_option( 'wp_travel_engine_settings' );

$paypal_exp_sandbox = isset( $wp_travel_engine_settings['paypal_exp_sandbox'] ) ? $wp_travel_engine_settings['paypal_exp_sandbox'] : 'false';
?>

<div class="wpte-field wpte-checkbox advance-checkbox">
	<label class="wpte-field-label" for="wp_travel_engine_settings[paypal_exp_sandbox]"><?php esc_html_e( 'Enable Sandbox', 'wp-travel-engine' ); ?></label>
	<div class="wpte-checkbox-wrap">
		<input type="hidden" value="false" name="wp_travel_engine_settings[paypal_exp_sandbox]">
		<input type="checkbox" 
            data-onchange-toggle-off-value="false"
			id="wp_travel_engine_settings[paypal_exp_sandbox]"
			name="wp_travel_engine_settings[paypal_exp_sandbox]"
			value="true" <?php checked( $paypal_exp_sandbox, 'true' ); ?>>
		<label for="wp_travel_engine_settings[paypal_exp_sandbox]"></label>
	</div>
	<span class="wpte-tooltip"><?php esc_html_e( 'Check this option to enable sandbox payment option.', 'wp-travel-engine' ); ?></span>
</div>
<div class="wpte-field wpte-text wpte-floated">
	<label for="wp_travel_engine_settings[paypal_exp_live_client]" class="wpte-field-label"><?php esc_html_e( 'Live Client ID', 'wp-travel-engine' ); ?></label>
	<input type="text" id="wp_travel_engine_settings[paypal_exp_live_client]" name="wp_travel_engine_settings[paypal_exp_live_client]" value="<?php echo isset( $wp_travel_engine_settings['paypal_exp_live_client'] ) ? esc_attr( $wp_travel_engine_settings['paypal_exp_live_client'] ) : ''; ?>">
	<span class="wpte-tooltip"><?php esc_html_e( 'Enter your PayPal Client ID for live mode.', 'wp-travel-engine' ); ?></span>
</div>
<div class="wpte-field wpte-text wpte-floated">
	<label for="wp_travel_engine_settings[paypal_exp_live_secret]" class="wpte-field-label"><?php esc_html_e( 'Live Secret Key', 'wp-travel-engine' ); ?></label>
	<input type="text" id="wp_travel_engine_settings[paypal_exp_live_secret]" name="wp_travel_engine_settings[paypal_exp_live_secret]" value="<?php echo isset( $wp_travel_engine_settings['paypal_exp_live_secret'] ) ? esc_attr( $wp_travel_engine_settings['paypal_exp_live_secret'] ) : ''; ?>">
	<span class="wpte-tooltip"><?php esc_html_e( 'Enter your PayPal Secret Key for live mode.', 'wp-travel-engine' ); ?></span>
</div>
<div class="wpte-field wpte-text wpte-floated">
	<label for="wp_travel_engine_settings[paypal_exp_sandbox_client]" class="wpte-field-label"><?php esc_html_e( 'Sandbox Client ID', 'wp-travel-engine' ); ?></label>
	<input type="text" id="wp_travel_engine_settings[paypal_exp_sandbox_client]" name="wp_travel_engine_settings[paypal_exp_sandbox_client]" value="<?php echo isset( $wp_travel_engine_settings['paypal_exp_sandbox_client'] ) ? esc_attr( $wp_travel_engine_settings['paypal_exp_sandbox_client'] ) : ''; ?>">
	<span class="wpte-tooltip"><?php esc_html_e( 'Enter your PayPal Client ID for sandbox mode.', 'wp-travel-engine' ); ?></span>
</div>
<div class="wpte-field wpte-text wpte-floated">
	<label for="wp_travel_engine_settings[paypal_exp_sandbox_secret]" class="wpte-field-label"><?php esc_html_e( 'Sandbox Secret Key', 'wp-travel-engine' ); ?></label>
	<input type="text" id="wp_travel_engine_settings[paypal_exp_sandbox_secret]" name="wp_travel_engine_settings[paypal_exp_sandbox_secret]" value="<?php echo isset( $wp_travel_engine_settings['paypal_exp_sandbox_secret'] ) ? esc_attr( $wp_travel_engine_settings['paypal_exp_sandbox_secret'] ) : ''; ?>">
	<span class="wpte-tooltip"><?php esc_html_e( 'Enter your PayPal Secret Key for sandbox mode.', 'wp-travel-engine' ); ?></span>
</div>

<?php 
$funding_options = array(
    'card' => __( 'Credit or debit cards', 'wp-travel-engine' ),
    'credit' => __( 'PayPal Credit', 'wp-travel-engine' ),
    'bancontact' => __( 'Bancontact', 'wp-travel-engine' ),
    'blik' => __( 'BLIK', 'wp-travel-engine' ),
    'eps' => __( 'eps', 'wp-travel-engine' ),
    'giropay' => __( 'giropay', 'wp-travel-engine' ),
    'ideal' => __( 'iDEAL', 'wp-travel-engine' ),
    'mercadopago' => __( 'Mercado Pago', 'wp-travel-engine' ),
    'mybank' => __( 'MyBank', 'wp-travel-engine' ),
    'p24' => __( 'Przelewy24', 'wp-travel-engine' ),
    'sepa' => __( 'SEPA-Lastschrift', 'wp-travel-engine' ),
    'sofort' => __( 'Sofort', 'wp-travel-engine' ),
);
$paypal_exp_dis_funding = isset( $wp_travel_engine_settings['paypal_exp_dis_funding'] ) ? $wp_travel_engine_settings['paypal_exp_dis_funding'] : [''] ; 

?>
<div class="wpte-field wpte-select wpte-floated">
    <label for="paypal_exp_dis_funding" class="wpte-field-label"><?php _e( "Disabled Funding Options", 'wp-travel-engine' ); ?></label>
    <select name="wp_travel_engine_settings[paypal_exp_dis_funding][]" id="paypal_exp_dis_funding" class="wpte-enhanced-select" multiple>
        <?php
        foreach ( $funding_options as $key => $val ) :
            $selected = in_array($key, $paypal_exp_dis_funding) ? 'selected' : '';
            echo '<option value="' . esc_attr($key) . '" ' . $selected . '>' . esc_html($val) . '</option>';
        endforeach;
        ?>
    </select>
    <span class="wpte-tooltip"><?php esc_html_e( 'Disabled options will disappear from button preview.', 'wp-travel-engine' ); ?></span>
</div>

<script>
    jQuery(document).ready(function($) {
        $('select[name="wp_travel_engine_settings[paypal_exp_dis_funding][]"]').on('change', function() {
            var selectElement = $(this);
            var selectedValues = selectElement.val();
            
            selectElement.next('input[type="hidden"][name="wp_travel_engine_settings[paypal_exp_dis_funding][]"]').remove();
            
            if (!selectedValues || selectedValues.length === 0) {
                $('<input>').attr({type: 'hidden', name: selectElement.attr('name'), value: ''}).insertAfter(selectElement);
            }
        });
    });
</script>