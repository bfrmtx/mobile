<?php

/**
 * @file config.php
 * @brief set up the session and the path for the database ALWAYS INCLUDE!
 * @details starts the SESSION and set the systemtype and so on.
 */

// check if session is already started (not uses isset ... this is better)
if (session_status() !== PHP_SESSION_ACTIVE) {
  session_start();
}
// ************************* set ADU-11e or ADU-10e here *************************
if (!isset($_SESSION['system_type']) || !is_string($_SESSION['system_type']) || $_SESSION['system_type'] === '') {
  $_SESSION['system_type'] = 'ADU-11e'; //!< change here if you want to change the system! to ADU-10e
}

if (!defined('NSLOTS')) {
  define('NSLOTS', $_SESSION['system_type'] === 'ADU-11e' ? 8 : 5);
  // NSLOTS can be used as for ($i = 0; $i < NSLOTS; $i++) without a $ sign because it is a constant.
}

// define the root of the PHP project for includes and so on
define('BASE_DIR', __DIR__ . DIRECTORY_SEPARATOR); //!< this is the base dir for includes and so on. for example /www/adu/mobile/ if this file is in /www/adu/mobile/config.php
define('SYSTEM_DIR', BASE_DIR . 'system' . DIRECTORY_SEPARATOR); //!< this is the system dir for includes and so on
define('INIT_DIR', SYSTEM_DIR . $_SESSION['system_type'] . DIRECTORY_SEPARATOR); //!< create the ADU-11e . ... system/ADU-11e/ for JSON files
define('JAVA_SCRIPT_DIR', BASE_DIR . 'js' . DIRECTORY_SEPARATOR); //!< this is the js dir for includes and so on
define('CSS_DIR', BASE_DIR . 'css' . DIRECTORY_SEPARATOR); //!< this is the css dir for includes and the top_navbar.html
define('PICS_DIR', BASE_DIR . 'pics' . DIRECTORY_SEPARATOR); //!< this is the pics dir for pictures and so on
define('TRAITS_DIR', BASE_DIR . 'traits' . DIRECTORY_SEPARATOR); //!< this is the traits dir for traits and so on
define('PHP_DIR', BASE_DIR . 'php' . DIRECTORY_SEPARATOR); //!< this is the php dir for php files, not directly belong to the system.
define('ICONS_DIR', BASE_DIR . 'icons' . DIRECTORY_SEPARATOR); //!< this is the icons dir for SVG status icons
// continued at the end.
/**
 * @function autoload
 * @brief if you write $db = new database(); it will look for the file database.php, CASE SENSITIVE.
 */
spl_autoload_register(function ($class) {
  // Define the possible directories to search for class files
  $directories = [
    SYSTEM_DIR,
    TRAITS_DIR,
    PHP_DIR,
  ];

  // Loop through each directory and check for the class file
  foreach ($directories as $directory) {
    $file = $directory . $class . '.php';
    if (file_exists($file)) {
      require_once $file;
      return;
    }
  }

  // If the class file was not found in any directory, you can choose to throw an error or ignore it
  // For example, you could throw an exception:
  throw new Exception("Class file for '$class' not found in any directory.");
});

/**
 * @function adu_db_root
 * @brief Resolve the database directory per host OS and allow overriding via env var.
 */
function adu_db_root() {
  // developer can override with ADU_DB_ROOT like /home/devel/adu/
  $env_root = getenv('ADU_DB_ROOT');
  if (!empty($env_root)) {
    return rtrim($env_root, '/');
  }

  // start with the ADU system default if it exists AND contains db files
  if (is_dir('/home/database') && (file_exists('/home/database/hwConfig.db') || file_exists('/home/database/job.db'))) {
    return '/home/database';
  }

  $users = array();
  $homes = array();

  foreach (array('USER', 'LOGNAME', 'SUDO_USER') as $user_key) {
    $user_value = (string) getenv($user_key);
    if ($user_value !== '') {
      $users[] = $user_value;
    }
  }

  $home_env = (string) getenv('HOME');
  if ($home_env !== '') {
    $homes[] = $home_env;
  }

  if (function_exists('posix_geteuid') && function_exists('posix_getpwuid')) {
    $posix_user = posix_getpwuid(posix_geteuid());
    if (is_array($posix_user)) {
      if (!empty($posix_user['name']) && is_string($posix_user['name'])) {
        $users[] = $posix_user['name'];
      }
      if (!empty($posix_user['dir']) && is_string($posix_user['dir'])) {
        $homes[] = $posix_user['dir'];
      }
    }
  }

  $users = array_values(array_unique($users));
  $homes = array_values(array_unique($homes));

  // if we are on mac we are under /Users/$USER
  if (PHP_OS_FAMILY === 'Darwin') {
    foreach ($users as $user) {
      $candidate = '/Users/' . $user . '/adu_database';
      if (is_dir($candidate)) {
        return $candidate;
      }
    }

    if (is_dir('/Users/bfr/adu_database')) {
      return '/Users/bfr/adu_database';
    }

    $matches = glob('/Users/*/adu_database');
    if (is_array($matches)) {
      foreach ($matches as $candidate) {
        if (is_dir($candidate)) {
          return $candidate;
        }
      }
    }
  }

  // Check resolved home directories directly first (works even when USER is not set).
  foreach ($homes as $home) {
    $candidate = rtrim($home, '/') . '/adu_database';
    if (is_dir($candidate)) {
      return $candidate;
    }
  }

  // on Linux we are under /home/$USER
  foreach ($users as $user) {
    $candidate = '/home/' . $user . '/adu_database';
    if (is_dir($candidate)) {
      return $candidate;
    }
  }

  // Last-resort scan when runtime environment variables are unavailable.
  $linux_matches = glob('/home/*/adu_database');
  if (is_array($linux_matches)) {
    foreach ($linux_matches as $candidate) {
      if (is_dir($candidate)) {
        return $candidate;
      }
    }
  }

  // Keep a deterministic fallback for diagnostics when expected folders are missing.
  return '/home/database';
}
// DB_DIR has already a trailing slash
define('DB_DIR', adu_db_root() . DIRECTORY_SEPARATOR); //!< root directory where ADU database files reside (e.g. /home/database/ or /home/$user/adu_database/).
