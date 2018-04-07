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
 * 2018-01-26 TC moOde 4.0
 * 2018-04-02 TC moOde 4.1 access-control-origin not needed
 *
 */
 
require_once dirname(__FILE__) . '/inc/playerlib.php';

// load session vars (cfg_system + cfg_radio)
// NOTE cfg_radio vars are loaded into $_SESSION by worker so might not be present here until worker startup completes
playerSession('open', '', '');
session_write_close();

debugLog('engine-mpd: Connect');
debugLog('engine-mpd: Session loaded');

debugLog('engine-mpd: Open socket');
$sock = openMpdSock('localhost', 6600);

if (!$sock) {
	debugLog('engine-mpd: Connection to MPD failed');
	echo json_encode(array('error' => 'openMpdSock() failed', 'module' => 'engine-mpd'));
	exit();	
}

 // get initial mpd status data
debugLog('engine-mpd: Get initial status');
$current = parseStatus(getMpdStatus($sock));

// mpd idle
debugLog('engine-mpd: UI state=(' . $_GET['state'] . '), MPD state=(' . $current['state'] .')');
if ($_GET['state'] == $current['state']) {

	debugLog('engine-mpd: Idle');
	
	// idle mpd and wait for change in state
	sendMpdCmd($sock, 'idle');
	stream_set_timeout($sock, $_SESSION['engine_mpd_sock_timeout']); // value determines how often PHP times out the socket
	//workerLog('engine-mpd: socket timeout');
	
	debugLog('engine-mpd: Wait for idle timeout');
	$resp = readMpdResp($sock);

	debugLog('engine-mpd: resp[0]=(' . explode("\n", $resp)[0] . ')');

	// get new status
	debugLog('engine-mpd: Get new status');
	$current = parseStatus(getMpdStatus($sock));
	
	// add idle timeout event
	$current['idle_timeout_event'] = explode("\n", $resp)[0];
	
	debugLog('engine-mpd: Idle timeout event=(' . $current['idle_timeout_event'] . ')');
	//workerLog('engine-mpd: Idle timeout event=(' . $current['idle_timeout_event'] . ')');
}

// create enhanced metadata
debugLog('engine-mpd: Generating enhanced metadata');
$current = enhanceMetadata($current, $sock, 'mediainfo');

debugLog('engine-mpd: Metadata returned to client: Size=(' . sizeof($current) . ')');

//foreach ($current as $key => $value) {
//	debugLog('engine-mpd: Metadata returned to client: Raw=(' . $key . ' ' . $value . ')');
//}

//debugLog('engine-mpd: Metadata returned to client: Json=(' . json_encode($current) . ')');

// TEST I don't think this is needed
//header('Access-Control-Allow-Origin: *');

echo json_encode($current);

closeMpdSock($sock);
