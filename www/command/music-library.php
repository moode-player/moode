<?php
/*
 * SPDX-License-Identifier: GPL-3.0-or-later
 * Copyright 2014 The moOde audio player project / Tim Curtis
*/

require_once __DIR__ . '/../inc/common.php';
require_once __DIR__ . '/../inc/mpd.php';
require_once __DIR__ . '/../inc/music-library.php';
require_once __DIR__ . '/../inc/session.php';
require_once __DIR__ . '/../inc/sql.php';

$sock = getMpdSock('command/music-library.php');
phpSession('open_ro');

chkVariables($_GET, array('path', 'query', 'tagname'));
chkVariables($_POST, array('name'));

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
		if (submitJob('create_saved_search', $_POST['name'])) {
			echo json_encode('job submitted');
		} else {
			echo json_encode('worker busy');
		}
		break;
	case 'get_recent_playlist':
		phpSession('open_ro');
		echo json_encode($_SESSION['lib_recent_playlist']);
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
