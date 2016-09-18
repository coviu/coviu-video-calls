<?php

namespace coviu\Api;

class OAuth2ClientException extends \Exception
{
  public $response;

  public function __construct($response)
  {
    $this->response = $response;
    parent::__construct("OAuth2 client operation failed");
  }
}
