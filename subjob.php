<?php

/**
 * @ file subjob.php
 * @ brief subjob page for changing values of job_data
 */
require_once __DIR__ . '/config.php'; //!< make your SESSION and path.
require_once PHP_DIR . 'php_functions.php'; //!< this is the php file for shared php functions for the mobile pages.
require_once PHP_DIR . 'joblist.php';

$job = new job('job.db', 'job');          // initialize the job, adu hadles post updates for HW
$job->read();                             // load persisted state from DB
$job->handle_post_updates();              // handle frequency and subjob POST updates

// ── special job POST handler ───────────────────────────────────────────
if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST' && ($_POST['sender'] ?? '') === 'joblist'
  && ($_POST['what'] ?? '') === 'special' && isset($_POST['value'])
) {
  $joblist = new joblist('jobs.db', 'jobs', $job->get_prep_time());
  $joblist->insert_special_job(intval($_POST['value']));
  $_SESSION['msg_info'] = 'Special job marker added (value=' . intval($_POST['value']) . ').';
  header('Location: subjob.php');
  exit;
}

print_header("SUBJOBs / EDI");
?>

<body>
  <?php
  show_status_navbar();
  show_messages();

  $selftest_active = mobile_is_selftest_active();
  ?>

  <div id="mainDiv" class="w3-main" style="margin-left:0px">
    <?php if ($selftest_active) { ?>
      <?php echo render_selftest_active_banner(); ?>
    <?php } ?>

    <div class="w3-row w3-padding-32">
      <div class="w3-full w3-container">
        <h3 class="w3-text-deep-orange">Main&nbsp;&nbsp;
          <?php echo $job->get_virtual_sampling_rate() . " Hz"; ?>
          &nbsp;&nbsp;<a href="index.php" class="w3-text-black">Back to Jobs</a></h3>
        <table style="width:95%;border:1px solid black;border-collapse:collapse;border-spacing:5px;">
          <tr>
            <td align="center">
              <h3><?php echo $job->set_straight_button(); ?></h3>
            </td>
            <td align="center">
              <h3><?php echo $job->set_filter_button(); ?></h3>
            </td>
            <td align="center">
              <h3><?php echo $job->set_shots_button(); ?></h3>
            </td>
          </tr>
        </table>
      </div>
    </div>

    <?php if ($job->is_filter() || $job->is_shot()) { ?>
      <div class="w3-row w3-padding-16">
        <?php if ($job->is_filter()) { ?>
          <div class="w3-full w3-container">
            <table style="width:95%;border:1px solid black;border-collapse:collapse;border-spacing:5px;">
              <tr>
                <td align="right" style="width:35%">
                  <h3 class="w3-text-deep-orange">Filtered Rate Output&nbsp;&nbsp;</h3>
                </td>
                <td>
                  <h3><?php echo $job->virtual_sub_filter_rates_drop_down(); ?></h3>
                </td>
              </tr>
            </table>
          </div>
        <?php } ?>

        <?php if ($job->is_shot()) { ?>
          <div class="w3-full w3-container">
            <table style="width:95%;border:1px solid black;border-collapse:collapse;border-spacing:5px;">
              <tr>
                <td align="right" style="width:35%">
                  <h3 class="w3-text-deep-orange">Shot Rate&nbsp;&nbsp;</h3>
                </td>
                <td>
                  <h3><?php echo $job->virtual_shot_rates_drop_down(); ?></h3>
                </td>
              </tr>
              <tr>
                <td align="right" style="width:35%">
                  <h3 class="w3-text-deep-orange">Shot Cycle&nbsp;&nbsp;</h3>
                </td>
                <td>
                  <h3><?php echo $job->sub_cycle_drop_down(); ?></h3>
                </td>
              </tr>
              <tr>
                <td align="right" style="width:35%">
                  <h3 class="w3-text-deep-orange">Shot Duration&nbsp;&nbsp;</h3>
                </td>
                <td>
                  <h3><?php echo $job->sub_duration_drop_down(); ?></h3>
                </td>
              </tr>
            </table>
          </div>
        <?php } ?>
      </div>
    <?php } ?>

    <div class="w3-row w3-padding-16">
      <div class="w3-full w3-container">
        <span style="display:flex;gap:12px;flex-wrap:wrap;">
          <form method="post" action="subjob.php" style="margin:0;">
            <input type="hidden" name="sender" value="joblist" />
            <input type="hidden" name="what" value="special" />
            <input type="hidden" name="value" value="-3" />
            <button type="submit" class="w3-button w3-red w3-round" style="font-size:1.1em;padding:8px 24px;">Shut Down</button>
          </form>
          <form method="post" action="subjob.php" style="margin:0;">
            <input type="hidden" name="sender" value="joblist" />
            <input type="hidden" name="what" value="special" />
            <input type="hidden" name="value" value="-4" />
            <button type="submit" class="w3-button w3-orange w3-round" style="font-size:1.1em;padding:8px 24px;">Reboot</button>
          </form>
        </span>
      </div>
    </div>

    <div class="w3-row w3-padding-16">
      <div class="w3-full w3-container">
        <table style="width:95%;border:1px solid black;border-collapse:collapse;border-spacing:5px;">
          <tr>
            <td align="right" style="width:35%">
              <h3 class="w3-text-deep-orange">Station ID&nbsp;&nbsp;</h3>
            </td>
            <td>
              <form method="post" action="subjob.php" id="station_id_form" style="margin:0;">
                <input
                  style="width:100%"
                  type="text"
                  name="station_id"
                  id="station_id_input"
                  value="<?php echo htmlspecialchars($job->get_station_id(), ENT_QUOTES, 'UTF-8'); ?>"
                  autocomplete="off"
                  spellcheck="false" />
              </form>
            </td>
          </tr>
        </table>
      </div>
    </div>
  </div>

  <script>
    (function() {
      var form = document.getElementById('station_id_form');
      var input = document.getElementById('station_id_input');
      if (!form || !input) {
        return;
      }

      var timer = null;
      var last = input.value;

      function submitIfChanged() {
        if (input.value === last) {
          return;
        }
        last = input.value;
        form.submit();
      }

      input.addEventListener('input', function() {
        if (timer) {
          clearTimeout(timer);
        }
        timer = setTimeout(submitIfChanged, 1500);
      });

      input.addEventListener('change', submitIfChanged);
      input.addEventListener('blur', submitIfChanged);
    })();
  </script>

</body>

</html>