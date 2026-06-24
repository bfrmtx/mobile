<?php

/**
 * @file nav_status_bar.php
 * @brief Defines nav_status_bar: generates the top navigation + live SVG status bar for all main pages.
 *        Replaces the static top_navbar.html / show_navbar() pattern.
 */
require_once __DIR__ . '/../config.php';
require_once SYSTEM_DIR . 'system_status.php';

class nav_status_bar extends status {

  /** Nav menu links shown in the hamburger dropdown. */
  private array $nav_links = [
    'status.php'   => 'System Status',
    'log.php'      => 'System Log',
    'index.php'    => 'Jobs',
    'subjob.php'   => 'Subjobs / EDI',
    'channels.php' => 'Channels',
    'selftest.php' => 'Selftest Result',
    'jobs.php'     => 'JobDB',
    'doc/index.html' => 'Assistance',
  ];

  public function __construct() {
    parent::__construct();
  }

  // ── Icon descriptor helpers ───────────────────────────────────────────────

  /** 
   * @brief we skip the wait for sync state here; recording shall be started with fix only (4).
   * @return array{svg:string, class:string} GPS lock status icon. */
  private function get_gps_icon(): array {
    $synced = ($this->gps_status !== null && $this->gps_status->get_sync_state() === 4);
    return ['svg' => 'gps.svg', 'class' => $synced ? 'icon-green' : 'icon-red'];
  }

  /** @return array{svg:string, class:string} Recording state icon. */
  private function get_recording_icon(): array {
    if ($this->recording_status !== null && $this->recording_status->get_sampling_rate() > 0) {
      return ['svg' => 'rec_active.svg', 'class' => ''];
    } elseif ($this->recording_status !== null && $this->recording_status->get_time_to_next_job() > 0) {
      return ['svg' => 'wait_start.svg', 'class' => 'icon-yellow'];
    }
    return ['svg' => 'idle.svg', 'class' => 'icon-green'];
  }

  /** @return array{svg:string, class:string} Battery icon for slot 1 or 2. */
  private function get_batt_icon(int $slot): array {
    $v = ($slot === 1)
      ? ($this->adu_status !== null ? $this->adu_status->get_batt_voltage_1() : 0.0)
      : ($this->adu_status !== null ? $this->adu_status->get_batt_voltage_2() : 0.0);

    if ($v > 12.8) {
      return ['svg' => 'battery-charge-level-100-percent-icon.svg', 'class' => 'icon-green'];
    } elseif ($v > 12.2) {
      return ['svg' => 'battery-charge-level-75-percent-icon.svg',  'class' => 'icon-green'];
    } elseif ($v > 11.8) {
      return ['svg' => 'battery-charge-level-50-percent-icon.svg',  'class' => 'icon-yellow'];
    }
    return ['svg' => 'battery-charge-level-20-percent-icon.svg', 'class' => 'icon-red'];
  }

  /**
   * @brief > 40 GB green, > 20 GB yellow, <= 20 GB red
   *  @return array{svg:string, class:string} SD card free space icon. 
   */
  private function get_disk_icon(): array {
    $GB = ($this->adu_status !== null) ? $this->adu_status->get_free_disk_space_mb_sd() : 0;
    if ($GB > 40 * 1024) {
      $class = 'icon-green';
    } elseif ($GB > 20 * 1024) {
      $class = 'icon-yellow';
    } else {
      $class = 'icon-red';
    }
    return ['svg' => 'disk_status.svg', 'class' => $class];
  }

  // ── Icons fragment (reused by AJAX endpoint) ─────────────────────────────

  /**
   * @brief Render only the status icon spans — no wrapping div.
   *        Called by render_navbar() and by the status_icons.php AJAX endpoint.
   * @return string  HTML of all <span class="status-icon">…</span> elements.
   */
  public function render_status_icons(): string {
    $html = '';
    foreach (
      [
        $this->get_gps_icon(),
        $this->get_batt_icon(1),
        $this->get_batt_icon(2),
        $this->get_disk_icon(),
        $this->get_recording_icon(),
      ] as $icon
    ) {
      $html .= '    ' . $this->render_icon($icon['svg'], $icon['class']) . PHP_EOL;
    }
    return $html;
  }

  /**
   * @brief Render GPS date-time label and status icons together.
   *        This fragment is used in the navbar and in the AJAX refresh endpoint.
   * @return string  HTML for the status bar content.
   */
  public function render_status_bar_content(): string {
    $gps_date_time = ($this->gps_status !== null) ? trim($this->gps_status->get_date_time()) : '';
    if ($gps_date_time === '') {
      $gps_date_time = '--';
    }

    $safe_date_time = htmlspecialchars($gps_date_time, ENT_QUOTES, 'UTF-8');

    $html  = '    <span class="gps-date-time" style="color:#fff; margin-right:10px; font-size:0.9em; white-space:nowrap;">' . $safe_date_time . '</span>' . PHP_EOL;
    $html .= '    <span class="status-icons">' . PHP_EOL;
    $html .= $this->render_status_icons();
    $html .= '    </span>' . PHP_EOL;

    return $html;
  }

  // ── SVG renderer ─────────────────────────────────────────────────────────

  /**
   * @brief Load a UTF-8 SVG from ICONS_DIR, inject a CSS class onto the <svg> element, and return inline HTML.
   * @param string $svg_file  Filename only, e.g. 'gps.svg'
   * @param string $css_class e.g. 'icon-green'
   * @return string  Ready-to-echo HTML.
   */
  private function render_icon(string $svg_file, string $css_class = ''): string {
    $path = ICONS_DIR . $svg_file;
    $svg  = @file_get_contents($path);
    if ($svg === false) {
      return ''; // missing icon: silently skip
    }
    // Inject class into the opening <svg …> tag (prepend so any existing class is preserved).
    if ($css_class !== '') {
      $svg = preg_replace('/<svg\b/', '<svg class="' . htmlspecialchars($css_class, ENT_QUOTES, 'UTF-8') . '"', $svg, 1);
    }
    if ($svg === null || strpos($svg, '<svg') === false) {
      return '';
    }
    return '<span class="status-icon">' . $svg . '</span>';
  }

  // ── Public render ─────────────────────────────────────────────────────────

  /**
   * @brief Render the full top navigation + status icon bar.
   *        Also opens the <div id="navbar"> wrapper (caller must close it with </div>).
   * @return string  Complete HTML for the header + wrapper opening tag.
   */
  public function render_navbar(): string {
    $html  = '<header class="topnav_adu">' . PHP_EOL;

    // ── Hidden checkbox drives hamburger CSS toggle ──────────────────────────
    $html .= '  <input class="menu-btn" type="checkbox" id="menu-btn"/>' . PHP_EOL;
    $html .= '  <label class="menu-icon" for="menu-btn"><span class="navicon"></span></label>' . PHP_EOL;

    // ── Status icons (right side, floated right) ─────────────────────────────
    $html .= '  <div class="status-icons-bar">' . PHP_EOL;
    $html .= $this->render_status_bar_content();
    $html .= '  </div>' . PHP_EOL;

    // ── Dropdown nav links ────────────────────────────────────────────────────
    $html .= '  <div id="menuLinks" class="menuLinksDiv">' . PHP_EOL;
    $html .= '    <ul>' . PHP_EOL;
    foreach ($this->nav_links as $href => $label) {
      $safe_href  = htmlspecialchars($href,  ENT_QUOTES, 'UTF-8');
      $safe_label = htmlspecialchars($label, ENT_QUOTES, 'UTF-8');
      $html .= '      <li><a href="' . $safe_href . '" onclick="menuLinkClicked()">' . $safe_label . '</a></li>' . PHP_EOL;
    }
    $html .= '    </ul>' . PHP_EOL;
    $html .= '  </div>' . PHP_EOL;

    // ── Menu JS (same logic as old top_navbar.html) ───────────────────────────
    $html .= '  <script>' . PHP_EOL;
    $html .= '    document.getElementById("menuLinks").style.display = "none";' . PHP_EOL;
    $html .= '    function menuLinkClicked() {' . PHP_EOL;
    $html .= '      document.getElementById("menuLinks").style.display = "none";' . PHP_EOL;
    $html .= '      document.getElementById("menu-btn").checked = false;' . PHP_EOL;
    $html .= '    }' . PHP_EOL;
    $html .= '    document.getElementById("menu-btn").addEventListener("change", function() {' . PHP_EOL;
    $html .= '      document.getElementById("menuLinks").style.display = this.checked ? "block" : "none";' . PHP_EOL;
    $html .= '    });' . PHP_EOL;
    $html .= '    // Auto-refresh status icons every 2 seconds via AJAX' . PHP_EOL;
    $html .= '    (function refreshStatusIcons() {' . PHP_EOL;
    $html .= '      $.get("system/status_icons.php", function(html) {' . PHP_EOL;
    $html .= '        $(".status-icons-bar").html(html);' . PHP_EOL;
    $html .= '      });' . PHP_EOL;
    $html .= '      setTimeout(refreshStatusIcons, 2000);' . PHP_EOL;
    $html .= '    })();' . PHP_EOL;
    $html .= '  </script>' . PHP_EOL;

    $html .= '</header>' . PHP_EOL;

    // Open the main content wrapper (matches what show_navbar() opened).
    $html .= '<div id="navbar" style="margin-left:0px; margin-top:56px; background-color:WhiteSmoke; min-height:100vh;">' . PHP_EOL;

    return $html;
  }
}
