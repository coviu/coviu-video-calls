<?php
/*
 * Plugin Name: Coviu Video Calls
 * Plugin URI: http://wordpress.org/extend/plugins/coviu-video-calls/
 * Description: Add Coviu video calling to your Website.
 * Author: Silvia Pfeiffer, NICTA, Coviu
 * Version: 0.5
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
	@version    0.5
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
	require_once __DIR__.'/vendor/coviu/coviu-sdk/src/UserApi.php';
}

use coviu\Api\Coviu;
use coviu\Api\OAuth2ClientException;

/// ***  Set up and remove options for plugin *** ///

register_activation_hook( __FILE__, 'cvu_setup_options' );
function cvu_setup_options() {
	$options = new stdClass();
	$options->api_key = '';
	$options->api_key_secret = '';
	$options->grant = null;
	$options->embed_participant_pages = false;
	$options->oauth_url = '';
	$options->require_oauth = false;
	$options->oauth_team = null;
	cvu_update_options($options);

	$theme_default_template = get_stylesheet_directory() . '/single.php';
	$theme_template = get_stylesheet_directory() . '/single-cvu_session.php';
	if (!file_exists($theme_template)) {
		copy($theme_default_template, $theme_template);
	}
}

register_deactivation_hook( __FILE__, 'cvu_teardown_options' );
function cvu_teardown_options() {
	delete_option('coviu-video-calls');
	cvu_delete_user_options();
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
	wp_enqueue_style( 'cvu-admin', plugins_url('coviu-video-calls/coviu-calls.css'));
}

function cvu_appointments_page() {
	if ( !current_user_can( 'read' ) )  {
		wp_die( __( 'You do not have sufficient permissions to access this page.' ) );
	}

	// retrieve stored options
	$options = cvu_get_options();

	$coviu = cvu_client($options);

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
				'logout'         => 'cvu_oauth_logout',
				'add_session'    => 'cvu_session_add',
				'delete_session' => 'cvu_session_delete',
				'add_guest'      => 'cvu_guest_add',
				'delete_guest'   => 'cvu_guest_delete',
				'add_host'       => 'cvu_host_add',
				'delete_host'    => 'cvu_host_delete',
			);

			$action = $actions[$_POST['coviu']['action']];

			$action($_POST['coviu'], $coviu, $options);
		}
	}
	?>
	<div class="wrap">
		<h2><?php _e('Appointments', 'coviu-video-calls'); ?></h2>

		<!-- DISPLAY SESSION LIST -->
		<?php

		if ($options->api_key == '' || $options->api_key_secret == '') {
			?>
			<h2><a href="options-general.php?page=coviu-video-calls%2Fcoviu-calls.php">Start by setting up the Coviu API keys</a></h2>
			<p>After that, you will be able to create and list appointments here.</p>
			<?php
		} else {
			$show_sessions = true;

			if ($options->require_oauth) {
				$show_sessions = cvu_oauth($coviu, $options);
			}

			if ($show_sessions) {
				?>
				<h2><?php _e('Add a Video Appointment', 'coviu-video-calls'); ?></h2>
				<?php
				cvu_session_form( $_SERVER["REQUEST_URI"] );
				cvu_sessions_display( $_SERVER["REQUEST_URI"], $coviu, $options );
			}
		}
		?>
	</div>
	<?php

	cvu_update_client($coviu, $options);
}

function cvu_settings_page() {
	if ( !current_user_can( 'manage_options' ) )  {
		wp_die( __( 'You do not have sufficient permissions to access this page.' ) );
	}

	// retrieve stored options
	$options = cvu_get_options();

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
				$api_key        = $_POST['coviu']['api_key'];
				$api_key_secret = $_POST['coviu']['api_key_secret'];

				// Changing the API key needs to reset authentication
				if ($api_key != $options->api_key || $api_key_secret != $options->api_key_secret) {
					$options->grant = null;
					$options->oauth_team = null;
					cvu_delete_user_options();
				}

				// updating credentials
				$options->api_key                 = $api_key;
				$options->api_key_secret          = $api_key_secret;
				$options->embed_participant_pages = isset($_POST['coviu']['embed_participant_pages']);
				$options->oauth_url               = $_POST['coviu']['oauth_url'];
				$options->require_oauth           = isset($_POST['coviu']['require_oauth']);
				cvu_update_options($options);

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
	<div class="wrap cvc-wrap">
		<h2><?php _e('Settings: Coviu Video Calls', 'coviu-video-calls'); ?></h2>

		<div class="postbox">
			<h2><span><?php esc_attr_e( 'About', 'coviu-video-calls' ); ?></span></h2>
			<p>
			<?php _e(
				'Coviu Video Calls allows you to add appointment bookings of live video calls to your Website.',
				'coviu-video-calls'
				); ?>
			</p>
			<p>
			<?php _e(
				'As a result, you get two types of links to a video room: those of an owner who enters the room without knocking, and those of a guest who has to knock.',
				'coviu-video-calls'
			); ?>
			</p>
			<p>
			<?php _e('More information:', 'coviu-video-calls'); ?>
			<a href="https://help.coviu.com/api-information">Coviu API</a>
			</p>
		</div>

		<!-- DISPLAY CREDENTIALS FORM -->
		<?php
			cvu_settings_form( $_SERVER["REQUEST_URI"], $options );
		?>
	</div>
	<?php

}

function cvu_settings_form( $actionurl, $options ) {
	?>
	<form id="credentials" method="post" action="<?php echo $actionurl; ?>">
		<?php wp_nonce_field( 'cvu_options', 'cvu_options_security' ); ?>
		<input type="hidden" name="coviu[action]" value="settings" />

		<div class="postbox">
			<h3><?php _e('Setup', 'coviu-video-calls'); ?></h3>
			<p>
				<?php esc_attr_e(
					'Sign up for a',
					'coviu-video-calls'
				); ?>
				<a href="https://coviu.com/checkout/team?plan-type=api-plan" target="_blank"><?php _e('developer account', 'coviu-video-calls'); ?></a>
				<?php esc_attr_e(
					'and create new credentials for accessing the Coviu API.',
					'coviu-video-calls'
				); ?>
			</p>
			<p>
				<?php _e('API Key:', 'coviu-video-calls'); ?>
				<input type="text" name="coviu[api_key]" value="<?php echo $options->api_key ?>"/>
			</p>
			<p>
				<?php _e('Password:', 'coviu-video-calls'); ?>
				<input type="text" name="coviu[api_key_secret]" value="<?php echo $options->api_key_secret ?>"/>
			</p>
		</div>

		<div class="postbox">
			<h2><?php _e('Customisation', 'coviu-video-calls'); ?></h2>
			<p>
				<p>
				<?php _e(
					'Your appointments can either link into a full-screen Coviu room, or you can have Coviu rooms rendered inside a Wordpress page with your branding around it. Note that screensharing will not work when video calls are inside Wordpress pages.',
					'coviu-video-calls'
					); ?>
				</p>
				<?php _e('Video calls as Wordpress pages:', 'coviu-video-calls'); ?>
				<input type="checkbox" name="coviu[embed_participant_pages]" value="true" <?php if ($options->embed_participant_pages) echo 'checked'; ?>/>
			</p>
		</div>

		<div class="postbox">
			<h2><?php _e('Partner Application', 'coviu-video-calls'); ?></h2>

			<p><?php _e('If you don\'t want to pay Coviu per session, but rather want users registered and already paying for a Coviu Team account to be able to schedule sessions on this Wordpress site, activate this section. Otherwise ignore it.', 'coviu-video-calls'); ?></p>

			<p>
				<?php _e('Use Coviu as partner application:', 'coviu-video-calls'); ?>
				<input type="checkbox" name="coviu[require_oauth]" value="true" <?php if ($options->require_oauth) echo ' checked'; ?>/>
			</p>

			<div id="require_oauth">
				<h4>Steps to complete:</h4>
				<ul style="list-style-type: circle; padding-left:20px;">
					<li>
						<?php _e('Start by registering your Wordpress site as an application in your '); ?>
						<a href="https://coviu.com/" target="_blank"><?php _e('Coviu developer account.', 'coviu-video-calls'); ?></a>

					</li>
					<li>
						<?php _e('Provide it with the below Authorization Callback URL.'); ?>
					</li>
					<li>
						<?php _e('Then copy the Authorization Flow URL that it provides you with below and save your settings.',
						'coviu-video-calls'); ?>
					</li>
				</ul>
				<p>
					<?php
					$url = get_admin_url(null, 'admin.php?page=coviu-appointments-menu');
					_e('Authorization Callback Url:', 'coviu-video-calls');
					?>
					<a href="<?php echo $url ?>"><?php echo $url ?></a>
				</p>
				<p>
					<?php _e('Authorization Flow URL:', 'coviu-video-calls'); ?>
					<input type="text" name="coviu[oauth_url]" value="<?php echo $options->oauth_url ?>"/>
				</p>
				<p>
				<?php
					_e('FYI: The first user to connect their Coviu team user to this Wordpress site will connect the Coviu Team account. Subsequent users to authenticate with Coviu all have to be from that same team account.',
						'coviu-video-calls');
				?>
				</p>
				<?php if ($options->oauth_team != null) { ?>
					<?php $link = cvu_oauth_team_url($options); ?>
					<h5><?php _e('Authorized With:', 'coviu-video-calls') ?> <?php echo $link ?></h5>
				<?php } else { ?>
					<h5><?php _e('No Team linked with') ?></h5>
				<?php } ?>

			</div>
		</div>

		<p class="submit">
			<input name="Submit" type="submit" class="button-primary" value="<?php _e('Save Settings', 'coviu-video-calls'); ?>" />
		</p>

	</form>
	<?php
}

function cvu_oauth($coviu, $options) {
	$user_options = cvu_get_user_options();

	if (isset($_GET['code']) &&
		isset($user_options['grant']) && is_null($user_options['grant'])) {
		$code = $_GET['code'];

		cvu_oauth_login($coviu, $options, $user_options, $code);
	}

	$user_options = cvu_get_user_options();
	if (isset($user_options['grant']) && !is_null($user_options['grant'])) {
		?> Connected to Coviu.
		<form method="post" action="<?php echo $_SERVER["REQUEST_URI"] ?>">
			<?php wp_nonce_field( 'cvu_options', 'cvu_options_security' ); ?>
			<input type="hidden" name="coviu[action]" value="logout" />
			<input name="Submit" type="submit" class="button-primary" value="<?php _e('Disconnect', 'coviu-video-calls'); ?>" />
		</form>
		<?php

		return true;
	} else {
		?> <a href="<?php echo $options->oauth_url ?>">Login with Coviu</a> <?php

		return false;
	}
}

function cvu_oauth_login($coviu, $options, $user_options, $code) {
	try {
		$grant = $coviu->authorizationCode($code);
	} catch (OAuth2ClientException $e) {
		error(__("Failed to authenticate.", 'coviu-video-calls'));
		return;
	}

	// Keep track of old grant, in case we need to
	$old_grant = $coviu->getGrant();
	$coviu->setGrant($grant);

	$team = $coviu->user->getAuthorizedTeam();

	if (is_null($options->oauth_team)) {
		$options->oauth_team = $team;
		cvu_update_options($options);
	} else if ($team['team_id'] != $options->oauth_team['team_id']) {
		$link = cvu_oauth_team_url($options);
		error(__("Must be on the team: '".$link."'", 'coviu-video-calls'));
		$coviu->setGrant($old_grant);
		return;
	}

	$user_options['grant'] = $grant;
	cvu_update_user_options($user_options);
}

function cvu_oauth_logout($post, $coviu, $options) {
	$user = get_current_user_id();

	$user_options = cvu_get_user_options($user);
	$user_options['grant'] = null;
	cvu_update_user_options($user_options, $user);
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
				start_time.val(start.toISOString());
				end_time.val(end.toISOString());
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

function cvu_sessions_display( $actionurl, $coviu, $options ) {
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
		$content = '<iframe src="' . $participant['entry_url'] . '" style="width: 100%; height: 600px; border: none"></iframe>';
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

	return get_site_url() . '/?cvu_session=' . $post->post_name;
}

function cvu_oauth_team_url($options) {
	$path = 'https://'.$options->oauth_team['subdomain'].'.coviu.com/team';
	$name = $options->oauth_team['name'];
	return '<a href="'.$path.'">'.$name.'</a>';
}

/**
 * Recover a coviu API client instance.
 * Grant is recovered from a stored cache, either for a specific user, or for the stored API key
 * After any API calls have been performed, the client must be cleaned up with: cvu_update_client
 */
function cvu_client($options, $user_id = null) {
	$user_options = cvu_get_user_options($user_id);

	// Get the existing grant, if it exists
	$grant = null;
	if ($options->require_oauth && isset($user_options['grant'])) {
		$grant = $user_options['grant'];
	} else if (!is_null($options->grant)) {
		$grant = $options->grant;
	}

	return new Coviu($options->api_key, $options->api_key_secret, $grant);
}

/// Cleanup function for cvu_client
function cvu_update_client($coviu, $options, $user_id = null) {
	$user_options = cvu_get_user_options($user_id);

	$grant = $coviu->getGrant();

	if ($options->require_oauth && isset($user_options['grant'])) {
		$user_options['grant'] = $grant;
		return cvu_update_user_options($user_options, $user_id);
	} else {
		$options->grant = $grant;
		return cvu_update_options($options);
	}
}

function cvu_get_user_options($user_id = null) {
	if (is_null($user_id)) {
		$user_id = get_current_user_id();
	}

	$options = get_user_meta($user_id, 'coviu-video-calls');
	if (empty($options)) {
		return [];
	}
	return $options[0];
}

function cvu_update_user_options($options, $user_id = null) {
	if (is_null($user_id)) {
		$user_id = get_current_user_id();
	}

	return update_user_meta($user_id, 'coviu-video-calls', $options);
}

function cvu_update_options($options) {
	return update_option('coviu-video-calls', $options);
}

function cvu_get_options() {
	return get_option('coviu-video-calls');
}

function cvu_delete_user_options() {
	delete_metadata('user', 0, 'coviu-video-calls', '', true);
}

function cvu_guest_add( $post, $coviu, $options ) {
	// put together a participant
	$participant = array(
		'display_name' => $post['participant_name'],
		'role'         => 'guest',
		// 'state'        => 'test-state',
	);

	$added = cvu_participant_add( $coviu, $options, $post['session_id'], $participant );
}

function cvu_guest_delete( $post, $coviu, $options ) {
	cvu_participant_delete( $coviu, $options, $post['guest_id'] );
}

function cvu_host_add( $post, $coviu, $options ) {
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

	$added = cvu_participant_add( $coviu, $options, $post['session_id'], $participant );
}

function cvu_host_delete( $post, $coviu, $options ) {
	cvu_participant_delete( $coviu, $options, $post['host_id'] );
}

function cvu_participant_add( $coviu, $options, $session_id, $participant ) {
	// participant
	try {
		return $coviu->sessions->addParticipant ($session_id, $participant);
	} catch (\Exception $e) {
		error( $e->getMessage() );
		return;
	}
}

function cvu_participant_delete( $coviu, $options, $participant_id ) {
	try {
		return $coviu->sessions->deleteParticipant($participant_id);
	} catch (\Exception $e) {
		error( $e->getMessage() );
		return;
	}
}

function cvu_session_add( $post, $coviu, $options ) {
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
		cvu_host_add($post, $coviu, $options);
	}
}

function cvu_session_delete( $post, $coviu, $options ) {
	$session_id = $post['session_id'];

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

	if (count($posts) < 1) {
		return null;
	}
	return $posts[0];
}
