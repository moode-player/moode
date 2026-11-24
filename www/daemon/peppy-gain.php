#!/usr/bin/php
<?php
/*
 * SPDX-License-Identifier: GPL-3.0-or-later
 * Copyright 2026 The moOde audio player project / Tim Curtis
 * Copyright 2026 @Gjuju (PR #773)
 *
 * Publish the hardware volume attenuation for PeppyMeter (volume.gain.db.source)
 * and the volume and text readouts (volume.text.source, text.source).
 *
 * peppyalsa taps the signal upstream of the DAC attenuator, so with a hardware
 * volume the VU needles ignore the knob. Republish the attenuation on every ALSA
 * control change - whatever moved it: the WebUI, vol.sh (USB knob, IR remote),
 * rotvol.sh, mpc or an external amixer.
 *
 * The readout is the volume reported by the REST API get_volume, refreshed on ALSA
 * control changes, MPD mixer events and renderer volume changes (rendvol.json).
 * The text readout fills the peppy_text template with the metadata of the active
 * source: the renderer metadata cache its overlay shows, else currentsong.txt.
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

function publishText($dbh) {
	$template = sqlQuery("SELECT value FROM cfg_system WHERE param='peppy_text'", $dbh)[0]['value'];
	$text = '';
	if ($template != '') {
		$metaFiles = array('aplactive' => APLMETA_CACHE_FILE, 'qbzactive' => QBZMETA_CACHE_FILE,
			'spotactive' => SPOTMETA_CACHE_FILE, 'solactive' => SOLMETA_CACHE_FILE, 'btactive' => BTMETA_CACHE_FILE);
		$renderer = getActiveRenderer();
		$current = array_map(function ($value) { return htmlspecialchars_decode((string)$value, ENT_NOQUOTES); },
			parseDelimFile((string)@file_get_contents(CURRENTSONG_TXT), '='));
		if (isset($metaFiles[$renderer])) {
			$meta = json_decode((string)@file_get_contents($metaFiles[$renderer]), true);
		} else {
			$meta = $renderer == '' ? $current : array();
			if ($renderer == '' && isset($meta['artist']) && $meta['artist'] == DEFAULT_STATION_NAME) {
				// Radio: the station name, as Playback shows it
				$meta['artist'] = isset($meta['album']) ? $meta['album'] : '';
			}
		}

		// {br}: a line break, spaces around it dropped
		$template = preg_replace('/\s*\{br\}\s*/', "\n", $template);
		$found = false;
		$text = preg_replace_callback('/\{(title|artist|album)\}/', function ($match) use ($meta, &$found) {
			$value = is_array($meta) && isset($meta[$match[1]]) ? trim($meta[$match[1]]) : '';
			$found = $found || $value != '';
			return $value;
		}, $template);
		if (!$found) {
			// No metadata: the renderer or input label, as on its overlay
			$text = $renderer != '' && isset($current['file']) ? $current['file'] : '';
		}
	}

	if ($text !== $GLOBALS['peppyText']) {
		file_put_contents(PEPPY_TEXT_FILE, $text);
		$GLOBALS['peppyText'] = $text;
	}
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
$peppyText = null;

// Publish once up front: alsactl only reports changes, and PeppyMeter falls back to
// unity gain while the file is missing (full-scale needles against an attenuated DAC)
publishGainDb($dbh);
// Retried on each select timeout until the web server answers (not up yet at boot)
$volumeOk = publishVolume();
publishText($dbh);

$streams = array('alsa' => false, 'mpd' => false, 'files' => false);
$retryAt = array('alsa' => 0, 'mpd' => 0, 'files' => 0);
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
				// Renderer volume and metadata caches, currentsong.txt: all in /var/local/www
				$streams[$name] = popen('inotifywait -m -q -e close_write,moved_to --format %f --include ' .
					escapeshellarg('(rendvol|aplmeta|qbzmeta|spotmeta|solmeta|btmeta)\\.json$|currentsong\\.txt$') . ' ' .
					dirname(RENDVOL_CACHE_FILE) . ' 2>/dev/null', 'r');
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
			$line = fgets($stream);
			$ok = $line !== false;
		}

		if ($ok) {
			if ($name == 'alsa') {
				publishGainDb($dbh);
			}
			if ($name != 'files' || trim($line) == basename(RENDVOL_CACHE_FILE)) {
				$volumeOk = publishVolume();
			}
			if ($name == 'files') {
				publishText($dbh);
			}
		} else {
			$name == 'mpd' ? fclose($stream) : pclose($stream);
			$streams[$name] = false;
		}
	}
}
