<?php

/**
 * @file status.php
 * @brief System status overview page for the mobile interface.
 * // this pages NEEDS A REFRESH ALL every 2 seconds, as it shows the live status of the system. We will use AJAX to fetch the latest status icons and GPS date-time from the server every 2 seconds, and update the nav bar accordingly.
 */
require_once __DIR__ . '/config.php';
require_once PHP_DIR . 'php_functions.php';
require_once SYSTEM_DIR . 'system_status.php';

$status = new status();
print_header('System Status', 2);
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
          <h2 class="w3-text-deep-orange">System Status</h2>
          <h3><a href="index.php" class="w3-text-black">back toJobs</a></h3>
        </div>
      </div>

      <div class="w3-row-padding w3-padding-16">

        <?php if ($status->recording_status !== null): ?>
          <div class="w3-full w3-container w3-margin-bottom">
            <?php echo $status->recording_status->get_recording_status_html(); ?>
          </div>
        <?php endif; ?>

        <?php if ($status->adu_status !== null): ?>
          <div class="w3-half w3-container w3-margin-bottom">
            <?php echo $status->adu_status->get_adu_status_html(); ?>
          </div>
        <?php endif; ?>

        <?php if ($status->gps_status !== null): ?>
          <div class="w3-half w3-container w3-margin-bottom">
            <?php echo $status->gps_status->get_gps_status_html(); ?>
          </div>
        <?php endif; ?>
      </div>
    </div>
  </div>
</body>

</html>