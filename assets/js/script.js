const { __, _x, _n, _nx } = wp.i18n;

const paypalExpHandler = function() {
	this.actions = {};
	this.form = document.getElementById(wte_addons.form_id);

	var parent = this;

	this.completePayment = function( data ) {
		location.href = data.result.redir_url;
		return;
	};

	this.clientVars = {};
	this.clientVars[wte_addons.sdk_config.env] = wte_addons.sdk_config.client_id;
	this.buttonArgs = {
		env: wte_addons.sdk_config.env,
		client: parent.clientVars,
		style: wte_addons.sdk_config.button,
		commit: true,
		onInit: function( data, actions ) {
			parent.actions = actions;

			// Check if the form is valid on load.
			if ( !parent.form.checkValidity() ) {
				parent.actions.disable();
			} else {
				parent.actions.enable();
			}

			// Check if the form is valid on change.
			jQuery(parent.form).on('change', function() {
				if ( !parent.form.checkValidity() ) {
					parent.actions.disable();
				} else {
					parent.actions.enable();
				}
			});
		},
		onClick: function( data, actions ) {
			// Check if the form is valid before proceeding.
			if ( !parent.form.checkValidity() ) {
				jQuery(parent.form).parsley().validate();
				return false;
			}
		},
		createOrder: async function( data, actions ) {
			let formData = new FormData(parent.form);
			formData.set('action', 'wte_addons_create_order');

			try {
				const response = await fetch( wte_addons.ajax_url, {
					method: "POST",
					body: formData
				});

				if (!response.ok) {
					throw new Error( __('Order response was not ok', 'wte-addons') );
				}

				const res_data = await response.json();

				if ( res_data.success ) {
					return res_data.data.order_id;
				} else {
					throw new Error( res_data.data.message || __('Error occurred during create-order call to PayPal.', 'wte-addons') );
				}
			} catch (error) {
				alert( __('Could not initiate PayPal Checkout.', 'wte-addons') + ' ' + error.message );
			}
		},
		onCancel: async function onCancel(data) {
			if ( data.orderID ) {
				let post_data = 'action=wte_addons_cancel_order&order_id=' + data.orderID + '&_wpnonce=' + wte_addons.cancel_nonce;
				
				try {
					const response = await fetch( wte_addons.ajax_url, {
						method: "post",
						headers: {
							'Content-Type': 'application/x-www-form-urlencoded'
						},
						body: post_data
					});
	
					if (!response.ok) {
						throw new Error( __('Payment response was not ok', 'wte-addons') );
					}
	
					const res_data = await response.json();
					if ( res_data.success ) {
						location.href = res_data.data.redir_url || wte_addons.home_url;
					} else {
						throw new Error( res_data.data.message || __('Error occurred during create-order call to PayPal.', 'wte-addons') );
					}
				} catch (error) {
					//console.error(error);
					//alert('PayPal returned an error! Transaction could not be processed. Enable the debug logging feature to get more details...\n\n' + JSON.stringify(error));
					location.href = wte_addons.home_url;
				}
			} else {
				location.href = wte_addons.home_url;
			}

			return;
		},
		onApprove: async function( data, actions ) {
			// Create the data object to be sent to the server.
			let pp_bn_data = {
				order_id: data.orderID,
				nonce: wte_addons.approve_nonce
			};
			let nonce = wte_addons.approve_nonce;

			let post_data = 'action=wte_addons_capture_order&data=' + encodeURIComponent(JSON.stringify(pp_bn_data)) + '&_wpnonce=' + nonce;
			try {
				const response = await fetch( wte_addons.ajax_url, {
					method: "post",
					headers: {
						'Content-Type': 'application/x-www-form-urlencoded'
					},
					body: post_data
				});

				if (!response.ok) {
					throw new Error( __('Booking response was not ok', 'wte-addons') );
				}

				const res_data = await response.json();
				if ( res_data.success ) {
					return parent.completePayment(res_data.data);
				} else {
					throw new Error( res_data.data.message || __('Error occurred during create-order call to PayPal.', 'wte-addons') );
				}
			} catch (error) {
				console.error(error);
				alert('PayPal returned an error! Transaction could not be processed. Enable the debug logging feature to get more details...\n\n' + JSON.stringify(error));
			}
		},
		onError: function( err ) {
			alert( err );
		},
	};

	jQuery( document ).trigger( 'wpec_before_render_button', [ this ] );
	paypal.Buttons(this.buttonArgs).render('#' + wte_addons.button_id);
};