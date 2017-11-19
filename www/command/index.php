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
 * 2017-11-11 TC moOde 4.0
 *
 */
 
require_once dirname(__FILE__) . '/../inc/playerlib.php';

playerSession('open', '' ,'');
session_write_close();

if (isset($_GET['cmd']) && $_GET['cmd'] === '') {
	echo 'command missing';
}
// BASH
elseif (stripos($_GET['cmd'], '.sh') !== false ) {							
	sysCmd('/var/www/' . $_GET['cmd']);
}
// PHP
elseif (stripos($_GET['cmd'], '.php') !== false ) {							
	sysCmd('/var/www/' . $_GET['cmd']);
}
// MPD
else {
	if (false === ($sock = openMpdSock('localhost', 6600))) {
		$msg = 'command/index: Connection to MPD failed'; 
		workerLog($msg);
		exit($msg . "\n");	
	} 
	else {
		if (strpos($_GET['cmd'], ',') !== false) {
			$cmds = explode(',', $_GET['cmd']);
			chainMpdCmdsDelay($sock, $cmds, 250000);
			echo 'OK';
			closeMpdSock($sock);
		}
		else {
			sendMpdCmd($sock, $_GET['cmd']);
			echo readMpdResp($sock);
			closeMpdSock($sock);
		}
	}
}

