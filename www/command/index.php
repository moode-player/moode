<?php
/*
 * SPDX-License-Identifier: GPL-3.0-or-later
 * Copyright 2014 The moOde audio player project / Tim Curtis
 * Copyright 2013 The tsunamp player ui / Andrea Coiutti & Simone De Gregori
*/

require_once __DIR__ . '/../inc/common.php';
require_once __DIR__ . '/../inc/mpd.php';
require_once __DIR__ . '/../inc/session.php';
require_once __DIR__ . '/../inc/sql.php';

if (!isset($_GET['cmd']) || empty($_GET['cmd'])) {
	echo 'Command is missing';
	exit(1);
}

// DEBUG:
//workerLog('index.php: cmd=' . $_GET['cmd']);

$cmd = explode(' ', $_GET['cmd']);
switch ($cmd[0]) {
	case 'get_currentsong':
		echo json_encode(parseDelimFile(file_get_contents('/var/local/www/currentsong.txt'), "="));
		break;
	case 'get_output_format':
		phpSession('open_ro');
		echo json_encode(getALSAOutputFormat());
		break;
	case 'get_volume':
		$result = sysCmd('/var/www/util/vol.sh');
		echo $result[0];
		break;
	case 'set_volume':			// N | -mute | -up N | -dn N
	case 'vol.sh': 				// DEPRECATED: used in spotevent, spspost, multiroom.php
		$result = sysCmd('/var/www/util/vol.sh' . getArgs($cmd));
		echo 'OK';
		break;
	case 'set_coverview':		// -on | -off
	case 'coverview.php':		// DEPRECATED: not used via http
		$result = sysCmd('/var/www/util/coverview.php' . getArgs($cmd));
		echo $result[0];
		break;
	case 'trx_control':			// Up to 3 args
	case 'trx-control.php':		// DEPRECATED: used in: spotevent, spspre, multiroom.php, players.php, trx-config.php
		$result = sysCmd('/var/www/util/trx-control.php' . getArgs($cmd));
		echo $result[0];
		break;
	case 'upd_library':
	case 'libupd-submit.php':	// DEPRECATED: not used via http
		$result = sysCmd('/var/www/util/libupd-submit.php');
		echo 'Library update submitted';
		break;
	case 'restart_renderer': 	// --bluetooth | --airplay | --spotify | --squeezelite | --roonbridge
		$result = sysCmd('/var/www/util/restart-renderer.php ' . getArgs($cmd));
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
		for($i = 0; $i < $argCount; $i++) {
			$args .= ' ' . $cmd[$i + 1];
		}
	} else {
		$args = '';
	}

	return $args;
}
