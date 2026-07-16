#!/usr/bin/php
<?php
/*
 * SPDX-License-Identifier: GPL-3.0-or-later
 * Copyright 2026 The moOde audio player project / Tim Curtis
 *
 * Publish the hardware volume attenuation for PeppyMeter (volume.gain.db.source).
 *
 * peppyalsa taps the signal upstream of the DAC attenuator, so with a hardware
 * volume the VU needles ignore the knob. Republish the attenuation on every ALSA
 * control change - whatever moved it: the WebUI, vol.sh (USB knob, IR remote),
 * rotvol.sh, mpc or an external amixer.
 *
 * Started by the worker when peppy_display is on. Runs as root (sysCmd), so it
 * never opens the PHP session: that would recreate the session file root-owned and
 * lock the www-data web app out of it.
*/

require_once __DIR__ . '/../inc/common.php';
require_once __DIR__ . '/../inc/mpd.php';
require_once __DIR__ . '/../inc/sql.php';

function getGainConfig($dbh) {
	$config = array();
	$rows = sqlQuery("SELECT param, value FROM cfg_system WHERE param IN ('cardnum', 'amixname', 'mpdmixer')", $dbh);
	foreach ($rows as $row) {
		$config[$row['param']] = $row['value'];
	}

	return $config;
}

function publishGainDb($dbh) {
	// Re-read on each event: the value has to follow a volume type change even if the
	// restart the worker queues for it is ever missed
	$config = getGainConfig($dbh);

	// Only the hardware mixer needs this. Software and CamillaDSP volume attenuate
	// upstream of peppyalsa, so the meter follows them natively: publish 0 (unity).
	if ($config['mpdmixer'] != 'hardware') {
		$gainDb = '0';
	} else {
		$mappedDbVol = getAlsaMappedDbVol($config['cardnum'], $config['amixname'], $config['mpdmixer']);
		$gainDb = $mappedDbVol == '' ? '0' : rtrim($mappedDbVol, 'dB');
	}

	file_put_contents(PEPPY_GAIN_DB_FILE, $gainDb);
}

$dbh = sqlConnect();
$cardNum = getGainConfig($dbh)['cardnum'];

// Publish once up front: alsactl only reports changes, and PeppyMeter falls back to
// unity gain while the file is missing (full-scale needles against an attenuated DAC)
publishGainDb($dbh);

while (true) {
	// Blocks, printing a line per control change
	$monitor = popen('alsactl monitor hw:' . $cardNum . ' 2>/dev/null', 'r');
	if ($monitor !== false) {
		while (fgets($monitor) !== false) {
			publishGainDb($dbh);
		}
		pclose($monitor);
	}
	// Only reached if the card went away (USB DAC unplugged). The worker restarts us
	// on a card change; keep retrying so a replug alone is enough to recover.
	sleep(PEPPY_GAIN_MON_RETRY);
}
