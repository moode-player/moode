<?php
/*
 * SPDX-License-Identifier: GPL-3.0-or-later
 * Copyright 2014 The moOde audio player project / Tim Curtis
 * Copyright 2013 The tsunamp player ui / Andrea Coiutti & Simone De Gregori
*/

require_once __DIR__ . '/inc/common.php';
require_once __DIR__ . '/inc/mpd.php';
require_once __DIR__ . '/inc/music-library.php';
require_once __DIR__ . '/inc/session.php';
require_once __DIR__ . '/inc/sql.php';

instrumentLog('Script start');

// Check for Worker startup complete
$wrkready = sqlQuery("SELECT value FROM cfg_system WHERE param='wrkready'", sqlConnect())[0]['value'];
if ($wrkready == '0') {
	instrumentLog('Script exit');
	exit;
}

// Check for MPD connection failure
$sock = openMpdSock('localhost', 6600);
if (!$sock) {
	debugLog('engine-mpd: ' . $_SERVER['REMOTE_ADDR'] . ' Socket open failed');
	echo json_encode(array('error' => array('code' => '1001', 'message' => 'Socket open failed')));
	instrumentLog('Script exit');
	exit;
} else {
	 // How often PHP times out the socket (default 600000 secs - 7 days)
	$timeout = sqlQuery("SELECT value FROM cfg_system WHERE param='empd_socket_timeout'", sqlConnect())[0]['value'];
	stream_set_timeout($sock, $timeout);
	instrumentLog('Socket opened (timeout ' . $timeout . ' secs)');
}

// Get MPD status
$status = getMpdStatus($sock);

// Initiate MPD idle
if ($_GET['state'] == $status['state']) {
	instrumentLog('Idle');

	sendMpdCmd($sock, 'idle');
	$resp = readMpdResp($sock);

	if ($resp == 'Socket timed out') {
		instrumentLog('** Socket timed out');
	} else {
		$event = explode("\n", $resp)[0];
		$status = getMpdStatus($sock);
		$status['idle_timeout_event'] = $event;
		instrumentLog('Event (' . $event . ')');
	}
}

if ($resp != 'Socket timed out') {
	// Return data to front-end
	$metadata = json_encode(enhanceMetadata($status, $sock, 'engine_mpd_php'));
	if ($metadata === false) {
		instrumentLog('Error returned to front-end');
		echo json_encode(array('error' => array('code' => json_last_error(), 'message' => json_last_error_msg())));
	} else {
		instrumentLog('Data returned to front-end');
		echo $metadata;
	}
} else {
	echo json_encode(array('error' => array('code' => '1002', 'message' => 'Socket timed out')));
}

closeMpdSock($sock);
instrumentLog('Socket closed');
instrumentLog('Script end');

// Instrumentation log
function instrumentLog($msg) {
	workerLog('engine-mpd: ' . $_SERVER['REMOTE_ADDR'] . ' ' . $msg);
}
