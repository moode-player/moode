#!/usr/bin/php
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

require_once __DIR__ . '/../inc/common.php';
require_once __DIR__ . '/../inc/mpd.php';
require_once __DIR__ . '/../inc/multiroom.php';
require_once __DIR__ . '/../inc/renderer.php';
require_once __DIR__ . '/../inc/session.php';
require_once __DIR__ . '/../inc/sql.php';

session_id(phpSession('get_sessionid'));
phpSession('open');

$option = isset($argv[1]) ? $argv[1] : '';

switch ($option) {
	case '--bluetooth':
		restartBluetooth();
		break;
	case '--airplay':
		restartAirPlay();
		break;
	case '--spotify':
		restartSpotify();
		break;
	case '--squeezelite':
		restartSqueezelite();
		break;
	case '--roonbridge':
		restartRoonbridge();
		break;
	default:
		echo
"Usage: restart-renderer [OPTION]
Moode renderer restarter

With no OPTION print the help text and exit.

 --bluetooth\tRestart bluetooth
 --airplay\tRestart airplay
 --spotify\tRestart spotify
 --squeezelite\tRestart squeezelite
 --roonbridge\tRestart roonbridge\n";
		break;
}

phpSession('close');

function restartBluetooth() {
	stopBluetooth()
	sysCmd('/var/www/util/vol.sh -restore');
	// Reset to inactive
	phpSession('write', 'btactive', '0');
	// Dismiss active screen
	sendEngCmd('btactive0');

	// Restore MPD volume and start Bluetooth
	sysCmd('/var/www/util/vol.sh -restore');
	$status = startBluetooth();
	if ($status != 'started') {
		echo $status;
	}
}

function restartAirPlay() {
	stopAirPlay();
	startAirPlay();
}

function restartSpotify() {
	stopSpotify();
	startSpotify();
}

function restartSqueezelite() {
	stopSqueezelite();
	phpSession('write', 'rsmaftersl', 'No');
	startSqueezelite();
}

function restartRoonbridge() {
	stopRoonBridge();
	startRoonBridge();
}
