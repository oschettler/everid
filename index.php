<?php
ini_set('display_errors', TRUE);
error_reporting(-1);

date_default_timezone_set('Europe/Berlin');

session_set_cookie_params(86400 * 7);
session_start();

require_once 'vendor/autoload.php';

ini_set("include_path", ini_get("include_path") . ":vendor/evernote/evernote/lib");
require_once 'autoload.php';

require_once 'Evernote/Client.php';

ORM::configure('sqlite:db/everid.sqlite');

config('dispatch.views', './views');
config('source', 'settings.ini');

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

on('GET', '/', function () {
  render('index', array(
    'site_name' => config('site.name'),
    'page_title' => config('site.title'),
  ));
});

prefix('/webhook', function () { include 'update.php'; });

prefix('/user', function () { include 'user.php'; });

prefix('/auth', function () { include 'auth.php'; });

prefix('/github', function () { include 'github.php'; });

prefix('/contact', function () { include 'contact.php'; });

dispatch();
