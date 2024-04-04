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
require_once __DIR__ . '/common.php';
require_once __DIR__ . '/mpd.php';
require_once __DIR__ . '/renderer.php';
require_once __DIR__ . '/session.php';
require_once __DIR__ . '/sql.php';

function cfgI2SDevice() {
	$dbh = sqlConnect();

	// Remove audio overlay line if it exists (next line after 'dtoverlay=disable-wifi')
	$lines = sysCmd('cat ' . BOOT_CONFIG_TXT . ' | grep -A 1 "' . CFG_DTOVERLAY_DISABLE_WIFI . '" | wc -l')[0];
	if ($lines >= 2) {
		sysCmd('sed -i "/' . CFG_DTOVERLAY_DISABLE_WIFI . '/q" ' . BOOT_CONFIG_TXT);
	}
	// Remove 'force_eeprom_read=0'line (only exists for hifiberry devices)
	sysCmd('sed -i "/force_eeprom_read=0/d" ' . BOOT_CONFIG_TXT);
	// Add force_eeprom_read=0 if hifiberry device
	$str = $_SESSION['i2sdevice'] . $_SESSION['i2soverlay'];
	$eeprom = str_contains($str, 'hifiberry') ? '\nforce_eeprom_read=0' : '';

	// Add the audio overlay
	if ($_SESSION['i2sdevice'] == 'None' && $_SESSION['i2soverlay'] == 'None') {
		// Reset to Pi HDMI 1
		$cardNum = getAlsaCardNumForDevice(PI_HDMI1);
		phpSession('write', 'cardnum', $cardNum);
		phpSession('write', 'adevname', PI_HDMI1);
		phpSession('write', 'mpdmixer', 'software'); //
		sqlUpdate('cfg_mpd', $dbh, 'device', $cardNum);
		sqlUpdate('cfg_mpd', $dbh, 'mixer_type', 'software'); //
	} else if ($_SESSION['i2sdevice'] != 'None') {
		// Named I2S device
		$result = sqlRead('cfg_audiodev', $dbh, $_SESSION['i2sdevice']);
		sysCmd('sed -i s"/' .
			CFG_DTOVERLAY_DISABLE_WIFI . '/' .
			CFG_DTOVERLAY_DISABLE_WIFI . '\ndtoverlay=' . $result[0]['driver'] . $eeprom . '/" ' . BOOT_CONFIG_TXT);
	} else {
		// DT overlay
		sysCmd('sed -i s"/' .
			CFG_DTOVERLAY_DISABLE_WIFI . '/' .
			CFG_DTOVERLAY_DISABLE_WIFI . '\ndtoverlay=' . $_SESSION['i2soverlay'] . $eeprom . '/" ' . BOOT_CONFIG_TXT);
	}
}

function isI2SDevice($deviceName) {
	if ($_SESSION['i2sdevice'] != 'None' && $deviceName == $_SESSION['i2sdevice']) {
		// Named I2S device in cfg_audiodev
		$isI2SDevice = true;
	} else if ($_SESSION['i2soverlay'] != 'None') {
		// Name parsed from aplay -l
		$aplayName = trim(sysCmd("aplay -l | awk -F'[' '/card " . $_SESSION['cardnum'] . "/{print $2}' | cut -d']' -f1")[0]);
		if ($aplayName == getAlsaDeviceNames()[$_SESSION['cardnum']]) {
			$isI2SDevice = true;
		} else {
			$isI2SDevice = false;
			workerLog('isI2SDevice(): Error: aplay name not found for i2soverlay=' . $_SESSION['i2soverlay']);
		}
	} else {
		$isI2SDevice = false;
	}

	//workerLog('isI2SDevice(' . $deviceName . ')=' . ($isI2SDevice === true ? 'true' : 'false'));
	return $isI2SDevice;
}

function getConfiguredAudioOutput() {
	// Configured device
	$deviceName = getAlsaDeviceNames()[$_SESSION['cardnum']];

	if ($_SESSION['multiroom_tx'] == 'On') {
		$configuredOutput = AO_TRXSEND;
	} else if (isI2SDevice($deviceName)) {
		$configuredOutput = AO_I2S;
	} else if ($deviceName == PI_HEADPHONE) {
		$configuredOutput = AO_HEADPHONE;
	} else if ($deviceName == PI_HDMI1 || $deviceName == PI_HDMI2) {
		$configuredOutput = AO_HDMI;
	} else {
		$configuredOutput = AO_USB;
	}

	//workerLog('getConfiguredAudioOutput(): ' . $configuredOutput);
	return $configuredOutput;
}

// Set audio source
function setAudioIn($inputSource) {
	sysCmd('mpc stop');
	$wrkReady = sqlQuery("SELECT value FROM cfg_system WHERE param='wrkready'", sqlConnect())[0]['value'];
	// No need to configure Local during startup (wrkready = 0)
	if ($inputSource == 'Local' && $wrkReady == '1') {
		if ($_SESSION['i2sdevice'] == 'HiFiBerry DAC+ ADC') {
			sysCmd('killall -s 9 alsaloop');
		} else if ($_SESSION['i2sdevice'] == 'Audiophonics ES9028/9038 DAC') {
			sysCmd('amixer -c 0 sset "I2S/SPDIF Select" I2S');
		}
		if ($_SESSION['mpdmixer'] == 'hardware') {
			// Save Preamp volume
			phpSession('write', 'volknob_preamp', $_SESSION['volknob']);
			// Restore saved MPD volume
			// vol.sh only updates cfg_system 'volknob' so lets also update the SESSION var
			phpSession('write', 'volknob', $_SESSION['volknob_mpd']);
			sysCmd('/var/www/util/vol.sh ' . $_SESSION['volknob_mpd']);
		}

		sendEngCmd('inpactive0');

		if ($_SESSION['rsmafterinp'] == 'Yes') {
			sysCmd('mpc play');
		}
	} else if ($inputSource == 'Analog' || $inputSource == 'S/PDIF') {
		// NOTE: the Source Select form requires MPD Volume control to be set to Hardware or Disabled (0dB)
		if ($_SESSION['mpdmixer'] == 'hardware') {
			// Don't update this value during startup (wrkready = 0)
			if ($wrkReady == '1') {
				// Save MPD volume
				phpSession('write', 'volknob_mpd', $_SESSION['volknob']);
			}
			// Restore saved Preamp volume
			// vol.sh only updates cfg_system 'volknob' so lets also update the SESSION var
			phpSession('write', 'volknob', $_SESSION['volknob_preamp']);
			sysCmd('/var/www/util/vol.sh ' . $_SESSION['volknob_preamp']);
		}

		if ($_SESSION['i2sdevice'] == 'HiFiBerry DAC+ ADC') {
			sysCmd('alsaloop > /dev/null 2>&1 &');
		} else if ($_SESSION['i2sdevice'] == 'Audiophonics ES9028/9038 DAC') {
			sysCmd('amixer -c 0 sset "I2S/SPDIF Select" SPDIF');
		}

		sendEngCmd('inpactive1');
	}
}

// Set MPD and renderer audio output
function setAudioOut($output) {
	if ($output == 'Local') {
		changeMPDMixer($_SESSION['mpdmixer_local']);
		sysCmd('/var/www/util/vol.sh -restore');
		sysCmd('mpc stop');
		sysCmd('mpc enable only "' . ALSA_DEFAULT . '"');
	} else if ($output == 'Bluetooth') {
		// Save if not Software
		if ($_SESSION['mpdmixer'] != 'software') {
			phpSession('write', 'mpdmixer_local', $_SESSION['mpdmixer']);
			changeMPDMixer('software');
		}

		phpSession('write', 'btactive', '0');
		sendEngCmd('btactive0');
		sysCmd('/var/www/util/vol.sh -restore');
		sysCmd('mpc stop');
		sysCmd('mpc enable only "' . ALSA_BLUETOOTH .'"');
	}

	// Update audio out and BT out confs
	updAudioOutAndBtOutConfs($_SESSION['cardnum'], $_SESSION['alsa_output_mode']);

	// Restart renderers if indicated
	if ($_SESSION['airplaysvc'] == '1') {
		stopAirPlay();
		startAirPlay();
	}
	if ($_SESSION['spotifysvc'] == '1') {
		stopSpotify();
		startSpotify();
	}

	// Set HTTP server state
	setMpdHttpd();

	// Restart MPD
	sysCmd('systemctl restart mpd');
}

// Update ALSA audio out and BT out confs
function updAudioOutAndBtOutConfs($cardNum, $outputMode) {
	// $outputMode:
	// plughw	Default
	// hw		Direct
	// iec958	IEC958
	if ($_SESSION['audioout'] == 'Local') {
		// With DSP
		if ($_SESSION['alsaequal'] != 'Off') {
			sysCmd("sed -i '/slave.pcm/c\slave.pcm \"alsaequal\"' " . ALSA_PLUGIN_PATH . '/_audioout.conf');
			sysCmd("sed -i '/a { channels 2 pcm/c\a { channels 2 pcm \"alsaequal\" }' " . ALSA_PLUGIN_PATH . '/_sndaloop.conf');
		} else if ($_SESSION['camilladsp'] != 'off') {
			sysCmd("sed -i '/slave.pcm/c\slave.pcm \"camilladsp\"' " . ALSA_PLUGIN_PATH . '/_audioout.conf');
			sysCmd("sed -i '/a { channels 2 pcm/c\a { channels 2 pcm \"camilladsp\" }' " . ALSA_PLUGIN_PATH . '/_sndaloop.conf');
		} else if ($_SESSION['crossfeed'] != 'Off') {
			sysCmd("sed -i '/slave.pcm/c\slave.pcm \"crossfeed\"' " . ALSA_PLUGIN_PATH . '/_audioout.conf');
			sysCmd("sed -i '/a { channels 2 pcm/c\a { channels 2 pcm \"crossfeed\" }' " . ALSA_PLUGIN_PATH . '/_sndaloop.conf');
		} else if ($_SESSION['eqfa12p'] != 'Off') {
			sysCmd("sed -i '/slave.pcm/c\slave.pcm \"eqfa12p\"' " . ALSA_PLUGIN_PATH . '/_audioout.conf');
			sysCmd("sed -i '/a { channels 2 pcm/c\a { channels 2 pcm \"eqfa12p\" }' " . ALSA_PLUGIN_PATH . '/_sndaloop.conf');
		} else if ($_SESSION['invert_polarity'] != '0') {
			sysCmd("sed -i '/slave.pcm/c\slave.pcm \"invpolarity\"' " . ALSA_PLUGIN_PATH . '/_audioout.conf');
			sysCmd("sed -i '/a { channels 2 pcm/c\a { channels 2 pcm \"invpolarity\" }' " . ALSA_PLUGIN_PATH . '/_sndaloop.conf');
		} else {
			// No DSP
			$alsaDevice = $outputMode == 'iec958' ? ALSA_IEC958_DEVICE . $cardNum : $outputMode . ':' . $cardNum . ',0';
			sysCmd("sed -i '/slave.pcm/c\slave.pcm \"" . $alsaDevice . "\"' " . ALSA_PLUGIN_PATH . '/_audioout.conf');
			sysCmd("sed -i '/a { channels 2 pcm/c\a { channels 2 pcm \""  . $alsaDevice . "\" }' " . ALSA_PLUGIN_PATH . '/_sndaloop.conf');
		}

		// Update squeezelite.conf
		cfgSqueezelite();
	} else {
		// Bluetooth out
		sysCmd("sed -i '/slave.pcm/c\slave.pcm \"btstream\"' " . ALSA_PLUGIN_PATH . '/_audioout.conf');
		sysCmd("sed -i '/a { channels 2 pcm/c\a { channels 2 pcm \"btstream\" }' " . ALSA_PLUGIN_PATH . '/_sndaloop.conf');
	}
}

// Update ALSA DSP and Bluetooth in confs
function updDspAndBtInConfs($cardNum, $newOutputMode, $oldOutputMode = '') {
	// NOTE: This is done because the function can be called to change either the cardnum or the output mode
	$oldOutputMode = empty($oldOutputMode) ? $_SESSION['alsa_output_mode'] : $oldOutputMode;

	// ALSA DSP confs
	// NOTE: Crossfeed, eqfa12p and alsaequal only work with 'plughw' output mode
	sysCmd("sed -i '/slave.pcm \"" . 'plughw' . "/c\slave.pcm \"" . 'plughw' . ':' . $cardNum . ",0\"' " . ALSA_PLUGIN_PATH . '/alsaequal.conf');
	sysCmd("sed -i '/slave.pcm \"" . 'plughw' . "/c\slave.pcm \"" . 'plughw' . ':' . $cardNum . ",0\"' " . ALSA_PLUGIN_PATH . '/crossfeed.conf');
	sysCmd("sed -i '/slave.pcm \"" . 'plughw' . "/c\slave.pcm \"" . 'plughw' . ':' . $cardNum . ",0\"' " . ALSA_PLUGIN_PATH . '/eqfa12p.conf');
	sysCmd("sed -i '/pcm \"" . $oldOutputMode . "/c\pcm \"" . $newOutputMode . ':' . $cardNum . ",0\"' " . ALSA_PLUGIN_PATH . '/invpolarity.conf');
	$cdsp = new CamillaDsp($_SESSION['camilladsp'], $cardNum, $_SESSION['camilladsp_quickconv']);
	if ($_SESSION['cdsp_fix_playback'] == 'Yes' ) {
		$cdsp->setPlaybackDevice($cardNum, $newOutputMode);
	}

	// Bluetooth confs (incoming connections)
	// NOTE: bluealsaaplay.conf AUDIODEV=_audioout or plughw depending on Bluetooth Config, ALSA output mode
}

// Read output device cache
function readOutputDeviceCache($deviceName) {
	$dbh = sqlConnect();

    $result = sqlRead('cfg_outputdev', $dbh, $deviceName);
    if ($result === true) {
    	// Not in table
		$values = 'device not found';
    } else {
		// In table
		$values = array(
			'device_name' => $result[0]['device_name'],
			'mpd_volume_type' => $result[0]['mpd_volume_type'],
			'alsa_output_mode' => $result[0]['alsa_output_mode'],
			'alsa_max_volume' => $result[0]['alsa_max_volume']);
    }

	return $values;
}

// Update output device cache
function updOutputDeviceCache($deviceName) {
	$dbh = sqlConnect();

    $result = sqlRead('cfg_outputdev', $dbh, $deviceName);
    if ($result === true) {
    	// Not in table so add new
    	$values =
			'"' . $deviceName . '",' .
			'"' . $_SESSION['mpdmixer'] . '",' .
			'"' . $_SESSION['alsa_output_mode'] . '",' .
			'"' . $_SESSION['alsavolume_max'] . '"';
    	$result = sqlInsert('cfg_outputdev', $dbh, $values);
    } else {
		$value = array(
			'mpd_volume_type' => $_SESSION['mpdmixer'],
			'alsa_output_mode' => $_SESSION['alsa_output_mode'],
			'alsa_max_volume' => $_SESSION['alsavolume_max']);
		$result = sqlUpdate('cfg_outputdev', $dbh, $deviceName, $value);
    }
}
