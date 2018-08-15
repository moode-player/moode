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
 * 2018-04-02 TC moOde 4.1
 * - add configMpdOutputs()
 * - update theme switch/case in autoConfig()
 * - add nohook wpa_supplicant to dhcpcd.conf
 * - improve library loader
 * - add w to mountExists()
 * - allow playlist url as File1= in addToPL()
 * - only getencodedAt() once for a given file in enhanceMetadata()
 * - remove icon from ui_notify();
 * - add portfile define
 * - revert from $_SESSION['res_boot_config_txt'] to '/boot/config.txt'
 * - remove sps metadata routines
 * - remove djmount
 * - add socket routines for engine-cmd ipc
 * - add revision code a020d3 for Pi-3B+
 * - add wificountry to cfgNetIfaces(), autoConfig()
 * - add FEAT_UPNPSYNC and $FEAT_UPNPSYNC
 * 2018-07-11 TC moOde 4.2
 * - convert to per-ouput mixer_type in mpd.conf since global mixer_type deprecated
 * - add setAudioOut() for Bluetooth configuration
 * - add $FEAT_INPUTSEL
 * - change from hw: to AUDIODEV=plughw: for /etc/bluealsaaplay.conf
 * - wrk_mpdconf(): fix cardnum not being updated in 20-bluealsa-dmix.conf, bluealsa-aplay.conf
 * - use jpg instead of png for radio logos
 * - move db update from waitworker() to src-config.php
 * - add logfile option to startSps()
 * - searchDB() adv browse search
 * - add hwrev code 0000 for OrangePi
 *
 */
 
define('MPD_RESPONSE_ERR', 'ACK');
define('MPD_RESPONSE_OK',  'OK');
define('SQLDB', 'sqlite:/var/local/www/db/moode-sqlite3.db');
define('PORT_FILE', '/tmp/portfile');

error_reporting(E_ERROR);

// features availability bitmask
const FEAT_ADVKERNELS =  0b0000000000000001;	//    1
const FEAT_AIRPLAY =     0b0000000000000010;	//    2
const FEAT_MINIDLNA =    0b0000000000000100;	//    4
const FEAT_MPDAS =       0b0000000000001000;	//    8 
const FEAT_SQUEEZELITE = 0b0000000000010000;	//   16
const FEAT_UPMPDCLI =    0b0000000000100000;	//   32
const FEAT_SQSHCHK =     0b0000000001000000;	//   64
const FEAT_GMUSICAPI =   0b0000000010000000;	//  128
const FEAT_LOCALUI =     0b0000000100000000;	//  256
const FEAT_INPUTSEL =    0b0000001000000000;	//  512
const FEAT_UPNPSYNC =    0b0000010000000000;	//  1024 

// mirror for footer.php
$FEAT_AIRPLAY = 	0b0000000000000010;
$FEAT_SQUEEZELITE = 0b0000000000010000;
$FEAT_UPMPDCLI = 	0b0000000000100000;
$FEAT_INPUTSEL = 	0b0000001000000000;

// worker message logger
function workerLog($msg, $mode) {
	if (!isset($mode)) {$mode = 'a';} // default= append mode
		
	$fh = fopen('/var/log/moode.log', $mode);
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
		
	$fh = fopen('/var/log/moode.log', $mode);
	fwrite($fh, date('Ymd His ') . $msg . "\n");
	fclose($fh);
}

// core mpd functions

// AG from Moode 3 prototype
// TC retry to improve robustness
function openMpdSock($host, $port) {
	for ($i = 0; $i < 4; $i++) {  
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
	debugLog('readMpdResponse(): reading response');

	while (false !== ($str = fgets($sock, 1024)) && !feof($sock)) {
		if (strncmp(MPD_RESPONSE_OK, $str, strlen(MPD_RESPONSE_OK)) == 0) {
			$resp = $resp == '' ? $str : $resp;

			debugLog('readMpdResponse(): success $str=(' . explode("\n", $str)[0] . ')');
			debugLog('readMpdResponse(): success $resp[0]=(' . explode("\n", $resp)[0] . ')');	
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
	if (filesize('/var/local/www/libcache.json') != 0) {
		debugLog('loadLibrary(): Cache data returned to client');
		return file_get_contents('/var/local/www/libcache.json');
	}
	else {
		debugLog('loadLibrary(): Generating flat list...');
		$flat = genFlatList($sock);
				
		if ($flat != '') {
			debugLog('loadLibrary(): Flat list generated');
			debugLog('loadLibrary(): Generating library...');
			$tagcache = json_encode(genLibrary($flat));
			debugLog('loadLibrary(): Cache data returned to client');
			return $tagcache;
		}
		else {
			debugLog('loadLibrary(): Flat list empty');
			return '';
		}
	}
}

// generate flat list from mpd tag database
function genFlatList($sock) {
	sendMpdCmd($sock, 'listallinfo'); // tpc r41, fastest
	//sendMpdCmd($sock, 'find modified-since 2000-01-01T00:00:00Z'); // original, full time stamp
	$resp = readMpdResp($sock);
	
	if (!is_null($resp) && substr($resp, 0, 2) != 'OK') {
		$lines = explode("\n", $resp);
		$item = 0;
		$flat = array();
		
		for ($i = 0; $i < count($lines); $i++) {
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
		
		return $flat;
	}
	else {
		return '';		
	}
}

// generate library {Genre1: {Artist1: {Album1: [{song1}, {song2}], Album2:...}, Artist2:...}, Genre2:...}
function genLibrary($flat) {
	$lib = array();

	// Artist list sort
	$libartist = $_SESSION['libartistcol']; // r42x rm Use from the setting

	foreach ($flat as $flatData) {
		$genre = $flatData['Genre'] ? $flatData['Genre'] : 'Unknown';
		$artist = $flatData[$libartist] ? $flatData[$libartist] : ($flatData['Artist'] ? $flatData['Artist'] : 'Unknown');
		$album = $flatData['Album'] ? $flatData['Album'] : 'Unknown';
		// albumsort tag if present
		//$album = $flatData['AlbumSort'] ? $flatData['AlbumSort'] : ($flatData['Album'] ? $flatData['Album'] :'Unknown');

		if (!$lib[$genre]) {$lib[$genre] = array();}
		if (!$lib[$genre][$artist]) {$lib[$genre][$artist] = array();}
        if (!$lib[$genre][$artist][$album]) {$lib[$genre][$artist][$album] = array();}

		$songData = array(
			'file' => $flatData['file'],
			'tracknum' => ($flatData['Track'] ? $flatData['Track'] : ''),
			'title' => $flatData['Title'],
			'actual_artist' => ($flatData['Artist'] ? $flatData['Artist'] : 'Unknown'),
			'year' => $flatData['Date'],
			'time' => $flatData['Time'],
			'time_mmss' => songTime($flatData['Time'])
		);
			
		array_push($lib[$genre][$artist][$album], $songData);
	}
	
	if (file_put_contents('/var/local/www/libcache.json', json_encode($lib)) === false) {
		debugLog('genLibrary: create libcache.json failed');		
	}
	
	return $lib;
}

// add group of songs to playlist (library panel)
function addallToPL($sock, $songs) {
	$cmds = array();

	foreach ($songs as $song) {
		$path = $song['file'];
		array_push($cmds, 'add "' . html_entity_decode($path) . '"');
	}
	
	chainMpdCmds($sock, $cmds);
}

// add to playlist (browse panel)
// tpc r41 allow playlist url as File1=
function addToPL($sock, $path) {
	$ext = getFileExt($path);

	// playlist 
	if ($ext == 'm3u' || $ext == 'pls' || $ext == 'cue' || strpos($path, '/') === false) {
		// radio stations
		if (strpos($path, 'RADIO') !== false) {
			if (strpos($path, 'Soma FM') !== false) {
				updStreamLink($path); // just for Soma FM stations
			}
			else {
				// check for playlist as url
				$pls = file_get_contents('/var/lib/mpd/music/' . $path);
				$url = parseDelimFile($pls, '=')['File1'];
				$end = substr($url, -4);
				if ($end == '.pls' || $end == '.m3u') {
					$path = $url;
				}
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

// update direct stream link
function updStreamLink($path) {
	$plsFile = '/var/lib/mpd/music/' . $path;

	// current pls file
	$currentPls = file_get_contents($plsFile);
	$currentUrl = parseDelimFile($currentPls, '=')['File1'];

	// pls permalink file from streaming service provider
	$servicePls = file_get_contents($_SESSION[$currentUrl]['permalink']);

	if ($servicePls !== false) {
		$serviceUrl = parseDelimFile($servicePls, '=')['File1'];
	
		if ($currentUrl != $serviceUrl) {
			// update pls file and sql table
			$currentPls = str_replace($currentUrl, $serviceUrl, $currentPls);
			file_put_contents($plsFile, $currentPls);
			cfgdb_update('cfg_radio', cfgdb_connect(), $_SESSION[$currentUrl]['name'], $serviceUrl);
	
			// create new session var
			session_start();
			$_SESSION[$serviceUrl] = $_SESSION[$currentUrl];
			unset($_SESSION[$currentUrl]);
			session_write_close();
		}
	}
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
	} else {
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
			} else if ($element == 'directory') {
				$idx++;				
				$diridx++; // record directory index for further processing
				$file = $value;
				$array[$idx]['directory'] = $file;
			} else if ($element == 'playlist') {
				if (substr($value,0, 5) == 'RADIO' || strtolower(pathinfo($value, PATHINFO_EXTENSION)) == 'cue') {
					$idx++;
					$file = $value;
					$array[$idx]['file'] = $file;
					$array[$idx]['fileext'] = getFileExt($file);
				} else {
					$idx++;
					$file = $value;
					$array[$idx]['playlist'] = $file;
				}
			} else {
				$array[$idx][$element] = $value;
				$array[$idx]['Time2'] = songTime($array[$idx]['Time']);
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
			//workerLog('parseStatuus(): ' . $element . ' : ' . $value);			
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
		elseif (!isset($array['duration'])) {
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
		 	// for aiff, wav files and some radio stations ex: Czech Radio Classic
			if ($array['bitrate'] == '0') {
			 	$array['bitrate'] = number_format((( (float)$audio_format[0] * (float)$array['audio_sample_depth'] * (float)$audio_format[2] ) / 1000000), 3, '.', '');
			}
			else {
			 	$array['bitrate'] = strlen($array['bitrate']) < 4 ? $array['bitrate'] : substr($array['bitrate'], 0, 1) . '.' . substr($array['bitrate'], 1, 3) ;
			}

			$array['bitrate'] .= strpos($array['bitrate'], '.') === false ? ' kbps' : ' mbps';
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

// parse mpd conf settings
function parseCfgMpd($dbh) {
	// load settings
	$result = cfgdb_read('cfg_mpd', $dbh);
	$array = array();
	
	foreach ($result as $row) {
		$array[$row['param']] = $row['value_player'];
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
		$querystr = "SELECT param, value_player FROM cfg_mpd WHERE value_player!=''";
	}
	else if ($param == 'mpdconfdefault') {
		$querystr = "SELECT param, value_default FROM cfg_mpd WHERE value_default!=''";
	}
	else if ($table == 'cfg_audiodev') {
		$querystr = 'SELECT name, dacchip, chipoptions, arch, iface, kernel, driver, advdriver, advoptions FROM ' . $table . ' WHERE name="' . $param . '"';
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
			$querystr = "UPDATE " . $table . " SET value_player='" . $value . "' where param='" . $key . "'";
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
	}

	if (sdbquery($querystr,$dbh)) {
		return true;
	} else {
		return false;
	}
}

function cfgdb_write($table, $dbh, $values) {
	$querystr = "INSERT INTO " . $table . " VALUES (NULL, " . $values . ")";

	if (sdbquery($querystr,$dbh)) {
		return true;
	} else {
		return false;
	}
}

function cfgdb_delete($table, $dbh, $id) {
	if (!isset($id)) {
		$querystr = "DELETE FROM " . $table;
	} else {
		$querystr = "DELETE FROM " . $table . " WHERE id=" . $id;
	}

	if (sdbquery($querystr,$dbh)) {
		return true;
	} else {
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
		} else {
			return $result;
		}
	} else {
		return false;
	}
}

function wrk_mpdconf($i2sdevice) {
	// load settings
	$mpdcfg = sdbquery("SELECT param,value_player FROM cfg_mpd WHERE value_player!=''", cfgdb_connect());
	
	// header
	$output =  "#########################################\n";
	$output .= "# This file is automatically generated by\n";
	$output .= "# the player MPD configuration page.     \n";
	$output .= "#########################################\n\n";
	
	// parse output
	foreach ($mpdcfg as $cfg) {
		if ($cfg['param'] == 'audio_output_format' && $cfg['value_player'] == 'disabled') {
			$output .= '';
		}
		// 0.20 series mpd uses a plugin block named 'resampler' instead of this param
		else if ($cfg['param'] == 'samplerate_converter') {
			$samplerate_converter = $cfg['value_player'];
			$output .= '';
		}
		else if ($cfg['param'] == 'sox_multithreading') {
			$sox_multithreading = $cfg['value_player'];
			$output .= '';
		}
		else if ($cfg['param'] == 'dop') {
			$dop = $cfg['value_player'];
		}
		else if ($cfg['param'] == 'device') {
			playerSession('write', 'cardnum', $cfg['value_player']);
			$device = $cfg['value_player'];
		}
		else if ($cfg['param'] == 'mixer_type') {
			playerSession('write', 'mpdmixer', $cfg['value_player']);
			if ($cfg['value_player'] == 'hardware') { 
				$hwmixer = getMixerName($i2sdevice);
				$mixertype = 'hardware';
			}
			else {
				// software or disabled (disabled = 'none' in conf file)
				$mixertype = $cfg['value_player'] == 'disabled' ? 'none' : 'software';
			}
		}
		else {
			$output .= $cfg['param'] . " \"" . $cfg['value_player'] . "\"\n";
		}
	}

	// format audio input / resampler / output interfaces

	// input
	$output .= "max_connections \"20\"\n";
	$output .= "\n";
	$output .= "decoder {\n";
	$output .= "plugin \"ffmpeg\"\n";
	$output .= "enabled \"yes\"\n";
	$output .= "}\n";
	$output .= "\n";
	$output .= "input {\n";
	$output .= "plugin \"curl\"\n";
	$output .= "}\n";
	$output .= "\n";

	// resampler
	$output .= "resampler {\n";
	$output .= "plugin \"soxr\"\n";
	$output .= "quality \"" . $samplerate_converter . "\"\n";
	$output .= "threads \"" . $sox_multithreading . "\"\n";
	$output .= "}\n";
	$output .= "\n";

	// ALSA default
	$output .= "audio_output {\n";
	$output .= "type \"alsa\"\n";
	$output .= "name \"ALSA default\"\n";
	$output .= "device \"hw:" . $device . ",0\"\n";
	$output .= "mixer_type \"" . $mixertype . "\"\n";
	if ($mixertype == 'hardware') {
		$output .= "mixer_control \"" . $hwmixer . "\"\n";
		$output .= "mixer_device \"hw:" . $device . "\"\n";
		$output .= "mixer_index \"0\"\n";
	}
	$output .= "dop \"" . $dop . "\"\n";
	$output .= "}\n\n";

	// crossfeed
	$output .= "audio_output {\n";
	$output .= "type \"alsa\"\n";
	$output .= "name \"ALSA crossfeed\"\n";
	$output .= "device \"crossfeed\"\n";
	$output .= "mixer_type \"" . $mixertype . "\"\n";
	if ($mixertype == 'hardware') {
		$output .= "mixer_control \"" . $hwmixer . "\"\n";
		$output .= "mixer_device \"hw:" . $device . "\"\n";
		$output .= "mixer_index \"0\"\n";
	}
	$output .= "dop \"" . $dop . "\"\n";
	$output .= "}\n\n";

	// parametric eq
	$output .= "audio_output {\n";
	$output .= "type \"alsa\"\n";
	$output .= "name \"ALSA parametric eq\"\n";
	$output .= "device \"eqfa4p\"\n";
	$output .= "mixer_type \"" . $mixertype . "\"\n";
	if ($mixertype == 'hardware') {
		$output .= "mixer_control \"" . $hwmixer . "\"\n";
		$output .= "mixer_device \"hw:" . $device . "\"\n";
		$output .= "mixer_index \"0\"\n";
	}
	$output .= "dop \"" . $dop . "\"\n";
	$output .= "}\n\n";

	// graphic eq
	$output .= "audio_output {\n";
	$output .= "type \"alsa\"\n";
	$output .= "name \"ALSA graphic eq\"\n";
	$output .= "device \"alsaequal\"\n";
	$output .= "mixer_type \"" . $mixertype . "\"\n";
	if ($mixertype == 'hardware') {
		$output .= "mixer_control \"" . $hwmixer . "\"\n";
		$output .= "mixer_device \"hw:" . $device . "\"\n";
		$output .= "mixer_index \"0\"\n";
	}
	$output .= "dop \"" . $dop . "\"\n";
	$output .= "}\n\n";

	// bluetooth stream
	$output .= "audio_output {\n";
	$output .= "type \"alsa\"\n";
	$output .= "name \"ALSA bluetooth\"\n";
	$output .= "device \"btstream\"\n";
	$output .= "mixer_type \"software\"\n";
	$output .= "}\n";

	$fh = fopen('/etc/mpd.conf', 'w');
	fwrite($fh, $output);
	fclose($fh);

	// update confs with device num (cardnum)
	sysCmd("sed -i '/slave.pcm \"plughw/c\ \tslave.pcm \"plughw:" . $device . ",0\";' /usr/share/alsa/alsa.conf.d/crossfeed.conf");
	sysCmd("sed -i '/slave.pcm \"plughw/c\ \tslave.pcm \"plughw:" . $device . ",0\";' /usr/share/alsa/alsa.conf.d/eqfa4p.conf");
	sysCmd("sed -i '/slave.pcm \"plughw/c\ \tslave.pcm \"plughw:" . $device . ",0\";' /usr/share/alsa/alsa.conf.d/alsaequal.conf");
	sysCmd("sed -i '/card/c\ \t    card " . $device . "' /usr/share/alsa/alsa.conf.d/20-bluealsa-dmix.conf");
	sysCmd("sed -i '/AUDIODEV/c\AUDIODEV=plughw:" . $device . ",0' /etc/bluealsaaplay.conf");
}

// return amixer name
function getMixerName($i2sdevice) {
	if ($i2sdevice == 'none') {
		// default is PCM for USB and On-board
		// if usb device has no mixer assign default name
		$result = sysCmd('/var/www/command/util.sh get-mixername');
		$mixername = $result[0] == '' ? 'PCM' : $result[0];
	}
	//elseif ($i2sdevice == 'HiFiBerry Amp(Amp+)') {
	elseif ($i2sdevice == 'HiFiBerry Amp(Amp+)' || ($i2sdevice == 'Allo Piano 2.1 Hi-Fi DAC' && $_SESSION['piano_dualmode'] != 'None')) {
		$mixername = 'Master';
	}
	else {
		 // I2S default
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

function wrk_sourcecfg($queueargs) {
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
			
			if (wrk_sourcemount('mount', $newmountID)) {
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
			
			if (wrk_sourcemount('mount', $queueargs['mount']['id'])) {
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

function wrk_sourcemount($action, $id) {
	switch ($action) {
		case 'mount':
			$dbh = cfgdb_connect();
			$mp = cfgdb_read('cfg_source', $dbh, '', $id);

			sysCmd("mkdir \"/mnt/NAS/" . $mp[0]['name'] . "\"");

			if ($mp[0]['type'] == 'cifs') {
				// smb/cifs mount
				$mountstr = "mount -t cifs \"//" . $mp[0]['address'] . "/" . $mp[0]['remotedir'] . "\" -o username=" . $mp[0]['username'] . ",password='" . $mp[0]['password'] . "',rsize=" . $mp[0]['rsize'] . ",wsize=" . $mp[0]['wsize'] . ",iocharset=" . $mp[0]['charset'] . "," . $mp[0]['options'] . " \"/mnt/NAS/" . $mp[0]['name'] . "\"";
			}
			else {
				// nfs mount
				$mountstr = "mount -t nfs -o " . $mp[0]['options'] . " \"" . $mp[0]['address'] . ":/" . $mp[0]['remotedir'] . "\" \"/mnt/NAS/" . $mp[0]['name'] . "\"";
			}

			$sysoutput = sysCmd($mountstr);
			debugLog('wrk_sourcemount(): mountstr=(' . $mountstr . ')');
			debugLog('wrk_sourcemount(): sysoutput=(' . implode("\n", $sysoutput) . ')');

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
				workerLog('wrk_sourcemount(): Mount error: (' . implode("\n", $sysoutput) . ')');
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
					$return = wrk_sourcemount('mount', $mp['id']);
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
			//workerLog('wrk_sourcemount(): $mounts= <' . $mounts . '>');

			foreach ($mounts as $mp) {
				if (mountExists($mp['name'])) {
					if ($mp[0]['type'] == 'cifs') {
						sysCmd('umount -l "/mnt/NAS/' . $mp[0]['name'] . '"'); // lazy unmount
					} else {
						sysCmd('umount -f "/mnt/NAS/' . $mp[0]['name'] . '"'); // force unmount (for unreachable NFS)
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
	$output .= "<script>";
	$output .= "jQuery(document).ready(function() {";
	$output .= "$.pnotify.defaults.history = false;";
	$output .= "$.pnotify({";
	$output .= "title: '" . $notify['title'] . "',";
	$output .= "text: '" . $notify['msg'] . "',";
	//$output .= "icon: 'icon-ok',";
	$output .= "icon: '',";
	if (isset($notify['duration'])) {	
		$output .= "delay: " . strval($notify['duration'] * 1000) . ",";
	} else {
		$output .= "delay: '2000',";
	}
	$output .= "opacity: 1.0});";
	$output .= "});";
	$output .= "</script>";
	echo $output;
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
	$result = sysCmd('mount | grep -ow ' . '"' . $mountname .'"'); // tpc r41, add w
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
	// outformat 'verbose' = 16 bit, 44.1 kHz, Stereo
	// outformat 'default' = 16/44.1k
	// bit depth is omitted if the format is lossy

	$encoded = '';

	// radio station
	if (isset($song['Name']) || (substr($song['file'], 0, 4) == 'http' && !isset($song['Artist']))) {
		$encoded = $outformat === 'verbose' ? 'VBR compression' : 'VBR';
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
		// 2017-11-3 TC is this still needed?
		// hack to allow file names with accented characters to work when passed to mediainfo via exec()
		$locale = 'en_GB.utf-8';
		setlocale(LC_ALL, $locale);
		putenv('LC_ALL='.$locale);

		$fullpath = '/var/lib/mpd/music/' . $song['file'];

		if ($song['file'] == '' || !file_exists($fullpath)) {
			return 'File does not exist';
		}

		// avprobe
		// codec_name=flac
		// sample_fmt=s16
		// sample_rate=44100
		// channels=2
		// bits_per_raw_sample=16
		$cmd = 'avprobe -show_streams ' . '"' . $fullpath . '"' . ' 2>&1 | egrep "codec_name|sample_fmt|sample_rate|channels|bits_per_raw_sample"';
		debugLog($cmd);
		$result = sysCmd($cmd);

		// only the first 5 rows
		for ($i = 0; $i < 5; $i++) {
			list($param, $value) = explode('=', $result[$i]);
			$avprobe[$param] = $value;
		} 
		
		if ($outformat === 'verbose') {
			$bitdepth = $avprobe['bits_per_raw_sample'] == 'N/A' ? '' : $avprobe['bits_per_raw_sample'] . ' bit, ' ;
			$encoded = $bitdepth . formatRate($avprobe['sample_rate']) . ' kHz, ' . formatChan($avprobe['channels']) . ', ' . strtoupper($avprobe['codec_name']);
			//$encoded = substr($avprobe['sample_fmt'], 1) . ' bit, ' . formatRate($avprobe['sample_rate']) . ' kHz, ' . formatChan($avprobe['channels']) . ', ' . strtoupper($avprobe['codec_name']);
		}
		else {
			// with just sample rate
			//$encoded = $result[0] . '/' . formatRate($result[1]);

			// with audio format added
			$bitdepth = $avprobe['bits_per_raw_sample'] == 'N/A' ? '' : $avprobe['bits_per_raw_sample'] . '/' ;
			$encoded = $bitdepth . formatRate($avprobe['sample_rate']) . 'k ' . strtoupper($avprobe['codec_name']);
			//$encoded = substr($avprobe['sample_fmt'], 1) . '/' . formatRate($avprobe['sample_rate']) . ' ' . strtoupper($avprobe['codec_name']);
		}
		// avprobe

		/*
		// mediainfo
		// hack to allow file names with accented characters to work when passed to mediainfo via exec()
		$locale = 'en_GB.utf-8';
		setlocale(LC_ALL, $locale);
		putenv('LC_ALL='.$locale);

		$cmd = 'mediainfo --Inform="Audio;file:///var/www/mediainfo.tpl" ' . '"' . $fullpath . '"';
		debugLog($cmd);
		$result = sysCmd($cmd);

		$bitdepth = $result[0] == '' ? '?' : $result[0];
		$samplerate = $result[1] == '' ? '?' : $result[1];
		$channels = $result[2];
		$bitrate = $result[3] < 1000000 ? substr($result[3], 0, 3) : substr($result[3], 0, 1) . '.' . substr($result[3], 1, 3);
		$brunits = strpos($bitrate, '.') === false ? ' kbps' : ' mbps';
		$format = $result[4];

		if ($outformat === 'verbose') {
			$encoded = $bitdepth . ' bit, ' . formatRate($samplerate) . ' kHz, ' . formatChan($channels) . ' (' . $format . ')';
		}
		else {
			// with just sample rate
			//$encoded = $result[0] . '/' . formatRate($result[1]);

			// with bitrate and audio format added
			$encoded = $bitdepth . '/' . formatRate($samplerate) . ' ' . $format . ' (' . $bitrate . $brunits . ') ';
		}
		// mediainfo
		*/
	}	

	return $encoded;
}

// start shairport-sync
function startSps() {
	// r42x verbose logging
	if ($_SESSION['debuglog'] == '1') {
		$logging = '-vv';
		$logfile = '/var/log/shairport-sync.log';
	}
	else {
		$logging = '';
		$logfile = '/dev/null';
	}

	// get device num and hardware mixer name
	$array = sdbquery('select value_player from cfg_mpd where param="device"', cfgdb_connect());
	$device = $array[0]['value_player'];
	$mixername = $_SESSION['amixname'];

	// format base cmd string
	//$cmd = '/usr/local/bin/shairport-sync -a "' . $_SESSION['airplayname'] . '" -S soxr -w -B /var/local/www/commandw/spspre.sh -E /var/local/www/commandw/spspost.sh ' . '-- -d hw:' . $device;
	$cmd = '/usr/local/bin/shairport-sync ' . $logging . ' -a "' . $_SESSION['airplayname'] . '" -S soxr -w -B /var/local/www/commandw/spspre.sh -E /var/local/www/commandw/spspost.sh ' . '-- -d hw:' . $device;

	// add volume config
	if ($_SESSION['airplayvol'] == 'auto') {
		//$cmd .= $_SESSION['alsavolume'] == 'none' ? ' > /dev/null 2>&1 &' : ' -c ' . '"' . $mixername  . '"' . ' > /dev/null 2>&1 &';
		$cmd .= $_SESSION['alsavolume'] == 'none' ? ' > ' . $logfile . ' 2>&1 &' : ' -c ' . '"' . $mixername  . '"' . ' > ' . $logfile . ' 2>&1 &';
	}
	else {
		//$cmd .= ' > /dev/null 2>&1 &';
		$cmd .= ' > ' . $logfile . ' 2>&1 &';
	}
	
	// start shairport-sync
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

// get upnp coverart url
function getUpnpCoverUrl() {
	$result = sysCmd('upexplorer --album-art "' . $_SESSION['upnpname'] . '"');
	return $result[0];
}

// configure chip options
function cfgChipOptions($chipoptions) {
	$array = explode(',', $chipoptions);

	sysCmd('amixer -c 0 sset "Analogue" ' . $array[0]); // Analog volume
	sysCmd('amixer -c 0 sset "Analogue Playback Boost" ' . $array[1]); // Analog volume boost
	sysCmd('amixer -c 0 sset "DSP Program" ' . '"' . $array[2] . '"'); // Digital interpolation filter
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
		$data .= "nohook wpa_supplicant"; // tpc r41
	}
	else {
		$data .= "#AP mode\n";
		$data .= "#interface wlan0\n";
		$data .= "#static ip_address=172.24.1.1/24\n";
		$data .= "#nohook wpa_supplicant"; // tpc r41
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
	sysCmd('sed -i "$ a#AP mode\ninterface wlan0\nstatic ip_address=172.24.1.1/24\nnohook wpa_supplicant" /etc/dhcpcd.conf'); // tpc r41
	sysCmd('systemctl daemon-reload');
	sysCmd('systemctl restart dhcpcd');
	sysCmd('systemctl start hostapd');
	sysCmd('systemctl start dnsmasq');
}

function resetApMode() {
	sysCmd('sed -i "/AP mode/,/$p/ d" /etc/dhcpcd.conf');
	sysCmd('sed -i "$ a#AP mode\n#interface wlan0\n#static ip_address=172.24.1.1/24\n#nohook wpa_supplicant" /etc/dhcpcd.conf'); // tpc r41
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
		'0000' => 'OrangePiPC', //r42z	
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
		'00c1' => 'Pi-Zero W 512MB v1.1',
		'0092' => 'Pi-Zero 512MB v1.2',
		'0093' => 'Pi-Zero 512MB v1.3'
	); 

    $uname=posix_uname();
	if($uname['machine'] === 'aarch64'){
	  $revnum = sysCmd('vcgencmd otp_dump | awk -F: ' . "'" .'/^30:/{print substr($2,5)}' . "'");
	}
	else{
      $uname=posix_uname();
 	  if($uname['machine'] === 'aarch64'){
 	    $revnum = sysCmd('vcgencmd otp_dump | awk -F: ' . "'" .'/^30:/{print substr($2,5)}' . "'");
 	  }
 	  else{
 	    $revnum = sysCmd('awk ' . "'" . '{if ($1=="Revision") print substr($3,length($3)-3)}' . "'" . ' /proc/cpuinfo');
 	  }
	}
	return array_key_exists($revnum[0], $revname) ? $revname[$revnum[0]] : 'Unknown Pi-model';
}

/*
900021	A+		1.1	512 MB	Sony UK
900032	B+		1.2	512 MB	Sony UK
900092	Zero	1.2	512 MB	Sony UK
900093	Zero	1.3	512 MB	Sony UK
9000c1	Zero W	1.1	512 MB	Sony UK
920093	Zero	1.3	512 MB	Embest
a01040	2B		1.0	1 GB	Sony UK
a01041	2B		1.1	1 GB	Sony UK
a02082	3B		1.2	1 GB	Sony UK
a020d3	3B+		1.3	1 GB	Sony UK
a020a0	CM3		1.0	1 GB	Sony UK
a21041	2B		1.1	1 GB	Embest
a22042	2B		1.2	1 GB	Embest (with BCM2837)
a22082	3B		1.2	1 GB	Embest
a32082	3B		1.2	1 GB	Sony Japan
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

	// theme color
	//tpc r41 use same as worker.php
	switch (strtolower($autocfg['themecolor'])) {
		case 'alizarin': // hex color: #c0392b, rgba 192,57,43,0.71
			sysCmd('/var/www/command/util.sh alizarin'); // don't specify colors, this is the default
			break;
		case 'amethyst':
			sysCmd('/var/www/command/util.sh amethyst 8e44ad "rgba(142,68,173,0.71)"');
			break;
		case 'bluejeans':
			sysCmd('/var/www/command/util.sh bluejeans 1a439c "rgba(26,67,156,0.71)"');
			break;
		case 'carrot':
			sysCmd('/var/www/command/util.sh carrot d35400 "rgba(211,84,0,0.71)"');
			break;
		case 'emerald':
			sysCmd('/var/www/command/util.sh emerald 27ae60 "rgba(39,174,96,0.71)"');
			break;
		case 'fallenleaf':
			sysCmd('/var/www/command/util.sh fallenleaf cb8c3e "rgba(203,140,62,0.71)"');
			break;
		case 'grass':
			sysCmd('/var/www/command/util.sh grass 7ead49 "rgba(126,173,73,0.71)"');
			break;
		case 'herb':
			sysCmd('/var/www/command/util.sh herb 317589 "rgba(49,117,137,0.71)"');
			break;
		case 'lavender':
			sysCmd('/var/www/command/util.sh lavender 876dc6 "rgba(135,109,198,0.71)"');
			break;
		case 'river':
			sysCmd('/var/www/command/util.sh river 2980b9 "rgba(41,128,185,0.71)"');
			break;
		case 'rose':
			sysCmd('/var/www/command/util.sh rose c1649b "rgba(193,100,155,0.71)"');
			break;
		case 'silver':
			sysCmd('/var/www/command/util.sh silver 999999 "rgba(153,153,153,0.71)"');
			break;
		case 'turquoise':
			sysCmd('/var/www/command/util.sh turquoise 16a085 "rgba(22,160,133,0.71)"');
			break;
	}

	playerSession('write', 'themecolor', $autocfg['themecolor']);
	workerLog('worker: themecolor (' . $autocfg['themecolor'] . ')');

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
		// rXY ex: r26
		$result = sysCmd("awk '/Release: /{print $2;}' /var/www/footer.php | sed 's/,//'");
		$str = 'r' . str_replace('.', '', $result[0]);
		return $str;
	}
}

// ensure valid mpd output config, tpc r41
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
	elseif ($_SESSION['audioout'] == 'Bluetooth') {
		$output = '5';
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
	$array = sdbquery('select value_player from cfg_mpd where param="device"', $dbh);
	cfgdb_update('cfg_sl', $dbh, 'AUDIODEVICE', $array[0]['value_player']);

	// load settings
	$result = cfgdb_read('cfg_sl', $dbh);
	
	// generate config file output
	foreach ($result as $row) {
		if ($row['param'] == 'AUDIODEVICE') {
			$output .= $row['param'] . '="hw:' . $row['value'] . ',0"' . "\n";
		}
		else {
			$output .= $row['param'] . '=' . $row['value'] . "\n";
		}
	}
	
	// write config file
	$fh = fopen('/etc/squeezelite.conf', 'w');
	fwrite($fh, $output);
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
	// get device config
	$result = cfgdb_read('cfg_audiodev', cfgdb_connect(), $i2sDevice);

	 // remove all existing dtoverlay= lines			
	sysCmd('sed -i /dtoverlay/d ' . '/boot/config.txt');
		
	// reset to 'none' for certain devices that are only supported in Advanced kernel
	if (strpos($result[0]['kernel'], 'Advanced') !== false && $_SESSION['kernel'] == 'Standard') {
		$i2sDevice = 'none';
	}

	// configure new overlay or deactivate
	if ($i2sDevice == 'none') {
		sysCmd('sed -i "s/dtparam=audio=off/dtparam=audio=on/" ' . '/boot/config.txt');
	}
	else {
		sysCmd('sed -i "s/dtparam=audio=on/dtparam=audio=off/" ' . '/boot/config.txt');
		// advanced kernels
		if (strpos($_SESSION['kernel'], 'Advanced') !== false) {
			// certain devices can get an extra overlay
			if ($i2sDevice == 'Buffalo I' || $i2sDevice == 'Buffalo II/IIIse' || $i2sDevice == 'DDDAC1794 NOS') {
				if (strpos($result[0]['advdriver'], $result[0]['advoptions']) !== false) {
					$advdriver = explode(',', $result[0]['advdriver']);
					sysCmd('echo dtoverlay=' . $advdriver[0] . ',' . $advdriver[1] . ' >> ' . '/boot/config.txt');
					sysCmd('echo dtoverlay=' . $result[0]['advoptions'] . ' >> ' . '/boot/config.txt');
				}
				else {
					sysCmd('echo dtoverlay=' . $result[0]['advdriver'] . ' >> ' . '/boot/config.txt');
				}
			}
			// single overlay only
			else {
				sysCmd('echo dtoverlay=' . $result[0]['advdriver'] . ' >> ' . '/boot/config.txt');
			}
		}
		// standard kernel
		else {
			if ($result[0]['advoptions'] == 'slave' || $result[0]['advoptions'] == 'glb_mclk') {
				sysCmd('echo dtoverlay=' . $result[0]['advdriver'] . ' >> ' . '/boot/config.txt');
			}
			else {
				sysCmd('echo dtoverlay=' . $result[0]['driver'] . ' >> ' . '/boot/config.txt');
			}
		}
	}

	// store for Customize and Audio info popups
	playerSession('write', 'adevname', $i2sDevice);

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

// set MPD audio output
function setAudioOut($audioout) {
	if ($audioout == 'Local') {
		// reconfigure MPD volume for Local
		reconfMpdVolume($_SESSION['mpdmixer_local']);
		sysCmd('/var/www/vol.sh restore');
		sysCmd('mpc stop');
		sysCmd('mpc enable only 1'); // MPD ALSA default output
	}
	else if ($audioout == 'Bluetooth') {
		// reconfigure MPD volume to 'software' if 'disabled'
		if ($_SESSION['mpdmixer'] == 'disabled') {
			reconfMpdVolume('software');
			playerSession('write', 'mpdmixer_local', 'disabled');
		}
		// un-dim the UI
		playerSession('write', 'btactive', '0');
		sendEngCmd('btactive0');
		sysCmd('/var/www/vol.sh restore');
		sysCmd('mpc stop');
		sysCmd('mpc enable only 5'); // MPD ALSA bluetooth output
	}

	sysCmd('systemctl restart mpd');
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
	wrk_mpdconf($_SESSION['i2sdevice']);
}

// create enhanced metadata
function enhanceMetadata($current, $sock, $flags) {
	define(LOGO_ROOT_DIR, 'images/radio-logos/');
	define(DEF_RADIO_COVER, 'images/default-cover-v6.svg');
	define(DEF_COVER, 'images/default-cover-v6.svg');

	$song = parseCurrentSong($sock);
	$current['file'] = $song['file'];
	
	// NOTE any of these might be '' null string
	$current['track'] = $song['Track'];
	$current['date'] = $song['Date'];
	$current['composer'] = $song['Composer'];
	
	if ($current['file'] == null) {
		$current['artist'] = '';
		$current['title'] = '';
		$current['album'] = '';
		$current['coverurl'] = DEF_COVER;
		debugLog('enhanceMetadata(): File is NULL');
	}
	else {
		// get encoded bit depth and sample rate
		// tpc r41 only getencodedAt() once for a given file
		//workerLog('current=(' . $current['file'] . ')');
		//workerLog('session=(' . $_SESSION['currentfile'] . ')');
		if ($current['file'] != $_SESSION['currentfile']) {
			$current['encoded'] = $flags == 'mediainfo' ? getEncodedAt($song, 'default') : '';
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
				$current['title'] = $song['file'];
			}
			else {
				// use custom name for particular stations
				//$current['title'] = strpos($song['Title'], 'Radio Active FM') !== false ? $song['file'] : $song['Title'];
				$current['title'] = $song['Title'];
			}
			
			if (isset($_SESSION[$song['file']])) {
				// use xmitted name for SOMA FM stations
				$current['album'] = substr($_SESSION[$song['file']]['name'], 0, 4) == 'Soma' ? $song['Name'] : $_SESSION[$song['file']]['name'];
				if ($_SESSION[$song['file']]['logo'] == 'local') {
					// r42g change to jpg
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
				$current['coverurl'] = DEF_RADIO_COVER;
			}
			
		// song file or upnp url	
		}
		else {
			$current['artist'] = isset($song['Artist']) ? $song['Artist'] : 'Unknown artist';
			$current['title'] = isset($song['Title']) ? $song['Title'] : pathinfo(basename($song['file']), PATHINFO_FILENAME);
			$current['album'] = isset($song['Album']) ? $song['Album'] : 'Unknown album';
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