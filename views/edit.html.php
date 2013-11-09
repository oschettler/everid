<h1>Edit User</h1>
<form id="edit-user" role="form" method="POST">
  <div class="form-group">
    <label for="name">Site name</label>
    <input type="text" class="form-control" name="name" id="name-field" placeholder="Enter name" value="<?php echo $name; ?>">
  </div>
  <div class="form-group">
    <label for="title">Title</label>
    <input type="text" class="form-control" name="title" id="title-field" placeholder="Enter title" value="<?php echo $name; ?>">
  </div>
  <div class="form-group">
    <label>Notebook</label>
    <select name="notebook" id="notebook-field" class="form-control">
      <?php
      foreach ($notebooks as $guid => $name):
        if ($guid == $notebook) {
          $selected = ' selected="selected"';
        }
        else {
          $selected = '';
        }
        ?>
        <option<?php echo $selected; ?> value="<?php echo $guid; ?>"><?php echo $name; ?></option>
        <?php
      endforeach;
      ?>
    </select>
  </div>
  <div class="form-group">
    <input name="navigation" id="navigation-field" type="hidden">
    <label>Navigation</label>
    <div class="well" id="outliner"></div>
  </div>
  <div class="form-group buttons">
    <button type="submit" class="btn btn-default">Save</button>
  </div>
</form>
<script type="text/javascript">
jQuery(function ($) {
	$("#outliner").concord({
		"prefs": {
		/*
			"outlineFont": "Georgia", 
			"outlineFontSize": 18, 
			"outlineLineHeight": 24,
    */
			"renderMode": false,
			"readonly": false,
			"typeIcons": appTypeIcons
		},
  });
	opXmlToOutline("<?php echo addslashes(str_replace("\r\n", '', $navigation)); ?>");
	
	$('#edit-user').submit(function () {
  	$('#navigation-field').val(opOutlineToXml().replace(/"/g, '&quot;'));
  	return true;
	});
});
</script>