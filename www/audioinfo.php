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
require_once __DIR__ . '/inc/alsa.php';
require_once __DIR__ . '/inc/cdsp.php';
require_once __DIR__ . '/inc/mpd.php';
require_once __DIR__ . '/inc/music-library.php';
require_once __DIR__ . '/inc/session.php';
require_once __DIR__ . '/inc/sql.php';

$sock = getMpdSock();
$dbh = sqlConnect();
phpSession('open_ro');

// Hardware params
$cardNum = $_SESSION['multiroom_tx'] == 'On' ? array_search('Dummy', getAlsaCards()) : $_SESSION['cardnum'];
$hwParams = getAlsaHwParams($cardNum);

// Cfg_mpd settings
$cfgMPD = getCfgMpd($dbh);

// Bluetooth active
$result = sysCmd('pgrep -l bluealsa-aplay');
$btActive = strpos($result[0], 'bluealsa-aplay') !== false ? true : false;

//
// INPUT
//

if ($_SESSION['aplactive'] == '1') {
	$_file = 'AirPlay stream';
	$_encoded_at = 'Unknown';
	$_decoded_to = '16 bit, 44.1 kHz, Stereo, ';
	$_decode_rate = 'VBR';
} else if ($_SESSION['spotactive'] == '1') {
	$_file = 'Spotify stream';
	$_encoded_at = 'Unknown';
	$_decoded_to = '16 bit, 44.1 kHz, Stereo, ';
	$_decode_rate = 'VBR';
} else if ($_SESSION['slactive'] == '1') {
	$_file = 'Squeezelite stream';
	$_encoded_at = 'Unknown';
	$_decoded_to = 'Unknown, ';
	$_decode_rate = 'VBR';
} else if ($_SESSION['rbactive'] == '1') {
	$_file = 'RoonBridge stream';
	$_encoded_at = 'Unknown';
	$_decoded_to = 'Unknown, ';
	$_decode_rate = 'VBR';
} else if ($btActive === true && $_SESSION['audioout'] == 'Local') {
	$_file = 'Bluetooth stream';
	$_encoded_at = 'Unknown';
	$_decoded_to = '16 bit, 44.1 kHz, Stereo, ';
	$_decode_rate = 'VBR';
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

	if ($hwParams['status'] == 'active' || ($_SESSION['audioout'] == 'Bluetooth' && $mpdStatus['state'] == 'play')) {
		// DSD: Native bitstream, DoP or DSD to PMC on-the-fly conversion
		if ($mpdStatus['audio_sample_depth'] == 'dsd64') {
			$_encoded_at = 'DSD64, 1 bit, 2.822 MHz Stereo';
			if ($cfgMPD['dop'] == 'yes') {
				$_decoded_to = 'DoP 24 bit 176.4 kHz, Stereo';
				$_decode_rate = '8.467 Mbps';
			} else if ($hwParams['format'] == 'DSD bitstream') {
				$_decoded_to = 'DSD bitstream';
				$_decode_rate = $hwParams['calcrate'] . ' Mbps';
			} else {
				$_decoded_to = 'PCM, ' . $hwParams['format'] . ' bit, ' . $hwParams['rate'] . ' kHz, ' . $hwParams['channels'];
				$_decode_rate = $hwParams['calcrate'] . ' Mbps';
			}
		} else if ($mpdStatus['audio_sample_depth'] == 'dsd128') {
			$_encoded_at = 'DSD128, 1 bit, 5.644 MHz Stereo';
			if ($cfgMPD['dop'] == 'yes') {
				$_decoded_to = 'DoP 24 bit 352.8 kHz, Stereo';
				$_decode_rate = '16.934 Mbps';
			} else if ($hwParams['format'] == 'DSD bitstream') {
				$_decoded_to = 'DSD bitstream';
				$_decode_rate = $hwParams['calcrate'] . ' Mbps';
			} else {
				$_decoded_to = 'PCM, ' . $hwParams['format'] . ' bit, ' . $hwParams['rate'] . ' kHz, ' . $hwParams['channels'];
				$_decode_rate = $hwParams['calcrate'] . ' Mbps';
			}
		} else if ($mpdStatus['audio_sample_depth'] == 'dsd256') {
			$_encoded_at = 'DSD256, 1 bit, 11.288 MHz Stereo';
			if ($cfgMPD['dop'] == 'yes') {
				$_decoded_to = 'DoP 24 bit 705.6 kHz, Stereo';
				$_decode_rate = '33.868 Mbps';
			} else if ($hwParams['format'] == 'DSD bitstream') {
				$_decoded_to = 'DSD bitstream';
				$_decode_rate = $hwParams['calcrate'] . ' Mbps';
			} else {
				$_decoded_to = 'PCM, ' . $hwParams['format'] . ' bit, ' . $hwParams['rate'] . ' kHz, ' . $hwParams['channels'];
				$_decode_rate = $hwParams['calcrate'] . ' Mbps';
			}
		} else if ($mpdStatus['audio_sample_depth'] == 'dsd512') {
			$_encoded_at = 'DSD512, 1 bit, 22.576 MHz Stereo';
			if ($cfgMPD['dop'] == 'yes') {
				$_decoded_to = 'DoP 24 bit 1.411 MHz, Stereo';
				$_decode_rate = '67.736 Mbps';
			} else if ($hwParams['format'] == 'DSD bitstream') {
				$_decoded_to = 'DSD bitstream';
				$_decode_rate = $hwParams['calcrate'] . ' Mbps';
			} else {
				$_decoded_to = 'PCM, ' . $hwParams['format'] . ' bit, ' . $hwParams['rate'] . ' kHz, ' . $hwParams['channels'];
				$_decode_rate = $hwParams['calcrate'] . ' Mbps';
			}
		}
		else if ($mpdStatus['audio_sample_depth'] == 'dsd1024') {
			$_encoded_at = 'DSD1024, 1 bit, 45.152 Mbps Stereo';
			if ($cfgMPD['dop'] == 'yes') {
				$_decoded_to = 'DoP 24 bit 2.822 MHz, Stereo';
				$_decode_rate = '135.472 Mbps';
			}
			elseif ($hwParams['format'] == 'DSD bitstream') {
				$_decoded_to = 'DSD bitstream';
				$_decode_rate = $hwParams['calcrate'] . ' Mbps';
			}
			else {
				$_decoded_to = 'PCM, ' . $hwParams['format'] . ' bit, ' . $hwParams['rate'] . ' kHz, ' . $hwParams['channels'];
				$_decode_rate = $hwParams['calcrate'] . ' Mbps';
			}
		} else {
			// PCM
			$_decoded_to = $mpdStatus['audio_sample_depth'] . ' bit, ' . $mpdStatus['audio_sample_rate'];
			$_decoded_to .= empty($mpdStatus['audio_sample_rate']) ? '' : ' kHz, ' . $mpdStatus['audio_channels'];
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

if ($_SESSION['audioout'] == 'Bluetooth' && $mpdStatus['state'] == 'play') {
	$_hwparams_format = '16 bit, 44.1 kHz, Stereo, ';
	$_hwparams_calcrate = '1.411 Mbps';
} else if ($hwParams['status'] == 'active') {
	$pcmRate = $hwParams['format'] == 'DSD bitstream' ?  '' : ' bit, ' . $hwParams['rate'] . ' kHz, ' . $hwParams['channels'];
	$_hwparams_format = $hwParams['format'] . $pcmRate;
	$_hwparams_calcrate = ', ' . $hwParams['calcrate'] . ' Mbps';
} else {
	$_hwparams_format = '';
	$_hwparams_calcrate = 'Not playing';
}
// Output chain
// Renderer
if ($_SESSION['slactive'] == '1') {
	$renderer = 'Squeezelite';
} else if ($_SESSION['rbactive'] == '1') {
	$renderer = 'Roonbridge';
} else if ($_SESSION['aplactive'] == '1') {
	$renderer = 'AirPlay';
} else if ($_SESSION['spotactive'] == '1') {
	$renderer = 'Spotify';
} else if ($btActive === true) {
	$renderer = 'Bluetooth';
} else {
	$renderer = 'MPD';
}
// DSP and output mode
if ($_SESSION['invert_polarity'] == '1') {
	$dsp = 'Invpolarity';
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
// Combine parts
if ($_SESSION['audioout'] == 'Bluetooth') {
	$_alsa_output_chain = 'MPD -> Bluetooth stream -> Bluetooth speaker';
} else if ($_SESSION['multiroom_tx'] == 'On') {
	$_alsa_output_chain = $renderer . ' -> Multiroom Sender';
} else if ($_SESSION['multiroom_rx'] == 'On') {
	$_alsa_output_chain = 'Multiroom Receiver -> Device';
} else if ($renderer == 'Squeezelite' || $renderer == 'Roonbridge') {
	$_alsa_output_chain = $renderer;
} else if ($dsp != '') {
	$_alsa_output_chain = $renderer . ' -> ' . $dsp . ' -> ' . $outputMode . ' -> Device';
} else {
	$_alsa_output_chain = $renderer . ' -> ' . $outputMode . ' -> Device';
}

// ALSA Output mode and Loopback
$_alsa_output_mode = $_SESSION['multiroom_tx'] == 'On' ? 'Loopback (hw)' : ($_SESSION['alsa_output_mode'] == 'plughw' ? 'Default (plughw)' : 'Direct (hw)');
$_alsa_loopback = $_SESSION['alsa_loopback'] == 'Off' ? 'off' : $_SESSION['alsa_loopback']; // NOTE: 'off' is a class that hides the element

//
// DSP
//

// Volume type
if ($_SESSION['mpdmixer'] == 'hardware') {
	$_volume_mixer = 'Hardware (On-chip)';
}
elseif ($_SESSION['mpdmixer'] == 'software') {
	$_volume_mixer = 'Software (MPD)';
}
elseif ($_SESSION['mpdmixer'] == 'none') {
	$_volume_mixer = 'Fixed (0dB output)';
}
elseif ($_SESSION['mpdmixer'] == 'null') {
	$_volume_mixer = 'Null (External control)';
}
else {
	$_volume_mixer = 'ERROR: Unknow MPD volume type';
}

if ($_SESSION['aplactive'] == '1' || $_SESSION['spotactive'] == '1' || $_SESSION['slactive'] == '1' ||
	$_SESSION['inpactive'] == '1' || $_SESSION['rbactive'] == '1' || $btActive === true || $_SESSION['audioout'] == 'Bluetooth') {
	// Renderer active
	// NOTE: Class 'off' hides the item
	$_resample_rate = '';
	$_resample_quality = 'off';
	$_polarity_inv = 'off';
	$_crossfade = 'off';
	$_crossfeed = 'off';
	$_replaygain = 'off';
	$_vol_normalize = 'off';

	if ($_SESSION['aplactive'] == '1' || $_SESSION['spotactive'] == '1') {
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
		$_resample_rate = 'Off';
		$_selective_resample = 'Off';
		$_resample_quality = 'Off';
	} else {
		$_resample_rate = $cfgMPD['audio_output_depth'] . ' bit, ' . $cfgMPD['audio_output_rate'] . ' kHz, ' . $cfgMPD['audio_output_chan'];
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

} else if ($_SESSION['i2sdevice'] == 'Audiophonics ES9028/9038 DAC' || $_SESSION['i2sdevice'] == 'Audiophonics ES9028/9038 DAC (Pre 2019)') {
	$audiophonicsQ2mOsf = $array[0];
	$audiophonicsQ2mInput = $array[1];
	$_chip_options = 'Filter=' . $audiophonicsQ2mOsf . ', Input=' . $audiophonicsQ2mInput;
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
$_mixer_name = $_SESSION['amixname'];
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

	// Ex 44100:16:2 or disabled
	if ($array['audio_output_format'] == 'disabled') {
	 	$array['audio_output_rate'] = '';
	 	$array['audio_output_depth'] = '';
	 	$array['audio_output_chan'] = '';
	} else {
	 	$format = explode(":", $array['audio_output_format']);
	 	$array['audio_output_rate'] = formatRate($format[0]);
	 	$array['audio_output_depth'] = $format[1];
	 	$array['audio_output_chan'] = formatChannels($format[2]);
	}

	return $array;
}
