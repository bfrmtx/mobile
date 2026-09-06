<?php

/**
 * @file channels.php
 * @brief Channel overview and per-slot configuration page for the mobile interface.
 */
require_once __DIR__ . '/config.php';
require_once PHP_DIR . 'php_functions.php';
require_once PHP_DIR . 'joblist.php';
require_once SYSTEM_DIR . 'system_status.php';
$job = new job('job.db', 'job');


if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST' && ($_POST['sender'] ?? '') === 'joblist'
  && ($_POST['what'] ?? '') === 'special' && intval($_POST['value'] ?? 0) === -2
) {
  $joblist = new joblist('jobs.db', 'jobs', PREP_TIME);
  $joblist->insert_special_job(-2);
  $_SESSION['msg_info'] = 'Detect sensors marker added.';
  $_SESSION['detect_sensors_reload'] = true;
  // Set a default reload time of 12 seconds, Martin says 10s, ensure that the values are in the DB
  $_SESSION['detect_sensors_reload_seconds'] = 12;
  header('Location: channels.php');
  exit;
}

if ($job->handle_post_updates()) {
  $_SESSION['msg_info'] = 'Channel settings updated.';
  header('Location: channels.php');
  exit;
}

print_header('Channels');
$detect_sensors_reload = !empty($_SESSION['detect_sensors_reload']);
$detect_sensors_reload_seconds = max(10, intval($_SESSION['detect_sensors_reload_seconds'] ?? 10));
unset($_SESSION['detect_sensors_reload']);
unset($_SESSION['detect_sensors_reload_seconds']);
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
          <h3><a href="index.php" class="w3-text-black">Back to Jobs</a> &Leftrightarrow; <a href="status.php" class="w3-text-black">to Status</a></h3>
        </div>
      </div>

      <div class="w3-row w3-padding-16">
        <div class="w3-half w3-container">
          <form method="post" action="channels.php" style="margin:0;">
            <input type="hidden" name="sender" value="joblist" />
            <input type="hidden" name="what" value="special" />
            <input type="hidden" name="value" value="-2" />

            <?php if (!mobile_is_recording_or_selftest()): ?>
              <button type="submit" id="detectSensorsBtn" class="w3-button w3-blue w3-round" style="font-size:1.1em;padding:8px 24px;">Detect Sensors</button>
            <?php else: ?>
              <button type="button" class="w3-button w3-grey w3-round" style="font-size:1.1em;padding:8px 24px;cursor:not-allowed;" disabled aria-disabled="true" title="Unavailable while recording or selftest">Detect Sensors (Unavailable While Recording or Selftest)</button>
            <?php endif; ?>

            <div id="detectSensorsMessage" class="w3-text-green w3-margin-top" style="font-weight:bold;"></div>

          </form>
        </div>
        <div class="w3-half w3-container">
          <form method="post" action="channels.php" style="margin:0;">
            <input type="hidden" name="use_atss" value="<?php echo $job->get_use_atss() ? '0' : '1'; ?>" />
            <?php if ($job->get_use_atss()): ?>
              <button type="submit" class="w3-button w3-green w3-round" style="font-size:1.1em;padding:8px 24px;">using new atss</button>
            <?php else: ?>
              <button type="submit" class="w3-button w3-orange w3-round" style="font-size:1.1em;padding:8px 24px;">using old ats</button>
            <?php endif; ?>
          </form>
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
  <?php if ($detect_sensors_reload): ?>
    <script>
      let seconds = <?php echo intval($detect_sensors_reload_seconds); ?>;
      const messageDiv = document.getElementById('detectSensorsMessage');
      const button = document.getElementById('detectSensorsBtn');

      if (button) {
        button.disabled = true;
      }

      const interval = setInterval(() => {
        if (messageDiv) {
          messageDiv.innerHTML = 'Detection triggered! Reloading in ' + seconds + ' seconds...';
        }
        seconds--;

        if (seconds < 0) {
          clearInterval(interval);
          window.location.href = window.location.pathname;
        }
      }, 1000);
    </script>
  <?php endif; ?>
</body>

</html>