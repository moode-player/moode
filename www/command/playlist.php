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

require_once dirname(__FILE__) . '/../inc/playerlib.php';

//workerLog('playlist.php: cmd=(' . $_GET['cmd'] . ')');
if (isset($_GET['cmd']) && $_GET['cmd'] === '') {
	workerLog('playlist.php: Error: $_GET cmd is empty or missing');
	exit(0);
}
/*

playerSession('open', '' ,'');
session_write_close();
$dbh = cfgdb_connect();
*/

//
// COMMANDS
//

switch ($_GET['cmd']) {
	case 'set_plcover_image':
		if (submitJob($_GET['cmd'], $_POST['name'] . ',' . $_POST['blob'], '', '')) {
			echo json_encode('job submitted');
		}
		else {
			echo json_encode('worker busy');
		}
		break;
	case 'new_playlist':
	case 'upd_playlist':
		$pl_name = html_entity_decode($_POST['path']['name']);
		$pl_meta = array('genre' => $_POST['path']['genre'], 'cover' => 'local');
		$pl_items = $_POST['path']['items'];

		put_playlist_contents($pl_name, $pl_meta, $pl_items);
		put_playlist_cover($pl_name);
		break;
	case 'add_to_playlist':
		$pl_name = html_entity_decode($_POST['path']['playlist']);
		$pl_meta = '';

		if (count($_POST['path']['items']) == 1 && strpos($_POST['path']['items'][0], '.pls') !== false) {
			// Radio station
			$result = parseStationFile(file_get_contents(MPD_MUSICROOT . $_POST['path']['items'][0]));
			$_POST['path']['items'][0] = $result['File1']; // URL
		}

		put_playlist_contents($pl_name, $pl_meta, $_POST['path']['items'], FILE_APPEND);
		put_playlist_cover ($pl_name);
		break;
	case 'del_playlist':
		sysCmd('rm "' . MPD_PLAYLIST_ROOT . html_entity_decode($_POST['path']) . '.m3u"');
		sysCmd('rm "' . PLAYLIST_COVERS_ROOT . html_entity_decode($_POST['path']) . '.jpg"');
		break;
	// Save Queue to playlist
    case 'savepl':
		$pl_name = html_entity_decode($_GET['plname']);
		$sock = get_mpd_sock();

		// Get metadata (may not exist so defaults will be returned)
		$metadata = get_playlist_metadata($pl_name);

		// Create playlist
        sendMpdCmd($sock, 'rm "' . $pl_name . '"');
		$resp = readMpdResp($sock);
        sendMpdCmd($sock, 'save "' . $pl_name . '"');
        echo json_encode(readMpdResp($sock));
		sysCmd('chmod 0777 "' . MPD_PLAYLIST_ROOT . $pl_name . '.m3u"');
		sysCmd('chown root:root "' . MPD_PLAYLIST_ROOT . $pl_name . '.m3u"');

		// Insert/Update metadata tags
		put_playlist_metadata($pl_name, array('#EXTGENRE:' . $metadata['genre'], '#EXTIMG:' . $metadata['cover']));

		// Write default image if one does not already exist
		if (!file_exists(PLAYLIST_COVERS_ROOT . $pl_name . '.jpg')) {
			sysCmd('cp /var/www/images/notfound.jpg ' . '"PLAYLIST_COVERS_ROOT' . $pl_name . '.jpg"');
		}

		echo json_encode('OK');
		break;
	case 'setfav':
		$pl_name = html_entity_decode($_GET['favname']);

		// Get metadata (may not exist so defaults will be returned)
		$metadata = get_playlist_metadata($pl_name);

		$file = '/var/lib/mpd/playlists/' . $pl_name . '.m3u';
		if (!file_exists($file)) {

			sysCmd('touch "' . $file . '"');

			sysCmd('chmod 777 "' . $file . '"');
			sysCmd('chown root:root "' . $file . '"');
		}
		else { // Ensure corrent permissions
			sysCmd('chmod 777 "' . $file . '"');
			sysCmd('chown root:root "' . $file . '"');
		}

		// Insert/Update metadata tags
		put_playlist_metadata($pl_name, array('#EXTGENRE:' . $metadata['genre'], '#EXTIMG:' . $metadata['cover']));

		// Write default image if one does not already exist
		if (!file_exists(PLAYLIST_COVERS_ROOT . $pl_name . '.jpg')) {
			sysCmd('cp /var/www/images/notfound.jpg ' . '"PLAYLIST_COVERS_ROOT' . $pl_name . '.jpg"');
		}

		playerSession('write', 'favorites_name', $_GET['favname']);

		echo json_encode('OK');
		break;
	case 'addfav':
        if (isset($_GET['favitem']) && $_GET['favitem'] != '' && $_GET['favitem'] != 'null') {
			$pl_name = $_SESSION['favorites_name'];

			// Get metadata (may not exist so defaults will be returned)
			$metadata = get_playlist_metadata($pl_name);

			// Create file if it doesn't exost
			$file = '/var/lib/mpd/playlists/' . $pl_name . '.m3u';
			if (!file_exists($file)) {
				sysCmd('touch "' . $file . '"');
			}
			// Ensure correct permissions
			sysCmd('chmod 777 "' . $file . '"');
			sysCmd('chown root:root "' . $file . '"');

			// Insert/Update metadata tags
			put_playlist_metadata($pl_name, array('#EXTGENRE:' . $metadata['genre'], '#EXTIMG:' . $metadata['cover']));

			// Write default image if one does not already exist
			if (!file_exists(PLAYLIST_COVERS_ROOT . $pl_name . '.jpg')) {
				sysCmd('cp /var/www/images/notfound.jpg ' . '"PLAYLIST_COVERS_ROOT' . $pl_name . '.jpg"');
			}

			// Append item (prevent adding duplicate)
			$result = sysCmd('fgrep "' . $_GET['favitem'] . '" "' . $file . '"');
			if (empty($result[0])) {
				sysCmd('echo "' . $_GET['favitem'] . '" >> "' . $file . '"');
			}
			echo json_encode('OK');
		}
		break;
	case 'get_pl_items_fv':
		// For Folder view
		echo json_encode(list_playlist_fv(get_mpd_sock(), $_POST['path']));
		break;
	case 'get_playlists':
		$result = get_playlists();
		echo json_encode($result);
		break;
	case 'get_playlist_contents':
		$playlist = get_playlist_contents($_POST['path'], cfgdb_connect(), get_mpd_sock());
		$array = array('id' => '-1', 'name' => $playlist['name'], 'genre' => $playlist['genre'], 'items' => $playlist['items']);
		echo json_encode($array);
		break;
}

// Close MPD socket
if (isset($sock) && $sock !== false) {
	closeMpdSock($sock);
}

//
// FUNCTIONS
//

// Return list of playlists including metadata
function get_playlists() {
	$playlists = array();

	if (false === ($files = scandir(MPD_PLAYLIST_ROOT))) {
		workerLog('get_playlists(): Directory read failed on ' . MPD_PLAYLIST_ROOT);
	}
	else {
		foreach ($files as $file) {
			if ($file != '.' && $file != '..') {
				$pl_name = basename($file, '.m3u');
				$pl_meta = get_playlist_metadata($pl_name);
				array_push($playlists, array('name' => $pl_name, 'genre' => $pl_meta['genre'], 'cover' => $pl_meta['cover']));
			}
		}
	}

	return $playlists;
}
// Return playlist metadata and items
function get_playlist_contents($pl_name, $dbh, $sock) {
	$pl_file =  MPD_PLAYLIST_ROOT . $pl_name . '.m3u';

	$genre = '';
	$cover = 'local';
	$items = array();

	if (false === ($pl_items = file($pl_file, FILE_IGNORE_NEW_LINES))) {
		workerLog('get_playlist_contents(): File read failed on ' . $pl_file);
	}
	else {
		// Parse genre and cover (first 2 lines) and create item {name, path, line2}
		foreach($pl_items as $item) {
			if (strpos($item, '#EXTGENRE') !== false) {
				$genre = explode(':', $item)[1];
			}
			elseif (strpos($item, '#EXTIMG') !== false) {
				$cover = explode(':', $item)[1];
			}
			else {
				if (substr($item, 0, 4) == 'http') {
					// Radio station
					$result = sdbquery("SELECT name FROM cfg_radio WHERE station='" . SQLite3::escapeString($item) . "'", $dbh);
					if ($result === true) {
						// Query successful but no reault, set name to URL
						$name = $item;
					}
					else {
						// Query successful and non-empty result
						$name = $result[0]['name'];
					}
					$line2 = 'Radio Station';
				}
				else {
					// Song file
					sendMpdCmd($sock, 'lsinfo "' . $item . '"');
					$tags = parseDelimFile(readMpdResp($sock), ': ');
					$name = $tags['Title'] ? $tags['Title'] : 'Unknown title';
					$line2 = ($tags['Album'] ? $tags['Album'] : 'Unknown album') . ' - ' .
						($tags['Artist'] ? $tags['Artist'] : 'Unknown artist');
				}

				array_push($items, array('name' => $name, 'path' => $item, 'line2' => $line2));
			}
		}
	}

	return array('genre' => $genre, 'cover' => $cover, 'items' => $items);
}
// Return playlist metadata
function get_playlist_metadata($pl_name) {
	// NOTE: If no tags exist in the playlist then this function returns the initial values of the tags
	$pl_file =  MPD_PLAYLIST_ROOT . $pl_name . '.m3u';

	$genre = '';
	$cover = 'local';
	$num_ext_tags = 2;

	if (false === ($fh = fopen($pl_file, 'r'))) {
		workerLog('get_playlist_metadata(): File open failed on ' . $pl_file);
	}
	else {
		while (false !== ($line = fgets($fh))) {
			if (feof($fh)) break;
			if ($num_ext_tags-- == 0) break;
			if (strpos($line, '#EXTGENRE') !== false) {
				$genre = explode(':', trim($line))[1];
			}
			elseif (strpos($line, '#EXTIMG') !== false) {
				$cover = explode(':', trim($line))[1];
			}
		}

		fclose($fh);
	}

	return array('genre' => $genre, 'cover' => $cover);
}
// Create or update playlist metadata tags
function put_playlist_metadata($pl_name, $pl_meta) {
	$pl_file =  MPD_PLAYLIST_ROOT . $pl_name . '.m3u';

	// NOTE: Is there a more efficient way?
	if (false === ($pl_items = file($pl_file, FILE_IGNORE_NEW_LINES))) {
		workerLog('put_playlist_metadata(): File read failed on ' . $pl_file);
	}
	else {
		array_splice($pl_items, 0, 0, $pl_meta);
		$contents = implode(PHP_EOL, $pl_items);
		if (false == (file_put_contents($pl_file, $contents))) {
			workerLog('put_playlist_metadata(): File write failed on ' . $pl_file);
		}
	}
}
// Create or update playlist file
function put_playlist_contents($pl_name, $pl_meta, $pl_items, $append_flag = 0) {
	$pl_file =  MPD_PLAYLIST_ROOT . $pl_name . '.m3u';

	if ($append_flag == 0) {
		$contents = '#EXTGENRE:' . $pl_meta['genre'] . "\n";
		$contents .= '#EXTIMG:' . $pl_meta['cover'] . "\n";
	}

	foreach ($pl_items as $item) {
		$contents .= $item . "\n";
	}

	if (false == (file_put_contents($pl_file, $contents, $append_flag))) {
		workerLog('put_playlist_contents(): File write failed on ' . $pl_file);
	}
	else {
		sysCmd('chmod 0777 "' . $pl_file . '"');
		sysCmd('chown root:root "' . $pl_file . '"');
	}
}
// Add cover image
function put_playlist_cover ($pl_name) {
	$pl_tmp_image = PLAYLIST_COVERS_ROOT . TMP_IMAGE_PREFIX . $pl_name . '.jpg';
	$pl_cover_image = PLAYLIST_COVERS_ROOT . $pl_name . '.jpg';
	$default_image = '/var/www/images/notfound.jpg';

	sendEngCmd('set_cover_image1'); // Show spinner
	sleep(3); // Allow time for set_plcover_image job to create __tmp__ image file

	if (file_exists($pl_tmp_image)) {
		sysCmd('mv "' . $pl_tmp_image . '" "' . $pl_cover_image . '"');
	}
	elseif (!file_exists($pl_cover_image)) {
		sysCmd('cp "' . $default_image . '" "' . $pl_cover_image . '"');
	}

	sendEngCmd('set_cover_image0'); // Hide spinner
}

// Return contents of playlist (Folder view)
function list_playlist_fv($sock, $pl_name) {
	sendMpdCmd($sock, 'listplaylist "' . $pl_name . '"');
	$pl_items = readMpdResp($sock);

	return parseList($pl_items);
}

// Return MPD socket or exit script
function get_mpd_sock() {
	if (false === ($sock = openMpdSock('localhost', 6600))) {
		workerLog('get_mpd_sock(): Connection to MPD failed');
		exit(0);
	}
	else {
		return $sock;
	}
}
