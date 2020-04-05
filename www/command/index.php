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
 * 2019-08-08 TC moOde 6.0.0
 *
 */

require_once dirname(__FILE__) . '/../inc/playerlib.php';

playerSession('open', '' ,'');
session_write_close();
if (isset($_GET['cmd']) && empty($_GET['cmd'])) {
	echo 'Command missing';
}
// SH, PHP or other defined commands
elseif (stripos($_GET['cmd'], '.sh') !== false || stripos($_GET['cmd'], '.php') !== false) {
	// Check for valid chrs
    if (preg_match('/^[A-Za-z0-9 _.-]+$/', $_GET['cmd'])) {
		// Reject directory traversal ../
		if (substr_count($_GET['cmd'], '.') > 1) {
			echo 'Invalid string';
		}
		// Check for valid commands
        elseif (stripos($_GET['cmd'], 'vol.sh') !== false) {
			$result = sysCmd('/var/www/' . $_GET['cmd']);
			echo $result[0];
        }
		elseif (stripos($_GET['cmd'], 'libupd-submit.php') !== false) {
			$result = sysCmd('/var/www/' . $_GET['cmd']);
			echo 'Library update submitted';
        }
        else {
            echo 'Unknown command';
        }
    }
    else {
    	echo 'Invalid string';
    }
}
// MPD commands
else {
	if (false === ($sock = openMpdSock('localhost', 6600))) {
		$msg = 'command/index: Connection to MPD failed';
		workerLog($msg);
		exit($msg . "\n");
	}
	else {
		sendMpdCmd($sock, $_GET['cmd']);
		$result = readMpdResp($sock);
		closeMpdSock($sock);
		//echo $result;
	}
}
