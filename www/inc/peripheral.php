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
function putPeppyConfig($type, $configArray) {
	$configFile = $type == 'meter' ? PEPPY_METER_ETC_DIR . '/config.txt' : PEPPY_SPECTRUM_ETC_DIR . '/config.txt';
	// Code goes here
}
function getPeppyFolderList($type) {
	$peppyBaseDir = $type == 'meter' ? PEPPY_METER_OPT_DIR : PEPPY_SPECTRUM_OPT_DIR;
	return glob($peppyBaseDir . '/*/', GLOB_ONLYDIR);
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
