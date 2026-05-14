<?php

/**
 * @file status_icons.php
 * @brief AJAX endpoint — returns GPS date-time + status icons for the nav bar.
 *        Called every 2 seconds by the jQuery poller in nav_status_bar.php.
 */
require_once __DIR__ . '/../config.php';
require_once SYSTEM_DIR . 'nav_status_bar.php';

header('Content-Type: text/html; charset=UTF-8');
header('Cache-Control: no-store, no-cache, must-revalidate');
header('Pragma: no-cache');

$bar = new nav_status_bar();
echo $bar->render_status_bar_content();
