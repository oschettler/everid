<!DOCTYPE html>
<html lang="en">
  <head>
    <meta charset="utf-8">
    <title><?php echo $site_name, ' // ', strip_tags($page_title); ?></title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="//netdna.bootstrapcdn.com/bootstrap/3.0.0/css/bootstrap.min.css">
    <link rel="stylesheet" href="//netdna.bootstrapcdn.com/bootstrap/3.0.0/css/bootstrap-theme.min.css">
    <link rel="stylesheet" href="/css/styles.css">
    <link rel="stylesheet" href="/concord/concord.css"/>
    <link href="//netdna.bootstrapcdn.com/font-awesome/3.2.1/css/font-awesome.css" rel="stylesheet">
    <script src="//code.jquery.com/jquery-1.10.2.min.js"></script>
    <script src="//netdna.bootstrapcdn.com/bootstrap/3.0.0/js/bootstrap.min.js"></script>
    <script src="/concord/concord.js"></script>
    <script src="/concord/concordUtils.js"></script>
    <script>
    jQuery(function ($) {
      setTimeout(function () {
        $('.alert').slideUp('fast');
      }, 3000);
    });
    </script>
  </head>
  <body>
    <?php echo partial('alert'); ?>
    <?php
    echo partial('navbar', array(
      'site_name' => $site_name, 
      'page_title' => $page_title
    )); 
    ?>

    <div class="container">
      <?php echo content(); ?>
    </div>
  </body>
</html>