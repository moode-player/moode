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

chkVariables($_GET, array('path'));

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
	$tagArray = array();
	$result = sqlQuery("SELECT * FROM cfg_radio WHERE station='" . SQLite3::escapeString($path) . "'", sqlConnect());

	$tagArray[0] = array('Logo' => LOGO_ROOT_DIR . $result[0]['name'] . '.jpg');
	$tagArray[1] = array('Station name' => $result[0]['name']);
	$tagArray[2] = array('Playable URL' => $result[0]['station']);
	$tagArray[3] = array('Type' => $result[0]['type'] == 'f' ? 'Favorite' : ($result[0]['type'] == 'r' ? 'Regular' : 'Hidden'));
	$tagArray[4] = array('Genre' => $result[0]['genre']);
	$tagArray[5] = array('Broadcaster' => $result[0]['broadcaster']);
	$tagArray[6] = array('Home page' => $result[0]['home_page']);
	$tagArray[7] = array('Language' => $result[0]['language']);
	$tagArray[8] = array('Country' => $result[0]['country']);
	$tagArray[9] = array('Region' => $result[0]['region']);
	$tagArray[10] = array('Bitrate' => $result[0]['bitrate']);
	$tagArray[11] = array('Audio format' => $result[0]['format']);
	$tagArray[12] = array('Geo fenced' => $result[0]['geo_fenced']);

	return $tagArray;
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
	12 OriginalDate
	13 OriginalReleaseDate
	14 Duration
	15 Audio format
	16 Comment
	*/

	if (is_null($resp)) {
		debugLog('parseTrackInfo(): Returned null');
		return null;
	} else {
		$tagArray = array();
		$line = strtok($resp, "\n");
		$numLines = 15;

		for ($i = 0; $i < $numLines; $i++) {
			$tagArray[$i] = '';
		}

		while ($line) {
			list ($element, $origValue) = explode(': ', $line, 2);

			$value = htmlspecialchars($origValue, ENT_NOQUOTES);

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
					$file = $origValue;
					$level = stripos(dirname($file), '.cue', -4) === false ? 1 : 2;
					$cover_hash = md5(dirname($file, $level));
					$tagArray[0] = file_exists(THMCACHE_DIR . $cover_hash . '.jpg') ? array('Covers' => $cover_hash) : array('Covers' => '');
					break;
				case 'Artist':
				case 'Performer':
					$artists .= $value . ', ';
					break;
				case 'AlbumArtist':
					$tagArray[3] = array('Album artist' => $value);
					break;
				case 'Composer':
					$tagArray[4] = array($element => $value);
					break;
				case 'Conductor':
					$tagArray[5] = array($element => $value);
					break;
				case 'Genre':
					$genres .= $value . ', ';
					break;
				case 'Album':
					$tagArray[7] = array($element => $value);
					break;
				case 'Disc':
					$tagArray[8] = array($element => $value);
					break;
				case 'Track':
					$tagArray[9] = array($element => $value);
					break;
				case 'Title':
					$tagArray[10] = array($element => $value);
					break;
				case 'Date':
				case 'OriginalDate':
				case 'OriginalReleaseDate':
					// Format YYYY or YYYYMM
					$year = substr($value, 0, 4);
					$month = substr($value, 4, 2);
					$idx = $element == 'Date' ? 11 : ($element == 'OriginalDate' ? 12 : 13);
					$tagArray[$idx] = empty($month) ?
						array($element => $value) :
						array($element => MONTH_NAME[$month] . ' ' . $year);
					break;
				case 'Time':
					$tagArray[14] = array('Duration' => formatSongTime($value));
					break;
				case 'Comment':
					$tagArray[16] = array($element => $value);
					break;
			}

			$line = strtok("\n");
		}

		// File path
		$tagArray[1] = isset($file) ? array('File path' => $file) : '';
		// Artists and genres
		$tagArray[2] = !empty(rtrim($artists, ', ')) ? array('Artists' => rtrim($artists, ', ')) : '';
		$tagArray[6] = !empty(rtrim($genres, ', ')) ? array('Genres' => rtrim($genres, ', ')) : '';
		// Audio format
		$encodedAt = getEncodedAt(array('file' => $file, 'Format' => $format), 'verbose');
		$tagArray[15] = $encodedAt == 'Not playing' ? '' : array('Audio format' => $encodedAt);
	}

	return $tagArray;
}
