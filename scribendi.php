<?php
/*
Plugin Name: Scribendi.com Editing and Proofreading Services
Plugin URI: http://wordpress.org/extend/plugins/scribendi-editing-and-proofreading/
Description: Scribendi provides ISO certified, comprehensive, and professional editing services to WordPress users. Our services are available 24/7.
Version: 2.0.0
Author: Scribendi.com
Author URI: http://www.scribendi.com/
*/
/*  Copyright 2011  Scribendi.com (link : http://www.scribendi.com/contact)

    This program is free software; you can redistribute it and/or modify
    it under the terms of the GNU General Public License, version 2, as
    published by the Free Software Foundation.

    This program is distributed in the hope that it will be useful,
    but WITHOUT ANY WARRANTY; without even the implied warranty of
    MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
    GNU General Public License for more details.

    You should have received a copy of the GNU General Public License
    along with this program; if not, write to the Free Software
    Foundation, Inc., 51 Franklin St, Fifth Floor, Boston, MA  02110-1301  USA
*/

/*
 * Define the base constants
 */
define('SCRIBENDI_BASEURL', 'http://www.scribendi.com');
define('SCRIBENDI_CHECK_PERIOD', 5 * 60); // query server period in seconds
define('SCRIBENDI_PLUGIN_DIR', WP_PLUGIN_DIR . '/' . plugin_basename(dirname(__FILE__)));
define('SCRIBENDI_PLUGIN_URL', WP_PLUGIN_URL . '/' . plugin_basename(dirname(__FILE__)));
define('SCRIBENDI_SERVICE', 258); // service to use, DO NOT CHANGE!
define('SCRIBENDI_PLUGIN_SOURCE', 'wordpress');

/*
 * Set language resource
 */
load_plugin_textdomain('scribendi', false, 'lib/languages/');

/*
 * Register the activation hook
 */
register_activation_hook(__FILE__, 'scribendi_activate');

/*
 * Display errors
 */
if ( isset($_GET['action']) && 'error_scrape' == $_GET['action']) {
	echo get_option('scribendi_activate_error');
	delete_option('scribendi_activate_error');
	die();
}

/**
 * Run requirements checks and die if they fail
 *
 * @return void
 */
function scribendi_activate() {
	$error = '';

	/*
	 * Block old versions of PHP
	 */
	if ( version_compare(PHP_VERSION, '5.2.3', '<=') ) {
		$error .= __('Your version of PHP is too old to run the Scribendi plugin.', 'scribendi').'<br />';
		$error .= __('Please update your PHP installation to at least 5.2.3.', 'scribendi').'<br />';
		$error .= __('You must deactivate this plugin.', 'scribendi');

		add_option('scribendi_activate_error', $error);
		trigger_error('', E_USER_ERROR);
	}

	if ( defined('WP_POST_REVISIONS') && WP_POST_REVISIONS != 'WP_POST_REVISIONS' ) {
		if ( WP_POST_REVISIONS !== true || WP_POST_REVISIONS !== -1) {
			$error .= __('You have either disabled or limited the Wordpress Revision System.', 'scribendi').'<br />';
			$error .= __('Please set WP_POST_REVISIONS to true before using the Scribendi plugin.', 'scribendi').'<br />';
			$error .= __('You must deactivate this plugin.', 'scribendi');

			add_option('scribendi_activate_error', $error);
			trigger_error('', E_USER_ERROR);
		}
	}
}

/**
 * Log messages to a text file for debugging
 *
 * @param mixed $inMessage String, array or object or whatever
 * @param string $inSource Additional source
 * @return void
 */
function logMessage($inMessage, $inSource = '[ALWAYS]') {
	if ( is_array($inMessage) || is_object($inMessage) ) {
		$inMessage = print_r($inMessage,1);
	}

	/*
	 * Remove any carriage returns (ascii 13)
	 */
	$inMessage = preg_replace("/\r/", '', $inMessage);

	/*
	 * Prefix with a timestamp and the $source if set, using the mask set in the writer for the date
	 */
	$datestamp = date('Y-m-d H:i:s');

	/*
	 * Strip new lines and reformat with timestamp
	 */
	$inMessage = preg_replace("/\n/", "\n$datestamp".(($inSource) ? " $inSource" : ''), $inMessage);
	$inMessage = $datestamp.(($inSource) ? " $inSource" : '')." $inMessage\n";

	$logFile = dirname(__FILE__).'/debug.log';
	if ( !file_exists($logFile) ) {
		touch($logFile);
		chmod($logFile, 0644);
	}
	@file_put_contents($logFile, $inMessage, LOCK_EX|FILE_APPEND);
}


/*
 * Load required dependencies
 */
require_once SCRIBENDI_PLUGIN_DIR.'/lib/scribendi_api_lib.php';
require_once SCRIBENDI_PLUGIN_DIR.'/lib/views.php';
require_once SCRIBENDI_PLUGIN_DIR.'/lib/api_functions.php';
require_once SCRIBENDI_PLUGIN_DIR.'/lib/callbacks.php';
require_once SCRIBENDI_PLUGIN_DIR.'/lib/account_functions.php';
require_once SCRIBENDI_PLUGIN_DIR.'/lib/order_functions.php';
spl_autoload_register('Scribendi_Api_Autoloader::autoload');

/*
 * Create some constants for convenience
 */
define('SCRIBENDI_PUBLIC_KEY', 'scribendi_'.Scribendi_Api_Constants::FIELD_PUBLIC_KEY);
define('SCRIBENDI_PRIVATE_KEY', 'scribendi_privatekey');
define('SCRIBENDI_CUSTOMER_ID', 'scribendi_'.Scribendi_Api_Constants::FIELD_REQUEST_CUSTOMER);
define('SCRIBENDI_DEFAULT_CURRENCY', 'scribendi_'.Scribendi_Api_Constants::FIELD_CURRENCY_ID);
define('SCRIBENDI_DEFAULT_ENGLISH', 'scribendi_'.Scribendi_Api_Constants::FIELD_ORDER_ENGLISH_VERSION);
define('SCRIBENDI_ALWAYS_POST_EDITS', 'scribendi_always_post_edits');

define('SCRIBENDI_API_SERVER', 'scribendi_'.str_replace('.', '_', Scribendi_Api_Client::OPTION_API_SERVER));
define('SCRIBENDI_API_PAYMENT_SERVER', 'scribendi_payment_server');
define('SCRIBENDI_API_ORDER_SERVER', 'scribendi_order_server');
define('SCRIBENDI_API_CONN_TIMEOUT', 'scribendi_'.str_replace('.', '_', Scribendi_Api_Client::OPTION_CLIENT_CONNECTION_TIMEOUT));
define('SCRIBENDI_API_TIMEOUT', 'scribendi_'.str_replace('.', '_', Scribendi_Api_Client::OPTION_CLIENT_TIMEOUT));
define('SCRIBENDI_API_STATUS_INTERVAL', 'scribendi_status_interval');
define('SCRIBENDI_API_STATUS_DATE', 'scribendi_status_date');

define('SCRIBENDI_API_CURRENCIES', 'scribendi_currencies');
define('SCRIBENDI_API_CURRENCIES_DATE', 'scribendi_currencies_date');
define('SCRIBENDI_API_CURRENCIES_LIFETIME', 86400*7); // cache currency info for a week

define('SCRIBENDI_OPTION_ORDER_ID', '_scribendi_order_id');
define('SCRIBENDI_OPTION_ORDER_DETAILS', '_scribendi_order_details');
define('SCRIBENDI_OPTION_ORDER_DOWNLOADED', '_scribendi_order_downloaded');
define('SCRIBENDI_OPTION_ORDER_REVISION', '_scribendi_order_revision');
define('SCRIBENDI_OPTION_PREVIOUS_ORDERS', '_scribendi_previous_orders');
define('SCRIBENDI_OPTION_SCRIBENDI_REVISION', '_scribendi_edited_revision');

define('SCRIBENDI_TOOLBOX_ID', 'scribendi_toolbox');

/*
 * Register our additional stylesheet
 */
add_action('admin_init', 'scribendi_enqueue_styles');
add_action('admin_init', 'scribendi_enqueue_scripts');

/*
 * Register admin menu and header components
 */
add_action('admin_menu', 'scribendi_create_menu');
add_action('admin_menu', 'scribendi_register_sidebar_controls');
add_action('admin_footer', 'scribendi_register_ajax_calls');
add_action('admin_footer', 'scribendi_dialog_templates');

/*
 * Register other filters / actions
 */
add_filter('manage_posts_columns', 'scribendi_register_columns');
add_filter('manage_pages_columns', 'scribendi_register_columns');
add_action('manage_posts_custom_column', 'scribendi_get_column_data', 10, 2);
add_action('manage_pages_custom_column', 'scribendi_get_column_data', 10, 2);

/*
 * Add hook into user settings
 */
add_action('show_user_profile', 'scribendi_register_user_settings');
add_action('edit_user_profile', 'scribendi_register_user_settings');
add_action('profile_update', 'scribendi_user_profile_update');

/*
 * Register status update check
 */
add_action('admin_init', 'scribendi_order_status_update');

/*
 * Bind ajax commands
 */
add_action('wp_ajax_scribendi_quote', 'scribendi_quote');
add_action('wp_ajax_scribendi_order', 'scribendi_order');
add_action('wp_ajax_scribendi_order_cancel', 'scribendi_order_cancel');
add_action('wp_ajax_scribendi_key_check', 'scribendi_key_check');