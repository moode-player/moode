<?php
/*
 * SPDX-License-Identifier: GPL-3.0-or-later
 * Copyright 2014 The moOde audio player project / Tim Curtis
*/

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
