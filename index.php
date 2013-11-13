<?php
ini_set('display_errors', TRUE);
error_reporting(-1);

session_set_cookie_params(86400);
session_start();

require_once 'vendor/autoload.php';

ini_set("include_path", ini_get("include_path") . ":vendor/evernote/evernote/lib");
require_once 'autoload.php';

require_once 'Evernote/Client.php';

ORM::configure('sqlite:db/everid.sqlite');

config('dispatch.views', './views');
config('source', 'settings.ini');

/**
 * Build a hierarchical <ul> list from an OPML tree
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
 
on('GET', '/', function () {
  render('index', array(
    'site_name' => config('site.name'),
    'page_title' => config('site.title'),
  ));
});

prefix('/user', function () { 
  include 'user.php'; 
});

prefix('/auth', function () { include 'auth.php'; });

dispatch();
