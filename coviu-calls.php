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
	$end_time = wp_get_datetime_now()->add(new DateInterval('PT1H'));

	?>
	<script type="text/javascript">
		jQuery(document).ready(function($){
			jQuery('#datepicker').datepicker({
				dateFormat: "dd M yy",
			});
		});
	</script>

	<form id="add_session" method="post" action="<?php echo $actionurl; ?>">
		<?php wp_nonce_field( 'cvu_options', 'cvu_options_security' ); ?>
		<input type="hidden" name="coviu[action]" value="add_session" />

		<p>
			<?php _e('Description:', 'coviu-video-calls'); ?>
			<input type="text" name="coviu[name]" value="Description of Appointment" size="40"/>
		</p>
		<p>
			<?php _e('Date:', 'coviu-video-calls'); ?>
			<input id="datepicker" type="text" name="coviu[date]" value="<?php echo current_time('d M Y'); ?>" />
		</p>
		<p>
			<?php _e('Start time:', 'coviu-video-calls'); ?>
			<input type="time" name="coviu[start]" value="<?php echo current_time('H:i'); ?>" />
		</p>
		<p>
			<?php _e('End time:', 'coviu-video-calls'); ?>
			<input type="time" name="coviu[end]" value="<?php echo $end_time->format('H:i'); ?>" />
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

			date_default_timezone_set('GMT');
			foreach ($sessions as $key => $session) {
				$start_time = new DateTime($session['start_time']);
				$end_time   = new DateTime($session['end_time']);

				$sessions[$key]['start_time'] = $start_time->setTimezone(wp_get_datetimezone());
				$sessions[$key]['end_time']   = $end_time->setTimezone(wp_get_datetimezone());
			}

			function cmp_by_time($session1, $session2) {
				return $session1['start_time'] < $session2['start_time'];
			}
			usort($sessions, 'cmp_by_time');

			$upcoming_split_index = 0;
			$now = wp_get_datetime_now();
			foreach ($sessions as $session) {
				if ($now >= $session['start_time']) break;

				$upcoming_split_index++;
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
				<th>Date</th>
				<th>Start</th>
				<th>End</th>
				<th>Host</th>
				<th>Guest</th>
				<?php if (strpos($title, 'Upcoming') !== false ) { ?>
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
	$now = wp_get_datetime_now();

	?>
	<tr>
		<td><?php echo substr($session['session_id'], 0, 5). " ... "; ?></td>
		<td><?php echo $session['session_name']; ?></td>
		<td><?php echo $session['start_time']->format('d-M-Y'); ?></td>
		<td><?php echo $session['start_time']->format('H:i'); ?></td>
		<td><?php echo $session['end_time']->format('H:i'); ?></td>
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
		$session_time = $session['start_time'];
		if ($session_time > $now) { ?>
			<td>
				<a href="#" class="thickbox_custom" data-role='host' data-sessionid="<?php echo $session['session_id']; ?>"><?php _e('Add Host', 'coviu-video-calls') ?></a><br/>
				<a href="#" class="thickbox_custom" data-role='guest' data-sessionid="<?php echo $session['session_id']; ?>"><?php _e('Add Guest', 'coviu-video-calls') ?></a><br/>
				<a href="#" onclick="delete_session('<?php echo $session['session_id']; ?>');"><?php echo __(
'Cancel') ?></a></td>
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
	$added = $coviu->sessions->addParticipant ($post['session_id'], $participant);
}

function cvu_session_add( $post, $options ) {
	// Recover coviu
	$coviu = new Coviu($options->api_key, $options->api_key_secret);

	// created date-time objects
	$start    = $post['date'] . ' ' . $post['start'];
	$end      = $post['date'] . ' ' . $post['end'];
	$startObj = wp_get_datetime($start);
	$endObj   = wp_get_datetime($end);

	// check dates
	if ($endObj <= $startObj) {
		?><div class="error"><p><strong><?php echo __("Error: Can't create an Appointment that starts after it ends.", 'coviu-video-calls'); ?></strong></p></div><?php
		return;
	}
	if ($startObj <= wp_get_datetime_now()) {
		?><div class="error"><p><strong><?php echo __("Error: Can't create an Appointment in the past.", 'coviu-video-calls'); ?></strong></p></div><?php
		return;
	}

	// add the session
	$session = array(
		'session_name' => $post['name'],
		'start_time' => $startObj->format(DateTime::ATOM),
		'end_time' => $endObj->format(DateTime::ATOM),
		// 'picture' => 'http://www.fillmurray.com/200/300',
	);

	try {
		$session = $coviu->sessions->createSession($session);
	} catch (\Exception $e) {
		?><div class="error"><p><strong><?php echo $e->getMessage(); ?></strong></p></div><?php
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
		?><div class="error"><p><strong><?php echo $e->getMessage(); ?></strong></p></div><?php
		return;
	}

	// notify if deleted
	if ($deleted) {
		?><div class="updated"><p><strong><?php printf(__("Deleted session %s.", "Deleted session %s.", $session_id, 'coviu-video-calls'), $session_id); ?></strong></p></div><?php
	} else {
		?><div class="error"><p><strong><?php echo __("Can't delete an Appointment that doesn't exist.", 'coviu-video-calls'); ?></strong></p></div><?php
	}
}

function prettyprint($var) {
	print '<pre>'; print_r($var); print '</pre>';
}

// Wordpress is an absolute mess when it comes to time handling

function wp_get_datetime_now() {
	return wp_get_datetime(current_time('Y-m-d H:i'));
}

function wp_get_datetime($time) {
	return new DateTime($time, wp_get_datetimezone());
}

function wp_format_datetime($datetime) {
	return $datetime->format(get_option('date_format') + ' ' + get_option('time_format'));
}

function wp_get_datetimezone() {
	return new DateTimeZone(wp_get_timezone_string());
}

function wp_get_timezone_string() {
	// if site timezone string exists, return it
	if ( $timezone = get_option( 'timezone_string' ) ) {
		return $timezone;
	}

	// get UTC offset, if it isn't set then return UTC
	if ( 0 === ( $utc_offset = get_option( 'gmt_offset', 0 ) ) ) {
		return 'UTC';
	}

	// adjust UTC offset from hours to seconds
	$utc_offset *= 3600;

	// attempt to guess the timezone string from the UTC offset
	if ( $timezone = timezone_name_from_abbr( '', $utc_offset, 0 ) ) {
		return $timezone;
	}

	// last try, guess timezone string manually
	$is_dst = date( 'I' );

	foreach ( timezone_abbreviations_list() as $abbr ) {
		foreach ( $abbr as $city ) {
			if ( $city['dst'] == $is_dst && $city['offset'] == $utc_offset )
				return $city['timezone_id'];
		}
	}

	// fallback to UTC
	return 'UTC';
}
