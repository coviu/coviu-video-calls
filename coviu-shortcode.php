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
 * Functions related to setting up a shortcode for coviu video button.
 * [coviu-video]
 */

require_once(WP_PLUGIN_DIR . '/coviu-video/coviu-auth.php');

use Ramsey\Uuid\Uuid;
use Firebase\JWT\JWT;

/// ***   Short Code   *** ///
add_shortcode( 'coviu-video', 'cvu_shortcode' );

function cvu_shortcode( $atts ){
  global $endpoint;

  // retrieve stored api keys
  $options  = get_option('coviu-video');
  $api_key = $options->api_key;
  $api_key_secret = $options->api_key_secret;
  if (!$api_key || !$api_key_secret) {
    return "";
  }

  // Recover an access token
  $grant = get_access_token( $api_key, $api_key_secret );

  // Get the root of the api
  $api_root = get_api_root($grant->access_token);

  // Create a new subscription for one of your users
  $subscription_ref = Uuid::uuid4();
  $subscription = create_subscription( $grant->access_token,
                                       $api_root,
                                       array('ref' => $subscription_ref->toString(),
                                            'name' => 'Dr. Jane Who',
                                           'email' => 'briely.marum@gmail.com'
                                       ) );

  // generate a random string for the session Id.
  // This only needs to be known to the participants.
  $session_id = Uuid::uuid4()->toString();

  // Sign a jwt for the owner of the subscription. This lets them into the call straight away.
  $token = array(
      'iss'   => $api_key,
      'un'    => 'Dr. Jane Who',
      'ref'   => $subscription_ref,
      'sid'   => $session_id,
      'img'   => 'http://www.fillmurray.com/200/300',
      'email' => 'dr.who@gmail.com',
      'rle'   => 'owner',
      'rtn'   => 'https://coviu.com',
      'nbf'   => time(),
      'exp'   => time() + 60*60
  );

  $owner = JWT::encode($token, $api_key_secret, 'HS256');

  return cvu_shortcode_display($endpoint."/v1/session/".$owner);
}

function cvu_shortcode_display( $owner_url ) {
?>
  <script language="javascript" type="text/javascript">
  function popitup(url) {
    newwindow = window.open(url,'name','height=640,width=1200');
    if (window.focus) { newwindow.focus(); }
    return false;
  }
  </script>
  <button><a href="<?php echo $owner_url ?>" onclick="return popitup('<?php echo $owner_url ?>')">Enter video call</a></button>
<?php
}


