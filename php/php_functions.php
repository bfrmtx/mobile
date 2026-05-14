<?php

/**
 * @file php_functions.php
 * @brief shared php functions for the mobile pages
 */

// Ensure global path/session constants (BASE_DIR, CSS_DIR, ...) are available.
if (!defined('BASE_DIR')) {
  require_once __DIR__ . '/../config.php';
}

/**
 * @brief Build a web asset URL from a path relative to BASE_DIR.
 * @param[in] $relative_path Path relative to mobile root, e.g. css/w3.css
 * @return string URL path suitable for href/src attributes
 */
function base_asset_url(string $relative_path): string {
  $relative = ltrim(str_replace('\\', '/', $relative_path), '/');
  $absolute = str_replace('\\', '/', BASE_DIR . $relative);
  $doc_root = isset($_SERVER['DOCUMENT_ROOT']) ? rtrim(str_replace('\\', '/', (string) $_SERVER['DOCUMENT_ROOT']), '/') : '';

  if ($doc_root !== '' && str_starts_with($absolute, $doc_root . '/')) {
    return substr($absolute, strlen($doc_root));
  }

  // Fallback keeps links usable when DOCUMENT_ROOT is unavailable (CLI/tests).
  return '/' . $relative;
}

if (!function_exists('mobile_rewrite_relative_urls')) {
  /**
   * @brief Fallback passthrough when URL rewrite helper is not available.
   */
  function mobile_rewrite_relative_urls(string $html): string {
    return $html;
  }
}

/**
 * @brief get the innerHTML for a slider and its display element
 * @param[in] $post_name the name of the slider element
 * @param[in] $slider_display the name of the display element for the slider value
 * @details this is for start_hours, start_minutes, start_seconds, stop_hours, stop_minutes, stop_seconds sliders, duration_hours, duration_minutes, duration_seconds sliders. azimuth and tilt.
 */
function get_slider_innerHTML($post_name, $slider_display) {
  $pad_two_digits = preg_match('/(?:^|_)(hours|minutes|seconds)$/', (string) $post_name) === 1;
  $str = PHP_EOL . '<script>' . PHP_EOL;
  $str .= 'var sliders_' . $post_name . ' = document.getElementById("' . $post_name . '");' . PHP_EOL;
  $str .= 'var outputs_' . $post_name . ' = document.getElementById("' . $slider_display . '");' . PHP_EOL;
  $str .= 'var format_' . $post_name . ' = function(v) { return ' . ($pad_two_digits ? 'String(v).padStart(2, "0")' : 'v') . '; };' . PHP_EOL;
  $str .= 'outputs_' . $post_name . '.innerHTML = format_' . $post_name . '(sliders_' . $post_name . '.value);' . PHP_EOL;
  $str .= 'sliders_' . $post_name . '.oninput = function() {' . PHP_EOL;
  $str .= 'outputs_' .  $post_name . '.innerHTML = format_' . $post_name . '(this.value);' . PHP_EOL;
  $str .= '}' . PHP_EOL;
  $str .= '</script>' . PHP_EOL;
  echo $str;
}

/**
 * @brief echo the innerHTML for a slider and its display element
 * @param[in] $prefix the text to display before the slider value
 * @param[in] $slider_display the name of the display element for the slider value
 * @details same as get_slider_innerHTML but only for the display element.
 * @details with two digits like 00:01:12
 */
function slider_value_display($prefix, $slider_display) {
  echo $prefix . '<span id="' . $slider_display . '"></span>' . PHP_EOL;
}

function print_header($page_title, $refresh = 0, $auto_reload_div = null, $reload_interval = 2000) {
  $safe_title = htmlspecialchars((string) $page_title, ENT_QUOTES, 'UTF-8');
  $w3_css = htmlspecialchars(base_asset_url('css/w3.css'), ENT_QUOTES, 'UTF-8');
  $theme_css = htmlspecialchars(base_asset_url('css/w3-theme-black.css'), ENT_QUOTES, 'UTF-8');
  $nav_css = htmlspecialchars(base_asset_url('css/nav.css'), ENT_QUOTES, 'UTF-8');
  $jquery_js = htmlspecialchars(base_asset_url('js/jquery.js'), ENT_QUOTES, 'UTF-8');

  echo '<!DOCTYPE html>' . PHP_EOL;
  echo '<html lang="en">' . PHP_EOL;
  echo '<title>' . $safe_title . '</title>' . PHP_EOL;
  echo '<head>' . PHP_EOL;
  echo '  <meta charset="UTF-8">' . PHP_EOL;
  if ($refresh > 0) {
    echo '  <meta http-equiv="refresh" content="' . $refresh . '">' . PHP_EOL;
  }
  echo '  <meta name="viewport" content="width=device-width, initial-scale=1">' . PHP_EOL;
  echo '  <link rel="stylesheet" href="' . $w3_css . '">' . PHP_EOL;
  echo '  <link rel="stylesheet" href="' . $theme_css . '">' . PHP_EOL;
  echo '  <link rel="stylesheet" href="' . $nav_css . '">' . PHP_EOL;
  // load the newer jquery
  echo '  <script src="' . $jquery_js . '"></script>' . PHP_EOL;
  // datepicker
  // ...
  // style sheets
  echo '  <style type="text/css">' . PHP_EOL;
  echo '    .input-group {' . PHP_EOL;
  echo '      width: 110px;' . PHP_EOL;
  echo '      margin-bottom: 10px;' . PHP_EOL;
  echo '    }' . PHP_EOL;
  echo '    .pull-center {' . PHP_EOL;
  echo '      margin-left: auto;' . PHP_EOL;
  echo '      margin-right: auto;' . PHP_EOL;
  echo '    }' . PHP_EOL;
  echo '    @media (min-width: 768px) {' . PHP_EOL;
  echo '      .container {' . PHP_EOL;
  echo '        max-width: 730px;' . PHP_EOL;
  echo '      }' . PHP_EOL;
  echo '    }' . PHP_EOL;
  echo '    @media (max-width: 767px) {' . PHP_EOL;
  echo '      .pull-center {' . PHP_EOL;
  echo '        float: right;' . PHP_EOL;
  echo '      }' . PHP_EOL;
  echo '    }' . PHP_EOL;
  echo '  </style>' . PHP_EOL;
  echo '  <style>' . PHP_EOL;
  echo '    h6.hidden {' . PHP_EOL;
  echo '      visibility: hidden;' . PHP_EOL;
  echo '    }' . PHP_EOL;
  echo '  </style>' . PHP_EOL;
  echo '  <style>' . PHP_EOL;
  echo '    html,' . PHP_EOL;
  echo '    body,' . PHP_EOL;
  echo '    h1,' . PHP_EOL;
  echo '    h2,' . PHP_EOL;
  echo '    h3,' . PHP_EOL;
  echo '    h4,' . PHP_EOL;
  echo '    h5,' . PHP_EOL;
  echo '    h6 {' . PHP_EOL;
  echo '      font-family: sans-serif;' . PHP_EOL;
  echo '    }' . PHP_EOL;
  echo '  </style>' . PHP_EOL;

  if ($auto_reload_div !== null) {
    $safe_auto_reload_div = json_encode((string) $auto_reload_div, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP);
    echo '<script>' . PHP_EOL;
    echo 'function reload() {' . PHP_EOL;
    echo '  const target = ' . $safe_auto_reload_div . ';' . PHP_EOL;
    echo '  $("#" + target).load("#" + target + " > *");' . PHP_EOL;
    echo '  clearInterval();' . PHP_EOL;
    echo '  setTimeout(reload, ' . $reload_interval . ');' . PHP_EOL;
    echo '}' . PHP_EOL;
    echo 'setTimeout(reload, 1);' . PHP_EOL;
    echo '</script>' . PHP_EOL;
  }

  // finish the header
  echo '</head>' . PHP_EOL;
}

/**
 * @brief Show an automatic alert message on the screen.
 * @param message The message to display.
 * @param duration The duration in milliseconds for which the message should be displayed.
 */
function showAutoAlert($message, $duration = 1000) {
  echo '<script>' . PHP_EOL;
  echo '  const div = document.createElement("div");' . PHP_EOL;
  echo '  div.textContent = ' . json_encode($message) . ';' . PHP_EOL;
  echo '  div.style.cssText = "position:fixed;top:20px;left:50%;transform:translateX(-50%);background:#333;color:#fff;padding:15px 20px;border-radius:5px;z-index:9999;";' . PHP_EOL;
  echo '  document.body.appendChild(div);' . PHP_EOL;
  echo '  setTimeout(() => div.remove(), ' . $duration . ');' . PHP_EOL;
  echo '</script>' . PHP_EOL;
}
/**
 * @brief Show messages stored in the session and then clear them.
 * This function checks for various types of messages (error, warning, info, hint, short) in the session. If any of these messages are set, it displays them using the showAutoAlert function and then unsets the message from the session to prevent it from being shown again.
 */
function show_messages() {
  if (isset($_SESSION["error_message"])) {
    showAutoAlert($_SESSION["error_message"], 2000);
    unset($_SESSION["error_message"]);
  }
  if (isset($_SESSION["warning_message"])) {
    showAutoAlert($_SESSION["warning_message"], 2000);
    unset($_SESSION["warning_message"]);
  }
  if (isset($_SESSION["info_message"])) {
    showAutoAlert($_SESSION["info_message"], 2000);
    unset($_SESSION["info_message"]);
  }
  if (isset($_SESSION["hint_message"])) {
    showAutoAlert($_SESSION["hint_message"], 2000);
    unset($_SESSION["hint_message"]);
  }
  if (isset($_SESSION["short_message"])) {
    showAutoAlert($_SESSION["short_message"], 1000);
    unset($_SESSION["short_message"]);
  }
  if (isset($_SESSION["msg_info"])) {
    showAutoAlert($_SESSION["msg_info"], 1500);
    unset($_SESSION["msg_info"]);
  }
}

/** 
 * @brief Show the navigation bar.
 * This function includes the top navigation bar HTML and sets the body background color.
 */
/**
 * @deprecated Use show_status_navbar() instead.
 * @brief Show the legacy static navigation bar.
 */
function show_navbar() {
  $navbar_file = CSS_DIR . 'top_navbar.html';
  $navbar_html = file_get_contents($navbar_file);
  if ($navbar_html === false) {
    throw new RuntimeException('Unable to read navbar HTML: ' . $navbar_file);
  }
  echo mobile_rewrite_relative_urls($navbar_html);
  echo '<div id="navbar" style="margin-left:0px; margin-top:56px; background-color:WhiteSmoke; min-height:100vh;">';
}

/**
 * @brief Show the dynamic navigation + status icon bar.
 *        Replaces show_navbar(). Also opens <div id="navbar"> — caller must close it with </div>.
 */
function show_status_navbar(): void {
  require_once SYSTEM_DIR . 'nav_status_bar.php';
  $bar = new nav_status_bar();
  echo $bar->render_navbar();
}

function get_iso_datepicker_submit_js() {
  $str = PHP_EOL . '<script type="text/javascript">' . PHP_EOL;
  $str .= 'function syncPickerToIsoDate(isoInputId, value) {' . PHP_EOL;
  $str .= '  var isoInput = document.getElementById(isoInputId);' . PHP_EOL;
  $str .= '  if (!isoInput) return;' . PHP_EOL;
  $str .= '  isoInput.value = value;' . PHP_EOL;
  $str .= '  if (isoInput.form) isoInput.form.submit();' . PHP_EOL;
  $str .= '}' . PHP_EOL;
  $str .= 'function syncIsoDateToPicker(pickerInputId, value) {' . PHP_EOL;
  $str .= '  if (!/^\\d{4}-\\d{2}-\\d{2}$/.test(value)) return;' . PHP_EOL;
  $str .= '  var pickerInput = document.getElementById(pickerInputId);' . PHP_EOL;
  $str .= '  if (!pickerInput) return;' . PHP_EOL;
  $str .= '  pickerInput.value = value;' . PHP_EOL;
  $str .= '}' . PHP_EOL;
  $str .= 'function prepIsoDatePicker(isoInputId, pickerInputId) {' . PHP_EOL;
  $str .= '  var isoInput = document.getElementById(isoInputId);' . PHP_EOL;
  $str .= '  var pickerInput = document.getElementById(pickerInputId);' . PHP_EOL;
  $str .= '  if (!isoInput || !pickerInput) return;' . PHP_EOL;
  $str .= '  if (/^\\d{4}-\\d{2}-\\d{2}$/.test(isoInput.value)) {' . PHP_EOL;
  $str .= '    pickerInput.value = isoInput.value;' . PHP_EOL;
  $str .= '  }' . PHP_EOL;
  $str .= '}' . PHP_EOL;
  $str .= '</script>' . PHP_EOL;
  return $str;
}

function get_iso_datepicker_submit($post_name, $value, $sender = null, $what = null, $id = 0, $min_value = null) {
  $safe_name = htmlspecialchars($post_name, ENT_QUOTES, 'UTF-8');
  $safe_value = htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
  $safe_sender = ($sender !== null) ? htmlspecialchars((string) $sender, ENT_QUOTES, 'UTF-8') : null;
  $safe_what = ($what !== null) ? htmlspecialchars((string) $what, ENT_QUOTES, 'UTF-8') : null;
  $safe_id = intval($id);
  $safe_min_value = '';
  if (is_string($min_value) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $min_value) === 1) {
    $safe_min_value = htmlspecialchars($min_value, ENT_QUOTES, 'UTF-8');
  }
  $value_name = ($safe_sender === null) ? $safe_name : 'value';
  $id_base = preg_replace('/[^a-zA-Z0-9_]/', '_', $post_name);
  $iso_id = $id_base . '_iso';
  $picker_id = $id_base . '_picker';

  $form = '<form style="display:inline;" method="post" action="">' . PHP_EOL;
  if ($safe_sender !== null) {
    $form .= '<input type="hidden" name="sender" value="' . $safe_sender . '">' . PHP_EOL;
    $form .= '<input type="hidden" name="id" value="' . $safe_id . '">' . PHP_EOL;
    $form .= '<input type="hidden" name="what" value="' . ($safe_what !== null ? $safe_what : $safe_name) . '">' . PHP_EOL;
  }
  $form .= '<span style="white-space:nowrap;">' . PHP_EOL;
  $form .= '<input type="text" id="' . $iso_id . '" name="' . $value_name . '" inputmode="numeric" minlength="10" maxlength="10" pattern="[0-9]{4}-[0-9]{2}-[0-9]{2}" placeholder="YYYY-MM-DD" title="Use format YYYY-MM-DD" value="' . $safe_value . '" oninput="syncIsoDateToPicker(\'' . $picker_id . '\', this.value)" onchange="this.form.submit()">' . PHP_EOL;
  $form .= '<span style="position:relative;display:inline-block;margin-left:4px;vertical-align:middle;">' . PHP_EOL;
  $form .= '<button type="button" title="Open calendar" aria-label="Open calendar" tabindex="-1" style="pointer-events:none;">&#128197;</button>' . PHP_EOL;
  $form .= '<input type="date" id="' . $picker_id . '" value="' . $safe_value . '"' . ($safe_min_value !== '' ? ' min="' . $safe_min_value . '"' : '') . ' onfocus="prepIsoDatePicker(\'' . $iso_id . '\', \'' . $picker_id . '\')" onchange="syncPickerToIsoDate(\'' . $iso_id . '\', this.value)" style="position:absolute;left:0;top:0;width:100%;height:100%;opacity:0;cursor:pointer;">' . PHP_EOL;
  $form .= '</span>' . PHP_EOL;
  $form .= '</span>' . PHP_EOL;
  $form .= '</form>' . PHP_EOL;
  return $form;
}

function seconds_to_time($secs, $always_show_days = false) {
  $duration = max(0, intval($secs));
  $days = intdiv($duration, 24 * 60 * 60);
  $duration = $duration - ($days * 24 * 60 * 60);
  $hours = intdiv($duration, 60 * 60);
  $duration = $duration - ($hours * 60 * 60);
  $minutes = intdiv($duration, 60);
  $duration = $duration - ($minutes * 60);
  $seconds = $duration;

  if (($days > 0) || ($always_show_days)) {
    return sprintf('%d days %02d:%02d:%02d', $days, $hours, $minutes, $seconds);
  }
  return sprintf('%02d:%02d:%02d', $hours, $minutes, $seconds);
}
