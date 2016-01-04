<?php

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
*/

/*
 * Set up a shortcode for coviu video button for the owner:
 * [coviu-link-owner ref='xxx' sessionid='yyy' start='time' end='time']
 * - identify the owner by ref
 * - provide a sessionid to allow referencing it
 * - provide optional start and end time
 *
 * Set up a shortcode for coviu video URL for the guest:
 * [coviu-link-guest ref='xxx' sessionid='yyy' name='patient' email='patient@gmail.com']
 * - identify the owner by ref
 * - identify the session by sessionid
 * - provide a name for the guest
 * - provide an email for the guest
 */

use Ramsey\Uuid\Uuid;
use Firebase\JWT\JWT;

/// ***   Short Codes   *** ///
add_shortcode( 'coviu-link-owner', 'cvu_shortcode_owner' );
add_shortcode( 'coviu-link-guest', 'cvu_shortcode_guest' );

function cvu_shortcode_owner( $atts ){
  global $endpoint;

  // retrieve stored api keys
  $options  = get_option('coviu-video');
  $api_key = $options->api_key;
  $api_key_secret = $options->api_key_secret;
  if (!$api_key || !$api_key_secret) {
    return "Missing Coviu API keys";
  }

  // get shortcode attributes
  extract(shortcode_atts(array(
    'ref'       => '',
    'sessionid' => '',
    ), $atts));

  // Recover an access token
  $grant = get_access_token( $api_key, $api_key_secret );

  // Get the root of the api
  $api_root = get_api_root($grant->access_token);

  // Retrieve subscription by ref
  $subscription = get_subscription_by_ref($grant->access_token, $api_root, $ref);

  if (!$subscription) {
    return sprintf(__("<p><i>ERROR: user reference \"%s\" not found.</i></p>", 'coviu-video'), $ref);
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

  return cvu_shortcode_display('owner', $endpoint."/v1/session/".$owner);
}


function cvu_shortcode_guest( $atts ){
  global $endpoint;

  // retrieve stored api keys
  $options  = get_option('coviu-video');
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
    'email'     => '',
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
      'email' => $email,
      'rle'   => 'owner',
      'rtn'   => 'https://coviu.com',
      'nbf'   => time(),
      'exp'   => time() + 60*60
  );

  $guest = JWT::encode($token, $api_key_secret, 'HS256');

  return cvu_shortcode_display('guest', $endpoint."/v1/session/".$guest);
}

function cvu_shortcode_display( $role, $user_url ) {
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
    <button><a href="<?php echo $user_url ?>" onclick="return popitup('<?php echo $user_url ?>')">Enter video call</a></button>
    <?php
  } else {
    ?>
    <a href="<?php echo $user_url ?>" onclick="return popitup('<?php echo $user_url ?>')">Guest video call link</a>
    <?php
  }
}


