<?php
/*
 * Plugin Name: Coviu Video Calls
 * Plugin URI: http://wordpress.org/extend/plugins/coviu-video-calls/
 * Description: Add Coviu video calling to your Website.
 * Author: Silvia Pfeiffer, NICTA, Coviu
 * Version: 0.2
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
add_action( 'admin_menu', 'cvu_appointments_menu' );
add_action( 'admin_enqueue_scripts', 'cvu_register_admin_scripts' );

function cvu_admin_menu() {
	add_options_page(__('Coviu Video Calls Settings', 'coviu-video-calls'), __('Coviu Calls', 'coviu-video-calls'), 'manage_options', __FILE__, 'cvu_settings_page');
}

function cvu_appointments_menu() {
	$title = __('Coviu Appointments', 'coviu-appointments');
	add_menu_page($title, 'Appointments', 'manage_options', 'coviu-appointments-menu', 'cvu_appointments_page', plugins_url('coviu-video-calls/images/icon.png'), 30);
}

function cvu_register_admin_scripts() {
	wp_enqueue_style( 'jquery-ui-datepicker' , '//ajax.googleapis.com/ajax/libs/jqueryui/1.10.4/themes/smoothness/jquery-ui.css');
	wp_enqueue_script( 'jquery-ui-datepicker' );
}

function cvu_appointments_page() {
	if ( !current_user_can( 'manage_options' ) )  {
		wp_die( __( 'You do not have sufficient permissions to access this page.' ) );
	}

	// retrieve stored options
	$options = get_option('coviu-video-calls');

	// Always use GMT internally (matches with coviu API)
	date_default_timezone_set('GMT');

	// process form data
	if( isset($_POST['coviu']) ) {
		// nonce check
		if (! isset( $_POST['cvu_options_security'] ) ||
			! wp_verify_nonce( $_POST['cvu_options_security'], 'cvu_options')) {
			print 'Sorry, your nonce did not verify.';
			exit;

		} else {
			if ($_POST['coviu']['action'] == 'delete_session') {

				cvu_session_delete( $_POST['coviu']['session_id'], $options );

			} elseif ($_POST['coviu']['action'] == 'add_session') {

				cvu_session_add( $_POST['coviu'], $options );

			} elseif ($_POST['coviu']['action'] == 'add_participant') {

				cvu_participant_add( $_POST['coviu'], $options );

			}
		}
	}

	?>
	<div class="wrap">
		<h2><?php _e('Coviu Appointments', 'coviu-video-calls'); ?></h2>

		<!-- DISPLAY SESSION LIST -->
		<?php
		if ($options->api_key != '' && $options->api_key_secret != '') {
			?>

			<h2><?php _e('Add a Video Appointment', 'coviu-video-calls'); ?></h2>
			<?php
			cvu_session_form( $_SERVER["REQUEST_URI"] );
			cvu_sessions_display( $_SERVER["REQUEST_URI"], $options );
		}
		?>
	</div>
	<?php
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

		} elseif ($_POST['coviu']['action'] == 'credentials') {
			// clean up entered data from surplus white space
			$_POST['coviu']['api_key']        = trim(sanitize_text_field($_POST['coviu']['api_key']));
			$_POST['coviu']['api_key_secret'] = trim(sanitize_text_field($_POST['coviu']['api_key_secret']));

			// check if credentials were provided
			if ( !$_POST['coviu']['api_key'] || !$_POST['coviu']['api_key_secret'] ) {
				error( __('Missing API credentials.', 'coviu-video-calls') );
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
			<input name="Submit" type="submit" class="button-primary" value="<?php _e('Update Credentials', 'coviu-video-calls'); ?>" />
		</p>
	</form>
	<?php
}

function cvu_session_form( $actionurl ) {
	?>
	<script type="text/javascript">
		jQuery(document).ready(function($){
			// Set local times to now, now + 1hour
			var now = new Date();
			jQuery('#start_time').val(now.toLocalISOString());
			now.setHours(now.getHours() + 1);
			jQuery('#end_time').val(now.toLocalISOString());

			jQuery('#add_session').submit(function() {
				// Convert local times to UTC before submitting
				var start_time = jQuery('#start_time');
				var end_time = jQuery('#end_time');
				// Goota change the field type to circumvent input validation
				start_time.attr('type', 'text');
				end_time.attr('type', 'text');
				var millis = now.getTime() + (now.getTimezoneOffset() * 60000);
				var start = new Date(jQuery('#start_time').val());
				var end = new Date(jQuery('#end_time').val());
				start_time.val(local_to_utc(start).toISOString());
				end_time.val(local_to_utc(end).toISOString());
			});
		});

		function local_to_utc(date) {
			var time = date.getTime() + date.getTimezoneOffset() * 60000;
			var out = new Date();
			out.setTime(time);
			return out;
		}

		// Javascript is absolute garbage
		function pad(number) {
			if (number < 10) {
				return '0' + number;
			}
			return number;
		}

		Date.prototype.toLocalISOString = function() {
			return this.getFullYear() +
				'-' + pad(this.getMonth() + 1) +
				'-' + pad(this.getDate()) +
				'T' + pad(this.getHours()) +
				':' + pad(this.getMinutes());
		};
	</script>

	<form id="add_session" method="post" action="<?php echo $actionurl; ?>">
		<?php wp_nonce_field( 'cvu_options', 'cvu_options_security' ); ?>
		<input type="hidden" name="coviu[action]" value="add_session" />

		<p>
			<?php _e('Description:', 'coviu-video-calls'); ?>
			<input type="text" name="coviu[name]" value="Description of Appointment" size="40"/>
		</p>
		<p>
			<?php _e('Start:', 'coviu-video-calls'); ?>
			<input id="start_time" type="datetime-local" name="coviu[start_time]" />
		</p>
		<p>
			<?php _e('End:', 'coviu-video-calls'); ?>
			<input id="end_time" type="datetime-local" name="coviu[end_time]" />
		</p>
		<p class="submit">
			<input name="Submit" type="submit" class="button-primary" value="<?php _e('Add Appointment', 'coviu-video-calls'); ?>" />
		</p>
	</form>
	<?php
}

function cvu_sessions_display( $actionurl, $options ) {
	?>
	<script type="text/javascript">
		// set up thickbox function handling
		jQuery(document).ready(function() {
			jQuery('.thickbox_custom').click(function() {
				// get params for thickbox form
				var role = jQuery(this).data('role');
				var session_id = jQuery(this).data('sessionid');

				// set params in form
				jQuery('#participant_form input#role').val(role);
				jQuery('#participant_form input#session_id').val(session_id);
				if (role == 'host') {
					jQuery('#participant_form input#submit').val("<?php _e('Add host', 'coviu-video-calls'); ?>");
				} else {
					jQuery('#participant_form input#submit').val("<?php _e('Add guest', 'coviu-video-calls'); ?>");
				}

				// render thickbox
				tb_show('Add ' + role + ' to Appointment', '#TB_inline?height=170&width=400&inlineId=participant_form', false);
				this.blur();
				return false;
			});
		});

		function delete_session(session_id) {
			jQuery('#session_id').val(session_id);
			jQuery('#submit_action').val('delete_session');
			var confirmtext = <?php echo '"'. sprintf(__('Are you sure you want to remove Appointment %s?', 'coviu-video-calls'), '"+ session_id +"') .'"'; ?>;
			if (!confirm(confirmtext)) {
					return false;
			}
			jQuery('#edit_session').submit();
		}

		// Convert datetimes to local
		jQuery(document).ready(function() {
			jQuery('.datetime').each(function(i, obj) {
				obj = jQuery(obj);
				var date = new Date(obj.text());
				obj.text(date.toLocaleString());
			});
		});
	</script>

	<form id="edit_session" method="post" action="<?php echo $actionurl; ?>">
		<?php wp_nonce_field( 'cvu_options', 'cvu_options_security' ); ?>
		<input type="hidden" name="coviu[action]" id="submit_action"/>
		<input type="hidden" name="coviu[session_id]" id="session_id"/>

		<style>
			.cvu_list {
				min-width: 100%;
				overflow: scroll;
				white-space: nowrap;
			}
			.cvu_list tbody tr:nth-of-type(even) {background-color: white;}
			.cvu_list th {
				background-color:#0085ba;
				font-weight:bold;
				color:#fff;
				padding: 0 5px;
			}
			.cvu_list tbody tr td:nth-of-type(1) {font-weight: bold;}
			.cvu_list tbody tr td {
				text-align: center;
			}
		</style>

		<?php
			// for overlays
			add_thickbox();

			// Recover coviu
			$coviu = new Coviu($options->api_key, $options->api_key_secret);

			// Get the first page of sessions
			$sessions = $coviu->sessions->getSessions();
			$sessions = $sessions['content'];
			//var_dump($sessions);

			foreach ($sessions as $key => $session) {
				$sessions[$key]['start_time'] = new DateTime($session['start_time']);
				$sessions[$key]['end_time']   = new DateTime($session['end_time']);
			}

			function cmp_by_time($session1, $session2) {
				return $session1['start_time'] < $session2['start_time'];
			}
			// sort by start_time
			usort($sessions, 'cmp_by_time');

			$upcoming_split_index = 0;
			$active_sessions = [];
			$now = new DateTime();

			// remove current sesions from array
			foreach ($sessions as $key => $session) {
				if ( $now >= $session['start_time'] && $now <= $session['end_time']) {
					array_push( $active_sessions, $session );
					unset( $sessions[$key] );
				}
			}
			array_values($sessions);

			// determine split point in remaining sessions
			foreach ($sessions as $key => $session) {
				if ($now > $session['start_time']) break;
				$upcoming_split_index++;
			}


			if (count($active_sessions) > 0) {
				// reverse sort order to get current ones first
				$active_sessions = array_reverse($active_sessions);
				cvu_sessions_table('Active Appointments', $active_sessions);
			}

			$upcoming_sessions = array_slice($sessions, 0, $upcoming_split_index);
			if (count($upcoming_sessions) > 0) {
				// reverse sort order to get current ones first
				$upcoming_sessions = array_reverse($upcoming_sessions);
				cvu_sessions_table('Upcoming Appointments', $upcoming_sessions);
			}

			$past_sessions = array_slice($sessions, $upcoming_split_index);
			if (count($past_sessions) > 0) {
				cvu_sessions_table('Past Appointments', $past_sessions);
			}
		?>
		<div id="sessions">
		</div>
	</form>

	<!-- The overlay thickbox form -->
	<div id="participant_form" style="display:none;">
		<p>
			<form id="add_participant" method="post" action="<?php echo $actionurl; ?>">
				<?php wp_nonce_field( 'cvu_options', 'cvu_options_security' ); ?>
				<input type="hidden" name="coviu[action]" value="add_participant" />
				<input type="hidden" name="coviu[session_id]" id="session_id" value=""/>
				<input type="hidden" name="coviu[role]" id="role" value=""/>

				<p>
					<?php _e('Name:', 'coviu-video-calls'); ?>
					<input type="text" name="coviu[participant_name]"/>
				</p>
				<p>
					<input name="Submit" type="submit" class="button-primary" id="submit" value="" />
				</p>
			</form>
		</p>
	</div>
	<?php
}

function cvu_session_table_header($title) {
	?>
		<thead>
			<tr>
				<th>ID</th>
				<th>Description</th>
				<th>Start</th>
				<th>End</th>
				<th>Host</th>
				<th>Guest</th>
				<?php if (substr_compare($title, 'Past', 0, 4) !== 0) { ?>
					<th>Action</th>
				<?php } ?>
			</tr>
		</thead>
	<?php
}

function cvu_sessions_table($title, $sessions) {
	?>
		<h2> <?php echo $title; ?> </h2>
		<table class="cvu_list">
			<?php cvu_session_table_header($title); ?>
			<tbody> <?php

				foreach ($sessions as $session) {
					cvu_session_display($session);
				}

			?> </tbody>
		</table>
	<?php
}

function cvu_session_display($session) {
	$hosts = array();
	$guests = array();
	if (array_key_exists('participants', $session)) {
		foreach ($session['participants'] as $participant) {
			if ($participant['role'] == "HOST") {
				array_push($hosts, $participant);
			} else {
				array_push($guests, $participant);
			}
		}
	}
	$now = new DateTime();

	?>
	<tr>
		<td><?php echo substr($session['session_id'], 0, 5). " ... "; ?></td>
		<td><?php echo $session['session_name']; ?></td>
		<td class="datetime"><?php echo $session['start_time']->format(DateTime::ATOM); ?></td>
		<td class="datetime"><?php echo $session['end_time']->format(DateTime::ATOM); ?></td>
		<td>
			<?php foreach($hosts as $host) { ?>
				<img src="<?php echo $host['picture']; ?>" width="30px"/>
				<a href="<?php echo $host['entry_url']; ?>"><?php echo $host['display_name']; ?>
				</a>
				<br/>
			<?php } ?>
		</td>
		<td>
			<?php foreach($guests as $guest) { ?>
				<a href="<?php echo $guest['entry_url']; ?>"><?php echo $guest['display_name']; ?>
				</a>
				<br/>
			<?php } ?>
		</td>
		<?php
		$session_time = $session['end_time'];
		if ($session_time >= $now) { ?>
			<td>
				<a href="#" class="thickbox_custom" data-role='host' data-sessionid="<?php echo $session['session_id']; ?>"><?php _e('Add Host', 'coviu-video-calls') ?></a><br/>
				<a href="#" class="thickbox_custom" data-role='guest' data-sessionid="<?php echo $session['session_id']; ?>"><?php _e('Add Guest', 'coviu-video-calls') ?></a><br/>
				<?php // active sessions cannot be deleted
				if ($session['start_time'] >= $now) {
				?>
					<a href="#" onclick="delete_session('<?php echo $session['session_id']; ?>');">
						<?php echo __('Cancel') ?>
					</a>
				<?php } ?>
			</td>
		<?php } ?>
	</tr>
	<?php
}


function cvu_participant_add( $post, $options ) {
	// Recover coviu
	$coviu = new Coviu($options->api_key, $options->api_key_secret);

	// put together a participant
	$participant = array(
		'display_name' => $post['participant_name'],
		'role'         => $post['role'],
		'picture'      => 'http://fillmurray.com/200/300',
		'state'        => 'test-state'
	);

	// add a host or guest participant
	try {
		$added = $coviu->sessions->addParticipant ($post['session_id'], $participant);
	} catch (\Exception $e) {
		error( $e->getMessage() );
		return;
	}

}

function cvu_session_add( $post, $options ) {
	// Recover coviu
	$coviu = new Coviu($options->api_key, $options->api_key_secret);

	// created date-time objects
	$start_time = new DateTime($post['start_time']);
	$end_time   = new DateTime($post['end_time']);
	$now        = new DateTime();

	// check dates
	if ($end_time < $start_time) {
		error( __("Can't create an Appointment that starts after it ends.", 'coviu-video-calls'));
		return;
	}
	if ($start_time < $now) {
		error( __("Can't create an Appointment in the past.", 'coviu-video-calls'));
		return;
	}

	// add the session
	$session = array(
		'session_name' => $post['name'],
		'start_time' => $start_time->format(DateTime::ATOM),
		'end_time' => $end_time->format(DateTime::ATOM),
		// 'picture' => 'http://www.fillmurray.com/200/300',
	);

	try {
		$session = $coviu->sessions->createSession($session);
	} catch (\Exception $e) {
		error( $e->getMessage() );
		return;
	}
}

function cvu_session_delete( $session_id, $options ) {
	// Recover coviu
	$coviu = new Coviu($options->api_key, $options->api_key_secret);

	// delete the session
	try {
		$deleted = $coviu->sessions->deleteSession( $session_id );
	} catch (\Exception $e) {
		error( $e->getMessage() );
		return;
	}

	// notify if deleted
	if ($deleted) {
		?><div class="updated"><p><strong><?php printf(__("Deleted session %s.", "Deleted session %s.", $session_id, 'coviu-video-calls'), $session_id); ?></strong></p></div><?php
	} else {
		error( __("Can't delete an Appointment that doesn't exist.", 'coviu-video-calls') );
	}
}

function error($err_str) {
	?><div class="error">
		<p><strong><?php echo str_replace('\n', '<br/>', 'Error: '.$err_str); ?></strong></p>
	</div><?php
	return;
}

function prettyprint($var) {
	print '<pre>'; print_r($var); print '</pre>';
}
