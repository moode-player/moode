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
require_once 'common.php';
require_once 'alsa.php';
require_once 'cdsp.php';
require_once 'eqp.php';
require_once 'mpd.php';
require_once 'network.php';
require_once 'session.php';
require_once 'sql.php';

$dbh = sqlConnect();
$cdsp = new CamillaDsp($_SESSION['camilladsp'], $_SESSION['cardnum'], $_SESSION['camilladsp_quickconv']);
phpSession('open');

// AUDIO OUTPUT

// Output device
if (isset($_POST['update_output_device']) && $_POST['output_device'] != $_SESSION['cardnum']) {

	// Airplay and Spotify will be restarted if device (cardnum) has changed
	$device_chg = $_POST['output_device'] != $_SESSION['cardnum'] ? 1 : 0;

	// Mixer change (no mixer change)
	$mixer_chg = 0;

	// Update SQL table
	sqlUpdate('cfg_mpd', $dbh, 'device', $_POST['output_device']);

	// Submit job
	$queue_args = $device_chg . ',' . $mixer_chg;
	submitJob('mpdcfg', $queue_args, 'Output device updated', 'MPD restarted');
}

// Volume type
if (isset($_POST['update_volume_type']) && $_POST['mixer_type'] != $_SESSION['mpdmixer']) {
	// Changing to Fixed (0dB)
	if ($_POST['mixer_type'] == 'none') {
		$mixer_chg = 'fixed';
	}
	// Changing from Fixed (0dB)
	elseif ($_SESSION['mpdmixer'] == 'none') {
		$mixer_chg = $_POST['mixer_type'];
	}
	// Change between hardware, software or null mixer
	else {
		$mixer_chg = 0;
	}

	// Device change (no device change)
	$device_chg = 0;

	// Update SQL table
	sqlUpdate('cfg_mpd', $dbh, 'mixer_type', $_POST['mixer_type']);

	// Submit job
	$queue_args = $device_chg . ',' . $mixer_chg;
	submitJob('mpdcfg', $queue_args, 'Volume type updated', 'MPD restarted');
}

// I2S AUDIO DEVICE

// Flag that controls what is displayed in the Output device field after changing I2S device or overlay
$_reboot_required = 0;

// Named device
if (isset($_POST['update_i2s_device'])) {
	if (isset($_POST['i2sdevice']) && $_POST['i2sdevice'] != $_SESSION['i2sdevice']) {
		phpSession('write', 'i2sdevice', $_POST['i2sdevice']);
		$title = 'I2S device updated';
		$msg = $_POST['i2sdevice'] == 'None' ?
			'<b>Restart required</b><br>After restart select an Output device.' :
			'<b>Restart required</b><br>After restart edit chip and/or driver options.';

		$_reboot_required = 1;
		submitJob('i2sdevice', $_POST['i2sdevice'], $title, $msg, 300);
	}
}
// Device overlay
if (isset($_POST['update_i2s_overlay'])) {
	if (isset($_POST['i2soverlay']) && $_POST['i2soverlay'] != $_SESSION['i2soverlay']) {
		phpSession('write', 'i2soverlay', $_POST['i2soverlay']);
		$title = 'I2S overlay updated';
		$msg = $_POST['i2soverlay'] == 'None' ?
			'<b>Restart required</b><br>After restart select Output device.' :
			'<b>Restart required</b>';

		$_reboot_required = 1;
		submitJob('i2sdevice', 'None', $title, $msg, 300);
	}
}
// Driver options
if (isset($_POST['update_drvoptions'])) {
	if (isset($_POST['drvoptions']) && $_POST['drvoptions'] != 'none') {
		$result = sqlQuery("SELECT driver, drvoptions FROM cfg_audiodev WHERE name='" . $_SESSION['i2sdevice'] . "'", $dbh);
		$driver = explode(',', $result[0]['driver']);
		$driverupd = $_POST['drvoptions'] == 'Enabled' ? $driver[0] . ',' . $result[0]['drvoptions'] : $driver[0];

		$result = sqlQuery("UPDATE cfg_audiodev SET driver='" . $driverupd . "' WHERE name='" . $_SESSION['i2sdevice'] . "'", $dbh);
		submitJob('i2sdevice', $_SESSION['i2sdevice'], 'Driver options updated', 'Restart required');
	}
}

// ALSA OPTIONS

// Max volume
if (isset($_POST['update_alsavolume_max'])) {
	if (isset($_POST['alsavolume_max'])) {
		submitJob('alsavolume_max', $_POST['alsavolume_max'], 'Max volume updated', '');
		phpSession('write', 'alsavolume_max', $_POST['alsavolume_max']);
	}
}
// Output mode
if (isset($_POST['update_alsa_output_mode'])) {
	if (isset($_POST['alsa_output_mode']) && $_POST['alsa_output_mode'] != $_SESSION['alsa_output_mode']) {
		$old_output_mode = $_SESSION['alsa_output_mode'];
		$new_output_mode = $_POST['alsa_output_mode'];
		// NOTE: Update session first for functions used in job
		phpSession('write', 'alsa_output_mode', $new_output_mode);
		submitJob('alsa_output_mode', $old_output_mode, 'Output mode updated', '');
	}
}
// Loopback
if (isset($_POST['update_alsa_loopback'])) {
	if (isset($_POST['alsa_loopback']) && $_POST['alsa_loopback'] != $_SESSION['alsa_loopback']) {

		// Check to see if module is in use
		if ($_POST['alsa_loopback'] == 'Off') {
			$result = sysCmd('sudo modprobe -r snd-aloop');
			if (!empty($result)) {
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

// MPD OPTIONS

// General

// Restart mpd
if (isset($_POST['mpdrestart']) && $_POST['mpdrestart'] == 1) {
	submitJob('mpdrestart', '', 'MPD restarted', '');
}
// Autoplay last played item after reboot/powerup
if (isset($_POST['autoplay']) && $_POST['autoplay'] != $_SESSION['autoplay']) {
	$_SESSION['notify']['title'] = $_POST['autoplay'] == 1 ? 'Autoplay on' : 'Autoplay off';
	phpSession('write', 'autoplay', $_POST['autoplay']);
}

// Auto-shuffle

// Service
if (isset($_POST['ashufflesvc']) && $_POST['ashufflesvc'] != $_SESSION['ashufflesvc']) {
	$_SESSION['notify']['title'] = $_POST['ashufflesvc'] == 1 ? 'Auto-shuffle on' : 'Auto-shuffle off';
	$_SESSION['notify']['duration'] = 3;
	phpSession('write', 'ashufflesvc', $_POST['ashufflesvc']);

	// Turn off MPD random play so no conflict
	$sock = openMpdSock('localhost', 6600);
	sendMpdCmd($sock, 'random 0');
	$resp = readMpdResp($sock);

	// Kill the service if indicated
	if ($_POST['ashufflesvc'] == 0) {
		sysCmd('killall -s 9 ashuffle > /dev/null');
		phpSession('write', 'ashuffle', '0');
		sendMpdCmd($sock, 'consume 0');
		$resp = readMpdResp($sock);
	}
}
// Mode
if (isset($_POST['update_ashuffle_mode']) && $_POST['ashuffle_mode'] != $_SESSION['ashuffle_mode']) {
	phpSession('write', 'ashuffle_mode', $_POST['ashuffle_mode']);
	if ($_SESSION['ashuffle'] == '1') {
		$_SESSION['notify']['title'] = 'Mode updated, random turned off';
		$_SESSION['notify']['duration'] = 3;
		stopAutoShuffle();
	}
	else {
		$_SESSION['notify']['title'] = 'Mode updated';
		$_SESSION['notify']['duration'] = 3;
	}
}
// Filter
if (isset($_POST['update_ashuffle_filter']) && $_POST['ashuffle_filter'] != $_SESSION['ashuffle_filter']) {
	$trim_filter = trim($_POST['ashuffle_filter']);
	phpSession('write', 'ashuffle_filter', ($trim_filter == '' ? 'None' : $trim_filter));
	if ($_SESSION['ashuffle'] == '1') {
		$_SESSION['notify']['title'] = 'Filter updated, random turned off';
		$_SESSION['notify']['duration'] = 3;
		stopAutoShuffle();
	}
	else {
		$_SESSION['notify']['title'] = 'Filter updated';
		$_SESSION['notify']['duration'] = 3;
	}
}

// Volume options

// Volume step limit
if (isset($_POST['volume_step_limit']) && $_POST['volume_step_limit'] != $_SESSION['volume_step_limit']) {
	phpSession('write', 'volume_step_limit', $_POST['volume_step_limit']);
	$_SESSION['notify']['title'] = 'Setting updated';
}
// Volume MPD mmax
if (isset($_POST['volume_mpd_max']) && $_POST['volume_mpd_max'] != $_SESSION['volume_mpd_max']) {
	phpSession('write', 'volume_mpd_max', $_POST['volume_mpd_max']);
	$_SESSION['notify']['title'] = 'Setting updated';
}
// Display dB volume
if (isset($_POST['update_volume_db_display']) && $_POST['volume_db_display'] != $_SESSION['volume_db_display']) {
	phpSession('write', 'volume_db_display', $_POST['volume_db_display']);
	$_SESSION['notify']['title'] = 'Setting updated';
}
// USB volume knob
if (isset($_POST['update_usb_volknob']) && $_POST['usb_volknob'] != $_SESSION['usb_volknob']) {
	$title = $_POST['usb_volknob'] == 1 ? 'USB volume knob on' : 'USB volume knob off';
	submitJob('usb_volknob', $_POST['usb_volknob'], $title, '');
	phpSession('write', 'usb_volknob', $_POST['usb_volknob']);
}
// Rotary encoder
if (isset($_POST['update_rotenc'])) {
	if (isset($_POST['rotenc_params']) && $_POST['rotenc_params'] != $_SESSION['rotenc_params']) {
		$title = 'Rotenc params updated';
		phpSession('write', 'rotenc_params', $_POST['rotenc_params']);
	}

	if (isset($_POST['rotaryenc']) && $_POST['rotaryenc'] != $_SESSION['rotaryenc']) {
		$title = $_POST['rotaryenc'] == 1 ? 'Rotary encoder on' : 'Rotary encoder off';
		phpSession('write', 'rotaryenc', $_POST['rotaryenc']);
	}

	if (isset($title)) {
		submitJob('rotaryenc', $_POST['rotaryenc'], $title, '');
	}
}

// DSP options

// Crossfade
if (isset($_POST['mpdcrossfade']) && $_POST['mpdcrossfade'] != $_SESSION['mpdcrossfade']) {
	submitJob('mpdcrossfade', $_POST['mpdcrossfade'], 'Crossfade settings updated', '');
	phpSession('write', 'mpdcrossfade', $_POST['mpdcrossfade']);
}
// Crossfeed
if (isset($_POST['crossfeed']) && $_POST['crossfeed'] != $_SESSION['crossfeed']) {
	phpSession('write', 'crossfeed', $_POST['crossfeed']);
	submitJob('crossfeed', $_POST['crossfeed'], 'Crossfeed ' . ($_POST['crossfeed'] == 'Off' ? 'off' : 'on'), '');
}
// Polarity inversion
if (isset($_POST['update_invert_polarity']) && $_POST['invert_polarity'] != $_SESSION['invert_polarity']) {
	$title = $_POST['invert_polarity'] == 1 ? 'Polarity inversion on' : 'Polarity inversion off';
	submitJob('invpolarity', $_POST['invert_polarity'], $title, '');
	phpSession('write', 'invert_polarity', $_POST['invert_polarity']);
}

// HTTP streaming

// Server
if (isset($_POST['mpd_httpd']) && $_POST['mpd_httpd'] != $_SESSION['mpd_httpd']) {
	$title = $_POST['mpd_httpd'] == 1 ? 'HTTP server on' : 'HTTP server off';
	submitJob('mpd_httpd', $_POST['mpd_httpd'], $title, '');
	phpSession('write', 'mpd_httpd', $_POST['mpd_httpd']);
}
// Port
if (isset($_POST['mpd_httpd_port']) && $_POST['mpd_httpd_port'] != $_SESSION['mpd_httpd_port']) {
	phpSession('write', 'mpd_httpd_port', $_POST['mpd_httpd_port']);
	submitJob('mpd_httpd_port', $_POST['mpd_httpd_port'], 'HTTP port updated', 'MPD restarted');
}
// Encoder
if (isset($_POST['mpd_httpd_encoder']) && $_POST['mpd_httpd_encoder'] != $_SESSION['mpd_httpd_encoder']) {
	phpSession('write', 'mpd_httpd_encoder', $_POST['mpd_httpd_encoder']);
	submitJob('mpd_httpd_encoder', $_POST['mpd_httpd_encoder'], 'HTTP encoder updated', 'MPD restarted');
}

// EQUALIZERS

// Parametric eq
$eqfa12p = Eqp12($dbh);
if (isset($_POST['eqfa12p']) && ((intval($_POST['eqfa12p']) ? "On" : "Off") != $_SESSION['eqfa12p'] || intval($_POST['eqfa12p']) != $eqfa12p->getActivePresetIndex())) {
	// Pass old,new curve name to worker job
	$currentActive = $eqfa12p->getActivePresetIndex();
	$newActive = intval($_POST['eqfa12p']);
	$eqfa12p->setActivePresetIndex($newActive);
	phpSession('write', 'eqfa12p', $newActive == 0 ? "Off" : "On");
	submitJob('eqfa12p', $currentActive . ',' . $newActive, 'Parametric EQ updated', 'MPD restarted');
}
unset($eqfa12p);

// Graphic eq
if (isset($_POST['alsaequal']) && $_POST['alsaequal'] != $_SESSION['alsaequal']) {
	// Pass old,new curve name to worker job
	phpSession('write', 'alsaequal', $_POST['alsaequal']);
	submitJob('alsaequal', $_SESSION['alsaequal'] . ',' . $_POST['alsaequal'], 'Graphic EQ updated', '');
}

// CamillaDSP
if (isset($_POST['update_camilladsp']) && isset($_POST['camilladsp']) && $_POST['camilladsp'] != $_SESSION['camilladsp']) {
	$currentMode = $_SESSION['camilladsp'];
	phpSession('write', 'camilladsp', $_POST['camilladsp']);
	$cdsp->selectConfig($_POST['camilladsp']);
	if ($_SESSION['cdsp_fix_playback'] == 'Yes' ) {
		$cdsp->setPlaybackDevice($_SESSION['cardnum'], $_SESSION['alsa_output_mode']);
	}

    if ( $_SESSION['camilladsp'] != $currentMode && ( $_SESSION['camilladsp'] == 'off' || $currentMode == 'off')) {
		submitJob('camilladsp', $_POST['camilladsp'], 'CamillaDSP ' . $cdsp->getConfigLabel($_POST['camilladsp']), '');
	} else {
		$cdsp->reloadConfig();
	}
}

// AUDIO RENDERERS

// Bluetooth renderer

// Service
if (isset($_POST['update_bt_settings'])) {
	$currentBtName = $_SESSION['btname'];

	if (isset($_POST['btname']) && $_POST['btname'] != $_SESSION['btname']) {
		$title = 'Bluetooth name updated';
		phpSession('write', 'btname', $_POST['btname']);
	}
	if (isset($_POST['btsvc']) && $_POST['btsvc'] != $_SESSION['btsvc']) {
		$title = $_POST['btsvc'] == 1 ? 'Bluetooth controller on' : 'Bluetooth controller off';
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
	submitJob('pairing_agent', $_POST['pairing_agent'], ($_POST['pairing_agent'] == 1 ? 'Pairing agent on' : 'Pairing agent off'), '');
}
// Restart pairing agent
if (isset($_POST['parestart']) && $_POST['parestart'] == 1 && $_SESSION['btsvc'] == '1') {
	submitJob('pairing_agent', '', 'Pairing agent restarted', '');
}
// Speaker sharing
if (isset($_POST['update_bt_multi'])) {
	phpSession('write', 'btmulti', $_POST['btmulti']);
	submitJob('btmulti', '', ($_POST['btmulti'] == 1 ? 'Speaker sharing on' : 'Speaker sharing off'), '');
}
// Resume MPD
if (isset($_POST['update_rsmafterbt'])) {
	phpSession('write', 'rsmafterbt', $_POST['rsmafterbt']);
	$_SESSION['notify']['title'] = 'Setting updated';
}

// Airplay renderer

// Service
if (isset($_POST['update_airplay_settings'])) {
	if (isset($_POST['airplayname']) && $_POST['airplayname'] != $_SESSION['airplayname']) {
		$title = 'Airplay name updated';
		phpSession('write', 'airplayname', $_POST['airplayname']);
	}
	if (isset($_POST['airplaysvc']) && $_POST['airplaysvc'] != $_SESSION['airplaysvc']) {
		$title = $_POST['airplaysvc'] == 1 ? 'Airplay renderer on' : 'Airplay renderer off';
		phpSession('write', 'airplaysvc', $_POST['airplaysvc']);
	}
	if (isset($title)) {
		submitJob('airplaysvc', '', $title, '');
	}
}
// Resume MPD
if (isset($_POST['update_rsmafterapl'])) {
	phpSession('write', 'rsmafterapl', $_POST['rsmafterapl']);
	$_SESSION['notify']['title'] = 'Setting updated';
}
// Restart
if (isset($_POST['airplayrestart']) && $_POST['airplayrestart'] == 1 && $_SESSION['airplaysvc'] == '1') {
	submitJob('airplaysvc', '', 'Airplay renderer restarted', '');
}

// Spotify renderer

// Service
if (isset($_POST['update_spotify_settings'])) {
	if (isset($_POST['spotifyname']) && $_POST['spotifyname'] != $_SESSION['spotifyname']) {
		$title = 'Spotify name updated';
		phpSession('write', 'spotifyname', $_POST['spotifyname']);
	}
	if (isset($_POST['spotifysvc']) && $_POST['spotifysvc'] != $_SESSION['spotifysvc']) {
		$title = $_POST['spotifysvc'] == 1 ? 'Spotify renderer on' : 'Spotify renderer off';
		phpSession('write', 'spotifysvc', $_POST['spotifysvc']);
	}
	if (isset($title)) {
		submitJob('spotifysvc', '', $title, '');
	}
}
// Resume MPD
if (isset($_POST['update_rsmafterspot'])) {
	phpSession('write', 'rsmafterspot', $_POST['rsmafterspot']);
	$_SESSION['notify']['title'] = 'Setting updated';
}
// Restart
if (isset($_POST['spotifyrestart']) && $_POST['spotifyrestart'] == 1 && $_SESSION['spotifysvc'] == '1') {
	submitJob('spotifysvc', '', 'Spotify renderer restarted', '');
}
// Clear credential cache
if (isset($_POST['spotify_clear_credentials']) && $_POST['spotify_clear_credentials'] == 1) {
	submitJob('spotify_clear_credentials', '', 'Credential cache cleared', '');
}

// Squeezelite renderer

// Service
if (isset($_POST['update_sl_settings'])) {
	if (isset($_POST['slsvc']) && $_POST['slsvc'] != $_SESSION['slsvc']) {
		$title = $_POST['slsvc'] == 1 ? 'Squeezelite renderer on' : 'Squeezelite renderer off';
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
	$_SESSION['notify']['title'] = 'Setting updated';
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
		$title = $_POST['rbsvc'] == 1 ? 'RoonBridge renderer on' : 'RoonBridge renderer off';
		phpSession('write', 'rbsvc', $_POST['rbsvc']);
	}
	if (isset($title)) {
		submitJob('rbsvc', '', $title, '');
	}
}
// Resume MPD
if (isset($_POST['update_rsmafterrb'])) {
	phpSession('write', 'rsmafterrb', $_POST['rsmafterrb']);
	$_SESSION['notify']['title'] = 'Setting updated';
}
// Restart
if (isset($_POST['rbrestart']) && $_POST['rbrestart'] == 1) {
	submitJob('rbrestart', '', 'RoonBridge restarted', '');
}

// UPnP/DLNA

// Service
if (isset($_POST['update_upnp_settings'])) {
	$currentUpnpName = $_SESSION['upnpname'];
	if (isset($_POST['upnpname']) && $_POST['upnpname'] != $_SESSION['upnpname']) {
		$title = 'UPnP name updated';
		phpSession('write', 'upnpname', $_POST['upnpname']);
	}
	if (isset($_POST['upnpsvc']) && $_POST['upnpsvc'] != $_SESSION['upnpsvc']) {
		$title = $_POST['upnpsvc'] == 1 ? 'UPnP renderer on' : 'UPnP renderer off';
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
		$title = 'DLNA name updated';
		phpSession('write', 'dlnaname', $_POST['dlnaname']);
	}
	if (isset($_POST['dlnasvc']) && $_POST['dlnasvc'] != $_SESSION['dlnasvc']) {
		$title = $_POST['dlnasvc'] == 1 ? 'DLNA server on' : 'DLNA server off';
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
		submitJob('dlnarebuild', '', 'Database rebuild initiated', '');
	}
	else {
		$_SESSION['notify']['title'] = 'Turn DLNA server on';
		$_SESSION['notify']['msg'] = 'Database rebuild will initiate';
	}
}

phpSession('close');

// LOAD MPD PARAMS
$result = sqlRead('cfg_mpd', $dbh);
$mpdconf = array();
foreach ($result as $row) {
	$mpdconf[$row['param']] = $row['value'];
}

// AUDIO OUTPUT

// Output device
// Pi HDMI 1 & 2, Pi Headphone jack, I2S device, USB device
$dev = $_reboot_required == true ? array('********') : getAlsaDeviceNames();
if ($dev[0] != '') {$_mpd_select['device'] .= "<option value=\"0\" " . (($mpdconf['device'] == '0') ? "selected" : "") . " >$dev[0]</option>\n";}
if ($dev[1] != '') {$_mpd_select['device'] .= "<option value=\"1\" " . (($mpdconf['device'] == '1') ? "selected" : "") . " >$dev[1]</option>\n";}
if ($dev[2] != '') {$_mpd_select['device'] .= "<option value=\"2\" " . (($mpdconf['device'] == '2') ? "selected" : "") . " >$dev[2]</option>\n";}
if ($dev[3] != '') {$_mpd_select['device'] .= "<option value=\"3\" " . (($mpdconf['device'] == '3') ? "selected" : "") . " >$dev[3]</option>\n";}
$cards = getAlsaCards();
$_device_error = ($_SESSION['i2sdevice'] == 'None' && $_SESSION['i2soverlay'] == 'None' && $cards[$mpdconf['device']] == 'empty') ? 'Device turned off or disconnected' : '';
// Volume type
// Hardware, Software, Fixed (0dB), Null (External control)
if ($_SESSION['alsavolume'] != 'none' || $mpdconf['mixer_type'] == 'hardware') {
	$_mpd_select['mixer_type'] .= "<option value=\"hardware\" " . (($mpdconf['mixer_type'] == 'hardware') ? "selected" : "") . ">Hardware</option>\n";
}
$_mpd_select['mixer_type'] .= "<option value=\"software\" " . (($mpdconf['mixer_type'] == 'software') ? "selected" : "") . ">Software</option>\n";
$_mpd_select['mixer_type'] .= "<option value=\"none\" " . (($mpdconf['mixer_type'] == 'none') ? "selected" : "") . ">Fixed (0dB output)</option>\n";
$_mpd_select['mixer_type'] .= "<option value=\"null\" " . (($mpdconf['mixer_type'] == 'null') ? "selected" : "") . ">Null (External control)</option>\n";
// Named I2S devices
$result = sqlQuery("SELECT name FROM cfg_audiodev WHERE iface='I2S' AND list='yes'", $dbh);
$array = array();
$array[0]['name'] = 'None';
$dac_list = array_merge($array, $result);
foreach ($dac_list as $dac) {
	$selected = ($_SESSION['i2sdevice'] == $dac['name']) ? ' selected' : '';
	$_i2s['i2sdevice'] .= sprintf('<option value="%s"%s>%s</option>\n', $dac['name'], $selected, $dac['name']);
}
// DT overlays
$overlay_list = sysCmd('moodeutl -o');
array_unshift($overlay_list, 'None');
foreach ($overlay_list as $overlay) {
	$overlay_name = ($overlay == 'None') ? $overlay : substr($overlay, 0, -5); // Strip .dtbo extension
	// NOTE: This can be used to filter the list
	/*$result = sqlQuery("SELECT name FROM cfg_audiodev WHERE iface='I2S' AND list='yes' AND driver='" . $overlay_name . "'", $dbh);
	if ($result === true || $overlay_name == 'None') { // true = query executed but returnes no results
		$selected = ($_SESSION['i2soverlay'] == $overlay_name) ? ' selected' : '';
		$_i2s['i2soverlay'] .= sprintf('<option value="%s"%s>%s</option>\n', $overlay_name, $selected, $overlay_name);
	}*/
	$selected = ($_SESSION['i2soverlay'] == $overlay_name) ? ' selected' : '';
	$_i2s['i2soverlay'] .= sprintf('<option value="%s"%s>%s</option>\n', $overlay_name, $selected, $overlay_name);
}
// Driver options
$result = sqlQuery("SELECT chipoptions, driver, drvoptions FROM cfg_audiodev WHERE name='" . $_SESSION['i2sdevice'] . "'", $dbh);
if (!empty($result[0]['drvoptions']) && $_SESSION['i2soverlay'] == 'None') {
	$_select['drvoptions'] .= "<option value=\"Enabled\" " . ((strpos($result[0]['driver'], $result[0]['drvoptions']) !== false) ? "selected" : "") . ">" . $result[0]['drvoptions'] . " Enabled</option>\n";
	$_select['drvoptions'] .= "<option value=\"Disabled\" " . ((strpos($result[0]['driver'], $result[0]['drvoptions']) === false) ? "selected" : "") . ">" . $result[0]['drvoptions'] . " Disabled</option>\n";
	$_driveropt_btn_disable = '';
}
else {
	$_select['drvoptions'] .= "<option value=\"none\" selected>None available</option>\n";
	$_driveropt_btn_disable = 'disabled';
}

// Button disables
if ($_SESSION['audioout'] == 'Bluetooth' || $_SESSION['multiroom_tx'] == 'On' || $_SESSION['multiroom_rx'] == 'On') {
	$_output_device_btn_disabled = 'disabled';
	$_volume_type_btn_disabled = 'disabled';
	$_driveropt_btn_disable = 'disabled';
	$_chip_btn_disable = 'disabled';
	$_chip_link_disable = 'onclick="return false;"';
	$_i2sdevice_btn_disable = 'disabled';
	$_i2soverlay_btn_disable = 'disabled';
}
else {
	$_output_device_btn_disabled = ($_SESSION['i2sdevice'] == 'None' && $_SESSION['i2soverlay'] == 'None') ? '' : 'disabled';
	$_volume_type_btn_disabled = '';
	$_i2sdevice_btn_disable = $_SESSION['i2soverlay'] == 'None' ? '' : 'disabled';
	$_i2soverlay_btn_disable = $_SESSION['i2sdevice'] == 'None' ? '' : 'disabled';
	$_chip_btn_disable = (!empty($result[0]['chipoptions']) && $_SESSION['i2soverlay'] == 'None') ? '' : 'disabled';
	$_chip_link_disable = (!empty($result[0]['chipoptions']) && $_SESSION['i2soverlay'] == 'None') ? '' : 'onclick="return false;"';
}

// ALSA OPTIONS

// Max volume
if ($_SESSION['alsavolume'] == 'none') {
	$_alsavolume_max = '';
	$_alsavolume_max_readonly = 'readonly';
	$_alsavolume_max_hide = 'hide'; // Hides the SET button
	$_alsavolume_max_msg = "<em>&nbsp;Hardware volume controller not detected</em>";
}
else {
	$_alsavolume_max = $_SESSION['alsavolume_max'];
	$_alsavolume_max_readonly = '';
	$_alsavolume_max_hide = '';
	$_alsavolume_max_msg = '';
}
// Output mode
$_alsa_output_mode_disable = $_SESSION['alsa_loopback'] == 'Off' ? '' : 'disabled';
$_select['alsa_output_mode'] .= "<option value=\"plughw\" " . (($_SESSION['alsa_output_mode'] == 'plughw') ? "selected" : "") . ">Default (plughw)</option>\n";
$_select['alsa_output_mode'] .= "<option value=\"hw\" " . (($_SESSION['alsa_output_mode'] == 'hw') ? "selected" : "") . ">Direct (hw)</option>\n";
// Loopback
$_alsa_loopback_disable = $_SESSION['alsa_output_mode'] == 'plughw' ? '' : 'disabled';
$_select['alsa_loopback1'] .= "<input type=\"radio\" name=\"alsa_loopback\" id=\"toggle_alsa_loopback1\" value=\"On\" " . (($_SESSION['alsa_loopback'] == 'On') ? "checked=\"checked\"" : "") . ">\n";
$_select['alsa_loopback0'] .= "<input type=\"radio\" name=\"alsa_loopback\" id=\"toggle_alsa_loopback2\" value=\"Off\" " . (($_SESSION['alsa_loopback'] == 'Off') ? "checked=\"checked\"" : "") . ">\n";

// Multiroom configure
$_multiroom_feat_enable = $_SESSION['feat_bitmask'] & FEAT_MULTIROOM ? '' : 'hide';

// MPD OPTIONS

// Autoplay after start
$_select['autoplay1'] .= "<input type=\"radio\" name=\"autoplay\" id=\"toggleautoplay1\" value=\"1\" " . (($_SESSION['autoplay'] == 1) ? "checked=\"checked\"" : "") . ">\n";
$_select['autoplay0'] .= "<input type=\"radio\" name=\"autoplay\" id=\"toggleautoplay2\" value=\"0\" " . (($_SESSION['autoplay'] == 0) ? "checked=\"checked\"" : "") . ">\n";
// Auto-shuffle
$_select['ashufflesvc1'] .= "<input type=\"radio\" name=\"ashufflesvc\" id=\"toggleashufflesvc1\" value=\"1\" " . (($_SESSION['ashufflesvc'] == 1) ? "checked=\"checked\"" : "") . ">\n";
$_select['ashufflesvc0'] .= "<input type=\"radio\" name=\"ashufflesvc\" id=\"toggleashufflesvc2\" value=\"0\" " . (($_SESSION['ashufflesvc'] == 0) ? "checked=\"checked\"" : "") . ">\n";
$_select['ashuffle_mode'] .= "<option value=\"Track\" " . (($_SESSION['ashuffle_mode'] == 'Track') ? "selected" : "") . ">Track</option>\n";
$_select['ashuffle_mode'] .= "<option value=\"Album\" " . (($_SESSION['ashuffle_mode'] == 'Album') ? "selected" : "") . ">Album</option>\n";
$_ashuffle_filter = str_replace('"', '&quot;', $_SESSION['ashuffle_filter']);
// Volume step limit
$_select['volume_step_limit'] .= "<option value=\"2\" " . (($_SESSION['volume_step_limit'] == '2') ? "selected" : "") . ">2</option>\n";
$_select['volume_step_limit'] .= "<option value=\"5\" " . (($_SESSION['volume_step_limit'] == '5') ? "selected" : "") . ">5</option>\n";
$_select['volume_step_limit'] .= "<option value=\"10\" " . (($_SESSION['volume_step_limit'] == '10') ? "selected" : "") . ">10</option>\n";
// Max MPD volume
$_volume_mpd_max = $_SESSION['volume_mpd_max'];
// Display dB volume
$_select['volume_db_display1'] .= "<input type=\"radio\" name=\"volume_db_display\" id=\"toggle_volume_db_display1\" value=\"1\" " . (($_SESSION['volume_db_display'] == 1) ? "checked=\"checked\"" : "") . ">\n";
$_select['volume_db_display0'] .= "<input type=\"radio\" name=\"volume_db_display\" id=\"toggle_volume_db_display2\" value=\"0\" " . (($_SESSION['volume_db_display'] == 0) ? "checked=\"checked\"" : "") . ">\n";
// USB volume knob
$_select['usb_volknob1'] .= "<input type=\"radio\" name=\"usb_volknob\" id=\"toggle_usb_volknob1\" value=\"1\" " . (($_SESSION['usb_volknob'] == 1) ? "checked=\"checked\"" : "") . ">\n";
$_select['usb_volknob0'] .= "<input type=\"radio\" name=\"usb_volknob\" id=\"toggle_usb_volknob2\" value=\"0\" " . (($_SESSION['usb_volknob'] == 0) ? "checked=\"checked\"" : "") . ">\n";
// Rotary encoder
$_select['rotaryenc1'] .= "<input type=\"radio\" name=\"rotaryenc\" id=\"togglerotaryenc1\" value=\"1\" " . (($_SESSION['rotaryenc'] == 1) ? "checked=\"checked\"" : "") . ">\n";
$_select['rotaryenc0'] .= "<input type=\"radio\" name=\"rotaryenc\" id=\"togglerotaryenc2\" value=\"0\" " . (($_SESSION['rotaryenc'] == 0) ? "checked=\"checked\"" : "") . ">\n";
$_select['rotenc_params'] = $_SESSION['rotenc_params'];
// Crossfade
$_mpdcrossfade = $_SESSION['mpdcrossfade'];

// Local out
// NOTE: Only one of the DSP'can be on
if ($_SESSION['audioout'] == 'Local' && $_SESSION['multiroom_tx'] == 'Off') {
	$_invpolarity_set_disabled = ($_SESSION['crossfeed'] != 'Off' || $_SESSION['eqfa12p'] != 'Off' || $_SESSION['alsaequal'] != 'Off' || $_SESSION['camilladsp'] != 'off') ? 'disabled' : '';
	$_crossfeed_set_disabled = ($_SESSION['invert_polarity'] != '0' || $_SESSION['eqfa12p'] != 'Off' || $_SESSION['alsaequal'] != 'Off' || $_SESSION['camilladsp'] != 'off') ? 'disabled' : '';
	$_eqfa12p_set_disabled = ($_SESSION['invert_polarity'] != '0' || $_SESSION['crossfeed'] != 'Off' || $_SESSION['alsaequal'] != 'Off' || $_SESSION['camilladsp'] != 'off') ? 'disabled' : '';
	$_alsaequal_set_disabled = ($_SESSION['invert_polarity'] != '0' || $_SESSION['crossfeed'] != 'Off' || $_SESSION['eqfa12p'] != 'Off' || $_SESSION['camilladsp'] != 'off') ? 'disabled' : '';
	$model = substr($_SESSION['hdwrrev'], 3, 1);
	$cmmodel = substr($_SESSION['hdwrrev'], 3, 3); // Generic Pi-CM3+, Pi-CM4 for future use
	$name = $_SESSION['hdwrrev'];
	// Pi-Zero 2 W, Pi-2B rev 1.2, Allo USBridge SIG, Pi-3B/B+/A+, Pi-4B
	if ((strpos($name, 'Pi-Zero 2') !== false) || $name == 'Pi-2B 1.2 1GB' || $model == '3' || $model == '4' || $name == 'Allo USBridge SIG [CM3+ Lite 1GB v1.0]') {
		$_camilladsp_set_disabled = ($_SESSION['invert_polarity'] != '0' || $_SESSION['crossfeed'] != 'Off' || $_SESSION['eqfa12p'] != 'Off' || $_SESSION['alsaequal'] != 'Off') ? 'disabled' : '';
	}
	else {
		$_camilladsp_set_disabled = 'disabled';
	}
}
// Bluetooth out or Multiroom Sender On
// NOTE: Don't allow any DSP to be set
else {
	$_invpolarity_set_disabled = 'disabled';
	$_crossfeed_set_disabled = 'disabled';
	$_eqfa12p_set_disabled = 'disabled';
	$_alsaequal_set_disabled = 'disabled';
	$_camilladsp_set_disabled = 'disabled';
}

// Polarity inversion
$_select['invert_polarity1'] .= "<input type=\"radio\" name=\"invert_polarity\" id=\"toggle_invert_polarity1\" value=\"1\" " . (($_SESSION['invert_polarity'] == 1) ? "checked=\"checked\"" : "") . ">\n";
$_select['invert_polarity0'] .= "<input type=\"radio\" name=\"invert_polarity\" id=\"toggle_invert_polarity2\" value=\"0\" " . (($_SESSION['invert_polarity'] == 0) ? "checked=\"checked\"" : "") . ">\n";
// Crossfeed
$_select['crossfeed'] .= "<option value=\"Off\" " . (($_SESSION['crossfeed'] == 'Off' OR $_SESSION['crossfeed'] == '') ? "selected" : "") . ">Off</option>\n";
if ($_crossfeed_set_disabled == '') {
	$_select['crossfeed'] .= "<option value=\"700 3.0\" " . (($_SESSION['crossfeed'] == '700 3.0') ? "selected" : "") . ">700 Hz 3.0 dB</option>\n";
	$_select['crossfeed'] .= "<option value=\"700 4.5\" " . (($_SESSION['crossfeed'] == '700 4.5') ? "selected" : "") . ">700 Hz 4.5 dB</option>\n";
	$_select['crossfeed'] .= "<option value=\"800 6.0\" " . (($_SESSION['crossfeed'] == '800 6.0') ? "selected" : "") . ">800 Hz 6.0 dB</option>\n";
	$_select['crossfeed'] .= "<option value=\"650 10.0\" " . (($_SESSION['crossfeed'] == '650 10.0') ? "selected" : "") . ">650 Hz 10.0 dB</option>\n";
}
// HTTP streaming server
$_select['mpd_httpd1'] .= "<input type=\"radio\" name=\"mpd_httpd\" id=\"toggle-mpd-httpd1\" value=\"1\" " . (($_SESSION['mpd_httpd'] == 1) ? "checked=\"checked\"" : "") . ">\n";
$_select['mpd_httpd0'] .= "<input type=\"radio\" name=\"mpd_httpd\" id=\"toggle-mpd-httpd2\" value=\"0\" " . (($_SESSION['mpd_httpd'] == 0) ? "checked=\"checked\"" : "") . ">\n";
// Port
$_mpd_httpd_port = $_SESSION['mpd_httpd_port'];
// Encoder
$_select['mpd_httpd_encoder'] .= "<option value=\"flac\" " . (($_SESSION['mpd_httpd_encoder'] == 'flac') ? "selected" : "") . ">FLAC</option>\n";
$_select['mpd_httpd_encoder'] .= "<option value=\"lame\" " . (($_SESSION['mpd_httpd_encoder'] == 'lame') ? "selected" : "") . ">LAME (MP3)</option>\n";

// EQUALIZERS

// Parametric equalizer
$eqfa12p = Eqp12($dbh);
$presets = $eqfa12p->getPresets();
$array = array();
$array[0] = 'Off';
$curveList = $_eqfa12p_set_disabled == '' ? array_replace($array, $presets) : $array;
$curve_selected_id = $eqfa12p->getActivePresetIndex();
foreach ($curveList as $key=>$curveName) {
	$selected = ($key == $curve_selected_id) ? 'selected' : '';
	$_select['eqfa12p'] .= sprintf('<option value="%s" %s>%s</option>\n', $key, $selected, $curveName);
}
unset($eqfa12p);

// Graphic equalizer
$result = sqlQuery('SELECT curve_name FROM cfg_eqalsa', $dbh);
$array = array();
$array[0]['curve_name'] = 'Off';
$curveList = $_alsaequal_set_disabled == '' ? array_merge($array, $result) : $array;
foreach ($curveList as $curve) {
	$curveName = $curve['curve_name'];
	$selected = ($_SESSION['alsaequal'] == $curve['curve_name']) ? 'selected' : '';
	$_select['alsaequal'] .= sprintf('<option value="%s" %s>%s</option>\n', $curve['curve_name'], $selected, $curveName);
}

// CamillaDSP
$configs = $cdsp->getAvailableConfigs();
foreach ($configs as $config_file=>$config_name) {
	$selected = ($_SESSION['camilladsp'] == $config_file) ? 'selected' : '';
	$_select['camilladsp'] .= sprintf("<option value='%s' %s>%s</option>\n", $config_file, $selected, $config_name);
}

//Check, if the config file is valid
if( $_SESSION['camilladsp'] != 'off' && $_SESSION['camilladsp'] != 'custom') {
	$camilladsp_config_check_result = $cdsp->checkConfigFile($_SESSION['camilladsp']);
	$camilladsp_config_check_output = implode('<br>', $camilladsp_config_check_result['msg']);
	if( $camilladsp_config_check_result['valid'] == CDSP_CHECK_NOTFOUND) {
		$camilladsp_config_check = "<span style='color: red'>&#10007;</span> ".$camilladsp_config_check_output;
	} elseif( $camilladsp_config_check_result['valid'] == CDSP_CHECK_VALID) {
		$camilladsp_config_check = "<span style='color: green'>&check;</span> " . $camilladsp_config_check_output;
	} else {
		$camilladsp_config_check = "<span style='color: red'>&#10007;</span> " . $camilladsp_config_check_output;
	}
}

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
// Airplay renderer
$_feat_airplay = $_SESSION['feat_bitmask'] & FEAT_AIRPLAY ? '' : 'hide';
$_SESSION['airplaysvc'] == '1' ? $_airplay_btn_disable = '' : $_airplay_btn_disable = 'disabled';
$_SESSION['airplaysvc'] == '1' ? $_airplay_link_disable = '' : $_airplay_link_disable = 'onclick="return false;"';
$_select['airplaysvc1'] .= "<input type=\"radio\" name=\"airplaysvc\" id=\"toggleairplaysvc1\" value=\"1\" " . (($_SESSION['airplaysvc'] == 1) ? "checked=\"checked\"" : "") . ">\n";
$_select['airplaysvc0'] .= "<input type=\"radio\" name=\"airplaysvc\" id=\"toggleairplaysvc2\" value=\"0\" " . (($_SESSION['airplaysvc'] == 0) ? "checked=\"checked\"" : "") . ">\n";
$_select['airplayname'] = $_SESSION['airplayname'];
$_select['rsmafterapl'] .= "<option value=\"Yes\" " . (($_SESSION['rsmafterapl'] == 'Yes') ? "selected" : "") . ">Yes</option>\n";
$_select['rsmafterapl'] .= "<option value=\"No\" " . (($_SESSION['rsmafterapl'] == 'No') ? "selected" : "") . ">No</option>\n";
// Spotify renderer
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
	$_feat_roonbridge = 'hide';
}

// UPnP/DLNA

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

waitWorker(1, 'snd-config');

$tpl = "snd-config.html";
$section = basename(__FILE__, '.php');
storeBackLink($section, $tpl);

include('header.php');
eval("echoTemplate(\"" . getTemplate("templates/$tpl") . "\");");
include('footer.php');
