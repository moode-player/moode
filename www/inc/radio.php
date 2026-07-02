<?php
/*
 * SPDX-License-Identifier: GPL-3.0-or-later
 * Copyright 2026 The moOde audio player project / Tim Curtis
*/

require_once __DIR__ . '/common.php';
require_once __DIR__ . '/session.php';
require_once __DIR__ . '/sql.php';

function getRadioCoverUrl($title, $station = 'None') {
	// DEBUG:
	//workerLog('getRadioCoverUrl(): title|station: ' . $title . ' | ' . $station);

	$title = html_entity_decode($title);

	// Check cache first
	$dbh = sqlConnect();
	$cachedUrl = sqlQuery("SELECT cover_url FROM cfg_rcucache WHERE title='" . SQLite3::escapeString($title) . "'", $dbh);
	if (!empty($cachedUrl[0])) { // URL, 'None' or ''
		// DEBUG: Report cached cover URL used
		workerLog('getRadioCoverUrl(): Returned cached URL for: ' . $title);
		return $cachedUrl[0]['cover_url'];
	}

	// Search provider
	phpSession('open_ro');
	switch ($_SESSION['radio_covers']) {
		case 'Radio Cover+':
			$coverUrl = radioCoverPlus($title, $station);
			break;
		case 'iTunes':
			$coverUrl = searchItunes($title, $_SESSION['radiocover_query_timeout']);
			break;
		default:
			workerLog('getRadioCoverUrl(): WARNING: Session var "radio_covers" is empty');
	}

	// Update cache
	sqlQuery("INSERT OR IGNORE INTO cfg_rcucache VALUES " .
		'(NULL,' . "'" . SQLite3::escapeString($title) . "', '" . $coverUrl . "'" . ')', $dbh);

	return $coverUrl;
}

function radioCoverPlus($title, $station) {
	// DEBUG:
	//workerLog('radioCoverPlus(): Begin');
	//workerLog('radioCoverPlus(): ' . $title . ' | ' . $station);
	$coverUrl = sysCmd('/var/www/util/radiocover_plus.py ' .
		'--title "' . $title . '" ' .
		'--station "' . $station . '"'
		)[0];

	// DEBUG:
	//workerLog('radioCoverPlus(): ' . (empty($coverUrl) ? 'No cover found' : "Cover:\n" . $coverUrl));
	return $coverUrl;
}

function searchItunes($title, $timeout) {
	// DEBUG:
	//workerLog('searchItunes(): Begin');
	//workerLog('searchItunes(): ' . $title);
	$titleParts = explode(' - ', $title); // $titleParts[0]: Artist name, $titleParts[1]: Track title
	$coverUrl = sysCmd('/var/www/util/itunescover.py ' .
		'--artist "' . $titleParts[0] . '" ' .
		'--title "' . $titleParts[1] . '" ' .
		'--timeout ' .  $timeout
		)[0];

	// DEBUG:
	//workerLog('searchItunes(): ' . (empty($coverUrl) ? 'No cover found' : "Cover:\n" . $coverUrl));
	return $coverUrl;
}
// PHP version of /var/www/util/itunescover.py
function __searchItunes($title) {
	// DEBUG:
	//workerLog('searchItunes(): Begin');
	// Create search query
	$trackLimit = '10'; // Max number of tracks to return from iTunes query
	$titleParts = explode(' - ', $title); // $titleParts[0]: Artist name, $titleParts[1]: Track title
	$query = '?term=' . urlencode($titleParts[0] . ' ' . $titleParts[1]) .
		'&media=music&entity=musicTrack&limit=' . $trackLimit;
	$apiUrl = ITUNES_API_BASE_URL . $query;

	// Get stream timeout, same for both connect and readdata
	$timeout = $_SESSION['radiocover_query_timeout'] . '.0';
	$options = array(
		'http' => array(
			'protocol_version' => (float)'1.1',
			'timeout' => (float)$timeout
		)
	);

	// Submit query to iTunes
	$result = file_get_contents($apiUrl, false, stream_context_create($options));
	if ($result === false) {
		$msg = 'Search failed for: ' . $title;
		$coverUrl = 'None';
	} else {
		$resultArray = json_decode($result, true);
		if ($resultArray['resultCount'] == '0') {
			$msg = 'Search returned 0 results for: ' . $title;
			$coverUrl = 'None';
		} else {
			// DEBUG: Report result count and/or full results
			//workerLog('searchItunes(): - Returned ' . $resultArray['resultCount'] . ' results');
			//workerLog('searchItunes(): - Full results:' . "\n" . print_r($resultArray['results'] ,true));
			$coverUrl = 'None';
			$i = 0;
			foreach ($resultArray['results'] as $result) {
				// DEBUG: Find artist match in results
				//workerLog('searchItunes(): - Checking result[' . $i . '] album: ' . $result['collectionName']);
				$itunesArtist = strtolower(str_replace($result['artistName'], ' ', ''));
				$titleArtist = strtolower(str_replace($titleParts[0], ' ', ''));
				if ($titleArtist == $itunesArtist) {
					$coverUrl = str_replace('100x100', '1000x1000', $resultArray['results'][$i]['artworkUrl100']);
					$msg = 'Search successful for: ' . $title  . "\n" .
						'Cover: ' . $coverUrl . "\n" .
						'Query: ' . $apiUrl;
					// DEBUG: Report artist match
					//workerLog('searchItunes(): - Artist match found');
					break;
				}
			}
		}
	}

	// DEBUG: Report result
	//workerLog('searchItunes(): ' . $msg);

	return $coverUrl; // URL or 'None'
}

function getRadioCoverUrlCacheCount() {
	sqlQuery("SELECT count() FROM cfg_rcucache", sqlConnect());
}

function clearRadioCoverUrlCache() {
	sqlQuery("DELETE FROM cfg_rcucache", sqlConnect());
}
