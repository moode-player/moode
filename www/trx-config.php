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

set_include_path('/var/www/inc');
require_once 'playerlib.php';
require_once 'mpd.php';
require_once 'session.php';
require_once 'sql.php';
require_once 'multiroom.php';

phpSession('open');
$dbh = sqlConnect();

//
// SENDER
//
if (isset($_POST['update_multiroom_tx'])) {
	if (isset($_POST['multiroom_tx']) && $_POST['multiroom_tx'] != $_SESSION['multiroom_tx']) {
		$title = 'Multiroom sender ' . $_POST['multiroom_tx'];
		phpSession('write', 'multiroom_tx', $_POST['multiroom_tx']);
		submitJob('multiroom_tx', '', $title, '');
	}
}
if (isset($_POST['update_alsa_loopback'])) {
	if (isset($_POST['alsa_loopback']) && $_POST['alsa_loopback'] != $_SESSION['alsa_loopback']) {
		// Check to see if module is in use
		if ($_POST['alsa_loopback'] == 'Off') {
			$inUse = sysCmd('sudo modprobe -r snd-aloop');
			if (!empty($inUse)) {
				$_SESSION['notify']['title'] = 'Unable to turn off';
				$_SESSION['notify']['msg'] = 'Loopback is in use';
				$_SESSION['notify']['duration'] = 5;
			}
			else {
				submitJob('alsa_loopback', 'Off', 'Loopback Off', '');
				phpSession('write', 'alsa_loopback', 'Off');
			}
		}
		else {
			submitJob('alsa_loopback', 'On', 'Loopback On', '');
			phpSession('write', 'alsa_loopback', 'On');
		}
	}
}
if (isset($_POST['update_multiroom_initvol'])) {
	$result = sqlQuery("UPDATE cfg_multiroom SET value='" . $_POST['multiroom_initvol'] . "' " . "WHERE param='initial_volume'", $dbh);
	submitJob('multiroom_initvol', $_POST['multiroom_initvol'], 'Volume levels initialized', '');
}
if (isset($_POST['multiroom_tx_restart'])) {
	submitJob('multiroom_tx_restart', '', 'Sender restarted', '');
}
if (isset($_POST['multiroom_tx_discover'])) {
	// Scan the network for hosts with open port 6600 (MPD)
	$port6600Hosts = scanForMPDHosts();
	$thisIpAddr = sysCmd('hostname -I')[0];

	// Parse the results
	$_SESSION['rx_hostnames'] = '';
	$_SESSION['rx_addresses'] = '';
	$timeout = getStreamTimeout();
	foreach ($port6600Hosts as $ipAddr) {
		if ($ipAddr != $thisIpAddr) {
			if (false === ($status = file_get_contents('http://' . $ipAddr . '/command/?cmd=trx-status.php -rx', false, $timeout))) {
				debugLog('trx-config.php: get_rx_status failed: ' . $ipAddr);
			}
			else {
				if ($status != 'Unknown command') { // r740 or higher host
					$rxStatus = explode(',', $status);
					// rx, On/Off/Disabled/Unknown, volume, volume,mute_1/0, mastervol_opt_in_1/0, hostname
					// NOTE: Only include hosts with status = On/Off
					if ($rxStatus[1] == 'On' || $rxStatus[1] == 'Off') {
						// r800 status will have a 6th element (hostname) otherwise sub in ip address
						$_SESSION['rx_hostnames'] .= (count($rxStatus) > 5 ? $rxStatus[5] : $ipAddr) . ', ';
						$_SESSION['rx_addresses'] .= $ipAddr . ' ';
					}
				}
			}
		}
	}
	$_SESSION['rx_hostnames'] = rtrim($_SESSION['rx_hostnames'], ', ');
	$_SESSION['rx_addresses'] = rtrim($_SESSION['rx_addresses'], ' ');

	// Check for no receivers found
	if (empty(trim($_SESSION['rx_hostnames']))) {
		$_SESSION['rx_hostnames'] = 'No receivers found';
		$_SESSION['notify']['title'] = $_SESSION['rx_hostnames'];
	}
	else {
		$_SESSION['notify']['title'] = 'Discovery complete';
		$_SESSION['notify']['msg'] = 'Found: ' . $_SESSION['rx_hostnames'];
	}
}
if (isset($_POST['update_multiroom_tx_bfr'])) {
	if (isset($_POST['multiroom_tx_bfr']) && $_POST['multiroom_tx_bfr'] != $_cfg_multiroom['tx_bfr']) {
		$result = sqlQuery("UPDATE cfg_multiroom SET value='" . $_POST['multiroom_tx_bfr'] . "' " . "WHERE param='tx_bfr'", $dbh);
		$msg = $_SESSION['multiroom_tx'] == 'On' ? 'Sender restarted' : '';
		submitJob('multiroom_tx_restart', '', 'ALSA buffer updated', $msg);
	}
}
if (isset($_POST['update_multiroom_tx_frame_size'])) {
	if (isset($_POST['multiroom_tx_frame_size']) && $_POST['multiroom_tx_frame_size'] != $_cfg_multiroom['tx_frame_size']) {
		$result = sqlQuery("UPDATE cfg_multiroom SET value='" . $_POST['multiroom_tx_frame_size'] . "' " . "WHERE param='tx_frame_size'", $dbh);
		$msg = $_SESSION['multiroom_tx'] == 'On' ? 'Sender restarted' : '';
		submitJob('multiroom_tx_restart', '', 'Opus frame size updated', $msg);
	}
}
if (isset($_POST['update_multiroom_tx_rtprio'])) {
	if (isset($_POST['multiroom_tx_rtprio']) && $_POST['multiroom_tx_rtprio'] != $_cfg_multiroom['tx_rtprio']) {
		$result = sqlQuery("UPDATE cfg_multiroom SET value='" . $_POST['multiroom_tx_rtprio'] . "' " . "WHERE param='tx_rtprio'", $dbh);
		$msg = $_SESSION['multiroom_tx'] == 'On' ? 'Sender restarted' : '';
		submitJob('multiroom_tx_restart', '', 'Realtime priority updated', $msg);
	}
}
if (isset($_POST['update_multiroom_tx_query_timeout'])) {
	if (isset($_POST['multiroom_tx_query_timeout']) && $_POST['multiroom_tx_query_timeout'] != $_cfg_multiroom['multiroom_tx_query_timeout']) {
		$_SESSION['notify']['title'] = 'Query timeout updated';
		$result = sqlQuery("UPDATE cfg_multiroom SET value='" . $_POST['multiroom_tx_query_timeout'] . "' " . "WHERE param='tx_query_timeout'", $dbh);
	}
}

//
// RECEIVER
//

if (isset($_POST['update_multiroom_rx'])) {
	if (isset($_POST['multiroom_rx']) && $_POST['multiroom_rx'] != $_SESSION['multiroom_rx']) {
		$title = 'Multiroom receiver ' . $_POST['multiroom_rx'];
		phpSession('write', 'multiroom_rx', $_POST['multiroom_rx']);
		submitJob('multiroom_rx', '', $title, '');
	}
}
if (isset($_POST['update_multiroom_rx_mastervol_opt_in'])) {
	if (isset($_POST['multiroom_rx_mastervol_opt_in']) && $_POST['multiroom_rx_mastervol_opt_in'] != $_cfg_multiroom['rx_mastervol_opt_in']) {
		$_SESSION['notify']['title'] = 'Master volume opt-in ' . ($_POST['multiroom_rx_mastervol_opt_in'] == '1' ? 'Yes' : 'No');
		$result = sqlQuery("UPDATE cfg_multiroom SET value='" . $_POST['multiroom_rx_mastervol_opt_in'] . "' " . "WHERE param='rx_mastervol_opt_in'", $dbh);
	}
}
if (isset($_POST['update_multiroom_rx_alsa_output_mode'])) {
	if (isset($_POST['multiroom_rx_alsa_output_mode']) && $_POST['multiroom_rx_alsa_output_mode'] != $_cfg_multiroom['rx_alsa_output_mode']) {
		$result = sqlQuery("UPDATE cfg_multiroom SET value='" . $_POST['multiroom_rx_alsa_output_mode'] . "' " . "WHERE param='rx_alsa_output_mode'", $dbh);
		$msg = $_SESSION['multiroom_rx'] == 'On' ? 'Receiver restarted' : '';
		submitJob('multiroom_rx_restart', '', 'ALSA output mode updated', $msg);
	}
}
if (isset($_POST['update_multiroom_rx_alsavol'])) {
	if (isset($_POST['multiroom_rx_alsavol'])) {
		$_SESSION['notify']['title'] = 'ALSA volume updated';
		sysCmd('/var/www/command/util.sh set-alsavol ' . $_SESSION['amixname'] . ' ' . $_POST['multiroom_rx_alsavol']);
	}
}
if (isset($_POST['multiroom_rx_restart'])) {
	submitJob('multiroom_rx_restart', '', 'Receiver restarted', '');
}
if (isset($_POST['update_multiroom_rx_bfr'])) {
	if (isset($_POST['multiroom_rx_bfr']) && $_POST['multiroom_rx_bfr'] != $_cfg_multiroom['rx_bfr']) {
		$result = sqlQuery("UPDATE cfg_multiroom SET value='" . $_POST['multiroom_rx_bfr'] . "' " . "WHERE param='rx_bfr'", $dbh);
		$msg = $_SESSION['multiroom_rx'] == 'On' ? 'Receiver restarted' : '';
		submitJob('multiroom_rx_restart', '', 'ALSA buffer updated', $msg);
	}
}
if (isset($_POST['update_multiroom_rx_jitter_bfr'])) {
	if (isset($_POST['multiroom_rx_jitter_bfr']) && $_POST['multiroom_rx_jitter_bfr'] != $_cfg_multiroom['rx_jitter_bfr']) {
		$result = sqlQuery("UPDATE cfg_multiroom SET value='" . $_POST['multiroom_rx_jitter_bfr'] . "' " . "WHERE param='rx_jitter_bfr'", $dbh);
		$msg = $_SESSION['multiroom_rx'] == 'On' ? 'Receiver restarted' : '';
		submitJob('multiroom_rx_restart', '', 'RTP Jitter buffer updated', $msg);
	}
}
if (isset($_POST['update_multiroom_rx_frame_size'])) {
	if (isset($_POST['multiroom_rx_frame_size']) && $_POST['multiroom_rx_frame_size'] != $_cfg_multiroom['rx_frame_size']) {
		$result = sqlQuery("UPDATE cfg_multiroom SET value='" . $_POST['multiroom_rx_frame_size'] . "' " . "WHERE param='rx_frame_size'", $dbh);
		$msg = $_SESSION['multiroom_rx'] == 'On' ? 'Receiver restarted' : '';
		submitJob('multiroom_rx_restart', '', 'Opus frame size updated', $msg);
	}
}
if (isset($_POST['update_multiroom_rx_rtprio'])) {
	if (isset($_POST['multiroom_rx_rtprio']) && $_POST['multiroom_rx_rtprio'] != $_cfg_multiroom['rx_rtprio']) {
		$result = sqlQuery("UPDATE cfg_multiroom SET value='" . $_POST['multiroom_rx_rtprio'] . "' " . "WHERE param='rx_rtprio'", $dbh);
		$msg = $_SESSION['multiroom_rx'] == 'On' ? 'Receiver restarted' : '';
		submitJob('multiroom_rx_restart', '', 'Realtime priority updated', $msg);
	}
}

if (phpSession('get_status') == PHP_SESSION_ACTIVE) {
	phpSession('close');
}

//
// FORM DATA
//

$params = sqlRead('cfg_multiroom', $dbh);
foreach ($params as $row) {
    $_cfg_multiroom[$row['param']] = $row['value'];
}

// Feature and button states
$_feat_multiroom = $_SESSION['feat_bitmask'] & FEAT_MULTIROOM ? '' : 'hide';
$_dsp_on = ($_SESSION['crossfeed'] == 'Off' && $_SESSION['eqfa12p'] == 'Off' && $_SESSION['alsaequal'] == 'Off' &&
	$_SESSION['camilladsp'] == 'off' && $_SESSION['invert_polarity'] == '0') ? false : true;
$_multiroom_tx_disable = ($_SESSION['alsa_loopback'] == 'Off' || $_dsp_on == true) ? 'disabled' : '';
$_tx_restart_btn_disable = $_SESSION['multiroom_tx'] == 'Off' ? 'disabled' : '';
$_tx_restart_link_disable = $_SESSION['multiroom_tx'] == 'Off' ? 'onclick="return false;"' : '';
$_tx_adv_options_hide = $_SESSION['tx_adv_toggle'] == 'Advanced (&minus;)' ? '' : 'hide';
$_multiroom_rx_disable = ($_SESSION['alsavolume'] == 'none' || $_SESSION['mpdmixer'] == 'software') ? 'disabled' : '';
$_rx_restart_btn_disable = $_SESSION['multiroom_rx'] != 'On' ? 'disabled' : '';
$_rx_restart_link_disable = $_SESSION['multiroom_rx'] != 'On' ? 'onclick="return false;"' : '';
$_multiroom_initvol_disable = empty($_SESSION['rx_hostnames']) ? 'disable' : '';
$_rx_adv_options_hide = $_SESSION['rx_adv_toggle'] == 'Advanced (&minus;)' ? '' : 'hide';

// Sender
$_select['multiroom_tx1'] .= "<input type=\"radio\" name=\"multiroom_tx\" id=\"toggle_multiroom_tx1\" value=\"On\" " . (($_SESSION['multiroom_tx'] == 'On') ? "checked=\"checked\"" : "") . ">\n";
$_select['multiroom_tx0'] .= "<input type=\"radio\" name=\"multiroom_tx\" id=\"toggle_multiroom_tx2\" value=\"Off\" " . (($_SESSION['multiroom_tx'] == 'Off') ? "checked=\"checked\"" : "") . ">\n";
$_alsa_loopback_disable = $_SESSION['alsa_output_mode'] == 'plughw' ? '' : 'disabled';
$_select['alsa_loopback1'] .= "<input type=\"radio\" name=\"alsa_loopback\" id=\"toggle_alsa_loopback1\" value=\"On\" " . (($_SESSION['alsa_loopback'] == 'On') ? "checked=\"checked\"" : "") . ">\n";
$_select['alsa_loopback0'] .= "<input type=\"radio\" name=\"alsa_loopback\" id=\"toggle_alsa_loopback2\" value=\"Off\" " . (($_SESSION['alsa_loopback'] == 'Off') ? "checked=\"checked\"" : "") . ">\n";
$_multiroom_initvol = $_cfg_multiroom['initial_volume'];
$_rx_hostnames = $_SESSION['rx_hostnames'] != 'No receivers found' ? 'Found: ' . $_SESSION['rx_hostnames'] : $_SESSION['rx_hostnames'];
// Advanced options
$_select['multiroom_tx_bfr'] .= "<option value=\"16\" " . (($_cfg_multiroom['tx_bfr'] == '16') ? "selected" : "") . ">16</option>\n";
$_select['multiroom_tx_bfr'] .= "<option value=\"32\" " . (($_cfg_multiroom['tx_bfr'] == '32') ? "selected" : "") . ">32</option>\n";
$_select['multiroom_tx_bfr'] .= "<option value=\"48\" " . (($_cfg_multiroom['tx_bfr'] == '48') ? "selected" : "") . ">48</option>\n";
$_select['multiroom_tx_bfr'] .= "<option value=\"64\" " . (($_cfg_multiroom['tx_bfr'] == '64') ? "selected" : "") . ">64 (Default)</option>\n";
$_select['multiroom_tx_bfr'] .= "<option value=\"96\" " . (($_cfg_multiroom['tx_bfr'] == '96') ? "selected" : "") . ">96</option>\n";
$_select['multiroom_tx_bfr'] .= "<option value=\"128\" " . (($_cfg_multiroom['tx_bfr'] == '128') ? "selected" : "") . ">128</option>\n";
$_select['multiroom_tx_frame_size'] .= "<option value=\"120\" " . (($_cfg_multiroom['tx_frame_size'] == '120') ? "selected" : "") . ">2.5</option>\n";
$_select['multiroom_tx_frame_size'] .= "<option value=\"240\" " . (($_cfg_multiroom['tx_frame_size'] == '240') ? "selected" : "") . ">7.5</option>\n";
$_select['multiroom_tx_frame_size'] .= "<option value=\"480\" " . (($_cfg_multiroom['tx_frame_size'] == '480') ? "selected" : "") . ">10</option>\n";
$_select['multiroom_tx_frame_size'] .= "<option value=\"960\" " . (($_cfg_multiroom['tx_frame_size'] == '960') ? "selected" : "") . ">20</option>\n";
$_select['multiroom_tx_frame_size'] .= "<option value=\"1920\" " . (($_cfg_multiroom['tx_frame_size'] == '1920') ? "selected" : "") . ">40 (Default)</option>\n";
//$_select['multiroom_tx_frame_size'] .= "<option value=\"2880\" " . (($_cfg_multiroom['tx_frame_size'] == '2880') ? "selected" : "") . ">60</option>\n";
$_multiroom_tx_rtprio = $_cfg_multiroom['tx_rtprio'];
$_multiroom_tx_query_timeout = $_cfg_multiroom['tx_query_timeout'];

// Receiver
$_select['multiroom_rx'] .= "<option value=\"Disabled\" " . (($_SESSION['multiroom_rx'] == 'Disabled') ? "selected" : "") . ">Disabled</option>\n";
$_select['multiroom_rx'] .= "<option value=\"On\" " . (($_SESSION['multiroom_rx'] == 'On') ? "selected" : "") . ">On</option>\n";
$_select['multiroom_rx'] .= "<option value=\"Off\" " . (($_SESSION['multiroom_rx'] == 'Off') ? "selected" : "") . ">Off</option>\n";
$_select['multiroom_rx_mastervol_opt_in1'] .= "<input type=\"radio\" name=\"multiroom_rx_mastervol_opt_in\" id=\"toggle_multiroom_rx_mastervol_opt_in1\" value=\"1\" " . (($_cfg_multiroom['rx_mastervol_opt_in'] == '1') ? "checked=\"checked\"" : "") . ">\n";
$_select['multiroom_rx_mastervol_opt_in0'] .= "<input type=\"radio\" name=\"multiroom_rx_mastervol_opt_in\" id=\"toggle_multiroom_rx_mastervol_opt_in2\" value=\"0\" " . (($_cfg_multiroom['rx_mastervol_opt_in'] == '0') ? "checked=\"checked\"" : "") . ">\n";
$_select['multiroom_rx_alsa_output_mode'] .= "<option value=\"plughw\" " . (($_cfg_multiroom['rx_alsa_output_mode'] == 'plughw') ? "selected" : "") . ">Default (plughw)</option>\n";
$_select['multiroom_rx_alsa_output_mode'] .= "<option value=\"hw\" " . (($_cfg_multiroom['rx_alsa_output_mode'] == 'hw') ? "selected" : "") . ">Direct (hw)</option>\n";
$_multiroom_rx_alsavol = rtrim(sysCmd('/var/www/command/util.sh get-alsavol ' . '"' . $_SESSION['amixname'] . '"')[0], '%');
// Advanced options
$_select['multiroom_rx_bfr'] .= "<option value=\"16\" " . (($_cfg_multiroom['rx_bfr'] == '16') ? "selected" : "") . ">16</option>\n";
$_select['multiroom_rx_bfr'] .= "<option value=\"32\" " . (($_cfg_multiroom['rx_bfr'] == '32') ? "selected" : "") . ">32</option>\n";
$_select['multiroom_rx_bfr'] .= "<option value=\"48\" " . (($_cfg_multiroom['rx_bfr'] == '48') ? "selected" : "") . ">48</option>\n";
$_select['multiroom_rx_bfr'] .= "<option value=\"64\" " . (($_cfg_multiroom['rx_bfr'] == '64') ? "selected" : "") . ">64 (Default)</option>\n";
$_select['multiroom_rx_bfr'] .= "<option value=\"96\" " . (($_cfg_multiroom['rx_bfr'] == '96') ? "selected" : "") . ">96</option>\n";
$_select['multiroom_rx_bfr'] .= "<option value=\"128\" " . (($_cfg_multiroom['rx_bfr'] == '128') ? "selected" : "") . ">128</option>\n";
$_select['multiroom_rx_jitter_bfr'] .= "<option value=\"16\" " . (($_cfg_multiroom['rx_jitter_bfr'] == '16') ? "selected" : "") . ">16</option>\n";
$_select['multiroom_rx_jitter_bfr'] .= "<option value=\"32\" " . (($_cfg_multiroom['rx_jitter_bfr'] == '32') ? "selected" : "") . ">32 (Default)</option>\n";
$_select['multiroom_rx_jitter_bfr'] .= "<option value=\"48\" " . (($_cfg_multiroom['rx_jitter_bfr'] == '48') ? "selected" : "") . ">48</option>\n";
$_select['multiroom_rx_jitter_bfr'] .= "<option value=\"64\" " . (($_cfg_multiroom['rx_jitter_bfr'] == '64') ? "selected" : "") . ">64</option>\n";
$_select['multiroom_rx_jitter_bfr'] .= "<option value=\"96\" " . (($_cfg_multiroom['rx_jitter_bfr'] == '96') ? "selected" : "") . ">96</option>\n";
$_select['multiroom_rx_jitter_bfr'] .= "<option value=\"128\" " . (($_cfg_multiroom['rx_jitter_bfr'] == '128') ? "selected" : "") . ">128</option>\n";
$_select['multiroom_rx_frame_size'] .= "<option value=\"120\" " . (($_cfg_multiroom['rx_frame_size'] == '120') ? "selected" : "") . ">2.5</option>\n";
$_select['multiroom_rx_frame_size'] .= "<option value=\"240\" " . (($_cfg_multiroom['rx_frame_size'] == '240') ? "selected" : "") . ">7.5</option>\n";
$_select['multiroom_rx_frame_size'] .= "<option value=\"480\" " . (($_cfg_multiroom['rx_frame_size'] == '480') ? "selected" : "") . ">10</option>\n";
$_select['multiroom_rx_frame_size'] .= "<option value=\"960\" " . (($_cfg_multiroom['rx_frame_size'] == '960') ? "selected" : "") . ">20</option>\n";
$_select['multiroom_rx_frame_size'] .= "<option value=\"1920\" " . (($_cfg_multiroom['rx_frame_size'] == '1920') ? "selected" : "") . ">40 (Default)</option>\n";
//$_select['multiroom_rx_frame_size'] .= "<option value=\"2880\" " . (($_cfg_multiroom['rx_frame_size'] == '2880') ? "selected" : "") . ">60</option>\n";
$_multiroom_rx_rtprio = $_cfg_multiroom['rx_rtprio'];

waitWorker(1, 'trx-config');

$tpl = "trx-config.html";
$section = basename(__FILE__, '.php');
storeBackLink($section, $tpl);

include('header.php');
eval("echoTemplate(\"" . getTemplate("templates/$tpl") . "\");");
include('footer.php');
