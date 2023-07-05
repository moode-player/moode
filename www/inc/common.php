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

set_include_path('/var/www/inc');
error_reporting(E_ERROR);

// PHP 8 equivalent funcrtions are used in the CUE support functions in this file
require_once __DIR__ . '/php8-equiv.php';
// Alsa functions getAlsaHwParams() and getAlsaCardNum() are by waitWorker() in this file
require_once __DIR__ . '/alsa.php';

// Common
const MPD_RESPONSE_ERR = 'ACK';
const MPD_RESPONSE_OK = 'OK';
const MPD_MUSICROOT = '/var/lib/mpd/music/';
const MPD_PLAYLIST_ROOT = '/var/lib/mpd/playlists/';
const PLAYLIST_COVERS_ROOT = '/var/local/www/imagesw/playlist-covers/';
const RADIO_LOGOS_ROOT = '/var/local/www/imagesw/radio-logos/';
const TMP_IMAGE_PREFIX = '__tmp__';
const SQLDB = 'sqlite:/var/local/www/db/moode-sqlite3.db';
const SQLDB_PATH = '/var/local/www/db/moode-sqlite3.db';
const MOODE_LOG = '/var/log/moode.log';
const AUTOCFG_LOG = '/var/log/moode_autocfg.log';
const UPDATER_LOG = '/var/log/moode_update.log';
const PLAY_HISTORY_LOG = '/var/log/moode_playhistory.log';
const MOUNTMON_LOG = '/var/log/moode_mountmon.log';
const MPD_LOG = '/var/log/mpd/log';
const PORT_FILE = '/tmp/moode_portfile';
const THMCACHE_DIR = '/var/local/www/imagesw/thmcache/';
const LIBCACHE_BASE = '/var/local/www/libcache';
const ALSA_PLUGIN_PATH = '/etc/alsa/conf.d';
const SESSION_SAVE_PATH = '/var/local/php';
const STATION_EXPORT_DIR = '/var/local/www/imagesw';
const MPD_VERSIONS_CONF = '/var/local/www/mpd_versions.conf';
const LOGO_ROOT_DIR = 'imagesw/radio-logos/';
const DEF_RADIO_COVER = 'images/default-cover-v6.svg';
const DEF_COVER = 'images/default-cover-v6.svg';
const DEV_ROOTFS_SIZE = 3670016000; // Bytes (3.5GB)
const LOW_DISKSPACE_LIMIT = 524288; // Bytes (512MB)
const ROOT_DIRECTORIES = array('NAS', 'SDCARD', 'USB');
const BOOT_CONFIG_TXT = '/boot/config.txt';
const BOOT_CONFIG_BKP = '/boot/bootcfg.bkp';

// Size and quality factor for small thumbs
// Used in thumb-gen.php, worker.php
const THM_SM_W = 80;
const THM_SM_H = 80;
const THM_SM_Q = 75;

// Features availability bitmask
// NOTE: Updates must also be made to matching code blocks in playerlib.js, sysinfo.sh, moodeutl, and footer.php
// sqlite3 /var/local/www/db/moode-sqlite3.db "SELECT value FROM cfg_system WHERE param='feat_bitmask'"
// sqlite3 /var/local/www/db/moode-sqlite3.db "UPDATE cfg_system SET value='97206' WHERE param='feat_bitmask'"
const FEAT_HTTPS		= 1;		//   HTTPS-Only mode
const FEAT_AIRPLAY		= 2;		// y AirPlay renderer
const FEAT_MINIDLNA 	= 4;		// y DLNA server
const FEAT_RECORDER		= 8; 		//   Stream recorder
const FEAT_SQUEEZELITE	= 16;		// y Squeezelite renderer
const FEAT_UPMPDCLI 	= 32;		// y UPnP client for MPD
const FEAT_SQSHCHK		= 64;		// 	 Require squashfs for software update
const FEAT_ROONBRIDGE	= 128;		// y RoonBridge renderer
const FEAT_LOCALUI		= 256;		// y Local display
const FEAT_INPSOURCE	= 512;		// y Input source select
const FEAT_UPNPSYNC 	= 1024;		//   UPnP volume sync
const FEAT_SPOTIFY		= 2048;		// y Spotify Connect renderer
const FEAT_GPIO 		= 4096;		// y GPIO button handler
const FEAT_RESERVED		= 8192;		// y Reserved for future use
const FEAT_BLUETOOTH	= 16384;	// y Bluetooth renderer
const FEAT_DEVTWEAKS	= 32768;	//   Developer tweaks
const FEAT_MULTIROOM	= 65536;	// y Multiroom audio
//						-------
//						  97206

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

// MPD output names
const ALSA_DEFAULT			= 'ALSA Default';
const ALSA_BLUETOOTH		= 'ALSA Bluetooth';
const HTTP_SERVER			= 'HTTP Server';
const STREAM_RECORDER		= 'Stream Recorder';

// Recorder plugin (currently not available)
const RECORDER_RECORDINGS_DIR 	 = '/Recordings';
const RECORDER_DEFAULT_COVER	 = 'Recorded Radio.jpg';
const RECORDER_DEFAULT_ALBUM_TAG = 'Recorded YYYY-MM-DD';

// Daemon loop sleep intervals
include '/var/www/inc/sleep-interval.php';

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
	// Logging off
	if (!isset($_SESSION['debuglog']) || $_SESSION['debuglog'] == '0') {
		return;
	}

	$fh = fopen(MOODE_LOG, $mode);
	fwrite($fh, date('Ymd His ') . $msg . "\n");
	fclose($fh);
}

// Mountmon message logger
function mountmonLog($msg, $mode = 'a') {
	// Logging off
	if (!isset($_SESSION['debuglog']) || $_SESSION['debuglog'] == '0') {
		return;
	}

	$fh = fopen(MOUNTMON_LOG, $mode);
	fwrite($fh, date('Ymd His ') . $msg . "\n");
	fclose($fh);
}

// Execute shell command
function sysCmd($cmd) {
	exec('sudo ' . $cmd . " 2>&1", $output);
	return $output;
}

// Used in template scripts
// eval("echoTemplate(\"" . php ev("templates/$tpl") . "\");");
function getTemplate($template) {
	return str_replace("\"", "\\\"", implode("", file($template)));
}
function echoTemplate($template) {
	echo $template;
}

// Send command to front-end via engine-cmd.php
function sendEngCmd ($cmd) {
	if (false === ($ports = file(PORT_FILE, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES))) {
		// This case is ok and occurs if UI has never been started
		debugLog('sendEngCmd(): File open failed, UI has never been opened in Browser');
	} else {
		// Retry until UI connects or retry limit reached
		$retry_limit = 4;
		$retry_count = 0;
		while (count($ports) === 0) {
			++$retry_count;
			$ports = file(PORT_FILE, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
			sleep (1);
			if (--$retry_limit == 0) {
				break;
			}
		}

		foreach ($ports as $port) {
			if (false !== ($sock = socket_create(AF_INET, SOCK_STREAM, SOL_TCP))) {
				if (false !== ($result = socket_connect($sock, '127.0.0.1', $port))) {
					sockWrite($sock, $cmd);
					socket_close($sock);
				} else {
					sysCmd('sed -i /' . $port . '/d ' . PORT_FILE);
				}
			} else {
				workerLog('sendEngCmd(): Socket create failed');
			}
		}
	}
}

function sockWrite($sock, $msg) {
    $length = strlen($msg);
	$retryCount = 4;
	for ($i = 0; $i < $retryCount; $i++) {
        if (false === ($sent = socket_write($sock, $msg, $length))) {
			workerLog('sockWrite(): Socket write failed (' . $i . ')');
            return false;
        }

        if ($sent < $length) {
            $msg = substr($msg, $sent);
            $length -= $sent;
        } else {
            return true;
        }
    }
	// We only get here if $i = $retryCount
	workerLog('sockWrite(): Socket write failed after ' . $retryCount . ' tries');
    return false;
}

// CUE support
function isCueTrack($path) {
	return str_contains($path, '.cue/track');
}
function ensureAudioFile($path) {
	$normalized = false;
	$track = 'ANY';

	if (isCueTrack($path)) { // e.g. "/a/path/to/a/cue/filename.cue/track0001"
		$track = (int)str_replace('track', '', pathinfo($path, PATHINFO_BASENAME)); // e.g. "0001"
		$path = pathinfo($path, PATHINFO_DIRNAME); // e.g. "/a/path/to/a/cue/filename.cue"
	}

	if ('cue' == strtolower(pathinfo($path, PATHINFO_EXTENSION))) {
		if (!str_starts_with($path, MPD_MUSICROOT)) { // If not included, add the absolute mpd music path
			$path = MPD_MUSICROOT . $path;
			$normalized = true;
		}

		if (file_exists($path)) {
			$lastFile = '';
			$cueSheetLines = file($path);
			$totLines = count($cueSheetLines);
			$lineNdx = 0;

			while ($lineNdx < $totLines) { // Searching FILE "<filename.ext>" WAVE
				$line = trim($cueSheetLines[$lineNdx]);
				if (str_starts_with($line, 'FILE ') &&  str_ends_with($line, ' WAVE')) {
					$lastFile = pathinfo($path, PATHINFO_DIRNAME) . '/' . str_replace('"', '', str_replace('FILE ', '', str_replace(' WAVE', '', $line)));
				} else if (str_starts_with($line, 'TRACK ')) { // Searching TRACK xx AUDIO
					$trackdata = explode(' ', $line, 3);
					$tracknumber = (int)$trackdata[1];

					if (('ANY' == $track) || ($track == $tracknumber)) {
						$path = $lastFile;
						$lineNdx = $totLines;
					}
				}
				$lineNdx++;
			}
		}

		if ($normalized && str_starts_with($path, MPD_MUSICROOT)) {
			$path = str_replace(MPD_MUSICROOT, '', $path);  // If added by us, remove the absolute mpd music path
		}
	}

	return $path;
}

function getFileExt($file) {
	if (isCueTrack($file)) { // If this is a cue track index, strip it from the file in order to be able to get its extension...
		$file = pathinfo($file, PATHINFO_DIRNAME);
	}
	return substr($file, 0, 4) == 'http' ? '' : strtolower(pathinfo($file, PATHINFO_EXTENSION));
}

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

function formatSongTime($sec) {
	$mins = sprintf('%02d', floor($sec / 60));
	$secs = sprintf(':%02d', (int) $sec % 60);
	return $mins . $secs;
}

function formatRate ($rate) {
	$rates = array('*' => '*', '32000' => '32', '48000' => '48', '96000' => '96', '192000' => '192', '384000' => '384', '768000' => '768',
	'22050' => '22.05', '44100' => '44.1', '88200' => '88.2', '176400' => '176.4', '352800' => '352.8', '705600' => '705.6',
	'dsd64' => 'dsd64', 'dsd128' => 'dsd128', 'dsd256' => 'dsd256', 'dsd512' => 'dsd512', 'dsd1024' => 'dsd1024',
	'2822400' => '2.822', '5644800' => '5.644', '11289600' => '11.288', '22579200' => '22.576', 45158400 => 45.152);
	return $rates[$rate];
}

function formatChannels($channels) {
	if ($channels == '1') {
	 	$str = 'Mono';
	} else if ($channels == '2' || $channels == '*') {
	 	$str = 'Stereo';
	} else if ($channels > 2) {
	 	$str = 'Multichannel';
	} else {
		$str = 'Undefined';
	}
 	return $str;
}

function uiNotify($notify) {
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
	} else {
		$script .= "delay: '3000',";
	}
	$script .= "opacity: 1.0});\n";
	$script .= "}\n";
	$script .= "</script>\n";

	echo $script;
}

// Submit job to worker.php
function submitJob($jobName, $jobArgs = '', $title = '', $msg = '', $duration = 3) {
	if ($_SESSION['w_lock'] != 1 && $_SESSION['w_queue'] == '') {
		if (phpSession('get_status') != PHP_SESSION_ACTIVE) {
			phpSession('open');
		}
		// For worker.php
		$_SESSION['w_queue'] = $jobName;
		$_SESSION['w_active'] = 1;
		$_SESSION['w_queueargs'] = $jobArgs;

		// For footer.php
		$_SESSION['notify']['title'] = $title;
		$_SESSION['notify']['msg'] = $msg;
		$_SESSION['notify']['duration'] = $duration;

		// NOTE: Session will be closed by caller script

		return true;
	} else {
		//echo json_encode('worker busy');
		$_SESSION['notify']['title'] = 'System is busy';
		$_SESSION['notify']['msg'] = 'Try again';
		return false;
	}
}

// Wait for worker to process job
// NOTE: Called from cfg scripts
function waitWorker($caller) {
	debugLog('waitWorker(): Start (' . $caller . ', w_active=' . $_SESSION['w_active'] . ')');
	$loopCnt = 0;

	if ($_SESSION['w_active'] == 1) {
		do {
			usleep(WAITWORKER_SLEEP);
			debugLog('waitWorker(): Wait  (' . ++$loopCnt . ')');

			phpSession('open_ro');
		} while ($_SESSION['w_active'] != 0);
	}

	debugLog('waitWorker(): End   (' . $caller . ', w_active=' . $_SESSION['w_active'] . ')');
}

// $path
// - Remote: cfg_system 'res_software_upd_url'
// - Local:  /var/local/www/
function checkForUpd($path) {
	if (false === ($contents = file_get_contents($path . 'update-' . getPkgId() . '.txt'))) {
		$result['Date'] = 'None';
	} else {
		$result = parseDelimFile($contents, ': ');
	}

	return $result;
}

// Return the update package id (default 'moode') plus an optional suffix for testing
function getPkgId () {
	$result = sqlQuery("SELECT value FROM cfg_system WHERE param='pkgid_suffix'", sqlConnect());
	return 'moode' . $result[0]['value'];
}

// Get moode release
function getMoodeRel($options = '') {
	if ($options === 'verbose') {
		// Verbose: major.minor.patch yyyy-mm-dd
		$result = sysCmd("moodeutl --mooderel | tr -d '\n'");
		return $result[0];
	} else {
		// Compact: rNNN
		$result = sysCmd("moodeutl --mooderel | tr -d '\n'");
		$str = 'r' . str_replace('.', '', explode(' ', $result[0])[0]);
		return $str;
	}
}

// Store back link for configs
function storeBackLink($section, $tpl) {
	$refererLink = substr($_SERVER['HTTP_REFERER'], strrpos($_SERVER['HTTP_REFERER'], '/'));
	//workerLog('storeBackLink(): refererLink=' . substr($_SERVER['HTTP_REFERER'], strrpos($_SERVER['HTTP_REFERER'], '/')));

	$rootConfigs = array('lib-config', 'snd-config', 'net-config', 'sys-config', 'ren-config');
	$tplConfigs = array(
		'apl-config.html'	=> '/ren-config.php',
		'bkp-config.html'	=> '/sys-config.php',
		'cdsp-config.html' 	=> '/snd-config.php',
		'cdsp-configeditor.html' => '/cdsp-config.php',
		'eqg-config.html'	=> '/snd-config.php',
		'eqp-config.html'	=> '/snd-config.php',
		'gpio-config.html'	=> '/sys-config.php',
		'spo-config.html' 	=> '/ren-config.php',
		'sqe-config.html'	=> '/ren-config.php',
		'src-config.html'	=> '/lib-config.php',
		'sys-status.html'	=> '/sys-config.php',
		'upp-config.html' 	=> '/ren-config.php'
	);

	phpSession('open');

	if (array_key_exists($tpl, $tplConfigs)) {
		$_SESSION['config_back_link'] = $tplConfigs[$tpl];
	} else if ($tpl == 'blu-config.html' && $refererLink == '/ren-config.php') {
		$_SESSION['config_back_link'] = '/ren-config.php#bluetooth';
	} else if ($tpl == 'mpd-config.html' && $refererLink == '/snd-config.php') {
		$_SESSION['config_back_link'] = '/snd-config.php#mpd-options';
	} else if ($tpl == 'trx-config.html' && $refererLink == '/snd-config.php') {
		$_SESSION['config_back_link'] = '/snd-config.php#alsa-options';
	} else if ($tpl == 'cdsp-config.html') {
		$_SESSION['config_back_link'] = $_SESSION['alt_back_link'];
	} else if (in_array($section, $rootConfigs)) {
		$_SESSION['config_back_link'] = '/index.php';
	} else if (stripos($_SERVER['HTTP_REFERER'], $section) === false) {
		$_SESSION['config_back_link'] = $refererLink;
	} else {
		//workerLog('storeBackLink(): else block');
	}

	phpSession('close');
}

// Used for 2 levels back: cdsp-configeditor -> cdsp-config -> /index.php
function setAltBackLink() {
	phpSession('open');

	$refererLink = substr($_SERVER['HTTP_REFERER'], strrpos($_SERVER['HTTP_REFERER'], '/'));

	// NOTE: $_SESSION['alt_back_link'] is reset to '' in /index.php
	if (empty($_SESSION['alt_back_link'])) {
		if ($refererLink == '/index.php' || $refererLink == '/') {
			$_SESSION['alt_back_link'] = '/index.php';
		} else {
			$_SESSION['alt_back_link'] = '/snd-config.php#equalizers';
		}
	}

	phpSession('close');
}

function getUserID() {
	$result = sysCmd('ls /home/');
	return $result[0];
}
