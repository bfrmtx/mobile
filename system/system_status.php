<?php

/** * @file system_status.php
 * @brief This file defines the system status class, which represents the overall status of the ADU system, including the status of all slots and other relevant information.
 * database: systemStatus.db
 * tables: adu, gps, recording, ALL key value pairs. READ ONLY
 * the main pager later, need a refresh all 2 seconds
 */

require_once __DIR__ . '/../config.php'; //!< make your SESSION and path.
require_once TRAITS_DIR . 'global_vars.php';  //!< this trait provides the  global vars

/**
 * @brief Convert milliseconds to degrees as string like 41°24'12.2"N 2°10'26.5"E for paste into google maps
 */
function milli_seconds_to_degree_lat(float $ms): string {
  // 1 degree = 60 minutes = 3600 seconds = 3,600,000 ms
  return sprintf(
    "%d°%d'%0.1f\"%s",
    floor(abs($ms) / 3600000), // degrees
    floor((abs($ms) % 3600000) / 60000), // minutes
    (abs($ms) % 60000) / 1000, // seconds
    ($ms >= 0) ? 'N' : 'S' // N/S for latitude, E/W for longitude (you can adjust this based on your needs)
  );
}

/**
 * @brief Convert milliseconds to degrees as string like 41°24'12.2"N 2°10'26.5"E for paste into google maps
 */
function milli_seconds_to_degree_lon(float $ms): string {
  // 1 degree = 60 minutes = 3600 seconds = 3,600,000 ms
  return sprintf(
    "%d°%d'%0.1f\"%s",
    floor(abs($ms) / 3600000), // degrees
    floor((abs($ms) % 3600000) / 60000), // minutes
    (abs($ms) % 60000) / 1000, // seconds
    ($ms >= 0) ? 'E' : 'W' // N/S for latitude, E/W for longitude (you can adjust this based on your needs)
  );
}

/**
 * convert seconds to hh mm ss
 */
function seconds_to_hh_mm_ss(int $seconds): string {
  $hours = floor($seconds / 3600);
  $minutes = floor(($seconds % 3600) / 60);
  $secs = $seconds % 60;
  return sprintf("%02d:%02d:%02d", $hours, $minutes, $secs);
}

class adu_status {
  private string  $date_time;                     //!< date and time of the status reading YYYY-MM-DD HH:MM:SS
  private int     $batt_state;                    //!< battery state of the ADU 0 = low, 2 = fair, 3 good
  private float   $batt_voltage_1;                //!< battery voltage for battery 1; red = 10, orange >= 11.5 green >= 12.8 V
  private float   $batt_voltage_2;                //!< battery voltage for battery 2; red = 10, orange >= 11.5 green >= 12.8 V
  private float   $temperature_system;            //!< system temperature of the ADU deg Celcius 
  private float   $temperature_sensor;            //!< sensor temperature of the ADU deg Celcius
  private int     $free_disk_space_mb_sd;         //!< free disk space on SD card in MB
  private int     $free_disk_space_mb_usb;        //!< free disk space on USB drive in MB
  private int     $selftest_status;               //!< self test status 0 = not run, 1 = pass, 2 = fail
  private int     $selftest_active;               //!< whether self test is currently active (0 = no, 1 = yes)
  private int     $recording_status;              //!< recording status 0 = not recording, 1 = recording
  private int     $ts_copy_status;                //!< copy status 

  public function __construct(array $kv) {
    $this->date_time = $kv['date_time'] ?? '';
    $this->batt_state = $kv['batt_state'] ?? '';
    $this->batt_voltage_1 = floatval($kv['batt_voltage_1'] ?? 0);
    $this->batt_voltage_2 = floatval($kv['batt_voltage_2'] ?? 0);
    $this->temperature_system = floatval($kv['temperature_system'] ?? 0);
    $this->temperature_sensor = floatval($kv['temperature_sensor'] ?? 0);
    $this->free_disk_space_mb_sd = intval($kv['free_disk_space_mb_sd'] ?? 0);
    $this->free_disk_space_mb_usb = intval($kv['free_disk_space_mb_usb'] ?? 0);
    $this->selftest_status = intval($kv['selftest_status'] ?? 0);
    $this->selftest_active = intval($kv['selftest_active'] ?? 0);
    $this->recording_status = intval($kv['recording_status'] ?? 0);
    $this->ts_copy_status = intval($kv['ts_copy_status'] ?? 0);
  }

  public function get_batt_voltage_1(): float {
    return $this->batt_voltage_1;
  }

  public function get_batt_voltage_2(): float {
    return $this->batt_voltage_2;
  }


  public function get_free_disk_space_mb_sd(): int {
    return $this->free_disk_space_mb_sd;
  }

  public function get_selftest_active(): int {
    return $this->selftest_active;
  }

  public function get_recording_active(): int {
    return $this->recording_status;
  }

  public function get_adu_status_html(): string {
    $html = '<div class="w3-container w3-padding-16">';
    $html .= '<h3 class="w3-text-deep-orange">ADU Status</h3>';
    $html .= 'ADU BIOS Date Time: ' . $this->date_time . '<br>';
    $html .= 'Battery State: ' . $this->batt_state . '<br>';
    $html .= 'Battery Voltage 1: ' . number_format($this->batt_voltage_1, 1) . ' V<br>';
    $html .= 'Battery Voltage 2: ' . number_format($this->batt_voltage_2, 1) . ' V<br>';
    $html .= 'System Temperature: ' . number_format($this->temperature_system, 1) . ' °C<br>';
    $html .= 'Sensor Temperature: ' . number_format($this->temperature_sensor, 1) . ' °C<br>';
    $html .= 'Free Disk Space SD: ' . number_format($this->free_disk_space_mb_sd, 1) . ' MB <br>';
    $html .= 'Free Disk Space USB: ' . number_format($this->free_disk_space_mb_usb, 1) . ' MB <br>';
    $html .= 'Selftest Status: ' . ($this->selftest_status === 0 ? 'Not Run' : ($this->selftest_status === 1 ? 'Pass' : 'Fail')) . '<br>';
    $html .= 'Selftest Active: ' . ($this->selftest_active === 0 ? 'No' : 'Yes') . '<br>';
    $html .= 'Recording Status: ' . ($this->recording_status === 0 ? 'Not Recording' : 'Recording') . '<br>';
    $html .= 'Timestamp Copy Status: ' . ($this->ts_copy_status === 0 ? 'Not Copied' : 'Copied') . '<br>';
    $html .= '</div>';
    return $html;
  }
}

class gps_status {
  private string $date_time;          //!< date and time of the GPS status reading YYYY-MM-DD HH:MM:SS
  private float $latitude;            //!< latitude of the GPS position in ms (milli seconds)
  private float $longitude;           //!< longitude of the GPS position in ms (milli seconds)
  private float $elevation;           //!< elevation of the GPS position in meters
  private int $sats_tracked;          //!< number of satellites currently tracked (evaluated)
  private int $sats_in_view_gps;      //!< number of GPS satellites in view
  private int $sats_in_view_glonass;  //!< number of GLONASS satellites in view
  private int $sats_in_view_beidou;   //!< number of BeiDou satellites in view
  private int $sats_in_view_galileo;  //!< number of Galileo satellites in view
  private int $sync_state;            //!< synchronization state of the GPS (0 = no fix, 1 = 2D fix, 2 = 3D fix, 3 = fully synced, 4 = G4Fix includes system sync)
  private int $mode;                  //!< mode of the GPS 0 = stationary, 1 = moving

  public function __construct(array $kv) {
    $this->date_time = $kv['date_time'] ?? '';
    $this->latitude = floatval($kv['latitude'] ?? 0);
    $this->longitude = floatval($kv['longitude'] ?? 0);
    $this->elevation = floatval($kv['elevation'] ?? 0);
    $this->sats_tracked = intval($kv['sats_tracked'] ?? 0);
    $this->sats_in_view_gps = intval($kv['sats_in_view_gps'] ?? 0);
    $this->sats_in_view_glonass = intval($kv['sats_in_view_glonass'] ?? 0);
    $this->sats_in_view_beidou = intval($kv['sats_in_view_beidou'] ?? 0);
    $this->sats_in_view_galileo = intval($kv['sats_in_view_galileo'] ?? 0);
    $this->sync_state = intval($kv['sync_state'] ?? 0);
    $this->mode = intval($kv['mode'] ?? 0);
  }

  public function get_sync_state(): int {
    return $this->sync_state;
  }
  public function get_date_time(): string {
    return $this->date_time;
  }

  public function get_gps_status_html(): string {
    $html = '<div class="w3-container w3-padding-16">';
    $html .= '<h3 class="w3-text-deep-orange">GPS Status</h3>';
    $html .= 'Date Time: ' . $this->date_time . '<br>';
    $html .= 'Latitude: ' . milli_seconds_to_degree_lat($this->latitude) . '<br>';
    $html .= 'Longitude: ' . milli_seconds_to_degree_lon($this->longitude) . '<br>';
    $html .= 'Elevation: ' . $this->elevation . ' m<br>';
    $html .= 'Satellites Tracked (in use): ' . $this->sats_tracked . '<br>';
    $html .= 'Satellites in View (GPS): ' . $this->sats_in_view_gps . '<br>';
    $html .= 'Satellites in View (GLONASS): ' . $this->sats_in_view_glonass . '<br>';
    $html .= 'Satellites in View (BeiDou): ' . $this->sats_in_view_beidou . '<br>';
    $html .= 'Satellites in View (Galileo): ' . $this->sats_in_view_galileo . '<br>';
    $html .= 'Sync State: ' . $this->sync_state . '<br>';
    $html .= 'Mode: ' . $this->mode . '<br>';
    $html .= '</div>';
    return $html;
  }
}

class recording_status {
  private int $sampling_rate;             //!< sampling rate of the recording in Hz
  private int $buffer_size;               //!< buffer size of the recording
  private int $num_buffers;               //!< number of buffers
  private int $used_channels;             //!< number of used channels
  private int $remaining_job_time;        //!< remaining job time in seconds
  private int $time_to_next_job;          //!< time to next job in seconds
  private string $target_directory;       //!< target directory /mtdata/data/....

  public function __construct(array $kv) {
    $this->sampling_rate = intval($kv['sampling_rate'] ?? 0);
    $this->buffer_size = intval($kv['buffer_size'] ?? 0);
    $this->num_buffers = intval($kv['num_buffers'] ?? 0);
    $this->used_channels = intval($kv['used_channels'] ?? 0);
    $this->remaining_job_time = intval($kv['remaining_job_time'] ?? 0);
    $this->time_to_next_job = intval($kv['time_to_next_job'] ?? 0);
    $this->target_directory = $kv['target_directory'] ?? '';
  }

  public function get_sampling_rate(): int {
    return $this->sampling_rate;
  }

  public function get_time_to_next_job(): int {
    return $this->time_to_next_job;
  }

  public function get_recording_status_html(): string {
    $html = '<div class="w3-container w3-padding-16">';
    $html .= '<h3 class="w3-text-deep-orange">Recording Status</h3>';
    $html .= 'Sampling Rate: ' . $this->sampling_rate . ' Hz<br>';
    $html .= 'Buffer Size: ' . $this->buffer_size . '<br>';
    $html .= 'Number of Buffers: ' . $this->num_buffers . '<br>';
    $html .= 'Used Channels: ' . $this->used_channels . '<br>';
    $html .= 'Remaining Job Time: ' . seconds_to_hh_mm_ss($this->remaining_job_time) . '<br>';
    $html .= 'Time to Next Job: ' . seconds_to_hh_mm_ss($this->time_to_next_job) . '<br>';
    $html .= 'Target Directory: ' . $this->target_directory . '<br>';
    $html .= '</div>';
    return $html;
  }
}

class status {
  protected database $db; //!< database connection for self test results
  protected array $table_names = ['adu', 'gps', 'recording']; //!< table names for system status
  protected string $db_file = 'systemStatus.db'; //!< database file for system status
  public ?adu_status $adu_status = null; //!< adu_status object for the ADU status
  public ?gps_status $gps_status = null; //!< gps_status object for the GPS status
  public ?recording_status $recording_status = null; //!< recording object for the recording status

  public function __construct() {
    $this->db = new database($this->db_file);
    // read all tables with their key value pairs.
    foreach ($this->table_names as $table_name) {
      $this->db->set_table($table_name);
      $kv = $this->db->read_key_value_table();
      if ($table_name === 'adu') {
        $this->adu_status = new adu_status($kv);
      } elseif ($table_name === 'gps') {
        $this->gps_status = new gps_status($kv);
      } elseif ($table_name === 'recording') {
        $this->recording_status = new recording_status($kv);
      }
    }
  }
}
