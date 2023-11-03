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
require_once __DIR__ . '/mpd.php';
require_once __DIR__ . '/renderer.php';
require_once __DIR__ . '/session.php';
require_once __DIR__ . '/sql.php';

function cfgI2sOverlay($i2sDevice) {
	// Removes the line after dtparam=audio=off which would be a dtoverlay=audio_overlay line
	sysCmd('sed -i "/dtparam=audio=off/{n;d}" /boot/config.txt');
	// Remove the force_eeprom_read=0 line (it will only exist for hifiberry dtoverlays
	sysCmd('sed -i "/force_eeprom_read=0/d" /boot/config.txt');

	// Add force_eeprom_read=0 for all hifiberry cards
	$forceEepromRead0 = (stripos($i2sDevice, 'hifiberry') !== false || stripos($_SESSION['i2soverlay'], 'hifiberry') !== false) ?
		'\nforce_eeprom_read=0' : '';

	if ($i2sDevice == 'None' && $_SESSION['i2soverlay'] == 'None') {
		// Reset to Pi HDMI-1
		sysCmd('sed -i "s/dtparam=audio=off/dtparam=audio=on/" /boot/config.txt');
		# This will trigger an MPD conf update during startup and set all the device params correctly
		phpSession('write', 'adevname', 'Pi HDMI 1');
	} else if ($i2sDevice != 'None') {
		// Named I2S device
		$result = sqlRead('cfg_audiodev', sqlConnect(), $i2sDevice);
		sysCmd('sed -i "/dtparam=audio=/c \dtparam=audio=off\ndtoverlay=' . $result[0]['driver'] . $forceEepromRead0 . '" /boot/config.txt');
		phpSession('write', 'cardnum', '0');
		phpSession('write', 'adevname', $result[0]['name']);
		sqlUpdate('cfg_mpd', sqlConnect(), 'device', '0');
	} else {
		// DT overlay
		sysCmd('sed -i "/dtparam=audio=/c \dtparam=audio=off\ndtoverlay=' . $_SESSION['i2soverlay'] . $forceEepromRead0 . '" /boot/config.txt');
		phpSession('write', 'cardnum', '0');
		phpSession('write', 'adevname', $_SESSION['i2soverlay']);
		sqlUpdate('cfg_mpd', sqlConnect(), 'device', '0');
	}
}

// Set audio source
function setAudioIn($inputSource) {
	sysCmd('mpc stop');
	$result = sqlQuery("SELECT value FROM cfg_system WHERE param='wrkready'", sqlConnect());

 	// No need to configure Local during startup (wrkready = 0)
	if ($inputSource == 'Local' && $result[0]['value'] == '1') {
		if ($_SESSION['i2sdevice'] == 'HiFiBerry DAC+ ADC') {
			sysCmd('killall -s 9 alsaloop');
		} else if ($_SESSION['i2sdevice'] == 'Audiophonics ES9028/9038 DAC' || $_SESSION['i2sdevice'] == 'Audiophonics ES9028/9038 DAC (Pre 2019)') {
			sysCmd('amixer -c 0 sset "I2S/SPDIF Select" I2S');
		}
		if ($_SESSION['mpdmixer'] == 'hardware') {
			phpSession('write', 'volknob_preamp', $_SESSION['volknob']);
			sysCmd('/var/www/vol.sh ' . $_SESSION['volknob_mpd']);
		}

		sendEngCmd('inpactive0');

		if ($_SESSION['rsmafterinp'] == 'Yes') {
			sysCmd('mpc play');
		}
	} else if ($inputSource == 'Analog' || $inputSource == 'S/PDIF') {
		// NOTE: the Source Select form requires MPD Volume control is set to Hardware or Disabled (0dB)
		if ($_SESSION['mpdmixer'] == 'hardware') {
			if ($result[0]['value'] == '1') {
				// Only update this value during startup (wrkready = 0)
				phpSession('write', 'volknob_mpd', $_SESSION['volknob']);
			}
			sysCmd('/var/www/vol.sh ' . $_SESSION['volknob_preamp']);
		}

		if ($_SESSION['i2sdevice'] == 'HiFiBerry DAC+ ADC') {
			sysCmd('alsaloop > /dev/null 2>&1 &');
		} else if ($_SESSION['i2sdevice'] == 'Audiophonics ES9028/9038 DAC' || $_SESSION['i2sdevice'] == 'Audiophonics ES9028/9038 DAC (Pre 2019)') {
			sysCmd('amixer -c 0 sset "I2S/SPDIF Select" SPDIF');
		}

		sendEngCmd('inpactive1');
	}
}

// Set MPD and renderer audio output
function setAudioOut($output) {
	if ($output == 'Local') {
		changeMPDMixer($_SESSION['mpdmixer_local']);
		sysCmd('/var/www/vol.sh -restore');
		sysCmd('mpc stop');
		sysCmd('mpc enable only "' . ALSA_DEFAULT . '"');
	} else if ($output == 'Bluetooth') {
		// Save if Fixed (0dB) or Hardware
		if ($_SESSION['mpdmixer'] == 'none' || $_SESSION['mpdmixer'] == 'hardware') {
			phpSession('write', 'mpdmixer_local', $_SESSION['mpdmixer']);
			changeMPDMixer('software');
		}

		phpSession('write', 'btactive', '0');
		sendEngCmd('btactive0');
		sysCmd('/var/www/vol.sh -restore');
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

// Update ALSA audio out and Bt out confs
function updAudioOutAndBtOutConfs($cardNum, $outputMode) {
	// $outputMode:
	// - plughw	Default
	// - hw		Direct
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
			sysCmd("sed -i '/slave.pcm/c\slave.pcm \"" . $outputMode . ':' . $cardNum . ",0\"' " . ALSA_PLUGIN_PATH . '/_audioout.conf');
			sysCmd("sed -i '/a { channels 2 pcm/c\a { channels 2 pcm \""  . $outputMode . ':' . $cardNum . ",0\" }' " . ALSA_PLUGIN_PATH . '/_sndaloop.conf');
		}

		// Update squeezelite.conf
		cfgSqueezelite();
	} else {
		// Bluetooth out
		sysCmd("sed -i '/slave.pcm/c\slave.pcm \"btstream\"' " . ALSA_PLUGIN_PATH . '/_audioout.conf');
		sysCmd("sed -i '/a { channels 2 pcm/c\a { channels 2 pcm \"btstream\" }' " . ALSA_PLUGIN_PATH . '/_sndaloop.conf');
	}
}

// Update ALSA DSP and BT in confs
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
	// NOTE: Section removed, not needed anymore since bluealsaaplay.conf using AUDIODEV=_audioout instead of ALSA hw or plughw

	// TODO: check option to determine whether _audioout or plughw is used.
}
