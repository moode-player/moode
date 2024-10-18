#!/usr/bin/php
<?php
/*
 * SPDX-License-Identifier: GPL-3.0-or-later
 * Copyright 2014 The moOde audio player project / Tim Curtis
*/

require_once __DIR__ . '/../inc/common.php';
require_once __DIR__ . '/../inc/mpd.php';
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
			// Seek to last end position in log file
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
					sysCmd("systemctl restart mpd");
					$sock = openMpdSock('localhost', 6600); // 6 x .5sec retries
					workerLog($sock === false ?
						'mpdmon: MPD port 6600: connection refused' :
						'mpdmon: MPD port 6600: accepting connections');
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
