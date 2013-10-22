<?php
ini_set('display_errors', TRUE);
error_reporting(-1);

session_start();

require_once 'vendor/autoload.php';

ini_set("include_path", ini_get("include_path") . ":vendor/evernote/evernote/lib");
require_once 'autoload.php';

require_once 'Evernote/Client.php';

ORM::configure('sqlite:db/everid.sqlite');

config('dispatch.views', './views');
config('source', 'settings.ini');

before(function () {
  if (empty($_SESSION['accessToken'])) {
    return;
  }
  
  $accountInfo = array();
  
  foreach (explode(':', $_SESSION['accessToken']) as $field) {
    if (preg_match('/^(\w+)=(.*)$/', $field, $matches)) {
      $accountInfo[$matches[1]] = $matches[2];
    }
  }
  
  $account = ORM::for_table('account')
    ->where_equal('username', $accountInfo['A'])
    ->find_one();

  if ($account) {
    if ($account->token != $_SESSION['accessToken']) {
      $account->token = $_SESSION['accessToken'];
      $account->updated = time();
      $account->save();
    }
  
    $_SESSION['account'] = (object)array(
      'username' => $account->username,
      'name' => $account->name,
      'title' => $account->title,
      'notebook' => $account->notebook,
    );
  }
  else {
    $account = ORM::for_table('account')->create();
    $account->username = $accountInfo['A'];
    $account->evernote_id = base_convert($accountInfo['U'], 16, 10);
    $account->token = $_SESSION['accessToken'];
    $account->created = $account->updated = time();
    $account->save();

    $_SESSION['account'] = (object)array(
      'username' => $account->username,
    );
  }
  
  $missingInfo = FALSE;
  foreach (array('name', 'title', 'notebook') as $field) {
    if (empty($account->{$field})) {
      $missingInfo = TRUE;
    }
  }
  
  if ($_SERVER['REQUEST_URI'] != '/user/edit' && $missingInfo) {
    flash('success', 'Welcome. We need some minimal configuration');
    redirect('/user/edit');
  }
});

on('GET', '/', function () {
  render('index', array(
    'site_name' => config('site.name'),
    'page_title' => config('site.title'),
  ));
});

prefix('/user', function () { include 'user.php'; });

on('GET', '/update', function () {
  //$auth = config('site.auth');
  
  if (empty($_SESSION['accessToken'])) {
    redirect('/');
  }
  $auth = $_SESSION['accessToken'];
  
  $client = new Evernote\Client(array('token' => $auth));
  $store = $client->getNoteStore();
  foreach ($store->listNotebooks() as $notebook) {
    if ($notebook->name == config('site.notebook')) {
      $filter = new EDAM\NoteStore\NoteFilter(array(
        'notebookGuid' => $notebook->guid,
      ));
      $spec = new EDAM\NoteStore\NotesMetadataResultSpec();
      $spec->includeTitle = TRUE;
      $spec->includeCreated = TRUE;
      $spec->includeUpdated = TRUE;
      $spec->includeDeleted = TRUE;
      
      $noteList = $store->findNotesMetadata($auth, $filter, 0, 10, $spec);
      var_dump($noteList);
    }
  }
});

prefix('auth', function () { include 'auth.php'; });

dispatch();
