<?php

/**
 * @file database.php
 * @brief SQLite-only database access classes and trait.
 */
require_once __DIR__ . '/../config.php'; //!< make your SESSION and path.
require_once TRAITS_DIR . 'global_vars.php';

class database extends SQLite3 {
  use global_vars;                         //!< global_vars for drop downs and selectors
  protected string $db_file = '';          //!< path to the SQLite database file
  protected string $table = '';            //!< quoted table name for use in queries

  /**
   * @brief Constructor for the database class.
   * @param string $db_file_ The path to the SQLite database file.
   * @details we use just the name, NOT a path.
   * @details default is /home/database/$db_file_ for the ADU system.
   * @details on MAC we should get $candidate = '/Users/' . $user . '/adu_database';
   * @details on Linux we should get $candidate = '/home/' . $user . '/adu_database';
   * @param bool $create_if_missing_ Whether to create the database file if it does not exist.
   * @throws RuntimeException If the database file is not found and creation is not allowed.
   * @throws InvalidArgumentException If the provided database file path is invalid.
   */
  public function __construct(string $db_file_, bool $create_if_missing_ = false) {

    // check if empty
    if (empty($db_file_)) {
      throw new InvalidArgumentException('Database file path cannot be empty.');
    }
    // Resolve DB path; relative names are rooted in DB_DIR.
    $db_file_path = ($db_file_[0] === DIRECTORY_SEPARATOR)
      ? $db_file_
      : DB_DIR . ltrim($db_file_, DIRECTORY_SEPARATOR);

    if (file_exists($db_file_path)) {
      if (!is_file($db_file_path)) {
        throw new RuntimeException('Database path is not a file: ' . $db_file_path);
      }
    } else {
      if (!$create_if_missing_) {
        throw new RuntimeException('Database file not found: ' . $db_file_path);
      }
      $dir = dirname($db_file_path);
      if (!is_dir($dir)) {
        throw new RuntimeException('Database directory not found: ' . $dir);
      }
    }
    $this->db_file = $db_file_path;

    // sqlite3 will create the database file if it does not exist, so we don't need to handle that here.
    try {
      parent::__construct($this->db_file);
      $this->enableExceptions(true);              // Enable exceptions for error handling
      $this->busyTimeout(DB_TIMEOUT * 1000);      // Set busy timeout in milliseconds
    } catch (Exception $e) {
      throw new RuntimeException('Failed to open database: ' . $e->getMessage());
    }
  }

  public function __destruct() {
    $this->close();
    $this->db_file = '';
    $this->table = '';
  }

  /**
   * @brief Set the table name for the database queries.
   * @param string $table_name The name of the table.
   * @details The table name will be quoted to prevent SQL injection.
   */
  public function set_table(string $table_name): void {
    $this->table = '"' . str_replace('"', '""', $table_name) . '"'; // Quote the table name to prevent SQL injection
  }

  /**
   * @brief Get the current table name for the database queries.
   * @return string The current table name.
   */
  public function get_table(): string {
    return $this->table;
  }

  /**
   * @brief Get the path to the SQLite database file.
   * @return string The path to the database file.
   */
  public function get_db_file(): string {
    return $this->db_file;
  }
  /**
   * @brief Read all key-value pairs from the specified table.
   * @return array An associative array of key-value pairs from the table.
   * @throws RuntimeException If the database is not open or the table name is not set.
   * @throws RuntimeException If the query fails to execute.
   */
  public function read_key_value_table(): array {
    // check if open
    if (!$this->db_file) {
      throw new RuntimeException('Database is not open.');
    }
    // check if table is set
    if (!$this->table) {
      throw new RuntimeException('Table name is not set.');
    }
    $result = $this->query('SELECT * FROM ' . $this->table);
    if (!$result) {
      throw new RuntimeException('Failed to read from table: ' . $this->lastErrorMsg());
    }
    $kv = []; // Initialize an empty key value array to hold the key-value pairs
    while ($row = $result->fetchArray(SQLITE3_ASSOC)) {
      $kv[$row['key']] = $row['value'];
    }
    return $kv;
  }

  /**
   * @brief Write key-value pairs to the specified table, replacing existing entries if the key already exists.
   * @param array $kv An associative array of key-value pairs to write to the table.
   * @throws RuntimeException If the database is not open or the table name is not set.
   * @throws RuntimeException If the query fails to execute.
   */
  public function write_key_value_table(array $kv): void {
    // check if open
    if (!$this->db_file) {
      throw new RuntimeException('Database is not open.');
    }
    // check if table is set
    if (!$this->table) {
      throw new RuntimeException('Table name is not set.');
    }
    $this->exec('BEGIN TRANSACTION');
    foreach ($kv as $key => $value) {
      $stmt = $this->prepare('INSERT OR REPLACE INTO ' . $this->table . ' (key, value) VALUES (:key, :value)');
      $stmt->bindValue(':key', $key, SQLITE3_TEXT);
      $stmt->bindValue(':value', $value, SQLITE3_TEXT);
      if (!$stmt->execute()) {
        throw new RuntimeException('Failed to write to table: ' . $this->lastErrorMsg());
      }
    }
    $this->exec('COMMIT');
  }

  /**
   * @brief Update existing key-value pairs in the specified table.
   * @param array $kv An associative array of key-value pairs to update in the table.
   * @throws RuntimeException If the database is not open or the table name is not set.
   * @throws RuntimeException If the query fails to execute.
   * @details This method will only update existing entries; it will not insert new entries. Tables are small
   */
  public function update_key_value_table(array $kv): void {
    // check if open
    if (!$this->db_file) {
      throw new RuntimeException('Database is not open.');
    }
    // check if table is set
    if (!$this->table) {
      throw new RuntimeException('Table name is not set.');
    }
    $this->exec('BEGIN TRANSACTION');
    foreach ($kv as $key => $value) {
      $stmt = $this->prepare('UPDATE ' . $this->table . ' SET value = :value WHERE key = :key');
      $stmt->bindValue(':key', $key, SQLITE3_TEXT);
      $stmt->bindValue(':value', $value, SQLITE3_TEXT);
      if (!$stmt->execute()) {
        throw new RuntimeException('Failed to update table: ' . $this->lastErrorMsg());
      }
    }
    $this->exec('COMMIT');
  }

  public function create_key_value_table(): void {
    // check if open
    if (!$this->db_file) {
      throw new RuntimeException('Database is not open.');
    }
    // check if table is set
    if (!$this->table) {
      throw new RuntimeException('Table name is not set.');
    }
    $sql = 'CREATE TABLE IF NOT EXISTS ' . $this->table . ' (id INTEGER PRIMARY KEY AUTOINCREMENT, key TEXT UNIQUE, value TEXT)';
    if (!$this->exec($sql)) {
      throw new RuntimeException('Failed to create key-value table: ' . $this->lastErrorMsg());
    }
  }

  /**
   * @brief Delete a row from the specified table by ID.
   * @param int $id The ID of the row to delete.
   * @throws RuntimeException If the database is not open or the table name is not set.
   * @throws RuntimeException If the query fails to execute.
   */
  public function delete_row_by_id(int $id): void {
    // check if open
    if (!$this->db_file) {
      throw new RuntimeException('Database is not open.');
    }
    // check if table is set
    if (!$this->table) {
      throw new RuntimeException('Table name is not set.');
    }
    $stmt = $this->prepare('DELETE FROM ' . $this->table . ' WHERE id = :id');
    $stmt->bindValue(':id', $id, SQLITE3_INTEGER);
    if (!$stmt->execute()) {
      throw new RuntimeException('Failed to delete row from table: ' . $this->lastErrorMsg());
    }
  }

  /**
   * @brief Delete all entries from the specified table.
   * @throws RuntimeException If the database is not open or the table name is not set.
   * @throws RuntimeException If the query fails to execute.
   * @details This method will remove all entries from the table, effectively emptying it.
   */
  public function empty_table(bool $vacuum = false): void {
    // check if open
    if (!$this->db_file) {
      throw new RuntimeException('Database is not open.');
    }
    // check if table is set
    if (!$this->table) {
      throw new RuntimeException('Table name is not set.');
    }
    $this->exec('DELETE FROM ' . $this->table);
    if ($vacuum) {
      $this->exec('VACUUM');
    }
  }

  /**
   * @brief Create a table from an SQL file.
   * @param string $sql_file The path to the SQL file containing the table creation statement.
   * @throws RuntimeException If the SQL file cannot be read or if the SQL execution fails.
   * @details The SQL file should contain a valid SQL statement to create the table
   * @details during runtime you should use file like INIT_DIR . 'filename.sql'. or 
   * example  INIT_DIR . 'systemStatus' . DIRECTORY_SEPARATOR . 'adu.sql'
   * example  INIT_DIR . 'job' . DIRECTORY_SEPARATOR . 'job.sql'
   */
  public function create_table_from_SQL_file($sql_file): void {
    $sql = file_get_contents($sql_file);
    if ($sql === false) {
      throw new RuntimeException('Failed to read SQL file: ' . $sql_file);
    }

    if (!$this->exec($sql)) {
      throw new RuntimeException('Failed to execute SQL from file: ' . $this->lastErrorMsg());
    }

    $json_file = preg_replace('/\.sql$/', '.json', $sql_file);
    if (file_exists($json_file)) {
      $json_data = file_get_contents($json_file);
      if ($json_data === false) {
        throw new RuntimeException('Failed to read JSON file: ' . $json_file);
      }
    } else {
      return; // No JSON file to read, so we just return after creating the table
    }
    // the table is ALWAYS id, key (text), value (text), so we can simply put the key-value pairs into the table
    $kv = json_decode($json_data, true);
    if (!is_array($kv)) {
      throw new RuntimeException('Failed to decode JSON data from file: ' . $json_file);
    }
    $this->set_table(basename($sql_file, '.sql'));
    $this->write_key_value_table($kv);
  }

  public function update_job_table(array $kv, int $id = 1): void {
    // check if open
    if (!$this->db_file) {
      throw new RuntimeException('Database is not open.');
    }
    // check if table is set
    if (!$this->table) {
      throw new RuntimeException('Table name is not set.');
    }
    $this->exec('BEGIN TRANSACTION');
    foreach ($kv as $key => $value) {
      $stmt = $this->prepare('UPDATE ' . $this->table . ' SET ' . $key . ' = :value WHERE id = :id');
      $stmt->bindValue(':id', $id, SQLITE3_INTEGER);
      $stmt->bindValue(':value', $value, SQLITE3_TEXT);
      if (!$stmt->execute()) {
        throw new RuntimeException('Failed to update table: ' . $this->lastErrorMsg());
      }
    }
    $this->exec('COMMIT');
  }

  public function insert_jobs_row(array $kv): int {
    // check if open
    if (!$this->db_file) {
      throw new RuntimeException('Database is not open.');
    }
    // check if table is set
    if (!$this->table) {
      throw new RuntimeException('Table name is not set.');
    }
    unset($kv['id']); // remove the id from the array, as it will be auto-incremented
    $columns = implode(', ', array_keys($kv));
    $placeholders = ':' . implode(', :', array_keys($kv));
    $stmt = $this->prepare('INSERT INTO ' . $this->table . ' (' . $columns . ') VALUES (' . $placeholders . ')');
    foreach ($kv as $key => $value) {
      $stmt->bindValue(':' . $key, $value, SQLITE3_TEXT);
    }
    if (!$stmt->execute()) {
      throw new RuntimeException('Failed to insert into table: ' . $this->lastErrorMsg());
    }
    return $this->lastInsertRowID();
  }

  function check_start_date_time_columns(): bool {
    // check if open
    if (!$this->db_file) {
      throw new RuntimeException('Database is not open.');
    }
    // check if table is set
    if (!$this->table) {
      throw new RuntimeException('Table name is not set.');
    }
    $row = $this->querySingle('SELECT * FROM ' . $this->table . ' LIMIT 1', true);
    if (!$row) {
      throw new RuntimeException('Failed to read from table: ' . $this->lastErrorMsg());
    }
    return isset($row['start_date']) && isset($row['start_time']);
  }

  /**
   * @brief Get rows from the specified table. IMPORTANT: use check_start_date_time_columns() if you read other tables than job or jobs! ONLY these tables have guranteed start_date and start_time columns.
   * @param int $id If given (not -1), fetch only the row with this id.
   * @param bool $order_by_date Whether to order the results by start_date and start_time (true) or not (false). Ignored if $id is given.
   * @return array If $id is given: a single associative array for that row (or [] if not found). Otherwise: an array of associative arrays, one per row.
   * @throws RuntimeException If the database is not open or the table name is not set.
   * @throws RuntimeException If the query fails to execute.
   */
  public function get_rows(int $id = -1, bool $order_by_date = false): array {
    // check if open
    if (!$this->db_file) {
      throw new RuntimeException('Database is not open.');
    }
    // check if table is set
    if (!$this->table) {
      throw new RuntimeException('Table name is not set.');
    }
    if ($id != -1) {
      $stmt = $this->prepare('SELECT * FROM ' . $this->table . ' WHERE id = :id');
      $stmt->bindValue(':id', $id, SQLITE3_INTEGER);
      $result = $stmt->execute();
      if (!$result) {
        throw new RuntimeException('Failed to read from table: ' . $this->lastErrorMsg());
      }
      $row = $result->fetchArray(SQLITE3_ASSOC);
      return is_array($row) ? $row : [];
    }
    $query = 'SELECT * FROM ' . $this->table;
    if ($order_by_date) {
      $query .= ' ORDER BY start_date ASC, start_time ASC';
    }
    $result = $this->query($query);
    if (!$result) {
      throw new RuntimeException('Failed to read from table: ' . $this->lastErrorMsg());
    }
    $rows = [];
    while ($row = $result->fetchArray(SQLITE3_ASSOC)) {
      $rows[] = $row;
    }
    return $rows;
  }


  /**
   * @brief Reorder jobs in the specified table based on start_date and start_time.
   * @throws RuntimeException If the database is not open or the table name is not set.
   * @throws RuntimeException If the query fails to execute.
   * @details This method will reorder the jobs in the table based on their start_date and start_time. It assumes that the table has columns named 'start_date' and 'start_time' for ordering. If these columns are not present, an exception will be thrown.
   * @details Nothing is done if the table is ordered already (no re-write needed)
   */
  public function reorder_jobs(bool $check_start_date_time_columns = false) {
    if (!$this->db_file) {
      throw new RuntimeException('Database is not open.');
    }
    if (!$this->table) {
      throw new RuntimeException('Table name is not set.');
    }
    try {
      if ($check_start_date_time_columns && !$this->check_start_date_time_columns()) {
        throw new RuntimeException('Table does not have start_date and start_time columns.');
      }
      // Read all rows (jobs) from db
      $job_rows = $this->get_rows(-1, false);
      $job_rows_ordered = $this->get_rows(-1, true);
      // Reorder logic here
      // Nothing is done if the table is already ordered correctly
      if ($job_rows !== $job_rows_ordered) {
        // If the order is different, we need to reorder the jobs in the database
        $this->exec('BEGIN TRANSACTION');
        $this->exec('DELETE FROM ' . $this->table);
        foreach ($job_rows_ordered as $row) {
          unset($row['id']); // Remove the id to let it auto-increment
          $columns = implode(', ', array_keys($row));
          $placeholders = ':' . implode(', :', array_keys($row));
          $stmt = $this->prepare('INSERT INTO ' . $this->table . ' (' . $columns . ') VALUES (' . $placeholders . ')');
          foreach ($row as $key => $value) {
            $stmt->bindValue(':' . $key, $value, SQLITE3_TEXT);
          }
          if (!$stmt->execute()) {
            throw new RuntimeException('Failed to insert into table: ' . $this->lastErrorMsg());
          }
        }
        $this->exec('COMMIT');
      }
    } catch (RuntimeException $e) {
      throw new RuntimeException('Failed to reorder jobs: ' . $e->getMessage());
    }
    // now we make sure that the jobs have a pause of PREP_TIME between them

  }
}
