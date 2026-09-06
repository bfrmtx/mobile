<?php

/**
 * @file mcp_jobs.php
 * @brief MCP Job history page: list all recorded jobs with Delete action ONLY.
 * this class is used to DISPLAY database mcpdb.db, table jobs
 * cols:
 * id : int
 * start: datetime, YYYY-MM-DD HH:MM:SS
 * stop: datetime, YYYY-MM-DD HH:MM:SS
 * started: int, 0 or 1
 * job: text, contains the XML job data, NOT TO BE USED OR DISPLAYED, just for reference
 * 
 * @note This class is used to manage the MCP job history, which is a separate database from the main job history. We ONLY DELETE here.
 * 
 */
require_once __DIR__ . '/../config.php';
require_once PHP_DIR . 'php_functions.php';

class mcp_jobs {
  private $db_file;
  private $table_name;
  private database $db;

  public function __construct($db_file = 'mcpdb.db', $table_name = 'jobs') {
    $this->db_file = $db_file;
    $this->table_name = $table_name;
    $this->db = new database($this->db_file);
    $this->db->set_table($this->table_name);
    // no create here, it is not my table!
  }

  /**
   * @brief Run a SELECT query with optional named parameters.
   * @param string $sql
   * @param array $params
   * @return array
   */
  private function select_rows(string $sql, array $params = []): array {
    $stmt = $this->db->prepare($sql);
    if (!$stmt) {
      throw new RuntimeException('Failed to prepare statement: ' . $this->db->lastErrorMsg());
    }

    foreach ($params as $name => $value) {
      $type = SQLITE3_TEXT;
      if (is_int($value)) {
        $type = SQLITE3_INTEGER;
      } elseif (is_float($value)) {
        $type = SQLITE3_FLOAT;
      } elseif ($value === null) {
        $type = SQLITE3_NULL;
      }
      $stmt->bindValue((string) $name, $value, $type);
    }

    $result = $stmt->execute();
    if (!$result) {
      throw new RuntimeException('Failed to execute statement: ' . $this->db->lastErrorMsg());
    }

    $rows = [];
    while ($row = $result->fetchArray(SQLITE3_ASSOC)) {
      $rows[] = $row;
    }
    return $rows;
  }

  /**
   * @brief Get the ID of a job row by its start datetime.
   * @param string $start  The start datetime (YYYY-MM-DD HH:MM:SS).
   * @return int|null  The row ID, or null if not found.
   */
  public function get_id_by_start($start) {
    $sql = 'SELECT id FROM ' . $this->db->get_table() . ' WHERE start = :start LIMIT 1';
    $params = [':start' => $start];

    try {
      $result = $this->select_rows($sql, $params);
    } catch (Throwable $e) {
      return null;
    }

    if ($result && count($result) > 0) {
      return intval($result[0]['id']);
    }
    return null;
  }

  /**
   * @brief Delete one row from the jobs table by its ID.
   * @param int $id  The row ID to delete.
   */
  public function delete_row_by_id(int $id): void {
    $this->db->delete_row_by_id($id);
  }
  public function empty_table(bool $vacuum = false): void {
    $this->db->empty_table($vacuum);
  }

  /**
   * @brief Get all start datetimes as an array of id, start_date and start_time.
   * @return array  An array of ids, start date and start times (YYYY-MM-DD) (HH:MM:SS), newest first.
   */
  public function get_all_id_start_as_start_date_and_start_time(): array {
    $sql = 'SELECT id, start FROM ' . $this->db->get_table() . ' ORDER BY start DESC';
    try {
      $result = $this->select_rows($sql);
    } catch (Throwable $e) {
      return [];
    }
    // now convert to array of start_date and start_time
    $start_array = [];
    foreach ($result as $row) {
      $start = $row['start'];
      $start_date = substr(trim($start), 0, 10); // YYYY-MM-DD
      $start_time = substr(trim($start), 11, 8); // HH:MM:SS
      $start_array[] = ['id' => intval($row['id']), 'start_date' => $start_date, 'start_time' => $start_time];
    }
    return $start_array;
  }

  public function get_row_by_id(int $id): ?array {
    $sql = 'SELECT * FROM ' . $this->db->get_table() . ' WHERE id = :id LIMIT 1';
    $params = [':id' => $id];

    try {
      $result = $this->select_rows($sql, $params);
    } catch (Throwable $e) {
      return null;
    }

    if ($result && count($result) > 0) {
      return $result[0];
    }
    return null;
  }

  public function get_all_rows(): array {
    $sql = 'SELECT * FROM ' . $this->db->get_table() . ' ORDER BY start DESC';
    try {
      return $this->select_rows($sql);
    } catch (Throwable $e) {
      return [];
    }
  }
}
