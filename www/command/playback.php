<?php
/*
 * SPDX-License-Identifier: GPL-3.0-or-later
 * Copyright 2014 The moOde audio player project / Tim Curtis
*/

require_once __DIR__ . '/../inc/common.php';
require_once __DIR__ . '/../inc/mpd.php';
require_once __DIR__ . '/../inc/multiroom.php';
require_once __DIR__ . '/../inc/music-library.php';
require_once __DIR__ . '/../inc/session.php';
require_once __DIR__ . '/../inc/sql.php';

$dbh = sqlConnect();
$sock = getMpdSock('command/playback.php');

chkVariables($_GET);
chkVariables($_POST);

switch ($_GET['cmd']) {
	 // Called from function setVolume() in playelib.js
	case 'upd_volume':
		// Local volume
		phpSession('open');
		$currentVol = $_SESSION['volknob']; // Save for Receiver

		if ($_POST['event'] != 'mute') {
			phpSession('write', 'volknob', $_POST['volknob']);
		}
		phpSession('close');

		if ($_SESSION['mpdmixer'] == 'hardware') {
			sysCmd('amixer -M -c ' . $_SESSION['cardnum'] . ' sset "' . $_SESSION['amixname'] . '" ' . $_POST['volknob'] . '%' );
		} else {
			sendMpdCmd($sock, 'setvol ' . $_POST['volknob']);
			$resp = readMpdResp($sock);
		}

		// Receiver(s) volume
		if ($_SESSION['multiroom_tx'] == 'On') {
			$volDiff = $currentVol - $_POST['volknob'];

			if ($_POST['event'] == 'mute' || $_POST['event'] == 'unmute') {
				$rxVolCmd = '-mute'; // Toggle mute on/off
			} else if ($volDiff == 0) {
				$rxVolCmd = $_POST['volknob'];
			} else {
				$rxVolCmd = $volDiff < 0 ? '-up ' . abs($volDiff) : '-dn ' . $volDiff;
			}

			updReceiverVol($rxVolCmd, true); // True = Master volume change
		}

		echo json_encode('OK');
		break;
	case 'mute_rx_vol':
		phpSession('open_ro');
		updReceiverVol('-mute');
		echo json_encode('OK');
		break;
	case 'toggle_ashuffle':
		phpSession('open');
		phpSession('write', 'ashuffle', $_POST['toggle_value']);
		phpSession('close');
		$_POST['toggle_value'] == '1' ? startAutoShuffle() : stopAutoShuffle();
		break;
	case 'toggle_coverview':
		phpSession('open');
		$_SESSION['toggle_coverview'] = $_SESSION['toggle_coverview'] == '-off' ? '-on' : '-off';
		phpSession('close');
		$result = sysCmd('/var/www/util/coverview.php ' . $_SESSION['toggle_coverview']);
		break;
	case 'get_mpd_status':
		echo json_encode(getMpdStatus($sock));
		break;
	case 'reset_screen_saver':
		if (submitJob($_GET['cmd'])) {
			echo json_encode('job submitted');
		} else {
			echo json_encode('worker busy');
		}
		break;
	case 'upd_toggle_coverview':
		phpSession('open');
		$_SESSION['toggle_coverview'] = $_POST['toggle_value'];
		phpSession('close');
		break;
	case 'upd_clock_radio':
		if (submitJob($_GET['cmd'])) {
			echo json_encode('job submitted');
		} else {
			echo json_encode('worker busy');
		}
		break;
	case 'set_bg_image':
		if (submitJob($_GET['cmd'], $_POST['blob'])) {
			echo json_encode('job submitted');
		} else {
			echo json_encode('worker busy');
		}
		break;
	case 'remove_bg_image':
		sysCmd('rm /var/local/www/imagesw/bgimage.jpg');
		echo json_encode('OK');
		break;
	case 'get_play_history':
		echo json_encode(getPlayHistory(shell_exec('cat /var/log/moode_playhistory.log')));
		break;
	default:
		echo 'Unknown command';
		break;
}

// Close MPD socket
if (isset($sock) && $sock !== false) {
	closeMpdSock($sock);
}

// parse play history log
function getPlayHistory($resp) {
	if (is_null($resp) ) {
		return 'getPlayHistory(): Response is null';
	}
	else {
		$array = array();
		$line = strtok($resp, "\n");
		$i = 0;

		while ( $line ) {
			$array[$i] = $line;
			$i++;
			$line = strtok("\n");
		}
	}

	return $array;
}
