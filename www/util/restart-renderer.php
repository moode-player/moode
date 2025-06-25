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
$stopOnly = (isset($argv[2]) && $argv[2] == '--stop') ? true : false;

switch ($option) {
	case '--bluetooth':
		restartBluetooth($stopOnly);
		break;
	case '--airplay':
		restartAirPlay($stopOnly);
		break;
	case '--spotify':
		restartSpotify($stopOnly);
		break;
	case '--deezer':
		restartDeezer($stopOnly);
		break;
	case '--squeezelite':
		restartSqueezelite($stopOnly);
		break;
	case '--plexamp':
		restartPlexamp($stopOnly);
		break;
	case '--roonbridge':
		restartRoonBridge($stopOnly);
		break;
	default:
		//[--bluetooth | --airplay | --spotify | --deezer | --squeezelite | --plexamp | --roonbridge]
		$btArg = $_SESSION['feat_bitmask'] & FEAT_BLUETOOTH ? "--bluetooth\tRestart Bluetooth\n" : "";
		$apArg = $_SESSION['feat_bitmask'] & FEAT_AIRPLAY ? " --airplay\tRestart AirPlay\n" : "";
		$spArg = $_SESSION['feat_bitmask'] & FEAT_SPOTIFY ? " --spotify\tRestart Spotify Connect\n" : "";
		$dzArg = $_SESSION['feat_bitmask'] & FEAT_DEEZER ? " --deezer\tRestart Deezer Connect\n" : "";
		$slArg = $_SESSION['feat_bitmask'] & FEAT_SQUEEZELITE ? " --squeezelite\tRestart Squeezelite\n" : "";
		$paArg = $_SESSION['feat_bitmask'] & FEAT_PLEXAMP ? " --plexamp\tRestart Plexamp\n" : "";
		$rbArg = $_SESSION['feat_bitmask'] & FEAT_ROONBRIDGE ? " --roonbridge\tRestart RoonBridge\n" : "";
		$rendererList = $btArg . $apArg . $spArg . $dzArg . $slArg . $paArg . $rbArg;
		echo
"Usage: restart-renderer [OPTION] [--stop]
Moode renderer restarter

With no OPTION print the help text and exit.
With OPTION --stop the renderer is stopped but not started.

 $rendererList";
 		break;
}

phpSession('close');

function restartBluetooth($stopOnly) {
	stopBluetooth();
	if ($stopOnly === false) {
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
}

function restartAirPlay($stopOnly) {
	stopAirPlay();
	if ($stopOnly === false) {
		startAirPlay();
	}
}

function restartSpotify($stopOnly) {
	stopSpotify();
	if ($stopOnly === false) {
		startSpotify();
	}
}

function restartDeezer($stopOnly) {
	stopDeezer();
	if ($stopOnly === false) {
		startDeezer();
	}
}

function restartSqueezelite($stopOnly) {
	stopSqueezelite();
	phpSession('write', 'rsmaftersl', 'No');
	if ($stopOnly === false) {
		startSqueezelite();
	}
}

function restartPlexamp($stopOnly) {
	stopPlexamp();
	if ($stopOnly === false) {
		startPlexamp();
	}
}

function restartRoonBridge($stopOnly) {
	stopRoonBridge();
	if ($stopOnly === false) {
		startRoonBridge();
	}
}
