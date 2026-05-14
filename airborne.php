<?php

/**
 * @ file airborne.php
 * @ brief this page is ONLY for the "no electric" buttons
 * @brief the risk is high that the user accidently switches off the electric channels.
 */
require_once __DIR__ . '/config.php'; //!< make your SESSION and path.
require_once PHP_DIR . 'php_functions.php'; //!< this is the php file for shared php functions for the mobile pages.
require_once PHP_DIR . 'joblist.php';

$job = new job('job.db', 'job');         // initialize job; adu is handled internally
$job->handle_post_updates();             // process any POST updates and persist to DB

// Handle no-electric joblist submissions before sending output.
if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST' && ($_POST['sender'] ?? '') === 'joblist') {
  $joblist = new joblist('jobs.db', 'jobs', $job->get_prep_time());
  if (($_POST['what'] ?? '') === 'submit') {
    $new_id = $joblist->insert_from_job($job, true);
    if ($new_id > 0) {
      $_SESSION['msg_info'] = 'Job submitted (electric channels off).';
    }
  } elseif (($_POST['what'] ?? '') === 'start_now') {
    $new_id = $joblist->insert_from_job_now($job, true);
    if ($new_id > 0) {
      $_SESSION['msg_info'] = 'Job scheduled to start now (electric channels off).';
    }
  }
  header('Location: airborne.php');
  exit;
}

print_header('Airborne NO ELECTRIC');
?>

<body>
  <?php
  show_status_navbar();
  show_messages();
  ?>

  <style>
    .airborne-hero {
      display: flex;
      justify-content: center;
      align-items: center;
      min-height: 220px;
      padding: 16px;
      background: #f0f0f0;
      border-radius: 12px;
    }

    @keyframes airborne-spin-main {
      0% {
        transform: scaleX(1);
        opacity: 0.9;
      }

      50% {
        transform: scaleX(0.1);
        opacity: 0.4;
      }

      100% {
        transform: scaleX(1);
        opacity: 0.9;
      }
    }

    @keyframes airborne-spin-tail {
      0% {
        transform: rotate(0deg);
      }

      100% {
        transform: rotate(360deg);
      }
    }

    @keyframes airborne-hover-flight {
      0% {
        transform: translateY(0px);
      }

      50% {
        transform: translateY(-10px);
      }

      100% {
        transform: translateY(0px);
      }
    }

    .airborne-hero #main-propeller {
      animation: airborne-spin-main 0.15s linear infinite;
      transform-origin: 105px 43px;
    }

    .airborne-hero #tail-propeller {
      animation: airborne-spin-tail 0.1s linear infinite;
      transform-origin: 247px 75px;
    }

    .airborne-hero #helicopter-body {
      animation: airborne-hover-flight 2.5s ease-in-out infinite;
    }
  </style>

  <div id="mainDiv" class="w3-main" style="margin-left:0px">
    <div class="w3-content" style="max-width:1200px">
      <div class="w3-row w3-padding-32">
        <div class="w3-full w3-container">
          <h2 class="w3-text-deep-orange">Airborne NO ELECTRIC</h2>
          <h3><a href="index.php" class="w3-text-black">Back to Jobs</a></h3>
          <p>This page is dedicated to actions with electric channels switched off.</p>
        </div>
      </div>

      <div class="w3-row w3-padding-16">
        <div class="w3-full w3-container">
          <div class="airborne-hero">
            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 300 200" width="300" height="200" aria-label="Animated helicopter" role="img">
              <g id="helicopter-body">
                <path d="M 75 140 L 155 140 M 90 120 L 90 140 M 135 120 L 135 140" stroke="#ff5722" stroke-width="4" stroke-linecap="round" />
                <path d="M 155 140 C 165 140 170 135 170 130" fill="none" stroke="#ff5722" stroke-width="4" stroke-linecap="round" />

                <path d="M 130 100 L 247 75 L 247 50" fill="none" stroke="#0077b6" stroke-width="8" stroke-linejoin="round" stroke-linecap="round" />
                <path d="M 247 75 L 255 55 L 240 50 Z" fill="#004f7c" />

                <path d="M 60 100 C 60 70 90 60 130 60 C 160 60 165 85 155 110 C 145 125 100 125 75 120 C 63 115 60 108 60 100 Z" fill="#004f7c" />
                <path d="M 62 95 C 62 80 75 67 95 65 C 95 85 95 95 90 105 C 75 105 65 102 62 95 Z" fill="#caf0f8" opacity="0.8" />
                <text x="94" y="103" font-size="10" font-family="Arial, sans-serif" font-weight="700" fill="#ffffff">D-HBGR</text>

                <rect x="102" y="43" width="6" height="18" fill="#ff5722" />

                <g id="tail-propeller">
                  <line x1="232" y1="75" x2="262" y2="75" stroke="#ff5722" stroke-width="3" stroke-linecap="round" />
                  <line x1="247" y1="60" x2="247" y2="90" stroke="#ff5722" stroke-width="3" stroke-linecap="round" />
                  <circle cx="247" cy="75" r="4" fill="#666" />
                </g>

                <g id="main-propeller">
                  <ellipse cx="105" cy="43" rx="75" ry="4" fill="#333" opacity="0.7" />
                  <line x1="30" y1="43" x2="180" y2="43" stroke="#222" stroke-width="3" stroke-linecap="round" />
                </g>
              </g>
            </svg>
          </div>
        </div>
      </div>

      <div class="w3-row w3-padding-16">
        <div class="w3-full w3-container">
          <span style="display:inline-flex;gap:16px;flex-wrap:wrap;">
            <form method="post" action="airborne.php" style="margin:0;">
              <input type="hidden" name="sender" value="joblist" />
              <input type="hidden" name="what" value="submit" />
              <button type="submit" class="w3-button w3-grey w3-round" style="font-size:1.1em;padding:8px 24px;">Submit Job (no electric)</button>
            </form>

            <form method="post" action="airborne.php" style="margin:0;">
              <input type="hidden" name="sender" value="joblist" />
              <input type="hidden" name="what" value="start_now" />
              <button type="submit" class="w3-button w3-sand w3-round" style="font-size:1.1em;padding:8px 24px;">Start Now (no electric)</button>
            </form>
          </span>
        </div>
      </div>

      <div class="w3-row w3-padding-16">
        <div class="w3-full w3-container">
          <h4 class="w3-text-deep-orange">Safety Reminder</h4>
          <p>These actions schedule jobs with electric channels off. Verify this is intended before submitting.</p>
        </div>
      </div>
    </div>
  </div>
</body>

</html>