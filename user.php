<?php
/**
 * Routes for prefix "/user"
 */
function navigation($tree, $lv = 0) {
  $result = '<ul class="level-' . $lv . '">';
  
  foreach ($tree as $item) {
    $result .= strtr('<li><a href="%link">%title</a></li>', array(
      '%link' => $item->link,
      '%title' => $item->title,
    ));
    if (!empty($item->_)) {
      $result .= navigation($item->_, $lv + 1);
    }
  }
  
  $result .= '</ul>';
  return $result;
}
 
before(function ($method, $path) {
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
  
  // var_dump(array('BEFORE' => $account));
  
  $missingInfo = FALSE;
  foreach (array('name', 'title', 'notebook') as $field) {
    if (empty($account->{$field})) {
      $missingInfo = TRUE;
    }
  }
  
  if ($path != '/user/edit' && $missingInfo) {
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

  $notebooks = array();
  $client = new Evernote\Client(array('token' => $_SESSION['accessToken']));
  foreach ($client->getNoteStore()->listNotebooks() as $notebook) {
    $notebooks[$notebook->guid] = $notebook->name; 
  }
  
  $navigation = $account->navigation;
  if (empty($navigation)) {
    $navigation = '<?xml version="1.0" encoding="UTF-8"?><opml version="2.0"><head><title>Navigation</title></head><body><outline text=""/></body></opml>';
  }

  render('edit', array(
    'name' => $account->name,
    'title' => $account->title,
    'notebook' => $account->notebook,
    'notebooks' => $notebooks,
    //'nav_tree' => navigation(json_decode($account->navigation)),
    'navigation' => json_encode($navigation),

    'site_name' => config('site.name'),
    'page_title' => config('site.title'),
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

  foreach (array('name', 'title', 'notebook') as $field) {
    $account->{$field} = $_POST[$field];
  }
  //$account->navigation = preg_replace('/&quot;/', '"', $_POST['navigation']);
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
  echo $account->navigation;
});

on('POST', '/nav-save', function () {
  $account = ORM::for_table('account')
    ->where_equal('token', $_SESSION['accessToken'])
    ->find_one();

  $account->navigation = $_POST['opml'];
  $account->save();
  echo json_encode('OK');
});

on('GET', '/update', function () {
  //$auth = config('site.auth');
  
  if (empty($_SESSION['accessToken'])) {
    redirect('/');
  }
  
  //header('Content-type: text/plain');
  
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
      
      $dir = dirname(__FILE__) . '/../sites/' . $notebook->name . '/';
      
      $noteList = $store->findNotesMetadata($auth, $filter, 0, 10, $spec);
      foreach ($noteList->notes as $remoteNote) {
        $localNote = ORM::for_table('note')
          ->where_equal('guid', $remoteNote->guid)
          ->find_one();
        
        echo "{$remoteNote->title} ... ";
        if (TRUE || !$localNote || $localNote->updated < $remoteNote->updated / 1000) {
          if ($localNote) {
            $diff = $remoteNote->updated / 1000 - $localNote->updated; 
            echo "updated: {$diff}<br>\n";
          }
          else {
            echo "new<br>\n";
          }
          
          $note = $store->getNote($auth, $remoteNote->guid,
            TRUE, // withContent
            FALSE, // withResourcesData
            FALSE, // withResourcesRecognition
            FALSE // withResourcesAlternateData
          );

          if (empty($note->attributes->sourceURL)) {
            die("You need to set the URL on note {$note->title}");
          }
          
          $note->tagNames = $store->getNoteTagNames($auth, $remoteNote->guid);
          $tags = join(' ', $note->tagNames);
          
          $fname = $dir . $note->attributes->sourceURL . '.html';
          @mkdir(dirname($fname), 0775, /*recursive*/TRUE);
          
          $content = "---
title: {$note->title}
layout: default
tags: {$tags}
---
";

          $noteContent = simplexml_load_string($note->content);
          foreach ($noteContent->children() as $c) {
            $el = $c->asXML();
            $content .= $el . "\n";
            /*
            if (preg_match('#^<div>(.*)</div>$#', $el, $matches)) {
              $content .= $matches[1] . "\n";
            }
            else {
              $content .= $el . "\n";
            }
            */
          }
          file_put_contents($fname, $content);
          
          $now = time();
          if (!$localNote) {
            $localNote = ORM::for_table('note')->create();
            $localNote->created = $now;
          }
          $localNote->guid = $note->guid;
          $localNote->title = $note->title;
          $localNote->structure = json_encode((object)$note);
          $localNote->updated = $now;
          
          $localNote->save();
        }
        else {
          echo "no change<br>\n";
        }
      }
    }
  }
  
});

