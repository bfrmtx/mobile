<?php

/**
 * @file job_time.php
 */

require_once __DIR__ . '/../traits/global_vars.php';
/**
 * @brief This file contains the job_time class 
 * @details the class works in UTC ONLY!!!!
 * @details display is either UTC or local time based on the SESSION offset + DST, but storage is always UTC
 * @details takes care of $_SESSION["job_time_utc_offset"], $_SESSION["job_time_dst"], $_SESSION["job_time_grid"]
 */

class job_time extends DateTimeImmutable {
  // variables
  protected int $duration = MIN_JOB_DURATION;   //!< duration of the job in seconds, default is 0, meaning no duration set yet
  protected bool $job_time_grid = true;         //!< whether the job time is aligned to the grid or not, default is true
  public DateTimeImmutable $display_time;       //!<  display-only time derived from the UTC storage value and session offset
  protected string $job_time_utc_offset = '+00:00'; //!<  DISPLAY selected UTC offset like +02:00, -05:30 etc
  protected int $job_time_dst = 0;              //!< whether the DISPLAY job time is in daylight saving time or not, default is false

  private static function utc_timezone(): DateTimeZone {
    return new DateTimeZone('UTC');
  }

  private static function pin_to_next_grid(DateTimeImmutable $utc_time): DateTimeImmutable {
    // idempotent: only pushed forward when too soon or misaligned, so re-construction never keeps drifting the start time
    $minimum_timestamp = time() + PREP_TIME;
    $candidate_timestamp = max($utc_time->getTimestamp(), $minimum_timestamp);
    $remainder = $candidate_timestamp % GRID;

    if ($remainder !== 0) {
      $candidate_timestamp += GRID - $remainder;
    }

    return (new DateTimeImmutable('@' . $candidate_timestamp))->setTimezone(self::utc_timezone());
  }

  private static function normalize_utc_offset(string $utc_offset): string {
    $utc_offset = trim($utc_offset);

    if ($utc_offset === '') {
      return '+00:00';
    }

    if ($utc_offset === 'Z' || $utc_offset === 'UTC') {
      return '+00:00';
    }

    if (!preg_match('/^([+-]?)(\d{1,2})(?::?(\d{2}))?$/', $utc_offset, $matches)) {
      throw new InvalidArgumentException('Invalid UTC offset format.');
    }

    $sign = $matches[1] === '-' ? '-' : '+';
    $hours = (int) $matches[2];
    $minutes = isset($matches[3]) ? (int) $matches[3] : 0;

    if ($hours > 14 || $minutes > 59 || ($hours === 14 && $minutes !== 0)) {
      throw new InvalidArgumentException('UTC offset is out of range.');
    }

    return sprintf('%s%02d:%02d', $sign, $hours, $minutes);
  }

  /**
   * @brief Construct a new job_time object.
   * @param string $start_date Empty for UTC now, otherwise an ISO date such as YYYY-MM-DD.
   * @param string $start_time Empty with $start_date for UTC now, otherwise an ISO time such as HH:MM or HH:MM:SS.
   * @param int|null $duration_ Optional duration in seconds.
   * @details Storage is always normalized to UTC.
   * @note We use YYYY-MM-DD HH:MM:SS format for all stored values, and the timezone is always UTC, no fractional seconds.
   */
  public function __construct(string $start_date = '', string $start_time = '', ?int $duration_ = null) {
    if (session_status() !== PHP_SESSION_ACTIVE) {
      @session_start();
    }

    if (session_status() === PHP_SESSION_ACTIVE) {
      $this->job_time_grid = (bool) ($_SESSION['job_time_grid'] ?? $this->job_time_grid);
      try {
        $this->job_time_utc_offset = self::normalize_utc_offset((string) ($_SESSION['job_time_utc_offset'] ?? '+00:00'));
      } catch (InvalidArgumentException $e) {
        $this->job_time_utc_offset = '+00:00'; // fall back on corrupted/invalid session data instead of throwing
      }
      $this->job_time_dst = $_SESSION['job_time_dst'] ?? 0;

      // timezone_and_dst_form() posts here; apply immediately and persist so the next request sees it too
      if (isset($_POST['display_timezone_form'])) {
        if (isset($_POST['display_utc_offset']) && is_string($_POST['display_utc_offset'])) {
          try {
            $this->job_time_utc_offset = self::normalize_utc_offset($_POST['display_utc_offset']);
          } catch (InvalidArgumentException $e) {
            $this->job_time_utc_offset = '+00:00';
          }
        }
        $this->job_time_dst = array_key_exists('display_dst', $_POST) ? 1 : 0;
        $this->job_time_grid = array_key_exists('display_grid', $_POST);
        $_SESSION['job_time_utc_offset'] = $this->job_time_utc_offset;
        $_SESSION['job_time_dst'] = $this->job_time_dst;
        $_SESSION['job_time_grid'] = $this->job_time_grid;
      }
    }

    if ($start_date === '' && $start_time === '') {
      $utc_time = new DateTimeImmutable('now', self::utc_timezone());
    } elseif ($start_date === '' || $start_time === '') {
      throw new InvalidArgumentException('start_date and start_time must both be provided together.');
    } else {
      $parsed_time = new DateTimeImmutable($start_date . 'T' . $start_time, self::utc_timezone());
      $utc_time = $parsed_time->setTimezone(self::utc_timezone());
    }

    if ($this->job_time_grid) {
      $utc_time = self::pin_to_next_grid($utc_time);
    }

    parent::__construct($utc_time->format('Y-m-d H:i:s'), self::utc_timezone());

    if ($duration_ === null || $duration_ < MIN_JOB_DURATION) {
      $duration_ = MIN_JOB_DURATION;
    }

    $this->duration = $duration_;

    $this->display_time = $this->utc_to_local_datetime($utc_time);
  }

  private function offset_timezone(): DateTimeZone {
    return new DateTimeZone($this->job_time_utc_offset);
  }

  public function set_start_time_now(): void {
    // __construct() re-applies grid pinning itself (based on job_time_grid), so we only need to enforce the PREP_TIME lead here
    $utc_time = (new DateTimeImmutable('now', self::utc_timezone()))->modify('+' . PREP_TIME . ' seconds');
    self::__construct($utc_time->format('Y-m-d'), $utc_time->format('H:i:s'), $this->duration);
  }

  /**
   * @brief Convert a UTC DateTimeImmutable to the selected display timezone (offset + DST).
   */
  public function utc_to_local_datetime(DateTimeImmutable $utc_time): DateTimeImmutable {
    $local_time = $utc_time->setTimezone($this->offset_timezone());
    if ($this->job_time_dst) {
      $local_time = $local_time->modify('+1 hour');
    }
    return $local_time;
  }

  /**
   * @brief Convert a display-local (offset + DST) date/time pair back to UTC.
   */
  private function local_to_utc(string $date, string $time): DateTimeImmutable {
    $local_time = new DateTimeImmutable($date . 'T' . $time, $this->offset_timezone());
    if ($this->job_time_dst) {
      $local_time = $local_time->modify('-1 hour');
    }
    return $local_time->setTimezone(self::utc_timezone());
  }

  /**
   * @brief Format an arbitrary UTC DateTimeImmutable in the selected display timezone.
   */
  public function format_display_datetime(DateTimeImmutable $utc_time, string $format = 'Y-m-d H:i:s'): string {
    return $this->utc_to_local_datetime($utc_time)->format($format);
  }

  /** @brief Return the start datetime in display (offset + DST) time. */
  public function get_local_start_datetime(): DateTimeImmutable {
    return $this->display_time;
  }

  /** @brief Return the UTC start datetime (master value, this object itself). */
  public function get_start_datetime_utc(): DateTimeImmutable {
    return $this;
  }

  /** @brief Return the UTC stop datetime derived from start + duration. */
  public function get_stop_datetime_utc(): DateTimeImmutable {
    return $this->add(new DateInterval('PT' . $this->duration . 'S'));
  }

  /**
   * @brief Get the current job time as a job_time object.
   * @return job_time The current job time.
   */
  public static function now(): job_time {
    return new self();
  }

  // logic operators ==, !=, <, >, for job_time (and job/adu/frequency_handler) objects, time comparison only
  public function equals(job_time $other): bool {
    return $this->get_start_datetime_utc() === $other->get_start_datetime_utc();
  }
  public function not_equals(job_time $other): bool {
    return !$this->equals($other);
  }
  public function less_than(job_time $other): bool {
    return $this->get_start_datetime_utc() < $other->get_start_datetime_utc();
  }
  public function greater_than(job_time $other): bool {
    return $this->get_start_datetime_utc() > $other->get_start_datetime_utc();
  }

  /**
   * @brief Check whether two raw UTC intervals are less than PREP_TIME apart (or overlapping).
   * @details Static and side-effect-free so callers can check DB rows directly (e.g. via row_interval())
   *          without constructing a job_time, whose constructor has side effects (grid-pinning, minimum
   *          lead time vs. now()) that would be wrong to apply just to compare already-stored rows.
   */
  public static function intervals_intersect(DateTimeImmutable $a_start, DateTimeImmutable $a_end, DateTimeImmutable $b_start, DateTimeImmutable $b_end): bool {
    // give $b a PREP_TIME quiet zone before/after it; $a's raw window must not fall inside it
    $b_start = $b_start->sub(new DateInterval('PT' . PREP_TIME . 'S'));
    $b_end = $b_end->add(new DateInterval('PT' . PREP_TIME . 'S'));
    return ($a_start < $b_end) && ($b_start < $a_end);
  }

  /**
   * @brief Check if this job_time intersects with another in time, considering PREP_TIME quiet zones.
   * @param job_time $other The other job_time to compare with.
   * @return bool True if the two intersect (less than PREP_TIME apart, or overlapping), false otherwise.
   */
  public function intersects(job_time $other): bool {
    return self::intervals_intersect(
      $this->get_start_datetime_utc(),
      $this->get_stop_datetime_utc(),
      $other->get_start_datetime_utc(),
      $other->get_stop_datetime_utc()
    );
  }

  /**
   * @brief Adjust the start time of the job by a given number of seconds.
   * @param int $seconds The number of seconds to shift the start time by. Can be positive or negative.
   * @return void
   */
  public function shift_start_time(int $seconds): void {
    // Calculate the new UTC start time by adding the shift in seconds
    $utc_time = $this->get_start_datetime_utc()->add(new DateInterval('PT' . $seconds . 'S'));
    self::__construct($utc_time->format('Y-m-d'), $utc_time->format('H:i:s'), $this->duration);
  }

  /**
   * @brief bumps the next job PREP_TIME ahead from last job's stop time OR  if job_time_grid is active, to the next grid time after this stop time
   *  
   * @note should be only called on a ASCENDINGLY SORTED sequence of job_times.
   * @param job_time $next The next job_time to compare with. Will be modified in case
   */
  public function job_bump(job_time $next): void {
    $current_end = $this->get_stop_datetime_utc();
    $next_start = $next->get_start_datetime_utc();
    $diff = $next_start->getTimestamp() - $current_end->getTimestamp();
    if ($diff < PREP_TIME) {
      $bump = PREP_TIME - $diff; // seconds to bump the next job starting
      $next->shift_start_time($bump); // here a constructor is called, which respects GRID in case
    }
  }

  public function set_time_from_array(array $time_array): void {
    if (!isset($time_array['start_date'], $time_array['start_time'])) {
      throw new InvalidArgumentException('Both start_date and start_time must be provided in the array.');
    }

    // self:: (not $this->), so this always re-runs job_time's own constructor even when $this is a job/adu/frequency_handler instance
    self::__construct($time_array['start_date'], $time_array['start_time'], $time_array['duration'] ?? null);
  }

  /**
   * @brief Get the duration of the job in seconds.
   * @return int The duration of the job in seconds.
   */
  public function get_duration(): int {
    return $this->duration;
  }

  /**
   * @brief Get the job time in UTC as a DateTimeImmutable object.
   * @return DateTimeImmutable The job time in UTC.
   */
  public function get_utc_time(): DateTimeImmutable {
    return $this;
  }

  // ************************** FOR START
  /**
   * @brief Get the start time of the job.
   * @param bool $display Whether to return the display time or the UTC time.
   * @return string The start time of the job.
   */
  public function get_start_time(bool $display = false): string {
    if ($display) {
      return $this->display_time->format('H:i:s');
    }
    return $this->format('H:i:s');
  }

  /**
   * @brief Get the start date of the job.
   * @param bool $display Whether to return the display date or the UTC date.
   * @return string The start date of the job.
   */
  public function get_start_date(bool $display = false): string {
    if ($display) {
      return $this->display_time->format('Y-m-d');
    }
    return $this->format('Y-m-d');
  }

  /**
   * @brief Get the start datetime of the job.
   * @param bool $display Whether to return the display datetime or the UTC datetime.
   * @return string The start datetime of the job.
   */
  public function get_start_datetime(bool $display = false): string {
    if ($display) {
      return $this->display_time->format('Y-m-d H:i:s');
    }
    return $this->format('Y-m-d H:i:s');
  }
  public function get_start_datetime_array(bool $display = false): array {
    return array(
      'start_date' => $this->get_start_date($display),
      'start_time' => $this->get_start_time($display),
    );
  }

  // ************************** FOR STOP

  public function get_stop_time(bool $display = false): string {
    $stop_time = $this->add(new DateInterval('PT' . $this->duration . 'S'));
    if ($display) {
      return $this->utc_to_local_datetime($stop_time)->format('H:i:s');
    }
    return $stop_time->format('H:i:s');
  }

  public function get_stop_date(bool $display = false): string {
    $stop_time = $this->add(new DateInterval('PT' . $this->duration . 'S'));
    if ($display) {
      return $this->utc_to_local_datetime($stop_time)->format('Y-m-d');
    }
    return $stop_time->format('Y-m-d');
  }

  public function get_stop_datetime(bool $display = false): string {
    $stop_time = $this->add(new DateInterval('PT' . $this->duration . 'S'));
    if ($display) {
      return $this->utc_to_local_datetime($stop_time)->format('Y-m-d H:i:s');
    }
    return $stop_time->format('Y-m-d H:i:s');
  }

  /**
   * @brief Set the duration of the job.
   * @param int $duration_ The new duration in seconds.
   */
  public function set_duration(int $duration_): void {
    if ($duration_ < MIN_JOB_DURATION) {
      $this->duration = MIN_JOB_DURATION;
    } else {
      $this->duration = $duration_;
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
  /**
   * @brief Format a duration in seconds into HH:MM:SS format.
   */
  public function format_duration_hms(int $duration): string {
    $duration = max(0, $duration);
    $hours = intdiv($duration, 3600);
    $minutes = intdiv($duration % 3600, 60);
    $seconds = $duration % 60;
    return sprintf('%02d:%02d:%02d', $hours, $minutes, $seconds);
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
    switch ($post_name) {
      case 'start_hours':
        return $this->render_slider_form($post_name, $post_name, (int) $this->display_time->format('H'), 0, 23);
      case 'start_minutes':
        return $this->render_slider_form($post_name, $post_name, (int) $this->display_time->format('i'), 0, 59);
      case 'start_seconds':
        return $this->render_slider_form($post_name, $post_name, (int) $this->display_time->format('s'), 0, 59);
      default:
        throw new InvalidArgumentException('Unsupported start slider: ' . $post_name);
    }
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

  /**
   * @brief Generate the HTML form for selecting display timezone, DST and grid preferences.
   */
  public function timezone_and_dst_form(): string {
    $form = '<form method="post" action="">' . PHP_EOL;
    $form .= '<input type="hidden" name="display_timezone_form" value="1" />' . PHP_EOL;
    $form .= '<select name="display_utc_offset" id="display_utc_offset" style="max-width:280px;width:80%;" onchange="this.form.submit()">' . PHP_EOL;

    foreach ($this->adu_timezones as $utc_offset => $label) {
      $selected = ($utc_offset === $this->job_time_utc_offset) ? ' selected="selected"' : '';
      $form .= '<option value="' . htmlspecialchars($utc_offset, ENT_QUOTES, 'UTF-8') . '"' . $selected . '>' . htmlspecialchars($label, ENT_QUOTES, 'UTF-8') . '</option>' . PHP_EOL;
    }

    $dst_checked = $this->job_time_dst ? ' checked="checked"' : '';
    $grid_checked = $this->job_time_grid ? ' checked="checked"' : '';
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

  /**
   * @brief Set the stop date of the job.
   * @param string $stop_date The new stop date in 'Y-m-d' format.
   * @param bool $display Whether the provided stop date in UTC or display time, take care of job_time_utc_offset and job_time_dst.
   * @throws InvalidArgumentException if the new duration is less than the minimum allowed duration.
   */
  public function set_stop_date(string $stop_date, bool $display = false): void {
    if ($display) {
      $stop_time = $this->local_to_utc($stop_date, $this->get_stop_time(true));
    } else {
      $stop_time = new DateTimeImmutable($stop_date . 'T' . $this->get_stop_time(false), self::utc_timezone());
    }
    $new_duration = $stop_time->getTimestamp() - $this->getTimestamp();
    if ($new_duration < MIN_JOB_DURATION) {
      throw new InvalidArgumentException('Stop date results in duration less than minimum allowed.');
    }
    $this->duration = $new_duration;
  }

  /**
   * @brief Update the time of the job. core data is UTC 
   * @param string $what The part of the time to update ('start_date', 'start_time', 'start_hours', 'start_minutes', 'start_seconds', 'duration').
   * @param mixed $value The new value for the specified part of the time.
   * @return bool True if the update was successful, false otherwise.
   */
  public function update_time(string $what, $value): bool {
    $local = $this->display_time; // GUI edits (sliders/datepicker) operate on display time, not UTC
    switch ($what) {
      case 'start_date':
        if (!is_string($value) || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $value)) {
          return false;
        }
        $new_utc = $this->local_to_utc($value, $local->format('H:i:s'));
        break;
      case 'start_time':
        if (!is_string($value) || !preg_match('/^\d{2}:\d{2}(:\d{2})?$/', $value)) {
          return false;
        }
        $new_utc = $this->local_to_utc($local->format('Y-m-d'), $value);
        break;
      case 'start_hours':
        if (!is_numeric($value) || (int) $value < 0 || (int) $value > 23) {
          return false;
        }
        $new_utc = $this->local_to_utc($local->format('Y-m-d'), sprintf('%02d:%s', (int) $value, $local->format('i:s')));
        break;
      case 'start_minutes':
        if (!is_numeric($value) || (int) $value < 0 || (int) $value > 59) {
          return false;
        }
        $new_utc = $this->local_to_utc($local->format('Y-m-d'), sprintf('%s:%02d:%s', $local->format('H'), (int) $value, $local->format('s')));
        break;
      case 'start_seconds':
        if (!is_numeric($value) || (int) $value < 0 || (int) $value > 59) {
          return false;
        }
        $new_utc = $this->local_to_utc($local->format('Y-m-d'), sprintf('%s:%02d', $local->format('H:i'), (int) $value));
        break;
      case 'duration':
        if (!is_numeric($value) || (int) $value < MIN_JOB_DURATION) {
          return false;
        }
        $this->duration = (int) $value;
        return true;
      default:
        return false;
    }

    // self:: (not $this->), so this always re-runs job_time's own constructor even when $this is a job/adu/frequency_handler instance
    self::__construct($new_utc->format('Y-m-d'), $new_utc->format('H:i:s'), $this->duration);
    return true;
  }

  public function handle_post_updates(): bool {
    if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
      return false;
    }
    if (!(isset($_POST['sender']) && $_POST['sender'] === 'job_time' && isset($_POST['what']) && isset($_POST['value']))) {
      return false;
    }

    $what = $_POST['what'];
    $value = $_POST['value'];

    if (in_array($what, ['start_date', 'start_hours', 'start_minutes', 'start_seconds'], true)) {
      if ($this->update_time($what, $value)) {
        return true;
      }
      error_log("Failed to update job time for what: $what with value: $value");
      return false;
    }

    if ($what === 'stop_date') {
      if (!is_string($value) || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $value)) {
        error_log("Failed to update job stop date with value: $value");
        return false;
      }
      try {
        $this->set_stop_date($value, true);
        return true;
      } catch (InvalidArgumentException $e) {
        error_log('Failed to update job stop date: ' . $e->getMessage());
        return false;
      }
    }

    if (in_array($what, ['duration_hours', 'duration_minutes', 'duration_seconds'], true)) {
      if (!is_numeric($value)) {
        return false;
      }
      $days = intdiv(max(0, $this->duration), 86400);
      [$h, $m, $s] = $this->get_duration_parts();
      if ($what === 'duration_hours')   $h = max(0, min(23, (int) $value));
      if ($what === 'duration_minutes') $m = max(0, min(59, (int) $value));
      if ($what === 'duration_seconds') $s = max(0, min(59, (int) $value));
      $this->set_duration(($days * 86400) + ($h * 3600) + ($m * 60) + $s);
      return true;
    }

    return false;
  }


  /**
   * @brief Array of ADU timezones for display purposes and drop-down selection.
   * @details This array maps UTC offsets to their corresponding timezone names.
   */
  public array $adu_timezones = [
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
  ];
} // end class
