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
 * 2017-11-11 TC moOde 4.0
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

// mpd.conf
$mpdconf = parseCfgMpd($dbh);

// bluetooth session
$btAplay = sysCmd('pgrep bluealsa-aplay');

// input
if ($_SESSION['airplayactv'] == '1') {
	$file = 'Airplay stream';
	$encoded_at = 'Unknown';
	$decoded_to = '16 bit, 44.1 kHz, Stereo';
	$decode_rate = 'VBR';
}
elseif ($btAplay[0] != '') {
	$file = 'Bluetooth stream';
	$encoded_at = 'Unknown';
	$decoded_to = '16 bit, 44.1 kHz, Stereo';
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
	
	if ($hwparams['status'] == 'active') { 		
		// dsd
		if ($status['audio_sample_depth'] == 'dsd64') {
			$encoded_at = 'DSD64, 1 bit, 2.822 mbps Stereo';
			if ($mpdconf['dop'] == 'yes') {
				$decoded_to = 'DoP 24 bit 176.4 kHz, Stereo';
				$decode_rate = '8,467 mbps';
			}
			elseif ($hwparams['format'] == 'DSD bitstream') {
				$decoded_to = 'DSD bitstream';
				$decode_rate = $hwparams['calcrate'] . ' mbps';
			}
			else {
				$decoded_to = 'PCM, ' . $hwparams['format'] . ' bit, ' . $hwparams['rate'] . ' kHz, ' . $hwparams['channels'];
				$decode_rate = $hwparams['calcrate'] . ' mbps';
			}
		}
		else if ($status['audio_sample_depth'] == 'dsd128') {
			$encoded_at = 'DSD128, 1 bit, 5.644 msps Stereo';
			if ($mpdconf['dop'] == 'yes') {
				$decoded_to = 'DoP 24 bit 352.8 kHz, Stereo';
				$decode_rate = '16.934 mbps';
			}
			elseif ($hwparams['format'] == 'DSD bitstream') {
				$decoded_to = 'DSD bitstream';
				$decode_rate = $hwparams['calcrate'] . ' mbps';
			}
			else {
				$decoded_to = 'PCM, ' . $hwparams['format'] . ' bit, ' . $hwparams['rate'] . ' kHz, ' . $hwparams['channels'];
				$decode_rate = $hwparams['calcrate'] . ' mbps';
			}
		}
		else if ($status['audio_sample_depth'] == 'dsd256') {
			$encoded_at = 'DSD256, 1 bit, 11.288 msps Stereo';
			if ($mpdconf['dop'] == 'yes') {
				$decoded_to = 'DoP 24 bit 705.6 kHz, Stereo';
				$decode_rate = '33.868 mbps';
			}
			elseif ($hwparams['format'] == 'DSD bitstream') {
				$decoded_to = 'DSD bitstream';
				$decode_rate = $hwparams['calcrate'] . ' mbps';
			}
			else {
				$decoded_to = 'PCM, ' . $hwparams['format'] . ' bit, ' . $hwparams['rate'] . ' kHz, ' . $hwparams['channels'];
				$decode_rate = $hwparams['calcrate'] . ' mbps';
			}
		}
		else if ($status['audio_sample_depth'] == 'dsd512') {
			$encoded_at = 'DSD512, 1 bit, 22.576 msps Stereo';
			if ($mpdconf['dop'] == 'yes') {
				$decoded_to = 'DoP 24 bit 1.411 MHz, Stereo';
				$decode_rate = '67.736 mbps';
			}
			elseif ($hwparams['format'] == 'DSD bitstream') {
				$decoded_to = 'DSD bitstream';
				$decode_rate = $hwparams['calcrate'] . ' mbps';
			}
			else {
				$decoded_to = 'PCM, ' . $hwparams['format'] . ' bit, ' . $hwparams['rate'] . ' kHz, ' . $hwparams['channels'];
				$decode_rate = $hwparams['calcrate'] . ' mbps';
			}
		}

		// pcm
		else {
			$decoded_to = $status['audio_sample_depth'] . ' bit, ' . $status['audio_sample_rate'];
			$decoded_to .= empty($status['audio_sample_rate']) ? '' : ' kHz, ' . $status['audio_channels'];
			$decode_rate = $status['bitrate'];
		}
	}
	else {
		$decoded_to = '';
		$decode_rate = '0 bps';
	}
}

// dsp
if ($mpdconf['audio_output_format'] == 'disabled' || $_SESSION['airplayactv'] == '1' || $_SESSION['slsvc'] == '1') {
	$resampler = 'off';
	$resampler_format = '';
}
else {
	$resampler_format = $mpdconf['audio_output_depth'] . ' bit, ' . $mpdconf['audio_output_rate'] . ' kHz, ' . $mpdconf['audio_output_chan'];
	$resampler = ' (SoX ' . $mpdconf['samplerate_converter'] . ' quality)';
}

if ($_SESSION['crossfeed'] != 'Off') {
	$array = explode(' ', $_SESSION['crossfeed']);
	$crossfeed = $array[0] . ' Hz ' . $array[1] . ' dB';
}
else {
	$crossfeed = 'off';
}

$geq = $_SESSION['alsaequal'] == 'Off' ? 'off' : $_SESSION['alsaequal'];
$peq = $_SESSION['eqfa4p'] == 'Off' ? 'off' : $_SESSION['eqfa4p'];
$equalizer = 'Graphic EQ: (' . $geq . '), Parametric EQ: (' . $peq . '}';

// chip options
$result = cfgdb_read('cfg_audiodev', $dbh, $_SESSION['i2sdevice']);
$chips = array('Burr Brown PCM5242','Burr Brown PCM5142','Burr Brown PCM5122','Burr Brown PCM5121','Burr Brown PCM5122 (PCM5121)','Burr Brown TAS5756');
if (in_array($result[0]['dacchip'], $chips) && $result[0]['chipoptions'] != '') {
	$array = explode(',', $result[0]['chipoptions']);

	$analoggain = $array[0] === '100' ? '0 dB' : '-6 dB'; // Analog gain
	$analogboost = $array[1] === '100' ? '.8 dB' : '0 dB'; // Analog gain boost
	$digfilter = $array[2]; // Digital interpolation filter

	$chip_options = $digfilter . ', gain=' . $analoggain . ', boost=' . $analogboost;
}
else {
	$chip_options = 'none';
}

// volume
if ($_SESSION['mpdmixer'] == 'hardware') {
	$volume = 'Hardware (On-chip volume controller)';
}
else if ($_SESSION['mpdmixer'] == 'software') {
	$volume = 'Software (MPD 32-bit float with dither)';
}
else {
	$volume = 'Disabled (100% volume level is output by MPD)';
}

// output
if ($hwparams['status'] == 'active') {
	$hwparams_format = $hwparams['format'] . ' bit, ' . $hwparams['rate'] . ' kHz, ' . $hwparams['channels'];
	$hwparams_calcrate = $hwparams['calcrate'] . ' mbps';
}
else {
	$hwparams_format = '';
	$hwparams_calcrate = '0 bps';
}

// audio device
$result = cfgdb_read('cfg_audiodev', $dbh, $_SESSION['adevname']);
$devname = $_SESSION['adevname'] == 'none' ? '' : $_SESSION['adevname'];
$dacchip = $result[0]['dacchip'];
$devarch = $result[0]['arch'];
$iface = $result[0]['iface'];

// system info (displayed in modal footer)
$cpuload = shell_exec("top -bn 2 -d 0.5 | grep 'Cpu(s)' | tail -n 1 | awk '{print $2 + $4 + $6}'");
$cpuload = number_format($cpuload,0,'.','');
$cputemp = substr(shell_exec('cat /sys/class/thermal/thermal_zone0/temp'), 0, 2);
$cpufreq = (float)shell_exec('cat /sys/devices/system/cpu/cpu0/cpufreq/scaling_cur_freq');

if ($cpufreq < 1000000) {
	$cpufreq = number_format($cpufreq / 1000, 0, '.', '');
	$cpufreq .= ' MHz';
}
else {
	$cpufreq = number_format($cpufreq / 1000000, 1, '.', '');
	$cpufreq .= ' GHz';
}

$result = shell_exec('grep -c ^processor /proc/cpuinfo');
$cores = $result > 1 ? $result . ' cores' : $result . ' core';
$sysarch = trim(shell_exec('uname -m'));

if ($_SESSION['kernel'] == 'Advanced-RT') {
	$kerneltype = 'Real-Time';
}
else if ($_SESSION['kernel'] == 'Advanced-LL') {
	$kerneltype = 'Low Latency';
}
else if ($_SESSION['kernel'] == 'Standard') {
	$kerneltype = 'Standard';
}
$kerneltype = $kerneltype . ' ' . $_SESSION['kernelver'];

$tpl = 'audioinfo.html';
eval('echoTemplate("' . getTemplate("templates/$tpl") . '");');
