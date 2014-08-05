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
      
  $account = ORM::for_table('user')
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
      'id' => $account->id,
      'evernote_id' => $account->evernote_id,
    );
  }
  else {
    $account = ORM::for_table('user')->create();
    $account->username = $user->username;
    $account->evernote_id = $user->id;
    $account->token = $_SESSION['accessToken'];
    $account->created = $account->updated = time();
    $account->save();

    error_log("NEW user username={$user->username}, evernote_id={$account->evernote_id}\n", 3, '/tmp/everid.log');

    $_SESSION['account'] = (object)array(
      'username' => $account->username,
      'id' => $account->id,
    );
  }
  
  // var_dump(array('BEFORE' => $account));
  $missingInfo = FALSE;
  /*
  foreach (array('name', 'notebook') as $field) {
    if (empty($account->{$field})) {
      $missingInfo = TRUE;
    }
  }
  */
  
  
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
  
  $user = ORM::for_table('user')
    ->where('id', $_SESSION['account']->id)
    ->find_one();

  $sites = ORM::for_table('site')
    ->where('user_id', $_SESSION['account']->id)
    ->find_many();

  render('sites', array(
    'page_title' => 'Sites',
    'email' => $user->email, 
    'sites' => $sites,
  ));
});

on('POST', '/email', function () {
  if (empty($_SESSION['accessToken'])) {
    flash('error', 'Not logged in');
    redirect('/');
  }
  
  $user = ORM::for_table('user')
    ->where('id', $_SESSION['account']->id)
    ->find_one();

  $user->email = params('email');
  $user->updated = time();
  $user->save();
  
  flash('success', 'Email address saved.');
  redirect('/user/edit');
});

function render_site_form($site) {
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
  
  $config = $site->config;
  if (empty($config)) {
    $config = '<?xml version="1.0" encoding="UTF-8"?><opml version="2.0"><head><title>Config</title></head><body><outline text=""/></body></opml>';
  }

  render('edit', array(
    'id' => $site->id,
    'page_title' => empty($site->name) ? 'Add site' : 'Edit site',
    'name' => $site->name,
    'notebook' => $site->notebook,
    'notebooks' => $notebooks,
    'theme' => $site->theme,
    'themes' => $themes,
    'domain' => $site->domain,
    'github_username' => $site->github_username,
    'github_repo' => $site->github_repo,
    'config' => json_encode($config),
  ));  
}

on('GET', '/site/add', function () {
  if (empty($_SESSION['accessToken'])) {
    flash('error', 'Not logged in');
    redirect('/');
  }
  
  render_site_form((object)array(
    'id' => 'NONE',
    'name' => '',
    'notebook' => '',
    'theme' => '',
    'domain' => '',
    'github_username' => '',
    'github_repo' => '',
    'config' => '',
  ));
  
});

on('GET', '/site/:id', function () {
  if (empty($_SESSION['accessToken'])) {
    flash('error', 'Not logged in');
    redirect('/');
  }
  
  //var_dump(array('BEFORE' => $account)); exit;
  
  $site = ORM::for_table('site')
    ->where_equal('id', params('id'))
    ->find_one();
  
  render_site_form($site);

});

function save_site($site) {
  foreach (array('name', 'theme', 'notebook', 'domain', 'github_username', 'github_repo') as $field) {
    if (!empty($_POST[$field])) {
      $site->{$field} = $_POST[$field];
    }
  }
  
  $site->user_id = $_SESSION['account']->id;
  
  $site->save();
}

on('POST', '/site/add', function () {

  if (empty($_SESSION['accessToken'])) {
    flash('error', 'Not logged in');
    redirect('/');
  }

  $site = ORM::for_table('site')->create();
  $site->created = $site->updated = time();

  save_site($site);

  json_out(array('id' => $site->id, 'status' => 'added'));
});

on('POST', '/site/:id', function () {

  if (empty($_SESSION['accessToken'])) {
    flash('error', 'Not logged in');
    redirect('/');
  }

  $site = ORM::for_table('site')
    ->where_equal('id', params('id'))
    ->find_one();
    
  $site->updated = time();

  save_site($site);

  json_out(array('id' => $site->id, 'status' => 'saved'));
});

on('POST', '/nav-open', function () {
  $id = params('id');
  
  if ($id == 'NONE') {
    $site = ORM::for_table('site')->create();
  }
  else {
    $site = ORM::for_table('site')
      ->where('id', $id)
      ->find_one();
  }

  header('Content-type: text/xml; charset=UTF-8');
  if (!$site->config) {
    echo "<?xml version=\"1.0\" encoding=\"UTF-8\"?><opml version=\"2.0\"><head><title>Configuration</title><expansionState>2</expansionState></head><body><outline text=\"_config\" name=\"My Site\"></outline><outline text=\"navigation\" type=\"list\"><outline text=\"Home\" url=\"./\"/><outline text=\"About\" url=\"./about.html\"/></outline></body></opml>";
  }
  echo $site->config;
});

on('POST', '/nav-save', function () {
  error_log("NAV-SAVE id=" . params('id') . "\n", 3, '/tmp/everid.log');
  $site = ORM::for_table('site')
    ->where('id', params('id'))
    ->find_one();

  $site->config = $_POST['opml'];
  $site->save();
  echo json_encode('OK');
});

on('POST', '/del-site/:id', function () {
  $id = params('id');
  $site = ORM::for_table('site')
    ->where('id', $id)
    ->find_one();
  if (!$site) {
    error(500, 'No such site');
  }
  $site->delete();
  json_out(array('success', "Site #{$id} deleted"));
});

/**
 * Update sites for the currently logged-in user
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
    $account = ORM::for_table('user')
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
    $account = ORM::for_table('user')
      ->where_equal('token', $auth)
      ->find_one();
  }
  
  if (!$account) {
    die("No such user {$account}\n");
  }
  
  $sites = ORM::for_table('site')
    ->where('user_id', $account->id)
    ->find_many();
  
  foreach ($sites as $site) {
    list($status, $msg) = update($account->token, $site);
  
    if ($status == 'success') {
      echo join($lf, $msg);
    } 
    else {
      echo "ERROR: {$msg}";
    }
  }
});

