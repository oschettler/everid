<div class="jumbotron">
  <h1><?php echo $page_title; ?></h1>
  <p>Publish an Evernote notebook on the web.</p>
  <ol>
    <li>Select individual notes</li>
    <li>Define your page navigation</li>
    <li>Choose a theme</li>
    <li>Go! *)</li>
  </ol>

  <?php
  if (empty($_SESSION['account'])):
    ?>
    <p>
      <a class="btn btn-lg btn-primary" href="/auth/authorize">Login with Evernote</a>
    </p>
    <?php
  endif;
  ?>
</div>

<p>*) Actually, it's not quite this simple yet. I have written up a <a href="http://oschettler.github.io/everid/">walk-through</a>.</p>

<?php echo partial('disqus'); ?>