<?php

/**
 * @file channels.php
 * @brief Channel overview and per-slot configuration page for the mobile interface.
 */
require_once __DIR__ . '/config.php';
require_once PHP_DIR . 'php_functions.php';

$job = new job('job.db', 'job');

if ($job->handle_post_updates()) {
  $_SESSION['msg_info'] = 'Channel settings updated.';
  header('Location: channels.php');
  exit;
}

print_header('Channels');
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
          <h2 class="w3-text-deep-orange">Channels</h2>
          <h3><a href="index.php" class="w3-text-black">Back to Jobs</a></h3>
        </div>
      </div>

      <div class="w3-row-padding w3-padding-16">
        <?php for ($slot_num = 0; $slot_num < NSLOTS; $slot_num++): ?>
          <?php $slot = $job->get_slot($slot_num); ?>
          <?php if ($slot instanceof slot): ?>
            <?php
            $sensor_type = trim((string) $slot->get_sensor_type());
            if ($slot_num > 1 && $sensor_type === '') {
              continue;
            }
            ?>
            <div class="w3-half w3-container w3-margin-bottom">
              <div class="w3-container w3-padding-16">
                <h3 class="w3-text-deep-orange">Slot <?php echo intval($slot_num); ?></h3>
                <?php echo $slot->channel_html(); ?>
              </div>
            </div>
          <?php endif; ?>
        <?php endfor; ?>
      </div>
    </div>
  </div>
</body>

</html>