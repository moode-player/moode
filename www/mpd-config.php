<?php 
/**
 * moOde audio player (C) 2014 Tim Curtis
 * http://moodeaudio.org
 *
 * tsunamp player ui (C) 2013 Andrea Coiutti & Simone De Gregori
 * http://www.tsunamp.com
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
 * 2019-05-07 TC moOde 5.2
 *
 */
 
require_once dirname(__FILE__) . '/inc/playerlib.php';

playerSession('open', '' ,'');
session_write_close();
$dbh = cfgdb_connect();

// save changes to /etc/mpd.conf
if (isset($_POST['save']) && $_POST['save'] == '1') {
	// restart shairport-sync if device num has changed
	$queueargs = $_POST['conf']['device'] == $_SESSION['cardnum'] ? '' : 'devicechg';
	
	// update sql table
	foreach ($_POST['conf'] as $key => $value) {
		cfgdb_update('cfg_mpd', $dbh, $key, $value);
	}
	
	// set 0 volume for mixer type disabled
	if ($_POST['conf']['mixer_type'] == 'disabled') {
		sendMpdCmd($sock, 'setvol 0');
		$resp = readMpdResp($sock);
		closeMpdSock($sock);
	}

	// update the mixertype for audioout -> local
	playerSession('write', 'mpdmixer_local', $_POST['conf']['mixer_type']);
	
	// update /etc/mpd.conf
	submitJob('mpdcfg', $queueargs, 'Changes saved', 'MPD restarted');
}
	
// load settings
$result = cfgdb_read('cfg_mpd', $dbh);
$mpdconf = array();

foreach ($result as $row) {
	$mpdconf[$row['param']] = $row['value']; // r45b
}

if ($_SESSION['audioout'] == 'Bluetooth') {
	$_save_disabled = 'disabled';
	$_hide_msg = '';
}
else {
	$_save_disabled = '';
	$_hide_msg = 'hide';
}

// mpd version specific params
$_hide_020_params = substr($_SESSION['mpdver'], 0, 4) == '0.21' ? 'hide' : ''; 
$_hide_021_params = substr($_SESSION['mpdver'], 0, 4) == '0.20' ? 'hide' : ''; 

// get device names
$dev = getDeviceNames();

// zeroconf, deprecate but leave in conf file
/*$_mpd_select['zeroconf_enabled'] .= "<option value=\"yes\" " . (($mpdconf['zeroconf_enabled'] == 'yes') ? "selected" : "") . ">Yes</option>\n";
$_mpd_select['zeroconf_enabled'] .= "<option value=\"no\" " . (($mpdconf['zeroconf_enabled'] == 'no') ? "selected" : "") . ">No</option>\n";
$_mpd_select['zeroconf_name'] = $mpdconf['zeroconf_name'];*/

// dsd over pcm (DoP)
$_mpd_select['dop'] .= "<option value=\"yes\" " . (($mpdconf['dop'] == 'yes') ? "selected" : "") . " >Yes</option>\n";	
$_mpd_select['dop'] .= "<option value=\"no\" " . (($mpdconf['dop'] == 'no') ? "selected" : "") . " >No</option>\n";	

// audio output device
if ($dev[0] != '') {$_mpd_select['device'] .= "<option value=\"0\" " . (($mpdconf['device'] == '0') ? "selected" : "") . " >$dev[0]</option>\n";}
if ($dev[1] != '') {$_mpd_select['device'] .= "<option value=\"1\" " . (($mpdconf['device'] == '1') ? "selected" : "") . " >$dev[1]</option>\n";}

// volume control
$_mpd_select['mixer_type'] .= "<option value=\"disabled\" " . (($mpdconf['mixer_type'] == 'disabled') ? "selected" : "") . ">Disabled (0dB output)</option>\n";
if ($_SESSION['alsavolume'] != 'none') {$_mpd_select['mixer_type'] .= "<option value=\"hardware\" " . (($mpdconf['mixer_type'] == 'hardware') ? "selected" : "") . ">Hardware</option>\n";}
$_mpd_select['mixer_type'] .= "<option value=\"software\" " . (($mpdconf['mixer_type'] == 'software') ? "selected" : "") . ">Software</option>\n";

// resampling rate
$_mpd_select['audio_output_format'] .= "<option value=\"disabled\" " . (($mpdconf['audio_output_format'] == 'disabled' OR $mpdconf['audio_output_format'] == '') ? "selected" : "") . ">Disabled</option>\n";
$_mpd_select['audio_output_format'] .= "<option value=\"*:*:1\" " . (($mpdconf['audio_output_format'] == '*:*:1') ? "selected" : "") . ">Mono output</option>\n";
$_mpd_select['audio_output_format'] .= "<option value=\"*:16:*\" " . (($mpdconf['audio_output_format'] == '*:16:*') ? "selected" : "") . ">16 bit / * kHz</option>\n";
$_mpd_select['audio_output_format'] .= "<option value=\"44100:16:2\" " . (($mpdconf['audio_output_format'] == '44100:16:2') ? "selected" : "") . ">16 bit / 44.1 kHz</option>\n";
$_mpd_select['audio_output_format'] .= "<option value=\"48000:16:2\" " . (($mpdconf['audio_output_format'] == '48000:16:2') ? "selected" : "") . ">16 bit / 48 kHz</option>\n";
$_mpd_select['audio_output_format'] .= "<option value=\"88200:16:2\" " . (($mpdconf['audio_output_format'] == '88200:16:2') ? "selected" : "") . ">16 bit / 88.2 kHz</option>\n";
$_mpd_select['audio_output_format'] .= "<option value=\"96000:16:2\" " . (($mpdconf['audio_output_format'] == '96000:16:2') ? "selected" : "") . ">16 bit / 96 kHz</option>\n";
$_mpd_select['audio_output_format'] .= "<option value=\"176400:16:2\" " . (($mpdconf['audio_output_format'] == '176400:16:2') ? "selected" : "") . ">16 bit / 176.4 kHz</option>\n";
$_mpd_select['audio_output_format'] .= "<option value=\"192000:16:2\" " . (($mpdconf['audio_output_format'] == '192000:16:2') ? "selected" : "") . ">16 bit / 192 kHz</option>\n";
$_mpd_select['audio_output_format'] .= "<option value=\"352800:16:2\" " . (($mpdconf['audio_output_format'] == '352800:16:2') ? "selected" : "") . ">16 bit / 352.8 kHz</option>\n";
$_mpd_select['audio_output_format'] .= "<option value=\"384000:16:2\" " . (($mpdconf['audio_output_format'] == '384000:16:2') ? "selected" : "") . ">16 bit / 384 kHz</option>\n";

$_mpd_select['audio_output_format'] .= "<option value=\"*:24:*\" " . (($mpdconf['audio_output_format'] == '*:24:*') ? "selected" : "") . ">24 bit / * kHz</option>\n";
$_mpd_select['audio_output_format'] .= "<option value=\"44100:24:2\" " . (($mpdconf['audio_output_format'] == '44100:24:2') ? "selected" : "") . ">24 bit / 44.1 kHz</option>\n";
$_mpd_select['audio_output_format'] .= "<option value=\"48000:24:2\" " . (($mpdconf['audio_output_format'] == '48000:24:2') ? "selected" : "") . ">24 bit / 48 kHz</option>\n";
$_mpd_select['audio_output_format'] .= "<option value=\"88200:24:2\" " . (($mpdconf['audio_output_format'] == '88200:24:2') ? "selected" : "") . ">24 bit / 88.2 kHz</option>\n";
$_mpd_select['audio_output_format'] .= "<option value=\"96000:24:2\" " . (($mpdconf['audio_output_format'] == '96000:24:2') ? "selected" : "") . ">24 bit / 96 kHz</option>\n";
$_mpd_select['audio_output_format'] .= "<option value=\"176400:24:2\" " . (($mpdconf['audio_output_format'] == '176400:24:2') ? "selected" : "") . ">24 bit / 176.4 kHz</option>\n";
$_mpd_select['audio_output_format'] .= "<option value=\"192000:24:2\" " . (($mpdconf['audio_output_format'] == '192000:24:2') ? "selected" : "") . ">24 bit / 192 kHz</option>\n";
$_mpd_select['audio_output_format'] .= "<option value=\"352800:24:2\" " . (($mpdconf['audio_output_format'] == '352800:24:2') ? "selected" : "") . ">24 bit / 352.8 kHz</option>\n";
$_mpd_select['audio_output_format'] .= "<option value=\"384000:24:2\" " . (($mpdconf['audio_output_format'] == '384000:24:2') ? "selected" : "") . ">24 bit / 384 kHz</option>\n";

$_mpd_select['audio_output_format'] .= "<option value=\"*:32:*\" " . (($mpdconf['audio_output_format'] == '*:32:*') ? "selected" : "") . ">32 bit / * kHz</option>\n";
$_mpd_select['audio_output_format'] .= "<option value=\"44100:32:2\" " . (($mpdconf['audio_output_format'] == '44100:32:2') ? "selected" : "") . ">32 bit / 44.1 kHz</option>\n";
$_mpd_select['audio_output_format'] .= "<option value=\"48000:32:2\" " . (($mpdconf['audio_output_format'] == '48000:32:2') ? "selected" : "") . ">32 bit / 48 kHz</option>\n";
$_mpd_select['audio_output_format'] .= "<option value=\"88200:32:2\" " . (($mpdconf['audio_output_format'] == '88200:32:2') ? "selected" : "") . ">32 bit / 88.2 kHz</option>\n";
$_mpd_select['audio_output_format'] .= "<option value=\"96000:32:2\" " . (($mpdconf['audio_output_format'] == '96000:32:2') ? "selected" : "") . ">32 bit / 96 kHz</option>\n";
$_mpd_select['audio_output_format'] .= "<option value=\"176400:32:2\" " . (($mpdconf['audio_output_format'] == '176400:32:2') ? "selected" : "") . ">32 bit / 176.4 kHz</option>\n";
$_mpd_select['audio_output_format'] .= "<option value=\"192000:32:2\" " . (($mpdconf['audio_output_format'] == '192000:32:2') ? "selected" : "") . ">32 bit / 192 kHz</option>\n";
$_mpd_select['audio_output_format'] .= "<option value=\"352800:32:2\" " . (($mpdconf['audio_output_format'] == '352800:32:2') ? "selected" : "") . ">32 bit / 352.8 kHz</option>\n";
$_mpd_select['audio_output_format'] .= "<option value=\"384000:32:2\" " . (($mpdconf['audio_output_format'] == '384000:32:2') ? "selected" : "") . ">32 bit / 384 kHz</option>\n";

// resampling quality
$_mpd_select['samplerate_converter'] .= "<option value=\"very high\" " . (($mpdconf['samplerate_converter'] == 'very high') ? "selected" : "") . " >Very high quality</option>\n";	
$_mpd_select['samplerate_converter'] .= "<option value=\"high\" " . (($mpdconf['samplerate_converter'] == 'high') ? "selected" : "") . " >High quality</option>\n";	
$_mpd_select['samplerate_converter'] .= "<option value=\"medium\" " . (($mpdconf['samplerate_converter'] == 'medium') ? "selected" : "") . " >Medium quality</option>\n";	

// sox multithreading
$_mpd_select['sox_multithreading'] .= "<option value=\"0\" " . (($mpdconf['sox_multithreading'] == '0') ? "selected" : "") . " >Yes</option>\n";	
$_mpd_select['sox_multithreading'] .= "<option value=\"1\" " . (($mpdconf['sox_multithreading'] == '1') ? "selected" : "") . " >No</option>\n";

// gapless mp3 playback, r45b deprecate from form and conf file
/*$_mpd_select['gapless_mp3_playback'] .= "<option value=\"yes\" " . (($mpdconf['gapless_mp3_playback'] == 'yes') ? "selected" : "") . " >Yes</option>\n";	
$_mpd_select['gapless_mp3_playback'] .= "<option value=\"no\" " . (($mpdconf['gapless_mp3_playback'] == 'no') ? "selected" : "") . " >No</option>\n";*/

// replaygain
$_mpd_select['replaygain'] .= "<option value=\"off\" " . (($mpdconf['replaygain'] == 'off') ? "selected" : "") . " >Off</option>\n";	
$_mpd_select['replaygain'] .= "<option value=\"auto\" " . (($mpdconf['replaygain'] == 'auto') ? "selected" : "") . " >Auto</option>\n";	
$_mpd_select['replaygain'] .= "<option value=\"album\" " . (($mpdconf['replaygain'] == 'album') ? "selected" : "") . " >Album</option>\n";	
$_mpd_select['replaygain'] .= "<option value=\"track\" " . (($mpdconf['replaygain'] == 'track') ? "selected" : "") . " >Track</option>\n";

// replaygain preamp, r45b
$_mpd_select['replaygain_preamp'] = $mpdconf['replaygain_preamp'];

/* DEPRECATE
// replaygain handler
$_mpd_select['replay_gain_handler'] .= "<option value=\"software\" " . (($mpdconf['replay_gain_handler'] == 'software') ? "selected" : "") . " >Software</option>\n";	
$_mpd_select['replay_gain_handler'] .= "<option value=\"mixer\" " . (($mpdconf['replay_gain_handler'] == 'mixer') ? "selected" : "") . " >Mixer</option>\n";	
*/

// volume normalization
$_mpd_select['volume_normalization'] .= "<option value=\"yes\" " . (($mpdconf['volume_normalization'] == 'yes') ? "selected" : "") . " >Yes</option>\n";	
$_mpd_select['volume_normalization'] .= "<option value=\"no\" " . (($mpdconf['volume_normalization'] == 'no') ? "selected" : "") . " >No</option>\n";	

// audio buffer size
$_mpd_select['audio_buffer_size'] = $mpdconf['audio_buffer_size'];

// buffer fill % before play, r45b for 0.20 only
if ($_hide_020_params == '') {
	$_mpd_select['buffer_before_play'] .= "<option value=\"0%\" " . (($mpdconf['buffer_before_play'] == '0%') ? "selected" : "") . " >Disabled</option>\n";	
	$_mpd_select['buffer_before_play'] .= "<option value=\"10%\" " . (($mpdconf['buffer_before_play'] == '10%') ? "selected" : "") . " >10%</option>\n";	
	$_mpd_select['buffer_before_play'] .= "<option value=\"20%\" " . (($mpdconf['buffer_before_play'] == '20%') ? "selected" : "") . " >20%</option>\n";	
	$_mpd_select['buffer_before_play'] .= "<option value=\"30%\" " . (($mpdconf['buffer_before_play'] == '30%') ? "selected" : "") . " >30%</option>\n";	
}

/* DEPRECATE
// hardware buffer time, r45b
$_mpd_select['buffer_time'] .= "<option value=\"500000\" " . (($mpdconf['buffer_time'] == '500000') ? "selected" : "") . " >0.5 secs (Default)</option>\n";	
$_mpd_select['buffer_time'] .= "<option value=\"750000\" " . (($mpdconf['buffer_time'] == '750000') ? "selected" : "") . " >0.75 secs</option>\n";	
$_mpd_select['buffer_time'] .= "<option value=\"1000000\" " . (($mpdconf['buffer_time'] == '1000000') ? "selected" : "") . " >1.0 secs</option>\n";	
$_mpd_select['buffer_time'] .= "<option value=\"1250000\" " . (($mpdconf['buffer_time'] == '1250000') ? "selected" : "") . " >1.25 secs</option>\n";	
// hardware period time, r45b
$_mpd_select['period_time'] .= "<option value=\"256000000\" " . (($mpdconf['period_time'] == '256000000') ? "selected" : "") . " >Default</option>\n";	
$_mpd_select['period_time'] .= "<option value=\"1024000000\" " . (($mpdconf['period_time'] == '1024000000') ? "selected" : "") . " >4X</option>\n";	
$_mpd_select['period_time'] .= "<option value=\"512000000\" " . (($mpdconf['period_time'] == '512000000') ? "selected" : "") . " >2X</option>\n";	
$_mpd_select['period_time'] .= "<option value=\"64000000\" " . (($mpdconf['period_time'] == '64000000') ? "selected" : "") . " >0.25X</option>\n";	
$_mpd_select['period_time'] .= "<option value=\"640000\" " . (($mpdconf['period_time'] == '640000') ? "selected" : "") . " >0.0025X</option>\n";	
$_mpd_select['period_time'] .= "<option value=\"64000\" " . (($mpdconf['period_time'] == '64000') ? "selected" : "") . " >0.00025X</option>\n";	

// ALSA auto-resample, r45b
$_mpd_select['auto_resample'] .= "<option value=\"yes\" " . (($mpdconf['auto_resample'] == 'yes') ? "selected" : "") . " >Yes</option>\n";	
$_mpd_select['auto_resample'] .= "<option value=\"no\" " . (($mpdconf['auto_resample'] == 'no') ? "selected" : "") . " >No</option>\n";	
// ALSA auto-channels, r45b
$_mpd_select['auto_channels'] .= "<option value=\"yes\" " . (($mpdconf['auto_channels'] == 'yes') ? "selected" : "") . " >Yes</option>\n";	
$_mpd_select['auto_channels'] .= "<option value=\"no\" " . (($mpdconf['auto_channels'] == 'no') ? "selected" : "") . " >No</option>\n";	
/// ALSA auto-format, r45b
$_mpd_select['auto_format'] .= "<option value=\"yes\" " . (($mpdconf['auto_format'] == 'yes') ? "selected" : "") . " >Yes</option>\n";	
$_mpd_select['auto_format'] .= "<option value=\"no\" " . (($mpdconf['auto_format'] == 'no') ? "selected" : "") . " >No</option>\n";	
*/

waitWorker(1, 'mpd-config');

$tpl = "mpd-config.html";
$section = basename(__FILE__, '.php');
storeBackLink($section, $tpl);

include('/var/local/www/header.php'); 
eval("echoTemplate(\"" . getTemplate("templates/$tpl") . "\");");
include('footer.php');
