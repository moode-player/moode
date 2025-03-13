<?php
/*
 * SPDX-License-Identifier: GPL-3.0-or-later
 * Copyright 2014 The moOde audio player project / Tim Curtis
 * Copyright 2013 The tsunamp player ui / Andrea Coiutti & Simone De Gregori
*/

require_once __DIR__ . '/inc/common.php';
require_once __DIR__ . '/inc/session.php';
require_once __DIR__ . '/inc/sql.php';

$dbh = sqlConnect();
phpSession('open_ro');

chkVariables($_POST);

// Save changes to /etc/mpd.conf
if (isset($_POST['save']) && $_POST['save'] == '1') {
	// Add audio_output_format
	$_POST['conf']['audio_output_format'] = $_POST['sox_enabled'] == 'No' ? 'disabled' : $_POST['sox_sample_rate'] . ':' . $_POST['sox_bit_depth'] . ':' . $_POST['sox_channels'];

	// Set selective_resample_mode
	if ($_POST['sox_enabled'] == 'No' || $_POST['sox_sample_rate'] == '*') {
		$_POST['conf']['selective_resample_mode'] = '0';
	}

	// Update SQL table
	foreach ($_POST['conf'] as $key => $value) {
		if ($key == 'audio_buffer_size' || $key == 'max_output_buffer_size') {
			$value = $value * 1024; // Convert from MB to KB
		}

		sqlUpdate('cfg_mpd', $dbh, $key, $value);
	}

	// No device or mixer changes (moved to snd-config.php)
	$deviceChange = 0;
	$mixerChange = 0;
	$queueArgs = $deviceChange . ',' . $mixerChange;
	submitJob('mpdcfg', $queueArgs, NOTIFY_TITLE_INFO, 'MPD' . NOTIFY_MSG_SVC_RESTARTED);
}

// Load settings
$result = sqlRead('cfg_mpd', $dbh);
$cfgMPD = array();

foreach ($result as $row) {
	$cfgMPD[$row['param']] = $row['value'];
}

if ($_SESSION['audioout'] == 'Bluetooth' || $_SESSION['multiroom_tx'] == 'On' || $_SESSION['multiroom_rx'] == 'On') {
	$_save_disabled = 'disabled';
} else {
	$_save_disabled = '';
}

// MPD VERSION
$_mpd_version = $_SESSION['mpdver'];

// DSD SUPPORT

// Format
$_mpd_select['dop'] .= "<option value=\"no\" " . (($cfgMPD['dop'] == 'no') ? "selected" : "") . " >Native DSD (Default)</option>\n";
$_mpd_select['dop'] .= "<option value=\"yes\" " . (($cfgMPD['dop'] == 'yes') ? "selected" : "") . " >DSD over PCM (DoP)</option>\n";
// DSD silence before stop
$_mpd_select['stop_dsd_silence'] .= "<option value=\"yes\" " . (($cfgMPD['stop_dsd_silence'] == 'yes') ? "selected" : "") . " >Yes</option>\n";
$_mpd_select['stop_dsd_silence'] .= "<option value=\"no\" " . (($cfgMPD['stop_dsd_silence'] == 'no') ? "selected" : "") . " >No</option>\n";
// Thesycon DSD workaround
$_mpd_select['thesycon_dsd_workaround'] .= "<option value=\"yes\" " . (($cfgMPD['thesycon_dsd_workaround'] == 'yes') ? "selected" : "") . " >Yes</option>\n";
$_mpd_select['thesycon_dsd_workaround'] .= "<option value=\"no\" " . (($cfgMPD['thesycon_dsd_workaround'] == 'no') ? "selected" : "") . " >No</option>\n";

// SOX RESAMPLING

// Enabled
$_mpd_select['sox_enabled'] .= "<option value=\"Yes\" " . (($cfgMPD['audio_output_format'] != 'disabled') ? "selected" : "") . " >Yes</option>\n";
$_mpd_select['sox_enabled'] .= "<option value=\"No\" " . (($cfgMPD['audio_output_format'] == 'disabled') ? "selected" : "") . " >No</option>\n";
// Bit depth
$format = array('','','');
$format = explode(':', $cfgMPD['audio_output_format']);
$_mpd_select['sox_bit_depth'] .= "<option value=\"*\" " . (($cfgMPD['audio_output_format'] != 'disabled' && $format[1] == '*') ? "selected" : "") . " >Any</option>\n";
$_mpd_select['sox_bit_depth'] .= "<option value=\"16\" " . (($cfgMPD['audio_output_format'] != 'disabled' && $format[1] == '16') ? "selected" : "") . " >16</option>\n";
$_mpd_select['sox_bit_depth'] .= "<option value=\"24\" " . (($cfgMPD['audio_output_format'] != 'disabled' && $format[1] == '24') ? "selected" : "") . " >24</option>\n";
$_mpd_select['sox_bit_depth'] .= "<option value=\"32\" " . (($cfgMPD['audio_output_format'] != 'disabled' && $format[1] == '32') ? "selected" : "") . " >32</option>\n";
// Sample rate
$_mpd_select['sox_sample_rate'] .= "<option value=\"*\" " . (($cfgMPD['audio_output_format'] != 'disabled' && $format[0] == '*') ? "selected" : "") . " >Any</option>\n";
$_mpd_select['sox_sample_rate'] .= "<option value=\"44100\" " . (($cfgMPD['audio_output_format'] != 'disabled' && $format[0] == '44100') ? "selected" : "") . " >44.1</option>\n";
$_mpd_select['sox_sample_rate'] .= "<option value=\"48000\" " . (($cfgMPD['audio_output_format'] != 'disabled' && $format[0] == '48000') ? "selected" : "") . " >48</option>\n";
$_mpd_select['sox_sample_rate'] .= "<option value=\"88200\" " . (($cfgMPD['audio_output_format'] != 'disabled' && $format[0] == '88200') ? "selected" : "") . " >88.2</option>\n";
$_mpd_select['sox_sample_rate'] .= "<option value=\"96000\" " . (($cfgMPD['audio_output_format'] != 'disabled' && $format[0] == '96000') ? "selected" : "") . " >96</option>\n";
$_mpd_select['sox_sample_rate'] .= "<option value=\"176400\" " . (($cfgMPD['audio_output_format'] != 'disabled' && $format[0] == '176400') ? "selected" : "") . " >176.4</option>\n";
$_mpd_select['sox_sample_rate'] .= "<option value=\"192000\" " . (($cfgMPD['audio_output_format'] != 'disabled' && $format[0] == '192000') ? "selected" : "") . " >192</option>\n";
$_mpd_select['sox_sample_rate'] .= "<option value=\"352800\" " . (($cfgMPD['audio_output_format'] != 'disabled' && $format[0] == '352800') ? "selected" : "") . " >352.8</option>\n";
$_mpd_select['sox_sample_rate'] .= "<option value=\"384000\" " . (($cfgMPD['audio_output_format'] != 'disabled' && $format[0] == '384000') ? "selected" : "") . " >384</option>\n";
$_mpd_select['sox_sample_rate'] .= "<option value=\"705600\" " . (($cfgMPD['audio_output_format'] != 'disabled' && $format[0] == '705600') ? "selected" : "") . " >705.6</option>\n";
$_mpd_select['sox_sample_rate'] .= "<option value=\"768000\" " . (($cfgMPD['audio_output_format'] != 'disabled' && $format[0] == '768000') ? "selected" : "") . " >768</option>\n";
// Channels
$_mpd_select['sox_channels'] .= "<option value=\"*\" " . (($cfgMPD['audio_output_format'] != 'disabled' && $format[2] == '*') ? "selected" : "") . " >Any</option>\n";
$_mpd_select['sox_channels'] .= "<option value=\"2\" " . (($cfgMPD['audio_output_format'] != 'disabled' && $format[2] == '2') ? "selected" : "") . " >Stereo</option>\n";
$_mpd_select['sox_channels'] .= "<option value=\"1\" " . (($cfgMPD['audio_output_format'] != 'disabled' && $format[2] == '1') ? "selected" : "") . " >Mono</option>\n";
// Selective resample mode
$_selective_resampling_hide = ''; // This is ment to control visibility of the feature in case MPD no longer supports the patch
$selectiveModeLabel = array(
	SOX_UPSAMPLE_ALL => 'Upsample if source &lt; target rate',
	SOX_UPSAMPLE_ONLY_41K => 'Upsample only 44.1K source rate',
	SOX_UPSAMPLE_ONLY_4148K => 'Upsample only 44.1K and 48K source rates',
	SOX_ADHERE_BASE_FREQ => 'Resample (adhere to base freq)',
	SOX_UPSAMPLE_ALL + SOX_ADHERE_BASE_FREQ => 'Upsample if source &lt; target rate (adhere to base freq)'
);
$_mpd_select['selective_resample_mode'] .= "<option value=\"0\" " . (($cfgMPD['selective_resample_mode'] == '0') ? "selected" : "") . " >Disabled</option>\n";
$_mpd_select['selective_resample_mode'] .= "<option value=\"" . SOX_UPSAMPLE_ALL . "\" " . (($cfgMPD['selective_resample_mode'] == SOX_UPSAMPLE_ALL) ? "selected" : "") . " >" . $selectiveModeLabel[SOX_UPSAMPLE_ALL] . "</option>\n";
$_mpd_select['selective_resample_mode'] .= "<option value=\"" . SOX_UPSAMPLE_ONLY_41K . "\" " . (($cfgMPD['selective_resample_mode'] == SOX_UPSAMPLE_ONLY_41K) ? "selected" : "") . " >" . $selectiveModeLabel[SOX_UPSAMPLE_ONLY_41K] . "</option>\n";
$_mpd_select['selective_resample_mode'] .= "<option value=\"" . SOX_UPSAMPLE_ONLY_4148K . "\" " . (($cfgMPD['selective_resample_mode'] == SOX_UPSAMPLE_ONLY_4148K) ? "selected" : "") . " >" . $selectiveModeLabel[SOX_UPSAMPLE_ONLY_4148K] . "</option>\n";
$_mpd_select['selective_resample_mode'] .= "<option value=\"" . SOX_ADHERE_BASE_FREQ . "\" " . (($cfgMPD['selective_resample_mode'] == SOX_ADHERE_BASE_FREQ) ? "selected" : "") . " >" . $selectiveModeLabel[SOX_ADHERE_BASE_FREQ] . "</option>\n";
$_mpd_select['selective_resample_mode'] .= "<option value=\"" . (SOX_UPSAMPLE_ALL + SOX_ADHERE_BASE_FREQ) . "\" " . (($cfgMPD['selective_resample_mode'] == (SOX_UPSAMPLE_ALL + SOX_ADHERE_BASE_FREQ)) ? "selected" : "") . " >" . $selectiveModeLabel[SOX_UPSAMPLE_ALL + SOX_ADHERE_BASE_FREQ] . "</option>\n";
$_selective_mode_selected = $cfgMPD['selective_resample_mode'] == '0' ? '' :
	'<span class="config-help-static">Selected: ' . $selectiveModeLabel[$cfgMPD['selective_resample_mode']] . '</span>';
// Resampling quality
$_mpd_select['sox_quality'] .= "<option value=\"very high\" " . (($cfgMPD['sox_quality'] == 'very high') ? "selected" : "") . " >Very high</option>\n";
$_mpd_select['sox_quality'] .= "<option value=\"high\" " . (($cfgMPD['sox_quality'] == 'high') ? "selected" : "") . " >High (Default)</option>\n";
$_mpd_select['sox_quality'] .= "<option value=\"medium\" " . (($cfgMPD['sox_quality'] == 'medium') ? "selected" : "") . " >Medium</option>\n";
$_mpd_select['sox_quality'] .= "<option value=\"custom\" " . (($cfgMPD['sox_quality'] == 'custom') ? "selected" : "") . " >Custom recipe</option>\n";
// Custom SoX recipe
$_sox_custom_recipe_hide = '';
$_mpd_select['sox_precision'] .= "<option value=\"16\" " . (($cfgMPD['sox_precision'] == '16') ? "selected" : "") . " >16 bit</option>\n";
$_mpd_select['sox_precision'] .= "<option value=\"20\" " . (($cfgMPD['sox_precision'] == '20') ? "selected" : "") . " >20 bit (Default)</option>\n";
$_mpd_select['sox_precision'] .= "<option value=\"24\" " . (($cfgMPD['sox_precision'] == '24') ? "selected" : "") . " >24 bit</option>\n";
$_mpd_select['sox_precision'] .= "<option value=\"28\" " . (($cfgMPD['sox_precision'] == '28') ? "selected" : "") . " >28 bit</option>\n";
$_mpd_select['sox_precision'] .= "<option value=\"32\" " . (($cfgMPD['sox_precision'] == '32') ? "selected" : "") . " >32 bit</option>\n";
$_mpd_select['sox_phase_response'] = $cfgMPD['sox_phase_response'];
$_mpd_select['sox_passband_end'] = $cfgMPD['sox_passband_end'];
$_mpd_select['sox_stopband_begin'] = $cfgMPD['sox_stopband_begin'];
$_mpd_select['sox_attenuation'] = $cfgMPD['sox_attenuation'];
$_mpd_select['sox_flags'] = $cfgMPD['sox_flags'];
// SoX multithreading
$_mpd_select['sox_multithreading'] .= "<option value=\"0\" " . (($cfgMPD['sox_multithreading'] == '0') ? "selected" : "") . " >Yes</option>\n";
$_mpd_select['sox_multithreading'] .= "<option value=\"1\" " . (($cfgMPD['sox_multithreading'] == '1') ? "selected" : "") . " >No</option>\n";

// GAIN AND NORMALIZATION

// Replaygain
$_mpd_select['replaygain'] .= "<option value=\"off\" " . (($cfgMPD['replaygain'] == 'off') ? "selected" : "") . " >Off</option>\n";
$_mpd_select['replaygain'] .= "<option value=\"auto\" " . (($cfgMPD['replaygain'] == 'auto') ? "selected" : "") . " >Auto</option>\n";
$_mpd_select['replaygain'] .= "<option value=\"album\" " . (($cfgMPD['replaygain'] == 'album') ? "selected" : "") . " >Album</option>\n";
$_mpd_select['replaygain'] .= "<option value=\"track\" " . (($cfgMPD['replaygain'] == 'track') ? "selected" : "") . " >Track</option>\n";
// Replaygain preamp
$_mpd_select['replaygain_preamp'] = $cfgMPD['replaygain_preamp'];
// Volume normalization
$_mpd_select['volume_normalization'] .= "<option value=\"yes\" " . (($cfgMPD['volume_normalization'] == 'yes') ? "selected" : "") . " >Yes</option>\n";
$_mpd_select['volume_normalization'] .= "<option value=\"no\" " . (($cfgMPD['volume_normalization'] == 'no') ? "selected" : "") . " >No</option>\n";

// RESOURCE ALLOCATION

// Buffers
$_mpd_select['audio_buffer_size'] = $cfgMPD['audio_buffer_size'] / 1024; // Convert these from KB to MB
$_mpd_select['max_output_buffer_size'] = $cfgMPD['max_output_buffer_size'] / 1024;
// Max Queue items
$_mpd_select['max_playlist_length'] = $cfgMPD['max_playlist_length'];
// Input cache
$_mpd_select['input_cache'] .= "<option value=\"Disabled\" " . (($cfgMPD['input_cache'] == 'Disabled') ? "selected" : "") . " >Disabled</option>\n";
$_mpd_select['input_cache'] .= "<option value=\"128 MB\" " . (($cfgMPD['input_cache'] == '128 MB') ? "selected" : "") . " >128 MB</option>\n";
$_mpd_select['input_cache'] .= "<option value=\"256 MB\" " . (($cfgMPD['input_cache'] == '256 MB') ? "selected" : "") . " >256 MB</option>\n";
$_mpd_select['input_cache'] .= "<option value=\"512 MB\" " . (($cfgMPD['input_cache'] == '512 MB') ? "selected" : "") . " >512 MB</option>\n";
$_mpd_select['input_cache'] .= "<option value=\"1 GB\" " . (($cfgMPD['input_cache'] == '1 GB') ? "selected" : "") . " >1 GB</option>\n";
$_mpd_select['input_cache'] .= "<option value=\"2 GB\" " . (($cfgMPD['input_cache'] == '2 GB') ? "selected" : "") . " >2 GB</option>\n";

// HTTP PROXY

$_mpd_select['proxy'] = $cfgMPD['proxy'];
$_mpd_select['proxy_user'] = $cfgMPD['proxy_user'];
$_mpd_select['proxy_password'] = $cfgMPD['proxy_password'];

// OTHER OPTIONS

// Log level
$_mpd_select['log_level'] .= "<option value=\"notice\" " . (($cfgMPD['log_level'] == 'notice') ? "selected" : "") . " >Notice (Default)</option>\n";
$_mpd_select['log_level'] .= "<option value=\"error\" " . (($cfgMPD['log_level'] == 'error') ? "selected" : "") . " >Error</option>\n";
$_mpd_select['log_level'] .= "<option value=\"warning\" " . (($cfgMPD['log_level'] == 'warning') ? "selected" : "") . " >Warning</option>\n";
$_mpd_select['log_level'] .= "<option value=\"info\" " . (($cfgMPD['log_level'] == 'info') ? "selected" : "") . " >Info</option>\n";
$_mpd_select['log_level'] .= "<option value=\"verbose\" " . (($cfgMPD['log_level'] == 'verbose') ? "selected" : "") . " >Verbose</option>\n";
// ALSA: Close on pause
$_mpd_select['close_on_pause'] .= "<option value=\"yes\" " . (($cfgMPD['close_on_pause'] == 'yes') ? "selected" : "") . " >Yes</option>\n";
$_mpd_select['close_on_pause'] .= "<option value=\"no\" " . (($cfgMPD['close_on_pause'] == 'no') ? "selected" : "") . " >No</option>\n";
// ALSA: Device buffer time in microseconds (label in milliseconds)
$_mpd_select['buffer_time'] .= "<option value=\"500000\" " . (($cfgMPD['buffer_time'] == '500000') ? "selected" : "") . " >500 (Default)</option>\n";
$_mpd_select['buffer_time'] .= "<option value=\"400000\" " . (($cfgMPD['buffer_time'] == '400000') ? "selected" : "") . " >400</option>\n";
$_mpd_select['buffer_time'] .= "<option value=\"300000\" " . (($cfgMPD['buffer_time'] == '300000') ? "selected" : "") . " >300</option>\n";
$_mpd_select['buffer_time'] .= "<option value=\"200000\" " . (($cfgMPD['buffer_time'] == '200000') ? "selected" : "") . " >200</option>\n";
$_mpd_select['buffer_time'] .= "<option value=\"100000\" " . (($cfgMPD['buffer_time'] == '100000') ? "selected" : "") . " >100</option>\n";
$_period_time = $cfgMPD['buffer_time'] / 4000; // miliseconds

waitWorker('mpd-config');

$tpl = "mpd-config.html";
$section = basename(__FILE__, '.php');
storeBackLink($section, $tpl);

include('header.php');
eval("echoTemplate(\"" . getTemplate("templates/$tpl") . "\");");
include('footer.php');
