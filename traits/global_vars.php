<?php
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
}
