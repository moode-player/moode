#!/usr/bin/php
<?php
/*
 * SPDX-License-Identifier: GPL-3.0-or-later
 * Copyright 2026 The moOde audio player project / Tim Curtis
 * Copyright 2026 @Gjuju (PR #773)
 *
 * Publish the hardware volume attenuation for PeppyMeter (volume.gain.db.source)
 * and the volume readout (volume.text.source).
 *
 * peppyalsa taps the signal upstream of the DAC attenuator, so with a hardware
 * volume the VU needles ignore the knob. Republish the attenuation on every ALSA
 * control change - whatever moved it: the WebUI, vol.sh (USB knob, IR remote),
 * rotvol.sh, mpc or an external amixer.
 *
 * The readout is the volume reported by the REST API get_volume, refreshed on ALSA
 * control changes, MPD mixer events and renderer volume changes (rendvol.json).
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

function publishVolume() {
	$state = json_decode((string)@file_get_contents('http://127.0.0.1/command/?cmd=get_volume', false,
		stream_context_create(array('http' => array('timeout' => 5)))), true);
	if (!is_array($state)) {
		return false;
	}
	if ($state['muted'] == 'yes') {
		$volume = '--';
	} else {
		$volume = (string)$state['volume'];
	}

	// Write only on change: PeppyMeter re-reads the file whenever it is written
	if ($volume !== $GLOBALS['peppyVolume']) {
		file_put_contents(PEPPY_VOLUME_FILE, $volume);
		$GLOBALS['peppyVolume'] = $volume;
	}

	return true;
}

function openMpdIdle() {
	$sock = openMpdSock('localhost', 6600);
	if ($sock !== false) {
		sendMpdCmd($sock, 'idle mixer');
	}

	return $sock;
}

$dbh = sqlConnect();
$cardNum = getGainConfig($dbh)['cardnum'];
$peppyVolume = null;

// Publish once up front: alsactl only reports changes, and PeppyMeter falls back to
// unity gain while the file is missing (full-scale needles against an attenuated DAC)
publishGainDb($dbh);
// Retried on each select timeout until the web server answers (not up yet at boot)
$volumeOk = publishVolume();

$streams = array('alsa' => false, 'mpd' => false, 'rendvol' => false);
$retryAt = array('alsa' => 0, 'mpd' => 0, 'rendvol' => 0);
while (true) {
	// (Re)open what is missing: the card went away (USB DAC unplugged), MPD restarted,
	// or inotifywait was killed (the LCD updater job runs killall inotifywait)
	foreach ($streams as $name => $stream) {
		if ($stream === false && time() >= $retryAt[$name]) {
			if ($name == 'alsa') {
				$streams[$name] = popen('alsactl monitor hw:' . $cardNum . ' 2>/dev/null', 'r');
			} else if ($name == 'mpd') {
				$streams[$name] = openMpdIdle();
			} else {
				$streams[$name] = popen('inotifywait -m -q -e close_write,moved_to --include ' .
					escapeshellarg('rendvol\\.json$') . ' ' . dirname(RENDVOL_CACHE_FILE) . ' 2>/dev/null', 'r');
			}
			$retryAt[$name] = time() + PEPPY_GAIN_MON_RETRY;
		}
	}

	$read = array_filter($streams);
	$write = $except = null;
	if (empty($read)) {
		sleep(PEPPY_GAIN_MON_RETRY);
		continue;
	}
	if (!stream_select($read, $write, $except, PEPPY_GAIN_MON_RETRY)) {
		if (!$volumeOk) {
			$volumeOk = publishVolume();
		}
		continue;
	}

	foreach ($read as $stream) {
		$name = array_search($stream, $streams, true);
		if ($name == 'mpd') {
			// changed: lines then OK, then wait for the next event
			readMpdResp($stream);
			$ok = !feof($stream);
			if ($ok) {
				sendMpdCmd($stream, 'idle mixer');
			}
		} else {
			$ok = fgets($stream) !== false;
		}

		if ($ok) {
			if ($name == 'alsa') {
				publishGainDb($dbh);
			}
			$volumeOk = publishVolume();
		} else {
			$name == 'mpd' ? fclose($stream) : pclose($stream);
			$streams[$name] = false;
		}
	}
}
