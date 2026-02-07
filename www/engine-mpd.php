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

// Fetch the socket timeout value
$timeout = sqlQuery("SELECT value FROM cfg_system WHERE param='empd_socket_timeout'", sqlConnect())[0]['value'];
$sockTimeout = $timeout == 'default' ? MPD_DEFAULT_SOCKET_TIMEOUT : $timeout; // Default is 600000 secs (7 days)
scriptLog('Script start');

// Check for Worker startup complete
$wrkready = sqlQuery("SELECT value FROM cfg_system WHERE param='wrkready'", sqlConnect())[0]['value'];
if ($wrkready == '0') {
	scriptLog('Script exit (startup not complete yet)');
	exit;
}

// Check for MPD connection failure
$sock = openMpdSock('localhost', 6600);
if (!$sock) {
	debugLog('engine-mpd: ' . $_SERVER['REMOTE_ADDR'] . ' Socket open failed');
	echo json_encode(array('error' => array('code' => '1001', 'message' => 'Socket open failed')));
	scriptLog('Script exit (socket open failed)');
	exit;
} else {
	 // Determines how often PHP times out the socket
	stream_set_timeout($sock, $sockTimeout);
	scriptLog('Socket opened (timeout ' . $sockTimeout . ' secs)');
}

// Get MPD status
$status = getMpdStatus($sock);
$status['empd_socket_timeout'] = $sockTimeout;

// Initiate MPD idle
if ($_GET['state'] == $status['state']) {
	scriptLog('Idle');

	sendMpdCmd($sock, 'idle');
	$resp = readMpdResp($sock);

	if ($resp == 'Socket timed out') {
		scriptLog('** Socket timed out');
	} else {
		$event = explode("\n", $resp)[0];
		$status = getMpdStatus($sock);
		$status['idle_timeout_event'] = $event;
		$status['empd_socket_timeout'] = $sockTimeout;
		scriptLog('Event (' . $event . ')');
	}
}

if ($resp != 'Socket timed out') {
	// Return data to front-end
	$metadata = json_encode(enhanceMetadata($status, $sock, 'engine_mpd_php'));
	if ($metadata === false) {
		scriptLog('Error returned to front-end (json encode failed)');
		echo json_encode(array('error' => array('code' => json_last_error(), 'message' => json_last_error_msg())));
	} else {
		scriptLog('Data returned to front-end');
		echo $metadata;
	}
} else {
	echo json_encode(array('error' => array('code' => '1002', 'message' => 'Socket timed out')));
}

closeMpdSock($sock);
scriptLog('Socket closed');
scriptLog('Script end');

// Script log (Default is off)
// Turn on/off
// sudo sed -i 's/#workerLog/workerLog/' /var/www/engine-mpd.php
// sudo sed -i 's/workerLog/#workerLog/' /var/www/engine-mpd.php
// Set timeout value
// moodeutl -q "UPDATE cfg_system SET value='30' WHERE param='empd_socket_timeout'"
// moodeutl -q "UPDATE cfg_system SET value='default' WHERE param='empd_socket_timeout'"
function scriptLog($msg) {
	#workerLog('engine-mpd: ' . $_SERVER['REMOTE_ADDR'] . ' ' . $msg);
}
