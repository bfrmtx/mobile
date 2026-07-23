<?php

/**
 * @file selftest.php
 * @brief Selftest results overview page for the mobile interface.
 */
require_once __DIR__ . '/config.php';
require_once PHP_DIR . 'php_functions.php';
require_once SYSTEM_DIR . 'str/selftest_results.php';

$str_selftest = new str_selftest_results('selftestResult.db');

print_header('Selftest Results');
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
          <h2 class="w3-text-deep-orange">Selftest Results</h2>
          <h3><a href="index.php" class="w3-text-black">Back to Jobs</a> &Leftrightarrow; <a href="channels.php" class="w3-text-black">to Channels</a> &Leftrightarrow; <a href="status.php" class="w3-text-black">to Status</a></h3>
        </div>
      </div>

      <div class="w3-row-padding w3-padding-16">
        <?php echo $str_selftest->st_results_html(); ?>
      </div>
    </div>
  </div>
</body>

</html>