<?php

/** @file system_log.php
 * @brief System log model/renderer for the mobile interface.
 */
require_once __DIR__ . '/../config.php';
require_once PHP_DIR . 'php_functions.php';

class system_log {
  protected database $db; //!< database connection for system log
  protected string $db_file = 'systemLog.db'; //!< database file for system log
  protected string $table_name = 'log'; //!< table name for system log
  private int $read_n_lines = 20; //!< number of log entries to read for display
  private array $priority_map = [ //!< mapping of log level integers to strings
    0 => 'EMERG',
    1 => 'ALERT',
    2 => 'CRIT',
    3 => 'ERR',
    4 => 'WARNING',
    5 => 'NOTICE',
    6 => 'INFO',
    7 => 'DEBUG',
  ];

  public function __construct() {
    $this->db = new database($this->db_file);
    $this->db->set_table($this->table_name);
  }

  public function time_to_date_time(int $timestamp): string {
    return date('Y-m-d H:i:s', $timestamp);
  }

  /** @return array{offset:int, priority:int|null} */
  private function get_state_from_request(): array {
    $offset = intval($_GET['offset'] ?? 0);
    if ($offset < 0) {
      $offset = 0;
    }

    $priority = null;
    if (isset($_GET['priority']) && $_GET['priority'] !== '' && $_GET['priority'] !== 'all') {
      $candidate = intval($_GET['priority']);
      if (array_key_exists($candidate, $this->priority_map)) {
        $priority = $candidate;
      }
    }

    return ['offset' => $offset, 'priority' => $priority];
  }

  private function get_total_rows(?int $priority): int {
    $pdo = $this->db->get_pdo();
    if ($pdo === null) {
      return 0;
    }

    if ($priority === null) {
      $stmt = $pdo->query('SELECT COUNT(*) AS cnt FROM ' . $this->table_name);
      $row = $stmt !== false ? $stmt->fetch(PDO::FETCH_ASSOC) : false;
      return intval($row['cnt'] ?? 0);
    }

    $stmt = $pdo->prepare('SELECT COUNT(*) AS cnt FROM ' . $this->table_name . ' WHERE priority = :priority');
    $stmt->bindValue(':priority', $priority, PDO::PARAM_INT);
    $stmt->execute();
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    return intval($row['cnt'] ?? 0);
  }

  private function get_max_offset(int $total_rows): int {
    if ($total_rows <= $this->read_n_lines) {
      return 0;
    }
    $last_page_index = intdiv($total_rows - 1, $this->read_n_lines);
    return $last_page_index * $this->read_n_lines;
  }

  /** @return array<int,array<string,mixed>> */
  private function get_rows(int $offset, ?int $priority): array {
    $pdo = $this->db->get_pdo();
    if ($pdo === null) {
      return [];
    }

    if ($priority === null) {
      $stmt = $pdo->prepare(
        'SELECT id, date_time, priority, main_index, sub_index, message '
          . 'FROM ' . $this->table_name . ' '
          . 'ORDER BY id DESC LIMIT :limit OFFSET :offset'
      );
      $stmt->bindValue(':limit', $this->read_n_lines, PDO::PARAM_INT);
      $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
      $stmt->execute();
      return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    $stmt = $pdo->prepare(
      'SELECT id, date_time, priority, main_index, sub_index, message '
        . 'FROM ' . $this->table_name . ' '
        . 'WHERE priority = :priority '
        . 'ORDER BY id DESC LIMIT :limit OFFSET :offset'
    );
    $stmt->bindValue(':priority', $priority, PDO::PARAM_INT);
    $stmt->bindValue(':limit', $this->read_n_lines, PDO::PARAM_INT);
    $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
    $stmt->execute();
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
  }

  private function qs(int $offset, ?int $priority): string {
    $params = ['offset' => strval(max(0, $offset))];
    if ($priority !== null) {
      $params['priority'] = strval($priority);
    }
    return http_build_query($params);
  }

  private function render_nav_buttons(int $offset, int $max_offset, ?int $priority): string {
    $newest_offset = 0;
    $oldest_offset = $max_offset;
    $previous_offset = max(0, $offset - $this->read_n_lines);
    $next_offset = min($max_offset, $offset + $this->read_n_lines);

    $disabled_previous = $offset <= 0;
    $disabled_next = $offset >= $max_offset;

    $html = '<div class="w3-row w3-margin-bottom">';
    $html .= '<div class="w3-col s12">';
    $html .= '<div class="w3-bar">';

    $html .= '<a class="w3-button w3-border w3-margin-right' . ($disabled_previous ? ' w3-disabled' : '') . '" href="?' . htmlspecialchars($this->qs($previous_offset, $priority), ENT_QUOTES, 'UTF-8') . '">&lt;&lt;</a>';
    $html .= '<a class="w3-button w3-border w3-margin-right' . ($disabled_previous ? ' w3-disabled' : '') . '" href="?' . htmlspecialchars($this->qs($newest_offset, $priority), ENT_QUOTES, 'UTF-8') . '">first</a>';
    $html .= '<a class="w3-button w3-border w3-margin-right' . ($disabled_next ? ' w3-disabled' : '') . '" href="?' . htmlspecialchars($this->qs($oldest_offset, $priority), ENT_QUOTES, 'UTF-8') . '">last</a>';
    $html .= '<a class="w3-button w3-border' . ($disabled_next ? ' w3-disabled' : '') . '" href="?' . htmlspecialchars($this->qs($next_offset, $priority), ENT_QUOTES, 'UTF-8') . '">&gt;&gt;</a>';

    $html .= '</div>';
    $html .= '</div>';
    $html .= '</div>';

    return $html;
  }

  private function render_filter_buttons(?int $active_priority): string {
    $html = '<div class="w3-row w3-margin-bottom">';
    $html .= '<div class="w3-col s12">';
    $html .= '<div class="w3-bar">';

    $all_class = $active_priority === null ? ' w3-black' : ' w3-light-grey';
    $html .= '<a class="w3-button w3-border w3-margin-right' . $all_class . '" href="?' . htmlspecialchars($this->qs(0, null), ENT_QUOTES, 'UTF-8') . '">ALL</a>';

    foreach ($this->priority_map as $priority => $label) {
      $btn_class = ($active_priority === $priority) ? ' w3-black' : ' w3-light-grey';
      $safe_label = htmlspecialchars($label, ENT_QUOTES, 'UTF-8');
      $html .= '<a class="w3-button w3-border w3-margin-right' . $btn_class . '" href="?' . htmlspecialchars($this->qs(0, $priority), ENT_QUOTES, 'UTF-8') . '">' . $safe_label . '</a>';
    }

    $html .= '</div>';
    $html .= '</div>';
    $html .= '</div>';

    return $html;
  }

  /** @param array<int,array<string,mixed>> $rows */
  private function render_table(array $rows): string {
    $html = '<div class="w3-responsive">';
    $html .= '<table class="w3-table-all w3-small">';
    $html .= '<thead>';
    $html .= '<tr class="w3-theme-l3">';
    $html .= '<th>ID</th>';
    $html .= '<th>Date Time (UTC)</th>';
    $html .= '<th>Priority</th>';
    $html .= '<th>Main</th>';
    $html .= '<th>Sub</th>';
    $html .= '<th>Message</th>';
    $html .= '</tr>';
    $html .= '</thead>';
    $html .= '<tbody>';

    if (count($rows) === 0) {
      $html .= '<tr><td colspan="6">No log entries found.</td></tr>';
    } else {
      foreach ($rows as $row) {
        $id = intval($row['id'] ?? 0);
        $ts = intval($row['date_time'] ?? 0);
        $prio = intval($row['priority'] ?? -1);
        $main = intval($row['main_index'] ?? 0);
        $sub = intval($row['sub_index'] ?? 0);
        $msg = htmlspecialchars(strval($row['message'] ?? ''), ENT_QUOTES, 'UTF-8');
        $prio_text = $this->priority_map[$prio] ?? ('P' . $prio);

        $html .= '<tr>';
        $html .= '<td>' . $id . '</td>';
        $html .= '<td>' . htmlspecialchars($this->time_to_date_time($ts), ENT_QUOTES, 'UTF-8') . '</td>';
        $html .= '<td>' . htmlspecialchars($prio_text, ENT_QUOTES, 'UTF-8') . ' (' . $prio . ')</td>';
        $html .= '<td>' . $main . '</td>';
        $html .= '<td>' . $sub . '</td>';
        $html .= '<td>' . $msg . '</td>';
        $html .= '</tr>';
      }
    }

    $html .= '</tbody>';
    $html .= '</table>';
    $html .= '</div>';

    return $html;
  }

  public function render_page_content(): string {
    $state = $this->get_state_from_request();
    $priority = $state['priority'];
    $total_rows = $this->get_total_rows($priority);
    $max_offset = $this->get_max_offset($total_rows);
    $offset = min($state['offset'], $max_offset);
    $rows = $this->get_rows($offset, $priority);

    $from = $total_rows > 0 ? ($offset + 1) : 0;
    $to = min($offset + $this->read_n_lines, $total_rows);

    $html = '';
    $html .= $this->render_nav_buttons($offset, $max_offset, $priority);
    $html .= $this->render_filter_buttons($priority);
    $html .= '<div class="w3-small w3-text-grey w3-margin-bottom">';
    $html .= 'Showing ' . $from . ' - ' . $to . ' of ' . $total_rows . ' entries';
    $html .= '</div>';
    $html .= $this->render_table($rows);

    return $html;
  }
} // end of class system_log