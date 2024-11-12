<?php
/*
 * SPDX-License-Identifier: GPL-3.0-or-later
 * Copyright 2014 The moOde audio player project / Tim Curtis
*/

require_once __DIR__ . '/inc/common.php';
require_once __DIR__ . '/inc/alsa.php';
require_once __DIR__ . '/inc/cdsp.php';
require_once __DIR__ . '/inc/mpd.php';
require_once __DIR__ . '/inc/music-library.php';
require_once __DIR__ . '/inc/session.php';
require_once __DIR__ . '/inc/sql.php';

$sock = getMpdSock();
$dbh = sqlConnect();
phpSession('open_ro');

// ALSA Hardware params
$adevName = $_SESSION['adevname'] == TRX_SENDER_NAME ? ALSA_LOOPBACK_DEVICE : $_SESSION['adevname'];
$hwParams = getAlsaHwParams(getAlsaCardNumForDevice($adevName));
//workerLog('audioinfo.php: cardnum: ' . print_r(getAlsaCardNumForDevice($_SESSION['adevname']), true));
//workerLog('audioinfo.php: hwparams: ' . print_r($hwParams, true));

// SQL table cfg_mpd settings
$cfgMPD = getCfgMpd($dbh);

// Bluetooth active
$result = sysCmd('pgrep -l bluealsa-aplay');
$btActive = strpos($result[0], 'bluealsa-aplay') !== false ? true : false;

// Other renderer active
$aplActive = sqlQuery("SELECT value FROM cfg_system WHERE param='aplactive'", $dbh)[0]['value'];
$spotActive = sqlQuery("SELECT value FROM cfg_system WHERE param='spotactive'", $dbh)[0]['value'];
$slActive = sqlQuery("SELECT value FROM cfg_system WHERE param='slactive'", $dbh)[0]['value'];
$paActive = sqlQuery("SELECT value FROM cfg_system WHERE param='paactive'", $dbh)[0]['value'];
$rbActive = sqlQuery("SELECT value FROM cfg_system WHERE param='rbactive'", $dbh)[0]['value'];

//
// INPUT
//

if ($btActive === true && $_SESSION['audioout'] == 'Local') {
	$_file = 'Bluetooth stream';
	$_encoded_at = sysCmd("bluealsa-cli -v list-pcms | awk -F\": \" '/Selected codec/ {print $2}' | cut -d\":\" -f1")[0];
	$_decoded_to = 'PCM 16 bit 44.1 kHz, Stereo';
	$_decode_rate = '';
} else if ($aplActive == '1') {
	$_file = 'AirPlay stream';
	$_encoded_at = 'ALAC or AAC';
	$_decoded_to = 'PCM 16 bit 44.1 kHz, Stereo';
	$_decode_rate = '';
} else if ($spotActive == '1') {
	$_file = 'Spotify stream';
	$_encoded_at = 'Ogg/Vorbis or AAC';
	$_decoded_to = 'PCM 16 bit 44.1 kHz, Stereo';
	$_decode_rate = '';
} else if ($slActive == '1') {
	$_file = 'Squeezelite stream';
	$_encoded_at = 'Unknown';
	$_decoded_to = 'Unknown';
	$_decode_rate = '';
} else if ($paActive == '1') {
	$_file = 'Plexamp stream';
	$_encoded_at = 'Unknown';
	$_decoded_to = 'Unknown';
	$_decode_rate = '';
} else if ($rbActive == '1') {
	$_file = 'RoonBridge stream';
	$_encoded_at = 'Unknown';
	$_decoded_to = 'Unknown';
	$_decode_rate = '';
} else if ($_SESSION['multiroom_rx'] == 'On') {
	$_file = 'Multiroom sender stream';
	$_encoded_at = 'Opus 16 bit 48 kHz, Stereo';
	$_decoded_to = 'PCM 16 bit 48 kHz, Stereo';
	$_decode_rate = '';
} else {
	$song = getCurrentSong($sock);
	$_file = $song['file'];

	// Krishna Simonese: if current file is a UPNP/DLNA url, replace *20 with space
	// NOTE Normally URI would be encoded %20 but ? is changing it to *20
	if ( substr( $_file, 0, 4) == 'http' ) {
		$_file = str_replace( '*20', ' ', $_file );
	}

	$_encoded_at = getEncodedAt($song, 'verbose');
	$mpdStatus = getMpdStatus($sock);
	//workerLog(print_r($_encoded_at, true));
	//workerLog(print_r($mpdStatus, true));

	if ($hwParams['status'] == 'active' || ($_SESSION['audioout'] == 'Bluetooth' && $mpdStatus['state'] == 'play')) {
		// DSD: Native bitstream, DoP or DSD to PCM on-the-fly conversion
		if (strpos($mpdStatus['audio_format'], 'DSD') !== false) {
			// Encoded at
			$_encoded_at =
				'DSD 1 bit ' .
				$mpdStatus['audio_sample_rate'] . ' MHz, ' .
				$mpdStatus['audio_channels'];
			// Decoded to
			if ($cfgMPD['dop'] == 'yes') {
				$dop = formatDoP($mpdStatus['audio_format']);
				$_decoded_to = $dop['decoded_to'];
				$_decode_rate = $dop['decode_rate'];
			} else if ($hwParams['format'] == 'DSD') {
				$_decoded_to = 'DSD 1 bit ' . $mpdStatus['audio_sample_rate'] . ' MHz, ' . $mpdStatus['audio_channels'];
				$_decode_rate = $hwParams['calcrate'] . ' Mbps';
			} else {
				$_decoded_to = 'PCM ' . $hwParams['format'] . ' bit ' . $hwParams['rate'] . ' kHz, ' . $hwParams['channels'];
				$_decode_rate = $hwParams['calcrate'] . ' Mbps';
			}
		} else {
			// PCM
			$rate = empty($mpdStatus['audio_sample_rate']) ? '?' : $mpdStatus['audio_sample_rate'];
			$_decoded_to = 'PCM ' . $mpdStatus['audio_sample_depth'] . ' bit ' . $rate . ' kHz, ' . $mpdStatus['audio_channels'];
			$_decode_rate = $mpdStatus['bitrate'];
		}
		$_decode_rate = ', ' . $_decode_rate;
	} else {
		$_decoded_to = '';
		$_decode_rate = 'Not playing';
	}
}

//
// OUTPUT
//

// Output format
if ($_SESSION['audioout'] == 'Bluetooth' && $mpdStatus['state'] == 'play') {
	$_hwparams_format = 'PCM 16 bit 44.1 kHz, Stereo, ';
	$_hwparams_calcrate = '1.411 Mbps';
} else if ($hwParams['status'] == 'active') {
	// NOTE: $hwParams['format'] = 'DSD' or PCM bit depth
	$format = $hwParams['format'] == 'DSD' ?
		'DSD 1 bit ' . $mpdStatus['audio_sample_rate'] . ' MHz, ' . $mpdStatus['audio_channels'] :
		'PCM ' . $hwParams['format'] . ' bit ' . $hwParams['rate'] . ' kHz, ' . $hwParams['channels'];
	$_hwparams_format = $format;
	$_hwparams_calcrate = ', ' . $hwParams['calcrate'] . ' Mbps';
} else {
	$_hwparams_format = '';
	$_hwparams_calcrate = 'Not playing';
}
$_alsa_output_format = $_hwparams_format . $_hwparams_calcrate;

// Output chain
// Renderer
if ($btActive === true) {
	$renderer = 'Bluetooth';
} else if ($aplActive == '1') {
	$renderer = 'AirPlay';
} else if ($spotActive == '1') {
	$renderer = 'Spotify';
} else if ($slActive == '1') {
	$renderer = 'Squeezelite';
} else if ($paActive == '1') {
	$renderer = 'Plexamp';
} else if ($rbActive == '1') {
	$renderer = 'Roonbridge';
} else {
	$renderer = 'MPD';
}
// DSP and output mode

if ($_SESSION['invert_polarity'] == '1') {
	$dsp = 'Polarity inversion';
	$outputMode = $_SESSION['alsa_output_mode'];
} else if ($_SESSION['crossfeed'] != 'Off') {
	$dsp = 'Crossfeed';
	$outputMode = 'plughw';
} else if ($_SESSION['eqfa12p'] != 'Off') {
	$dsp = 'Parametric EQ';
	$outputMode = 'plughw';
} else if ($_SESSION['alsaequal'] != 'Off') {
	$dsp = 'Graphic EQ';
	$outputMode = 'plughw';
} else if (getCamillaDspConfigName($_SESSION['camilladsp']) != 'Off') {
	$dsp = 'CamillaDSP';
	$outputMode = $_SESSION['alsa_output_mode'];
} else {
	$dsp = '';
	$outputMode = $_SESSION['alsa_output_mode'];
}

// Bluetooth overrides
if ($btActive === true) {
	if ($_SESSION['alsa_output_mode'] == 'iec958') {
		$outputModeName = ALSA_OUTPUT_MODE_NAME[$_SESSION['alsa_output_mode']];
	} else {
		$outputModeName = ALSA_OUTPUT_MODE_BT_NAME[$_SESSION['alsa_output_mode_bt']];
		$outputMode = $_SESSION['alsa_output_mode_bt'] == '_audioout' ?
			$_SESSION['alsa_output_mode'] :
			'plughw';
	}
} else {
	$outputModeName = ALSA_OUTPUT_MODE_NAME[$_SESSION['alsa_output_mode']];
}

// Combine parts
if ($_SESSION['audioout'] == 'Bluetooth') {
	$_audio_output_chain = 'MPD &rarr; Bluetooth stream &rarr; Bluetooth speaker';
} else if ($_SESSION['multiroom_tx'] == 'On') {
	$_audio_output_chain = $renderer . ' &rarr; Multiroom Sender';
} else if ($_SESSION['multiroom_rx'] == 'On') {
	$_audio_output_chain = 'Multiroom Receiver &rarr; Device';
} else if ($dsp != '') {
	$_audio_output_chain = $renderer . ' &rarr; ' . $dsp . ' &rarr; ' . $outputMode . ' &rarr; Device';
} else {
	$_audio_output_chain = $renderer . ' &rarr; ' . $outputMode . ' &rarr; Device';
}

// ALSA Output mode and Loopback
$_alsa_output_mode = $_SESSION['multiroom_tx'] == 'On' ?
	ALSA_LOOPBACK_DEVICE :
	 $outputModeName . ' (' . $outputMode . ')';
$_alsa_loopback = $_SESSION['alsa_loopback'] == 'Off' ? 'off' : $_SESSION['alsa_loopback'];
$_alsa_loopback_class = $_alsa_loopback; // NOTE: 'off' is a class that hides the element

//
// DSP
//

// Volume type and range
if ($_SESSION['mpdmixer'] == 'hardware') {
	$_volume_mixer = 'Hardware (On-chip)';
}
else if ($_SESSION['mpdmixer'] == 'software') {
	$_volume_mixer = 'MPD Software (24-bit)'; // 32-bit?
}
else if ($_SESSION['mpdmixer'] == 'none') {
	$_volume_mixer = 'Fixed (0dB output)';
}
else if ($_SESSION['mpdmixer'] == 'null' && $_SESSION['camilladsp'] != 'off' && $_SESSION['camilladsp_volume_sync'] == 'on') {
	$_volume_mixer = 'CamillaDSP (64-bit), Range ' . $_SESSION['camilladsp_volume_range'] . ' dB';
}
elseif ($_SESSION['mpdmixer'] == 'null') {
	$_volume_mixer = 'Null (External control)';
}
else {
	$_volume_mixer = 'Error: Unknow MPD volume type';
}
// Volume levels
$knobVol = $_SESSION['volknob'];
$alsaVol = getAlsaVolumeDb($_SESSION['amixname']);
$cdspVol = CamillaDSP::getCDSPVol() . 'dB';
$_volume_levels = 'Knob ' . $knobVol . ', ALSA ' . $alsaVol . ', CDSP ' . $cdspVol;

if ($aplActive == '1' || $spotActive == '1' || $slActive == '1' || $paActive == '1' || $rbActive == '1' ||
	$btActive === true || $_SESSION['audioout'] == 'Bluetooth' || $_SESSION['inpactive'] == '1') {
	// Renderer active
	// NOTE: Class 'off' hides the item
	$_resample_format = '';
	$_resample_quality = 'off';
	$_polarity_inv = 'off';
	$_crossfade = 'off';
	$_crossfeed = 'off';
	$_replaygain = 'off';
	$_vol_normalize = 'off';

	if ($aplActive == '1' || $spotActive == '1') {
		$_peq = $_SESSION['eqfa12p'] == 'Off' ? 'off' : $_SESSION['eqfa12p'];
		$_geq = $_SESSION['alsaequal'] == 'Off' ? 'off' : $_SESSION['alsaequal'];
        $_camilladsp = getCamillaDspConfigName($_SESSION['camilladsp']);
	} else {
		$_peq = 'off';
		$_geq = 'off';
        $_camilladsp = 'off';
	}
} else {
	// MPD
	// Resampling
	if ($cfgMPD['audio_output_format'] == 'disabled') {
		$_resample_format = 'Off';
		$_selective_resample = 'Off';
		$_resample_quality = 'Off';
	} else {
		$_resample_format =
			$cfgMPD['audio_output_depth'] . 'Bit, ' .
			$cfgMPD['audio_output_rate'] . 'kHz, ' .
			$cfgMPD['audio_output_channels'];
		$resample_modes = array('0' => 'disabled',
			SOX_UPSAMPLE_ALL => 'Source < target rate',
			SOX_UPSAMPLE_ONLY_41K => 'Only 44.1K source rate',
			SOX_UPSAMPLE_ONLY_4148K => 'Only 44.1K and 48K source rates',
			SOX_ADHERE_BASE_FREQ => 'Resample (adhere to base freq)',
			(SOX_UPSAMPLE_ALL + SOX_ADHERE_BASE_FREQ) => 'Source < target rate (adhere to base freq)'
		);
		$_selective_resampling_hide = ''; // <!-- This is ment to control visibility of the feature in case MPD no longer supports the patch -->
		$_selective_resample = $resample_modes[$cfgMPD['selective_resample_mode']];
		$_resample_quality = $cfgMPD['sox_quality'];
		if ($cfgMPD['sox_quality'] == 'custom') {
			$_resample_quality .= ' [' .
			'p=' . $cfgMPD['sox_precision'] .
			' | r=' . $cfgMPD['sox_phase_response'] .
			' | e=' . $cfgMPD['sox_passband_end'] .
			' | b=' . $cfgMPD['sox_stopband_begin'] .
			' | a=' . $cfgMPD['sox_attenuation'] .
			' | f=' . $cfgMPD['sox_flags'] . ']';
		}
	}
	// Polarity inversion
	// NOTE: 'off' is a class that hides the element
	$_polarity_inv = $_SESSION['invert_polarity'] == '0' ? 'off' : 'On';
	// MPD Crossfade
	$_crossfade = $_SESSION['mpdcrossfade'] == '0' ? 'off' : $_SESSION['mpdcrossfade'] . ' seconds';
	// Crossfeed
	if ($_SESSION['crossfeed'] != 'Off') {
		$array = explode(' ', $_SESSION['crossfeed']);
		$_crossfeed = $array[0] . ' Hz ' . $array[1] . ' dB';
	} else {
		$_crossfeed = 'off';
	}
	// Equalizers
	$_peq = $_SESSION['eqfa12p'] == 'Off' ? 'off' : $_SESSION['eqfa12p'];
	$_geq = $_SESSION['alsaequal'] == 'Off' ? 'off' : $_SESSION['alsaequal'];
    $_camilladsp = getCamillaDspConfigName($_SESSION['camilladsp']);
	// Replaygain and volume normalization
	$_replaygain = $cfgMPD['replaygain'];
	$_vol_normalize = $cfgMPD['volume_normalization'] == 'no' ? 'off' : $cfgMPD['volume_normalization'];
}
// Chip options
$result = sqlRead('cfg_audiodev', $dbh, $_SESSION['i2sdevice']);
$array = explode(',', $result[0]['chipoptions']);

if (strpos($result[0]['dacchip'], 'PCM5') !== false || strpos($result[0]['dacchip'], 'TAS') !== false) {
	 // Analog gain, analog gain boost, digital interpolation filter
	$analogGain = $array[0] === '100' ? '0dB' : '-6dB';
	$analogBoost = $array[1] === '100' ? '.8dB' : '0dB';
	$digFilter = $array[2];
	$_chip_options = $digFilter . ', Gain=' . $analogGain . ', Boost=' . $analogBoost;
} else if ($_SESSION['i2sdevice'] == 'Allo Piano 2.1 Hi-Fi DAC') {
	// Get current settings
	$dualMode = sysCmd('/var/www/util/sysutil.sh get-piano-dualmode');
	$subMode = sysCmd('/var/www/util/sysutil.sh get-piano-submode');
	$subVol = sysCmd('/var/www/util/sysutil.sh get-piano-subvol');
	$lowPass = sysCmd('/var/www/util/sysutil.sh get-piano-lowpass');

	// Determine output mode
	if ($dualMode[0] != 'None') {
		$mode = $dualMode[0];
		$subVol = '';
		$lowPass = '';
	} else if ($subMode[0] == '2.0') {
		$mode = 'Stereo';
		$subVol = '';
		$lowPass = '';
	} else {
		$mode = 'Subwoofer' . $subMode[0];
		$subVol = 'Volume=' . $subVol;
		$lowPass = 'Lowpass=' . $lowPass;
	}

	$_chip_options = 'Mode=' . $mode . $subVol . $lowPass;
} else if ($_SESSION['i2sdevice'] == 'Allo Katana DAC') {
	// Oversampling filter, de-emphasis, DoP
	$katanaOsf = $array[0];
	$katanaDeemphasis = $array[1];
	$katanaDop = $array[2];
	$_chip_options = $katanaOsf . ', De-emphasis=' . $katanaDeemphasis . ', DoP=' . $katanaDop;

} else if ($_SESSION['i2sdevice'] == 'Audiophonics ES9028/9038 DAC') {
	$audiophonicsQ2mOsf = $array[0];
	$audiophonicsQ2mInput = $array[1];
	$_chip_options = 'Filter=' . $audiophonicsQ2mOsf . ', Input=' . $audiophonicsQ2mInput;
} else if ($_SESSION['i2sdevice'] == 'IanCanada (MonitorPi Pro with ESS DAC)') {
	$iancanadaQ2mOsf = $array[0];
	$_chip_options = 'Filter=' . $iancanadaQ2mOsf;
} else {
	$_chip_options = 'None';
}

//
// AUDIO DEVICE
//

$result = sqlQuery("SELECT * FROM cfg_audiodev WHERE name='" . $_SESSION['adevname'] . "' or alt_name='" . $_SESSION['adevname'] . "'", $dbh);
if ($result === true) { // Not in table
	$_chip_hide = 'hide';
	$_iface = $_SESSION['i2soverlay'] == 'None' ? 'USB' : 'I2S';
} else {
	$_chip_hide = '';
	$_dacchip = $result[0]['dacchip'];
	$_iface = $result[0]['iface'];
}
$_devname = $_SESSION['adevname'];
$_mixer_name = $_SESSION['amixname'] == 'none' ? 'n/a' : $_SESSION['amixname'];
$_audio_formats = $_SESSION['audio_formats'];
$_hdwr_rev = $_SESSION['hdwrrev'];

closeMpdSock($sock);
$tpl = 'audioinfo.html';
eval('echoTemplate("' . getTemplate("templates/$tpl") . '");');

function getCamillaDspConfigName($config) {
	$cdsp = new CamillaDsp($_SESSION['camilladsp'], $_SESSION['cardnum'], $_SESSION['camilladsp_quickconv']);
	$configs = $cdsp->getAvailableConfigs( True);
	$configLabel = $config;
	if(array_key_exists($config, $configs) ) {
		$configLabel = $configs[$config];
	}

	return $configLabel;
}

function getCfgMpd($dbh) {
	$result = sqlRead('cfg_mpd', $dbh);
	$array = array();

	foreach ($result as $row) {
		$array[$row['param']] = $row['value'];
	}

	// SoX resampling
	// Ex 44100:16:2 or disabled
	if ($array['audio_output_format'] == 'disabled') {
	 	$array['audio_output_rate'] = '';
	 	$array['audio_output_depth'] = '';
	 	$array['audio_output_channels'] = '';
	} else {
	 	$format = explode(":", $array['audio_output_format']);
	 	$array['audio_output_rate'] = formatRate($format[0]);
	 	$array['audio_output_depth'] = $format[1];
	 	$array['audio_output_channels'] = formatChannels($format[2]);
	}

	return $array;
}
