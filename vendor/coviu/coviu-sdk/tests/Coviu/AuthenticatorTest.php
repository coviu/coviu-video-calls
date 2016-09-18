<?php

namespace coviu\Api;

use PHPUnit_Framework_TestCase;

class MockRequest
{
  private $value;

  public function __construct($value)
  {
    $this->value = $value;
  }

  public function run()
  {
    return $this->value;
  }
}

class MockOAuth2Client
{
  public $get_access_token_called;
  public $refresh_access_token;
  public $grant;

  public function __construct()
  {
    $this->get_access_token_called = 0;
    $this->refresh_access_token = 0;
    $this->grant = [
      "ok" => TRUE,
      "body" => [
        "access_token" => "access token",
        "refresh_token" => "refresh token",
        "expires_in" => 10
      ]
    ];
  }

  public function getAccessToken()
  {
    $this->get_access_token_called++;
    return new MockRequest($this->grant);
  }

  public function refreshAccessToken()
  {
    $this->refresh_access_token++;
    return new MockRequest($this->grant);
  }
}

class AuthenticatorTest extends PHPUnit_Framework_TestCase
{
  private static $endpoint = 'http://localhost:9400/v1';
  private static $api_key = '8de85310-7c43-4606-a450-43a348398a4b';
  private static $key_secret = 'abcdefg';

  public function testWillRequestAccessToken()
  {
    $client = new MockOAuth2Client();
    $authenticator = new Authenticator($client);
    $this->assertTrue($authenticator->needsInit());
    $auth = $authenticator();
    $this->assertEquals($auth, 'Bearer '.$client->grant['body']['access_token']);
    $this->assertEquals($client->get_access_token_called, 1);
    $this->assertEquals($client->refresh_access_token, 0);
    $this->assertFalse($authenticator->needsRefresh());
  }

  public function testCanRefreshAccessToken()
  {
    $client = new MockOAuth2Client();
    $authenticator = new Authenticator($client);
    $auth = $authenticator();
    $this->assertEquals($auth, 'Bearer '.$client->grant['body']['access_token']);
    $this->assertEquals($client->get_access_token_called, 1);
    $this->assertEquals($client->refresh_access_token, 0);
    $this->assertFalse($authenticator->needsRefresh());

    // Set the clock to some point after the next refresh
    $authenticator->setClock(function() {
      return time() + 10;
    });
    $client->grant['body']['access_token'] = 'new access token';

    // We now need to refresh
    $this->assertTrue($authenticator->needsRefresh());
    $auth = $authenticator();

    // We can get a beaer token;
    $this->assertEquals($auth, 'Bearer new access token');
    $this->assertEquals($client->get_access_token_called, 1);
    $this->assertEquals($client->refresh_access_token, 1);
  }

  public function testThrowsExceptionIfCantGetAccessToken()
  {
    try {
      $client = new MockOAuth2Client();
      $client->grant['ok'] = FALSE;
      $authenticator = new Authenticator($client);
      $auth = $authenticator();
      $this->assertTrue(FALSE);
    } catch(OAuth2ClientException $e) {
      $this->assertFalse($e->response['ok']);
    }
  }

  public function testThrowsExceptionIfRefreshFails()
  {
    try {
      $client = new MockOAuth2Client();
      $authenticator = new Authenticator($client);
      $auth = $authenticator();
      $this->assertEquals($auth, 'Bearer '.$client->grant['body']['access_token']);
      $this->assertEquals($client->get_access_token_called, 1);
      $this->assertEquals($client->refresh_access_token, 0);
      $this->assertFalse($authenticator->needsRefresh());

      // Set the clock to some point after the next refresh
      $authenticator->setClock(function() {
        return time() + 10;
      });
      $client->grant['ok'] = FALSE;

      // We now need to refresh
      $this->assertTrue($authenticator->needsRefresh());
      $auth = $authenticator();
      $this->assertTrue(FALSE);
    } catch(OAuth2ClientException $e) {
      $this->assertFalse($e->response['ok']);
    }
  }

  public function testCanMakeRequest()
  {
    $request = Request::request(self::$endpoint);
    $keys = self::build_session_client();
    $client = new OAuth2Client($keys['clientId'], $keys['secret'], $request);
    $authenticator = new Authenticator($client);
    $sessionRequest = $request->auth($authenticator)->get()->path('/sessions');
    $result = $sessionRequest->run();
    $this->assertTrue($result['ok']);
  }

  // TODO: Remove the duplication with SdkTest.
  public static function build_session_client()
  {
    $req = Request::request(self::$endpoint);
    $client = new OAuth2Client(self::$api_key, self::$key_secret, $req);
    $authenticator = new Authenticator($client);
    $post = $req->auth($authenticator)->post()->json();
    // Create a user
    $user = $post->path('/users')->body(Examples::user())->run()['body'];
    // Create a team.
    $team = $post->path('/users/'.$user['userId'].'/teams')->body(Examples::team())->run()['body'];
    // Create an api client for that team owned by that user.
    $client = $post->path('/system/clients')->body(Examples::client($user['userId'], $team['teamId']))->run()['body'];
    return $client;
  }
}
