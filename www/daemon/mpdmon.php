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

require_once __DIR__ . '/../inc/common.php';
require_once __DIR__ . '/../inc/session.php';
require_once __DIR__ . '/../inc/sql.php';

if (isset($argv[1])) {
	$opt = explode(',', $argv[1]);
	$sleepInterval = $opt[0];
	$resumePlay = $opt[1];
	$threshold = $opt[2];
} else {
	$sleepInterval = 6;
	$resumePlay = 'Yes';
	$threshold = 3;
}
//workerLog('mpdmon.php: Options: ' . $sleepInterval . '|' . $resumePlay . '|' . $threshold);

$logMessage = 'alsa_output: Decoder is too slow; playing silence to avoid xrun';
$msgCount = 0;
$currentFile = '';
$logSize = filesize(MPD_LOG) - 1024;
session_id(phpSession('get_sessionid'));

while (true) {
	phpSession('open_ro'); // NOTE this does open/close so it must be in the loop to get latest session

	if ($currentFile != $_SESSION['currentfile']) {
		$msgCount = 0;
	}

	// Only radio stations that opt in for monotoring
	if (isset($_SESSION[$_SESSION['currentfile']]) && $_SESSION[$_SESSION['currentfile']]['monitor'] == 'Yes') {
		//workerLog('mpdmon: Monitoring: ' . $_SESSION['currentfile']);
		clearstatcache();
		$currentSize = filesize(MPD_LOG);
		$currentFile = $_SESSION['currentfile'];

		if ($logSize != $currentSize) {
			// Seek to end of log file
			$fh = fopen(MPD_LOG, "r");
			fseek($fh, $logSize);

			// Read log messages and check for match
		    while ($data = fgets($fh)) {
				//workerLog($data);
				if (strpos($data, $logMessage) !== false) {
					$msgCount++;
					workerLog('mpdmon: ' .
						'Buffer underrun ' . $msgCount . ' detected: ' .
						$_SESSION[$_SESSION['currentfile']]['name']
					);
				}

				if ($msgCount == $threshold) {
					$msg = 'mpdmon: MPD restarted';
					sysCmd('systemctl restart mpd');
					if ($resumePlay == 'Yes') {
						$msg .= ', play resumed';
						sysCmd('mpc play');
					}
					workerLog($msg);
					$msgCount = 0;
				}
			}

			fclose($fh);
			$logSize = $currentSize;
		}
	}

	sleep($sleepInterval);
}
