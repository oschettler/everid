<?php
$msg = flash('error'); 
if ($msg):
  ?>
  <div class="alert alert-danger"><?php echo $msg; ?></div>
  <?php
endif;
?>