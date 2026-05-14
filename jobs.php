<?php

/**
 * @file jobs.php
 * @brief Job history page: list all recorded jobs with Copy and Delete actions.
 */
require_once __DIR__ . '/config.php';
require_once PHP_DIR . 'php_functions.php';
require_once PHP_DIR . 'joblist.php';
print_header("Job List");
?>

<body>
  <?php
  show_status_navbar();
  show_messages();

  $job     = new job('job.db', 'job');
  $job->handle_post_updates();
  $joblist = new joblist('jobs.db', 'jobs', $job->get_prep_time());

  // ── POST handler ───────────────────────────────────────────────────────────
  if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST' && ($_POST['sender'] ?? '') === 'joblist') {
    $what = $_POST['what'] ?? '';
    $id   = intval($_POST['id'] ?? 0);

    if ($what === 'copy_to_job' && $id > 0) {
      if ($joblist->copy_to_job($id, $job)) {
        $_SESSION['msg_info'] = 'Job #' . $id . ' copied back to active job.';
      } else {
        $_SESSION['msg_warning'] = 'Row #' . $id . ' not found.';
      }
    } elseif ($what === 'delete' && $id > 0) {
      $joblist->delete_row($id);
      $_SESSION['msg_info'] = 'Row #' . $id . ' deleted.';
    }

    header('Location: jobs.php');
    exit;
  }
  ?>

  <div id="mainDiv" class="w3-main" style="margin-left:0px">
    <div class="w3-row w3-padding-32">
      <div class="w3-full w3-container">
        <h2 class="w3-text-deep-orange">Job History</h2>
        <h3><a href="index.php" class="w3-text-black">Back to Jobs</a></h3>
        <?php echo $joblist->render_table('jobs.php'); ?>
      </div>
    </div>
  </div>

</body>

</html>