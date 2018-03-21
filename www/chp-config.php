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
 * Refer to the link below for a copy of the GNU General Public License.
 * http://www.gnu.org/licenses/
 *
 * 2018-01-26 TC moOde 4.0
 *
 */

require_once dirname(__FILE__) . '/inc/playerlib.php';

playerSession('open', '' ,''); 
$dbh = cfgdb_connect();
$sock = openMpdSock('localhost', 6600);

// apply setting changes
if (isset($_POST['apply']) && $_POST['apply'] == '1') {
	$result = cfgdb_read('cfg_audiodev', $dbh, $_SESSION['i2sdevice']);
	$chipoptions = $_POST['config']['analoggain'] . ',' . $_POST['config']['analogboost'] . ',' . $_POST['config']['digfilter'];

	// amixer cmds
	cfgChipOptions($chipoptions);

	// see if digital filter change submitted
	if (explode(',', $result[0]['chipoptions'])[2] != $_POST['config']['digfilter']) {
		$status = parseStatus(getMpdStatus($sock));

		// restart playback to make filter change effective
		if ($status['state'] === 'play') {
			$cmds = array('pause', 'play');
			chainMpdCmdsDelay($sock, $cmds, 1000000);
		}						
	}

	// update chip options
	$result = cfgdb_update('cfg_audiodev', $dbh, $_SESSION['i2sdevice'], $chipoptions);

	// update Allo Piano 2.1 Hi-Fi DAC settings
	if ($_POST['config']['outputmode'] == 'Dual-Mono' || $_POST['config']['outputmode'] == 'Dual-Stereo') {
		sysCmd('/var/www/command/util.sh set-piano-dualmode ' . '"' . $_POST['config']['outputmode'] . '"');
	}
	else {
		sysCmd('/var/www/command/util.sh set-piano-submode ' . '"' . $_POST['config']['outputmode'] . '"');
		sysCmd('/var/www/command/util.sh set-piano-lowpass ' . '"' . $_POST['config']['lowpass'] . '"');
		sysCmd('/var/www/command/util.sh set-piano-subvol ' . '"' . $_POST['config']['subwvol'] . '"');
	}

	$_SESSION['notify']['title'] = 'Chip/Device options updated';
	$_SESSION['notify']['msg'] = 'REBOOT then APPLY MPD settings';
	$_SESSION['notify']['duration'] = 20;
}
	
session_write_close();

// load chip options
$result = cfgdb_read('cfg_audiodev', $dbh, $_SESSION['i2sdevice']);
$array = explode(',', $result[0]['chipoptions']);

$analoggain = $array[0]; // Analog gain
$analogboost = $array[1]; // Analog gain boost
$digfilter = $array[2]; // Digital interpolation filter

// digital interpolation filter
$_select['digfilter'] .= "<option value=\"FIR interpolation with de-emphasis\" " . (($digfilter == 'FIR interpolation with de-emphasis') ? "selected" : "") . ">FIR interpolation with de-emphasis</option>\n";
$_select['digfilter'] .= "<option value=\"High attenuation with de-emphasis\" " . (($digfilter == 'High attenuation with de-emphasis') ? "selected" : "") . ">High attenuation with de-emphasis</option>\n";
$_select['digfilter'] .= "<option value=\"Low latency IIR with de-emphasis\" " . (($digfilter == 'Low latency IIR with de-emphasis') ? "selected" : "") . ">Low latency IIR with de-emphasis</option>\n";
$_select['digfilter'] .= "<option value=\"Ringing-less low latency FIR\" " . (($digfilter == 'Ringing-less low latency FIR') ? "selected" : "") . ">Ringing-less low latency FIR</option>\n";
// analog volume
$_select['analoggain'] .= "<option value=\"100\" " . (($analoggain == '100') ? "selected" : "") . ">0 dB (2-Vrms)</option>\n";
$_select['analoggain'] .= "<option value=\"0\" " . (($analoggain == '0') ? "selected" : "") . ">-6 dB (1-Vrms)</option>\n";
// analog playback boost
$_select['analogboost'] .= "<option value=\"0\" " . (($analogboost == '0') ? "selected" : "") . ">0 dB (normal amplitude)</option>\n";
$_select['analogboost'] .= "<option value=\"100\" " . (($analogboost == '100') ? "selected" : "") . ">.8 dB (10% boosted amplitude)</option>\n";

// allo piano 2.1 Hi-Fi DAC
if ($_SESSION['i2sdevice'] == 'Allo Piano 2.1 Hi-Fi DAC') {
	$_allo_piano_hide = '';

	// get current settings
	$dualmode = sysCmd('/var/www/command/util.sh get-piano-dualmode');
	$submode = sysCmd('/var/www/command/util.sh get-piano-submode');
	$subvol = sysCmd('/var/www/command/util.sh get-piano-subvol');
	$lowpass = sysCmd('/var/www/command/util.sh get-piano-lowpass');

	// determine output mode
	if ($dualmode[0] != 'None') {
		$outputmode = $dualmode[0];
		//$_allo_piano_submode_disabled = 'disabled';
		$_allo_piano_submode_disabled = '';
	}
	elseif ($submode[0] == '2.0') {
		$outputmode = $submode[0];
		//$_allo_piano_submode_disabled = 'disabled';
		$_allo_piano_submode_disabled = '';
	}
	else {
		$outputmode = $submode[0];
		$_allo_piano_submode_disabled = '';
	}

	$_select['outputmode'] .= "<option value=\"Dual-Mono\" " . (($outputmode == 'Dual-Mono') ? "selected" : "") . ">Dual-Mono</option>\n";
	$_select['outputmode'] .= "<option value=\"Dual-Stereo\" " . (($outputmode == 'Dual-Stereo') ? "selected" : "") . ">Dual-Stereo</option>\n";
	$_select['outputmode'] .= "<option value=\"2.0\" " . (($outputmode == '2.0') ? "selected" : "") . ">Stereo</option>\n";
	$_select['outputmode'] .= "<option value=\"2.1\" " . (($outputmode == '2.1') ? "selected" : "") . ">Subwoofer 2.1</option>\n";
	$_select['outputmode'] .= "<option value=\"2.2\" " . (($outputmode == '2.2') ? "selected" : "") . ">Subwoofer 2.2</option>\n";

	$_select['lowpass'] .= "<option value=\"60\" " . (($lowpass[0] == '60') ? "selected" : "") . ">60</option>\n";
	$_select['lowpass'] .= "<option value=\"70\" " . (($lowpass[0] == '70') ? "selected" : "") . ">70</option>\n";
	$_select['lowpass'] .= "<option value=\"80\" " . (($lowpass[0] == '80') ? "selected" : "") . ">80</option>\n";
	$_select['lowpass'] .= "<option value=\"90\" " . (($lowpass[0] == '90') ? "selected" : "") . ">90</option>\n";
	$_select['lowpass'] .= "<option value=\"100\" " . (($lowpass[0] == '100') ? "selected" : "") . ">100</option>\n";
	$_select['lowpass'] .= "<option value=\"110\" " . (($lowpass[0] == '110') ? "selected" : "") . ">110</option>\n";
	$_select['lowpass'] .= "<option value=\"120\" " . (($lowpass[0] == '120') ? "selected" : "") . ">120</option>\n";
	$_select['lowpass'] .= "<option value=\"130\" " . (($lowpass[0] == '130') ? "selected" : "") . ">130</option>\n";
	$_select['lowpass'] .= "<option value=\"140\" " . (($lowpass[0] == '140') ? "selected" : "") . ">140</option>\n";
	$_select['lowpass'] .= "<option value=\"150\" " . (($lowpass[0] == '150') ? "selected" : "") . ">150</option>\n";
	$_select['lowpass'] .= "<option value=\"160\" " . (($lowpass[0] == '160') ? "selected" : "") . ">160</option>\n";
	$_select['lowpass'] .= "<option value=\"170\" " . (($lowpass[0] == '170') ? "selected" : "") . ">170</option>\n";
	$_select['lowpass'] .= "<option value=\"180\" " . (($lowpass[0] == '180') ? "selected" : "") . ">180</option>\n";
	$_select['lowpass'] .= "<option value=\"190\" " . (($lowpass[0] == '190') ? "selected" : "") . ">190</option>\n";
	$_select['lowpass'] .= "<option value=\"200\" " . (($lowpass[0] == '200') ? "selected" : "") . ">200</option>\n";

	$subwvol = str_replace('%', '', $subvol[0]);
}
else{
	$_allo_piano_hide = 'hide';
}

$section = basename(__FILE__, '.php');
$tpl = "chp-config.html";
include('/var/local/www/header.php'); 
waitWorker(1);
eval("echoTemplate(\"" . getTemplate("templates/$tpl") . "\");");
include('footer.php');
