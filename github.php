<?php
/**
 * Github OAuth modeled after https://gist.github.com/aaronpk/3612742 
 */

function api($url, $post=FALSE, $headers=array()) {
  $ch = curl_init($url);
  curl_setopt($ch, CURLOPT_RETURNTRANSFER, TRUE);
 
  if ($post) {
    curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($post));
  }
 
  $headers[] = 'Accept: application/json';
 
  if(session('access_token')) {
    $headers[] = 'Authorization: Bearer ' . session('access_token');
  }
 
  curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
 
  $response = curl_exec($ch);
  return json_decode($response);
}

on('GET', '/login', function () {
  if (empty(session('account'))) {
    flash('Please login at Evernote first');
    redirect('/');
  }

  // Generate a random hash and store in the session for security
  session('github_state', hash('sha256', microtime(TRUE).rand().$_SERVER['REMOTE_ADDR']));
  session('github_access_token', NULL);
 
  $params = array(
    'client_id' => config('github.oauth_client_id'),
    'redirect_uri' => strtr(config('github.oauth_callback_url'), array(
      '%schema' => empty($_SERVER['HTTPS']) ? "http" : "https",
      '%host' => $_SERVER['SERVER_NAME'],
    )),
    'scope' => 'repo,user',
    'state' => session('github_state')
  );
 
  // Redirect the user to Github's authorization page
  redirect(config('github.authorize_url') . '?' . http_build_query($params));
});

on('GET', '/callback', function () {
  if (params('code')) {
    $callback_url = strtr(config('github.oauth_callback_url'), array(
      '%schema' => empty($_SERVER['HTTPS']) ? "http" : "https",
      '%host' => $_SERVER['SERVER_NAME'],
    ));
   
    // Verify the state matches our stored state
    if (!params('state') || session('github_state') != params('state')) {
      redirect($callback_url);
    }
   
    // Exchange the auth code for a token
    $token = api(config('github.token_url'), array(
      'client_id' => config('github.oauth_client_id'),
      'client_secret' => config('github.oauth_client_secret'),
      'redirect_uri' => $callback_url,
      'state' => session('github_state'),
      'code' => params('code')
    ));
    session('github_access_token', $token->access_token);
    redirect($callback_url);
  }
  
  if (session('github_access_token')) {
  
    $session_account = session('account');
    $account = ORM::for_table('account')
      ->where_equal('username', $session_account->username
      ->find_one();

    if ($account->github_token != session('github_access_token')) {
      $account->github_token = session('github_access_token');
      $account->updated = time();
      $account->save();
    }

    flash("You have connected to your Github account");
    redirect('/');
  }

});
