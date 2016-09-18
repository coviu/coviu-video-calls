<?php

namespace coviu\Api;

class Coviu
{
  public $sessions;
  public function __construct($api_key, $key_secret, $endpoint = 'https://api.coviu.com/v1', $auto_run = true, $throw_on_failure = true)
  {
    $base = Request::request($endpoint);
    $client = new OAuth2Client($api_key, $key_secret, $base);
    $this->sessions = new SessionApi($base->auth(new Authenticator($client)));
    if ($auto_run)
    {
      $this->sessions = new RunDecorator($this->sessions);
      if ($throw_on_failure)
      {
        $this->sessions = new ThrowDecorator($this->sessions);
      }
    }
  }
}

// The RunDecorator automatically runs api requests that are generated.
class RunDecorator
{
  private $target;

  public function __construct($target)
  {
    $this->target = $target;
  }

  public function __call($method, $args)
  {
    return call_user_func_array(array($this->target, $method), $args)->run();
  }
}

// The ThrowDecorator turns failed http requests into exceptions. This behaviour can be turned off, but
// is probably the most convenient api to expose.
class ThrowDecorator
{
  private $target;
  public function __construct($target)
  {
    $this->target = $target;
  }

  public function __call($method, $args)
  {
    $res = call_user_func_array(array($this->target, $method), $args);
    if (is_array($res) && array_key_exists("ok", $res))
    {
      if (!$res["ok"])
      {
        throw new HttpException($res);
      }
      return $res['body'];
    }
    return $res;
  }
}
