<?php
/*
 * SPDX-License-Identifier: GPL-3.0-or-later
 * Copyright 2014 The moOde audio player project / Tim Curtis
*/

require_once __DIR__ . '/alsa.php';
require_once __DIR__ . '/common.php';
require_once __DIR__ . '/mpd.php';
require_once __DIR__ . '/renderer.php';
require_once __DIR__ . '/session.php';
require_once __DIR__ . '/sql.php';

function cfgI2SDevice($caller = '') {
	$dbh = sqlConnect();

	if ($_SESSION['i2sdevice'] == 'None' && $_SESSION['i2soverlay'] == 'None') {
		// No overlay
		updBootConfigTxt('upd_audio_overlay', '#dtoverlay=none');
		updBootConfigTxt('upd_force_eeprom_read', '#');
		if ($caller != 'autocfg') {
			// Reset to Pi HDMI 1 when called from WebUI
			$cardNum = getAlsaCardNumForDevice(PI_HDMI1);
			phpSession('write', 'cardnum', $cardNum);
			phpSession('write', 'adevname', PI_HDMI1);
			phpSession('write', 'mpdmixer', 'software');
			sqlUpdate('cfg_mpd', $dbh, 'device', $cardNum);
			sqlUpdate('cfg_mpd', $dbh, 'mixer_type', 'software');
		}
	} else if ($_SESSION['i2sdevice'] != 'None') {
		// Named I2S device
		$result = sqlRead('cfg_audiodev', $dbh, $_SESSION['i2sdevice']);
		updBootConfigTxt('upd_audio_overlay', 'dtoverlay=' . $result[0]['driver']);
		$value = str_contains($result[0]['driver'], 'hifiberry') ? '' : '#';
		updBootConfigTxt('upd_force_eeprom_read', $value);
	} else {
		// DT overlay
		updBootConfigTxt('upd_audio_overlay', 'dtoverlay=' . $_SESSION['i2soverlay']);
		$value = str_contains($_SESSION['i2soverlay'], 'hifiberry') ? '' : '#';
		updBootConfigTxt('upd_force_eeprom_read', $value);
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

function isHDMIDevice($deviceName) {
	return ($deviceName == PI_HDMI1 || $deviceName == PI_HDMI2);
}

function isUSBDevice($cardNum) {
	return file_exists('/proc/asound/card' . $cardNum . '/usbid');
}

function getAudioOutputIface($cardNum) {
	$deviceName = getAlsaDeviceNames()[$cardNum];

	if ($_SESSION['multiroom_tx'] == 'On') {
		$outputIface = AO_TRXSEND;
	} else if (isI2SDevice($deviceName)) {
		$outputIface = AO_I2S;
	} else if ($deviceName == PI_HEADPHONE) {
		$outputIface = AO_HEADPHONE;
	} else if ($deviceName == PI_HDMI1 || $deviceName == PI_HDMI2) {
		$outputIface = AO_HDMI;
	} else {
		$outputIface = AO_USB;
	}

	//workerLog('getAudioOutputIface(): ' . $outputIface);
	return $outputIface;
}

function getAlsaIEC958Device() {
	$piModel = substr($_SESSION['hdwrrev'], 3, 1);
	$piName = $_SESSION['hdwrrev'];

	if ($piModel < '4' || str_contains($piName, 'Pi-Zero 2')) {
		// Only 1 HDMI port, omit the card number
		$device = ALSA_IEC958_DEVICE;
	} else {
		// 2 HDMI ports
		$device = $_SESSION['adevname'] == PI_HDMI1 ? ALSA_IEC958_DEVICE . '0' : ALSA_IEC958_DEVICE . '1';
	}

	return $device;
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
			sysCmd('amixer -c ' . $_SESSION['cardnum'] . ' sset "I2S/SPDIF Select" I2S');
		}
		if ($_SESSION['mpdmixer'] == 'hardware') {
			// Save Preamp volume
			phpSession('write', 'volknob_preamp', $_SESSION['volknob']);
			// Restore saved MPD volume
			// vol.sh only updates cfg_system 'volknob' so lets also update the SESSION var
			phpSession('write', 'volknob', $_SESSION['volknob_mpd']);
			sysCmd('/var/www/util/vol.sh ' . $_SESSION['volknob_mpd']);
		}

		sendFECmd('inpactive0');

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
			$captureDevice = 'plughw:' . $_SESSION['cardnum'] . ',0';
			$playbackDevice = '_audioout';
			sysCmd('alsaloop -C ' . $captureDevice . ' -P ' . $playbackDevice . ' > /dev/null 2>&1 &');
		} else if ($_SESSION['i2sdevice'] == 'Audiophonics ES9028/9038 DAC') {
			sysCmd('amixer -c ' . $_SESSION['cardnum'] . ' sset "I2S/SPDIF Select" SPDIF');
		}

		sendFECmd('inpactive1');
	}
}

// Set MPD and renderer audio output
function setAudioOut($output) {
	sysCmd('mpc stop');

	if ($output == 'Local') {
		changeMPDMixer($_SESSION['mpdmixer_local']);
		sysCmd('mpc enable only "' . ALSA_DEFAULT . '"');
	} else if ($output == 'Bluetooth') {
		// Save if not Software
		if ($_SESSION['mpdmixer'] != 'software') {
			phpSession('write', 'mpdmixer_local', $_SESSION['mpdmixer']);
			changeMPDMixer('software');
		}
		phpSession('write', 'btactive', '0');
		sendFECmd('btactive0');
		sysCmd('mpc enable only "' . ALSA_BLUETOOTH .'"');
	}

	// Update audio out and BT out confs
	updAudioOutAndBtOutConfs($_SESSION['cardnum'], $_SESSION['alsa_output_mode']);
	updPeppyConfs($_SESSION['cardnum'], $_SESSION['alsa_output_mode']);

	// Restart renderers if indicated
	if ($_SESSION['airplaysvc'] == '1') {
		stopAirPlay();
		startAirPlay();
	}
	if ($_SESSION['spotifysvc'] == '1') {
		stopSpotify();
		startSpotify();
	}
	if ($_SESSION['deezersvc'] == '1') {
		stopDeezer();
		startDeezer();
	}

	// Set HTTP server state
	setMpdHttpd();

	// Restart MPD
	sysCmd('systemctl restart mpd');
	// Ensure its fully started and accepting connections
	$sock = openMpdSock('localhost', 6600);
	closeMpdSock($sock);

	// Set volume
	sysCmd('/var/www/util/vol.sh -restore');
}

// Update ALSA output configs (_audioout and _sndaloop)
function updAudioOutAndBtOutConfs($cardNum, $outputMode) {
	// $outputMode: plughw | hw | iec98
	// With DSP
	if ($_SESSION['alsaequal'] != 'Off') {
		$alsaDevice = 'alsaequal';
	} else if ($_SESSION['camilladsp'] != 'off') {
		$alsaDevice = 'camilladsp';
	} else if ($_SESSION['crossfeed'] != 'Off') {
		$alsaDevice = 'crossfeed';
	} else if ($_SESSION['eqfa12p'] != 'Off') {
		$alsaDevice = 'eqfa12p';
	} else if ($_SESSION['invert_polarity'] != '0') {
		$alsaDevice = 'invpolarity';
	// No DSP
	} else {
		if ($_SESSION['peppy_display'] == '1') {
			$alsaDevice = 'peppy';
		} else if ($_SESSION['audioout'] == 'Bluetooth') {
			$alsaDevice = 'btstream';
		} else {
			$alsaDevice = $outputMode == 'iec958' ? getAlsaIEC958Device() : $outputMode . ':' . $cardNum . ',0';
		}
	}

	// Update configs
	sysCmd("sed -i 's/^slave.pcm.*/slave.pcm \"" . $alsaDevice .  "\"/' " . ALSA_PLUGIN_PATH . '/_audioout.conf');
	sysCmd("sed -i 's/^a { channels 2 pcm.*/a { channels 2 pcm \"" . $alsaDevice .  "\" }/' " . ALSA_PLUGIN_PATH . '/_sndaloop.conf');

	// DEBUG:
	//workerLog('updAudioOutAndBtOutConfs(): ' . $cardNum . ':' . $outputMode . ' | ' . $alsaDevice);
}

// Update DSP and Bluetooth output configs
function updDspAndBtInConfs($cardNum, $outputMode) {
	// $outputMode: plughw | hw | iec98
	// DSP configs
	if ($_SESSION['peppy_display'] == '1') {
		$alsaDevice1 = 'peppy';
		$alsaDevice2 = 'peppy';
	} else {
		if ($_SESSION['audioout'] == 'Local') {
			// Alsaequal, crossfeed and eqfa12p: plughw is required when not HDMI device
			$alsaDevice1 = $outputMode == 'iec958' ? getAlsaIEC958Device() : 'plughw' . ':' . $cardNum . ',0';
			// Invpolarity: can use any ALSA output mode
			$alsaDevice2 = $outputMode == 'iec958' ? getAlsaIEC958Device() : $outputMode . ':' . $cardNum . ',0';
		} else {
			// Bluetooth speaker
			$alsaDevice1 = 'btstream';
			$alsaDevice2 = 'btstream';
		}
	}

	// Update configs
	sysCmd("sed -i 's/.*#device.*/slave.pcm \"" . $alsaDevice1 . "\" #device/' " . ALSA_PLUGIN_PATH . '/alsaequal.conf');
	sysCmd("sed -i 's/.*#device.*/slave.pcm \"" . $alsaDevice1 . "\" #device/' " . ALSA_PLUGIN_PATH . '/crossfeed.conf');
	sysCmd("sed -i 's/.*#device.*/slave.pcm \"" . $alsaDevice1 . "\" #device/' " . ALSA_PLUGIN_PATH . '/eqfa12p.conf');
	sysCmd("sed -i 's/.*#device.*/pcm \"" . $alsaDevice2 . "\" #device/' " . ALSA_PLUGIN_PATH . '/invpolarity.conf');

	// CamillaDSP: can use any ALSA output mode, getAlsaIEC958Device() is called in function setPlaybackDevice()
	$cdsp = new CamillaDsp($_SESSION['camilladsp'], $cardNum, $_SESSION['camilladsp_quickconv']);
	if ($_SESSION['cdsp_fix_playback'] == 'Yes' ) {
		$cdsp->setPlaybackDevice($cardNum, $outputMode);
	}

	// Bluetooth config (inbound)
	if ($_SESSION['peppy_display'] == '1') {
		if ($_SESSION['camilladsp'] != 'off') {
			$alsaDevice = 'camilladsp';
		} else {
			$alsaDevice = 'peppy';
		}
	// AUDIODEV=_audioout or plughw depending on Bluetooth Config, ALSA output mode
	} else if ($_SESSION['alsa_output_mode_bt'] == 'plughw') {
		$alsaDevice = $outputMode == 'iec958' ? getAlsaIEC958Device() : 'plughw' . ':' . $cardNum . ',0';
	} else {
		$alsaDevice = $_SESSION['alsa_output_mode_bt']; // _audioout
	}
	sysCmd("sed -i 's/^AUDIODEV.*/AUDIODEV=" . $alsaDevice . "/' /etc/bluealsaaplay.conf");

	// DEBUG:
	//workerLog('updDspAndBtInConfs():       ' . $cardNum . ':' . $outputMode . ' | ' . $alsaDevice1 . ' | ' . $alsaDevice2);
}

// Update Peppy output configs
function updPeppyConfs($cardNum, $outputMode) {
	// $outputMode: plughw | hw | iec98
	// ALSA device
	if ($_SESSION['audioout'] == 'Bluetooth') {
		$alsaDevice = 'btstream';
	} else {
		$alsaDevice = $outputMode == 'iec958' ? getAlsaIEC958Device() : $outputMode . ':' . $cardNum . ',0';
	}
	sysCmd("sed -i 's/^slave.pcm.*/slave.pcm \"" . $alsaDevice . "\"/' " . ALSA_PLUGIN_PATH . '/_peppyout.conf');
	// ALSA mixer
	$alsaMixer = $_SESSION['amixname'] == 'none' ? 'PCM' : $_SESSION['amixname'];
	$peppyConfFile = file_exists(ALSA_PLUGIN_PATH . '/peppy.conf.hide') ? '/peppy.conf.hide' : '/peppy.conf';
	sysCmd("sed -i 's/^name.*/name \"" . $alsaMixer . "\"/' " . ALSA_PLUGIN_PATH . $peppyConfFile);
	sysCmd("sed -i 's/^card.*/card " . $cardNum . "/' " . ALSA_PLUGIN_PATH . $peppyConfFile);
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
			"'" . $deviceName . "'," .
			"'" . $_SESSION['mpdmixer'] . "'," .
			"'" . $_SESSION['alsa_output_mode'] . "'," .
			"'" . $_SESSION['alsavolume_max'] . "'";
    	$result = sqlInsert('cfg_outputdev', $dbh, $values);
    } else {
		$value = array(
			'mpd_volume_type' => $_SESSION['mpdmixer'],
			'alsa_output_mode' => $_SESSION['alsa_output_mode'],
			'alsa_max_volume' => $_SESSION['alsavolume_max']);
		$result = sqlUpdate('cfg_outputdev', $dbh, $deviceName, $value);
    }
}

function checkOutputDeviceCache($deviceName, $cardNum) {
	$cachedDev = readOutputDeviceCache($deviceName);
	if ($cachedDev == 'device not found') {
		$volumeType = 'software';
		$alsaOutputMode = getAudioOutputIface($cardNum) == AO_HDMI ? 'iec958' : 'plughw';
		$alsaMaxVolume = $_SESSION['alsavolume_max'];
	} else {
		if ($_SESSION['camilladsp'] == 'off') {
			if ($cachedDev['mpd_volume_type'] == 'null') {
				$volumeType = $_SESSION['alsavolume'] != 'none' ? 'hardware' : 'software';
			} else {
				$volumeType = $cachedDev['mpd_volume_type'];
			}
		} else {
			$volumeType = 'null';
		}
		$alsaOutputMode = $cachedDev['alsa_output_mode'];
		$alsaMaxVolume = $cachedDev['alsa_max_volume'];
	}

	return array(
		'mpd_volume_type' => $volumeType,
		'alsa_output_mode' => $alsaOutputMode,
		'alsa_max_volume' => $alsaMaxVolume,);
}
