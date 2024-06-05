<?php
/*
 * SPDX-License-Identifier: GPL-3.0-or-later
 * Copyright 2014 The moOde audio player project / Tim Curtis
*/

require_once __DIR__ . '/../inc/common.php';
require_once __DIR__ . '/../inc/mpd.php';
require_once __DIR__ . '/../inc/session.php';
require_once __DIR__ . '/../inc/sql.php';

switch ($_GET['cmd']) {
	case 'disconnect_renderer':
		// Squeezelite and trx-rx hog the audio output so need to be turned off in order to released it
		phpSession('open');
	 	if ($_POST['job'] == 'slsvc') {
	 		phpSession('write', 'slsvc', '0');
	 	} else if ($_POST['job'] == 'multiroom_rx') {
	 		phpSession('write', 'multiroom_rx', 'Off');
	 	}
		phpSession('close');

	 	// AirPlay, Spotify, Plexamp and RoonBridge are session based and so they can simply be restarted to effect a disconnect
	 	// NOTE: 'disconnect_renderer' is passed as a job queue arg and tested for in worker so that MPD play can be resumed if indicated
	 	if (submitJob($_POST['job'], $_GET['cmd'])) {
	 		echo json_encode('job submitted');
	 	} else {
	 		echo json_encode('worker busy');
	 	}
		break;
	default:
		echo 'Unknown command';
		break;
}
