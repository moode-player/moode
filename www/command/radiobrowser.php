<?php
/*
 * SPDX-License-Identifier: GPL-3.0-or-later
 * Copyright 2014 The moOde audio player project / Tim Curtis
 *
 * radio-browser.info AJAX endpoint: dispatches ?cmd=… to the inc/radio-browser.php
 * function library. Derived from RubaTron's Radio Browser extension (GPL-3.0-or-later).
*/

require_once __DIR__ . '/../inc/radio-browser.php';

// Validate GET/POST scalars. Station payloads for write actions arrive as JSON on
// php://input (see rbInputStation()) and are sanitised individually below.
chkVariables($_GET);
chkVariables($_POST);

$cmd = $_GET['cmd'] ?? '';
$response = array('success' => false, 'message' => 'Unknown command');

switch ($cmd) {
	case 'logo':
		rbServeLogo($_REQUEST['u'] ?? '');
		// rbServeLogo() streams the image (or 302s) and exit()s.
		break;

	case 'search':
		$params = array(
			'name' => $_REQUEST['name'] ?? '',
			'countrycode' => $_REQUEST['countrycode'] ?? '',
			'tag' => $_REQUEST['tag'] ?? '',
			'offset' => (int)($_REQUEST['offset'] ?? 0),
			'limit' => (int)($_REQUEST['limit'] ?? RADIOBROWSER_LIMIT),
			'order' => 'clickcount',
			'reverse' => 'true',
			'hidebroken' => 'true'
		);
		$params = array_filter($params, function ($v) {
			return $v !== '' && $v !== null;
		});
		$key = 'search_' . md5(json_encode($params));
		$data = rbCacheGet($key, RADIOBROWSER_CACHE_TTL);
		if ($data === false) {
			$data = rbApi('/json/stations/search', $params);
			if ($data !== false) {
				rbCacheSet($key, $data);
			} else {
				$data = rbCacheGet($key, 0); // Stale fallback if API is down
			}
		}
		if ($data !== false) {
			$response = array('success' => true, 'stations' => rbShapeResults($data, sqlConnect()), 'batch' => count($data));
		} else {
			$response = array('success' => false, 'message' => 'No results or API error');
		}
		break;

	case 'countries':
		$data = rbCacheGet('countries', RADIOBROWSER_CACHE_TTL_STATIC);
		if ($data === false) {
			$data = rbApi('/json/countries', array('hidebroken' => 'true'));
			if ($data !== false) {
				rbCacheSet('countries', $data);
			}
		}
		$response = $data !== false ?
			array('success' => true, 'countries' => $data) :
			array('success' => false, 'message' => 'API error');
		break;

	case 'genres':
		$data = rbCacheGet('genres', RADIOBROWSER_CACHE_TTL_STATIC);
		if ($data === false) {
			$data = rbApi('/json/tags', array('hidebroken' => 'true', 'order' => 'stationcount', 'reverse' => 'true', 'limit' => 200));
			if ($data !== false) {
				rbCacheSet('genres', $data);
			}
		}
		$response = $data !== false ?
			array('success' => true, 'genres' => $data) :
			array('success' => false, 'message' => 'API error');
		break;

	case 'recently_played':
		$favUrls = rbFavoriteUrls(sqlConnect());
		$stations = array();
		foreach (rbGetRecent() as $s) {
			$s['added'] = isset($favUrls[rbNormalizeUrl($s['url'])]);
			$stations[] = $s;
		}
		$response = array('success' => true, 'stations' => $stations);
		break;

	case 'add':
		$station = rbInputStation();
		$url = trim($station['url'] ?? '');
		if ($url === '' || !preg_match('#^https?://#i', $url) || str_contains($url, '"')) {
			$response = array('success' => false, 'message' => 'Invalid station URL');
			break;
		}
		$name = rbSafeName($station['name'] ?? DEFAULT_STATION_NAME);
		$dbh = sqlConnect();

		// Already in cfg_radio? Promote to favorite (idempotent), never duplicate the stream
		$existing = sqlQuery("SELECT id, type, name FROM cfg_radio WHERE station='" . SQLite3::escapeString($url) . "' LIMIT 1", $dbh);
		if (is_array($existing)) {
			if ($existing[0]['type'] == 'f') {
				$response = array('success' => true, 'message' => 'Station already in favorites');
			} else {
				sqlQuery("UPDATE cfg_radio SET type='f' WHERE id='" . $existing[0]['id'] . "'", $dbh);
				// The native Radio grid plays RADIO/<name>.pls; a promoted 'u' has none, so
				// create it (+ ensure the logo). Stock 'r' stations already ship theirs — don't overwrite.
				$exName = $existing[0]['name'];
				if (!file_exists(MPD_MUSICROOT . 'RADIO/' . $exName . '.pls')) {
					rbEnsureLogo($exName, trim($station['favicon'] ?? ''));
					rbWritePls($exName, $url);
				}
				$response = array('success' => true, 'message' => 'Station added to favorites');
			}
			break;
		}

		// New station: create the local logo files (favicon → convert, else default cover)
		rbEnsureLogo($name, trim($station['favicon'] ?? ''));

		rbWriteStation(array(
			'url' => $url,
			'name' => $name,
			'genre' => trim($station['tags'] ?? ''),
			'language' => trim($station['language'] ?? ''),
			'country' => trim($station['country'] ?? ''),
			'region' => trim($station['state'] ?? ''),
			'bitrate' => (string)(int)($station['bitrate'] ?? 0),
			'format' => trim($station['codec'] ?? ''),
			'home_page' => trim($station['homepage'] ?? '')
		));
		$response = array('success' => true, 'message' => 'Station added to favorites');
		break;

	case 'remove':
		$station = rbInputStation();
		$url = trim($station['url'] ?? '');
		if ($url === '') {
			$response = array('success' => false, 'message' => 'No station URL');
			break;
		}
		$dbh = sqlConnect();
		$row = sqlQuery("SELECT id, name FROM cfg_radio WHERE station='" . SQLite3::escapeString($url) . "' AND type='f' LIMIT 1", $dbh);
		if (!is_array($row)) {
			$response = array('success' => false, 'message' => 'Station is not in favorites');
			break;
		}
		// Core moOde stations (id < 499): just un-favorite (f -> r), keep them in the list.
		// User/imported RB stations (id >= 499): if the stream is STILL in the play queue,
		// demote to transient 'u' so now-playing/thumb keep resolving (the queue-prune deletes
		// it once it leaves the queue); only fully delete it when it's not queued anymore.
		if ((int)$row[0]['id'] < 499) {
			sqlQuery("UPDATE cfg_radio SET type='r' WHERE id='" . $row[0]['id'] . "'", $dbh);
		} else {
			$queued = rbQueuedUrls();
			if (isset($queued[rbNormalizeUrl($url)])) {
				sqlQuery("UPDATE cfg_radio SET type='u' WHERE id='" . $row[0]['id'] . "'", $dbh);
			} else {
				rbDeleteStation($row[0]['name']);
			}
		}
		$response = array('success' => true, 'message' => 'Station removed from favorites');
		break;

	case 'remove_recent':
		$station = rbInputStation();
		$url = trim($station['url'] ?? '');
		if ($url === '') {
			$response = array('success' => false, 'message' => 'No station URL');
			break;
		}
		rbRemoveRecent($url);
		$response = array('success' => true, 'message' => 'Removed from recent');
		break;

	case 'register':
		// Called when a Radio Browser tile's context menu opens: make the native queue
		// actions (Add/Play/Add next/…) resolve a not-yet-added station (logo + type='u').
		$station = rbInputStation();
		$url = trim($station['url'] ?? '');
		if ($url === '' || !preg_match('#^https?://#i', $url) || str_contains($url, '"')) {
			$response = array('success' => false, 'message' => 'Invalid station URL');
			break;
		}
		rbRegisterStation($station);
		rbPruneOrphanStations($url); // drop transient 'u' rows that have left the queue
		$response = array('success' => true, 'message' => 'Registered');
		break;

	case 'play':
		$station = rbInputStation();
		$url = trim($station['url'] ?? '');
		if ($url === '' || !preg_match('#^https?://#i', $url) || str_contains($url, '"')) {
			$response = array('success' => false, 'message' => 'Invalid station URL');
			break;
		}
		$favicon = trim($station['favicon'] ?? '');
		// Ensure logo + cfg_radio type='u' + session var (so now-playing resolves the stream)
		$reg = rbRegisterStation($station);
		$name = $reg['name'];
		$format = $reg['format'];
		$bitrate = $reg['bitrate'];
		$homepage = $reg['homepage'];

		$sock = getMpdSock('command/radiobrowser.php');
		sendMpdCmd($sock, 'addid "' . $url . '"');
		$resp = readMpdResp($sock);
		if (preg_match('/Id:\s*(\d+)/', $resp, $m)) {
			sendMpdCmd($sock, 'playid ' . $m[1]);
			readMpdResp($sock);
			$response = array('success' => true, 'message' => 'Playing: ' . $name, 'name' => $name, 'url' => $url, 'format' => $format, 'bitrate' => $bitrate, 'home_page' => $homepage);
		} else {
			$response = array('success' => false, 'message' => 'MPD addid failed');
		}
		closeMpdSock($sock);

		rbAddRecent(array(
			'name' => $name,
			'url' => $url,
			'favicon' => rbCacheImage($favicon),
			'country' => trim($station['country'] ?? ''),
			'tags' => trim($station['tags'] ?? ''),
			'bitrate' => (int)$bitrate,
			'codec' => $format,
			'stationuuid' => trim($station['stationuuid'] ?? ''),
			'played_at' => time()
		));

		rbPruneOrphanStations($url); // drop transient 'u' rows that have left the queue

		// Click tracking (fire-and-forget) — radio-browser.info best practice
		$uuid = trim($station['stationuuid'] ?? '');
		if ($uuid !== '' && preg_match('/^[0-9a-f\-]{36}$/i', $uuid)) {
			$options = array('http' => array(
				'method' => 'POST',
				'timeout' => 3.0,
				'header' => "User-Agent: " . RADIOBROWSER_UA . "\r\n"
			));
			@file_get_contents('https://' . RADIOBROWSER_API_PRIMARY . '/json/url/' . $uuid, false, stream_context_create($options));
		}
		break;

	default:
		$response = array('success' => false, 'message' => 'Unknown command');
		break;
}

echo json_encode($response);
