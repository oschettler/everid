<?php
ini_set('display_errors', TRUE);
error_reporting(-1);

session_start();

require_once 'vendor/autoload.php';

ini_set("include_path", ini_get("include_path") . ":vendor/evernote/evernote/lib");
require_once 'autoload.php';

require_once 'Evernote/Client.php';

config('dispatch.views', './views');
config('source', 'settings.ini');

on('GET', '/', function () {
  render('index', array(
    'site_name' => 'EverID',
    'page_title' => 'Publish<br>from Evernote<br>to the web'
  ));
});

on('GET', '/update', function () {
  $auth = config('site.auth');
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

prefix('auth', function () {

  on('GET', '/callback', function () {
    require_once 'evernote_auth.php';
  
    if (handleCallback()) {
        if (getTokenCredentials()) {
            listNotebooks();
        }
    }
  });
  
  on('GET', '/authorize', function () {
    require_once 'evernote_auth.php';

    if (getTemporaryCredentials()) {
        // We obtained temporary credentials, now redirect the user to evernote.com to authorize access
        header('Location: ' . getAuthorizationUrl());
    }
  });

  on('GET', '/reset', function () {
    require_once 'evernote_auth.php';

    resetSession();
  });

});

dispatch();
