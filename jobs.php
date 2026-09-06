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
$joblist = new joblist('jobs.db', 'jobs', PREP_TIME);
$joblist->reorder_jobs();
$joblist->sync_del_mcp_jobs();
$saved_joblists = $joblist->list_saved_joblists();
$loadable_joblists = $joblist->list_loadable_joblists();

// ── POST handler ───────────────────────────────────────────────────────────
if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST' && ($_POST['sender'] ?? '') === 'joblist') {
  $what = $_POST['what'] ?? '';
  $id   = intval($_POST['id'] ?? 0);
  $joblist_name = strval($_POST['joblist_name'] ?? '');
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
  } elseif ($what === 'save_as_joblist') {
    $normalized_name = joblist::normalize_joblist_filename($joblist_name);
    if (!joblist::is_valid_joblist_filename($normalized_name)) {
      $_SESSION['msg_warning'] = 'Enter a valid joblist name using letters, numbers, dot, dash or underscore.';
    } else {
      $overwriting = $joblist->joblist_exists($normalized_name);
      if ($joblist->save_as_joblist($normalized_name)) {
        $_SESSION['msg_info'] = $overwriting
          ? 'JobList "' . $normalized_name . '" overwritten.'
          : 'JobList "' . $normalized_name . '" saved.';
      } else {
        $_SESSION['msg_warning'] = 'JobList "' . $normalized_name . '" could not be saved.';
      }
    }
  } elseif ($what === 'clear_table') {
    $joblist->empty_table(true);
    $_SESSION['msg_info'] = 'Job table cleared.';
  } elseif ($what === 'load_joblist' && !empty($joblist_name)) {
    $normalized_name = joblist::normalize_joblist_filename($joblist_name);
    if (!joblist::is_valid_loadable_joblist_filename($normalized_name)) {
      $_SESSION['msg_warning'] = 'Enter a valid joblist name using letters, numbers, dot, dash or underscore.';
    } elseif (!$joblist->joblist_exists($normalized_name)) {
      $_SESSION['msg_warning'] = 'JobList "' . $normalized_name . '" does not exist.';
    } elseif ($joblist->load_joblist($normalized_name)) {
      $_SESSION['msg_info'] = 'JobList "' . $normalized_name . '" loaded into jobs table.';
    } else {
      $_SESSION['msg_warning'] = 'JobList "' . $normalized_name . '" could not be loaded.';
    }
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

        <form method="post" action="jobs.php" class="w3-card w3-white w3-round" style="margin:12px 0;padding:12px;">
          <input type="hidden" name="sender" value="joblist" />
          <input type="hidden" name="what" value="save_as_joblist" />
          <label for="joblist_name" class="w3-text-dark-grey" style="display:block;margin-bottom:6px;"><strong>Save as JobList</strong></label>
          <?php if (!empty($saved_joblists)) { ?>
            <!-- datalist has no visible dropdown on mobile Safari, so use a real select to pick an existing name -->
            <select
              id="saved-joblists-select"
              class="w3-select w3-border w3-round"
              style="margin:0 0 8px 0;"
              onchange="if(this.value){document.getElementById('joblist_name').value=this.value;}this.selectedIndex=0;">
              <option value="">— Overwrite existing JobList —</option>
              <?php foreach ($saved_joblists as $saved_joblist) { ?>
                <option value="<?php echo htmlspecialchars($saved_joblist, ENT_QUOTES, 'UTF-8'); ?>"><?php echo htmlspecialchars($saved_joblist, ENT_QUOTES, 'UTF-8'); ?></option>
              <?php } ?>
            </select>
          <?php } ?>
          <div class="w3-row-padding" style="padding:0;display:flex;gap:8px;align-items:center;flex-wrap:wrap;">
            <div style="flex:1 1 240px;min-width:220px;">
              <input
                id="joblist_name"
                name="joblist_name"
                type="text"
                class="w3-input w3-border w3-round"
                style="margin:0;"
                placeholder="e.g. pt_long"
                pattern="[A-Za-z0-9][A-Za-z0-9._-]*"
                title="Use letters, numbers, dot, dash or underscore."
                required />
            </div>
            <button type="submit" class="w3-button w3-deep-orange w3-round" style="padding:8px 16px;">Save as JobList</button>
          </div>
          <?php if (!empty($saved_joblists)) { ?>
            <div style="margin-top:6px;color:#666;font-size:0.92em;">Pick an existing name above to overwrite it, or type a new name below.</div>
          <?php } else { ?>
            <div style="margin-top:6px;color:#666;font-size:0.92em;">Saved JobLists are stored on the server in DB_DIR/joblists.</div>
          <?php } ?>
        </form>

        <?php if (!empty($loadable_joblists)) { ?>
          <form method="post" action="jobs.php" class="w3-card w3-white w3-round" style="margin:12px 0;padding:12px;">
            <input type="hidden" name="sender" value="joblist" />
            <input type="hidden" name="what" value="load_joblist" />
            <label for="load_joblist_name" class="w3-text-dark-grey" style="display:block;margin-bottom:6px;"><strong>Load JobList -> actual Job Time applies (set first!)</strong></label>
            <div class="w3-row-padding" style="padding:0;display:flex;gap:8px;align-items:center;flex-wrap:wrap;">
              <div style="flex:1 1 240px;min-width:220px;">
                <select id="load_joblist_name" name="joblist_name" class="w3-select w3-border w3-round" style="margin:0;" required>
                  <option value="">— Select a JobList —</option>
                  <?php foreach ($loadable_joblists as $saved_joblist) { ?>
                    <option value="<?php echo htmlspecialchars($saved_joblist, ENT_QUOTES, 'UTF-8'); ?>"><?php echo htmlspecialchars($saved_joblist, ENT_QUOTES, 'UTF-8'); ?></option>
                  <?php } ?>
                </select>
              </div>
              <button type="submit" class="w3-button w3-deep-orange w3-round" style="padding:8px 16px;" onclick="return confirm('Replace the current jobs table with the selected JobList?');">Load JobList</button>
            </div>
            <div style="margin-top:6px;color:#666;font-size:0.92em;">Loading replaces all rows in the jobs table with the contents of the selected JobList.</div>
          </form>
        <?php } ?>

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