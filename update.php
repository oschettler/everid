<?php

require_once 'github_api.php';

function update($auth, $site) {
  $log = array();

  $github = new Github(
    $site->github_token,
    $site->github_username,
    $site->github_repo
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
    $log[] = "Creating branch 'gh-pages'";
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
      
      $log[] = "Updating '{$notebook->name}'";
      
      $tree = $github->trees();
  
      // Check for all directories in standard Jekyll directory structure
      $dirs = array(
        '_drafts' => NULL, 
        '_includes' => NULL, 
        '_layouts' => NULL, 
        '_posts' => NULL, 
        '_data' => NULL, 
        '_site' => NULL,
        'res' => NULL,
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
          $log[] = "Creating {$dir}";
          $github->mkdir($dir);
        }  
      }
      
      $log[] = "Theme '{$site->theme}'";
      $theme = dirname(__FILE__) . '/themes/' . $site->theme . '/';
      if (!is_dir($theme)) {
        return array('error',
          "No such theme '{$theme}'"
        );
      }
      
      if (!file_exists($theme . 'layout.html')) {
        return array('error', 
          "No layout {$theme}layout.html"
        );
      }
      $github->save(
        '_layouts/default.html',
        file_get_contents($theme . 'layout.html'),
        file_sha('_layouts/default.html')
      );

      if (file_exists($theme . 'styles.css')) { 
        $log[] = "Styles";
        $github->save(
          'styles.css',
          file_get_contents($theme . 'styles.css'),
          file_sha('styles.css')
        );
      }
            
      if (!empty($site->domain)) {
        $log[] = "Will serve domain {$site->domain}";
        $github->save(
          'CNAME',
          $site->domain,
          file_sha('CNAME')
        );
      }
      
      $xml = simplexml_load_string($site->config);
      $config = array();
      foreach ($xml->body->outline as $outline) {
        list($name, $yaml) = opml2yaml($outline);

        if ($name == '_config') {
          $fname = '_config.yml';           
        }
        else {
          $fname = '_data/' . preg_replace('/\W+/u', '_', $name) . '.yml';
        }
        $log[] = "Writing {$fname}";
        $github->save($fname, $yaml, file_sha($fname));
      }
      
      $noteList = $store->findNotesMetadata($auth, $filter, 0, 10, $spec);
      foreach ($noteList->notes as $remoteNote) {
        $localNote = ORM::for_table('note')
          ->where_equal('guid', $remoteNote->guid)
          ->find_one();
        
        $log[] = $remoteNote->title;
        if (TRUE || !$localNote || $localNote->updated < $remoteNote->updated / 1000) {
          $note = $store->getNote($auth, $remoteNote->guid,
            TRUE, // withContent
            TRUE, // withResourcesData
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
            if (preg_match('/^url(:|=)(\S+)/i', $tag, $matches)) {
              $url = $matches[2];
            }
          }
          if (!$url) {
            return array('error', 
              "You need to set the URL on note {$note->title}"
            );
          }
                  
          if (preg_match('/\.(html|md)$/', $url, $matches)) {
            $fname = $url; 
          }               
          else {
            $fname = $url . '.html';
          }   
          
          $log[] = "fname={$fname}";
                    
          if ($localNote) {
            $diff = $remoteNote->updated / 1000 - $localNote->updated; 
            $log[] = "updated: {$diff}";
          }
          else {
            $log[] = "new";
          }
          
          $tags = join(' ', $note->tagNames);
          
          $mime = array(
            'image/gif' => '.gif',
            'image/jpeg' => '.jpg',
            'image/png' => '.png',
            'audio/wav' => '.wav',
            'audio/mpeg' => '.m4a',
            'application/pdf' => '.pdf'
          );

          $res = array();
          $reslist = '';
          if ($note->resources) {
            foreach ($note->resources as $resource) {
              $ext = !empty($mime[$resource->mime]) ? $mime[$resource->mime] : '.bin';
              $resname = "res/{$resource->guid}{$ext}";
              $reshash = md5($resource->data->body, 0);
              $res[$reshash] = $resname;
              $reslist .= "\n  {$reshash}: {$resname}";
              $log[] = "Writing '{$resname}'";
              $github->save($resname, $resource->data->body, file_sha($resname));
            }
          }
          
          $content = "---
title: {$note->title}
layout: default
tags: {$tags}
res: {$reslist}
---

<h1>{{ page.title }}</h1>
";

          $noteContent = simplexml_load_string($note->content);
          foreach ($noteContent->children() as $c) {
          
            $el = preg_replace_callback(array(
                '#<en-media(.*?)/>#', 
                '#<en-media(.*?)></en-media>#'
              ), 
              function ($matches0) use ($res, $log) {
                $attr = array();
              
                foreach (preg_split('/\s+/', $matches0[1]) as $assign) {
                  if (preg_match('/(\w+)="([^"]+)"/', $assign, $matches1)) {
                    $attr[$matches1[1]] = $matches1[2];
                  }
                }
                
                if (empty($attr['hash']) || empty($attr['type'])) {
                  $log[] = " - missing type/hash in {$matches0[0]}";
                }
                
                if (strpos($attr['type'], 'image/') === 0) {
                  return '<img src="' . $res[$attr['hash']] . '"/>';
                }
                else {
                  return '<a href="' . $res[$attr['hash']] . '">' . $attr['hash'] . '</a>';
                }
              }, 
              $c->asXML()
            );
                        
            $content .= $el . "\n";
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
          $log[] = "no change";
        }
      } // notes
    } // matching notebook
  } // all notebooks
  return array('success', $log);
} // update

/**
 * Called from Evernote with parameters
 *  - userId: "556714",
 *  - guid: "6c740887-9e3e-4556-ab8a-aa73bce7329d",
 *  - notebookGuid: "24b4aba4-c785-4bf7-896c-cadcd1e775eb",
 *  - reason: "update"
 */
on('GET', '/', function () {
  $suffix = config('env') == 'prod' ? '' : '-stage';	

  header('Content-type: application/json; charset=UTF-8');
  error_log(strftime('[%Y-%m-%d %H:%M:%S] WEBHOOK' . json_encode($_GET) . "\n"), 3, '/tmp/everid' . $suffix . '.log');
  
  if (empty($_GET['userId'])) {
    error_log(strftime('[%Y-%m-%d %H:%M:%S] WEBHOOK error#1' . "\n"), 3, '/tmp/everid' . $suffix . '.log');
    die(json_encode(array(
      'status' => 'error', 
      'message' => 'Missing UserId'
    )));
  }
  
  $account = ORM::for_table('user')
    ->where_equal('evernote_id', $_GET['userId'])
    ->find_one();

  if (!$account) {
    error_log(strftime('[%Y-%m-%d %H:%M:%S] WEBHOOK error#2' . "\n"), 3, '/tmp/everid' . $suffix . '.log');
    die(json_encode(array(
      'status' => 'error', 
      'message' => "No such user '{$_GET['userId']}"
    )));
  }
  
  $site = ORM::for_table('site')
    ->where('notebook', $_GET['notebookGuid'])
    ->find_one();
  
  if (empty($_GET['notebookGuid']) || !$site) {
    error_log(strftime('[%Y-%m-%d %H:%M:%S] WEBHOOK error#3' . "\n"), 3, '/tmp/everid' . $suffix . '.log');
    die(json_encode(array(
      'status' => 'info', 
      'message' => "Unregistered notebook"
    )));
  }
  
  error_log(strftime('[%Y-%m-%d %H:%M:%S] WEBHOOK GO' . "\n"), 3, '/tmp/everid' . $suffix . '.log');
  if (strpos($_SERVER['HTTP_USER_AGENT'], 'Java') === 0) {
    error_log(strftime('[%Y-%m-%d %H:%M:%S] WEBHOOK updating site #' . $site->id . ' for user #' . $account->id . "\n"), 3, '/tmp/everid' . $suffix . '.log');
    error_log(strftime('[%Y-%m-%d %H:%M:%S] WEBHOOK') 
      . json_encode(update($account->token, $site)) . "\n", 3, '/tmp/everid' . $suffix . '.log');
    echo 'OK';
  }
  else {
    echo json_encode(update($account->token, $site));
  }
});
