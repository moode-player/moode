<?php
/*
 * SPDX-License-Identifier: GPL-3.0-or-later
 * Copyright 2014 The moOde audio player project / Tim Curtis
*/

require_once __DIR__ . '/../inc/alsa.php';
require_once __DIR__ . '/../inc/common.php';
require_once __DIR__ . '/../inc/mpd.php';
require_once __DIR__ . '/../inc/music-library.php';
require_once __DIR__ . '/../inc/session.php';
require_once __DIR__ . '/../inc/sql.php';

switch ($_GET['cmd']) {
	case 'station_info':
		echo json_encode(parseStationInfo($_GET['path']));
		break;
	case 'track_info':
		$sock = getMpdSock();
		sendMpdCmd($sock,'lsinfo "' . $_GET['path'] .'"');
		echo json_encode(parseTrackInfo(readMpdResp($sock)));
		break;
	default:
		echo 'Unknown command';
		break;
}

// Parse station info
function parseStationInfo($path) {
	$array = array();
	$result = sqlQuery("SELECT * FROM cfg_radio WHERE station='" . SQLite3::escapeString($path) . "'", sqlConnect());

	$array[0] = array('Logo' => LOGO_ROOT_DIR . $result[0]['name'] . '.jpg');
	$array[1] = array('Station name' => $result[0]['name']);
	$array[2] = array('Playable URL' => $result[0]['station']);
	$array[3] = array('Type' => $result[0]['type'] == 'f' ? 'Favorite' : ($result[0]['type'] == 'r' ? 'Regular' : 'Hidden'));
	$array[4] = array('Genre' => $result[0]['genre']);
	$array[5] = array('Broadcaster' => $result[0]['broadcaster']);
	$array[6] = array('Home page' => $result[0]['home_page']);
	$array[7] = array('Language' => $result[0]['language']);
	$array[8] = array('Country' => $result[0]['country']);
	$array[9] = array('Region' => $result[0]['region']);
	$array[10] = array('Bitrate' => $result[0]['bitrate']);
	$array[11] = array('Audio format' => $result[0]['format']);
	$array[12] = array('Geo fenced' => $result[0]['geo_fenced']);

	return $array;
}

function parseTrackInfo($resp) {
	//workerLog('parseTrackInfo(): (' . $resp . ')');
	/* Layout
	0  Cover url
	1  File path
	2  Artists
	3  Album artist
	4  Composer
	5  Conductor
	6  Genres
	7  Album
	8  Disc
	9  Track
	10 Title
	11 Date
	12 Duration
	13 Audio format
	14 Comment
	*/

	if (is_null($resp)) {
		debugLog('parseTrackInfo(): Returned null');
		return null;
	} else {
		$array = array();
		$line = strtok($resp, "\n");
		$numLines = 14;

		for ($i = 0; $i < $numLines; $i++) {
			$array[$i] = '';
		}

		while ($line) {
			list ($element, $value) = explode(': ', $line, 2);

			switch ($element) {
				// Not needed for display
				case 'duration':
				case 'Last-Modified':
					break;
				case 'Format':
					$format = $value;
					break;
				// All others
				case 'file':
					$file = $value;
					$level = stripos(dirname($file), '.cue', -4) === false ? 1 : 2;
					$cover_hash = md5(dirname($file, $level));
					$array[0] = file_exists(THMCACHE_DIR . $cover_hash . '.jpg') ? array('Covers' => $cover_hash) : array('Covers' => '');
					break;
				case 'Artist':
				case 'Performer':
					$artists .= $value . ', ';
					break;
				case 'AlbumArtist':
					$array[3] = array('Album artist' => $value);
					break;
				case 'Composer':
					$array[4] = array($element => $value);
					break;
				case 'Conductor':
					$array[5] = array($element => $value);
					break;
				case 'Genre':
					$genres .= $value . ', ';
					break;
				case 'Album':
					$array[7] = array($element => htmlspecialchars($value));
					break;
				case 'Disc':
					$array[8] = array($element => $value);
					break;
				case 'Track':
					$array[9] = array($element => $value);
					break;
				case 'Title':
					$array[10] = array($element => $value);
					break;
				case 'Date':
					// Format YYYY or YYYYMM
					$year = substr($value, 0, 4);
					$month = substr($value, 4, 2);
					$array[11] = empty($month) ?
						array($element => $value) :
						array($element => MONTH_NAME[$month] . ' ' . $year);
					break;
				case 'Time':
					$array[12] = array('Duration' => formatSongTime($value));
					break;
				case 'Comment':
					$array[14] = array($element => $value);
					break;
			}

			$line = strtok("\n");
		}

		// File path
		$array[1] = isset($file) ? array('File path' => $file) : array('File path' => 'Not playing');
		// Artists and genres
		$array[2] = !empty(rtrim($artists, ', ')) ? array('Artists' => rtrim($artists, ', ')) : '';
		$array[6] = !empty(rtrim($genres, ', ')) ? array('Genres' => rtrim($genres, ', ')) : '';
		// Audio format
		$encodedAt = getEncodedAt(array('file' => $file, 'Format' => $format), 'verbose');

		$array[13] = $encodedAt == 'Not playing' ? '' : array('Audio format' => $encodedAt);
	}

	return $array;
}
