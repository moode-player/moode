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
// This param is passed by moodeutl function stopAllRenderers()
// Ex: /var/www/util/restart-renderer.php --bluetooth --stop
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
	case '--sendspin':
		restartSendspin($stopOnly);
		break;
	case '--upnp':
		restartUPnP($stopOnly);
		break;
	case '--help':
		if (posix_getuid() != 0) {
			fwrite(STDERR, "This command requires sudo to print the help\n");
			return;
		}
		//[--bluetooth | --airplay | --spotify | --deezer | --upnp | --squeezelite | --plexamp | --roonbridge | --sendspin]
		$btArg = $_SESSION['feat_bitmask'] & FEAT_BLUETOOTH ? "--bluetooth\tRestart Bluetooth\n" : "";
		$apArg = $_SESSION['feat_bitmask'] & FEAT_AIRPLAY ? " --airplay\tRestart AirPlay\n" : "";
		$spArg = $_SESSION['feat_bitmask'] & FEAT_SPOTIFY ? " --spotify\tRestart Spotify Connect\n" : "";
		$dzArg = $_SESSION['feat_bitmask'] & FEAT_DEEZER ? " --deezer\tRestart Deezer Connect\n" : "";
		$upArg = $_SESSION['feat_bitmask'] & FEAT_UPMPDCLI ? " --upnp\t\tRestart UPnP\n" : "";
		$slArg = $_SESSION['feat_bitmask'] & FEAT_SQUEEZELITE ? " --squeezelite\tRestart Squeezelite\n" : "";
		$paArg = $_SESSION['feat_bitmask'] & FEAT_PLEXAMP ? " --plexamp\tRestart Plexamp\n" : "";
		$rbArg = $_SESSION['feat_bitmask'] & FEAT_ROONBRIDGE ? " --roonbridge\tRestart RoonBridge\n" : "";
		$ssArg = $_SESSION['feat_bitmask'] & FEAT_SENDSPIN ? " --sendspin\tRestart Sendspin\n" : "";
		$rendererList = ' '. $btArg . $apArg . $spArg . $dzArg . $upArg . $slArg . $paArg . $rbArg . $ssArg .
		" --help\t\tPrint this help text\n";
		echo
"Usage: restart-renderer [OPTION] [--stop]
Moode renderer restart utility

With --stop the renderer is stopped but not started.\n" .
 $rendererList;
 		break;
	default:
		echo "Missing option. Use sudo /var/www/util/restart-renderer.php --help\n";
		break;
}

phpSession('close');

function restartBluetooth($stopOnly) {
	stopBluetooth();
	if ($stopOnly === false) {
		// Restore MPD volume
		sysCmd('/var/www/util/vol.sh -restore');
		// Reset to inactive
		phpSession('write', 'btactive', '0');
		// Dismiss active screen
		sendFECmd('btactive0');
		// Start Bluetooth
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

function restartUPnP($stopOnly) {
	stopUPnP();
	if ($stopOnly === false) {
		startUPnP();
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

function restartSendspin($stopOnly) {
	stopSendspin();
	if ($stopOnly === false) {
		startSendspin();
	}
}
