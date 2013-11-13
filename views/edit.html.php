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
  <div class="row navigation">
    <div class="col-md-8 main">
      <?php echo partial('outliner-nav'); ?>
      <label>Navigation</label>
      <div class="well" id="outliner"></div>
    </div>
    <div class="col-md-4 right">
      <label>Attributes</label>
        <ul id="attributes"></ul>
    </div><!-- .col-md-4 -->
  </div><!- .row .navigation-->
  <div class="form-group buttons">
    <button type="submit" class="btn btn-default">Save</button>
  </div>
</form>
<script type="text/javascript">
jQuery(function ($) {
	$("#outliner").concord({
	  id: "4142",
	  open: '/user/nav-open',
		save: '/user/nav-save',
		callbacks: {
  		opCursorMoved: function () {
  		  var line = '<li><span class="name">{name}</span><span class="value">{value}</span></li>';
     		$(line).appendTo('#attributes');
    		var attributes = opGetAtts();
    		for (var a in attributes) {
      		if (!attributes.hasOwnAttribute(a)) {
        		continue;
      		}
      		$(line).appendTo('#attributes');
    		}
    		console.log("MOVED", attributes);
  		}
		},
		prefs: {
		/*
			"outlineFont": "Georgia", 
			"outlineFontSize": 18, 
			"outlineLineHeight": 24,
    */
			renderMode: false,
			readonly: false,
			typeIcons: appTypeIcons,
		},
  });
	//opXmlToOutline(<?php echo $navigation; ?>);
	
	$('#edit-user').submit(function () {
	  var 
	    $form = $(this),
	    $outliner = $('#outliner'),
	    $button = $('[type=submit]', this);
	 
    $button.prop('disabled', true);
	  
    console.log("SAVING");
    $.post($form.attr('action'), $form.serialize(), function (data) {
      console.log("PHASE 1 " + data);
      $outliner.concord().save(function () {
        console.log("DONE");
        $button.prop('disabled', false);
      });
    });
  	return false;
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