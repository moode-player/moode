#!/usr/bin/php
<?php
/**
 * moOde audio player (C) 2014 Tim Curtis
 * http://moodeaudio.org
 *
 * This Program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 3, or (at your option)
 * any later version.
 *
 * This Program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program.  If not, see <http://www.gnu.org/licenses/>.
 *
 */

 /**
  * autocfg-gen exports the supported autoconfig settings to the screen.
  * Don't use this file directly but instead use the command "sudo moodeutl -e" otherwise the
  * session_id() function will not be able to access the PHP session
  * (C) 2020 @bitlab (@bitkeeper Git)
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

// Load session+sql params
$result = sqlRead('cfg_system', sqlConnect());
$currentSettings = array();
foreach ($result as $row) {
    $currentSettings[$row['param']] = $row['value'];
}

// Add certain session-only params so they are available to autoConfigExtract()
$currentSettings['updater_auto_check'] = $_SESSION['updater_auto_check'];
$currentSettings['worker_responsiveness'] = $_SESSION['worker_responsiveness'];
$currentSettings['fs_mountmon'] = $_SESSION['fs_mountmon'];

// Extract to ini file
print(autoConfigExtract($currentSettings));
