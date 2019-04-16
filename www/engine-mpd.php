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
 * 2019-04-12 TC moOde 5.0
 *
 */
 
require_once dirname(__FILE__) . '/inc/playerlib.php';

// load session vars
// note: radio station vars are not loaded into session until worker.php starts
playerSession('open', '', '');
session_write_close();

// wait till worker.php is ready
if (!isset($_SESSION['wrkready']) || $_SESSION['wrkready'] == '0') {
	debugLog('engine-mpd: Worker not ready yet');
	exit(0);
}

$sock = openMpdSock('localhost', 6600);
if (!$sock) {
	debugLog('engine-mpd: Connection to MPD failed');
	echo json_encode(array('error' => 'openMpdSock() failed', 'module' => 'engine-mpd'));
	exit(0);	
}

debugLog('engine-mpd: Get initial status');
$current = parseStatus(getMpdStatus($sock));

// initiate mpd idle
debugLog('engine-mpd: UI state=(' . $_GET['state'] . '), MPD state=(' . $current['state'] .')');
if ($_GET['state'] == $current['state']) {
	debugLog('engine-mpd: Wait for idle timeout');
	sendMpdCmd($sock, 'idle');
	stream_set_timeout($sock, $_SESSION['engine_mpd_sock_timeout']); // value determines how often PHP times out the socket	
	$resp = readMpdResp($sock); 

	$event = explode("\n", $resp)[0];
	debugLog('engine-mpd: Idle timeout event=(' . $event . ')');
	debugLog('engine-mpd: Get new status');
	$current = parseStatus(getMpdStatus($sock));
	$current['idle_timeout_event'] = $event;	
}

// create enhanced metadata
debugLog('engine-mpd: Generating enhanced metadata');
$current = enhanceMetadata($current, $sock, 'engine_mpd_php'); // r44a
closeMpdSock($sock);
debugLog('engine-mpd: Metadata returned to client: Size=(' . sizeof($current) . ')');
//foreach ($current as $key => $value) {debugLog('engine-mpd: Metadata returned to client: Raw=(' . $key . ' ' . $value . ')');}
//debugLog('engine-mpd: Metadata returned to client: Json=(' . json_encode($current) . ')');

// r45b @ohinckel https: //github.com/moode-player/moode/pull/14/files
$current_json = json_encode($current);
if ($current_json === FALSE) {
	echo json_encode(array('error' => array('code' => json_last_error(), 'message' => json_last_error_msg())));
}
else {
	echo $current_json;
}
