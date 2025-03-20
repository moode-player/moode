<?php
/*
 * SPDX-License-Identifier: GPL-3.0-or-later
 * Copyright 2014 The moOde audio player project / Tim Curtis
*/

require_once __DIR__ . '/inc/alsa.php';
require_once __DIR__ . '/inc/common.php';
require_once __DIR__ . '/inc/network.php';
require_once __DIR__ . '/inc/session.php';
require_once __DIR__ . '/inc/sql.php';

$dbh = sqlConnect();
phpSession('open');
updAlsaVolume($_SESSION['amixname']);

chkVariables($_POST);

// Bluetooth
if (isset($_POST['update_bt_settings'])) {
	$currentBtName = $_SESSION['btname'];

	if (isset($_POST['btname']) && $_POST['btname'] != $_SESSION['btname']) {
		$update = true;
		phpSession('write', 'btname', $_POST['btname']);
	}
	if (isset($_POST['btsvc']) && $_POST['btsvc'] != $_SESSION['btsvc']) {
		$update = true;
		phpSession('write', 'btsvc', $_POST['btsvc']);
		if ($_POST['btsvc'] == '0') {
			phpSession('write', 'pairing_agent', '0');
		}
	}
	if (isset($update)) {
		submitJob('btsvc', '"' . $currentBtName . '" ' . '"' . $_POST['btname'] . '"');
	}
}
if (isset($_POST['btrestart']) && $_POST['btrestart'] == 1 && $_SESSION['btsvc'] == '1') {
	submitJob('btsvc', '', NOTIFY_TITLE_INFO, NAME_BLUETOOTH . NOTIFY_MSG_SVC_MANUAL_RESTART);
}
if (isset($_POST['update_bt_pin_code']) && $_POST['update_bt_pin_code'] != 'Pincode set') {
	phpSession('write', 'bt_pin_code', $_POST['bt_pin_code']);
	$notify = $_SESSION['btsvc'] == '1' ?
		array('title' => NOTIFY_TITLE_INFO, 'msg' => NAME_BLUETOOTH_PAIRING_AGENT . NOTIFY_MSG_SVC_RESTARTED) :
		array('title' => '', 'msg' => '');
	submitJob('bt_pin_code', $_SESSION['bt_pin_code'], $notify['title'], $notify['msg']);
}
if (isset($_POST['update_alsavolume_max_bt'])) {
	$_SESSION['alsavolume_max_bt'] = $_POST['alsavolume_max_bt'];
}
if (isset($_POST['update_cdspvolume_max_bt'])) {
	$_SESSION['cdspvolume_max_bt'] = $_POST['cdspvolume_max_bt'];
}
if (isset($_POST['update_bt_auto_disconnect']) && $_POST['bt_auto_disconnect'] != $_SESSION['bt_auto_disconnect']) {
	$_SESSION['bt_auto_disconnect'] = $_POST['bt_auto_disconnect'];
	submitJob('reset_bt_auto_disconnect', '', NOTIFY_TITLE_INFO, 'Auto-disconnect time updated');
}

if (isset($_POST['update_rsmafterbt'])) {
	phpSession('write', 'rsmafterbt', $_POST['rsmafterbt']);
}

// AirPlay
if (isset($_POST['update_airplay_settings'])) {
	if (isset($_POST['airplayname']) && $_POST['airplayname'] != $_SESSION['airplayname']) {
		$update = true;
		phpSession('write', 'airplayname', $_POST['airplayname']);
	}
	if (isset($_POST['airplaysvc']) && $_POST['airplaysvc'] != $_SESSION['airplaysvc']) {
		$update = true;
		phpSession('write', 'airplaysvc', $_POST['airplaysvc']);
	}
	if (isset($update)) {
		submitJob('airplaysvc');
	}
}
if (isset($_POST['update_rsmafterapl'])) {
	phpSession('write', 'rsmafterapl', $_POST['rsmafterapl']);
}
if (isset($_POST['airplayrestart']) && $_POST['airplayrestart'] == 1 && $_SESSION['airplaysvc'] == '1') {
	submitJob('airplaysvc', '', NOTIFY_TITLE_INFO, NAME_AIRPLAY . NOTIFY_MSG_SVC_MANUAL_RESTART);
}

// Spotify Connect
if (isset($_POST['update_spotify_settings'])) {
	if (isset($_POST['spotifyname']) && $_POST['spotifyname'] != $_SESSION['spotifyname']) {
		$update = true;
		phpSession('write', 'spotifyname', $_POST['spotifyname']);
	}
	if (isset($_POST['spotifysvc']) && $_POST['spotifysvc'] != $_SESSION['spotifysvc']) {
		$update = true;
		phpSession('write', 'spotifysvc', $_POST['spotifysvc']);
	}
	if (isset($update)) {
		submitJob('spotifysvc');
	}
}
if (isset($_POST['update_rsmafterspot'])) {
	phpSession('write', 'rsmafterspot', $_POST['rsmafterspot']);
}
if (isset($_POST['spotifyrestart']) && $_POST['spotifyrestart'] == 1 && $_SESSION['spotifysvc'] == '1') {
	submitJob('spotifysvc', '', NOTIFY_TITLE_INFO, NAME_SPOTIFY . NOTIFY_MSG_SVC_MANUAL_RESTART);
}
if (isset($_POST['spotify_clear_credentials']) && $_POST['spotify_clear_credentials'] == 1) {
	submitJob('spotify_clear_credentials', '', NOTIFY_TITLE_INFO, 'Credential cache cleared');
}

// Deezer Connect
if (isset($_POST['update_deezer_settings'])) {
	if (isset($_POST['deezername']) && $_POST['deezername'] != $_SESSION['deezername']) {
		$update = true;
		phpSession('write', 'deezername', $_POST['deezername']);
	}
	if (isset($_POST['deezersvc']) && $_POST['deezersvc'] != $_SESSION['deezersvc']) {
		$update = true;
		phpSession('write', 'deezersvc', $_POST['deezersvc']);
	}
	if (isset($update)) {
		submitJob('deezersvc');
	}
}
if (isset($_POST['update_rsmafterdeez'])) {
	phpSession('write', 'rsmafterdeez', $_POST['rsmafterdeez']);
}
if (isset($_POST['deezerrestart']) && $_POST['deezerrestart'] == 1 && $_SESSION['deezersvc'] == '1') {
	submitJob('deezersvc', '', NOTIFY_TITLE_INFO, NAME_DEEZER . NOTIFY_MSG_SVC_MANUAL_RESTART);
}

// Squeezelite
if (isset($_POST['update_sl_settings'])) {
	if (isset($_POST['slsvc']) && $_POST['slsvc'] != $_SESSION['slsvc']) {
		$update = true;
		phpSession('write', 'slsvc', $_POST['slsvc']);
	}
	if (isset($update)) {
		if ($_POST['slsvc'] == 0) {
			phpSession('write', 'rsmaftersl', 'No');
		}
		submitJob('slsvc');
	}
}
if (isset($_POST['update_rsmaftersl'])) {
	phpSession('write', 'rsmaftersl', $_POST['rsmaftersl']);
}
if (isset($_POST['slrestart']) && $_POST['slrestart'] == 1) {
	phpSession('write', 'rsmaftersl', 'No');
	submitJob('slrestart', '', NOTIFY_TITLE_INFO, NAME_SQUEEZELITE . NOTIFY_MSG_SVC_MANUAL_RESTART);
}

// UPnP client for MPD
if (isset($_POST['update_upnp_settings'])) {
	$currentUpnpName = $_SESSION['upnpname'];
	if (isset($_POST['upnpname']) && $_POST['upnpname'] != $_SESSION['upnpname']) {
		$update = true;
		phpSession('write', 'upnpname', $_POST['upnpname']);
	}
	if (isset($_POST['upnpsvc']) && $_POST['upnpsvc'] != $_SESSION['upnpsvc']) {
		$update = true;
		phpSession('write', 'upnpsvc', $_POST['upnpsvc']);
	}
	if (isset($update)) {
		submitJob('upnpsvc', '"' . $currentUpnpName . '" ' . '"' . $_POST['upnpname'] . '"');
	}
}
if (isset($_POST['upnprestart']) && $_POST['upnprestart'] == 1 && $_SESSION['upnpsvc'] == '1') {
	submitJob('upnpsvc', '', NOTIFY_TITLE_INFO, NAME_UPNP . NOTIFY_MSG_SVC_MANUAL_RESTART);
}

// Plexamp
if (isset($_POST['update_pa_settings'])) {
	if (isset($_POST['pasvc']) && $_POST['pasvc'] != $_SESSION['pasvc']) {
		$update = true;
		phpSession('write', 'pasvc', $_POST['pasvc']);
	}
	if (isset($update)) {
		submitJob('pasvc');
	}
}
if (isset($_POST['update_alsavolume_max_pa'])) {
	$_SESSION['alsavolume_max_pa'] = $_POST['alsavolume_max_pa'];
}
if (isset($_POST['update_rsmafterpa'])) {
	phpSession('write', 'rsmafterpa', $_POST['rsmafterpa']);
}
if (isset($_POST['parestart']) && $_POST['parestart'] == 1) {
	submitJob('parestart', '', NOTIFY_TITLE_INFO, NAME_PLEXAMP . NOTIFY_MSG_SVC_MANUAL_RESTART);
}

// RoonBridge
if (isset($_POST['update_rb_settings'])) {
	if (isset($_POST['rbsvc']) && $_POST['rbsvc'] != $_SESSION['rbsvc']) {
		$update = true;
		phpSession('write', 'rbsvc', $_POST['rbsvc']);
	}
	if (isset($update)) {
		submitJob('rbsvc');
	}
}
if (isset($_POST['update_rsmafterrb'])) {
	phpSession('write', 'rsmafterrb', $_POST['rsmafterrb']);
}
if (isset($_POST['rbrestart']) && $_POST['rbrestart'] == 1) {
	submitJob('rbrestart', '', NOTIFY_TITLE_INFO, NAME_ROONBRIDGE . NOTIFY_MSG_SVC_MANUAL_RESTART);
}

phpSession('close');

// Bluetooth
$_feat_bluetooth = $_SESSION['feat_bitmask'] & FEAT_BLUETOOTH ? '' : 'hide';
$_SESSION['btsvc'] == '1' ? $_bt_btn_disable = '' : $_bt_btn_disable = 'disabled';
$_SESSION['btsvc'] == '1' ? $_bt_link_disable = '' : $_bt_link_disable = 'onclick="return false;"';
$autoClick = " onchange=\"autoClick('#btn-set-btsvc');\"";
$_select['btsvc_on']  .= "<input type=\"radio\" name=\"btsvc\" id=\"toggle-btsvc-1\" value=\"1\" " . (($_SESSION['btsvc'] == '1') ? "checked=\"checked\"" : "") . $autoClick . ">\n";
$_select['btsvc_off'] .= "<input type=\"radio\" name=\"btsvc\" id=\"toggle-btsvc-2\" value=\"0\" " . (($_SESSION['btsvc'] == '0') ? "checked=\"checked\"" : "") . $autoClick . ">\n";
$_select['btname'] = $_SESSION['btname'];
if (empty($_SESSION['bt_pin_code'])) {
	$_bt_pin_code = '';
	$_pwd_input_format = 'password';
} else {
	$_bt_pin_code = 'Pincode set';
	$_pwd_input_format = 'text';
}

if ($_SESSION['alsavolume'] == 'none') {
	$_alsavolume_max_bt = '';
	$_alsavolume_max_bt_readonly = 'readonly';
	$_alsavolume_max_bt_disable = 'disabled';
	$_alsavolume_max_bt_msg = '<span class="config-msg-static"><i>Hardware volume controller not detected</i></span>';
} else {
	$_alsavolume_max_bt = $_SESSION['alsavolume_max_bt'];
	$_alsavolume_max_bt_readonly = '';
	$_alsavolume_max_bt_disable = '';
	$_alsavolume_max_bt_msg = '';
}
$_cdspvolume_max_bt = $_SESSION['cdspvolume_max_bt'];
$_select['bt_auto_disconnect'] .= "<option value=\"never\" " . (($_SESSION['bt_auto_disconnect'] == 'never') ? "selected" : "") . ">Never (Default)</option>\n";
$_select['bt_auto_disconnect'] .= "<option value=\"10\" " . (($_SESSION['bt_auto_disconnect'] == '10') ? "selected" : "") . ">10 secs</option>\n";
$_select['bt_auto_disconnect'] .= "<option value=\"20\" " . (($_SESSION['bt_auto_disconnect'] == '20') ? "selected" : "") . ">20 secs</option>\n";
$_select['bt_auto_disconnect'] .= "<option value=\"30\" " . (($_SESSION['bt_auto_disconnect'] == '30') ? "selected" : "") . ">30 secs</option>\n";
$_select['bt_auto_disconnect'] .= "<option value=\"60\" " . (($_SESSION['bt_auto_disconnect'] == '60') ? "selected" : "") . ">60 secs</option>\n";
$_select['bt_auto_disconnect'] .= "<option value=\"90\" " . (($_SESSION['bt_auto_disconnect'] == '90') ? "selected" : "") . ">90 secs</option>\n";
$_select['bt_auto_disconnect'] .= "<option value=\"120\" " . (($_SESSION['bt_auto_disconnect'] == '120') ? "selected" : "") . ">2 mins</option>\n";
$_select['bt_auto_disconnect'] .= "<option value=\"300\" " . (($_SESSION['bt_auto_disconnect'] == '300') ? "selected" : "") . ">5 mins</option>\n";
$autoClick = " onchange=\"autoClick('#btn-set-rsmafterbt');\" " . $_bt_btn_disable;
$_select['rsmafterbt_on'] .= "<input type=\"radio\" name=\"rsmafterbt\" id=\"toggle-rsmafterbt-1\" value=\"1\" " . (($_SESSION['rsmafterbt'] == '1') ? "checked=\"checked\"" : "") . $autoClick . ">\n";
$_select['rsmafterbt_off']  .= "<input type=\"radio\" name=\"rsmafterbt\" id=\"toggle-rsmafterbt-2\" value=\"0\" " . (($_SESSION['rsmafterbt'] == '0') ? "checked=\"checked\"" : "") . $autoClick . ">\n";

// AirPlay
$_feat_airplay = $_SESSION['feat_bitmask'] & FEAT_AIRPLAY ? '' : 'hide';
$_SESSION['airplaysvc'] == '1' ? $_airplay_btn_disable = '' : $_airplay_btn_disable = 'disabled';
$_SESSION['airplaysvc'] == '1' ? $_airplay_link_disable = '' : $_airplay_link_disable = 'onclick="return false;"';
$autoClick = " onchange=\"autoClick('#btn-set-airplaysvc');\"";
$_select['airplaysvc_on']  .= "<input type=\"radio\" name=\"airplaysvc\" id=\"toggle-airplaysvc-1\" value=\"1\" " . (($_SESSION['airplaysvc'] == '1') ? "checked=\"checked\"" : "") . $autoClick . ">\n";
$_select['airplaysvc_off'] .= "<input type=\"radio\" name=\"airplaysvc\" id=\"toggle-airplaysvc-2\" value=\"0\" " . (($_SESSION['airplaysvc'] == '0') ? "checked=\"checked\"" : "") . $autoClick . ">\n";
$_select['airplayname'] = $_SESSION['airplayname'];
$autoClick = " onchange=\"autoClick('#btn-set-rsmafterapl');\" " . $_airplay_btn_disable;
$_select['rsmafterapl_on'] .= "<input type=\"radio\" name=\"rsmafterapl\" id=\"toggle-rsmafterapl-1\" value=\"Yes\" " . (($_SESSION['rsmafterapl'] == 'Yes') ? "checked=\"checked\"" : "") . $autoClick . ">\n";
$_select['rsmafterapl_off']  .= "<input type=\"radio\" name=\"rsmafterapl\" id=\"toggle-rsmafterapl-2\" value=\"No\" " . (($_SESSION['rsmafterapl'] == 'No') ? "checked=\"checked\"" : "") . $autoClick . ">\n";

// Spotify Connect
$_feat_spotify = $_SESSION['feat_bitmask'] & FEAT_SPOTIFY ? '' : 'hide';
$_SESSION['spotifysvc'] == '1' ? $_spotify_btn_disable = '' : $_spotify_btn_disable = 'disabled';
$_SESSION['spotifysvc'] == '1' ? $_spotify_link_disable = '' : $_spotify_link_disable = 'onclick="return false;"';
$autoClick = " onchange=\"autoClick('#btn-set-spotifysvc');\"";
$_select['spotifysvc_on']  .= "<input type=\"radio\" name=\"spotifysvc\" id=\"toggle-spotifysvc-1\" value=\"1\" " . (($_SESSION['spotifysvc'] == '1') ? "checked=\"checked\"" : "") . $autoClick . ">\n";
$_select['spotifysvc_off'] .= "<input type=\"radio\" name=\"spotifysvc\" id=\"toggle-spotifysvc-2\" value=\"0\" " . (($_SESSION['spotifysvc'] == '0') ? "checked=\"checked\"" : "") . $autoClick . ">\n";
$_select['spotifyname'] = $_SESSION['spotifyname'];
$autoClick = " onchange=\"autoClick('#btn-set-rsmafterspot');\" " . $_spotify_btn_disable;
$_select['rsmafterspot_on'] .= "<input type=\"radio\" name=\"rsmafterspot\" id=\"toggle-rsmafterspot-1\" value=\"Yes\" " . (($_SESSION['rsmafterspot'] == 'Yes') ? "checked=\"checked\"" : "") . $autoClick . ">\n";
$_select['rsmafterspot_off']  .= "<input type=\"radio\" name=\"rsmafterspot\" id=\"toggle-rsmafterspot-2\" value=\"No\" " . (($_SESSION['rsmafterspot'] == 'No') ? "checked=\"checked\"" : "") . $autoClick . ">\n";

// Deezer Connect
$_feat_deezer = $_SESSION['feat_bitmask'] & FEAT_DEEZER ? '' : 'hide';
$result = sqlRead('cfg_deezer', $dbh);
$cfgDeezer = array();
foreach ($result as $row) {
	$cfgDeezer[$row['param']] = $row['value'];
}
if ($_SESSION['deezersvc'] == '0') {
	$_deezer_btn_disable = 'disabled';
	$_deezer_link_disable = 'disabled';
} else {
	$_deezer_btn_disable = '';
	$_deezer_link_disable = '';
}
$_deezer_credentials_msg = (empty($cfgDeezer['email']) || empty($cfgDeezer['password'])) ?
	'<span class="config-help-static"><em>Credentials have not been entered yet</em></span>' : '';
$_deezersvc_btn_disable = $_deezer_credentials_msg == '' ? '' : 'disabled';
$autoClick = " onchange=\"autoClick('#btn-set-deezersvc');\" " . $_deezersvc_btn_disable;
$_select['deezersvc_on']  .= "<input type=\"radio\" name=\"deezersvc\" id=\"toggle-deezersvc-1\" value=\"1\" " . (($_SESSION['deezersvc'] == '1') ? "checked=\"checked\"" : "") . $autoClick . ">\n";
$_select['deezersvc_off'] .= "<input type=\"radio\" name=\"deezersvc\" id=\"toggle-deezersvc-2\" value=\"0\" " . (($_SESSION['deezersvc'] == '0') ? "checked=\"checked\"" : "") . $autoClick . ">\n";
$_select['deezername'] = $_SESSION['deezername'];
$autoClick = " onchange=\"autoClick('#btn-set-rsmafterdeez');\" " . $_deezer_btn_disable;
$_select['rsmafterdeez_on'] .= "<input type=\"radio\" name=\"rsmafterdeez\" id=\"toggle-rsmafterdeez-1\" value=\"Yes\" " . (($_SESSION['rsmafterdeez'] == 'Yes') ? "checked=\"checked\"" : "") . $autoClick . ">\n";
$_select['rsmafterdeez_off']  .= "<input type=\"radio\" name=\"rsmafterdeez\" id=\"toggle-rsmafterdeez-2\" value=\"No\" " . (($_SESSION['rsmafterdeez'] == 'No') ? "checked=\"checked\"" : "") . $autoClick . ">\n";

// Squeezelite
$_feat_squeezelite = $_SESSION['feat_bitmask'] & FEAT_SQUEEZELITE ? '' : 'hide';
$_SESSION['slsvc'] == '1' ? $_sl_btn_disable = '' : $_sl_btn_disable = 'disabled';
$_SESSION['slsvc'] == '1' ? $_sl_link_disable = '' : $_sl_link_disable = 'onclick="return false;"';
$autoClick = " onchange=\"autoClick('#btn-set-slsvc');\"";
$_select['slsvc_on']  .= "<input type=\"radio\" name=\"slsvc\" id=\"toggle-slsvc-1\" value=\"1\" " . (($_SESSION['slsvc'] == '1') ? "checked=\"checked\"" : "") . $autoClick . ">\n";
$_select['slsvc_off'] .= "<input type=\"radio\" name=\"slsvc\" id=\"toggle-slsvc-2\" value=\"0\" " . (($_SESSION['slsvc'] == '0') ? "checked=\"checked\"" : "") . $autoClick . ">\n";
$autoClick = " onchange=\"autoClick('#btn-set-rsmaftersl');\" " . $_sl_btn_disable;
$_select['rsmaftersl_on'] .= "<input type=\"radio\" name=\"rsmaftersl\" id=\"toggle-rsmaftersl-1\" value=\"Yes\" " . (($_SESSION['rsmaftersl'] == 'Yes') ? "checked=\"checked\"" : "") . $autoClick . ">\n";
$_select['rsmaftersl_off']  .= "<input type=\"radio\" name=\"rsmaftersl\" id=\"toggle-rsmaftersl-2\" value=\"No\" " . (($_SESSION['rsmaftersl'] == 'No') ? "checked=\"checked\"" : "") . $autoClick . ">\n";

// UPnP client for MPD
$_feat_upmpdcli = $_SESSION['feat_bitmask'] & FEAT_UPMPDCLI ? '' : 'hide';
$_SESSION['upnpsvc'] == '1' ? $_upnp_btn_disable = '' : $_upnp_btn_disable = 'disabled';
$_SESSION['upnpsvc'] == '1' ? $_upnp_link_disable = '' : $_upnp_link_disable = 'onclick="return false;"';
$_SESSION['dlnasvc'] == '1' ? $_dlna_btn_disable = '' : $_dlna_btn_disable = 'disabled';
$_SESSION['dlnasvc'] == '1' ? $_dlna_link_disable = '' : $_dlna_link_disable = 'onclick="return false;"';
$autoClick = " onchange=\"autoClick('#btn-set-upnpsvc');\"";
$_select['upnpsvc_on']  .= "<input type=\"radio\" name=\"upnpsvc\" id=\"toggle-upnpsvc-1\" value=\"1\" " . (($_SESSION['upnpsvc'] == '1') ? "checked=\"checked\"" : "") . $autoClick . ">\n";
$_select['upnpsvc_off'] .= "<input type=\"radio\" name=\"upnpsvc\" id=\"toggle-upnpsvc-2\" value=\"0\" " . (($_SESSION['upnpsvc'] == '0') ? "checked=\"checked\"" : "") . $autoClick . ">\n";
$_select['upnpname'] = $_SESSION['upnpname'];

// Plexamp
if (($_SESSION['feat_bitmask'] & FEAT_PLEXAMP)) {
	$_feat_plexamp = '';
	$_SESSION['plexamp_installed'] == 'yes' ? $_pa_svcbtn_disable = '' : $_pa_svcbtn_disable = 'disabled';
	$_SESSION['pasvc'] == '1' ? $_pa_btn_disable = '' : $_pa_btn_disable = 'disabled';
	$_SESSION['pasvc'] == '1' ? $_pa_link_disable = '' : $_pa_link_disable = 'onclick="return false;"';
	$autoClick = " onchange=\"autoClick('#btn-set-pasvc');\" " . $_pa_svcbtn_disable;
	$_select['pasvc_on']  .= "<input type=\"radio\" name=\"pasvc\" id=\"toggle-pasvc-1\" value=\"1\" " . (($_SESSION['pasvc'] == '1') ? "checked=\"checked\"" : "") . $autoClick . ">\n";
	$_select['pasvc_off'] .= "<input type=\"radio\" name=\"pasvc\" id=\"toggle-pasvc-2\" value=\"0\" " . (($_SESSION['pasvc'] == '0') ? "checked=\"checked\"" : "") . $autoClick . ">\n";
	if ($_SESSION['alsavolume'] == 'none') {
		$_alsavolume_max_pa = '';
		$_alsavolume_max_pa_readonly = 'readonly';
		$_alsavolume_max_pa_disable = 'disabled';
		$_alsavolume_max_pa_msg = '<span class="config-msg-static"><i>Hardware volume controller not detected</i></span>';
	} else {
		$_alsavolume_max_pa = $_SESSION['alsavolume_max_pa'];
		$_alsavolume_max_pa_readonly = '';
		$_alsavolume_max_pa_disable = '';
		$_alsavolume_max_pa_msg = '';
	}
	$autoClick = " onchange=\"autoClick('#btn-set-rsmafterpa');\" " . $_pa_btn_disable;
	$_select['rsmafterpa_on'] .= "<input type=\"radio\" name=\"rsmafterpa\" id=\"toggle-rsmafterpa-1\" value=\"Yes\" " . (($_SESSION['rsmafterpa'] == 'Yes') ? "checked=\"checked\"" : "") . $autoClick . ">\n";
	$_select['rsmafterpa_off']  .= "<input type=\"radio\" name=\"rsmafterpa\" id=\"toggle-rsmafterpa-2\" value=\"No\" " . (($_SESSION['rsmafterpa'] == 'No') ? "checked=\"checked\"" : "") . $autoClick . ">\n";
} else {
	$_feat_plexamp = 'hide';
}

// RoonBridge
if (($_SESSION['feat_bitmask'] & FEAT_ROONBRIDGE)) {
	$_feat_roonbridge = '';
	$_SESSION['roonbridge_installed'] == 'yes' ? $_rb_svcbtn_disable = '' : $_rb_svcbtn_disable = 'disabled';
	$_SESSION['rbsvc'] == '1' ? $_rb_btn_disable = '' : $_rb_btn_disable = 'disabled';
	$_SESSION['rbsvc'] == '1' ? $_rb_link_disable = '' : $_rb_link_disable = 'onclick="return false;"';
	$autoClick = " onchange=\"autoClick('#btn-set-rbsvc');\" " . $_rb_svcbtn_disable;
	$_select['rbsvc_on']  .= "<input type=\"radio\" name=\"rbsvc\" id=\"toggle-rbsvc-1\" value=\"1\" " . (($_SESSION['rbsvc'] == '1') ? "checked=\"checked\"" : "") . $autoClick . ">\n";
	$_select['rbsvc_off'] .= "<input type=\"radio\" name=\"rbsvc\" id=\"toggle-rbsvc-2\" value=\"0\" " . (($_SESSION['rbsvc'] == '0') ? "checked=\"checked\"" : "") . $autoClick . ">\n";
	$autoClick = " onchange=\"autoClick('#btn-set-rsmafterrb');\" " . $_rb_btn_disable;
	$_select['rsmafterrb_on'] .= "<input type=\"radio\" name=\"rsmafterrb\" id=\"toggle-rsmafterrb-1\" value=\"Yes\" " . (($_SESSION['rsmafterrb'] == 'Yes') ? "checked=\"checked\"" : "") . $autoClick . ">\n";
	$_select['rsmafterrb_off']  .= "<input type=\"radio\" name=\"rsmafterrb\" id=\"toggle-rsmafterrb-2\" value=\"No\" " . (($_SESSION['rsmafterrb'] == 'No') ? "checked=\"checked\"" : "") . $autoClick . ">\n";
} else {
	$_feat_roonbridge = 'hide';
}

waitWorker('ren-config');

$tpl = "ren-config.html";
$section = basename(__FILE__, '.php');
storeBackLink($section, $tpl);

include('header.php');
eval("echoTemplate(\"" . getTemplate("templates/$tpl") . "\");");
include('footer.php');
