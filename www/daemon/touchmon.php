#!/usr/bin/php
<?php
/*
 * SPDX-License-Identifier: GPL-3.0-or-later
 * Copyright 2026 The moOde audio player project / Tim Curtis
 *
 * Based on Peppy_ONOFF.py posted in the moOde Forum
 * Copyright 2025-2026 @fdealexa
 * https://moodeaudio.org/forum/showthread.php?tid=3484&pid=69333#pid69333
 *
*/

require_once __DIR__ . '/../inc/common.php';
require_once __DIR__ . '/../inc/mpd.php';
require_once __DIR__ . '/../inc/sql.php';

// Initialization
debugLog('touchmon: Started');
debugLog('touchmon: Initialization');
$timeoutArg = !isset($argv[1]) ? TOUCHMON_TIMEOUT_DEFAULT : $argv[1];
$timeout = $timeoutArg;
$dbh = sqlConnect();
sysCmd('rm ' . TOUCHMON_LOG . ' > /dev/null');
sysCmd('killall -s9 xinput > /dev/null');

// Monitor for touch events
debugLog('touchmon: Polling loop...');
while (true) {
	debugLog('touchmon: Top');
	// XServer must be running
	if (isWebuiOn($dbh) === true || isPeppyOn($dbh) === true) {
		debugLog('touchmon: - XServer is running');
		// Start xinput or restart it in case localdisplay restarted or turned off
		if (isXinputOn() === false) {
			debugLog('touchmon: - xinput started');
			startXinput();
			$lastMTime = filemtime(TOUCHMON_LOG);
		}

		// Get file modified time
		clearstatcache();
		$currentMTime = filemtime(TOUCHMON_LOG);

		// Touch detected
		if ($currentMTime != $lastMTime) {
			debugLog('touchmon: - touch detected');
			$timeout = $timeoutArg;
			debugLog('touchmon: - timeout reset to ' . $timeout);
			if (isWebuiOn($dbh) === false) {
				debugLog('touchmon: - setdisplay webui');
				sysCmd('moodeutl --setdisplay webui');
			}

			$lastMTime = $currentMTime;
		}

		// Switch to Peppy if no touch events within the timeout period
		if (isWebuiOn($dbh) === true && isMpdPlaying() === true) {
			--$timeout;
			debugLog('touchmon: - timeout ' . $timeout);
			if ($timeout == 0) {
				debugLog('touchmon: - setdisplay peppy');
				sysCmd('moodeutl --setdisplay peppy');
			}
		}
	} else {
		debugLog('touchmon: - X11 not running');
	}

	sleep(1);
}

function isXinputOn() {
	$xinputOn = sysCmd('pgrep -c xinput')[0];
	return $xinputOn > 0 ? true : false;
}
function startXinput() {
	shell_exec('export DISPLAY=:0');
	shell_exec('xinput --test-xi2 --root | unbuffer -p grep RawTouchEnd > ' . TOUCHMON_LOG . ' &');
}
function isWebuiOn($dbh) {
	$webuiOn = sqlQuery("SELECT value FROM cfg_system WHERE param='local_display'", $dbh)[0]['value'];
	return $webuiOn == '1' ? true : false;
}
function isPeppyOn($dbh) {
	$peppyOn = sqlQuery("SELECT value FROM cfg_system WHERE param='peppy_display'", $dbh)[0]['value'];
	return $peppyOn == '1' ? true : false;
}
function isMpdPlaying() {
	if (false !== ($sock = openMpdSock('localhost', 6600))) {
		$mpdState = getMpdStatus($sock)['state'];
		closeMpdSock($sock);
	} else {
		$mpdState = 'unknown';
		debugLog('touchmon: CRITICAL ERROR: Unable to connect to MPD');
	}
	return $mpdState == 'play' ? true : false;
}
