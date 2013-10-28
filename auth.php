<?php
/**
 * Routes for prefix "/auth"
 */

on('GET', '/callback', function () {

  if (isset($_GET['oauth_verifier'])) {
    $_SESSION['oauthVerifier'] = $_GET['oauth_verifier'];

    if (isset($_SESSION['accessToken'])) {
      flash('error', 'Temporary credentials may only be exchanged for token credentials once');
      error_log($m);
      redirect('/');
    }

    try {
      $client = new Evernote\Client(array(
        'consumerKey' => config('oauth.consumer_key'),
        'consumerSecret' => config('oauth.consumer_secret'),
        'sandbox' => config('evernote.sandbox')
      ));
      $accessTokenInfo = $client->getAccessToken(
        $_SESSION['requestToken'], 
        $_SESSION['requestTokenSecret'], 
        $_SESSION['oauthVerifier']
      );
      if ($accessTokenInfo) {
        $_SESSION['accessToken'] = $accessTokenInfo['oauth_token'];
        
        error_log("ACCESS TOKEN: " . $_SESSION['accessToken']);
        // The authenticated action

        flash('success', 'Welcome back');
        redirect('/user/update');
      } 
      else {
        flash('error', 'Failed to obtain token credentials.');
      }
    } 
    catch (OAuthException $e) {
      flash('error', 'Error obtaining token credentials: ' . $e->getMessage());
    }

  }
  else {
    flash('error', 'Content owner did not authorize the temporary credentials');
  }
  redirect('/');
});

on('GET', '/authorize', function () {

  try {
    $client = new Evernote\Client(array(
      'consumerKey' => config('oauth.consumer_key'),
      'consumerSecret' => config('oauth.consumer_secret'),
      'sandbox' => config('evernote.sandbox')
    ));

    $requestTokenInfo = $client->getRequestToken(      
      strtr(config('oauth.callback_url'), array(
        '%schema' => empty($_SERVER['HTTPS']) ? "http" : "https",
        '%host' => $_SERVER['SERVER_NAME'],
      ))
    );
    if ($requestTokenInfo) {
      $_SESSION['requestToken'] = $requestTokenInfo['oauth_token'];
      $_SESSION['requestTokenSecret'] = $requestTokenInfo['oauth_token_secret'];

      redirect($client->getAuthorizeUrl($_SESSION['requestToken']));
    } 
    else {
      flash('error', 'Failed to obtain temporary credentials.');
    }
  } 
  catch (OAuthException $e) {
    flash('error', 'Error obtaining temporary credentials: ' . $e->getMessage());
  }
  redirect('/');
});

on('GET', '/logout', function () {

  unset($_SESSION['account']);

  unset($_SESSION['requestToken']);
  unset($_SESSION['requestTokenSecret']);
  unset($_SESSION['oauthVerifier']);
  unset($_SESSION['accessToken']);
  unset($_SESSION['accessTokenSecret']);
  unset($_SESSION['tokenExpires']);
  
  flash('success', 'You are now logged out');
  redirect('/');
});
