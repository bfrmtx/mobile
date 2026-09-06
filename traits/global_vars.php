<?php

const PREP_TIME = 30;        //!< seconds the system needs to prepare for a job. time between jobs must be at least this long 
const MIN_JOB_DURATION = 8;  //!< seconds the system runs a job at least. 
const GRID = 64;             //!< seconds   n * GRID after 1970-01-01 00:00:00 UTC is a raster or grid to safely start a job
const DB_TIMEOUT = 2;        //!< seconds to wait for a database query to complete before timing out

trait global_vars {


  protected array $channel_types_h = [    // shared hard-coded channel type selector values for magnetic field sensors
    'Hx',
    'Hy',
    'Hz'
  ];
  protected array $channel_types_e = [    // shared hard-coded channel type selector values for electric field sensors
    'Ex',
    'Ey',
    'Ez',
    'Jx',
    'Jy',
    'Jz'
  ];
  protected array $sensor_types_e = [      //!< shared hard-coded sensor type selector values for electric field sensors
    'EFP-06',
    'EFP-07',
    'StROD',
    'BUF_5',
    'BUF_10',
    'BUF_25',
    'Plate',
    'Clamp'
  ];
  protected array $sensor_types_e_aliases = [      //!< shared hard-coded sensor type selector values for electric field sensors
    'EFP06',
    'EFP07',
    'StROD',
    'BUF_5',
    'BUF_10',
    'BUF_25',
    'Plate',
    'Clamp'
  ];
  protected array $sensor_types_h = [      //!< shared hard-coded sensor type selector values for magnetic field sensors
    'MFS-06e',
    'MFS-07e',
    'MFS-10e',
    'MFS-12e',
    'MFS-14e',
    'SHFT-02e',
    'SHFT-03e',
    'SHFT-04e',
    'FGS-03e',
    'FGS-04e'
  ];
  protected array $sensor_types_h_aliases = [      //!< shared hard-coded sensor type selector values for magnetic field sensors
    'MFS06e',
    'MFS07e',
    'MFS10e',
    'MFS12e',
    'MFS14e',
    'SHFT02e',
    'SHFT03e',
    'SHFT04e',
    'FGS03e',
    'FGS04e'
  ];

  protected array $allowed_choppers = [-1, 0, 1]; //!< shared hard-coded chopper selector values for electric field sensors, -1 for auto, 0 for chopper off, 1 for chopper on
  protected array $allowed_gains = [              //!< shared hard-coded gain selector values for electric and magnetic field sensors, -1 for auto, 1,2,4,8 for gain of 1, 2, 4 and 8
    -1,
    1,
    2,
    4,
    8
  ];
  protected array $sampling_rates_adu_11e = [      //!< shared hard-coded sample rate for ADU-11e
    131072,
    65536,
    32768,
    16384,
    8192,
    4096,
    2048,
    1024
  ];
  protected array $sampling_rates_adu_10e = [      //!< shared hard-coded sample rate selector values for electric and magnetic field sensors for ADU-10e, which has a different set of sampling rates
    4096,
    2048,
    1024,
    512
  ];


  protected array $digital_filters = [      //!< shared hard-coded digital filter, 0 == off, 4, 8, 32 for decimation rates
    0,
    4,
    8,
    32
  ];

  protected array $cycles_mins = [              //!< shared hard-coded cycle selector values for cyclic sub-jobs, minutes (calc seconds later), so start a subjob every x minutes
    1,
    2,
    3,
    5,
    15,
    30,
    60,
    240,
  ];


  protected array $shot_durations_mins = [                //!< shared hard-coded shot selector values for cyclic sub-jobs, in minutes. calculate seconds later by x * 64))
    0.5,
    1,
    3,
    10,
    15,
    30,
    60,
    180
  ];

  protected array $copy_to_usbs = [           //!< shared hard-coded copy to USB selector values for copying the job data to USB after the job is finished, 0 for no copy, 1 for copy; leave the USB device connected!
    0,
    1
  ];

  /**
   * @brief Map a sensor type stored in the SQL database (alias form, e.g. 'MFS06e', 'EFP06')
   *        to the canonical form used by the web interface (e.g. 'MFS-06e', 'EFP-06').
   * @param string $alias Sensor type as stored in the database.
   * @return string Canonical sensor type, or the unchanged input if no mapping applies.
   * @details The *_aliases arrays are index-aligned with their canonical counterparts.
   */
  protected function sensor_type_from_alias(string $alias): string {
    $idx = array_search($alias, $this->sensor_types_e_aliases, true);
    if ($idx !== false) {
      return $this->sensor_types_e[$idx];
    }
    $idx = array_search($alias, $this->sensor_types_h_aliases, true);
    if ($idx !== false) {
      return $this->sensor_types_h[$idx];
    }
    return $alias; // already canonical or empty/unknown: leave unchanged
  }

  /**
   * @brief Map a canonical sensor type used by the web interface (e.g. 'MFS-06e', 'EFP-06')
   *        to the alias form stored in the SQL database (e.g. 'MFS06e', 'EFP06').
   * @param string $canonical Sensor type as used by the web interface.
   * @return string Alias sensor type, or the unchanged input if no mapping applies.
   * @details The *_aliases arrays are index-aligned with their canonical counterparts.
   */
  protected function sensor_type_to_alias(string $canonical): string {
    $idx = array_search($canonical, $this->sensor_types_e, true);
    if ($idx !== false) {
      return $this->sensor_types_e_aliases[$idx];
    }
    $idx = array_search($canonical, $this->sensor_types_h, true);
    if ($idx !== false) {
      return $this->sensor_types_h_aliases[$idx];
    }
    return $canonical; // already alias or empty/unknown: leave unchanged
  }
}


/**
 * @brief Columns for the job table.
 * @details This array defines the structure of the job table in the database.
 * @note most of them appear as variables in class later
 */
$job_table_columns = [
  'id',
  'start_date',
  'start_time',
  'duration',
  'sampling_rate',
  'digital_filter',
  'split_main',
  'cal_mode',
  'channel_types',
  'choppers',
  'gains',
  'dipole_lengths',
  'use_atss',
  'copy_to_usb',
  'sub_cycle',
  'sub_duration',
  'sub_filter',
  'split_sub',
  'station_id'
];

/**
 * @brief Columns for the jobs table, including additional fields like power_off_limit, slots_on, and started.
 * @details This array defines the structure of the jobs table in the database.
 * @note most of them appear as variables in class later
 */
$jobs_table_columns = [
  'id',
  'start_date',
  'start_time',
  'duration',
  'sampling_rate',
  'digital_filter',
  'split_main',
  'cal_mode',
  'channel_types',
  'choppers',
  'gains',
  'dipole_lengths',
  'use_atss',
  'copy_to_usb',
  'sub_cycle',
  'sub_duration',
  'sub_filter',
  'split_sub',
  'station_id',
  'power_off_limit',
  'slots_on',
  'started'
];
