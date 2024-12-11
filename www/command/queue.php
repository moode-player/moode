<?php
/*
 * SPDX-License-Identifier: GPL-3.0-or-later
 * Copyright 2014 The moOde audio player project / Tim Curtis
*/

require_once __DIR__ . '/../inc/common.php';
require_once __DIR__ . '/../inc/mpd.php';
require_once __DIR__ . '/../inc/music-library.php';
require_once __DIR__ . '/../inc/queue.php';
require_once __DIR__ . '/../inc/session.php';
require_once __DIR__ . '/../inc/sql.php';

$sock = getMpdSock();
phpSession('open_ro');

chkVariables($_GET);
chkVariables($_POST, array('path'));

// Turn off auto-shuffle and consume mode before Queue is updated
$queueCmds = array(
    'delete_playqueue_item', 'move_playqueue_item', 'favorite_playqueue_item',
    'add_item', 'add_item_next', 'play_item', 'play_item_next', /*'clear_add_item',*/ 'clear_play_item',
    'add_group', 'add_group_next', 'play_group', 'play_group_next', /*'clear_add_group',*/ 'clear_play_group'
);
if ($_SESSION['ashuffle'] == '1' && in_array($_GET['cmd'], $queueCmds)) {
    turnOffAutoShuffle($sock);
}

switch ($_GET['cmd']) {
	case 'get_playqueue':
		sendMpdCmd($sock, 'playlistinfo');
		$resp = readMpdResp($sock);
		echo json_encode(getPlayqueue($resp));
		break;
	case 'get_playqueue_item_tag':
		// Return the value of the "file" tag for Clock radio and Audio info
		sendMpdCmd($sock, 'playlistinfo ' . $_GET['songpos']);
		$resp = readMpdResp($sock);
		echo json_encode(getPlayqueueItemTag($resp, $_GET['tag']));
		break;
	case 'delete_playqueue_item':
		sendMpdCmd($sock, 'delete ' . $_GET['range']);
		$resp = readMpdResp($sock);
		break;
	case 'move_playqueue_item':
		sendMpdCmd($sock, 'move ' . $_GET['range'] . ' ' . $_GET['newpos']);
		$resp = readMpdResp($sock);
		break;
    case 'get_playqueue_item':
		sendMpdCmd($sock, 'playlistinfo ' . $_GET['songpos']);
        echo json_encode(parseDelimFile(readMpdResp($sock), ': ')['file']);
		break;
    case 'clear_playqueue':
        workerLog('clear_playqueue');
		sendMpdCmd($sock, 'clear');
        $resp = readMpdResp($sock);
        updLibRecentPlaylistVar('None');
		break;
	case 'add_item':
	case 'add_item_next':
        $status = getMpdStatus($sock);
        $cmds = array(addItemToQueue($_POST['path']));
        if ($_GET['cmd'] == 'add_item_next') {
         array_push($cmds, 'move ' . $status['playlistlength'] . ' ' . ($status['song'] + 1));
        }
        chainMpdCmds($sock, $cmds);
        break;
	case 'play_item':
	case 'play_item_next':
		// Search the Queue for the item
		$search = strpos($_POST['path'], 'RADIO') !== false ? parseDelimFile(file_get_contents(MPD_MUSICROOT . $_POST['path']), '=')['File1'] : $_POST['path'];
		$result = findInQueue($sock, 'file', $search);

		if (isset($result['Pos'])) {
			// Play already Queued item
			sendMpdCmd($sock, 'play ' . $result['Pos']);
			$resp = readMpdResp($sock);
		} else {
			// Otherwise play the item after adding it to the Queue
			$status = getMpdStatus($sock);
			$cmds = array(addItemToQueue($_POST['path']));
			if ($_GET['cmd'] == 'play_item_next') {
				$pos = isset($status['song']) ? $status['song'] + 1 : $status['playlistlength'];
				array_push($cmds, 'move ' . $status['playlistlength'] . ' ' . $pos);
			} else {
				$pos = $status['playlistlength'];
			}
			array_push($cmds, 'play ' . $pos);
			chainMpdCmds($sock, $cmds);
		}
		break;
	/*case 'clear_add_item':*/
	case 'clear_play_item':
	 	$cmds = array('clear');
        updLibRecentPlaylistVar('None');
		array_push($cmds, addItemToQueue($_POST['path']));
		if ($_GET['cmd'] == 'clear_play_item') {
			array_push($cmds, 'play');
		}
		chainMpdCmds($sock, $cmds);
        putToggleSongId('0');
		break;
	 // Queue commands for a group of songs: Genre, Artist or Albums in Tag/Album view
	case 'add_group':
	case 'add_group_next':
		$status = getMpdStatus($sock);
		$cmds = addGroupToQueue($_POST['path']);
		if ($_GET['cmd'] == 'add_group_next') {
			array_push($cmds, 'move ' . $status['playlistlength'] . ':' .
				($status['playlistlength'] + count($_POST['path'])) . ' ' . ($status['song'] + 1));
		}
		chainMpdCmds($sock, $cmds);
		break;
	case 'play_group':
	case 'play_group_next':
    	// Search the Queue for the group
		sendMpdCmd($sock, 'lsinfo "' . $_POST['path'][0] . '"');
		$album = parseDelimFile(readMpdResp($sock), ': ')['Album'];
		$result = findInQueue($sock, 'album', $album);
		$last = count($_POST['path']) - 1;

		if (!empty($result) && $_POST['path'][0] == $result[0]['file'] && $_POST['path'][$last] == $result[$last]['file']) {
            // Group is already in the Queue if first and last file exist sequentially
			$pos = $result[0]['Pos'];
			sendMpdCmd($sock, 'play ' . $pos);
			$resp = readMpdResp($sock);
		} else {
			// Otherwise play the group after adding it to the Queue
			$status = getMpdStatus($sock);
			$cmds = addGroupToQueue($_POST['path']);
		 	if ($_GET['cmd'] == 'play_group_next') {
				$pos = isset($status['song']) ? $status['song'] + 1 : $status['playlistlength'];
				if ($pos != 0) {
					array_push($cmds, 'move ' . $status['playlistlength'] . ':' .
						($status['playlistlength'] + count($_POST['path'])) . ' ' . ($status['song'] + 1));
				}
			} else {
				$pos = $status['playlistlength'];
			}
			array_push($cmds, 'play ' . $pos);
			chainMpdCmds($sock, $cmds);
		}
        putToggleSongId($pos);
		break;
	/*case 'clear_add_group':*/
	case 'clear_play_group':
		$cmds = array_merge(array('clear'), addGroupToQueue($_POST['path']));

		if ($_GET['cmd'] == 'clear_play_group') {
			array_push($cmds, 'play'); // Defaults to pos 0
		}

		chainMpdCmds($sock, $cmds);
        putToggleSongId('0');
		break;
    default:
		echo 'Unknown command';
		break;
}

// Close MPD socket
if (isset($sock) && $sock !== false) {
	closeMpdSock($sock);
}
/*DELETE: moved to inc/queue.php
// Return MPD queue
function getPlayqueue($resp) {
	if (is_null($resp)) {
        debugLog('getPlayqueue(): Returned null');
		return null;
	} else {
		$queue = array();
		$line = strtok($resp,"\n");
		$idx = -1;

		while ($line) {
			list ($element, $origValue) = explode(': ', $line, 2);

            $value = htmlspecialchars($origValue, ENT_NOQUOTES);

			if ($element == 'file') {
				$idx++;
				$queue[$idx]['file'] = $origValue;
                $level = stripos(dirname($origValue), '.cue', -4) === false ? 1 : 2;
                $queue[$idx]['cover_hash'] = substr($origValue, 0, 4) == 'http' ? '' : md5(dirname($origValue, $level));
				$queue[$idx]['fileext'] = getSongFileExt($origValue);
				$queue[$idx]['TimeMMSS'] = formatSongTime($queue[$idx]['Time']);
			} else {
				if ($element == 'Genre' || $element == 'Artist' || $element == 'AlbumArtist' || $element == 'Conductor' || $element == 'Performer') {
					// Return only the first of multiple occurrences of the following tags
					if (!isset($queue[$idx][$element])) {
						$queue[$idx][$element] = $value;
					}
				} else {
					// All other tags
					$queue[$idx][$element] = $value;
				}
			}

			$line = strtok("\n");
		}
	}
    // DEBUG:
    //workerLog(print_r($queue, true));
	return $queue;
}

// Return the value of the tag
function getPlayqueueItemTag($resp, $tag) {
	$tags = array();
	$line = strtok($resp, "\n");

	while ($line) {
		list($element, $value) = explode(': ', $line, 2);
		$tags[$element] = $value;
		$line = strtok("\n");
	}

	return $tags[$tag];
}

// Add one item (song file, playlist, radio station, directory) to the Queue
function addItemToQueue($path) {
	$ext = getSongFileExt($path);
	$pl_extensions = array('m3u', 'pls', 'cue');
	//workerLog('path=' . $path . ', ext=(' . $ext . ')');

	if ((in_array($ext, $pl_extensions) && !isCueTrack($path)) || isSavedPlaylist($path)) {
        // Use load for saved playlist, cue sheet, radio station
        //(!str_contains($path, '/') && !in_array($path, ROOT_DIRECTORIES))) { // saved playlist
		if (str_contains($path, 'RADIO')) {
            // Radio station special case: Check for playlist as URL
			$pls = file_get_contents(MPD_MUSICROOT . $path);
			$url = parseDelimFile($pls, '=')['File1'];
			$ext = substr($url, -4);
			if ($ext == '.pls' || $ext == '.m3u') {
				$path = $url;
			}
		}
		$cmd = 'load';
	} else {
        // Use add for song file or directory
		$cmd = 'add';
	}

	return $cmd . ' "' . html_entity_decode($path) . '"';
}

function isSavedPlaylist($path) {
    if (!str_contains($path, '/') && !in_array($path, ROOT_DIRECTORIES)) {
        phpSession('open');
        $_SESSION['lib_recent_playlist'] = $path;
        phpSession('close');
        $isSavedPlaylist = true;
    } else {
        $isSavedPlaylist = false;
    }
    return $isSavedPlaylist;
}

function updLibRecentPlaylistVar($value) {
    phpSession('open');
    $_SESSION['lib_recent_playlist'] = $value;
    phpSession('close');
}

// Add group of song files to the Queue (Tag/Album view)
function addGroupToQueue($songs) {
	$cmds = array();

	foreach ($songs as $song) {
		array_push($cmds, 'add "' . html_entity_decode($song) . '"');
	}

	return $cmds;
}

// Find a file or album in the Queue
function findInQueue($sock, $tag, $search) {
	sendMpdCmd($sock, 'playlistfind ' . $tag . ' "' . $search . '"');
	$resp = readMpdResp($sock);

	if ($resp == "OK\n") {
		return ''; // Not found
	}

	$queue = array();
	$line = strtok($resp, "\n");

	if ($tag == 'file') {
        // Return position
		while ($line) {
			list ($element, $value) = explode(": ", $line, 2);
			if ($element == 'Pos') {
				$queue['Pos'] = $value;
				break;
			}

			$line = strtok("\n");
		}
	} else if ($tag == 'album') {
        // Return files and positions
		$i = 0;
		while ($line) {
			list ($element, $value) = explode(": ", $line, 2);
			if ($element == 'file') {
				$queue[$i]['file'] = $value;
			}
			if ($element == 'Pos') {
				$queue[$i]['Pos'] = $value;
				$i++;
			}

			$line = strtok("\n");
		}
	}

	return $queue;
}

// Turn off auto-shuffle and consume mode
function turnOffAutoShuffle($sock) {
    phpSession('open');
	phpSession('write', 'ashuffle', '0');
    phpSession('close');

	sysCmd('killall -s 9 ashuffle > /dev/null');

	sendMpdCmd($sock, 'consume 0');
	$resp = readMpdResp($sock);
}

// Update toggle songid for clear_ and  commands
function putToggleSongId($pos) {
    phpSession('open');
    phpSession('write', 'toggle_songid', $pos);
    phpSession('close');
}
*/
