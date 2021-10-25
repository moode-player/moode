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

set_include_path('/var/www/inc');
require_once 'playerlib.php';

$option = isset($argv[1]) ? $argv[1] : '';
session_id(playerSession('getsessionid'));
session_start();

switch ($option) {
	case '-rx':
		if (isset($argv[2])) {
			rx_onoff($argv[2]);
			$status = '';
		}
		else {
			$status = rx_status();
		}
		break;
	case '-tx':
		$status = tx_status();
		break;
	case '-all':
		$status = all_status();
		break;
	case '-set-mpdvol':
		$rx_status_parts = explode(',', rx_status()); // rx,On/Off/Unknown,volume,mute_1/0,mastervol_opt_in_1/0
		if ($rx_status_parts[4] == '1') {
			sysCmd('/var/www/vol.sh ' . $argv[2] . (isset($argv[3]) ? ' ' . $argv[3] : ''));
		}
		$status = '';
		break;
	// This is used to set rx to 0dB when Airplay or Spotify connects to Sender
	case '-set-alsavol':
		if (isset($argv[2])) {
			if ($_SESSION['multiroom_rx'] == 'On') {
				set_alsavol($argv[2]);
			}
			$status = '';
		}
		else {
			$status = 'Missing arg';
		}
		break;
	default:
		$status = 'Missing arg';
		break;
}

session_write_close();

if ($status != '') {
	echo $status;
}
exit(0);

function rx_onoff($onoff) {
	if ($_SESSION['mpdmixer'] == 'hardware' || $_SESSION['mpdmixer'] == 'none') {
		playerSession('write', 'multiroom_rx', $onoff);
		$onoff == 'On' ? startMultiroomReceiver() : stopMultiroomReceiver();
	}
}

function rx_status() {
	$result = sdbquery("SELECT value FROM cfg_multiroom WHERE param = 'rx_mastervol_opt_in'", cfgdb_connect());
	$volume = $_SESSION['mpdmixer'] == 'none' ? '0dB' : ($_SESSION['mpdmixer'] == 'software' ? '?' : $_SESSION['volknob']);
	// rx,On/Off/Unknown,volume,mute_1/0,mastervol_opt_in_1/0
	return 'rx' . ',' . $_SESSION['multiroom_rx'] . ',' . $volume . ',' . $_SESSION['volmute'] . ',' . $result[0]['value'];
}

function tx_status() {
	$volume = $_SESSION['mpdmixer'] == 'none' ? '0dB' : $_SESSION['volknob'];
	return 'tx' . ',' . $_SESSION['multiroom_tx'] . ',' . $volume . ',' . $_SESSION['volmute'];
}

function all_status() {
	return rx_status() . ',' . tx_status();
}

function set_alsavol($vol) {
	sysCmd('/var/www/command/util.sh set-alsavol "' . $_SESSION['amixname'] . '" ' . $vol);
}
