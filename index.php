<?php
require_once 'vendor/autoload.php';

config('dispatch.views', './views');
config('source', 'settings.ini');

on('GET', '/', function () {
  echo "Hallo Welt";
});

dispatch();
