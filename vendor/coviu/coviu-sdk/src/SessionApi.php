<?php

namespace coviu\Api;


class SessionApi
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
   * Create a new session
   * @param: session  - {
   *  "session_name": optional display name for the session
   *  "start_time": utc date string,
   *  "end_time": utc date string,
   *  "picture": optional url for room image,
   *  "participants": [{
   *    "display_name": optional string for participant display name,
   *    "picture": option url for participant avatar,
   *    "role": *required* - "guest", or "host",
   *    "state": option content for client use
   *   }, ...]
   * }
   */
  public function createSession ($session)
  {
      return $this->post->path('/sessions')->body($session);
  }

  /*
   * Get a session by id
   * @param: sessionId - string
   */
  public function getSession ($sessionId)
  {
    return $this->get->path('/sessions/')->subpath($sessionId);
  }

  /*
   * Get the a page of sessions
   * @param: query - {
   *    "page": optional number,
   *    "page_size": optional number,
   *    "start_time": optional utc date string,
   *    "end_time": optional utc date string
   *  }
   */
  public function getSessions($query = [])
  {
    return $this->get->path('/sessions')->query($query);
  }

  /*
   * Update a session
   * @param: sessionId - string
   * @param: update - {
   *    "session_name": optional display name for the session
   *    "start_time": optional utc date string,
   *    "end_time": optional utc date string,
   *    "picture": optional utc url for room image,
   *  }
   */
  public function updateSession($sessionId, $update)
  {
    return $this->put->path('/sessions/')->subpath($sessionId)->body($update);
  }

  /*
   * Cancel a session
   * @param: sessionId - string
   */
  public function deleteSession($sessionId)
  {
    return $this->del->path('/sessions/')->subpath($sessionId);
  }

  /*
   * Get the participants of a session
   * @param: sessionId - string
   */
  public function getSessionParticipants($sessionId)
  {
    return $this->get->path('/sessions/')->subpath($sessionId)->subpath('/participants');
  }

  /*
   * Add a participant to a session
   * @param: sessionId - string, the session to add the participant to.
   * @param: participant - {
   *    "display_name": optional string for entry display name,
   *    "picture": optional url for participant avatar,
   *    "role": *required* - "guest", or "host",
   *    "state": optional content for client use
   *  }
   */
  public function addParticipant ($sessionId, $participant)
  {
    return $this->post->path('/sessions/')->subpath($sessionId)->subpath('/participants')->body($participant);
  }

  /*
   * Get a participant by id.
   * @param: participantId - string, the id of the participatn.
   */
  public function getParticipant ($participantId)
  {
    return $this->get->path('/participants/')->subpath($participantId);
  }

  /*
   * Update a participant
   * @param: participantId - string
   * @param: update - {
   *    "display_name": optional string for entry display name,
   *    "picture": optional url for participant avatar,
   *    "role":  optional "guest", or "host",
   *    "state": optional content for client use
   *  }
   */
  public function updateParticipant($participantId, $update)
  {
    return $this->put->path('/participants/')->subpath($participantId)->body($update);
  }

  /*
   * Remove a participant.
   * @param: particpantId - string, the id of the participant
   */
  public function deleteParticipant($participantId)
  {
    return $this->del->path('/participants/')->subpath($participantId);
  }
}
