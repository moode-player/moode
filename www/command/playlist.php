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
require_once __DIR__ . '/../inc/music-library.php';
require_once __DIR__ . '/../inc/session.php';
require_once __DIR__ . '/../inc/sql.php';

const NUMBER_EXT_TAGS = 2;

switch ($_GET['cmd']) {
	case 'set_plcover_image':
		if (submitJob($_GET['cmd'], $_POST['name'] . ',' . $_POST['blob'], '', '')) {
			echo json_encode('job submitted');
		} else {
			echo json_encode('worker busy');
		}
		break;
	case 'new_playlist':
	case 'upd_playlist':
		$plName = html_entity_decode($_POST['path']['name']);

		// Get metadata (may not exist so defaults will be returned)
		$plMeta = getPlaylistMetadata($plName);
		$updPlMeta = array('genre' => $_POST['path']['genre'], 'cover' => $plMeta['cover']);
		$plItems = $_POST['path']['items'];

		// Write metadata tags, contents and cover image
		putPlaylistContents($plName, $updPlMeta, $plItems);
		putPlaylistCover($plName);
		break;
	case 'add_to_playlist':
		$plName = html_entity_decode($_POST['path']['playlist']);
		// Get metadata (may not exist so defaults will be returned)
		$plMeta = getPlaylistMetadata($plName);

		// Replace with URL if radio station
		if (count($_POST['path']['items']) == 1 && substr($_POST['path']['items'][0], -4) == '.pls') {
			$stName = substr($_POST['path']['items'][0], 6, -4); // Trim RADIO/ and .pls
			$result = sqlQuery("SELECT station FROM cfg_radio WHERE name='" . SQLite3::escapeString($stName) . "'", sqlConnect());
			$_POST['path']['items'][0] = $result[0]['station']; // URL
		}

		// Write metadata tags, contents and cover image
		putPlaylistMetadata($plName, array('#EXTGENRE:' . $plMeta['genre'], '#EXTIMG:' . $plMeta['cover']));
		putPlaylistContents($plName, $plMeta, $_POST['path']['items'], FILE_APPEND);
		putPlaylistCover($plName);
		break;
	case 'del_playlist':
		sysCmd('rm "' . MPD_PLAYLIST_ROOT . html_entity_decode($_POST['path']) . '.m3u"');
		sysCmd('rm "' . PLAYLIST_COVERS_ROOT . html_entity_decode($_POST['path']) . '.jpg"');
		break;
    case 'save_queue_to_playlist':
		$plName = html_entity_decode($_GET['name']);
		$plFile = MPD_PLAYLIST_ROOT . $plName . '.m3u';
		$sock = getMpdSock();

		// Get metadata (may not exist so defaults will be returned)
		$plMeta = getPlaylistMetadata($plName);

		// Create playlist from queue
        sendMpdCmd($sock, 'rm "' . $plName . '"');
		$resp = readMpdResp($sock);
        sendMpdCmd($sock, 'save "' . $plName . '"');
        echo json_encode(readMpdResp($sock));
		sysCmd('chmod 0777 "' . $plFile . '"');
		sysCmd('chown root:root "' . $plFile . '"');

		// Get playlist items
		if (false === ($plItems = file($plFile, FILE_IGNORE_NEW_LINES))) {
			workerLog('save_queue_to_playlist: File read failed on ' . $plFile);
		} else {
			// Write metadata tags, contents and cover image
			putPlaylistContents($plName, $plMeta, $plItems);
			putPlaylistCover($plName);
		}
		break;
	case 'get_favorites_name':
		$result = sqlRead('cfg_system', sqlConnect(), 'favorites_name');
		echo json_encode($result[0]['value']);
		break;
	case 'set_favorites_name':
		$plName = html_entity_decode($_GET['name']);
		$plFile = MPD_PLAYLIST_ROOT . $plName . '.m3u';

		// Get metadata (may not exist so defaults will be returned)
		$plMeta = getPlaylistMetadata($plName);

		// Create empty playlist if it doesn't exists
		if (!file_exists($plFile)) {
			sysCmd('touch "' . $plFile . '"');
			sysCmd('chmod 0777 "' . $plFile . '"');
			sysCmd('chown root:root "' . $plFile . '"');
		}

		// Write metadata tags and cover image
		putPlaylistMetadata($plName, array('#EXTGENRE:' . $plMeta['genre'], '#EXTIMG:' . $plMeta['cover']));
		putPlaylistCover($plName);

		phpSession('open');
		phpSession('write', 'favorites_name', $plName);
		phpSession('close');
		break;
	case 'add_item_to_favorites':
        if (isset($_GET['item']) && !empty($_GET['item'])) {
			phpSession('open_ro');
			$plName = $_SESSION['favorites_name'];

			$plFile = MPD_PLAYLIST_ROOT . $plName . '.m3u';

			// Get metadata (may not exist so defaults will be returned)
			$plMeta = getPlaylistMetadata($plName);

			// Create playlist if it doesn't exist
			if (!file_exists($plFile)) {
				sysCmd('touch "' . $plFile . '"');
				sysCmd('chmod 0777 "' . $plFile . '"');
				sysCmd('chown root:root "' . $plFile . '"');
			}

			// Write metadata tags and cover image
			putPlaylistMetadata($plName, array('#EXTGENRE:' . $plMeta['genre'], '#EXTIMG:' . $plMeta['cover']));
			putPlaylistCover($plName);

			// Append item (prevent adding duplicate)
			$result = sysCmd('fgrep "' . $_GET['item'] . '" "' . $plFile . '"');
			if (empty($result[0])) {
				sysCmd('echo "' . $_GET['item'] . '" >> "' . $plFile . '"');
			}

			// NOTE: currently only radio stations have a favorites tag
			$result = markItemAsFavorite($_GET['item']);
			debugLog($result);
		}
		break;
	case 'get_pl_items_fv': // For Folder view
		echo json_encode(listPlaylistFv($_GET['path']));
		break;
	case 'get_playlists':
		echo json_encode(getPlaylists());
		break;
	case 'get_playlist_contents':
		$playlist = getPlaylistContents($_POST['path']);
		$array = array('name' => $playlist['name'], 'genre' => $playlist['genre'], 'items' => $playlist['items']);
		echo json_encode($array);
		break;
	default:
		echo 'Unknown command';
		break;
}

// Close MPD socket
if (isset($sock) && $sock !== false) {
	closeMpdSock($sock);
}

// Return list of playlists including metadata
function getPlaylists() {
	$playlists = array();

	if (false === ($files = scandir(MPD_PLAYLIST_ROOT))) {
		workerLog('getPlaylists(): Directory read failed on ' . MPD_PLAYLIST_ROOT);
	} else {
		foreach ($files as $file) {
			if ($file != '.' && $file != '..') {
				$plName = basename($file, '.m3u');
				$plMeta = getPlaylistMetadata($plName);
				array_push($playlists, array('name' => $plName, 'genre' => $plMeta['genre'], 'cover' => $plMeta['cover']));
			}
		}
	}

	return $playlists;
}
// Return playlist metadata and items
function getPlaylistContents($plName) {
	$plFile = MPD_PLAYLIST_ROOT . $plName . '.m3u';
	$dbh = sqlConnect();
	$sock = getMpdSock();

	$genre = '';
	$cover = 'default';
	$items = array();

	if (false === ($plItems = file($plFile, FILE_IGNORE_NEW_LINES))) {
		workerLog('getPlaylistContents(): File read failed on ' . $plFile);
	} else {
		// Parse genre and cover (first 2 lines) and create item {name, path, line2}
		foreach($plItems as $item) {
			if (strpos($item, '#EXTGENRE') !== false) {
				$genre = explode(':', $item)[1];
			} else if (strpos($item, '#EXTIMG') !== false) {
				$cover = explode(':', $item)[1];
			} else {
				if (substr($item, 0, 4) == 'http') {
					// Radio station
					$result = sqlQuery("SELECT name FROM cfg_radio WHERE station='" . SQLite3::escapeString($item) . "'", $dbh);
					if ($result === true) {
						// Query successful but no result, set name to URL
						$name = $item;
					} else {
						// Query successful and non-empty result
						$name = $result[0]['name'];
					}
					$line2 = 'Radio Station';
				} else {
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
function getPlaylistMetadata($plName) {
	$plFile = MPD_PLAYLIST_ROOT . $plName . '.m3u';

	// NOTE: If no tags exist in the playlist then this function returns the initial values of the tags
	$genre = '';
	$cover = 'default';
	$numExtTags = NUMBER_EXT_TAGS;

	if (false === ($fh = fopen($plFile, 'r'))) {
		debugLog('getPlaylistMetadata(): File open failed on ' . $plFile);
	} else {
		while (false !== ($line = fgets($fh))) {
			if (feof($fh)) break;
			if ($numExtTags-- == 0) break;
			if (strpos($line, '#EXTGENRE') !== false) {
				$genre = explode(':', trim($line))[1];
			} else if (strpos($line, '#EXTIMG') !== false) {
				$cover = explode(':', trim($line))[1];
			}
		}

		fclose($fh);
	}

	return array('genre' => $genre, 'cover' => $cover);
}
// Create/update playlist file
function putPlaylistContents($plName, $plMeta, $plItems, $appendFlag = 0) {
	$plFile = MPD_PLAYLIST_ROOT . $plName . '.m3u';

	if ($appendFlag == 0) {
		$contents = '#EXTGENRE:' . $plMeta['genre'] . "\n";
		$contents .= '#EXTIMG:' . $plMeta['cover'] . "\n";
	}

	foreach ($plItems as $item) {
		$contents .= $item . "\n";
	}

	if (false == (file_put_contents($plFile, $contents, $appendFlag))) {
		workerLog('putPlaylistContents(): File write failed on ' . $plFile);
	} else {
		sysCmd('chmod 0777 "' . $plFile . '"');
		sysCmd('chown root:root "' . $plFile . '"');
	}
}
// Create/update playlist metadata
function putPlaylistMetadata($plName, $plMeta) {
	$plFile = MPD_PLAYLIST_ROOT . $plName . '.m3u';

	// NOTE: Is there a more efficient way?
	if (false === ($plItems = file($plFile, FILE_IGNORE_NEW_LINES))) {
		workerLog('putPlaylistMetadata(): File read failed on ' . $plFile);
	} else {
		array_splice($plItems, 0, NUMBER_EXT_TAGS, $plMeta);
		foreach ($plItems as $item) {
			$contents .= $item . "\n";
		}
		if (false == (file_put_contents($plFile, $contents))) {
			workerLog('putPlaylistMetadata(): File write failed on ' . $plFile);
		}
	}
}
// Add/update cover image
function putPlaylistCover($plName) {
	$plTmpImage = PLAYLIST_COVERS_ROOT . TMP_IMAGE_PREFIX . $plName . '.jpg';
	$plCoverImage = PLAYLIST_COVERS_ROOT . $plName . '.jpg';
	$defaultImage = '/var/www/images/pldefault.jpg';

	sendEngCmd('set_cover_image1'); // Show spinner
	sleep(3); // Allow time for set_plcover_image job to create __tmp__ image file

	if (file_exists($plTmpImage)) {
		sysCmd('mv "' . $plTmpImage . '" "' . $plCoverImage . '"');
		sysCmd('sed -i s/#EXTIMG:default/#EXTIMG:local/ "' . MPD_PLAYLIST_ROOT . $plName . '.m3u"');
	} else if (!file_exists($plCoverImage)) {
		sysCmd('cp "' . $defaultImage . '" "' . $plCoverImage . '"');
		// Change tag value to 'default' so renderPlaylistView() can detect that its a default image
		sysCmd('sed -i s/#EXTIMG:local/#EXTIMG:default/ "' . MPD_PLAYLIST_ROOT . $plName . '.m3u"');
	}

	sendEngCmd('set_cover_image0'); // Hide spinner
}

// Return contents of playlist (Folder view)
function listPlaylistFv($plName) {
	$sock = getMpdSock();
	sendMpdCmd($sock, 'listplaylist "' . $plName . '"');
	$resp = readMpdResp($sock);
	return formatMpdQueryResults($resp);
}

// Mark item as favorite
function markItemAsFavorite($item) {
	// Only radio stations support a favorites tag
	// NOTE: http could also be a UPnP file but no good way to tell
	if (substr($item, 0, 4) == 'http') {
		$dbh = sqlConnect();
		$result = sqlQuery("SELECT name FROM cfg_radio WHERE station='" . $item . "'", $dbh);
		if ($result === true) {
			// Query execution succeeded but no match found
			$msg = 'markItemAsFavorite(): Not in cfg_radio:' . $item;
		} else {
			$result = sqlQuery("UPDATE cfg_radio SET type='f' WHERE station='" . $item . "'", $dbh);
			$msg = 'markItemAsFavorite(): Updated: ' . $item;
		}
	} else {
		$msg = 'markItemAsFavorite(): Not a station: ' . $item;
	}

	return $msg;
}
