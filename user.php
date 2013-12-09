<?php
/**
 * Recursively transform OPML to YAML
 */
function opml2yaml($opml, $level = 0) {

  $indent = str_repeat(' ', $level);
  $yaml = "";

  $attr = array();
  foreach ($opml->attributes() as $name => $value) {
    $attr[$name] = $value;
  }

  $outline_name = NULL;
  if (isset($attr['text'])) {
    $outline_name = $attr['text'];
    unset($attr['text']);
  }

  $outline_type = NULL;
  if (isset($attr['type'])) {
    $outline_type = $attr['type'];
    unset($attr['type']);
  }

  if ($outline_type != 'list') {
    foreach ($attr as $name => $value) {
      $yaml .= "{$indent}{$name}: {$value}\n";
    }
  }

  foreach ($opml->outline as $child) {
    list($child_name, $child_yaml) = opml2yaml($child, 2);

    if ($outline_type == 'list') {
      $yaml .= "{$indent}- text: {$child_name}\n{$child_yaml}";
    }
    else {
      $yaml .= "{$indent}{$child_name}:\n{$child_yaml}";
    }
  }
  
  return array($outline_name, $yaml);
}

function file_sha($fname, $sha = NULL) {
  static $files;
  
  if ($sha) {
    $files[$fname] = $sha;
  }
  if (empty($files[$fname])) {
    return NULL;
  }
  else {
    return $files[$fname];
  }
}

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
    $account->evernote_id = $evernote_id = base_convert($accountInfo['U'], 16, 10);
    $account->token = $_SESSION['accessToken'];
    $account->created = $account->updated = time();
    $account->save();

    error_log("NEW user username={$user->username}, evernote_id={$evernote_id}\n", 3, '/tmp/everid.log');

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

  foreach (array('name', 'theme', 'notebook', 'github_username', 'github_repo') as $field) {
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
  require_once 'github.php';

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
  $auth = $account->token;

  $github = new Github(
    $account->github_token,
    $account->github_username,
    $account->github_repo
  );
    
  // Check for gh-pages branch
  $master_sha = NULL;
  $gh_pages_sha = NULL;
  
  foreach ($github->branches() as $branch) {
    if ($branch->name == 'gh-pages') {
      $gh_pages_sha = $branch->commit->sha;
    }
    if ($branch->name == 'master') {
      $master_sha = $branch->commit->sha;
    }
  }
  
  if (!$gh_pages_sha) {
    echo "Creating branch \"gh-pages\"{$lf}";
    $branch = $github->createBranch('gh-pages', $master_sha);
    $gh_pages_sha = $branch->object->sha;
  }
    
  $client = new Evernote\Client(array(
    'token' => $auth,
    'sandbox' => config('evernote.sandbox'),
  ));
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
      
      echo "Updating \"{$notebook->name}\"...{$lf}";
      
      $tree = $github->trees();
  
      // Check for all directories in standard Jekyll directory structure
      $dirs = array(
        '_drafts' => NULL, 
        '_includes' => NULL, 
        '_layouts' => NULL, 
        '_posts' => NULL, 
        '_data' => NULL, 
        '_site' => NULL,
      );     

      foreach ($tree->tree as $item) {
        if ($item->type == 'tree') {
          if (array_key_exists($item->path, $dirs)) {
            $dirs[$item->path] = $item->sha;
          } 
        }
        else
        if ($item->type == 'blob') {
          file_sha($item->path, $item->sha);
        }
      }
      
      foreach ($dirs as $dir => $sha) {
        if ($sha === NULL) {
          echo "Creating {$dir}{$lf}";
          $github->mkdir($dir);
        }  
      }
      
      echo "Theme \"{$account->theme}\"{$lf}";
      $theme = dirname(__FILE__) . '/themes/' . $account->theme . '/';
      if (!is_dir($theme)) {
        die("No such theme \"{$theme}\"\n");
      }
      
      if (!file_exists($theme . 'layout.html')) {
        die("No layout {$theme}layout.html");
      }
      $github->save(
        '_layouts/default.html',
        file_get_contents($theme . 'layout.html'),
        file_sha('_layouts/default.html')
      );

      if (file_exists($theme . 'styles.css')) { 
        echo "Styles{$lf}";
        $github->save(
          'styles.css',
          file_get_contents($theme . 'styles.css'),
          file_sha('styles.css')
        );
      }
            
      if (!empty($account->domain)) {
        echo "Will serve domain {$account->domain}{$lf}";
        $github->save(
          'CNAME',
          $account->domain,
          file_sha('CNAME')
        );
      }
      
      $xml = simplexml_load_string($account->config);
      $config = array();
      foreach ($xml->body->outline as $outline) {
        list($name, $yaml) = opml2yaml($outline);

        if ($name == '_config') {
          $fname = '_config.yml';           
        }
        else {
          $fname = '_data/' . preg_replace('/\W+/u', '_', $name) . '.yml';
        }
        echo "Writing {$fname}{$lf}";
        $github->save($fname, $yaml, file_sha($fname));
      }
      
      $noteList = $store->findNotesMetadata($auth, $filter, 0, 10, $spec);
      foreach ($noteList->notes as $remoteNote) {
        $localNote = ORM::for_table('note')
          ->where_equal('guid', $remoteNote->guid)
          ->find_one();
        
        echo "{$remoteNote->title} ... ";
        if (TRUE || !$localNote || $localNote->updated < $remoteNote->updated / 1000) {
          $note = $store->getNote($auth, $remoteNote->guid,
            TRUE, // withContent
            FALSE, // withResourcesData
            FALSE, // withResourcesRecognition
            FALSE // withResourcesAlternateData
          );

          $note->tagNames = $store->getNoteTagNames($auth, $remoteNote->guid);
          
          $url = NULL;
          if (!empty($note->attributes->sourceURL)) {
            $url = $note->attributes->sourceURL;
          }
          else
          foreach ($note->tagNames as $tag) {
            if (preg_match('/^url:(\S+)/i', $tag, $matches)) {
              $url = $matches[1];
            }
          }
          if (!$url) {
            die("You need to set the URL on note {$note->title}");
          }
                  
          if (preg_match('/\.(html|md)$/', $url, $matches)) {
            $fname = $url; 
          }               
          else {
            $fname = $url . '.html';
          }   
          
          echo "({$fname}) ";
                    
          if ($localNote) {
            $diff = $remoteNote->updated / 1000 - $localNote->updated; 
            echo "updated: {$diff}{$lf}";
          }
          else {
            echo "new{$lf}";
          }
          
          $tags = join(' ', $note->tagNames);
          
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

          $github->save($fname, $content, file_sha($fname));

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
          echo "no change{$lf}";
        }
      } // notes
    } // matching notebook
  } // all notebooks
  
});

