<?php

namespace coviu\Api;

use PHPUnit_Framework_TestCase;

class RequestTest extends PHPUnit_Framework_TestCase
{
  private static $endpoint = 'http://localhost:9400/v1';

  // We can construct a request a request and it is initialised to
  // an empty get request to the supplied endpoint.
  public function testInitialisation()
  {
    $request = Request::request(self::$endpoint);
    $this->assertFalse($request->isSentinel());
    $this->assertEquals($request->find('METHOD'), 'GET');
    $this->assertEquals($request->find('HOST'), self::$endpoint);
  }

  // The last invocation that sets a query parameter is kept
  // each request value is immutable. We rely on structural sharing.
  public function testOverride()
  {
    $request = Request::request(self::$endpoint);
    $req2 = $request->post();
    $this->assertEquals($request->find('METHOD'), 'GET');
    $this->assertEquals($req2->find('METHOD'), 'POST');
  }

  // calling $method sets a value on the requestt that can be queried back with $query
  public function setAndTestValueMethods($method, $query)
  {
    $that = $method('value');
    $this->assertEquals($that->find($query), 'value');
    $this->assertEquals($that->render()[$query], 'value');
  }

  // Test that these methods set the appropriate values on the request
  public function testMethodsThatTakeValueParamters()
  {
    $tests = [['host', 'HOST'], ['body', 'BODY'], ['query', 'QUERY'], ['auth','AUTH']];
    foreach($tests as $test) {
      $this->setAndTestValueMethods([Request::request(self::$endpoint), $test[0]], $test[1]);
    }
  }

  // Calling $method sets the $query request paramter to $expected.
  public function setAndTest($method, $query, $expected)
  {
    $that = $method();
    $this->assertEquals($that->find($query), $expected);
    $this->assertEquals($that->render()[$query], $expected);
  }

  // Test that these methods set the appropriate request parameter to the correct value.
  public function testMethodsThatWrapValueParameters()
  {
    $tests = [['get','METHOD','GET'], ['post','METHOD','POST'], ['put','METHOD','PUT'], ['delete','METHOD','DELETE']];
    foreach($tests as $test) {
      $this->setAndTest([Request::request(self::$endpoint),$test[0]], $test[1], $test[2]);
    }
  }

  // the subpath method has some slightly different semantics
  public function testSetSubPath()
  {
    $request = Request::request(self::$endpoint)->path('/foo');
    $this->assertEquals($request->subpath('/bar')->find('PATH'), '/foo/bar');
  }
}
