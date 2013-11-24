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
  foreach (array('name', 'notebook') as $field) {
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

  $themes = array();
  foreach (ORM::for_table('theme')
    ->find_many() as $theme) {
    $themes[$theme->name] = $theme->title;
  }

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
    'site_name' => 'EverID',
    'page_title' => 'Edit User',
    'name' => $account->name,
    'notebook' => $account->notebook,
    'notebooks' => $notebooks,
    'theme' => $account->theme,
    'themes' => $themes,
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

  foreach (array('name', 'theme', 'notebook') as $field) {
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
  require_once 'github.php';

  if (!empty($_SERVER['argv'][1])) {
    /*
     * Called with a username. Get the Evernote token from the database
     */
    header('Content-type: text/plain; charset=UTF-8');
    $lf = "\n";
    
    $u = $_SERVER['argv'][1];
    echo "Updating fur user {$u}\n";
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
        if ($item->type == 'tree' && array_key_exists($item->path, $dirs)) {
          $dirs[$item->path] = $item->sha;
        }
      }
      
      foreach ($dirs as $dir => $sha) {
        if ($sha === NULL) {
          echo "Creating {$dir}{$lf}";
          var_dump($github->mkdir($dir, $gh_pages_sha));
        }  
      }
      
      exit;
      
      $theme = dirname(__FILE__) . '/themes/' . $account->theme . '/';
      if (!is_dir($theme)) {
        die("No such theme \"{$theme}\"\n");
      }
      
      if (!copy($theme . 'layout.html', $dir . '_layouts/default.html')) {
        die("Can't copy layout\n");
      }
      else {
        $out = array(); exec("cd '{$dir}'; git add _layouts/default.html", $out, $status);
        echo "LAYOUT: " . join('<br>', $out) . '<br>';
        if (FALSE && $status) {
          die("Command failed: " . join('<br>', $out) . "\n");
        }
      }
      
      if (file_exists($theme . 'styles.css') 
        && !copy($theme . 'styles.css', $dir . 'styles.css')) {

        die("Can't copy styles\n");
      }
      else {
        $out = array(); exec("cd '{$dir}'; git add styles.css", $out, $status);
        echo "STYLES: " . join('<br>', $out) . '<br>';
        if (FALSE && $status) {
          die("Command failed: " . join('<br>', $out) . "\n");
        }
      }
      
      if (!empty($account->domain)) {
        echo "Will serve domain {$account->domain}<br>\n";
        file_put_contents($dir . 'CNAME', $account->domain);
        $out = array(); exec("cd '{$dir}'; git add CNAME", $out, $status);
        echo "CNAME: " . join('<br>', $out) . '<br>';
        if (FALSE && $status) {
          die("Command failed: " . join('<br>', $out) . "\n");
        }
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
        echo "Writing {$fname}<br>\n";
        file_put_contents($fname, $yaml);
        $out = array(); exec("cd '{$dir}'; git add {$fname}", $out, $status);
        echo "ADD: " . join('<br>', $out) . '<br>';
        if (FALSE && $status) {
          die("Command failed: " . join('<br>', $out) . "\n");
        }
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
          $out = array(); exec("cd '{$dir}'; git add {$fname}", $out, $status);
          echo "ADD: " . join('<br>', $out) . '<br>';
          if (FALSE && $status) {
            die("Command failed: " . join('<br>', $out) . "\n");
          }
          
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
      } // notes
      
      $out = array(); exec(strftime("cd '{$dir}'; git commit -a -m 'Update %Y-%d-%m %H:%M:%S' 2>&1"), $out, $status);
      echo "COMMIT: " . join('<br>', $out) . '<br>';
      if (FALSE && $status) {
        die("Command failed: " . join('<br>', $out) . "\n");
      }      

      $out = array(); exec(strftime("cd '{$dir}'; GIT_SSH='{$dir}ssh.sh' git push origin 2>&1"), $out, $status);
      echo "PUSH: " . join('<br>', $out) . '<br>';
      if (FALSE && $status) {
        die("Command failed: " . join('<br>', $out) . "\n");
      }      
    } // matching notebook
  } // all notebooks
  
});

