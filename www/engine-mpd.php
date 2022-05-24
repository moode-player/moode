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
