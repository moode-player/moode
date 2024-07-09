<?php
/*
 * SPDX-License-Identifier: GPL-3.0-or-later
 * Copyright 2014 The moOde audio player project / Tim Curtis
 * Copyright 2013 The tsunamp player ui / Andrea Coiutti & Simone De Gregori
*/

set_include_path('/var/www/inc');
error_reporting(E_ERROR);

// Time zone
$timeZone = sysCmd("timedatectl | awk -F' ' '/Time zone/{print $3}'")[0];
date_default_timezone_set($timeZone);

// Constants
require_once __DIR__ . '/constants.php';

// Daemon loop sleep intervals
require_once __DIR__ .  '/sleep-interval.php';

// Worker message logger
function workerLog($msg, $mode = 'a') {
	$fh = fopen(MOODE_LOG, $mode);
	fwrite($fh, date('Ymd His ') . $msg . "\n");
	fclose($fh);
}

// Debug message logger
function debugLog($msg, $mode = 'a') {
	if (!isset($_SESSION['debuglog']) || $_SESSION['debuglog'] == '0') {
		return;
	}
	$fh = fopen(MOODE_LOG, $mode);
	fwrite($fh, date('Ymd His ') . $msg . "\n");
	fclose($fh);
}

// Mountmon message logger
function mountmonLog($msg, $mode = 'a') {
	if (!isset($_SESSION['debuglog']) || $_SESSION['debuglog'] == '0') {
		return;
	}
	$fh = fopen(MOUNTMON_LOG, $mode);
	fwrite($fh, date('Ymd His ') . $msg . "\n");
	fclose($fh);
}

// Auto-config message logger
function autoCfgLog($msg, $mode = 'a') {
	$fh = fopen(AUTOCFG_LOG, $mode);
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
function sendFECmd ($cmd) {
	if (false === ($ports = file(PORT_FILE, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES))) {
		// This case is ok and occurs if UI has never been started
		debugLog('sendFECmd(): File open failed, UI has never been opened in Browser');
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
				workerLog('sendFECmd(): Socket create failed');
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

function uiNotify($notify) {
	$script .= "<script>\n";
	$script .= "function ui_notify() {\n";
	$script .= "$.pnotify.defaults.history = false;\n";
	$script .= "$.pnotify({";
	$script .= "title: '" . $notify['title'] . "',";
	$script .= "text: '" . $notify['msg'] . "',";
	$script .= "icon: '',";
	$script .= "delay: " . (isset($notify['duration']) ? strval($notify['duration'] * 1000) : NOTIFY_DURATION_DEFAULT) . ",";
	$script .= "opacity: 1.0});\n";
	$script .= "}\n";
	$script .= "</script>\n";
	echo $script;
}

// Submit job to worker.php
function submitJob($jobName, $jobArgs = '', $title = '', $msg = '', $duration = NOTIFY_DURATION_DEFAULT) {
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
		$_SESSION['notify']['title'] = NOTIFY_TITLE_INFO;
		$_SESSION['notify']['msg'] = 'System is busy, try again';
		return false;
	}
}

// Wait for worker to process job
// NOTE: Called from cfg scripts
function waitWorker($caller) {
	debugLog('waitWorker(): Start ' . $caller . ', w_active=' . $_SESSION['w_active']);
	$loopCnt = 0;

	if ($_SESSION['w_active'] == 1) {
		do {
			usleep(WAITWORKER_SLEEP);
			debugLog('waitWorker(): Wait  ' . ++$loopCnt);

			phpSession('open_ro');
		} while ($_SESSION['w_active'] != 0);
	}

	debugLog('waitWorker(): End   ' . $caller . ', w_active=' . $_SESSION['w_active']);
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
	return 'moode' . getMoodeSeries() . $result[0]['value'];
}

// Get release
function getMoodeRel($options = '') {
	if ($options === 'verbose') {
		// Verbose: 'major.minor.patch yyyy-mm-dd'
		$result = sysCmd("moodeutl --mooderel | tr -d '\n'");
		return $result[0];
	} else {
		// Compact: 'rNNN'
		$result = sysCmd("moodeutl --mooderel | tr -d '\n'");
		$str = 'r' . str_replace('.', '', explode(' ', $result[0])[0]);
		return $str;
	}
}

// Get major version (series) S in 'rSNN'
function getMoodeSeries() {
	return substr(getMoodeRel(), 1, 1);
}

// Store back link for configs
function storeBackLink($section, $tpl) {
	$refererLink = substr($_SERVER['HTTP_REFERER'], strrpos($_SERVER['HTTP_REFERER'], '/'));
	//workerLog('storeBackLink(): refererLink=' . substr($_SERVER['HTTP_REFERER'], strrpos($_SERVER['HTTP_REFERER'], '/')));

	$rootConfigs = array('lib-config', 'snd-config', 'net-config', 'sys-config', 'ren-config', 'per-config');
	$tplConfigs = array(
		'apl-config.html'	=> '/ren-config.php',
		'bkp-config.html'	=> '/sys-config.php',
		'cdsp-config.html' 	=> '/snd-config.php',
		'cdsp-configeditor.html' => '/cdsp-config.php',
		'eqg-config.html'	=> '/snd-config.php',
		'eqp-config.html'	=> '/snd-config.php',
		'gpio-config.html'	=> '/per-config.php',
		'spo-config.html' 	=> '/ren-config.php',
		'sqe-config.html'	=> '/ren-config.php',
		'src-config.html'	=> '/lib-config.php',
		'sys-status.html'	=> '/sys-config.php',
		'upp-config.html' 	=> '/ren-config.php'
	);

	phpSession('open');

	if (array_key_exists($tpl, $tplConfigs)) {
		$_SESSION['config_back_link'] = $tplConfigs[$tpl];
	} else if ($tpl == 'blu-control.html' && $refererLink == '/ren-config.php') {
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

// Assumes only one dir under /home, the one corresponding to the userid
// entered into the Raspberry Pi Imager when prepping the image.
function getUserID() {
	// Check for and delete '/home/pi' if it has no userid. This dir is created
	// by the moode-player package install during in-place update.
	if (file_exists('/home/pi/') && empty(sysCmd('grep "pi" /etc/passwd'))) {
		sysCmd('rm -rf /home/pi/');
	}

	$result = sysCmd('ls /home/');
	return $result[0];
}

// NOTE:
// hostname -I = 192.168.1.121 fd87:f129:9943:4934:1192:907d:d9b6:e98d
// hostname -I | cut -d " " -f 1 = 192.168.1.121
function getThisIpAddr() {
	return sysCmd('hostname -I | cut -d " " -f 1')[0];
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

function chkBootConfigTxt() {
	$lines = file(BOOT_CONFIG_TXT, FILE_IGNORE_NEW_LINES);
	$headerCount = 0;
	if (str_contains($lines[1], CFG_MAIN_FILE_HEADER)) {
		// Line 1 has the correct header
		$headerCount++;

		// Check for rest of headers
		foreach ($lines as $line) {
			//workerLog($line);
			if ($line == CFG_DEVICE_FILTERS_HEADER) {
				$headerCount++;
			}
			if ($line == CFG_GENERAL_SETTINGS_HEADER) {
				$headerCount++;
			}
			if ($line == CFG_DO_NOT_ALTER_HEADER) {
				$headerCount++;
			}
			if ($line == CFG_AUDIO_OVERLAYS_HEADER) {
				$headerCount++;
			}
		}

		// Restore default config if needed
		if ($headerCount == CFG_HEADERS_REQUIRED) {
			$status = 'Required headers present';
		} else {
			$status = 'Required header missing';
		}
	} else {
		$status = 'Main header missing';
	}

	return $status;
}
function updBootConfigTxt($action, $value) {
	switch ($action) {
		case 'upd_audio_overlay':
			// $value: #dtoverlay=none or dtoverlay=actual_overlay
			// Remove the line after CFG_AUDIO_OVERLAYS_HEADER which would be a dtoverlay=audio_overlay line
			sysCmd('sed -i "/' . CFG_AUDIO_OVERLAYS_HEADER . '/{n;d}" ' . BOOT_CONFIG_TXT);
			// Replace the section header and include the audio overlay below it
			sysCmd('sed -i s"/' .
				CFG_AUDIO_OVERLAYS_HEADER . '/' .
				CFG_AUDIO_OVERLAYS_HEADER . '\n' . $value . '/" ' . BOOT_CONFIG_TXT);
			break;
		case 'upd_force_eeprom_read':
			// $value: '#' or ''
			sysCmd('sed -i /' . CFG_FORCE_EEPROM_READ . "/c\\" . $value . 'dtoverlay=' . CFG_FORCE_EEPROM_READ . ' ' . BOOT_CONFIG_TXT);
			break;
		case 'upd_rotate_screen':
			// $value: '0' or '180'
			if ($value == '0') {
				sysCmd('sed -i /' . CFG_PITOUCH_INVERTXY . "/c\\" . '#' . 'dtoverlay=' . CFG_PITOUCH_INVERTXY . ' ' . BOOT_CONFIG_TXT);
				sysCmd('sed -i /' . CFG_DISPLAY_AUTODETECT . "/c\\" . '' . CFG_DISPLAY_AUTODETECT . ' ' . BOOT_CONFIG_TXT);
				sysCmd('sed -i "s/ ' . CFG_PITOUCH_ROTATE_180 . '//"' . ' ' . BOOT_CMDLINE_TXT);
			} else {
				sysCmd('sed -i /' . CFG_PITOUCH_INVERTXY . "/c\\" . '' . 'dtoverlay=' . CFG_PITOUCH_INVERTXY . ' ' . BOOT_CONFIG_TXT);
				sysCmd('sed -i /' . CFG_DISPLAY_AUTODETECT . "/c\\" . '#' . CFG_DISPLAY_AUTODETECT . ' ' . BOOT_CONFIG_TXT);
				sysCmd('sed -i "s/$/ ' . CFG_PITOUCH_ROTATE_180 . '/"' . ' ' . BOOT_CMDLINE_TXT);
			}
			break;
		case 'upd_hdmi_enable_4kp60':
			// $value: '0' or '1'
			sysCmd('sed -i s"/' .
				CFG_HDMI_ENABLE_4KP60 . '=.*/' .
				CFG_HDMI_ENABLE_4KP60 . '=' . $value . '/" ' . BOOT_CONFIG_TXT);
			break;
		case 'upd_pi_audio_driver':
			// $value: '#' or ''
			sysCmd('sed -i /' . CFG_PI_AUDIO_DRIVER . "/c\\" . $value . 'dtoverlay=' . CFG_PI_AUDIO_DRIVER . ' ' . BOOT_CONFIG_TXT);
			break;
		case 'upd_pci_express':
			// $value: 'off' or 'gen2' or 'gen3'
			$prefix1 = $value == 'off' ? '#' : '';
			$prefix2 = $value == 'off' ? '#' : ($value == 'gen2' ? '#' : '');
			sysCmd('sed -i /' . CFG_PCI_EXPRESS . "$/c\\" . $prefix1 . 'dtparam=' . CFG_PCI_EXPRESS . ' ' . BOOT_CONFIG_TXT);
			sysCmd('sed -i /' . CFG_PCI_EXPRESS_GEN3 . "/c\\" . $prefix2 . 'dtparam=' . CFG_PCI_EXPRESS_GEN3 . ' ' . BOOT_CONFIG_TXT);
			break;
		case 'upd_disable_bt':
			// $value: '#' or ''
			sysCmd('sed -i /' . CFG_DISABLE_BT . "/c\\" . $value . 'dtoverlay=' . CFG_DISABLE_BT . ' ' . BOOT_CONFIG_TXT);
			break;
		case 'upd_disable_wifi':
			// $value: '#' or ''
			sysCmd('sed -i /' . CFG_DISABLE_WIFI . "/c\\" . $value . 'dtoverlay=' . CFG_DISABLE_WIFI . ' ' . BOOT_CONFIG_TXT);
			break;
	}
}
