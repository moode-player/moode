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
 * 2019-04-12 TC moOde 5.0
 *
 */

require_once dirname(__FILE__) . '/inc/playerlib.php';

playerSession('open', '' ,'');
$section = basename(__FILE__, '.php');
storeBackLink($section);

// submitted actions
if (isset($_POST['run_btcmd']) && $_POST['run_btcmd'] == '1') {
	$cmd = $_POST['btcmd'];
	sleep(1);

	if ($cmd == '-R') {
		$result = sysCmd('/var/www/command/bt.sh ' . $cmd);
		sleep(1);
		$cmd = '-p';
	}
	elseif ($cmd == '-D') {
		$result = sysCmd('/var/www/command/bt.sh ' . $cmd);
		sleep(1);
		$cmd = '-c';
	}
}
else {
	if ($_SESSION['btsvc'] == '1') {
		$cmd = ($_SESSION['btactive'] == '1' || $_SESSION['audioout'] == 'Bluetooth') ? '-c' : '-p';
	}
	else {
		$cmd = '-H';
	}
}

// pair with device
if (isset($_POST['pairwith_device']) && $_POST['pairwith_device'] == '1') {
	sysCmd('/var/www/command/bt.sh -P ' . '"' . $_POST['scanned_device'] . '"');
	$cmd = '-p';
	sleep(1);
}

// remove pairing
if (isset($_POST['rm_paired_device']) && $_POST['rm_paired_device'] == '1') {
	sysCmd('/var/www/command/bt.sh -r ' . '"' . $_POST['paired_device'] . '"');
	$cmd = '-p';
	sleep(1);
}

// connect to device
if (isset($_POST['connectto_device']) && $_POST['connectto_device'] == '1') {
	// update MAC address
	if ($_POST['audioout'] == 'Bluetooth') {
		sysCmd("sed -i '/device/c\ \t\tdevice \"" . $_POST['paired_device'] . "\"'" .  ' /usr/share/alsa/alsa.conf.d/btstream.conf');
	}
	// update MPD output
	playerSession('write', 'audioout', $_POST['audioout']);
	//setAudioOut($_POST['audioout']);	
	// connect device
	sysCmd('/var/www/command/bt.sh -C ' . '"' . $_POST['paired_device'] . '"');
	$cmd = '-c';
	sleep(1);
	setAudioOut($_POST['audioout']);	
}

// change MPD audio output
if (isset($_POST['chg_audioout']) && $_POST['chg_audioout'] == '1') {
	// update MAC address
	if ($_POST['audioout'] == 'Bluetooth' && (isset($_POST['paired_device']) || isset($_POST['connected_device']))) {
		$device = isset($_POST['paired_device']) ? $_POST['paired_device'] : $_POST['connected_device'];
		sysCmd("sed -i '/device/c\ \t\tdevice \"" . $device . "\"'" .  ' /usr/share/alsa/alsa.conf.d/btstream.conf');
	}
	// update MPD output
	playerSession('write', 'audioout', $_POST['audioout']);
	setAudioOut($_POST['audioout']);	
}

// disconnect paired device
if (isset($_POST['disconnect_device']) && $_POST['disconnect_device'] == '1') {
	// update MPD output
	playerSession('write', 'audioout', $_POST['audioout']);
	setAudioOut($_POST['audioout']);	
	// disconnect
	sysCmd('/var/www/command/bt.sh -d ' . '"' . $_POST['connected_device'] . '"');
	$cmd = '-p';
	sleep(1);
}

session_write_close();

// command list
$_cmd['btcmd'] .= "<option value=\"-s\" " . (($cmd == '-s') ? "selected" : "") . ">SCAN for devices</option>\n";
$_cmd['btcmd'] .= "<option value=\"-p\" " . (($cmd == '-p') ? "selected" : "") . ">LIST paired</option>\n";
$_cmd['btcmd'] .= "<option value=\"-c\" " . (($cmd == '-c') ? "selected" : "") . ">LIST connected</option>\n";
//$_cmd['btcmd'] .= "<option value=\"-l\" " . (($cmd == '-l') ? "selected" : "") . ">LIST discovered</option>\n";
$_cmd['btcmd'] .= "<option value=\"-D\" " . (($cmd == '-D') ? "selected" : "") . ">DISCONNECT all</option>\n";
$_cmd['btcmd'] .= "<option value=\"-R\" " . (($cmd == '-R') ? "selected" : "") . ">REMOVE all paired</option>\n";
//$_cmd['btcmd'] .= "<option value=\"-i\" " . (($cmd == '-i') ? "selected" : "") . ">INITIALIZE controller</option>\n";
$_cmd['btcmd'] .= "<option value=\"-H\" " . (($cmd == '-H') ? "selected" : "") . ">HELP</option>\n";

// initial control states
$_hide_ctl['paired_device'] = 'hide';
$_hide_ctl['connected_device'] = 'hide';
$_hide_ctl['scanned_device'] = 'hide';
//$_hide_ctl['chg_audioout'] = $cmd == '-c' ? '' : 'hide';
$_bt_disabled = $_SESSION['btsvc'] == '1' ? '' : 'disabled';
$_bt_msg_hide = $_SESSION['btsvc'] == '1' ? 'hide' : '';
$_ao_msg_hide = ($cmd == '-p' || $cmd == '-c') ? '' : 'hide';

// run the cmd 
$result = sysCmd('/var/www/command/bt.sh ' . $cmd);
if ($cmd == '-i') {
	// remove ansi color codes and fix formatting in the output of -i
	$result = preg_replace('#\\x1b[[][^A-Za-z]*[A-Za-z]#', '', $result);
	$result = str_replace('Waiting to connect to bluetoothd...', 'Waiting to connect to bluetoothd...<br>', $result);
}

/*
// format output for html
for ($i = 0; $i < count($result); $i++) {
	$_cmd_output .= $result[$i] . "<br>";
}
*/
// TEST alternative 2
if ($cmd == '-H' || $cmd == '-i') {
	for ($i = 0; $i < count($result); $i++) {
		$_cmd_output .= $result[$i] . "<br>";
	}
}
else {
	for ($i = 2; $i < count($result); $i++) {
		if ($result[$i] != '**') {
			$_cmd_output .= '** ' . substr($result[$i], 21) . '<br>';
		}
	}
}
$_cmd_output = empty($_cmd_output) ? 'No devices' : $_cmd_output;

// audio output
$_select['audioout'] .= "<option value=\"Local\" " . (($_SESSION['audioout'] == 'Local') ? "selected" : "") . ">MPD Audio output -> Local</option>\n";
$_select['audioout'] .= "<option value=\"Bluetooth\" " . (($_SESSION['audioout'] == 'Bluetooth') ? "selected" : "") . ">MPD Audio output -> Bluetooth</option>\n";

// provide a select for removing | disconnecting | pairing | connecting
if ($cmd == '-p' || $cmd == '-c' || $cmd == '-l' || $cmd == '-s') {
	if ($cmd == '-p') {$type = 'paired_device';}
	if ($cmd == '-c') {$type = 'connected_device';}
	if ($cmd == '-l') {$type = 'scanned_device';}
	if ($cmd == '-s') {$type = 'scanned_device';}

	for ($i = 0; $i < count($result); $i++) {
		$token = explode(' ', $result[$i], 3);
		if (strpos($token[1], ':') !== false) {
			$_device[$type] .= "<option value=\"" . $token[1] . "\">" . $token[2] . "</option>\n";
		}		
	}
	// hide/unhide controls
	$_hide_ctl[$type] = empty($_device[$type]) ? 'hide' : '';
}

waitWorker(1);

$tpl = "blu-config.html";
include('/var/local/www/header.php'); 
eval("echoTemplate(\"" . getTemplate("templates/$tpl") . "\");");
include('footer.php');
