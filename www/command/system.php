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
	case 'reboot':
	case 'poweroff':
	case 'update_library':
		if (submitJob($_GET['cmd'])) {
			echo json_encode('job submitted');
		} else {
			echo json_encode('worker busy');
		}
		break;
	case 'get_client_ip':
		echo json_encode($_SERVER['REMOTE_ADDR']);
		break;
	case 'restart_local_display':
		if ($_SESSION['local_display'] == '1') {
			if (submitJob('local_display_restart')) {
				echo json_encode('job submitted');
			} else {
				echo json_encode('worker busy');
			}
		}
		break;
	default:
		echo 'Unknown command';
		break;
}

phpSession('close');
