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
// $displayType = meter|spectrum
function startPeppyDisplay($displayType) {
	sysCmd('mv ' . ALSA_PLUGIN_PATH . '/peppy.conf.hide ' .ALSA_PLUGIN_PATH . '/peppy.conf');
	sysCmd('/var/www/daemon/peppy-display.sh --' . $displayType . ' on');
}
function stopPeppyDisplay($displayType) {
	sysCmd('/var/www/daemon/peppy-display.sh --' . $displayType . ' off');
	sysCmd('mv ' . ALSA_PLUGIN_PATH . '/peppy.conf ' .ALSA_PLUGIN_PATH . '/peppy.conf.hide');
}
function restartPeppyDisplay($displayType) {
	sysCmd('/var/www/daemon/peppy-display.sh --' . $displayType . ' restart');
}
function restartMpdAndRenderers($resetAlsaCtl) {
	// Restart MPD
	sysCmd('systemctl stop mpd');
	if ($resetAlsaCtl === true) {
		sysCmd('alsactl clean ' . $_SESSION['cardnum']); // Clean (reset) application controls
		sysCmd('alsactl init ' . $_SESSION['cardnum']); // Initialize driver to a default state
	}
	sysCmd('systemctl start mpd');
	$sock = openMpdSock('localhost', 6600); // Ensure MPD ready to accept connections
	closeMpdSock($sock);

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

// LCD updater
function startLcdUpdater() {
	sysCmd('/var/www/daemon/lcd-updater.sh');
}

// GPIO button handler
function startGpioBtnHandler() {
	sysCmd('/var/www/daemon/gpio_buttons.py ' . GPIOBUTTONS_SLEEP . ' > /dev/null &');
}
