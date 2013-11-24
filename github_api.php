<?php

function github_api($url, $post=FALSE, $headers=array()) {
  $ch = curl_init($url);
  curl_setopt($ch, CURLOPT_RETURNTRANSFER, TRUE);
 
  if ($post) {
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($post));
  }
  
  if (strpos($url, ' ') !== FALSE) {
    list($verb, $url) = explode(' ', $url);
    if ($verb == 'PUT') {
      curl_setopt($ch, CURLOPT_PUT, TRUE);
      file_put_contents('curl.json', json_encode($post));
      curl_setopt($ch, CURLOPT_INFILE, fopen('curl.json', 'r'));  
    }
  }
 
  $headers[] = 'Accept: application/json';
  $headers[] = 'User-Agent: ' . config('github.app_name');
 
  if(!empty($_SESSION['access_token'])) {
    $headers[] = 'Authorization: Bearer ' . $_SESSION['access_token'];
  }
 
  curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
  curl_setopt($ch, CURLOPT_VERBOSE, TRUE);
 
  $response = curl_exec($ch);
  return json_decode($response);
}

class Github {

  function __construct($token, $username, $repo) {
    $this->token = $token;
    $this->username = $username;
    $this->repo = $repo;
    $this->url_base = config('github.api_url_base');
  }
  
  function mkdir($path, $parent) {
  
    // Create _posts, following 
    // http://mdswanson.com/blog/2011/07/23/digging-around-the-github-api-take-2.html

    $info = github_api(
      'PUT ' . $this->url_base . "repos/{$this->username}/{$this->repo}/contents/{$path}/empty",
      /*post*/array(
        'path' => "{$path}/empty",
        'message' => "mkdir {$path}",
        'content' => '',
        'branch' => 'gh-pages',        
      ),
      array('Authorization: Bearer ' . $this->token)
    );
    
    var_dump($info); exit;
    
    $branch_sha = $info->object->sha;
    
    $info = github_api(
      $this->url_base . "repos/{$this->username}/{$this->repo}/git/commits/{$info->object->sha}",
      /*post*/FALSE,
      array('Authorization: Bearer ' . $this->token)
    );
  
    $info = github_api(
      $this->url_base . "repos/{$this->username}/{$this->repo}/git/trees",
      /*post*/array(
        'base_tree' => $info->tree->sha,
        'tree' => array(
          'path' => $path . '/empty',
          'mode' => '100644',
          'type' => 'tree',
          'sha' => $branch_sha,
          'content' => '',
        )
      ),
      array('Authorization: Bearer ' . $this->token)
    );
    
    var_dump($info);
    exit;
    
  }
  
  function save($path) {
    
  }
  
  function branches() {
    return github_api(
      $this->url_base . "repos/{$this->username}/{$this->repo}/branches",
      /*post*/FALSE,
      array('Authorization: Bearer ' . $this->token)
    );
  }
  
  function createBranch($branch, $branch_from_sha) {
    return github_api(
      $this->url_base . "repos/{$this->username}/{$this->repo}/git/refs",
      /*post*/array(
        'ref' => 'refs/head/' . $branch,
        'sha' => $branch_from_sha,
      ),
      array('Authorization: Bearer ' . $this->token)
    );
  }
  
  function trees() {
    return github_api(
      $this->url_base . "repos/{$this->username}/{$this->repo}/git/trees/gh-pages?recursive=1",
      /*post*/FALSE,
      array('Authorization: Bearer ' . $this->token)
    );
  }
}
