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
require_once 'mpd.php';
require_once 'session.php';
require_once 'sql.php';

switch ($_GET['cmd']) {
	case 'station_info':
		echo json_encode(parseStationInfo($_GET['path']));
		break;
	case 'track_info':
		$sock = getMpdSock();
		sendMpdCmd($sock,'lsinfo "' . $_GET['path'] .'"');
		echo json_encode(parseTrackInfo(readMpdResp($sock)));
		break;
}
