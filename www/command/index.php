<?php
/*
 * SPDX-License-Identifier: GPL-3.0-or-later
 * Copyright 2014 The moOde audio player project / Tim Curtis
 * Copyright 2013 The tsunamp player ui / Andrea Coiutti & Simone De Gregori
*/

require_once __DIR__ . '/../inc/cdsp.php';
require_once __DIR__ . '/../inc/common.php';
require_once __DIR__ . '/../inc/mpd.php';
require_once __DIR__ . '/../inc/multiroom.php';
require_once __DIR__ . '/../inc/queue.php';
require_once __DIR__ . '/../inc/session.php';
require_once __DIR__ . '/../inc/sql.php';

if (!isset($_GET['cmd']) || empty($_GET['cmd'])) {
	echo 'Command is missing';
	exit(1);
}

// DEBUG:
//workerLog('index.php: $_GET[cmd]=' . $_GET['cmd']);

chkValue('cmd', $_GET['cmd']);
$dbh = sqlConnect();
$cmd = explode(' ', $_GET['cmd']);

switch ($cmd[0]) {
	// REST API commands
	case 'get_currentsong':
		echo json_encode(parseDelimFile(file_get_contents('/var/local/www/currentsong.txt'), "="), JSON_FORCE_OBJECT);
		break;
	case 'get_output_format':
		_openSessionReadOnly($dbh);
		echo json_encode(array('format' => getALSAOutputFormat()), JSON_FORCE_OBJECT);
		break;
	case 'get_volume':
		$volume = sysCmd('/var/www/util/vol.sh')[0];
		$mute = sqlQuery("SELECT value FROM cfg_system WHERE param='volmute'", $dbh)[0]['value'];
		echo json_encode(array('volume' => $volume, 'muted' => ($mute == '0' ? 'no' : 'yes')));
		break;
	case 'set_volume': // N | -mute | -up N | -dn N
		$rendererActive = chkRendererActive();
		if ($rendererActive === true) {
			echo json_encode(array('alert' => 'Volume cannot be changed while a renderer is active'));
		} else {
			$volCmd = getArgs($cmd);
			$result = sysCmd('/var/www/util/vol.sh' . $volCmd);
			// Receiver(s) volume
			_openSessionReadOnly($dbh);
			if ($_SESSION['multiroom_tx'] == 'On') {
				if ($volCmd == ' -mute') {
					$rxVolCmd = '-mute';
				} else if (str_contains($volCmd, '-')) {
					$rxVolCmd = trim($volCmd); // -up N | -dn N
				} else {
					$volDiff = $_SESSION['volknob'] - trim($volCmd); // N
					$rxVolCmd = $volDiff < 0 ? '-up ' . abs($volDiff) : '-dn ' . $volDiff;
				}
				updReceiverVol($rxVolCmd, true); // True = Master volume change
			}

			$volume = sysCmd('/var/www/util/vol.sh')[0];
			$mute = sqlQuery("SELECT value FROM cfg_system WHERE param='volmute'", $dbh)[0]['value'];
			echo json_encode(array('volume' => $volume, 'muted' => ($mute == '0' ? 'no' : 'yes')));
		}
		break;
	case 'clear_queue':
		$sock = getMpdSock('command/index.php');
		sendMpdCmd($sock, 'clear');
		$resp = readMpdResp($sock);
		break;
	case 'play_item':
	case 'play_item_next':
		$item = trim(getArgs($cmd));

		// Turn off auto-shuffle
		_openSessionReadOnly($dbh);
		if ($_SESSION['ashuffle'] == '1') {
		    turnOffAutoShuffle($sock);
		}

		// Parse the item type
		if (str_contains($item, 'RADIO') || str_contains($item, 'http')) {
			$itemType = 'Station';
		} else if (str_contains($item, '.m3u')) {
			$itemType = 'Playlist';
		} else if (!(str_contains($item, 'RADIO') || str_contains($item, '.'))) {
			$itemType = 'Album';
		} else {
			$itemType = 'Track';
		}
		debugLog($cmd[0] . ': ' . $itemType . '|' . $item);

		// Process by itemType
		if ($itemType == 'Playlist' || $itemType == 'Album') {
			// Clear Queue
			$sock = getMpdSock('command/index.php');
			sendMpdCmd($sock, 'clear');
			$resp = readMpdResp($sock);

			// Submit POST to queue.php
			$item = $itemType == 'Playlist' ? rtrim($item, '.m3u') : $item;
			$url = 'http://' . $_SESSION['ipaddress'] . '/command/queue.php?cmd=' . $cmd[0];
			$options = array(
				'http' => array(
					'header'  => 'Content-type: application/x-www-form-urlencoded',
					'method'  => 'POST',
					'content' => http_build_query(array('path' => $item))
				)
			);
			$context  = stream_context_create($options);
			$result = file_get_contents($url, false, $context);
			if ($result === false) {
				debugLog($cmd[0] . ': file_get_contents(' . $url . ') failed');
			} else {
				debugLog($cmd[0] . ': file_get_contents(' . $url . ') succeeded');
			}
		} else if ($itemType == 'Station' || $itemType == 'Track') {
			// Search the Queue for the item
			$sock = getMpdSock('command/index.php');
			$search = strpos($item, 'RADIO') !== false ? parseDelimFile(file_get_contents(MPD_MUSICROOT . $item), '=')['File1'] : $item;
			$result = findInQueue($sock, 'file', $search);
			if (isset($result['Pos'])) {
				// Play already Queued item
				sendMpdCmd($sock, 'play ' . $result['Pos']);
				$resp = readMpdResp($sock);
			} else {
				// Otherwise play the item after adding it to the Queue
				$status = getMpdStatus($sock);
				$cmds = array(addItemToQueue($item));
				if ($cmd[0] == 'play_item_next') {
					$pos = isset($status['song']) ? $status['song'] + 1 : $status['playlistlength'];
					array_push($cmds, 'move ' . $status['playlistlength'] . ' ' . $pos);
				} else {
					$pos = $status['playlistlength'];
				}
				array_push($cmds, 'play ' . $pos);
				chainMpdCmds($sock, $cmds);
			}
		}
		echo json_encode(array('info' => 'OK'));
		break;
	case 'toggle_play_pause':
		$sock = getMpdSock('command/index.php');
		$status = getMpdStatus($sock);
		$currentSong = getCurrentSong($sock);
		if (substr($currentSong['file'], 0, 4) == 'http' && !isset($status['duration'])) {
			// Radio station
			$cmd = $status['state'] == 'play' ? 'stop' : 'play';
		} else {
			// Song file
			$cmd = $status['state'] == 'play' ? 'pause' : 'play';
		}
		sendMpdCmd($sock, $cmd);
		echo json_encode(array('state' => $cmd));
		break;
	case 'get_cdsp_config':
		_openSessionReadOnly($dbh);
		echo json_encode(array('config' => $_SESSION['camilladsp']));
		break;
	case 'set_cdsp_config':
		$newConfig = trim(getArgs($cmd));
		if (!empty($newConfig)) {
			_openSession($dbh);
			$currentConfig = $_SESSION['camilladsp'];
			$cdsp = new CamillaDsp($_SESSION['camilladsp'], $_SESSION['cardnum'], $_SESSION['camilladsp_quickconv']);
			// Validate arg
			$validConfigName = false;
			$configs = $cdsp->getAvailableConfigs();
			foreach ($configs as $configFile => $configName) {
				if ($newConfig == $configName) {
					$validConfigName = true;
					break;
				}
			}
			if ($validConfigName) {
				$newConfig = strtolower($newConfig) == 'off' ? 'off' : $newConfig . '.yml';
				phpSession('write', 'camilladsp', $newConfig);
				$cdsp->selectConfig($newConfig);
				$cdsp->updCDSPConfig($newConfig, $currentConfig, $cdsp);
				phpSession('close');
				echo json_encode(array('config' => $_SESSION['camilladsp']));
			} else {
				echo json_encode(array('alert' => 'Invalid Configuration name'));
			}
		} else {
			echo json_encode(array('alert' => 'Argument missing: Configuration name'));
		}
		break;
	case 'get_receiver_status': // -rx
		$result = sysCmd('/var/www/util/trx-control.php -rx')[0];
		echo json_encode(array('status' => $result));
		break;
	case 'set_receiver_onoff': // -on | -off
		// Parse on/off state from status
		$status = sysCmd('/var/www/util/trx-control.php -rx')[0];
		$onoffState = explode(',', $status)[1];
		// Process command
		$onoffCmd = trim(getArgs($cmd));
		$onoffCmd = $onoffCmd == '-on' ? 'On' : 'Off';
		if ($onoffCmd != $onoffState) {
			$result = sysCmd('/var/www/util/trx-control.php -rx ' . $onoffCmd)[0];
			$result = sysCmd('/var/www/util/trx-control.php -rx')[0];
			echo json_encode(array('status' => $result));
		} else {
			echo json_encode(array('alert' => 'Receiver is already ' . $onoffCmd));
		}
		break;
	case 'set_coverview': // -on | -off
		$cvState = sysCmd('/var/www/util/coverview.php' . getArgs($cmd))[0];
		echo json_encode(array('info' => $cvState));
		break;
	case 'upd_library':
		$result = sysCmd('/var/www/util/libupd-submit.php');
		echo json_encode(array('info' => 'Library update submitted'));
		break;
	case 'restart_renderer': // --bluetooth | --airplay | --spotify | --pleezer | --squeezelite | --roonbridge
		$result = sysCmd('moodeutl -R' . getArgs($cmd));
		echo $result[0] == 'Renderer restarted' ?
			json_encode(array('info' => 'Renderer restart submitted')) :
			json_encode(array('alert' => 'Missing or invalid argument'));
		break;
	case 'renderer_onoff': // --bluetooth | --airplay | --spotify | --pleezer | --squeezelite | --roonbridge [on|off]
		$result = sysCmd('moodeutl -Ro' . getArgs($cmd));
		echo str_contains($result[0], 'Renderer turned') ?
			json_encode(array('info' => 'Renderer ' . getArgs($cmd) . ' submitted')) :
			json_encode(array('alert' => 'Missing or invalid argument'));
		break;
	case 'set_display': // webui | peppy | toggle
		$result = sysCmd('moodeutl --setdisplay' . getArgs($cmd));
		echo empty($result) ?
			json_encode(array('info' => 'Set display to ' . getArgs($cmd) . ' submitted')) :
			json_encode(array('alert' => 'Missing or invalid argument'));
		break;

	// API commands
	case 'trx_control': // Up to 3 args, result is status or empty, used by renderer event scripts
		$result = sysCmd('/var/www/util/trx-control.php' . getArgs($cmd))[0];
		echo $result;
		break;

	// MPD commands
	default:
		if (false === ($sock = openMpdSock('localhost', 6600))) {
			debugLog('command/index.php: Connection to MPD failed');
		} else {
			sendMpdCmd($sock, $_GET['cmd']);
			$resp = readMpdResp($sock);
			echo json_encode(parseMpdRespAsJSON($resp), JSON_FORCE_OBJECT);
		}
}

// Close MPD socket
if (isset($sock) && $sock !== false) {
	closeMpdSock($sock);
}

function getArgs($cmd) {
	$argCount = count($cmd);

	if ($argCount > 1) {
		for ($i = 0; $i < $argCount; $i++) {
			chkValue('cmd', $cmd[$i + 1]);
			$args .= ' ' . $cmd[$i + 1];
		}
	} else {
		$args = '';
	}

	return $args;
}

// We use these session functions instead of phpSession() because CLI based REST
// commands sent for example by curl don't send the PHP session cookie containing
// the sessionid as does a Browser. This results in bogus empty session files
// being created in /var/local/php/ and no access to session vars
function _openSession($dbh) {
	$sessionID = sqlRead('cfg_system', $dbh, 'sessionid')[0]['value'];
	session_id($sessionID);
	session_start();
}
function _openSessionReadOnly($dbh) {
	$sessionID = sqlRead('cfg_system', $dbh, 'sessionid')[0]['value'];
	session_id($sessionID);
	session_start();
	session_write_close();
}
