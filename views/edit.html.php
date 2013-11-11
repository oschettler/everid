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
  <div class="form-group navigation">
    <input name="navigation" id="navigation-field" type="hidden">
    <label>Navigation</label>
    <div id="outliner-nav" class="btn-group">
      <button id="rendermode-status" type="button" class="btn btn-default">R</button>
      <button type="button" class="btn btn-default">2</button>

      <div class="btn-group">
        <button type="button" class="btn btn-default dropdown-toggle" data-toggle="dropdown"><span class="glyphicon glyphicon-chevron-down"></span></button>
        <ul class="dropdown-menu pull-right" role="menu">
  				<li><a onclick="opExpand ();"><span class="menuKeystroke">⌘,</span>Expand</a></li>
  				<li><a onclick="opExpandAllLevels ();">Expand All Subs</a></li>
  				<li><a onclick="opExpandEverything ();">Expand Everything</a></li>
  				
  				<li class="divider"></li>
  				<li><a onclick="opCollapse ();"><span class="menuKeystroke">⌘.</span>Collapse</a></li>
  				<li><a onclick="opCollapseEverything ();">Collapse Everything</a></li>
  				
  				<li class="divider"></li>
  				<li><a onclick="opReorg (up, 1);"><span class="menuKeystroke">⌘U</span>Move Up</a></li>
  				<li><a onclick="opReorg (down, 1);"><span class="menuKeystroke">⌘D</span>Move Down</a></li>
  				<li><a onclick="opReorg (left, 1);"><span class="menuKeystroke">⌘L</span>Move Left</a></li>
  				<li><a onclick="opReorg (right, 1);"><span class="menuKeystroke">⌘R</span>Move Right</a></li>
  				
  				<li class="divider"></li>
  				<li><a onclick="opPromote ();"><span class="menuKeystroke">⌘[</span>Promote</a></li>
  				<li><a onclick="opDemote ();"><span class="menuKeystroke">⌘]</span>Demote</a></li>
  				
  				<li class="divider"></li>
  				<li><a onclick="runSelection ();"><span class="menuKeystroke">⌘/</span>Run Selection</a></li>
  				<li><a onclick="toggleComment ();"><span class="menuKeystroke">⌘\</span>Toggle Comment</a></li>
  				
  				<li class="divider"></li>
  				<li><a onclick="toggleRenderMode ();"><span class="menuKeystroke">⌘`</span>Toggle Render Mode</a></li>
        </ul>
      </div>
    </div>
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
	
	
	/*
	 * START: Render mode
	 */
  var $concord = $(defaultUtilsOutliner).concord();
  
  if ($concord.op.getRenderMode()) {
  	$('#rendermode-status').addClass('active');
  }
  else {
  	$('#rendermode-status').removeClass('active');
  }
  
	window.toggleRenderMode = function () {
    var newRenderMode = !$concord.op.getRenderMode();

  	$concord.op.setRenderMode(newRenderMode);
  	
    if (newRenderMode) {
    	$('#rendermode-status').addClass('active');
    }
    else {
    	$('#rendermode-status').removeClass('active');
    }
	};

  // Button toggles renderMode
  $('#rendermode-status').on('click', toggleRenderMode);
  
  /*
	 * END: Render mode
	 */
  
});
</script>