<h1><?php echo $page_title; ?></h1>

<form id="edit-user" role="form" method="POST" action="/user/email">
  <div class="form-group">
    <label for="name">Email address</label>
    <input type="email" class="form-control" name="email" id="name-field" placeholder="you@domain.com" value="<?php echo $email; ?>">
    <p>Enter your email address here if you want to get an email for each page build.</p> 
  </div>

  <div class="form-group buttons">
    <button type="submit" class="btn btn-default">Save</button>
  </div>

</form>

<table class="table table-striped">
  <thead>
    <th>Name</th>
    <th>Github Username</th>
    <th>Github Repo</th>
    <th>Domain</th>
    <th>Theme</th>
    <th class="action">
      <a class="add-site" href="/user/site/add"><span class="glyphicon glyphicon-plus-sign"></span></a>
    </th>
  </thead>
  <tbody>
  <?php
  foreach ($sites as $site):
    ?>
    <tr>
      <td><?php echo $site->name; ?></td>
      <td><?php echo $site->github_username; ?></td>
      <td><?php echo $site->github_repo; ?></td>
      <td>
        <?php 
        if (empty($site->domain)) {
          $domain = "{$site->github_username}.github.io/{$site->github_repo}";    
        }
        else {
          $domain = $site->domain; 
        }
        ?>
        <a target="site" href="http://<?php echo $domain; ?>"><?php echo $domain; ?></a>
      </td>
      <td><?php echo $site->theme; ?></td>
      <td class="action">
        <a href="/user/site/<?php echo $site->id; ?>" class="edit edit-site"><span class="glyphicon glyphicon-edit"></span></a>
        <a href="/user/del-site/<?php echo $site->id; ?>" class="del del-site" data-title="Site #<?php echo $site->id; ?>"><span class="glyphicon glyphicon-trash"></span></a>
      </td>
    </tr>
    <?php
  endforeach;
  ?>
  </tbody>
</table>
<script type="text/javascript">
jQuery(function () {
  /*
   * Delete site
   */
  $('a.del').click(function (e) {
    if (confirm('Really delete ' + $(this).attr('data-title') + '?')) {
      $.post($(this).attr('href'), function (data) {
        $.cookie('_F', JSON.stringify(data));
        location.reload();
      }, 'json');
    }
    e.preventDefault();
  });

});
</script>