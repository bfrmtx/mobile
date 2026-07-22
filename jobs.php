<?php

/**
 * @file jobs.php
 * @brief Job history page: list all recorded jobs with Copy and Delete actions.
 */
require_once __DIR__ . '/config.php';
require_once PHP_DIR . 'php_functions.php';
require_once PHP_DIR . 'joblist.php';

$job     = new job('job.db', 'job');
$job->handle_post_updates();
$joblist = new joblist('jobs.db', 'jobs', $job->get_prep_time());

// ── POST handler ───────────────────────────────────────────────────────────
if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST' && ($_POST['sender'] ?? '') === 'joblist') {
  $what = $_POST['what'] ?? '';
  $id   = intval($_POST['id'] ?? 0);
  $redirect = 'jobs.php';

  if ($what === 'copy_to_job' && $id > 0) {
    if ($joblist->copy_to_job($id, $job)) {
      $_SESSION['msg_info'] = 'Job #' . $id . ' copied back to active job.';
      $redirect = 'index.php';
    } else {
      $_SESSION['msg_warning'] = 'Row #' . $id . ' not found.';
    }
  } elseif ($what === 'delete' && $id > 0) {
    $joblist->delete_row($id);
    $_SESSION['msg_info'] = 'Row #' . $id . ' deleted.';
  } elseif ($what === 'clear_table') {
    $joblist->delete_all_rows(true);
    $_SESSION['msg_info'] = 'Job table cleared.';
  }

  header('Location: ' . $redirect);
  exit;
}

print_header("Job List");
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
        <h2 class="w3-text-deep-orange">Job History</h2>
        <h3><a href="index.php" class="w3-text-black">Back to Jobs</a></h3>

        <form method="post" action="jobs.php" style="margin:12px 0;">
          <input type="hidden" name="sender" value="joblist" />
          <input type="hidden" name="what" value="clear_table" />
          <button type="submit" class="w3-button w3-red w3-round" style="padding:8px 16px;" onclick="return confirm('Clear all rows from the jobs table?');">Clear Table</button>
        </form>

        <?php echo $joblist->render_table('jobs.php'); ?>
      </div>
    </div>
  </div>

</body>

</html>