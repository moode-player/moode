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
$currentSettings['fan_temp0'] = $_SESSION['fan_temp0'];
$currentSettings['log2ram'] = $_SESSION['log2ram'];
$currentSettings['tmp2ram'] = $_SESSION['tmp2ram'];
$currentSettings['usb_volknob'] = $_SESSION['usb_volknob'];
$currentSettings['led_state'] = $_SESSION['led_state'];
$currentSettings['eth0chk'] = $_SESSION['eth0chk'];
$currentSettings['avahi_options'] = $_SESSION['avahi_options'];
$currentSettings['ready_script'] = $_SESSION['ready_script'];
$currentSettings['ready_script_wait'] = $_SESSION['ready_script_wait'];
// Network
$currentSettings['approto'] = $_SESSION['approto'];
// Audio: MPD options
$currentSettings['ashuffle_mode'] = $_SESSION['ashuffle_mode'];
$currentSettings['ashuffle_window'] = $_SESSION['ashuffle_window'];
$currentSettings['ashuffle_filter'] = $_SESSION['ashuffle_filter'];
$currentSettings['ashuffle_exclude'] = $_SESSION['ashuffle_exclude'];
// Audio: Bluetooth
$currentSettings['alsa_output_mode_bt'] = $_SESSION['alsa_output_mode_bt'];
$currentSettings['alsavolume_max_bt'] = $_SESSION['alsavolume_max_bt'];
$currentSettings['cdspvolume_max_bt'] = $_SESSION['cdspvolume_max_bt'];
$currentSettings['bt_auto_disconnect'] = $_SESSION['bt_auto_disconnect'];
// Audio: Plexamp
$currentSettings['alsavolume_max_pa'] = $_SESSION['alsavolume_max_pa'];
// Audio: ALSA
$currentSettings['alsa_empty_retry'] = $_SESSION['alsa_empty_retry'];
// Library
$currentSettings['lib_scope'] = $_SESSION['lib_scope'];
$currentSettings['lib_active_search'] = $_SESSION['lib_active_search'];
// Radio monitor
$currentSettings['mpd_monitor_svc'] = $_SESSION['mpd_monitor_svc'];
$currentSettings['mpd_monitor_opt'] = $_SESSION['mpd_monitor_opt'];
// Mount monitor
$currentSettings['fs_mountmon'] = $_SESSION['fs_mountmon'];
// Local display
$currentSettings['scn_cursor'] = $_SESSION['scn_cursor'];
$currentSettings['on_screen_kbd'] = $_SESSION['on_screen_kbd'];
$currentSettings['scn_blank'] = $_SESSION['scn_blank'];
$currentSettings['disable_gpu_chromium'] = $_SESSION['disable_gpu_chromium'];
$currentSettings['hdmi_cec'] = $_SESSION['hdmi_cec'];
$currentSettings['hdmi_enable_4kp60'] = $_SESSION['hdmi_enable_4kp60'];
$currentSettings['scn_cursor'] = $_SESSION['scn_cursor'];
$currentSettings['scn_blank'] = $_SESSION['scn_blank'];
$currentSettings['dsi_scn_brightness'] = $_SESSION['dsi_scn_brightness'];
// LCD updater
$currentSettings['lcdup'] = $_SESSION['lcdup'];
// Miscellaneous
$currentSettings['auto_coverview'] = $_SESSION['auto_coverview'];
$currentSettings['rotaryenc'] = $_SESSION['rotaryenc'];

// Extract to ini file
print(autoConfigExtract($currentSettings));
