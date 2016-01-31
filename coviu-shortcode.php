<?php

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
*/

/*
 * Set up a shortcode for coviu video button for the owner:
 * [coviu-link-owner ref='xxx' sessionid='yyy' start='time' end='time' embed]
 * - identify the owner by ref
 * - provide a sessionid to allow referencing it
 * - provide optional start and end time
 *
 * Set up a shortcode for coviu video URL for the guest:
 * [coviu-link-guest ref='xxx' sessionid='yyy' name='patient' embed]
 * - identify the owner by ref
 * - identify the session by sessionid
 * - provide a name for the guest
 */

use Ramsey\Uuid\Uuid;
use Firebase\JWT\JWT;

/// ***   Short Codes   *** ///
add_shortcode( 'coviu-link-owner', 'cvu_link_owner' );
add_shortcode( 'coviu-url-owner', 'cvu_url_owner' );

add_shortcode( 'coviu-link-guest', 'cvu_link_guest' );
add_shortcode( 'coviu-url-guest', 'cvu_url_guest' );

function cvu_link_owner( $atts ){
	// get shortcode attributes
	extract(shortcode_atts(array(
		'embed'     => '0',
		'color'     => ''
		), $atts));
	$embed = (bool) $embed;

	$owner_url = cvu_url_owner( $atts );

	return cvu_shortcode_display('owner', $owner_url, $embed, $color);
}

function cvu_url_owner( $atts ){
	global $endpoint;

	// get shortcode attributes
	extract(shortcode_atts(array(
		'ref'       => '',
		'sessionid' => '',
		'start'     => '',
		'end'       => '',
		), $atts));

	// retrieve stored api keys
	$options  = get_option('coviu-video-calls');
	$api_key = $options->api_key;
	$api_key_secret = $options->api_key_secret;
	if (!$api_key || !$api_key_secret) {
		return "Missing Coviu API keys";
	}

	// Recover an access token
	$grant = get_access_token( $api_key, $api_key_secret );

	// Get the root of the api
	$api_root = get_api_root($grant->access_token);

	// Retrieve subscription by ref
	$subscription = get_subscription_by_ref($grant->access_token, $api_root, $ref);

	if (!$subscription) {
		return sprintf(__("<p><i>ERROR: user reference \"%s\" not found.</i></p>", 'coviu-video-calls'), $ref);
	}

	// Sign a jwt for the owner of the subscription. This lets them into the call straight away.
	$token = array(
			'iss'   => $api_key,
			'un'    => $subscription->content->name,
			'ref'   => $ref,
			'sid'   => $sessionid,
			'img'   => 'http://www.fillmurray.com/200/300',
			'email' => $subscription->content->email,
			'rle'   => 'owner',
			'rtn'   => 'https://coviu.com',
			'nbf'   => time(),
			'exp'   => time() + 60*60
	);

	$owner = JWT::encode($token, $api_key_secret, 'HS256');

	return $endpoint."/v1/session/".$owner;
}


function cvu_link_guest( $atts ){
	// get shortcode attributes
	extract(shortcode_atts(array(
		'embed'     => '0',
		'color'     => ''
		), $atts));
	$embed = (bool) $embed;

	$guest_url = cvu_url_guest( $atts );

	return cvu_shortcode_display('guest', $guest_url, $embed, $color);
}

function cvu_url_guest( $atts ){
	global $endpoint;

	// retrieve stored api keys
	$options  = get_option('coviu-video-calls');
	$api_key = $options->api_key;
	$api_key_secret = $options->api_key_secret;
	if (!$api_key || !$api_key_secret) {
		return "Missing Coviu API keys";
	}

	// get shortcode attributes
	extract(shortcode_atts(array(
		'ref'       => '',
		'sessionid' => '',
		'name'      => '',
		), $atts));

	// Recover an access token
	$grant = get_access_token( $api_key, $api_key_secret );

	// Get the root of the api
	$api_root = get_api_root($grant->access_token);

	// Sign a jwt for the owner of the subscription. This lets them into the call straight away.
	$token = array(
			'iss'   => $api_key,
			'un'    => $name,
			'ref'   => $ref,
			'sid'   => $sessionid,
			'img'   => 'http://www.fillmurray.com/200/300',
			'rle'   => 'owner',
			'rtn'   => 'https://coviu.com',
			'nbf'   => time(),
			'exp'   => time() + 60*60
	);

	$guest = JWT::encode($token, $api_key_secret, 'HS256');

	return $endpoint."/v1/session/".$guest;
}

function cvu_shortcode_display( $role, $user_url, $embed, $color ) {
	if ($embed == true) {
		?>
		<iframe src="<?php echo $user_url ?>" width="100%" height="450px"></iframe>
		<?php
	} else {
		?>
		<script language="javascript" type="text/javascript">
		function popitup(url) {
			newwindow = window.open(url, '_blank','height=640,width=1200');
			if (window.focus) { newwindow.focus(); }
			return false;
		}
		</script>
		<?php

		if ($role == 'owner') {
			?>
			<button type="button">
			<a href="<?php echo $user_url ?>"  style="color: <?php echo $color ?>" onclick="return popitup('<?php echo $user_url ?>')">Enter video call</a>
			</button>
			<?php
		} else {
			?>
			<a href="<?php echo $user_url ?>" style="color: <?php echo $color ?>" onclick="return popitup('<?php echo $user_url ?>')">Guest video call link</a>
			<?php
		}
	}
}


