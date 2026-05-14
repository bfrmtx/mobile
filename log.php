<?php

/**
 * @file log.php
 * @brief System log page for the mobile interface.
 */
require_once __DIR__ . '/config.php';
require_once PHP_DIR . 'php_functions.php';
require_once SYSTEM_DIR . 'system_log.php';

$syslog = new system_log();

print_header('System Log');
?>

<body>
  <?php
  show_status_navbar();
  show_messages();
  ?>

  <div id="mainDiv" class="w3-main" style="margin-left:0px">
    <div class="w3-content" style="max-width:1200px">
      <div class="w3-row w3-padding-32">
        <div class="w3-full w3-container">
          <h2 class="w3-text-deep-orange">System Log</h2>
          <h3><a href="index.php" class="w3-text-black">Back to Jobs</a></h3>
          <?php echo $syslog->render_page_content(); ?>
        </div>
      </div>
    </div>
  </div>
</body>

</html>