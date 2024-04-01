<?php
/**
 * moOde audio player (C) 2014 Tim Curtis
 * http://moodeaudio.org
 *
 * tsunamp player ui (C) 2013 Andrea Coiutti & Simone De Gregori
 * http://www.tsunamp.com
 *
 * This Program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 3, or (at your option)
 * any later version.
 *
 * This Program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program.  If not, see <http://www.gnu.org/licenses/>.
 *
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
		$result = sysCmd('/var/www/vol.sh');
		echo $result[0];
		break;
	case 'set_volume':			// N | -mute | -up N | -dn N
	case 'vol.sh': 				// DEPRECATED: used in spotevent, spspost, multiroom.php
		$result = sysCmd('/var/www/vol.sh' . getArgs($cmd));
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
		$result = sysCmd('/var/www/libupd-submit.php');
		echo 'Library update submitted';
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
