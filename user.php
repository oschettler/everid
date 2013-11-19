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
  if (!empty($_SERVER['argv'][1])) {
    /*
     * Called with a username. Get the token from the database
     */
    $u = $_SERVER['argv'][1];
    echo "Updating fur user {$u}\n";
    $account = ORM::for_table('account')
      ->where_equal('username', $u)
      ->find_one();
    if (!$account) {
      die("No such user {$account}\n");
    }
    $auth = $account->token;
  }
  else {
    /*
     * Called with an authenticated session. Get the matching user from the database
     */
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

  
  header('Content-type: text/html; charset=UTF-8');
  
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
      
      $dir = $sites_dir . '/' . $account->username . '/' . $notebook->name . '/';
      echo "Updating \"{$notebook->name}\" in \"{$dir}\"...<br>\n";

      exec("rm -rf '{$dir}'");
      @mkdir($dir . '.ssh', 0775, /*recursive*/TRUE);
      if (!is_dir($dir)) {
        die("Can't write to {$dir}");
      }

      if (!empty($account->ssh_private)) {
        file_put_contents($dir . '.ssh/id_rsa', $account->ssh_private);
        chmod($dir . '.ssh/id_rsa', 0600);
        file_put_contents($dir . '.ssh/id_rsa.pub', $account->ssh_public);
      }
      
      file_put_contents($dir . 'ssh.sh', "#!/bin/bash\nexport HOME='{$dir}'\nssh \"$@\"\n");
      chmod("{$dir}ssh.sh", 0700);
      //putenv("HOME='{$dir}'");
      //putenv("GIT_SSH='{$dir}ssh.sh'");
      
      // XXX The following will fail if the remote repo does not yet contain a branch "gh-pages"
      $out = array(); exec("cd '{$dir}'; git init; git remote add origin '{$account->git_repo}' 2>&1", $out, $status);
      echo join('<br>', $out) . '<br>';
      if (FALSE && $status) {
        die("{$cmd} failed: " . join('<br>', $out) . "\n");
      }
      
      $out = array(); exec("cd '{$dir}'; GIT_SSH='{$dir}ssh.sh' git fetch 2>&1", $out, $status);
      echo `ls -la '{$dir}ssh.sh'`, "\n";
      echo "FETCH: " . join('<br>', $out) . '<br>';
      if (FALSE && $status) {
        die("{$cmd} failed: " . join('<br>', $out) . "\n");
      }

      $out = array(); exec("cd '{$dir}'; git checkout gh-pages 2>&1", $out, $status);
      echo "CHECKOUT: " . join('<br>', $out) . '<br>';
      if (FALSE && $status) {
        die("{$cmd} failed: " . join('<br>', $out) . "\n");
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

