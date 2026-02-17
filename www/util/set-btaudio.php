#!/usr/bin/php
<?php
/*
 * SPDX-License-Identifier: GPL-3.0-or-later
 * Copyright 2026 The moOde audio player project / Tim Curtis
*/

require_once __DIR__ . '/../inc/audio.php';
require_once __DIR__ . '/../inc/common.php';
require_once __DIR__ . '/../inc/mpd.php';
require_once __DIR__ . '/../inc/session.php';
require_once __DIR__ . '/../inc/sql.php';

if (posix_getuid() != 0) {
	fwrite(STDERR, "Use sudo to run this script\n");
	exit;
}
if (!isset($argv[1]) || $argv[1] == '--help' || ($argv[1] != '--local' && $argv[1] != '--btspeaker')) {
	echo 'Usage: set-btaudio.php [--local|--btspeaker <MAC address>]' . "\n";
	exit;
} else if ($argv[1] == '--btspeaker' && !isset($argv[2])) {
	echo 'Missing MAC address' . "\n";
	exit;
}
$output = $argv[1] == '--local' ? 'Local' : 'Bluetooth';

// Update configs
session_id(phpSession('get_sessionid'));
phpSession('open');

if ($output == 'Bluetooth') {
	if ($_SESSION['audioout'] != 'Bluetooth') {
		phpSession('write', 'audioout', $output);
		setAudioOut($output);
		// Update MAC address
		sysCmd("sed -i '/device/c\device \"" . $argv[2] . "\"' " . ALSA_PLUGIN_PATH . '/btstream.conf');
		// Connect device
		sysCmd('/var/www/util/blu-control.sh -C ' . '"' . $macAddr . '"');
	} else {
		echo 'Output is already set to Bluetooth' . "\n";
	}
} else if ($output == 'Local') {
	if ($_SESSION['audioout'] != 'Local') {
		phpSession('write', 'audioout', $output);
		setAudioOut($output);
		// Disconnect device
		sysCmd('/var/www/util/blu-control.sh -d ' . '"' . $macAddr . '"');
	} else {
		echo 'Output is already set to Local' . "\n";
	}
}

phpSession('close');
