<?php
/*
 * Plugin Name: Coviu Video Calls
 * Plugin URI: http://wordpress.org/extend/plugins/coviu-calls/
 * Description: Add Coviu video calling to your Website. 
 * Author: Silvia Pfeiffer
 * Version: 0.1
 * Author URI: http://www.coviu.com/
 * License: GPL2
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html.
 * Text Domain: coviu-calls
 * Domain Path: /languages
 */

/*
	Copyright 2015  Silvia Pfeiffer  (email : silviapfeiffer1@gmail.com)

	This program is free software; you can redistribute it and/or modify
	it under the terms of the GNU General Public License as published by
	the Free Software Foundation; either version 2 of the License, or
	(at your option) any later version.

	This program is distributed in the hope that it will be useful,
	but WITHOUT ANY WARRANTY; without even the implied warranty of
	MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
	GNU General Public License for more details.

	You should have received a copy of the GNU General Public License
	along with this program; if not, write to the Free Software
	Foundation, Inc., 51 Franklin St, Fifth Floor, Boston, MA  02110-1301  USA

	@package    coviu-calls
	@author     Silvia Pfeiffer <silviapfeiffer1@gmail.com>
	@copyright  Copyright 2015 Silvia Pfeiffer
	@license    http://www.gnu.org/licenses/gpl.txt GPL 2.0
	@version    0.1
	@link       http://wordpress.org/extend/plugins/coviu-calls/

*/
defined( 'ABSPATH' ) or die( 'No script kiddies please!' );


require_once(WP_PLUGIN_DIR . '/coviu-calls/coviu-api.php');
require_once(WP_PLUGIN_DIR . '/coviu-calls/coviu-shortcode.php');

/// ***  Set up and remove options for plugin *** ///

register_activation_hook( __FILE__, 'cvu_setup_options' );
function cvu_setup_options() {
	$options = new stdClass();
	$options->api_key = '';
	$options->api_key_secret = '';

	add_option('coviu-calls', $options);
}

register_deactivation_hook( __FILE__, 'cvu_teardown_options' );
function cvu_teardown_options() {
	delete_option('coviu-calls');
}


/// ***   Admin Settings Page   *** ///

add_action( 'admin_menu', 'cvu_admin_menu' );

function cvu_admin_menu() {
	add_options_page(__('Coviu Video Calls Settings', 'coviu-calls'), __('Coviu Calls', 'coviu-calls'), 'manage_options', __FILE__, 'cvu_settings_page');
}

function cvu_settings_page() {
	if ( !current_user_can( 'manage_options' ) )  {
		wp_die( __( 'You do not have sufficient permissions to access this page.' ) );
	}

	// retrieve stored options
	$options = get_option('coviu-calls');

	// process form data
	if( isset($_POST['coviu']) ) {

		// nonce check
		if (! isset( $_POST['cvu_options_security'] ) ||
			! wp_verify_nonce( $_POST['cvu_options_security'], 'cvu_options')) {
			print 'Sorry, your nonce did not verify.';
			exit;

		} else {

			if ($_POST['coviu']['action'] == 'credentials') {

				// clean up entered data from surplus white space
				$_POST['coviu']['api_key']        = trim(sanitize_text_field($_POST['coviu']['api_key']));
				$_POST['coviu']['api_key_secret'] = trim(sanitize_text_field($_POST['coviu']['api_key_secret']));

				// check if credentials were provided
				if ( !$_POST['coviu']['api_key'] || !$_POST['coviu']['api_key_secret'] ) {
					?>
					<div class="error">
						<p><strong><?php echo __('Missing API credentials.', 'coviu-calls'); ?></strong></p>
					</div>
					<?php
				} else {

					// updating credentials
					$options->api_key    = $_POST['coviu']['api_key'];
					$options->api_key_secret = $_POST['coviu']['api_key_secret'];
					update_option('coviu-calls', $options);

					?>
					<div class="updated">
						<p><strong><?php echo __('Stored credentials.', 'coviu-calls'); ?></strong></p>
					</div>
					<?php
				}

			} elseif ($_POST['coviu']['action'] == 'delete_subscription') {

				cvu_subscription_delete( $_POST['coviu']['subscription_id'], $options );

			} elseif ($_POST['coviu']['action'] == 'add_subscription') {

				cvu_subscription_add( $_POST['coviu'], $options );

			}
		}
	}

	// render the settings page
	?>
	<div class="wrap">
		<h2><?php _e('Coviu Video Calls Settings', 'coviu-calls'); ?></h2>

		<!-- DISPLAY CREDENTIALS FORM -->
		<h3><?php _e('Credentials', 'coviu-calls'); ?></h3>
		<p>
			To use Coviu Video Conferencing, you need a <a href="https://www.coviu.com/developer/" target="_blank">developer account</a> and credentials for accessing the API.
		</p>

		<?php
			cvu_credentials_form( $_SERVER["REQUEST_URI"], $options );
		?>

		<!-- DISPLAY SUBSCRIPTIONS LIST -->
		<?php
		if ($options->api_key != '' && $options->api_key_secret != '') {
			?>

			<h3><?php _e('Subscriptions', 'coviu-calls'); ?></h3>
			<h4>Add a  subscription</h4>
			<?php
			cvu_subscription_form( $_SERVER["REQUEST_URI"] );
			?>

			<h4>List of active subscriptions</h4>
			<?php
			cvu_subscriptions_display( $_SERVER["REQUEST_URI"], $options );
		}
		?>
	</div>
	<?php

}

function cvu_credentials_form( $actionurl, $options ) {
	?>
	<form id="credentials" method="post" action="<?php echo $actionurl; ?>">
		<?php wp_nonce_field( 'cvu_options', 'cvu_options_security' ); ?>
		<input type="hidden" name="coviu[action]" value="credentials" />

		<p>
			<?php _e('API Key:', 'coviu-calls'); ?>
			<input type="text" name="coviu[api_key]" value="<?php echo $options->api_key ?>"/>
		</p>
		<p>
			<?php _e('Secret Key:', 'coviu-calls'); ?>
			<input type="text" name="coviu[api_key_secret]" value="<?php echo $options->api_key_secret ?>"/>
		</p>
		<p class="submit">
			<input name="Submit" type="submit" class="button-primary" value="<?php _e('Add credentials', 'coviu-calls'); ?>" />
		</p>
	</form>
	<?php
}

function cvu_subscription_form( $actionurl ) {
	?>
	<form id="add_subscription" method="post" action="<?php echo $actionurl; ?>">
		<?php wp_nonce_field( 'cvu_options', 'cvu_options_security' ); ?>
		<input type="hidden" name="coviu[action]" value="add_subscription" />

		<p>
			<?php _e('Reference:', 'coviu-calls'); ?>
			<input type="text" name="coviu[ref]" value="Subscriber reference"/>
		</p>
		<p>
			<?php _e('Name:', 'coviu-calls'); ?>
			<input type="text" name="coviu[name]" value="Name of subscription owner"/>
		</p>
		<p>
			<?php _e('Email:', 'coviu-calls'); ?>
			<input type="text" name="coviu[email]" value="Email of subscription owner"/>
		</p>
		<p class="submit">
			<input name="Submit" type="submit" class="button-primary" value="<?php _e('Add subscription', 'coviu-calls'); ?>" />
		</p>
	</form>
	<?php
}

function cvu_subscriptions_display( $actionurl, $options ) {
	?>
	<script type="text/javascript">
		function delete_subscription(subscription_id) {
			jQuery('#subscription_id').val(subscription_id);
			var confirmtext = <?php echo '"'. sprintf(__('Are you sure you want to remove subscription %s?', 'coviu-calls'), '"+ subscription_id +"') .'"'; ?>;
			if (!confirm(confirmtext)) {
					return false;
			}
			jQuery('#delete_subscription').submit();
		}
	</script>

	<form id="delete_subscription" method="post" action="<?php echo $actionurl; ?>">
		<?php wp_nonce_field( 'cvu_options', 'cvu_options_security' ); ?>
		<input type="hidden" name="coviu[action]" value="delete_subscription" />
		<input type="hidden" name="coviu[subscription_id]" id="subscription_id"/>

		<style>
			#subscription_list tbody tr:nth-of-type(even) {background-color: white;}
			#subscription_list th {
				background-color:#0085ba;
				font-weight:bold;
				color:#fff;
				padding: 0 5px;
			}
			#subscription_list tbody tr td:nth-of-type(1) {font-weight: bold;}
		</style>

		<table id="subscription_list">
		<thead>
			<tr>
				<th>Reference</th>
				<th>Name</th>
				<th>Email</th>
				<th>Sessions</th>
				<th>Action</th>
			</tr>
		</thead>
		<tbody>
		<?php
			// Recover an access token
			$grant = get_access_token( $options->api_key, $options->api_key_secret );

			// Get the root of the api
			$api_root = get_api_root($grant->access_token);

			// Get the first page of subscriptions
			$subscriptions = get_subscriptions( $grant->access_token, $api_root );

			// print active subscriptions
			for ($i=0, $c=count($subscriptions->content); $i<$c; $i++) {
				if ($subscriptions->content[$i]->content->active) {
					cvu_subscription_display( $subscriptions->content[$i], $options );
				}
			}
		?>
		</tbody>
		</table>
	</form>
	<?php
}

function cvu_subscription_display( $subscription, $options ) {
	// Recover an access token
	$grant = get_access_token( $options->api_key, $options->api_key_secret );

	// Get the root of the api
	$api_root = get_api_root($grant->access_token);

	// Retrieve number of sessions
	$sessions = get_sessions($grant->access_token, $api_root, $subscription);

	?>
	<tr>
		<td><?php echo $subscription->content->remoteRef; ?></td>
		<td><?php echo $subscription->content->name; ?></td>
		<td><?php echo $subscription->content->email; ?></td>
		<td><?php echo count($sessions->content); ?></td>
		<td><a href="#" onclick="delete_subscription('<?php echo $subscription->content->subscriptionId; ?>');"><?php echo __('Delete') ?></a></td>
	</tr>
	<?php
}

function cvu_subscription_delete( $subscriptionId, $options ) {
	// Recover an access token
	$grant = get_access_token( $options->api_key, $options->api_key_secret );

	// Get the root of the api
	$api_root = get_api_root($grant->access_token);

	// delete the subscription
	$deleted = delete_subscription( $grant->access_token, $api_root, $subscriptionId );

	// notify if deleted
	if ($deleted) {
		?><div class="updated"><p><strong><?php printf(__("Deleted subscription %s.", "Deleted subscription %s.", $subscriptionId, 'coviu-calls'), $subscriptionId); ?></strong></p></div><?php
	} else {
		?><div class="error"><p><strong><?php echo __("Can't delete a subscription that doesn't exist.", 'coviu-calls'); ?></strong></p></div><?php
	}
}

function cvu_subscription_add( $post, $options ) {
	// Recover an access token
	$grant = get_access_token( $options->api_key, $options->api_key_secret );

	// Get the root of the api
	$api_root = get_api_root($grant->access_token);

	// Create a new subscription
	$subscription = create_subscription( $grant->access_token,
																			 $api_root,
																			 array('ref' => $post['ref'],
																						'name' => $post['name'],
																					 'email' => $post['email']
																			 ) );
}

function prettyprint($var) {
	print '<pre>'; print_r($var); print '</pre>';
}
