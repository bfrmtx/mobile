<?php

/**
 * @ file index.php
 * @ brief main page and submitting jobs for the mobile interface
 */
require_once __DIR__ . '/config.php'; //!< make your SESSION and path.
require_once PHP_DIR . 'php_functions.php'; //!< this is the php file for shared php functions for the mobile pages.
require_once PHP_DIR . 'joblist.php';

$job = new job('job.db', 'job');         // initialize job; adu is handled internally
$job->handle_post_updates();             // process any POST updates and persist to DB

// Handle joblist submissions before sending any output headers/body.
if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST' && ($_POST['sender'] ?? '') === 'joblist') {
  $joblist = new joblist('jobs.db', 'jobs', $job->get_prep_time());
  $electric_off = (($_POST['electric_off'] ?? '0') === '1');
  if (($_POST['what'] ?? '') === 'submit') {
    $new_id = $joblist->insert_from_job($job, $electric_off);
    if ($new_id > 0) {
      $_SESSION['msg_info'] = $electric_off ? 'Job submitted (electric channels off).' : 'Job submitted to job list.';
    }
  } elseif (($_POST['what'] ?? '') === 'start_now') {
    $new_id = $joblist->insert_from_job_now($job, $electric_off);
    if ($new_id > 0) {
      $_SESSION['msg_info'] = $electric_off ? 'Job scheduled to start now (electric channels off).' : 'Job scheduled to start now.';
    }
  } elseif (($_POST['what'] ?? '') === 'special' && isset($_POST['value'])) {
    $joblist->insert_special_job(intval($_POST['value']));
    $_SESSION['msg_info'] = 'Special job marker added (value=' . intval($_POST['value']) . ').';
  }
  header('Location: index.php');
  exit;
}

print_header("JOBs");
?>

<body>
  <?php
  show_status_navbar();
  show_messages();

  $selftest_active = mobile_is_selftest_active();

  $total_duration       = max(0, $job->get_duration());
  $total_days           = intdiv($total_duration, 86400);
  $start_date_time       = $job->get_start_datetime_utc();
  $stop_date_time        = $job->get_stop_datetime_utc();
  $start_local_date_time = $job->get_local_start_datetime();
  $stop_local_date_time  = $job->utc_to_local_datetime($stop_date_time);
  $start_local_time      = $job->format_display_datetime($start_date_time);
  $stop_local_time       = $job->format_display_datetime($stop_date_time);
  ?>
  <?php echo get_iso_datepicker_submit_js(); ?>
  <div id="mainDiv" class="w3-main" style="margin-left:0px">
    <?php if ($selftest_active) { ?>
      <?php echo render_selftest_active_banner(); ?>
    <?php } ?>

    <div class="w3-row w3-padding-32">
      <div class="w3-full w3-container">
        <table style="width:95%;border:1px solid black;border-collapse:collapse;border-spacing:5px;">
          <tr>
            <td align="center">
              <h3 class="w3-text-deep-orange"> <?php echo $job->sampling_rate_drop_down_plus_link(); ?></h3>
            </td>
          </tr>
        </table>
      </div>
    </div>

    <div class="w3-row w3-padding-16">
      <div class="w3-half w3-container">
        <table style="width:95%;border:1px solid black;border-collapse:collapse;border-spacing:5px;">
          <tr>
            <td style="width:10%;" align="right">
              <h3 class="w3-text-deep-orange">Start&nbsp;&nbsp;</h3>
            </td>
            <td style="width:85%">
              <h3>
                <?php
                echo get_iso_datepicker_submit('start_date', $start_local_date_time->format('Y-m-d'), 'job_time', 'start_date');
                echo '&nbsp;&nbsp;<nobr>';
                slider_value_display('', 'start_hours_slider');
                slider_value_display(': ', 'start_minutes_slider');
                slider_value_display(': ', 'start_seconds_slider');
                echo '</nobr>';
                ?>
              </h3>
            </td>
          </tr>
          <tr>
            <td align="right">
              <h3 class="w3-text-deep-orange">Hours&nbsp;&nbsp;</h3>
            </td>
            <td>
              <?php
              echo $job->select_start_time('start_hours');
              get_slider_innerHTML('start_hours', 'start_hours_slider');
              ?>
            </td>
          </tr>
          <tr>
            <td align="right">
              <h3 class="w3-text-deep-orange">Minutes&nbsp;&nbsp;</h3>
            </td>
            <td>
              <?php
              echo $job->select_start_time('start_minutes');
              get_slider_innerHTML('start_minutes', 'start_minutes_slider');
              ?>
            </td>
          </tr>
          <tr>
            <td align="right">
              <h3 class="w3-text-deep-orange">Seconds&nbsp;&nbsp;</h3>
            </td>
            <td>
              <?php
              echo $job->select_start_time('start_seconds');
              get_slider_innerHTML('start_seconds', 'start_seconds_slider');
              ?>
            </td>
          </tr>
          <tr>
            <td align="right">
              <h3 class="w3-text-deep-orange">UTC&nbsp;&nbsp;</h3>
            </td>
            <td>
              <h3><?php echo htmlspecialchars($start_date_time->format('Y-m-d H:i:s'), ENT_QUOTES, 'UTF-8'); ?></h3>
            </td>
          </tr>
          <tr>
            <td align="right">
              <h3 class="w3-text-deep-orange">Local&nbsp;&nbsp;</h3>
            </td>
            <td>
              <h3><?php echo htmlspecialchars($start_local_time, ENT_QUOTES, 'UTF-8'); ?></h3>
            </td>
          </tr>
          <tr>
            <td align="right">
              <h3 class="w3-text-deep-orange"><strong>Subjobs&nbsp;&nbsp;</strong></h3>
            </td>
            <td>
              <h3><?php echo $job->subjob_status(); ?></h3>
            </td>
          </tr>
        </table>
      </div>

      <div class="w3-half w3-container">
        <table style="width:95%;border:1px solid black;border-collapse:collapse;border-spacing:5px;">
          <tr>
            <td style="width:10%;" align="right">
              <h3 class="w3-text-deep-orange">Stop&nbsp;&nbsp;</h3>
            </td>
            <td style="width:85%">
              <h3>
                <?php
                echo get_iso_datepicker_submit('stop_date', $stop_local_date_time->format('Y-m-d'), 'job_time', 'stop_date', 0, $start_local_date_time->format('Y-m-d'));
                echo '&nbsp;&nbsp;<nobr>';
                echo '<span id="stop_hours_preview">' . htmlspecialchars($stop_local_date_time->format('H'), ENT_QUOTES, 'UTF-8') . '</span>';
                echo ': <span id="stop_minutes_preview">' . htmlspecialchars($stop_local_date_time->format('i'), ENT_QUOTES, 'UTF-8') . '</span>';
                echo ': <span id="stop_seconds_preview">' . htmlspecialchars($stop_local_date_time->format('s'), ENT_QUOTES, 'UTF-8') . '</span>';
                echo '</nobr>';
                ?>
              </h3>
            </td>
          </tr>
          <tr>
            <td align="right">
              <h3 class="w3-text-deep-orange">Hours&nbsp;&nbsp;</h3>
            </td>
            <td>
              <?php
              echo $job->select_duration_hours('duration_hours');
              ?>
            </td>
          </tr>
          <tr>
            <td align="right">
              <h3 class="w3-text-deep-orange">Minutes&nbsp;&nbsp;</h3>
            </td>
            <td>
              <?php
              echo $job->select_duration_minutes('duration_minutes');
              ?>
            </td>
          </tr>
          <tr>
            <td align="right">
              <h3 class="w3-text-deep-orange">Seconds&nbsp;&nbsp;</h3>
            </td>
            <td>
              <?php
              echo $job->select_duration_seconds('duration_seconds');
              ?>
            </td>
          </tr>
          <tr>
            <td align="right">
              <h3 class="w3-text-deep-orange">UTC&nbsp;&nbsp;</h3>
            </td>
            <td>
              <h3><?php echo htmlspecialchars($stop_date_time->format('Y-m-d H:i:s'), ENT_QUOTES, 'UTF-8'); ?></h3>
            </td>
          </tr>
          <tr>
            <td align="right">
              <h3 class="w3-text-deep-orange">Local&nbsp;&nbsp;</h3>
            </td>
            <td>
              <h3><?php echo htmlspecialchars($stop_local_time, ENT_QUOTES, 'UTF-8'); ?></h3>
            </td>
          </tr>
          <tr>
            <td align="right">
              <h3 class="w3-text-deep-orange">Total&nbsp;&nbsp;</h3>
            </td>
            <td>
              <input type="hidden" id="duration_days" value="<?php echo intval($total_days); ?>" />
              <h3><?php
                  echo htmlspecialchars('Days ' . sprintf('%03d', $total_days) . ' ' . $job->format_duration_hms($total_duration % 86400), ENT_QUOTES, 'UTF-8');
                  ?></h3>
            </td>
          </tr>
        </table>
      </div>
    </div>
  </div>
  <div class="w3-half w3-container w3-padding-16">
    <h3 class="w3-text-deep-orange">Status&nbsp;&nbsp;</h3>
    <h3 class="w3-text-deep-orange">TimeZone&nbsp;&nbsp;</h3>
    <h3><?php echo $job->timezone_and_dst_form(); ?></h3>
  </div>
  <div class="w3-half w3-container w3-padding-16">
    <h3 class="w3-text-deep-orange">Dipole&nbsp;&nbsp;</h3>
    <?php
    $dipole_labels = [
      0 => 'N &#8660; S',
      1 => 'E &#8660; W',
    ];
    foreach ($dipole_labels as $slot_idx => $dipole_label) {
      $slot = $job->get_slot($slot_idx);
      if ($slot === null) {
        continue;
      }
    ?>
      <div class="w3-row" style="margin-bottom:8px;">
        <div class="w3-quarter">
          <h4 class="w3-text-deep-orange"><?php echo $dipole_label; ?></h4>
        </div>
        <div class="w3-threequarter">
          <?php echo $slot->dipole_length_form(); ?>
        </div>
      </div>
    <?php
    }
    ?>
  </div>

  <!-- ── Submit / Stop buttons ───────────────────────────────────────────── -->
  <div class="w3-row w3-padding-16">
    <div class="w3-full w3-container">
      <span style="display:inline-flex;gap:16px;flex-wrap:wrap;">
        <form method="post" action="index.php" style="margin:0;">
          <input type="hidden" name="sender" value="joblist" />
          <input type="hidden" name="what" value="submit" />
          <button type="submit" class="w3-button w3-black w3-round" style="font-size:1.1em;padding:8px 24px;" <?php echo $selftest_active ? ' disabled' : ''; ?>>Submit Job</button>
        </form>

        <form method="post" action="index.php" style="margin:0;">
          <input type="hidden" name="sender" value="joblist" />
          <input type="hidden" name="what" value="start_now" />
          <button type="submit" class="w3-button w3-khaki w3-round" style="font-size:1.1em;padding:8px 24px;" <?php echo $selftest_active ? ' disabled' : ''; ?>>Start Now</button>
        </form>

        <form method="get" action="airborne.php" style="margin:0;">
          <button type="submit" class="w3-button w3-grey w3-round" style="font-size:1.1em;padding:8px 24px;" <?php echo $selftest_active ? ' disabled' : ''; ?>>Airborne (no electric)</button>
        </form>

        <form method="post" action="index.php" style="margin:0;">
          <input type="hidden" name="sender" value="joblist" />
          <input type="hidden" name="what" value="special" />
          <input type="hidden" name="value" value="-1" />
          <button type="submit" class="w3-button w3-deep-orange w3-round" style="font-size:1.1em;padding:8px 24px;" <?php echo $selftest_active ? ' disabled' : ''; ?>>Stop Job</button>
        </form>
      </span>
    </div>
  </div>
  <?php if ($selftest_active) { ?>
    <script>
      // Auto-refresh only while selftest is active; it stops once DB reports finished.
      setTimeout(function() {
        window.location.reload();
      }, 2000);
    </script>
  <?php } ?>
  <script>
    function padTimePart(value) {
      return String(value).padStart(2, '0');
    }

    function formatIsoDateLocal(dateValue) {
      return dateValue.getFullYear() + '-' + padTimePart(dateValue.getMonth() + 1) + '-' + padTimePart(dateValue.getDate());
    }

    function parseLocalIsoDate(value) {
      var match = /^(\d{4})-(\d{2})-(\d{2})$/.exec(value || '');
      if (!match) {
        return null;
      }

      var parsedDate = new Date(Number(match[1]), Number(match[2]) - 1, Number(match[3]), 0, 0, 0, 0);
      if (Number.isNaN(parsedDate.getTime())) {
        return null;
      }

      return parsedDate;
    }

    function getSliderValue(sliderId) {
      var slider = document.getElementById(sliderId);
      if (!slider) {
        return null;
      }

      var parsedValue = Number.parseInt(slider.value, 10);
      return Number.isNaN(parsedValue) ? null : parsedValue;
    }

    function updateStopDateTimePreview() {
      var startDateInput = document.getElementById('start_date_iso');
      var stopDateInput = document.getElementById('stop_date_iso');
      var stopDatePicker = document.getElementById('stop_date_picker');
      var stopHoursPreview = document.getElementById('stop_hours_preview');
      var stopMinutesPreview = document.getElementById('stop_minutes_preview');
      var stopSecondsPreview = document.getElementById('stop_seconds_preview');

      if (!startDateInput || !stopDateInput || !stopHoursPreview || !stopMinutesPreview || !stopSecondsPreview) {
        return;
      }

      var startDate = parseLocalIsoDate(startDateInput.value);
      var startHours = getSliderValue('start_hours');
      var startMinutes = getSliderValue('start_minutes');
      var startSeconds = getSliderValue('start_seconds');
      var durationHours = getSliderValue('duration_hours');
      var durationMinutes = getSliderValue('duration_minutes');
      var durationSeconds = getSliderValue('duration_seconds');
      var durationDaysInput = document.getElementById('duration_days');
      var durationDays = durationDaysInput ? Number.parseInt(durationDaysInput.value, 10) : 0;
      if (Number.isNaN(durationDays) || durationDays < 0) {
        durationDays = 0;
      }

      if (!startDate || startHours === null || startMinutes === null || startSeconds === null || durationHours === null || durationMinutes === null || durationSeconds === null) {
        return;
      }

      startDate.setHours(startHours, startMinutes, startSeconds, 0);

      var stopDate = new Date(startDate.getTime() + ((((durationDays * 24 * 60 * 60) + (durationHours * 60 * 60) + (durationMinutes * 60) + durationSeconds)) * 1000));
      var stopIsoDate = formatIsoDateLocal(stopDate);

      stopDateInput.value = stopIsoDate;
      if (stopDatePicker) {
        stopDatePicker.value = stopIsoDate;
      }

      stopHoursPreview.textContent = padTimePart(stopDate.getHours());
      stopMinutesPreview.textContent = padTimePart(stopDate.getMinutes());
      stopSecondsPreview.textContent = padTimePart(stopDate.getSeconds());
    }

    [
      'start_date_iso',
      'start_date_picker',
      'start_hours',
      'start_minutes',
      'start_seconds',
      'duration_hours',
      'duration_minutes',
      'duration_seconds'
    ].forEach(function(inputId) {
      var input = document.getElementById(inputId);
      if (!input) {
        return;
      }

      input.addEventListener('input', updateStopDateTimePreview);
      input.addEventListener('change', updateStopDateTimePreview);
    });

    updateStopDateTimePreview();
  </script>
</body>

</html>