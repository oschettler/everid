<h1>Edit User</h1>
<form role="form" method="POST">
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
  <div class="form-group nav-edit">
    <label for="navigation">Navigation</label>
    <input type="hidden" name="navigation" value="<?php echo $navigation; ?>">
    <?php
    echo $nav_tree;
    ?>
  </div>
  <div class="form-group buttons">
    <button type="submit" class="btn btn-default">Save</button>
  </div>
</form>
