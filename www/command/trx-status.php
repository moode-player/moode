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
		$status = rx_status();
		break;
	case '-tx':
		$status = tx_status();
		break;
	case '-all':
		$status = all_status();
		break;
	default:
		$status = 'Missing arg';
		break;
}

session_write_close();
echo $status;
exit(0);

function rx_status() {
	return 'rx' . ',' . $_SESSION['multiroom_rx'] . ',' . $_SESSION['volknob'] . ',' . $_SESSION['volmute'];
}

function tx_status() {
	return 'tx' . ',' . $_SESSION['multiroom_tx'] . ',' . $_SESSION['volknob'] . ',' . $_SESSION['volmute'];
}

function all_status() {
	return rx_status() . ',' . tx_status();
}
