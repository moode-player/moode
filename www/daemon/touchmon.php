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
$timeoutArg = !isset($argv[1]) ? TOUCHMON_TIMEOUT_DEFAULT : $argv[1];
$timeout = $timeoutArg;
$dbh = sqlConnect();
sysCmd('rm ' . TOUCHMON_LOG . ' > /dev/null');
sysCmd('killall -s9 xinput > /dev/null');

// Wait for xServer to start
$maxLoops = 3;
for ($i = 0; $i < $maxLoops; $i++) {
	debugLog('touchmon: Wait ' . ($i + 1) . ' for xServer to start');
	sleep(3);
	$result = sysCmd('pgrep -c Xorg')[0];
	if ($result > 0) {
		break;
	}
}
$result = sysCmd('pgrep -c Xorg')[0];
if ($result > 0) {
	debugLog('touchmon: XServer is running');
} else {
	workerLog('touchmon: CRITICAL ERROR: XServer is not running');
	exit (1);
}

// Monitor for touch events
while (true) {
	debugLog('touchmon: Loop');
	// Start/restart xinput in case localdisplay.service restarted or stopped
	if (isXinputOn() === false) {
		debugLog('touchmon: - start xinput');
		startXinput();
		$lastMTime = filemtime(TOUCHMON_LOG);
	}

	if (isPeppyALSAOn() === true) {
		// Get logfile modified time
		clearstatcache();
		$currentMTime = filemtime(TOUCHMON_LOG);

		// Switch to webUI (touch detected)
		if ($currentMTime != $lastMTime) {
			debugLog('touchmon: - touch detected');
			debugLog('touchmon: - timeout reset to ' . $timeoutArg);
			$timeout = $timeoutArg;
			if (isWebuiOn($dbh) === false) {
				debugLog('touchmon: - switch to webui');
				exec('sudo moodeutl --setdisplay webui');
			}

			$lastMTime = $currentMTime;
		}

		// Switch to Peppy if no touch events within the timeout period
		if (isWebuiOn($dbh) === true && isMpdPlaying() === true) {
			debugLog('touchmon: - timeout ' . $timeout);
			--$timeout;
			if ($timeout == 0) {
				debugLog('touchmon: - switch to peppy');
				exec('sudo moodeutl --setdisplay peppy');
				debugLog('touchmon: - timeout reset to ' . $timeoutArg);
				$timeout = $timeoutArg;
			}
		}
		// Switch to WebUI immediately when MPD stops
		if (isPeppyOn($dbh) === true && isMpdPlaying() === false) {
			debugLog('touchmon: - switch to webui');
			exec('sudo moodeutl --setdisplay webui');
		}
	} else {
		debugLog('touchmon: - WARNING: peppyalsa is not enabled');
	}

	sleep(1);
}

// Helpers

function isXinputOn() {
	$xinputOn = sysCmd('pgrep -c xinput')[0];
	return $xinputOn > 0 ? true : false;
}
function startXinput() {
	shell_exec('export DISPLAY=:0');
	shell_exec('xinput --test-xi2 --root | unbuffer -p grep RawTouchEnd > ' . TOUCHMON_LOG . ' &');
}
function isPeppyALSAOn() {
	return file_exists('/etc/alsa/conf.d/peppy.conf');
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
		workerLog('touchmon: CRITICAL ERROR: Unable to connect to MPD');
	}
	return $mpdState == 'play' ? true : false;
}
