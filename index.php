<?php
require_once 'vendor/autoload.php';

config('dispatch.views', './views');

on('GET', '/', function () {
  echo "Hallo Welt";
});
