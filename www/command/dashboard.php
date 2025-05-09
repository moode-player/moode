<?php
/*
 * SPDX-License-Identifier: GPL-3.0-or-later
 * Copyright 2014 The moOde audio player project / Tim Curtis
*/

require_once __DIR__ . '/../inc/common.php';
require_once __DIR__ . '/../inc/session.php';
require_once __DIR__ . '/../inc/sql.php';

session_id(phpSession('get_sessionid'));
phpSession('open');

chkVariables($_GET);

switch ($_GET['cmd']) {
	case 'update_library':
	case 'regen_thmcache':
		if (submitJob($_GET['cmd'])) {
			echo json_encode('job submitted');
		} else {
			echo json_encode('worker busy');
		}
		break;
	case 'regen_thumbnails':
	case 'refresh_screen':
		sendFECmd('refresh_screen');
		break;
	case 'play':
	case 'pause':
	case 'stop':
	case 'next':
	case 'prev':
		sysCmd('mpc ' . $_GET['cmd']);
		break;
	default:
		echo 'Unknown command';
		break;
}

phpSession('close');
