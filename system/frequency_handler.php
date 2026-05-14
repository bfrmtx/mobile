<?php

/**
 * @file frequency_handler.php
 * @brief Frequency handler for ADU system.
 */
require_once __DIR__ . '/../config.php'; //!< make your SESSION and path.
require_once TRAITS_DIR . 'global_vars.php';


class frequency_handler extends job_time {
  use global_vars; //!< global_vars for drop downs and selectors

  // job class will read this for me later!
  protected int $sampling_rate  = 0;                  //!< sampling rate in Hz controlled by virtual rates. this is the SQL sampling_rate field, no direct access from UI.
  private array $native_sampling_rates = [];          //!< array of native sampling rates for this ADU, set by child class (ADU) based on the board type, shall never be changed!
  protected int $digital_filter = 0;                  //!< digital filter setting controlled virtual rates, no direct access from UI.
  protected int $sub_cycle      = 0;                  //!< sub-cycle time in seconds, 0 means no sub-cycles, for "shots" (in job class)
  protected int $sub_duration   = 0;                  //!< sub-cycle duration in seconds, for "shots" (in job class)
  protected int $sub_filter     = 0;                  //!< sub_filter filter setting controlled virtual rates, no direct access from UI.

  private array $virtual_sampling_rates = [];         //!< array of sampling rates for the system
  private int $virtual_sampling_rate = 0;             //!< current virtual sampling rate, set by virtual_sampling_rates, no direct access from UI.

  public function __construct(array $native_sampling_rates_) {
    parent::__construct();
    $this->native_sampling_rates = $native_sampling_rates_;
    if (empty($this->native_sampling_rates)) {
      throw new Exception("Native sampling rates must be provided by the child class constructor based on the board type.");
    }
    $this->virtual_sampling_rates = $this->native_sampling_rates;
    // append by dividing with digital_filters; skip 0, and increasing order from 4 to 32
    $min = min($this->native_sampling_rates);
    foreach ($this->digital_filters as $filter) { // start at min
      if (!$filter) continue;
      $lower = intval($min / $filter);  // divide the minimum native by filters
      if ($lower >= 1) {
        $this->virtual_sampling_rates[] = $lower; // add the lower virtual sampling rates to the array
      }
    }
    $this->sampling_rate = 4096;                // default for empty job, but will be updated by job SQL configuration; this is just a fallback default
    $this->virtual_sampling_rate = $this->sampling_rate; // fallback default

  }

  public function __destruct() {
    // Destructor code if needed
  }

  public function init_virtual_rate_from_sql(): void {

    // we need to set the virtual_sampling_rate based on the sampling_rate and digital_filter, as the virtual_sampling_rate is what the user selects in the UI, and the sampling_rate and digital_filter are what we store in the SQL database.
    if ($this->digital_filter > 0) {
      $this->virtual_sampling_rate = intval($this->sampling_rate / $this->digital_filter);
    } else {
      $this->virtual_sampling_rate = $this->sampling_rate;
    }
  }

  public function set_virtual_sampling_rate(int $rate): void {
    if (!in_array($rate, $this->virtual_sampling_rates)) {
      throw new Exception("Invalid virtual sampling rate selected. Please select a valid rate from the drop-down menu.");
    }
    $this->virtual_sampling_rate = $rate;
    // calculate the get_digital_filter in case
    if (in_array($this->virtual_sampling_rate, $this->native_sampling_rates)) {
      $this->digital_filter = 0; // no filter if virtual rate is native
      $this->sampling_rate = $this->virtual_sampling_rate; // for straight mode, the sampling rate is the virtual rate
    } else {
      $ratio = intval(min($this->native_sampling_rates) / $this->virtual_sampling_rate);
      if (!in_array($ratio, $this->digital_filters)) {
        throw new Exception("Invalid digital filter ratio calculated for the given virtual rate. Please check the virtual rate and native sampling rates.");
      }
      $this->digital_filter = $ratio; // set the digital filter to the ratio of the smallest native sampling rate to the virtual sampling rate
      $this->sampling_rate = intval($this->virtual_sampling_rate * $this->digital_filter); // the effective sampling rate after applying the digital filter
      // reset sub-filter and shot settings when changing the virtual sampling rate, as they might not be valid anymore.
      $this->sub_filter = 0;
      $this->sub_cycle = 0;
      $this->sub_duration = 0;
    }
  }

  /**
   * just get the sampling rates
   * @return array 
   */
  public function get_virtual_sampling_rates(): array {
    return $this->virtual_sampling_rates;
  }

  public function get_virtual_sampling_rate(): int {
    return $this->virtual_sampling_rate;
  }

  /**
   * just get the filter rates   
   * @return  array
   */
  public function get_virtual_sub_filter_rates(): array {
    if (in_array($this->virtual_sampling_rate, $this->native_sampling_rates)) {
      $virtual_filter_sampling_rates = [];
      foreach ($this->digital_filters as $filter) {
        if (!$filter) continue;
        $effective_rate = intval($this->virtual_sampling_rate / $filter);
        if ($effective_rate >= 1) {
          $virtual_filter_sampling_rates[] = $effective_rate;
        }
      }
      return $virtual_filter_sampling_rates;
    } else {
      return []; // if the current virtual rate is not a native sampling rate or if a digital filter is already applied, we do not have valid filter rates to show
    }
  }

  /**
   * just get the shot rates   
   * @return array 
   */
  public function get_virtual_shot_rates(): array {
    if (in_array($this->virtual_sampling_rate, $this->native_sampling_rates)) {
      $virtual_shot_sampling_rates = [];
      foreach ($this->digital_filters as $filter) {
        if (!$filter) continue;
        $effective_rate = intval($this->virtual_sampling_rate * $filter);
        if ($effective_rate <= max($this->native_sampling_rates)) {
          $virtual_shot_sampling_rates[] = $effective_rate;
        }
      }
      return $virtual_shot_sampling_rates;
    } else {
      return []; // if the current virtual rate is not a native sampling rate or if a digital filter is already applied, we do not have valid shot rates to show
    }
  }

  public function get_sampling_rate(): int {
    return $this->sampling_rate;
  }

  public function get_digital_filter(): int {
    return $this->digital_filter;
  }

  public function get_sub_cycle(): int {
    return $this->sub_cycle;
  }
  public function get_sub_duration(): int {
    return $this->sub_duration;
  }
  public function get_sub_filter(): int {
    return $this->sub_filter;
  }

  public function set_sub_cycle_mins(int $mins): void {
    if (!in_array($mins, $this->cycles_mins)) {
      throw new Exception("Invalid sub-cycle time selected. Please select a valid time from the drop-down menu.");
    }
    $this->sub_cycle = intval($mins * 60); // convert minutes to seconds
  }

  public function set_sub_duration_mins(float $mins): void {
    $allowed_durations = array_map('floatval', $this->shot_durations_mins);
    if (!in_array($mins, $allowed_durations, true)) {
      throw new Exception("Invalid sub-duration time selected. Please select a valid time from the drop-down menu.");
    }
    $temp_duration = intval($mins * 64); // convert minutes to seconds (almost)
    if ($temp_duration > $this->sub_cycle) {
      $_SESSION['error_message'] = "Sub-duration cannot be greater than sub-cycle. Please select a valid duration.";
      return;
    }
    $this->sub_duration = intval($mins * 64); // convert minutes to seconds (almost)
  }

  /**
   * @brief Reset sub-job settings for straight mode and take smallest native sampling rate as default virtual rate.
   * This can be treated also like a small reset.
   */
  public function set_straight(): void {

    if (!in_array($this->virtual_sampling_rate, $this->native_sampling_rates)) {
      $this->virtual_sampling_rate = min($this->native_sampling_rates); // default to the smallest native sampling rate for straight mode
    }
    // reset shots
    $this->sub_cycle = 0;
    $this->sub_duration = 0;
    // reset filter
    $this->sub_filter = 0;
    $this->digital_filter = 0;
    $this->sampling_rate = $this->virtual_sampling_rate; // for straight mode.
  }
  /**
   * a straight job has no sub_filter, no sub_cycle, and no sub_duration
   * a) is either a native sampling rate
   * b) or a decimated sampling rate with digital_filter, but no sub_filter, no sub_cycle, and no sub_duration
   */
  public function is_straight(): bool {
    return (!$this->sub_filter && !$this->sub_cycle && !$this->sub_duration);
  }

  /**
   * @brief Enter filter mode: keep virtual_rate (native), open a sub_filter sub-pipe.
   * virtual_rate is NEVER changed here;
   */
  public function set_filter(): void {
    // virtual_rate must be a native sampling rate; if it is currently a decimated virtual rate
    // (digital_filter was applied), snap to the underlying native sampling_rate instead.
    if (!in_array($this->virtual_sampling_rate, $this->native_sampling_rates)) {
      $_SESSION['error_message'] = "Filter mode can only be applied when the virtual sampling rate is a native sampling rate. Please set a native sampling rate first.";
      return;
    }
    $this->digital_filter = 0;
    $this->sub_cycle      = 0;
    $this->sub_duration   = 0;
    // having a native sampling rate as virtual rate, we can always apply the strongest filter.
    $this->sub_filter     = max($this->digital_filters); // apply the strongest filter .
  }

  /**
   * a filter job has a main pipe (digital_filter) and a filtered subpipe (sub_filter), no sub_cycle and sub_duration
   */
  public function is_filter(): bool {
    return ($this->sub_filter > 0 && !$this->digital_filter && !$this->sub_cycle && !$this->sub_duration);
  }


  /**
   * @brief Reset shot settings and enter shot mode with the default shot-rate ratio and timing.
   * The child job class persists the resulting state after calling this method.
   */
  public function set_shots(): void {
    if (!in_array($this->virtual_sampling_rate, $this->native_sampling_rates)) {
      $_SESSION['error_message'] = "Shots mode can only be applied when the virtual sampling rate is a native sampling rate. Please set a native sampling rate first.";
      return;
    }
    // find the greatest digital_filter which fits into virtual_sampling_rate * digital_filter <= max(native_sampling_rates), that will be our default shot rate, and we set the digital filter to that.
    $valid_shot_filters = [];
    foreach ($this->digital_filters as $filter) {
      if (!$filter) continue;
      $effective_shot_rate = intval($this->virtual_sampling_rate * $filter);
      if ($effective_shot_rate <= max($this->native_sampling_rates)) {
        $valid_shot_filters[] = $filter;
      }
    }
    if (empty($valid_shot_filters)) {
      $_SESSION['error_message'] = "No valid shot rates available for the current virtual sampling rate. Please choose a SMALLER sampling rate.";
      return;
    }
    $this->digital_filter = max($valid_shot_filters); // apply the strongest valid shot filter as default
    if (!in_array($this->digital_filter, $this->digital_filters)) {
      throw new Exception("Invalid shot filter ratio calculated for the given virtual rate. Please check the virtual rate and digital filters.");
    } else {
      $this->digital_filter = max($valid_shot_filters); // apply the strongest valid shot filter as default
      $this->sampling_rate = intval($this->virtual_sampling_rate * $this->digital_filter); // the effective sampling rate before applying the digital filter
    }
    $this->sub_cycle = 180;
    $this->sub_duration = 32;
    $this->sub_filter = 0;
  }
  /**
   * a shot has a main pipe (digital_filter) and a unfiltered subpipe (sub_cycle, sub_duration)
   */
  public function is_shot(): bool {
    return ($this->sub_cycle > 0 && $this->sub_duration > 0 && $this->digital_filter > 0);
  }

  /**
   * @brief Apply posted frequency changes; persistence is handled by the job wrapper.
   */
  public function handle_post_updates(): bool {
    $updated_parent = parent::handle_post_updates();
    $updated = false;
    if (
      isset($_POST['sender']) && $_POST['sender'] === 'frequency_handler'
      && isset($_POST['what'])
    ) {
      $what  = $_POST['what'];
      $value = $_POST['value'] ?? null;

      if ($what === 'set_straight') {
        $this->set_straight();
        $updated = true;
      } elseif ($what === 'set_filter') {
        $this->set_filter();
        $updated = true;
      } elseif ($what === 'set_shots') {
        $this->set_shots();
        $updated = true;
      } elseif ($what === 'virtual_rate' && $value !== null) {
        $this->set_virtual_sampling_rate(intval($value));
        $updated = true;
      } elseif ($what === 'set_sub_cycle_mins' && $value !== null) {
        $this->set_sub_cycle_mins(intval($value));
        $updated = true;
      } elseif ($what === 'set_sub_duration_mins' && $value !== null) {
        $this->set_sub_duration_mins(floatval($value));
        $updated = true;
      } elseif ($what === 'sub_filter_rate' && $value !== null) {
        $this->set_sub_filter_rate(intval($value));
        $updated = true;
      } elseif ($what === 'shot_rate' && $value !== null) {
        $this->set_shot_rate(intval($value));
        $updated = true;
      }
    }
    return $updated || $updated_parent;
  }

  /**
   * @brief Generate the HTML for the sampling rate drop-down menu.
   * we work in the protected virtual_sampling_rates array.
   * @return string HTML code for the sampling rate drop-down menu.
   * 
   */
  public function sampling_rate_drop_down() {
    $html = '<form method="POST" action="">' . PHP_EOL;
    $html .= '<input type="hidden" name="sender" value="frequency_handler" />' . PHP_EOL;
    $html .= '<input type="hidden" name="id" value="0" />' . PHP_EOL; // no slot id
    $html .= '<input type="hidden" name="what" value="virtual_rate" />' . PHP_EOL;
    $html .= '<select name="value" id="virtual_rate" onchange="this.form.submit()">' . PHP_EOL;
    foreach ($this->get_virtual_sampling_rates() as $rate) {
      $is_native = in_array((int) $rate, $this->native_sampling_rates, true);
      $selected = ((int) $rate === (int) $this->virtual_sampling_rate) ? ' selected="selected"' : '';
      $label_suffix = $is_native ? '' : ' (fil)';
      $html .= '<option value="' . $rate . '"' . $selected . '>' . $rate . ' Hz' . $label_suffix . '</option>' . PHP_EOL;
    }
    $html .= '</select>' . PHP_EOL;
    $html .= '</form>';
    return $html;
  }

  /**
   * @brief Generate a single-line sampling rate control with a status link to its right.
   * @return string HTML code for the sampling rate drop-down plus status link.
   */
  public function sampling_rate_drop_down_plus_link(): string {
    $html = '<span style="display:inline-flex;align-items:center;gap:14px;white-space:nowrap;">';
    $html .= $this->sampling_rate_drop_down();
    $html .= '<a href="status.php" class="w3-text-black">StatusPage</a>';
    $html .= '</span>';
    return $html;
  }

  /**
   * @brief Generate a button that clears filter/shot sub-job settings and returns to straight mode.
   * @return string HTML code for the straight-mode submit button.
   */
  public function set_straight_button(): string {
    $html = '<form method="POST" action="">' . PHP_EOL;
    $html .= '<input type="hidden" name="sender" value="frequency_handler" />' . PHP_EOL;
    $html .= '<input type="hidden" name="id" value="0" />' . PHP_EOL;
    $html .= '<input type="hidden" name="what" value="set_straight" />' . PHP_EOL;
    $html .= '<button type="submit">Set Straight</button>' . PHP_EOL;
    $html .= '</form>';
    return $html;
  }

  /**
   * @brief Generate a button that switches to the default filter-mode setup.
   * @return string HTML code for the filter-mode submit button.
   */
  public function set_filter_button(): string {
    $html = '<form method="POST" action="">' . PHP_EOL;
    $html .= '<input type="hidden" name="sender" value="frequency_handler" />' . PHP_EOL;
    $html .= '<input type="hidden" name="id" value="0" />' . PHP_EOL;
    $html .= '<input type="hidden" name="what" value="set_filter" />' . PHP_EOL;
    $html .= '<button type="submit">Set Filter</button>' . PHP_EOL;
    $html .= '</form>';
    return $html;
  }

  /**
   * @brief Generate a button that switches to the default shot-mode setup.
   * @return string HTML code for the shot-mode submit button.
   */
  public function set_shots_button(): string {
    $html = '<form method="POST" action="">' . PHP_EOL;
    $html .= '<input type="hidden" name="sender" value="frequency_handler" />' . PHP_EOL;
    $html .= '<input type="hidden" name="id" value="0" />' . PHP_EOL;
    $html .= '<input type="hidden" name="what" value="set_shots" />' . PHP_EOL;
    $html .= '<button type="submit">Set Shots</button>' . PHP_EOL;
    $html .= '</form>';
    return $html;
  }

  public function sub_cycle_drop_down(): string {
    if (!$this->is_shot()) {
      return '<p style="color:navy;">activate shots first.</p>';
    }
    $html = '<form method="POST" action="">' . PHP_EOL;
    $html .= '<input type="hidden" name="sender" value="frequency_handler" />' . PHP_EOL;
    $html .= '<input type="hidden" name="id" value="0" />' . PHP_EOL;
    $html .= '<input type="hidden" name="what" value="set_sub_cycle_mins" />' . PHP_EOL;
    $html .= '<select name="value" id="sub_cycle_mins" onchange="this.form.submit()">' . PHP_EOL;
    foreach ($this->cycles_mins as $mins) {
      $selected = ((int) $mins * 60 === (int) $this->sub_cycle) ? ' selected="selected"' : '';
      $html .= '<option value="' . $mins . '"' . $selected . '>' . $mins . ' min</option>' . PHP_EOL;
    }
    $html .= '</select>' . PHP_EOL;
    $html .= '</form>';
    return $html;
  }

  public function sub_duration_drop_down(): string {
    if (!$this->is_shot()) {
      return '<p style="color:navy;">activate shots first.</p>';
    }
    $html = '<form method="POST" action="">' . PHP_EOL;
    $html .= '<input type="hidden" name="sender" value="frequency_handler" />' . PHP_EOL;
    $html .= '<input type="hidden" name="id" value="0" />' . PHP_EOL;
    $html .= '<input type="hidden" name="what" value="set_sub_duration_mins" />' . PHP_EOL;
    $html .= '<select name="value" id="sub_duration_mins" onchange="this.form.submit()">' . PHP_EOL;
    foreach ($this->shot_durations_mins as $mins) {
      $duration_seconds = intval(((float) $mins) * 64);
      $selected = ($duration_seconds === (int) $this->sub_duration) ? ' selected="selected"' : '';
      $html .= '<option value="' . $mins . '"' . $selected . '>' . $mins . ' min</option>' . PHP_EOL;
    }
    $html .= '</select>' . PHP_EOL;
    $html .= '</form>';
    return $html;
  }

  /**
   * @brief we get a virtual sub-filter rate in Hz. This will be processed as $sub_filter = virtual_rate / sub_filter_rate, so finally we set the sub_filter ONLY!
   * @param int $sub_filter_rate
   */
  public function set_sub_filter_rate(int $sub_filter_rate): void {
    // in straight mode with filter already set: no change.
    if ($this->digital_filter > 0) {
      $_SESSION['error_message'] = "Sub-filter rates can only be applied with native frequencies";
      return; // if a digital filter is already applied to the virtual rate.
    }
    if ($this->sub_cycle || $this->sub_duration) {
      // clean up. we know that sub_cycle and sub_duration need a digital filter!
      $this->sub_cycle = 0;
      $this->sub_duration = 0;
    }
    $ratio = intval($this->virtual_sampling_rate / $sub_filter_rate);
    // the ratio must fit into digital_filters, otherwise something went wrong.
    if (!in_array($ratio, $this->digital_filters)) {
      $_SESSION['error_message'] = "Invalid sub-filter rate selected.";
      return; // the ratio must be an integer, otherwise it's not a valid filter rate for the current virtual rate
    }
    $this->sub_filter = $ratio;
  }

  /**
   * @brief Set the current shot rate, which actually adjusts the digital filter!
   * @param int $shot_rate The new shot rate selected by the user.
   * @brief getting 16384, having 4096 virtual rate, restults in ratio 4, which is in digital filters. <br>
   * the SQL database later gets: sample_rate 16384, sub_filter 0, digital_filter 4, sub_cycle and duration to be set. <br>
   * The virtual_rate is 4096, the shot_rate is 16384, which is 4 times the virtual rate.
   */
  public function set_shot_rate(int $shot_rate): void {
    if (!in_array($this->virtual_sampling_rate, $this->native_sampling_rates)) {
      $_SESSION['error_message'] = "Shot mode can only be applied with native frequencies";
      return; // if a digital filter is already applied to the virtual rate.
    }
    if ($this->sub_filter && !$this->digital_filter) {
      $this->sub_filter = 0; // clean up sub_filter if it was set
    }
    $ratio = intval($shot_rate / $this->virtual_sampling_rate);
    // the ratio must fit into digital_filters, otherwise something went wrong.
    // and must be in digital_filters
    if (!in_array($ratio, $this->digital_filters) && $ratio > 1) {
      $_SESSION['error_message'] = "Invalid shot rate selected.";
      return; // the ratio must be one of the valid digital filters, otherwise it's not a valid shot rate for the current virtual rate
    } elseif (in_array($ratio, $this->digital_filters) && $ratio != 0) {
      $this->digital_filter = $ratio; // for shots, we set the digital filter to the ratio, and use sub_cycle and sub_duration to control the timing of the unfiltered sub-pipe
      $this->sampling_rate = intval($this->virtual_sampling_rate * $this->digital_filter); // the effective sampling rate BEFORE applying the digital filter
    } else {
      $_SESSION['error_message'] = "Invalid shot rate selected.";
      return;
    }
  }

  /**
   * @brief Generate the HTML for the virtual sub-filter rates drop-down menu. It can be empty if no valid sub-filter rates are available for the current virtual sampling rate (this is the case for lower sampling rates, where a filter is already applied, e.g. less than 1024 Hz, the lowest native rate of ADU-11e).
   * @return string HTML code for the virtual sub-filter rates drop-down menu.
   */
  public function virtual_sub_filter_rates_drop_down() {
    if (!$this->is_filter()) {
      return '<p style="color:navy;">activate filter first.</p>';
    }
    $html = '<form method="POST" action="">' . PHP_EOL;
    $html .= '<input type="hidden" name="sender" value="frequency_handler" />' . PHP_EOL;
    $html .= '<input type="hidden" name="id" value="0" />' . PHP_EOL; // no slot id
    $html .= '<input type="hidden" name="what" value="sub_filter_rate" />' . PHP_EOL;
    $html .= '<select name="value" id="virtual_sub_filter_rate" onchange="this.form.submit()">' . PHP_EOL;
    foreach ($this->get_virtual_sub_filter_rates() as $rate) {
      $selected = ((int) $rate === (int) ($this->virtual_sampling_rate / $this->sub_filter)) ? ' selected="selected"' : '';
      $html .= '<option value="' . $rate . '"' . $selected . '>' . $rate . ' Hz</option>' . PHP_EOL;
    }
    $html .= '</select>' . PHP_EOL;
    $html .= '</form>';
    return $html;
  }

  /**
   * @brief Generate the HTML for the virtual shot rates drop-down menu. It can be empty if no valid shot rates are available for the current virtual sampling rate (this is the case for higher sampling rates, where virtual rate * 4 (smalles filter) already exceeds the maximum sampling rate of the system, e.g. 131072 Hz, the highest native rate of ADU-11e).
   * @return string HTML code for the virtual shot rates drop-down menu.
   */
  public function virtual_shot_rates_drop_down() {
    if (!$this->is_shot()) {
      return '<p style="color:navy;">activate shots first.</p>';
    }
    $html = '<form method="POST" action="">' . PHP_EOL;
    $html .= '<input type="hidden" name="sender" value="frequency_handler" />' . PHP_EOL;
    $html .= '<input type="hidden" name="id" value="0" />' . PHP_EOL; // no slot id
    $html .= '<input type="hidden" name="what" value="shot_rate" />' . PHP_EOL;
    $html .= '<select name="value" id="virtual_shot_rate" onchange="this.form.submit()">' . PHP_EOL;
    foreach ($this->get_virtual_shot_rates() as $rate) {
      $selected = ((int) $rate === (int) ($this->virtual_sampling_rate * $this->digital_filter)) ? ' selected="selected"' : '';
      $html .= '<option value="' . $rate . '"' . $selected . '>' . $rate . ' Hz</option>' . PHP_EOL;
    }
    $html .= '</select>' . PHP_EOL;
    $html .= '</form>';
    return $html;
  }
} // end of class