<?php
/**
 * Routes for prefix "/user"
 */

on('GET', '/edit', function () {
  if (empty($_SESSION['accessToken'])) {
    flash('error', 'Not logged in');
    redirect('/');
  }
  
  $account = ORM::for_table('account')
    ->where_equal('token', $_SESSION['accessToken'])
    ->find_one();

  $notebooks = array(); 
  $client = new Evernote\Client(array('token' => $_SESSION['accessToken']));
  foreach ($client->getNoteStore()->listNotebooks() as $notebook) {
    $notebooks[$notebook->guid] = $notebook->name; 
  }

  render('edit', array(
    'name' => $account->name,
    'title' => $account->title,
    'notebook' => $account->notebook,
    'notebooks' => $notebooks,

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
  redirect('/user/edit');
});

