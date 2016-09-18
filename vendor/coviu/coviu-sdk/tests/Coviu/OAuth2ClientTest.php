<?php

namespace coviu\Api;

use PHPUnit_Framework_TestCase;

class OAuth2ClientTest extends PHPUnit_Framework_TestCase
{
  private static $endpoint = 'http://localhost:9400/v1';
  private static $api_key = '8de85310-7c43-4606-a450-43a348398a4b';
  private static $key_secret = 'abcdefg';

  public function testCanGetAccessToken()
  {
    $client = new OAuth2Client(self::$api_key, self::$key_secret, Request::request(self::$endpoint));
    $res = $client->getAccessToken()->run();
    $this->assertTrue(isset($res['body']['access_token']));
    $this->assertTrue(isset($res['body']['refresh_token']));
    $this->assertTrue(isset($res['body']['expires_in']));
  }

  public function testCanRefreshAccessToken()
  {
    $client = new OAuth2Client(self::$api_key, self::$key_secret, Request::request(self::$endpoint));
    $res = $client->getAccessToken()->run();
    $this->assertTrue(isset($res['body']['access_token']));
    $this->assertTrue(isset($res['body']['refresh_token']));
    $this->assertTrue(isset($res['body']['expires_in']));
    $res = $client->refreshAccessToken($res['body']['refresh_token'])->run();
    $this->assertTrue(isset($res['body']['access_token']));
    $this->assertTrue(isset($res['body']['refresh_token']));
    $this->assertTrue(isset($res['body']['expires_in']));
  }
}
