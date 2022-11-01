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

// Bluetooth renderer
// Service
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
		submitJob('btsvc', '"' . $currentBtName . '" ' . '"' . $_POST['btname'] . '"', $title, '');
	}
}
// Restart Bluetooth service
if (isset($_POST['btrestart']) && $_POST['btrestart'] == 1 && $_SESSION['btsvc'] == '1') {
	submitJob('btsvc', '', 'Bluetooth controller restarted', '');
}
// Pairing agent
if (isset($_POST['update_pairing_agent'])) {
	phpSession('write', 'pairing_agent', $_POST['pairing_agent']);
	submitJob('pairing_agent', $_POST['pairing_agent'], 'Settings updated', '');
}
// Restart pairing agent
if (isset($_POST['parestart']) && $_POST['parestart'] == 1 && $_SESSION['btsvc'] == '1') {
	submitJob('pairing_agent', '', 'Pairing agent restarted', '');
}
// Speaker sharing
if (isset($_POST['update_bt_multi'])) {
	phpSession('write', 'btmulti', $_POST['btmulti']);
	submitJob('btmulti', '', 'Settings updated', '');
}
// Resume MPD
if (isset($_POST['update_rsmafterbt'])) {
	phpSession('write', 'rsmafterbt', $_POST['rsmafterbt']);
	$_SESSION['notify']['title'] = 'Settings updated';
}

// AirPlay renderer
// Service
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
		submitJob('airplaysvc', '', $title, '');
	}
}
// Protocol version
if (isset($_POST['update_airplay_protocol'])) {
	$_SESSION['airplay_protocol'] = substr($_POST['airplay_protocol'], -1);
	submitJob('airplay_protocol', $_POST['airplay_protocol'], 'Settings updated', '');
}
// Resume MPD
if (isset($_POST['update_rsmafterapl'])) {
	phpSession('write', 'rsmafterapl', $_POST['rsmafterapl']);
	$_SESSION['notify']['title'] = 'Settings updated';
}
// Restart
if (isset($_POST['airplayrestart']) && $_POST['airplayrestart'] == 1 && $_SESSION['airplaysvc'] == '1') {
	submitJob('airplaysvc', '', 'AirPlay restarted', '');
}

// Spotify renderer
// Service
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
		submitJob('spotifysvc', '', $title, '');
	}
}
// Resume MPD
if (isset($_POST['update_rsmafterspot'])) {
	phpSession('write', 'rsmafterspot', $_POST['rsmafterspot']);
	$_SESSION['notify']['title'] = 'Settings updated';
}
// Restart
if (isset($_POST['spotifyrestart']) && $_POST['spotifyrestart'] == 1 && $_SESSION['spotifysvc'] == '1') {
	submitJob('spotifysvc', '', 'Spotify connect restarted', '');
}
// Clear credential cache
if (isset($_POST['spotify_clear_credentials']) && $_POST['spotify_clear_credentials'] == 1) {
	submitJob('spotify_clear_credentials', '', 'Credential cache cleared', '');
}

// Squeezelite renderer
// Service
if (isset($_POST['update_sl_settings'])) {
	if (isset($_POST['slsvc']) && $_POST['slsvc'] != $_SESSION['slsvc']) {
		$title = 'Settings updated';
		phpSession('write', 'slsvc', $_POST['slsvc']);
	}
	if (isset($title)) {
		if ($_POST['slsvc'] == 0) {
			phpSession('write', 'rsmaftersl', 'No');
		}
		submitJob('slsvc', '', $title, '');
	}
}
// Resume MPD
if (isset($_POST['update_rsmaftersl'])) {
	phpSession('write', 'rsmaftersl', $_POST['rsmaftersl']);
	$_SESSION['notify']['title'] = 'Settings updated';
}
// Restart
if (isset($_POST['slrestart']) && $_POST['slrestart'] == 1) {
	phpSession('write', 'rsmaftersl', 'No');
	submitJob('slrestart', '', 'Squeezelite restarted', '');
}

// RoonBridge renderer
// Service
if (isset($_POST['update_rb_settings'])) {
	if (isset($_POST['rbsvc']) && $_POST['rbsvc'] != $_SESSION['rbsvc']) {
		$title = 'Settings updated';
		phpSession('write', 'rbsvc', $_POST['rbsvc']);
	}
	if (isset($title)) {
		submitJob('rbsvc', '', $title, '');
	}
}
// Resume MPD
if (isset($_POST['update_rsmafterrb'])) {
	phpSession('write', 'rsmafterrb', $_POST['rsmafterrb']);
	$_SESSION['notify']['title'] = 'Settings updated';
}
// Restart
if (isset($_POST['rbrestart']) && $_POST['rbrestart'] == 1) {
	submitJob('rbrestart', '', 'RoonBridge restarted', '');
}

// UPnP/DLNA
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
		submitJob('upnpsvc', '"' . $currentUpnpName . '" ' . '"' . $_POST['upnpname'] . '"', $title, '');
	}
}
// Restart
if (isset($_POST['upnprestart']) && $_POST['upnprestart'] == 1 && $_SESSION['upnpsvc'] == '1') {
	submitJob('upnpsvc', '', 'UPnP renderer restarted', '');
}
// DLNA server
if (isset($_POST['update_dlna_settings'])) {
	$currentDlnaName = $_SESSION['dlnaname'];
	if (isset($_POST['dlnaname']) && $_POST['dlnaname'] != $_SESSION['dlnaname']) {
		$title = 'Settings updated';
		$msg = '';
		phpSession('write', 'dlnaname', $_POST['dlnaname']);
	}
	if (isset($_POST['dlnasvc']) && $_POST['dlnasvc'] != $_SESSION['dlnasvc']) {
		$title = 'Settings updated';
		$msg = $_POST['dlnasvc'] == 1 ? 'Database rebuild initiated' : '';
		phpSession('write', 'dlnasvc', $_POST['dlnasvc']);
	}
	if (isset($title)) {
		submitJob('minidlna', '"' . $currentDlnaName . '" ' . '"' . $_POST['dlnaname'] . '"', $title, $msg);
	}
}
// Rebuild DLNA db
if (isset($_POST['rebuild_dlnadb'])) {
	if ($_SESSION['dlnasvc'] == 1) {
		submitJob('dlnarebuild', '', 'Database rebuild initiated...', '');
	}
	else {
		$_SESSION['notify']['title'] = 'Turn DLNA server on';
		$_SESSION['notify']['msg'] = 'Database rebuild will initiate';
	}
}

phpSession('close');

// AUDIO RENDERERS

// Bluetooth renderer
$_feat_bluetooth = $_SESSION['feat_bitmask'] & FEAT_BLUETOOTH ? '' : 'hide';
$_SESSION['btsvc'] == '1' ? $_bt_btn_disable = '' : $_bt_btn_disable = 'disabled';
$_SESSION['btsvc'] == '1' ? $_bt_link_disable = '' : $_bt_link_disable = 'onclick="return false;"';
$_select['btsvc1'] .= "<input type=\"radio\" name=\"btsvc\" id=\"togglebtsvc1\" value=\"1\" " . (($_SESSION['btsvc'] == 1) ? "checked=\"checked\"" : "") . ">\n";
$_select['btsvc0'] .= "<input type=\"radio\" name=\"btsvc\" id=\"togglebtsvc2\" value=\"0\" " . (($_SESSION['btsvc'] == 0) ? "checked=\"checked\"" : "") . ">\n";
$_select['btname'] = $_SESSION['btname'];
$_SESSION['pairing_agent'] == '1' ? $_pa_btn_disable = '' : $_pa_btn_disable = 'disabled';
$_SESSION['pairing_agent'] == '1' ? $_pa_link_disable = '' : $_pa_link_disable = 'onclick="return false;"';
$_select['pairing_agent1'] .= "<input type=\"radio\" name=\"pairing_agent\" id=\"toggle-pairing-agent1\" value=\"1\" " . (($_SESSION['pairing_agent'] == 1) ? "checked=\"checked\"" : "") . ">\n";
$_select['pairing_agent0'] .= "<input type=\"radio\" name=\"pairing_agent\" id=\"toggle-pairing-agent2\" value=\"0\" " . (($_SESSION['pairing_agent'] == 0) ? "checked=\"checked\"" : "") . ">\n";
$_select['btmulti1'] .= "<input type=\"radio\" name=\"btmulti\" id=\"togglebtmulti1\" value=\"1\" " . (($_SESSION['btmulti'] == 1) ? "checked=\"checked\"" : "") . ">\n";
$_select['btmulti0'] .= "<input type=\"radio\" name=\"btmulti\" id=\"togglebtmulti2\" value=\"0\" " . (($_SESSION['btmulti'] == 0) ? "checked=\"checked\"" : "") . ">\n";
$_select['rsmafterbt'] .= "<option value=\"1\" " . (($_SESSION['rsmafterbt'] == '1') ? "selected" : "") . ">Yes</option>\n";
$_select['rsmafterbt'] .= "<option value=\"0\" " . (($_SESSION['rsmafterbt'] == '0') ? "selected" : "") . ">No</option>\n";

// AirPlay renderer
$_feat_airplay = $_SESSION['feat_bitmask'] & FEAT_AIRPLAY ? '' : 'hide';
$_SESSION['airplaysvc'] == '1' ? $_airplay_btn_disable = '' : $_airplay_btn_disable = 'disabled';
$_SESSION['airplaysvc'] == '1' ? $_airplay_link_disable = '' : $_airplay_link_disable = 'onclick="return false;"';
$_select['airplaysvc1'] .= "<input type=\"radio\" name=\"airplaysvc\" id=\"toggleairplaysvc1\" value=\"1\" " . (($_SESSION['airplaysvc'] == 1) ? "checked=\"checked\"" : "") . ">\n";
$_select['airplaysvc0'] .= "<input type=\"radio\" name=\"airplaysvc\" id=\"toggleairplaysvc2\" value=\"0\" " . (($_SESSION['airplaysvc'] == 0) ? "checked=\"checked\"" : "") . ">\n";
$_select['airplayname'] = $_SESSION['airplayname'];
$_select['airplay_protocol'] .= "<option value=\"1\" " . (($_SESSION['airplay_protocol'] == '1') ? "selected" : "") . ">AirPlay 1</option>\n";
$_select['airplay_protocol'] .= "<option value=\"2\" " . (($_SESSION['airplay_protocol'] == '2') ? "selected" : "") . ">AirPlay 2</option>\n";
$_select['rsmafterapl'] .= "<option value=\"Yes\" " . (($_SESSION['rsmafterapl'] == 'Yes') ? "selected" : "") . ">Yes</option>\n";
$_select['rsmafterapl'] .= "<option value=\"No\" " . (($_SESSION['rsmafterapl'] == 'No') ? "selected" : "") . ">No</option>\n";

// Spotify connect renderer
$_feat_spotify = $_SESSION['feat_bitmask'] & FEAT_SPOTIFY ? '' : 'hide';
$_SESSION['spotifysvc'] == '1' ? $_spotify_btn_disable = '' : $_spotify_btn_disable = 'disabled';
$_SESSION['spotifysvc'] == '1' ? $_spotify_link_disable = '' : $_spotify_link_disable = 'onclick="return false;"';
$_select['spotifysvc1'] .= "<input type=\"radio\" name=\"spotifysvc\" id=\"togglespotifysvc1\" value=\"1\" " . (($_SESSION['spotifysvc'] == 1) ? "checked=\"checked\"" : "") . ">\n";
$_select['spotifysvc0'] .= "<input type=\"radio\" name=\"spotifysvc\" id=\"togglespotifysvc2\" value=\"0\" " . (($_SESSION['spotifysvc'] == 0) ? "checked=\"checked\"" : "") . ">\n";
$_select['spotifyname'] = $_SESSION['spotifyname'];
$_select['rsmafterspot'] .= "<option value=\"Yes\" " . (($_SESSION['rsmafterspot'] == 'Yes') ? "selected" : "") . ">Yes</option>\n";
$_select['rsmafterspot'] .= "<option value=\"No\" " . (($_SESSION['rsmafterspot'] == 'No') ? "selected" : "") . ">No</option>\n";

// Squeezelite renderer
$_feat_squeezelite = $_SESSION['feat_bitmask'] & FEAT_SQUEEZELITE ? '' : 'hide';
$_SESSION['slsvc'] == '1' ? $_rb_svcbtn_disable = 'disabled' : $_rb_svcbtn_disable = '';
$_SESSION['slsvc'] == '1' ? $_sl_btn_disable = '' : $_sl_btn_disable = 'disabled';
$_SESSION['slsvc'] == '1' ? $_sl_link_disable = '' : $_sl_link_disable = 'onclick="return false;"';
$_select['slsvc1'] .= "<input type=\"radio\" name=\"slsvc\" id=\"toggleslsvc1\" value=\"1\" " . (($_SESSION['slsvc'] == 1) ? "checked=\"checked\"" : "") . ">\n";
$_select['slsvc0'] .= "<input type=\"radio\" name=\"slsvc\" id=\"toggleslsvc2\" value=\"0\" " . (($_SESSION['slsvc'] == 0) ? "checked=\"checked\"" : "") . ">\n";
$_select['rsmaftersl'] .= "<option value=\"Yes\" " . (($_SESSION['rsmaftersl'] == 'Yes') ? "selected" : "") . ">Yes</option>\n";
$_select['rsmaftersl'] .= "<option value=\"No\" " . (($_SESSION['rsmaftersl'] == 'No') ? "selected" : "") . ">No</option>\n";

// RoonBridge renderer
if (($_SESSION['feat_bitmask'] & FEAT_ROONBRIDGE) && $_SESSION['roonbridge_installed'] == 'yes') {
	$_roonbridge_install_msg = '';
	$_roonbridge_label_style = 'style="margin-bottom:0;"';
	$_feat_roonbridge = '';
	$_SESSION['rbsvc'] == '1' ? $_sl_svcbtn_disable = 'disabled' : $_sl_svcbtn_disable = '';
	$_SESSION['rbsvc'] == '1' ? $_rb_btn_disable = '' : $_rb_btn_disable = 'disabled';
	$_SESSION['rbsvc'] == '1' ? $_rb_link_disable = '' : $_rb_link_disable = 'onclick="return false;"';
	$_select['rbsvc1'] .= "<input type=\"radio\" name=\"rbsvc\" id=\"togglerbsvc1\" value=\"1\" " . (($_SESSION['rbsvc'] == 1) ? "checked=\"checked\"" : "") . ">\n";
	$_select['rbsvc0'] .= "<input type=\"radio\" name=\"rbsvc\" id=\"togglerbsvc2\" value=\"0\" " . (($_SESSION['rbsvc'] == 0) ? "checked=\"checked\"" : "") . ">\n";
	$_select['rsmafterrb'] .= "<option value=\"Yes\" " . (($_SESSION['rsmafterrb'] == 'Yes') ? "selected" : "") . ">Yes</option>\n";
	$_select['rsmafterrb'] .= "<option value=\"No\" " . (($_SESSION['rsmafterrb'] == 'No') ? "selected" : "") . ">No</option>\n";
}
else {
	$_roonbridge_install_msg = "<div style=\"margin:-1em 0 1em 0;\">This component is provided by the manufacturer. Visit their website for installation instructions.</div>";
	$_roonbridge_label_style = '';
	$_feat_roonbridge = 'hide';
}

// UPnP client for MPD
$_feat_upmpdcli = $_SESSION['feat_bitmask'] & FEAT_UPMPDCLI ? '' : 'hide';
$_SESSION['upnpsvc'] == '1' ? $_upnp_btn_disable = '' : $_upnp_btn_disable = 'disabled';
$_SESSION['upnpsvc'] == '1' ? $_upnp_link_disable = '' : $_upnp_link_disable = 'onclick="return false;"';
$_select['upnpsvc1'] .= "<input type=\"radio\" name=\"upnpsvc\" id=\"toggleupnpsvc1\" value=\"1\" " . (($_SESSION['upnpsvc'] == 1) ? "checked=\"checked\"" : "") . ">\n";
$_select['upnpsvc0'] .= "<input type=\"radio\" name=\"upnpsvc\" id=\"toggleupnpsvc2\" value=\"0\" " . (($_SESSION['upnpsvc'] == 0) ? "checked=\"checked\"" : "") . ">\n";
$_select['upnpname'] = $_SESSION['upnpname'];
// DLNA server
$_feat_minidlna = $_SESSION['feat_bitmask'] & FEAT_MINIDLNA ? '' : 'hide';
$_SESSION['dlnasvc'] == '1' ? $_dlna_btn_disable = '' : $_dlna_btn_disable = 'disabled';
$_SESSION['dlnasvc'] == '1' ? $_dlna_link_disable = '' : $_dlna_link_disable = 'onclick="return false;"';
$_select['dlnasvc1'] .= "<input type=\"radio\" name=\"dlnasvc\" id=\"toggledlnasvc1\" value=\"1\" " . (($_SESSION['dlnasvc'] == 1) ? "checked=\"checked\"" : "") . ">\n";
$_select['dlnasvc0'] .= "<input type=\"radio\" name=\"dlnasvc\" id=\"toggledlnasvc2\" value=\"0\" " . (($_SESSION['dlnasvc'] == 0) ? "checked=\"checked\"" : "") . ">\n";
$_select['dlnaname'] = $_SESSION['dlnaname'];
$_select['hostip'] = getHostIp();

waitWorker(1, 'ren-config');

$tpl = "ren-config.html";
$section = basename(__FILE__, '.php');
storeBackLink($section, $tpl);

include('header.php');
eval("echoTemplate(\"" . getTemplate("templates/$tpl") . "\");");
include('footer.php');
