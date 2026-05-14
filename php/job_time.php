<?php

/**
 * @file job_time.php
 * */

/** class job_time
 * @brief UTC STRICTLY USED!
 * @details start_time is UTC
 * @details duration is in seconds (integer)
 * @details end_time is UTC and calculated from start_time and duration
 * @variables such as utc_offset and dst are for display purposes only. If set, input values are immediately converted to UTC!!
 * @details the class SHARES kv! 
 */
class job_time {

  protected DateTimeImmutable $start_date_time;   //!< in the format YYYY-MM-DD HH:MM:SS, MASTER
  protected int $utc_offset_seconds;
  protected string $selected_utc_offset;
  protected bool $dst;
  protected bool $grid;                           // pin to a 64s intval since timestamp 0
  protected int $duration;                        //!< in seconds
  protected int $prep_time = 30;                  //!< in seconds, default 30s prep time for job to be ready after start time, so the job must be at minimum 30s in the future, otherwise it will not start.
  public function __construct() {
    $this->utc_offset_seconds = 0;
    $this->selected_utc_offset = '+00:00';
    $this->dst = false;
    $this->grid = true;
    $this->sync_display_time_preferences();
    $this->start_date_time = new DateTimeImmutable('@' . time()); // initialize to current time in UTC
    $this->start_date_time = $this->start_date_time->setTimezone(new DateTimeZone('UTC'));
    $this->calc_grid_start_time();
    $this->duration = 60; // default duration of 60 seconds
  }
  public function set_utc_offset_seconds(int $offset_seconds_) {
    $this->utc_offset_seconds = $offset_seconds_;
  }
  public function set_dst(bool $dst_) {
    $this->dst = $dst_;
  }
  public function get_utc_offset_seconds(): int {
    return $this->utc_offset_seconds;
  }
  public function get_dst(): bool {
    return $this->dst;
  }
  public function get_prep_time(): int {
    return $this->prep_time;
  }

  public function set_grid(bool $grid_) {
    $this->grid = $grid_;
  }
  public function get_grid(): bool {
    return $this->grid;
  }

  public function get_selected_utc_offset(): string {
    return $this->selected_utc_offset;
  }

  protected function normalize_utc_offset_string(string $utc_offset): string {
    $normalized_offset = trim($utc_offset);
    if ($normalized_offset === '' || $normalized_offset === 'UTC' || $normalized_offset === '00:00') {
      $normalized_offset = '+00:00';
    }
    if (preg_match('/^\d{2}:\d{2}$/', $normalized_offset) === 1) {
      $normalized_offset = '+' . $normalized_offset;
    }
    if (preg_match('/^[+-]\d{2}:\d{2}$/', $normalized_offset) !== 1) {
      $normalized_offset = '+00:00';
    }
    if (!array_key_exists($normalized_offset, $this->adu_timezones)) {
      $normalized_offset = '+00:00';
    }
    return $normalized_offset;
  }

  /**
   * @brief Sync the display time preferences from session and POST data, and update the UTC offset seconds accordingly.
   */
  protected function sync_display_time_preferences(): void {
    if (session_status() !== PHP_SESSION_ACTIVE) {
      @session_start();
    }

    if (isset($_SESSION['job_time_utc_offset']) && is_string($_SESSION['job_time_utc_offset'])) {
      $this->selected_utc_offset = $this->normalize_utc_offset_string($_SESSION['job_time_utc_offset']);
    }
    if (isset($_SESSION['job_time_dst'])) {
      $this->dst = ($_SESSION['job_time_dst'] === '1');
    }
    if (isset($_SESSION['job_time_grid'])) {
      $this->grid = ($_SESSION['job_time_grid'] === '1');
    }

    if (isset($_POST['display_timezone_form'])) {
      if (isset($_POST['display_utc_offset']) && is_string($_POST['display_utc_offset'])) {
        $this->selected_utc_offset = $this->normalize_utc_offset_string($_POST['display_utc_offset']);
      }
      $this->dst = array_key_exists('display_dst', $_POST);
      $this->grid = array_key_exists('display_grid', $_POST);
      $_SESSION['job_time_utc_offset'] = $this->selected_utc_offset;
      $_SESSION['job_time_dst'] = $this->dst ? '1' : '0';
      $_SESSION['job_time_grid'] = $this->grid ? '1' : '0';
    }

    $this->utc_offset_seconds = $this->utc_offset_to_seconds($this->selected_utc_offset, $this->dst);
  }

  public function utc_offset_to_seconds(string $utc_offset, ?bool $dst = null): int {
    $normalized_offset = $this->normalize_utc_offset_string($utc_offset);
    if (preg_match('/^(?<sign>[+-])(?<hours>\d{2}):(?<minutes>\d{2})$/', $normalized_offset, $matches) !== 1) {
      return 0;
    }

    $seconds = (intval($matches['hours']) * 3600) + (intval($matches['minutes']) * 60);
    if ($matches['sign'] === '-') {
      $seconds *= -1;
    }
    if (($dst ?? $this->dst) === true) {
      $seconds += 3600;
    }
    return $seconds;
  }

  protected function shift_datetime_seconds(DateTimeImmutable $date_time, int $seconds): DateTimeImmutable {
    $timestamp = $date_time->getTimestamp() + $seconds;
    return (new DateTimeImmutable('@' . $timestamp))->setTimezone(new DateTimeZone('UTC'));
  }

  public function utc_to_local_datetime(DateTimeImmutable $utc_datetime): DateTimeImmutable {
    $this->sync_display_time_preferences();
    return $this->shift_datetime_seconds($utc_datetime->setTimezone(new DateTimeZone('UTC')), $this->utc_offset_seconds);
  }

  public function local_to_utc_datetime(DateTimeImmutable $local_datetime): DateTimeImmutable {
    $this->sync_display_time_preferences();
    return $this->shift_datetime_seconds($local_datetime->setTimezone(new DateTimeZone('UTC')), -1 * $this->utc_offset_seconds);
  }

  public function format_display_datetime(DateTimeImmutable $utc_datetime, string $format = 'Y-m-d H:i:s'): string {
    return $this->utc_to_local_datetime($utc_datetime)->format($format);
  }

  /**
   * @brief Generate the HTML form for selecting display timezone and DST preferences.
   * @brief POST will be catched above.
   */
  public function timezone_and_dst_form(): string {
    $this->sync_display_time_preferences();
    $form = '<form method="post" action="">' . PHP_EOL;
    $form .= '<input type="hidden" name="display_timezone_form" value="1" />' . PHP_EOL;
    $form .= '<select name="display_utc_offset" id="display_utc_offset" style="max-width:280px;width:80%;" onchange="this.form.submit()">' . PHP_EOL;

    foreach ($this->adu_timezones as $utc_offset => $label) {
      $selected = ($utc_offset === $this->selected_utc_offset) ? ' selected="selected"' : '';
      $form .= '<option value="' . htmlspecialchars($utc_offset, ENT_QUOTES, 'UTF-8') . '"' . $selected . '>' . htmlspecialchars($label, ENT_QUOTES, 'UTF-8') . '</option>' . PHP_EOL;
    }

    $dst_checked = $this->dst ? ' checked="checked"' : '';
    $grid_checked = $this->grid ? ' checked="checked"' : '';
    $form .= '</select>' . PHP_EOL;
    $form .= '<span style="display:inline-flex;align-items:center;gap:8px;margin-left:8px;white-space:nowrap;">';
    $form .= '<label style="margin:0;white-space:nowrap;">';
    $form .= '<input type="checkbox" name="display_dst" value="1"' . $dst_checked . ' onchange="this.form.submit()"> DST';
    $form .= '</label>' . PHP_EOL;
    $form .= '<label style="margin:0;white-space:nowrap;">';
    $form .= '<input type="checkbox" name="display_grid" value="1"' . $grid_checked . ' onchange="this.form.submit()"> &nbsp;&nbsp;Grid';
    $form .= '</label>' . PHP_EOL;
    $form .= '</span>' . PHP_EOL;
    $form .= '</form>' . PHP_EOL;
    return $form;
  }

  /** @brief Return the start datetime in local display time. */
  public function get_local_start_datetime(): DateTimeImmutable {
    $this->normalize_start_time();
    return $this->utc_to_local_datetime($this->start_date_time);
  }

  public function update_job_start_from_local_input(string $what, $value): bool {
    $local_start = $this->get_local_start_datetime();
    $updated_local_start = $local_start;

    if ($what === 'start_date') {
      $local_date = trim((string) $value);
      if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $local_date) !== 1) {
        return false;
      }
      $updated_local_start = DateTimeImmutable::createFromFormat('Y-m-d H:i:s', $local_date . ' ' . $local_start->format('H:i:s'), new DateTimeZone('UTC'));
      if ($updated_local_start === false) {
        return false;
      }
    } elseif ($what === 'start_hours') {
      $updated_local_start = $local_start->setTime(max(0, min(23, intval($value))), intval($local_start->format('i')), intval($local_start->format('s')));
    } elseif ($what === 'start_minutes') {
      $updated_local_start = $local_start->setTime(intval($local_start->format('H')), max(0, min(59, intval($value))), intval($local_start->format('s')));
    } elseif ($what === 'start_seconds') {
      $updated_local_start = $local_start->setTime(intval($local_start->format('H')), intval($local_start->format('i')), max(0, min(59, intval($value))));
    } else {
      return false;
    }

    $updated_utc_start = $this->local_to_utc_datetime($updated_local_start);
    $normalized_timestamp = $this->normalize_to_minimum_start_timestamp($updated_utc_start->getTimestamp());
    $this->start_date_time = (new DateTimeImmutable('@' . $normalized_timestamp))->setTimezone(new DateTimeZone('UTC'));
    return true;
  }

  public function update_job_stop_date_from_local_input($value): bool {
    $local_date = trim((string) $value);
    if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $local_date) !== 1) {
      return false;
    }

    $local_stop = $this->utc_to_local_datetime($this->get_stop_datetime_utc());
    $updated_local_stop = DateTimeImmutable::createFromFormat('Y-m-d H:i:s', $local_date . ' ' . $local_stop->format('H:i:s'), new DateTimeZone('UTC'));
    if ($updated_local_stop === false) {
      return false;
    }

    $local_start = $this->get_local_start_datetime();
    if ($updated_local_stop->getTimestamp() < $local_start->getTimestamp()) {
      $updated_local_stop = $local_start;
    }

    $updated_utc_stop = $this->local_to_utc_datetime($updated_local_stop);
    $this->set_duration(max(0, $updated_utc_stop->getTimestamp() - $this->start_date_time->getTimestamp()));
    return true;
  }

  /**
   * @brief Handle POST updates owned by job_time (start/stop and duration widgets).
   * @return bool True when at least one job_time field changed.
   */
  public function handle_post_updates(): bool {
    if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
      return false;
    }

    if (
      isset($_POST['sender']) && $_POST['sender'] === 'job_time'
      && isset($_POST['what']) && isset($_POST['value'])
    ) {
      $what = $_POST['what'];
      $value = $_POST['value'];

      if (in_array($what, ['start_date', 'start_hours', 'start_minutes', 'start_seconds'], true)) {
        return $this->update_job_start_from_local_input($what, $value);
      }

      if ($what === 'stop_date') {
        return $this->update_job_stop_date_from_local_input($value);
      }

      if (in_array($what, ['duration_hours', 'duration_minutes', 'duration_seconds'], true)) {
        $days = intdiv(max(0, $this->duration), 86400);
        [$h, $m, $s] = $this->get_duration_parts();
        if ($what === 'duration_hours')   $h = max(0, min(23, intval($value)));
        if ($what === 'duration_minutes') $m = max(0, min(59, intval($value)));
        if ($what === 'duration_seconds') $s = max(0, min(59, intval($value)));
        $this->set_duration(($days * 86400) + ($h * 3600) + ($m * 60) + $s);
        return true;
      }
    }

    return false;
  }

  // java script sliders will add or subtract days, hours, minutes or seconds to the start time. We convert that to seconds and add it to the start time.
  public function adjust_start_time_seconds(int $adjust_seconds) {
    if ($adjust_seconds >= 0) {
      $this->start_date_time = $this->start_date_time->add(new DateInterval('PT' . $adjust_seconds . 'S'));
    } else {
      $this->start_date_time = $this->start_date_time->sub(new DateInterval('PT' . abs($adjust_seconds) . 'S'));
    }
    $this->calc_grid_start_time();
  }
  public function adjust_start_time_minutes(int $adjust_minutes) {
    $this->adjust_start_time_seconds($adjust_minutes * 60);
  }
  public function adjust_start_time_hours(int $adjust_hours) {
    $this->adjust_start_time_seconds($adjust_hours * 3600);
  }
  // to be done: date from date picker

  public function set_duration(int $duration_) {
    $this->duration = $duration_;
  }
  public function get_duration(): int {
    return $this->duration;
  }
  protected function normalize_to_minimum_start_timestamp(int $timestamp): int {
    $minimum_timestamp = time() + $this->prep_time;
    $timestamp = max($timestamp, $minimum_timestamp);

    if ($this->grid) {
      $remainder = $timestamp % 64;
      if ($remainder !== 0) {
        $timestamp += 64 - $remainder;
      }
    }

    return $timestamp;
  }

  protected function calc_grid_start_time() {
    $timestamp = $this->normalize_to_minimum_start_timestamp($this->start_date_time->getTimestamp());
    $this->start_date_time = (new DateTimeImmutable('@' . $timestamp))->setTimezone(new DateTimeZone('UTC'));
  }

  protected function normalize_start_time(): void {
    $normalized_timestamp = $this->normalize_to_minimum_start_timestamp($this->start_date_time->getTimestamp());
    if ($normalized_timestamp !== $this->start_date_time->getTimestamp()) {
      $this->start_date_time = (new DateTimeImmutable('@' . $normalized_timestamp))->setTimezone(new DateTimeZone('UTC'));
    }
  }

  protected function render_slider_form(string $what, string $slider_id, int $value, int $min, int $max): string {
    $safe_what = htmlspecialchars($what, ENT_QUOTES, 'UTF-8');
    $safe_slider_id = htmlspecialchars($slider_id, ENT_QUOTES, 'UTF-8');

    $form = '<form method="post" action="">' . PHP_EOL;
    $form .= '<input type="hidden" name="sender" value="job_time" />' . PHP_EOL;
    $form .= '<input type="hidden" name="id" value="0" />' . PHP_EOL;
    $form .= '<input type="hidden" name="what" value="' . $safe_what . '" />' . PHP_EOL;
    $form .= '<input class="slider" style="width:90%" type="range" name="value" id="' . $safe_slider_id . '" value="' . $value . '" min="' . $min . '" max="' . $max . '" step="1" onchange="this.form.submit()">' . PHP_EOL;
    $form .= '</form>' . PHP_EOL;
    return $form;
  }

  public function select_start_time(string $post_name): string {
    $start_date_time = $this->get_local_start_datetime();

    if ($post_name === 'start_hours') {
      return $this->render_slider_form($post_name, $post_name, intval($start_date_time->format('H')), 0, 23);
    }
    if ($post_name === 'start_minutes') {
      return $this->render_slider_form($post_name, $post_name, intval($start_date_time->format('i')), 0, 59);
    }
    if ($post_name === 'start_seconds') {
      return $this->render_slider_form($post_name, $post_name, intval($start_date_time->format('s')), 0, 59);
    }

    throw new InvalidArgumentException('Unsupported start slider: ' . $post_name);
  }

  public function select_duration_hours(string $post_name): string {
    [$hours] = $this->get_duration_parts();
    return $this->render_slider_form($post_name, $post_name, $hours, 0, 23);
  }

  public function select_duration_minutes(string $post_name): string {
    [, $minutes] = $this->get_duration_parts();
    return $this->render_slider_form($post_name, $post_name, $minutes, 0, 59);
  }

  public function select_duration_seconds(string $post_name): string {
    [,, $seconds] = $this->get_duration_parts();
    return $this->render_slider_form($post_name, $post_name, $seconds, 0, 59);
  }

  // ── Self-contained accessors (used when job extends job_time) ────────────

  /** @brief Return the UTC start datetime (master value). */
  public function get_start_datetime_utc(): DateTimeImmutable {
    return $this->start_date_time;
  }

  /** @brief Return the UTC stop datetime derived from start + duration. */
  public function get_stop_datetime_utc(): DateTimeImmutable {
    $stop_ts = $this->start_date_time->getTimestamp() + $this->duration;
    return (new DateTimeImmutable('@' . $stop_ts))->setTimezone(new DateTimeZone('UTC'));
  }

  /** @brief Return the start date as a 'Y-m-d' string (UTC). */
  public function get_start_date(): string {
    return $this->start_date_time->format('Y-m-d');
  }

  /** @brief Return the start time as a 'H:i:s' string (UTC). */
  public function get_start_time(): string {
    return $this->start_date_time->format('H:i:s');
  }

  /** @brief Set the date part of start_date_time; keep existing time. */
  public function set_start_date(string $date): void {
    if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $date) !== 1) {
      return;
    }
    $new_dt = DateTimeImmutable::createFromFormat(
      'Y-m-d H:i:s',
      $date . ' ' . $this->start_date_time->format('H:i:s'),
      new DateTimeZone('UTC')
    );
    if ($new_dt !== false) {
      $this->start_date_time = $new_dt;
    }
  }

  /** @brief Set the time part of start_date_time; keep existing date. */
  public function set_start_time(string $time): void {
    if (preg_match('/^\d{2}:\d{2}:\d{2}$/', $time) !== 1) {
      return;
    }
    $new_dt = DateTimeImmutable::createFromFormat(
      'Y-m-d H:i:s',
      $this->start_date_time->format('Y-m-d') . ' ' . $time,
      new DateTimeZone('UTC')
    );
    if ($new_dt !== false) {
      $this->start_date_time = $new_dt;
    }
  }

  /**
   * @brief Decompose the duration into [hours, minutes, seconds].
   * @return int[] Three-element array: [hours, minutes, seconds].
   */
  public function get_duration_parts(): array {
    $d = max(0, $this->duration);
    $day_remainder = $d % 86400;
    return [intdiv($day_remainder, 3600), intdiv($day_remainder % 3600, 60), $day_remainder % 60];
  }

  // ── Formatting helpers ──────────────────────────────────────────────────

  public function format_duration_hms(int $duration): string {
    $duration = max(0, $duration);
    $hours = intdiv($duration, 3600);
    $minutes = intdiv($duration % 3600, 60);
    $seconds = $duration % 60;
    return sprintf('%02d:%02d:%02d', $hours, $minutes, $seconds);
  }

  public $adu_timezones = array(
    '-12:00' => '[UTC - 12] Baker Island Time',
    '-11:00' => '[UTC - 11] Niue Time, Samoa Standard Time',
    '-10:00' => '[UTC - 10] Hawaii-Aleutian Standard Time, Cook Island Time',
    '-09:30' => '[UTC - 9:30] Marquesas Islands Time',
    '-09:00' => '[UTC - 9] Alaska Standard Time, Gambier Island Time',
    '-08:00' => '[UTC - 8] Pacific Standard Time',
    '-07:00' => '[UTC - 7] Mountain Standard Time',
    '-06:00' => '[UTC - 6] Central Standard Time',
    '-05:00' => '[UTC - 5] Eastern Standard Time',
    '-04:30' => '[UTC - 4:30] Venezuelan Standard Time',
    '-04:00' => '[UTC - 4] Atlantic Standard Time',
    '-03:30' => '[UTC - 3:30] Newfoundland Standard Time',
    '-03:00' => '[UTC - 3] Amazon Standard Time, Central Greenland Time',
    '-02:00' => '[UTC - 2] Fernando de Noronha Time, South Georgia Time',
    '-01:00' => '[UTC - 1] Azores Standard Time, Cape Verde, Eastern Greenland Time',
    '+00:00' => '[UTC] UTC, Western European Time',
    '+01:00' => '[UTC + 1] Central European Time, West African Time',
    '+02:00' => '[UTC + 2] Eastern European Time, Central African Time',
    '+03:00' => '[UTC + 3] Moscow Standard Time, Eastern African Time',
    '+03:30' => '[UTC + 3:30] Iran Standard Time',
    '+04:00' => '[UTC + 4] Gulf Standard Time, Samara Standard Time',
    '+04:30' => '[UTC + 4:30] Afghanistan Time',
    '+05:00' => '[UTC + 5] Pakistan Standard Time, Yekaterinburg Standard Time',
    '+05:30' => '[UTC + 5:30] Indian Standard Time, Sri Lanka Time',
    '+05:45' => '[UTC + 5:45] Nepal Time',
    '+06:00' => '[UTC + 6] Bangladesh Time, Bhutan Time, Novosibirsk Standard Time',
    '+06:30' => '[UTC + 6:30] Cocos Islands Time, Myanmar Time',
    '+07:00' => '[UTC + 7] Indochina Time, Krasnoyarsk Standard Time',
    '+08:00' => '[UTC + 8] Chinese Standard Time, AUS Western Standard Time, Irkutsk ST',
    '+08:45' => '[UTC + 8:45] Southeastern Western Australia Standard Time',
    '+09:00' => '[UTC + 9] Japan Standard Time, Korea Standard Time, Chita Standard Time',
    '+09:30' => '[UTC + 9:30] Australian Central Standard Time',
    '+10:00' => '[UTC + 10] Australian Eastern Standard Time, Vladivostok Standard Time',
    '+10:30' => '[UTC + 10:30] Lord Howe Standard Time',
    '+11:00' => '[UTC + 11] Solomon Island Time, Magadan Standard Time',
    '+11:30' => '[UTC + 11:30] Norfolk Island Time',
    '+12:00' => '[UTC + 12] New Zealand Time, Fiji Time, Kamchatka Standard Time',
    '+12:45' => '[UTC + 12:45] Chatham Islands Time',
    '+13:00' => '[UTC + 13] Tonga Time, Phoenix Islands Time',
    '+14:00' => '[UTC + 14] Line Island Time'
  );
}
