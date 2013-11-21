<?php
ini_set('display_errors', TRUE);
error_reporting(-1);

session_set_cookie_params(86400 * 7);
session_start();

require_once 'vendor/autoload.php';

ini_set("include_path", ini_get("include_path") . ":vendor/evernote/evernote/lib");
require_once 'autoload.php';

require_once 'Evernote/Client.php';

ORM::configure('sqlite:db/everid.sqlite');

config('dispatch.views', './views');
config('source', 'settings.ini');

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

prefix('/github', function () { include 'github.php'; });

dispatch();
