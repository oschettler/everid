<?php
/**
 * Transform OPML to YAML
 */
function opml2yaml($opml, $level = 0) {

  $indent = str_repeat(' ', $level);
  $yaml = '';
  foreach ($opml as $outline) {
    $yaml .= "{$indent}-\n";    

    foreach ($outline->attributes() as $name => $value) {
      $yaml .= "{$indent}  {$name}: {$value}\n";
    }

    if (count($outline->outline)) {
      $yaml .= opml2yaml($outline->outline, 2);
    }
  }
  
  return $yaml;
}

/**
 * Routes for prefix "/user"
 */
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
  
  $config = $account->config;
  if (empty($config)) {
    $config = '<?xml version="1.0" encoding="UTF-8"?><opml version="2.0"><head><title>Config</title></head><body><outline text=""/></body></opml>';
  }

  render('edit', array(
    'name' => $account->name,
    'title' => $account->title,
    'notebook' => $account->notebook,
    'notebooks' => $notebooks,
    'config' => json_encode($config),

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
  if (empty($_SESSION['accessToken'])) {
    flash('error', 'Not logged in');
    redirect('/');
  }
  
  //var_dump(array('BEFORE' => $account)); exit;
  
  $auth = $_SESSION['accessToken'];
    $account = ORM::for_table('account')
    ->where_equal('token', $auth)
    ->find_one();
  
  //header('Content-type: text/plain');
  
  $sites_dir = config('sites');
  if (empty($sites_dir)) {
    $sites_dir = dirname(__FILE__) . '/../sites';
  }
  
  $client = new Evernote\Client(array('token' => $auth));
  $store = $client->getNoteStore();
  foreach ($store->listNotebooks() as $notebook) {
    if ($notebook->guid == $account->notebook) {
      $filter = new EDAM\NoteStore\NoteFilter(array(
        'notebookGuid' => $notebook->guid,
      ));
      $spec = new EDAM\NoteStore\NotesMetadataResultSpec();
      $spec->includeTitle = TRUE;
      $spec->includeCreated = TRUE;
      $spec->includeUpdated = TRUE;
      $spec->includeDeleted = TRUE;
      
      $dir = $sites_dir . '/' . $notebook->name . '/';
      echo "Updating \"{$notebook->name}\" in \"{$dir}\"...\n";
      @mkdir($dir, 0775, /*recursive*/TRUE);
      if (!is_dir($dir)) {
        die("Can't write to {$dir}");
      }
      
      // Prepare Jekyll directory structure
      foreach (array('_drafts', '_includes', '_layouts', '_posts', '_data', '_site') as $subdir) {
        @mkdir($dir . $subdir, 0775);
      }
      
      $theme = dirname(__FILE__) . '/themes/' . $account->theme . '/';
      if (!is_dir($theme)) {
        die("No such theme \"{$theme}\"\n");
      }
      
      if (!copy($theme . 'layout.html', $dir . '_layouts/default.html')) {
        die("Can't copy layout\n");
      }
      
      if (!copy($theme . 'styles.css', $dir . 'styles.css')) {
        die("Can't copy styles\n");
      }

      $xml = simplexml_load_string($account->config);
      $config = array();
      foreach ($xml->body->outline as $outline) {
        list($name, $yaml) = opml2yaml($outline);

        if ($name == '_config') {
          $fname = $dir . '_config.yml';           
        }
        else {
          $fname = $dir . '_data/' . preg_replace('/\W+/u', '_', $name) . '.yml';
        }
        file_put_contents($fname, $yaml);
      }
      
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

