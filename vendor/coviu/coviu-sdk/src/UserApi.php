<?php

namespace coviu\Api;


class UserApi
{
  private $get;
  private $post;
  private $put;
  private $del;

  public function __construct($service)
  {
    $this->get = $service->get();
    $this->post = $service->json()->post();
    $this->put = $service->json()->put();
    $this->del = $service->delete();
  }

  /*
   * Get the team associted with the authorized used.
   */
  public function getAuthorizedTeam ()
  {
    return $this->get->path('/user/team');
  }
}
