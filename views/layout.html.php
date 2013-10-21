<!DOCTYPE html>
<html>
  <head>
    <title><?php echo $site_name, ' // ', strip_tags($page_title); ?></title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="//netdna.bootstrapcdn.com/bootstrap/3.0.0/css/bootstrap.min.css">
    <link rel="stylesheet" href="//netdna.bootstrapcdn.com/bootstrap/3.0.0/css/bootstrap-theme.min.css">
    <link rel="stylesheet" href="/css/styles.css">
  </head>
  <body>
    <?php partial('alert'); ?>
    <?php
    echo partial('navbar', array(
      'site_name' => $site_name, 
      'page_title' => $page_title
    )); 
    ?>

    <div class="container">
      <?php echo content(); ?>
    </div>
    <script src="//code.jquery.com/jquery.js"></script>
    <script src="//netdna.bootstrapcdn.com/bootstrap/3.0.0/js/bootstrap.min.js"></script>
  </body>
</html>