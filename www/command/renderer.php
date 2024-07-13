<?php
/*
 * SPDX-License-Identifier: GPL-3.0-or-later
 * Copyright 2014 The moOde audio player project / Tim Curtis
*/

require_once __DIR__ . '/../inc/common.php';
require_once __DIR__ . '/../inc/mpd.php';
require_once __DIR__ . '/../inc/session.php';
require_once __DIR__ . '/../inc/sql.php';

// This script processes 'Turn off' and 'Disconnect' actions from the corresponding buttons on
// Renderer Active overlays for Airplay, Spotify Connect, Squeezelite, Plexamp, RoonBridge,
// and Multiroom Receiver.
//
// NOTE: The Bluetooth Active overlay provides a 'Bluetooth Control' button which opens the
// Bluetooth Control screen where the client can be disconnected.
//
switch ($_GET['cmd']) {
	case 'disconnect_renderer':
		// Squeezelite, Plexamp and trx-rx hog the audio output so need to be turned off in order to released it
		phpSession('open');
	 	if ($_POST['job'] == 'slsvc') {
	 		phpSession('write', 'slsvc', '0');
		} else if ($_POST['job'] == 'pasvc') {
	 		phpSession('write', 'pasvc', '0');
	 	} else if ($_POST['job'] == 'multiroom_rx') {
	 		phpSession('write', 'multiroom_rx', 'Off');
	 	}
		phpSession('close');

	 	// AirPlay, Spotify Connect and RoonBridge are session based, they can be restarted to effect a disconnect
	 	// NOTE: 'disconnect_renderer' is passed to worker so MPD play can be resumed if indicated
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
