<?php
/*
 * SPDX-License-Identifier: GPL-3.0-or-later
 * Copyright 2014 The moOde audio player project / Tim Curtis
*/

require_once __DIR__ . '/../inc/common.php';
require_once __DIR__ . '/../inc/mpd.php';
require_once __DIR__ . '/../inc/music-library.php';
require_once __DIR__ . '/../inc/session.php';
require_once __DIR__ . '/../inc/sql.php';

const NUMBER_EXT_TAGS = 2;

chkVariables($_GET, array('item'));
chkVariables($_POST, array('path'));

switch ($_GET['cmd']) {
	case 'export_playlist':
		// Stream a playlist's .m3u file as a download
		$plName = isset($_GET['name']) ? basename(html_entity_decode($_GET['name'])) : '';
		$plFile = MPD_PLAYLIST_ROOT . $plName . '.m3u';
		if ($plName === '' || strpos($plName, '..') !== false || !file_exists($plFile)) {
			http_response_code(404);
			exit();
		}
		header('Content-Description: File Transfer');
		header('Content-Type: audio/x-mpegurl');
		header('Content-Disposition: attachment; filename="' . $plName . '.m3u"');
		header('Content-Length: ' . filesize($plFile));
		header('Pragma: no-cache');
		header('Expires: 0');
		readfile($plFile);
		exit();
	case 'analyze_import':
		// Classify the entries of an uploaded .m3u so unknown local paths can be remapped.
		// When a 'remap' is supplied, it is applied before validating, so the same endpoint
		// also serves the modal's "Test" button (re-check paths after remapping).
		$content = isset($_POST['content']) ? $_POST['content'] : '';
		if ($content === '' || strlen($content) > 5 * 1024 * 1024) {
			echo json_encode(array('status' => 'error', 'msg' => 'File is empty or too large'));
			break;
		}
		$knownDirs = knownPlaylistDirs();
		$remap = isset($_POST['remap']) ? json_decode($_POST['remap'], true) : array();
		if (!is_array($remap)) {
			$remap = array();
		}
		foreach ($remap as $old => $new) {
			if (!in_array($new, $knownDirs, true)) {
				unset($remap[$old]);
			}
		}
		$total = 0;
		$okLocal = 0;
		$urlCount = 0;
		$remapped = 0;
		$groups = array(); // unknown prefix => count + sample
		foreach (preg_split('/\r\n|\r|\n/', $content) as $line) {
			$line = trim($line);
			if ($line === '' || isMetaEntry($line)) {
				continue;
			}
			$total++;
			if (isUrlEntry($line)) {
				$urlCount++;
				continue;
			}
			$line = applyPathRemap($line, $remap, $remapped);
			$prefix = unknownPathPrefix($line);
			if ($prefix === '') {
				$okLocal++;
			} else {
				if (!isset($groups[$prefix])) {
					$groups[$prefix] = array('prefix' => $prefix, 'count' => 0, 'sample' => $line);
				}
				$groups[$prefix]['count']++;
			}
		}
		$unknown = array();
		foreach ($groups as $g) {
			$g['suggested'] = suggestKnownDir($g['prefix'], $knownDirs);
			$unknown[] = $g;
		}
		echo json_encode(array('status' => 'ok', 'total' => $total, 'ok_local' => $okLocal,
			'url_count' => $urlCount, 'remapped' => $remapped, 'unknown' => $unknown, 'known_dirs' => $knownDirs));
		break;
	case 'import_playlist':
		// Write an uploaded .m3u as a new playlist, applying optional path remapping
		$content = isset($_POST['content']) ? $_POST['content'] : '';
		if ($content === '' || strlen($content) > 5 * 1024 * 1024) {
			echo json_encode(array('status' => 'error', 'msg' => 'File is empty or too large'));
			break;
		}
		$plName = isset($_POST['name']) ? basename(html_entity_decode($_POST['name'])) : '';
		if ($plName === '' || preg_match('/["\\\\$`\/]/', $plName) || strpos($plName, '..') !== false) {
			echo json_encode(array('status' => 'error', 'msg' => 'Invalid playlist name'));
			break;
		}
		$plFile = MPD_PLAYLIST_ROOT . $plName . '.m3u';
		if (file_exists($plFile)) {
			echo json_encode(array('status' => 'error', 'msg' => 'A playlist with this name already exists'));
			break;
		}
		// Accept only remap targets that are real known dirs (defends against crafted POSTs)
		$remap = isset($_POST['remap']) ? json_decode($_POST['remap'], true) : array();
		if (!is_array($remap)) {
			$remap = array();
		}
		$knownDirs = knownPlaylistDirs();
		foreach ($remap as $old => $new) {
			if (!in_array($new, $knownDirs, true)) {
				unset($remap[$old]);
			}
		}
		$dropInvalid = isset($_POST['drop_invalid']) && $_POST['drop_invalid'] == '1';

		$out = array();
		$imported = 0;
		$remapped = 0;
		$dropped = 0;
		foreach (preg_split('/\r\n|\r|\n/', $content) as $line) {
			$line = trim($line);
			if ($line === '') {
				continue;
			}
			if (isMetaEntry($line) || isUrlEntry($line)) {
				$out[] = $line;
				if (isUrlEntry($line)) {
					$imported++;
				}
				continue;
			}
			$line = applyPathRemap($line, $remap, $remapped);
			if (file_exists(MPD_MUSICROOT . $line)) {
				$out[] = $line;
				$imported++;
			} else if ($dropInvalid) {
				$dropped++;
			} else {
				$out[] = $line;
				$imported++;
			}
		}

		// MPD_PLAYLIST_ROOT is root-owned so stage in a www-data temp then copy via
		// sysCmd (root), matching the owner/perms convention used elsewhere here
		$tmpFile = tempnam(sys_get_temp_dir(), 'plimport');
		file_put_contents($tmpFile, implode("\n", $out) . "\n");
		sysCmd('cp "' . $tmpFile . '" "' . $plFile . '"');
		sysCmd('chmod 0777 "' . $plFile . '"');
		sysCmd('chown root:root "' . $plFile . '"');
		unlink($tmpFile);
		echo json_encode(array('status' => 'ok', 'name' => $plName,
			'imported' => $imported, 'remapped' => $remapped, 'dropped' => $dropped));
		break;
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
		// DEBUG:
		//workerLog("playlist.php: add_to_playlist\n" . print_r($_POST, true));
		$plName = html_entity_decode($_POST['path']['playlist']);

		// Get metadata (may not exist so defaults will be returned)
		$plMeta = getPlaylistMetadata($plName);

		// Replace with URL if radio station
		if (count($_POST['path']['items']) == 1 && substr($_POST['path']['items'][0], -4) == '.pls') {
			$stName = substr($_POST['path']['items'][0], 6, -4); // Trim RADIO/ and .pls
			$result = sqlQuery("SELECT station FROM cfg_radio WHERE name='" . SQLite3::escapeString($stName) . "'", sqlConnect());
			$_POST['path']['items'][0] = $result[0]['station']; // URL
		}

		// File may not exist yet (User enters new playlist name)
		$plFile = MPD_PLAYLIST_ROOT . $plName . '.m3u';
		if (!file_exists($plFile)) {
			sysCmd('touch "' . $plFile . '"');
			sysCmd('chmod 0777 "' . $plFile . '"');
			sysCmd('chown root:root "' . $plFile . '"');
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
		$sock = getMpdSock('command/playlist.php');

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
	$sock = getMpdSock('command/playlist.php');
	$genre = '';
	$cover = 'default';
	$items = array();
	$extinfTitle = null;
	if (false === ($plItems = file($plFile, FILE_IGNORE_NEW_LINES))) {
		workerLog('getPlaylistContents(): File read failed on ' . $plFile);
	} else {
		// Parse genre and cover and create item {name, path, line2}
		foreach($plItems as $item) {
			if (strpos($item, '#EXTGENRE') !== false) {
				$genre = explode(':', $item)[1];
			} else if (strpos($item, '#EXTIMG') !== false) {
				$cover = explode(':', $item)[1];
			} else if (strpos($item, '#EXTINF') !== false) {
				// Extract title from "#EXTINF:-1,Episode title here"
				$extinfTitle = strpos($item, ',') !== false ? trim(substr($item, strpos($item, ',') + 1)) : null;
			} else if (substr($item, 0, 1) === '#') {
				// Skip all other # lines (#EXTM3U, #PLAYLIST, etc.)
				continue;
			} else {
				if (substr($item, 0, 4) == 'http') {
					// Radio station or podcast episode
					if ($extinfTitle) {
						// Use title from preceding #EXTINF line
						$name = $extinfTitle;
						$extinfTitle = null;
					} else {
						$result = sqlQuery("SELECT name FROM cfg_radio WHERE station='" . SQLite3::escapeString($item) . "'", $dbh);
						if ($result === true) {
							// Query successful but no result, set name to URL
							$name = $item;
						} else {
							// Query successful and non-empty result
							$name = $result[0]['name'];
						}
					}
					$line2 = 'Radio Station';
				} else {
					// Song file
					sendMpdCmd($sock, 'lsinfo "' . escapeDblQuotes($item) . '"');
					$tags = parseDelimFile(readMpdResp($sock), ': ');
					$name = $tags['Title'] ? $tags['Title'] : 'Unknown title';
					$line2 = ($tags['Album'] ? $tags['Album'] : 'Unknown album') . ' - ' .
							($tags['Artist'] ? $tags['Artist'] : 'Unknown artist');
					$extinfTitle = null;
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
	$defaultImage = DEFAULT_PLAYLIST_COVER;

	sendFECmd('set_cover_image1'); // Show spinner
	sleep(3); // Allow time for set_plcover_image job to create __tmp__ image file

	if (file_exists($plTmpImage)) {
		sysCmd('mv "' . $plTmpImage . '" "' . $plCoverImage . '"');
		sysCmd('sed -i s/#EXTIMG:default/#EXTIMG:local/ "' . MPD_PLAYLIST_ROOT . $plName . '.m3u"');
	} else if (!file_exists($plCoverImage)) {
		sysCmd('cp "' . $defaultImage . '" "' . $plCoverImage . '"');
		// Change tag value to 'default' so renderPlaylistView() can detect that its a default image
		sysCmd('sed -i s/#EXTIMG:local/#EXTIMG:default/ "' . MPD_PLAYLIST_ROOT . $plName . '.m3u"');
	}

	sendFECmd('set_cover_image0'); // Hide spinner
}

// Return contents of playlist (Folder view)
function listPlaylistFv($plName) {
	$sock = getMpdSock('command/playlist.php');
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

// Playlist import helpers (path validation and remapping)

// A metadata line (#EXTGENRE, #EXTIMG, #EXTM3U, #EXTINF, ...)
function isMetaEntry($line) {
	return $line !== '' && $line[0] == '#';
}
// A remote stream / radio station (never path-rewritten)
function isUrlEntry($line) {
	return preg_match('#^https?://#i', $line) == 1;
}
// Longest existing prefix of a local entry + the first missing segment, or '' if
// the whole path resolves under the MPD music root (= present in the Folder view)
function unknownPathPrefix($path) {
	$accum = '';
	foreach (explode('/', $path) as $seg) {
		$test = $accum == '' ? $seg : $accum . '/' . $seg;
		if (file_exists(MPD_MUSICROOT . $test)) {
			$accum = $test;
		} else {
			return $test;
		}
	}
	return '';
}
// Known roots and their immediate subdirs (the Folder view roots/shares), depth <= 2
function knownPlaylistDirs() {
	$dirs = array();
	foreach (ROOT_DIRECTORIES as $root) {
		if ($root == 'RADIO') {
			continue;
		}
		$rootPath = MPD_MUSICROOT . $root;
		if (is_dir($rootPath)) {
			$dirs[] = $root;
			foreach (scandir($rootPath) as $sub) {
				if ($sub != '.' && $sub != '..' && is_dir($rootPath . '/' . $sub)) {
					$dirs[] = $root . '/' . $sub;
				}
			}
		}
	}
	return $dirs;
}
// Best replacement guess for an unknown prefix: a single same-root share, else ''
function suggestKnownDir($prefix, $knownDirs) {
	$root = explode('/', $prefix)[0];
	$shares = array();
	$bareRoot = array();
	foreach ($knownDirs as $dir) {
		if ($dir === $root) {
			$bareRoot[] = $dir;
		} else if (strpos($dir, $root . '/') === 0) {
			$shares[] = $dir;
		}
	}
	if (count($shares) == 1) {
		return $shares[0];
	}
	if (count($shares) == 0 && count($bareRoot) == 1) {
		return $bareRoot[0];
	}
	return '';
}
// Apply the longest matching prefix remap rule to a local entry
function applyPathRemap($path, $remap, &$remapped) {
	foreach ($remap as $old => $new) {
		if ($old === '' || $new === '') {
			continue;
		}
		if ($path === $old || strpos($path, $old . '/') === 0) {
			$remapped++;
			return $new . substr($path, strlen($old));
		}
	}
	return $path;
}
