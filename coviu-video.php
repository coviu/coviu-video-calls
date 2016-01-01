<?php
/*
 * Plugin Name: Coviu Video
 * Plugin URI: http://wordpress.org/extend/plugins/coviu-video/
 * Description: Add Coviu video calling to your Website. 
 * Author: Silvia Pfeiffer
 * Version: 0.1
 * Author URI: http://www.coviu.com/
 * License: GPL2
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html.
 * Text Domain: coviu-video
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

	@package    coviu-video
	@author     Silvia Pfeiffer <silviapfeiffer1@gmail.com>
	@copyright  Copyright 2015 Silvia Pfeiffer
	@license    http://www.gnu.org/licenses/gpl.txt GPL 2.0
	@version    0.1
	@link       http://wordpress.org/extend/plugins/coviu-video/

*/
defined( 'ABSPATH' ) or die( 'No script kiddies please!' );


require_once(WP_PLUGIN_DIR . '/coviu-video/coviu-shortcode.php');

/// ***  Set up and remove options for plugin *** ///

register_activation_hook( __FILE__, 'cvu_setup_options' );
function cvu_setup_options() {
	$options = new stdClass();
	$options->api_key = '';
	$options->api_key_secret = '';

	add_option('coviu-video', $options);
}

register_deactivation_hook( __FILE__, 'cvu_teardown_options' );
function cvu_teardown_options() {
	delete_option('coviu-video');   
}


/// ***   Admin Settings Page   *** ///

add_action( 'admin_menu', 'cvu_admin_menu' );

function cvu_admin_menu() {
	add_options_page(__('Coviu Video Settings', 'coviu-video'), __('Coviu Video', 'coviu-video'), 'manage_options', __FILE__, 'cvu_settings_page');
}

function cvu_settings_page() {
	if ( !current_user_can( 'manage_options' ) )  {
		wp_die( __( 'You do not have sufficient permissions to access this page.' ) );
	}

	// retrieve stored options
	$options = get_option('coviu-video');

	// process form data
	if( isset($_POST['coviu']) ) {

		// nonce check
		if (! isset( $_POST['cvu_options_security'] ) ||
			! wp_verify_nonce( $_POST['cvu_options_security'], 'cvu_options')) {
			print 'Sorry, your nonce did not verify.';
			exit;

		} else {
			// clean up entered data from surplus white space
			$_POST['coviu']['api_key']        = trim(sanitize_text_field($_POST['coviu']['api_key']));
			$_POST['coviu']['api_key_secret'] = trim(sanitize_text_field($_POST['coviu']['api_key_secret']));

			// check if credentials were provided
			if ( !$_POST['coviu']['api_key'] || !$_POST['coviu']['api_key_secret'] ) {
				?>
				<div class="error">
					<p><strong><?php echo __('Missing API credentials.', 'coviu-video'); ?></strong></p>
				</div>
				<?php
			} else {

				// updating credentials
				$options->api_key    = $_POST['coviu']['api_key'];
				$options->api_key_secret = $_POST['coviu']['api_key_secret'];
				update_option('coviu-video', $options);

				?>
				<div class="updated">
					<p><strong><?php echo __('Stored credentials.', 'coviu-video'); ?></strong></p>
				</div>
				<?php
			}
		}
	}

	// render the settings page
	?>
	<div class="wrap">
		<h2><?php _e('Coviu Video Settings', 'coviu-video'); ?></h2>
		<p>
			To use Coviu Video Conferencing, you need a <a href="https://www.coviu.com/developer/" target="_blank">developer account</a> and credentials for accessing the API.
		</p>

		<form method="post" action="<?php echo $_SERVER["REQUEST_URI"]; ?>">
			<?php wp_nonce_field( 'cvu_options', 'cvu_options_security' ); ?>

			<p>
				<?php _e('API Key:', 'coviu-video'); ?>
				<input type="text" name="coviu[api_key]" value="<?php echo $options->api_key ?>"/>
			</p>
			<p>
				<?php _e('Secret Key:', 'coviu-video'); ?>
				<input type="text" name="coviu[api_key_secret]" value="<?php echo $options->api_key_secret ?>"/>
			</p>
			<p class="submit">
				<input name="Submit" type="submit" class="button-primary" value="<?php _e('Add credentials', 'coviu-video'); ?>" />
			</p>
		</form>
	</div>
	<?php

}