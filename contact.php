<?php
/**
 * Smallish contact application as seen on feedback.schettler.net 
 */

on('GET', '/', function () {

  function trello($name, $descr) {
    $trello_key          = TRELLO_API_KEY;
    $trello_api_endpoint = TRELLO_API_ENDPOINT;
    $trello_list_id      = TRELLO_API_LIST_ID;
    $trello_member_token = TRELLO_API_TOKEN; // Guard this well
  
    $ch = curl_init("$trello_api_endpoint/cards");
    curl_setopt_array($ch, array(
      CURLOPT_SSL_VERIFYPEER => false, // Probably won't work otherwise
      CURLOPT_RETURNTRANSFER => true, // So we can get the URL of the newly-created card
      CURLOPT_POST           => true,
      CURLOPT_POSTFIELDS => http_build_query(array(
          'key'    => $trello_key,
          'token'  => $trello_member_token,
          'idList' => $trello_list_id,
          'name'   => $name,
          'desc'   => $descr
        )),
    ));
    $result = curl_exec($ch);
    $trello_card = json_decode($result);
    return $trello_card->url;
  }
  
  // Simple contact form
  $client_id = CLIENT_ID;
  $uniq = uniqid();
  $fields = array(
    'client' => array(
      'value' => $client_id,
      'type' => 'hidden'
    ),
    'token' => array(
      'value' => $uniq,
      'type' => 'hidden'
    ),
    'name' => array(
      'label' => 'Your name / Company',
      'type' => 'text'
    ),
    'phone' => array(
      'label' => 'Your phone number',
      'type' => 'text'
    ),
    'email' => array(
      'label' => 'Your email address',
      'type' => 'text'
    ),
    'email2' => array(
      'label' => 'Your email address (repeated)',
      'type' => 'text'
    ),
    'body' => array(
      'label' => 'Your message',
      'type' => 'textarea'
    )
  );
  $form = json_encode($fields);
  
  mysql_connect('127.0.0.1', DB_USERNAME, DB_PASSWORD);
  if (!mysql_select_db(DB_NAME)) {
    error_log('ERR: ' . mysql_error() . "\n", 3, '/tmp/everid-contact.log');
  }
  mysql_query('SET NAMES UTF8');
  
  header('Content-type: application/json; charset="utf-8"');
  //error_log('GET: ' . json_encode($_GET) . "\n", 3, '/tmp/contact.log');
  //error_log('SRV: ' . json_encode($_SERVER) . "\n", 3, '/tmp/contact.log');
  if (isset($_REQUEST['field']) && empty($_REQUEST['field']['email2'])) {
    error_log('FORM: ' . json_encode($_REQUEST['field']) . "\n", 3, '/tmp/contact.log');
  
    $status = 'failed';
    
    if ($_REQUEST['field']['client'] == $client_id) {
      $txt = '';
      $sql = strftime("UPDATE contacts SET updated = '%Y-%m-%d %H:%M:%S'");
      foreach (array_keys($fields) as $name) {
        if (empty($_REQUEST['field'][$name]) || in_array($name, array('client', 'token'))) {
          continue;
        }
        $sql .= ", {$name} = '" . mysql_real_escape_string($_REQUEST['field'][$name]) . "'";
        $txt .= "{$name}: {$_REQUEST['field'][$name]}\n";
      }
      $sql .= " WHERE token = '" . mysql_real_escape_string($_REQUEST['field']['token']) . "'";
      error_log('SQL: ' . $sql . "\n", 3, '/tmp/contact.log');
      if (!mysql_query($sql)) {
        error_log('ERR: ' . mysql_error() . "\n", 3, '/tmp/contact.log');
      }
      $status = 'success';
    }
  
    trello($_REQUEST['field']['token'], $txt);  
  
    echo "{$_GET['callback']}({'status': 'success'})";
  }
  else {
    if (!mysql_query(sprintf("INSERT INTO contacts(token, client_ip, created) VALUES('%s', '%s', '%s')",
      $uniq, $_SERVER['REMOTE_ADDR'], strftime('%Y-%m-%d %H:%M:%S')
    ))) {
      error_log('ERR: ' . mysql_error() . "\n", 3, '/tmp/contact.log');
    }
    echo "{$_GET['callback']}({$form})";
  }
});
