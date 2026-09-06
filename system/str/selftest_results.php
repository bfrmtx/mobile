<?php

/** * @file str_selftest_results.php
 * @brief This file defines the STR (self test result) class, which represents a self test result in the system.
 */

require_once __DIR__ . '/../../config.php';      //!< make your SESSION and path.
require_once SYSTEM_DIR . 'str/str_base.php'; //!< this is the base class for self test results, which represents a base class for self test results in the system. These are ALL key value pairs tables! All read only

class str_selftest_results {
  protected database $db; //!< database connection for self test results
  protected array $table_names = ['gps', 'con', 'main_backplane', 'cpu', 'led']; //!< table names for self test results
  protected array $str_bases = []; //!< array of str_base objects for each table name

  public function __construct(string $db_file_) {
    $this->db = new database($db_file_);
    for ($i = 0; $i < NSLOTS; $i++) {
      $this->table_names[] = 'slot' . strval($i);
    }
    // read all tables with their key value pairs.
    foreach ($this->table_names as $table_name) {
      $this->db->set_table($table_name);
      try {
        $kv = $this->db->read_key_value_table();
      } catch (RuntimeException $e) {
        $kv = [];
      }
      $this->str_bases[$table_name] = new str_base($table_name, $kv);
    }
  }
  public function __destruct() {
    // Destructor code if needed
  }

  public function get_str_bases(): array {
    return $this->str_bases;
  }

  public function st_results_html(): string {
    $html = '';
    foreach ($this->str_bases as $str_base) {
      $html .= '<div class="w3-half w3-container w3-margin-bottom">';
      $html .= '<h3 class="w3-text-deep-orange">' . $str_base->get_board_type() . '</h3>';
      $html .= $str_base->get_error_code();
      $html .= '<br>';
      $html .= $str_base->get_severity();
      $html .= '<br>';
      $html .= $str_base->get_message();
      $html .= '<br>';
      $html .= $str_base->get_max_amplitude();
      $html .= '<br>';
      $html .= $str_base->get_noise_check();
      $html .= '</div>';
    }
    return $html;
  }
}
