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
global $wp_version;

// don't use autoloader for WP4.6 compatibility; instead include specific files
if (substr_compare($wp_version, "4.6.1", 0, 3) !== 0) {
	require_once __DIR__.'/vendor/autoload.php';
} else {
	require_once __DIR__.'/vendor/coviu/coviu-sdk/src/Authenticator.php';
	require_once __DIR__.'/vendor/coviu/coviu-sdk/src/Coviu.php';
	require_once __DIR__.'/vendor/coviu/coviu-sdk/src/HttpException.php';
	require_once __DIR__.'/vendor/coviu/coviu-sdk/src/OAuth2Client.php';
	require_once __DIR__.'/vendor/coviu/coviu-sdk/src/OAuth2ClientException.php';
	require_once __DIR__.'/vendor/coviu/coviu-sdk/src/Request.php';
	require_once __DIR__.'/vendor/coviu/coviu-sdk/src/SessionApi.php';
}

use coviu\Api\Coviu;

/// ***  Set up and remove options for plugin *** ///

register_activation_hook( __FILE__, 'cvu_setup_options' );
function cvu_setup_options() {
	$options = new stdClass();
	$options->api_key = '';
	$options->api_key_secret = '';
	$options->embed_participant_pages = false;

	add_option('coviu-video-calls', $options);
}

register_deactivation_hook( __FILE__, 'cvu_teardown_options' );
function cvu_teardown_options() {
	delete_option('coviu-video-calls');
}

add_action( 'init', 'create_post_type' );
function create_post_type() {
	register_post_type( 'cvu_session',
		array(
			'labels' => array(
				'name' => __( 'Coviu Sessions' ),
				'singular_name' => __( 'Coviu Session' )
			),
			'public' => true,
			'exclude_from_search' => true,
			'show_in_menu' => false,
			'show_in_nav_menus' => false,
			'has_archive' => false,
			'rewrite' => false,
			'can_export' => false
		)
	);
}

add_filter( 'posttype_rewrite_rules', 'cvu_add_permastruct' );
function cvu_add_permastruct( $rules ) {
    $struct = '/%posttype%/%postname%/';

    global $wp_rewrite;
    $rules = $wp_rewrite->generate_rewrite_rules(
        $struct,
        EP_PERMALINK,
        false,
        true,
        true,
        false,
        true
    );

    return $rules;
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
	add_menu_page($title, 'Appointments', 'read', 'coviu-appointments-menu', 'cvu_appointments_page', plugins_url('coviu-video-calls/images/icon.png'), 30);
}

function cvu_register_admin_scripts() {
	wp_enqueue_style( 'jquery-ui-datepicker' , '//ajax.googleapis.com/ajax/libs/jqueryui/1.10.4/themes/smoothness/jquery-ui.css');
	wp_enqueue_script( 'jquery-ui-datepicker' );
}

function cvu_appointments_page() {
	if ( !current_user_can( 'read' ) )  {
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
			$actions = array(
				'add_session'    => 'cvu_session_add',
				'delete_session' => 'cvu_session_delete',
				'add_guest'      => 'cvu_guest_add',
				'delete_guest'   => 'cvu_guest_delete',
				'add_host'       => 'cvu_host_add',
				'delete_host'    => 'cvu_host_delete',
			);

			$action = $actions[$_POST['coviu']['action']];

			$action($_POST['coviu'], $options);
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
		} else {
			?>
			<h2><a href="options-general.php?page=coviu-video-calls%2Fcoviu-calls.php">Start by setting up the Coviu API keys</a></h2>
			<p>After that, you will be able to create and list appointments here.</p>
			<?php
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

		} elseif ($_POST['coviu']['action'] == 'settings') {
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
				$options->embed_participant_pages = $_POST['coviu']['embed_participant_pages'];
				update_option('coviu-video-calls', $options);

				?>
				<div class="updated">
					<p><strong><?php echo __('Stored settings.', 'coviu-video-calls'); ?></strong></p>
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

		<p>
			To use Coviu Video Calls, you need to sign up for a <a href="https://coviu.com/checkout/team?plan-type=api-plan" target="_blank">developer account</a> and get yourself credentials for accessing the API.
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
		<input type="hidden" name="coviu[action]" value="settings" />

		<h3><?php _e('Credentials', 'coviu-video-calls'); ?></h3>
		<p>
			<?php _e('API Key:', 'coviu-video-calls'); ?>
			<input type="text" name="coviu[api_key]" value="<?php echo $options->api_key ?>"/>
		</p>
		<p>
			<?php _e('Password:', 'coviu-video-calls'); ?>
			<input type="text" name="coviu[api_key_secret]" value="<?php echo $options->api_key_secret ?>"/>
		</p>
		<h3><?php _e('Experimental', 'coviu-video-calls'); ?></h3>
		<p>
			<?php _e('Embed participant pages:', 'coviu-video-calls'); ?>
			<input type="checkbox" name="coviu[embed_participant_pages]" value="true" <?php if ($options->embed_participant_pages) echo 'checked'; ?>/>
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
			round_up_to_quater_hour(now);
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

				// Gotta convert between timezones manually,
				// because Javascript sure isn't going to do it
				var start = new Date(jQuery('#start_time').val());
				var end = new Date(jQuery('#end_time').val());
				start_time.val(local_to_utc(start).toISOString());
				end_time.val(local_to_utc(end).toISOString());
			});
		});

		function round_up_to_quater_hour(date) {
			var minutes = Math.ceil(date.getMinutes() / 15) * 15;
			if (minutes == 60) {
				minutes = 0;
				date.setHours(date.getHours() + 1);
			}
			date.setMinutes(minutes);
		}

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
	// for overlays
	add_thickbox();

	?>
	<script type="text/javascript">
		// set up thickbox function handling
		jQuery(document).ready(function() {
			jQuery('.copy_link').click(function() {
				copyTextToClipboard(jQuery(this).data('link'));
			});

			jQuery('.thickbox_custom').click(function() {
				// get params for thickbox form
				var role = jQuery(this).data('role');
				var session_id = jQuery(this).data('sessionid');
				var id = role + '_form';

				// set params in form
				jQuery('#' + id + ' input#session_id').val(session_id);
				if (role == 'host') {
					jQuery('#' + id + ' input[type="submit"]').val("<?php _e('Add host', 'coviu-video-calls'); ?>");
				} else {
					jQuery('#' + id + ' input[type="submit"]').val("<?php _e('Add guest', 'coviu-video-calls'); ?>");
				}

				// render thickbox
				tb_show('Add ' + role + ' to Appointment', '#TB_inline?height=170&width=400&inlineId=' + id, false);
				this.blur();
				return false;
			});
		});

		function copyTextToClipboard(text) {
			var textArea = jQuery('<textarea></textarea>');
			jQuery(document.body).append(textArea);

			// Make sure we can 'click' the text area
			textArea.css('position', 'fixed');
			textArea.css('top', 0);
			textArea.css('left', 0);

			// Make it as invisible as possible
			textArea.css('width', '2em');
			textArea.css('height', '2em');
			textArea.css('padding', 0);
			textArea.css('border', 'none');
			textArea.css('outline', 'none');
			textArea.css('boxShadow', 'none');
			textArea.css('background', 'transparent');

			textArea.val(text);
			textArea.select();

			try {
				var succeded = document.execCommand('copy');
				var msg = succeded ? 'successful' : 'unsuccessful';
				console.log('Copying text command was ' + msg);
			} finally {
				textArea.remove();
			}
		}

		function delete_session(session_id) {
			jQuery('#session_id').val(session_id);
			jQuery('#submit_action').val('delete_session');
			var confirmtext = <?php echo '"'. sprintf(__('Are you sure you want to remove Appointment %s?', 'coviu-video-calls'), '"+ session_id +"') .'"'; ?>;
			if (!confirm(confirmtext)) {
					return false;
			}
			jQuery('#edit_session').submit();
		}

		function delete_guest(guest_id) {
			jQuery("#add_guest input[name='coviu[action]']").val('delete_guest');
			jQuery("#add_guest input[name='coviu[guest_id]']").val(guest_id);
			var confirmtext = "Are you sure you want to remove Guest '" + guest_id + "'";
			if (!confirm(confirmtext)) return false;
			jQuery('#add_guest').submit();
		}

		function delete_host(host_id) {
			jQuery("#add_host input[name='coviu[action]']").val('delete_host');
			jQuery("#add_host input[name='coviu[host_id]']").val(host_id);
			var confirmtext = "Are you sure you want to remove Host '" + host_id + "'";
			if (!confirm(confirmtext)) return false;
			jQuery('#add_host').submit();
		}

		// Convert datetimes to local
		jQuery(document).ready(function() {
			jQuery('.datetime').each(function(i, obj) {
				obj = jQuery(obj);
				var date = new Date(obj.text());
				var date_options = { day: 'numeric', month: 'numeric', year: 'numeric'};
				var time_options = { hour: 'numeric', minute: 'numeric'};
				obj.html(date.toLocaleDateString(navigator.languages[0], date_options) + "<br/>" + date.toLocaleTimeString(navigator.languages[0], time_options));
			});
		});
	</script>

	<form id="edit_session" method="post" action="<?php echo $actionurl; ?>">
		<?php wp_nonce_field( 'cvu_options', 'cvu_options_security' ); ?>
		<input type="hidden" name="coviu[action]" id="submit_action"/>
		<input type="hidden" name="coviu[session_id]" id="session_id"/>
	</form>

	<style>
		.tooltip img {
			cursor:pointer;
			border: 1px solid transparent;
		}
		.tooltip img:active {
			border: 1px solid grey;
		}
		.tooltip .tooltiptext {
			visibility: hidden;
			width: 120px;
			background-color: black;
			color: #fff;
			text-align: center;
			padding: 5px 0;
			border-radius: 6px;
			position: absolute;
			z-index: 1;
		}
		.tooltip:hover .tooltiptext {
			visibility: visible;
		}
		.tooltip .tooltiptext::after {
			content: " ";
			position: absolute;
			border-style: solid;
			border-color: transparent transparent black transparent;
		}
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
		.cvu_list tbody tr td.center {
			text-align: center;
		}
	</style>

	<?php
		// Recover coviu
		$coviu = new Coviu($options->api_key, $options->api_key_secret);

		$now = new DateTime();

		$params = array(
			'page_size' => 10,
		);
		if (!current_user_can( 'edit_posts' )) {
			$params = array_merge($params, array(
				'state' => wp_get_current_user()->ID,
			));
		}

		$active_sessions = cvu_get_active_sessions($coviu, $now, $params);
		if (count($active_sessions) > 0) {
			// reverse sort order to get current ones first
			$active_sessions = array_reverse($active_sessions);
			cvu_sessions_table($options, 'Active Appointments', $active_sessions, true);
		}

		list($upcoming_sessions, $more) = cvu_get_upcoming_sessions($coviu, $now, $params);
		if (count($upcoming_sessions) > 0) {
			cvu_sessions_table($options, 'Upcoming Appointments', $upcoming_sessions, true);
			cvu_pagination('upcoming_page', $more);
		}

		list($past_sessions, $more) = cvu_get_past_sessions($coviu, $now, $params, false);
		if (count($past_sessions) > 0) {
			cvu_sessions_table($options, 'Past Appointments', $past_sessions, false);
			cvu_pagination('past_page', $more);
		}
	?>

	<!-- The overlay thickbox form -->
	<div id="host_form" style="display:none;">
		<p>
			<form id="add_host" method="post" action="<?php echo $actionurl; ?>">
				<?php wp_nonce_field( 'cvu_options', 'cvu_options_security' ); ?>
				<input type="hidden" name="coviu[action]" value="add_host"/>
				<input type="hidden" name="coviu[host_id]"/>
				<input type="hidden" name="coviu[session_id]" id="session_id" value=""/>

				<p>
					<?php _e('User:', 'coviu-video-calls'); ?>
					<select name="coviu[user_id]">
						<?php foreach (get_users() as $user) { ?>
						<option value="<?php echo $user->get('ID'); ?>"> <?php echo $user->get('display_name'); ?> </option>
						<?php } ?>
					</select>
				</p>
				<p>
					<input name="Submit" type="submit" class="button-primary" value="" />
				</p>
			</form>
		</p>
	</div>
	<div id="guest_form" style="display:none;">
		<p>
			<form id="add_guest" method="post" action="<?php echo $actionurl; ?>">
				<?php wp_nonce_field( 'cvu_options', 'cvu_options_security' ); ?>
				<input type="hidden" name="coviu[action]" value="add_guest"/>
				<input type="hidden" name="coviu[guest_id]"/>
				<input type="hidden" name="coviu[session_id]" id="session_id" value=""/>

				<p>
					<?php _e('Name:', 'coviu-video-calls'); ?>
					<input type="text" name="coviu[participant_name]"/>
				</p>
				<p>
					<input name="Submit" type="submit" class="button-primary" value="" />
				</p>
			</form>
		</p>
	</div>
	<?php
}

function cvu_session_table_header($title, $allow_actions) {
	?>
		<thead>
			<tr>
				<th>ID</th>
				<th>Description</th>
				<th>Start</th>
				<th>End</th>
				<th>Host</th>
				<th>Guest</th>
				<?php if ($allow_actions) { ?>
					<th>Action</th>
				<?php } ?>
			</tr>
		</thead>
	<?php
}

function cvu_sessions_table($options, $title, $sessions, $allow_actions) {
	?>
		<h2> <?php echo $title; ?> </h2>
		<table class="cvu_list">
			<?php cvu_session_table_header($title, $allow_actions); ?>
			<tbody> <?php

				foreach ($sessions as $session) {
					cvu_session_display($options, $session, $allow_actions);
				}

			?> </tbody>
		</table>
	<?php
}

function cvu_session_display($options, $session, $allow_actions) {
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
	$end_time = $session['end_time'];

	?>
	<tr>
		<td><?php echo substr($session['session_id'], 0, 5). " ... "; ?></td>
		<td><?php echo $session['session_name']; ?></td>
		<td class="datetime center"><?php echo $session['start_time']->format(DateTime::ATOM); ?></td>
		<td class="datetime center"><?php echo $session['end_time']->format(DateTime::ATOM); ?></td>
		<td>
			<?php foreach($hosts as $host) { ?>
				<?php $url = cvu_embed_participant_page($options, $host); ?>
				<img src="<?php echo $host['picture']; ?>" width="30px"/>
				<span class='copy_link tooltip' data-link="<?php echo $url; ?>">
					<img src="http://c.dryicons.com/images/icon_sets/symbolize_icons_set/png/16x16/link.png">
					<span class="tooltiptext">Copy Link</span>
				</span>
				<a href="<?php echo $url; ?>">
					<?php echo $host['display_name']; ?>
				</a>
				<?php if ($end_time >= $now) { ?>
					<span class='tooltip' onclick="delete_host('<?php echo $host['participant_id']; ?>');">
						<img src="http://individual.icons-land.com/IconsPreview/BaseSoftware/PNG/16x16/DeleteRed.png">
						<span class="tooltiptext">Remove</span>
					</span>
				<?php } ?>
				<br/>
			<?php } ?>
		</td>
		<td>
			<?php foreach($guests as $guest) { ?>
				<?php $url = cvu_embed_participant_page($options, $guest); ?>
				<span class='copy_link tooltip' data-link="<?php echo $url; ?>	">
					<img src="http://c.dryicons.com/images/icon_sets/symbolize_icons_set/png/16x16/link.png">
					<span class="tooltiptext">Copy Link</span>
				</span>
				<a href="<?php echo $url; ?>">
					<?php echo $guest['display_name']; ?>
				</a>
				<?php if ($end_time >= $now) { ?>
					<span class='tooltip' onclick="delete_guest('<?php echo $guest['participant_id']; ?>');">
						<img src="http://individual.icons-land.com/IconsPreview/BaseSoftware/PNG/16x16/DeleteRed.png">
						<span class="tooltiptext">Remove</span>
					</span>
				<?php } ?>
				<br/>
			<?php } ?>
		</td>
		<?php if ($allow_actions) { ?>
			<td class="center">
				<a href="#" class="thickbox_custom" data-role='host' data-sessionid="<?php echo $session['session_id']; ?>"><?php _e('Add Host', 'coviu-video-calls') ?></a><br/>
				<a href="#" class="thickbox_custom" data-role='guest' data-sessionid="<?php echo $session['session_id']; ?>"><?php _e('Add Guest', 'coviu-video-calls') ?></a><br/>
				<!-- active sessions cannot be deleted -->
				<?php if ($session['start_time'] >= $now) { ?>
					<a href="#" onclick="delete_session('<?php echo $session['session_id']; ?>');">
						<?php echo __('Cancel') ?>
					</a>
				<?php } ?>
			</td>
		<?php } ?>
	</tr>
	<?php
}

function cvu_pagination($page_number_name, $more = true) {
	$page = (int)get_query_arg($page_number_name, 0);

	$next_url     = add_query_arg($page_number_name, $page + 1);
	$previous_url = add_query_arg($page_number_name, $page - 1);

	?>
		<span>
			<?php if ($page > 0) { ?>
				 <a href="<?php echo $previous_url; ?>" class="button-primary">
						Previous Page
				</a>
			<?php } ?>

			<?php if ($more) { ?>
				<a href="<?php echo $next_url; ?>" class="button-primary">
						Next Page
				</a>
			<?php } ?>
		</span>
	<?php
}

function cvu_embed_participant_page($options, $participant) {
	if (!$options->embed_participant_pages) {
		return $participant['entry_url'];
	}

	$post = get_session_post_by_name($participant['participant_id']);

	if ($post == null) {
		$content = '<iframe src="' . $participant['entry_url'] . '" style="width: 100%; border: none"></iframe>';
		$params = array(
			'post_content' => $content,
			'post_name' => $participant['participant_id'],
			'guid' => $participant['participant_id'],
			'post_type' => 'cvu_session',
			'post_status' => 'publish',
		);
		$id = wp_insert_post($params);

		$post = get_post($id);
	}

	return '/?cvu_session=' . $post->post_name;
}

function cvu_guest_add( $post, $options ) {
	// put together a participant
	$participant = array(
		'display_name' => $post['participant_name'],
		'role'         => 'guest',
		// 'state'        => 'test-state',
	);

	$added = cvu_participant_add( $options, $post['session_id'], $participant );
}

function cvu_guest_delete( $post, $options ) {
	cvu_participant_delete( $options, $post['guest_id'] );
}

function cvu_host_add( $post, $options ) {
	$user = get_user_by('id', $post['user_id']);
	if (!$user) {
		error(__("Can't add Host with non-existent user.", 'coviu-video-calls'));
		exit;
	}

	$picture = get_avatar_url($user->get('ID'));

	// put together a participant
	$participant = array(
		'display_name' => $user->get('display_name'),
		'role'         => 'host',
		'picture'      => $picture,
		'state'        => (string)($user->get('ID')),
	);

	$added = cvu_participant_add( $options, $post['session_id'], $participant );
}

function cvu_host_delete( $post, $options ) {
	cvu_participant_delete( $options, $post['host_id'] );
}

function cvu_participant_add( $options, $session_id, $participant ) {
	// Recover coviu
	$coviu = new Coviu($options->api_key, $options->api_key_secret);

	// participant
	try {
		return $coviu->sessions->addParticipant ($session_id, $participant);
	} catch (\Exception $e) {
		error( $e->getMessage() );
		return;
	}
}

function cvu_participant_delete( $options, $participant_id ) {
	// Recover coviu
	$coviu = new Coviu($options->api_key, $options->api_key_secret);

	try {
		return $coviu->sessions->deleteParticipant($participant_id);
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

	if (!current_user_can( 'edit_posts' )) {
		$post = array(
			'user_id'    => wp_get_current_user()->ID,
			'session_id' => $session['session_id'],
		);
		cvu_host_add($post, $options);
	}
}

function cvu_session_delete( $post, $options ) {
	$session_id = $post['session_id'];

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

function cvu_get_active_sessions($coviu, $now, $params = array()) {
	// Sessions are a maximum of 12 hours long
	// So we can grab sessions within a 12 hour radius to get all currently active ones
	$interval = new DateInterval("PT12H");
	$start_time = clone $now; // clone is a dumb implementation, it only works like this.
	$start_time = $start_time->sub($interval);
	$end_time = clone $now;
	$end_time = $end_time->add($interval);

	$params = array_merge($params, array(
		'start_time' => $start_time->format(DateTime::ATOM),
		'end_time'   => $end_time->format(DateTime::ATOM),
		'page_size'  => NULL, // unset this
	));

	list($sessions, $dummy) = cvu_get_sessions($coviu, $params);

	foreach ($sessions as $key => $session) {
		if ( $now < $session['start_time'] || $now > $session['end_time']) {
			unset( $sessions[$key] );
		}
	}

	array_values($sessions); // reindex

	return $sessions;
}

function cvu_get_upcoming_sessions($coviu, $now, $params = array()) {
	$params = array_merge($params, array(
		'start_time' => $now->format(DateTime::ATOM),
		'page'       => (int)get_query_arg('upcoming_page', 0),
	));

	return cvu_get_sessions($coviu, $params);
}

function cvu_get_past_sessions($coviu, $now, $params = array()) {
	$params = array_merge($params, array(
		'order'    => 'reverse',
		'end_time' => $now->format(DateTime::ATOM),
		'page'     => (int)get_query_arg('past_page', 0),
	));

	return cvu_get_sessions($coviu, $params);
}

function cvu_get_sessions($coviu, $params) {
	$result = $coviu->sessions->getSessions($params);

	$sessions = $result['content'];
	$more     = $result['more'];

	foreach ($sessions as $key => $session) {
		$sessions[$key]['start_time'] = new DateTime($session['start_time']);
		$sessions[$key]['end_time']   = new DateTime($session['end_time']);
	}

	return array($sessions, $more);
}

function get_query_arg($name, $default = NULL) {
	if (isset($_GET[$name])) return $_GET[$name];
	return $default;
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

function get_session_post_by_name( $name ){
	$params = array(
		'name' => $name,
		'post_type' => 'cvu_session',
		'post_status' => 'any',
		'posts_per_page' => 1,
	);
	$posts = get_posts($params);

	if (count($posts) == 1) {
		return null;
	}
	return $posts[0];
}
