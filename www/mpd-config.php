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
 * 2020-MM-DD TC moOde 7.0.0
 *
 */

require_once dirname(__FILE__) . '/inc/playerlib.php';

playerSession('open', '' ,'');
session_write_close();
$dbh = cfgdb_connect();

// Save changes to /etc/mpd.conf
if (isset($_POST['save']) && $_POST['save'] == '1') {
	// Restart shairport-sync if device num has changed
	$queueargs = $_POST['conf']['device'] == $_SESSION['cardnum'] ? '' : 'devicechg';

	// Add audio_output_format
	$_POST['conf']['audio_output_format'] = $_POST['sox_enabled'] == 'No' ? 'disabled' : $_POST['sox_sample_rate'] . ':' . $_POST['sox_bit_depth'] . ':' . $_POST['sox_channels'];

	// Update sql table
	foreach ($_POST['conf'] as $key => $value) {
		if ($key == 'audio_buffer_size' || $key == 'max_output_buffer_size') {
			$value = $value * 1024; // Convert from MB to KB
		}

		cfgdb_update('cfg_mpd', $dbh, $key, $value);
	}

	// Set 0 volume for mixer type disabled
	if ($_POST['conf']['mixer_type'] == 'disabled') {
		sysCmd('/var/www/vol.sh 0');
	}

	// Update the mixertype for audioout -> local
	playerSession('write', 'mpdmixer_local', $_POST['conf']['mixer_type']);

	// Update /etc/mpd.conf
	if ($queueargs == 'devicechg') {
		$title = 'Audio output has changed';
		$message = 'Restart required';
		$duration = 10;
	}
	else {
		$title = 'Changes saved';
		$message = 'MPD restarted';
		$duration = 3;
	}
	submitJob('mpdcfg', $queueargs, $title, $message, $duration);
}

// Load settings
$result = cfgdb_read('cfg_mpd', $dbh);
$mpdconf = array();

foreach ($result as $row) {
	$mpdconf[$row['param']] = $row['value'];
}

if ($_SESSION['audioout'] == 'Bluetooth') {
	$_save_disabled = 'disabled';
	$_hide_msg = '';
}
else {
	$_save_disabled = '';
	$_hide_msg = 'hide';
}

// Audio output
$dev = getDeviceNames();
if ($dev[0] != '') {$_mpd_select['device'] .= "<option value=\"0\" " . (($mpdconf['device'] == '0') ? "selected" : "") . " >$dev[0]</option>\n";}
if ($dev[1] != '') {$_mpd_select['device'] .= "<option value=\"1\" " . (($mpdconf['device'] == '1') ? "selected" : "") . " >$dev[1]</option>\n";}
if ($dev[2] != '') {$_mpd_select['device'] .= "<option value=\"2\" " . (($mpdconf['device'] == '2') ? "selected" : "") . " >$dev[2]</option>\n";}
if ($dev[3] != '') {$_mpd_select['device'] .= "<option value=\"3\" " . (($mpdconf['device'] == '3') ? "selected" : "") . " >$dev[3]</option>\n";}

// Volume control
if ($_SESSION['alsavolume'] != 'none') {
	$_mpd_select['mixer_type'] .= "<option value=\"hardware\" " . (($mpdconf['mixer_type'] == 'hardware') ? "selected" : "") . ">Hardware</option>\n";
}
$_mpd_select['mixer_type'] .= "<option value=\"software\" " . (($mpdconf['mixer_type'] == 'software') ? "selected" : "") . ">Software</option>\n";
$_mpd_select['mixer_type'] .= "<option value=\"disabled\" " . (($mpdconf['mixer_type'] == 'disabled') ? "selected" : "") . ">Disabled (0dB output)</option>\n";

// DSD support
$_mpd_select['dop'] .= "<option value=\"no\" " . (($mpdconf['dop'] == 'no') ? "selected" : "") . " >Native DSD (Default)</option>\n";
$_mpd_select['dop'] .= "<option value=\"yes\" " . (($mpdconf['dop'] == 'yes') ? "selected" : "") . " >DSD over PCM (DoP)</option>\n";

// SoX resampling
$format = array('','','');
$format = explode(':', $mpdconf['audio_output_format']);
// Enabled
$_mpd_select['sox_enabled'] .= "<option value=\"Yes\" " . (($mpdconf['audio_output_format'] != 'disabled') ? "selected" : "") . " >Yes</option>\n";
$_mpd_select['sox_enabled'] .= "<option value=\"No\" " . (($mpdconf['audio_output_format'] == 'disabled') ? "selected" : "") . " >No</option>\n";
// Bit depth
$_mpd_select['sox_bit_depth'] .= "<option value=\"*\" " . (($mpdconf['audio_output_format'] != 'disabled' && $format[1] == '*') ? "selected" : "") . " >Any</option>\n";
$_mpd_select['sox_bit_depth'] .= "<option value=\"16\" " . (($mpdconf['audio_output_format'] != 'disabled' && $format[1] == '16') ? "selected" : "") . " >16</option>\n";
$_mpd_select['sox_bit_depth'] .= "<option value=\"24\" " . (($mpdconf['audio_output_format'] != 'disabled' && $format[1] == '24') ? "selected" : "") . " >24</option>\n";
$_mpd_select['sox_bit_depth'] .= "<option value=\"32\" " . (($mpdconf['audio_output_format'] != 'disabled' && $format[1] == '32') ? "selected" : "") . " >32</option>\n";
// Sample rate
$_mpd_select['sox_sample_rate'] .= "<option value=\"*\" " . (($mpdconf['audio_output_format'] != 'disabled' && $format[0] == '*') ? "selected" : "") . " >Any</option>\n";
$_mpd_select['sox_sample_rate'] .= "<option value=\"44100\" " . (($mpdconf['audio_output_format'] != 'disabled' && $format[0] == '44100') ? "selected" : "") . " >44.1</option>\n";
$_mpd_select['sox_sample_rate'] .= "<option value=\"48000\" " . (($mpdconf['audio_output_format'] != 'disabled' && $format[0] == '48000') ? "selected" : "") . " >48</option>\n";
$_mpd_select['sox_sample_rate'] .= "<option value=\"88200\" " . (($mpdconf['audio_output_format'] != 'disabled' && $format[0] == '88200') ? "selected" : "") . " >88.2</option>\n";
$_mpd_select['sox_sample_rate'] .= "<option value=\"96000\" " . (($mpdconf['audio_output_format'] != 'disabled' && $format[0] == '96000') ? "selected" : "") . " >96</option>\n";
$_mpd_select['sox_sample_rate'] .= "<option value=\"176400\" " . (($mpdconf['audio_output_format'] != 'disabled' && $format[0] == '176400') ? "selected" : "") . " >176.41</option>\n";
$_mpd_select['sox_sample_rate'] .= "<option value=\"192000\" " . (($mpdconf['audio_output_format'] != 'disabled' && $format[0] == '192000') ? "selected" : "") . " >192</option>\n";
$_mpd_select['sox_sample_rate'] .= "<option value=\"352800\" " . (($mpdconf['audio_output_format'] != 'disabled' && $format[0] == '352800') ? "selected" : "") . " >352.8</option>\n";
$_mpd_select['sox_sample_rate'] .= "<option value=\"384000\" " . (($mpdconf['audio_output_format'] != 'disabled' && $format[0] == '384000') ? "selected" : "") . " >384</option>\n";
$_mpd_select['sox_sample_rate'] .= "<option value=\"705600\" " . (($mpdconf['audio_output_format'] != 'disabled' && $format[0] == '705600') ? "selected" : "") . " >705.6</option>\n";
$_mpd_select['sox_sample_rate'] .= "<option value=\"768000\" " . (($mpdconf['audio_output_format'] != 'disabled' && $format[0] == '768000') ? "selected" : "") . " >768</option>\n";
// Channels
$_mpd_select['sox_channels'] .= "<option value=\"2\" " . (($mpdconf['audio_output_format'] != 'disabled' && $format[2] == '2') ? "selected" : "") . " >Stereo</option>\n";
$_mpd_select['sox_channels'] .= "<option value=\"1\" " . (($mpdconf['audio_output_format'] != 'disabled' && $format[2] == '1') ? "selected" : "") . " >Mono</option>\n";

// Selective resample mode
$patch_id = explode('_p0x', $_SESSION['mpdver'])[1];
if ($patch_id & PATCH_SELECTIVE_RESAMPLING) {
	$_selective_resampling_hide = '';
	$_mpd_select['selective_resample_mode'] .= "<option value=\"0\" " . (($mpdconf['selective_resample_mode'] == '0') ? "selected" : "") . " >Disabled</option>\n";
	$_mpd_select['selective_resample_mode'] .= "<option value=\"" . SOX_UPSAMPLE_ALL . "\" " . (($mpdconf['selective_resample_mode'] == SOX_UPSAMPLE_ALL) ? "selected" : "") . " >Upsample if source &lt; target rate</option>\n";
	$_mpd_select['selective_resample_mode'] .= "<option value=\"" . SOX_UPSAMPLE_ONLY_41K . "\" " . (($mpdconf['selective_resample_mode'] == SOX_UPSAMPLE_ONLY_41K) ? "selected" : "") . " >Upsample only 44.1K source rate</option>\n";
	$_mpd_select['selective_resample_mode'] .= "<option value=\"" . SOX_UPSAMPLE_ONLY_4148K . "\" " . (($mpdconf['selective_resample_mode'] == SOX_UPSAMPLE_ONLY_4148K) ? "selected" : "") . " >Upsample only 44.1K and 48K source rates</option>\n";
	$_mpd_select['selective_resample_mode'] .= "<option value=\"" . SOX_ADHERE_BASE_FREQ . "\" " . (($mpdconf['selective_resample_mode'] == SOX_ADHERE_BASE_FREQ) ? "selected" : "") . " >Resample (adhere to base freq)</option>\n";
	$_mpd_select['selective_resample_mode'] .= "<option value=\"" . (SOX_UPSAMPLE_ALL + SOX_ADHERE_BASE_FREQ) . "\" " . (($mpdconf['selective_resample_mode'] == (SOX_UPSAMPLE_ALL + SOX_ADHERE_BASE_FREQ)) ? "selected" : "") . " >Upsample if source &lt; target rate (adhere to base freq)</option>\n";
}
else {
	$_selective_resampling_hide = 'hide';
}
// Resampling quality
$_mpd_select['sox_quality'] .= "<option value=\"very high\" " . (($mpdconf['sox_quality'] == 'very high') ? "selected" : "") . " >Very high</option>\n";
$_mpd_select['sox_quality'] .= "<option value=\"high\" " . (($mpdconf['sox_quality'] == 'high') ? "selected" : "") . " >High (Default)</option>\n";
$_mpd_select['sox_quality'] .= "<option value=\"medium\" " . (($mpdconf['sox_quality'] == 'medium') ? "selected" : "") . " >Medium</option>\n";
if ($patch_id & PATCH_SOX_CUSTOM_RECIPE) {
	$_mpd_select['sox_quality'] .= "<option value=\"custom\" " . (($mpdconf['sox_quality'] == 'custom') ? "selected" : "") . " >Custom recipe</option>\n";
}

// Custom SoX recipe
if ($patch_id & PATCH_SOX_CUSTOM_RECIPE) {
	$_sox_custom_recipe_hide = '';
	$_mpd_select['sox_precision'] .= "<option value=\"16\" " . (($mpdconf['sox_precision'] == '16') ? "selected" : "") . " >16 bit</option>\n";
	$_mpd_select['sox_precision'] .= "<option value=\"20\" " . (($mpdconf['sox_precision'] == '20') ? "selected" : "") . " >20 bit (Default)</option>\n";
	$_mpd_select['sox_precision'] .= "<option value=\"24\" " . (($mpdconf['sox_precision'] == '24') ? "selected" : "") . " >24 bit</option>\n";
	$_mpd_select['sox_precision'] .= "<option value=\"28\" " . (($mpdconf['sox_precision'] == '28') ? "selected" : "") . " >28 bit</option>\n";
	$_mpd_select['sox_precision'] .= "<option value=\"32\" " . (($mpdconf['sox_precision'] == '32') ? "selected" : "") . " >32 bit</option>\n";
	$_mpd_select['sox_phase_response'] = $mpdconf['sox_phase_response'];
	$_mpd_select['sox_passband_end'] = $mpdconf['sox_passband_end'];
	$_mpd_select['sox_stopband_begin'] = $mpdconf['sox_stopband_begin'];
	$_mpd_select['sox_attenuation'] = $mpdconf['sox_attenuation'];
	$_mpd_select['sox_flags'] = $mpdconf['sox_flags'];
}
else {
	$_sox_custom_recipe_hide = 'hide';
}

// SoX multithreading
$_mpd_select['sox_multithreading'] .= "<option value=\"0\" " . (($mpdconf['sox_multithreading'] == '0') ? "selected" : "") . " >Yes</option>\n";
$_mpd_select['sox_multithreading'] .= "<option value=\"1\" " . (($mpdconf['sox_multithreading'] == '1') ? "selected" : "") . " >No</option>\n";

// Replaygain
$_mpd_select['replaygain'] .= "<option value=\"off\" " . (($mpdconf['replaygain'] == 'off') ? "selected" : "") . " >Off</option>\n";
$_mpd_select['replaygain'] .= "<option value=\"auto\" " . (($mpdconf['replaygain'] == 'auto') ? "selected" : "") . " >Auto</option>\n";
$_mpd_select['replaygain'] .= "<option value=\"album\" " . (($mpdconf['replaygain'] == 'album') ? "selected" : "") . " >Album</option>\n";
$_mpd_select['replaygain'] .= "<option value=\"track\" " . (($mpdconf['replaygain'] == 'track') ? "selected" : "") . " >Track</option>\n";

// Replaygain preamp
$_mpd_select['replaygain_preamp'] = $mpdconf['replaygain_preamp'];

// Volume normalization
$_mpd_select['volume_normalization'] .= "<option value=\"yes\" " . (($mpdconf['volume_normalization'] == 'yes') ? "selected" : "") . " >Yes</option>\n";
$_mpd_select['volume_normalization'] .= "<option value=\"no\" " . (($mpdconf['volume_normalization'] == 'no') ? "selected" : "") . " >No</option>\n";

// Resource limits
$_mpd_select['audio_buffer_size'] = $mpdconf['audio_buffer_size'] / 1024; // Convert these from KB to MB
$_mpd_select['max_output_buffer_size'] = $mpdconf['max_output_buffer_size'] / 1024;
$_mpd_select['max_playlist_length'] = $mpdconf['max_playlist_length'];
$_mpd_select['input_cache'] .= "<option value=\"Disabled\" " . (($mpdconf['input_cache'] == 'Disabled') ? "selected" : "") . " >Disabled</option>\n";
$_mpd_select['input_cache'] .= "<option value=\"128 MB\" " . (($mpdconf['input_cache'] == '128 MB') ? "selected" : "") . " >128 MB</option>\n";
$_mpd_select['input_cache'] .= "<option value=\"256 MB\" " . (($mpdconf['input_cache'] == '256 MB') ? "selected" : "") . " >256 MB</option>\n";
$_mpd_select['input_cache'] .= "<option value=\"512 MB\" " . (($mpdconf['input_cache'] == '512 MB') ? "selected" : "") . " >512 MB</option>\n";
$_mpd_select['input_cache'] .= "<option value=\"1 GB\" " . (($mpdconf['input_cache'] == '1 GB') ? "selected" : "") . " >1 GB</option>\n";
$_mpd_select['input_cache'] .= "<option value=\"2 GB\" " . (($mpdconf['input_cache'] == '2 GB') ? "selected" : "") . " >2 GB</option>\n";

// Log level
$_mpd_select['log_level'] .= "<option value=\"default\" " . (($mpdconf['log_level'] == 'default') ? "selected" : "") . " >Default</option>\n";
$_mpd_select['log_level'] .= "<option value=\"verbose\" " . (($mpdconf['log_level'] == 'verbose') ? "selected" : "") . " >Verbose</option>\n";

/* DEPRECATE
// hardware buffer time
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

// ALSA auto-resample
$_mpd_select['auto_resample'] .= "<option value=\"yes\" " . (($mpdconf['auto_resample'] == 'yes') ? "selected" : "") . " >Yes</option>\n";
$_mpd_select['auto_resample'] .= "<option value=\"no\" " . (($mpdconf['auto_resample'] == 'no') ? "selected" : "") . " >No</option>\n";
// ALSA auto-channels, r45b
$_mpd_select['auto_channels'] .= "<option value=\"yes\" " . (($mpdconf['auto_channels'] == 'yes') ? "selected" : "") . " >Yes</option>\n";
$_mpd_select['auto_channels'] .= "<option value=\"no\" " . (($mpdconf['auto_channels'] == 'no') ? "selected" : "") . " >No</option>\n";
/// ALSA auto-format, r45b
$_mpd_select['auto_format'] .= "<option value=\"yes\" " . (($mpdconf['auto_format'] == 'yes') ? "selected" : "") . " >Yes</option>\n";
$_mpd_select['auto_format'] .= "<option value=\"no\" " . (($mpdconf['auto_format'] == 'no') ? "selected" : "") . " >No</option>\n";
*/

/* DEPRECATE
// Zeroconf
$_mpd_select['zeroconf_enabled'] .= "<option value=\"yes\" " . (($mpdconf['zeroconf_enabled'] == 'yes') ? "selected" : "") . ">Yes</option>\n";
$_mpd_select['zeroconf_enabled'] .= "<option value=\"no\" " . (($mpdconf['zeroconf_enabled'] == 'no') ? "selected" : "") . ">No</option>\n";
$_mpd_select['zeroconf_name'] = $mpdconf['zeroconf_name'];
*/

waitWorker(1, 'mpd-config');

$tpl = "mpd-config.html";
$section = basename(__FILE__, '.php');
storeBackLink($section, $tpl);

include('header.php');
eval("echoTemplate(\"" . getTemplate("templates/$tpl") . "\");");
include('footer.min.php');
