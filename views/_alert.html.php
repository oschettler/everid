<?php
$msg = flash('error'); 
if ($msg) {
  $class = 'danger';
}
else {
  $msg = flash('success');
  $class = 'success';
}

if ($msg):
  ?>
  <div class="alert alert-<?php echo $class; ?>">
    <a class="close" data-dismiss="alert" href="#" aria-hidden="true">&times;</a>   
    <?php echo $msg; ?>
  </div>
  <?php
endif;
