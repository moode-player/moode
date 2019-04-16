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
 
define('MPD_RESPONSE_ERR', 'ACK');
define('MPD_RESPONSE_OK',  'OK');
define('MPD_MUSICROOT',  '/var/lib/mpd/music/');
define('SQLDB', 'sqlite:/var/local/www/db/moode-sqlite3.db');
define('MOODELOG', '/var/log/moode.log');
define('PORT_FILE', '/tmp/portfile');
define('THMCACHE_DIR', '/var/local/www/imagesw/thmcache/');
define('LIBCACHE_JSON', '/var/local/www/libcache.json');

error_reporting(E_ERROR);

// features availability bitmask
const FEAT_RESERVED =    0b0000000000000001;	//     1
const FEAT_AIRPLAY =     0b0000000000000010;	//     2
const FEAT_MINIDLNA =    0b0000000000000100;	//     4
const FEAT_MPDAS =       0b0000000000001000;	//     8 
const FEAT_SQUEEZELITE = 0b0000000000010000;	//    16
const FEAT_UPMPDCLI =    0b0000000000100000;	//    32
const FEAT_SQSHCHK =     0b0000000001000000;	//    64
const FEAT_GMUSICAPI =   0b0000000010000000;	//   128
const FEAT_LOCALUI =     0b0000000100000000;	//   256
const FEAT_SOURCESEL =   0b0000001000000000;	//   512
const FEAT_UPNPSYNC =    0b0000010000000000;	//  1024
const FEAT_SPOTIFY =     0b0000100000000000;	//  2048
const FEAT_GPIO =	    0b0001000000000000;	//  4096

// mirror for footer.php
$FEAT_AIRPLAY =		0b0000000000000010;
$FEAT_SQUEEZELITE =	0b0000000000010000;
$FEAT_UPMPDCLI = 	0b0000000000100000;
$FEAT_SOURCESEL = 	0b0000001000000000;
$FEAT_SPOTIFY =		0b0000100000000000;

// worker message logger
function workerLog($msg, $mode) {
	$mode = isset($mode) ? $mode : 'a';
	$fh = fopen(MOODELOG, $mode);
	fwrite($fh, date('Ymd His ') . $msg . "\n");
	fclose($fh);
}

// debug message logger
function debugLog($msg, $mode) {
	// logging off
	if (!isset($_SESSION['debuglog']) || $_SESSION['debuglog'] == '0') {
		return;
	}

	if (!isset($mode)) {$mode = 'a';} // default= append mode

	$fh = fopen(MOODELOG, $mode);
	fwrite($fh, date('Ymd His ') . $msg . "\n");
	fclose($fh);
}

// Helper functions for html generation	(pcasto)
function versioned_resource($file, $type='stylesheet') {
	$resourcetag = getMoodeRel();
	$version_indicator = '?v=';
	$tagged_link = '<link href="' . $file . $version_indicator . $resourcetag . '" rel="' . $type .'">';
	//workerLog($tagged_link);
	echo $tagged_link . "\n";
}
function versioned_script($file, $type='') {
	$resourcetag = getMoodeRel();
	$version_indicator = '?v=';
	$tagged_src = '<script src="' . $file . $version_indicator . $resourcetag . '"';
	if ($type != '' ) {
		$tagged_src .= ' type="' . $type . '"';
	}
	$tagged_src .= '></script>';
	//workerLog($tagged_src);
	echo $tagged_src . "\n";
}	

// core mpd functions

// AG from Moode 3 prototype
// TC retry to improve robustness
function openMpdSock($host, $port) {
	for ($i = 0; $i < 6; $i++) {  
		if (false === ($sock = @stream_socket_client('tcp://' . $host . ':' . $port, $errorno, $errorstr, 30))) {
			debugLog('openMpdSocket(): connection failed (' . ($i + 1) . ')');
			debugLog('openMpdSocket(): errorno: ' . $errorno . ', ' . $errorstr);
		}
		else {
			$resp = readMpdResp($sock);
			break;
		}

		usleep(500000); // .5 secs
	}
	
	return $sock;
}

// TC rewrite to handle fgets() fail
function readMpdResp($sock) {
	$resp = '';
	//debugLog('readMpdResponse(): reading response'); // comment these out to reduce log clutter

	while (false !== ($str = fgets($sock, 1024)) && !feof($sock)) {
		if (strncmp(MPD_RESPONSE_OK, $str, strlen(MPD_RESPONSE_OK)) == 0) {
			$resp = $resp == '' ? $str : $resp;
			//debugLog('readMpdResponse(): success $str=(' . explode("\n", $str)[0] . ')');
			//debugLog('readMpdResponse(): success $resp[0]=(' . explode("\n", $resp)[0] . ')');	
			return $resp;
		}

		if (strncmp(MPD_RESPONSE_ERR, $str, strlen(MPD_RESPONSE_ERR)) == 0) {
			$msg = 'readMpdResponse(): error: response $str[0]=(' . explode("\n", $str)[0] . ')';
			debugLog($msg);
			return $msg;
		}

		$resp .= $str;
	}

	if (!feof($sock)) {
		debugLog('readMpdResponse(): error: fgets fail due to socket being timed out or PHP/MPD connection failure');
		debugLog('readMpdResponse(): error: $resp[0]=(' . explode("\n", $resp)[0] . ')');
	}

	return $resp;
}

function closeMpdSock($sock) {
	sendMpdCmd($sock, 'close');
	//$resp = readMpdResp($sock);
	fclose($sock);
}

function sendMpdCmd($sock, $cmd) {
	fputs($sock, $cmd . "\n");	
}

function chainMpdCmds($sock, $cmds) {
    foreach ($cmds as $cmd) {
        sendMpdCmd($sock, $cmd);
        $resp = readMpdResp($sock);
    }
}

function chainMpdCmdsDelay($sock, $cmds, $delay) {
    sendMpdCmd($sock, $cmds[0]);
    $resp = readMpdResp($sock);

	usleep($delay); // microseconds 1000000 = 1 sec

    sendMpdCmd($sock, $cmds[1]);
    $resp = readMpdResp($sock);
}

function getMpdStatus($sock) {
	sendMpdCmd($sock, 'status');
	$status = readMpdResp($sock);
	
	return $status;
}

// miscellaneous core functions

function sysCmd($cmd) {
	exec('sudo ' . $cmd . " 2>&1", $output);
	return $output;
}

function getTemplate($template) {
	return str_replace("\"", "\\\"", implode("", file($template)));
}

function echoTemplate($template) {
	echo $template;
}

function phpVer() {
	$version = phpversion();
	return substr($version, 0, 3); 
}

if (phpVer() == '5.3') {
	// fix sessions per environment PHP 5.3
	function session_status() {
		if (session_id()) {
			return 1;
		} else {
			return 2;
		}
	}
}

function integrityCheck() {
	$warning = false;
	$result = cfgdb_read('cfg_hash', cfgdb_connect());
	foreach ($result as $row) {
		if (md5(file_get_contents($row['param'])) !== $row['value']) {
			if ($row['action'] === 'exit') {
				return false;
			}
			elseif ($row['action'] === 'warning') {
				workerLog('worker: Integrity check (' . $row['action'] . ': ' . basename($row['param']) . ')');
				$warning = true;
			}
			else {
				return false;
			}
		}
	}

	return $warning === true ? 'passed with warnings' : 'passed';
}

function extMusicRoot() {
	$_SESSION['musicroot_ext'] = rand(1000000, 9999999);
	sysCmd('find /var/www -type l -delete');
	sysCmd('ln -s ' . MPD_MUSICROOT . ' /var/www/' . $_SESSION['musicroot_ext']);
	return true;
}

// socket routines for engine-cmd.php
function sendEngCmd ($cmd) {
	//workerLog('sendCmd(): Reading in portfile');
	if (false === ($ports = file(PORT_FILE, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES))) {
		// this case is ok and occurs if UI has never been started
		//workerLog('sendEngCmd(): File open failed');
	}
	else {
		//workerLog('sendEngCmd(): Connecting to each of ' . count($ports) . ' port(s)');
		foreach ($ports as $port) {
			//workerLog('sendEngCmd(): Port: ' . $port);
			if (false !== ($sock = socket_create(AF_INET, SOCK_STREAM, SOL_TCP))) {
				if (false !== ($result = socket_connect($sock, '127.0.0.1', $port))) {
					//workerLog('sendEngCmd(): write cmd: ' . $cmd .' to port: ' . $port);
					sockWrite($sock, $cmd);
					socket_close($sock); 
				}
				else {
					//workerLog('sendEngCmd(): Updating portfile (remove ' . $port . ')');
					sysCmd('sed -i /' . $port . '/d ' . PORT_FILE);
				}
			}
			else {
				workerLog('sendEngCmd(): Socket create failed');						
			}
		}
	}
}
function sockWrite($sock, $msg) { 
	//workerLog('sockWrite(): Sending: ' . $msg);
    $length = strlen($msg);
	// try up to 4 times
	for ($i = 0; $i < 4; $i++) {  
        if (false === ($sent = socket_write($sock, $msg, $length))) {
			workerLog('sockWrite(): Socket write failed (' . $i . ')');
            return false; 
        } 
		//workerLog('sockWrite(): sent,length: ' . $sent . ',' . $length);
        if ($sent < $length) { 
            $msg = substr($msg, $sent); 
            $length -= $sent; 
			//workerLog('sockWrite(): Resending: ' . $msg);
        }
		else { 
			//workerLog('sockWrite(): Send complete');
            return true; 
        } 
    } 
	// We only get here if $i = 4
	workerLog('sockWrite(): Socket write failed after ' . $i . ' tries');
    return false; 
} 

// caching library loader TC and AG
function loadLibrary($sock) {
	if (filesize(LIBCACHE_JSON) != 0) {
		debugLog('loadLibrary(): Cache data returned to client');
		return file_get_contents(LIBCACHE_JSON);
	}
	else {
		debugLog('loadLibrary(): Generating flat list...');
		$flat = genFlatList($sock);
				
		if ($flat != '') {
			debugLog('loadLibrary(): Flat list generated');
			debugLog('loadLibrary(): Generating library...');
			// normal or UTF8 replace
			if ($_SESSION['library_utf8rep'] == 'No') {
				$tagarray = genLibrary($flat);
			}
			else {
				$tagarray = genLibraryUTF8Rep($flat);
			}
			debugLog('loadLibrary(): Cache data returned to client');
			return $tagarray;
		}
		else {
			debugLog('loadLibrary(): Flat list empty');
			return '';
		}
	}
}

// generate flat list from mpd tag database
function genFlatList($sock) {
	sendMpdCmd($sock, 'listallinfo');
	$resp = readMpdResp($sock);
	
	if (!is_null($resp) && substr($resp, 0, 2) != 'OK') {
		$lines = explode("\n", $resp);
		$item = 0;
		$flat = array();
		$linecount = count($lines);
		
		for ($i = 0; $i < $linecount; $i++) {
			list($element, $value) = explode(': ', $lines[$i], 2);

			if ($element == 'file') {
				$item = count($flat);
				$flat[$item][$element] = $value;
			}
			// screen out dir and pl from listallinfo
			elseif ($element == 'directory' || $element == 'playlist') {
				++$i;			
			}
			else {
				$flat[$item][$element] = $value;
			}
		} 
		//workerLog(print_r($flat, true));
		return $flat;
	}
	else {
		return '';		
	}
}

// generate library {Genre1: {Artist1: {Album1: [{song1}, {song2}], Album2:...}, Artist2:...}, Genre2:...}
function genLibrary($flat) {
	$lib = array();

	// use Artist or AlbumAetist for the Artist column
	$libartist = $_SESSION['libartistcol'];

	foreach ($flat as $flatData) {
		$genre = $flatData['Genre'] ? $flatData['Genre'] : 'Unknown';
		// use sort tags if present
		$artist = $flatData['ArtistSort'] ? $flatData['ArtistSort'] : ($flatData[$libartist] ? $flatData[$libartist] : ($flatData['Artist'] ? $flatData['Artist'] : 'Unknown'));
		$album = $flatData['AlbumSort'] ? $flatData['AlbumSort'] : ($flatData['Album'] ? $flatData['Album'] :'Unknown');
		// w/o sort tags
		//$artist = $flatData[$libartist] ? $flatData[$libartist] : ($flatData['Artist'] ? $flatData['Artist'] : 'Unknown');
		//$album = $flatData['Album'] ? $flatData['Album'] : 'Unknown';
		// add year (Date) 
		//$album = $flatData['Album'] ? $flatData['Date'] . ' - ' . $flatData['Album'] : 'Unknown';

		if (!$lib[$genre]) {$lib[$genre] = array();}
		if (!$lib[$genre][$artist]) {$lib[$genre][$artist] = array();}
		if (!$lib[$genre][$artist][$album]) {$lib[$genre][$artist][$album] = array();}

		$songData = array(
			'file' => $flatData['file'],
			'tracknum' => ($flatData['Track'] ? $flatData['Track'] : ''),
			'title' => $flatData['Title'],
			'disc' => ($flatData['Disc'] ? $flatData['Disc'] : '1'),
			'actual_artist' => ($flatData['Artist'] ? $flatData['Artist'] : 'Artist tag missing'),
			'composer' => ($flatData['Composer'] ? $flatData['Composer'] : 'Composer tag missing'),
			'year' => $flatData['Date'],
			'time' => $flatData['Time'],
			'time_mmss' => songTime($flatData['Time'])
		);
			
		array_push($lib[$genre][$artist][$album], $songData);
	}

	$json_lib = json_encode($lib);
	if (file_put_contents(LIBCACHE_JSON, $json_lib) === false) {
		debugLog('genLibrary: create libcache.json failed');		
	}
	//workerLog(print_r($lib, true));
	return $json_lib;
}
// Many Chinese songs and song directories have characters that are not UTF8 causing json_encode to fail which leaves the
// libcache.json file empty. Replacing the non-UTF8 chars in the array before json_encode solves this problem (@lazybat).
function genLibraryUTF8Rep($flat) {
	$lib = array();

	// use Artist or AlbumAetist for the Artist column
	$libartist = $_SESSION['libartistcol'];

	foreach ($flat as $flatData) {
		$genre = utf8rep($flatData['Genre'] ? $flatData['Genre'] : 'Unknown');
 		$artist = utf8rep($flatData[$libartist] ? $flatData[$libartist] : ($flatData['Artist'] ? $flatData['Artist'] : 'Unknown'));
 		$album = utf8rep($flatData['Album'] ? $flatData['Album'] : 'Unknown');
		//$album = $flatData['AlbumSort'] ? $flatData['AlbumSort'] : ($flatData['Album'] ? $flatData['Album'] :'Unknown'); // albumsort tag if present

		if (!$lib[$genre]) {$lib[$genre] = array();}
		if (!$lib[$genre][$artist]) {$lib[$genre][$artist] = array();}
        if (!$lib[$genre][$artist][$album]) {$lib[$genre][$artist][$album] = array();}

		$songData = array(
 			'file' => utf8rep($flatData['file']),
 			'tracknum' => utf8rep(($flatData['Track'] ? $flatData['Track'] : '')), //r44f add inner brackets
 			'title' => utf8rep($flatData['Title']),
			'disc' => ($flatData['Disc'] ? $flatData['Disc'] : '1'),
 			'actual_artist' => utf8rep(($flatData['Artist'] ? $flatData['Artist'] : 'Artist tag missing')), //r44f add inner brackets
			'composer' => utf8rep(($flatData['Composer'] ? $flatData['Composer'] : 'Composer tag missing')),
 			'year' => utf8rep($flatData['Date']),
 			'time' => utf8rep($flatData['Time']),
 			'time_mmss' => utf8rep(songTime($flatData['Time']))
		);
			
		array_push($lib[$genre][$artist][$album], $songData);
	}

	$json_lib = json_encode($lib);
	if (file_put_contents(LIBCACHE_JSON, $json_lib) === false) {
		debugLog('genLibrary: create libcache.json failed');		
	}	
	return $json_lib;
}
// UTF8 replace (@lazybat)
function utf8rep($some_string) {
	// reject overly long 2 byte sequences, as well as characters above U+10000 and replace with ? (@lazybat)
	$some_string = preg_replace('/[\x00-\x08\x10\x0B\x0C\x0E-\x19\x7F]'.
		'|[\x00-\x7F][\x80-\xBF]+'.
		'|([\xC0\xC1]|[\xF0-\xFF])[\x80-\xBF]*'.
		'|[\xC2-\xDF]((?![\x80-\xBF])|[\x80-\xBF]{2,})'.
		'|[\xE0-\xEF](([\x80-\xBF](?![\x80-\xBF]))|(?![\x80-\xBF]{2})|[\x80-\xBF]{3,})/S',
		'--', $some_string );

	//reject overly long 3 byte sequences and UTF-16 surrogates and replace with ?
	$some_string = preg_replace('/\xE0[\x80-\x9F][\x80-\xBF]'.
		'|\xED[\xA0-\xBF][\x80-\xBF]/S','--', $some_string );

	return $some_string;
}

function clearLibCache() {
	sysCmd('truncate ' . LIBCACHE_JSON . ' --size 0');
	cfgdb_update('cfg_system', cfgdb_connect(), 'lib_pos','-1,-1,-1');
}

// add group of songs to playlist (library panel)
function addallToPL($sock, $songs) {
	$cmds = array();

	foreach ($songs as $song) {
		$path = $song;
		array_push($cmds, 'add "' . html_entity_decode($path) . '"');
	}
	
	chainMpdCmds($sock, $cmds);
}

// add to playlist (from folder and radio panels)
function addToPL($sock, $path) {
	//workerLog($path);
	$ext = getFileExt($path);

	// radio dir
	if ($path == 'RADIO') {
		sendMpdCmd($sock, 'listfiles RADIO');
		$resp = readMpdResp($sock);
		
		$files = array();
		$line = strtok($resp, "\n");
		$i = 0;
		
		while ($line) {
			list($param, $value) = explode(': ', $line, 2);
			if ($param == 'file') {
				$files[$i] = $value;
				$i++;
			}
			$line = strtok("\n");
		}

		natcasesort($files);

		foreach($files as $file) {
			sendMpdCmd($sock, 'load "RADIO/' . $file . '"');
			$resp = readMpdResp($sock);
		}
	}
	// playlist 
	elseif ($ext == 'm3u' || $ext == 'pls' || $ext == 'cue' || (strpos($path, '/') === false && $path != 'NAS' && $path != 'SDCARD')) {
		// radio stations
		if (strpos($path, 'RADIO') !== false) {
			// check for playlist as url
			$pls = file_get_contents(MPD_MUSICROOT . $path);
			$url = parseDelimFile($pls, '=')['File1'];
			$end = substr($url, -4);
			if ($end == '.pls' || $end == '.m3u') {
				$path = $url;
			}
		}
		sendMpdCmd($sock, 'load "' . html_entity_decode($path) . '"');
	}
	// file or dir
	else {
		sendMpdCmd($sock, 'add "' . html_entity_decode($path) . '"');
	}

	$resp = readMpdResp($sock);
	return $resp;
}

// get file extension
function getFileExt($file) {
	$pos = strrpos($file, '.');
	$ext = substr($file, $pos + 1);
	
	return strtolower($ext);
}

// parse delimited file
function parseDelimFile($data, $delim) {
	$array = array();
	$line = strtok($data, "\n");
	
	while ($line) {
		list($param, $value) = explode($delim, $line, 2);
		$array[$param] = $value;
		$line = strtok("\n");
	}

	return $array;
}

// get playist
function getPLInfo($sock) {
	sendMpdCmd($sock, 'playlistinfo');
	$resp = readMpdResp($sock);
	
	$pl = parseList($resp);
	
	return $pl;
}

// list contents of saved playlist
function listSavedPL($sock, $plname) {
	sendMpdCmd($sock, 'listplaylist "' . $plname . '"');
	$pl = readMpdResp($sock);
	
	return parseList($pl);
}

// delete saved playlist file
function delPLFile($sock, $plname) {
	sendMpdCmd($sock, 'rm "' . $plname . '"');
	$resp = readMpdResp($sock);
	return $resp;
}

// search mpd database
function searchDB($sock, $querytype, $query) {
	//workerLog($querytype . ', ' . $query);
	switch ($querytype) {
		// list a database path
		case 'lsinfo':
			if (isset($query) && !empty($query)){
				sendMpdCmd($sock, 'lsinfo "' . html_entity_decode($query) . '"');
				break;
			} else {
				sendMpdCmd($sock, 'lsinfo');
				break;
			}
		// search all tags
		case 'any':
			sendMpdCmd($sock, 'search any "' . html_entity_decode($query) . '"');
			break;
		// search specified tags
		case 'specific':
			sendMpdCmd($sock, 'search ' . html_entity_decode($query));
			break;
	}	

	$resp = readMpdResp($sock);
	return parseList($resp);
}

// format mpd lsinfo output
function parseList($resp) {
	if (is_null($resp)) {
		return NULL;
	}
	else {
		$array = array();
		$line = strtok($resp,"\n");
		$file = '';
		$idx = -1;

		while ($line) {
			list ($element, $value) = explode(': ', $line, 2);

			if ($element == 'file') {
				$idx++;
				$file = $value;
				$array[$idx]['file'] = $file;
				$array[$idx]['fileext'] = getFileExt($file);
			}
			else if ($element == 'directory') {
				$idx++;				
				$diridx++; // record directory index for further processing
				$file = $value;
				$array[$idx]['directory'] = $file;
			}
			else if ($element == 'playlist') {
				if (substr($value,0, 5) == 'RADIO' || strtolower(pathinfo($value, PATHINFO_EXTENSION)) == 'cue') {
					$idx++;
					$file = $value;
					$array[$idx]['file'] = $file;
					$array[$idx]['fileext'] = getFileExt($file);
				}
				else {
					$idx++;
					$file = $value;
					$array[$idx]['playlist'] = $file;
				}
			} 
			else {
				$array[$idx][$element] = $value;
				$array[$idx]['TimeMMSS'] = songTime($array[$idx]['Time']);
			}

			$line = strtok("\n");
		}
		
		// put dirs on top
		if (isset($diridx) && isset($array[0]['file']) ) {
			$files = array_slice($array, 0, -$diridx);
            $dirs = array_slice($array, -$diridx);
            $array = array_merge($dirs, $files);
		}
	}
	
	return $array;
}

function songTime($sec) {
	$mins = sprintf('%02d', floor($sec / 60));
	$secs = sprintf(':%02d', (int) $sec % 60);
	
	return $mins . $secs;
}

// format mpd status output
function parseStatus($resp) {

	// this return probably needs a redo
	if (is_null($resp)) {
		return NULL;
		//workerLog('parseStatus(): NULL return');
	}
	else {		
		$array = array();
		$line = strtok($resp, "\n");
		
		while ($line) {
			list($element, $value) = explode(': ', $line, 2);
			$array[$element] = $value;
			$line = strtok("\n");
		} 

		// elapsed time
		// RADIO - time: 293:0, elapsed: 292.501, duration not present
		// SONG  - time: 4:391, elapsed: 4.156, duration: 391.466
		// if state is stop then time, elapsed and duration are not present
		// time x:y where x = elapsed ss, y = duration ss
		$time = explode(':', $array['time']);

		// stopped
		if ($array['state'] == 'stop') {
			$percent = '0';
			$array['elapsed'] = '0';
			$array['time'] = '0';
		}
		// radio, upnp
		elseif (!isset($array['duration']) || $array['duration'] == 0) { // @ohinckel https: //github.com/moode-player/moode/pull/13
			$percent = '0';
			$array['elapsed'] = $time[0];
			$array['time'] = $time[1];
		}
		// song file
		else {	
			if ($time[0] != '0') {
				$percent = round(($time[0] * 100) / $time[1]);
				$array['elapsed'] = $time[0];
				$array['time'] = $time[1];
			}
			else {
				$percent = '0';
				$array['elapsed'] = $time[0];
				$array['time'] = $time[1];
			}
		}

		$array['song_percent'] = $percent;
		$array['elapsed'] = $time[0];
		$array['time'] = $time[1];

		// sample rate
		// example formats for $array['audio'], dsd64:2, dsd128:2, 44100:24:2
	 	$audio_format = explode(':', $array['audio']);
	 	$array['audio_sample_rate'] = formatRate($audio_format[0]);

		// bit depth
		if (strpos($array['audio_sample_rate'], 'dsd') !== false) {
			$array['audio_sample_depth'] = $array['audio_sample_rate'];
		}
		else {
			// workaround for AAC files that show "f" for bit depth, assume decoded to 24 bit
		 	$array['audio_sample_depth'] = $audio_format[1] == 'f' ? '24' : $audio_format[1];
		}
	 	
	 	// channels
	 	if (strpos($array['audio_sample_rate'], 'dsd') !== false) {
	 		$array['audio_channels'] = formatChan($audio_format[1]);
	 	}
	 	else {
		 	$array['audio_channels'] = formatChan($audio_format[2]);
		}

		// bit rate
		if (!isset($array['bitrate']) || trim($array['bitrate']) == '') {
			$array['bitrate'] = '0 bps';
		}
	 	else {
			if ($array['bitrate'] == '0') {
				$array['bitrate'] = '';
				// for aiff, wav files and some radio stations ex: Czech Radio Classic
			 	//$array['bitrate'] = number_format((( (float)$audio_format[0] * (float)$array['audio_sample_depth'] * (float)$audio_format[2] ) / 1000000), 3, '.', '');
			}
			else {
			 	$array['bitrate'] = strlen($array['bitrate']) < 4 ? $array['bitrate'] : substr($array['bitrate'], 0, 1) . '.' . substr($array['bitrate'], 1, 3) ;
			 	$array['bitrate'] .= strpos($array['bitrate'], '.') === false ? ' kbps' : ' mbps';
			}
		}
	}

	return $array;
}

function formatRate ($rate) {
	$rates = array('*' => '*', '32000' => '32', '48000' => '48', '96000' => '96', '192000' => '192', '384000' => '384', '768000' => '768', 
	'22050' => '22.05', '44100' => '44.1', '88200' => '88.2', '176400' => '176.4', '352800' => '352.8', '705600' => '705.6',
	'dsd64' => 'dsd64', 'dsd128' => 'dsd128');

	return $rates[$rate];
}

function formatChan($channels) {
	if ($channels == '1') {
	 	$chanStr = 'Mono';
	} else if ($channels == '2' || $channels == '*') {
	 	$chanStr = 'Stereo';
	} else if ($channels > 2) {
	 	$chanStr = 'Multichannel';
	}

 	return $chanStr;
}

// parse audio output hardware params
function parseHwParams($resp) {
	if (is_null($resp)) {
		return 'Error, parseHwParams response is null';
	}
	elseif ($resp != "closed\n" && $resp != "no setup\n") {
		$array = array();
		$line = strtok($resp, "\n");
		
		while ($line) {
			list ( $element, $value ) = explode(": ", $line);
			$array[$element] = $value;
			$line = strtok("\n");
		} 
		
		// rate "44100 (44100/1)"
	 	$rate = substr($array['rate'], 0, strpos($array['rate'], ' ('));
	 	$array['rate'] = formatRate($rate);
	 	$_rate = (float)$rate;
	 	
		// format DSD_U16_BE" or "DSD_U32_BE"
		if (substr($array['format'], 0, 3) == 'DSD') {
			$_bits = (float)substr($array['format'], 5, 2);
			$array['format'] = 'DSD bitstream';
		}
		// format "S24_3LE" etc
		else {
			$array['format'] = substr($array['format'], 1, 2);
			$_bits = (float)$array['format'];
		}
		
		// channels
		$_chans = (float)$array['channels'];
		$array['channels'] = formatChan($array['channels']);

		// mbps rate
		$array['status'] = 'active';
		$array['calcrate'] = number_format((($_rate * $_bits * $_chans) / 1000000), 3, '.', '');	 
	}
	else {		
		$array['status'] = trim($resp, "\n");
		$array['calcrate'] = '0 bps';	 
	}
	
	return $array;
}

// parse mpd currentsong output
function parseCurrentSong($sock) {
	sendMpdCmd($sock, 'currentsong');
	$resp = readMpdResp($sock);

	if (is_null($resp) ) {
		return 'Error, parseCurrentSong response is null';
	} else {
		$array = array();
		$line = strtok($resp, "\n");

		while ($line) {
			list ($element, $value) = explode(": ", $line, 2);
			$array[$element] = $value;
			$line = strtok("\n");
		}
		
		return $array;
	}
}

// parse cfg_mpd settings
function parseCfgMpd($dbh) {
	$result = cfgdb_read('cfg_mpd', $dbh);
	$array = array();
	
	foreach ($result as $row) {
		$array[$row['param']] = $row['value'];
	}
	
	// ex 44100:16:2 or disabled
	if ($array['audio_output_format'] == 'disabled') {
	 	$array['audio_output_rate'] = '';
	 	$array['audio_output_depth'] = '';
	 	$array['audio_output_chan'] = '';
	} else {
	 	$format = explode(":", $array['audio_output_format']);
	 	$array['audio_output_rate'] = formatRate($format[0]);
	 	$array['audio_output_depth'] = $format[1];
	 	$array['audio_output_chan'] = formatChan($format[2]);
	}
	
	return $array;
}
	
// parse radio station file
function parseStationFile($resp) {
	if (is_null($resp) ) {
		return 'Error, parseStationFile response is null';
	} else {
		$array = array();
		$line = strtok($resp, "\n");

		while ($line) {
			list ($element, $value) = explode("=", $line, 2);
			$array[$element] = $value;
			$line = strtok("\n");
		} 
	}
		
	return $array;
}
	
// parse play history log
function parsePlayHist($resp) {
	if (is_null($resp) ) {
		return 'parsePlayHist(): parsePlayHist response is null';
	} else {
		$array = array();
		$line = strtok($resp, "\n");
		$i = 0;
		
		while ( $line ) {
			$array[$i] = $line;
			$i++;
			$line = strtok("\n");
		} 
	}

	return $array;
}

// update play history log
function updPlayHist($historyitem) {
	$file = '/var/local/www/playhistory.log';
	$fh = fopen($file, 'a') or exit('updPlayHist(): file open failed on ' . $file);

	fwrite($fh, $historyitem . "\n");
	fclose($fh);
	
	return 'OK';
}
	
// session and sql table management
function playerSession($action, $var, $value) {
	$status = session_status();

	// open session
	if ($action == 'open') {		
		if($status != 2) { // 2 = active session
			$sessionid = playerSession('getsessionid'); // session not active so get from sql
			if (!empty($sessionid)) {
				session_id($sessionid); // set session to existing id
				session_start();
			} else {
				session_start();
				playerSession('storesessionid'); // store new session id
			}
		}
	
		// load cfg_system sql table into session vars
		$dbh  = cfgdb_connect();
		$params = cfgdb_read('cfg_system', $dbh);

		foreach ($params as $row) {
			$_SESSION[$row['param']] = $row['value'];
		}
		
		$dbh  = null;
	}

	// unlock session files
	if ($action == 'unlock') {
		session_write_close();
	}
	
	// unset and destroy session
	if ($action == 'destroy') {
		session_unset();
		
		if (session_destroy()) {
			$dbh  = cfgdb_connect();
			
			// clear the session id 
			if (cfgdb_update('cfg_system', $dbh, 'sessionid','')) {
				$dbh = null;
				return true;
			} else {
				echo "cannot reset session on SQLite datastore";
				return false;
			}
		}
	}
	
	// store a value in the cfgdb and session var
	if ($action == 'write') {
		$_SESSION[$var] = $value;
		$dbh  = cfgdb_connect();
		cfgdb_update('cfg_system', $dbh, $var, $value);
		$dbh = null;
	}
	
	// store session id
	if ($action == 'storesessionid') {
		$sessionid = session_id();
		playerSession('write', 'sessionid', $sessionid);
	}
	
	// get session id from sql (used in worker)
	if ($action == 'getsessionid') {
		$dbh  = cfgdb_connect();
		$result = cfgdb_read('cfg_system', $dbh, 'sessionid');
		$dbh = null;

		return $result['0']['value'];
	}
}

function cfgdb_connect() {
	if ($dbh  = new PDO(SQLDB)) {
		return $dbh;
	} else {
		echo "cannot open SQLite database";
		return false;
	}
}

function cfgdb_read($table, $dbh, $param, $id) {
	if(!isset($param)) {
		$querystr = 'SELECT * FROM ' . $table;
	}
	else if (isset($id)) {
		$querystr = "SELECT * FROM " . $table . " WHERE id='" . $id . "'";
	}
	else if ($param == 'mpdconf') {
		$querystr = "SELECT param, value FROM cfg_mpd WHERE value!=''";
	}
	else if ($table == 'cfg_audiodev') {
		$filter = $param == 'all' ? ' WHERE list="yes"' : ' WHERE name="' . $param . '" AND list="yes"';
		$querystr = 'SELECT name, dacchip, chipoptions, iface, list, driver, drvoptions FROM ' . $table . $filter;
	}
	else if ($table == 'cfg_theme') {
		$querystr = 'SELECT theme_name, tx_color, bg_color, mbg_color FROM ' . $table . ' WHERE theme_name="' . $param . '"';
	}
	else if ($table == 'cfg_radio') {
		$querystr = 'SELECT station, name, logo FROM ' . $table . ' WHERE station="' . $param . '"';
	}
	else {
		$querystr = 'SELECT value FROM ' . $table . ' WHERE param="' . $param . '"';
	}

	$result = sdbquery($querystr, $dbh);
	return $result;
}

function cfgdb_update($table, $dbh, $key, $value) {
	switch ($table) {
		case 'cfg_system':
			$querystr = "UPDATE " . $table . " SET value='" . $value . "' where param='" . $key . "'";
			break;
		
		case 'cfg_mpd':
			$querystr = "UPDATE " . $table . " SET value='" . $value . "' where param='" . $key . "'";
			break;
		
		case 'cfg_network':
			// use escaped single quotes in ssid and pwd
			$querystr = "UPDATE " . $table . 
				" SET method='" . $value['method'] . 
				"', ipaddr='" . $value['ipaddr'] . 
				"', netmask='" . $value['netmask'] . 
				"', gateway='" . $value['gateway'] . 
				"', pridns='" . $value['pridns'] . 
				"', secdns='" . $value['secdns'] . 
				"', wlanssid='" . str_replace("'", "''", $value['wlanssid']) . 
				"', wlansec='" . $value['wlansec'] . 
				"', wlanpwd='" . str_replace("'", "''", $value['wlanpwd']) . 
				"' WHERE iface='" . $key . "'";				
			//workerLog('cfgdb_update: ' . $querystr);
			break;
		
		case 'cfg_source':
			$querystr = "UPDATE " . $table . " SET name='" . $value['name'] . "', type='" . $value['type'] . "', address='" . $value['address'] . "', remotedir='" . $value['remotedir'] . "', username='" . $value['username'] . "', password='" . $value['password'] . "', charset='" . $value['charset'] . "', rsize='" . $value['rsize'] . "', wsize='" . $value['wsize'] . "', options='" . $value['options'] . "', error='" . $value['error'] . "' WHERE id=" . $value['id'];
			break;
		
		case 'cfg_audiodev':
			$querystr = "UPDATE " . $table . " SET chipoptions='" . $value . "' WHERE name='" . $key . "'";
			break;
		
		case 'cfg_radio':
			$querystr = "UPDATE " . $table . " SET station='" . $value . "' WHERE name='" . $key . "'";
			break;
		case 'cfg_sl':
			$querystr = "UPDATE " . $table . " SET value='" . $value . "' WHERE param='" . $key . "'";
			break;
		case 'cfg_airplay':
			$querystr = "UPDATE " . $table . " SET value='" . $value . "' WHERE param='" . $key . "'";
			break;
		case 'cfg_spotify':
			$querystr = "UPDATE " . $table . " SET value='" . $value . "' WHERE param='" . $key . "'";
			break;
		case 'cfg_upnp':
			$querystr = "UPDATE " . $table . " SET value='" . $value . "' WHERE param='" . $key . "'";
			break;
		case 'cfg_eqfa4p':
			$querystr = "UPDATE " . $table . 
				" SET master_gain='" . $value['master_gain'] . 
				"', band1_params='" . $value['band1_params'] . 
				"', band2_params='" . $value['band2_params'] . 
				"', band3_params='" . $value['band3_params'] . 
				"', band4_params='" . $value['band4_params'] . 
				"' WHERE curve_name='" . $key . "'";
			//workerLog('cfgdb_update: ' . $querystr);
			break;
		case 'cfg_gpio':
			$querystr = "UPDATE " . $table . 
				" SET enabled='" . $value['enabled'] . 
				"', pin='" . $value['pin'] . 
				"', command='" . $value['command'] . 
				"', param='" . $value['param'] . 
				"', value='" . $value['value'] . 
				"' WHERE id='" . $key . "'";
			//workerLog('cfgdb_update: ' . $querystr);
			break;
	}

	if (sdbquery($querystr,$dbh)) {
		return true;
	}
	else {
		return false;
	}
}

function cfgdb_write($table, $dbh, $values) {
	$querystr = "INSERT INTO " . $table . " VALUES (NULL, " . $values . ")"; // NULL causes the Id column to be set to the next number

	if (sdbquery($querystr,$dbh)) {
		return true;
	}
	else {
		return false;
	}
}

function cfgdb_delete($table, $dbh, $id) {
	if (!isset($id)) {
		$querystr = "DELETE FROM " . $table;
	}
	else {
		$querystr = "DELETE FROM " . $table . " WHERE id=" . $id;
	}

	if (sdbquery($querystr,$dbh)) {
		return true;
	}
	else {
		return false;
	}
}

function sdbquery($querystr, $dbh) {
	$query = $dbh->prepare($querystr);
	if ($query->execute()) {
		$result = array();
		$i = 0;
		foreach ($query as $value) {
			$result[$i] = $value;
			$i++;
		}
		$dbh = null;
		if (empty($result)) {
			return true;
		}
		else {
			return $result;
		}
	}
	else {
		return false;
	}
}

function updMpdConf($i2sdevice) {
	$mpdcfg = sdbquery("SELECT param,value FROM cfg_mpd WHERE value!=''", cfgdb_connect());
	$mpdver = substr($_SESSION['mpdver'], 0, 4);

	$data .= "#########################################\n";
	$data .= "# This file is automatically generated   \n";
	$data .= "# by the MPD configuration page.         \n";
	$data .= "#########################################\n\n";
	
	foreach ($mpdcfg as $cfg) {
		switch ($cfg['param']) {
			// code block or other params
			case 'metadata_to_use':
				$data .= $mpdver == '0.20' ? '' : $cfg['param'] . " \"" . $cfg['value'] . "\"\n";
				break;
			case 'device':
				$device = $cfg['value'];
				playerSession('write', 'cardnum', $cfg['value']);
				break;
			case 'mixer_type':
				$mixertype = $cfg['value'] == 'disabled' ? 'none' : $cfg['value'];
				$hwmixer = $cfg['value'] == 'hardware' ? getMixerName($i2sdevice) : '';
				playerSession('write', 'mpdmixer', $cfg['value']);
				break;
			case 'dop':
				$dop = $cfg['value'];
				break;
			case 'audio_output_format':
				$data .= $cfg['value'] == 'disabled' ? '' : $cfg['param'] . " \"" . $cfg['value'] . "\"\n";
				break;
			case 'samplerate_converter':
				$samplerate_converter = $cfg['value'];
				break;
			case 'sox_multithreading':
				$sox_multithreading = $cfg['value'];
				break;
			case 'replaygain_handler':
				$replaygain_handler = $mpdver == '0.21' ? '' : $cfg['param'] . " \"" . $cfg['value'] . "\"\n";
				break;
			case 'buffer_before_play':
				$data .= $mpdver == '0.21' ? '' : $cfg['param'] . " \"" . $cfg['value'] . "\"\n";
				break;
			case 'auto_resample':
				$auto_resample = $cfg['value'];
				break;
			case 'auto_channels':
				$auto_channels = $cfg['value'];
				break;
			case 'auto_format':
				$auto_format = $cfg['value'];
				break;
			case 'buffer_time':
				$buffer_time = $cfg['value'];
				break;
			case 'period_time':
				$period_time = $cfg['value'];
				break;	
			// default param handling
			default:
				$data .= $cfg['param'] . " \"" . $cfg['value'] . "\"\n";
				break;
		}
	}

	// input
	$data .= "max_connections \"128\"\n";
	$data .= "\n";
	$data .= "decoder {\n";
	$data .= "plugin \"ffmpeg\"\n";
	$data .= "enabled \"yes\"\n";
	$data .= "}\n\n";
	$data .= "input {\n";
	$data .= "plugin \"curl\"\n";
	$data .= "}\n\n";

	// resampler
	$data .= "resampler {\n";
	$data .= "plugin \"soxr\"\n";
	$data .= "quality \"" . $samplerate_converter . "\"\n";
	$data .= "threads \"" . $sox_multithreading . "\"\n";
	$data .= "}\n\n";

	// alsa local outputs
	$names = array(
		"name \"ALSA default\"\n" . "device \"hw:" . $device . ",0\"\n",
		"name \"ALSA crossfeed\"\n" . "device \"crossfeed\"\n",
		"name \"ALSA parametric eq\"\n" . "device \"eqfa4p\"\n",
		"name \"ALSA graphic eq\"\n" . "device \"alsaequal\"\n",
		"name \"ALSA polarity inversion\"\n" . "device \"invpolarity\"\n");
	foreach ($names as $name) {
		$data .= "audio_output {\n";
		$data .= "type \"alsa\"\n";
		$data .= $name;
		$data .= "mixer_type \"" . $mixertype . "\"\n";
		$data .= $mixertype == 'hardware' ? "mixer_control \"" . $hwmixer . "\"\n" . "mixer_device \"hw:" . $device . "\"\n" . "mixer_index \"0\"\n" : '';
		$data .= "dop \"" . $dop . "\"\n";
		$data .= $replaygain_handler;
		$data .= "auto_resample \"" . $auto_resample . "\"\n";
		$data .= "auto_channels \"" . $auto_channels . "\"\n";
		$data .= "auto_format \"" . $auto_format . "\"\n";
		$data .= "buffer_time \"" . $buffer_time . "\"\n";
		$data .= "period_time \"" . $period_time . "\"\n";
		$data .= "}\n\n";
	}

	// alsa bluetooth output
	$data .= "audio_output {\n";
	$data .= "type \"alsa\"\n";
	$data .= "name \"ALSA bluetooth\"\n";
	$data .= "device \"btstream\"\n";
	$data .= "mixer_type \"software\"\n";
	$data .= $replaygain_handler;
	$data .= "auto_resample \"" . $auto_resample . "\"\n";
	$data .= "auto_channels \"" . $auto_channels . "\"\n";
	$data .= "auto_format \"" . $auto_format . "\"\n";
	$data .= "buffer_time \"" . $buffer_time . "\"\n";
	$data .= "period_time \"" . $period_time . "\"\n";
	$data .= "}\n\n";

	// mpd httpd
	$data .= "audio_output {\n";
	$data .= "type \"httpd\"\n";
	$data .= "name \"HTTP stream\"\n";
	$data .= "port \"" . $_SESSION['mpd_httpd_port'] . "\"\n";
	$data .= "encoder \"" . $_SESSION['mpd_httpd_encoder'] . "\"\n";
	$data .= $_SESSION['mpd_httpd_encoder'] == 'flac' ? "compression \"0\"\n" : "bitrate \"320\"\n";
	$data .= "tags \"yes\"\n";
	$data .= "always_on \"yes\"\n";
	$data .= "}\n";

	$fh = fopen('/etc/mpd.conf', 'w');
	fwrite($fh, $data);
	fclose($fh);

	// update confs with device num (cardnum)
	sysCmd("sed -i '/slave.pcm \"plughw/c\ \tslave.pcm \"plughw:" . $device . ",0\";' /usr/share/alsa/alsa.conf.d/crossfeed.conf");
	sysCmd("sed -i '/slave.pcm \"plughw/c\ \tslave.pcm \"plughw:" . $device . ",0\";' /usr/share/alsa/alsa.conf.d/eqfa4p.conf");
	sysCmd("sed -i '/slave.pcm \"plughw/c\ \tslave.pcm \"plughw:" . $device . ",0\";' /usr/share/alsa/alsa.conf.d/alsaequal.conf");
	sysCmd("sed -i '/pcm \"hw/c\ \t\tpcm \"hw:" . $device . ",0\"' /usr/share/alsa/alsa.conf.d/invpolarity.conf");
	sysCmd("sed -i '/card/c\ \t    card " . $device . "' /usr/share/alsa/alsa.conf.d/20-bluealsa-dmix.conf");
	sysCmd("sed -i '/AUDIODEV/c\AUDIODEV=plughw:" . $device . ",0' /etc/bluealsaaplay.conf");

	// store device name for Audio info popup
	if ($_SESSION['i2sdevice'] != 'none') {
		$adevname = $_SESSION['i2sdevice'];
	}
	else if ($device == '0') {
		$adevname = 'On-board audio device';
	}
	else {
		$adevname = 'USB audio device';
	}
	playerSession('write', 'adevname', $adevname);
}

// return amixer name
function getMixerName($i2sdevice) {
	// USB and On-board: default is PCM otherwise use returned mixer name
	if ($i2sdevice == 'none') {
		$result = sysCmd('/var/www/command/util.sh get-mixername');
		$mixername = $result[0] == '' ? 'PCM' : $result[0];
	}
	// I2S exceptions
	elseif ($i2sdevice == 'HiFiBerry Amp(Amp+)' || $i2sdevice == 'Allo Katana DAC' || ($i2sdevice == 'Allo Piano 2.1 Hi-Fi DAC' && $_SESSION['piano_dualmode'] != 'None')) {
		$mixername = 'Master';
	}
	// I2S default
	else {
		$mixername = 'Digital';
	}

	return $mixername;
}

// make text for audio device field (mpd and sqe-config)
function getDeviceNames () {
	$dev = array();

	$card0 = file_get_contents('/proc/asound/card0/id');
	$card1 = file_get_contents('/proc/asound/card1/id');
	
	// device 0
	if ($card0 == "ALSA\n") {
		$dev[0] = 'On-board audio device';
	} 
	else if ($_SESSION['i2sdevice'] != 'none') {
		$dev[0] = 'I2S audio device';
	}
	else {
		$dev[0] = '';
	}
	
	// device 1
	if ($card1 != '' && $card0 == "ALSA\n") {
		$dev[1] = 'USB audio device';
	}
	else {
		$dev[1] = '';
	}

	return $dev;
}

function sourceCfg($queueargs) {
	$action = $queueargs['mount']['action'];
	unset($queueargs['mount']['action']);
	
	switch ($action) {
		case 'add':
			$dbh = cfgdb_connect();
			unset($queueargs['mount']['id']);
			
			// format values string
			foreach ($queueargs['mount'] as $key => $value) {
				$values .= "'" . SQLite3::escapeString($value) . "',";
			}
			$values .= "''"; // error column

			// write new entry
			cfgdb_write('cfg_source', $dbh, $values);
			$newmountID = $dbh->lastInsertId();
			
			if (sourceMount('mount', $newmountID)) {
				$return = 1;
			}
			else {
				$return = 0;
			}

			break;
		
		case 'edit':
			$dbh = cfgdb_connect();
			$mp = cfgdb_read('cfg_source', $dbh, '', $queueargs['mount']['id']);
			
			cfgdb_update('cfg_source', $dbh, '', $queueargs['mount']);
			
			if ($mp[0]['type'] == 'cifs') {
				sysCmd('umount -l "/mnt/NAS/' . $mp[0]['name'] . '"'); // lazy umount
			}
			else {
				sysCmd('umount -f "/mnt/NAS/' . $mp[0]['name'] . '"'); // force unmount (for unreachable NFS)
			}
			
			if ($mp[0]['name'] != $queueargs['mount']['name']) {
				sysCmd('rmdir "/mnt/NAS/' . $mp[0]['name'] . '"');
				sysCmd('mkdir "/mnt/NAS/' . $queueargs['mount']['name'] . '"');
			}
			
			if (sourceMount('mount', $queueargs['mount']['id'])) {
				$return = 1;
			}
			else {
				$return = 0;
			}
			
			break;
		
		case 'delete':
			$dbh = cfgdb_connect();
			$mp = cfgdb_read('cfg_source', $dbh, '', $queueargs['mount']['id']);
			
			if ($mp[0]['type'] == 'cifs') {
				sysCmd('umount -l "/mnt/NAS/' . $mp[0]['name'] . '"'); // lazy umount
			}
			else {
				sysCmd('umount -f "/mnt/NAS/' . $mp[0]['name'] . '"'); // force unmount (for unreachable NFS)
			}

			sysCmd('rmdir "/mnt/NAS/' . $mp[0]['name'] . '"');

			if (cfgdb_delete('cfg_source', $dbh, $queueargs['mount']['id'])) {
				$return = 1;
			}
			else {
				$return = 0;
			}

			break;
	}

	return $return;
}

function sourceMount($action, $id) {
	switch ($action) {
		case 'mount':
			$dbh = cfgdb_connect();
			$mp = cfgdb_read('cfg_source', $dbh, '', $id);

			sysCmd("mkdir \"/mnt/NAS/" . $mp[0]['name'] . "\"");

			if ($mp[0]['type'] == 'cifs') {
				// smb/cifs mount
				// new w/dbl quoted username and password
				$mountstr = "mount -t cifs \"//" . $mp[0]['address'] . "/" . $mp[0]['remotedir'] . "\" -o username=\"" . $mp[0]['username'] . "\",password=\"" . $mp[0]['password'] . "\",rsize=" . $mp[0]['rsize'] . ",wsize=" . $mp[0]['wsize'] . ",iocharset=" . $mp[0]['charset'] . "," . $mp[0]['options'] . " \"/mnt/NAS/" . $mp[0]['name'] . "\"";
				// original
				//$mountstr = "mount -t cifs \"//" . $mp[0]['address'] . "/" . $mp[0]['remotedir'] . "\" -o username=" . $mp[0]['username'] . ",password='" . $mp[0]['password'] . "',rsize=" . $mp[0]['rsize'] . ",wsize=" . $mp[0]['wsize'] . ",iocharset=" . $mp[0]['charset'] . "," . $mp[0]['options'] . " \"/mnt/NAS/" . $mp[0]['name'] . "\"";
			}
			else {
				// nfs mount
				$mountstr = "mount -t nfs -o " . $mp[0]['options'] . " \"" . $mp[0]['address'] . ":/" . $mp[0]['remotedir'] . "\" \"/mnt/NAS/" . $mp[0]['name'] . "\"";
			}

			$sysoutput = sysCmd($mountstr);
			debugLog('sourceMount(): mountstr=(' . $mountstr . ')');
			debugLog('sourceMount(): sysoutput=(' . implode("\n", $sysoutput) . ')');

			if (empty($sysoutput)) {
				if (!empty($mp[0]['error'])) {
					$mp[0]['error'] = '';
					cfgdb_update('cfg_source', $dbh, '', $mp[0]);
				}

				$return = 1;
			}
			else {
				sysCmd("rmdir \"/mnt/NAS/" . $mp[0]['name'] . "\"");
				$mp[0]['error'] = 'Mount error';
				workerLog('sourceMount(): Mount error: (' . implode("\n", $sysoutput) . ')');
				cfgdb_update('cfg_source', $dbh, '', $mp[0]);

				$return = 0;
			}	

			break;
		
		case 'mountall':
			$dbh = cfgdb_connect();

			// cfgdb_read returns: query results === true if results empty | false if query failed
			$mounts = cfgdb_read('cfg_source', $dbh);

			foreach ($mounts as $mp) {
				if (!mountExists($mp['name'])) {
					$return = sourceMount('mount', $mp['id']);
				}
			}

			// status returned to worker
			if ($mounts === true) {
				$return = 'none configured';
			} 
			else if ($mounts === false) {
				$return = 'query failed';
			}
			else {
				$return = 'mountall initiated';
			}

			break;

		case 'unmountall':
			$dbh = cfgdb_connect();

			// cfgdb_read returns: query results === true if results empty | false if query failed
			$mounts = cfgdb_read('cfg_source', $dbh);
			//workerLog('sourceMount(): $mounts= <' . $mounts . '>');

			foreach ($mounts as $mp) {
				//workerLog('unmountall: name=' . $mp['name'] . ', type=' . $mp['type']);
				if (mountExists($mp['name'])) {
					if ($mp['type'] == 'cifs') {
						sysCmd('umount -f "/mnt/NAS/' . $mp['name'] . '"'); // change from -l (lazy) to force unmount
					}
					else {
						sysCmd('umount -f "/mnt/NAS/' . $mp['name'] . '"'); // force unmount (for unreachable NFS)
					}
				}
			}

			// status returned to worker
			if ($mounts === true) {
				$return = 'none configured';
			} 
			else if ($mounts === false) {
				$return = 'query failed';
			}
			else {
				$return = 'unmountall initiated';
			}

			break;
	}

	return $return;
}

function ui_notify($notify) {
	$script .= "<script>";
	$script .= "jQuery(document).ready(function() {";
	$script .= "$.pnotify.defaults.history = false;";
	$script .= "$.pnotify({";
	$script .= "title: '" . $notify['title'] . "',";
	$script .= "text: '" . $notify['msg'] . "',";
	//$script .= "icon: 'icon-ok',";
	$script .= "icon: '',";
	if (isset($notify['duration'])) {	
		$script .= "delay: " . strval($notify['duration'] * 1000) . ",";
	} else {
		$script .= "delay: '2000',";
	}
	$script .= "opacity: 1.0});";
	$script .= "});";
	$script .= "</script>";
	echo $script;
}

function waitWorker($sleeptime, $caller) {
	debugLog('waitWorker(): Start (' . $caller . ', w_active=' . $_SESSION['w_active'] . ')');
	$loopcnt = 0;

	if ($_SESSION['w_active'] == 1) {
		do {
			sleep($sleeptime);
			session_start();
			session_write_close();

			debugLog('waitWorker(): Wait  (' . ++$loopcnt . ')');

		} while ($_SESSION['w_active'] != 0);
	}

	debugLog('waitWorker(): End   (' . $caller . ', w_active=' . $_SESSION['w_active'] . ')');
} 

function mountExists($mountname) {
	$result = sysCmd('mount | grep -ow ' . '"/mnt/NAS/' . $mountname .'"');
	if (!empty($result)) {
		return true;
	}
	else {
		return false;
	}
}

// return kernel version without "-v7" suffix
function getKernelVer($kernel) {
	return str_replace('-v7', '', $kernel);
}

// submit job to worker.php
function submitJob($jobName, $jobArgs, $title, $msg, $duration) {
	if ($_SESSION['w_lock'] != 1 && $_SESSION['w_queue'] == '') {
		session_start();
		
		$_SESSION['w_queue'] = $jobName;
		$_SESSION['w_active'] = 1;
		$_SESSION['w_queueargs'] = $jobArgs;
		
		// we do it this way because $_SESSION['notify'] is tested in footer.php and jobs can be submitted by js
		if ($title !== '') {$_SESSION['notify']['title'] = $title;}
		if ($msg !== '') {$_SESSION['notify']['msg'] = $msg;}
		if (isset($duration)) {$_SESSION['notify']['duration'] = $duration;}
		
		session_write_close();
		return true;
	}
	else {
		echo json_encode('worker busy');
		return false;
	}
}

// extract "Audio" metadata from file and format it for display
function getEncodedAt($song, $outformat) {
	// outformat 'default' = 16/44.1k FLAC
	// outformat 'verbose' = 16 bit, 44.1 kHz, Stereo FLAC
	// bit depth is omitted if the format is lossy

	$encoded = 'NULL';

	// radio station
	if (isset($song['Name']) || (substr($song['file'], 0, 4) == 'http' && !isset($song['Artist']))) {
		$encoded = $outformat == 'verbose' ? 'VBR compression' : 'VBR';
	}
	// UPnP file
	elseif (substr($song['file'], 0, 4) == 'http' && isset($song['Artist'])) {
		$encoded = 'Unknown';
	} 
	// DSD file
	elseif (getFileExt($song['file']) == 'dsf' || getFileExt($song['file']) == 'dff') {
		$encoded = 'DSD';
	} 
	// PCM file
	else {
		$fullpath = MPD_MUSICROOT . $song['file'];

		if ($song['file'] == '' || !file_exists($fullpath)) {
			return 'File does not exist';
		}

		// hack to allow file names with accented characters to work when passed to mediainfo via exec()
		$locale = 'en_GB.utf-8'; // is this still needed?
		setlocale(LC_ALL, $locale);
		putenv('LC_ALL=' . $locale);

		// mediainfo
		$cmd = 'mediainfo --Inform="Audio;file:///var/www/mediainfo.tpl" ' . '"' . $fullpath . '"';
		debugLog($cmd);
		$result = sysCmd($cmd);

		$bitdepth = $result[0] == '' ? '?' : $result[0];
		$samplerate = $result[1] == '' ? '?' : $result[1];
		$channels = $result[2];
		$format = $result[3];

		if ($outformat == 'default') {
			$encoded = $bitdepth == '?' ? formatRate($samplerate) . ' ' . $format : $bitdepth . '/' . formatRate($samplerate) . ' ' . $format;
		}
		else {
			$encoded = $bitdepth == '?' ? formatRate($samplerate) . ' kHz, ' . formatChan($channels) . ' ' . $format : $bitdepth . ' bit, ' . formatRate($samplerate) . ' kHz, ' . formatChan($channels) . ' ' . $format;
		}
	}

	return $encoded;
}

function stopSps () {
	sysCmd('killall shairport-sync');
	sysCmd('/var/www/vol.sh -restore');
	// reset to inactive
	playerSession('write', 'airplayactv', '0');
	$GLOBALS['aplactive'] = '0';
	sendEngCmd('aplactive0');
}

function startSps() {
	// verbose logging
	if ($_SESSION['debuglog'] == '1') {
		$logging = '-vv';
		$logfile = '/var/log/shairport-sync.log';
	}
	else {
		$logging = '';
		$logfile = '/dev/null';
	}

	// get device num
	$array = sdbquery('select value from cfg_mpd where param="device"', cfgdb_connect());
	$device = $array[0]['value'];

	if ($_SESSION['alsaequal'] != 'Off') {
		$device = 'alsaequal';
	}
	elseif ($_SESSION['eqfa4p'] != 'Off') {
		$device = 'eqfa4p';
	}
	else {
		$device = 'hw:' . $array[0]['value'];
	}

	// interpolation param handled in config file
	$cmd = '/usr/local/bin/shairport-sync ' . $logging .
		' -a "' . $_SESSION['airplayname'] . '" ' . 
		//'-w -B /var/local/www/commandw/spspre.sh -E /var/local/www/commandw/spspost.sh ' .
		'-- -d ' . $device . ' > ' . $logfile . ' 2>&1 &';

	debugLog('worker: (' . $cmd . ')');
	sysCmd($cmd);
}

function stopSpotify() {
	sysCmd('killall librespot');
	sysCmd('/var/www/vol.sh -restore');
	// reset to inactive
	playerSession('write', 'spotactive', '0');
	$GLOBALS['spotactive'] = '0';
	sendEngCmd('spotactive0');
}

function startSpotify() {
	$result = cfgdb_read('cfg_spotify', cfgdb_connect());
	$cfg_spotify = array();
	foreach ($result as $row) {
		$cfg_spotify[$row['param']] = $row['value'];
	}

	if ($_SESSION['alsaequal'] != 'Off') {
		$device = 'alsaequal';
	}
	elseif ($_SESSION['eqfa4p'] != 'Off') {
		$device = 'eqfa4p';
	}
	else {
		$device = 'plughw:' . $_SESSION['cardnum'];
	}

	$linear_volume = $cfg_spotify['volume_curve'] == 'Linear' ? ' --linear-volume' : '';
	$volume_normalization = $cfg_spotify['volume_normalization'] == 'Yes' ? ' --enable-volume-normalisation --normalisation-pregain ' .  $cfg_spotify['normalization_pregain'] : '';
	
	$cmd = 'librespot' . 
		' --name "' . $_SESSION['spotifyname'] . '"' .
		' --bitrate ' . $cfg_spotify['bitrate'] . 
		' --initial-volume ' . $cfg_spotify['initial_volume'] . 
		$linear_volume . 
		$volume_normalization .
		' --cache /var/local/www/spotify_cache --disable-audio-cache --backend alsa --device "' . $device . '"' . // audio file cache eats disk space
		' --onevent /var/local/www/commandw/spotevent.sh' . 
		' > /dev/null 2>&1 &';
		//' -v > /home/pi/librespot.txt 2>&1 &'; // r45a debug

	debugLog('worker: (' . $cmd . ')');
	sysCmd($cmd);
}

// start bluetooth
function startBt() {
	sysCmd('systemctl start hciuart');
	sysCmd('systemctl start bluetooth');
	sysCmd('systemctl start bluealsa');

	// we should have a MAC address 
	$result = sysCmd('ls /var/lib/bluetooth');
	if ($result[0] == '') {
		workerLog('worker: Bluetooth error, no MAC address');
	}
	// initialize controller
	else {
		$result = sysCmd('/var/www/command/bt.sh -i');
		workerLog('worker: Bluetooth controller initialized');
	}
}

// start dlna server
function startMiniDlna() {
	sysCmd('systemctl start minidlna');
}

// start lcd updater
function startLcdUpdater() {
	$script = $_SESSION['lcdupscript'] != '' ? $_SESSION['lcdupscript'] : 'cp /var/local/www/currentsong.txt /home/pi/lcd.txt';
	$cmd = '/var/www/command/lcdup.sh ' . '"' . $script . '"';
	sysCmd($cmd);
}

// start gpio button handler
function startGpioSvc() {
	sysCmd('/var/www/command/gpio-buttons.py > /dev/null 2&1 &');
}

// get upnp coverart url
function getUpnpCoverUrl() {
	$result = sysCmd('upexplorer --album-art "' . $_SESSION['upnpname'] . '"');
	return $result[0];
}

// configure chip options
function cfgChipOptions($chipoptions, $chiptype) {
	$array = explode(',', $chipoptions);

	// analog volume, analog volume boost, digital interpolation filter
	if ($chiptype == 'burr_brown_pcm5') {
		sysCmd('amixer -c 0 sset "Analogue" ' . $array[0]);
		sysCmd('amixer -c 0 sset "Analogue Playback Boost" ' . $array[1]);
		sysCmd('amixer -c 0 sset "DSP Program" ' . '"' . $array[2] . '"');
	}
	// oversampling filter, de-emphasis, dop
	else if ($chiptype == 'ess_sabre_katana') {
		sysCmd('amixer -c 0 sset "DSP Program" ' . '"' . $array[0] . '"');
		sysCmd('amixer -c 0 sset "Deemphasis" ' . $array[1]);
		sysCmd('amixer -c 0 sset "DoP" ' . $array[2]);
	}
	// oversampling filter, input select
	else if ($chiptype == 'ess_sabre_audiophonics_q2m') {
		sysCmd('amixer -c 0 sset "FIR Filter Type" ' . '"' . $array[0] . '"');
		sysCmd('amixer -c 0 sset "I2S/SPDIF Select" ' . '"' . $array[1] . '"');
	}
}

// configure network interfaces
function cfgNetIfaces() {
	// default interfaces file
	$fp = fopen('/etc/network/interfaces', 'w');
	$data  = "#########################################\n";
	$data .= "# This file is automatically generated by\n";
	$data .= "# the player Network configuration page. \n";
	$data .= "#########################################\n\n";
	$data  .= "# interfaces(5) file used by ifup(8) and ifdown(8)\n\n";	
	$data  .= "# Please note that this file is written to be used with dhcpcd\n";
	$data  .= "# For static IP, consult /etc/dhcpcd.conf and 'man dhcpcd.conf'\n\n";
	$data  .= "# Include files from /etc/network/interfaces.d:\n";
	$data  .= "source-directory /etc/network/interfaces.d\n";
	fwrite($fp, $data);
	fclose($fp);

	// write dhcpcd.conf
	// eth0
	$fp = fopen('/etc/dhcpcd.conf', 'w');
	$data  = "#########################################\n";
	$data .= "# This file is automatically generated by\n";
	$data .= "# the player Network configuration page. \n";
	$data .= "#########################################\n\n";
	$data .= "hostname\n";
	$data .= "clientid\n";
	$data .= "persistent\n";
	$data .= "option rapid_commit\n";
	$data .= "option domain_name_servers, domain_name, domain_search, host_name\n";
	$data .= "option classless_static_routes\n";
	$data .= "option ntp_servers\n";
	$data .= "option interface_mtu\n";
	$data .= "require dhcp_server_identifier\n";
	$data .= "slaac private\n";
	// read network config
	$result = sdbquery('select * from cfg_network', cfgdb_connect());
	// eth0 static
	if ($result[0]['method'] == 'static') {
		$data .= "interface eth0\n";
		$data .= 'static ip_address=' . $result[0]['ipaddr'] . '/' . $result[0]['netmask'] . "\n";
		$data .= 'static routers=' . $result[0]['gateway'] . "\n";
		$data .= 'static domain_name_servers=' . $result[0]['pridns'] . ' ' . $result[0]['secdns'] . "\n";
	}
	// wlan0 static
	if ($result[1]['method'] == 'static') {
		$data .= "interface wlan0\n";
		$data .= 'static ip_address=' . $result[1]['ipaddr'] . '/' . $result[1]['netmask'] . "\n";
		$data .= 'static routers=' . $result[1]['gateway'] . "\n";
		$data .= 'static domain_name_servers=' . $result[1]['pridns'] . ' ' . $result[1]['secdns'] . "\n";
	}
	// wlan0 AP mode
	if (empty($result[1]['wlanssid']) || $result[1]['wlanssid'] == 'blank (activates AP mode)') {
		$data .= "#AP mode\n";
		$data .= "interface wlan0\n";
		$data .= "static ip_address=172.24.1.1/24\n";
		$data .= "nohook wpa_supplicant";
	}
	else {
		$data .= "#AP mode\n";
		$data .= "#interface wlan0\n";
		$data .= "#static ip_address=172.24.1.1/24\n";
		$data .= "#nohook wpa_supplicant";
	}
	fwrite($fp, $data);
	fclose($fp);

	// wpa_supplicant.conf
	$fp = fopen('/etc/wpa_supplicant/wpa_supplicant.conf', 'w');
	$data  = "#########################################\n";
	$data .= "# This file is automatically generated by\n";
	$data .= "# the player Network configuration page. \n";
	$data .= "#########################################\n\n";
	$data .= 'country=' . $_SESSION['wificountry'] . "\n";
	$data .= "ctrl_interface=DIR=/var/run/wpa_supplicant GROUP=netdev\n";
	$data .= "update_config=1\n\n";
	if (!empty($result[1]['wlanssid']) && $result[1]['wlanssid'] != 'blank (activates AP mode)') {
		$data .= "network={\n";
		$data .= 'ssid=' . '"' . $result[1]['wlanssid'] . '"' . "\n";
		$data .= "scan_ssid=1\n";
		// secure network
		if ($result[1]['wlansec'] == 'wpa') {
			//$data .= "key_mgmt=WPA-PSK\n";
			$data .= 'psk=' . '"' . $result[1]['wlanpwd'] . '"' . "\n";
		}
		// no security
		else {
			$data .= "key_mgmt=NONE\n";
		}
		$data .= "}\n";
	}
	fwrite($fp, $data);
	fclose($fp);
}

// configure hostapd conf
function cfgHostApd() {
	$file = '/etc/hostapd/hostapd.conf';
	$fp = fopen($file, 'w');

	// header
	$data  = "#########################################\n";
	$data .= "# This file is automatically generated by\n";
	$data .= "# the player Network configuration page. \n";
	$data .= "#########################################\n\n";

	$data .= "# Interface and driver\n";
	$data .= "interface=wlan0\n";
	$data .= "driver=nl80211\n\n";
	
	$data .= "# Wireless settings\n";
	$data .= "ssid=" . $_SESSION['apdssid'] . "\n";
	$data .= "hw_mode=g\n";
	$data .= "channel=" . $_SESSION['apdchan'] . "\n\n";
	
	$data .= "# Security settings\n";
	$data .= "macaddr_acl=0\n";
	$data .= "auth_algs=1\n";
	$data .= "ignore_broadcast_ssid=0\n";
	$data .= "wpa=2\n";
	$data .= "wpa_key_mgmt=WPA-PSK\n";
	$data .= "wpa_passphrase=" . $_SESSION['apdpwd'] . "\n";
	$data .= "rsn_pairwise=CCMP\n";

	fwrite($fp, $data);
	fclose($fp);
}

function activateApMode() {
	sysCmd('sed -i "/AP mode/,/$p/ d" /etc/dhcpcd.conf');
	sysCmd('sed -i "$ a#AP mode\ninterface wlan0\nstatic ip_address=172.24.1.1/24\nnohook wpa_supplicant" /etc/dhcpcd.conf');
	sysCmd('systemctl daemon-reload');
	sysCmd('systemctl restart dhcpcd');
	sysCmd('systemctl start hostapd');
	sysCmd('systemctl start dnsmasq');
}

function resetApMode() {
	sysCmd('sed -i "/AP mode/,/$p/ d" /etc/dhcpcd.conf');
	sysCmd('sed -i "$ a#AP mode\n#interface wlan0\n#static ip_address=172.24.1.1/24\n#nohook wpa_supplicant" /etc/dhcpcd.conf');
}

function waitForIpAddr($iface, $maxloops, $sleeptime) {
	// defaults
	if (!isset($maxloops)) {$maxloops = 3;}
	if (!isset($sleeptime)) {$sleeptime = 3000000;} // 3 secs

	for ($i = 0; $i < $maxloops; $i++) {
		$ipaddr = sysCmd('ip addr list ' . $iface . " | grep \"inet \" |cut -d' ' -f6|cut -d/ -f1");
		if (!empty($ipaddr[0])) {
			break;
		}
		else {
			workerLog('worker: ' . $iface .' wait '. $i . ' for IP address');
			usleep($sleeptime);
		}
	}

	return $ipaddr;
}

function getHostIp() {
	$eth0ip = '';
	$wlan0ip = '';
	
	// check both interfaces
	$eth0 = sysCmd('ip addr list | grep eth0');
	if (!empty($eth0)) {
		$eth0ip = sysCmd("ip addr list eth0 | grep \"inet \" |cut -d' ' -f6|cut -d/ -f1");
	}

	$wlan0 = sysCmd('ip addr list | grep wlan0');
	if (!empty($wlan0)) {
		$wlan0ip = sysCmd("ip addr list wlan0 | grep \"inet \" |cut -d' ' -f6|cut -d/ -f1");
	}

	// use Ethernet address if present
	if (!empty($eth0ip[0])) {
		$hostip = $eth0ip[0];
	}
	elseif (!empty($wlan0ip[0])) {
		$hostip = $wlan0ip[0];
	}
	else {
		$hostip = '127.0.0.1';
	}

	return $hostip;
}

// return hardware revision
function getHdwrRev() {
	$revname = array(
		'0000' => 'OrangePiPC',
		'0002' => 'Pi-1B 256MB',
		'0003' => 'Pi-1B 256MB',
		'0004' => 'Pi-1B 256MB',
		'0005' => 'Pi-1B 256MB',
		'0006' => 'Pi-1B 256MB',
		'0007' => 'Pi-1A 256MB',
		'0008' => 'Pi-1A 256MB',
		'0009' => 'Pi-1A 256MB',
		'000d' => 'Pi-1B 512MB',
		'000e' => 'Pi-1B 512MB',
		'000f' => 'Pi-1B 512MB',
		'0010' => 'Pi-1B+ 512MB',
		'0011' => 'Pi-CM1 512MB',
		'0012' => 'Pi-1A+ 256MB',
		'0013' => 'Pi-1B+ 512MB',
		'0014' => 'Pi-CM1 512MB',
		'0015' => 'Pi-1A+ 256/512MB',
		'0021' => 'Pi-1A+ 512MB v1.1',
		'0032' => 'Pi-1B+ 512MB v1.2',
		'1040' => 'Pi-2B 1GB v1.0',
		'1041' => 'Pi-2B 1GB v1.1',
		'1041' => 'Pi-2B 1GB v1.1',
		'2042' => 'Pi-2B 1GB v1.2',
		'2082' => 'Pi-3B 1GB v1.2',
		'20d3' => 'Pi-3B+ 1GB v1.3',
		'20a0' => 'Pi-CM3 1GB v1.0',
		'20e0' => 'Pi-3A+ 512 MB v1.0',
		'00c1' => 'Pi-Zero W 512MB v1.1',
		'0092' => 'Pi-Zero 512MB v1.2',
		'0093' => 'Pi-Zero 512MB v1.3'
	); 

	//$revnum = sysCmd('awk ' . "'" . '{if ($1=="Revision") print substr($3,length($3)-3)}' . "'" . ' /proc/cpuinfo');
	// support arm64
	$uname=posix_uname();
	if ($uname['machine'] === 'aarch64') {
		$revnum = sysCmd('vcgencmd otp_dump | awk -F: ' . "'" . '/^30:/{print substr($2,5)}' . "'");
	}
	else {
		$uname=posix_uname();
		if ($uname['machine'] === 'aarch64') {
			$revnum = sysCmd('vcgencmd otp_dump | awk -F: ' . "'" . '/^30:/{print substr($2,5)}' . "'");
		}
		else {
			$revnum = sysCmd('awk ' . "'" . '{if ($1=="Revision") print substr($3,length($3)-3)}' . "'" . ' /proc/cpuinfo');
		}
	}

	return array_key_exists($revnum[0], $revname) ? $revname[$revnum[0]] : 'Unknown Pi-model';
}

/*
old style revision codes
0002	B		1.0	256 MB	Egoman
0003	B		1.0	256 MB	Egoman
0004	B		2.0	256 MB	Sony UK
0005	B		2.0	256 MB	Qisda
0006	B		2.0	256 MB	Egoman
0007	A		2.0	256 MB	Egoman
0008	A		2.0	256 MB	Sony UK
0009	A		2.0	256 MB	Qisda
000d	B		2.0	512 MB	Egoman
000e	B		2.0	512 MB	Sony UK
000f	B		2.0	512 MB	Egoman
0010	B+		1.0	512 MB	Sony UK
0011	CM1		1.0	512 MB	Sony UK
0012	A+		1.1	256 MB	Sony UK
0013	B+		1.2	512 MB	Embest
0014	CM1		1.0	512 MB	Embest
0015	A+		1.1	256 MB / 512 MB	Embest 
new style revision codes
90 0021	A+		1.1	512 MB	Sony UK
90 0032	B+		1.2	512 MB	Sony UK
90 0092	Zero	1.2	512 MB	Sony UK
90 0093	Zero	1.3	512 MB	Sony UK
90 00c1	Zero W	1.1	512 MB	Sony UK
92 0093	Zero	1.3	512 MB	Embest
a0 1040	2B		1.0	1 GB	Sony UK
a0 1041	2B		1.1	1 GB	Sony UK
a0 2082	3B		1.2	1 GB	Sony UK
a0 20d3	3B+		1.3	1 GB	Sony UK
a0 20a0	CM3		1.0	1 GB	Sony UK
a2 1041	2B		1.1	1 GB	Embest
a2 2042	2B		1.2	1 GB	Embest (with BCM2837)
a2 2082	3B		1.2	1 GB	Embest
a3 2082	3B		1.2	1 GB	Sony Japan
a5 2082	3B		1.2	1 GB	Stadium
a0 20d3	3B+		1.3	1 GB	Sony UK
90 20e0	3A+		1.0	512 MB	Sony UK
*/

// config audio scrobbler
function cfgAudioScrobbler($cfg) {
	$file = '/usr/local/etc/mpdasrc';
	$fp = fopen($file, 'w');

	$data  = "#########################################\n";
	$data .= "# This file is automatically generated by\n";
	$data .= "# the player System configuration page. \n";
	$data .= "#########################################\n\n";

	$data .= "username = " . $_SESSION['mpdasuser'] . "\n";
	$data .= "password = " . (empty($_SESSION['mpdaspwd']) ? '' : $_SESSION['mpdaspwd']) . "\n";	
	$data .= "runas = mpd\n";
	$data .= "debug = 0\n";

	fwrite($fp, $data);
	fclose($fp);
}

// auto-configure settings at worker startup
function autoConfig($cfgfile) {
	$contents = file_get_contents($cfgfile);

	$autocfg = array();
	$line = strtok($contents, "\n");

	while ($line) {
		$firstchr = substr($line, 0, 1);

		if (!($firstchr == '#' || $firstchr == '[')) {
			list ($element, $value) = explode("=", $line, 2);
			$autocfg[$element] = $value;
		}

		$line = strtok("\n");
	}

	// [names]

	// host name
	sysCmd('/var/www/command/util.sh chg-name host "moode" ' . '"' . $autocfg['hostname'] . '"');
	playerSession('write', 'hostname', $autocfg['hostname']);
	workerLog('worker: hostname (' . $autocfg['hostname'] . ')');

	// browser title
	sysCmd('/var/www/command/util.sh chg-name browsertitle "moOde Player" ' . '"' . $autocfg['browsertitle'] . '"');
	playerSession('write', 'browsertitle', $autocfg['browsertitle']);
	workerLog('worker: browsertitle (' . $autocfg['browsertitle'] . ')');

	// bluetooth name
	sysCmd('/var/www/command/util.sh chg-name bluetooth "Moode Bluetooth" ' . '"' . $autocfg['bluetoothname'] . '"');
	playerSession('write', 'btname', $autocfg['bluetoothname']);
	workerLog('worker: btname (' . $autocfg['bluetoothname'] . ')');

	// airplay name
	playerSession('write', 'airplayname', $autocfg['airplayname']);
	workerLog('worker: airplayname (' . $autocfg['airplayname'] . ')');

	// spotify name
	playerSession('write', 'spotifyname', $autocfg['spotifyname']);
	workerLog('worker: spotifyname (' . $autocfg['spotifyname'] . ')');

	// squeezelite name
	$dbh = cfgdb_connect();
	$result = sdbquery('update cfg_sl set value=' . "'" . $autocfg['squeezelitename'] . "'" . ' where param=' . "'PLAYERNAME'", $dbh);
	sysCmd('/var/www/command/util.sh chg-name squeezelite "Moode" ' . '"' . $autocfg['squeezelitename'] . '"');

	// upnp name
	sysCmd('/var/www/command/util.sh chg-name upnp "Moode UPNP" ' . '"' . $autocfg['upnpname'] . '"');
	playerSession('write', 'upnpname', $autocfg['upnpname']);
	workerLog('worker: upnpname (' . $autocfg['upnpname'] . ')');

	// dlna name
	sysCmd('/var/www/command/util.sh chg-name dlna "Moode DLNA" ' . '"' . $autocfg['dlnaname'] . '"');
	playerSession('write', 'dlnaname', $autocfg['dlnaname']);
	workerLog('worker: dlnaname (' . $autocfg['dlnaname'] . ')');

	// mpd zeroconf name
	sysCmd('/var/www/command/util.sh chg-name mpdzeroconf ' . "'" . '"Moode MPD"' . "'" . ' ' . "'" . '"' . $autocfg['mpdzeroconf'] . '"' . "'");
	cfgdb_update('cfg_mpd', cfgdb_connect(), 'zeroconf_name', $autocfg['mpdzeroconf']);
	workerLog('worker: mpdzeroconf (' . $autocfg['mpdzeroconf'] . ')');

	// [network]

	// wlan ssid, security, password, country
	$netcfg = sdbquery('select * from cfg_network', $dbh);
	$value = array('method' => $netcfg[1]['method'], 'ipaddr' => $netcfg[1]['ipaddr'], 'netmask' => $netcfg[1]['netmask'], 'gateway' => $netcfg[1]['gateway'], 'pridns' => $netcfg[1]['pridns'], 'secdns' => $netcfg[1]['secdns'], 'wlanssid' => $autocfg['wlanssid'], 'wlansec' => $autocfg['wlansec'], 'wlanpwd' => $autocfg['wlanpwd']);
	cfgdb_update('cfg_network', $dbh, 'wlan0', $value);
	playerSession('write', 'wificountry', $autocfg['wlancountry']);

	cfgNetIfaces();

	workerLog('worker: wlanssid (' . $autocfg['wlanssid'] . ')');
	workerLog('worker: wlansec (' . $autocfg['wlansec'] . ')');
	workerLog('worker: wlanpwd (' . $autocfg['wlanpwd'] . ')');
	workerLog('worker: wlancountry (' . $autocfg['wlancountry'] . ')');

	// apd ssid, channel and passwpord
	playerSession('write', 'apdssid', $autocfg['apdssid']);
	playerSession('write', 'apdchan', $autocfg['apdchan']);
	playerSession('write', 'apdpwd', $autocfg['apdpwd']);

	cfgHostApd();

	workerLog('worker: apdssid (' . $autocfg['apdssid'] . ')');
	workerLog('worker: apdchan (' . $autocfg['apdchan'] . ')');
	workerLog('worker: apdpwd (' . $autocfg['apdpwd'] . ')');

	// [services]

	// airplay receiver
	playerSession('write', 'airplaysvc', $autocfg['airplaysvc']);
	workerLog('worker: airplayrcvr (' . $autocfg['airplaysvc'] . ')');

	// upnp renderer
	playerSession('write', 'upnpsvc', $autocfg['upnpsvc']);
	workerLog('worker: upnprenderer (' . $autocfg['upnpsvc'] . ')');

	// dlna server
	playerSession('write', 'dlnasvc', $autocfg['dlnasvc']);
	workerLog('worker: dlnaserver (' . $autocfg['dlnasvc'] . ')');

	// [other]

	// timezone
	sysCmd('/var/www/command/util.sh set-timezone ' . $autocfg['timezone']);
	playerSession('write', 'timezone', $autocfg['timezone']);
	workerLog('worker: timezone (' . $autocfg['timezone'] . ')');

	// theme name, r45e
	playerSession('write', 'themename', $autocfg['themename']);
	workerLog('worker: theme name (' . $autocfg['themename'] . ')');

	// accent color, r45e
	playerSession('write', 'accent_color', $autocfg['accentcolor']);
	workerLog('worker: accent color (' . $autocfg['accentcolor'] . ')');

	// remove config file
	sysCmd('rm ' . $cfgfile);
	workerLog('worker: cfgfile removed');
}

// check for available software update
function checkForUpd($path) {
	// $path
	// - http: //moodeaudio.org/downloads/
	// - /var/local/www/

	// check for update package ex: update-r26.txt
	if (false === ($tmp = file_get_contents($path . 'update-' . getPkgId() . '.txt'))) {
		$result['pkgdate'] = 'None'; 
	}
	else {
		$result = parseDelimFile($tmp, '=');
	}

	return $result;
}

// get package id (either -test or '')
function getPkgId () {
	$result = sdbquery("select value from cfg_system where param='pkgid'", cfgdb_connect());
	return getMoodeRel() . $result[0]['value'];
}

// get moode release version and date
function getMoodeRel($options) {
	if ($options === 'verbose') {
		// major.minor yyyy-mm-dd ex: 2.6 2016-06-07
		$result = sysCmd("awk '/Release: /{print $2 " . '" "' . " $3;}' /var/www/footer.php | sed 's/,//'");
		return $result[0];
	}
	else {
		// rXY ex: r26c
		$result = sysCmd("awk '/Release: /{print $2;}' /var/www/footer.php | sed 's/,//'");
		$str = 'r' . str_replace('.', '', $result[0]);
		return $str;
	}
}

// ensure valid mpd output config
function configMpdOutputs() {
	if ($_SESSION['crossfeed'] != 'Off') {
		$output = '2';
	}
	elseif ($_SESSION['eqfa4p'] != 'Off') {
		$output = '3';
	}
	elseif ($_SESSION['alsaequal'] != 'Off') {
		$output = '4';
	}
	elseif ($_SESSION['invert_polarity'] == '1') {
		$output = '5';
	}
	elseif ($_SESSION['audioout'] == 'Bluetooth') {
		$output = '6';
	}
	else {
		$output = '1'; // ALSA default
	}

	return $output;
}

// parse result of mpd outputs cmd
function parseMpdOutputs($resp) {
	$array = array();
	$line = strtok($resp, "\n");

	while ($line) {
		list ($element, $value) = explode(": ", $line, 2);

		if ($element == 'outputid') {
			$id = $value;				
			$array[$id] = 'MPD output ' . ($id + 1) . ' '; 
		}

		if ($element == 'outputname') {
			$array[$id] .= $value;

		}

		if ($element == 'outputenabled') {
			$array[$id] .= $value == '0' ? ' (off)' : ' (on)';
		}

		$line = strtok("\n");
	}

	return $array;
}

// config squeezelite
function cfgSqueezelite() {
	// update sql table with current MPD device num
	$dbh = cfgdb_connect();
	$array = sdbquery('select value from cfg_mpd where param="device"', $dbh);
	cfgdb_update('cfg_sl', $dbh, 'AUDIODEVICE', $array[0]['value']);

	// load settings
	$result = cfgdb_read('cfg_sl', $dbh);
	
	// generate config file output
	foreach ($result as $row) {
		if ($row['param'] == 'AUDIODEVICE') {
			$data .= $row['param'] . '="hw:' . $row['value'] . ',0"' . "\n";
		}
		else {
			$data .= $row['param'] . '=' . $row['value'] . "\n";
		}
	}
	
	// write config file
	$fh = fopen('/etc/squeezelite.conf', 'w');
	fwrite($fh, $data);
	fclose($fh);
}

// start squeezelite
function startSqueezeLite () {
	sysCmd('killall -s 9 squeezelite');
	$result = sysCmd('pgrep -l squeezelite');
	$count = 10;
	while ($result[0] && $count > 0) {				
		sleep(1);
		$result = sysCmd('pgrep -l squeezelite');
		--$count;
	}			
	sysCmd('systemctl start squeezelite');
}

function cfgI2sOverlay($i2sDevice) {
	sysCmd('sed -i /dtoverlay/d ' . '/boot/config.txt'); // remove dtoverlays

	// on-board or i2s audio
	if ($i2sDevice == 'none') {
		sysCmd('sed -i "s/dtparam=audio=off/dtparam=audio=on/" ' . '/boot/config.txt');
	}
	else {
		$result = cfgdb_read('cfg_audiodev', cfgdb_connect(), $i2sDevice);	
		sysCmd('sed -i "s/dtparam=audio=on/dtparam=audio=off/" ' . '/boot/config.txt');
		sysCmd('echo dtoverlay=' . $result[0]['driver'] . ' >> ' . '/boot/config.txt');
	}

	// add these back in
	$cmd = $_SESSION['p3wifi'] == '0' ? 'echo dtoverlay=pi3-disable-wifi >> ' . '/boot/config.txt' : 'echo "#dtoverlay=pi3-disable-wifi" >> ' . '/boot/config.txt';
	sysCmd($cmd);
	$cmd = $_SESSION['p3bt'] == '0' ? 'echo dtoverlay=pi3-disable-bt >> ' . '/boot/config.txt' : 'echo "#dtoverlay=pi3-disable-bt" >> ' . '/boot/config.txt';
	sysCmd($cmd);
}

// pi3 wifi adapter enable/disable 
function ctlWifi($ctl) {
	$cmd = $ctl == '0' ? 'sed -i /pi3-disable-wifi/c\dtoverlay=pi3-disable-wifi ' . '/boot/config.txt' : 'sed -i /pi3-disable-wifi/c\#dtoverlay=pi3-disable-wifi ' . '/boot/config.txt';
	sysCmd($cmd);
}

// pi3 bt adapter enable/disable
function ctlBt($ctl) {
	if ($ctl == '0') {
		sysCmd('sed -i /pi3-disable-bt/c\dtoverlay=pi3-disable-bt ' . '/boot/config.txt');
	}
	else {
		sysCmd('sed -i /pi3-disable-bt/c\#dtoverlay=pi3-disable-bt ' . '/boot/config.txt');
	}
}

// set audio source 
function setAudioIn($input_source) {
	sysCmd('mpc stop');

	if ($input_source == 'Local' && $_SESSION['wrkready'] == '1') { // no need to configure Local during startup (wrkready = 0)
		if ($_SESSION['i2sdevice'] == 'HiFiBerry DAC+ ADC') {		
			sysCmd('killall -s 9 alsaloop');
		}
		elseif ($_SESSION['i2sdevice'] == 'Audiophonics ES9028/9038 DAC' || $_SESSION['i2sdevice'] == 'Audiophonics ES9028/9038 DAC (Pre 2019)') {
			sysCmd('amixer -c 0 sset "I2S/SPDIF Select" I2S');
		}

		sysCmd('/var/www/vol.sh -restore');
		sendEngCmd('inpactive0');

		if ($_SESSION['rsmafterinp'] == 'Yes') {
			sysCmd('mpc play');
		}
	}
	elseif ($input_source == 'Analog' || $input_source == 'S/PDIF') {
		if ($_SESSION['alsavolume'] != 'none') {
			sysCmd('/var/www/command/util.sh set-alsavol ' . '"' . $_SESSION['amixname']  . '"' . ' 100');
		}

		if ($_SESSION['i2sdevice'] == 'HiFiBerry DAC+ ADC') {		
			sysCmd('alsaloop > /dev/null 2>&1 &');
		}
		elseif ($_SESSION['i2sdevice'] == 'Audiophonics ES9028/9038 DAC' || $_SESSION['i2sdevice'] == 'Audiophonics ES9028/9038 DAC (Pre 2019)') {
			sysCmd('amixer -c 0 sset "I2S/SPDIF Select" SPDIF');
		}

		sendEngCmd('inpactive1');
	}
}

// set MPD audio output
function setAudioOut($audioout) {
	if ($audioout == 'Local') {
		reconfMpdVolume($_SESSION['mpdmixer_local']);		
		sysCmd('/var/www/vol.sh -restore');
		sysCmd('mpc stop');

		if ($_SESSION['crossfeed'] != 'Off') {
			$output = '2';
		}
		elseif ($_SESSION['eqfa4p'] != 'Off') {
			$output = '3';
		}
		elseif ($_SESSION['alsaequal'] != 'Off') {
			$output = '4';
		}
		elseif ($_SESSION['invert_polarity'] != '0') {
			$output = '5';
		}
		else {
			$output = '1';
		}

		sysCmd('mpc enable only ' . $output);
	}
	else if ($audioout == 'Bluetooth') {		
		if ($_SESSION['mpdmixer'] == 'disabled') {
			reconfMpdVolume('software');
			playerSession('write', 'mpdmixer_local', 'disabled');
		}

		playerSession('write', 'btactive', '0'); // dismiss the input source overlay
		sendEngCmd('btactive0');
		sysCmd('/var/www/vol.sh -restore');
		sysCmd('mpc stop');
		sysCmd('mpc enable only 6'); // ALSA bluetooth output
	}

	setMpdHttpd();	
	sysCmd('systemctl restart mpd');
}

// set mpd httpd on/off
function setMpdHttpd () {
	$cmd = $_SESSION['mpd_httpd'] == '1' ? 'mpc enable 7' : 'mpc disable 7';
	sysCmd($cmd);
	//$result = sysCmd($cmd);
	//workerLog('$result=(' . $result[0]);
}

// reconfigure MPD volume
function reconfMpdVolume($mixertype) {
	cfgdb_update('cfg_mpd', cfgdb_connect(), 'mixer_type', $mixertype);
	playerSession('write', 'mpdmixer', $mixertype);
	// reset hardware volume to 0dB (100) if indicated
	if (($mixertype == 'software' || $mixertype == 'disabled') && $_SESSION['alsavolume'] != 'none') {
		sysCmd('/var/www/command/util.sh set-alsavol ' . '"' . $_SESSION['amixname']  . '"' . ' 100');
	}
	// update /etc/mpd.conf
	updMpdConf($_SESSION['i2sdevice']);
}

// store back link for configs
function storeBackLink($section, $tpl) {
	$root_configs = array('lib-config', 'snd-config', 'net-config', 'sys-config');
	$referer_link = substr($_SERVER['HTTP_REFERER'], strrpos($_SERVER['HTTP_REFERER'], '/'));

	session_start();

	if ($tpl == 'nas-config.html') {
		$_SESSION['http_config_back'] = '/lib-config.php';
	}
	else if (in_array($section, $root_configs)) {
		$_SESSION['http_config_back'] = '/index.php';
	}
	else if (stripos($_SERVER['HTTP_REFERER'], $section) === false) {
		$_SESSION['http_config_back'] = $referer_link;
	}
	else {
		//workerLog('storeBackLink(): else block');
	}

	session_write_close();
	//workerLog('storeBackLink(): back=' . $_SESSION['http_config_back'] . ', $tpl=' . $tpl . ', $section=' . $section . ', $referer_link=' . $referer_link);
}

// create enhanced metadata
function enhanceMetadata($current, $sock, $caller) {
	define(LOGO_ROOT_DIR, 'images/radio-logos/');
	define(DEF_RADIO_COVER, 'images/default-cover-v6.svg');
	define(DEF_COVER, 'images/default-cover-v6.svg');

	$song = parseCurrentSong($sock);
	$current['file'] = $song['file'];
	
	// NOTE any of these might be '' null string
	$current['track'] = $song['Track'];
	$current['date'] = $song['Date'];
	$current['composer'] = $song['Composer'];
	// cover hash
	if ($caller == 'engine_mpd_php') {
		$current['cover_art_hash'] = getCoverHash($current['file']);
		//workerLog('$current: cover hash: ' . $current['cover_art_hash']);
	}
	
	if ($current['file'] == null) {
		$current['artist'] = '';
		$current['title'] = '';
		$current['album'] = '';
		$current['coverurl'] = DEF_COVER;
		debugLog('enhanceMetadata(): File is NULL');
	}
	else {
		//workerLog('enhanceMetadata(): Caller=' . $caller);
		//workerLog('enhanceMetadata(): current= ' . $current['file']);
		//workerLog('enhanceMetadata(): session= ' . $_SESSION['currentfile']);
		// only do this code block once for a given file
		if ($current['file'] != $_SESSION['currentfile']) {
			$current['encoded'] = getEncodedAt($song, 'default'); // encoded bit depth and sample rate, r44d1 rm conditional logic
			session_start();
			$_SESSION['currentfile'] = $current['file'];
			$_SESSION['currentencoded'] = $current['encoded'];
			session_write_close();
		}
		else {
			$current['encoded'] = $_SESSION['currentencoded'];
		}
		//debugLog('enhanceMetadata(): File=' . $current['file']);
		//debugLog('enhanceMetadata(): Encoded=' . $current['encoded']);

		// itunes aac or aiff file
		$ext = getFileExt($song['file']);
		if (isset($song['Name']) && ($ext == 'm4a' || $ext == 'aif' || $ext == 'aiff')) {
			$current['artist'] = isset($song['Artist']) ? $song['Artist'] : 'Unknown artist';
			$current['title'] = $song['Name']; 
			$current['album'] = isset($song['Album']) ? $song['Album'] : 'Unknown album';
			$current['coverurl'] = '/coverart.php/' . rawurlencode($song['file']);
			//debugLog('enhanceMetadata(): iTunes AAC or AIFF file');
		}
		// radio station
		elseif (isset($song['Name']) || (substr($song['file'], 0, 4) == 'http' && !isset($song['Artist']))) {
			debugLog('enhanceMetadata(): Radio station');
			$current['artist'] = 'Radio station';
			
			if (!isset($song['Title']) || trim($song['Title']) == '') {
				$current['title'] = 'Streaming source';
				//$current['title'] = $song['file']; // URL
			}
			else {
				// use custom name for certain stations if needed
				//$current['title'] = strpos($song['Title'], 'Radio Active FM') !== false ? $song['file'] : $song['Title'];
				$current['title'] = $song['Title'];
			}
			
			if (isset($_SESSION[$song['file']])) {
				// use xmitted name for SOMA FM stations
				$current['album'] = substr($_SESSION[$song['file']]['name'], 0, 4) == 'Soma' ? $song['Name'] : $_SESSION[$song['file']]['name'];
				// include original station name
				$current['station_name'] = $_SESSION[$song['file']]['name'];
				if ($_SESSION[$song['file']]['logo'] == 'local') {
					$current['coverurl'] = LOGO_ROOT_DIR . $_SESSION[$song['file']]['name'] . ".jpg"; // local logo image
				}
				else {
					$current['coverurl'] = $_SESSION[$song['file']]['logo']; // url logo image
				}

				# hardcode displayed bitrate for BBC 320K stations since MPD does not seem to pick up the rate since 0.20.10
				if (strpos($_SESSION[$song['file']]['name'], 'BBC') !== false && strpos($_SESSION[$song['file']]['name'], '320K') !== false) {
					$current['bitrate'] = '320';
				}
			}
			else {
				// not in radio station table, use xmitted name or 'unknown'
				$current['album'] = isset($song['Name']) ? $song['Name'] : 'Unknown station';
				$current['station_name'] = $current['album'];
				$current['coverurl'] = DEF_RADIO_COVER;
			}			
		}
		// song file or upnp url	
		else {
			$current['artist'] = isset($song['Artist']) ? $song['Artist'] : 'Unknown artist';
			$current['title'] = isset($song['Title']) ? $song['Title'] : pathinfo(basename($song['file']), PATHINFO_FILENAME);
			$current['album'] = isset($song['Album']) ? $song['Album'] : 'Unknown album';
			$current['disc'] = isset($song['Disc']) ? $song['Disc'] : 'Disc tag missing';
			$current['coverurl'] = substr($song['file'], 0, 4) == 'http' ? getUpnpCoverUrl() : '/coverart.php/' . rawurlencode($song['file']);
			// in case 2 url's are returned
			$current['coverurl'] = explode(',', $current['coverurl'])[0];
			debugLog('enhanceMetadata(): coverurl: (' . $current['coverurl'] . ')');

			if (substr($song['file'], 0, 4) == 'http') {
				debugLog('enhanceMetadata(): UPnP url');
			}
			else {
				debugLog('enhanceMetadata(): Song file');
			}
		}
	}
	
	return $current;
}

function getCoverHash($file, $ext) {
	set_include_path('/var/www/inc');
	$ext = getFileExt($file);

	// PCM song files only
	if (substr($file, 0, 4) != 'http' &&  $ext != 'dsf' && $ext != 'dff') {
		session_start();
		$search_pri = $_SESSION['library_covsearchpri'];
		session_write_close();

		$path = MPD_MUSICROOT . $file;
		$hash = false;
		//workerlog('getCoverHash(): path: ' . $path);
	
		// file: embedded cover
		if ($search_pri == 'Embedded cover') { // embedded first
			$hash = getHash($path);
		}

		if ($hash === false) {
			if (is_dir($path)) {
				// dir: cover image file
				if (substr($path, -1) !== '/') {$path .= '/';}
				$hash = parseDir($path);
			}
			else { 
				// file: cover image file in containing dir
				$dirpath = pathinfo($path, PATHINFO_DIRNAME) . '/';
				$hash = parseDir($dirpath);
			}
			
			if ($hash === false) {
				if ($search_pri == 'Cover image file') { // embedded last
					$hash = getHash($path);
				}
			}
	
			if ($hash === false) {
				// nothing found
				$hash = 'getCoverHash(): no cover found';
			}
		}
	}
	else {
		//$hash = 'getCoverHash(): not a PCM file';
		$hash = rand();
	}

	return $hash;
}

// modified versions of coverart.php functions 
// (C) 2015 Andreas Goetz
function rtnHash($mime, $hash) {
	//workerLog('getCoverHash(): rtnHash(): ' . $mime . ', ' . strlen($hash) . ' bytes');
	switch ($mime) {
		case "image/gif":
		case "image/jpg":
		case "image/jpeg":
		case "image/png":
		case "image/tif":
		case "image/tiff":
			return $hash;
		default :
			break;
	}

	return false;
}
function getHash($path) {
	//workerLog('getCoverHash(): getHash(): ' . $path);
	if (!file_exists($path)) {
		//workerLog('getCoverHash(): getHash(): ' . $path . ' (does not exist)');
		return false;
	}

	$hash = false;
	$ext = pathinfo($path, PATHINFO_EXTENSION);

	switch (strtolower($ext)) {
		// image file
		case 'gif':
		case 'jpg':
		case 'jpeg':
		case 'png':
		case 'tif':
		case 'tiff':
			$stat = stat($path);
			$hash = md5(file_get_contents($path, 1024) + $stat['size']);
			break;

		// embedded images			
		case 'mp3':
			require_once 'Zend/Media/Id3v2.php';
			try {
				$id3v2 = new Zend_Media_Id3v2($path, array('hash_only' => true));
			
				if (isset($id3v2->apic)) {
					$hash = rtnHash($id3v2->apic->mimeType, $id3v2->apic->imageData);
					//workerLog('getCoverHash(): Id3v2: apic->imageData: length: ' . strlen($id3->apic->imageData));
				}
			}
			catch (Zend_Media_Id3_Exception $e) {
				//workerLog('getCoverHash(): Zend media exception: ' . $e->getMessage()); 
			}
			break;

		case 'flac':
			require_once 'Zend/Media/Flac.php';
			try {
				$flac = new Zend_Media_Flac($path, $hash_only = true);

				if ($flac->hasMetadataBlock(Zend_Media_Flac::PICTURE)) {
					$picture = $flac->getPicture();
					//workerLog('getCoverHash(): flac: getData(): length: ' . strlen($picture->getData()));
					$hash = rtnHash($picture->getMimeType(), $picture->getData());
				}
			}
			catch (Zend_Media_Flac_Exception $e) {
				//workerLog('getCoverHash(): Zend media exception: ' . $e->getMessage()); 
			}
			break;

        case 'm4a':
            require_once 'Zend/Media/Iso14496.php';
            try {
                $iso14496 = new Zend_Media_Iso14496($path, array('hash_only' => true));
                $picture = $iso14496->moov->udta->meta->ilst->covr;
                $mime = ($picture->getFlags() & Zend_Media_Iso14496_Box_Data::JPEG) == Zend_Media_Iso14496_Box_Data::JPEG
                    ? 'image/jpeg'
                    : (
                        ($picture->getFlags() & Zend_Media_Iso14496_Box_Data::PNG) == Zend_Media_Iso14496_Box_Data::PNG
                        ? 'image/png'
                        : null
                    );
                if ($mime) {
                    $hash = rtnHash($mime, md5($picture->getValue()));
                }
            }
            catch (Zend_Media_Iso14496_Exception $e) {
				//workerLog('getCoverHash(): Zend media exception: ' . $e->getMessage()); 
            }
            break;
	}

	return $hash;
}
function parseDir($path) {
	//workerLog('getCoverHash(): parseDir(): ' . $path);
	// default cover files
	$covers = array(
		'Cover.jpg', 'cover.jpg', 'Cover.jpeg', 'cover.jpeg', 'Cover.png', 'cover.png', 'Cover.tif', 'cover.tif', 'Cover.tiff', 'cover.tiff',
		'Folder.jpg', 'folder.jpg', 'Folder.jpeg', 'folder.jpeg', 'Folder.png', 'folder.png', 'Folder.tif', 'folder.tif', 'Folder.tiff', 'folder.tiff'
	);
	foreach ($covers as $file) {
		$result = getHash($path . $file);
		if ($result !== false) {
			break;
		}
	}
	// all other image files
	$extensions = array('jpg', 'jpeg', 'png', 'tif', 'tiff');
	$path = str_replace('[', '\[', $path);
	$path = str_replace(']', '\]', $path);
	foreach (glob($path . '*') as $file) {
		//workerLog('getCoverHash(): parseDir(): glob' . $file);
		if (is_file($file) && in_array(strtolower(pathinfo($file, PATHINFO_EXTENSION)), $extensions)) {
			$result = getHash($file);
			if ($result !== false) {
				break;
			}
		}
	}

	return $result;
}
