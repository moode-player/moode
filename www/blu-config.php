<?php
/*
 * SPDX-License-Identifier: GPL-3.0-or-later
 * Copyright 2014 The moOde audio player project / Tim Curtis
*/

require_once __DIR__ . '/inc/common.php';
require_once __DIR__ . '/inc/alsa.php';
require_once __DIR__ . '/inc/audio.php';
require_once __DIR__ . '/inc/mpd.php';
require_once __DIR__ . '/inc/renderer.php';
require_once __DIR__ . '/inc/session.php';
require_once __DIR__ . '/inc/cdsp.php';

phpSession('open');

chkVariables($_POST);

// Controller commands
if (isset($_POST['run_btcmd']) && $_POST['run_btcmd'] == '1') {
	$cmd = $_POST['btcmd'];
	sleep(1);

	if ($cmd == '-R') {
		$result = sysCmd('/var/www/util/blu-control.sh ' . $cmd);
		sleep(1);
		$cmd = '-p';
	} else if ($cmd == '-D') {
		$result = sysCmd('/var/www/util/blu-control.sh ' . $cmd);
		sleep(1);
		$cmd = '-c';
	}
} else {
	if ($_SESSION['btsvc'] == '1') {
		$cmd = ($_SESSION['btactive'] == '1' || $_SESSION['audioout'] == 'Bluetooth') ? '-c' : '-p';
	} else {
		$cmd = '-H';
	}
}
// Pair with device
if (isset($_POST['pairwith_device']) && $_POST['pairwith_device'] == '1') {
	sysCmd('/var/www/util/blu-control.sh -P ' . '"' . $_POST['scanned_device'] . '"');
	$cmd = '-p';
	sleep(1);
}
// Remove pairing
if (isset($_POST['rm_paired_device']) && $_POST['rm_paired_device'] == '1') {
	sysCmd('/var/www/util/blu-control.sh -r ' . '"' . $_POST['paired_device'] . '"');
	$cmd = '-p';
	sleep(1);
}

function updateBluetoothSpeakerMACAddress($deviceMac) {
	sysCmd("sed -i '/device/c\device \"" . $deviceMac . "\"' " . ALSA_PLUGIN_PATH . '/btstream.conf');
	phpSession('write', 'used_bt_speaker', $deviceMac);
}

// Connect to device
if (isset($_POST['connectto_device']) && $_POST['connectto_device'] == '1') {
	if ($_POST['audioout'] == 'Bluetooth') {
		updateBluetoothSpeakerMACAddress($_POST['paired_device']);
	}
	phpSession('write', 'audioout', $_POST['audioout']);
	sysCmd('/var/www/util/blu-control.sh -C ' . '"' . $_POST['paired_device'] . '"');
	$cmd = '-c';
	sleep(1);
	setAudioOut($_POST['audioout']);
}

// Change audio routing
if (isset($_POST['change_audioout_bt']) && $_POST['audioout'] != $_SESSION['audioout']) {
	if ($_POST['audioout'] == 'Bluetooth' && (isset($_POST['paired_device']) || isset($_POST['connected_device']))) {
		// Change to Bluetooth out, update MAC address
		$device = isset($_POST['paired_device']) ? $_POST['paired_device'] : $_POST['connected_device'];
		updateBluetoothSpeakerMACAddress($device);

		phpSession('write', 'audioout', $_POST['audioout']);
		setAudioOut($_POST['audioout']);
	} else {
		// Change to local out, disconnect device
		phpSession('write', 'audioout', $_POST['audioout']);
		setAudioOut($_POST['audioout']);
		if (isset($_POST['connected_device'])) {
			sysCmd('/var/www/util/blu-control.sh -d ' . '"' . $_POST['connected_device'] . '"');
		}
		$cmd = '-p';
		sleep(1);
	}
}

// Disconnect device
if (isset($_POST['disconnect_device']) && $_POST['disconnect_device'] == '1') {
	$audioout = $_SESSION['audioout'] == 'Bluetooth' ? 'Local' : $_SESSION['audioout'];
	phpSession('write', 'audioout', $audioout);
	setAudioOut($audioout);
	sysCmd('/var/www/util/blu-control.sh -d ' . '"' . $_POST['connected_device'] . '"');
	$cmd = '-p';
	sleep(1);
}

// I-O buffer time
if (isset($_POST['update_pcm_buffer']) && $_POST['update_pcm_buffer'] == '1') {
	phpSession('write', 'bluez_pcm_buffer', $_POST['pcm_buffer']);
	sysCmd("sed -i '/BUFFERTIME/c\BUFFERTIME=" . $_POST['pcm_buffer'] . "' /etc/bluealsaaplay.conf");
}

// SBC encoder mode
if (isset($_POST['update_sbc_quality']) && $_POST['update_sbc_quality'] == '1') {
	$_SESSION['bluez_sbc_quality'] = $_POST['sbc_quality'];
	sysCmd("sed -i 's/--sbc-quality.*/--sbc-quality=" . $_POST['sbc_quality'] . "/' /etc/systemd/system/bluealsa.service");
	sysCmd('systemctl daemon-reload');
}

// ALSA output mode
if (isset($_POST['update_alsa_output_mode_bt']) && $_POST['update_alsa_output_mode_bt'] == '1') {
	// Either _audioout (Standard) or plughw (Compatibility)
	$_SESSION['alsa_output_mode_bt'] = $_POST['alsa_output_mode_bt'];
	if ($_POST['alsa_output_mode_bt'] == 'plughw') {
		$alsaDevice = $_SESSION['alsa_output_mode'] == 'iec958' ? getAlsaIEC958Device() : 'plughw' . ':' . $_SESSION['cardnum'] . ',0';
	} else {
		$alsaDevice = $_POST['alsa_output_mode_bt']; // _audioout
	}
	sysCmd("sed -i '/AUDIODEV/c\AUDIODEV=" . $alsaDevice . "' /etc/bluealsaaplay.conf");
}

phpSession('close');

// Command list
$_cmd['btcmd'] .= "<option value=\"-s\" " . (($cmd == '-s') ? "selected" : "") . ">SCAN (Standard)</option>\n";
$_cmd['btcmd'] .= "<option value=\"-S\" " . (($cmd == '-S') ? "selected" : "") . ">SCAN (Plus LE devices)</option>\n";
$_cmd['btcmd'] .= "<option value=\"-p\" " . (($cmd == '-p') ? "selected" : "") . ">LIST paired</option>\n";
$_cmd['btcmd'] .= "<option value=\"-c\" " . (($cmd == '-c') ? "selected" : "") . ">LIST connected</option>\n";
$_cmd['btcmd'] .= "<option value=\"-l\" " . (($cmd == '-l') ? "selected" : "") . ">LIST trusted</option>\n";
$_cmd['btcmd'] .= "<option value=\"-D\" " . (($cmd == '-D') ? "selected" : "") . ">DISCONNECT all</option>\n";
$_cmd['btcmd'] .= "<option value=\"-R\" " . (($cmd == '-R') ? "selected" : "") . ">REMOVE all devices</option>\n";
$_cmd['btcmd'] .= "<option value=\"-H\" " . (($cmd == '-H') ? "selected" : "") . ">HELP</option>\n";

// Initial control states
$_hide_ctl['paired_device'] = 'hide';
$_hide_ctl['connected_device'] = 'hide';
$_hide_ctl['scanned_device'] = 'hide';
$_ctl_disabled = $_SESSION['btsvc'] == '1' ? '' : 'disabled';

// Run the cmd
$result = sysCmd('/var/www/util/blu-control.sh ' . $cmd);

// Format output for HTML
if ($cmd == '-H') {
	$_cmd_output = 'Turn Bluetooth on in Renderers then select a command to submit to the controller<br>';
} else {
	for ($i = 2; $i < count($result); $i++) {
		if ($result[$i] != '**') {
			if (stripos($result[$i], 'Trust expires') !== false) {
				$_cmd_output .= $result[$i] . '<br>**<br>';
			} else {
				$_cmd_output .= '** ' . substr($result[$i], 21) . '<br>';
			}
		}
	}
}
$_cmd_output = empty($_cmd_output) ? 'No devices' : $_cmd_output;

// Audio output
$_select['audioout'] .= "<option value=\"Local\" " . (($_SESSION['audioout'] == 'Local') ? "selected" : "") . ">Local audio</option>\n";
$_select['audioout'] .= "<option value=\"Bluetooth\" " . (($_SESSION['audioout'] == 'Bluetooth') ? "selected" : "") . ">Bluetooth speaker</option>\n";

// Provide a select for removing, disconnecting, pairing or connecting a device
$cmd_array = array('-p', '-c', '-l', '-s', '-S');
if (in_array($cmd, $cmd_array)) {
	switch ($cmd) {
		case '-p':
			$type = 'paired_device';
			break;
		case '-c':
			$type = 'connected_device';
			break;
		case '-l':
		case '-s':
		case '-S':
			$type = 'scanned_device';
			break;
	}

	for ($i = 0; $i < count($result); $i++) {
		$token = explode(' ', $result[$i], 3);
		if (strpos($token[1], ':') !== false) {
			$_device[$type] .= "<option value=\"" . $token[1] . "\">" . $token[2] . "</option>\n";
		}
	}
	// Hide/unhide controls
	$_hide_ctl[$type] = empty($_device[$type]) ? 'hide' : '';
}

// PCM I-O buffer time (micro seconds)
$_select['pcm_buffer'] .= "<option value=\"500000\" " . (($_SESSION['bluez_pcm_buffer'] == '500000') ? "selected" : "") . ">500 ms (Default)</option>\n";
$_select['pcm_buffer'] .= "<option value=\"250000\" " . (($_SESSION['bluez_pcm_buffer'] == '250000') ? "selected" : "") . ">250 ms</option>\n";
$_select['pcm_buffer'] .= "<option value=\"125000\" " . (($_SESSION['bluez_pcm_buffer'] == '125000') ? "selected" : "") . ">125 ms</option>\n";
$_select['pcm_buffer'] .= "<option value=\"60000\" "  . (($_SESSION['bluez_pcm_buffer'] == '60000') ? "selected" : "")  . "> 60 ms</option>\n";
$_select['pcm_buffer'] .= "<option value=\"40000\" "  . (($_SESSION['bluez_pcm_buffer'] == '40000') ? "selected" : "")  . "> 40 ms</option>\n";
$_select['pcm_buffer'] .= "<option value=\"20000\" "  . (($_SESSION['bluez_pcm_buffer'] == '20000') ? "selected" : "")  . "> 20 ms</option>\n";

// SBC quality
$_select['sbc_quality'] .= "<option value=\"low\" " . (($_SESSION['bluez_sbc_quality'] == 'low') ? "selected" : "") . ">Low (213 kbps)</option>\n";
$_select['sbc_quality'] .= "<option value=\"medium\" " . (($_SESSION['bluez_sbc_quality'] == 'medium') ? "selected" : "") . ">Medium (237 kbps)</option>\n";
$_select['sbc_quality'] .= "<option value=\"high\" " . (($_SESSION['bluez_sbc_quality'] == 'high') ? "selected" : "") . ">High (345 kbps)</option>\n";
$_select['sbc_quality'] .= "<option value=\"xq\" " . (($_SESSION['bluez_sbc_quality'] == 'xq') ? "selected" : "") . ">XQ (452 kbps)</option>\n";
$_select['sbc_quality'] .= "<option value=\"xq+\" " . (($_SESSION['bluez_sbc_quality'] == 'xq+') ? "selected" : "") . ">XQ+ (551 kbps)</option>\n";

// ALSA output mode
$_select['alsa_output_mode_bt'] .= "<option value=\"_audioout\" " . (($_SESSION['alsa_output_mode_bt'] == '_audioout') ? "selected" : "") . ">Standard</option>\n";
$_select['alsa_output_mode_bt'] .= "<option value=\"plughw\" " . (($_SESSION['alsa_output_mode_bt'] == 'plughw') ? "selected" : "") . ">Compatibility</option>\n";

waitWorker('blu-control');

$tpl = "blu-config.html";
$section = basename(__FILE__, '.php');
storeBackLink($section, $tpl);

include('header.php');
eval("echoTemplate(\"" . getTemplate("templates/$tpl") . "\");");
include('footer.php');
