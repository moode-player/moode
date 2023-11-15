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
require_once __DIR__ . '/inc/network.php';
require_once __DIR__ . '/inc/session.php';

phpSession('open');

// Bluetooth
if (isset($_POST['update_bt_settings'])) {
	$currentBtName = $_SESSION['btname'];

	if (isset($_POST['btname']) && $_POST['btname'] != $_SESSION['btname']) {
		$title = 'Settings updated';
		phpSession('write', 'btname', $_POST['btname']);
	}
	if (isset($_POST['btsvc']) && $_POST['btsvc'] != $_SESSION['btsvc']) {
		$title = 'Settings updated';
		phpSession('write', 'btsvc', $_POST['btsvc']);
		if ($_POST['btsvc'] == '0') {
			phpSession('write', 'pairing_agent', '0');
		}
	}
	if (isset($title)) {
		submitJob('btsvc', '"' . $currentBtName . '" ' . '"' . $_POST['btname'] . '"', $title);
	}
}
if (isset($_POST['btrestart']) && $_POST['btrestart'] == 1 && $_SESSION['btsvc'] == '1') {
	submitJob('btsvc', '', 'Bluetooth controller restarted');
}
if (isset($_POST['update_pairing_agent'])) {
	phpSession('write', 'pairing_agent', $_POST['pairing_agent']);
	submitJob('pairing_agent', $_POST['pairing_agent'], 'Settings updated');
}
if (isset($_POST['parestart']) && $_POST['parestart'] == 1 && $_SESSION['btsvc'] == '1') {
	submitJob('pairing_agent', '', 'Pairing agent restarted');
}
if (isset($_POST['update_rsmafterbt'])) {
	phpSession('write', 'rsmafterbt', $_POST['rsmafterbt']);
	$_SESSION['notify']['title'] = 'Settings updated';
}

// AirPlay
if (isset($_POST['update_airplay_settings'])) {
	if (isset($_POST['airplayname']) && $_POST['airplayname'] != $_SESSION['airplayname']) {
		$title = 'Settings updated';
		phpSession('write', 'airplayname', $_POST['airplayname']);
	}
	if (isset($_POST['airplaysvc']) && $_POST['airplaysvc'] != $_SESSION['airplaysvc']) {
		$title = 'Settings updated';
		phpSession('write', 'airplaysvc', $_POST['airplaysvc']);
	}
	if (isset($title)) {
		submitJob('airplaysvc', '', $title);
	}
}
if (isset($_POST['update_rsmafterapl'])) {
	phpSession('write', 'rsmafterapl', $_POST['rsmafterapl']);
	$_SESSION['notify']['title'] = 'Settings updated';
}
if (isset($_POST['airplayrestart']) && $_POST['airplayrestart'] == 1 && $_SESSION['airplaysvc'] == '1') {
	submitJob('airplaysvc', '', 'AirPlay restarted');
}

// Spotify Connect
if (isset($_POST['update_spotify_settings'])) {
	if (isset($_POST['spotifyname']) && $_POST['spotifyname'] != $_SESSION['spotifyname']) {
		$title = 'Settings updated';
		phpSession('write', 'spotifyname', $_POST['spotifyname']);
	}
	if (isset($_POST['spotifysvc']) && $_POST['spotifysvc'] != $_SESSION['spotifysvc']) {
		$title = 'Settings updated';
		phpSession('write', 'spotifysvc', $_POST['spotifysvc']);
	}
	if (isset($title)) {
		submitJob('spotifysvc', '', $title);
	}
}
if (isset($_POST['update_rsmafterspot'])) {
	phpSession('write', 'rsmafterspot', $_POST['rsmafterspot']);
	$_SESSION['notify']['title'] = 'Settings updated';
}
if (isset($_POST['spotifyrestart']) && $_POST['spotifyrestart'] == 1 && $_SESSION['spotifysvc'] == '1') {
	submitJob('spotifysvc', '', 'Spotify connect restarted');
}
if (isset($_POST['spotify_clear_credentials']) && $_POST['spotify_clear_credentials'] == 1) {
	submitJob('spotify_clear_credentials', '', 'Credential cache cleared');
}

// Squeezelite
if (isset($_POST['update_sl_settings'])) {
	if (isset($_POST['slsvc']) && $_POST['slsvc'] != $_SESSION['slsvc']) {
		$title = 'Settings updated';
		phpSession('write', 'slsvc', $_POST['slsvc']);
	}
	if (isset($title)) {
		if ($_POST['slsvc'] == 0) {
			phpSession('write', 'rsmaftersl', 'No');
		}
		submitJob('slsvc', '', $title);
	}
}
if (isset($_POST['update_rsmaftersl'])) {
	phpSession('write', 'rsmaftersl', $_POST['rsmaftersl']);
	$_SESSION['notify']['title'] = 'Settings updated';
}
if (isset($_POST['slrestart']) && $_POST['slrestart'] == 1) {
	phpSession('write', 'rsmaftersl', 'No');
	submitJob('slrestart', '', 'Squeezelite restarted');
}

// RoonBridge
if (isset($_POST['update_rb_settings'])) {
	if (isset($_POST['rbsvc']) && $_POST['rbsvc'] != $_SESSION['rbsvc']) {
		$title = 'Settings updated';
		phpSession('write', 'rbsvc', $_POST['rbsvc']);
	}
	if (isset($title)) {
		submitJob('rbsvc', '', $title);
	}
}
if (isset($_POST['update_rsmafterrb'])) {
	phpSession('write', 'rsmafterrb', $_POST['rsmafterrb']);
	$_SESSION['notify']['title'] = 'Settings updated';
}
if (isset($_POST['rbrestart']) && $_POST['rbrestart'] == 1) {
	submitJob('rbrestart', '', 'RoonBridge restarted');
}

// UPnP client for MPD
if (isset($_POST['update_upnp_settings'])) {
	$currentUpnpName = $_SESSION['upnpname'];
	if (isset($_POST['upnpname']) && $_POST['upnpname'] != $_SESSION['upnpname']) {
		$title = 'Settings updated';
		phpSession('write', 'upnpname', $_POST['upnpname']);
	}
	if (isset($_POST['upnpsvc']) && $_POST['upnpsvc'] != $_SESSION['upnpsvc']) {
		$title = 'Settings updated';
		phpSession('write', 'upnpsvc', $_POST['upnpsvc']);
	}
	if (isset($title)) {
		submitJob('upnpsvc', '"' . $currentUpnpName . '" ' . '"' . $_POST['upnpname'] . '"', $title);
	}
}
if (isset($_POST['upnprestart']) && $_POST['upnprestart'] == 1 && $_SESSION['upnpsvc'] == '1') {
	submitJob('upnpsvc', '', 'UPnP renderer restarted');
}

phpSession('close');

// Bluetooth
$_feat_bluetooth = $_SESSION['feat_bitmask'] & FEAT_BLUETOOTH ? '' : 'hide';
$_SESSION['btsvc'] == '1' ? $_bt_btn_disable = '' : $_bt_btn_disable = 'disabled';
$_SESSION['btsvc'] == '1' ? $_bt_link_disable = '' : $_bt_link_disable = 'onclick="return false;"';
$_SESSION['pairing_agent'] == '1' ? $_pa_btn_disable = '' : $_pa_btn_disable = 'disabled';
$_SESSION['pairing_agent'] == '1' ? $_pa_link_disable = '' : $_pa_link_disable = 'onclick="return false;"';
$autoClick = " onchange=\"autoClick('#btn-set-btsvc');\"";
$_select['btsvc_on']  .= "<input type=\"radio\" name=\"btsvc\" id=\"toggle-btsvc-1\" value=\"1\" " . (($_SESSION['btsvc'] == '1') ? "checked=\"checked\"" : "") . $autoClick . ">\n";
$_select['btsvc_off'] .= "<input type=\"radio\" name=\"btsvc\" id=\"toggle-btsvc-2\" value=\"0\" " . (($_SESSION['btsvc'] == '0') ? "checked=\"checked\"" : "") . $autoClick . ">\n";
$_select['btname'] = $_SESSION['btname'];
$autoClick = " onchange=\"autoClick('#btn-set-pairing-agent');\" " . $_bt_btn_disable;
$_select['pairing_agent_on']  .= "<input type=\"radio\" name=\"pairing_agent\" id=\"toggle-pairing-agent-1\" value=\"1\" " . (($_SESSION['pairing_agent'] == 1) ? "checked=\"checked\"" : "") . $autoClick . ">\n";
$_select['pairing_agent_off'] .= "<input type=\"radio\" name=\"pairing_agent\" id=\"toggle-pairing-agent-2\" value=\"0\" " . (($_SESSION['pairing_agent'] == 0) ? "checked=\"checked\"" : "") . $autoClick . ">\n";
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

// Squeezelite
$_feat_squeezelite = $_SESSION['feat_bitmask'] & FEAT_SQUEEZELITE ? '' : 'hide';
//DELETE $_SESSION['slsvc'] == '1' ? $_rb_svcbtn_disable = 'disabled' : $_rb_svcbtn_disable = '';
$_SESSION['slsvc'] == '1' ? $_sl_btn_disable = '' : $_sl_btn_disable = 'disabled';
$_SESSION['slsvc'] == '1' ? $_sl_link_disable = '' : $_sl_link_disable = 'onclick="return false;"';
$autoClick = " onchange=\"autoClick('#btn-set-slsvc');\"";
$_select['slsvc_on']  .= "<input type=\"radio\" name=\"slsvc\" id=\"toggle-slsvc-1\" value=\"1\" " . (($_SESSION['slsvc'] == '1') ? "checked=\"checked\"" : "") . $autoClick . ">\n";
$_select['slsvc_off'] .= "<input type=\"radio\" name=\"slsvc\" id=\"toggle-slsvc-2\" value=\"0\" " . (($_SESSION['slsvc'] == '0') ? "checked=\"checked\"" : "") . $autoClick . ">\n";
$autoClick = " onchange=\"autoClick('#btn-set-rsmaftersl');\" " . $_sl_btn_disable;
$_select['rsmaftersl_on'] .= "<input type=\"radio\" name=\"rsmaftersl\" id=\"toggle-rsmaftersl-1\" value=\"Yes\" " . (($_SESSION['rsmaftersl'] == 'Yes') ? "checked=\"checked\"" : "") . $autoClick . ">\n";
$_select['rsmaftersl_off']  .= "<input type=\"radio\" name=\"rsmaftersl\" id=\"toggle-rsmaftersl-2\" value=\"No\" " . (($_SESSION['rsmaftersl'] == 'No') ? "checked=\"checked\"" : "") . $autoClick . ">\n";

// RoonBridge
if (($_SESSION['feat_bitmask'] & FEAT_ROONBRIDGE) && $_SESSION['roonbridge_installed'] == 'yes') {
	$_feat_roonbridge = '';
	//DELETE $_SESSION['rbsvc'] == '1' ? $_sl_svcbtn_disable = 'disabled' : $_sl_svcbtn_disable = '';
	$_SESSION['rbsvc'] == '1' ? $_rb_btn_disable = '' : $_rb_btn_disable = 'disabled';
	$_SESSION['rbsvc'] == '1' ? $_rb_link_disable = '' : $_rb_link_disable = 'onclick="return false;"';
	$autoClick = " onchange=\"autoClick('#btn-set-rbsvc');\"";
	$_select['rbsvc_on']  .= "<input type=\"radio\" name=\"rbsvc\" id=\"toggle-rbsvc-1\" value=\"1\" " . (($_SESSION['rbsvc'] == '1') ? "checked=\"checked\"" : "") . $autoClick . ">\n";
	$_select['rbsvc_off'] .= "<input type=\"radio\" name=\"rbsvc\" id=\"toggle-rbsvc-2\" value=\"0\" " . (($_SESSION['rbsvc'] == '0') ? "checked=\"checked\"" : "") . $autoClick . ">\n";
	$autoClick = " onchange=\"autoClick('#btn-set-rsmafterrb');\" " . $_rb_btn_disable;
	$_select['rsmafterrb_on'] .= "<input type=\"radio\" name=\"rsmafterrb\" id=\"toggle-rsmafterrb-1\" value=\"Yes\" " . (($_SESSION['rsmafterrb'] == 'Yes') ? "checked=\"checked\"" : "") . $autoClick . ">\n";
	$_select['rsmafterrb_off']  .= "<input type=\"radio\" name=\"rsmafterrb\" id=\"toggle-rsmafterrb-2\" value=\"No\" " . (($_SESSION['rsmafterrb'] == 'No') ? "checked=\"checked\"" : "") . $autoClick . ">\n";
} else {
	$_feat_roonbridge = 'hide';
}

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

waitWorker('ren-config');

$tpl = "ren-config.html";
$section = basename(__FILE__, '.php');
storeBackLink($section, $tpl);

include('header.php');
eval("echoTemplate(\"" . getTemplate("templates/$tpl") . "\");");
include('footer.php');
