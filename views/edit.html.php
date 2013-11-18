<h1>Edit User</h1>
<form id="edit-user" role="form" method="POST">
  <div class="form-group">
    <label for="name">Site name</label>
    <input type="text" class="form-control" name="name" id="name-field" placeholder="Enter name" value="<?php echo $name; ?>">
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
    <label>Theme</label>
    <select name="theme" id="theme-field" class="form-control">
      <?php
      foreach ($themes as $name => $title):
        if ($name == $theme) {
          $selected = ' selected="selected"';
        }
        else {
          $selected = '';
        }
        ?>
        <option<?php echo $selected; ?> value="<?php echo $name; ?>"><?php echo $title; ?></option>
        <?php
      endforeach;
      ?>
    </select>
  </div>

  <div class="row config">
    <div class="col-md-8 main">
      <?php echo partial('outliner-nav'); ?>
      <label>Configuration</label>
      <div class="well" id="outliner"></div>
    </div>
    <div class="col-md-4 right">
      <label>Attributes</label>
      <ul id="attributes"></ul>
    </div><!-- .col-md-4 -->
  </div><!- .row .config-->

  <div class="form-group buttons">
    <button type="submit" class="btn btn-default">Save</button>
  </div>

</form>

<script id="attribute-line" type="html/template">
<li>
  <input class="form-control" name="name" placeholder="name">
  <input class="form-control" name="value" placeholder="value">
</li>
</script>

<script type="text/javascript">
jQuery(function ($) {
	$("#outliner").concord({
	  id: "4142",
	  open: '/user/nav-open',
		save: '/user/nav-save',
		callbacks: {
  		opCursorMoved: function () {
  		  var line = $('#attribute-line').html();
  		  
  		  $('#attributes').html('');
    		var attributes = opGetAtts();
    		for (var a in attributes) {
      		if (!attributes.hasOwnProperty(a)) {
        		continue;
      		}
          var $line = $(line);
          $line.find('[name=name]').val(a);
          $line.find('[name=value]').val(attributes[a]);
      		$line.appendTo('#attributes');
    		}
     		$(line).appendTo('#attributes');
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
	
	/**
	 * Save user and outline
	 */
	$('#edit-user').submit(function () {
	  var 
	    $form = $(this),
	    $outliner = $('#outliner'),
	    $button = $('[type=submit]', this);
	    
    // Temporarily remove outline attributes out of the form
    var nav_attributes = $('#attributes').remove();
	 
    $button.prop('disabled', true);
	  
    console.log("SAVING");
    $.post($form.attr('action'), $form.serialize(), function (data) {
      console.log("PHASE 1 " + data);
        
      // Add temporarily removed outline attributes again
      $('div.right').append(nav_attributes);

      function done() {
        console.log("DONE");
        $button.prop('disabled', false);
      }

      if (opHasChanged()) {
        $outliner.concord().save(done);
      }
      else {
        done();
      }

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

  /**
   * Save attributes
   */
  $('div.right').on('blur', '#attributes input', function () {
    var
      attributes = {};
    
    $('#attributes li').each(function () {
      var 
        name = $('input[name=name]', this).val(),
        value = $('input[name=value]', this).val();
      
      if (name != '') {
        attributes[name] = value;
      }
      
      /*
      // Prepare to set focus to next input after edit
      if (prev) {
        var next = this;
        $(prev).one('blur', function () {
          console.log("FOCUS", $('input[name=name]', next).val());
          $(next).focus();
        });
      }
      prev = this;
      */
    });
    console.log(attributes);
    opSetAtts(attributes);
    opRedraw();
  }); 
  
});
</script>