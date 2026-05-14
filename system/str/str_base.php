<?php

/** * @file str_base.php
 * @brief This file defines the STR (self test result) base class, which represents a base class for self test results in the system. These are ALL key value pairs tables! All read only
 */

require_once __DIR__ . '/../../config.php';      //!< make your SESSION and path.
require_once TRAITS_DIR . 'global_vars.php';  //!< this trait provides the  global vars

class str_base {
  protected $kv = []; //!< key value pairs for the STR (self test result) base class
  protected string $table_name = ''; //!< table name for the STR (self test result) base class
  protected string $board_type = '';         //!< bpl (backplane) --- IGNORE ---
  protected int $slot_num = -1; //!< slot number for the STR (self test result) base class
  public function __construct(string $table_name_, array $kv_) {
    $this->kv = $kv_;
    $this->table_name = $table_name_;
    if ($this->table_name === 'con') {
      $this->board_type = "Conn. Board";
    } elseif ($this->table_name === 'main_backplane') {
      $this->board_type = "Main Backplane";
    } elseif ($this->table_name === 'cpu') {
      $this->board_type = "CPU Board";
    } elseif ($this->table_name === 'gps') {
      $this->board_type = "GPS Board";
    } elseif ($this->table_name === 'led') {
      $this->board_type = "LED Board";
    } elseif (strpos($this->table_name, 'slot') !== false) {
      $this->board_type = "Slot " . str_replace('slot', '', $this->table_name);
      $this->slot_num = intval(str_replace('slot', '', $this->table_name));
    } else {
      $this->board_type = "Unknown Board Type";
    }
  }

  public function __destruct() {
    // Destructor code if needed    
  }

  public function get_board_type(): string {
    if ($this->slot_num >= 0) {
      return "Slot " . $this->slot_num;
    }
    return $this->board_type;
  }

  public function get_max_amplitude(): string {
    if ($this->slot_num == -1) {
      return ''; // do nothing if not a slot, as only slots have max amplitude values
    }
    $html = '';
    if (isset($this->kv['max_amplitude'])) {
      $max_amplitude_value = floatval($this->kv['max_amplitude']);
      if ($max_amplitude_value > 0) {
        $html .= '<span class="w3-text-blue"> Max Amplitude: ' . number_format($max_amplitude_value, 3) . ' mV</span>';
      } else {
        $html .= '<span class="w3-text-gray"> --- </span>';
      }
    } else {
      $html .= '<span class="w3-text-gray"> --- </span>';
    }
    return $html;
  }

  public function get_noise_check(): string {
    if ($this->slot_num == -1) {
      return ''; // do nothing if not a slot, as only slots have noise check values
    }
    $html = '';
    if (isset($this->kv['noise_check'])) {
      $noise_check_value = floatval($this->kv['noise_check']);
      if ($noise_check_value < 0.5) {
        $html .= '<span class="w3-text-green"> Noise Check: ' . number_format($noise_check_value, 3) . ' mV</span>';
      } elseif ($noise_check_value < 0.55) {
        $html .= '<span class="w3-text-yellow"> Noise Check: ' . number_format($noise_check_value, 3) . ' mV</span>';
      } else {
        $html .= '<span class="w3-text-red"> Noise Check: ' . number_format($noise_check_value, 3) . ' mV</span>';
      }
    } else {
      $html .= '<span class="w3-text-gray"> --- </span>';
    }
    return $html;
  }

  /**
   * brief get the error code, an integer where 0 means no error, and non-zero means warning or error. We will color code it in the HTML output.
   */
  public function get_error_code(): string {
    $html = '';
    $error_code = $this->kv['error_code'] ?? null;
    if ($error_code === null) {
      $html .= '<span class="w3-text-gray"> error_code: ---</span>';
    } elseif (intval($error_code) == 0) {
      $html .= '<span class="w3-text-green"> error_code: ' . $error_code . ' (OK)</span>';
    } else {
      $html .= '<span class="w3-text-red"> error_code: ' . $error_code . ' (Warning / Error)</span>';
    }
    return $html;
  }

  public function get_severity(): string {
    $html = '';
    $severity = $this->kv['severity'] ?? null;
    if ($severity === null) {
      $html .= '<span class="w3-text-gray"> severity: ---</span>';
    } elseif (intval($severity) == 0) {
      $html .= '<span class="w3-text-green"> severity: ' . $severity . ' (OK)</span>';
    } elseif (intval($severity) == 1) {
      $html .= '<span class="w3-text-yellow"> severity: ' . $severity . ' (Warning)</span>';
    } else {
      $html .= '<span class="w3-text-red"> severity: ' . $severity . ' (Warning / Error)</span>';
    }
    return $html;
  }

  public function get_message(): string {
    $html = '';
    if (!empty($this->kv['message'])) {
      if ($this->kv['severity'] == 0) {
        $html .= '<span class="w3-text-green"> message: ' . htmlspecialchars($this->kv['message']) . '</span>';
      } elseif ($this->kv['severity'] == 1) {
        $html .= '<span class="w3-text-yellow"> message: ' . htmlspecialchars($this->kv['message']) . '</span>';
      } else {
        $html .= '<span class="w3-text-red"> message: ' . htmlspecialchars($this->kv['message']) . '</span>';
      }
    } else {
      $html .= '<span class="w3-text-gray"> --- </span>';
    }
    return $html;
  }

  /**
   * brief get the LSB (least significant bit), a float
   */
  public function get_lsb(): string {
    if ($this->slot_num == -1) {
      return ''; // do nothing if not a slot, as only slots have LSB values
    }
    $html = '';
    $html .= '<span class="w3-text-black"> --- </span>';
    if (isset($this->kv['lsb'])) {
      $lsb_value = floatval($this->kv['lsb']);
      if ($lsb_value > 0) {
        $html = '<span class="w3-text-blue"> LSB: ' . number_format($lsb_value, 6) . '</span>';
      } else {
        $html = '<span class="w3-text-gray"> --- </span>';
      }
    }
    return $html;
  }
}

// end str_base class