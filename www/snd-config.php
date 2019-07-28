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
 * 2019-MM-DD TC moOde 5.4
 *
 */

require_once dirname(__FILE__) . '/inc/playerlib.php';

playerSession('open', '' ,'');

// DEVICE

// i2s device
if (isset($_POST['update_i2s_device'])) {
	if (isset($_POST['i2sdevice'])) {
		playerSession('write', 'i2sdevice', $_POST['i2sdevice']);
		submitJob('i2sdevice', $_POST['i2sdevice'], 'I2S audio device updated', '- Edit Driver options<br>- Reboot then edit Chip options', 20);
	}
}

// advanced driver options
if (isset($_POST['update_drvoptions'])) {
	if (isset($_POST['drvoptions']) && $_POST['drvoptions'] != 'none') {
		$result = sdbquery("SELECT driver, drvoptions FROM cfg_audiodev WHERE name='" . $_SESSION['i2sdevice'] . "'", cfgdb_connect());
		$driver = explode(',', $result[0]['driver']);
		$driverupd = $_POST['drvoptions'] == 'Enabled' ? $driver[0] . ',' . $result[0]['drvoptions'] : $driver[0];

		$result = sdbquery("UPDATE cfg_audiodev SET driver='" . $driverupd . "' WHERE name='" . $_SESSION['i2sdevice'] . "'", cfgdb_connect());
		submitJob('i2sdevice', $_SESSION['i2sdevice'], 'Driver options updated', 'Reboot required');
	}
}

// alsa volume
if (isset($_POST['update_alsa_volume'])) {
	if (isset($_POST['alsavolume'])) {
		submitJob('alsavolume', $_POST['alsavolume'], 'ALSA volume updated', '');
		playerSession('write', 'alsavolume', $_POST['alsavolume']);
	}
}

// MPD

// mpd version
if (isset($_POST['update_mpdver'])) {
	playerSession('write', 'mpdver', $_POST['mpdver']);
	submitJob('mpdver', $_POST['mpdver'], 'MPD ' . $_POST['mpdver'] . ' installed', 'Database rebuild started...');
}

// restart mpd
if (isset($_POST['mpdrestart']) && $_POST['mpdrestart'] == 1) {
	submitJob('mpdrestart', '', 'MPD restarted', '');
}

// auto-shuffle
if (isset($_POST['ashufflesvc']) && $_POST['ashufflesvc'] != $_SESSION['ashufflesvc']) {
	$_SESSION['notify']['title'] = $_POST['ashufflesvc'] == 1 ? 'Auto-shuffle on' : 'Auto-shuffle off';
	$_SESSION['notify']['duration'] = 3;
	playerSession('write', 'ashufflesvc', $_POST['ashufflesvc']);

	// turn off MPD random play so no conflict
	$sock = openMpdSock('localhost', 6600);
	sendMpdCmd($sock, 'random 0');
	$resp = readMpdResp($sock);

	// kill the service if indicated
	if ($_POST['ashufflesvc'] == 0) {
		sysCmd('killall -s 9 ashuffle > /dev/null');
		playerSession('write', 'ashuffle', '0');
		sendMpdCmd($sock, 'consume 0');
		$resp = readMpdResp($sock);
	}
}

// autoplay last played item after reboot/powerup
if (isset($_POST['autoplay']) && $_POST['autoplay'] != $_SESSION['autoplay']) {
	$_SESSION['notify']['title'] = $_POST['autoplay'] == 1 ? 'Autoplay on' : 'Autoplay off';
	$_SESSION['notify']['duration'] = 3;
	playerSession('write', 'autoplay', $_POST['autoplay']);
}

// rotary encoder
if (isset($_POST['update_rotenc'])) {
	if (isset($_POST['rotenc_params']) && $_POST['rotenc_params'] != $_SESSION['rotenc_params']) {
		$title = 'Rotenc params updated';
		playerSession('write', 'rotenc_params', $_POST['rotenc_params']);
	}

	if (isset($_POST['rotaryenc']) && $_POST['rotaryenc'] != $_SESSION['rotaryenc']) {
		$title = $_POST['rotaryenc'] == 1 ? 'Rotary encoder on' : 'Rotary encoder off';
		playerSession('write', 'rotaryenc', $_POST['rotaryenc']);
	}

	if (isset($title)) {
		submitJob('rotaryenc', $_POST['rotaryenc'], $title, '');
	}
}

// polarity inversion
if (isset($_POST['update_invert_polarity']) && $_POST['invert_polarity'] != $_SESSION['invert_polarity']) {
	$title = $_POST['invert_polarity'] == 1 ? 'Polarity inversion on' : 'Polarity inversion off';
	submitJob('invert_polarity', $_POST['invert_polarity'], $title, '');
	playerSession('write', 'invert_polarity', $_POST['invert_polarity']);
}

// mpd crossfade
if (isset($_POST['mpdcrossfade']) && $_POST['mpdcrossfade'] != $_SESSION['mpdcrossfade']) {
	submitJob('mpdcrossfade', $_POST['mpdcrossfade'], 'Crossfade settings updated', '');
	playerSession('write', 'mpdcrossfade', $_POST['mpdcrossfade']);
}

// http streaming server
if (isset($_POST['mpd_httpd']) && $_POST['mpd_httpd'] != $_SESSION['mpd_httpd']) {
	$title = $_POST['mpd_httpd'] == 1 ? 'HTTP server on' : 'HTTP server off';
	submitJob('mpd_httpd', $_POST['mpd_httpd'], $title, '');
	playerSession('write', 'mpd_httpd', $_POST['mpd_httpd']);
}
// port
if (isset($_POST['mpd_httpd_port']) && $_POST['mpd_httpd_port'] != $_SESSION['mpd_httpd_port']) {
	playerSession('write', 'mpd_httpd_port', $_POST['mpd_httpd_port']);
	submitJob('mpd_httpd_port', $_POST['mpd_httpd_port'], 'HTTP port updated', 'MPD restarted');
}
// encoder
if (isset($_POST['mpd_httpd_encoder']) && $_POST['mpd_httpd_encoder'] != $_SESSION['mpd_httpd_encoder']) {
	playerSession('write', 'mpd_httpd_encoder', $_POST['mpd_httpd_encoder']);
	submitJob('mpd_httpd_encoder', $_POST['mpd_httpd_encoder'], 'HTTP encoder updated', 'MPD restarted');
}

// DSP

// crossfeed
if (isset($_POST['crossfeed']) && $_POST['crossfeed'] != $_SESSION['crossfeed']) {
	playerSession('write', 'crossfeed', $_POST['crossfeed']);
	submitJob('crossfeed', $_POST['crossfeed'], 'Crossfeed ' . ($_POST['crossfeed'] == 'Off' ? 'off' : 'on'), '');
}

// parametric eq
if (isset($_POST['eqfa4p']) && $_POST['eqfa4p'] != $_SESSION['eqfa4p']) {
	// pass old,new curve name to worker job
	playerSession('write', 'eqfa4p', $_POST['eqfa4p']);
	submitJob('eqfa4p', $_SESSION['eqfa4p'] . ',' . $_POST['eqfa4p'], 'Parametric EQ ' . ($_POST['eqfa4p'] == 'Off' ? 'off' : 'on'), 'MPD restarted');
}

// graphic eq
if (isset($_POST['alsaequal']) && $_POST['alsaequal'] != $_SESSION['alsaequal']) {
	// pass old,new curve name to worker job
	playerSession('write', 'alsaequal', $_POST['alsaequal']);
	submitJob('alsaequal', $_SESSION['alsaequal'] . ',' . $_POST['alsaequal'], 'Graphic EQ ' . ($_POST['alsaequal'] == 'Off' ? 'off' : 'on'), '');
}

// RENDERERS

// BLUETOOTH RENDERER
if (isset($_POST['update_bt_settings'])) {
	$currentBtName = $_SESSION['btname'];

	if (isset($_POST['btname']) && $_POST['btname'] != $_SESSION['btname']) {
		$title = 'Bluetooth name updated';
		playerSession('write', 'btname', $_POST['btname']);
	}

	if (isset($_POST['btsvc']) && $_POST['btsvc'] != $_SESSION['btsvc']) {
		$title = $_POST['btsvc'] == 1 ? 'Bluetooth controller on' : 'Bluetooth controller off';
		playerSession('write', 'btsvc', $_POST['btsvc']);
	}

	if (isset($title)) {
		submitJob('btsvc', '"' . $currentBtName . '" ' . '"' . $_POST['btname'] . '"', $title, '');
	}
}
// pairing agent
if (isset($_POST['update_pairing_agent'])) {
	playerSession('write', 'pairing_agent', $_POST['pairing_agent']);
	submitJob('pairing_agent', $_POST['pairing_agent'], ($_POST['pairing_agent'] == 1 ? 'Pairing agent on' : 'Pairing agent off'), '');
}
// speaker sharing
if (isset($_POST['update_bt_multi'])) {
	playerSession('write', 'btmulti', $_POST['btmulti']);
	submitJob('btmulti', '', ($_POST['btmulti'] == 1 ? 'Speaker sharing on' : 'Speaker sharing off'), '');
}
// resume mpd after bt
if (isset($_POST['update_rsmafterbt'])) {
	playerSession('write', 'rsmafterbt', $_POST['rsmafterbt']);
	$_SESSION['notify']['title'] = 'Setting updated';
}
// restart bluetooth
if (isset($_POST['btrestart']) && $_POST['btrestart'] == 1 && $_SESSION['btsvc'] == '1') {
	submitJob('btsvc', '', 'Bluetooth controller restarted', '');
}

// AIRPLAY RENDERER
if (isset($_POST['update_airplay_settings'])) {
	if (isset($_POST['airplayname']) && $_POST['airplayname'] != $_SESSION['airplayname']) {
		$title = 'Airplay name updated';
		playerSession('write', 'airplayname', $_POST['airplayname']);
	}

	if (isset($_POST['airplaysvc']) && $_POST['airplaysvc'] != $_SESSION['airplaysvc']) {
		$title = $_POST['airplaysvc'] == 1 ? 'Airplay receiver on' : 'Airplay receiver off';
		playerSession('write', 'airplaysvc', $_POST['airplaysvc']);
	}

	if (isset($title)) {
		submitJob('airplaysvc', '', $title, '');
	}
}
// resume mpd after airplay
if (isset($_POST['update_rsmafterapl'])) {
	playerSession('write', 'rsmafterapl', $_POST['rsmafterapl']);
	$_SESSION['notify']['title'] = 'Setting updated';
}
// restart airplay
if (isset($_POST['airplayrestart']) && $_POST['airplayrestart'] == 1 && $_SESSION['airplaysvc'] == '1') {
	submitJob('airplaysvc', '', 'Airplay receiver restarted', '');
}

// SPOTIFY RENDERER
if (isset($_POST['update_spotify_settings'])) {
	if (isset($_POST['spotifyname']) && $_POST['spotifyname'] != $_SESSION['spotifyname']) {
		$title = 'Spotify name updated';
		playerSession('write', 'spotifyname', $_POST['spotifyname']);
	}

	if (isset($_POST['spotifysvc']) && $_POST['spotifysvc'] != $_SESSION['spotifysvc']) {
		$title = $_POST['spotifysvc'] == 1 ? 'Spotify receiver on' : 'Spotify receiver off';
		playerSession('write', 'spotifysvc', $_POST['spotifysvc']);
	}

	if (isset($title)) {
		submitJob('spotifysvc', '', $title, '');
	}
}
// resume mpd after spotify
if (isset($_POST['update_rsmafterspot'])) {
	playerSession('write', 'rsmafterspot', $_POST['rsmafterspot']);
	$_SESSION['notify']['title'] = 'Setting updated';
}
// restart spotify
if (isset($_POST['spotifyrestart']) && $_POST['spotifyrestart'] == 1 && $_SESSION['spotifysvc'] == '1') {
	submitJob('spotifysvc', '', 'Spotify receiver restarted', '');
}
// clear credential cache
if (isset($_POST['spotify_clear_credentials']) && $_POST['spotify_clear_credentials'] == 1) {
	submitJob('spotify_clear_credentials', '', 'Credential cache cleared', '');
}

// SQUEEZELITE RENDERER
if (isset($_POST['update_sl_settings'])) {
	if (isset($_POST['slsvc']) && $_POST['slsvc'] != $_SESSION['slsvc']) {
		$title = $_POST['slsvc'] == 1 ? 'Squeezelite renderer on' : 'Squeezelite renderer off';
		playerSession('write', 'slsvc', $_POST['slsvc']);
	}

	if (isset($title)) {
		submitJob('slsvc', '', $title, '');
	}
}
// resume mpd after squeezelite
if (isset($_POST['update_rsmaftersl'])) {
	playerSession('write', 'rsmaftersl', $_POST['rsmaftersl']);
	$_SESSION['notify']['title'] = 'Setting updated';
}
// restart squeezelite
if (isset($_POST['slrestart']) && $_POST['slrestart'] == 1) {
	submitJob('slrestart', '', 'Squeezelite restarted', '');
}

// UPNP/DLNA

// upnp client for mpd
if (isset($_POST['update_upnp_settings'])) {
	$currentUpnpName = $_SESSION['upnpname'];

	if (isset($_POST['upnpname']) && $_POST['upnpname'] != $_SESSION['upnpname']) {
		$title = 'UPnP name updated';
		playerSession('write', 'upnpname', $_POST['upnpname']);
	}

	if (isset($_POST['upnpsvc']) && $_POST['upnpsvc'] != $_SESSION['upnpsvc']) {
		$title = $_POST['upnpsvc'] == 1 ? 'UPnP renderer on' : 'UPnP renderer off';
		playerSession('write', 'upnpsvc', $_POST['upnpsvc']);
	}

	if (isset($title)) {
		submitJob('upnpsvc', '"' . $currentUpnpName . '" ' . '"' . $_POST['upnpname'] . '"', $title, '');
	}
}
// restart upnp client
if (isset($_POST['upnprestart']) && $_POST['upnprestart'] == 1 && $_SESSION['upnpsvc'] == '1') {
	submitJob('upnpsvc', '', 'UPnP renderer restarted', '');
}

// dlna server
if (isset($_POST['update_dlna_settings'])) {
	$currentDlnaName = $_SESSION['dlnaname'];

	if (isset($_POST['dlnaname']) && $_POST['dlnaname'] != $_SESSION['dlnaname']) {
		$title = 'DLNA name updated';
		playerSession('write', 'dlnaname', $_POST['dlnaname']);
	}

	if (isset($_POST['dlnasvc']) && $_POST['dlnasvc'] != $_SESSION['dlnasvc']) {
		$title = $_POST['dlnasvc'] == 1 ? 'DLNA server on' : 'DLNA server off';
		$msg = $_POST['dlnasvc'] == 1 ? 'DB rebuild initiated' : '';
		playerSession('write', 'dlnasvc', $_POST['dlnasvc']);
	}

	if (isset($title)) {
		submitJob('minidlna', '"' . $currentDlnaName . '" ' . '"' . $_POST['dlnaname'] . '"', $title, $msg);
	}
}
// rebuild dlna db
if (isset($_POST['rebuild_dlnadb'])) {
	if ($_SESSION['dlnasvc'] == 1) {
		submitJob('dlnarebuild', '', 'DB rebuild initiated', '');
	}
	else {
		$_SESSION['notify']['title'] = 'Turn DLNA server on';
		$_SESSION['notify']['msg'] = 'DB rebuild will initiate';
	}
}
// upnp browser
if (isset($_POST['update_upnp_browser'])) {
	if (isset($_POST['upnp_browser']) && $_POST['upnp_browser'] != $_SESSION['upnp_browser']) {
		$title = $_POST['upnp_browser'] == 1 ? 'UPnP browser on' : 'UPnP browser off';
		playerSession('write', 'upnp_browser', $_POST['upnp_browser']);
	}

	if (isset($title)) {
		submitJob('upnp_browser', $_POST['upnp_browser'], $title, '');
	}
}
// restart upnp browser
if (isset($_POST['upnp_browser_restart']) && $_POST['upnp_browser_restart'] == 1 && $_SESSION['upnp_browser'] == '1') {
	submitJob('upnp_browser', '', 'UPnP browser restarted', '');
}

// SERVICES

// audio scrobbler
if (isset($_POST['update_mpdas'])) {
	if (isset($_POST['mpdasuser']) && $_POST['mpdasuser'] != $_SESSION['mpdasuser']) {
		$title = "Scrobbler credentials updated";
		playerSession('write', 'mpdasuser', $_POST['mpdasuser']);
	}

	if (isset($_POST['mpdaspwd']) && $_POST['mpdaspwd'] != $_SESSION['mpdaspwd']) {
		$title = "Scrobbler credentials updated";
		playerSession('write', 'mpdaspwd', $_POST['mpdaspwd']);
	}

	if (isset($_POST['mpdassvc']) && $_POST['mpdassvc'] != $_SESSION['mpdassvc']) {
		$title = $_POST['mpdassvc'] == 1 ? 'Audio Scrobbler on' : 'Audio Scrobbler off';
		playerSession('write', 'mpdassvc', $_POST['mpdassvc']);
	}

	if (isset($title)) {
		submitJob('mpdassvc', $_POST['mpdassvc'], $title, '');
	}
}

session_write_close();

// DEVICE

// i2s audio device
$result = sdbquery("SELECT name FROM cfg_audiodev WHERE iface='I2S' AND list='yes'", cfgdb_connect());
$array = array();
$array[0]['name'] = 'none';
$dacList = array_merge($array, $result);
foreach ($dacList as $dac) {
	$dacName = ($dac['name'] == 'none') ? 'None' : $dac['name'];
	$selected = ($_SESSION['i2sdevice'] == $dac['name']) ? ' selected' : '';
	$_i2s['i2sdevice'] .= sprintf('<option value="%s"%s>%s</option>\n', $dac['name'], $selected, $dacName);
}

// driver options
$result = sdbquery("SELECT chipoptions, driver, drvoptions FROM cfg_audiodev WHERE name='" . $_SESSION['i2sdevice'] . "'", cfgdb_connect());
if (!empty($result[0]['drvoptions'])) {
	$_select['drvoptions'] .= "<option value=\"Enabled\" " . ((strpos($result[0]['driver'], $result[0]['drvoptions']) !== false) ? "selected" : "") . ">" . $result[0]['drvoptions'] . " Enabled</option>\n";
	$_select['drvoptions'] .= "<option value=\"Disabled\" " . ((strpos($result[0]['driver'], $result[0]['drvoptions']) === false) ? "selected" : "") . ">" . $result[0]['drvoptions'] . " Disabled</option>\n";
}
else {
	$_select['drvoptions'] .= "<option value=\"none\" selected>None available</option>\n";
}

// chip/device options
if (empty($result[0]['chipoptions'])) {
	$_chipoptions_btn = '<button class="btn btn-primary btn-medium" style="margin-top:0px;" disabled>Edit options</button><br>';
}
else {
	//$_chipoptions_btn = '<a class="btn btn-primary btn-medium" style="margin-top:0px;" href="chp-config.php">Edit options</a><br>';
	$_chipoptions_btn = '<a href="chp-config.php"><button class="btn btn-primary btn-medium" style="margin-top:0px;">Edit options</button></a><br>';
}

// alsa volume
if ($_SESSION['alsavolume'] == 'none') {
	$_alsa_volume = '';
	$_alsa_volume_readonly = 'readonly';
	$_alsa_volume_hide = 'hide';
	$_alsa_volume_msg = "<span class=\"help-block-configs help-block-margin\">Hardware volume controller not detected</span>";
}
else {
	$mixername = getMixerName($_SESSION['i2sdevice']);
	// TC there is a visudo config that allows this cmd to be run by www-data, the user context for this page
	$result = sysCmd("/var/www/command/util.sh get-alsavol " . '"' . $mixername . '"');
	$_alsa_volume = str_replace('%', '', $result[0]);
	if (isset($_POST['alsavolume']) && $_alsa_volume != $_POST['alsavolume']) { // worker has not processed the change yet
		$_alsa_volume = $_POST['alsavolume'];
	}
	$_alsa_volume_readonly = '';
	$_alsa_volume_hide = '';
	$_alsa_volume_msg = '';
}

// MPD

// mpd version
$_select['mpdver'] .= "<option value=\"0.20.23\" " . (($_SESSION['mpdver'] == '0.20.23') ? "selected" : "") . ">0.20.23 (Default)</option>\n";
//$_select['mpdver'] .= "<option value=\"0.22-git\" " . (($_SESSION['mpdver'] == '0.22-git') ? "selected" : "") . ">0.22-git (Testing)</option>\n";

// auto-shuffle
$_select['ashufflesvc1'] .= "<input type=\"radio\" name=\"ashufflesvc\" id=\"toggleashufflesvc1\" value=\"1\" " . (($_SESSION['ashufflesvc'] == 1) ? "checked=\"checked\"" : "") . ">\n";
$_select['ashufflesvc0'] .= "<input type=\"radio\" name=\"ashufflesvc\" id=\"toggleashufflesvc2\" value=\"0\" " . (($_SESSION['ashufflesvc'] == 0) ? "checked=\"checked\"" : "") . ">\n";

// autoplay after start
$_select['autoplay1'] .= "<input type=\"radio\" name=\"autoplay\" id=\"toggleautoplay1\" value=\"1\" " . (($_SESSION['autoplay'] == 1) ? "checked=\"checked\"" : "") . ">\n";
$_select['autoplay0'] .= "<input type=\"radio\" name=\"autoplay\" id=\"toggleautoplay2\" value=\"0\" " . (($_SESSION['autoplay'] == 0) ? "checked=\"checked\"" : "") . ">\n";

// rotary encoder
$_select['rotaryenc1'] .= "<input type=\"radio\" name=\"rotaryenc\" id=\"togglerotaryenc1\" value=\"1\" " . (($_SESSION['rotaryenc'] == 1) ? "checked=\"checked\"" : "") . ">\n";
$_select['rotaryenc0'] .= "<input type=\"radio\" name=\"rotaryenc\" id=\"togglerotaryenc2\" value=\"0\" " . (($_SESSION['rotaryenc'] == 0) ? "checked=\"checked\"" : "") . ">\n";
$_select['rotenc_params'] = $_SESSION['rotenc_params'];

// polarity invrsion
$_select['invert_polarity1'] .= "<input type=\"radio\" name=\"invert_polarity\" id=\"toggle_invert_polarity1\" value=\"1\" " . (($_SESSION['invert_polarity'] == 1) ? "checked=\"checked\"" : "") . ">\n";
$_select['invert_polarity0'] .= "<input type=\"radio\" name=\"invert_polarity\" id=\"toggle_invert_polarity2\" value=\"0\" " . (($_SESSION['invert_polarity'] == 0) ? "checked=\"checked\"" : "") . ">\n";

// mpd crossfade
$_mpdcrossfade = $_SESSION['mpdcrossfade'];

// http streaming server
$_select['mpd_httpd1'] .= "<input type=\"radio\" name=\"mpd_httpd\" id=\"toggle-mpd-httpd1\" value=\"1\" " . (($_SESSION['mpd_httpd'] == 1) ? "checked=\"checked\"" : "") . ">\n";
$_select['mpd_httpd0'] .= "<input type=\"radio\" name=\"mpd_httpd\" id=\"toggle-mpd-httpd2\" value=\"0\" " . (($_SESSION['mpd_httpd'] == 0) ? "checked=\"checked\"" : "") . ">\n";
// port
$_mpd_httpd_port = $_SESSION['mpd_httpd_port'];
// encoder
$_select['mpd_httpd_encoder'] .= "<option value=\"flac\" " . (($_SESSION['mpd_httpd_encoder'] == 'flac') ? "selected" : "") . ">FLAC</option>\n";
$_select['mpd_httpd_encoder'] .= "<option value=\"lame\" " . (($_SESSION['mpd_httpd_encoder'] == 'lame') ? "selected" : "") . ">LAME (MP3)</option>\n";

// DSP

// only one of polarity inversion, crossfeed, alsaequal or eqfa4p can be on
$_invpolarity_set_disabled = ($_SESSION['crossfeed'] != 'Off' || $_SESSION['eqfa4p'] != 'Off' || $_SESSION['alsaequal'] != 'Off') ? 'disabled' : '';
$_crossfeed_set_disabled = ($_SESSION['invert_polarity'] != '0' || $_SESSION['eqfa4p'] != 'Off' || $_SESSION['alsaequal'] != 'Off') ? 'disabled' : '';
$_eqfa4p_set_disabled = ($_SESSION['invert_polarity'] != '0' || $_SESSION['crossfeed'] != 'Off' || $_SESSION['alsaequal'] != 'Off') ? 'disabled' : '';
$_alsaequal_set_disabled = ($_SESSION['invert_polarity'] != '0' || $_SESSION['crossfeed'] != 'Off' || $_SESSION['eqfa4p'] != 'Off') ? 'disabled' : '';

// crossfeed
$_select['crossfeed'] .= "<option value=\"Off\" " . (($_SESSION['crossfeed'] == 'Off' OR $_SESSION['crossfeed'] == '') ? "selected" : "") . ">Off</option>\n";
if ($_crossfeed_set_disabled == '') {
	$_select['crossfeed'] .= "<option value=\"700 4.5\" " . (($_SESSION['crossfeed'] == '700 4.5') ? "selected" : "") . ">700 Hz 4.5 dB</option>\n";
	$_select['crossfeed'] .= "<option value=\"700 6.0\" " . (($_SESSION['crossfeed'] == '700 6.0') ? "selected" : "") . ">700 Hz 6.0 dB</option>\n";
	$_select['crossfeed'] .= "<option value=\"650 9.5\" " . (($_SESSION['crossfeed'] == '650 9.5') ? "selected" : "") . ">650 Hz 9.5 dB</option>\n";
}

// parametric equalizer
$result = sdbquery('SELECT curve_name FROM cfg_eqfa4p', cfgdb_connect());
$array = array();
$array[0]['curve_name'] = 'Off';
$curveList = $_eqfa4p_set_disabled == '' ? array_merge($array, $result) : $array;
foreach ($curveList as $curve) {
	$curveName = $curve['curve_name'];
	$selected = ($_SESSION['eqfa4p'] == $curve['curve_name']) ? 'selected' : '';
	$_select['eqfa4p'] .= sprintf('<option value="%s" %s>%s</option>\n', $curve['curve_name'], $selected, $curveName);
}

// graphic equalizer
$result = sdbquery('SELECT curve_name FROM cfg_eqalsa', cfgdb_connect());
$array = array();
$array[0]['curve_name'] = 'Off';
$curveList = $_alsaequal_set_disabled == '' ? array_merge($array, $result) : $array;
foreach ($curveList as $curve) {
	$curveName = $curve['curve_name'];
	$selected = ($_SESSION['alsaequal'] == $curve['curve_name']) ? 'selected' : '';
	$_select['alsaequal'] .= sprintf('<option value="%s" %s>%s</option>\n', $curve['curve_name'], $selected, $curveName);
}

// RENDERERS

// bluetooth
$_select['btsvc1'] .= "<input type=\"radio\" name=\"btsvc\" id=\"togglebtsvc1\" value=\"1\" " . (($_SESSION['btsvc'] == 1) ? "checked=\"checked\"" : "") . ">\n";
$_select['btsvc0'] .= "<input type=\"radio\" name=\"btsvc\" id=\"togglebtsvc2\" value=\"0\" " . (($_SESSION['btsvc'] == 0) ? "checked=\"checked\"" : "") . ">\n";
$_select['btname'] = $_SESSION['btname'];
$_select['pairing_agent1'] .= "<input type=\"radio\" name=\"pairing_agent\" id=\"toggle-pairing-agent1\" value=\"1\" " . (($_SESSION['pairing_agent'] == 1) ? "checked=\"checked\"" : "") . ">\n";
$_select['pairing_agent0'] .= "<input type=\"radio\" name=\"pairing_agent\" id=\"toggle-pairing-agent2\" value=\"0\" " . (($_SESSION['pairing_agent'] == 0) ? "checked=\"checked\"" : "") . ">\n";
$_select['btmulti1'] .= "<input type=\"radio\" name=\"btmulti\" id=\"togglebtmulti1\" value=\"1\" " . (($_SESSION['btmulti'] == 1) ? "checked=\"checked\"" : "") . ">\n";
$_select['btmulti0'] .= "<input type=\"radio\" name=\"btmulti\" id=\"togglebtmulti2\" value=\"0\" " . (($_SESSION['btmulti'] == 0) ? "checked=\"checked\"" : "") . ">\n";
$_select['rsmafterbt'] .= "<option value=\"1\" " . (($_SESSION['rsmafterbt'] == '1') ? "selected" : "") . ">Yes</option>\n";
$_select['rsmafterbt'] .= "<option value=\"0\" " . (($_SESSION['rsmafterbt'] == '0') ? "selected" : "") . ">No</option>\n";
$_bt_restart = $_SESSION['btsvc'] == '1' ? '#bt-restart' : '#notarget';

// airplay
if ($_SESSION['feat_bitmask'] & FEAT_AIRPLAY) {
	$_feat_airplay = '';
	$_select['airplaysvc1'] .= "<input type=\"radio\" name=\"airplaysvc\" id=\"toggleairplaysvc1\" value=\"1\" " . (($_SESSION['airplaysvc'] == 1) ? "checked=\"checked\"" : "") . ">\n";
	$_select['airplaysvc0'] .= "<input type=\"radio\" name=\"airplaysvc\" id=\"toggleairplaysvc2\" value=\"0\" " . (($_SESSION['airplaysvc'] == 0) ? "checked=\"checked\"" : "") . ">\n";
	$_select['airplayname'] = $_SESSION['airplayname'];
	$_select['rsmafterapl'] .= "<option value=\"Yes\" " . (($_SESSION['rsmafterapl'] == 'Yes') ? "selected" : "") . ">Yes</option>\n";
	$_select['rsmafterapl'] .= "<option value=\"No\" " . (($_SESSION['rsmafterapl'] == 'No') ? "selected" : "") . ">No</option>\n";
	$_airplay_restart = $_SESSION['airplaysvc'] == '1' ? '#airplay-restart' : '#notarget';
}
else {
	$_feat_airplay = 'hide';
}

// spotify
if ($_SESSION['feat_bitmask'] & FEAT_SPOTIFY) {
	$_feat_spotify = '';
	$_select['spotifysvc1'] .= "<input type=\"radio\" name=\"spotifysvc\" id=\"togglespotifysvc1\" value=\"1\" " . (($_SESSION['spotifysvc'] == 1) ? "checked=\"checked\"" : "") . ">\n";
	$_select['spotifysvc0'] .= "<input type=\"radio\" name=\"spotifysvc\" id=\"togglespotifysvc2\" value=\"0\" " . (($_SESSION['spotifysvc'] == 0) ? "checked=\"checked\"" : "") . ">\n";
	$_select['spotifyname'] = $_SESSION['spotifyname'];
	$_select['rsmafterspot'] .= "<option value=\"Yes\" " . (($_SESSION['rsmafterspot'] == 'Yes') ? "selected" : "") . ">Yes</option>\n";
	$_select['rsmafterspot'] .= "<option value=\"No\" " . (($_SESSION['rsmafterspot'] == 'No') ? "selected" : "") . ">No</option>\n";
	$_spotify_restart = $_SESSION['spotifysvc'] == '1' ? '#spotify-restart' : '#notarget';
}
else {
	$_feat_spotify = 'hide';
}

// squeezelite renderer
if ($_SESSION['feat_bitmask'] & FEAT_SQUEEZELITE) {
	$_feat_squeezelite = '';
	$_select['slsvc1'] .= "<input type=\"radio\" name=\"slsvc\" id=\"toggleslsvc1\" value=\"1\" " . (($_SESSION['slsvc'] == 1) ? "checked=\"checked\"" : "") . ">\n";
	$_select['slsvc0'] .= "<input type=\"radio\" name=\"slsvc\" id=\"toggleslsvc2\" value=\"0\" " . (($_SESSION['slsvc'] == 0) ? "checked=\"checked\"" : "") . ">\n";
	$_select['rsmaftersl'] .= "<option value=\"Yes\" " . (($_SESSION['rsmaftersl'] == 'Yes') ? "selected" : "") . ">Yes</option>\n";
	$_select['rsmaftersl'] .= "<option value=\"No\" " . (($_SESSION['rsmaftersl'] == 'No') ? "selected" : "") . ">No</option>\n";
	$_sl_restart = $_SESSION['slsvc'] == '1' ? '#sl-restart' : '#notarget';
}
else {
	$_feat_squeezelite = 'hide';
}

// UPnP/DLNA

// upnp mpd proxy
if ($_SESSION['feat_bitmask'] & FEAT_UPMPDCLI) {
	$_feat_upmpdcli = '';
	$_select['upnpsvc1'] .= "<input type=\"radio\" name=\"upnpsvc\" id=\"toggleupnpsvc1\" value=\"1\" " . (($_SESSION['upnpsvc'] == 1) ? "checked=\"checked\"" : "") . ">\n";
	$_select['upnpsvc0'] .= "<input type=\"radio\" name=\"upnpsvc\" id=\"toggleupnpsvc2\" value=\"0\" " . (($_SESSION['upnpsvc'] == 0) ? "checked=\"checked\"" : "") . ">\n";
	$_select['upnpname'] = $_SESSION['upnpname'];
	$_upnp_restart = $_SESSION['upnpsvc'] == '1' ? '#upnp-restart' : '#notarget';
}
else {
	$_feat_upmpdcli = 'hide';
}

// dlna server
if ($_SESSION['feat_bitmask'] & FEAT_MINIDLNA) {
	$_feat_minidlna = '';
	$_select['dlnasvc1'] .= "<input type=\"radio\" name=\"dlnasvc\" id=\"toggledlnasvc1\" value=\"1\" " . (($_SESSION['dlnasvc'] == 1) ? "checked=\"checked\"" : "") . ">\n";
	$_select['dlnasvc0'] .= "<input type=\"radio\" name=\"dlnasvc\" id=\"toggledlnasvc2\" value=\"0\" " . (($_SESSION['dlnasvc'] == 0) ? "checked=\"checked\"" : "") . ">\n";
	$_select['dlnaname'] = $_SESSION['dlnaname'];
	$_select['hostip'] = getHostIp();
}
else {
	$_feat_minidlna = 'hide';
}

// upnp browser
if ($_SESSION['feat_bitmask'] & FEAT_DJMOUNT) {
	$_feat_djmount = '';
	$_select['upnp_browser1'] .= "<input type=\"radio\" name=\"upnp_browser\" id=\"toggle_upnp_browser1\" value=\"1\" " . (($_SESSION['upnp_browser'] == 1) ? "checked=\"checked\"" : "") . ">\n";
	$_select['upnp_browser0'] .= "<input type=\"radio\" name=\"upnp_browser\" id=\"toggle_upnp_browser2\" value=\"0\" " . (($_SESSION['upnp_browser'] == 0) ? "checked=\"checked\"" : "") . ">\n";
	$_upnp_browser_restart = $_SESSION['upnp_browser'] == '1' ? '#upnp-browser-restart' : '#notarget';
}
else {
	$_feat_djmount = 'hide';
}

// SERVICES

// audio scrobbler
if ($_SESSION['feat_bitmask'] & FEAT_MPDAS) {
	$_feat_mpdas = '';
	$_select['mpdassvc1'] .= "<input type=\"radio\" name=\"mpdassvc\" id=\"togglempdassvc1\" value=\"1\" " . (($_SESSION['mpdassvc'] == 1) ? "checked=\"checked\"" : "") . ">\n";
	$_select['mpdassvc0'] .= "<input type=\"radio\" name=\"mpdassvc\" id=\"togglempdassvc2\" value=\"0\" " . (($_SESSION['mpdassvc'] == 0) ? "checked=\"checked\"" : "") . ">\n";
	$_select['mpdasuser'] = $_SESSION['mpdasuser'];
	$_select['mpdaspwd'] = $_SESSION['mpdaspwd'];
}
else {
	$_feat_mpdas = 'hide';
}

waitWorker(1, 'snd-config');

$tpl = "snd-config.html";
$section = basename(__FILE__, '.php');
storeBackLink($section, $tpl);

include('/var/local/www/header.php');
eval("echoTemplate(\"" . getTemplate("templates/$tpl") . "\");");
include('footer.php');
