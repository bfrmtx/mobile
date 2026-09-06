<?php

/**
 * @file joblist.php
 * @brief Manages the jobs table (history list): insert, copy-back, delete, render.
 */
require_once __DIR__ . '/../config.php';
require_once PHP_DIR . 'php_functions.php';
require_once PHP_DIR . 'job.php';

class joblist {

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
   * @param int    $prep_time Seconds in the future for the start time of stop-job markers (default PREP_TIME).
   */
  public function __construct(string $db_file_, string $table_) {
    $this->db = new database($db_file_);
    $this->db->set_table($table_);
    //$this->db->create_table_from_SQL_file(INIT_DIR . 'jobs' . DIRECTORY_SEPARATOR . 'jobs.sql'); // CREATE TABLE IF NOT EXISTS
  }

  // ── write operations ──────────────────────────────────────────────────────

  /**
   * @brief Build UTC interval [start, stop) from a jobs-row-like array.
   * @param array $row  Array with keys 'start_date', 'start_time', 'duration'.
   * @param bool  $lc_time  include local display times as well
   * @return array{0: DateTimeImmutable, 1: DateTimeImmutable}|null - as UTC start and stop
   *         or array{0: DateTimeImmutable, 1: DateTimeImmutable, 2: ?DateTimeImmutable, 3: ?DateTimeImmutable}|null - UTC plus local start/stop
   */
  private function row_interval(array $row, bool $lc_time = false): ?array {
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
    $stop = $start->modify('+' . $duration . ' seconds');
    // UTC values are authoritative. Duration defines the stop time.
    if (!$lc_time) {
      return [$start, $stop];
    }

    $display_tz = new job_time(); // already syncs job_time_utc_offset/job_time_dst from $_SESSION in its own constructor

    $local_start = $display_tz->utc_to_local_datetime($start);
    $local_stop = $display_tz->utc_to_local_datetime($stop);
    return [$start, $stop, $local_start, $local_stop];
  }

  /**
   * @brief Check whether a candidate job can be inserted without time overlap.
   * @details Delegates the PREP_TIME-aware overlap rule to job_time::intervals_intersect(), the single
   *          implementation shared with job::intersects(); row_interval() stays side-effect-free parsing only.
   */
  public function can_insert(array $candidate): bool {
    $candidate_interval = $this->row_interval($candidate);
    if ($candidate_interval === null) {
      $_SESSION['error_message'] = 'Cannot insert job: invalid start date/time.';
      return false;
    }
    // check if candidate sampling_rate > 0 AND if duration == 0, if so, we have not a valid job and no special marker
    if (intval($candidate['sampling_rate']) > 0 && intval($candidate['duration']) == 0) {
      $_SESSION['error_message'] = 'Cannot insert job: invalid duration.';
      return false;
    }

    [$new_start, $new_end] = $candidate_interval;

    foreach ($this->db->get_rows() as $row) {
      // Skip special marker rows like stop/shutdown/reboot.
      if (intval($row['sampling_rate'] ?? 0) <= 0) {
        continue;
      }
      $existing_interval = $this->row_interval($row);
      if ($existing_interval === null) {
        continue;
      }
      [$existing_start, $existing_end] = $existing_interval;
      if (job_time::intervals_intersect($new_start, $new_end, $existing_start, $existing_end)) {
        $_SESSION['error_message'] = 'Cannot insert job: overlaps existing job #' . intval($row['id'] ?? 0) . '.';
        return false;
      }
    }
    return true;
  }

  /**
   * @brief Copy the current state of $job into the jobs table as a new row.
   * @return int  The new row id.
   */
  public function insert_from_job(job $job, bool $electric_off = false, bool $now = false): int {
    if ($now) {
      $job->set_start_time_now();
    }
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
      // remove depreciated
      'power_off_limit' => 10.0,
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
   * @brief Insert a special marker row (e.g. sampling_rate = -1 for stop, -2 detect sensors, -3 for shutdown, -4 reboot).
   * @param int $value  The special sampling_rate value (must be negative).
   * @return int  The new row id.
   */
  public function insert_special_job(int $value): int {
    $dt = new DateTimeImmutable('@' . (time() + PREP_TIME), new DateTimeZone('UTC'));
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
    $row = $this->db->get_rows($id);
    if (empty($row)) {
      return false;
    }
    $job->restore_from_row($row);
    return true;
  }

  public function reorder_jobs(): void {
    $this->db->reorder_jobs();
  }

  /**
   * @brief get start SQLITE datetime of a job row by id 
   * @param int $id  Row id in the jobs table.
   * @return sqlite3 datetime
   */
  public function get_start_datetime(int $id): ?string {
    $row = $this->db->get_rows($id);
    if (empty($row)) {
      return null;
    }
    return trim(($row['start_date'] ?? '') . ' ' . ($row['start_time'] ?? ''));
  }

  /**
   * @brief Delete one row from the jobs table.
   */
  public function delete_row(int $id): void {
    $mcp_j = new mcp_jobs('mcpdb.db', 'jobs');
    $start_d_t = $this->get_start_datetime($id);
    if ($start_d_t !== null) {
      $mcp_id = $mcp_j->get_id_by_start($start_d_t);
      if ($mcp_id !== null) {
        $mcp_j->delete_row_by_id($mcp_id);
      }
    }

    $this->db->delete_row_by_id($id);
  }

  public function empty_table(bool $vacuum = false): void {
    $mcp_j = new mcp_jobs('mcpdb.db', 'jobs');
    $mcp_j->empty_table($vacuum);
    $this->db->empty_table($vacuum);
  }

  public function get_all_id_start_as_start_date_and_start_time(): array {
    $rows = $this->db->get_rows();
    $result = [];
    foreach ($rows as $row) {
      $id = intval($row['id'] ?? 0);
      $start_date = trim(strval($row['start_date'] ?? ''));
      $start_time = trim(strval($row['start_time'] ?? ''));
      if ($id > 0 && $start_date !== '' && $start_time !== '') {
        $result[] = [
          'id' => $id,
          'start_date' => $start_date,
          'start_time' => $start_time,
        ];
      }
    }
    return $result;
  }

  /**
   * @brief Sync_del the jobs table with the MCP jobs table (mcpdb.db, table jobs).
   * @details delete all rows in mcpdb.db, table jobs that are not in jobs.db
   * @details mcpdb.db uses datetime, jobs.db start_date, start_time
   * @return void
   */
  public function sync_del_mcp_jobs(): void {
    $mcp_j = new mcp_jobs('mcpdb.db', 'jobs');
    // function return trimmed values
    $mcp_rows = $mcp_j->get_all_id_start_as_start_date_and_start_time();
    $jobs_rows = $this->get_all_id_start_as_start_date_and_start_time();
    // delete all rows in mcp_rows that are not in jobs_rows
    foreach ($mcp_rows as $mcp_row) {
      $mcp_id = intval($mcp_row['id'] ?? 0);
      $mcp_start_date = $mcp_row['start_date'];
      $mcp_start_time = $mcp_row['start_time'];
      $found = false;
      foreach ($jobs_rows as $job_row) {
        $job_start_date = $job_row['start_date'];
        $job_start_time = $job_row['start_time'];
        if ($mcp_start_date === $job_start_date && $mcp_start_time === $job_start_time) {
          $found = true;
          break;
        }
      }
      if (!$found && $mcp_id > 0) {
        $mcp_j->delete_row_by_id($mcp_id);
      }
    }
  }




  // ── rendering ─────────────────────────────────────────────────────────────

  /**
   * @brief Render an HTML table of all jobs with Copy and Delete buttons.
   * @param string $page_url  Target page for the button forms (e.g. 'jobs.php').
   */
  public function render_table(string $page_url = 'jobs.php'): string {
    $rows = $this->db->get_rows();
    $safe_url = htmlspecialchars($page_url, ENT_QUOTES, 'UTF-8');

    $html  = '<table style="width:100%;border-collapse:collapse;font-size:0.95em;">' . PHP_EOL;
    $html .= '<thead><tr style="background:#444;color:#fff;">';
    foreach (['ID', 'Start (UTC)', 'Stop (UTC)', 'Duration', 'Sampling rate', 'Sub', '', ''] as $th) {
      $html .= '<th style="padding:4px 8px;text-align:left;">' . htmlspecialchars($th, ENT_QUOTES, 'UTF-8') . '</th>';
    }
    $html .= '</tr></thead>' . PHP_EOL;
    $html .= '<tbody>' . PHP_EOL;

    foreach ($rows as $row) {
      // create start_date_time, stop_date_time, local_start_date_time, local_stop_date_time according to the GUI selected timezone
      // we have $times[0,1,2,3] = start_utc, stop_utc, start_local, stop_local
      $times = $this->row_interval($row, true);
      $id = intval($row['id']);
      $sr = intval($row['sampling_rate'] ?? 0);
      $is_special = ($sr < 0);

      $row_style = $is_special ? 'background:#fff0f0;color:#c00;font-weight:bold;' : '';

      if ($is_special) {
        // special marker row (stop/shutdown/reboot/...): no real stop time to compute
        $utc_start = $times[0] instanceof DateTimeImmutable ? $times[0]->format('Y-m-d H:i:s') : '';
        $local_start = isset($times[2]) && $times[2] instanceof DateTimeImmutable ? $times[2]->format('Y-m-d H:i:s') : $utc_start;
        $start_col = htmlspecialchars($utc_start . ' UTC', ENT_QUOTES, 'UTF-8');
        if ($local_start !== $utc_start) {
          $start_col .= '<br>' . htmlspecialchars($local_start . ' LC', ENT_QUOTES, 'UTF-8');
        }
        if ($sr === -1) {
          $stop_col = ' (stop job)';
        } elseif ($sr === -2) {
          $stop_col = ' (detect sensors)';
        } elseif ($sr === -3) {
          $stop_col = ' (shutdown)';
        } elseif ($sr === -4) {
          $stop_col = ' (reboot)';
        } else {
          $stop_col = ' (unknown action)';
        }
        $dur_col     = '';
        $sr_col      = htmlspecialchars('sampling_rate = ' . $sr, ENT_QUOTES, 'UTF-8');
        $sub_col     = '';
      } else {
        $utc_start = $times[0] instanceof DateTimeImmutable ? $times[0]->format('Y-m-d H:i:s') : '';
        $utc_stop = $times[1] instanceof DateTimeImmutable ? $times[1]->format('Y-m-d H:i:s') : '';
        $local_start = isset($times[2]) && $times[2] instanceof DateTimeImmutable ? $times[2]->format('Y-m-d H:i:s') : $utc_start;
        $local_stop = isset($times[3]) && $times[3] instanceof DateTimeImmutable ? $times[3]->format('Y-m-d H:i:s') : $utc_stop;
        $start_col = htmlspecialchars($utc_start . ' UTC', ENT_QUOTES, 'UTF-8');
        if ($local_start !== $utc_start) {
          $start_col .= '<br>' . htmlspecialchars($local_start . ' LC', ENT_QUOTES, 'UTF-8');
        }
        $stop_col   = htmlspecialchars($utc_stop . ' UTC', ENT_QUOTES, 'UTF-8');
        if ($local_stop !== $utc_stop) {
          $stop_col .= '<br>' . htmlspecialchars($local_stop . ' LC', ENT_QUOTES, 'UTF-8');
        }
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
      }

      $html .= '<tr style="' . $row_style . '">';
      $html .= '<td style="padding:4px 8px;">' . $id . '</td>';
      $html .= '<td style="padding:4px 8px;">' . $start_col . '</td>';
      $html .= '<td style="padding:4px 8px;">' . $stop_col . '</td>';
      $html .= '<td style="padding:4px 8px;">' . $dur_col . '</td>';
      $html .= '<td style="padding:4px 8px;">' . $sr_col . '</td>';
      $html .= '<td style="padding:4px 8px;">' . $sub_col . '</td>';

      // Copy button (disabled for stop rows)
      if (!$is_special) {
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

  /**
   * @brief Save the jobs table as a new joblist file (SQLite) in the DB_DIR/joblists.
   * @param string $filename  The filename (without path) to save as.
   * @details if DB_DIR/joblists does not exist, it will be created. If the file already exists, it will be overwritten.
   * @return bool  True on success, false on failure.
   * @note The filename should be a valid SQLite filename (e.g., 'pt_long'), extension .db will be appended automatically. The file will be created in DB_DIR/joblists.
   */
  public static function normalize_joblist_filename(string $filename): string {
    $filename = trim($filename);
    if (strlen($filename) > 3 && strtolower(substr($filename, -3)) === '.db') {
      $filename = substr($filename, 0, -3);
    }
    return $filename;
  }

  public static function is_valid_joblist_filename(string $filename): bool {
    $filename = self::normalize_joblist_filename($filename);
    if ($filename === '' || $filename === '.' || $filename === '..') {
      return false;
    }
    return preg_match('/^[A-Za-z0-9][A-Za-z0-9._-]*$/', $filename) === 1;
  }

  //!< predefined, read-only joblists live in joblists/system; '/' can't appear in a user-saved name, so this prefix can never collide with one
  private const SYSTEM_JOBLIST_PREFIX = 'system/';

  public static function is_valid_loadable_joblist_filename(string $filename): bool {
    return self::resolve_joblist_location($filename) !== null;
  }

  /**
   * @brief Resolve a (possibly "system/"-prefixed) joblist name to its storage subdir and bare filename.
   * @return array{0: string, 1: string}|null  [$relative_dir, $bare_filename], $relative_dir is 'joblists' or 'joblists/system'; null when invalid.
   */
  private static function resolve_joblist_location(string $filename): ?array {
    $filename = self::normalize_joblist_filename($filename);
    $is_system = strncmp($filename, self::SYSTEM_JOBLIST_PREFIX, strlen(self::SYSTEM_JOBLIST_PREFIX)) === 0;
    if ($is_system) {
      $filename = substr($filename, strlen(self::SYSTEM_JOBLIST_PREFIX));
    }
    if (!self::is_valid_joblist_filename($filename)) {
      return null;
    }
    return [$is_system ? 'joblists/system' : 'joblists', $filename];
  }

  public function joblist_exists(string $filename): bool {
    $location = self::resolve_joblist_location($filename);
    if ($location === null) {
      return false;
    }
    [$rel_dir, $bare_name] = $location;
    return file_exists(DB_DIR . $rel_dir . '/' . $bare_name . '.db');
  }

  public function list_saved_joblists(): array {
    $joblists_dir = DB_DIR . 'joblists';
    if (!is_dir($joblists_dir)) {
      return [];
    }
    $files = glob($joblists_dir . '/*.db');
    if (!is_array($files)) {
      return [];
    }
    $joblists = [];
    foreach ($files as $file) {
      $joblists[] = basename($file, '.db');
    }
    sort($joblists, SORT_NATURAL | SORT_FLAG_CASE);
    return $joblists;
  }

  /**
   * @brief List user-saved joblists plus predefined ones from joblists/system (prefixed "system/"), for loading only.
   */
  public function list_loadable_joblists(): array {
    $joblists = $this->list_saved_joblists();

    $system_dir = DB_DIR . 'joblists/system';
    if (is_dir($system_dir)) {
      $files = glob($system_dir . '/*.db');
      if (is_array($files)) {
        foreach ($files as $file) {
          $joblists[] = self::SYSTEM_JOBLIST_PREFIX . basename($file, '.db');
        }
      }
    }
    sort($joblists, SORT_NATURAL | SORT_FLAG_CASE);
    return $joblists;
  }

  public function save_as_joblist(string $filename): bool {
    $filename = self::normalize_joblist_filename($filename);
    if (!self::is_valid_joblist_filename($filename)) {
      return false;
    }
    $joblists_dir = DB_DIR . 'joblists';
    if (!is_dir($joblists_dir)) {
      if (!mkdir($joblists_dir, 0755, true)) {
        return false;
      }
    }
    $full_path = $joblists_dir . '/' . $filename . '.db';
    $db_file = 'joblists/' . $filename . '.db';
    if (file_exists($full_path) && !unlink($full_path)) {
      return false;
    }
    // create a new database file and copy the jobs table into it
    $new_db = new database($db_file, true);
    $new_db->set_table('jobs');
    $new_db->create_table_from_SQL_file(INIT_DIR . 'jobs' . DIRECTORY_SEPARATOR . 'jobs.sql'); // CREATE TABLE IF NOT EXISTS
    $rows = $this->db->get_rows();
    foreach ($rows as $row) {
      $new_db->insert_jobs_row($row);
    }
    return true;
  }

  public function load_joblist(string $filename): bool {
    $location = self::resolve_joblist_location($filename);
    if ($location === null) {
      return false;
    }
    [$rel_dir, $bare_name] = $location;
    $full_path = DB_DIR . $rel_dir . '/' . $bare_name . '.db';
    if (!file_exists($full_path)) {
      return false;
    }
    // open the joblist database and copy its jobs table into the current jobs table
    $joblist_db = new database($rel_dir . '/' . $bare_name . '.db');
    $joblist_db->set_table('jobs');
    $rows = $joblist_db->get_rows();

    // we MUST create a job row by row, calling new job() which constructs the present adu (aka hw configuration)
    $job = new job('job.db', 'job');
    // job.db already holds the start_date/start_time set on index.php; sort the loaded rows and shift them
    // as a block so the earliest one lands there, preserving the relative spacing between the other rows
    $rows = reorder_jobs_rows($rows, $job->get_start_date(), $job->get_start_time());

    // clear current jobs table
    $this->db->empty_table(true);

    // @todo i need to bump the jobs to maintain relative spacing based on the earliest job's start time.
    foreach ($rows as $row) {
      $job->restore_from_row($row, false); // false = do not persist to job.db, just load into $job object
      $this->insert_from_job($job, false, false); // false = electric channels off, false = not starting now
    }
    return true;
  }
} // end joblist class
