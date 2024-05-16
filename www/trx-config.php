<?php
/*
 * SPDX-License-Identifier: GPL-3.0-or-later
 * Copyright 2014 The moOde audio player project / Tim Curtis
*/

require_once __DIR__ . '/inc/common.php';
require_once __DIR__ . '/inc/audio.php';
require_once __DIR__ . '/inc/mpd.php';
require_once __DIR__ . '/inc/multiroom.php';
require_once __DIR__ . '/inc/session.php';
require_once __DIR__ . '/inc/sql.php';

$dbh = sqlConnect();
phpSession('open');

//
// SENDER
//
if (isset($_POST['update_multiroom_tx'])) {
	if (isset($_POST['multiroom_tx']) && $_POST['multiroom_tx'] != $_SESSION['multiroom_tx']) {
		phpSession('write', 'multiroom_tx', $_POST['multiroom_tx']);
		submitJob('multiroom_tx');
		$notifyMsg = $_POST['multiroom_tx'] == 'On' ? 'trx_configuring_sender' : 'trx_configuring_mpd';
		sendFECmd($notifyMsg);
	}
}
if (isset($_POST['update_alsa_loopback'])) {
	if (isset($_POST['alsa_loopback']) && $_POST['alsa_loopback'] != $_SESSION['alsa_loopback']) {
		// Check to see if module is in use
		if ($_POST['alsa_loopback'] == 'Off') {
			$inUse = sysCmd('sudo modprobe -r snd-aloop');
			if (!empty($inUse)) {
				$_SESSION['notify']['title'] = NOTIFY_TITLE_ALERT;
				$_SESSION['notify']['msg'] = NOTIFY_MSG_LOOPBACK_ACTIVE;
			} else {
				submitJob('alsa_loopback', 'Off');
				phpSession('write', 'alsa_loopback', 'Off');
			}
		} else {
			submitJob('alsa_loopback', 'On');
			phpSession('write', 'alsa_loopback', 'On');
		}
	}
}
if (isset($_POST['update_multiroom_initvol'])) {
	$result = sqlQuery("UPDATE cfg_multiroom SET value='" . $_POST['multiroom_initvol'] . "' " . "WHERE param='initial_volume'", $dbh);
	submitJob('multiroom_initvol', $_POST['multiroom_initvol'], NOTIFY_TITLE_INFO, 'Volume levels initialized.');
}
if (isset($_POST['multiroom_tx_restart'])) {
	submitJob('multiroom_tx_restart', '', NOTIFY_TITLE_INFO, 'Sender restarted.');
}
if (isset($_POST['multiroom_tx_discover'])) {
	sendFECmd('trx_discovering_receivers');

	// Scan the network for hosts with open port 6600 (MPD)
	$port6600Hosts = scanForMPDHosts();
	$thisIpAddr = getThisIpAddr();

	// Parse the results
	$_SESSION['rx_hostnames'] = '';
	$_SESSION['rx_addresses'] = '';
	$timeout = getStreamTimeout();
	foreach ($port6600Hosts as $ipAddr) {
		if ($ipAddr != $thisIpAddr) {
			if (false === ($status = sendTrxControlCmd($ipAddr, '-rx'))) {
				debugLog('trx-config.php: get_rx_status failed: ' . $ipAddr);
			} else {
				if ($status != 'Unknown command') { // r740 or higher host
					$rxStatus = explode(',', $status);
					// rx, On/Off/Disabled/Unknown, volume, volume_mute_1/0, mastervol_opt_in_1/0, hostname
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
		$_SESSION['notify']['title'] = NOTIFY_TITLE_ALERT;
		$_SESSION['notify']['msg'] = $_SESSION['rx_hostnames'];
	} else {
		$_SESSION['notify']['title'] = NOTIFY_TITLE_INFO;
		$_SESSION['notify']['msg'] = 'Found: ' . $_SESSION['rx_hostnames'];
	}
}
if (isset($_POST['update_multiroom_tx_bfr'])) {
	if (isset($_POST['multiroom_tx_bfr']) && $_POST['multiroom_tx_bfr'] != $cfgMultiroom['tx_bfr']) {
		$result = sqlQuery("UPDATE cfg_multiroom SET value='" . $_POST['multiroom_tx_bfr'] . "' " . "WHERE param='tx_bfr'", $dbh);
		$notify = $_SESSION['multiroom_tx'] == 'On' ?
			array('title' => NOTIFY_TITLE_INFO, 'msg' => 'Sender restarted.') :
			array('title' => '', 'msg' => '');
		submitJob('multiroom_tx_restart', '', $notify['title'], $notify['msg']);
	}
}
if (isset($_POST['update_multiroom_tx_frame_size'])) {
	if (isset($_POST['multiroom_tx_frame_size']) && $_POST['multiroom_tx_frame_size'] != $cfgMultiroom['tx_frame_size']) {
		$result = sqlQuery("UPDATE cfg_multiroom SET value='" . $_POST['multiroom_tx_frame_size'] . "' " . "WHERE param='tx_frame_size'", $dbh);
		$notify = $_SESSION['multiroom_tx'] == 'On' ?
			array('title' => NOTIFY_TITLE_INFO, 'msg' => 'Sender restarted.') :
			array('title' => '', 'msg' => '');
		submitJob('multiroom_tx_restart', '', $notify['title'], $notify['msg']);
	}
}
if (isset($_POST['update_multiroom_tx_rtprio'])) {
	if (isset($_POST['multiroom_tx_rtprio']) && $_POST['multiroom_tx_rtprio'] != $cfgMultiroom['tx_rtprio']) {
		$result = sqlQuery("UPDATE cfg_multiroom SET value='" . $_POST['multiroom_tx_rtprio'] . "' " . "WHERE param='tx_rtprio'", $dbh);
		$notify = $_SESSION['multiroom_tx'] == 'On' ?
			array('title' => NOTIFY_TITLE_INFO, 'msg' => 'Sender restarted.') :
			array('title' => '', 'msg' => '');
		submitJob('multiroom_tx_restart', '', $notify['title'], $notify['msg']);
	}
}
if (isset($_POST['update_multiroom_tx_query_timeout'])) {
	if (isset($_POST['multiroom_tx_query_timeout']) && $_POST['multiroom_tx_query_timeout'] != $cfgMultiroom['multiroom_tx_query_timeout']) {
		$result = sqlQuery("UPDATE cfg_multiroom SET value='" . $_POST['multiroom_tx_query_timeout'] . "' " . "WHERE param='tx_query_timeout'", $dbh);
	}
}

//
// RECEIVER
//

if (isset($_POST['update_multiroom_rx'])) {
	if (isset($_POST['multiroom_rx']) && $_POST['multiroom_rx'] != $_SESSION['multiroom_rx']) {
		phpSession('write', 'multiroom_rx', $_POST['multiroom_rx']);
		submitJob('multiroom_rx');
	}
}
if (isset($_POST['update_multiroom_rx_mastervol_opt_in'])) {
	if (isset($_POST['multiroom_rx_mastervol_opt_in']) && $_POST['multiroom_rx_mastervol_opt_in'] != $cfgMultiroom['rx_mastervol_opt_in']) {
		$result = sqlQuery("UPDATE cfg_multiroom SET value='" . $_POST['multiroom_rx_mastervol_opt_in'] . "' " . "WHERE param='rx_mastervol_opt_in'", $dbh);
	}
}
if (isset($_POST['update_multiroom_rx_alsa_output_mode'])) {
	if (isset($_POST['multiroom_rx_alsa_output_mode']) && $_POST['multiroom_rx_alsa_output_mode'] != $cfgMultiroom['rx_alsa_output_mode']) {
		$result = sqlQuery("UPDATE cfg_multiroom SET value='" . $_POST['multiroom_rx_alsa_output_mode'] . "' " . "WHERE param='rx_alsa_output_mode'", $dbh);
		$notify = $_SESSION['multiroom_rx'] == 'On' ?
			array('title' => NOTIFY_TITLE_INFO, 'msg' => 'Receiver restarted.') :
			array('title' => '', 'msg' => '');
		submitJob('multiroom_rx_restart', '', $notify['title'], $notify['msg']);
	}
}
if (isset($_POST['update_multiroom_rx_alsavol'])) {
	if (isset($_POST['multiroom_rx_alsavol'])) {
		$result = sqlQuery("UPDATE cfg_multiroom SET value='" . $_POST['multiroom_rx_alsavol'] . "' " . "WHERE param='rx_alsa_volume_max'", $dbh);
		sysCmd('/var/www/util/sysutil.sh set-alsavol "' . $_SESSION['amixname'] . '" ' . $_POST['multiroom_rx_alsavol']);
	}
}
if (isset($_POST['multiroom_rx_restart'])) {
	submitJob('multiroom_rx_restart', '', NOTIFY_TITLE_INFO, 'Receiver restarted.');
}
if (isset($_POST['update_multiroom_rx_bfr'])) {
	if (isset($_POST['multiroom_rx_bfr']) && $_POST['multiroom_rx_bfr'] != $cfgMultiroom['rx_bfr']) {
		$result = sqlQuery("UPDATE cfg_multiroom SET value='" . $_POST['multiroom_rx_bfr'] . "' " . "WHERE param='rx_bfr'", $dbh);
		$notify = $_SESSION['multiroom_rx'] == 'On' ?
			array('title' => NOTIFY_TITLE_INFO, 'msg' => 'Receiver restarted.') :
			array('title' => '', 'msg' => '');
		submitJob('multiroom_rx_restart', '', $notify['title'], $notify['msg']);
	}
}
if (isset($_POST['update_multiroom_rx_jitter_bfr'])) {
	if (isset($_POST['multiroom_rx_jitter_bfr']) && $_POST['multiroom_rx_jitter_bfr'] != $cfgMultiroom['rx_jitter_bfr']) {
		$result = sqlQuery("UPDATE cfg_multiroom SET value='" . $_POST['multiroom_rx_jitter_bfr'] . "' " . "WHERE param='rx_jitter_bfr'", $dbh);
		$notify = $_SESSION['multiroom_rx'] == 'On' ?
			array('title' => NOTIFY_TITLE_INFO, 'msg' => 'Receiver restarted.') :
			array('title' => '', 'msg' => '');
		submitJob('multiroom_rx_restart', '', $notify['title'], $notify['msg']);
	}
}
if (isset($_POST['update_multiroom_rx_frame_size'])) {
	if (isset($_POST['multiroom_rx_frame_size']) && $_POST['multiroom_rx_frame_size'] != $cfgMultiroom['rx_frame_size']) {
		$result = sqlQuery("UPDATE cfg_multiroom SET value='" . $_POST['multiroom_rx_frame_size'] . "' " . "WHERE param='rx_frame_size'", $dbh);
		$notify = $_SESSION['multiroom_rx'] == 'On' ?
			array('title' => NOTIFY_TITLE_INFO, 'msg' => 'Receiver restarted.') :
			array('title' => '', 'msg' => '');
		submitJob('multiroom_rx_restart', '', $notify['title'], $notify['msg']);
	}
}
if (isset($_POST['update_multiroom_rx_rtprio'])) {
	if (isset($_POST['multiroom_rx_rtprio']) && $_POST['multiroom_rx_rtprio'] != $cfgMultiroom['rx_rtprio']) {
		$result = sqlQuery("UPDATE cfg_multiroom SET value='" . $_POST['multiroom_rx_rtprio'] . "' " . "WHERE param='rx_rtprio'", $dbh);
		$notify = $_SESSION['multiroom_rx'] == 'On' ?
			array('title' => NOTIFY_TITLE_INFO, 'msg' => 'Receiver restarted.') :
			array('title' => '', 'msg' => '');
		submitJob('multiroom_rx_restart', '', $notify['title'], $notify['msg']);
	}
}

phpSession('close');

$params = sqlRead('cfg_multiroom', $dbh);
foreach ($params as $row) {
    $cfgMultiroom[$row['param']] = $row['value'];
}

// Feature and button states
$_feat_multiroom = $_SESSION['feat_bitmask'] & FEAT_MULTIROOM ? '' : 'hide';
$_dsp_on = ($_SESSION['crossfeed'] == 'Off' && $_SESSION['eqfa12p'] == 'Off' && $_SESSION['alsaequal'] == 'Off' &&
	$_SESSION['camilladsp'] == 'off' && $_SESSION['invert_polarity'] == '0') ? false : true;
if ($_SESSION['multiroom_tx'] == 'Off') {
	$_multiroom_tx_disable = ($_SESSION['alsa_loopback'] == 'Off' || $_dsp_on == true) ? 'disabled' : '';
} else {
	$_multiroom_tx_disable = '';
}

$_tx_restart_btn_disable = $_SESSION['multiroom_tx'] == 'Off' ? 'disabled' : '';
$_tx_restart_link_disable = $_SESSION['multiroom_tx'] == 'Off' ? 'onclick="return false;"' : '';
$_tx_adv_options_hide = $_SESSION['tx_adv_toggle'] == 'Hide' ? '' : 'hide';
$_multiroom_rx_disable = ($_SESSION['mpdmixer'] == 'null') ? 'disabled' : ''; // Don't allow CamillaDSP Volume
$_rx_restart_btn_disable = $_SESSION['multiroom_rx'] != 'On' ? 'disabled' : '';
$_rx_restart_link_disable = $_SESSION['multiroom_rx'] != 'On' ? 'onclick="return false;"' : '';
$_multiroom_initvol_disable = (!isset($_SESSION['rx_hostnames']) || empty($_SESSION['rx_hostnames'])) ? 'disabled' : '';
$_rx_adv_options_hide = $_SESSION['rx_adv_toggle'] == 'Hide' ? '' : 'hide';

// Sender
$autoClick = " onchange=\"autoClick('#btn-set-multiroom-tx');\" " . $_multiroom_tx_disable;
$_select['multiroom_tx_on']  .= "<input type=\"radio\" name=\"multiroom_tx\" id=\"toggle-multiroom-tx-1\" value=\"On\" " . (($_SESSION['multiroom_tx'] == 'On') ? "checked=\"checked\"" : "") . $autoClick . ">\n";
$_select['multiroom_tx_off'] .= "<input type=\"radio\" name=\"multiroom_tx\" id=\"toggle-multiroom-tx-2\" value=\"Off\" " . (($_SESSION['multiroom_tx'] == 'Off') ? "checked=\"checked\"" : "") . $autoClick . ">\n";
$_alsa_loopback_disable= '';
$autoClick = " onchange=\"autoClick('#btn-set-alsa-loopback');\" " . $_alsa_loopback_disable;
$_select['alsa_loopback_on']  .= "<input type=\"radio\" name=\"alsa_loopback\" id=\"toggle-alsa-loopback-1\" value=\"On\" " . (($_SESSION['alsa_loopback'] == 'On') ? "checked=\"checked\"" : "") . $autoClick . ">\n";
$_select['alsa_loopback_off'] .= "<input type=\"radio\" name=\"alsa_loopback\" id=\"toggle-alsa-loopback-2\" value=\"Off\" " . (($_SESSION['alsa_loopback'] == 'Off') ? "checked=\"checked\"" : "") . $autoClick . ">\n";
if (!isset($_SESSION['rx_hostnames'])) {
	$_rx_hostnames = 'Discover has not been run yet';
} else {
	$_rx_hostnames = $_SESSION['rx_hostnames'] == 'No receivers found' ? $_SESSION['rx_hostnames'] : 'Found: ' . $_SESSION['rx_hostnames'];
}
$_multiroom_initvol = $cfgMultiroom['initial_volume'];
// Advanced options
$_select['multiroom_tx_bfr'] .= "<option value=\"16\" " . (($cfgMultiroom['tx_bfr'] == '16') ? "selected" : "") . ">16</option>\n";
$_select['multiroom_tx_bfr'] .= "<option value=\"32\" " . (($cfgMultiroom['tx_bfr'] == '32') ? "selected" : "") . ">32</option>\n";
$_select['multiroom_tx_bfr'] .= "<option value=\"48\" " . (($cfgMultiroom['tx_bfr'] == '48') ? "selected" : "") . ">48</option>\n";
$_select['multiroom_tx_bfr'] .= "<option value=\"64\" " . (($cfgMultiroom['tx_bfr'] == '64') ? "selected" : "") . ">64 (Default)</option>\n";
$_select['multiroom_tx_bfr'] .= "<option value=\"96\" " . (($cfgMultiroom['tx_bfr'] == '96') ? "selected" : "") . ">96</option>\n";
$_select['multiroom_tx_bfr'] .= "<option value=\"128\" " . (($cfgMultiroom['tx_bfr'] == '128') ? "selected" : "") . ">128</option>\n";
$_select['multiroom_tx_bfr'] .= "<option value=\"256\" " . (($cfgMultiroom['tx_bfr'] == '256') ? "selected" : "") . ">256</option>\n";
$_select['multiroom_tx_frame_size'] .= "<option value=\"120\" " . (($cfgMultiroom['tx_frame_size'] == '120') ? "selected" : "") . ">2.5</option>\n";
$_select['multiroom_tx_frame_size'] .= "<option value=\"240\" " . (($cfgMultiroom['tx_frame_size'] == '240') ? "selected" : "") . ">7.5</option>\n";
$_select['multiroom_tx_frame_size'] .= "<option value=\"480\" " . (($cfgMultiroom['tx_frame_size'] == '480') ? "selected" : "") . ">10</option>\n";
$_select['multiroom_tx_frame_size'] .= "<option value=\"960\" " . (($cfgMultiroom['tx_frame_size'] == '960') ? "selected" : "") . ">20 (Default)</option>\n";
$_select['multiroom_tx_frame_size'] .= "<option value=\"1920\" " . (($cfgMultiroom['tx_frame_size'] == '1920') ? "selected" : "") . ">40</option>\n";
//$_select['multiroom_tx_frame_size'] .= "<option value=\"2880\" " . (($cfgMultiroom['tx_frame_size'] == '2880') ? "selected" : "") . ">60</option>\n";
$_multiroom_tx_rtprio = $cfgMultiroom['tx_rtprio'];
$_multiroom_tx_query_timeout = $cfgMultiroom['tx_query_timeout'];

// Receiver
$_select['multiroom_rx'] .= "<option value=\"Disabled\" " . (($_SESSION['multiroom_rx'] == 'Disabled') ? "selected" : "") . ">Disabled</option>\n";
$_select['multiroom_rx'] .= "<option value=\"On\" " . (($_SESSION['multiroom_rx'] == 'On') ? "selected" : "") . ">On</option>\n";
$_select['multiroom_rx'] .= "<option value=\"Off\" " . (($_SESSION['multiroom_rx'] == 'Off') ? "selected" : "") . ">Off</option>\n";
$autoClick = " onchange=\"autoClick('#btn-set-multiroom-rx-mastervol-opt-in');\" " . $_localui_btn_disable;
$_select['multiroom_rx_mastervol_opt_in_on'] .= "<input type=\"radio\" name=\"multiroom_rx_mastervol_opt_in\" id=\"toggle-multiroom-rx-mastervol-opt-in-1\" value=\"1\" " . (($cfgMultiroom['rx_mastervol_opt_in'] == '1') ? "checked=\"checked\"" : "") . $autoClick . ">\n";
$_select['multiroom_rx_mastervol_opt_in_off'] .= "<input type=\"radio\" name=\"multiroom_rx_mastervol_opt_in\" id=\"toggle-multiroom-rx-mastervol-opt-in-2\" value=\"0\" " . (($cfgMultiroom['rx_mastervol_opt_in'] == '0') ? "checked=\"checked\"" : "") . $autoClick . ">\n";
if (substr($_SESSION['hdwrrev'], 3, 1) >= 3 && isHDMIDevice($_SESSION['adevname'])) {
	$_select['multiroom_rx_alsa_output_mode'] .= "<option value=\"iec958\" " . (($cfgMultiroom['rx_alsa_output_mode'] == 'iec958') ? "selected" : "") . ">" . ALSA_OUTPUT_MODE_NAME['iec958'] . "</option>\n";
}
$_select['multiroom_rx_alsa_output_mode'] .= "<option value=\"plughw\" " . (($cfgMultiroom['rx_alsa_output_mode'] == 'plughw') ? "selected" : "") . ">" . ALSA_OUTPUT_MODE_NAME['plughw'] . "</option>\n";
$_select['multiroom_rx_alsa_output_mode'] .= "<option value=\"hw\" " . (($cfgMultiroom['rx_alsa_output_mode'] == 'hw') ? "selected" : "") . ">" . ALSA_OUTPUT_MODE_NAME['hw'] . "</option>\n";
$_multiroom_rx_alsavol_max = $cfgMultiroom['rx_alsa_volume_max'];;
$_multiroom_rx_alsavol_pct = sysCmd('/var/www/util/sysutil.sh get-alsavol ' . '"' . $_SESSION['amixname'] . '"')[0];
if (stripos($_multiroom_rx_alsavol_percent, 'amixer:') === false) {
	$_multiroom_rx_alsavol_msg = '<span class="config-msg-static">Current ALSA volume: ' . $_multiroom_rx_alsavol_pct . '</span>';
	$_multiroom_rx_alsavol_disable = '';
} else {
	$_multiroom_rx_alsavol_msg = '<span class="config-msg-static"><i>Hardware volume controller not detected</i></span>';
	$_multiroom_rx_alsavol_disable = 'disabled';
}
// Advanced options
$_select['multiroom_rx_bfr'] .= "<option value=\"16\" " . (($cfgMultiroom['rx_bfr'] == '16') ? "selected" : "") . ">16</option>\n";
$_select['multiroom_rx_bfr'] .= "<option value=\"32\" " . (($cfgMultiroom['rx_bfr'] == '32') ? "selected" : "") . ">32</option>\n";
$_select['multiroom_rx_bfr'] .= "<option value=\"48\" " . (($cfgMultiroom['rx_bfr'] == '48') ? "selected" : "") . ">48</option>\n";
$_select['multiroom_rx_bfr'] .= "<option value=\"64\" " . (($cfgMultiroom['rx_bfr'] == '64') ? "selected" : "") . ">64 (Default)</option>\n";
$_select['multiroom_rx_bfr'] .= "<option value=\"96\" " . (($cfgMultiroom['rx_bfr'] == '96') ? "selected" : "") . ">96</option>\n";
$_select['multiroom_rx_bfr'] .= "<option value=\"128\" " . (($cfgMultiroom['rx_bfr'] == '128') ? "selected" : "") . ">128</option>\n";
$_select['multiroom_rx_bfr'] .= "<option value=\"256\" " . (($cfgMultiroom['rx_bfr'] == '256') ? "selected" : "") . ">256</option>\n";
$_select['multiroom_rx_jitter_bfr'] .= "<option value=\"16\" " . (($cfgMultiroom['rx_jitter_bfr'] == '16') ? "selected" : "") . ">16</option>\n";
$_select['multiroom_rx_jitter_bfr'] .= "<option value=\"32\" " . (($cfgMultiroom['rx_jitter_bfr'] == '32') ? "selected" : "") . ">32</option>\n";
$_select['multiroom_rx_jitter_bfr'] .= "<option value=\"48\" " . (($cfgMultiroom['rx_jitter_bfr'] == '48') ? "selected" : "") . ">48</option>\n";
$_select['multiroom_rx_jitter_bfr'] .= "<option value=\"64\" " . (($cfgMultiroom['rx_jitter_bfr'] == '64') ? "selected" : "") . ">64 (Default)</option>\n";
$_select['multiroom_rx_jitter_bfr'] .= "<option value=\"96\" " . (($cfgMultiroom['rx_jitter_bfr'] == '96') ? "selected" : "") . ">96</option>\n";
$_select['multiroom_rx_jitter_bfr'] .= "<option value=\"128\" " . (($cfgMultiroom['rx_jitter_bfr'] == '128') ? "selected" : "") . ">128</option>\n";
$_select['multiroom_rx_frame_size'] .= "<option value=\"120\" " . (($cfgMultiroom['rx_frame_size'] == '120') ? "selected" : "") . ">2.5</option>\n";
$_select['multiroom_rx_frame_size'] .= "<option value=\"240\" " . (($cfgMultiroom['rx_frame_size'] == '240') ? "selected" : "") . ">7.5</option>\n";
$_select['multiroom_rx_frame_size'] .= "<option value=\"480\" " . (($cfgMultiroom['rx_frame_size'] == '480') ? "selected" : "") . ">10</option>\n";
$_select['multiroom_rx_frame_size'] .= "<option value=\"960\" " . (($cfgMultiroom['rx_frame_size'] == '960') ? "selected" : "") . ">20 (Default)</option>\n";
$_select['multiroom_rx_frame_size'] .= "<option value=\"1920\" " . (($cfgMultiroom['rx_frame_size'] == '1920') ? "selected" : "") . ">40</option>\n";
//$_select['multiroom_rx_frame_size'] .= "<option value=\"2880\" " . (($cfgMultiroom['rx_frame_size'] == '2880') ? "selected" : "") . ">60</option>\n";
$_multiroom_rx_rtprio = $cfgMultiroom['rx_rtprio'];

waitWorker('trx-config');

$tpl = "trx-config.html";
$section = basename(__FILE__, '.php');
storeBackLink($section, $tpl);

include('header.php');
eval("echoTemplate(\"" . getTemplate("templates/$tpl") . "\");");
include('footer.php');
