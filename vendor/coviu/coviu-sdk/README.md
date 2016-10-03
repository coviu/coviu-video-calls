coviu-php-sdk - Coviu api php client library
============================================


Coviu provides a session based API for creating and restricting access to coviu calls. The core concepts exposed are

* Session: A coviu call that occurs between two or more parties at a specified time, and has a finite duration.
* Participants: Users who may participate in a coviu call.

Participants join a call by following a _session link_ in their browser, or mobile app. The _session link_
identifies the participant, including their name, optional avatar, and importantly their _role_. As such,
it is important that each person joining the call be issued a different _session link_, i.e. have a distinct
_participant_ created for them. A participant's _role_ identifies whether that user may access the call directly,
or if they are required the be _let in_ by an existing participant.

coviu-php-sdk exposes this functionality through a convenient php library.


### Installation

```bash
composer require coviu/Api
```

If you are not using composer in your application, still run the above command, which creates a `vendor` directory. Then commit the vendor directory into your codebase. You can get `composer` from https://getcomposer.org/download/ .

### Quickstart

Setup the sdk by passing in your api key and key secret

```php
require_once __DIR__.'/vendor/autoload.php';
use coviu\Api\Coviu;

$api_key = 'my_api_key_from_coviu.com';
$api_key_secret = 'my_api_key_secret';

$coviu = new Coviu('api_key', 'key_secret');
```

Schedule a session for the future.

```php
date_default_timezone_set('GMT');

$session = array(
  'session_name' => 'A test session with Dr. Who',
  'start_time' => (new \DateTime())->format(\DateTime::ATOM),
  'end_time' => (new \DateTime())->modify('+1 hour')->format(\DateTime::ATOM),
  'picture' => 'http://www.fillmurray.com/200/300'
);

$session = $coviu->sessions->createSession($session);
var_dump($session);

```

Example output
```php
array(8) {
  ["team_id"]=>
  string(36) "bc5f47f1-f990-4d4d-a332-d3aa27ce6b76"
  ["client_id"]=>
  string(36) "440ee0f6-f99a-4515-ad15-da67dc29b0fc"
  ["participants"]=>
  array(0) {
  }
  ["session_id"]=>
  string(36) "6a157415-55cd-45a4-a82f-cd78b52e67b3"
  ["session_name"]=>
  string(27) "A test session with Dr. Who"
  ["start_time"]=>
  string(24) "2016-06-18T12:37:59.000Z"
  ["end_time"]=>
  string(24) "2016-06-18T13:37:59.000Z"
  ["picture"]=>
  string(33) "http://www.fillmurray.com/200/300"
}
```

`$coviu->sessions->*` is a collection of functions that build requests that can be run against the api.


You can now add a participant to the session

```php
$host = array(
  'display_name' => 'Dr. Who',
  'role' => 'host', // or 'guest'
  'picture' => 'http://fillmurray.com/200/300',
  'state' => 'test-state'
);

$participant = $coviu->sessions->addParticipant($session['session_id'], $host);
var_dump($participant);
```

Example output
```php
array(8) {
  ["client_id"]=>
  string(36) "440ee0f6-f99a-4515-ad15-da67dc29b0fc"
  ["display_name"]=>
  string(7) "Dr. Who"
  ["entry_url"]=>
  string(62) "https://coviu.com/session/af1f3606-dfbf-4728-b3ca-8f099ca9024a"
  ["participant_id"]=>
  string(36) "af1f3606-dfbf-4728-b3ca-8f099ca9024a"
  ["picture"]=>
  string(29) "http://fillmurray.com/200/300"
  ["role"]=>
  string(4) "HOST"
  ["session_id"]=>
  string(36) "6de7f062-f6db-4253-93b3-8f45445ce2d9"
  ["state"]=>
  string(10) "test-state"
}
```

Notice the `entry_url` for the newly created participant. Following this url in a browser or in one of the coviu mobile apps
between `start_time` and `end_time` (while the session is active), will join the participant into the session, assuming
the role and identity provided.


We can now read the entire session structure back
```php
$sessions = $coviu->sessions->getSessions();

var_dump($sessions);

var_dump($coviu->$sessions->getSession($session['session_id']));
```

Example output
```php
array(8) {
  ["team_id"]=>
  string(36) "bc5f47f1-f990-4d4d-a332-d3aa27ce6b76"
  ["client_id"]=>
  string(36) "440ee0f6-f99a-4515-ad15-da67dc29b0fc"
  ["participants"]=>
  array(1) {
    [0]=>
    array(8) {
      ["client_id"]=>
      string(36) "440ee0f6-f99a-4515-ad15-da67dc29b0fc"
      ["display_name"]=>
      string(7) "Dr. Who"
      ["entry_url"]=>
      string(62) "https://coviu.com/session/15142b66-7e26-4c49-a232-bc4aa1126aff"
      ["participant_id"]=>
      string(36) "15142b66-7e26-4c49-a232-bc4aa1126aff"
      ["picture"]=>
      string(29) "http://fillmurray.com/200/300"
      ["role"]=>
      string(4) "HOST"
      ["session_id"]=>
      string(36) "7ec15ff3-87f9-4ec9-9484-6029d5da56a6"
      ["state"]=>
      string(10) "test-state"
    }
  }
  ["session_id"]=>
  string(36) "7ec15ff3-87f9-4ec9-9484-6029d5da56a6"
  ["session_name"]=>
  string(27) "A test session with Dr. Who"
  ["start_time"]=>
  string(24) "2016-06-19T09:32:26.000Z"
  ["end_time"]=>
  string(24) "2016-06-19T10:32:26.000Z"
  ["picture"]=>
  string(33) "http://www.fillmurray.com/200/300"
}
```

There's a full set of api documents provided with api source for the `coviu-sdk-api` npm module at /src/SessionApi.php
