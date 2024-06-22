#!/usr/bin/php
<?php
/*
 * SPDX-License-Identifier: GPL-3.0-or-later
 * Copyright 2014 The moOde audio player project / Tim Curtis
*/

require_once __DIR__ . '/../inc/common.php';
require_once __DIR__ . '/../inc/mpd.php';
require_once __DIR__ . '/../inc/multiroom.php';
require_once __DIR__ . '/../inc/renderer.php';
require_once __DIR__ . '/../inc/session.php';
require_once __DIR__ . '/../inc/sql.php';

session_id(phpSession('get_sessionid'));
phpSession('open');

$option = isset($argv[1]) ? $argv[1] : '';

switch ($option) {
	case '--bluetooth':
		restartBluetooth();
		break;
	case '--airplay':
		restartAirPlay();
		break;
	case '--spotify':
		restartSpotify();
		break;
	case '--squeezelite':
		restartSqueezelite();
		break;
	case '--plexamp':
		restartPlexamp();
		break;
	case '--roonbridge':
		restartRoonBridge();
		break;
	default:
		echo
"Usage: restart-renderer [OPTION]
Moode renderer restarter

With no OPTION print the help text and exit.

 --bluetooth\tRestart Bluetooth
 --airplay\tRestart AirPlay
 --spotify\tRestart Spotify Connect
 --squeezelite\tRestart Squeezelite
 --plexamp\tRestart Plexamp
 --roonbridge\tRestart RoonBridge\n";
		break;
}

phpSession('close');

function restartBluetooth() {
	stopBluetooth();
	sysCmd('/var/www/util/vol.sh -restore');
	// Reset to inactive
	phpSession('write', 'btactive', '0');
	// Dismiss active screen
	sendFECmd('btactive0');

	// Restore MPD volume and start Bluetooth
	sysCmd('/var/www/util/vol.sh -restore');
	$status = startBluetooth();
	if ($status != 'started') {
		echo $status;
	}
}

function restartAirPlay() {
	stopAirPlay();
	startAirPlay();
}

function restartSpotify() {
	stopSpotify();
	startSpotify();
}

function restartSqueezelite() {
	stopSqueezelite();
	phpSession('write', 'rsmaftersl', 'No');
	startSqueezelite();
}

function restartPlexamp() {
	stopPlexamp();
	startPlexamp();
}

function restartRoonBridge() {
	stopRoonBridge();
	startRoonBridge();
}
