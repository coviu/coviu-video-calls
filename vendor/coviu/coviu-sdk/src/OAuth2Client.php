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

namespace coviu\Api;

class OAuth2Client
{
  /** @var string */
  private $api_key;

  /** @var string */
  private $api_key_secret;

  /** @var Request */
  private $base;

  public function __construct($api_key, $api_key_secret, $base)
  {
      $this->api_key = $api_key;
      $this->api_key_secret = $api_key_secret;
      $that = $this;
      $auth = function() use (&$that) {return $that->basicAuth();};
      $this->base = $base->path('/auth/token')->form()->post()->auth($auth);
  }

  public function getAccessToken()
  {
    return $this->base->body(['grant_type' => 'client_credentials']);
  }

  public function refreshAccessToken( $refresh_token )
  {
    return $this->base->body(['grant_type' => 'refresh_token', 'refresh_token' => $refresh_token]);
  }

  private function basicAuth()
  {
    return 'Basic '.base64_encode($this->api_key.':'.$this->api_key_secret);
  }
}
