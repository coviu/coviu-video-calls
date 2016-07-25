<?php
/*
 * Plugin Name: Coviu Video Calls
 * Plugin URI: http://wordpress.org/extend/plugins/coviu-video-calls/
 * Description: Add Coviu video calling to your Website. 
 * Author: Silvia Pfeiffer, NICTA, Coviu
 * Version: 0.1
 * Author URI: http://www.coviu.com/
 * License: GPL2
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html.
 * Text Domain: coviu-video-calls
 * Domain Path: /languages
 */

/*
	Copyright 2015  Silvia Pfeiffer  (email : silvia.pfeiffer@coviu.com)
	Copyright 2015  National ICT Australia Limited (NICTA)
	Copyright 2015  Coviu

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

	@package    coviu-video-calls
	@author     Silvia Pfeiffer <silvia.pfeiffer@coviu.com>
	@copyright  Copyright 2015 Silvia Pfeiffer, NICTA, Coviu
	@license    http://www.gnu.org/licenses/gpl.txt GPL 2.0
	@version    0.1
	@link       http://wordpress.org/extend/plugins/coviu-video-calls/

*/
/*
	For Documentation of the Coviu API, refer to:
	https://github.com/coviu/coviu-api-python-demo
*/


defined( 'ABSPATH' ) or die( 'No script kiddies please!' );

require_once __DIR__.'/vendor/autoload.php';
use coviu\Api\Coviu;

require_once(WP_PLUGIN_DIR . '/coviu-video-calls/coviu-shortcode.php');

/// ***  Set up and remove options for plugin *** ///

register_activation_hook( __FILE__, 'cvu_setup_options' );
function cvu_setup_options() {
	$options = new stdClass();
	$options->api_key = '';
	$options->api_key_secret = '';

	add_option('coviu-video-calls', $options);
}

register_deactivation_hook( __FILE__, 'cvu_teardown_options' );
function cvu_teardown_options() {
	delete_option('coviu-video-calls');
}


/// ***   Admin Settings Page   *** ///

add_action( 'admin_menu', 'cvu_admin_menu' );
add_action( 'admin_enqueue_scripts', 'cvu_register_admin_scripts' );

function cvu_admin_menu() {
	add_options_page(__('Coviu Video Calls Settings', 'coviu-video-calls'), __('Coviu Calls', 'coviu-video-calls'), 'manage_options', __FILE__, 'cvu_settings_page');
}

function cvu_register_admin_scripts() {
	wp_enqueue_style( 'jquery-ui-datepicker' , '//ajax.googleapis.com/ajax/libs/jqueryui/1.10.4/themes/smoothness/jquery-ui.css');
	wp_enqueue_script( 'jquery-ui-datepicker' );
}

function cvu_settings_page() {
	if ( !current_user_can( 'manage_options' ) )  {
		wp_die( __( 'You do not have sufficient permissions to access this page.' ) );
	}

	// retrieve stored options
	$options = get_option('coviu-video-calls');

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
						<p><strong><?php echo __('Missing API credentials.', 'coviu-video-calls'); ?></strong></p>
					</div>
					<?php
				} else {

					// updating credentials
					$options->api_key    = $_POST['coviu']['api_key'];
					$options->api_key_secret = $_POST['coviu']['api_key_secret'];
					update_option('coviu-video-calls', $options);

					?>
					<div class="updated">
						<p><strong><?php echo __('Stored credentials.', 'coviu-video-calls'); ?></strong></p>
					</div>
					<?php
				}

			} elseif ($_POST['coviu']['action'] == 'delete_session') {

				cvu_session_delete( $_POST['coviu']['session_id'], $options );

			} elseif ($_POST['coviu']['action'] == 'add_session') {

				cvu_session_add( $_POST['coviu'], $options );

			}
		}
	}

	// render the settings page
	?>
	<div class="wrap">
		<h2><?php _e('Coviu Video Calls Settings', 'coviu-video-calls'); ?></h2>

		<!-- DISPLAY CREDENTIALS FORM -->
		<h3><?php _e('Credentials', 'coviu-video-calls'); ?></h3>
		<p>
			To use Coviu Video Calls, you need a <a href="https://www.coviu.com/developer/" target="_blank">developer account</a> and credentials for accessing the API.
		</p>

		<?php
			cvu_credentials_form( $_SERVER["REQUEST_URI"], $options );
		?>

		<!-- DISPLAY SESSION LIST -->
		<?php
		if ($options->api_key != '' && $options->api_key_secret != '') {
			?>

			<h3><?php _e('Add a session', 'coviu-video-calls'); ?></h3>
			<?php
			cvu_session_form( $_SERVER["REQUEST_URI"] );
			?>

			<h3><?php _e('List of sessions', 'coviu-video-calls'); ?></h3>
			<?php
			cvu_sessions_display( $_SERVER["REQUEST_URI"], $options );
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
			<?php _e('API Key:', 'coviu-video-calls'); ?>
			<input type="text" name="coviu[api_key]" value="<?php echo $options->api_key ?>"/>
		</p>
		<p>
			<?php _e('Password:', 'coviu-video-calls'); ?>
			<input type="text" name="coviu[api_key_secret]" value="<?php echo $options->api_key_secret ?>"/>
		</p>
		<p class="submit">
			<input name="Submit" type="submit" class="button-primary" value="<?php _e('Add credentials', 'coviu-video-calls'); ?>" />
		</p>
	</form>
	<?php
}

function cvu_session_form( $actionurl ) {
	?>
	<script type="text/javascript">
		jQuery(document).ready(function($){
			$('#datepicker').datepicker({
				dateFormat: "dd M yy"
		  });
		});
	</script>

	<form id="add_session" method="post" action="<?php echo $actionurl; ?>">
		<?php wp_nonce_field( 'cvu_options', 'cvu_options_security' ); ?>
		<input type="hidden" name="coviu[action]" value="add_session" />

		<p>
			<?php _e('Description:', 'coviu-video-calls'); ?>
			<input type="text" name="coviu[name]" value="Description of session"/>
		</p>
		<p>
			<?php _e('Date:', 'coviu-video-calls'); ?>
			<input id="datepicker" type="text" name="coviu[date]" value="<?php echo date('d M Y'); ?>" />
		</p>
		<p>
			<?php _e('Start time:', 'coviu-video-calls'); ?>
			<input type="time" name="coviu[start]" value="<?php echo date('H:i', time()); ?>" />
		</p>
		<p>
			<?php _e('End time:', 'coviu-video-calls'); ?>
			<input type="time" name="coviu[end]" value="<?php echo date('H:i', time() + (60*60)); ?>" />
		</p>
		<p class="submit">
			<input name="Submit" type="submit" class="button-primary" value="<?php _e('Add session', 'coviu-video-calls'); ?>" />
		</p>
	</form>
	<?php
}

function cvu_sessions_display( $actionurl, $options ) {
	?>
	<script type="text/javascript">
		var sessions = [];
		function delete_session(session_id) {
			jQuery('#session_id').val(session_id);
			jQuery('#submit_action').val('delete_session');
			var confirmtext = <?php echo '"'. sprintf(__('Are you sure you want to remove session %s?', 'coviu-video-calls'), '"+ session_id +"') .'"'; ?>;
			if (!confirm(confirmtext)) {
					return false;
			}
			jQuery('#edit_session').submit();
		}
		function add_participant(session_id, type) {
			console.log("adding " + type + " to " + session_id);
		}
	</script>

	<form id="edit_session" method="post" action="<?php echo $actionurl; ?>">
		<?php wp_nonce_field( 'cvu_options', 'cvu_options_security' ); ?>
		<input type="hidden" name="coviu[action]" id="submit_action"/>
		<input type="hidden" name="coviu[session_id]" id="session_id"/>

		<style>
			.cvu_list tbody tr:nth-of-type(even) {background-color: white;}
			.cvu_list th {
				background-color:#0085ba;
				font-weight:bold;
				color:#fff;
				padding: 0 5px;
			}
			.cvu_list tbody tr td:nth-of-type(1) {font-weight: bold;}
		</style>

		<?php
			// Recover coviu
			$coviu = new Coviu($options->api_key, $options->api_key_secret);

			// Get the first page of sessions
			$sessions = $coviu->sessions->getSessions();
			$sessions = $sessions['content'];
			//var_dump($sessions);

			// for overlays
			add_thickbox();
		?>
			<script type="text/javascript">
			sessions = <?php echo json_encode($sessions); ?>;
			console.log(sessions);
			</script>

			<table class="cvu_list">
			<thead>
				<tr>
					<th>ID</th>
					<th>Description</th>
					<th>Start</th>
					<th>End</th>
					<th>Host</th>
					<th>Guest</th>
					<th>Action</th>
				</tr>
			</thead>
			<tbody>
			<?php

			// print active sessions
			for ($i=0, $c=count($sessions); $i<$c; $i++) {
				$hosts = array();
				$guests = array();
				if (array_key_exists('participants', $sessions[$i])) {
					foreach ($sessions[$i]['participants'] as $participant) {
						if ($participant['role'] == "HOST") {
							array_push($hosts, $participant);
						} else {
							array_push($guests, $participant);
						}
					}
				}
				cvu_session_display( $sessions[$i] , $hosts, $guests);
			}
		?>
		</tbody>
		</table>
		<div id="sessions">
		</div>
	</form>
	<?php
}

function cvu_session_display( $session , $hosts, $guests ) {
	?>
	<tr>
		<td><?php echo substr($session['session_id'], 0, 5). " ... "; ?></td>
		<td><?php echo $session['session_name']; ?></td>
		<td><?php echo $session['start_time']; ?></td>
		<td><?php echo $session['end_time']; ?></td>
		<td><?php foreach($hosts as $host) { ?>
			  <img src="<?php echo $host['picture']; ?>" width="30px"/>
				<a href="<?php echo $host['entry_url']; ?>"><?php echo $host['display_name']; ?>
				</a>
				<br/>
			<?php } ?>
		</td>
		<td><?php foreach($guests as $guest) { ?>
				<a href="<?php echo $guest['entry_url']; ?>"><?php echo $guest['display_name']; ?>
				</a>
			<?php } ?>
		</td>
		<td>
			<?php
			$session_time = new \DateTime($session['start_time']);
			$now = new \DateTime();
			if ($session_time > $now) { ?>
				<a href="#" onclick="delete_session('<?php echo $session['session_id']; ?>');"><?php echo __(
'Cancel') ?></a></td>
			<?php } ?>
		</td>
	</tr>
	<?php
}


function cvu_participant_add( $post, $options ) {
	// Initiate the API
	$coviu = new Coviu( $options->api_key, $options->api_key_secret );

	// Create a new participant
	$participant = create_participant( $coviu,
																			 array('ref' => $post['ref'],
																						'name' => $post['name'],
																					 'email' => $post['email']
																			 ) );
}

function cvu_session_add( $post, $options ) {
	// Recover coviu
	$coviu = new Coviu($options->api_key, $options->api_key_secret);

	// created date-time objects
	$start = $post['date'] . ' ' . $post['start'];
	$end = $post['date'] . ' ' . $post['end'];
	$startObj = (new \DateTime($start));
	$endObj = (new \DateTime($end));

	// check dates
	if ($endObj <= $startObj) {
		?><div class="error"><p><strong><?php echo __("Error: Can't create a session that starts after it ends.", 'coviu-video-calls'); ?></strong></p></div><?php
		return;
	}
	if ($startObj <= (new \DateTime(now))) {
		?><div class="error"><p><strong><?php echo __("Error: Can't create a session in the past.", 'coviu-video-calls'); ?></strong></p></div><?php
		return;
	}

	// add the session
	$session = array(
	  'session_name' => $post['name'],
	  'start_time' => $startObj->format(\DateTime::ATOM),
	  'end_time' => $endObj->format(\DateTime::ATOM),
	  'picture' => 'http://www.fillmurray.com/200/300'
	);

	$session = $coviu->sessions->createSession($session);
}

function cvu_session_delete( $session_id, $options ) {
	// Recover coviu
	$coviu = new Coviu($options->api_key, $options->api_key_secret);

	// delete the session
	$deleted = $coviu->sessions->deleteSession( $session_id );

	// notify if deleted
	if ($deleted) {
		?><div class="updated"><p><strong><?php printf(__("Deleted session %s.", "Deleted session %s.", $session_id, 'coviu-video-calls'), $session_id); ?></strong></p></div><?php
	} else {
		?><div class="error"><p><strong><?php echo __("Can't delete a session that doesn't exist.", 'coviu-video-calls'); ?></strong></p></div><?php
	}
}

function prettyprint($var) {
	print '<pre>'; print_r($var); print '</pre>';
}
