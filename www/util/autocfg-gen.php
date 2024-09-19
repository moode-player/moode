#!/usr/bin/php
<?php
/*
 * SPDX-License-Identifier: GPL-3.0-or-later
 * Copyright 2014 The moOde audio player project / Tim Curtis
 * Copyright 2020 @bitlab (@bitkeeper Git)
*/

/*
  * autocfg-gen exports the supported autoconfig settings to the screen.
  * Don't use this file directly but instead use the command "sudo moodeutl -e" otherwise the
  * session_id() function will not be able to access the PHP session
*/

require_once __DIR__ . '/../inc/common.php';
require_once __DIR__ . '/../inc/sql.php';
require_once __DIR__ . '/../inc/autocfg.php';

// Open the session for read
// NOTE: moodeutl has to be run with sudo otherwise the session can't be accessed
$sessionId = trim(shell_exec("sqlite3 " . SQLDB_PATH . " \"SELECT value FROM cfg_system WHERE param='sessionid'\""));
session_id($sessionId);
if (session_start() === false) {
    workerLog('autocfg-gen.php: Session start failed, script exited');
    exit(1);
} else {
    session_write_close();
}

// Load sql/session params
$result = sqlRead('cfg_system', sqlConnect());
$currentSettings = array();
foreach ($result as $row) {
    $currentSettings[$row['param']] = $row['value'];
}

// Add session-only params so they are available to autoConfigExtract()
// System
$currentSettings['updater_auto_check'] = $_SESSION['updater_auto_check'];
$currentSettings['worker_responsiveness'] = $_SESSION['worker_responsiveness'];
$currentSettings['pi_audio_driver'] = $_SESSION['pi_audio_driver'];
$currentSettings['pci_express'] = $_SESSION['pci_express'];
$currentSettings['log2ram'] = $_SESSION['log2ram'];
// MPD options
$currentSettings['ashuffle_mode'] = $_SESSION['ashuffle_mode'];
$currentSettings['ashuffle_window'] = $_SESSION['ashuffle_window'];
$currentSettings['ashuffle_filter'] = $_SESSION['ashuffle_filter'];
// Bluetooth
$currentSettings['alsa_output_mode_bt'] = $_SESSION['alsa_output_mode_bt'];
$currentSettings['alsavolume_max_bt'] = $_SESSION['alsavolume_max_bt'];
$currentSettings['cdspvolume_max_bt'] = $_SESSION['cdspvolume_max_bt'];
// Plexamp
$currentSettings['alsavolume_max_pa'] = $_SESSION['alsavolume_max_pa'];
// Library
$currentSettings['lib_scope'] = $_SESSION['lib_scope'];
$currentSettings['lib_active_search'] = $_SESSION['lib_active_search'];
// Radio monitor
$currentSettings['mpd_monitor_svc'] = $_SESSION['mpd_monitor_svc'];
$currentSettings['mpd_monitor_opt'] = $_SESSION['mpd_monitor_opt'];
// Mount monitor
$currentSettings['fs_mountmon'] = $_SESSION['fs_mountmon'];
// Miscellaneous
$currentSettings['auto_coverview'] = $_SESSION['auto_coverview'];
$currentSettings['on_screen_kbd'] = $_SESSION['on_screen_kbd'];
$currentSettings['hdmi_cec'] = $_SESSION['hdmi_cec'];
$currentSettings['hdmi_enable_4kp60'] = $_SESSION['hdmi_enable_4kp60'];
$currentSettings['rotaryenc'] = $_SESSION['rotaryenc'];

// Extract to ini file
print(autoConfigExtract($currentSettings));
