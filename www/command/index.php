<?php
/*
 * SPDX-License-Identifier: GPL-3.0-or-later
 * Copyright 2014 The moOde audio player project / Tim Curtis
 * Copyright 2013 The tsunamp player ui / Andrea Coiutti & Simone De Gregori
*/

require_once __DIR__ . '/../inc/common.php';
require_once __DIR__ . '/../inc/mpd.php';
require_once __DIR__ . '/../inc/multiroom.php';
require_once __DIR__ . '/../inc/session.php';
require_once __DIR__ . '/../inc/cdsp.php';
require_once __DIR__ . '/../inc/sql.php';

if (!isset($_GET['cmd']) || empty($_GET['cmd'])) {
	echo 'Command is missing';
	exit(1);
}

chkValue($_GET['cmd']);

// DEBUG:
//workerLog('index.php: cmd=' . $_GET['cmd']);

$cmd = explode(' ', $_GET['cmd']);
switch ($cmd[0]) {
	case 'get_currentsong':
		echo json_encode(parseDelimFile(file_get_contents('/var/local/www/currentsong.txt'), "="));
		break;
	case 'get_output_format':
		//phpSession('open_ro');
		openSessionReadOnly();
		echo json_encode(getALSAOutputFormat());
		break;
	case 'get_volume':
		$result = sysCmd('/var/www/util/vol.sh');
		echo $result[0];
		break;
	case 'set_volume': // N | -mute | -up N | -dn N
		$rendererActive = chkRendererActive();
		if ($rendererActive === true) {
			echo 'Volume cannot be changed while a renderer is active';
		} else {
			$volCmd = getArgs($cmd);
			$result = sysCmd('/var/www/util/vol.sh' . $volCmd);
			// Receiver(s) volume
			//phpSession('open_ro');
			openSessionReadOnly();
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
			echo 'OK';
		}
		break;
	case 'get_cdsp_config':
		phpSession('open_ro');
		echo $_SESSION['camilladsp'];
		break;
	case 'set_cdsp_config':
		$newConfig = trim(getArgs($cmd));
		if (!empty($newConfig)) {
			phpSession('open');
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
				echo 'OK';
			} else {
				echo 'Invalid Configuration name';
			}
		} else {
			echo 'Argument missing: Configuration name';
		}
		break;
	case 'set_coverview': // -on | -off
		$result = sysCmd('/var/www/util/coverview.php' . getArgs($cmd));
		echo $result[0];
		break;
	case 'trx_control': // Up to 3 args
		$result = sysCmd('/var/www/util/trx-control.php' . getArgs($cmd));
		echo $result[0];
		break;
	case 'upd_library':
		$result = sysCmd('/var/www/util/libupd-submit.php');
		echo 'Library update submitted';
		break;
	case 'restart_renderer': // --bluetooth | --airplay | --spotify | --squeezelite | --roonbridge
		$result = sysCmd('/var/www/util/restart-renderer.php' . getArgs($cmd));
		echo (empty($result) ? 'OK' : 'Missing or invalid argument');
		break;
	default: // MPD commands
		if (false === ($sock = openMpdSock('localhost', 6600))) {
			debugLog('command/index.php: Connection to MPD failed');
		} else {
			sendMpdCmd($sock, $_GET['cmd']);
			$resp = readMpdResp($sock);
			closeMpdSock($sock);
			echo json_encode(parseMpdRespAsJSON($resp), JSON_FORCE_OBJECT);
		}
}

function getArgs($cmd) {
	$argCount = count($cmd);
	if ($argCount > 1) {
		for ($i = 0; $i < $argCount; $i++) {
			chkValue($cmd[$i + 1]);
			$args .= ' ' . $cmd[$i + 1];
		}
	} else {
		$args = '';
	}

	return $args;
}

function openSessionReadOnly() {
	$sessionId = trim(shell_exec("sqlite3 " . SQLDB_PATH . " \"SELECT value FROM cfg_system WHERE param='sessionid'\""));
	session_id($sessionId);
	session_start();
	session_write_close();
}
