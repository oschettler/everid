<?php
/**
 * Github OAuth modeled after https://gist.github.com/aaronpk/3612742 
 */

on('GET', '/login', function () {
  if (empty($_SESSION['account'])) {
    flash('Please login at Evernote first');
    redirect('/auth/authorize');
  }

  // Generate a random hash and store in the session for security
  $_SESSION['github_state'] = hash('sha256', microtime(TRUE).rand().$_SERVER['REMOTE_ADDR']);
  $_SESSION['github_access_token'] = NULL;
 
  $params = array(
    'client_id' => config('github.oauth_client_id'),
    'redirect_uri' => strtr(config('github.oauth_callback_url'), array(
      '%schema' => empty($_SERVER['HTTPS']) ? "http" : "https",
      '%host' => $_SERVER['SERVER_NAME'],
    )),
    'scope' => 'repo,user',
    'state' => $_SESSION['github_state']
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
    if (!params('state') || $_SESSION['github_state'] != params('state')) {
      error_log("State mismatch: {$_SESSION['github_state'] } != " . params('state'));
      redirect($callback_url);
    }
   
    // Exchange the auth code for a token
    $token = api(config('github.token_url'), /*POST*/ array(
      'client_id' => config('github.oauth_client_id'),
      'client_secret' => config('github.oauth_client_secret'),
      'redirect_uri' => $callback_url,
      'state' => $_SESSION['github_state'],
      'code' => params('code')
    ));
    if (!$token) {
      error_log("No token");
      redirect($callback_url);
    }
    $_SESSION['github_access_token'] = $token->access_token;
    redirect($callback_url);
  }
  
  if (!empty($_SESSION['github_access_token'])) {
  
    $session_account = $_SESSION['account'];
    $account = ORM::for_table('user')
      ->where_equal('username', $session_account->username)
      ->find_one();

    if ($account->github_token != $_SESSION['github_access_token']) {
      $account->github_token = $_SESSION['github_access_token'];
      $account->updated = time();
      $account->save();
    }

    flash("You have connected to your Github account");
    redirect('/');
  }
  
  if (!empty($_GET['error'])) {
    flash('Error: ' . $_GET['error']);
    //redirect('/');
  }

});
