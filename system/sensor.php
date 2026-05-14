<?php

/**
 * @file sensor.php
 * @brief This file defines the sensor class, which represents the connected sensor
 * @brief the sensor is "behind" the slot and operated via slot class.
 */
require_once __DIR__ . '/../config.php';        //!< make your SESSION and path.
require_once TRAITS_DIR . 'global_vars.php';    //!< this trait provides the  global vars
class sensor {
  use global_vars; //!< global_vars for drop downs and selectors
  protected string $sensor_type = '';             //!< EFP-06, ... MFS-06e and so on, ONLY SET
  protected int $sensor_serial = -1;              //!< serial number of the sensor ONLY SET 
  protected int $chopper = -1;                    //!< -1 auto, 1 if chopper is on, 0 if off; the later array choppers admistrated by ADU, this class delivers the array elements
  // dipole lenghts will be set frequently
  protected float $dipole_length = 0.0;          //!< dipole length for electric field sensors, 0.0 for magnetic field sensors; the later dipole_lengths admistrated by ADU, this class deivers the array elements
  private array $available_sensor_types = [];     //!< available sensor types
  private int $slot_num;                          //!< my connection number to slot num; it is private! We must have a slot number.

  /**
   * @brief Constructor for the sensor class
   * @param slot_num_ The slot number to which the sensor is connected
   * @param sensor_type_ The type of the sensor (e.g., EFP-06, MFS-06e), FORCE!
   * @param sensor_serial_ The serial number of the sensor
   * The software works ONLY with detected sensors; only for slots 0,1 we can submit values for the selected sensor
   */
  public function __construct(int $slot_num_, string $sensor_type_ = '', int $sensor_serial_ = 0) {
    if (empty($sensor_type_) || $sensor_type_ === 'UNKN_E') {
      $sensor_type_ = '';                         // empty
    } else {
      $this->sensor_type = $sensor_type_;
    }
    $this->slot_num = $slot_num_;                 // same as the connected slot
    $this->sensor_serial = $sensor_serial_;
    if (in_array($sensor_type_, $this->sensor_types_e)) {
      $this->available_sensor_types = $this->sensor_types_e;
      // for E we must avoid division by zero
      $this->dipole_length = 25.0;                // Set dipole length for electric field sensors as default
      $this->chopper = 0;                         // Set chopper to off for electric field sensors as default
    } elseif (in_array($sensor_type_, $this->sensor_types_h)) {
      $this->available_sensor_types = $this->sensor_types_h;
      $this->dipole_length = 0.0;                 // Set dipole length for magnetic field sensors
      $this->chopper = -1;                        // Set chopper to auto for magnetic field sensors
    }
  }
  public function __destruct() {
    // Destructor code if needed
  }

  public function set_chopper(int $chopper_) {
    $this->chopper = $chopper_;
  }

  public function get_chopper(): int {
    return $this->chopper;
  }

  public function set_sensor_serial(int $sensor_serial_) {
    $this->sensor_serial = intval($sensor_serial_);
  }

  public function get_sensor_serial(): int {
    return $this->sensor_serial;
  }

  public function get_sensor_type(): string {
    return $this->sensor_type;
  }

  public function set_sensor_type(string $sensor_type_) {
    $was_clamp = ($this->sensor_type === 'Clamp');
    $this->sensor_type = $sensor_type_;

    if ($sensor_type_ === 'Clamp') {
      $this->dipole_length = 1000.0;
      return;
    }

    if ($was_clamp) {
      $this->dipole_length = 25.0;
    }
  }

  public function get_dipole_length(): float {
    return $this->dipole_length;
  }

  public function is_electric_sensor(): bool {
    return in_array($this->sensor_type, $this->sensor_types_e, true);
  }

  public function supports_dipole_input(): bool {
    return $this->is_electric_sensor() && in_array($this->slot_num, [0, 1], true);
  }

  protected function parse_dipole_length_input($value): ?float {
    $input = trim((string) $value);
    if (preg_match('/^\d{1,4}(\.\d{1,2})?$/', $input) !== 1) {
      return null;
    }
    $parsed = floatval($input);
    if ($parsed <= 0.0 || $parsed > 9999.99) {
      return null;
    }
    return $parsed;
  }


  public function set_dipole_length(float $dipole_length_) {

    if (in_array($this->sensor_type, $this->sensor_types_e) && $dipole_length_ <= 0.0) {
      throw new Exception('Dipole length must be greater than zero for electric field sensors');
    }
    $this->dipole_length = $dipole_length_;
  }

  public function set_kv(array $kv) {
    if (isset($kv['chopper'])) {                    // job
      $this->set_chopper(intval($kv['chopper']));
    }
    if (isset($kv['sensor_serial'])) {              // hwConfig
      $this->set_sensor_serial(intval($kv['sensor_serial']));
    }
    if (isset($kv['sensor_type'])) {                // hwConfig
      $this->set_sensor_type(strval($kv['sensor_type']));
    }
    if (isset($kv['dipole_length'])) {             // job
      $parsed_dipole_length = $this->parse_dipole_length_input($kv['dipole_length']);
      if ($parsed_dipole_length !== null && $this->supports_dipole_input()) {
        $this->set_dipole_length($parsed_dipole_length);
      }
    }
  }

  public function handle_post_updates(): bool {
    // to do
    $updated = false;
    if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
      return false;
    }
    $post_class = $_POST['class'] ?? null;
    if (is_array($post_class)) {
      $name = $post_class['name'] ?? '';
      $slot_num = $post_class['slot'] ?? null;
      $key = $post_class['key'] ?? null;
      $value = $post_class['value'] ?? null;
      if ($name === 'sensor' && $slot_num !== null && $key !== null && $value !== null) {
        $slot_num = (int) $slot_num;
        if ($slot_num === $this->slot_num) {
          $kv = [
            $key => $value,
          ];
          $this->set_kv($kv);
          $updated = true;
        }
      }
    }
    return $updated;
  }

  /**
   * @brief Generate HTML for sensor type selector dropdown
   */
  protected function select_sensor_type_() {
    $form = '<form method="POST" action="" style="display:inline-block;margin:0;vertical-align:middle;">' . PHP_EOL;
    $form .= '<select style="width:auto;min-width:8em;display:inline-block;vertical-align:middle;" name="class[value]" id="sensor_type_' . intval($this->slot_num) . '" onchange="this.form.submit()">' . PHP_EOL;

    foreach ($this->available_sensor_types as $type) {
      $selected = ($type === $this->sensor_type) ? ' selected="selected"' : '';
      $safe_type = htmlspecialchars((string) $type, ENT_QUOTES, 'UTF-8');
      $form .= '<option value="' . $safe_type . '"' . $selected . '>' . $safe_type . '</option>' . PHP_EOL;
    }

    $form .= '</select>' . PHP_EOL;
    $form .= '<input type="hidden" name="class[name]" value="sensor" />';
    $form .= '<input type="hidden" name="class[slot]" value="' . intval($this->slot_num) . '" />';
    $form .= '<input type="hidden" name="class[key]" value="sensor_type" />';
    $form .= '</form>';

    return $form;
  }

  public function dipole_length_form(): string {
    if (!$this->supports_dipole_input()) {
      return '';
    }

    $slot_num = intval($this->slot_num);
    $form_id = 'dipole_length_form_' . $slot_num;
    $input_id = 'dipole_length_input_' . $slot_num;
    $display_value = number_format($this->dipole_length, 2, '.', '');

    $form = '<form method="POST" action="" id="' . htmlspecialchars($form_id, ENT_QUOTES, 'UTF-8') . '">' . PHP_EOL;
    $form .= '<input type="hidden" name="class[name]" value="sensor" />' . PHP_EOL;
    $form .= '<input type="hidden" name="class[slot]" value="' . $slot_num . '" />' . PHP_EOL;
    $form .= '<input type="hidden" name="class[key]" value="dipole_length" />' . PHP_EOL;
    $form .= '<input style="width:98%" type="number" inputmode="decimal" min="0.01" max="9999.99" step="0.01" name="class[value]" id="' . htmlspecialchars($input_id, ENT_QUOTES, 'UTF-8') . '" value="' . htmlspecialchars($display_value, ENT_QUOTES, 'UTF-8') . '" />' . PHP_EOL;
    $form .= '</form>' . PHP_EOL;

    $safe_form_id = htmlspecialchars($form_id, ENT_QUOTES, 'UTF-8');
    $safe_input_id = htmlspecialchars($input_id, ENT_QUOTES, 'UTF-8');
    $form .= '<script>(function(){' .
      'var f=document.getElementById("' . $safe_form_id . '");' .
      'var i=document.getElementById("' . $safe_input_id . '");' .
      'if(!f||!i){return;}' .
      'var timer=null;' .
      'var last=i.value;' .
      'function isValid(){return /^\\d{1,4}(\\.\\d{1,2})?$/.test(i.value) && parseFloat(i.value)>0;}' .
      'function submitForm(){' .
      'if(!isValid()||i.value===last){return;}' .
      'last=i.value;' .
      'f.submit();' .
      '}' .
      'function submitOnPageHide(){' .
      'if(!isValid()||i.value===last){return;}' .
      'last=i.value;' .
      'var fd=new FormData(f);' .
      'if(navigator.sendBeacon){navigator.sendBeacon(window.location.href,fd);return;}' .
      'f.submit();' .
      '}' .
      'i.addEventListener("input",function(){clearTimeout(timer);timer=setTimeout(submitForm,900);});' .
      'i.addEventListener("change",submitForm);' .
      'i.addEventListener("blur",submitForm);' .
      'window.addEventListener("pagehide",submitOnPageHide);' .
      '})();</script>' . PHP_EOL;

    return $form;
  }
} // end sensor class
