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
 * 2020-07-19 TC moOde 6.7.0
 *
 */

require_once dirname(__FILE__) . '/inc/playerlib.php';

if (false === ($sock = openMpdSock('localhost', 6600))) {
	$msg = 'audioinfo: Connection to MPD failed';
	workerLog($msg);
	exit($msg . "\n");
}
else {
	playerSession('open', '' ,'');
	$dbh = cfgdb_connect();
	session_write_close();
}

// hardware params
$hwparams = parseHwParams(shell_exec('cat /proc/asound/card' . $_SESSION['cardnum'] . '/pcm0p/sub0/hw_params'));

// cfg_mpd settings
$cfg_mpd = parseCfgMpd($dbh);

// bluetooth
$result = sysCmd('pgrep -l bluealsa-aplay');
$btactive = strpos($result[0], 'bluealsa-aplay') !== false ? true : false;

//
// IMPUT
//

if ($_SESSION['airplayactv'] == '1') {
	$file = 'Airplay stream';
	$encoded_at = 'Unknown';
	$decoded_to = '16 bit, 44.1 kHz, Stereo, ';
	$decode_rate = 'VBR';
}
elseif ($_SESSION['spotactive'] == '1') {
	$file = 'Spotify stream';
	$encoded_at = 'Unknown';
	$decoded_to = '16 bit, 44.1 kHz, Stereo, ';
	$decode_rate = 'VBR';
}
elseif ($_SESSION['slactive'] == '1') {
	$file = 'Squeezelite stream';
	$encoded_at = 'Unknown';
	$decoded_to = 'Unknown, ';
	$decode_rate = 'VBR';
}
elseif ($btactive === true && $_SESSION['audioout'] == 'Local') {
	$file = 'Bluetooth stream';
	$encoded_at = 'Unknown';
	$decoded_to = '16 bit, 44.1 kHz, Stereo, ';
	$decode_rate = 'VBR';
}
else {
	$song = parseCurrentSong($sock);
	$file = $song['file'];

	// Krishna Simonese: if current file is a UPNP/DLNA url, replace *20 with space
	// NOTE normaly URI would be encoded %20 but ? is changing it to *20
	if ( substr( $file, 0, 4) == 'http' ) {
		$file = str_replace( '*20', ' ', $file );
	}

	$encoded_at = getEncodedAt($song, 'verbose');
	$status = parseStatus(getMpdStatus($sock));

	if ($hwparams['status'] == 'active' || $_SESSION['audioout'] == 'Bluetooth') {
		// DSD: DoP, Native bitstream
		if ($status['audio_sample_depth'] == 'dsd64') {
			$encoded_at = 'DSD64, 1 bit, 2.822 Mbps Stereo';
			if ($cfg_mpd['dop'] == 'yes') {
				$decoded_to = 'DoP 24 bit 176.4 kHz, Stereo';
				$decode_rate = '8.467 Mbps';
			}
			elseif ($hwparams['format'] == 'DSD bitstream') {
				$decoded_to = 'DSD bitstream';
				$decode_rate = $hwparams['calcrate'] . ' Mbps';
			}
			else {
				$decoded_to = 'PCM, ' . $hwparams['format'] . ' bit, ' . $hwparams['rate'] . ' kHz, ' . $hwparams['channels'];
				$decode_rate = $hwparams['calcrate'] . ' Mbps';
			}
		}
		else if ($status['audio_sample_depth'] == 'dsd128') {
			$encoded_at = 'DSD128, 1 bit, 5.644 Mbps Stereo';
			if ($cfg_mpd['dop'] == 'yes') {
				$decoded_to = 'DoP 24 bit 352.8 kHz, Stereo';
				$decode_rate = '16.934 Mbps';
			}
			elseif ($hwparams['format'] == 'DSD bitstream') {
				$decoded_to = 'DSD bitstream';
				$decode_rate = $hwparams['calcrate'] . ' Mbps';
			}
			else {
				$decoded_to = 'PCM, ' . $hwparams['format'] . ' bit, ' . $hwparams['rate'] . ' kHz, ' . $hwparams['channels'];
				$decode_rate = $hwparams['calcrate'] . ' Mbps';
			}
		}
		else if ($status['audio_sample_depth'] == 'dsd256') {
			$encoded_at = 'DSD256, 1 bit, 11.288 Mbps Stereo';
			if ($cfg_mpd['dop'] == 'yes') {
				$decoded_to = 'DoP 24 bit 705.6 kHz, Stereo';
				$decode_rate = '33.868 Mbps';
			}
			elseif ($hwparams['format'] == 'DSD bitstream') {
				$decoded_to = 'DSD bitstream';
				$decode_rate = $hwparams['calcrate'] . ' Mbps';
			}
			else {
				$decoded_to = 'PCM, ' . $hwparams['format'] . ' bit, ' . $hwparams['rate'] . ' kHz, ' . $hwparams['channels'];
				$decode_rate = $hwparams['calcrate'] . ' Mbps';
			}
		}
		else if ($status['audio_sample_depth'] == 'dsd512') {
			$encoded_at = 'DSD512, 1 bit, 22.576 Mbps Stereo';
			if ($cfg_mpd['dop'] == 'yes') {
				$decoded_to = 'DoP 24 bit 1.411 MHz, Stereo';
				$decode_rate = '67.736 Mbps';
			}
			elseif ($hwparams['format'] == 'DSD bitstream') {
				$decoded_to = 'DSD bitstream';
				$decode_rate = $hwparams['calcrate'] . ' Mbps';
			}
			else {
				$decoded_to = 'PCM, ' . $hwparams['format'] . ' bit, ' . $hwparams['rate'] . ' kHz, ' . $hwparams['channels'];
				$decode_rate = $hwparams['calcrate'] . ' Mbps';
			}
		}
		else if ($status['audio_sample_depth'] == 'dsd1024') {
			$encoded_at = 'DSD1024, 1 bit, 90.304 Mbps Stereo';
			if ($cfg_mpd['dop'] == 'yes') {
				$decoded_to = 'DoP 24 bit 2.822 MHz, Stereo';
				$decode_rate = '135.472 Mbps';
			}
			elseif ($hwparams['format'] == 'DSD bitstream') {
				$decoded_to = 'DSD bitstream';
				$decode_rate = $hwparams['calcrate'] . ' Mbps';
			}
			else {
				$decoded_to = 'PCM, ' . $hwparams['format'] . ' bit, ' . $hwparams['rate'] . ' kHz, ' . $hwparams['channels'];
				$decode_rate = $hwparams['calcrate'] . ' Mbps';
			}
		}

		// PCM
		else {
			$decoded_to = $status['audio_sample_depth'] . ' bit, ' . $status['audio_sample_rate'];
			$decoded_to .= empty($status['audio_sample_rate']) ? '' : ' kHz, ' . $status['audio_channels'];
			$decode_rate = $status['bitrate'];
		}

		$decode_rate = ', ' . $decode_rate;
	}
	else {
		$decoded_to = '';
		$decode_rate = '0 bps';
	}
}

//
// OUTPUT
//

$output_destination = $_SESSION['audioout'];
if ($_SESSION['audioout'] == 'Bluetooth') {
	$hwparams_format = '16 bit, 44.1 kHz, Stereo, ';
	$hwparams_calcrate = '1.411 Mbps';
}
elseif ($hwparams['status'] == 'active') {
	$hwparams_format = $hwparams['format'] . ' bit, ' . $hwparams['rate'] . ' kHz, ' . $hwparams['channels'];
	$hwparams_calcrate = ', ' . $hwparams['calcrate'] . ' Mbps';
}
else {
	$hwparams_format = '';
	$hwparams_calcrate = '0 bps';
}

//
// DSP
//

// Volume control
if ($_SESSION['mpdmixer'] == 'hardware') {
	$volume_ctl = 'Hardware (on-chip volume controller)';
}
elseif ($_SESSION['mpdmixer'] == 'software') {
	$volume_ctl = 'Software (MPD 32-bit float with dither)';
}
else {
	$volume_ctl = 'Disabled (100% volume is output by MPD)';
}

// Renderers
if ($_SESSION['airplayactv'] == '1' || $_SESSION['spotactive'] == '1' || $_SESSION['slactive'] == '1' || $_SESSION['inpactive'] == '1' || $btactive === true) {
	$resampler_format = '';
	$resampler_quality = 'n/a';
	$polarity_inv = 'n/a';
	$crossfade = 'n/a';
	$crossfeed = 'n/a';
	$replaygain = 'n/a';
	$vol_normalize = 'n/a';

	if ($_SESSION['airplayactv'] == '1' || $_SESSION['spotactive'] == '1') {
		$peq = $_SESSION['eqfa4p'] == 'Off' ? 'off' : $_SESSION['eqfa4p'];
		$geq = $_SESSION['alsaequal'] == 'Off' ? 'off' : $_SESSION['alsaequal'];
	}
	else {
		$peq = 'n/a';
		$geq = 'n/a';
	}
}
// MPD
else {
	// Resampling
	if ($cfg_mpd['audio_output_format'] == 'disabled') {
		$resampler_format = '';
		$resampler_quality = 'off';
	}
	else {
		$resampler_format = $cfg_mpd['audio_output_depth'] . ' bit, ' . $cfg_mpd['audio_output_rate'] . ' kHz, ' . $cfg_mpd['audio_output_chan'];
		$resampler_quality = ' (SoX ' . $cfg_mpd['samplerate_converter'] . ' quality)';
	}
	// Polarity inversion
	$polarity_inv = $_SESSION['invert_polarity'] == '0' ? 'off' : 'on';
	// MPD Crossfade
	$crossfade = $_SESSION['mpdcrossfade'] == '0' ? 'off' : $_SESSION['mpdcrossfade'] . ' seconds';
	// Crossfeed
	if ($_SESSION['crossfeed'] != 'Off') {
		$array = explode(' ', $_SESSION['crossfeed']);
		$crossfeed = $array[0] . ' Hz ' . $array[1] . ' dB';
	}
	else {
		$crossfeed = 'off';
	}
	// Equalizers
	$peq = $_SESSION['eqfa4p'] == 'Off' ? 'off' : $_SESSION['eqfa4p'];
	$geq = $_SESSION['alsaequal'] == 'Off' ? 'off' : $_SESSION['alsaequal'];
	// Replaygain and volume normalization
	$replaygain = $cfg_mpd['replaygain'];
	$vol_normalize = $cfg_mpd['volume_normalization'] == 'no' ? 'off' : $cfg_mpd['volume_normalization'];
}
// chip options
$result = cfgdb_read('cfg_audiodev', $dbh, $_SESSION['i2sdevice']);
$array = explode(',', $result[0]['chipoptions']);

if (strpos($result[0]['dacchip'], 'PCM5') !== false || strpos($result[0]['dacchip'], 'TAS') !== false) {
	 // Analog gain, analog gain boost, digital interpolation filter
	$analoggain = $array[0] === '100' ? '0dB' : '-6dB';
	$analogboost = $array[1] === '100' ? '.8dB' : '0dB';
	$digfilter = $array[2];
	$chip_options = $digfilter . ', Gain=' . $analoggain . ', Boost=' . $analogboost;
}
elseif ($_SESSION['i2sdevice'] == 'Allo Piano 2.1 Hi-Fi DAC') {
	// get current settings
	$dualmode = sysCmd('/var/www/command/util.sh get-piano-dualmode');
	$submode = sysCmd('/var/www/command/util.sh get-piano-submode');
	$subvol = sysCmd('/var/www/command/util.sh get-piano-subvol');
	$lowpass = sysCmd('/var/www/command/util.sh get-piano-lowpass');

	// determine output mode
	if ($dualmode[0] != 'None') {
		$outputmode = $dualmode[0];
		$sub_vol = '';
		$low_pass = '';
	}
	elseif ($submode[0] == '2.0') {
		$outputmode = 'Stereo';
		$sub_vol = '';
		$low_pass = '';
	}
	else {
		$outputmode = 'Subwoofer' . $submode[0];
		$sub_vol = 'Volume=' . $subvol;
		$low_pass = 'Lowpass=' . $lowpass;
	}

	$chip_options = 'Mode=' . $outputmode . $sub_vol . $low_pass;
}
elseif ($_SESSION['i2sdevice'] == 'Allo Katana DAC') {
	// Oversampling filter, de-emphasis, DoP
	$katana_osf = $array[0];
	$katana_deemphasis = $array[1];
	$katana_dop = $array[2];
	$chip_options = $katana_osf . ', De-emphasis=' . $katana_deemphasis . ', DoP=' . $katana_dop;

}
elseif ($_SESSION['i2sdevice'] == 'Audiophonics ES9028/9038 DAC' || $_SESSION['i2sdevice'] == 'Audiophonics ES9028/9038 DAC (Pre 2019)') {
	$audiophonics_q2m_osf = $array[0];
	$audiophonics_q2m_input = $array[1];
	$chip_options = 'Filter=' . $audiophonics_q2m_osf . ', Input=' . $audiophonics_q2m_input;
}
else {
	$chip_options = 'None';
}

//
// AUDIO DEVICE
//

$result = cfgdb_read('cfg_audiodev', $dbh, $_SESSION['adevname']);
// Not in table implies USB audio device
if ($result === true) {
	$devname = 'USB audio device (' . $_SESSION['adevname'] . ')';
	$dacchip = '';
	$iface = 'USB';
}
else {
	$devname = $_SESSION['adevname'];
	$dacchip = $result[0]['dacchip'];
	$iface = $result[0]['iface'];
}
$mixer_name = $_SESSION['amixname'];
$audio_formats = $_SESSION['audio_formats'];
$hdwr_rev = $_SESSION['hdwrrev'];

$tpl = 'audioinfo.html';
eval('echoTemplate("' . getTemplate("templates/$tpl") . '");');
