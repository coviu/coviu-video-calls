<?php

namespace coviu\Api;

class HttpException extends \Exception
{
  public $response;
  public function __construct($response)
  {
    $msg = 'The HTTP request failed with status code '.$response['response']->status_code;
    if (is_array($response['body']) && $response['body']['error'])
    {
      $msg = $msg.'\n'.$response['body']['error']['message'];
    }
    parent::__construct($msg);
    $this->response = $response;
  }
}
