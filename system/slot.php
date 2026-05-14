<?php

/**
 * @file slot.php
 * @brief This file defines the slot class, which represents a physical connection.
 */

require_once __DIR__ . '/../config.php';        //!< make your SESSION and path.
require_once TRAITS_DIR . 'global_vars.php';    //!< this trait provides the  global vars
class slot extends sensor {
  use global_vars; //!< global_vars for drop downs and selectors
  protected int $slot_num = -1;                 //!< slot number for this slot 
  protected string $board_type = '';            //!< ADU-11e, ADU-10e
  protected int $serial = -1;                   //!< serial number of the slot
  protected string $channel_type = '';          //!< Ex, Ey, Hx, Hy, Hz, Jx, Jy, Jz and others, first 2 chars only.
  private array $available_channel_types = [];  //!< available channel types for this slot, set in constructor based on slot number
  protected int $gain = -1;                     //!< gain for this slot, -1 = auto, 1,2,4,8 for gain of 1, 2, 4 and 8
  protected int $selftest_gain = -1;            //!< gain detected by selftest; is never -1, so we must see a different value later.
  protected float $selftest_dc_offset = 0.0;    //!< dc offset detected by selftest, for display only
  protected float $selftest_probe_resistivity = -1.0; //!< contact resistance detected by selftest, for display only, only slot 0,1
  protected float $selftest_probe_resistivity_gnd_1 = -1.0; //!< ground resistance detected by selftest, for display only, only slot 0,1
  protected float $selftest_probe_resistivity_gnd_2 = -1.0; //!< ground resistance detected by selftest, for display only, only slot 0,1

  /**
   * @brief Constructor for the slot class
   * @param slot_num_ The slot number for this slot (0-7)
   * @param board_type_ The board type of the ADU this slot belongs to (e.g., ADU-11e, ADU-10e)
   * @param serial_ The serial number of the ADU this slot belongs to
   * @param sensor_type_ The type of the sensor connected to this slot (e.g., EFP-06, MFS-12e), optional
   * @param sensor_serial_ The serial number of the sensor connected to this slot, optional
   * The software works ONLY with detected sensors; only for slots 0,1 we can submit values for the selected sensor
   */
  public function __construct(int $slot_num_, string $board_type_, int $serial_, string $sensor_type_ = '', int $sensor_serial_ = 0) {
    if ($slot_num_ > NSLOTS - 1 || $slot_num_ < 0) {
      throw new Exception('slot_num must be between 0 and ' . (NSLOTS - 1));
    }
    $this->slot_num = $slot_num_;
    $this->board_type = $board_type_;
    $this->serial = $serial_;
    // construct the parent now
    if ($this->slot_num == 0 || $this->slot_num == 1) { // electric field sensor slots
      $this->available_channel_types = $this->channel_types_e; // for electric field sensor slots we use the electric field channel types
      if ($this->slot_num == 0) {
        $this->channel_type = 'Ex';                     // ADU specific for slot 0
        if ($sensor_serial_ <= 0) {
          $sensor_serial_ = 12;                         // default to 12 if not provided or invalid
        }
      } else {
        $this->channel_type = 'Ey';                     // ADU specific for slot 1
        if ($sensor_serial_ <= 0) {
          $sensor_serial_ = 34;                         // default to 34 if not provided or invalid
        }
      }
      if (empty($sensor_type_)) {
        $sensor_type_ = 'EFP-06'; // default to 'EFP-06' if no sensor type is provided; SLOT shall decide
      }
      parent::__construct($this->slot_num, $sensor_type_, $sensor_serial_);
    } else { // magnetic field sensor slots
      $this->available_channel_types = $this->channel_types_h; // for magnetic field sensor slots we use the magnetic field channel types
      if ($this->slot_num == 2 || $this->slot_num == 5) {
        $this->channel_type = 'Hx';                     // ADU specific for slot 2 and 5
      } elseif ($this->slot_num == 3 || $this->slot_num == 6) {
        $this->channel_type = 'Hy';                     // ADU specific for slot 3 and 6
      } elseif ($this->slot_num == 4 || $this->slot_num == 7) {
        $this->channel_type = 'Hz';                     // ADU specific for slot 4 and 7
      }
      parent::__construct($this->slot_num, $sensor_type_, $sensor_serial_);
    }
  }

  public function __destruct() {
    // Destructor code if needed
  }
  public function get_slot_num(): int {
    return $this->slot_num;
  }
  public function get_board_type(): string {
    return $this->board_type;
  }
  public function get_serial(): int {
    return $this->serial;
  }
  public function get_channel_type(): string {
    return $this->channel_type;
  }

  public function set_sensor_type(string $sensor_type_) {
    parent::set_sensor_type($sensor_type_);
    // For slots 0-1: user can set sensor type manually; for slots >1 this comes from hwConfig only
    // slot_on is now derived automatically in get_slot_on()
  }

  public function get_selftest_gain(): int {
    return $this->selftest_gain;
  }

  public function set_selftest_gain(int $selftest_gain_) {
    $this->selftest_gain = $selftest_gain_;
  }

  public function set_selftest_dc_offset(float $dc_offset_) {
    $this->selftest_dc_offset = $dc_offset_;
  }

  public function get_selftest_dc_offset(): string {
    // we will convert to int first
    $int_offset = intval(round($this->selftest_dc_offset)); //  mV and round to int
    // green < 50
    // yellow < 150
    // red else
    if ($this->slot_num > 1) {
      return 'DC Offset: <span class="w3-text-black">' . $int_offset . ' mV</span>';
    }
    if (abs($int_offset) < 50) {
      return 'DC Offset: <span class="w3-text-green">' . $int_offset . ' mV</span>';
    } elseif (abs($int_offset) < 150) {
      return 'DC Offset: <span class="w3-text-yellow">' . $int_offset . ' mV</span>';
    } else {
      return 'DC Offset: <span class="w3-text-red">' . $int_offset . ' mV</span>';
    }
  }

  /**
   * only set by ADU, later no change
   */
  public function set_selftest_probe_resistivity(float $resistivity_) {
    $this->selftest_probe_resistivity = $resistivity_;
  }

  /**
   *  probe against probe, which is N/S for slot 0 and E/W for slot 1
   */
  public function get_selftest_probe_resistivity(): string {
    // 0 Probe Res. N/S
    // 1 Probe Res. E/W
    $html = '';
    if ($this->slot_num == 0) {
      $html .= 'Probe Res. N/S: ';
    } elseif ($this->slot_num == 1) {
      $html .= 'Probe Res. E/W: ';
    }
    if ($this->selftest_probe_resistivity < 4000.0) {
      $html .= '<span class="w3-text-green">' . number_format($this->selftest_probe_resistivity, 0) . ' Ω</span>';
    } elseif ($this->selftest_probe_resistivity < 10000.0) {
      $html .= '<span class="w3-text-yellow">' . number_format($this->selftest_probe_resistivity / 1000.0, 3) . ' kΩ</span>';
    } else {
      $html .= '<span class="w3-text-red">' . number_format($this->selftest_probe_resistivity / 1000.0, 1) . ' kΩ</span>';
    }
    return $html;
  }

  public function set_selftest_probe_resistivity_gnd_1(float $resistivity_) {
    $this->selftest_probe_resistivity_gnd_1 = $resistivity_;
  }

  /**
   * GND against probe 1, which is N for slot 0 and W for slot 1
   */
  public function get_selftest_probe_resistivity_gnd_1(): string {
    // 0 GND Res. N/GND
    // 1 GND Res. W/GND
    if ($this->slot_num > 1) {
      return '';
    }
    $html = '';
    if ($this->slot_num == 0) {
      $html .= 'GND Res. N/GND: ';
    } elseif ($this->slot_num == 1) {
      $html .= 'GND Res. W/GND: ';
    }
    if ($this->selftest_probe_resistivity_gnd_1 < 4000.0) {
      $html .= '<span class="w3-text-green">' . number_format($this->selftest_probe_resistivity_gnd_1, 0) . ' Ω</span>';
    } elseif ($this->selftest_probe_resistivity_gnd_1 < 10000.0) {
      $html .= '<span class="w3-text-yellow">' . number_format($this->selftest_probe_resistivity_gnd_1 / 1000.0, 3) . ' kΩ</span>';
    } else {
      $html .= '<span class="w3-text-red">' . number_format($this->selftest_probe_resistivity_gnd_1 / 1000.0, 1) . ' kΩ</span>';
    }
    return $html;
  }

  public function set_selftest_probe_resistivity_gnd_2(float $resistivity_) {
    $this->selftest_probe_resistivity_gnd_2 = $resistivity_;
  }

  /**
   * GND against probe 2, which is S for slot 0 and E for slot 1
   */
  public function get_selftest_probe_resistivity_gnd_2(): string {
    // 0 GND Res. S/GND
    // 1 GND Res. E/GND
    if ($this->slot_num > 1) {
      return '';
    }
    $html = '';
    if ($this->slot_num == 0) {
      $html .= 'GND Res. S/GND: ';
    } elseif ($this->slot_num == 1) {
      $html .= 'GND Res. E/GND: ';
    }
    if ($this->selftest_probe_resistivity_gnd_2 < 4000.0) {
      $html .= '<span class="w3-text-green">' . number_format($this->selftest_probe_resistivity_gnd_2, 0) . ' Ω</span>';
    } elseif ($this->selftest_probe_resistivity_gnd_2 < 10000.0) {
      $html .= '<span class="w3-text-yellow">' . number_format($this->selftest_probe_resistivity_gnd_2 / 1000.0, 3) . ' kΩ</span>';
    } else {
      $html .= '<span class="w3-text-red">' . number_format($this->selftest_probe_resistivity_gnd_2 / 1000.0, 1) . ' kΩ</span>';
    }
    return $html;
  }





  public function set_channel_type(string $channel_type_) {
    $this->channel_type = substr($channel_type_, 0, 2);       // we only care about the first two characters for channel type
    if ($channel_type_ == 'Jx' || $channel_type_ == 'Jy' || $channel_type_ == 'Jz') {   // clamp current density sensors, dipole length is effectively zero
      $this->set_dipole_length(1000.0);                       // for currents we set to 1000.0; so no scaling later mV/km
      $this->set_sensor_type('Clamp');                        // for current density sensors we set the sensor type to Clamp
    }
  }

  public function set_gain(int $gain_) {
    if ($gain_ < -1 || $gain_ > 8) {
      throw new Exception('Invalid gain value: ' . $gain_);
    }
    $this->gain = $gain_;
  }
  public function get_gain(): int {
    return $this->gain;
  }

  public function set_kv(array $kv) {

    if (isset($kv['gain'])) {                   // job
      $this->set_gain(intval($kv['gain']));
    }
    if (isset($kv['channel_type'])) {           // job
      $this->set_channel_type($kv['channel_type']);
    }
    if (isset($kv['board_type'])) {            // hwConfig
      $this->board_type = strval($kv['board_type']);
    }
    if (isset($kv['serial'])) {                // hwConfig 
      $this->serial = intval($kv['serial']);
    }
    parent::set_kv($kv);
  }

  public function handle_post_updates(): bool {
    // to do
    $updated = false;
    if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
      return false;
    }
    $updated_parent = parent::handle_post_updates(); // call the parent handler for any additional processing, if needed
    $post_class = $_POST['class'] ?? null;
    if (is_array($post_class)) {
      $name = $post_class['name'] ?? '';
      $slot_num = $post_class['slot'] ?? null;
      $key = $post_class['key'] ?? null;
      $value = $post_class['value'] ?? null;
      if ($name === 'slot' && $slot_num !== null && $key !== null && $value !== null) {
        $slot_num = (int) $slot_num;
        if ($slot_num == $this->slot_num) {
          $kv = [
            $key => $value,
          ];
          $this->set_kv($kv);
          $updated = true;
        }
      }
    }
    return $updated || $updated_parent; // so return true if this slot has been updated or if the parent handler updated something
  }

  /**
   * @brief Generate HTML for channel type selector dropdown
   * @details submits class[name], class[slot], class[key], class[value]
   */
  public function select_channel_type() {
    $form = '<form method="POST" action="">' . PHP_EOL;
    $form .= '<select style="width:98%" name="class[value]" id="channel_type_' . intval($this->slot_num) . '" onchange="this.form.submit()">' . PHP_EOL;

    foreach ($this->available_channel_types as $type) {
      $selected = ($type === $this->channel_type) ? ' selected="selected"' : '';
      $safe_type = htmlspecialchars((string) $type, ENT_QUOTES, 'UTF-8');
      $form .= '<option value="' . $safe_type . '"' . $selected . '>' . $safe_type . '</option>' . PHP_EOL;
    }

    $form .= '</select>' . PHP_EOL;
    $form .= '<input type="hidden" name="class[name]" value="slot" />';
    $form .= '<input type="hidden" name="class[slot]" value="' . intval($this->slot_num) . '" />';
    $form .= '<input type="hidden" name="class[key]" value="channel_type" />';
    $form .= '</form>';

    return $form;
  }

  public function select_gain() {
    $form = '<form method="POST" action="" style="display:inline-block;margin:0;vertical-align:middle;">' . PHP_EOL;
    $form .= '<select style="width:auto;min-width:4.5em;display:inline-block;vertical-align:middle;" name="class[value]" id="gain_' . intval($this->slot_num) . '" onchange="this.form.submit()">' . PHP_EOL;

    foreach ($this->allowed_gains as $gain) {
      $selected = ($gain === $this->gain) ? ' selected="selected"' : '';
      $label = ($gain === -1) ? '-1 (auto)' : (string) $gain;
      $safe_label = htmlspecialchars($label, ENT_QUOTES, 'UTF-8');
      $form .= '<option value="' . intval($gain) . '"' . $selected . '>' . $safe_label . '</option>' . PHP_EOL;
    }

    $form .= '</select>' . PHP_EOL;
    $form .= '<input type="hidden" name="class[name]" value="slot" />';
    $form .= '<input type="hidden" name="class[slot]" value="' . intval($this->slot_num) . '" />';
    $form .= '<input type="hidden" name="class[key]" value="gain" />';
    $form .= '</form>';

    return $form;
  }

  /**
   * @brief Generate HTML for each channel == slot class + sensor class
   * @details 
   *  line 1: get_channel_type()
   *  line 2: if slot 0 or 1: select_sensor_type_() , else: get_sensor_type()
   *  line 3: get_sensor_serial()
   *  line 4: get_gain() 
   */
  public function channel_html() {
    $html = '';
    $html .= '<div style="white-space:nowrap;">';
    $html .= $this->get_channel_type() . ':&nbsp;';
    if ($this->slot_num == 0 || $this->slot_num == 1) {
      $html .= $this->select_sensor_type_() . '<br>';
    } else {
      $html .= $this->get_sensor_type() . '<br>';
    }
    $html .= '</div>';
    $html .= 'S/N: ' . $this->get_sensor_serial() . '<br>';
    $html .= '<div style="white-space:nowrap;">';
    $html .= 'Gain: detected: ' . $this->get_selftest_gain() . ' actual: ';
    $html .= $this->select_gain() . '<br>';
    $html .= '</div>';
    $html .= '<div style="white-space:nowrap;">';
    $html .= $this->get_selftest_dc_offset() . '<br>';
    $html .= '</div>';
    $html .= '<div style="white-space:nowrap;">';
    $html .= $this->get_selftest_probe_resistivity() . '<br>';
    $html .= $this->get_selftest_probe_resistivity_gnd_1() . '<br>';
    $html .= $this->get_selftest_probe_resistivity_gnd_2() . '<br>';
    $html .= '</div>';
    return $html;
  }
} // end slot class
