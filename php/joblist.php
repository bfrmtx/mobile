<?php

/**
 * @file joblist.php
 * @brief Manages the jobs table (history list): insert, copy-back, delete, render.
 */
require_once __DIR__ . '/../config.php';
require_once PHP_DIR . 'job.php';

class joblist {

  public int $prep_time;
  private database $db;
  protected array $special_sampling_rates = [   //!<special sampling rate values for ACTIONS
    -1, // stop job 
    -2, // detect sensors (type)
    -3, // shut down system
    -4  // reboot system
  ];

  /**
   * @param string $db_file_  SQLite filename (e.g. 'jobs.db'), resolved via DB_DIR.
   * @param string $table_    Table name inside that file (e.g. 'jobs').
   * @param int    $prep_time Seconds in the future for the start time of stop-job markers (default 30).
   */
  public function __construct(string $db_file_, string $table_, int $prep_time_ = 30) {
    $this->db = new database($db_file_, true); // create_if_missing = true
    $this->db->set_table($table_);
    $this->db->create_job_table(true); // CREATE TABLE IF NOT EXISTS
    $this->prep_time = $prep_time_;
  }

  // ── write operations ──────────────────────────────────────────────────────

  /**
   * @brief Build UTC interval [start, end) from a jobs-row-like array.
   * @return array{0: DateTimeImmutable, 1: DateTimeImmutable}|null
   */
  private function row_interval(array $row): ?array {
    $date = trim(strval($row['start_date'] ?? ''));
    $time = trim(strval($row['start_time'] ?? ''));
    if ($date === '' || $time === '') {
      return null;
    }
    $start = DateTimeImmutable::createFromFormat('Y-m-d H:i:s', $date . ' ' . $time, new DateTimeZone('UTC'));
    if ($start === false) {
      return null;
    }
    $duration = max(0, intval($row['duration'] ?? 0));
    $end = $start->modify('+' . $duration . ' seconds');
    return [$start, $end];
  }

  /**
   * @brief Check whether a candidate job can be inserted without time overlap.
   * @details Overlap rule: existing_start < new_end AND new_start < existing_end.
   */
  public function can_insert(array $candidate): bool {
    $candidate_interval = $this->row_interval($candidate);
    if ($candidate_interval === null) {
      $_SESSION['error_message'] = 'Cannot insert job: invalid start date/time.';
      return false;
    }
    [$new_start, $new_end] = $candidate_interval;

    foreach ($this->get_all_rows() as $row) {
      // Skip special marker rows like stop/shutdown/reboot.
      if (intval($row['sampling_rate'] ?? 0) <= 0) {
        continue;
      }
      $existing_interval = $this->row_interval($row);
      if ($existing_interval === null) {
        continue;
      }
      [$existing_start, $existing_end] = $existing_interval;

      if ($existing_start < $new_end && $new_start < $existing_end) {
        $id = intval($row['id'] ?? 0);
        $_SESSION['error_message'] = 'Cannot insert job: overlaps existing job #' . $id . '.';
        return false;
      }
    }
    return true;
  }

  /**
   * @brief Copy the current state of $job into the jobs table as a new row.
   * @return int  The new row id.
   */
  public function insert_from_job(job $job, bool $electric_off = false): int {
    $kv = [
      'start_date'     => $job->get_start_date(),
      'start_time'     => $job->get_start_time(),
      'duration'       => $job->get_duration(),
      'sampling_rate'  => $job->get_sampling_rate(),
      'digital_filter' => $job->get_digital_filter(),
      'split_main'     => $job->get_split_main(),
      'cal_mode'       => $job->get_cal_mode(),
      'channel_types'  => $job->get_channel_types(),
      'choppers'       => $job->get_choppers(),
      'gains'          => $job->get_gains(),
      'dipole_lengths' => $job->get_dipole_lengths(),
      'use_atss'       => $job->get_use_atss(),
      'copy_to_usb'    => $job->get_copy_to_usb(),
      'sub_cycle'      => $job->get_sub_cycle(),
      'sub_duration'   => $job->get_sub_duration(),
      'sub_filter'     => $job->get_sub_filter(),
      'split_sub'      => $job->get_split_sub(),
      'station_id'     => $job->get_station_id(),
      'power_off_limit' => $job->get_power_off_limit(),
      'slots_on'       => $job->get_slots_on($electric_off),
      'started'        => 0,
    ];

    if (!$this->can_insert($kv)) {
      return 0;
    }
    // check that we have at least one "1" in the slots_on string
    if (isset($kv['slots_on']) && strpos($kv['slots_on'], '1') === false) {
      return 0;
    }
    return $this->db->insert_jobs_row($kv);
  }

  /**
   * @brief Copy the current state of $job into the jobs table, but schedule the start at now + prep_time UTC.
   * @return int  The new row id.
   */
  public function insert_from_job_now(job $job, bool $electric_off = false): int {
    $dt = new DateTimeImmutable('@' . (time() + $this->prep_time), new DateTimeZone('UTC'));
    $kv = [
      'start_date'     => $dt->format('Y-m-d'),
      'start_time'     => $dt->format('H:i:s'),
      'duration'       => $job->get_duration(),
      'sampling_rate'  => $job->get_sampling_rate(),
      'digital_filter' => $job->get_digital_filter(),
      'split_main'     => $job->get_split_main(),
      'cal_mode'       => $job->get_cal_mode(),
      'channel_types'  => $job->get_channel_types(),
      'choppers'       => $job->get_choppers(),
      'gains'          => $job->get_gains(),
      'dipole_lengths' => $job->get_dipole_lengths(),
      'use_atss'       => $job->get_use_atss(),
      'copy_to_usb'    => $job->get_copy_to_usb(),
      'sub_cycle'      => $job->get_sub_cycle(),
      'sub_duration'   => $job->get_sub_duration(),
      'sub_filter'     => $job->get_sub_filter(),
      'split_sub'      => $job->get_split_sub(),
      'station_id'     => $job->get_station_id(),
      'power_off_limit' => $job->get_power_off_limit(),
      'slots_on'       => $job->get_slots_on($electric_off),
      'started'        => 0,
    ];

    if (!$this->can_insert($kv)) {
      return 0;
    }

    return $this->db->insert_jobs_row($kv);
  }

  /**
   * @brief Insert a special marker row (e.g. sampling_rate = -1 for stop, -3 for shutdown, etc.).
   * @param int $value  The special sampling_rate value (must be negative).
   * @return int  The new row id.
   */
  public function insert_special_job(int $value): int {
    $dt = new DateTimeImmutable('@' . (time() + $this->prep_time), new DateTimeZone('UTC'));
    return $this->db->insert_jobs_row([
      'sampling_rate' => $value,
      'start_date'    => $dt->format('Y-m-d'),
      'start_time'    => $dt->format('H:i:s'),
      'started'       => 0,
    ]);
  }

  /**
   * @brief Load a jobs-table row back into the live job (job.db) and persist it.
   * @param int  $id   Row id in the jobs table.
   * @param job  $job  The live job object to overwrite.
   * @return bool False when the row does not exist.
   */
  public function copy_to_job(int $id, job $job): bool {
    $row = $this->db->read_job_table($id);
    if (empty($row)) {
      return false;
    }
    $job->restore_from_row($row);
    return true;
  }

  /**
   * @brief Delete one row from the jobs table.
   */
  public function delete_row(int $id): void {
    $this->db->delete_row_by_id($id);
  }

  /**
   * @brief Return all rows, newest first.
   */
  public function get_all_rows(): array {
    return $this->db->read_all_rows();
  }

  // ── rendering ─────────────────────────────────────────────────────────────

  /**
   * @brief Render an HTML table of all jobs with Copy and Delete buttons.
   * @param string $page_url  Target page for the button forms (e.g. 'jobs.php').
   */
  public function render_table(string $page_url = 'jobs.php'): string {
    $rows = $this->get_all_rows();
    $safe_url = htmlspecialchars($page_url, ENT_QUOTES, 'UTF-8');

    $html  = '<table style="width:100%;border-collapse:collapse;font-size:0.95em;">' . PHP_EOL;
    $html .= '<thead><tr style="background:#444;color:#fff;">';
    foreach (['ID', 'Start (UTC)', 'Duration', 'Sample rate', 'Sub', 'Cal', '', ''] as $th) {
      $html .= '<th style="padding:4px 8px;text-align:left;">' . htmlspecialchars($th, ENT_QUOTES, 'UTF-8') . '</th>';
    }
    $html .= '</tr></thead>' . PHP_EOL;
    $html .= '<tbody>' . PHP_EOL;

    foreach ($rows as $row) {
      $id = intval($row['id']);
      $sr = intval($row['sampling_rate'] ?? 0);
      $is_stop = ($sr === -1);

      $row_style = $is_stop ? 'background:#fff0f0;color:#c00;font-weight:bold;' : '';

      if ($is_stop) {
        $stop_time   = trim(($row['start_date'] ?? '') . ' ' . ($row['start_time'] ?? ''));
        $start_col   = 'STOP' . ($stop_time !== '' ? ' @ ' . htmlspecialchars($stop_time, ENT_QUOTES, 'UTF-8') . ' UTC' : '');
        $dur_col     = '';
        $sr_col      = htmlspecialchars('sampling_rate = -1', ENT_QUOTES, 'UTF-8');
        $sub_col     = '';
        $cal_col     = '';
      } else {
        $start_col   = htmlspecialchars(($row['start_date'] ?? '') . ' ' . ($row['start_time'] ?? ''), ENT_QUOTES, 'UTF-8');
        $dur_sec     = intval($row['duration'] ?? 0);
        $dur_col     = htmlspecialchars(sprintf('%02d:%02d:%02d', intdiv($dur_sec, 3600), intdiv($dur_sec % 3600, 60), $dur_sec % 60), ENT_QUOTES, 'UTF-8');
        $sr_col      = htmlspecialchars((string) $sr, ENT_QUOTES, 'UTF-8');
        $sub_cycle   = intval($row['sub_cycle'] ?? 0);
        $sub_dur     = intval($row['sub_duration'] ?? 0);
        $sub_filter  = intval($row['sub_filter'] ?? 0);
        $sub_parts   = [];
        if ($sub_filter > 0)  $sub_parts[] = 'flt:' . $sub_filter;
        if ($sub_cycle  > 0)  $sub_parts[] = 'cyc:' . $sub_cycle;
        if ($sub_dur    > 0)  $sub_parts[] = 'dur:' . $sub_dur;
        $sub_col     = htmlspecialchars(implode(' ', $sub_parts), ENT_QUOTES, 'UTF-8');
        $cal_col     = htmlspecialchars($row['cal_mode'] ?? '', ENT_QUOTES, 'UTF-8');
      }

      $html .= '<tr style="' . $row_style . '">';
      $html .= '<td style="padding:4px 8px;">' . $id . '</td>';
      $html .= '<td style="padding:4px 8px;">' . $start_col . '</td>';
      $html .= '<td style="padding:4px 8px;">' . $dur_col . '</td>';
      $html .= '<td style="padding:4px 8px;">' . $sr_col . '</td>';
      $html .= '<td style="padding:4px 8px;">' . $sub_col . '</td>';
      $html .= '<td style="padding:4px 8px;">' . $cal_col . '</td>';

      // Copy button (disabled for stop rows)
      if (!$is_stop) {
        $html .= '<td style="padding:4px 8px;">';
        $html .= '<form method="post" action="' . $safe_url . '" style="margin:0;">';
        $html .= '<input type="hidden" name="sender" value="joblist" />';
        $html .= '<input type="hidden" name="what" value="copy_to_job" />';
        $html .= '<input type="hidden" name="id" value="' . $id . '" />';
        $html .= '<button type="submit" style="padding:2px 10px;cursor:pointer;">Copy</button>';
        $html .= '</form>';
        $html .= '</td>';
      } else {
        $html .= '<td></td>';
      }

      // Delete button
      $html .= '<td style="padding:4px 8px;">';
      $html .= '<form method="post" action="' . $safe_url . '" style="margin:0;" onsubmit="return confirm(\'Delete row ' . $id . '?\');">';
      $html .= '<input type="hidden" name="sender" value="joblist" />';
      $html .= '<input type="hidden" name="what" value="delete" />';
      $html .= '<input type="hidden" name="id" value="' . $id . '" />';
      $html .= '<button type="submit" style="padding:2px 10px;cursor:pointer;color:#c00;">Delete</button>';
      $html .= '</form>';
      $html .= '</td>';

      $html .= '</tr>' . PHP_EOL;
    }

    if (empty($rows)) {
      $html .= '<tr><td colspan="8" style="padding:8px;text-align:center;color:#888;">No jobs recorded yet.</td></tr>' . PHP_EOL;
    }

    $html .= '</tbody></table>' . PHP_EOL;
    return $html;
  }
} // end joblist class
