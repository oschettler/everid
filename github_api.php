<?php

/**
 * Call REST API, using file_get_contents 
 * and a stream_context for POST data or additional headers.
 *
 * Does not use CURL as libcurl has disabled https for PUT
 * in many installations.
 */
function api($url, $post=FALSE, $headers=array()) {
  $context_opt = array();

  if (strpos($url, ' ') !== FALSE) {
    list($verb, $url) = explode(' ', $url);
  }
  else {
    $verb = 'GET';
  }
 
  //$scheme = parse_url($url, PHP_URL_SCHEME);
  $scheme = 'http'; // This needs to be "http" even for "https"
  $context_opt = array($scheme => array()); 

  if ($post) {
    $context_opt[$scheme] = array(
      'method' => 'POST',
      'content' => json_encode($post),
    );
    $headers[] = 'Content-Type: application/json';
  }
  
  if ($verb != 'GET') {
    $context_opt[$scheme]['method'] = $verb;
  }

  $headers[] = 'Accept: application/json';
  $headers[] = 'User-Agent: ' . config('github.app_name');
 
  if(!empty($_SESSION['access_token'])) {
    $headers[] = 'Authorization: Bearer ' . $_SESSION['access_token'];
  }
 
  $context_opt[$scheme] += array(
    'header' => join('', array_map(function ($h) {
      return "$h\r\n";
    }, $headers))
  );
 
  $context = stream_context_create($context_opt);
  return json_decode(file_get_contents($url, /*include_path*/FALSE, $context));
}


class Github {

  function __construct($token, $username, $repo) {
    $this->token = $token;
    $this->username = $username;
    $this->repo = $repo;
    $this->url_base = config('github.api_url_base');
  }
  
  function mkdir($path) {
    return $this->save($path . '/empty', '-- empty file --');
  }
  
  function save($path, $content, $sha = NULL) {
    // Create _posts, following 
    // http://mdswanson.com/blog/2011/07/23/digging-around-the-github-api-take-2.html

    $params = array(
      'path' => "{$path}",
      'message' => "save {$path}",
      'content' => base64_encode($content),
      'branch' => 'gh-pages',        
    );
    
    if ($sha) {
      $params['sha'] = $sha;
    }

    $info = api(
      'PUT ' . $this->url_base . "repos/{$this->username}/{$this->repo}/contents/{$path}",
      $params,
      array('Authorization: Bearer ' . $this->token)
    );
  }
  
  function branches() {
    return api(
      $this->url_base . "repos/{$this->username}/{$this->repo}/branches",
      /*post*/FALSE,
      array('Authorization: Bearer ' . $this->token)
    );
  }
  
  function createBranch($branch, $branch_from_sha) {
    return api(
      $this->url_base . "repos/{$this->username}/{$this->repo}/git/refs",
      /*post*/array(
        'ref' => 'refs/head/' . $branch,
        'sha' => $branch_from_sha,
      ),
      array('Authorization: Bearer ' . $this->token)
    );
  }
  
  function trees() {
    return api(
      $this->url_base . "repos/{$this->username}/{$this->repo}/git/trees/gh-pages?recursive=1",
      /*post*/FALSE,
      array('Authorization: Bearer ' . $this->token)
    );
  }
}
