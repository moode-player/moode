<?php
/**
 * moOde audio player (C) 2014 Tim Curtis
 * http://moodeaudio.org
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
require_once __DIR__ . '/inc/alsa.php';
require_once __DIR__ . '/inc/audio.php';
require_once __DIR__ . '/inc/mpd.php';
require_once __DIR__ . '/inc/renderer.php';
require_once __DIR__ . '/inc/session.php';
require_once __DIR__ . '/inc/cdsp.php';

phpSession('open');

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

// Connect to device
if (isset($_POST['connectto_device']) && $_POST['connectto_device'] == '1') {
	if ($_POST['audioout'] == 'Bluetooth') { // Update MAC address
		sysCmd("sed -i '/device/c\device \"" . $_POST['paired_device'] . "\"' " . ALSA_PLUGIN_PATH . '/btstream.conf');
	}
	phpSession('write', 'audioout', $_POST['audioout']);
	sysCmd('/var/www/util/blu-control.sh -C ' . '"' . $_POST['paired_device'] . '"');
	$cmd = '-c';
	sleep(1);
	setAudioOut($_POST['audioout']);
}

// Change audio destination
if (isset($_POST['chg_audioout']) && $_POST['audioout'] != $_SESSION['audioout']) {
	if ($_POST['audioout'] == 'Bluetooth' && (isset($_POST['paired_device']) || isset($_POST['connected_device']))) {
		// Change to Bluetooth out, update MAC address
		$device = isset($_POST['paired_device']) ? $_POST['paired_device'] : $_POST['connected_device'];
		sysCmd("sed -i '/device/c\device \"" . $device . "\"' " . ALSA_PLUGIN_PATH . '/btstream.conf');
		phpSession('write', 'audioout', $_POST['audioout']);
		setAudioOut($_POST['audioout']);
	} else {
		// Change to local out, disconnect device
		phpSession('write', 'audioout', $_POST['audioout']);
		setAudioOut($_POST['audioout']);
		sysCmd('/var/www/util/blu-control.sh -d ' . '"' . $_POST['connected_device'] . '"');
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

// ALSA PCM buffer time
if (isset($_POST['update_pcm_buffer']) && $_POST['update_pcm_buffer'] == '1') {
	phpSession('write', 'bluez_pcm_buffer', $_POST['pcm_buffer']);
	sysCmd("sed -i '/BUFFERTIME/c\BUFFERTIME=" . $_POST['pcm_buffer'] . "' /etc/bluealsaaplay.conf");
	$_SESSION['notify']['title'] = 'Settings updated';
}

// ALSA output mode
if (isset($_POST['update_bt_alsa_output_mode']) && $_POST['update_bt_alsa_output_mode'] == '1') {
	$_SESSION['bt_alsa_output_mode'] = $_POST['bt_alsa_output_mode'];
	sysCmd("sed -i '/AUDIODEV/c\AUDIODEV=" . $_POST['bt_alsa_output_mode'] . "' /etc/bluealsaaplay.conf");
	$_SESSION['notify']['title'] = 'Settings updated';
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
$_bt_disabled = $_SESSION['btsvc'] == '1' ? '' : 'disabled';

if ($cmd == '-p' || $cmd == '-c') {
	$_ao_msg_hide = '';
	$_ao_msg_margin = 'config-help-no-margin';
} else {
	$_ao_msg_hide = 'hide';
	$_ao_msg_margin = '';
}

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

// ALSA PCM output buffer time (micro seconds)
$_select['pcm_buffer'] .= "<option value=\"500000\" " . (($_SESSION['bluez_pcm_buffer'] == '500000') ? "selected" : "") . ">500 ms (Default)</option>\n";
$_select['pcm_buffer'] .= "<option value=\"250000\" " . (($_SESSION['bluez_pcm_buffer'] == '250000') ? "selected" : "") . ">250 ms</option>\n";
$_select['pcm_buffer'] .= "<option value=\"125000\" " . (($_SESSION['bluez_pcm_buffer'] == '125000') ? "selected" : "") . ">125 ms</option>\n";
$_select['pcm_buffer'] .= "<option value=\"60000\" "  . (($_SESSION['bluez_pcm_buffer'] == '60000') ? "selected" : "")  . "> 60 ms</option>\n";
$_select['pcm_buffer'] .= "<option value=\"40000\" "  . (($_SESSION['bluez_pcm_buffer'] == '40000') ? "selected" : "")  . "> 40 ms</option>\n";
$_select['pcm_buffer'] .= "<option value=\"20000\" "  . (($_SESSION['bluez_pcm_buffer'] == '20000') ? "selected" : "")  . "> 20 ms</option>\n";

// ALSA output mode
$_select['bt_alsa_output_mode'] .= "<option value=\"_audioout\" " . (($_SESSION['bt_alsa_output_mode'] == '_audioout') ? "selected" : "") . ">Default</option>\n";
$_select['bt_alsa_output_mode'] .= "<option value=\"plughw\" " . (($_SESSION['bt_alsa_output_mode'] == 'plughw') ? "selected" : "") . ">Compatibility</option>\n";

waitWorker('blu-config');

$tpl = "blu-config.html";
$section = basename(__FILE__, '.php');
storeBackLink($section, $tpl);

include('header.php');
eval("echoTemplate(\"" . getTemplate("templates/$tpl") . "\");");
include('footer.php');
