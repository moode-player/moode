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

if (isset($_GET['cmd']) && empty($_GET['cmd'])) {
	echo 'Command missing';
} else if (stripos($_GET['cmd'], '.sh') === false && stripos($_GET['cmd'], '.php') === false) {
	// PHP functions
	if ($_GET['cmd'] == 'get_currentsong') {
		$array = parseDelimFile(file_get_contents('/var/local/www/currentsong.txt'), "=");
		echo json_encode($array);
	} else if ($_GET['cmd'] == 'get_output_format') {
		echo json_encode(getALSAOutputFormat());
	} else {
		// MPD commands
		if (false === ($sock = openMpdSock('localhost', 6600))) {
			workerLog('command/index.php: Connection to MPD failed');
		} else {
			sendMpdCmd($sock, $_GET['cmd']);
			$resp = readMpdResp($sock);
			closeMpdSock($sock);
			if (stripos($resp, 'Error:')) {
				echo $resp;
			}
		}
	}
} else {
	// PHP and BASH scripts
    if (preg_match('/^[A-Za-z0-9 _.-]+$/', $_GET['cmd'])) {
		if (substr_count($_GET['cmd'], '.') > 1) {
			echo 'Invalid string'; // Reject directory traversal ../
		} else if (stripos($_GET['cmd'], 'vol.sh') !== false) {
			$result = sysCmd('/var/www/' . $_GET['cmd']);
			echo $result[0];
        } else if (stripos($_GET['cmd'], 'libupd-submit.php') !== false) {
			$result = sysCmd('/var/www/' . $_GET['cmd']);
			echo 'Library update submitted';
        } else if (stripos($_GET['cmd'], 'trx-control.php') !== false) {
			$result = sysCmd('/var/www/util/' . $_GET['cmd']);
			echo $result[0];
        } else if (stripos($_GET['cmd'], 'coverview.php') !== false) {
			$result = sysCmd('/var/www/util/' . $_GET['cmd']);
			echo $result[0];
        } else {
            echo 'Unknown command';
        }
    } else {
    	echo 'Invalid string';
    }
}
