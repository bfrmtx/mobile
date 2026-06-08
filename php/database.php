<?php

/**
 * @file database.php
 * @brief SQLite-only database access classes and trait.
 */
require_once __DIR__ . '/../config.php'; //!< make your SESSION and path.
require_once TRAITS_DIR . 'global_vars.php';
class database {
  use global_vars; //!< global_vars for drop downs and selectors
  protected string $db_file = '';          //!< path to the SQLite database file
  protected ?PDO $pdo = null;              //!< PDO instance for database connection
  protected int $busy_timeout_s = 2;       //!< max wait on locked SQLite operations
  protected string $table = '';            //!< quoted table name for use in queries

  public function __construct(string $db_file_, bool $create_if_missing_ = false) {

    // we use just the name, NOT a path.
    // default is /home/database/$db_file_ for the ADU system.
    // on MAC we should get $candidate = '/Users/' . $user . '/adu_database';
    // on Linux we should get $candidate = '/home/' . $user . '/adu_database';
    if ($db_file_ !== '' && $db_file_[0] !== DIRECTORY_SEPARATOR) {
      $candidate = DB_DIR . ltrim($db_file_, DIRECTORY_SEPARATOR);
      if (file_exists($candidate) || $create_if_missing_) {
        $this->db_file = $candidate;
      } else {
        throw new RuntimeException('Database file not found: ' . $candidate);
      }
    } else {
      throw new InvalidArgumentException('Invalid database file path: ' . $db_file_);
    }
    try {
      // create a new PDO instance with a file-based SQLite connection
      // options:
      // null username 
      // null password
      // set error mode to exceptions and a reasonable timeout for busy locks
      $this->pdo = new PDO(
        'sqlite:' . $this->db_file,
        null,
        null,
        [
          PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
          PDO::ATTR_TIMEOUT => $this->busy_timeout_s, // set the timeout for busy locks in seconds
        ]
      );

      // Align SQLite lock wait with the configured timeout in milliseconds.
      $this->pdo->exec('PRAGMA busy_timeout = ' . intval($this->busy_timeout_s * 1000)); // SQLite expects busy_timeout in milliseconds
    } catch (PDOException $e) {
      throw new RuntimeException('Database connection failed: ' . $e->getMessage(), 0, $e);
    }
  }

  public function __destruct() {
    // Close the database connection
    $this->pdo = null;
  }

  public function close() {
    $this->pdo = null;
    $this->table = '';
  }

  public function get_pdo(): ?PDO {
    return $this->pdo;
  }

  /**
   * @function _set_table
   * @brief Set the table name for this database instance, properly quoted for use in queries
   * @param string $table_ The name of the table to set (unquoted)
   * @detail for this embedded system you take care to use valid names with spaces and keywords and ";"
   */
  public function set_table(string $table_) {
    $this->table = $table_;
  }

  /**
   * @function read_key_value_table
   * @brief Read all key-value pairs from the current table and return as an associative array
   * @return array An associative array of key-value pairs from the table
   * @details the class will analyze the return values
   * @throws RuntimeException if the database connection is not established or the table name is not set
   */
  public function read_key_value_table() {
    if ($this->pdo === null) {
      throw new RuntimeException('Database connection is not established.');
    }
    if ($this->table === '') {
      throw new RuntimeException('Table name is not set.');
    }
    try {
      $stmt = $this->pdo->query('SELECT key, value FROM ' . $this->table);
      $kv = [];
      while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $kv[$row['key']] = $row['value'];
      }
      return $kv;
    } catch (PDOException $e) {
      throw new RuntimeException('Failed to read from database: ' . $e->getMessage(), 0, $e);
    }
  }

  public function write_key_value_table(array $kv) {
    if ($this->pdo === null) {
      throw new RuntimeException('Database connection is not established.');
    }
    if ($this->table === '') {
      throw new RuntimeException('Table name is not set.');
    }
    try {
      // Use a transaction for batch insert/update
      $this->pdo->beginTransaction();
      $stmt = $this->pdo->prepare('REPLACE INTO ' . $this->table . ' (key, value) VALUES (:key, :value)');
      foreach ($kv as $key => $value) {
        $stmt->execute([':key' => $key, ':value' => $value]);
      }
      $this->pdo->commit();
    } catch (PDOException $e) {
      $this->pdo->rollBack();
      throw new RuntimeException('Failed to write to database: ' . $e->getMessage(), 0, $e);
    }
  }

  /**
   * @brief Update existing key-value rows only; never insert new rows.
   * @details Intended for hwConfig tables where row ids must stay constant.
   */
  public function update_key_value_table(array $kv): void {
    if ($this->pdo === null) {
      throw new RuntimeException('Database connection is not established.');
    }
    if ($this->table === '') {
      throw new RuntimeException('Table name is not set.');
    }
    try {
      $this->pdo->beginTransaction();
      $stmt = $this->pdo->prepare('UPDATE ' . $this->table . ' SET value = :value WHERE key = :key');
      foreach ($kv as $key => $value) {
        $stmt->execute([':key' => $key, ':value' => $value]);
      }
      $this->pdo->commit();
    } catch (PDOException $e) {
      $this->pdo->rollBack();
      throw new RuntimeException('Failed to update database rows: ' . $e->getMessage(), 0, $e);
    }
  }

  /**
   * create job or jobs table
   */
  public function create_job_table(bool $for_job_list = false) {
    if ($this->pdo === null) {
      throw new RuntimeException('Database connection is not established.');
    }
    if ($this->table === '') {
      throw new RuntimeException('Table name is not set.');
    }
    try {
      $primary_key_sql = 'id INTEGER PRIMARY KEY CHECK (id = 1)';
      if ($this->table === 'jobs') {
        $primary_key_sql = '"id" INTEGER PRIMARY KEY AUTOINCREMENT';
      }
      $extra_columns_sql = '';
      if ($for_job_list) {
        $extra_columns_sql = ',
        "slots_on" TEXT,
        "started" INTEGER';
      }
      $this->pdo->exec('CREATE TABLE IF NOT EXISTS ' . $this->table . ' (
        ' . $primary_key_sql . ',
        "start_date"	TEXT,
        "start_time"	TEXT,
        "duration"	INTEGER,
        "sampling_rate"	INTEGER,
        "digital_filter"	INTEGER,
        "split_main"	INTEGER DEFAULT 0,
        "cal_mode"	TEXT DEFAULT "off",
        "channel_types"	TEXT,
        "choppers"	TEXT,
        "gains"	TEXT,
        "dipole_lengths" TEXT,
        "use_atss" INTEGER,
        "copy_to_usb" INTEGER,
        "sub_cycle" INTEGER,
        "sub_duration" INTEGER,
        "sub_filter" INTEGER,
        "split_sub" INTEGER DEFAULT 0,
        "power_off_limit" REAL,
        "station_id" STRING' . $extra_columns_sql . '
      )');
    } catch (PDOException $e) {
      throw new RuntimeException('Failed to create table: ' . $e->getMessage(), 0, $e);
    }
  }

  public function update_job_table(array $kv) {
    if ($this->pdo === null) {
      throw new RuntimeException('Database connection is not established.');
    }
    if ($this->table === '') {
      throw new RuntimeException('Table name is not set.');
    }
    try {
      // Use REPLACE to insert if row doesn't exist, or update if it does
      $kv['id'] = 1; // Ensure id is set for the job table
      $columns = array_keys($kv);
      $placeholders = array_map(function ($col) {
        return ':' . $col;
      }, $columns);
      $column_list = implode(', ', array_map(function ($col) {
        return '"' . $col . '"';
      }, $columns));
      $sql = 'REPLACE INTO ' . $this->table . ' (' . $column_list . ') VALUES (' . implode(', ', $placeholders) . ')';
      $stmt = $this->pdo->prepare($sql);
      $params = [];
      foreach ($kv as $key => $value) {
        $params[':' . $key] = $value;
      }
      $stmt->execute($params);
    } catch (PDOException $e) {
      throw new RuntimeException('Failed to update job table: ' . $e->getMessage(), 0, $e);
    }
  }

  public function read_job_table(int $id = 1) {
    if ($this->pdo === null) {
      throw new RuntimeException('Database connection is not established.');
    }
    if ($this->table === '') {
      throw new RuntimeException('Table name is not set.');
    }
    try {
      $stmt = $this->pdo->prepare('SELECT * FROM ' . $this->table . ' WHERE id = :id');
      $stmt->execute([':id' => $id]);
      $row = $stmt->fetch(PDO::FETCH_ASSOC);
      return $row ?: [];
    } catch (PDOException $e) {
      throw new RuntimeException('Failed to read job table: ' . $e->getMessage(), 0, $e);
    }
  }

  /**
   * @function empty_table
   * @brief Delete all rows from the current table; we don't want to delete the table itself
   * @throws RuntimeException if the database connection is not established or the table name is not set
   */
  public function empty_table() {
    if ($this->pdo === null) {
      throw new RuntimeException('Database connection is not established.');
    }
    if ($this->table === '') {
      throw new RuntimeException('Table name is not set.');
    }
    try {
      $this->pdo->exec('DELETE FROM ' . $this->table);
    } catch (PDOException $e) {
      throw new RuntimeException('Failed to empty table: ' . $e->getMessage(), 0, $e);
    }
  }

  /**
   * @brief INSERT a new row into an AUTOINCREMENT table (e.g. jobs). id is not supplied.
   */
  public function insert_jobs_row(array $kv): int {
    if ($this->pdo === null) {
      throw new RuntimeException('Database connection is not established.');
    }
    if ($this->table === '') {
      throw new RuntimeException('Table name is not set.');
    }
    try {
      unset($kv['id']); // never supply id for AUTOINCREMENT
      $columns = array_keys($kv);
      $column_list = implode(', ', array_map(fn($c) => '"' . $c . '"', $columns));
      $placeholders = implode(', ', array_map(fn($c) => ':' . $c, $columns));
      $sql = 'INSERT INTO ' . $this->table . ' (' . $column_list . ') VALUES (' . $placeholders . ')';
      $stmt = $this->pdo->prepare($sql);
      foreach ($kv as $key => $value) {
        $stmt->bindValue(':' . $key, $value);
      }
      $stmt->execute();
      return (int) $this->pdo->lastInsertId();
    } catch (PDOException $e) {
      throw new RuntimeException('Failed to insert row: ' . $e->getMessage(), 0, $e);
    }
  }

  /**
   * @brief Return all rows ordered by id DESC.
   */
  public function read_all_rows(): array {
    if ($this->pdo === null) {
      throw new RuntimeException('Database connection is not established.');
    }
    if ($this->table === '') {
      throw new RuntimeException('Table name is not set.');
    }
    try {
      $stmt = $this->pdo->query('SELECT * FROM ' . $this->table . ' ORDER BY id DESC');
      return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
      throw new RuntimeException('Failed to read rows: ' . $e->getMessage(), 0, $e);
    }
  }

  /**
   * @brief Delete a single row by id.
   */
  public function delete_row_by_id(int $id): void {
    if ($this->pdo === null) {
      throw new RuntimeException('Database connection is not established.');
    }
    if ($this->table === '') {
      throw new RuntimeException('Table name is not set.');
    }
    try {
      $stmt = $this->pdo->prepare('DELETE FROM ' . $this->table . ' WHERE id = :id');
      $stmt->execute([':id' => $id]);
    } catch (PDOException $e) {
      throw new RuntimeException('Failed to delete row: ' . $e->getMessage(), 0, $e);
    }
  }

  public function create_key_value_table() {
    if ($this->pdo === null) {
      throw new RuntimeException('Database connection is not established.');
    }
    if ($this->table === '') {
      throw new RuntimeException('Table name is not set.');
    }
    try {
      $this->pdo->exec('CREATE TABLE IF NOT EXISTS ' . $this->table . ' (
          "id" INTEGER PRIMARY KEY AUTOINCREMENT,
          "key" TEXT,
          "value" TEXT
        )');
    } catch (PDOException $e) {
      throw new RuntimeException('Failed to create table: ' . $e->getMessage(), 0, $e);
    }
  }
} // end database class