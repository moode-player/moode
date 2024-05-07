<?php
/**
 * SPDX-License-Identifier: GPL-3.0-or-later
 * Copyright 2014 The moOde audio player project / Tim Curtis
 * Copyright 2013 The tsunamp player ui / Andrea Coiutti & Simone De Gregori
*/

require_once __DIR__ . '/inc/common.php';
require_once __DIR__ . '/inc/mpd.php';
require_once __DIR__ . '/inc/music-library.php';
require_once __DIR__ . '/inc/session.php';
require_once __DIR__ . '/inc/sql.php';

$result = sqlQuery("SELECT value FROM cfg_system WHERE param='wrkready'", sqlConnect());

// Check for Worker startup complete
if ($result[0]['value'] == '0') {
	exit; // Worker startup is not finished yet');
}

// Check for MPD connection failure
$sock = openMpdSock('localhost', 6600);
if (!$sock) {
	debugLog('engine-mpd: Connection to MPD failed');
	echo json_encode(array('error' => 'openMpdSock() failed', 'module' => 'engine-mpd'));
	exit;
}

$status = getMpdStatus($sock);

// Initiate MPD idle
if ($_GET['state'] == $status['state']) {
	sendMpdCmd($sock, 'idle');
	stream_set_timeout($sock, 600000); // Value determines how often PHP times out the socket
	$resp = readMpdResp($sock);

	$event = explode("\n", $resp)[0];
	$status = getMpdStatus($sock);
	$status['idle_timeout_event'] = $event;
}
// Create enhanced metadata
$metadata = enhanceMetadata($status, $sock, 'engine_mpd_php');
closeMpdSock($sock);

$metadata = json_encode($metadata); // @ohinckel https: //github.com/moode-player/moode/pull/14/files
if ($metadata === false) {
	echo json_encode(array('error' => array('code' => json_last_error(), 'message' => json_last_error_msg())));
} else {
	echo $metadata;
}
