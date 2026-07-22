<?php

/** * @file adu.php
 * @brief This file defines the ADU class, which represents an ADU (Analog/Digital Unit) in the system. 
 */

require_once __DIR__ . '/../config.php'; //!< make your SESSION and path.
require_once TRAITS_DIR . 'global_vars.php';  //!< this trait provides the  global vars

class adu extends frequency_handler {
  protected string $board_type = '';          //!< ADU-11e, ADU-10e
  protected int $serial = -1;                 //!< serial number of the ADU
  public array  $channel_types  = [];         //!< per-slot channel type settings (in slot class), we take care of explode and implode
  public array  $choppers       = [];         //!< per-slot chopper settings (in sensor class), we take care of explode and implode
  public array  $gains          = [];         //!< per-slot gain settings (in slot class), we take care of explode and implode
  public array  $dipole_lengths = [];         //!< per-slot dipole lengths (in sensor class), we take care of explode and implode
  private array $slots = [];                  //!< array of slot objects for this ADU
  // the sampling rate is in the job class
  private array $sampling_rates = [];         //!< array of native sampling rates for this ADU shall never be changed!
  private ?database $db = null;               //!< database connection for this ADU
  // we use these only for log, error and status messages

  public function __construct($serial_ = -1) {
    $this->serial = $serial_;
    // initialize the slots based on the board type
    // KISS keep it simple and stupid. system is defined in config.php
    if ($_SESSION['system_type'] == 'ADU-11e') {
      $this->sampling_rates = $this->sampling_rates_adu_11e; //!< set the sampling rates for ADU-11e
      for ($i = 0; $i < NSLOTS; $i++) {
        $this->slots[] = new slot($i, "ADU-11E-BB", $this->serial); // eraly creation, slot class will slecet a default sensor type
      }
    } elseif ($_SESSION['system_type'] == 'ADU-10e') {
      $this->sampling_rates = $this->sampling_rates_adu_10e; //!< set the sampling rates for ADU-10e
      for ($i = 0; $i < NSLOTS; $i++) {
        $this->slots[] = new slot($i, "ADU-10E-LF", $this->serial); // eraly creation, slot class will slecet a default sensor type
      }
    }
    $this->dipole_lengths = array_fill(0, NSLOTS, 0.0);
    parent::__construct($this->sampling_rates); //!< call the parent constructor to initialize the frequency handler with the sampling rates
  }
  public function __destruct() {
    // Destructor code if needed
  }

  // set board type and set serial ate NOT avaliable; this is done by firmaware

  public function get_serial(): int {
    return $this->serial;
  }

  public function get_board_type(): string {
    return $this->board_type;
  }

  public function get_lowest_sampling_rate(): int {
    if (empty($this->sampling_rates)) {
      return 0;
    }
    return min($this->sampling_rates);
  }

  public function get_highest_sampling_rate(): int {
    if (empty($this->sampling_rates)) {
      return 0;
    }
    return max($this->sampling_rates);
  }

  public function get_slot(int $i): ?slot {
    return $this->slots[$i] ?? null;
  }

  /**
   * @brief Route POST updates from UI widgets and write changes to DB immediately.
   * @return bool True when at least one field was changed and persisted.
   */
  public function handle_post_updates(): bool {
    if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
      return false;
    }
    $updated = parent::handle_post_updates(); // includes frequency_handler + job_time handlers

    $post_class = $_POST['class'] ?? null;
    if (is_array($post_class) && isset($post_class['slot'])) {
      $slot_num = intval($post_class['slot']);
      if (isset($this->slots[$slot_num])) {
        $slot = $this->slots[$slot_num];
        if ($slot->handle_post_updates()) {
          $this->persist_slot_hwConfig($slot_num, $slot);
          $updated = true;
        }
      }
      return $updated;
    }

    // Fallback: dispatch to all slots for generic POST shapes.
    // foreach ($this->slots as $slot_num => $slot) {
    //   if ($slot->handle_post_updates()) {
    //     $this->persist_slot_hwConfig($slot_num, $slot);
    //     $updated = true;
    //   }
    // }

    return $updated;
  }

  /**
   * @brief Persist one slot (including its sensor fields) to hwConfig DB.
   */
  private function persist_slot_hwConfig(int $slot_num, slot $slot): void {
    $db_file = 'hwConfig.db'; //!< path is handled in database class
    $this->db = new database($db_file);
    $this->db->set_table('slot' . $slot_num);
    // hwConfig is update-only: keep existing rows and ids stable.
    $this->db->create_key_value_table();

    $kv_db = [
      // only items for hwConfig
      // DB stores aliases (e.g. 'MFS06e', 'EFP06'); convert canonical web-interface names back.
      'sensor_type' => $slot->get_sensor_type_alias(),
      'sensor_serial' => $slot->get_sensor_serial(),
    ];
    $this->db->update_key_value_table($kv_db);
  }



  // ************* assembly and helper functions for the job class *****************

  /**
   * @brief Get the channel types for all slots
   * @brief the job class needs this to generate the channel types for the $channel_types in SQL column
   * @return string Comma-separated list of channel types
   */
  public function get_channel_types(): string {
    $channel_types = [];
    foreach ($this->slots as $slot) {
      $channel_types[] = $slot->get_channel_type();
    }
    return implode(',', $channel_types);
  }

  /**
   * @brief Get the chopper status for all slots
   * @brief the job class needs this to generate the chopper for the $chopper in SQL column
   * @return string Comma-separated list of chopper statuses
   */
  public function get_choppers(): string {
    $choppers = [];
    foreach ($this->slots as $slot) {
      $choppers[] = $slot->get_chopper();
    }
    return implode(',', $choppers);
  }

  /**
   * @brief Get the gains for all slots
   * @brief the job class needs this to generate the gains for the $gains in SQL column
   * @return string Comma-separated list of gains
   */

  public function get_gains(): string {
    $gains = [];
    foreach ($this->slots as $slot) {
      $gains[] = $slot->get_gain();
    }
    return implode(',', $gains);
  }


  /**
   * @brief Get the dipole lengths for all slots
   * @brief the job class needs this to generate the dipole lengths for the $dipole_lengths in SQL column
   * @return string Comma-separated list of dipole lengths
   */
  public function get_dipole_lengths(): string {
    $dipole_lengths = [];
    foreach ($this->slots as $slot) {
      $dipole_lengths[] = $slot->get_dipole_length();
    }
    return implode(',', $dipole_lengths);
  }

  /**
   * @brief Build slots_on CSV for firmware/job export.
   * @details Slots 0 and 1 are always enabled. Slots >1 are enabled when sensor_type is not empty.
   * @return string Comma-separated slot states, e.g. "1,1,1,1,1,0,0,0"
   */
  public function get_slots_on(bool $electric_off = false): string {
    $slots_on = [];
    foreach ($this->slots as $i => $slot) {
      if ($i <= 1) {
        $slots_on[] = $electric_off ? 0 : 1;
      } else {
        $slots_on[] = (trim($slot->get_sensor_type()) !== '') ? 1 : 0;
      }
    }
    // error message if all are 0
    if (array_sum($slots_on) === 0) {
      $_SESSION['error_message'] = 'Warning: All slots are off. Please enable at least one slot with a valid sensor type.';
    }
    return implode(',', $slots_on);
  }

  // ************* end helper functions for the job class *****************

  public function get_sampling_rates(): array {
    return $this->sampling_rates;
  }

  public function get_max_sampling_rate(): int {
    if (empty($this->sampling_rates)) {
      return 0;
    }
    return max($this->sampling_rates);
  }

  public function read_hwConfig() {
    $db_file = 'hwConfig.db'; //!< path is handled in database class
    $this->db = new database($db_file);
    $this->db->set_table('adu');
    $kv = $this->db->read_key_value_table();
    // now set the properties of the ADU based on the values in the database
    if (isset($kv['board_type'])) {
      $this->board_type = strval($kv['board_type']);
    }
    if (isset($kv['serial'])) {
      $this->serial = intval($kv['serial']);
    }

    // read the slots for this ADU
    $i = 0;
    foreach ($this->slots as $slot) {
      // clear kv
      $this->db->set_table('slot' . $i);
      $kv = [];
      $kv = $this->db->read_key_value_table();
      if ($i >= 2 && $i <= 7 && isset($kv['sensor_serial']) && intval($kv['sensor_serial']) === 0) {
        if (trim(strval($kv['sensor_type'] ?? '')) !== '') {
          $this->db->update_key_value_table(['sensor_type' => '']);
        }
        $kv['sensor_type'] = '';
      }
      $slot->set_kv($kv); //!< set the properties of the slot (and sensor) based on the values in the database
      $i++;
    }
    $this->read_selftest_result_gains(); //!< load read-only selftest gains for slot0..slot7
    // others are not needed for hardware configuration, they are not configurable.
    // we later use them for status and longs, but not creating jobs.
  }

  /**
   * @brief Read selftest results from selftestResult.db and apply to slots.
   * @details Read-only: gain, dc_offset, probe_resistivity, probe_resistivity_gnd_1, probe_resistivity_gnd_2.
   */
  public function read_selftest_result_gains(): void {
    try {
      $db = new database('selftestResult.db');
    } catch (RuntimeException $e) {
      // Selftest DB may not exist yet; keep defaults.
      return;
    }

    for ($i = 0; $i < NSLOTS && $i <= 7; $i++) {
      $slot = $this->get_slot($i);
      if ($slot === null) {
        continue;
      }
      $db->set_table('slot' . $i);
      try {
        $kv = $db->read_key_value_table();
      } catch (RuntimeException $e) {
        continue;
      }
      if (isset($kv['gain'])) {
        $slot->set_selftest_gain(intval($kv['gain']));
      }
      if (isset($kv['dc_offset'])) {
        $slot->set_selftest_dc_offset(floatval($kv['dc_offset']));
      }
      if (isset($kv['probe_res'])) {
        $slot->set_selftest_probe_resistivity(floatval($kv['probe_res']));
      }
      if (isset($kv['probe_res_gnd_1'])) {
        $slot->set_selftest_probe_resistivity_gnd_1(floatval($kv['probe_res_gnd_1']));
      }
      if (isset($kv['probe_res_gnd_2'])) {
        $slot->set_selftest_probe_resistivity_gnd_2(floatval($kv['probe_res_gnd_2']));
      }
    }
  }
} // end adu class