<?php
/**
 * Scribendi Wordpress Plugin - Views
 * 
 * Collates all the presentational aspects of the plugin
 */


/**
 * Display editing form for the settings
 *
 * @return void
 */
function scribendi_settings_page() {
?>
	<div class="wrap">
		<?php screen_icon(); ?>
		<h2><?php _e('Scribendi Plugin Options', 'scribendi'); ?></h2>
		<form method="post" action="options.php">
			<?php settings_fields('scribendi-settings-group'); ?>
			<p><?php _e('These are the main settings for the plugin.', 'scribendi'); ?></p>
			<p><?php _e('You must first <a href="https://www.scribendi.com/api" target="_blank">register with Scribendi.com</a> and obtain an API key before you can use this plugin.', 'scribendi'); ?></p>
			<p><?php _e('For detailed instructions on how to use the Scribendi plugin, please visit <a href="http://www.scribendi.com/wordpress_plugin" target="_blank">www.Scribendi.com/wordpress_plugin</a>.', 'scribendi'); ?></p>
			
			<h3><?php _e('Account and Default Settings', 'scribendi') ?></h3>
			<?php if ( get_option(SCRIBENDI_PUBLIC_KEY) ): ?>
				<?php $result = scribendi_test_api_settings(); ?>
				<?php if ( $result === true ):?>
					<div id='scribendi-api-settings-test' class='updated settings-error'>
						<p><strong><?php _e('Your API settings are correct.', 'scribendi');?></strong></p>
					</div>
				<?php elseif ( $result instanceof Exception ):?>
					<div id='scribendi-api-settings-test' class='error settings-error'>
						<p>
							<strong><?php _e('There was an error while testing your settings: ', 'scribendi'); ?></strong>
							<?php echo '<br />', $result->getMessage(); ?>
						</p>
					</div>
				<?php endif; ?>
			<?php endif; ?>
			<table class="form-table">
				<tr valign="top">
					<th scope="row"><label for="<?php echo SCRIBENDI_PUBLIC_KEY; ?>"><?php _e('Public Key', 'scribendi') ?></label></th>
					<td><input name="<?php echo SCRIBENDI_PUBLIC_KEY; ?>" type="text" id="<?php echo SCRIBENDI_PUBLIC_KEY; ?>" value="<?php echo get_option(SCRIBENDI_PUBLIC_KEY); ?>" class="regular-text" /></td>
				</tr>
				<tr valign="top">
					<th scope="row"><label for="<?php echo SCRIBENDI_PRIVATE_KEY; ?>"><?php _e('Private Key', 'scribendi') ?></label></th>
					<td><input name="<?php echo SCRIBENDI_PRIVATE_KEY; ?>" type="text" id="<?php echo SCRIBENDI_PRIVATE_KEY; ?>" value="<?php echo get_option(SCRIBENDI_PRIVATE_KEY); ?>" class="regular-text" /></td>
				</tr>
				<tr valign="top">
					<th scope="row"><label for="<?php echo SCRIBENDI_CUSTOMER_ID; ?>"><?php _e('Customer ID', 'scribendi') ?></label></th>
					<td><input name="<?php echo SCRIBENDI_CUSTOMER_ID; ?>" type="text" id="<?php echo SCRIBENDI_CUSTOMER_ID; ?>" value="<?php echo get_option(SCRIBENDI_CUSTOMER_ID); ?>" class="small-text" /></td>
				</tr>
				<tr valign="top">
					<th scope="row"><label for="<?php echo SCRIBENDI_DEFAULT_CURRENCY; ?>"><?php _e('Default Currency', 'scribendi') ?></label></th>
					<td>
						<select name="<?php echo SCRIBENDI_DEFAULT_CURRENCY; ?>" id="<?php echo SCRIBENDI_DEFAULT_CURRENCY; ?>" size="1">
							<?php foreach ( scribendi_get_currencies() as $oCurrency ): ?>
							<option value="<?php echo $oCurrency->getCurrencyId(); ?>" <?php if ( get_option(SCRIBENDI_DEFAULT_CURRENCY, 840) == $oCurrency->getCurrencyId() ) { echo 'selected="selected"'; }?>><?php echo $oCurrency->getDescription(); ?></option>
							<?php endforeach; ?>
						</select>
					</td>
				</tr>
				<tr valign="top">
					<th scope="row"><label for="<?php echo SCRIBENDI_DEFAULT_ENGLISH; ?>"><?php _e('Default English Version', 'scribendi') ?></label></th>
					<td>
						<select name="<?php echo SCRIBENDI_DEFAULT_ENGLISH; ?>" id="<?php echo SCRIBENDI_DEFAULT_ENGLISH; ?>" size="1">
							<?php foreach ( scribendi_get_english_types() as $type ): ?>
							<option value="<?php echo $type; ?>" <?php if ( get_option(SCRIBENDI_DEFAULT_ENGLISH, 'US') == $type ) { echo 'selected="selected"'; }?>><?php echo $type; ?></option>
							<?php endforeach; ?>
						</select>
					</td>
				</tr>
				<tr valign="top">
					<th scope="row"><label for="<?php echo SCRIBENDI_ALWAYS_POST_EDITS; ?>"><?php _e('Automatically Publish Edited Posts', 'scribendi') ?></label></th>
					<td>
						<input type="checkbox" name="<?php echo SCRIBENDI_ALWAYS_POST_EDITS; ?>" id="<?php echo SCRIBENDI_ALWAYS_POST_EDITS; ?>" value="1" <?php if ( get_option(SCRIBENDI_ALWAYS_POST_EDITS, 0) ) { echo 'checked="checked"'; } ?> />
					</td>
				</tr>
			</table>
			
			<h3><?php _e('Server Settings', 'scribendi') ?></h3>
			<p><?php _e('These settings are used to connect to the API server. You should not need to change them.', 'scribendi'); ?></p>
			<table class="form-table">
				<tr valign="top">
					<th scope="row"><label for="<?php echo SCRIBENDI_API_SERVER; ?>"><?php _e('API Server Address', 'scribendi') ?></label></th>
					<td><input name="<?php echo SCRIBENDI_API_SERVER; ?>" type="text" id="<?php echo SCRIBENDI_API_SERVER; ?>" value="<?php echo get_option(SCRIBENDI_API_SERVER, 'https://www.scribendi.com'); ?>" class="regular-text" /></td>
				</tr>
				<tr valign="top">
					<th scope="row"><label for="<?php echo SCRIBENDI_API_CONN_TIMEOUT; ?>"><?php _e('API Connection Time-out', 'scribendi') ?></label></th>
					<td><input name="<?php echo SCRIBENDI_API_CONN_TIMEOUT; ?>" type="text" id="<?php echo SCRIBENDI_API_CONN_TIMEOUT; ?>" value="<?php echo get_option(SCRIBENDI_API_CONN_TIMEOUT, 10); ?>" class="small-text" /></td>
				</tr>
				<tr valign="top">
					<th scope="row"><label for="<?php echo SCRIBENDI_API_TIMEOUT; ?>"><?php _e('API Request Time-out', 'scribendi') ?></label></th>
					<td><input name="<?php echo SCRIBENDI_API_TIMEOUT; ?>" type="text" id="<?php echo SCRIBENDI_API_TIMEOUT; ?>" value="<?php echo get_option(SCRIBENDI_API_TIMEOUT, 10); ?>" class="small-text" /></td>
				</tr>
				<tr valign="top">
					<th scope="row"><label for="<?php echo SCRIBENDI_API_PAYMENT_SERVER; ?>"><?php _e('Payment Server', 'scribendi') ?></label></th>
					<td><input name="<?php echo SCRIBENDI_API_PAYMENT_SERVER; ?>" type="text" id="<?php echo SCRIBENDI_API_PAYMENT_SERVER; ?>" value="<?php echo get_option(SCRIBENDI_API_PAYMENT_SERVER, 'https://www.scribendi.com/checkout?oid='); ?>" class="regular-text" /></td>
				</tr>
				<tr valign="top">
					<th scope="row"><label for="<?php echo SCRIBENDI_API_ORDER_SERVER; ?>"><?php _e('Order Server', 'scribendi') ?></label></th>
					<td><input name="<?php echo SCRIBENDI_API_ORDER_SERVER; ?>" type="text" id="<?php echo SCRIBENDI_API_ORDER_SERVER; ?>" value="<?php echo get_option(SCRIBENDI_API_ORDER_SERVER, 'https://www.scribendi.com/customer?view=cp_order_details&oid='); ?>" class="regular-text" /></td>
				</tr>
				<tr valign="top">
					<th scope="row"><label for="<?php echo SCRIBENDI_API_STATUS_INTERVAL; ?>"><?php _e('Status Refresh Interval', 'scribendi') ?></label></th>
					<td><input name="<?php echo SCRIBENDI_API_STATUS_INTERVAL; ?>" type="text" id="<?php echo SCRIBENDI_API_STATUS_INTERVAL; ?>" value="<?php echo get_option(SCRIBENDI_API_STATUS_INTERVAL, '15'); ?>" class="small-text" /> minutes</td>
				</tr>
			</table>
			
			<p class="submit"><input type="submit" class="button-primary" name="Submit" value="<?php _e('Save Changes') ?>" /></p>

			<p><?php _e('Questions, problems or comments? <a href="http://www.scribendi.com/contact" target="_blank">Contact Scribendi.com</a> for assistance.', 'scribendi'); ?></p>
		</form>
	</div>
<?php
}

/**
 * Displays the Scribendi user settings options
 *
 * @return void
 */
function scribendi_user_settings_page($user) {
	if ( current_user_can('manage_options') ) {
?>
	<h3><?php _e('Scribendi User Settings', 'scribendi') ?></h3>
	<p><?php _e('These settings are for the Scribendi Plugin.', 'scribendi'); ?></p>
	<table class="form-table">
		<tr valign="top">
			<th scope="row"><label for="<?php echo SCRIBENDI_CUSTOMER_ID; ?>"><?php _e('Scribendi Customer ID', 'scribendi') ?></label></th>
			<td>
				<input name="<?php echo SCRIBENDI_CUSTOMER_ID; ?>" type="text" id="<?php echo SCRIBENDI_CUSTOMER_ID; ?>" value="<?php echo get_user_option(SCRIBENDI_CUSTOMER_ID, $user->ID, false); ?>" class="small-text" />
				<em><?php _e('A unique Customer ID allows orders to be tracked to the person that created them.', 'scribendi'); ?></em>
			</td>
		</tr>
	</table>
<?php
	}
}

/**
 * Writes in the ajax call data
 *
 * @return void
 */
function scribendi_ajax_calls() {
?>
<script type="text/javascript" >
jQuery(document).ready(function($) {
	if ( $('#scribendi-quote').length > 0 ) {
		bindQuoteLink($);
	}
	
	bindOrderActions($);
<?php
	if ( isset($_GET['scribendi_request_edit']) && $_GET['scribendi_request_edit'] ) {
?>
	setTimeout(function() {
      $('#scribendi-quote').trigger('click');
    }, 1500);
<?php
	}
?>
});

function bindQuoteLink($) {
	$('#scribendi-quote').click(function(){
		var data = {
			action: 'scribendi_quote',
			wordcount: $('#word-count').text(),
			time: function() {
				var dt = new Date();
				return dt.getTime();
			}
		};

		var mce = typeof(tinyMCE) != 'undefined' ? tinyMCE.activeEditor : false, title, content;
		if ( mce && !mce.isHidden() ) {
			if ( mce.isDirty() ) {
				autosave();
			}
		} else {
			title = $('#post #title').val(), content = $('#post #content').val();
			if ( ( title || content ) && title + content != autosaveLast ) {
				autosave();
			}
		}

		$('#scribendi-quote-details').html('<p>Please wait...</p>');
		$.post(ajaxurl, data, function(response) {
			$('#scribendi-quote-details').html(response);
			bindOrderHandlers($);
			return false;
		});

		return false;
	});
}

function bindOrderHandlers($) {
	$('input.scribendi-order-service').click(function(){
		var data = {
			action: 'scribendi_order',
			wordcount: $('#word-count').text(),
			serviceId: $(this).parents('tr').attr('id').replace('scribendi-service-', ''),
			postId: $('#post_ID').val(),
			time: function() {
				var dt = new Date();
				return dt.getTime();
			}
		};

		$('#scribendi-quote-details').html('<p>Please wait...</p>');
		$.post(ajaxurl, data, function(response) {
			$('#scribendi-order-details').html(response);
			$('#scribendi-quote-details').html('');
			$('#scribendi-get-quotes').remove();
			bindOrderActions($);
			return false;
		});
		
		return false;
	});
};

function bindOrderActions($) {
	if ( $('#scribendi-order-cancel').length > 0 ) {
		$('#scribendi-order-cancel').click(function(){
			var data = {
				action: 'scribendi_order_cancel',
				postId: $('#post_ID').val(),
				time: function() {
					var dt = new Date();
					return dt.getTime();
				}
			};
	
			$('#scribendi-order-ajax').html('<p>Please wait...</p>');
			$.post(ajaxurl, data, function(response) {
				$('#scribendi-order-details').html(response);
				bindOrderActions($);
				bindQuoteLink($);
			});
			
			return false;
		});
	};
};
</script>
<?php
}

/**
 * Displays the scribendi toolbox on the editing page
 *
 * @param Scribendi_Api_Model_Order $oOrder
 * @return void
 */
function scribendi_display_toolbox(Scribendi_Api_Model_Order $oOrder) {
?>
	<div id="scribendi-order-details"></div>
	<div class="misc-pub-section">
		<div id="scribendi-get-quotes">
			<input type="hidden" name="scribendi_nonce" id="scribendi_nonce" value="<?php echo wp_create_nonce(plugin_basename(__FILE__)); ?>" />
			<?php if ( !$oOrder->getOrderId() ): ?>
				<p>
					<?php _e('Want to have your post professionally edited by the folks at Scribendi?', 'scribendi');?>
					<?php _e('Get a quote now! Remember to save your post first.', 'scribendi'); ?>
				</p>
				<input name="quote" type="submit" class="button" id="scribendi-quote" value="<?php esc_attr_e('Get Quotes', 'scribendi'); ?>" />
			<?php else: ?>
				<p>
					<?php _e('Made changes since your last order? Why not have your post re-edited?', 'scribendi');?>
					<?php _e('Get a quote now! Remember to save your post first.', 'scribendi'); ?>
				</p>
				<input name="quote" type="submit" class="button" id="scribendi-quote" value="<?php esc_attr_e('New Order', 'scribendi'); ?>" />
			<?php endif; ?>
		</div>
	</div>
	<div id="scribendi-quote-details"></div>
<?php
}

/**
 * Displays the a warning that toolbox is disabled until the post has been saved
 *
 * @return void
 */
function scribendi_display_toolbox_not_available() {
?>
	<p>
		<?php _e('Whoops! The Scribendi service is not available until your post has been saved as a draft, or is published.', 'scribendi'); ?>
	</p>
<?php
}

/**
 * Renders a quote result set to the screen
 *
 * @param Scribendi_Api_Client_Response_Quote $oResponse
 * @return void
 */
function scribendi_display_quotes(Scribendi_Api_Client_Response_Quote $oResponse) {
	$i = 0;
	$oCurrency = scribendi_get_default_currency();
?>
	<?php if ( count($oResponse->getResults()) > 0 ): ?>
		<h3><?php _e('Quotes', 'scribendi'); ?></h3>
		<?php /* @var Scribendi_Api_Model_Quote $oQuote */ ?>
		<table class="scribendi-quotes">
			<thead>
				<tr>
					<th class="scribendi-ready"><?php _e('Ready in', 'scribendi'); ?></th>
					<th class="scribendi-price"><?php _e('Price', 'scribendi'); ?></th>
					<th class="scribendi-actions">&nbsp;</th>
				</tr>
			</thead>
		<?php foreach ( $oResponse->getResults() as $oQuote ): ?>
			<tr id="scribendi-service-<?php echo $oQuote->getServiceId(); ?>" class="<?php if ( $i == 1 ) { echo 'alt'; $i=0; } else { $i++; } ?>">
				<td><?php echo $oQuote->getServiceTime(); ?></td>
				<td><?php echo $oCurrency->formatPrice($oQuote->getLocalPriceIncTax()); ?></td>
				<td class="center">
					<input type="hidden" name="scribendi_service" value="<?php echo $oQuote->getServiceId(); ?>" />
					<input name="scribendi_order" type="submit" class="button scribendi-order-service" value="<?php esc_attr_e('Order Now'); ?>" />
				</td>
			</tr>
		<?php endforeach; ?>
		</table>
	<?php else: ?>
		<p><?php _e('No quotes are available at this time.', 'scribendi'); ?></p>
	<?php endif; ?>
<?php
}

/**
 * Renders the order details response
 *
 * @param Scribendi_Api_Model_Order $oOrder
 * @return void
 */
function scribendi_display_order(Scribendi_Api_Model_Order $oOrder) {
	$oCurrency = scribendi_get_default_currency();
?>
	<div id="scribendi-order-details">
	<h3>
		<?php if ( $oOrder->getStatus() == Scribendi_Api_Model_Order::STATUS_QUOTE ): ?>
			<?php _e('Scribendi Quote Details', 'scribendi'); ?>
		<?php else: ?>
			<?php _e('Scribendi Order Details', 'scribendi'); ?>
		<?php endif; ?>
	</h3>
	<table class="scribendi-quotes">
		<thead>
			<tr>
				<th class="scribendi-ready"><?php _e('Ready in', 'scribendi'); ?></th>
				<th class="scribendi-price"><?php _e('Price', 'scribendi'); ?></th>
				<th class="scribendi-actions"><?php _e('Date', 'scribendi')?></th>
			</tr>
		</thead>
		<tr id="scribendi-order-<?php echo $oOrder->getOrderId(); ?>">
			<td><?php echo $oOrder->getServiceTime(); ?></td>
			<td><?php echo $oCurrency->formatPrice($oOrder->getLocalPriceIncTax()); ?></td>
			<td><?php echo $oOrder->getOrderDate()->format('d/m/Y');?></td>
		</tr>
		<tr class="alt">
			<td class="left" colspan="3">
				<?php scribendi_display_order_status($oOrder); ?>
			</td>
		</tr>
		<?php if ( $oOrder->getStatus() == Scribendi_Api_Model_Order::STATUS_QUOTE ):?>
			<tr>
				<td class="left">
					<input name="scribendi_order" id="scribendi-order-cancel" type="submit" class="button scribendi-order-service" value="<?php esc_attr_e('Cancel'); ?>" />
				</td>
				<td></td>
				<td class="right">
					<input name="scribendi_order" type="submit" class="button-primary scribendi-order-service" value="<?php esc_attr_e('Make Payment'); ?>" onclick="<?php scribendi_payment_window($oOrder); ?>; return false;" />
				</td>
			</tr>
		<?php endif; ?>
		<tr>
			<td colspan="3" id="scribendi-order-ajax"></td>
		</tr>
	</table>
	</div>
<?php
}

/**
 * Writes in a bit of Javascript to open a new window
 *
 * @param Scribendi_Api_Model_Order $inOrder
 * @return void
 */
function scribendi_payment_window(Scribendi_Api_Model_Order $inOrder) {
	echo "window.open('".
		get_option(SCRIBENDI_API_PAYMENT_SERVER, 'https://www.scribendi.com/checkout?oid=').
		$inOrder->getOrderId().
		"','Scribendi',".
		"'width=800,height=600,location=1,scrollbars=1,scrolling=1,menubar=1,status=1,toolbar=1,resizable=1');";
}

/**
 * Renders the order status
 *
 * @param Scribendi_Api_Model_Order $inOrder
 * @return void
 */
function scribendi_display_order_status(Scribendi_Api_Model_Order $inOrder) {
	switch ( $inOrder->getStatus() ) {
		case Scribendi_Api_Model_Order::STATUS_RETURNED:
		case Scribendi_Api_Model_Order::STATUS_DONE:
			$message = __("This post has already been edited by Scribendi (Order Ref: {$inOrder->getOrderId()}).", 'scribendi');
		break;
		
		case Scribendi_Api_Model_Order::STATUS_SUSPENDED:
			$message = __("Your order has been suspended. Please contact Scribendi.com for assistance.", 'scribendi');
		break;
		
		case Scribendi_Api_Model_Order::STATUS_IN_PROGRESS:
			$message = __("Your order is being processed and should be ready by {$inOrder->getReadyBy()->format('H:i d/m/Y')}.", 'scribendi');
		break;
		
		case Scribendi_Api_Model_Order::STATUS_CANCELLED:
			$message = __("Your order was cancelled.", 'scribendi');
		break;
		
		case Scribendi_Api_Model_Order::STATUS_QUOTE:
			$message = __("Your order is a quote. Payment is required to have it edited.", 'scribendi');
		break;
		
		case -5:
			$message = __("There was an error restoring your order details. Please visit Scribendi.com.", 'scribendi');
		break;
	}
?>
<div class="misc-pub-section">
	<?php echo $message; ?>
</div>
<?php	
}

/**
 * Adds the javascript to the output to trigger payment requestor
 *
 * @param Scribendi_Api_Model_Order $inOrder
 * @return void
 */
function scribendi_trigger_payment(Scribendi_Api_Model_Order $inOrder) {
?>
<script type="text/javascript" >
<?php scribendi_payment_window($inOrder); ?>
</script>
<?php
}

/**
 * Displays a warning that the account is out of credit
 *
 * @param Scribendi_Api_Model_Order $inOrder
 * @return void
 */
function scribendi_trigger_payment_credit(Scribendi_Api_Model_Order $inOrder) {
?>
	<p><?php _e('Your account has reached your credit limit. Please pay by credit card or contact Customer Services for assistance.', 'scribendi'); ?></p>
<?php
}
