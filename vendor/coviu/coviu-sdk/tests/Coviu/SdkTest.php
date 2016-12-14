<?php

namespace coviu\Api;

use PHPUnit_Framework_TestCase;

class SdkTest extends PHPUnit_Framework_TestCase
{
  private static $endpoint = 'http://localhost:9400/v1';
  private static $api_key = '8de85310-7c43-4606-a450-43a348398a4b';
  private static $key_secret = 'abcdefg';
  private $sdk;

  protected function setUp()
  {
    // Create a new user, team, and api key for each test, just for isolation.
    $client = self::build_session_client();
    // Our SDK with our api_key, secret test endpoint.
    $this->coviu = new Coviu($client['clientId'], $client['secret'], NULL, self::$endpoint);
  }

  // Get all sessions for this client
  public function testGetSessions()
  {
    $sessions = $this->coviu->sessions->getSessions();
    $this->assertTrue(is_array($sessions['content']));
    $this->assertTrue(is_int($sessions['page_size']));
    $this->assertTrue(is_int($sessions['page']));
    $this->assertTrue(is_bool($sessions['more']));
  }

  // Create a new session without any participants
  public function testCanCreateASession()
  {
    $session = $this->coviu->sessions->createSession(Examples::session());
    $this->assertTrue(array_key_exists('team_id', $session));
    $this->assertTrue(array_key_exists('client_id', $session));
    $this->assertTrue(array_key_exists('session_name', $session));
    $this->assertTrue(array_key_exists('start_time', $session));
    $this->assertTrue(array_key_exists('end_time', $session));
    $this->assertTrue(is_array($session['participants']));
  }

  // Add a host participant to a session
  public function testCanAddHostParticipant()
  {
    $session = $this->coviu->sessions->createSession(Examples::session());
    $participant = $this->coviu->sessions->addParticipant($session['session_id'], Examples::host());
    $this->assertTrue(array_key_exists('participant_id', $participant));
    $this->assertTrue(array_key_exists('entry_url', $participant));
    $this->assertEquals($participant['role'], 'HOST');
  }

  // Add a guest participant to a session.
  public function testCanAddGuestParticipant()
  {
    $session = $this->coviu->sessions->createSession(Examples::session());
    $participant = $this->coviu->sessions->addParticipant($session['session_id'], Examples::guest());
    $this->assertTrue(array_key_exists('participant_id', $participant));
    $this->assertTrue(array_key_exists('entry_url', $participant));
    $this->assertEquals($participant['role'], 'GUEST');
  }

  // Get a session by its session id.
  public function testCanGetSessionById()
  {
    $example = Examples::session();
    $example['participants'] = [Examples::guest(), Examples::host()];
    $session = $this->coviu->sessions->createSession($example);
    $recovered = $this->coviu->sessions->getSession($session['session_id']);
    $this->assertEquals($recovered['session_id'], $session['session_id']);
    $this->assertEquals(count($recovered['participants']), 2);
  }

  // Update a session's particpant. To e.g. change role and display name.
  public function testCanUpdateParticipant()
  {
    $session = $this->coviu->sessions->createSession(Examples::session());
    $participant = $this->coviu->sessions->addParticipant($session['session_id'], Examples::guest());
    $update = ['role'=> 'HOST', 'display_name' => 'new display name'];
    $updated = $this->coviu->sessions->updateParticipant($participant['participant_id'], $update);
    $this->assertEquals($updated['participant_id'], $participant['participant_id']);
    $this->assertEquals($updated['role'], $update['role']);
    $this->assertEquals($updated['display_name'], $update['display_name']);
  }

  // Update a session's start time, end time, picture, title.
  public function testCanUpdateSession()
  {
    $session = $this->coviu->sessions->createSession(Examples::session());
    $update = [
      'start_time' => (new \DateTime())->modify('+1 hour')->format(\DateTime::ATOM),
      'end_time' => (new \DateTime())->modify('+2 hour')->format(\DateTime::ATOM),
      'session_name' => 'new display name',
      'picture' => 'not a real picture'
    ];
    $updated = $this->coviu->sessions->updateSession($session['session_id'], $update);
    $this->assertEquals($updated['session_id'], $session['session_id']);
    $this->assertEquals($updated['session_name'], $update['session_name']);
    // A direct string comparison won't work because the returned datetime string has milliseconds.
    $this->assertEquals((new \DateTime($update['start_time']))->format(\DateTime::ATOM), $update['start_time']);
    $this->assertEquals((new \DateTime($update['end_time']))->format(\DateTime::ATOM), $update['end_time']);
    $this->assertEquals($updated['picture'], $update['picture']);
  }

  // Get the participants for a session.
  public function testCanGetSessionParticipants()
  {
    $example = Examples::session();
    $example['participants'] = [Examples::guest(), Examples::host()];
    $session = $this->coviu->sessions->createSession($example);
    $participants = $this->coviu->sessions->getSessionParticipants($session['session_id']);
    $this->assertEquals(count($participants), 2);
  }

  // Get a specific particpiant by its participant id.
  public function testCanGetParticipant()
  {
    $session = $this->coviu->sessions->createSession(Examples::session());
    $participant = $this->coviu->sessions->addParticipant($session['session_id'], Examples::guest());
    $recovered = $this->coviu->sessions->getParticipant($participant['participant_id']);
    $this->assertEquals($recovered['participant_id'], $participant['participant_id']);
    $this->assertEquals($recovered['session_id'], $session['session_id']);
  }

  // Remove a participant from a session.
  public function testCanRemoveParticipant()
  {
    $session = $this->coviu->sessions->createSession(Examples::session());
    $participant = $this->coviu->sessions->addParticipant($session['session_id'], Examples::guest());
    $removed = $this->coviu->sessions->deleteParticipant($participant['participant_id']);
    $this->assertTrue($removed['ok']);
    $recovered = $this->coviu->sessions->getSession($session['session_id']);
    $this->assertEquals(count($recovered['participants']), 0);
  }

  // Cancel a session altogether.
  public function testCanCancelASession()
  {
    $session = $this->coviu->sessions->createSession(Examples::session());
    $removed = $this->coviu->sessions->deleteSession($session['session_id']);
    $this->assertTrue($removed['ok']);
    try {
      $this->coviu->sessions->getSession($session['session_id']);
      $this->assertTrue(FALSE);
    } catch (HttpException $e) {
      // The session is not gone. You just can't see it any more.
      $this->assertEquals($e->response['response']->status_code, 401);
    }
  }

  // The above api key and secret allows us to create users, and new api keys.
  // Here we're doing that so each test occurs in isolation.
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

// A bunch of static functions for generating example input data for the test.
class Examples
{
  public static function session()
  {
    return [
      'start_time' => (new \DateTime())->modify('+10 seconds')->format(\DateTime::ATOM),
      'end_time' => (new \DateTime())->modify('+1 hour')->format(\DateTime::ATOM),
      'session_name' => 'example session',
      'picture' => 'http://www.fillmurray.com/200/300'
    ];
  }

  public static function host()
  {
    return [
      'display_name' => 'Dr. Who',
      'role' => 'host',
      'picture'=> 'http://fillmurray.com/200/300',
      'state'=> 'test-state'
    ];
  }

  public static function guest()
  {
    return [
      'display_name' => 'Dr. Who',
      'role'=> 'guest',
      'picture'=> 'http://fillmurray.com/200/300',
      'state'=> 'test-state'
    ];
  }

  public static function user()
  {
    return [
      'email' => uniqid().'@mailinator.com',
      'firstName' => 'That',
      'lastName' => 'Guy',
      'password' => uniqid(),
      'alias' => uniqid(),
      'imageUrl' => 'http://www.fillmurray.com/200/300'
    ];
  }

  public static function team() {
    return [
      'name' => 'Test Team',
      'subdomain' => uniqid() ,
      'imageUrl' => 'http://www.fillmurray.com/200/300'
    ];
  }

  public static function client($userId, $teamId)
  {
    return [
      "client_name" => "test client",
      "ownerId" => $userId,
      "scope" => ["team_api[".$teamId."]"]
    ];
  }

  public static function application()
  {
    return [
      "name" => "Test Application",
      "homePage" => "http://www.google.com",
      "redirectEndpoint" => "https://www.google.com",
      "image" => "https://i.imgflip.com/1fsd2t.jpg",
      "description" => "Short ribs hamburger bacon prosciutto jowl brisket. Biltong \
      corned beef turducken picanha rump pig t-bone beef sausage bacon tri-tip. \
      Pancetta andouille meatloaf, tri-tip picanha pork belly brisket turducken \
      pork loin capicola frankfurter pastrami bresaola landjaeger. Burgdoggen \
      t-bone shank picanha, meatball rump beef ribs corned beef tenderloin \
      swine leberkas ham turducken doner. Pork loin short ribs short loin \
      chicken jowl ham hock cow landjaeger andouille jerky tenderloin spare \
      ribs pork."
    ];
  }
}
