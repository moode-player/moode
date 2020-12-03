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
 * 2020-MM-DD TC moOde 7.0.0
 *
 * This includes the @chris-rudmin 2019-08-08 rewrite of the GenLibrary() function
 * to support the new Library renderer /var/www/js/scripts-library.js
 * Refer to https://github.com/moode-player/moode/pull/16 for more info.
 *
 */

define('MPD_RESPONSE_ERR', 'ACK');
define('MPD_RESPONSE_OK',  'OK');
define('MPD_MUSICROOT',  '/var/lib/mpd/music/');
define('SQLDB', 'sqlite:/var/local/www/db/moode-sqlite3.db');
define('SQLDB_PATH', '/var/local/www/db/moode-sqlite3.db');
define('MOODE_LOG', '/var/log/moode.log');
define('AUTOCFG_LOG', '/home/pi/autocfg.log');
define('PORT_FILE', '/tmp/portfile');
define('THMCACHE_DIR', '/var/local/www/imagesw/thmcache/');
define('LIBCACHE_BASE', '/var/local/www/libcache');
define('ALSA_PLUGIN_PATH', '/etc/alsa/conf.d');
define('SESSION_SAVE_PATH', '/var/local/php');
define('TMP_STATION_PREFIX', '__tmp__');
define('EXPORT_DIR', '/var/local/www/imagesw');
define('MPD_VERSIONS_CONF', '/var/local/www/mpd_versions.conf');

// Size and quality factor for small thumbs
// Used in thmcache.php, worker.php
define('THM_SM_W', '80');
define('THM_SM_H', '80');
define('THM_SM_Q', '75');

error_reporting(E_ERROR);

// Features availability bitmask
// NOTE: Updates must also be made to matching code blocks in playerlib.js, sysinfo.sh, moodeutl, and footer.php
// sqlite3 /var/local/www/db/moode-sqlite3.db "select value from cfg_system where param='feat_bitmask'"
// sqlite3 /var/local/www/db/moode-sqlite3.db "UPDATE cfg_system SET value='31671' WHERE param='feat_bitmask'"
const FEAT_KERNEL		= 1;		// y Kernel architecture option on System Config
const FEAT_AIRPLAY		= 2;		// y Airplay renderer
const FEAT_MINIDLNA 	= 4;		// y DLNA server
//const FEAT_MPDAS		= 8; 		// y MPD audio scrobbler (DEPRECATED)
const FEAT_RECORDER		= 8; 		//   Stream recorder
const FEAT_SQUEEZELITE	= 16;		// y Squeezelite renderer
const FEAT_UPMPDCLI 	= 32;		// y UPnP client for MPD
const FEAT_SQSHCHK		= 64;		// 	 Require squashfs for software update
const FEAT_RESERVED1	= 128;		// y Reserved for future use
const FEAT_LOCALUI		= 256;		// y Local display
const FEAT_INPSOURCE	= 512;		// y Input source select
const FEAT_UPNPSYNC 	= 1024;		//   UPnP volume sync
const FEAT_SPOTIFY		= 2048;		// y Spotify Connect renderer
const FEAT_GPIO 		= 4096;		// y GPIO button handler
const FEAT_DJMOUNT		= 8192;		// y UPnP media browser
const FEAT_BLUETOOTH	= 16384;	// y Bluetooth renderer
const FEAT_DEVTWEAKS	= 32768;	//   Developer tweaks
//						-------
//						  31671

// Mirror for footer.php
$FEAT_INPSOURCE 	= 512;

// MPD patch availability bitmask
// NOTE: Updates must also be made to matching code blocks in sysinfo.sh
// The value appears as a suffix to the MPD version string. Ex: mpd-0.21.24_p0x3
// $mpdver = explode(" ", strtok(shell_exec('mpd -V | grep "Music Player Daemon"'),"\n"))[3];
// $patch_id = explode('_p0x', $_SESSION['mpdver'])[1];
const PATCH_SELECTIVE_RESAMPLING	= 1; // Selective resampling options
const PATCH_SOX_CUSTOM_RECIPE		= 2; // Custom SoX resampling recipes
//								-------
//								  	  3

// Selective resampling bitmask
const SOX_UPSAMPLE_ALL			= 3; // Upsample if source < target rate
const SOX_UPSAMPLE_ONLY_41K		= 1; // Upsample only 44.1K source rate
const SOX_UPSAMPLE_ONLY_4148K	= 2; // Upsample only 44.1K and 48K source rates
const SOX_ADHERE_BASE_FREQ		= 8; // Resample (adhere to base freq)

// Album and Radio HD badge parameters
// NOTE: These are mirrored in playerlib.js
const ALBUM_HD_BADGE_TEXT 			= 'HD';
const ALBUM_BIT_DEPTH_THRESHOLD 	= 16;
const ALBUM_SAMPLE_RATE_THRESHOLD 	= 44100;
const RADIO_HD_BADGE_TEXT 			= 'HiRes';
const RADIO_BITRATE_THRESHOLD 		= 128;

// Reserved root directory names
$ROOT_DIRECTORIES = array('NAS', 'SDCARD', 'USB', 'UPNP');

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


// Core MPD functions

// AG from Moode 3 prototype
// TC retry to improve robustness
function openMpdSock($host, $port) {
	for ($i = 0; $i < 6; $i++) {
		if (false === ($sock = @stream_socket_client('tcp://' . $host . ':' . $port, $errorno, $errorstr, 30))) {
			debugLog('openMpdSocket(): error: connection failed (' . ($i + 1) . ') ' . $errorno . ', ' . $errorstr);
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

	while (false !== ($str = fgets($sock, 1024)) && !feof($sock)) {
		if (strncmp(MPD_RESPONSE_OK, $str, strlen(MPD_RESPONSE_OK)) == 0) {
			$resp = $resp == '' ? $str : $resp;
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
		// Socket timed out or PHP/MPD connection failure
		debugLog('readMpdResponse(): error: fgets failure (' . explode("\n", $resp)[0] . ')');
	}

	return $resp;
}

function closeMpdSock($sock) {
	sendMpdCmd($sock, 'close');
	fclose($sock);
}

function sendMpdCmd($sock, $cmd) {
	fputs($sock, $cmd . "\n");
}

function chainMpdCmds($sock, $cmds) {
    sendMpdCmd($sock, 'command_list_begin');
    foreach ($cmds as $cmd) {
        sendMpdCmd($sock, $cmd);
    }
    sendMpdCmd($sock, 'command_list_end');
    $resp = readMpdResp($sock);
}

function getMpdStatus($sock) {
	sendMpdCmd($sock, 'status');
	$status = readMpdResp($sock);
	return $status;
}

function getMpdStats($sock) {
	sendMpdCmd($sock, 'stats');
	$stats = readMpdResp($sock);
	return $stats;
}

// Miscellaneous core functions

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

	// Export device table
	sysCmd('sqlite3 ' . SQLDB_PATH . " \"SELECT * FROM cfg_audiodev WHERE drvoptions NOT IN ('slave', 'glb_mclk') AND chipoptions = ''\" > /tmp/cfg_audiodev.sql");
	// Broom www root
	sysCmd('find /var/www -type l -delete');

	// Check database schema
	$result = sysCmd('sqlite3 /var/local/www/db/moode-sqlite3.db .schema | grep ro_columns');
	if (empty($result)) {
		$_SESSION['ic_return_code'] = '1';
		return false;
	}

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

	// Verify the row count
	$count = sysCmd('sqlite3 ' . SQLDB_PATH . " \"SELECT COUNT() FROM cfg_audiodev\"");
	if ($count[0] != 78) {
		$_SESSION['ic_return_code'] = '4';
		return false;
	}

	return $warning === true ? 'passed with warnings' : 'passed';
}

// Socket routines for engine-cmd.php
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
	if (filesize(libcache_file()) != 0) {
		//workerLog('loadLibrary(): Return existing ' . libcache_file());
		return file_get_contents(libcache_file());
	}
	else {
		//workerLog('loadLibrary(): Generate new ' . libcache_file());
		$flat = genFlatList($sock);
		if ($flat != '') {
			// Normal or UTF8 replace
			if ($_SESSION['library_utf8rep'] == 'No') {
				$json_lib = genLibrary($flat);
			}
			else {
				$json_lib = genLibraryUTF8Rep($flat);
			}
			return $json_lib;
		}
		else {
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
		// NOTE: MPD must be compiled with libpcre++-dev to make use of PERL compatible regex
		// NOTE: Logic in genLibrary() determines whether M4A format is Lossless or Lossy
		switch ($_SESSION['library_flatlist_filter']) {
			// Return full library
			case 'full_lib':
			// These filters are in genLibrary()
			case 'encoded':
			case 'hdonly':
			case 'year':
				$cmd = "search base \"" . $dir . "\"";
				break;
			// Advanced search dialog
			case 'tags':
				$cmd = "search \"((base '" . $dir . "') AND (" . $_SESSION['library_flatlist_filter_str'] . "))\"";
				break;
			// Filter on specific tag containing the string or if string is empty perform an 'any' filter
			case 'album':
			case 'albumartist':
			case 'any':
			case 'artist':
			case 'composer':
			case 'conductor':
			case 'file':
			case 'genre':
			case 'label':
			case 'performer':
			case 'title':
			case 'work':
				$tag = empty($_SESSION['library_flatlist_filter_str']) ? 'any' : $_SESSION['library_flatlist_filter'];
				$str = empty($_SESSION['library_flatlist_filter_str']) ? $_SESSION['library_flatlist_filter'] : $_SESSION['library_flatlist_filter_str'];
				$cmd = "search \"((base '" . $dir . "') AND (" . $tag . " contains '" . $str . "'))\"";
				break;
			// Filter on file path or extension
			// NOTE: Lossless and Lossy have an additional m4a probe in genLibrary()
			case 'folder':
			case 'format':
				$cmd = "search \"((base '" . $dir . "') AND (file contains '" . $_SESSION['library_flatlist_filter_str'] . "'))\"";
				break;
			case 'lossless':
				$cmd = "search \"((base '" . $dir . "') AND (file =~ 'm4a$|flac$|aif$|aiff$|wav$|dsf$|dff$'))\"";
				break;
			case 'lossy':
				$cmd = "search \"((base '" . $dir . "') AND (file !~ 'flac$|aif$|aiff$|wav$|dsf$|dff$'))\"";
				break;
		}
		//workerLog($cmd);
		sendMpdCmd($sock, $cmd);
		$resp .= readMpdResp($sock);
	}

	//workerLog('genFlatList(): is_null($resp)= ' . (is_null($resp) === true ? 'true' : 'false') . ', substr($resp, 0, 2)= ' . substr($resp, 0, 2));
	if (!is_null($resp)) {
		$lines = explode("\n", $resp);
		$item = 0;
		$flat = array();
		$linecount = count($lines);
		//workerLog('genFlatList(): $linecount= ' . $linecount);

		for ($i = 0; $i < $linecount; $i++) {
			list($element, $value) = explode(': ', $lines[$i], 2);

			if ($element == 'OK') {
				// NOTE: Skip any ACK's
			}
			else if ($element == 'file') {
				$item = count($flat);
				$flat[$item][$element] = $value;
			}
			// @Atair: Gather possible multiple Genre, Artist, Performer and Conductor values as array
			elseif ($element == 'Genre') {
				if ($flat[$item]['Genre']) {
					array_push($flat[$item]['Genre'], $value);
				}
				else {
					$flat[$item]['Genre'] = array($value);
				}
			}
			elseif ($element == 'Artist') {
				if ($flat[$item]['Artist']) {
					array_push($flat[$item]['Artist'], $value);
				}
				else {
					$flat[$item]['Artist'] = array($value);
				}
			}
			// @Atair: add performers to artists
			elseif ($element == 'Performer') {
				if ($flat[$item]['Artist']) {
					array_push($flat[$item]['Artist'], $value);
				}
				else {
					$flat[$item]['Artist'] = array($value);
				}
			}
			// @Atair: add conductor to artists
			elseif ($element == 'Conductor') {
				if ($flat[$item]['Artist']) {
					array_push($flat[$item]['Artist'], $value);
				}
				else {
					$flat[$item]['Artist'] = array($value);
				}
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

// Generate library array (@chris-rudmin 2019-08-08 rewrite)
function genLibrary($flat) {
	$lib = array();

	// Break out misc lib options
	// [0] = Include comment tag: Yes | No
	// [1] = Album Key: Album@Artist (Default) | Album@Artist@AlbumID | FolderPath | FolderPath@AlbumID
	$inc_comment_tag = explode(',', $_SESSION['library_misc_options'])[0];
	$inc_mbrz_albumid = strpos(explode(',', $_SESSION['library_misc_options'])[1], 'AlbumID') !== false ? 'Yes' : 'No';

	// Setup date range filter
	if ($_SESSION['library_flatlist_filter'] == 'year') {
		$filter_year = explode('-', $_SESSION['library_flatlist_filter_str']);
		if (!isset($filter_year[1])) {
	 		$filter_year[1] = $filter_year[0];
		}
	}

	foreach ($flat as $flatData) {
		// M4A probe for ALAC when filtering by Lossless/Lossy
		if (strpos($_SESSION['library_flatlist_filter'], 'loss') !== false && getFileExt($flatData['file']) == 'm4a') {
			$fh = fopen(MPD_MUSICROOT . $flatData['file'], "rb");
			$alac_found = strpos(fread($fh, 512), 'alac');
			fclose($fh);

			if ($_SESSION['library_flatlist_filter'] == 'lossless' && $alac_found !== false) {
				$push = true;
			}
			elseif ($_SESSION['library_flatlist_filter'] == 'lossy' && $alac_found === false) {
				$push = true;
			}
			else {
				$push = false;
			}
			//workerLog(($push === true ? 'T|' : 'F|') . $flatData['file']);
		}
		else {
			$push = true;
		}

		// Date range filter
		if ($_SESSION['library_flatlist_filter'] == 'year') {
			$track_year = getTrackYear($flatData);
			$push = ($track_year >= $filter_year[0] && $track_year <= $filter_year[1]) ? true : false;
		}

		// Encoded or HD filter
		if ($_SESSION['library_flatlist_filter'] == 'encoded' || $_SESSION['library_flatlist_filter'] == 'hdonly') {
			$encoded_at = getEncodedAt($flatData, 'default', true);

			if ($_SESSION['library_flatlist_filter'] == 'encoded') {
				$push = strpos($encoded_at, $_SESSION['library_flatlist_filter_str']) !== false ? true : false;
			}
			else {
				$push = strpos($encoded_at, 'h', -1) !== false ? true : false;
			}
		}

		if ($push === true) {
			$songData = array(
				'file' => $flatData['file'],
				'tracknum' => ($flatData['Track'] ? $flatData['Track'] : ''),
				'title' => ($flatData['Title'] ? $flatData['Title'] : 'Unknown Title'),
				'disc' => ($flatData['Disc'] ? $flatData['Disc'] : '1'),
				//@Atair:
				// 1. artist can safely be empty, because it is no longed used as substitute for missing album_artist
				// 2. album_artist shall never be empty, otherwise the sort routines in scripts-library.js complain,
				//    because they expect artist as string and not as array in album_artist || artist constructs
				// 3. When AlbumArtist is not defined and artist contains a single value, it is assumed that Artist should be a surrogate for ALbumArtist.
				//    otherwise, when Artist is an array of two and more values or empty, the AlbumArtist is set to 'Unknown' (this is regarded as bad tagging)
				'artist' => ($flatData['Artist'] ? $flatData['Artist'] : array()), //@Atair: array is expected in scripts-library.js even when empty
				'album_artist' => ($flatData['AlbumArtist'] ? $flatData['AlbumArtist'] : (count($flatData['Artist']) == 1 ? $flatData['Artist'][0] : 'Unknown AlbumArtist')),
				'composer' => ($flatData['Composer'] ? $flatData['Composer'] : 'Composer tag missing'),
				'conductor' => ($flatData['Conductor'] ? $flatData['Conductor'] : 'Conductor tag missing'),
				'year' => getTrackYear($flatData),
				'time' => $flatData['Time'],
				'album' => ($flatData['Album'] ? $flatData['Album'] : 'Unknown Album'),
				'genre' => ($flatData['Genre'] ? $flatData['Genre'] : array('Unknown')), // @Atair: 'Unknown' genre has to be an array
				'time_mmss' => songTime($flatData['Time']),
				'last_modified' => $flatData['Last-Modified'],
				'encoded_at' => getEncodedAt($flatData, 'default', true),
				'comment' => (($flatData['Comment'] && $inc_comment_tag == 'Yes') ? $flatData['Comment'] : ''),
				'mb_albumid' => (($flatData['MUSICBRAINZ_ALBUMID'] && $inc_mbrz_albumid == 'Yes') ? $flatData['MUSICBRAINZ_ALBUMID'] : '0')
			);

			array_push($lib, $songData);
		}
	}

	// No filter results or empty MPD database
	if (count($lib) == 1 && empty($lib[0]['file'])) {
		$lib[0]['file'] = '';
		$lib[0]['title'] = '';
		$lib[0]['artist'] = array('');
		$lib[0]['album'] = 'Nothing found';
		$lib[0]['album_artist'] = '';
		$lib[0]['genre'] = array('');
		$lib[0]['time_mmss'] = '';
		$lib[0]['encoded_at'] = '0';
		//workerLog('genLibrary(): No filter results or empty MPD database');
		//workerLog(print_r($lib ,true));
	}

	if (false === ($json_lib = json_encode($lib, JSON_INVALID_UTF8_SUBSTITUTE))) {
		workerLog('genLibrary(): Error: json_encode($lib) failed');
	}

	if (false === (file_put_contents(libcache_file(), $json_lib))) {
		workerLog('genLibrary(): Error: file create failed: ' . libcache_file());
	}

	//workerLog(print_r($lib, true));
	//workerLog(print_r($json_lib, true));
	//workerLog('genLibrary(): json_error_message(): ' . json_error_message());
	return $json_lib;
}

function libcache_file() {
	switch ($_SESSION['library_flatlist_filter']) {
		case 'full_lib':
			$suffix = '_all.json';
			break;
		case 'folder':
		case 'format':
		case 'hdonly':
		case 'lossless':
		case 'lossy':
			$suffix = '_' . strtolower($_SESSION['library_flatlist_filter']) . '.json';
			break;
		case 'album':
		case 'any':
		case 'artist':
		case 'composer':
		case 'conductor':
		case 'encoded':
		case 'file':
		case 'genre':
		case 'label':
		case 'performer':
		case 'tags': // Indicates filter was submitted via Adv search
		case 'title':
		case 'work':
		case 'year':
			$suffix = '_tag.json';
			break;
	}

	return LIBCACHE_BASE . $suffix;
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
// libcache json file empty. Replacing the non-UTF8 chars in the array before json_encode solves this problem (@lazybat).
function genLibraryUTF8Rep($flat) {
	$lib = array();

	// Break out misc lib options
	// [0] = Include comment tag: Yes | No
	// [1] = Album Key: Album@Artist (Default) | Album@Artist@AlbumID | FolderPath | FolderPath@AlbumID
	$inc_comment_tag = explode(',', $_SESSION['library_misc_options'])[0];
	$inc_mbrz_albumid = strpos(explode(',', $_SESSION['library_misc_options'])[1], 'AlbumID') !== false ? 'Yes' : 'No';

	// Setup date range filter
	if ($_SESSION['library_flatlist_filter'] == 'year') {
		$filter_year = explode('-', $_SESSION['library_flatlist_filter_str']);
		if (!isset($filter_year[1])) {
	 		$filter_year[1] = $filter_year[0];
		}
	}

	foreach ($flat as $flatData) {
		// M4A probe for ALAC when filtering by Lossless/Lossy
		if (strpos($_SESSION['library_flatlist_filter'], 'loss') !== false && getFileExt($flatData['file']) == 'm4a') {
			$fh = fopen(MPD_MUSICROOT . $flatData['file'], "rb");
			$alac_found = strpos(fread($fh, 512), 'alac');
			fclose($fh);

			if ($_SESSION['library_flatlist_filter'] == 'lossless' && $alac_found !== false) {
				$push = true;
			}
			elseif ($_SESSION['library_flatlist_filter'] == 'lossy' && $alac_found === false) {
				$push = true;
			}
			else {
				$push = false;
			}
			//workerLog(($push === true ? 'T|' : 'F|') . $flatData['file']);
		}
		else {
			$push = true;
		}

		// Date range filter
		if ($_SESSION['library_flatlist_filter'] == 'year') {
			$track_year = getTrackYear($flatData);
			$push = ($track_year >= $filter_year[0] && $track_year <= $filter_year[1]) ? true : false;
		}

		// Encoded or HD filter
		if ($_SESSION['library_flatlist_filter'] == 'encoded' || $_SESSION['library_flatlist_filter'] == 'hdonly') {
			$encoded_at = getEncodedAt($flatData, 'default', true);

			if ($_SESSION['library_flatlist_filter'] == 'encoded') {
				$push = strpos($encoded_at, $_SESSION['library_flatlist_filter_str']) !== false ? true : false;
			}
			else {
				$push = strpos($encoded_at, 'h', -1) !== false ? true : false;
			}
		}

		if ($push === true) {
			$songData = array(
				'file' => utf8rep($flatData['file']),
				'tracknum' => utf8rep(($flatData['Track'] ? $flatData['Track'] : '')),
				'title' => utf8rep(($flatData['Title'] ? $flatData['Title'] : 'Unknown Title')),
				'disc' => ($flatData['Disc'] ? $flatData['Disc'] : '1'),
				'artist' => utf8repArray(($flatData['Artist'] ? $flatData['Artist'] : array())), //@Atair: array is expected in scripts-library.js even when empty
				//@Atair:
				// 1. album_artist shall never be empty, otherwise the sort routines in scripts-library.js complain,
				//    because they expect artist as string and not as array in album_artist || artist constructs
				// 2. When AlbumArtist is not defined and artist contains a single value, it is assumed that Artist should be a surrogate for ALbumArtist.
				//    otherwise, when Artist is an array of two and more values or empty, the AlbumArtist is set to 'Unknown' (this is regarded as bad tagging)
				'album_artist' => utf8rep(($flatData['AlbumArtist'] ? $flatData['AlbumArtist'] : (count($flatData['Artist']) == 1 ? $flatData['Artist'][0] : 'Unknown AlbumArtist'))),
				'composer' => utf8rep(($flatData['Composer'] ? $flatData['Composer'] : 'Composer tag missing')),
				'conductor' => utf8rep(($flatData['Conductor'] ? $flatData['Conductor'] : 'Conductor tag missing')),
				'year' => utf8rep(getTrackYear($flatData)),
				'time' => utf8rep($flatData['Time']),
				'album' => utf8rep(($flatData['Album'] ? $flatData['Album'] : 'Unknown Album')),
				'genre' => utf8repArray(($flatData['Genre'] ? $flatData['Genre'] : array('Unknown'))), // @Atair: 'Unknown' genre has to be an array
				'time_mmss' => utf8rep(songTime($flatData['Time'])),
				'last_modified' => $flatData['Last-Modified'],
				'encoded_at' => utf8rep(getEncodedAt($flatData, 'default', true)),
				'comment' => utf8rep((($flatData['Comment'] && $inc_comment_tag == 'Yes') ? $flatData['Comment'] : '')),
				'mb_albumid' => utf8rep((($flatData['MUSICBRAINZ_ALBUMID'] && $inc_mbrz_albumid == 'Yes') ? $flatData['MUSICBRAINZ_ALBUMID'] : '0'))
			);

			array_push($lib, $songData);
		}
	}

	// No filter results or empty MPD database
	if (count($lib) == 1 && empty($lib[0]['file'])) {
		$lib[0]['file'] = '';
		$lib[0]['title'] = '';
		$lib[0]['artist'] = array('');
		$lib[0]['album'] = 'Nothing found';
		$lib[0]['album_artist'] = '';
		$lib[0]['genre'] = array('');
		$lib[0]['time_mmss'] = '';
		$lib[0]['encoded_at'] = '0';
		//workerLog('genLibrary(): No filter results or empty MPD database');
		//workerLog(print_r($lib ,true));
	}

	if (false === ($json_lib = json_encode($lib, JSON_INVALID_UTF8_SUBSTITUTE))) {
		workerLog('genLibraryUTF8Rep(): error: json_encode($lib) failed');
	}

	if (false === (file_put_contents(libcache_file(), $json_lib))) {
		workerLog('genLibraryUTF8Rep(): error: file create failed: ' . libcache_file());
	}

	return $json_lib;
}

//@Atair: utf8rep for arrays
function utf8repArray($some_array) {
	for ($i=0; $i<count($some_array); $i++) {
		$some_array[$i] = utf8rep($some_array[$i]);
	}
	return $some_array;
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

function clearLibCacheAll() {
	sysCmd('truncate ' . LIBCACHE_BASE . '_* --size 0');
	cfgdb_update('cfg_system', cfgdb_connect(), 'lib_pos','-1,-1,-1');
}

function clearLibCacheFiltered() {
	sysCmd('truncate ' . LIBCACHE_BASE . '_folder.json --size 0');
	sysCmd('truncate ' . LIBCACHE_BASE . '_format.json --size 0');
	sysCmd('truncate ' . LIBCACHE_BASE . '_tag.json --size 0');
	cfgdb_update('cfg_system', cfgdb_connect(), 'lib_pos','-1,-1,-1');
}

// Add one item (song file, playlist, radio station, directory) to the Queue
function ___addItemToQueue($sock, $path) {
	$ext = getFileExt($path);
	$pl_extensions = array('m3u', 'pls', 'cue');
	//workerLog($path . ' (' . $ext . ')');

	// Use load for saved playlist, cue sheet, radio station
	if (in_array($ext, $pl_extensions) || (strpos($path, '/') === false && in_array($path, $GLOBALS['ROOT_DIRECTORIES']) === false)) {
		// Radio station special case
		if (strpos($path, 'RADIO') !== false) {
			// Check for playlist as URL
			$pls = file_get_contents(MPD_MUSICROOT . $path);
			$url = parseDelimFile($pls, '=')['File1'];
			$ext = substr($url, -4);
			if ($ext == '.pls' || $ext == '.m3u') {
				$path = $url;
			}
		}
		sendMpdCmd($sock, 'load "' . html_entity_decode($path) . '"');
	}
	// Use add for song file or directory
	else {
		sendMpdCmd($sock, 'add "' . html_entity_decode($path) . '"');
	}

	return readMpdResp($sock);
}

// Add one item (song file, playlist, radio station, directory) to the Queue
function addItemToQueue($path) {
	$ext = getFileExt($path);
	$pl_extensions = array('m3u', 'pls', 'cue');
	//workerLog($path . ' (' . $ext . ')');

	// Use load for saved playlist, cue sheet, radio station
	if (in_array($ext, $pl_extensions) || (strpos($path, '/') === false && in_array($path, $GLOBALS['ROOT_DIRECTORIES']) === false)) {
		// Radio station special case
		if (strpos($path, 'RADIO') !== false) {
			// Check for playlist as URL
			$pls = file_get_contents(MPD_MUSICROOT . $path);
			$url = parseDelimFile($pls, '=')['File1'];
			$ext = substr($url, -4);
			if ($ext == '.pls' || $ext == '.m3u') {
				$path = $url;
			}
		}
		$cmd = 'load';
	}
	// Use add for song file or directory
	else {
		$cmd = 'add';
	}

	return $cmd . ' "' . html_entity_decode($path) . '"';
}

// Add group of song files to the Queue (Tag/Album view)
function addGroupToQueue($songs) {
	$cmds = array();

	foreach ($songs as $song) {
		array_push($cmds, 'add "' . html_entity_decode($song) . '"');
	}

	return $cmds;
}

// Get file extension
function getFileExt($file) {
	$ext = strtolower(pathinfo($file, PATHINFO_EXTENSION));
	return $ext == '' ? 'no_extension_found' : $ext;
}

// Parse delimited file
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

// Get playist
function getPLInfo($sock) {
	sendMpdCmd($sock, 'playlistinfo');
	$resp = readMpdResp($sock);

	if (is_null($resp)) {
		return NULL;
	}
	else {
		$array = array();
		$line = strtok($resp,"\n");
		$idx = -1;

		while ($line) {
			list ($element, $value) = explode(': ', $line, 2);

			if ($element == 'file') {
				$idx++;
				$array[$idx]['file'] = $value;
				$array[$idx]['fileext'] = getFileExt($value);
				$array[$idx]['TimeMMSS'] = songTime($array[$idx]['Time']);
			}
			else {
				// Return only the first of multiple occurrences of the following tags
				if ($element == 'Genre' || $element == 'Artist' || $element == 'AlbumArtist' || $element == 'Conductor' || $element == 'Performer') {
					if (!isset($array[$idx][$element])) {
						$array[$idx][$element] = $value;
					}
				}
				// All other tags
				else {
					$array[$idx][$element] = $value;
				}
			}

			$line = strtok("\n");
		}
	}

	return $array;
}

// Parse station info
function parseStationInfo($path) {
	//workerLog($path);
	$array = array();
	$result = sdbquery("select * from cfg_radio where station='" . SQLite3::escapeString($path) . "'", cfgdb_connect());

	$array[0] = array('Station name' => $result[0]['name']);
	$array[1] = array('Playable URL' => $result[0]['station']);
	$array[2] = array('Type' => $result[0]['type'] == 'f' ? 'Favorite' : ($result[0]['type'] == 'r' ? 'Regular' : 'Hidden'));
	$array[3] = array('Genre' => $result[0]['genre']);
	$array[4] = array('Broadcaster' => $result[0]['broadcaster']);
	$array[5] = array('Language' => $result[0]['language']);
	$array[6] = array('Country' => $result[0]['country']);
	$array[7] = array('Region' => $result[0]['region']);
	$array[8] = array('Bitrate' => $result[0]['bitrate']);
	$array[9] = array('Audio format' => $result[0]['format']);
	$array[10] = array('Geo fenced' => $result[0]['geo_fenced']);

	//workerLog(print_r($array, true));
	return $array;
}

// Parse track info
function parseTrackInfo($resp) {
	/* Layout
	0  Artists
	1  Album artist
	2  Composer
	3  Conductor
	4  Genres
	5  Album
	6  Disc
	7  Track
	8  Title
	9  Date
	10 Duration
	11 Audio format
	*/

	if (is_null($resp)) {
		return NULL;
	}
	else {
		$array = array();
		$line = strtok($resp, "\n");
		$num_lines = 12;

		for ($i = 0; $i < $num_lines; $i++) {
			$array[$i] = '';
		}

		while ($line) {
			list ($element, $value) = explode(': ', $line, 2);

			switch ($element) {
				// Not needed for display
				case 'duration':
				case 'Last-Modified':
				case 'Format':
					break;
				// All others
				case 'file':
					$file = $value;
					break;
				case 'Artist':
				case 'Performer':
					$artists .= $value . ', ';
					break;
				case 'AlbumArtist':
					$array[1] = array('Album artist' => $value);
					break;
				case 'Composer':
					$array[2] = array($element => $value);
					break;
				case 'Conductor':
					$array[3] = array($element => $value);
					break;
				case 'Genre':
					$genres .= $value . ', ';
					break;
				case 'Album':
					$array[5] = array($element => $value);
					break;
				case 'Disc':
					$array[6] = array($element => $value);
					break;
				case 'Track':
					$array[7] = array($element => $value);
					break;
				case 'Title':
					$array[8] = array($element => $value);
					break;
				case 'Date':
					$array[9] = array($element => $value);
					break;
				case 'Time':
					$array[10] = array('Duration' => songTime($value));
					break;
			}

			$line = strtok("\n");
		}

		// Strip off trailing delimiter
		$array[0] = array('Artists' => rtrim($artists, ', '));
		$array[4] = array('Genres' => rtrim($genres, ', '));

		// Add audio format
		$array[11] = array('Audio format' => getEncodedAt(array('file' => $file), 'default'));
	}

	//workerLog(print_r($array, true));
	return $array;
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

// Search mpd database
function searchDB($sock, $querytype, $query = '') {
	//workerLog($querytype . ', ' . $query);
	switch ($querytype) {
		// List a database path
		case 'lsinfo':
			if (!empty($query)){
				sendMpdCmd($sock, 'lsinfo "' . html_entity_decode($query) . '"');
				break;
			}
			else {
				sendMpdCmd($sock, 'lsinfo');
				break;
			}
		// Search all tags
		case 'any':
			sendMpdCmd($sock, 'search any "' . html_entity_decode($query) . '"');
			break;
		// Search specified tags
		case 'specific':
			sendMpdCmd($sock, 'search "(' . html_entity_decode($query) . ')"');
			break;
	}

	$resp = readMpdResp($sock);
	return parseList($resp);
}

// Format searchDB output
function parseList($resp) {
	if (is_null($resp)) {
		return NULL;
	}
	else {
		$array = array();
		$line = strtok($resp,"\n");
		$idx = -1;

		while ($line) {
			list ($element, $value) = explode(': ', $line, 2);

			if ($element == 'file') {
				$idx++;
				$array[$idx]['file'] = $value;
				$array[$idx]['fileext'] = getFileExt($value);
			}
			else if ($element == 'directory') {
				$idx++;
				$diridx++; // Save directory index for further processing
				$array[$idx]['directory'] = $value;
				$cover_file = md5($value) . '_sm.jpg';
				$array[$idx]['cover_url'] = file_exists(THMCACHE_DIR . $cover_file) ? '/imagesw/thmcache/' . $cover_file : '';
			}
			else if ($element == 'playlist') {
				if (substr($value,0, 5) == 'RADIO' || strtolower(pathinfo($value, PATHINFO_EXTENSION)) == 'cue') {
					$idx++;
					$array[$idx]['file'] = $value;
					$array[$idx]['fileext'] = getFileExt($value);
				}
				else {
					$idx++;
					$array[$idx]['playlist'] = $value;
				}
			}
			else {
				$array[$idx][$element] = $value;
				$array[$idx]['TimeMMSS'] = songTime($array[$idx]['Time']);
			}

			$line = strtok("\n");
		}

		// Put dirs on top
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
		return 'parseHwParams(): Response is null';
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

// Parse MPD currentsong output
function parseCurrentSong($sock) {
	sendMpdCmd($sock, 'currentsong');
	$resp = readMpdResp($sock);

	$artist_count = 0;

	if (is_null($resp) ) {
		return 'parseCurrentSong(): Response is null';
	}
	else {
		$array = array();
		$line = strtok($resp, "\n");

		while ($line) {
			list ($element, $value) = explode(": ", $line, 2);

			// NOTE: Let's save this for future use
			// These tags can have multiple occurances so lets accumulate them as a delimited string
			/*if ($element == 'Genre' || $element == 'Artist' || $element == 'Conductor' || $element == 'Performer') {
				$array[$element] .= $value . '; ';
			}
			// All other tags
			else {
				$array[$element] = $value;
			}*/

			// Return only the first of multiple occurrences of the following tags
			if ($element == 'Genre' || $element == 'Artist' || $element == 'AlbumArtist' || $element == 'Conductor' || $element == 'Performer') {
				if (!isset($array[$element])) {
					$array[$element] = $value;
				}
				// Tally the number of "artists"
				if ($element == 'Artist' || $element == 'Conductor' || $element == 'Performer') {
					$artist_count++;
				}
			}
			// All other tags
			else {
				$array[$element] = $value;
			}

			$line = strtok("\n");
		}

		// NOTE: Let's save this for future use
		// Strip off trailing delimiter
		/*foreach($array as $key => $value) {
			if ($key == 'Genre' || $key == 'Artist' || $key == 'Conductor' || $key == 'Performer') {
				$array[$key] = rtrim($array[$key], '; ');
			}
		}*/

		$array['artist_count'] = $artist_count;

		//workerLog(print_r($array, true));
		return $array;
	}
}

// Find a file or album in the Queue
function findInQueue($sock, $tag, $search) {
	sendMpdCmd($sock, 'playlistfind ' . $tag . ' "' . $search . '"');
	$resp = readMpdResp($sock);

	if ($resp == "OK\n") {
		return 'findInQueue(): ' . $tag . ' ' . $search . ' not found';
	}

	$array = array();
	$line = strtok($resp, "\n");

	// Return position
	if ($tag == 'file') {
		while ($line) {
			list ($element, $value) = explode(": ", $line, 2);
			if ($element == 'Pos') {
				$array['Pos'] = $value;
				break;
			}

			$line = strtok("\n");
		}
	}
	// Return files and positions
	else if ($tag == 'album') {
		$i = 0;
		while ($line) {
			list ($element, $value) = explode(": ", $line, 2);
			if ($element == 'file') {
				$array[$i]['file'] = $value;
			}
			if ($element == 'Pos') {
				$array[$i]['Pos'] = $value;
				$i++;
			}

			$line = strtok("\n");
		}
	}

	return $array;
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
		return 'parseStationFile(): Response is null';
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
		return 'parsePlayHist(): Response is null';
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

// Database management
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
		$querystr = 'SELECT name, alt_name, dacchip, chipoptions, iface, list, driver, drvoptions FROM ' . $table . $filter;
	}
	else if ($table == 'cfg_theme') {
		$querystr = 'SELECT theme_name, tx_color, bg_color, mbg_color FROM ' . $table . ' WHERE theme_name="' . $param . '"';
	}
	else if ($table == 'cfg_radio') {
		$querystr = $param == 'all' ? 'SELECT * FROM ' . $table . ' WHERE station not in ("DELETED", "zx reserved 499")' :
			'SELECT station, name, logo FROM ' . $table . ' WHERE station="' . $param . '"';
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
			$querystr = "UPDATE " . $table . " SET value='" . SQLite3::escapeString($value) . "' where param='" . $key . "'";
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
	$patch_id = explode('_p0x', $_SESSION['mpdver'])[1];

	$data .= "#########################################\n";
	$data .= "# This file is automatically generated   \n";
	$data .= "# by the MPD configuration page.         \n";
	$data .= "#########################################\n\n";

	foreach ($mpdcfg as $cfg) {
		switch ($cfg['param']) {
			// Code block or other params
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
			case 'input_cache':
				$input_cache = $cfg['value'];
				break;
			case 'audio_output_format':
				$data .= $cfg['value'] == 'disabled' ? '' : $cfg['param'] . " \"" . $cfg['value'] . "\"\n";
				break;
			case 'sox_quality':
				$sox_quality = ($cfg['value'] == 'custom' && ($patch_id & PATCH_SOX_CUSTOM_RECIPE)) ? $cfg['value'] : 'high';
				break;
			case 'sox_multithreading':
				$sox_multithreading = $cfg['value'];
				break;
			case 'replay_gain_handler':
				$replay_gain_handler = $cfg['value'];
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
			case 'sox_precision':
				$sox_precision = $cfg['value'];
				break;
			case 'sox_phase_response':
				$sox_phase_response = $cfg['value'];
				break;
			case 'sox_passband_end':
				$sox_passband_end = $cfg['value'];
				break;
			case 'sox_stopband_begin':
				$sox_stopband_begin = $cfg['value'];
				break;
			case 'sox_attenuation':
				$sox_attenuation = $cfg['value'];
				break;
			case 'sox_flags':
				$sox_flags = $cfg['value'];
				break;
			case 'selective_resample_mode':
				$data .= ($cfg['value'] != '0' && ($patch_id & PATCH_SELECTIVE_RESAMPLING)) ? $cfg['param'] . " \"" . $cfg['value'] . "\"\n" : '';
				break;
			// Default param handling
			default:
				$data .= $cfg['param'] . " \"" . $cfg['value'] . "\"\n";
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

	// Input cache
	if ($input_cache != 'Disabled') {
		$data .= "input_cache {\n";
		$data .= "size \"" . $input_cache . "\"\n";
		$data .= "}\n\n";
	}

	// Resampler
	$data .= "resampler {\n";
	$data .= "plugin \"soxr\"\n";
	$data .= "quality \"" . $sox_quality . "\"\n";
	$data .= "threads \"" . $sox_multithreading . "\"\n";
	if ($sox_quality == 'custom' && ($patch_id & PATCH_SOX_CUSTOM_RECIPE)) {
		$data .= "precision \"" . $sox_precision . "\"\n";
	    $data .= "phase_response \"" . $sox_phase_response . "\"\n";
	    $data .= "passband_end \"" . $sox_passband_end . "\"\n";
	    $data .= "stopband_begin \"" . $sox_stopband_begin . "\"\n";
	    $data .= "attenuation \"" . $sox_attenuation . "\"\n";
	    $data .= "flags \"" . $sox_flags . "\"\n";
	}
	$data .= "}\n\n";

	// ALSA local (outputs 1 - 5)
	$names = array (
		"name \"ALSA default\"\n" . "device \"hw:" . $device . ",0\"\n",
		"name \"ALSA crossfeed\"\n" . "device \"crossfeed\"\n",
		"name \"ALSA parametric eq\"\n" . "device \"eqfa12p\"\n",
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

	// Stream recorder (output 8)
	if (($_SESSION['feat_bitmask'] & FEAT_RECORDER) && $_SESSION['recorder_status'] != 'Not installed') {
		include '/var/www/inc/recorder_mpd.php';
	}

	if ($_SESSION['feat_bitmask'] & FEAT_DEVTWEAKS) {
		$fh = fopen('/etc/mpd.moode.conf', 'w');
		fwrite($fh, $data);
		fclose($fh);
		sysCmd("/var/www/command/mpdconfmerge.py /etc/mpd.moode.conf /etc/mpd.custom.conf");
	}
	else {
		$fh = fopen('/etc/mpd.conf', 'w');
		fwrite($fh, $data);
		fclose($fh);
	}

	// Update confs with device num (cardnum)
	sysCmd("sed -i '/slave.pcm \"plughw/c\ \tslave.pcm \"plughw:" . $device . ",0\";' " . ALSA_PLUGIN_PATH . '/crossfeed.conf');
	sysCmd("sed -i '/slave.pcm \"plughw/c\ \tslave.pcm \"plughw:" . $device . ",0\";' " . ALSA_PLUGIN_PATH . '/eqfa12p.conf');
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
	// Pi HDMI 1, HDMI 2 or Headphone jack, or a USB audio device
	if ($_SESSION['i2sdevice'] == 'none') {
		for ($i = 0; $i < 4; $i++) {
			$alsa_id = trim(file_get_contents('/proc/asound/card' . $i . '/id'));
			$aplay_device_name = trim(sysCmd("aplay -l | awk -F'[' '/card " . $i . "/{print $2}' | cut -d']' -f1")[0]);
			$result = cfgdb_read('cfg_audiodev', cfgdb_connect(), $alsa_id);
			if ($result === true) { // Not in table
				$devices[$i] = $aplay_device_name;
			}
			else {
				$devices[$i] = $result[0]['alt_name'];
			}
			//workerLog('card' . $i . ' (' . $devices[$i] . ')');
		}
	}
	// I2S audio device
	else {
		$devices[0] = $_SESSION['i2sdevice'];
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

			debugLog('sourceMount(): Command=(' . $mountstr . ')');
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
	$script .= "<script>\n";
	$script .= "function ui_notify() {\n";
	$script .= "$.pnotify.defaults.history = false;\n";
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
	$script .= "opacity: 1.0});\n";
	$script .= "}\n";
	$script .= "</script>\n";

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

	// Special section to handle calls from genLibrary() to populate the element "encoded_at"
	// Uses the MPD Format tag (rate:bits:channels) for PCM and mediainfo for DSD
	// Returned string for PCM is "bits/rate format,flag" and for DSD it's "DSD rate,h"
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
			// Workaround for wrong bit depth returned for ALAC encoded m4a files
			$mpd_format_tag[1] = ($ext == 'm4a' && $mpd_format_tag[1] == '32') ? '24' : $mpd_format_tag[1];
			$hd = ($mpd_format_tag[1] != 'f' && $mpd_format_tag[1] > ALBUM_BIT_DEPTH_THRESHOLD) || $mpd_format_tag[0] > ALBUM_SAMPLE_RATE_THRESHOLD ? ',h' : ',s';
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
		$encoded_at = 'DSD ' . ($result[1] == '' ? '?' : formatRate($result[1]) . ' MHz');
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
		//workerLog($cmd);
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
	// Verbose logging
	if ($_SESSION['debuglog'] == '1') {
		$logging = '-vv';
		$logfile = '/var/log/shairport-sync.log';
	}
	else {
		$logging = '';
		$logfile = '/dev/null';
	}

	// Get device num
	$array = sdbquery('select value from cfg_mpd where param="device"', cfgdb_connect());
	$device = $array[0]['value'];

	if ($_SESSION['audioout'] == 'Bluetooth') {
		$device = 'btstream';
	}
	elseif ($_SESSION['alsaequal'] != 'Off') {
		$device = 'alsaequal';
	}
	elseif ($_SESSION['eqfa12p'] != 'Off') {
		$device = 'eqfa12p';
	}
	else {
		$device = 'plughw:' . $array[0]['value'];
	}

	// Interpolation param handled in config file
	$cmd = '/usr/local/bin/shairport-sync ' . $logging .
		' -a "' . $_SESSION['airplayname'] . '" ' .
		'-- -d ' . $device . ' > ' . $logfile . ' 2>&1 &';

	debugLog('startSps(): (' . $cmd . ')');
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
	elseif ($_SESSION['eqfa12p'] != 'Off') {
		$device = 'eqfa12p';
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
		//' -v > /home/pi/librespot.txt 2>&1 &'; // For debug

	debugLog('startSpotify(): (' . $cmd . ')');
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
		workerLog('startBt(): Bluetooth error, no MAC address');
	}
	// initialize controller
	else {
		$result = sysCmd('/var/www/command/bt.sh -i');
		//workerLog('startBt(): Bluetooth controller initialized');
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

// Auto-shuffle random play
function startAutoShuffle() {
	$ashuffle_filter = (!empty($_SESSION['ashuffle_filter']) && $_SESSION['ashuffle_filter'] != 'None') ?
		'mpc search ' . $_SESSION['ashuffle_filter'] . ' | ' : '';
	$ashuffle_file = $ashuffle_filter != '' ? '--file -' : '';
	$ashuffle_mode = $_SESSION['ashuffle_mode'] == 'Album' ? '--group-by album ' : '';
	$result = sysCmd($ashuffle_filter . '/usr/local/bin/ashuffle --queue-buffer 1 ' . $ashuffle_mode . $ashuffle_file . ' > /dev/null 2>&1 &');
}
function stopAutoShuffle() {
	sysCmd('killall -s 9 ashuffle > /dev/null');
	playerSession('write', 'ashuffle', '0');
	if (false === ($sock = openMpdSock('localhost', 6600))) {
		workerLog('stopAutoShuffle(): MPD connect failed');
		exit(0);
	}
	sendMpdCmd($sock, 'consume 0');
	$resp = readMpdResp($sock);
	closeMpdSock($sock);
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
		'c112' => 'Pi-4B 4GB v1.2',
		// Generic Pi-4B v1.4 code
		'3114' => 'Pi-4B 8GB v1.4',
		// Custom Pi-4B v1.4 codes to identify RAM size
		'd114' => 'Pi-4B 8GB v1.4'
	);

	$revnum = sysCmd('vcgencmd otp_dump | awk -F: ' . "'" . '/^30:/{print substr($2,5)}' . "'");

	// Pi-4B
	// Custom codes to identify the models by RAM size
 	if ($revnum[0] == '3111' || $revnum[0] == '3112' || $revnum[0] == '3114') {
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
d0 3114	4B		1.4	8GB		Sony UK
*/

// returns the settings for reading/writing the autoConfig file
function autoConfigSettings() {
	$debug = true;
	// handler is config item name is just setting the playSession
	function setPlayerSession($values) {
		playerSession('write', array_key_first($values), $values[array_key_first($values)]);
	}

	// handler is config item name is just setting the playSession and a syscmd call to util.sh
	function setPlayerSessionAndSysCmd($values, $command ) {
		sysCmd('/var/www/command/util.sh '.sprintf($command, $values[array_key_first($values)]) );
		playerSession('write', array_key_first($values), $values[array_key_first($values)]);
	}

	function setCfgMpd($values) {
		$dbh = cfgdb_connect();
		$total_query ='';
		foreach ($values  as $key=>$value) {
			$query = sprintf('update cfg_mpd set value="%s" where param="%s"; ', $value,  $key);
			$result = sdbquery($query, $dbh);
		}
	}

	function getCfgMpd($values) {
		$dbh = cfgdb_connect();
		$result ='';
		foreach ($values  as $key) {
			$query = 'select param,value from cfg_mpd where param="' . $key . '"';
			$rows = sdbquery($query, $dbh);
			$result = $result . sprintf("%s = \"%s\"\n", $key, $rows[0]['value']);
		}
		return $result;
	}

	// Configuration of the autoconfig item handling
	// - requires - array of autoconfig items that should be present (all) before the handler is executed.
	//            most item only have 1 autoconfig item, but network setting requires multiple to be present
	// - handler for setting the config item
	// - section_var - if autoconfig item name differs from session var, the name of the session var can be provided
	// - command - argument for util.sh when setPlayerSessionAndSysCmd handler is used.
	$configurationHandlers = [
		'Names',
		['requires' => ['browsertitle'] , 'handler' => setPlayerSession],
		['requires' => ['hostname'] , 'handler' => setPlayerSessionAndSysCmd, 'cmd' => 'chg-name host "moode" "%s"'],
		['requires' => ['bluetoothname'] , 'session_var' => 'btname', 'handler' => setPlayerSessionAndSysCmd, 'cmd' => 'chg-name bluetooth "Moode Bluetooth" "%s"'],
		['requires' => ['airplayname'] , 'handler' => setPlayerSession],
		['requires' => ['spotifyname'] , 'handler' => setPlayerSession],
		['requires' => ['squeezelitename'] , 'handler' => function($values) {
				$dbh = cfgdb_connect();
				$result = sdbquery('update cfg_sl set value=' . "'" . $values['squeezelitename'] . "'" . ' where param=' . "'PLAYERNAME'", $dbh);
				sysCmd('/var/www/command/util.sh chg-name squeezelite "Moode" ' . '"' . $values['squeezelitename'] . '"');
			}, 'custom_write' => function($values) {
				$dbh = cfgdb_connect();
				$result = sdbquery("select value from cfg_sl where param='PLAYERNAME'", $dbh)[0]['value'];
				return "squeezelitename = \"".$result."\"\n";
			}],
		['requires' => ['upnpname'] , 'handler' => setPlayerSessionAndSysCmd, 'cmd' => 'chg-name upnp "Moode UPNP" "%s"'],
		['requires' => ['dlnaname'] , 'handler' => setPlayerSessionAndSysCmd, 'cmd' => 'chg-name dlna "Moode DLNA" "%s"'],

		'System',
		['requires' => ['timezone'] , 'handler' => setPlayerSessionAndSysCmd, 'cmd' => 'set-timezone %s'],
		['requires' => ['keyboard'] , 'handler' => setPlayerSessionAndSysCmd, 'cmd' => 'set-keyboard %s'],
		//TODO: decide use the same value as in the database or make Captalized ?then also required a custom writer?
		// ['requires' => ['cpugov'] , 'handler' => function($values) {
		// 		//TODO: use native of the caption one ? , is not the same as in session. give problems with extraction
		// 		playerSession('write', 'cpugov', $values['cpugov'] == 'Performance' ? 'performance' : 'ondemand');
		//}],
		['requires' => ['cpugov'] , 'handler' => setPlayerSession],
		['requires' => ['hdmiport'] , 'handler' => setPlayerSession],
		['requires' => ['eth0chk'] , 'handler' => setPlayerSession],
		['requires' => ['led_state'] , 'handler' => setPlayerSession],
		['requires' => ['localui'] , 'handler' => setPlayerSession],
		['requires' => ['p3wifi'] , 'handler' => function($values) {
			ctlWifi($values['p3wifi']);
			playerSession('write', 'p3wifi', $values['p3wifi']);
		}],
		['requires' => ['p3bt'] , 'handler' => function($values) {
			ctlBt($values['p3bt']);
			playerSession('write', 'p3bt', $values['p3bt']);
		}],
		['requires' => ['expandfs'] , 'handler' => function($values) {
			if( in_array( strtolower($values['expandfs']), ["1", "yes", "true"]) ) {
				sysCmd('/var/www/command/resizefs.sh start');
			}
		}, 'custom_write' => function($values) {
			$result = sysCmd("df | grep root | awk '{print $2}'");
			$expanded = $result[0] > 3500000 ? 1 : 0;
			return sprintf("expandfs =\"%d\"\n", $expanded);
		}],

		'I2S Device',
		['requires' => ['i2sdevice'] , 'handler' => function($values) {
			cfgI2sOverlay($values['i2sdevice'] == "None" ? 'none' : $values['i2sdevice']);
			playerSession('write', 'i2sdevice', $values['i2sdevice']);
		}],

		'MPD',
		['requires' => ['mixer_type'] , 'handler' => setCfgMpd, 'custom_write' => getCfgMpd],
		['requires' => ['device'] , 'handler' => setCfgMpd, 'custom_write' => getCfgMpd],
		['requires' => ['audio_output_format'] , 'handler' => setCfgMpd, 'custom_write' => getCfgMpd],
		['requires' => ['selective_resample_mode'] , 'handler' => setCfgMpd, 'custom_write' => getCfgMpd],
		['requires' => ['sox_quality'] , 'handler' => setCfgMpd, 'custom_write' => getCfgMpd],
		['requires' => ['sox_multithreading'] , 'handler' => setCfgMpd, 'custom_write' => getCfgMpd],
		['requires' => ['sox_precision',
						'sox_phase_response',
						'sox_passband_end',
						'sox_stopband_begin',
						'sox_attenuation',
						'sox_flags']
		 , 'handler' => setCfgMpd, 'custom_write' => getCfgMpd],

		'Renderers',
		['requires' => ['btsvc'] , 'handler' => setPlayerSession],
		['requires' => ['pairing_agent'] , 'handler' => setPlayerSession],
		['requires' => ['rsmafterbt'] , 'handler' => setPlayerSession],
		['requires' => ['airplaysvc'] , 'handler' => setPlayerSession],
		['requires' => ['rsmafterapl'] , 'handler' => setPlayerSession],
		['requires' => ['spotifysvc'] , 'handler' => setPlayerSession],
		['requires' => ['rsmafterspot'] , 'handler' => setPlayerSession],
		['requires' => ['slsvc'] , 'handler' => setPlayerSession],
		['requires' => ['rsmaftersl'] , 'handler' => setPlayerSession],

		'UPnP/DLNA',
		['requires' => ['upnpsvc'] , 'handler' => setPlayerSession],
		['requires' => ['dlnasvc'] , 'handler' => setPlayerSession],
		['requires' => ['upnp_browser'] , 'handler' => setPlayerSession],

		'Network (wlan0)',
		['requires' => ['wlanssid', 'wlanpwd', 'wlansec', 'wlancountry'] , 'handler' => function($values) {
				$dbh = cfgdb_connect();
				$psk = genWpaPSK($values['wlanssid'], $values['wlanpwd']);
				$netcfg = sdbquery('select * from cfg_network', $dbh);
				$value = array('method' => $netcfg[1]['method'], 'ipaddr' => $netcfg[1]['ipaddr'], 'netmask' => $netcfg[1]['netmask'],
					'gateway' => $netcfg[1]['gateway'], 'pridns' => $netcfg[1]['pridns'], 'secdns' => $netcfg[1]['secdns'],
					'wlanssid' => $values['wlanssid'], 'wlansec' => $values['wlansec'], 'wlanpwd' => $psk, 'wlan_psk' => $psk,
					'wlan_country' => $values['wlancountry'], 'wlan_channel' => '');
				cfgdb_update('cfg_network', $dbh, 'wlan0', $value);
				cfgNetIfaces();
			}, 'custom_write' => function($values) {
				$dbh = cfgdb_connect();
				$row = sdbquery("select * from cfg_network where iface='wlan0'", $dbh)[0];
				$result="";
				$result = $result."wlanssid = \"" . $row['wlanssid'] . "\"\n";
				$result = $result."wlanpwd = \"" . "" . "\"\n"; // keep empty
				$result = $result."wlansec = \"" . $row['wlansec'] . "\"\n";
				$result = $result."wlancountry = \"" . $row['wlan_country'] . "\"\n";
				return $result;
			}],

		'Network (apd0)',
		['requires' => ['apdssid', 'apdpwd', 'apdchan'] , 'handler' => function($values) {
				$dbh = cfgdb_connect();
				$psk = genWpaPSK($values['apdssid'], $values['apdpwd']);
				$value = array('method' => '', 'ipaddr' => '', 'netmask' => '', 'gateway' => '', 'pridns' => '', 'secdns' => '',
					'wlanssid' => $values['apdssid'], 'wlansec' => '', 'wlanpwd' => $psk, 'wlan_psk' => $psk,
					'wlan_country' => '', 'wlan_channel' => $values['apdchan']);
				cfgdb_update('cfg_network', $dbh, 'apd0', $value);
				cfgHostApd();
			}, 'custom_write' => function($values) {
				$dbh = cfgdb_connect();
				$row = sdbquery("select * from cfg_network where iface='apd0'", $dbh)[0];
				$result = $result . "apdssid = \"" . $row['wlanssid'] . "\"\n";
				$result = $result . "apdpwd = \"" . "" . "\"\n"; // keep empty
				$result = $result . "apdchan = \"" . $row['wlan_channel'] . "\"\n";
				return $result;
			}],

		'Preferences',
		'Appearance',
		['requires' => ['themename'] , 'handler' => setPlayerSession],
		['requires' => ['accent_color'] , 'handler' => setPlayerSession],
		['requires' => ['alphablend'] , 'handler' => setPlayerSession],
		['requires' => ['adaptive'] , 'handler' => setPlayerSession],
		['requires' => ['cover_backdrop'] , 'handler' => setPlayerSession],
		['requires' => ['cover_blur'] , 'handler' => setPlayerSession],
		['requires' => ['cover_scale'] , 'handler' => setPlayerSession],
		['requires' => ['font_size'] , 'handler' => setPlayerSession],

		'Playback',
		['requires' => ['playlist_art'] , 'handler' => setPlayerSession],
		['requires' => ['extra_tags'] , 'handler' => setPlayerSession],
		['requires' => ['playhist'] , 'handler' => setPlayerSession],

		'Library',
		['requires' => ['library_instant_play'] , 'handler' => setPlayerSession],
		['requires' => ['library_albumview_sort'] , 'handler' => setPlayerSession],
		['requires' => ['library_tagview_sort'] , 'handler' => setPlayerSession],
		['requires' => ['library_recently_added'] , 'handler' => setPlayerSession],
		['requires' => ['library_encoded_at'] , 'handler' => setPlayerSession],
		['requires' => ['library_covsearchpri'] , 'handler' => setPlayerSession],
		['requires' => ['library_hiresthm'] , 'handler' => setPlayerSession],
		['requires' => ['library_thumbnail_columns'] , 'handler' => setPlayerSession],

		'Library (Advanced)',
		['requires' => ['library_tagview_artist'] , 'handler' => setPlayerSession],
		['requires' => ['library_misc_options'] , 'handler' => setPlayerSession],
		['requires' => ['library_ignore_articles'] , 'handler' => setPlayerSession],
		['requires' => ['library_show_genres'] , 'handler' => setPlayerSession],
		['requires' => ['library_tagview_covers'] , 'handler' => setPlayerSession],
		['requires' => ['library_ellipsis_limited_text'] , 'handler' => setPlayerSession],
		['requires' => ['library_utf8rep'] , 'handler' => setPlayerSession],

		'Internal',
		['requires' => ['first_use_help'] , 'handler' => function($values) {
			playerSession('write', 'first_use_help', ($values['first_use_help'] == 'Yes' ? 'y,y' : 'n,n'));
		}, 'custom_write' => function($values) {
			$value = $_SESSION['first_use_help'] == 'n,n' ? "No" : "Yes";
			return "first_use_help = \"".$value."\"\n";
		}],

		'Sources',
		// Sources are using the array construction of the ini reader
		// source_name[0] = ...
		['requires' => ['source_name',
						'source_type',
						'source_address',
						'source_remotedir',
						'source_username',
						'source_password',
						'source_charset',
						'source_rsize',
						'source_wsize',
						'source_wsize',
						'source_options'], 'handler' => function($values) {
			$source_count = count($values['source_name']);
			$keys = array_keys($values);

			for($index =0; $index< $source_count; $index++) {
				$mount = [ 'mount' => ['action' => 'add'] ];
				foreach($keys as $key) {
					$mount['mount'][substr($key, 7)] = $values[$key][$index];
				}
				sourceCfg($mount);
			}
		}, 'custom_write' => function($values) {
			$dbh = cfgdb_connect();
			$mounts = cfgdb_read('cfg_source', $dbh);
			$stringformat = "source_%s[%d] = \"%s\"\n";
			$source_export = "";
			foreach ($mounts  as $index=>$mp) {
				$source_export =  $source_export . sprintf($stringformat, 'name', $index, $mp['name']);
				$source_export =  $source_export . sprintf($stringformat, 'type', $index, $mp['type']);
				$source_export =  $source_export . sprintf($stringformat, 'address', $index, $mp['address']);
				$source_export =  $source_export . sprintf($stringformat, 'remotedir', $index, $mp['remotedir']);
				$source_export =  $source_export . sprintf($stringformat, 'username', $index, $mp['username']);
				$source_export =  $source_export . sprintf($stringformat, 'password', $index, $mp['password']);
				$source_export =  $source_export . sprintf($stringformat, 'charset', $index, $mp['charset']);
				$source_export =  $source_export . sprintf($stringformat, 'rsize', $index, $mp['rsize']);
				$source_export =  $source_export . sprintf($stringformat, 'wsize', $index, $mp['wsize']);
				$source_export =  $source_export . sprintf($stringformat, 'options', $index, $mp['options']);
				}
			return $source_export;
		}],
	];

	return $configurationHandlers;
}

// Auto-configure settings at worker startup
function autoConfig($cfgfile) {
	autoCfgLog('autocfg: Auto-configure initiated');

	try {
		$autocfg = parse_ini_file($cfgfile, false);

		$available_configs = array_keys($autocfg);

		autoCfgLog('autocfg: Configuration file parsed');
		$configurationHandlers = autoConfigSettings(); // contains supported configuration items

		$section = '';
		foreach ($configurationHandlers as $config) {
			$values = array();

			// Print new section header
			if( is_string($config) ) {
				autoCfgLog('autocfg: - '. $config);
			}
			// Check if all required cfgkeys are present
			elseif( !array_diff_key(array_flip($config['requires']), $autocfg) ) {
				// Copy all key/value sets
				foreach ($config['requires'] as $config_name) {
					$value = $autocfg[$config_name];
					// If config name differs from session var the session_var can be used
					$config_key =  array_key_exists('session_var', $config) ? $config['session_var'] : $config_name;
					$values[$config_key] = $value;
					autoCfgLog('autocfg: '. $config_name.': ' . $value);
					// Remove used autoconfig
					unset($available_configs[$config_name]);
				}
				// Call handler
				if( !array_key_exists('cmd', $config) )
					$config['handler'] ($values);
				else
					$config['handler'] ($values, $config['cmd']);
			}
			// Detect reuires with multiple keys which are no all present in provided configs
			elseif( count($config['requires']) >= 2 and count(array_diff_key(array_flip($config['requires']), $autocfg))>= 1) {
				$incompleteset = " [ ";
				foreach ($config['requires'] as $config_require) {
					$incompleteset = $incompleteset . " ". $config_require;
				}
				$incompleteset = $incompleteset . " ]";
				autoCfgLog('autocfg: Warning incomplete set '. $incompleteset. ' detected.');
			}
		}

		// Check for unused but supplied autocfg settings
		if( empty($available_configs) ) {
			foreach ($available_configs as $config_name) {
				autoCfgLog('autocfg: Warning: '. $config_name.': is unused, incomplete or wrong name.');
			}
		}
	}
	catch (Exception $e) {
		autoCfgLog('autocfg: Caught exception: '.  $e->getMessage() );
	}

 	sysCmd('rm ' . $cfgfile);
	autoCfgLog('autocfg: Configuration file deleted');
	autoCfgLog('autocfg: Auto-configure complete');
}

// Generates an autoconfig file as string based on the current settings
function autoconfigExtract() {
	$autoconfigstring = <<<EOT
	; #########################################
	; Copy this file to /boot/moodecfg.ini
	; It will be processed at startup and the
	; system will automaticly Restart.
	;
	; All param = "value" pairs must be present.
	; Set wlanssid = blank to start AP mode.
	; Example: wlanssid = ""
	;
	; Moode Release : %s
	; Create date	: %s
	;
	; ##########################################
	EOT;

	$autoconfigstring = sprintf($autoconfigstring, getMoodeRel('verbose'), date('Y-m-d H:i:s'));

	$configurationHandlers = autoConfigSettings(); // Contains supported configuration items

	foreach ($configurationHandlers as &$config) {
		$values = array();
		// Print new section header
		if( is_string($config) ) {
			$autoconfigstring = $autoconfigstring . "\n[" . $config. "]\n";
		} else {
			if( !array_key_exists('custom_write', $config)) {
				foreach ($config['requires'] as $config_name) {
					$config_key = array_key_exists('session_var', $config) ? $config['session_var'] : $config_name;
					if( array_key_exists($config_key, $_SESSION) ) {
						$autoconfigstring = $autoconfigstring . $config_key. " = \"" . $_SESSION[$config_key] . "\"\r\n";
					}
				}
			} else {
				$autoconfigstring = $autoconfigstring . $config['custom_write']($config['requires']);
			}
		}
	}

	return $autoconfigstring . "\n";
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
	elseif ($_SESSION['eqfa12p'] != 'Off') {
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
	 // Remove dtoverlays
	sysCmd('sed -i /dtoverlay/d ' . '/boot/config.txt');

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

	// Add these back in
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
		elseif ($_SESSION['eqfa12p'] != 'Off') {
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
	if ($_SESSION['airplaysvc'] == '1') {
		stopSps();
		startSps();
	}

	if ($_SESSION['spotifysvc'] == '1') {
		stopSpotify();
		startSpotify();
	}

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
		$_SESSION['config_back_link'] = '/lib-config.php';
	}
	else if (in_array($section, $root_configs)) {
		$_SESSION['config_back_link'] = '/index.php';
	}
	else if (stripos($_SERVER['HTTP_REFERER'], $section) === false) {
		$_SESSION['config_back_link'] = $referer_link;
	}
	else {
		//workerLog('storeBackLink(): else block');
	}

	session_write_close();
	//workerLog('storeBackLink(): back=' . $_SESSION['config_back_link'] . ', $tpl=' . $tpl . ', $section=' . $section . ', $referer_link=' . $referer_link);
}

// Create enhanced metadata
function enhanceMetadata($current, $sock, $caller = '') {
	define(LOGO_ROOT_DIR, 'imagesw/radio-logos/');
	define(DEF_RADIO_COVER, 'images/default-cover-v6.svg');
	define(DEF_COVER, 'images/default-cover-v6.svg');

	$song = parseCurrentSong($sock);
	$current['file'] = $song['file'];

	// NOTE: Any of these might be '' (empty)
	$current['genre'] = $song['Genre'];
	$current['track'] = $song['Track'];
	$current['date'] = $song['Date'];
	$current['composer'] = $song['Composer'];
	$current['conductor'] = $song['Conductor'];
	$current['performer'] = $song['Performer'];
	$current['albumartist'] = $song['AlbumArtist'];
	$current['artist_count'] = $song['artist_count'];
	// Cover hash and mapped db volume
	if ($caller == 'engine_mpd_php') {
		$current['cover_art_hash'] = getCoverHash($current['file']);
		$current['mapped_db_vol'] = getMappedDbVol();
	}

	if ($current['file'] == null) {
		$current['artist'] = '';
		$current['title'] = '';
		$current['album'] = '';
		$current['coverurl'] = DEF_COVER;
		debugLog('enhanceMetadata(): error: currentsong file is NULL');
	}
	else {
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

		// File extension
		$ext = getFileExt($song['file']);

		// iTunes aac or aiff file
		if (isset($song['Name']) && ($ext == 'm4a' || $ext == 'aif' || $ext == 'aiff')) {
			//workerLog('enhanceMetadata(): AAC or AIFF song file');
			$current['artist'] = isset($song['Artist']) ? $song['Artist'] : 'Unknown artist';
			$current['title'] = $song['Name'];
			$current['album'] = isset($song['Album']) ? $song['Album'] : 'Unknown album';
			$current['coverurl'] = '/coverart.php/' . rawurlencode($song['file']);
		}
		// Radio station
		elseif (substr($song['file'], 0, 4) == 'http' && !isset($current['duration'])) {
			//workerLog('enhanceMetadata(): Radio station');
			$current['artist'] = 'Radio station';
			$current['hidef'] = ($_SESSION[$song['file']]['bitrate'] > 128 || $_SESSION[$song['file']]['format'] == 'FLAC') ? 'yes' : 'no';

			if (!isset($song['Title']) || trim($song['Title']) == '') {
				$current['title'] = 'Streaming source';
			}
			else {
				// Use custom name for certain stations if needed
				// EX: $current['title'] = strpos($song['Title'], 'Radio Active FM') !== false ? $song['file'] : $song['Title'];
				$current['title'] = $song['Title'];
			}

			if (isset($_SESSION[$song['file']])) {
				// Use transmitted name for SOMA FM stations
				$current['album'] = substr($_SESSION[$song['file']]['name'], 0, 4) == 'Soma' ? $song['Name'] : $_SESSION[$song['file']]['name'];
				// Include original station name
				// DEPRECATE $current['station_name'] = $_SESSION[$song['file']]['name'];
				if ($_SESSION[$song['file']]['logo'] == 'local') {
					// Local logo image
					$current['coverurl'] = LOGO_ROOT_DIR . $_SESSION[$song['file']]['name'] . ".jpg";
				}
				else {
					// URL logo image
					$current['coverurl'] = $_SESSION[$song['file']]['logo'];
				}
				# NOTE: Hardcode displayed bitrate for BBC 320K stations since MPD does not seem to pick up the rate since 0.20.10
				if (strpos($_SESSION[$song['file']]['name'], 'BBC') !== false && strpos($_SESSION[$song['file']]['name'], '320K') !== false) {
					$current['bitrate'] = '320 kbps';
				}
			}
			else {
				// Not in radio station table, use transmitted name or 'Unknown'
				$current['album'] = isset($song['Name']) ? $song['Name'] : 'Unknown station';
				// DEPRECATE $current['station_name'] = $current['album'];
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
			// In case 2 url's are returned, use the first
			$current['coverurl'] = explode(',', $current['coverurl'])[0];

			if (substr($song['file'], 0, 4) == 'http') {
				//workerLog('enhanceMetadata(): UPnP url');
			}
			else {
				//workerLog('enhanceMetadata(): Song file');
			}
		}

		// Determine badging
		// NOTE: This is modeled after the code in getEncodedAt()
		if (!(substr($song['file'], 0, 4) == 'http' && !isset($current['duration']))) { // Not a radio station
			sendMpdCmd($sock, 'lsinfo "' . $song['file'] . '"');
			$song_data = parseDelimFile(readMpdResp($sock), ': ');
			$mpd_format_tag = explode(':', $song_data['Format']);
			// Lossy
			if ($ext == 'mp3' || ($mpd_format_tag[1] == 'f' && $mpd_format_tag[2] <= 2)) {
				$current['hidef'] = 'no';
			}
			// DSD
			elseif ($ext == 'dsf' || $ext == 'dff') {
				$current['hidef'] = 'yes';
			}
			// PCM or Multichannel PCM
			else {
				$current['hidef'] = ($mpd_format_tag[1] != 'f' && $mpd_format_tag[1] > ALBUM_BIT_DEPTH_THRESHOLD) || $mpd_format_tag[0] > ALBUM_SAMPLE_RATE_THRESHOLD ? 'yes' : 'no';
			}
		}
	}

	return $current;
}

function getMappedDbVol() {
	session_start();
	$cardnum = $_SESSION['cardnum'];
	$alsa_mixer = '"' . $_SESSION['amixname'] . '"';
	$mpd_mixer = $_SESSION['mpdmixer'];
	session_write_close();
	$result = sysCmd('amixer -c ' . $cardnum . ' sget ' . $alsa_mixer . ' | ' . "awk -F\"[][]\" '/dB/ {print $4; count++; if (count==1) exit}'");
	$mapped_db_vol = explode('.', $result[0])[0];
	return (empty($result[0]) || $mpd_mixer == 'software') ? '' : ($mapped_db_vol < -127 ? -127 : $mapped_db_vol) . 'dB';
}

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

		// file: embedded cover
		if ($search_pri == 'Embedded cover') { // Embedded first
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
				if ($search_pri == 'Cover image file') { // Embedded last
					$hash = getHash($path);
				}
			}

			if ($hash === false) {
				// Nothing found
				$hash = 'getCoverHash(): no cover found';
			}
		}
	}
	else {
		$hash = rand();
	}

	return $hash;
}

// Modified versions of coverart.php functions
// (C) 2015 Andreas Goetz
function rtnHash($mime, $hash) {
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
	if (!file_exists($path)) {
		return false;
	}

	$hash = false;
	$ext = pathinfo($path, PATHINFO_EXTENSION);

	switch (strtolower($ext)) {
		// Image file
		case 'gif':
		case 'jpg':
		case 'jpeg':
		case 'png':
		case 'tif':
		case 'tiff':
			$stat = stat($path);
			$hash = md5(file_get_contents($path, 1024) + $stat['size']);
			break;

		// Embedded images
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
	// Default cover files
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
	// All other image files
	$extensions = array('jpg', 'jpeg', 'png', 'tif', 'tiff');
	$path = str_replace('[', '\[', $path);
	$path = str_replace(']', '\]', $path);
	foreach (glob($path . '*') as $file) {
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
		if (substr($key, 0, 4) == 'http') {
			unset($_SESSION[$key]);
		}
	}
	// Load cfg_radio into session
	$result = cfgdb_read('cfg_radio', cfgdb_connect(), 'all');
	foreach ($result as $row) {
		$_SESSION[$row['station']] = array('name' => $row['name'], 'type' => $row['type'], 'logo' => $row['logo'],
			'bitrate' => $row['bitrate'], 'format' => $row['format']);
	}
}
