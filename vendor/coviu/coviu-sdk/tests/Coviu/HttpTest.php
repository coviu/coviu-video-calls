<?php

namespace coviu\Api;

use PHPUnit_Framework_TestCase;

// Tests for the http request execution logic.
// We're running against an echo server https://www.npmjs.com/package/echo-server
// that gives use the ability to execute the request and reflect on the result
class HttpTest extends PHPUnit_Framework_TestCase
{
  private static $endpoint = 'http://localhost:5000';

  public function testBasicGet()
  {
    $request = Request::request(self::$endpoint);
    $res = $request->run();
    $this->assertTrue($res['ok']);
    $this->assertEquals($res['body']['method'], 'GET');
    $this->assertEquals($res['body']['url'], '/');
    $this->assertEquals($res['body']['headers']['accept'], 'application/json');
  }

  public function testBasicGetWithPath()
  {
    $request = Request::request(self::$endpoint);
    $res = $request->path('/foo/bar')->run();
    $this->assertTrue($res['ok']);
    $this->assertEquals($res['body']['method'], 'GET');
    $this->assertEquals($res['body']['url'], '/foo/bar');
    $this->assertEquals($res['body']['headers']['accept'], 'application/json');
  }

  public function testPostForm()
  {
    $request =  Request::request(self::$endpoint);
    $res = $request->form()->post()->body(['foo'=>'bar', 'bob' => 'baz'])->run();
    $this->assertTrue($res['ok']);
    $this->assertEquals($res['body']['method'], 'POST');
    $this->assertEquals($res['body']['headers']['content-type'], 'application/x-www-form-urlencoded');
    $this->assertEquals($res['body']['body'], 'foo=bar&bob=baz');
  }

  public function testPostJson()
  {
    $request =  Request::request(self::$endpoint);
    $res = $request->json()->post()->body(['foo'=>'bar'])->run();
    $this->assertTrue($res['ok']);
    $this->assertEquals($res['body']['method'], 'POST');
    $this->assertEquals($res['body']['headers']['content-type'], 'application/json');
    $this->assertEquals(json_decode($res['body']['body'], true)['foo'], 'bar');
  }

  public function testPutJson()
  {
    $request =  Request::request(self::$endpoint);
    $res = $request->json()->put()->body(['foo'=>'bar'])->run();
    $this->assertTrue($res['ok']);
    $this->assertEquals($res['body']['method'], 'PUT');
    $this->assertEquals($res['body']['headers']['content-type'], 'application/json');
    $this->assertEquals(json_decode($res['body']['body'], true)['foo'], 'bar');
  }

  public function testPutForm()
  {
    $request =  Request::request(self::$endpoint);
    $res = $request->form()->put()->body(['foo'=>'bar', 'bob' => 'baz'])->run();
    $this->assertTrue($res['ok']);
    $this->assertEquals($res['body']['method'], 'PUT');
    $this->assertEquals($res['body']['headers']['content-type'], 'application/x-www-form-urlencoded');
    $this->assertEquals($res['body']['body'], 'foo=bar&bob=baz');
  }

  public function testQueryString()
  {
    $request =  Request::request(self::$endpoint);
    $res = $request->query(['foo'=>'bar', 'number'=>2, 'string' => 'has space'])->run();
    $this->assertTrue($res['ok']);
    $this->assertEquals($res['body']['url'], '/?foo=bar&number=2&string=has+space');
  }

  public function testBasicAuth()
  {
    $request = Request::request(self::$endpoint);
    $res = $request->auth('Basic something')->run();
    $this->assertTrue($res['ok']);
    $this->assertEquals($res['body']['headers']['authorization'], 'Basic something');
  }

  public function testDeferedAuth()
  {
    $called = 0;
    // #yolophp
    $mkAuth = function () use (&$called)
    {
      $called++;
      return 'Bearer token';
    };
    $request = Request::request(self::$endpoint);
    $res = $request->auth($mkAuth)->run();
    $this->assertTrue($res['ok']);
    $this->assertEquals($res['body']['headers']['authorization'], 'Bearer token');
    $this->assertEquals($called, 1);
  }

}
