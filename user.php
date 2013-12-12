<?php
/**
 * Routes for prefix "/user"
 */
before(function ($method, $path) {
  if (in_array($path, array('auth/logout', 'user/nav-open', 'user/nav-save'))) {
    return;
  }

  if (empty($_SESSION['accessToken'])) {
    return;
  }
  
  $accountInfo = array();
  
  foreach (explode(':', $_SESSION['accessToken']) as $field) {
    if (preg_match('/^(\w+)=(.*)$/', $field, $matches)) {
      $accountInfo[$matches[1]] = $matches[2];
    }
  }
  
  error_log(json_encode($accountInfo) . "\n", 3, '/tmp/everid.log');
  
  $user = NULL;
  $client = new Evernote\Client(array(
    'token' => $_SESSION['accessToken'],
    'sandbox' => config('evernote.sandbox'),
  ));
  
  try {
    $user = $client->getUserStore()->getUser();
    error_log("USER: " . json_encode($user) . "\n", 3, '/tmp/everid.log');
  }
  catch (Exception $e) {
    die("NO USER: " . json_encode($e));
  }
      
  $account = ORM::for_table('account')
    ->where_equal('username', $user->username)
    ->find_one();

  if ($account) {
  
    error_log("FOUND user {$user->username}\n", 3, '/tmp/everid.log');
    if ($account->token != $_SESSION['accessToken']) {
      error_log("New token {$_SESSION['accessToken']}. Previous: {$account->token}\n", 3, '/tmp/everid.log');
      $account->evernote_id = $user->id;
      $account->token = $_SESSION['accessToken'];
      $account->updated = time();
      $account->save();
    }
  
    $_SESSION['account'] = (object)array(
      'username' => $account->username,
      'evernote_id' => $account->evernote_id,
      'name' => $account->name,
      'notebook' => $account->notebook,
    );
  }
  else {
    $account = ORM::for_table('account')->create();
    $account->username = $user->username;
    $account->evernote_id = $user->id;
    $account->token = $_SESSION['accessToken'];
    $account->created = $account->updated = time();
    $account->save();

    error_log("NEW user username={$user->username}, evernote_id={$account->evernote_id}\n", 3, '/tmp/everid.log');

    $_SESSION['account'] = (object)array(
      'username' => $account->username,
    );
  }
  
  // var_dump(array('BEFORE' => $account));
  
  $missingInfo = FALSE;
  foreach (array('name', 'notebook') as $field) {
    if (empty($account->{$field})) {
      $missingInfo = TRUE;
    }
  }
  
  if ($path != 'user/edit' && $missingInfo) {
    error_log("PATH {$path}\n", 3, '/tmp/everid.log');
    flash('success', 'Welcome. We need some minimal configuration');
    redirect('/user/edit');
  }
});

on('GET', '/edit', function () {
  if (empty($_SESSION['accessToken'])) {
    flash('error', 'Not logged in');
    redirect('/');
  }
  
  //var_dump(array('BEFORE' => $account)); exit;
  
  $account = ORM::for_table('account')
    ->where_equal('token', $_SESSION['accessToken'])
    ->find_one();

  $themes = array();
  foreach (ORM::for_table('theme')
    ->find_many() as $theme) {
    $themes[$theme->name] = $theme->title;
  }

  $notebooks = array();
  $client = new Evernote\Client(array(
    'token' => $_SESSION['accessToken'],
    'sandbox' => config('evernote.sandbox'),
  ));
  
  try {
    foreach ($client->getNoteStore()->listNotebooks() as $notebook) {
      $notebooks[$notebook->guid] = $notebook->name; 
    }
  }
  catch (Exception $e) {
    $notebooks['error'] = "Could not retrieve notebooks";
    error_log(json_encode($e) . "\n", 3, '/tmp/everid.log');
    ob_start();
    debug_print_backtrace();
    error_log(ob_get_clean() . "\n", 3, '/tmp/everid.log');
  }
  
  $config = $account->config;
  if (empty($config)) {
    $config = '<?xml version="1.0" encoding="UTF-8"?><opml version="2.0"><head><title>Config</title></head><body><outline text=""/></body></opml>';
  }

  render('edit', array(
    'site_name' => 'EverID',
    'page_title' => 'Edit User',
    'name' => $account->name,
    'notebook' => $account->notebook,
    'notebooks' => $notebooks,
    'theme' => $account->theme,
    'themes' => $themes,
    'domain' => $account->domain,
    'github_username' => $account->github_username,
    'github_repo' => $account->github_repo,
    'config' => json_encode($config),
  ));
});

on('POST', '/edit', function () {

  if (empty($_SESSION['accessToken'])) {
    flash('error', 'Not logged in');
    redirect('/');
  }

  $account = ORM::for_table('account')
    ->where_equal('token', $_SESSION['accessToken'])
    ->find_one();

  foreach (array('name', 'theme', 'notebook', 'domain', 'github_username', 'github_repo') as $field) {
    if (!empty($_POST[$field])) {
      $account->{$field} = $_POST[$field];
    }
  }
  $account->save();
  flash('success', 'Account has been saved');
  //redirect('/user/edit');
  echo "OK";
});

on('POST', '/nav-open', function () {
  $account = ORM::for_table('account')
    ->where_equal('token', $_SESSION['accessToken'])
    ->find_one();
  header('Content-type: text/xml; charset=UTF-8');
  if (!$account->config) {
    echo "<?xml version=\"1.0\" encoding=\"UTF-8\"?><opml version=\"2.0\"><head><title>Configuration</title><expansionState>2</expansionState></head><body><outline text=\"_config\" name=\"My Site\"></outline><outline text=\"navigation\" type=\"list\"><outline text=\"Home\" url=\"./\"/><outline text=\"About\" url=\"./about.html\"/></outline></body></opml>";
  }
  echo $account->config;
});

on('POST', '/nav-save', function () {
  $account = ORM::for_table('account')
    ->where_equal('token', $_SESSION['accessToken'])
    ->find_one();

  $account->config = $_POST['opml'];
  $account->save();
  echo json_encode('OK');
});

/**
 * Update a site for the currently logged-in user
 */
on('GET', '/update', function () {
  require_once 'update.php';

  if (!empty($_SERVER['argv'][1])) {
    /*
     * Called with a username. Get the Evernote token from the database
     */
    header('Content-type: text/plain; charset=UTF-8');
    $lf = "\n";
    
    $u = $_SERVER['argv'][1];
    echo "Updating for user {$u}\n";
    $account = ORM::for_table('account')
      ->where_equal('username', $u)
      ->find_one();
  }
  else {
    /*
     * Called with an authenticated session. Get the matching user from the database
     */
    header('Content-type: text/html; charset=UTF-8');
    $lf = "<br>\n";

    if (empty($_SESSION['accessToken'])) {
      flash('error', 'Not logged in');
      redirect('/');
    }
    
    //var_dump(array('BEFORE' => $account)); exit;
    
    $auth = $_SESSION['accessToken'];
    $account = ORM::for_table('account')
      ->where_equal('token', $auth)
      ->find_one();
  }
  
  if (!$account) {
    die("No such user {$account}\n");
  }
  
  list($status, $msg) = update($account);
  
  if ($status == 'success') {
    echo join($lf, $msg);
  } 
  else {
    echo "ERROR: {$msg}";
  }
});

