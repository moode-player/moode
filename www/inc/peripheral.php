<?php
/*
 * SPDX-License-Identifier: GPL-3.0-or-later
 * Copyright 2014 The moOde audio player project / Tim Curtis
*/

require_once __DIR__ . '/common.php';

// Local display
function startLocalDisplay() {
	sysCmd('systemctl start localdisplay');
}
function stopLocalDisplay() {
	sysCmd('systemctl stop localdisplay');
}

// Peppy display
function createPeppyFifoPipes () {
	$pipeMeter = "/tmp/peppymeter";
	$pipeSpectrum = "/tmp/peppyspectrum";
	sysCmd('mkfifo ' . $pipeMeter . ' ' . $pipeSpectrum);
	sysCmd('chown root:root ' . $pipeMeter . ' ' . $pipeSpectrum);
	sysCmd('chmod 0666 ' . $pipeMeter . ' ' . $pipeSpectrum);
}
function hidePeppyConf() {
	sysCmd('mv ' . ALSA_PLUGIN_PATH . '/peppy.conf ' .ALSA_PLUGIN_PATH . '/peppy.conf.hide');
}
function unhidePeppyConf() {
	sysCmd('mv ' . ALSA_PLUGIN_PATH . '/peppy.conf.hide ' .ALSA_PLUGIN_PATH . '/peppy.conf');
}
function restartMpdAndRenderers($resetAlsaCtl) {
	// Restart MPD
	$MpdWasPlaying = sysCmd('mpc status | grep "\[playing\]"');

	sysCmd('systemctl stop mpd');
	if ($resetAlsaCtl === true) {
		sysCmd('alsactl clean ' . $_SESSION['cardnum']); // Clean (reset) application controls
		sysCmd('alsactl init ' . $_SESSION['cardnum']); // Initialize driver to a default state
	}
	sysCmd('systemctl start mpd');
	$sock = openMpdSock('localhost', 6600); // Ensure MPD ready to accept connections
	closeMpdSock($sock);

	if (!empty($MpdWasPlaying)) {
		sysCmd('mpc play');
	}

	// Restart renderers
	if ($_SESSION['airplaysvc'] == 1) {
		stopAirPlay();
		startAirPlay();
	}
	if ($_SESSION['spotifysvc'] == 1) {
		stopSpotify();
		startSpotify();
	}
	if ($_SESSION['deezersvc'] == 1) {
		stopDeezer();
		startDeezer();
	}
}
function allowPeppyInAlsaChain() {
	// NOTE: MPD cant play ALSA chain: _audioout -> [alsaequal or eqfa12p] -> peppy -> btstream
	if ($_SESSION['audioout'] == 'Bluetooth' && ($_SESSION['alsaequal'] != 'Off' || $_SESSION['eqfa12p'] != 'Off')) {
		$allowPeppy = false;
	} else {
		$allowPeppy = true;
	}
	return $allowPeppy;
}
function allowDspInAlsaChain() {
	if ($_SESSION['audioout'] == 'Bluetooth' && $_SESSION['peppy_display'] == '1') {
		$allowDsp = false;
	} else {
		$allowDsp = true;
	}
	return $allowDsp;
}
function getPeppyConfig($type) {
	$configFile = $type == 'meter' ? PEPPY_METER_ETC_DIR . '/config.txt' : PEPPY_SPECTRUM_ETC_DIR . '/config.txt';
	return parseDelimFile(file_get_contents($configFile), ' = ');
}
function putPeppyConfig($configArray) {
	foreach ($configArray as $key => $value) {
		chkValue($key, $value);
		switch ($key) {
			case 'screen_width':
				$param = 'screen.width';
				sysCmd("sed -i 's/^" . $param . '.*/' . $param . ' = ' . $value . "/' " . PEPPY_METER_ETC_DIR . '/config.txt');
				break;
			case 'screen_height':
				$param = 'screen.height';
				sysCmd("sed -i 's/^" . $param . '.*/' . $param . ' = ' . $value . "/' " . PEPPY_METER_ETC_DIR . '/config.txt');
				break;
			case 'random_interval':
				$param = 'random.meter.interval';
				sysCmd("sed -i 's/^" . $param . '.*/' . $param . ' = ' . $value . "/' " . PEPPY_METER_ETC_DIR . '/config.txt');
				$param = 'update.period';
				sysCmd("sed -i 's/^" . $param . '.*/' . $param . ' = ' . $value . "/' " . PEPPY_SPECTRUM_ETC_DIR . '/config.txt');
				break;
			case 'meter_folder':
				$param = 'meter.folder';
				sysCmd("sed -i 's/^" . $param . '.*/' . $param . ' = ' . $value . "/' " . PEPPY_METER_ETC_DIR . '/config.txt');
				break;
			case 'meter_name':
				$param = 'meter =';
				sysCmd("sed -i 's/^" . $param . '.*/' . $param . ' ' . $value . "/' " . PEPPY_METER_ETC_DIR . '/config.txt');
				break;
			case 'meter_normalization':
				$param = 'volume.max.in.pipe';
				sysCmd("sed -i 's/^" . $param . '.*/' . $param . ' = ' . number_format($value, 1) . "/' " . PEPPY_METER_ETC_DIR . '/config.txt');
				break;
			case 'frame_rate':
				$param = 'frame.rate';
				sysCmd("sed -i 's/^" . $param . '.*/' . $param . ' = ' . $value . "/' " . PEPPY_METER_ETC_DIR . '/config.txt');
				//sysCmd("sed -i 's/^" . $param . '.*/' . $param . ' = ' . $value . "/' " . PEPPY_SPECTRUM_ETC_DIR . '/config.txt');
				break;
			case 'polling_interval':
				$param = 'polling.interval';
				sysCmd("sed -i 's/^" . $param . '.*/' . $param . ' = ' . $value . "/' " . PEPPY_METER_ETC_DIR . '/config.txt');
				//$param = 'update.ui.interval';
				//sysCmd("sed -i 's/^" . $param . '.*/' . $param . ' = ' . $value . "/' " . PEPPY_SPECTRUM_ETC_DIR . '/config.txt');
				break;
			case 'smooth_buffer_size':
				$param = 'smooth.buffer.size';
				sysCmd("sed -i 's/^" . $param . '.*/' . $param . ' = ' . $value . "/' " . PEPPY_METER_ETC_DIR . '/config.txt');
				break;
			case 'spectrum_folder':
				$param = 'spectrum.folder';
				sysCmd("sed -i 's/^" . $param . '.*/' . $param . ' = ' . $value . "/' " . PEPPY_SPECTRUM_ETC_DIR . '/config.txt');
				break;
			case 'spectrum_name':
				$param = 'spectrum =';
				$value = $value == 'random' ? '' : $value;
				sysCmd("sed -i 's/^" . $param . '.*/' . $param . ' ' . $value . "/' " . PEPPY_SPECTRUM_ETC_DIR . '/config.txt');
				break;
		}
	}
}
function getPeppyFolderList($type) {
	$peppyBaseDir = $type == 'meter' ? PEPPY_METER_OPT_DIR : PEPPY_SPECTRUM_OPT_DIR;
	$array = glob($peppyBaseDir . '/*/', GLOB_ONLYDIR);
	sort($array);
	return $array;
}
function getPeppyFolderContents($type, $contentDir) {
	$peppyBaseDir = $type == 'meter' ? PEPPY_METER_OPT_DIR : PEPPY_SPECTRUM_OPT_DIR;
	$configFile = $type == 'meter' ? 'meters.txt' : 'spectrum.txt';
	// Get all [name] items
	$items = sysCmd('cat ' . $peppyBaseDir . '/' . $contentDir . '/' . $configFile . ' | grep "]"');
	// Strip the brackets and add comma separator
	foreach ($items as $item) {
		$item = rtrim(ltrim($item, '['), ']');
		$itemList .= $item . ', ';
	}
	$itemList = rtrim($itemList, ', ');

	return $itemList;
}

// LCD updater
function startLcdUpdater() {
	sysCmd('/var/www/daemon/lcd-updater.sh');
}

// GPIO button handler
function startGpioBtnHandler() {
	sysCmd('/var/www/daemon/gpio_buttons.py ' . GPIOBUTTONS_SLEEP . ' > /dev/null &');
}

function setScreenBlankTimeout($timeoutValue) {
	$timeoutValueDpms = $timeoutValue == 'off' ? '0' : $timeoutValue;
	sysCmd("sed -i 's/xset s.*/xset s " . $timeoutValue . " 0/' " . $_SESSION['home_dir'] . '/.xinitrc');
	sysCmd("sed -i 's/xset dpms.*/xset dpms " . $timeoutValueDpms . " 0 0/' " . $_SESSION['home_dir'] . '/.xinitrc');
}

function cecControl($cecCmd) {
	$cecPhysicalAddress = trim(sysCmd('cec-ctl --skip-info --physical-address')[0]);
	$cecVersion = $_SESSION['hdmi_cec_ver'] == '1.4' ? '--cec-version-1.4' : '';
	sysCmd('cec-ctl --skip-info --to 0 --active-source phys-addr=' . $cecPhysicalAddress);
	sysCmd('cec-ctl --skip-info --to 0 ' . $cecVersion . ' ' . $cecCmd);
}
