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
$onoff = isset($argv[2]) ? $argv[2] : '';

switch ($option) {
	case '--bluetooth':
		onoffBluetooth($onoff);
		break;
	case '--airplay':
		onoffAirPlay($onoff);
		break;
	case '--spotify':
		onoffSpotify($onoff);
		break;
	case '--deezer':
		onoffDeezer($onoff);
		break;
	case '--squeezelite':
		onoffSqueezelite($onoff);
		break;
	case '--plexamp':
		onoffPlexamp($onoff);
		break;
	case '--roonbridge':
		onoffRoonBridge($onoff);
		break;
	case '--upnp':
		onoffUPnP($onoff);
		break;
	case '--help':
		if (posix_getuid() != 0) {
			fwrite(STDERR, "This command requires sudo to print the help\n");
			return;
		}
		//[--bluetooth | --airplay | --spotify | --deezer | --upnp | --squeezelite | --plexamp | --roonbridge]
		$btArg = $_SESSION['feat_bitmask'] & FEAT_BLUETOOTH ? "--bluetooth\tTurn Bluetooth On/Off\n" : "";
		$apArg = $_SESSION['feat_bitmask'] & FEAT_AIRPLAY ? " --airplay\tTurn AirPlay On/Off\n" : "";
		$spArg = $_SESSION['feat_bitmask'] & FEAT_SPOTIFY ? " --spotify\tTurn Spotify Connect On/Off\n" : "";
		$dzArg = $_SESSION['feat_bitmask'] & FEAT_DEEZER ? " --deezer\tTurn Deezer ConnectOn/Off\n" : "";
		$upArg = $_SESSION['feat_bitmask'] & FEAT_UPMPDCLI ? " --upnp\t\tTurn UPnP On/Off\n" : "";
		$slArg = $_SESSION['feat_bitmask'] & FEAT_SQUEEZELITE ? " --squeezelite\tTurn Squeezelite On/Off\n" : "";
		$paArg = $_SESSION['feat_bitmask'] & FEAT_PLEXAMP ? " --plexamp\tTurn Plexamp On/Off\n" : "";
		$rbArg = $_SESSION['feat_bitmask'] & FEAT_ROONBRIDGE ? " --roonbridge\tTurn RoonBridge On/Off\n" : "";
		$rendererList = ' '. $btArg . $apArg . $spArg . $dzArg . $upArg . $slArg . $paArg . $rbArg .
		" --help\t\tPrint this help text\n";
		echo
"Usage: renderer-onoff [OPTION] [on|off]
Moode renderer on|off utility
\n" .
 $rendererList;
 		break;
	default:
		echo "Missing option. Use sudo /var/www/util/renderer-onoff.php --help\n";
		break;
}

phpSession('close');

if (empty($onoff || $onoff != 'on' || $onoff != 'off')) {
	echo "Invalid or missing on/off param. Use sudo /var/www/util/renderer-onoff.php --help\n";
}

function onoffBluetooth($onoff) {
	if ($onoff == 'on' && $_SESSION['btsvc'] == '0') {
		phpSession('write', 'btsvc', '1');
		sysCmd('/var/www/util/vol.sh -restore');
		$status = startBluetooth();
		if ($status != 'started') {
			echo $status;
		}
	} else if ($onoff == 'off' && $_SESSION['btsvc'] == '1') {
		phpSession('write', 'btsvc', '0');
		stopBluetooth();
		sysCmd('/var/www/util/vol.sh -restore');
		phpSession('write', 'btactive', '0');
		sendFECmd('btactive0');
	}
}

function onoffAirPlay($onoff) {
	if ($onoff == 'on' && $_SESSION['airplaysvc'] == '0') {
		phpSession('write', 'airplaysvc', '1');
		startAirPlay();
	} else if ($onoff == 'off' && $_SESSION['airplaysvc'] == '1') {
		phpSession('write', 'airplaysvc', '0');
		stopAirPlay();
	}
}

function onoffSpotify($onoff) {
	if ($onoff == 'on' && $_SESSION['spotifysvc'] == '0') {
		phpSession('write', 'spotifysvc', '1');
		startSpotify();
	} else if ($onoff == 'off' && $_SESSION['spotifysvc'] == '1') {
		phpSession('write', 'spotifysvc', '0');
		stopSpotify();
	}
}

function onoffDeezer($onoff) {
	if ($onoff == 'on' && $_SESSION['deezersvc'] == '0') {
		phpSession('write', 'deezersvc', '1');
		startDeezer();
	} else if ($onoff == 'off' && $_SESSION['deezersvc'] == '1') {
		phpSession('write', 'deezersvc', '0');
		stopDeezer();
	}
}

function onoffUPnP($onoff) {
	if ($onoff == 'on' && $_SESSION['upnpsvc'] == '0') {
		phpSession('write', 'upnpsvc', '1');
		startUPnP();
	} else if ($onoff == 'off' && $_SESSION['upnpsvc'] == '1') {
		phpSession('write', 'upnpsvc', '0');
		stopUPnP();
	}
}

function onoffSqueezelite($onoff) {
	if ($onoff == 'on' && $_SESSION['slsvc'] == '0') {
		phpSession('write', 'slsvc', '1');
		startSqueezelite();
	} else if ($onoff == 'off' && $_SESSION['slsvc'] == '1') {
		phpSession('write', 'slsvc', '0');
		stopSqueezelite();
	}
}

function onoffPlexamp($onoff) {
	if ($onoff == 'on' && $_SESSION['pasvc'] == '0') {
		phpSession('write', 'pasvc', '1');
		startPlexamp();
	} else if ($onoff == 'off' && $_SESSION['pasvc'] == '1') {
		phpSession('write', 'pasvc', '0');
		stopPlexamp();
	}
}

function onoffRoonBridge($onoff) {
	if ($onoff == 'on' && $_SESSION['rbsvc'] == '0') {
		phpSession('write', 'rbsvc', '1');
		startRoonBridge();
	} else if ($onoff == 'off' && $_SESSION['rbsvc'] == '1') {
		phpSession('write', 'rbsvc', '0');
		stopRoonBridge();
	}
}
