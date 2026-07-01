<?php
require_once __DIR__ . '/../../config.php'; //!< this will set up the autoload and the DB_DIR constant and so on.

/**
 * @file create_system.php
 * @brief This script creates the SQLite databases for the ADU-11e if n_slots == 8, otherwise for the ADU-10e if n_slots == 5. 
 */

$n_slots = 8; //!< the number of slots to be created a slot0, slot1, ..., slot7

/**
 * @param mixed $value
 * @return string
 */
function scalar_to_string($value) {
  if (is_array($value)) {
    return implode(', ', array_map(function ($item) {
      if (is_array($item) || is_object($item)) {
        return json_encode($item);
      }
      if ($item === null) {
        return '';
      }
      return (string)$item;
    }, $value));
  }
  if (is_object($value)) {
    return json_encode($value);
  }
  if ($value === null) {
    return '';
  }
  return (string)$value;
}

/**
 * @param array<mixed> $arr
 * @return bool
 */
function is_list_array($arr) {
  return array_keys($arr) === range(0, count($arr) - 1);
}

/**
 * @param SQLite3 $db
 * @param string $table_name
 * @return array<int, string>
 */
function get_table_columns($db, $table_name) {
  $columns = array();
  $query = $db->query('PRAGMA table_info("' . SQLite3::escapeString($table_name) . '")');
  while ($query && ($row = $query->fetchArray(SQLITE3_ASSOC))) {
    $columns[] = (string)$row['name'];
  }
  return $columns;
}

/**
 * @param SQLite3 $db
 * @param string $table_name
 * @param array<string, mixed> $row
 * @param array<int, string>|null $allowed_columns
 * @return void
 */
function insert_row($db, $table_name, $row, $allowed_columns = null) {
  if (!is_array($row) || count($row) === 0) {
    return;
  }

  $filtered = array();
  foreach ($row as $key => $value) {
    if (!is_string($key)) {
      continue;
    }
    if (is_array($allowed_columns) && !in_array($key, $allowed_columns, true)) {
      continue;
    }
    if ($key === 'id' && $value === null) {
      continue;
    }
    $filtered[$key] = $value;
  }

  if (count($filtered) === 0) {
    return;
  }

  $columns = array_keys($filtered);
  $column_sql = '"' . implode('", "', array_map(function ($col) {
    return SQLite3::escapeString($col);
  }, $columns)) . '"';
  $placeholders = implode(', ', array_fill(0, count($columns), '?'));

  $stmt = $db->prepare('INSERT INTO "' . SQLite3::escapeString($table_name) . '" (' . $column_sql . ') VALUES (' . $placeholders . ')');
  if ($stmt === false) {
    echo "Failed to prepare insert for table: " . $table_name . "\n";
    return;
  }

  $index = 1;
  foreach ($columns as $col) {
    $stmt->bindValue($index, scalar_to_string($filtered[$col]), SQLITE3_TEXT);
    $index++;
  }
  $stmt->execute();
}

/**
 * @param SQLite3 $db
 * @param string $table_name
 * @param string $key
 * @param mixed $value
 * @return void
 */
function upsert_key_value(SQLite3 $db, string $table_name, string $key, $value): void {
  $safe_table = SQLite3::escapeString($table_name);
  $safe_key = SQLite3::escapeString($key);
  $safe_value = SQLite3::escapeString(scalar_to_string($value));

  $exists = $db->querySingle('SELECT COUNT(*) FROM "' . $safe_table . '" WHERE key = "' . $safe_key . '"');
  if ((int) $exists > 0) {
    $db->exec('UPDATE "' . $safe_table . '" SET value = "' . $safe_value . '" WHERE key = "' . $safe_key . '"');
  } else {
    $db->exec('INSERT INTO "' . $safe_table . '" (key, value) VALUES ("' . $safe_key . '", "' . $safe_value . '")');
  }
}

/**
 * @param SQLite3 $db
 * @param int $n_slots
 * @return void
 * @brief Populate safe default values in the hwConfig database.
 */
function set_hwconfig_defaults(SQLite3 $db, int $n_slots): void {
  $i = 1;
  foreach (array('adu', 'con', 'gps', 'led') as $table_name) {
    $i++;
    if (count(get_table_columns($db, $table_name)) > 0) {
      upsert_key_value($db, $table_name, 'serial', $i); //!< set a default serial number for the ADU, CON, GPS and LED tables for testing purposes
    }
  }

  for ($slot_index = 0; $slot_index < $n_slots; ++$slot_index) {
    $table_name = 'slot' . $slot_index;
    if (count(get_table_columns($db, $table_name)) === 0) {
      continue;
    }
    if ($slot_index == 0) {
      $default_sensor_type = 'EFP06';
      upsert_key_value($db, $table_name, 'sensor_serial', 12);
    } else if ($slot_index == 1) {
      $default_sensor_type = 'EFP06';
      upsert_key_value($db, $table_name, 'sensor_serial', 34);
    } else if ($slot_index >= 5) {
      $default_sensor_type = 'SHFT03e';
      upsert_key_value($db, $table_name, 'sensor_serial', $slot_index);
    } else {
      $default_sensor_type = 'MFS12e';
      upsert_key_value($db, $table_name, 'sensor_serial', $slot_index);
    }
    upsert_key_value($db, $table_name, 'serial', $slot_index);
    upsert_key_value($db, $table_name, 'sensor_type', $default_sensor_type);
  }
}

function set_selftestResult_defaults(SQLite3 $db, int $n_slots): void {
  /*
error_code 0
severity 0
message ok
// slot 0, 1
probe_res
probe_res_gnd_1
probe_res_gnd_2
// end slot 0, 1
lsb
max_amplitude
dc_offset
noise_check
gain

  */
  for ($slot_index = 0; $slot_index < $n_slots; ++$slot_index) {
    $table_name = 'slot' . $slot_index;
    if (count(get_table_columns($db, $table_name)) === 0) {
      continue;
    }
    if ($slot_index == 0) {
      upsert_key_value($db, $table_name, 'probe_res', '900');
      upsert_key_value($db, $table_name, 'probe_res_gnd_1', '910');
      upsert_key_value($db, $table_name, 'probe_res_gnd_2', '990');
      upsert_key_value($db, $table_name, 'dc_offset', '110');
      upsert_key_value($db, $table_name, 'max_amplitude', '110');
      upsert_key_value($db, $table_name, 'noise_check', '0.600');
    } else if ($slot_index == 1) {
      upsert_key_value($db, $table_name, 'probe_res', '1200');
      upsert_key_value($db, $table_name, 'probe_res_gnd_1', '1210');
      upsert_key_value($db, $table_name, 'probe_res_gnd_2', '1240');
      upsert_key_value($db, $table_name, 'dc_offset', '220');
      upsert_key_value($db, $table_name, 'max_amplitude', '210');
      upsert_key_value($db, $table_name, 'noise_check', '0.400');
    }
    upsert_key_value($db, $table_name, 'error_code', 0);
    upsert_key_value($db, $table_name, 'severity', 0);
    upsert_key_value($db, $table_name, 'message', 'ok');
    upsert_key_value($db, $table_name, 'gain', 4); //!< set a default gain value for testing purposes
  }
}
/**
 * @param string $sql
 * @param string $source_table_name
 * @param string $target_table_name
 * @return string
 */
function rename_create_table_sql($sql, $source_table_name, $target_table_name) {
  $pattern = '/^(\s*CREATE\s+TABLE\s+)(?:"' . preg_quote($source_table_name, '/') . '"|' . preg_quote($source_table_name, '/') . ')(\s*\()/i';
  return (string)preg_replace($pattern, '$1"' . $target_table_name . '"$2', $sql, 1);
}

/**
 * @param string $json
 * @return string stripped JSON string without comments
 * @details This function removes both single-line (//) and multi-line ("slash star, star slash") comments from a JSON string while preserving string literals. It handles escaped characters and ensures that comments within strings are not removed.<br>
 * @note these comments ensure that we don't need to write a JSON file and AGAIN a JSON like file, explaining the variables.
 */
function strip_json_comments(string $json): string {
  $out = '';
  $len = strlen($json);
  $inString = false;
  $escape = false;
  $inLineComment = false;
  $inBlockComment = false;
  for ($i = 0; $i < $len; $i++) {
    $c = $json[$i];
    $n = $i + 1 < $len ? $json[$i + 1] : '';
    if ($inLineComment) {
      if ($c === "\n") {
        $inLineComment = false;
        $out .= $c;
      }
      continue;
    }
    if ($inBlockComment) {
      if ($c === '*' && $n === '/') {
        $inBlockComment = false;
        $i++;
      }
      continue;
    }
    if ($inString) {
      $out .= $c;
      if ($escape) {
        $escape = false;
      } elseif ($c === '\\') {
        $escape = true;
      } elseif ($c === '"') {
        $inString = false;
      }
      continue;
    }
    if ($c === '"') {
      $inString = true;
      $out .= $c;
      continue;
    }
    if ($c === '/' && $n === '/') {
      $inLineComment = true;
      $i++;
      continue;
    }
    if ($c === '/' && $n === '*') {
      $inBlockComment = true;
      $i++;
      continue;
    }
    $out .= $c;
  }
  return $out;
}

/**
 * @param SQLite3 $db
 * @param string $table_name
 * @param string $json_file
 * @return void
 */
function insert_json_data($db, $table_name, $json_file) {
  echo "Inserting data from file: " . basename($json_file) . " into table: " . $table_name . "\n";
  $json_content = file_get_contents($json_file);
  // that is important we have "//" comments in the JSON files
  $json_content = strip_json_comments($json_content);
  $data = json_decode($json_content, true); //!< decode the JSON data into an associative array
  if (!is_array($data)) {
    echo "Skipping invalid JSON in: " . basename($json_file) . "\n";
    return;
  }

  $table_columns = get_table_columns($db, $table_name);

  if (is_list_array($data)) {
    foreach ($data as $row) {
      if (is_array($row)) {
        insert_row($db, $table_name, $row, $table_columns);
      }
    }
    return;
  }

  $is_key_value_table = in_array('key', $table_columns, true) && in_array('value', $table_columns, true);
  if ($is_key_value_table) {
    foreach ($data as $key => $value) {
      if (is_string($key)) {
        insert_row($db, $table_name, array('key' => $key, 'value' => $value), $table_columns);
      }
    }
    return;
  }

  insert_row($db, $table_name, $data, $table_columns);
}

// export in tmp of the users home directory, in a folder named "sqlite_db_created"
$export_dir = getenv('HOME') . DIRECTORY_SEPARATOR . "tmp" . DIRECTORY_SEPARATOR . "sqlite_db_created";
if (!file_exists($export_dir)) {
  mkdir($export_dir, 0777, true);
}
// now
// a) all folders names in this directory are database names to be created
// b) all .sql files in these folders are the tables to be created in the respective database
// c) after creation of the table insert (if exists) the "table name.json" file with the data to be inserted into the table
$base_dir = __DIR__; //!< this is the directory where this script is located, which is the root of the databases to be created
$folders = scandir($base_dir);
foreach ($folders as $folder) {
  if ($folder === "." || $folder === "..") {
    continue;
  }
  $folder_path = $base_dir . DIRECTORY_SEPARATOR . $folder;
  if (!is_dir($folder_path)) {
    continue;
  }
  $db_name = $folder; //!< the folder name is the database name
  $db_path = $export_dir . DIRECTORY_SEPARATOR . $db_name . ".db"; //!< the path to the database file to be created
  if (file_exists($db_path)) {
    unlink($db_path);
  }
  $db = new SQLite3($db_path); //!< create a new SQLite3 database
  echo "Creating database: " . $db_name . "\n";
  // now get all .sql files in this folder and execute them to create the tables
  $sql_files = glob($folder_path . DIRECTORY_SEPARATOR . "*.sql");
  foreach ($sql_files as $sql_file) {
    $table_name = basename($sql_file, ".sql");
    $sql = file_get_contents($sql_file); //!< get the SQL commands from the file
    // now check if there is a corresponding JSON file with data to be inserted into the table
    $json_file = str_replace(".sql", ".json", $sql_file); //!< get the corresponding JSON file name

    if ($table_name === 'slot') {
      for ($slot_index = 0; $slot_index < $n_slots; $slot_index++) {
        $slot_table_name = 'slot' . $slot_index;
        echo "Creating table from file: " . basename($sql_file) . " as " . $slot_table_name . "\n";
        $slot_sql = rename_create_table_sql($sql, $table_name, $slot_table_name);
        $db->exec($slot_sql); //!< execute the SQL commands to create the table

        if (file_exists($json_file)) {
          insert_json_data($db, $slot_table_name, $json_file);
        }
      }
      continue;
    }

    echo "Creating table from file: " . basename($sql_file) . "\n";
    $db->exec($sql); //!< execute the SQL commands to create the table

    if (file_exists($json_file)) {
      insert_json_data($db, $table_name, $json_file);
    }
  }

  if ($db_name === 'hwConfig') {
    set_hwconfig_defaults($db, $n_slots);
  }
  if ($db_name === 'selftestResult') {
    set_selftestResult_defaults($db, $n_slots);
  }

  echo "Database created at: " . $db_path . "\n";
  $db->close(); //!< close the database connection
}
