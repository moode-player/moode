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
 * 2020-MM-DD TC moOde 6.7.1
 *
 * This includes the @chris-rudmin rewrite of the GenLibrary() function
 * to support the new Library renderer /var/www/js/scripts-library.js
 * Refer to https://github.com/moode-player/moode/pull/16 for more info.
 *
 */

define('MPD_RESPONSE_ERR', 'ACK');
define('MPD_RESPONSE_OK',  'OK');
define('MPD_MUSICROOT',  '/var/lib/mpd/music/');
define('SQLDB', 'sqlite:/var/local/www/db/moode-sqlite3.db');
define('MOODE_LOG', '/var/log/moode.log');
define('AUTOCFG_LOG', '/home/pi/autocfg.log');
define('PORT_FILE', '/tmp/portfile');
define('THMCACHE_DIR', '/var/local/www/imagesw/thmcache/');
define('LIBCACHE_JSON', '/var/local/www/libcache.json');
define('ALSA_PLUGIN_PATH', '/etc/alsa/conf.d');
define('SESSION_SAVE_PATH', '/var/local/php');
define('TMP_STATION_PREFIX', '__tmp__');
define('EXPORT_DIR', '/var/local/www/imagesw');
define('MPD_VERSIONS_CONF', '/var/local/www/mpd_versions.conf');

error_reporting(E_ERROR);

// Features availability bitmask
// NOTE: Updates must also be made to matching code blocks in playerlib.js, sysinfo.sh, moodeutl, and footer.php
// sqlite3 /var/local/www/db/moode-sqlite3.db "select value from cfg_system where param='feat_bitmask'"
// sqlite3 /var/local/www/db/moode-sqlite3.db "UPDATE cfg_system SET value='31679' WHERE param='feat_bitmask'"
const FEAT_KERNEL		= 1;		// y Kernel architecture option on System Config
const FEAT_AIRPLAY		= 2;		// y Airplay renderer
const FEAT_MINIDLNA 	= 4;		// y DLNA server
const FEAT_MPDAS		= 8; 		// y MPD audio scrobbler
const FEAT_SQUEEZELITE	= 16;		// y Squeezelite renderer
const FEAT_UPMPDCLI 	= 32;		// y UPnP client for MPD
const FEAT_SQSHCHK		= 64;		// 	 Require squashfs for software update
const FEAT_GMUSICAPI	= 128;		// y Google Play music service
const FEAT_LOCALUI		= 256;		// y Local display
const FEAT_INPSOURCE	= 512;		// y Input source select
const FEAT_UPNPSYNC 	= 1024;		//   UPnP volume sync
const FEAT_SPOTIFY		= 2048;		// y Spotify Connect renderer
const FEAT_GPIO 		= 4096;		// y GPIO button handler
const FEAT_DJMOUNT		= 8192;		// y UPnP media browser
const FEAT_BLUETOOTH	= 16384;	// y Bluetooth renderer
//						-------
//						  31679

// Mirror for footer.php
$FEAT_INPSOURCE 	= 512;

// Worker message logger
function workerLog($msg, $mode = 'a') {
	$fh = fopen(MOODE_LOG, $mode);
	fwrite($fh, date('Ymd His ') . $msg . "\n");
	fclose($fh);
}

// Auto-config message logger
function autoCfgLog($msg, $mode = 'a') {
	$fh = fopen(AUTOCFG_LOG, $mode);
	fwrite($fh, date('Ymd His ') . $msg . "\n");
	fclose($fh);
}

// Debug message logger
function debugLog($msg, $mode = 'a') {
	// logging off
	if (!isset($_SESSION['debuglog']) || $_SESSION['debuglog'] == '0') {
		return;
	}

	$fh = fopen(MOODE_LOG, $mode);
	fwrite($fh, date('Ymd His ') . $msg . "\n");
	fclose($fh);
}

// Helper functions for html generation (pcasto)
function versioned_resource($file, $type='stylesheet') {
	echo '<link href="' . $file . '?v=' . $_SESSION['moode_release'] . '" rel="' . $type .'">' . "\n";
}
function versioned_script($file, $type='') {
	echo '<script src="' . $file . '?v=' . $_SESSION['moode_release'] . '"' . ($type != '' ? ' type="' . $type . '"' . ' defer></script>' : ' defer></script>') . "\n";
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

function integrityCheck() {
	$warning = false;

	// Check database schema
	$result = sysCmd('sqlite3 /var/local/www/db/moode-sqlite3.db .schema | grep ro_columns');
	if (empty($result)) {
		$_SESSION['ic_return_code'] = '1';
		return false;
	}

	// Output static tables
	$result = sysCmd("sqlite3 /var/local/www/db/moode-sqlite3.db \"SELECT id,name,dacchip,iface,list,driver FROM cfg_audiodev WHERE drvoptions=''\" > /tmp/cfg_audiodev.sql");

	// Broom www root
	sysCmd('find /var/www -type l -delete');

	// Check hash table
	$result = cfgdb_read('cfg_hash', cfgdb_connect());
	foreach ($result as $row) {
		// Check mapped action
		if ($row['id'] < 9 && $row['action'] !== 'exit') {
			$_SESSION['ic_return_code'] = '2';
			return false;
		}

		// Check file hash
		if (md5(file_get_contents($row['param'])) !== $row['value']) {
			if ($row['action'] === 'exit') {
				$_SESSION['ic_return_code'] = '3';
				return false;
			}
			elseif ($row['action'] === 'warning') {
				workerLog('worker: Integrity check (' . $row['action'] . ': ' . basename($row['param']) . ')');
				$warning = true;
			}
			elseif ($row['action'] === 'ignore') {
				// NOP
			}
			else {
				$_SESSION['ic_return_code'] = '9';
				return false;
			}
		}
	}

	return $warning === true ? 'passed with warnings' : 'passed';
}

// socket routines for engine-cmd.php
function sendEngCmd ($cmd) {
	//workerLog('sendEngCmd(): cmd: ' . $cmd);
	//workerLog('sendEngCmd(): Reading in portfile');
	if (false === ($ports = file(PORT_FILE, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES))) {
		// this case is ok and occurs if UI has never been started
		workerLog('sendEngCmd(): File open failed, UI has never been started');
	}
	else {
		//workerLog('sendEngCmd(): Connecting to each of ' . count($ports) . ' port(s)');
		// Retry until UI connects or retry limit reached
		$retry_limit = 4;
		$retry_count = 0;
		while (count($ports) === 0) {
			++$retry_count;
			//workerLog('sendEngCmd(): Reading in portfile (retry ' . $retry_count . ')');
			$ports = file(PORT_FILE, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
			sleep (1);
			if (--$retry_limit == 0) {
				break;
			}
		}

		foreach ($ports as $port) {
			//workerLog('sendEngCmd(): Port: ' . $port);
			if (false !== ($sock = socket_create(AF_INET, SOCK_STREAM, SOL_TCP))) {
				if (false !== ($result = socket_connect($sock, '127.0.0.1', $port))) {
					//workerLog('sendEngCmd(): write cmd: ' . $cmd .' to port: ' . $port);
					sockWrite($sock, $cmd);
					socket_close($sock);
				}
				else {
					//workerLog('sendEngCmd(): Socket connect to port ' . $port . ' failed');
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

// Caching library loader
function loadLibrary($sock) {
	if (filesize(LIBCACHE_JSON) != 0) {
		debugLog('loadLibrary(): Cache data returned to client, length (' . filesize(LIBCACHE_JSON) . ')');
		return file_get_contents(LIBCACHE_JSON);
	}
	else {
		debugLog('loadLibrary(): Generating flat list...');
		$flat = genFlatList($sock);

		if ($flat != '') {
			debugLog('loadLibrary(): Flat list generated, size (' . sizeof($flat) . ')');
			debugLog('loadLibrary(): Generating library...');
			// Normal or UTF8 replace
			if ($_SESSION['library_utf8rep'] == 'No') {
				$json_lib = genLibrary($flat);
			}
			else {
				$json_lib = genLibraryUTF8Rep($flat);
			}
			debugLog('loadLibrary(): Cache data returned to client, length (' . strlen($json_lib) . ')');
			return $json_lib;
		}
		else {
			debugLog('loadLibrary(): Flat list empty');
			return '';
		}
	}
}

// Generate flat list from mpd tag database
function genFlatList($sock) {
	// Get root list
	sendMpdCmd($sock, 'lsinfo');
	$resp = readMpdResp($sock);
	$dirs = array();
	$line = strtok($resp, "\n");
	$i = 0;

	// Use directories only and exclude RADIO
	while ($line) {
		list($param, $value) = explode(': ', $line, 2);

		if ($param == 'directory' && $value != 'RADIO') {
			$dirs[$i] = $value;
			$i++;
		}

		$line = strtok("\n");
	}

	// Get metadata
	$resp = '';
	foreach ($dirs as $dir) {
		//workerLog('Directory: ' . $dir);
		sendMpdCmd($sock, 'listallinfo "' . $dir . '"');
		$resp .= readMpdResp($sock);
	}

	//workerLog('genFlatList(): is_null($resp)= ' . (is_null($resp) === true ? 'true' : 'false') . ', substr($resp, 0, 2)= ' . substr($resp, 0, 2));
	if (!is_null($resp) && substr($resp, 0, 2) != 'OK') {
		$lines = explode("\n", $resp);
		$item = 0;
		$flat = array();
		$linecount = count($lines);
		//workerLog('genFlatList(): $linecount= ' . $linecount);

		for ($i = 0; $i < $linecount; $i++) {
			list($element, $value) = explode(': ', $lines[$i], 2);

			if ($element == 'file') {
				$item = count($flat);
				$flat[$item][$element] = $value;
			}
			// Exclude directories and playlists from listallinfo
			elseif ($element == 'directory' || $element == 'playlist') {
				++$i;
			}
			else {
				$flat[$item][$element] = $value;
			}
		}
		//workerLog('genFlatList(): ' . print_r($flat, true));
		return $flat;
	}
	else {
		return '';
	}
}

// Generate library array (@chris-rudmin rewrite)
function genLibrary($flat) {
	$lib = array();

	foreach ($flat as $flatData) {
		//$ext = getFileExt($flatData['file']);

		$songData = array(
			'file' => $flatData['file'],
			'tracknum' => ($flatData['Track'] ? $flatData['Track'] : ''),
			'title' => ($flatData['Title'] ? $flatData['Title'] : 'Unknown Title'),
			'disc' => ($flatData['Disc'] ? $flatData['Disc'] : '1'),
			'artist' => ($flatData['Artist'] ? $flatData['Artist'] : 'Unknown Artist'),
			'album_artist' => $flatData['AlbumArtist'],
			'composer' => ($flatData['Composer'] ? $flatData['Composer'] : 'Composer tag missing'),
			'year' => getTrackYear($flatData),
			'time' => $flatData['Time'],
			'album' => ($flatData['Album'] ? $flatData['Album'] : 'Unknown Album'),
			'genre' => ($flatData['Genre'] ? $flatData['Genre'] : 'Unknown'),
			'time_mmss' => songTime($flatData['Time']),
			'last_modified' => $flatData['Last-Modified'],
			//'encoded_at' => ($ext == 'dsf' || $ext == 'dff' ? getEncodedAt($flatData, 'default', false) :
			//	getEncodedAt($flatData, 'default', true))
			'encoded_at' => getEncodedAt($flatData, 'default', true)
		);

		array_push($lib, $songData);
	}

	$json_lib = json_encode($lib, JSON_INVALID_UTF8_SUBSTITUTE);
	debugLog('genLibrary(): $lib, size= ' . sizeof($lib));
	debugLog('genLibrary(): $json_lib, length= ' . strlen($json_lib));
	debugLog('genLibrary(): json_last_error()= ' . json_last_error_msg());

	if (file_put_contents(LIBCACHE_JSON, $json_lib) === false) {
		debugLog('genLibrary: create libcache.json failed');
	}
	//workerLog(print_r($lib, true));
	return $json_lib;
}

function json_error_message() {
	switch (json_last_error()) {
		case JSON_ERROR_NONE:
			$error_message = 'No errors';
			break;
		case JSON_ERROR_DEPTH:
			$error_message = 'Maximum stack depth exceeded';
			break;
		case JSON_ERROR_STATE_MISMATCH:
			$error_message = 'Underflow or the modes mismatch';
			break;
		case JSON_ERROR_CTRL_CHAR:
			$error_message = 'Unexpected control character found';
			break;
		case JSON_ERROR_SYNTAX:
			$error_message = 'Syntax error, malformed JSON';
			break;
		case JSON_ERROR_UTF8:
			$error_message = 'Malformed UTF-8 characters, possibly incorrectly encoded';
			break;
		default:
			$error_message = 'Unknown error';
			break;
	}

	return $error_message;
}


/*
JSON_ERROR_NONE	No error has occurred
JSON_ERROR_DEPTH	The maximum stack depth has been exceeded
JSON_ERROR_STATE_MISMATCH	Invalid or malformed JSON
JSON_ERROR_CTRL_CHAR	Control character error, possibly incorrectly encoded
JSON_ERROR_SYNTAX	Syntax error
JSON_ERROR_UTF8	Malformed UTF-8 characters, possibly incorrectly encoded	PHP 5.3.3

JSON_ERROR_RECURSION	One or more recursive references in the value to be encoded	PHP 5.5.0
JSON_ERROR_INF_OR_NAN	One or more NAN or INF values in the value to be encoded	PHP 5.5.0
JSON_ERROR_UNSUPPORTED_TYPE	A value of a type that cannot be encoded was given	PHP 5.5.0
JSON_ERROR_INVALID_PROPERTY_NAME	A property name that cannot be encoded was given	PHP 7.0.0
JSON_ERROR_UTF16	Malformed UTF-16 characters, possibly incorrectly encoded	PHP 7.0.0
*/

// Many Chinese songs and song directories have characters that are not UTF8 causing json_encode to fail which leaves the
// libcache.json file empty. Replacing the non-UTF8 chars in the array before json_encode solves this problem (@lazybat).
function genLibraryUTF8Rep($flat) {
	$lib = array();

	foreach ($flat as $flatData) {
		//$ext = getFileExt($flatData['file']);

		$songData = array(
			'file' => utf8rep($flatData['file']),
			'tracknum' => utf8rep(($flatData['Track'] ? $flatData['Track'] : '')),
			'title' => utf8rep(($flatData['Title'] ? $flatData['Title'] : 'Unknown Title')),
			'disc' => ($flatData['Disc'] ? $flatData['Disc'] : '1'),
			'artist' => utf8rep(($flatData['Artist'] ? $flatData['Artist'] : 'Unknown Artist')),
			'album_artist' => utf8rep($flatData['AlbumArtist']),
			'composer' => utf8rep(($flatData['Composer'] ? $flatData['Composer'] : 'Composer tag missing')),
			'year' => utf8rep(getTrackYear($flatData)),
			'time' => utf8rep($flatData['Time']),
			'album' => utf8rep(($flatData['Album'] ? $flatData['Album'] : 'Unknown Album')),
			'genre' => utf8rep(($flatData['Genre'] ? $flatData['Genre'] : 'Unknown')),
			'time_mmss' => utf8rep(songTime($flatData['Time'])),
			'last_modified' => $flatData['Last-Modified'],
			//'encoded_at' => ($ext == 'dsf' || $ext == 'dff' ? utf8rep(getEncodedAt($flatData, 'default', false)) :
			//	utf8rep(getEncodedAt($flatData, 'default', true)))
			'encoded_at' => utf8rep(getEncodedAt($flatData, 'default', true))

		);

		array_push($lib, $songData);
	}

	$json_lib = json_encode($lib);
	if (file_put_contents(LIBCACHE_JSON, $json_lib) === false) {
		debugLog('genLibrary: create libcache.json failed');
	}
	return $json_lib;
}
// UTF8 replace (@lazybat)
function utf8rep($some_string) {
	// Reject overly long 2 byte sequences, as well as characters above U+10000 and replace with ? (@lazybat)
	$some_string = preg_replace('/[\x00-\x08\x10\x0B\x0C\x0E-\x19\x7F]'.
		'|[\x00-\x7F][\x80-\xBF]+'.
		'|([\xC0\xC1]|[\xF0-\xFF])[\x80-\xBF]*'.
		'|[\xC2-\xDF]((?![\x80-\xBF])|[\x80-\xBF]{2,})'.
		'|[\xE0-\xEF](([\x80-\xBF](?![\x80-\xBF]))|(?![\x80-\xBF]{2})|[\x80-\xBF]{3,})/S',
		'--', $some_string );

	// Reject overly long 3 byte sequences and UTF-16 surrogates and replace with ?
	$some_string = preg_replace('/\xE0[\x80-\x9F][\x80-\xBF]'.
		'|\xED[\xA0-\xBF][\x80-\xBF]/S','--', $some_string );

	return $some_string;
}

function getTrackYear($trackData) {
    if (array_key_exists('OriginalDate', $trackData)) {
        $trackYear = substr($trackData['OriginalDate'], 0, 4);
    }
    else if (array_key_exists('OriginalReleaseDate', $trackData)) {
        $trackYear = substr($trackData['OriginalReleaseDate'], 0, 4);
    }
    else if (array_key_exists('Date', $trackData)) {
        $trackYear = substr($trackData['Date'], 0, 4);
    }
	else {
		$trackYear = '';
	}

    return $trackYear;
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
function searchDB($sock, $querytype, $query = '') {
	//workerLog($querytype . ', ' . $query);
	switch ($querytype) {
		// list a database path
		case 'lsinfo':
			if (!empty($query)){
				sendMpdCmd($sock, 'lsinfo "' . html_entity_decode($query) . '"');
				break;
			}
			else {
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

// Format MPD status output
function parseStatus($resp) {

	// This return probably needs a redo
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

		// Elapsed time
		// Radio - time: 293:0, elapsed: 292.501, duration not present
		// Song  - time: 4:391, elapsed: 4.156, duration: 391.466
		// Podcast same as song.
		// If state is stop then time, elapsed and duration are not present
		// Time x:y where x = elapsed ss, y = duration ss
		$time = explode(':', $array['time']);

		// Stopped
		if ($array['state'] == 'stop') {
			$percent = '0';
			$array['elapsed'] = '0';
			$array['time'] = '0';
		}
		// Radio, UPnP
		elseif (!isset($array['duration']) || $array['duration'] == 0) { // @ohinckel https: //github.com/moode-player/moode/pull/13
			$percent = '0';
			$array['elapsed'] = $time[0];
			$array['time'] = $time[1];
		}
		// Song file, Podcsst
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

		// Sample rate
		// Example formats for $array['audio'], dsd64:2, dsd128:2, 44100:24:2
	 	$audio_format = explode(':', $array['audio']);
	 	$array['audio_sample_rate'] = formatRate($audio_format[0]);

		// Bit depth
		if (strpos($array['audio_sample_rate'], 'dsd') !== false) {
			$array['audio_sample_depth'] = $array['audio_sample_rate'];
		}
		else {
			// Workaround for AAC files that show "f" for bit depth, assume decoded to 24 bit
		 	$array['audio_sample_depth'] = $audio_format[1] == 'f' ? '24' : $audio_format[1];
		}

	 	// Channels
	 	if (strpos($array['audio_sample_rate'], 'dsd') !== false) {
	 		$array['audio_channels'] = formatChan($audio_format[1]);
	 	}
	 	else {
		 	$array['audio_channels'] = formatChan($audio_format[2]);
		}

		// Bit rate
		if (!isset($array['bitrate']) || trim($array['bitrate']) == '') {
			$array['bitrate'] = '0 bps';
		}
	 	else {
			if ($array['bitrate'] == '0') {
				$array['bitrate'] = '';
				// For aiff, wav files and some radio stations ex: Czech Radio Classic
			 	//$array['bitrate'] = number_format((( (float)$audio_format[0] * (float)$array['audio_sample_depth'] * (float)$audio_format[2] ) / 1000000), 3, '.', '');
			}
			else {
			 	$array['bitrate'] = strlen($array['bitrate']) < 4 ? $array['bitrate'] : substr($array['bitrate'], 0, 1) . '.' . substr($array['bitrate'], 1, 3) ;
			 	$array['bitrate'] .= strpos($array['bitrate'], '.') === false ? ' kbps' : ' Mbps';
			}
		}
	}

	return $array;
}

function formatRate ($rate) {
	$rates = array('*' => '*', '32000' => '32', '48000' => '48', '96000' => '96', '192000' => '192', '384000' => '384', '768000' => '768',
	'22050' => '22.05', '44100' => '44.1', '88200' => '88.2', '176400' => '176.4', '352800' => '352.8', '705600' => '705.6',
	'dsd64' => 'dsd64', 'dsd128' => 'dsd128', '2822400' => '2.822', '5644800' => '5.644', '11289600' => '11.288', '22579200' => '22.576');

	return $rates[$rate];
}

function formatChan($channels) {
	if ($channels == '1') {
	 	$chanStr = 'Mono';
	}
	else if ($channels == '2' || $channels == '*') {
	 	$chanStr = 'Stereo';
	}
	else if ($channels > 2) {
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

		// Mbps rate
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
	}
	else {
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

// Parse MPD playlistfind output
function parsePlaylistFind($sock, $path) {
	sendMpdCmd($sock, 'playlistfind file "' . $path . '"');
	$resp = readMpdResp($sock);

	if (is_null($resp) ) {
		return 'Error, parsePlaylistFind response is null';
	}
	else {
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

// Parse MPD listplaylist output
function parseListPlaylist($sock, $path) {
	sendMpdCmd($sock, 'listplaylist "' . $path . '"');
	$resp = readMpdResp($sock);

	if (is_null($resp) ) {
		return 'Error, parseListPlaylist response is null';
	}
	else {
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
	}
	else {
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
	}
	else {
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
	}
	else {
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

// session management
function playerSession($action, $var = '', $value = '') {
	// 0: PHP_SESSION_DISABLED	Sessions are currently disabled
	// 1: PHP_SESSION_NONE		Sessions are enabled, but no session has been started
	// 2: PHP_SESSION_ACTIVE	Sessions are enabled and a session has been started

	// open session and load from cfg_system
	if ($action == 'open') {
		$status = session_status();
		//workerLog('playerSession(open): session_status=' . ($status == 0 ? 'PHP_SESSION_DISABLED' : ($status == 1 ? 'PHP_SESSION_NONE' : 'PHP_SESSION_ACTIVE')));
		if ($status != PHP_SESSION_ACTIVE) {
			// use stored id
			$sessionid = playerSession('getsessionid');
			if (!empty($sessionid)) {
				session_id($sessionid);
				$return = session_start();
			}
			// generate and store new id
			else {
				$return = session_start();
				playerSession('storesessionid');
			}
			//workerLog('playerSession(open): session_start=' . (($return) ? 'TRUE' : 'FALSE'));
		}

		// load cfg_system into session vars
		$dbh  = cfgdb_connect();
		$params = cfgdb_read('cfg_system', $dbh);

		foreach ($params as $row) {
			$_SESSION[$row['param']] = $row['value'];
		}

		$dbh  = null;
	}

	// unlock and write session file
	if ($action == 'unlock') {
		session_write_close();
	}

	// unset and destroy session
	if ($action == 'destroy') {
		session_unset();

		if (session_destroy()) {
			$dbh  = cfgdb_connect();

			// clear session id
			if (cfgdb_update('cfg_system', $dbh, 'sessionid','')) {
				$dbh = null;
				return true;
			}
			else {
				echo "cannot reset session on SQLite datastore";
				return false;
			}
		}
	}

	// store a value in cfg_system and session var
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

	// get session id
	if ($action == 'getsessionid') {
		$dbh  = cfgdb_connect();
		$result = cfgdb_read('cfg_system', $dbh, 'sessionid');
		$dbh = null;

		return $result['0']['value'];
	}
}

// TODO: new session management functions
function phpSessionCheck($max_loops = 3, $sleep_time = 2) {
	$session_file = SESSION_SAVE_PATH . '/sess_' . $_SESSION['sessionid'];

	// NOTE: There is also a check in watchdog.sh
	for ($i = 0; $i < $max_loops; $i++) {
		$result = sysCmd('ls -l ' . $session_file . " | awk '{print $1 \",\" $3 \",\" $4;}'");

		if ($result[0] == '-rw-rw-rw-,www-data,www-data') {
			workerLog('worker: Session permissions (OK)');
			break;
		}
		else {
			workerLog('worker: Session permissions retry (' . ($i + 1) . ')');
			sysCmd('chown www-data:www-data ' . $session_file);
			sysCmd('chmod 0666 ' . $session_file);
		}

		sleep($sleep_time);
	}

	// Check for failure case on the way out
	if ($i == $max_loops) {
		$result = sysCmd('ls -l ' . $session_file . " | awk '{print $1 \",\" $3 \",\" $4;}'");

		if ($result[0] != '-rw-rw-rw-,www-data,www-data') {
			workerLog('worker: Session permissions (Failed after ' . $max_loops . ' retries)');
			workerLog('worker: Session permissions (' . $result[0] . ')');
		}
	}
}
// 0: PHP_SESSION_DISABLED	Sessions are currently disabled
// 1: PHP_SESSION_NONE		Sessions are enabled, but no session has been started
// 2: PHP_SESSION_ACTIVE	Sessions are enabled and a session has been started
function phpSession($action, $param = '', $value = '') {
	if ($action == 'open') {
		$status = session_status();
		//workerLog('phpSession(open): session_status=' . ($status == 0 ? 'PHP_SESSION_DISABLED' : ($status == 1 ? 'PHP_SESSION_NONE' : 'PHP_SESSION_ACTIVE')));
		if($status != PHP_SESSION_ACTIVE) {
			// use stored id
			$id = phpSession('get_sessionid');
			if (!empty($id)) {
				session_id($id);
				if (phpSession('start') === false) {
					workerLog('phpSession(open): session_start() using stored id failed');
				}
			}
			// generate new id and store it
			else {
				if (phpSession('start') === false) {
					workerLog('phpSession(open): session_start() using newly generated id failed');
				}
				phpSession('store_sessionid');
			}
		}
		// load session vars from cfg_system
		$rows = dbRead('cfg_system', dbConnect());
		foreach ($rows as $row) {
			$_SESSION[$row['param']] = $row['value'];
		}
	}
	elseif ($action == 'start') {
		if (session_start() === false) {
			workerLog('phpSession(start): session_start() failed');
			return false;
		}
		else {
			return true;
		}
	}
	elseif ($action == 'close') {
		if (session_write_close() === false) {
			workerLog('phpSession(close): session_write_close() failed');
			return false;
		}
		else {
			return true;
		}
	}
	elseif ($action == 'write') {
		$_SESSION[$param] = $value;
		dbUpdate('cfg_system', dbConnect(), $var, $value);
	}
	elseif ($action == 'store_sessionid') {
		phpSession('write', 'sessionid', session_id());
	}
	elseif ($action == 'get_sessionid') {
		$result = dbRead('cfg_system', dbConnect(), 'sessionid');
		return $result['0']['value'];
	}
}

// TODO: new database management
function dbConnect() {
	if ($dbh = new PDO(SQLDB)) {
		return $dbh;
	}
	else {
		workerLog('dbConnect(): Connection failed');
		return false;
	}
}

function dbRead($table, $dbh, $param = '', $id = '') {
	if(empty($param)) {
		$querystr = 'SELECT * FROM ' . $table;
	}
	else if (!empty($id)) {
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

	if ($result = dbQuery($querystr, $dbh)) {
		return $result;
	}
	else {
		workerLog('dbRead(): Query failed or empty result set');
		return false;
	}
}

function dbUpdate($table, $dbh, $key = '', $value) {
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
				"', command='" . trim($value['command']) .
				"', param='" . $value['param'] .
				"', value='" . $value['value'] .
				"' WHERE id='" . $key . "'";
			//workerLog('cfgdb_update: ' . $querystr);
			break;
	}

	if (dbQuery($querystr, $dbh)) {
		return true;
	}
	else {
		workerLog('dbUpdate(): Query failed');
		return false;
	}
}

//function cfgdb_write($table, $dbh, $values) {
function dbInsert($table, $dbh, $values) {
	$querystr = "INSERT INTO " . $table . " VALUES (NULL, " . $values . ")"; // NULL causes the Id column to be set to the next number

	if (dbQuery($querystr,$dbh)) {
		return true;
	}
	else {
		workerLog('dbInsert(): Query failed');
		return false;
	}
}

function dbDelete($table, $dbh, $id = '') {
	$querystr = empty($id) ? 'DELETE FROM ' . $table : 'DELETE FROM ' . $table . ' WHERE id=' . $id;

	if (dbQuery($querystr, $dbh)) {
		return true;
	}
	else {
		workerLog('dbDelete(): Query failed');
		return false;
	}
}

function dbQuery($querystr, $dbh) {
	$query = $dbh->prepare($querystr);

	if ($query->execute()) {
		$result = array();
		$i = 0;
		foreach ($query as $value) {
			$result[$i] = $value;
			$i++;
		}

		// destroy the PDO connection object created by dbConnect()
		// its also automatically destroyed when the script that created it ends
		$dbh = null;

		if (empty($result)) {
			return true;
		}
		else {
			return $result;
		}
	}
	else {
		workerLog('dbQuery(): Query failed (' . $querystr . ')');
		return false;
	}
}

// database management
function cfgdb_connect() {
	if ($dbh = new PDO(SQLDB)) {
		return $dbh;
	}
	else {
		echo "cannot open SQLite database";
		return false;
	}
}

function cfgdb_read($table, $dbh, $param = '', $id = '') {
	if (empty($param) && empty($id)) {
		$querystr = 'SELECT * FROM ' . $table;
	}
	else if (!empty($id)) {
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

function cfgdb_update($table, $dbh, $key = '', $value) {
	switch ($table) {
		case 'cfg_system':
			$querystr = "UPDATE " . $table . " SET value='" . $value . "' where param='" . $key . "'";
			break;

		case 'cfg_mpd':
			$querystr = "UPDATE " . $table . " SET value='" . $value . "' where param='" . $key . "'";
			break;

		case 'cfg_network':
			$querystr = "UPDATE " . $table .
				" SET method='" . $value['method'] .
				"', ipaddr='" . $value['ipaddr'] .
				"', netmask='" . $value['netmask'] .
				"', gateway='" . $value['gateway'] .
				"', pridns='" . $value['pridns'] .
				"', secdns='" . $value['secdns'] .
				"', wlanssid='" . SQLite3::escapeString($value['wlanssid']) .
				"', wlansec='" . $value['wlansec'] .
				"', wlanpwd='" . $value['wlanpwd'] .
				"', wlan_psk='" . $value['wlan_psk'] .
				"', wlan_country='" . $value['wlan_country'] .
				"', wlan_channel='" . $value['wlan_channel'] .
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
				"', command='" . trim($value['command']) .
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

function cfgdb_delete($table, $dbh, $id = '') {
	if (empty($id)) {
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
	$mpdcfg = sdbquery("SELECT param, value FROM cfg_mpd WHERE value!=''", cfgdb_connect());
	$mpdver = substr($_SESSION['mpdver'], 0, 4);

	$data .= "#########################################\n";
	$data .= "# This file is automatically generated   \n";
	$data .= "# by the MPD configuration page.         \n";
	$data .= "#########################################\n\n";

	foreach ($mpdcfg as $cfg) {
		switch ($cfg['param']) {
			// Code block or other params
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
			case 'replay_gain_handler':
				$replay_gain_handler = $cfg['value'];
				break;
			case 'buffer_before_play':
				//$data .= $mpdver == '0.20' ? $cfg['param'] . " \"" . $cfg['value'] . "\"\n" : '';
				$data .=  '';
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
			// Default param handling
			default:
				$data .= $cfg['param'] . " \"" . $cfg['value'] . "\"\n";
				if ($cfg['param'] == 'replaygain') {$replaygain = $cfg['value'];}
				break;
		}
	}

	// Input
	$data .= "max_connections \"128\"\n";
	$data .= "\n";
	$data .= "decoder {\n";
	$data .= "plugin \"ffmpeg\"\n";
	$data .= "enabled \"yes\"\n";
	$data .= "}\n\n";
	$data .= "input {\n";
	$data .= "plugin \"curl\"\n";
	$data .= "}\n\n";

	// Resampler
	$data .= "resampler {\n";
	$data .= "plugin \"soxr\"\n";
	$data .= "quality \"" . $samplerate_converter . "\"\n";
	$data .= "threads \"" . $sox_multithreading . "\"\n";
	$data .= "}\n\n";

	// ALSA local (outputs 1 - 5)
	$names = array (
		"name \"ALSA default\"\n" . "device \"hw:" . $device . ",0\"\n",
		"name \"ALSA crossfeed\"\n" . "device \"crossfeed\"\n",
		"name \"ALSA parametric eq\"\n" . "device \"eqfa4p\"\n",
		"name \"ALSA graphic eq\"\n" . "device \"alsaequal\"\n",
		"name \"ALSA polarity inversion\"\n" . "device \"invpolarity\"\n"
		);
	foreach ($names as $name) {
		$data .= "audio_output {\n";
		$data .= "type \"alsa\"\n";
		$data .= $name;
		$data .= "mixer_type \"" . $mixertype . "\"\n";
		$data .= $mixertype == 'hardware' ? "mixer_control \"" . $hwmixer . "\"\n" . "mixer_device \"hw:" . $device . "\"\n" . "mixer_index \"0\"\n" : '';
		$data .= "dop \"" . $dop . "\"\n";
		$data .= "}\n\n";
	}

	// ALSA bluetooth (output 6)
	$data .= "audio_output {\n";
	$data .= "type \"alsa\"\n";
	$data .= "name \"ALSA bluetooth\"\n";
	$data .= "device \"btstream\"\n";
	$data .= "mixer_type \"software\"\n";
	$data .= "}\n\n";

	// MPD httpd (output 7)
	$data .= "audio_output {\n";
	$data .= "type \"httpd\"\n";
	$data .= "name \"HTTP stream\"\n";
	$data .= "port \"" . $_SESSION['mpd_httpd_port'] . "\"\n";
	$data .= "encoder \"" . $_SESSION['mpd_httpd_encoder'] . "\"\n";
	$data .= $_SESSION['mpd_httpd_encoder'] == 'flac' ? "compression \"0\"\n" : "bitrate \"320\"\n";
	$data .= "tags \"yes\"\n";
	$data .= "always_on \"yes\"\n";
	$data .= "}\n\n";

	$fh = fopen('/etc/mpd.conf', 'w');
	fwrite($fh, $data);
	fclose($fh);

	// Update confs with device num (cardnum)
	sysCmd("sed -i '/slave.pcm \"plughw/c\ \tslave.pcm \"plughw:" . $device . ",0\";' " . ALSA_PLUGIN_PATH . '/crossfeed.conf');
	sysCmd("sed -i '/slave.pcm \"plughw/c\ \tslave.pcm \"plughw:" . $device . ",0\";' " . ALSA_PLUGIN_PATH . '/eqfa4p.conf');
	sysCmd("sed -i '/slave.pcm \"plughw/c\ \tslave.pcm \"plughw:" . $device . ",0\";' " . ALSA_PLUGIN_PATH . '/alsaequal.conf');
	sysCmd("sed -i '/pcm \"hw/c\ \t\tpcm \"hw:" . $device . ",0\"' " . ALSA_PLUGIN_PATH . '/invpolarity.conf');
	sysCmd("sed -i '/card/c\ \t    card " . $device . "' " . ALSA_PLUGIN_PATH . '/20-bluealsa-dmix.conf');
	sysCmd("sed -i '/AUDIODEV/c\AUDIODEV=plughw:" . $device . ",0' /etc/bluealsaaplay.conf");

	// Store device name for Audio info popup
	$adevname = $_SESSION['i2sdevice'] == 'none' ? getDeviceNames()[$device] : $_SESSION['i2sdevice'];
	playerSession('write', 'adevname', $adevname);
}

// Return mixer name
function getMixerName($i2sdevice) {
	// USB and On-board: default is PCM otherwise use returned mixer name
	// Pi HDMI-1, HDMI-2 or Headphone jack, or a USB device
	if ($i2sdevice == 'none') {
		$result = sysCmd('/var/www/command/util.sh get-mixername');
		$mixername = $result[0] == '' ? 'PCM' : str_replace(array('(', ')'), '', $result[0]);
	}
	// I2S exceptions
	elseif ($i2sdevice == 'HiFiBerry Amp(Amp+)') {
		$mixername = 'Channels';
	}
	elseif ($i2sdevice == 'HiFiBerry DAC+ DSP') {
		$mixername = 'DSPVolume';
	}
	elseif ($i2sdevice == 'Allo Katana DAC' || ($i2sdevice == 'Allo Piano 2.1 Hi-Fi DAC' && $_SESSION['piano_dualmode'] != 'None')) {
		$mixername = 'Master';
	}
	// I2S default
	else {
		$mixername = 'Digital';
	}

	return $mixername;
}

// Get device names assigned to each ALSA card
function getDeviceNames () {
	// Pi HDMI-1, HDMI-2 or Headphone jack, or a USB audio device
	if ($_SESSION['i2sdevice'] == 'none') {
		$pi_device_names = array('b1' => 'Pi HDMI 1', 'b2' => 'Pi HDMI 2', 'Headphones' => 'Pi Headphone jack');
		for ($i = 0; $i < 4; $i++) {
			$alsa_id = trim(file_get_contents('/proc/asound/card' . $i . '/id'));
			$aplay_device_name = trim(sysCmd("aplay -l | awk -F'[' '/card " . $i . "/{print $2}' | cut -d']' -f1")[0]);
			$devices[$i] = $pi_device_names[$alsa_id] != '' ? $pi_device_names[$alsa_id] : $aplay_device_name;
			//workerLog('card' . $i . ' (' . $devices[$i] . ')');
		}
	}
	// I2S audio device
	else {
		$devices[0] = trim(sysCmd("aplay -l | awk -F'[' '/card 0/{print $2}' | cut -d']' -f1")[0]);
	}

	return $devices;
}

// Music source config
function sourceCfg($queueargs) {
	$action = $queueargs['mount']['action'];
	unset($queueargs['mount']['action']);

	switch ($action) {
		case 'add':
			$dbh = cfgdb_connect();
			unset($queueargs['mount']['id']);

			foreach ($queueargs['mount'] as $key => $value) {
				$values .= "'" . SQLite3::escapeString($value) . "',";
			}
			// error column
			$values .= "''";

			cfgdb_write('cfg_source', $dbh, $values);
			$newmountID = $dbh->lastInsertId();

			$return = (sourceMount('mount', $newmountID)) ? true : false;
			break;

		case 'edit':
			$dbh = cfgdb_connect();
			$mp = cfgdb_read('cfg_source', $dbh, '', $queueargs['mount']['id']);
			// save the edits here in case the mount fails
			cfgdb_update('cfg_source', $dbh, '', $queueargs['mount']);

			// cifs and nfs
			if ($mp[0]['type'] != 'upnp') {
				if ($mp[0]['type'] == 'cifs') {
					sysCmd('umount -l "/mnt/NAS/' . $mp[0]['name'] . '"'); // lazy umount
				}
				else {
					sysCmd('umount -f "/mnt/NAS/' . $mp[0]['name'] . '"'); // force unmount (for unreachable NFS)
				}
				// empty check to ensure /mnt/NAS is never deleted
				if (!empty($mp[0]['name']) && $mp[0]['name'] != $queueargs['mount']['name']) {
					sysCmd('rmdir "/mnt/NAS/' . $mp[0]['name'] . '"');
					sysCmd('mkdir "/mnt/NAS/' . $queueargs['mount']['name'] . '"');
				}
			}
			// upnp
			else {
				sysCmd('rm "/var/lib/mpd/music/' . $mp[0]['name'] . '"');
			}

			$return = (sourceMount('mount', $queueargs['mount']['id'])) ? true : false;
			break;

		case 'delete':
			$dbh = cfgdb_connect();
			$mp = cfgdb_read('cfg_source', $dbh, '', $queueargs['mount']['id']);

			// cifs and nfs
			if ($mp[0]['type'] != 'upnp') {
				if ($mp[0]['type'] == 'cifs') {
					sysCmd('umount -l "/mnt/NAS/' . $mp[0]['name'] . '"'); // lazy umount
				}
				else {
					sysCmd('umount -f "/mnt/NAS/' . $mp[0]['name'] . '"'); // force unmount (for unreachable NFS)
				}
				// empty check to ensure /mnt/NAS is never deleted
				if (!empty($mp[0]['name'])) {
					sysCmd('rmdir "/mnt/NAS/' . $mp[0]['name'] . '"');
				}
			}
			// upnp
			else {
				sysCmd('rm "/var/lib/mpd/music/' . $mp[0]['name'] . '"');
			}

			$return = (cfgdb_delete('cfg_source', $dbh, $queueargs['mount']['id'])) ? true : false;
			break;
	}

 	// returns true/false
	return $return;
}

// Music source mount
function sourceMount($action, $id = '') {
	switch ($action) {
		case 'mount':
			$dbh = cfgdb_connect();
			$mp = cfgdb_read('cfg_source', $dbh, '', $id);

			// cifs and nfs
			if ($mp[0]['type'] != 'upnp') {
				if ($mp[0]['type'] == 'cifs') {
					$mountstr = "mount -t cifs \"//" . $mp[0]['address'] . "/" . $mp[0]['remotedir'] . "\" -o username=\"" . $mp[0]['username'] . "\",password=\"" . $mp[0]['password'] . "\",rsize=" . $mp[0]['rsize'] . ",wsize=" . $mp[0]['wsize'] . ",iocharset=" . $mp[0]['charset'] . "," . $mp[0]['options'] . " \"/mnt/NAS/" . $mp[0]['name'] . "\"";
				}
				else {
					$mountstr = "mount -t nfs -o " . $mp[0]['options'] . " \"" . $mp[0]['address'] . ":/" . $mp[0]['remotedir'] . "\" \"/mnt/NAS/" . $mp[0]['name'] . "\"";
				}

				sysCmd('mkdir "/mnt/NAS/' . $mp[0]['name'] . '"');
				$result = sysCmd($mountstr);

				if (empty($result)) {
					if (!empty($mp[0]['error'])) {
						$mp[0]['error'] = '';
						cfgdb_update('cfg_source', $dbh, '', $mp[0]);
					}

					$return = true;
				}
				else {
					// empty check to ensure /mnt/NAS is never deleted
					if (!empty($mp[0]['name'])) {
						sysCmd('rmdir "/mnt/NAS/' . $mp[0]['name'] . '"');
					}
					$mp[0]['error'] = 'Mount error';
					workerLog('sourceMount(): Mount error: (' . implode("\n", $result) . ')');
					cfgdb_update('cfg_source', $dbh, '', $mp[0]);

					$return = false;
				}
			}
			// upnp
			else {
				$mountstr = 'ln -s "/mnt/UPNP/' . $mp[0]['address'] . "/" . $mp[0]['remotedir'] . '" "/var/lib/mpd/music/' . $mp[0]['name'] . '"';
				$result = sysCmd($mountstr);
				$return = empty($result) ? true : false;
			}

			debugLog('sourceMount(): result=(' . implode("\n", $result) . ')');
			break;

		case 'mountall':
			$dbh = cfgdb_connect();

			// cfgdb_read returns: result set || true = results empty || false = query failed
			$mounts = cfgdb_read('cfg_source', $dbh);

			foreach ($mounts as $mp) {
				if (!mountExists($mp['name'])) {
					$return = sourceMount('mount', $mp['id']);
				}
			}

			// logged during worker startup
			$return = $mounts === true ? 'none configured' : ($mounts === false ? 'mountall failed' : 'mountall initiated');
			break;

		case 'unmountall':
			$dbh = cfgdb_connect();
			$mounts = cfgdb_read('cfg_source', $dbh);

			foreach ($mounts as $mp) {
				// cifs and nfs
				if ($mp[0]['type'] != 'upnp') {
					if (mountExists($mp['name'])) {
						if ($mp['type'] == 'cifs') {
							sysCmd('umount -f "/mnt/NAS/' . $mp['name'] . '"'); // change from -l (lazy) to force unmount
						}
						else {
							sysCmd('umount -f "/mnt/NAS/' . $mp['name'] . '"'); // force unmount (for unreachable NFS)
						}
					}
				}
				// upnp
				else {
					sysCmd('rm "/var/lib/mpd/music/' . $mp['name'] . '"');
				}
			}

			// logged during worker startup
			$return = $mounts === true ? 'none configured' : ($mounts === false ? 'unmountall failed' : 'unmountall initiated');
			break;
	}

	// returns true/false for 'mount' or a log message for 'mountall' and 'unmountall'
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
	}
	else {
		$script .= "delay: '3000',";
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
function submitJob($jobName, $jobArgs = '', $title = '', $msg = '', $duration = 3) {
	if ($_SESSION['w_lock'] != 1 && $_SESSION['w_queue'] == '') {
		session_start();
		// for worker.php
		$_SESSION['w_queue'] = $jobName;
		$_SESSION['w_active'] = 1;
		$_SESSION['w_queueargs'] = $jobArgs;

		// for footer.php
		$_SESSION['notify']['title'] = $title;
		$_SESSION['notify']['msg'] = $msg;
		$_SESSION['notify']['duration'] = $duration;

		session_write_close();
		return true;
	}
	else {
		echo json_encode('worker busy');
		return false;
	}
}

// Extract bit depth, sample rate and audio format for display
function getEncodedAt($song_data, $display_format, $called_from_genlib = false) {
	// $display_formats: 'default' Ex: 16/44.1k FLAC, 'verbose' Ex: 16 bit, 44.1 kHz, Stereo FLAC
	// NOTE: Bit depth is omitted if the format is lossy

	$encoded_at = 'NULL';
	$ext = getFileExt($song_data['file']);

	// Special sectuon to handle calls from genLibrary() to populate the element "encoded_at"
	// Uses the MPD Format tag (rate:bits:channels) for PCM and mediainfo for DSD
	// Returnesd string is "bits/rate format,flag" for PCM and "DSD rate,h" for DSD
	// Flags: l (lossy), s (standard definition), h (high definition: bits >16 || rate > 44.1 || DSD)
	if ($called_from_genlib) {
		$mpd_format_tag = explode(':', $song_data['Format']);
		// Lossy: return just the format since bit depth has no meaning and bitrate is not known until playback
		if ($ext == 'mp3' || ($mpd_format_tag[1] == 'f' && $mpd_format_tag[2] <= 2)) {
			$encoded_at = strtoupper($ext) . ',l';
		}
		// DSD
		elseif ($ext == 'dsf' || $ext == 'dff') {
			$result = sysCmd('mediainfo --Inform="Audio;file:///var/www/mediainfo.tpl" ' . '"' . MPD_MUSICROOT . $song_data['file'] . '"');
			$encoded_at = $result[1] == '' ? 'DSD,h' : formatRate($result[1]) . ' DSD,h';
		}
		// PCM or Multichannel PCM
		else {
			$hd = ($mpd_format_tag[1] != 'f' && $mpd_format_tag[1] > 16) || $mpd_format_tag[0] > 44100 ? ',h' : ',s';
			$encoded_at = ($mpd_format_tag[1] == 'f' ? '' : $mpd_format_tag[1] . '/') . formatRate($mpd_format_tag[0]) . ' ' . strtoupper($ext) . $hd;
		}
	}
	// Radio station
	elseif (isset($song_data['Name']) || (substr($song_data['file'], 0, 4) == 'http' && !isset($song_data['Artist']))) {
		$encoded_at = $display_format == 'verbose' ? 'VBR compression' : 'VBR';
	}
	// UPnP file
	elseif (substr($song_data['file'], 0, 4) == 'http' && isset($song_data['Artist'])) {
		$encoded_at = 'Unknown';
	}
	// DSD file
	elseif ($ext == 'dsf' || $ext == 'dff') {
		$result = sysCmd('mediainfo --Inform="Audio;file:///var/www/mediainfo.tpl" ' . '"' . MPD_MUSICROOT . $song_data['file'] . '"');
		$encoded_at = 'DSD ' . ($result[1] == '' ? '?' : formatRate($result[1]) . ' Mbps');
	}
	// PCM file
	else {
		if ($song_data['file'] == '' || !file_exists(MPD_MUSICROOT . $song_data['file'])) {
			return 'File does not exist';
		}

		// Hack to allow file names with accented characters to work when passed to mediainfo via exec()
		$locale = 'en_GB.utf-8'; // Is this still needed?
		setlocale(LC_ALL, $locale);
		putenv('LC_ALL=' . $locale);

		// Mediainfo
		$cmd = 'mediainfo --Inform="Audio;file:///var/www/mediainfo.tpl" ' . '"' . MPD_MUSICROOT . $song_data['file'] . '"';
		debugLog($cmd);
		$result = sysCmd($cmd);

		$bitdepth = $result[0] == '' ? '?' : $result[0];
		$samplerate = $result[1] == '' ? '?' : $result[1];
		$channels = $result[2];
		$format = $result[3];

		if ($display_format == 'default') {
			$encoded_at = ($bitdepth == '?' ? formatRate($samplerate) . ' ' . $format : $bitdepth . '/' . formatRate($samplerate) . ' ' . $format);
		}
		else {
			$encoded_at = ($bitdepth == '?' ? formatRate($samplerate) . ' kHz, ' . formatChan($channels) . ' ' . $format : $bitdepth . ' bit, ' . formatRate($samplerate) . ' kHz, ' . formatChan($channels) . ' ' . $format);
		}
	}

	return $encoded_at;
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

	if ($_SESSION['audioout'] == 'Bluetooth') {
		$device = 'btstream';
	}
	elseif ($_SESSION['alsaequal'] != 'Off') {
		$device = 'alsaequal';
	}
	elseif ($_SESSION['eqfa4p'] != 'Off') {
		$device = 'eqfa4p';
	}
	else {
		$device = 'plughw:' . $array[0]['value'];
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

	if ($_SESSION['audioout'] == 'Bluetooth') {
		$device = 'btstream';
	}
	elseif ($_SESSION['alsaequal'] != 'Off') {
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
	$autoplay = $cfg_spotify['autoplay'] == 'Yes' ? ' --autoplay' : '';

	$cmd = 'librespot' .
		' --name "' . $_SESSION['spotifyname'] . '"' .
		' --bitrate ' . $cfg_spotify['bitrate'] .
		' --initial-volume ' . $cfg_spotify['initial_volume'] .
		$linear_volume .
		$volume_normalization .
		$autoplay .
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
		//workerLog('worker: Bluetooth controller initialized');
	}
}

// start dlna server
function startMiniDlna() {
	sysCmd('systemctl start minidlna');
}

// start lcd updater
function startLcdUpdater() {
	sysCmd('/var/www/command/lcdup.sh');
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

// Configure chip options
function cfgChipOptions($chipoptions, $chiptype) {
	$array = explode(',', $chipoptions);

	// Burr Brows PCM5: Analog volume, analog volume boost, digital interpolation filter
	if ($chiptype == 'burr_brown_pcm5') {
		sysCmd('amixer -c 0 sset "Analogue" ' . $array[0]);
		sysCmd('amixer -c 0 sset "Analogue Playback Boost" ' . $array[1]);
		sysCmd('amixer -c 0 sset "DSP Program" ' . '"' . $array[2] . '"');
	}
	// Allo Katana: Oversampling filter, de-emphasis, dop
	else if ($chiptype == 'ess_sabre_katana') {
		sysCmd('amixer -c 0 sset "DSP Program" ' . '"' . $array[0] . '"');
		sysCmd('amixer -c 0 sset "Deemphasis" ' . $array[1]);
		sysCmd('amixer -c 0 sset "DoP" ' . $array[2]);
	}
	// Audiophonics ES9028/9038 Q2M: Oversampling filter, input select
	else if ($chiptype == 'ess_sabre_audiophonics_q2m') {
		sysCmd('amixer -c 0 sset "FIR Filter Type" ' . '"' . $array[0] . '"');
		sysCmd('amixer -c 0 sset "I2S/SPDIF Select" ' . '"' . $array[1] . '"');
	}
	// MERUS Amp HAT ZW: Power mode profile
	else if ($chiptype == 'merus_ma12070p') {
		sysCmd('amixer -c 0 sset "Q.PM Prof" ' . '"' . $array[0] . '"');
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
	if (empty($result[1]['wlanssid']) || $result[1]['wlanssid'] == 'None (activates AP mode)') {
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
	$data .= 'country=' . $result[1]['wlan_country'] . "\n";
	$data .= "ctrl_interface=DIR=/var/run/wpa_supplicant GROUP=netdev\n";
	$data .= "update_config=1\n\n";
	if (!empty($result[1]['wlanssid']) && $result[1]['wlanssid'] != 'None (activates AP mode)') {
		$data .= "network={\n";
		$data .= 'ssid=' . '"' . $result[1]['wlanssid'] . '"' . "\n";
		$data .= "scan_ssid=1\n";
		// secure
		if ($result[1]['wlansec'] == 'wpa') {
			$data .= 'psk=' . $result[1]['wlan_psk'] . "\n";
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
	// read network config [2] = apd0
	$result = sdbquery('select * from cfg_network', cfgdb_connect());

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
	$data .= "ssid=" . $result[2]['wlanssid'] . "\n";
	$data .= "hw_mode=g\n";
	$data .= "channel=" . $result[2]['wlan_channel'] . "\n\n";

	$data .= "# Security settings\n";
	$data .= "macaddr_acl=0\n";
	$data .= "auth_algs=1\n";
	$data .= "ignore_broadcast_ssid=0\n";
	$data .= "wpa=2\n";
	$data .= "wpa_key_mgmt=WPA-PSK\n";
	$data .= 'wpa_psk=' . $result[2]['wlan_psk'] . "\n";
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

function waitForIpAddr($iface, $maxloops = 3, $sleeptime = 3000000) {
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
		'0092' => 'Pi-Zero 512MB v1.2',
		'0093' => 'Pi-Zero 512MB v1.3',
		'00c1' => 'Pi-Zero W 512MB v1.1',
		'1040' => 'Pi-2B 1GB v1.0',
		'1041' => 'Pi-2B 1GB v1.1',
		'1041' => 'Pi-2B 1GB v1.1',
		'2042' => 'Pi-2B 1GB v1.2',
		'2082' => 'Pi-3B 1GB v1.2',
		'20a0' => 'Pi-CM3 1GB v1.0',
		'20d3' => 'Pi-3B+ 1GB v1.3',
		'20e0' => 'Pi-3A+ 512 MB v1.0',
		// Generic CM3+ code
		'2100' => 'Pi-CM3+ 1GB v1.0',
		// Artificial code For Allo USBridge Signature (CM3+ based PCB)
		'210a' => 'Allo USBridge SIG [CM3+ Lite 1GB v1.0]',
		// Generic Pi-4B v1.1 code
		'3111' => 'Pi-4B 1/2/4GB v1.1',
		// Custom Pi-4B v1.1 codes to identify RAM size
		'a111' => 'Pi-4B 1GB v1.1',
		'b111' => 'Pi-4B 2GB v1.1',
		'c111' => 'Pi-4B 4GB v1.1',
		// Generic Pi-4B v1.2 code
		'3112' => 'Pi-4B 4GB v1.2',
		// Custom Pi-4B v1.2 codes to identify RAM size
		'a112' => 'Pi-4B 1GB v1.2',
		'b112' => 'Pi-4B 2GB v1.2',
		'c112' => 'Pi-4B 4GB v1.2'
	);

	$revnum = sysCmd('vcgencmd otp_dump | awk -F: ' . "'" . '/^30:/{print substr($2,5)}' . "'");

	// Pi-4B
	// Custom codes to identify the models by RAM size
 	if ($revnum[0] == '3111' || $revnum[0] == '3112') {
		$prefix = sysCmd('awk ' . "'" . '{if ($1=="Revision") print substr($3,0,2)}' . "'" . ' /proc/cpuinfo');
		$revnum[0] = $prefix[0] . substr($revnum[0], 1, 3);
	}
	// Pi-CM3+
	elseif ($revnum[0] == '2100') {
 		// Chip ID for Texas Instruments, Inc. TUSB8041 4-Port Hub
 		$chip_id = sysCmd('lsusb | grep "0451:8142"');
		if (!empty(chip_id[0])) {
			// Allo USBridge Signature
			$revnum[0] =  '210a';
		}

	}

	return array_key_exists($revnum[0], $revname) ? $revname[$revnum[0]] : 'Unknown Pi-model';
}

/*
Old style revision codes
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
New style revision codes
90 0021	A+		1.1	512 MB	Sony UK
90 0032	B+		1.2	512 MB	Sony UK
90 0092	Zero	1.2	512 MB	Sony UK
90 0093	Zero	1.3	512 MB	Sony UK
90 00c1	Zero W	1.1	512 MB	Sony UK
90 20e0	3A+		1.0	512 MB	Sony UK
92 0093	Zero	1.3	512 MB	Embest
a0 1040	2B		1.0	1 GB	Sony UK
a0 1041	2B		1.1	1 GB	Sony UK
a0 2082	3B		1.2	1 GB	Sony UK
a0 20a0	CM3		1.0	1 GB	Sony UK
a0 20d3	3B+		1.3	1 GB	Sony UK
a0 2100	CM3+	1.0	1 GB	Sony UK
a2 1041	2B		1.1	1 GB	Embest
a2 2042	2B		1.2	1 GB	Embest (with BCM2837)
a2 2082	3B		1.2	1 GB	Embest
a2 20a0	CM3		1.0	1 GB	Embest
a3 2082	3B		1.2	1 GB	Sony Japan
a5 2082	3B		1.2	1 GB	Stadium
a0 3111	4B		1.1	1GB		Sony UK
b0 3111	4B		1.1	2GB		Sony UK
c0 3111	4B		1.1	4GB		Sony UK
a0 3112	4B		1.2	1GB		Sony UK
b0 3112	4B		1.2	2GB		Sony UK
c0 3112	4B		1.2	4GB		Sony UK
*/

// config audio scrobbler
function cfgAudioScrobbler() {
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

// Auto-configure settings at worker startup
function autoConfig($cfgfile) {
	autoCfgLog('autocfg: Auto-configure initiated');

	//$contents = file_get_contents($cfgfile);
	$contents = str_replace("\r\n", "\n", file_get_contents($cfgfile));
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

	autoCfgLog('autocfg: Configuration file parsed');

	//
	autoCfgLog('autocfg: - Names');
	//

	sysCmd('/var/www/command/util.sh chg-name host "moode" ' . '"' . $autocfg['hostname'] . '"');
	playerSession('write', 'hostname', $autocfg['hostname']);

	playerSession('write', 'browsertitle', $autocfg['browsertitle']);

	sysCmd('/var/www/command/util.sh chg-name bluetooth "Moode Bluetooth" ' . '"' . $autocfg['bluetoothname'] . '"');
	playerSession('write', 'btname', $autocfg['bluetoothname']);

	playerSession('write', 'airplayname', $autocfg['airplayname']);
	playerSession('write', 'spotifyname', $autocfg['spotifyname']);

	$dbh = cfgdb_connect();
	$result = sdbquery('update cfg_sl set value=' . "'" . $autocfg['squeezelitename'] . "'" . ' where param=' . "'PLAYERNAME'", $dbh);
	sysCmd('/var/www/command/util.sh chg-name squeezelite "Moode" ' . '"' . $autocfg['squeezelitename'] . '"');

	sysCmd('/var/www/command/util.sh chg-name upnp "Moode UPNP" ' . '"' . $autocfg['upnpname'] . '"');
	playerSession('write', 'upnpname', $autocfg['upnpname']);

	sysCmd('/var/www/command/util.sh chg-name dlna "Moode DLNA" ' . '"' . $autocfg['dlnaname'] . '"');
	playerSession('write', 'dlnaname', $autocfg['dlnaname']);

	sysCmd('/var/www/command/util.sh chg-name mpdzeroconf ' . "'" . '"Moode MPD"' . "'" . ' ' . "'" . '"' . $autocfg['mpdzeroconf'] . '"' . "'");
	cfgdb_update('cfg_mpd', cfgdb_connect(), 'zeroconf_name', $autocfg['mpdzeroconf']);

	autoCfgLog('autocfg: Host name: ' . $autocfg['hostname']);
	autoCfgLog('autocfg: Browser title: ' . $autocfg['browsertitle']);
	autoCfgLog('autocfg: Bluetooth: ' . $autocfg['bluetoothname']);
	autoCfgLog('autocfg: Airplay: ' . $autocfg['airplayname']);
	autoCfgLog('autocfg: Spotify: ' . $autocfg['spotifyname']);
	autoCfgLog('autocfg: Squeezelite: ' . $autocfg['squeezelitename']);
	autoCfgLog('autocfg: UPnP: ' . $autocfg['upnpname']);
	autoCfgLog('autocfg: DLNA: ' . $autocfg['dlnaname']);
	autoCfgLog('autocfg: MPD zeroconf: ' . $autocfg['mpdzeroconf']);

	//
	autoCfgLog('autocfg: - Network (wlan0)');
	//

	$psk = genWpaPSK($autocfg['wlanssid'], $autocfg['wlanpwd']);
	$netcfg = sdbquery('select * from cfg_network', $dbh);
	$value = array('method' => $netcfg[1]['method'], 'ipaddr' => $netcfg[1]['ipaddr'], 'netmask' => $netcfg[1]['netmask'],
		'gateway' => $netcfg[1]['gateway'], 'pridns' => $netcfg[1]['pridns'], 'secdns' => $netcfg[1]['secdns'],
		'wlanssid' => $autocfg['wlanssid'], 'wlansec' => $autocfg['wlansec'], 'wlanpwd' => $psk, 'wlan_psk' => $psk,
		'wlan_country' => $autocfg['wlancountry'], 'wlan_channel' => '');
	cfgdb_update('cfg_network', $dbh, 'wlan0', $value);
	cfgNetIfaces();

	autoCfgLog('autocfg: SSID: ' . $autocfg['wlanssid']);
	autoCfgLog('autocfg: Security: ' . $autocfg['wlansec']);
	autoCfgLog('autocfg: Password: ' . $autocfg['wlanpwd']);
	autoCfgLog('autocfg: PSK: ' . $psk);
	autoCfgLog('autocfg: Country: ' . $autocfg['wlancountry']);

	//
	autoCfgLog('autocfg: - Network (apd0)');
	//

	$psk = genWpaPSK($autocfg['apdssid'], $autocfg['apdpwd']);
	$value = array('method' => '', 'ipaddr' => '', 'netmask' => '', 'gateway' => '', 'pridns' => '', 'secdns' => '',
		'wlanssid' => $autocfg['apdssid'], 'wlansec' => '', 'wlanpwd' => $psk, 'wlan_psk' => $psk,
		'wlan_country' => '', 'wlan_channel' => $autocfg['apdchan']);
	cfgdb_update('cfg_network', $dbh, 'apd0', $value);
	cfgHostApd();

	autoCfgLog('autocfg: SSID: ' . $autocfg['apdssid']);
	autoCfgLog('autocfg: Password: ' . $autocfg['apdpwd']);
	autoCfgLog('autocfg: PSK: ' . $psk);
	autoCfgLog('autocfg: Channel: ' . $autocfg['apdchan']);

	//
	autoCfgLog('autocfg: - Services');
	//

	playerSession('write', 'airplaysvc', $autocfg['airplaysvc']);
	playerSession('write', 'upnpsvc', $autocfg['upnpsvc']);
	playerSession('write', 'dlnasvc', $autocfg['dlnasvc']);

	autoCfgLog('autocfg: Airplay: ' . ($autocfg['airplaysvc'] == '0' ? 'Off' : 'On'));
	autoCfgLog('autocfg: UPnP: ' . ($autocfg['upnpsvc'] == '0' ? 'Off' : 'On'));
	autoCfgLog('autocfg: DLNA: ' . ($autocfg['dlnasvc'] == '0' ? 'Off' : 'On'));

	//
	autoCfgLog('autocfg: - Other');
	//

	sysCmd('/var/www/command/util.sh set-timezone ' . $autocfg['timezone']);
	playerSession('write', 'timezone', $autocfg['timezone']);
	playerSession('write', 'themename', $autocfg['themename']);
	playerSession('write', 'accent_color', $autocfg['accentcolor']);

	autoCfgLog('autocfg: Time zone: ' . $autocfg['timezone']);
	autoCfgLog('autocfg: Theme name: ' . $autocfg['themename']);
	autoCfgLog('autocfg: Accent color: ' . $autocfg['accentcolor']);

	sysCmd('rm ' . $cfgfile);
	autoCfgLog('autocfg: Configuration file deleted');
	autoCfgLog('autocfg: Auto-configure complete');
}

function genWpaPSK($ssid, $passphrase) {
	$fh = fopen('/tmp/passphrase', 'w');
	fwrite($fh, $passphrase . "\n");
	fclose($fh);

	$result = sysCmd('wpa_passphrase "' . $ssid . '" < /tmp/passphrase');
	sysCmd('rm /tmp/passphrase');
	//workerLog(print_r($result, true));

	$psk = explode('=', $result[4]);
	return $psk[1];
}

// Check for available software update (ex: update-rNNN.txt)
// $path = http: //moodeaudio.org/downloads/ or /var/local/www/
function checkForUpd($path) {
	if (false === ($pkgfile_contents = file_get_contents($path . 'update-' . getPkgId() . '.txt'))) {
		$result['Date'] = 'None';
	}
	else {
		$result = parseDelimFile($pkgfile_contents, ': ');
	}

	return $result;
}

// Get the id of the update package.
// This allows appending a suffix to the id when testing packages. Ex: rNNN-test1
function getPkgId () {
	$result = sdbquery("select value from cfg_system where param='pkgid_suffix'", cfgdb_connect());
	return $_SESSION['moode_release'] . $result[0]['value'];
}

// Get moode release
function getMoodeRel($options = '') {
	// Verbose: major.minor.patch yyyy-mm-dd
	if ($options === 'verbose') {
		$result = sysCmd("awk '/Release: /{print $2 \" \" $3;}' /var/www/footer.php | cut -d\"<\" -f 1"); // Remove trailing </li
		return $result[0];
	}
	// Compact: rNNN
	else {
		$result = sysCmd("awk '/Release: /{print $2;}' /var/www/footer.php | cut -d\"<\" -f 1");
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

	// Pi HDMI-1, HDMI-2 or Headphone jack, or a USB device
	if ($i2sDevice == 'none') {
		sysCmd('sed -i "s/dtparam=audio=off/dtparam=audio=on/" ' . '/boot/config.txt');
	}
	// I2S audio device
	else {
		$result = cfgdb_read('cfg_audiodev', cfgdb_connect(), $i2sDevice);
		sysCmd('sed -i "s/dtparam=audio=on/dtparam=audio=off/" ' . '/boot/config.txt');
		sysCmd('echo dtoverlay=' . $result[0]['driver'] . ' >> ' . '/boot/config.txt');
		playerSession('write', 'cardnum', '0');
		playerSession('write', 'adevname', $result[0]['name']);
		cfgdb_update('cfg_mpd', cfgdb_connect(), 'device', '0');
	}

	// add these back in
	$cmd = $_SESSION['p3wifi'] == '0' ? 'echo dtoverlay=disable-wifi >> ' . '/boot/config.txt' : 'echo "#dtoverlay=disable-wifi" >> ' . '/boot/config.txt';
	sysCmd($cmd);
	$cmd = $_SESSION['p3bt'] == '0' ? 'echo dtoverlay=disable-bt >> ' . '/boot/config.txt' : 'echo "#dtoverlay=disable-bt" >> ' . '/boot/config.txt';
	sysCmd($cmd);
}

// pi3 wifi adapter enable/disable
function ctlWifi($ctl) {
	$cmd = $ctl == '0' ? 'sed -i /disable-wifi/c\dtoverlay=disable-wifi ' . '/boot/config.txt' : 'sed -i /disable-wifi/c\#dtoverlay=disable-wifi ' . '/boot/config.txt';
	sysCmd($cmd);
}

// pi3 bt adapter enable/disable
function ctlBt($ctl) {
	if ($ctl == '0') {
		sysCmd('sed -i /disable-bt/c\dtoverlay=disable-bt ' . '/boot/config.txt');
	}
	else {
		sysCmd('sed -i /disable-bt/c\#dtoverlay=disable-bt ' . '/boot/config.txt');
	}
}

// Set audio source
function setAudioIn($input_source) {
	sysCmd('mpc stop');
	$result = sdbquery("SELECT value FROM cfg_system WHERE param='wrkready'", cfgdb_connect());

 	// No need to configure Local during startup (wrkready = 0)
	if ($input_source == 'Local' && $result[0]['value'] == '1') {
		if ($_SESSION['i2sdevice'] == 'HiFiBerry DAC+ ADC') {
			sysCmd('killall -s 9 alsaloop');
		}
		elseif ($_SESSION['i2sdevice'] == 'Audiophonics ES9028/9038 DAC' || $_SESSION['i2sdevice'] == 'Audiophonics ES9028/9038 DAC (Pre 2019)') {
			sysCmd('amixer -c 0 sset "I2S/SPDIF Select" I2S');
		}

		if ($_SESSION['mpdmixer'] == 'hardware') {
			playerSession('write', 'volknob_preamp', $_SESSION['volknob']);
			sysCmd('/var/www/vol.sh ' . $_SESSION['volknob_mpd']);
		}

		sendEngCmd('inpactive0');

		if ($_SESSION['rsmafterinp'] == 'Yes') {
			sysCmd('mpc play');
		}
	}
	// NOTE: the Source Select form requires MPD Volume control is set to Hardware or Disabled (0dB)
	elseif ($input_source == 'Analog' || $input_source == 'S/PDIF') {
		if ($_SESSION['mpdmixer'] == 'hardware') {
			// Only update this value during startup (wrkready = 0)
			if ($result[0]['value'] == '1') {
				playerSession('write', 'volknob_mpd', $_SESSION['volknob']);
			}
			sysCmd('/var/www/vol.sh ' . $_SESSION['volknob_preamp']);
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

// Set MPD and renderer audio output
function setAudioOut($audioout) {
	// MPD
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

	// Renderers
	stopSps();
	stopSpotify();
	startSps();
	startSpotify();

	// Other
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

// Reconfigure MPD volume
function reconfMpdVolume($mixertype) {
	cfgdb_update('cfg_mpd', cfgdb_connect(), 'mixer_type', $mixertype);
	playerSession('write', 'mpdmixer', $mixertype);
	// Reset hardware volume to 0dB if indicated
	if (($mixertype == 'software' || $mixertype == 'disabled') && $_SESSION['alsavolume'] != 'none') {
		sysCmd('/var/www/command/util.sh set-alsavol ' . '"' . $_SESSION['amixname']  . '" ' . $_SESSION['alsavolume_max']);
	}
	// Update /etc/mpd.conf
	updMpdConf($_SESSION['i2sdevice']);
}

// store back link for configs
function storeBackLink($section, $tpl) {
	$root_configs = array('lib-config', 'snd-config', 'net-config', 'sys-config');
	$referer_link = substr($_SERVER['HTTP_REFERER'], strrpos($_SERVER['HTTP_REFERER'], '/'));

	session_start();

	if ($tpl == 'src-config.html') {
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

// Create enhanced metadata
function enhanceMetadata($current, $sock, $caller = '') {
	define(LOGO_ROOT_DIR, 'imagesw/radio-logos/');
	define(DEF_RADIO_COVER, 'images/default-cover-v6.svg');
	define(DEF_COVER, 'images/default-cover-v6.svg');

	$song = parseCurrentSong($sock);
	$current['file'] = $song['file'];

	// NOTE any of these might be '' null string
	$current['track'] = $song['Track'];
	$current['date'] = $song['Date'];
	$current['composer'] = $song['Composer'];
	// Cover hash
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
		// Only do this code block once for a given file
		if ($current['file'] != $_SESSION['currentfile']) {
			$current['encoded'] = getEncodedAt($song, 'default'); // encoded bit depth and sample rate
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

		// iTunes aac or aiff file
		$ext = getFileExt($song['file']);
		if (isset($song['Name']) && ($ext == 'm4a' || $ext == 'aif' || $ext == 'aiff')) {
			$current['artist'] = isset($song['Artist']) ? $song['Artist'] : 'Unknown artist';
			$current['title'] = $song['Name'];
			$current['album'] = isset($song['Album']) ? $song['Album'] : 'Unknown album';
			$current['coverurl'] = '/coverart.php/' . rawurlencode($song['file']);
			//debugLog('enhanceMetadata(): iTunes AAC or AIFF file');
		}
		// Radio station
		elseif (isset($song['Name']) || (substr($song['file'], 0, 4) == 'http' && /*!isset($song['Artist'])*/!isset($current['duration']))) {
			debugLog('enhanceMetadata(): Radio station');
			$current['artist'] = 'Radio station';

			if (!isset($song['Title']) || trim($song['Title']) == '') {
				$current['title'] = 'Streaming source';
				//$current['title'] = $song['file']; // URL
			}
			else {
				// Use custom name for certain stations if needed
				//$current['title'] = strpos($song['Title'], 'Radio Active FM') !== false ? $song['file'] : $song['Title'];
				$current['title'] = $song['Title'];
			}

			if (isset($_SESSION[$song['file']])) {
				// Use xmitted name for SOMA FM stations
				$current['album'] = substr($_SESSION[$song['file']]['name'], 0, 4) == 'Soma' ? $song['Name'] : $_SESSION[$song['file']]['name'];
				// Include original station name
				$current['station_name'] = $_SESSION[$song['file']]['name'];
				if ($_SESSION[$song['file']]['logo'] == 'local') {
					$current['coverurl'] = LOGO_ROOT_DIR . $_SESSION[$song['file']]['name'] . ".jpg"; // local logo image
				}
				else {
					$current['coverurl'] = $_SESSION[$song['file']]['logo']; // url logo image
				}
				# Hardcode displayed bitrate for BBC 320K stations since MPD does not seem to pick up the rate since 0.20.10
				if (strpos($_SESSION[$song['file']]['name'], 'BBC') !== false && strpos($_SESSION[$song['file']]['name'], '320K') !== false) {
					$current['bitrate'] = '320 kbps';
				}
			}
			else {
				// Not in radio station table, use xmitted name or 'unknown'
				$current['album'] = isset($song['Name']) ? $song['Name'] : 'Unknown station';
				$current['station_name'] = $current['album'];
				$current['coverurl'] = DEF_RADIO_COVER;
			}
		}
		// Song file, UPnP URL or Podcast
		else {
			$current['artist'] = isset($song['Artist']) ? $song['Artist'] : 'Unknown artist';
			$current['title'] = isset($song['Title']) ? $song['Title'] : pathinfo(basename($song['file']), PATHINFO_FILENAME);
			$current['album'] = isset($song['Album']) ? $song['Album'] : 'Unknown album';
			$current['disc'] = isset($song['Disc']) ? $song['Disc'] : 'Disc tag missing';
			if (substr($song['file'], 0, 4) == 'http') {
				// Podcast
				if (isset($_SESSION[$song['file']])) {
					$current['coverurl'] = LOGO_ROOT_DIR . $_SESSION[$song['file']]['name'] . ".jpg";
					$current['artist'] = 'Radio station';
					$current['album'] = $_SESSION[$song['file']]['name'];
				}
				// UPnP file
				else {
					$current['coverurl'] = getUpnpCoverUrl();
				}
			}
			// Song file
			else {
				$current['coverurl'] = '/coverart.php/' . rawurlencode($song['file']);
			}
			//$current['coverurl'] = substr($song['file'], 0, 4) == 'http' ? getUpnpCoverUrl() : '/coverart.php/' . rawurlencode($song['file']);
			// In case 2 url's are returned
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

//function getCoverHash($file, $ext) {
function getCoverHash($file) {
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

// Load radio data into session
function loadRadio() {
	// Delete radio station session vars to purge any orphans
	foreach ($_SESSION as $key => $value) {
		if (substr($key, 0, 5) == 'http:') {
			unset($_SESSION[$key]);
		}
	}
	// Load cfg_radio into session
	$result = cfgdb_read('cfg_radio', cfgdb_connect());
	foreach ($result as $row) {
		if ($row['station'] != 'DELETED') {
			$_SESSION[$row['station']] = array('name' => $row['name'], 'type' => $row['type'], 'logo' => $row['logo']);
		}
	}
}
