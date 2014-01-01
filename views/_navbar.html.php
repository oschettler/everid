<div class="navbar navbar-default navbar-fixed-top">
  <div class="container">
    <div class="navbar-header">
      <button type="button" class="navbar-toggle" data-toggle="collapse" data-target=".navbar-collapse">
        <span class="icon-bar"></span>
        <span class="icon-bar"></span>
        <span class="icon-bar"></span>
      </button>
      <a class="navbar-brand" href="/"><?php echo $site_name; ?></a>
    </div>
    <div class="navbar-collapse collapse">
      <ul class="nav navbar-nav">
        <li>
          <a href="http://docs.everid.net/">Documentation</a>
        </li>
        <li>
          <a href="http://docs.everid.net/about.html">About</a>
        </li>
      </ul>
      <ul class="nav navbar-nav navbar-right">
        <?php
        if (!empty($_SESSION['account'])):
          $account = $_SESSION['account'];
          ?>
          <li class="dropdown">
            <a href="#" class="dropdown-toggle" data-toggle="dropdown"><?php echo $account->username; ?> <b class="caret"></b></a>
            <ul class="dropdown-menu">
              <li>
                <a href="/github/login">Connect with Github</a>
              </li>
              <li>
                <a href="/user/edit">Sites</a>
              </li>
              <li>
                <a href="/auth/logout">Logout</a>
              </li>
            </ul>
          </li>
        <?php
        else:
        ?>
          <li>
            <a href="/auth/authorize" title="with Evernote">Login</a>
          </li>
          <?php
        endif;
        ?>
      </ul>
    </div>
  </div>
</div>