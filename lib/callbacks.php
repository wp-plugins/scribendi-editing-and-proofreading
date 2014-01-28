<?php
/**
 * Scribendi Wordpress Plugin - callbacks
 * 
 * The various callbacks used by the Scribendi plugin
 */

/**
 * Adds the required CSS resources
 *
 * @return void
 */
function scribendi_enqueue_styles() {
	wp_enqueue_style('scribendi_style', SCRIBENDI_PLUGIN_URL.'/css/scribendi.css', null, '1.2', 'screen');
	wp_enqueue_style('wp-jquery-ui-dialog');
}

/**
 * Adds the required JS scripts
 *
 * @return void
 */
function scribendi_enqueue_scripts() {
	wp_enqueue_script('jquery-ui-dialog');
	wp_enqueue_script('jquery-blockUI', SCRIBENDI_PLUGIN_URL . '/js/jquery.blockUI.js', 'jquery', '2.64', true );
}

/**
 * Create menu entry in the "Settings" section
 *
 * @return void
 */
function scribendi_create_menu() {
	add_submenu_page(
    	'options-general.php', 'Scribendi Settings', 'Scribendi', 'manage_options', 'scribendi-settings-config', 'scribendi_settings_page'
    );
	
	add_action('admin_init', 'scribendi_register_settings');
}

/**
 * Register the form options for the admin settings panel
 *
 * @return void
 */
function scribendi_register_settings() {
	register_setting('scribendi-settings-group', SCRIBENDI_PUBLIC_KEY);
	register_setting('scribendi-settings-group', SCRIBENDI_PRIVATE_KEY);
	register_setting('scribendi-settings-group', SCRIBENDI_CUSTOMER_ID);
	register_setting('scribendi-settings-group', SCRIBENDI_DEFAULT_CURRENCY);
	register_setting('scribendi-settings-group', SCRIBENDI_DEFAULT_ENGLISH);
	register_setting('scribendi-settings-group', SCRIBENDI_ALWAYS_POST_EDITS);
	
	register_setting('scribendi-settings-group', SCRIBENDI_API_SERVER);
	register_setting('scribendi-settings-group', SCRIBENDI_API_TIMEOUT);
	register_setting('scribendi-settings-group', SCRIBENDI_API_CONN_TIMEOUT);
	register_setting('scribendi-settings-group', SCRIBENDI_API_PAYMENT_SERVER);
	register_setting('scribendi-settings-group', SCRIBENDI_API_ORDER_SERVER);
	register_setting('scribendi-settings-group', SCRIBENDI_API_STATUS_INTERVAL);
}

/**
 * Adds an additional column to the posts / pages view
 *
 * @param array $columns
 * @return array
 */
function scribendi_register_columns($columns) {
	$columns['scribendi'] = __('Scribendi');
	return $columns;
}

/**
 * Handle Type column display
 * 
 * @return void
 */
function scribendi_get_column_data($column_name, $id) {
	$post = get_post($id);
	
	if ($column_name == 'scribendi') {
		$oOrder = scribendi_get_order_from_post($id);
		if ( $oOrder instanceof Scribendi_Api_Model_Order && $oOrder->getOrderId() > 0 ) {
			switch ( $oOrder->getStatus() ) {
				case Scribendi_Api_Model_Order::STATUS_RETURNED:
				case Scribendi_Api_Model_Order::STATUS_DONE:
					_e('Your order has been completed', 'scribendi');
				break;
				
				case Scribendi_Api_Model_Order::STATUS_CANCELLED:
					_e('Your editing request has been cancelled', 'scribendi');
				break;
				
				case Scribendi_Api_Model_Order::STATUS_IN_PROGRESS:
					_e('Your editing request is in progress', 'scribendi');
				break;
				
				case Scribendi_Api_Model_Order::STATUS_QUOTE:
					_e('You have an active quote for this item', 'scribendi');
				break;
				
				case Scribendi_Api_Model_Order::STATUS_SUSPENDED:
					_e('Your order has been suspended, please contact Scribendi.com', 'scribendi');
				break;
				
				case -5:
					_e('Error retrieving Scribendi order details', 'scribendi');
				break;
			}
		} else {
			_e('<a href="post.php?action=edit&amp;post='.$id.'&amp;scribendi_request_edit=true">Request an Edit</a>', 'scribendi');
		}
	}
}

/**
 * Adds the custom Scribendi toolbox to the admin pages
 *
 * @return void
 */
function scribendi_register_sidebar_controls() {
	add_meta_box(SCRIBENDI_TOOLBOX_ID, __('Scribendi Tools', 'scribendi'), 'scribendi_toolbox_builder', 'post', 'side', 'high');
	add_meta_box(SCRIBENDI_TOOLBOX_ID, __('Scribendi Tools', 'scribendi'), 'scribendi_toolbox_builder', 'page', 'side', 'high');
	
	/*
	 * Override the revisions meta-box to make it useful 
	 */
	add_meta_box('revisionsdiv', __('Post Revisions'), 'scribendi_post_revisions_meta_box', 'post', 'normal', 'high');
	add_meta_box('revisionsdiv', __('Page Revisions'), 'scribendi_post_revisions_meta_box', 'page', 'normal', 'high');
}

/**
 * Override for the revisions meta-box
 *
 * This is a direct replacement for the crap Wordpress functions that
 * have absolutely no override capabilities at all. This allows us to
 * flag revisions that have been edited by Scribendi.
 * 
 * @param mixed $post
 * @return void
 */
function scribendi_post_revisions_meta_box($post) {
	if ( !$post = get_post( $post_id ) ) {
		return;
	}
	if ( !$revisions = wp_get_post_revisions( $post->ID ) ) {
		_e('No revisions found', 'scribendi');
		return;
	}
	
	$previousOrders = unserialize(get_post_meta($post->ID, SCRIBENDI_OPTION_PREVIOUS_ORDERS, true));
	if ( !is_array($previousOrders) ) {
		$previousOrders = array();
	}
	$scribendiRevisionID = get_post_meta($post->ID, SCRIBENDI_OPTION_SCRIBENDI_REVISION, true);
	$scribendiOrderID = get_post_meta($post->ID, SCRIBENDI_OPTION_ORDER_ID, true);
	
	// add current post to revisions
	array_unshift( $revisions, $post );
	
	$class = false;
	$can_edit_post = current_user_can('edit_post', $post->ID);
	$scribendiOrderServer = get_option(SCRIBENDI_API_ORDER_SERVER, 'https://scribendi/customer?view=cp_order_details&oid=');
	
	echo '<table class="widefat post-revisions" cellspacing="0"><col style="width: 40%" /><col style="width: 40%" /><col style="width: 20%" /><thead><tr>
		<th scope="col">'.__('Date Created').'</th>
		<th scope="col">'.__('Author').'</th>
		<th scope="col" class="action-links">'.__('Actions').'</th>
		</tr></thead><tbody>';

	foreach ( $revisions as $revision ) {
		if ( !current_user_can( 'read_post', $revision->ID ) ) {
			continue;
		}
		if ( 'revision' === $revision->post_type && wp_is_post_autosave( $revision ) ) {
			continue;
		}
		if ( $revision->ID == $scribendiRevisionID || array_key_exists($revision->ID, $previousOrders) ) {
			$orderID = $revision->ID == $scribendiRevisionID ? $scribendiOrderID : $previousOrders[$revision->ID];
			$name = 'Scribendi.com Editors (<a href="'.$scribendiOrderServer.$orderID.'" target="_blank" title="'.esc_attr__('View order details on Scribendi.com (opens in new window)','scribendi').'">Order Ref: '.$orderID.'</a>)';
			$class = 'scribendi-highlight';
		} else {
			$name = get_the_author_meta( 'display_name', $revision->post_author );
			$class = $class ? '' : "alternate";
		}
		
		$date = wp_post_revision_title($revision);
		
		echo '<tr class="'.$class.'"><td>'.$date.'</td><td>'.$name.'</td><td class="action-links">';
		if ( $post->ID != $revision->ID && $can_edit_post ) {

            if ( get_bloginfo('version') >= 3.6 ) {

                $wp_nonce = wp_create_nonce('restore-post_' . $revision->ID);

			    echo '<a href="revision.php?revision='.$revision->ID.'">' . __('Compare', 'Scribendi') . '</a>';
                echo ' | <a href="revision.php?revision=' . $revision->ID . '&action=restore&_wpnonce=' . $wp_nonce . '">' . __('Publish', 'Scribendi').'</a>';

            } else {

                echo '<a href="revision.php?action=diff&right='.$post->ID.'&left='.$revision->ID.'">Compare</a>';
                echo ' | <a href="revision.php'.wp_nonce_url(add_query_arg(array( 'revision' => $revision->ID, 'diff' => false, 'action' => 'restore')), "restore-post_$post->ID|$revision->ID" ).'">'. __('Publish', 'Scribendi').'</a>';

            }
		}
		echo '</td></tr>';
	}
	echo '</tbody></table>';
}

/**
 * Builds the Scribendi toolbox controls
 *
 * @return void
 */
function scribendi_toolbox_builder($post) {
	echo '<p>';
	_e('Check your account and orders at any time at ', 'scribendi');
	echo '<a href="https://www.scribendi.com/customer">Scribendi.com</a>.';
	echo '</p>';

	/*
	 * Only add controls if good API details have been provided and the post has been saved already
	 */

    // Test API Settings
    $api_test = scribendi_test_api_settings();

    if($api_test instanceof Exception) {

        echo '<p>';
        _e('You must provide valid API details before you can use this plugin.
            Please verify you have entered your details correctly and have confirmed your
            email address during the registration process.', 'scribendi');
        echo '</p><p>';
        echo '<a href="' . get_bloginfo('url') . '/wp-admin/options-general.php?page=scribendi-settings-config">';
        _e('Click Here', 'scribendi');
        echo '</a>';
        _e(' to register or manage your settings.', 'scribendi');

    } elseif ( $post->post_status == 'auto-draft' ) {

		scribendi_display_toolbox_not_available();

	}  else {
		/*
		 * Fetch order object, always returns object regardless of status
		 */
		$oOrder = scribendi_get_order_from_post($post->ID);

		/*
		 * Show current order status
		 */
		if ( $oOrder->getOrderId() > 0 ) {
			scribendi_display_order($oOrder);
		}

		/*
		 * Render the rest of the toolbox
		 */
		scribendi_display_toolbox($oOrder);
	}
}

/**
 * Registers the admin ajax calls for the plugin
 *
 * @return void
 */
function scribendi_register_ajax_calls() {
	scribendi_ajax_calls();
}

/**
 * Registers the scribendi options with the user settings
 *
 * @param object $user Current user that is being edited
 * @return void
 */
function scribendi_register_user_settings($user) {
	scribendi_user_settings_page($user);
}

/**
 * Updates the user profile with Scribendi options
 *
 * @param integer $user_id
 * @return void
 */
function scribendi_user_profile_update($user_id) {
	if ( current_user_can('edit_user', $user_id) && isset($_POST[SCRIBENDI_CUSTOMER_ID]) && is_numeric($_POST[SCRIBENDI_CUSTOMER_ID]) && $_POST[SCRIBENDI_CUSTOMER_ID] > 0 ) {
		update_user_option($user_id, SCRIBENDI_CUSTOMER_ID, wp_filter_nohtml_kses($_POST[SCRIBENDI_CUSTOMER_ID]));
	}
}