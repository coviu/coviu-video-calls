<?php 

require_once __DIR__ . '/../vendor/autoload.php'; // Autoload files using Composer autoload
require_once __DIR__ . '/../src/Coviu.php';

use coviu\Api\Coviu;

$api_key = getenv('API_KEY');
if (!$api_key) {
  echo("Set API_KEY environment variable.");
  exit();
}

$key_secret = getenv('KEY_SECRET');
if (!$key_secret) {
  echo("Set KEY_SECRET environment variable.");
  exit();
}

// initiate the API
$coviu = new Coviu($api_key, $key_secret);


// schedule a session
date_default_timezone_set('GMT');

$session = array(
  'session_name' => 'A test session with Dr. Who',
  'start_time' => (new \DateTime())->format(\DateTime::ATOM),
  'end_time' => (new \DateTime())->modify('+1 hour')->format(\DateTime::ATOM),
  'picture' => 'http://www.fillmurray.com/200/300'
);

$session = $coviu->sessions->createSession($session);
var_dump($session);

// add participant to session
$host = array(
  'display_name' => 'Dr. Who',
  'role' => 'host', // or 'guest'
  'picture' => 'http://fillmurray.com/200/300',
  'state' => 'test-state'
);

$participant = $coviu->sessions->addParticipant($session['session_id'], $host);
var_dump($participant);


// get sessions
$sessions = $coviu->sessions->getSessions();

var_dump($sessions);
var_dump($coviu->sessions->getSession($session['session_id']));


