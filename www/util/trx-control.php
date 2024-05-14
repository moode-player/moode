#!/usr/bin/php
<?php
/*
 * SPDX-License-Identifier: GPL-3.0-or-later
 * Copyright 2014 The moOde audio player project / Tim Curtis
*/

require_once __DIR__ . '/../inc/common.php';
require_once __DIR__ . '/../inc/multiroom.php';
require_once __DIR__ . '/../inc/session.php';
require_once __DIR__ . '/../inc/sql.php';

session_id(phpSession('get_sessionid'));
phpSession('open');

$option = isset($argv[1]) ? $argv[1] : '';

switch ($option) {
	case '-rx':
		if (isset($argv[2])) {
			rxOnOff($argv[2]);
			$status = '';
		} else {
			$status = rxStatus();
		}
		break;
	case '-tx':
		$status = txStatus();
		break;
	case '-all':
		$status = allStatus();
		break;
	case '-set-mpdvol':
		sysCmd('/var/www/util/vol.sh ' . $argv[2] . (isset($argv[3]) ? ' ' . $argv[3] : ''));
		$result = sqlQuery("SELECT value FROM cfg_system WHERE param = 'volknob'", sqlConnect());
		$_SESSION['volknob'] = $result[0]['value'];
		$status = 'Volume ' . $result[0]['value'];
		break;
	case '-set-mpdvol-from-master':
		$rxStatusParts = explode(',', rxStatus());
		// rx, On/Off/Disabled/Unknown, volume, volume_mute_1/0, mastervol_opt_in_1/0, hostname
		if ($rxStatusParts[4] == '1') { // Master volume opt in?
			sysCmd('/var/www/util/vol.sh ' . $argv[2] . (isset($argv[3]) ? ' ' . $argv[3] : ''));
			$result = sqlQuery("SELECT value FROM cfg_system WHERE param = 'volknob'", sqlConnect());
			$_SESSION['volknob'] = $result[0]['value'];
			$status = 'Volume ' . $result[0]['value'];
		} else {
			$status = 'Master volume opt-in is No';
		}
		break;
	case '-set-mpdmute':
		sysCmd('/var/www/util/vol.sh -mute');
		$result = sqlQuery("SELECT value FROM cfg_system WHERE param = 'volknob'", sqlConnect());
		$status = 'Volume ' . $result[0]['value'];
		break;
	// This is used to set rx to 0dB when AirPlay or Spotify connects to Sender
	case '-set-alsavol':
		$result = sqlQuery("SELECT value FROM cfg_multiroom WHERE param = 'rx_alsa_volume_max'", sqlConnect());
		sysCmd('/var/www/util/sysutil.sh set-alsavol "' . $_SESSION['amixname'] . '" ' . $result[0]['value']);
		$status = '';

		/*DELETE:if (isset($argv[2])) {
			sysCmd('/var/www/util/sysutil.sh set-alsavol "' . $_SESSION['amixname'] . '" ' . $argv[2]);
			if ($_SESSION['multiroom_rx'] == 'On') {
				sysCmd('/var/www/util/sysutil.sh set-alsavol "' . $_SESSION['amixname'] . '" ' . $argv[2]);
			}
			$status = '';
		} else {
			$status = 'Missing option';
		}*/
		break;
	default:
		$status = 'Missing option';
		break;
}

if (phpSession('get_status') == PHP_SESSION_ACTIVE) {
	phpSession('close');
}

if ($status != '') {
	echo $status;
	//workerLog('Args: ' . $argv[1] . ' | ' . $argv[2]);
	//workerLog('Stat: ' . $status);
}
exit(0);

function rxOnOff($onoff) {
	// Don't allow CamillDSP volume
	//if ($_SESSION['mpdmixer'] == 'hardware' || $_SESSION['mpdmixer'] == 'none') {
	if ($_SESSION['mpdmixer'] != 'null') {
		phpSession('write', 'multiroom_rx', $onoff);
		$onoff == 'On' ? startMultiroomReceiver() : stopMultiroomReceiver();
	}
}

function rxStatus() {
	$dbh = sqlConnect();
	$mvOptIn = sqlQuery("SELECT value FROM cfg_multiroom WHERE param = 'rx_mastervol_opt_in'", $dbh);
	$volMute = sqlQuery("SELECT value FROM cfg_system WHERE param = 'volmute'", $dbh);
	// hardware		$_SESSION['volknob']
	// software		0dB
	// none			0dB
	// null			?
	$volume = $_SESSION['mpdmixer'] == 'hardware' ? $_SESSION['volknob'] :
		(($_SESSION['mpdmixer'] == 'software' || $_SESSION['mpdmixer'] == 'none') ? '0dB' : '?');
	return
		'rx' . ',' . 						// Receiver
		$_SESSION['multiroom_rx'] . ',' . 	// Status: On/Off/Disabled/Unknown
		$volume . ',' .						// Volume
		$volMute[0]['value'] . ',' . 		// Mute state: 1/0
		$mvOptIn[0]['value'] . ',' . 		// Master volume opt-in: 1/0
		$_SESSION['hostname'];				// Hostname from System Config entry
}

function txStatus() {
	$volume = $_SESSION['mpdmixer'] == 'none' ? '0dB' : $_SESSION['volknob'];
	return 'tx' . ',' . $_SESSION['multiroom_tx'] . ',' . $volume . ',' . $_SESSION['volmute'];
}

function allStatus() {
	return rxStatus() . ',' . txStatus();
}
