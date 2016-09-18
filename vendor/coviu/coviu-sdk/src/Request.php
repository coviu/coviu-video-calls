<?php

namespace coviu\Api;

use rmccue\requests;

function build_headers($render)
{
  $headers = [];
  if (array_key_exists('CONTENT_TYPE', $render))
  {
    $headers['Content-Type'] = $render['CONTENT_TYPE'];
  }

  if (array_key_exists('AUTH', $render))
  {
    $auth = $render['AUTH'];
    // Notice that auth may be callable, because we want to defer any decision to refresh
    // An access token until the last minute.
    if (is_callable($auth))
    {
      $headers['Authorization'] = $auth();
    } else
    {
      $headers['Authorization'] = $auth;
    }
  }

  if (array_key_exists('ACCEPT', $render))
  {
    $headers['Accept'] =  $render['ACCEPT'];
  }
  return $headers;
}

function build_url($render)
{
  $query = '';
  if (array_key_exists('QUERY', $render) && count($render['QUERY']) > 0)
  {
    $query = '?'.http_build_query($render['QUERY']);
  }

  return $render['HOST'].$render['PATH'].$query;
}

function build_body($render)
{
  if(array_key_exists('BODY', $render))
  {
    if (is_array($render['BODY']) && $render['JSON']) {
      return json_encode($render['BODY']);
    } else
    {
      // Seems the request library form encodes by default and sets the content type
      // if we set the body to an associative array.
      return $render['BODY'];
    }
  }
}

function issue_request($render, $url, $headers, $body)
{
  if ($render['METHOD'] == 'POST')
  {
    return  \Requests::post($url, $headers, $body);
  } else if ($render['METHOD'] == 'PUT')
  {
    return \Requests::put($url, $headers, $body);
  } else if ($render['METHOD'] == 'DELETE')
  {
    return \Requests::delete($url, $headers);
  }

  return \Requests::get($url, $headers);
}

function parse_response_body($response)
{

  $content = $response->headers['content-type'];
  if (isset($content) && strpos($content, 'json') >= 0)
  {
    return json_decode($response->body, TRUE);
  }
  return $response->body;
}

function is_success_response($response)
{
  return $response->status_code >= 200 && $response->status_code < 300;
}

function build_request_result($render, $response)
{
  return [
    'request' => $render,
    'response' => $response,
    'body' => parse_response_body($response),
    'ok' => is_success_response($response)
  ];
}

// Run a request against the the requests library
function run_request($request)
{
  $render = $request->render();
  $headers = build_headers($render);
  $url = build_url($render);
  $body = build_body($render);
  $response = issue_request($render, $url, $headers, $body);
  return build_request_result($render, $response);
}

// Request is an immutable data structure that lets us
// describe the http request we intend to run.
class Request {

  private $key;
  private $value;
  private $tail;

  // private constructor because values of $key have specific meaning to us
  private function __construct($key, $value, $tail)
  {
    $this->key = $key;
    $this->value = $value;
    $this->tail = $tail;
  }

  // Construct an new Request by providing an endpoint.
  public static function request($endpoint)
  {
    return (new Request(NULL, NULL, NULL))->host($endpoint)->get()->path('')->accept('application/json');
  }

  // Set the request parameter $key to $value
  public function set($key, $value)
  {
    return new Request($key, $value, $this);
  }

  // Set the http method of the request to $value
  public function method($value)
  {
    return $this->set('METHOD', $value);
  }

  // Set the host to $value
  public function host($value)
  {
    return $this->set('HOST', $value);
  }

  // Set the method to GET
  public function get()
  {
    return $this->method('GET');
  }

  // Set the method to POST
  public function post()
  {
    return $this->method('POST');
  }

  // Set the method to PUT
  public function put()
  {
    return $this->method('PUT');
  }

  // Set the method to DELETE
  public function delete()
  {
    return $this->method('DELETE');
  }

  // Set the path, of the request. Note that $host may have static
  // path components and the final URL is $host.$path, e.g
  // $host = 'https://api.coviu.com/v1';
  // $path = '/session';
  // therefore $url = 'https://api.coviu.com/v1/session';
  // TODO: fix the nomenclature
  public function path($value)
  {
    return $this->set('PATH', $value);
  }

  // Sets the accepts headers
  public function accept($value)
  {
    return $this->set('ACCEPT', $value);
  }

  // Set the content type
  public function contentType($value)
  {
    return $this->set('CONTENT_TYPE', $value);
  }

  // Set content type to json
  public function json()
  {
    return $this->contentType('application/json')->set('JSON', TRUE);
  }

  // Set content type to form
  public function form()
  {
    return $this->contentType('application/x-www-form-urlencoded')->set('JSON', FALSE);
  }

  // Appand $value to the end of the existing path
  public function subpath($value)
  {
    return $this->set('PATH', $this->find('PATH').$value);
  }

  // Set the method for attaching the AUTH header to the request
  // this may be a callable that produces the correct headers,
  // or it may be an array holding the Authorization headers.
  public function auth($value)
  {
    return $this->set('AUTH', $value);
  }

  // Set the body of the request.
  public function body($value)
  {
    return $this->set('BODY', $value);
  }

  // Set the query parameters of the request
  public function query($value)
  {
    return $this->set('QUERY', $value);
  }

  // Request is just a linked list, is $this the end.
  public function isSentinel()
  {
    return is_null($this->tail);
  }

  // Traverse the Request, calculating the final value for each request parameter.
  public function render()
  {
    if ($this->isSentinel()){
      return [];
    }
    $result = $this->tail->render();
    $result[$this->key] = $this->value;
    return $result;
  }

  // Find the value of a the request parameter $key, returning NULL if it doesn't exist.
  public function find($key)
  {
    if ($this->isSentinel())
    {
      return NULL;
    }

    if ($this->key == $key)
    {
      return $this->value;
    }

    return $this->tail->find($key);
  }

  public function run()
  {
    return run_request($this);
  }
}
