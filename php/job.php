<?php

/**
 * @file job.php
 * @brief Defines job 
 */
require_once __DIR__ . '/../config.php';           //!< make your SESSION and path.
require_once TRAITS_DIR . 'global_vars.php';    //!< this trait provides the  global vars

class job extends adu {

  // the job table columns
  // NOTE: start_date, start_time, duration are inherited from job_time (DateTimeImmutable + int).
  // Use get_start_date() for col 1
  // Use get_start_time() for col 2
  // Use get_duration() for col 3 / and set_*() to access them.
  // $sampling_rate;                          // sampling rate in Hz controlled by frequency_handler class
  // $digital_filter                          // setting controlled by frequency_handler class, no direct access from UI.
  // $split_main                              // setting controlled by frequency_handler class, no direct access from UI.
  public string $cal_mode       = 'off';
  //  $channel_types  = [];                   // per-slot channel type settings in slot class, adu creates array
  //  $choppers       = [];                   // per-slot chopper settings in sensor class via slot class , adu creates array
  //  $gains          = [];                   // per-slot gain settings in slot class , adu creates array
  //  $dipole_lengths = [];                   // per-slot dipole length settings for slots 0 and 1 only (in sensor class via slot class, adu creates array)
  public int    $use_atss       = 0;          // whether to use ATSS (1) or old ATS (0) (in job class)
  public int    $copy_to_usb    = 0;          // whether to copy the data to USB after the job is done, 1 = yes, 0 = no (in job class)
  // $sub_cycle      = 0;          // via frequency handler
  // $sub_duration   = 0;          // via frequency handler
  // $sub_filter     = 0;          // via frequency handler
  // $split_sub     = 0;           // via frequency handler
  public float  $power_off_limit = 10.0;        // power off limit in W, if the estimated power consumption exceeds this limit, the job will not be started (in job class)
  public string   $station_id        = '';          //!< site identifier; not managed here
  // end job table columns


  private ?database $db = null;                 //!< database connection for this job
  private ?adu $adu = null;                     //!< adu object for this job
  private bool $adu_updated = false;            //!< flag to track if ADU configuration was updated and needs to be written to DB  
  private string $table = '';                   //!< table name for this job in the database, set in constructor




  public function __construct(string $job_db_, string $job_table_) {
    // default values  (start_date_time and duration initialised by parent::__construct())
    $this->digital_filter = 0;
    $this->cal_mode = 'off';
    $this->use_atss = 0;
    $this->copy_to_usb = 0;
    $this->power_off_limit = 10.0;
    $this->station_id = '';
    // end default values

    // initialize the database connection for this job
    $this->db = new database($job_db_);
    $this->db->set_table($job_table_);
    $this->table = $job_table_;
    // initialize the ADU object for this job
    parent::__construct(); // ADU
    $this->read_hwConfig();                              // read the hardware configuration for this ADU

    // if table has now row, create it and write the default values to the database
    $row = $this->db->read_job_table();
    if (empty($row)) {
      $this->create(); //!< create the table if it doesn't exist and write the default values to the database
      $this->handle_post_updates(true); //!< write the default values to the database immediately
    } else {
      $this->read();   //!< read the existing values from the database and populate the job properties
    }
  }
  public function __destruct() {
    // Destructor code if needed
  }

  // create read empty functions for this job, which will interact with the database
  public function create() {
    $this->db->create_job_table(); //!< create the job table if it doesn't exist
  }

  public function read() {
    $row = $this->db->read_job_table(); //!< read the job data from the database
    if (!empty($row)) {
      $this->load_row($row);
    }
  }

  public function get_cal_mode(): string {
    return $this->cal_mode;
  }
  public function set_cal_mode(string $mode): void {
    $this->cal_mode = $mode;
    $this->persist_job_state();
  }
  public function get_use_atss(): int {
    return $this->use_atss;
  }
  public function set_use_atss(int $use_atss): void {
    $this->use_atss = $use_atss;
    $this->persist_job_state();
  }
  public function get_copy_to_usb(): int {
    return $this->copy_to_usb;
  }
  public function set_copy_to_usb(int $copy_to_usb): void {
    $this->copy_to_usb = $copy_to_usb;
    $this->persist_job_state();
  }
  /**
   * @brief Populate all job properties from a raw associative row array.
   * @details Shared by read() (from job table) and restore_from_row() (from jobs table).
   */
  private function load_row(array $row): void {
    // start_date_time is stored as separate date and time columns; reconstruct as UTC DateTimeImmutable
    $db_date = $row['start_date'] ?? $this->get_start_date();
    $db_time = $row['start_time'] ?? $this->get_start_time();
    $dt = DateTimeImmutable::createFromFormat('Y-m-d H:i:s', $db_date . ' ' . $db_time, new DateTimeZone('UTC'));
    if ($dt !== false) {
      $this->start_date_time = $dt;
    }
    if (isset($row['duration'])) {
      $this->set_duration(intval($row['duration']));
    }
    $this->sampling_rate = intval($row['sampling_rate'] ?? $this->sampling_rate);       // for frequency handler mainly
    $this->digital_filter = intval($row['digital_filter'] ?? $this->digital_filter);    // for frequency handler
    $this->split_main = intval($row['split_main'] ?? $this->split_main);                // for frequency handler
    $this->cal_mode = $row['cal_mode'] ?? $this->cal_mode;
    // for the arrays, we need to convert the CSV string back to an array -> they will go to the adu and their slots and sensors
    $this->channel_types = isset($row['channel_types']) ? explode(',', $row['channel_types']) : $this->channel_types;
    $this->choppers = isset($row['choppers']) ? array_map('intval', explode(',', $row['choppers'])) : $this->choppers;
    $this->gains = isset($row['gains']) ? array_map('intval', explode(',', $row['gains'])) : $this->gains;
    $this->dipole_lengths = isset($row['dipole_lengths']) ? array_map('floatval', explode(',', $row['dipole_lengths'])) : $this->dipole_lengths;
    // finished adu
    $this->use_atss = intval($row['use_atss'] ?? $this->use_atss);
    $this->copy_to_usb = intval($row['copy_to_usb'] ?? $this->copy_to_usb);
    $this->sub_cycle = intval($row['sub_cycle'] ?? $this->sub_cycle);                   // for frequency handler
    $this->sub_duration = intval($row['sub_duration'] ?? $this->sub_duration);          // for frequency handler
    $this->sub_filter = intval($row['sub_filter'] ?? $this->sub_filter);                // for frequency handler
    $this->split_sub = intval($row['split_sub'] ?? $this->split_sub);                   // for frequency handler
    $this->power_off_limit = floatval($row['power_off_limit'] ?? $this->power_off_limit);
    $this->station_id = strval($row['station_id'] ?? $this->station_id);
    $this->init_virtual_rate_from_sql();                                               // for frequency handler
    // we need to set the slots and sensors in the ADU object
    for ($i = 0; $i < NSLOTS; $i++) {
      $slot = $this->get_slot($i);
      if ($slot === null) {
        continue;
      }
      $kv = [];
      if (isset($row['channel_types'])) {
        $kv['channel_type'] = $this->channel_types[$i] ?? '';
      }
      if (isset($row['choppers'])) {
        $kv['chopper'] = $this->choppers[$i] ?? -1;
      }
      if (isset($row['gains'])) {
        $kv['gain'] = $this->gains[$i] ?? -1;
      }
      if (isset($row['dipole_lengths'])) {
        $kv['dipole_length'] = $this->dipole_lengths[$i] ?? 0.0;
      }
      $slot->set_kv($kv);
    }
  }

  /**
   * @brief Load job state from an arbitrary row (e.g. copied from jobs table) and persist to job.db.
   * @param array $row Associative row from jobs table.
   */
  public function restore_from_row(array $row): void {
    // jobs-table-only metadata must not be persisted back into job.db
    unset($row['slots_on'], $row['started']);
    $this->load_row($row);
    $this->persist_job_state();
  }
  // update is handled by the handle_post_updates function
  public function empty() {
    $this->db->empty_table(); //!< empty the job table in the database
  }

  /**
   * @brief Persist the current job state to the database.
   */
  private function persist_job_state(): void {
    $kv = [
      'start_date' => $this->get_start_date(),
      'start_time' => $this->get_start_time(),
      'duration'   => $this->get_duration(),
      'sampling_rate' => $this->sampling_rate,
      'digital_filter' => $this->digital_filter,
      'split_main' => $this->split_main,
      'cal_mode' => $this->cal_mode,
      'channel_types' => $this->get_channel_types(), //!< get the channel types from the ADU object as CSV string
      'choppers' => $this->get_choppers(), //!< get the chopper settings from the ADU object as CSV string
      'gains' => $this->get_gains(), //!< get the gain settings from the ADU object as CSV string
      'dipole_lengths' => $this->get_dipole_lengths(), //!< get the dipole lengths
      'use_atss' => $this->use_atss,
      'copy_to_usb' => $this->copy_to_usb,
      'sub_cycle' => $this->sub_cycle,
      'sub_duration' => $this->sub_duration,
      'sub_filter' => $this->sub_filter,
      'split_sub' => $this->split_sub,
      'power_off_limit' => $this->power_off_limit,
      'station_id' => $this->station_id,
    ];
    $this->db->update_job_table($kv); //!< write the updates to the database immediately
  }

  /**
   * @brief Detect POST-driven mode switches that already persisted through the job wrapper.
   */
  private function is_frequency_mode_switch_post(): bool {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
      return false;
    }

    if (($_POST['sender'] ?? '') !== 'frequency_handler') {
      return false;
    }

    return in_array($_POST['what'] ?? '', ['set_straight', 'set_filter', 'set_shots'], true);
  }

  public function set_straight(): void {
    parent::set_straight();
    $this->persist_job_state();
  }

  public function set_filter(): void {
    parent::set_filter();
    $this->persist_job_state();
  }

  public function set_shots(): void {
    parent::set_shots();
    $this->persist_job_state();
  }

  public function get_power_off_limit(): float {
    return $this->power_off_limit;
  }

  public function set_power_off_limit(float $limit): void {
    $this->power_off_limit = $limit;
    $this->persist_job_state();
  }

  public function get_station_id(): string {
    return $this->station_id;
  }

  public function set_station_id(string $station_id): void {
    $this->station_id = $station_id;
    $this->persist_job_state();
  }

  /**
   * @brief Route POST updates from UI widgets and write changes to DB immediately.
   * @return bool True when at least one field was changed and persisted.
   */
  public function handle_post_updates(bool $force = false): bool {
    $updated = parent::handle_post_updates();
    $updated_job = false;
    // we route the POST updates to the appropriate objects and write to DB immediately
    // this is called from index.php on every page load, so it will catch any updates from the UI widgets and persist them immediately
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {

      // cal mode always off
      // digital filter not set directly
      if (isset($_POST['use_atss'])) {
        $this->use_atss = intval($_POST['use_atss']);
        $updated = true;
        $updated_job = true;
      }
      if (isset($_POST['copy_to_usb'])) {
        $this->copy_to_usb = intval($_POST['copy_to_usb']);
        $updated = true;
        $updated_job = true;
      }
      if (isset($_POST['power_off_limit'])) {
        $this->power_off_limit = floatval($_POST['power_off_limit']);
        $updated = true;
        $updated_job = true;
      }
      if (isset($_POST['station_id'])) {
        $this->station_id = strval($_POST['station_id']);
        $updated = true;
        $updated_job = true;
      }
    }
    // after routing all updates, we write to DB if there were any updates
    if ($updated || $this->adu_updated || $force) {
      if (!$this->is_frequency_mode_switch_post() || $updated_job || $this->adu_updated || $force) {
        $this->persist_job_state();
      }
    }
    return $updated;
  }

  public function subjob_status() {
    $status_text = 'not scheduled';
    if ($this->is_filter()) {
      $status_text = 'filter job';
    } else if ($this->is_shot()) {
      $status_text = 'shot job';
    }
    return '<a href="subjob.php" class="w3-text-black">' . htmlspecialchars($status_text, ENT_QUOTES, 'UTF-8') . '</a>';
  }
} // end job class
