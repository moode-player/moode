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
require_once __DIR__ . '/../inc/session.php';
require_once __DIR__ . '/../inc/sql.php';

switch ($_GET['cmd']) {
	case 'disconnect_renderer':
		// Squeezelite and trx-rx hog the audio output so need to be turned off in order to released it
		phpSession('open');
	 	if ($_POST['job'] == 'slsvc') {
	 		phpSession('write', 'slsvc', '0');
	 	} else if ($_POST['job'] == 'multiroom_rx') {
	 		phpSession('write', 'multiroom_rx', 'Off');
	 	}
		phpSession('close');

	 	// AirPlay, Spotify and RoonBridge are session based and so they can simply be restarted to effect a disconnect
	 	// NOTE: 'disconnect_renderer' is passed as a job queue arg and tested for in worker so that MPD play can be resumed if indicated
	 	if (submitJob($_POST['job'], $_GET['cmd'])) {
	 		echo json_encode('job submitted');
	 	} else {
	 		echo json_encode('worker busy');
	 	}
		break;
	default:
		echo 'Unknown command';
		break;
}
