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

require_once __DIR__ . '/common.php';
require_once __DIR__ . '/cdsp.php';
require_once __DIR__ . '/mpd.php';
require_once __DIR__ . '/sql.php';

function getAlsaMixerName($i2sDevice) {
	if ($i2sDevice == 'None' && $_SESSION['i2soverlay'] == 'None') {
		// USB devices, Pi HDMI-1/2 or Headphone jack
		$result = sysCmd('/var/www/util/sysutil.sh get-mixername');
		if ($result[0] == '') {
			// Mixer name not found
			$mixerName = 'none';
		} else {
			// Mixer name found, strip off delimiters added by sysutil.sh get-mixername
			$mixerName = ltrim($result[0], '(');
			$mixerName = rtrim($mixerName, ')');
		}
	} else {
		// I2S devices
		if ($i2sDevice == 'HiFiBerry Amp(Amp+)') {
			$mixerName = 'Channels';
		} else if ($i2sDevice == 'HiFiBerry DAC+ DSP') {
			$mixerName = 'DSPVolume';
		} else if ($_SESSION['i2soverlay'] == 'hifiberry-dacplushd') {
			$mixerName = 'DAC';
		} else if ($i2sDevice == 'MERUS(tm) Amp piHAT ZW') {
			$mixerName = 'A.Mstr Vol';
		} else if (
			$i2sDevice == 'Allo Katana DAC' ||
			$i2sDevice == 'Allo Boss 2 DAC' ||
			$i2sDevice == 'Allo Piano 2.1 Hi-Fi DAC') {
			$mixerName = 'Master';
		} else {
			$result = sysCmd('/var/www/util/sysutil.sh get-mixername');
			if ($result[0] == '') {
				// Mixer name not found
				$mixerName = 'none';
			} else {
				// Mixer name found => assume default mixer name "Digital"
				$mixerName = 'Digital';
			}
		}
	}

	return $mixerName;
}

function getAlsaVolume($mixerName) {
	$result = sysCmd('/var/www/util/sysutil.sh get-alsavol ' . '"' . $mixerName . '"');
	if (substr($result[0], 0, 6 ) == 'amixer') {
		$alsaVolume = 'none';
	} else {
		$alsaVolume = str_replace('%', '', $result[0]);
	}

	return $alsaVolume;
}

// Get device names assigned to each ALSA card
function getAlsaDeviceNames() {
	// Pi HDMI 1, HDMI 2 or Headphone jack, or a USB audio device
	if ($_SESSION['i2sdevice'] == 'None' && $_SESSION['i2soverlay'] == 'None') {
		// Pi HDMI 1, HDMI 2 or Headphone jack, or a USB audio device
		for ($i = 0; $i < 4; $i++) {
			$alsaID = trim(file_get_contents('/proc/asound/card' . $i . '/id'));

			if (empty($alsaID)) {
				$devices[$i] = $i == $_SESSION['cardnum'] ? $_SESSION['adevname'] : '';
			} else if ($alsaID != 'Loopback' && $alsaID != 'Dummy') {
				$aplayDeviceName = trim(sysCmd("aplay -l | awk -F'[' '/card " . $i . "/{print $2}' | cut -d']' -f1")[0]);
				$result = sqlRead('cfg_audiodev', sqlConnect(), $alsaID);
				if ($result === true) { // Not in table
					$devices[$i] = $aplayDeviceName;
				} else {
					$devices[$i] = $result[0]['alt_name'];
				}
			}
		}
	} else {
		// I2S audio device
		$devices[0] = 'I2S audio device';
	}

	return $devices;
}

function getAlsaDevice($cardNum, $outputMode) {
	return $outputMode == 'iec958' ?
		ALSA_IEC958_DEVICE . $cardNum :
		$outputMode . ':' . $cardNum . ',0';
}

function getAlsaHwParams($cardNum) {
	$result = shell_exec('cat /proc/asound/card' . $cardNum . '/pcm0p/sub0/hw_params');

	if (is_null($result)) {
		return null;
	} else if ($result != "closed\n" && $result != "no setup\n") {
		$array = array();
		$line = strtok($result, "\n");

		while ($line) {
			list ($element, $value) = explode(': ', $line);
			$array[$element] = $value;
			$line = strtok("\n");
		}

		// Rates: '44100 (44100/1)' etc
	 	$rate = substr($array['rate'], 0, strpos($array['rate'], ' (')); // Could use: explode(' ', $array['rate'])
	 	$array['rate'] = formatRate($rate);
	 	$floatRate = (float)$rate;

		if (substr($array['format'], 0, 3) == 'DSD') {
			// Formats: 'DSD_U16_BE' or 'DSD_U32_BE'
			$floatBits = (float)substr($array['format'], 5, 2);
			$array['format'] = 'DSD';
		} else {
			// Formats: 'S16_LE' etc or IEC958_SUBFRAME_LE (Pi-5 HDMI with KMS driver)
			if ($array['format'] == ALSA_IEC958_FORMAT) {
				$array['format'] = getMpdStatus(getMpdSock())['audio_sample_depth'];
			} else {
				$array['format'] = substr($array['format'], 1, 2);
			}
			$floatBits = (float)$array['format'];
		}

		// Channels: '1', '2', '6' etc
		$floatChannels = (float)$array['channels'];
		$array['channels'] = formatChannels($array['channels']);

		// Mbps rate: calculated
		$array['status'] = 'active';
		$array['calcrate'] = number_format((($floatRate * $floatBits * $floatChannels) / 1000000), 3, '.', '');
	} else {
		$array['status'] = trim($result, "\n"); // closed
		$array['calcrate'] = '0 bps';
	}

	return $array; // rate, format, channels, status, calcrate (Mbps)
}

// Get ALSA card ID's
function getAlsaCards() {
	$cards = array();
	$maxCards = 4;
	for ($i = 0; $i < $maxCards; $i++) {
		$cardID = trim(file_get_contents('/proc/asound/card' . $i . '/id'));
		$cards[$i] = empty($cardID) ? 'empty' : $cardID;
	}
	//workerLog('getAlsaCards(): ' . print_r($cards, true));
	return $cards;
}

function getAlsaCardNum() {
	return $_SESSION['multiroom_tx'] == 'On' ? (array_search('Dummy', getAlsaCards()) - 1) : $_SESSION['cardnum'];
}

// With VC4 KMS driver + I2S device, card 0 is vc4hdmi1 and card 1 is I2S device but sometimes the order is reversed
function getAlsaCardNumVC4I2S() {
	$cards = sysCmd("aplay -l | grep card | awk '{print $3}'");
	return str_contains($cards[0], 'vc4hdmi') ? '1' : '0';
}

function setALSAVolTo0dB($alsaVolMax = '100') {
	sysCmd('/var/www/util/sysutil.sh set-alsavol ' . '"' . $_SESSION['amixname']  . '" ' . $alsaVolMax);
}

// Needs the session to be available for getAlsaCardNum()
function getALSAOutputFormat($mpdState = '', $mpdAudioSampleRate = '') {
	if ($mpdState == '') {
		// Called from command/index.php get_output_format
		$mpdStatus = getMpdStatus(getMpdSock());
		$outputStr = alsaOutputStr($mpdStatus['audio_sample_rate']);
	} else if ($mpdState == 'play') {
		// Called from enhanceMetadata() in inc/mpd.php
		$result = sqlQuery("SELECT value FROM cfg_system WHERE param='audioout'", sqlConnect());
		if ($result['value'] == 'Bluetooth') {
			$outputStr = 'PCM 16/44.1 kHz, 2ch'; // Maybe also 48K ?
		} else {
			$outputStr = alsaOutputStr($mpdAudioSampleRate);
		}
	} else {
		$outputStr = 'Not playing';
	}

	return $outputStr;
}

function alsaOutputStr($mpdAudioSampleRate = '') {
	$maxLoops = 3;
	$sleepTime = 250000;
	// Loop when checking hwparams to allow for any latency in the audio pipeline
	for ($i = 0; $i < $maxLoops; $i++) {
		$hwParams = getAlsaHwParams(getAlsaCardNum());
		//workerLog('alsaOutputStr(): ' . ($i + 1) . ' ' . $hwParams['status']);
		if ($hwParams['status'] == 'active') {
			$channels = getChannelCount($hwParams['channels']);
			$outputStr = $hwParams['format'] == 'DSD' ?
				'DSD ' . $mpdAudioSampleRate . ' MHz, ' . $channels :
				'PCM ' . $hwParams['format'] . '/' . $hwParams['rate'] . ' kHz, '. $channels;
			break;
		} else {
			$outputStr = 'Not playing';
		}

		usleep($sleepTime);
	}

	return$outputStr;
}

function getChannelCount($channelStr) {
	if ($channelStr == 'Mono') {
		$channelCount = '1';
	} else if ($channelStr == 'Stereo') {
		$channelCount = '2';
	} else {
		$channelCount = substr($channelStr, 0, 1); // N-Channel or ?-Channel
	}

	return $channelCount .'ch';
}
