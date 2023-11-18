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

require_once __DIR__ . '/common.php';
require_once __DIR__ . '/sql.php';

function phpSessionCheck($maxLoops = 3, $sleepTime = 2) {
	$sessionFile = SESSION_SAVE_PATH . '/sess_' . $_SESSION['sessionid'];

	for ($i = 0; $i < $maxLoops; $i++) {
		$result = sysCmd('ls -l ' . $sessionFile . " | awk '{print $1 \",\" $3 \",\" $4;}'");

		if ($result[0] == '-rw-rw-rw-,www-data,www-data') {
			workerLog('worker: Session check:     ok');
			break;
		} else {
			workerLog('worker: Session check:     retry ' . ($i + 1));
			sysCmd('chown www-data:www-data ' . $sessionFile);
			sysCmd('chmod 0666 ' . $sessionFile);
		}

		sleep($sleepTime);
	}

	// Check for failure case on the way out
	if ($i == $maxLoops) {
		$result = sysCmd('ls -l ' . $sessionFile . " | awk '{print $1 \",\" $3 \",\" $4;}'");

		if ($result[0] != '-rw-rw-rw-,www-data,www-data') {
			workerLog('worker: Session check:     failed after ' . $maxLoops . ' retries');
			workerLog('worker: Permissions:       ' . $result[0]);
		} else {
			workerLog('worker: Session check:     ok');
		}
	}
}

// 0: PHP_SESSION_DISABLED	Sessions are currently disabled
// 1: PHP_SESSION_NONE		Sessions are enabled, but no session has been started
// 2: PHP_SESSION_ACTIVE	Sessions are enabled and a session has been started
function phpSession($cmd, $param = '', $value = '', $caller = '') {
	switch ($cmd) {
		case 'load_system':
			$status = phpSession('get_status');
			if ($status != PHP_SESSION_ACTIVE) {
				// Use stored id
				$id = phpSession('get_sessionid');
				if (!empty($id)) {
					session_id($id);
					if (phpSession('open') === false) {
						debugLog('phpSession(open): session_start() using stored id failed');
						return false;
					}
				} else {
					// Generate new id and store it
					if (phpSession('open') === false) {
						debugLog('phpSession(open): session_start() using newly generated id failed');
						return false;
					}
					phpSession('put_sessionid');
				}
			}
			// Load cfg_system into session
			$rows = sqlRead('cfg_system', sqlConnect());
			foreach ($rows as $row) {
				$_SESSION[$row['param']] = $row['value'];
			}
			break;
		case 'load_radio':
			// Delete radio station session vars to purge any orphans
			foreach ($_SESSION as $key => $value) {
				if (substr($key, 0, 4) == 'http') {
					unset($_SESSION[$key]);
				}
			}
			// Load cfg_radio into session
			$rows = sqlRead('cfg_radio', sqlConnect(), 'all');
			foreach ($rows as $row) {
				$_SESSION[$row['station']] = array('name' => $row['name'], 'type' => $row['type'],
					'logo' => $row['logo'], 'bitrate' => $row['bitrate'], 'format' => $row['format'],
					'home_page' => $row['home_page'], 'monitor' => $row['monitor']);
			}
			//workerLog(print_r($_SESSION, true));
			break;
		case 'get_status':
			// NOTE: $param can be used to mark locations in the caller(s) for example phpSession('get_status', ' 1')
			$status = session_status();
			debugLog('phpSession(get_status)' . $param . ': status=' . ($status == 0 ? 'PHP_SESSION_DISABLED' : ($status == 1 ? 'PHP_SESSION_NONE' : 'PHP_SESSION_ACTIVE')));
			return $status;
			break;
		case 'open_ro': // Read only
			phpSession('open');
			phpSession('close', '', '', ', caller: phpSession(open_ro)');
			break;
		case 'open':
			if (session_start() === false) {
				debugLog('phpSession(start): session_start() failed');
				return false;
			} else {
				return true;
			}
			break;
		case 'close':
			if (session_write_close() === false) {
				debugLog('phpSession(close): session_write_close() failed' . $caller);
				return false;
			} else {
				return true;
			}
			break;
		case 'write':
			$_SESSION[$param] = $value;
			sqlUpdate('cfg_system', sqlConnect(), $param, $value);
			break;
		case 'get_sessionid':
			$result = sqlRead('cfg_system', sqlConnect(), 'sessionid');
			return $result['0']['value'];
			break;
		case 'put_sessionid':
			phpSession('write', 'sessionid', session_id());
			break;
	}
}
