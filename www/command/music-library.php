<?php
/**
 * moOde audio player (C) 2014 Tim Curtis
 * http://moodeaudio.org
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
require_once __DIR__ . '/../inc/music-library.php';
require_once __DIR__ . '/../inc/session.php';
require_once __DIR__ . '/../inc/sql.php';

$sock = getMpdSock();
phpSession('open_ro');

switch ($_GET['cmd']) {
	case 'update_library':
		$queueArgs = (isset($_GET['path']) && $_GET['path'] != '') ? $_GET['path'] : '';
		if (submitJob($_GET['cmd'], $queueArgs)) {
			echo json_encode('job submitted');
		} else {
			echo json_encode('worker busy');
		}
		break;
	case 'load_library':
		//sleep(10); // To simulate a long library load
		echo loadLibrary($sock);
    	break;
	case 'lsinfo':
		$path = isset($_GET['path']) && $_GET['path'] != '' ? $_GET['path'] : '';
		echo json_encode(searchMpdDb($sock, 'lsinfo', $path));
		break;
	case 'search':
		if (isset($_GET['query']) && $_GET['query'] != '' && isset($_GET['tagname']) && $_GET['tagname'] != '') {
			echo json_encode(searchMpdDb($sock, $_GET['tagname'], $_GET['query']));
		}
		break;
	case 'clear_libcache_all':
		clearLibCacheAll();
		break;
	case 'clear_libcache_filtered':
		clearLibCacheFiltered();
		break;
	case 'get_saved_searches':
		echo json_encode(getSavedSearches());
		break;
	case 'activate_saved_search':
		phpSession('open');
		$_SESSION['lib_active_search'] = $_POST['name'];
		phpSession('close');
		echo file_get_contents(LIBSEARCH_BASE . $_POST['name'] . '.json');
		break;
	case 'delete_saved_search':
		sysCmd('rm "' . LIBSEARCH_BASE . $_POST['name'] . '.json"');
		break;
	case 'clear_active_search':
		phpSession('open');
		$_SESSION['lib_active_search'] = 'None';
		phpSession('close');
		break;
	case 'create_saved_search':
		if (submitJob('create_saved_search', $_POST['name'], '', '')) {
			echo json_encode('job submitted');
		} else {
			echo json_encode('worker busy');
		}
		break;
	case 'thumcache_status':
		if (isset($_SESSION['thmcache_status']) && !empty($_SESSION['thmcache_status'])) {
			$status = $_SESSION['thmcache_status'];
		} else {
			$result = sysCmd('ls ' . THMCACHE_DIR);
		    if ($result[0] == '') {
				$status = 'Cache is empty';
			} else if (strpos($result[0], 'ls: cannot access') !== false) {
				$status = 'Cache directory missing. It will be recreated automatically.';
			} else {
				$stat = stat(THMCACHE_DIR);
				$status = 'Cache was last updated on ' . date("Y-m-d H:i:s", $stat['mtime']);
			}
		}
		echo json_encode($status);
		break;
	default:
		echo 'Unknown command';
		break;
}

// Close MPD socket
if (isset($sock) && $sock !== false) {
	closeMpdSock($sock);
}

function searchMpdDb($sock, $querytype, $query = '') {
	//workerLog($querytype . ', ' . $query);
	switch ($querytype) {
		// List a database path
		case 'lsinfo':
			if (!empty($query)){
				sendMpdCmd($sock, 'lsinfo "' . html_entity_decode($query) . '"');
				break;
			}
			else {
				sendMpdCmd($sock, 'lsinfo');
				break;
			}
		// Search all tags
		case 'any':
			sendMpdCmd($sock, 'search any "' . html_entity_decode($query) . '"');
			break;
		// Search specified tags
		case 'specific':
			sendMpdCmd($sock, 'search "(' . html_entity_decode($query) . ')"');
			break;
	}

	$resp = readMpdResp($sock);
	return formatMpdQueryResults($resp);
}
