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

require_once __DIR__ . '/alsa.php';
require_once __DIR__ . '/audio.php';
require_once __DIR__ . '/common.php';
require_once __DIR__ . '/cdsp.php';
require_once __DIR__ . '/mpd.php';
require_once __DIR__ . '/music-library.php';
require_once __DIR__ . '/sql.php';

function getAlsaMixerName($deviceName) {
	if (isI2SDevice($deviceName)) {
		// I2S devices
		// Mixer name exceptions
		if ($deviceName == 'HiFiBerry Amp(Amp+)') {
			$mixerName = 'Channels';
		} else if ($deviceName == 'HiFiBerry DAC+ DSP') {
			$mixerName = 'DSPVolume';
		} else if ($_SESSION['i2soverlay'] == 'hifiberry-dacplushd') {
			$mixerName = 'DAC';
		} else if ($deviceName == 'MERUS(tm) Amp piHAT ZW') {
			$mixerName = 'A.Mstr Vol';
		} else if (
			$deviceName == 'Allo Katana DAC' ||
			$deviceName == 'Allo Boss 2 DAC' ||
			$deviceName == 'Allo Piano 2.1 Hi-Fi DAC') {
			$mixerName = 'Master';
		} else {
			// Parse mixer names from amixer output
			$result = sysCmd('/var/www/util/sysutil.sh get-mixername');
			if (empty($result)) {
				// Mixer name not found
				$mixerName = 'none';
			} else {
				if (in_array('(' . ALSA_DEFAULT_MIXER_NAME_I2S . ')', $result)) {
					$mixerName = ALSA_DEFAULT_MIXER_NAME_I2S;
				} else {
					$mixerName = ALSA_DEFAULT_MIXER_NAME_I2S;
				}
			}
		}
	} else {
		// Pi HDMI 1/2, Headphone jack or USB devices
		if ($deviceName == PI_HDMI1 || $deviceName == PI_HDMI2) {
			// No need to query since the HDMI port may not be connected
			$mixerName = 'PCM';
		} else {
			// Parse mixer name from amixer output
			$result = sysCmd('/var/www/util/sysutil.sh get-mixername');
			if (empty($result)) {
				// Mixer name not found
				$mixerName = 'none';
			} else {
				// Use first if multiple returned, strip off delimiters added by get-mixername
				$mixerName = ltrim($result[0], '(');
				$mixerName = rtrim($mixerName, ')');
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

function getAlsaVolumeDb($mixerName) {
	$result = sysCmd('/var/www/util/sysutil.sh get-alsavol-db ' . '"' . $mixerName . '"');
	if (substr($result[0], 0, 6 ) == 'amixer') {
		$alsaVolume = 'none';
	} else {
		$alsaVolume = $result[0];
	}

	return $alsaVolume;
}

// Get ALSA card ID's
function getAlsaCardIDs() {
	$cardIDs = array();
	for ($i = 0; $i < ALSA_MAX_CARDS; $i++) {
		$cardID = trim(file_get_contents('/proc/asound/card' . $i . '/id'));
		$cardIDs[$i] = empty($cardID) ? ALSA_EMPTY_CARD : $cardID;
	}
	//workerLog('getAlsaCardIDs(): ' . print_r($cards, true));
	return $cardIDs;
}

// Get device names assigned to each ALSA card
// Use cfg_audiodev name if it exists, otherwise parse the name from aplay -l
function getAlsaDeviceNames() {
	$dbh = sqlConnect();
	$deviceNames = array();
	for ($i = 0; $i < ALSA_MAX_CARDS; $i++) {
		$cardID = trim(file_get_contents('/proc/asound/card' . $i . '/id'));
		$aplayDeviceName = trim(sysCmd("aplay -l | awk -F'[' '/card " . $i . "/{print $2}' | cut -d']' -f1")[0]);
		$isUSBDevice = empty(sysCmd('aplay -l | grep "card ' . $i . '" | grep "USB Audio"')) ? false : true;

		if (empty($cardID)) {
			$deviceNames[$i] = ALSA_EMPTY_CARD;
		} else if ($cardID == ALSA_LOOPBACK_DEVICE) {
			$deviceNames[$i] = $cardID;
		} else if ($cardID == ALSA_DUMMY_DEVICE) {
			$deviceNames[$i] = $cardID;
		} else {
			// Format singleton vc4hdmi0 if indicated
			$cardID .= ($cardID == ALSA_VC4HDMI_SINGLE_DEVICE ? '0' : '');

			// These card id's are defined in cfg_audiodev and have alternate (friendly) names
			// b1			Pi HDMI 1
			// b2			Pi HDMI 2
			// Headphones	Pi Headphone jack
			// vc4hdmi0		Pi HDMI 1
			// vc4hdmi1		Pi HDMI 2
			// Revolution	Allo Revolution DAC
			// DAC8STEREO	okto research dac8 Stereo
			$result = sqlRead('cfg_audiodev', $dbh, $cardID);

			// All queries return the following:
			// false			Query execution failed (rare)
			// true				Query successful: no rows contained a match
			// Array			Query successful: at least one row contained a match
			if ($result === true) {
				// Not in table: either USB or I2S device
				//$result = sysCmd('aplay -l | grep "card ' . $i . '" | grep "USB Audio"');
				if ($isUSBDevice) {
					// USB device: assign aplay device name
					$deviceNames[$i] = $aplayDeviceName;
				} else {
					// I2S device
					$result = sqlRead('cfg_audiodev', $dbh, $_SESSION['i2sdevice']);
					if ($result === true) {
						// Not in table: assign aplay device name
						$deviceNames[$i] = $aplayDeviceName;
					} else {
						// In table: assign defined name
						$deviceNames[$i] = $result[0]['name'];
					}
				}
			} else {
				// In table: assign alternate (friendly) name
				$deviceNames[$i] = $result[0]['alt_name'];
			}
		}
		//workerLog('getAlsaDeviceNames(): ' . $i . ': cardid=' . $cardID . ', name=' . $deviceNames[$i]);
	}

	return $deviceNames;
}

// Get ALSA card number assigned to device
function getAlsaCardNumForDevice($deviceName) {
	// Array index is the card number
	$deviceNames = getAlsaDeviceNames();

	if ($_SESSION['multiroom_tx'] == 'On') {
		// Multiroom sender uses ALSA Dummy device
		$cardNum = getArrayIndex(ALSA_DUMMY_DEVICE, $deviceNames);
	} else {
		// HDMI, I2S or USB device
		// USB device may not be connected and thus $deviceNames entry will be 'empty'
		$cardNum = getArrayIndex($deviceName, $deviceNames);
	}

	//workerLog('getAlsaCardNumForDevice(): card=' . $cardNum . ', device=' . $deviceName);
	return $cardNum;
}

function getArrayIndex($needle, $haystack) {
	$numElements = count($haystack);
	$index = ALSA_EMPTY_CARD;

	for ($i = 0; $i < $numElements; $i++) {
		if ($haystack[$i] == $needle) {
			$index = $i;
			break;
		}
	}

	return $index;
}

function setALSAVolTo0dB($alsaVolMax = '100') {
	sysCmd('/var/www/util/sysutil.sh set-alsavol ' . '"' . $_SESSION['amixname']  . '" ' . $alsaVolMax);
}

// Needs the session to be available for alsaOutputStr() -> getAlsaCardNumForDevice()
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
		$hwParams = getAlsaHwParams(getAlsaCardNumForDevice($_SESSION['adevname']));
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
