<?php
/*
 * SPDX-License-Identifier: GPL-3.0-or-later
 * Copyright 2026 The moOde audio player project / Tim Curtis
 * Copyright 2026 RadioBrowser extension   / @rubatron
 *	https://github.com/rubatron/RadioBrowser/tree/main
 * Copyright 2026 RadioBrowser integration / @Gjuju
 *	https://github.com/moode-player/moode/commit/910bee751a1f65fa80b1cd44383bc9450cacba19
 *
 * Radio browser function library.
 * Derived from @rubatron's RadioBrowser extension for moOde and re-implemented
 * using moOde's native PHP back-end conventions.
*/

require_once __DIR__ . '/common.php';
require_once __DIR__ . '/mpd.php';
require_once __DIR__ . '/session.php';
require_once __DIR__ . '/sql.php';

// HTTP GET with moOde's stream-context convention (no curl in core)
function rbHttpGet($url, $timeout = 10, $acceptJson = false) {
	$header = "User-Agent: " . RADIOBROWSER_UA . "\r\n";
	if ($acceptJson) {
		$header .= "Accept: application/json\r\n";
	}
	$options = array('http' => array(
		'method' => 'GET',
		'protocol_version' => (float)'1.1',
		'timeout' => (float)$timeout,
		'header' => $header,
		'follow_location' => 1,
		'max_redirects' => 3
	));
	// The context 'timeout' only bounds the READ; the http:// wrapper uses
	// default_socket_timeout for the CONNECT. Without bounding it, a favicon on a
	// dead/silent host hangs up to 60s and search (30 fetches) stalls for minutes.
	// Cap both so each fetch is capped at ~$timeout, like the plugin's cURL TIMEOUT.
	$prevSocketTimeout = ini_set('default_socket_timeout', (string)(int)ceil($timeout));
	$data = @file_get_contents($url, false, stream_context_create($options));
	if ($prevSocketTimeout !== false) {
		ini_set('default_socket_timeout', $prevSocketTimeout);
	}
	return $data;
}

// Discover the current API server list, per radio-browser.info guidance ("get a list
// of the servers", "names may change"): DNS SRV, then HTTP /json/servers, then the
// round-robin alias as last resort. Cached 12h; shuffled each call to spread load.
function rbGetServers() {
	$servers = rbCacheGet('servers', RADIOBROWSER_CACHE_TTL_STATIC);
	if (!is_array($servers) || !count($servers)) {
		$servers = array();
		$records = @dns_get_record(RADIOBROWSER_API_SRV, DNS_SRV);
		if (is_array($records)) {
			foreach ($records as $r) {
				if (!empty($r['target'])) { $servers[] = $r['target']; }
			}
		}
		if (!count($servers)) {
			$resp = rbHttpGet('https://' . RADIOBROWSER_API_PRIMARY . '/json/servers', 10, true);
			$list = ($resp !== false && $resp !== '') ? json_decode($resp, true) : null;
			if (is_array($list)) {
				foreach ($list as $s) {
					if (!empty($s['name'])) { $servers[] = $s['name']; }
				}
			}
		}
		$servers = array_values(array_unique($servers));
		if (!count($servers)) {
			return array(RADIOBROWSER_API_PRIMARY); // last resort, not cached
		}
		rbCacheSet('servers', $servers);
	}
	shuffle($servers);
	return $servers;
}

// Call the radio-browser.info JSON API with automatic mirror failover
function rbApi($endpoint, $params = array(), $timeout = 10) {
	$query = http_build_query($params);
	// Always try the round-robin alias first (fast, health-balanced), then the
	// dynamically discovered individual servers as failover.
	$servers = array_values(array_unique(array_merge(array(RADIOBROWSER_API_PRIMARY), rbGetServers())));
	foreach ($servers as $srv) {
		$url = 'https://' . $srv . $endpoint . ($query ? '?' . $query : '');
		$resp = rbHttpGet($url, $timeout, true);
		if ($resp !== false && $resp !== '') {
			$data = json_decode($resp, true);
			if ($data !== null) {
				return $data;
			}
		}
	}
	return false;
}

// Normalise a stream URL for identity comparison (scheme/trailing slash/case insensitive)
function rbNormalizeUrl($url) {
	$u = trim(strtolower($url));
	$u = preg_replace('#^https?://#', '', $u);
	$u = rtrim($u, '/');
	return $u;
}

// Make a station name safe for use as a filesystem/shell path component.
// moOde keeps station names verbatim as the logo/.pls filename (validateInput only
// SQL-escapes; putStationCover uses the raw name), and the now-playing renderer looks
// the logo up by that exact name — so we must NOT strip characters moOde keeps (notably
// '&', which is literal inside the double-quoted sysCmd paths). Only remove path
// separators and the few chars that break a double-quoted shell arg or a file write.
function rbSafeName($name) {
	$safe = preg_replace('#[/\\\\"`$\x00-\x1f]#', '', (string)$name);
	$safe = trim(preg_replace('/\s+/', ' ', $safe));
	$safe = substr($safe, 0, 128);
	return $safe !== '' ? $safe : DEFAULT_STATION_NAME;
}

// Read a station object from the JSON request body (fallback to POST fields)
function rbInputStation() {
	$st = json_decode(file_get_contents('php://input'), true);
	if (!is_array($st)) {
		$st = $_POST;
	}
	return is_array($st) ? $st : array();
}

// Simple file-based JSON response cache
function rbCacheGet($key, $ttl) {
	$file = RADIOBROWSER_CACHE . '/' . $key . '.json';
	if (file_exists($file) && ($ttl === 0 || (time() - filemtime($file) < $ttl))) {
		$data = json_decode(file_get_contents($file), true);
		return $data === null ? false : $data;
	}
	return false;
}
function rbCacheSet($key, $data) {
	@file_put_contents(RADIOBROWSER_CACHE . '/' . $key . '.json', json_encode($data));
}

// --- Station logo handling (ported from RubaTron's Radio Browser api.php) --------------
// Creates the three JPGs moOde expects for a radio station logo, synchronously (so they
// exist before the stream plays / the tile renders): <name>.jpg (400), thumbs/<name>.jpg
// (200), thumbs/<name>_sm.jpg (80). This is the plugin's rb_save_permanent_logo mechanism.

// Fetch raw image bytes from an http(s) URL or a local /var/local/www cache path
function rbFetchImageData($favicon) {
	if ($favicon === '') {
		return false;
	}
	if (preg_match('#^https?://#i', $favicon)) {
		$data = rbHttpGet($favicon, 8);
		return ($data !== false && strlen($data) > 100) ? $data : false;
	}
	// Local same-origin path (e.g. a cached favicon under imagesw/rb-logos/)
	$local = '/var/local/www/' . ltrim($favicon, '/');
	$real = realpath($local);
	if ($real !== false && str_starts_with($real, '/var/local/www/imagesw/') && is_file($real)) {
		$data = file_get_contents($real);
		return ($data !== false && strlen($data) > 100) ? $data : false;
	}
	return false;
}

// Resize a GD image to a square (white background), save as JPG
function rbResizeAndSave($src, $srcW, $srcH, $size, $outPath, $quality = 85) {
	$canvas = imagecreatetruecolor($size, $size);
	$white = imagecolorallocate($canvas, 255, 255, 255);
	imagefill($canvas, 0, 0, $white);
	$scale = min($size / $srcW, $size / $srcH);
	$newW = (int)($srcW * $scale);
	$newH = (int)($srcH * $scale);
	$x = (int)(($size - $newW) / 2);
	$y = (int)(($size - $newH) / 2);
	imagecopyresampled($canvas, $src, $x, $y, 0, 0, $newW, $newH, $srcW, $srcH);
	$result = imagejpeg($canvas, $outPath, $quality);
	$saved = @imagejpeg($canvas, $outPath, $quality);
	imagedestroy($canvas);
	return $saved;
}

// Save station logo (400/200/80) to moOde's radio-logos folder. Returns true if all saved.
function rbSaveLogo($name, $imageData) {
	// The RADIO_LOGOS_ROOT dirs are owned by root so use worker.php job processor which runs as root
	phpSession('open');
	submitJob('set_rblogo_image', $name . ',' . $imageData);
	phpSession('close');
	waitWorker('rbSaveLogo');
	return true;
}

// Ensure the station has local logo files: download+convert the favicon, else copy the
// moOde default cover. No-op if the small thumb already exists. Runs before play/add so
// moOde's native now-playing/playqueue renderer never requests a missing logo (no 404).
function rbEnsureLogo($name, $favicon) {
	if (file_exists(RADIO_LOGOS_ROOT . 'thumbs/' . $name . '_sm.jpg')) {
		return;
	}

	$saved = false;
	if ($favicon !== '' && !str_contains($favicon, 'encrypted-tbn0.gstatic.com')) {
		$data = rbFetchImageData($favicon);
		if ($data !== false) {
			$saved = rbSaveLogo($name, $data);
		}
	}

	if (!$saved) {
		sysCmd('cp "' . DEFAULT_NOTFOUND_COVER . '" "'  . RADIO_LOGOS_ROOT . $name . '.jpg"');
		sysCmd('cp "' . DEFAULT_NOTFOUND_COVER . '" "'  . RADIO_LOGOS_ROOT . 'thumbs/' . $name . '.jpg"');
		sysCmd('cp "' . DEFAULT_NOTFOUND_COVER . '" "'  . RADIO_LOGOS_ROOT . 'thumbs/' . $name . '_sm.jpg"');
	}
}

// Recently played history (file-based, most-recent-first)
function rbGetRecent() {
	if (!file_exists(RADIOBROWSER_RECENT_FILE)) {
		return array();
	}
	$data = json_decode(@file_get_contents(RADIOBROWSER_RECENT_FILE), true);
	return is_array($data) ? $data : array();
}
function rbAddRecent($station) {
	$fp = @fopen(RADIOBROWSER_RECENT_FILE, 'c+');
	if (!$fp) {
		return;
	}
	if (!flock($fp, LOCK_EX)) {
		fclose($fp);
		return;
	}
	$content = stream_get_contents($fp);
	$list = ($content !== '' && ($d = json_decode($content, true)) && is_array($d)) ? $d : array();
	$url = trim($station['url']);
	$list = array_values(array_filter($list, function ($item) use ($url) {
		return $item['url'] !== $url;
	}));
	array_unshift($list, $station);
	$list = array_slice($list, 0, RADIOBROWSER_RECENT_MAX);
	ftruncate($fp, 0);
	rewind($fp);
	fwrite($fp, json_encode($list, JSON_PRETTY_PRINT));
	fflush($fp);
	flock($fp, LOCK_UN);
	fclose($fp);
}
function rbRemoveRecent($url) {
	$fp = @fopen(RADIOBROWSER_RECENT_FILE, 'c+');
	if (!$fp) {
		return;
	}
	if (!flock($fp, LOCK_EX)) {
		fclose($fp);
		return;
	}
	$content = stream_get_contents($fp);
	$list = ($content !== '' && ($d = json_decode($content, true)) && is_array($d)) ? $d : array();
	$url = trim($url);
	$list = array_values(array_filter($list, function ($item) use ($url) {
		return $item['url'] !== $url;
	}));
	ftruncate($fp, 0);
	rewind($fp);
	fwrite($fp, json_encode($list, JSON_PRETTY_PRINT));
	fflush($fp);
	flock($fp, LOCK_UN);
	fclose($fp);
}

// Normalised URL set of the user's favorites (cfg_radio type='fb')
function rbFavoriteUrls($dbh) {
	$urls = array();
	$rows = sqlQuery("SELECT station FROM cfg_radio WHERE type='fb'", $dbh);
	if (is_array($rows)) {
		foreach ($rows as $r) {
			$urls[rbNormalizeUrl($r['station'])] = true;
		}
	}
	return $urls;
}

// Map a radio-browser.info result to the fields the UI needs, dedup and mark favorites
function rbShapeResults($data, $dbh) {
	$favUrls = rbFavoriteUrls($dbh);
	$deduped = array();
	$index = array();
	foreach ($data as $s) {
		$url = trim($s['url_resolved'] ?? $s['url'] ?? '');
		if ($url === '') {
			continue;
		}
		$key = rbNormalizeUrl($url);
		// Return the raw favicon URL — do NOT cache inline here. Caching 30 external
		// images synchronously in one request stalls search for tens of seconds (a
		// slow/dead host blocks the whole loop). The tile <img> instead points at the
		// same-origin 'logo' proxy below (rbServeLogo), so images are fetched+cached
		// per-image and in parallel by the browser, on demand.
		$favicon = trim($s['favicon'] ?? '');
		$station = array(
			'name' => trim($s['name'] ?? ''),
			'url' => $url,
			'favicon' => $favicon,
			'homepage' => trim($s['homepage'] ?? ''),
			'country' => trim($s['country'] ?? ''),
			'countrycode' => trim($s['countrycode'] ?? ''),
			'state' => trim($s['state'] ?? ''),
			'language' => trim($s['language'] ?? ''),
			'tags' => trim($s['tags'] ?? ''),
			'codec' => trim($s['codec'] ?? ''),
			'bitrate' => (int)($s['bitrate'] ?? 0),
			'stationuuid' => trim($s['stationuuid'] ?? ''),
			'added' => isset($favUrls[$key])
		);
		if (!isset($index[$key])) {
			$index[$key] = count($deduped);
			$deduped[] = $station;
		} else if ($deduped[$index[$key]]['favicon'] === '' && $favicon !== '') {
			$deduped[$index[$key]] = $station;
		}
	}
	return $deduped;
}

// Insert a favorite station into cfg_radio + session var + .pls, then update MPD.
// NOTE: mirrors the native new_station path in command/radio.php (kept self-contained
// so this feature stays purely additive and does not modify radio.php).
function rbWriteStation($s) {
	$dbh = sqlConnect();
	$values =
		'NULL,' .
		"'" . SQLite3::escapeString($s['url']) . "'," .
		"'" . SQLite3::escapeString($s['name']) . "'," .
		"'fb'," .
		"'local'," .
		"\"" . SQLite3::escapeString($s['genre']) . "\"," .
		"''," .
		"'" . SQLite3::escapeString($s['language']) . "'," .
		"'" . SQLite3::escapeString($s['country']) . "'," .
		"'" . SQLite3::escapeString($s['region']) . "'," .
		"'" . SQLite3::escapeString($s['bitrate']) . "'," .
		"'" . SQLite3::escapeString($s['format']) . "'," .
		"'No'," .
		"'" . SQLite3::escapeString($s['home_page']) . "'," .
		"'No'";
	sqlQuery('INSERT INTO cfg_radio VALUES (' . $values . ')', $dbh);

	phpSession('open');
	$_SESSION[$s['url']] = array(
		'name' => $s['name'],
		'type' => 'fb',
		'logo' => 'local',
		'bitrate' => $s['bitrate'],
		'format' => $s['format'],
		'home_page' => $s['home_page'],
		'monitor' => 'No'
	);
	phpSession('close');

	rbWritePls($s['name'], $s['url']);
}

// Create the RADIO/<name>.pls that the native Radio view plays (data-path="RADIO/<name>.pls").
// Factored out of rbWriteStation so promoting a played 'rb' station to favorite can also
// create it (a 'rb' has a logo but no .pls, so without this the promoted favorite won't play).
function rbWritePls($name, $url) {
	$plsFile = MPD_MUSICROOT . 'RADIO/' . $name . '.pls';
	$contents = "[playlist]\nFile1=" . $url . "\nTitle1=" . $name . "\nLength1=-1\nNumberOfEntries=1\nVersion=2\n";
	file_put_contents($plsFile, $contents);
	sysCmd('chmod 0777 "' . $plsFile . '"');
	sysCmd('chown root:root "' . $plsFile . '"');
	sysCmd('find ' . MPD_MUSICROOT . 'RADIO -name *.pls -exec touch {} \+');
	rbMpdUpdateRadio();
}

// Delete a station row + its .pls + logo files, then update MPD
function rbDeleteStation($name) {
	$dbh = sqlConnect();
	$row = sqlQuery("SELECT station FROM cfg_radio WHERE name='" . SQLite3::escapeString($name) . "'", $dbh);
	if (is_array($row)) {
		phpSession('open');
		unset($_SESSION[$row[0]['station']]);
		phpSession('close');
	}
	sqlQuery("DELETE FROM cfg_radio WHERE name='" . SQLite3::escapeString($name) . "'", $dbh);
	sysCmd('rm -f "' . MPD_MUSICROOT . 'RADIO/' . $name . '.pls"');
	sysCmd('rm -f "' . RADIO_LOGOS_ROOT . $name . '.jpg"');
	sysCmd('rm -f "' . RADIO_LOGOS_ROOT . 'thumbs/' . $name . '.jpg"');
	sysCmd('rm -f "' . RADIO_LOGOS_ROOT . 'thumbs/' . $name . '_sm.jpg"');
	sysCmd('find ' . MPD_MUSICROOT . 'RADIO -name *.pls -exec touch {} \+');
	rbMpdUpdateRadio();
}

function rbMpdUpdateRadio() {
	$sock = getMpdSock('command/radio-browser.php');
	sendMpdCmd($sock, 'update RADIO');
	readMpdResp($sock);
	closeMpdSock($sock);
}

// Normalised set of the stream URLs currently in the MPD play queue (for orphan pruning).
// Uses playlistinfo and reads the canonical `file: <uri>` lines (moOde's own convention,
// cf. getPlayqueue()/findInQueue) — the legacy `playlist` command's `pos:uri` format did
// NOT match cfg_radio.station here, so prune wrongly deleted still-queued 'rb' rows.
function rbQueuedUrls() {
	$urls = array();
	$sock = getMpdSock('command/radio-browser.php');
	sendMpdCmd($sock, 'playlistinfo');
	$resp = readMpdResp($sock);
	closeMpdSock($sock);
	if (is_string($resp)) {
		foreach (explode("\n", $resp) as $line) {
			if (strncmp($line, 'file: ', 6) === 0) {
				$urls[rbNormalizeUrl(trim(substr($line, 6)))] = true;
			}
		}
	}
	return $urls;
}

// Prune transient (type='rb') radio-browser stations that are no longer in the play queue.
// A 'rb' row exists ONLY so moOde's native now-playing/playqueue renderer can resolve a
// played-but-unsaved stream's name/logo (via cfg_radio → session/RADIO.json); once the
// stream leaves the queue the row is dead weight, so we delete it (row + local logo files
// + session var). Keeps cfg_radio authoritative and self-cleaning without any temporary
// JSON. $keepUrl protects the station currently being registered/played (it may not be in
// the queue yet). Favorites (type='fb') and core/native stations are never touched.
function rbPruneOrphanStations($keepUrl = '') {
	$dbh = sqlConnect();
	$rows = sqlQuery("SELECT station, name FROM cfg_radio WHERE type='rb'", $dbh);
	if (!is_array($rows)) {
		return;
	}
	$queued = rbQueuedUrls();
	$keep = rbNormalizeUrl($keepUrl);
	phpSession('open');
	foreach ($rows as $r) {
		$norm = rbNormalizeUrl($r['station']);
		if ($norm === $keep || isset($queued[$norm])) {
			continue;
		}
		unset($_SESSION[$r['station']]);
		sqlQuery("DELETE FROM cfg_radio WHERE station='" . SQLite3::escapeString($r['station']) . "' AND type='rb'", $dbh);
		$name = $r['name'];
		sysCmd('rm -f "' . RADIO_LOGOS_ROOT . $name . '.jpg"');
		sysCmd('rm -f "' . RADIO_LOGOS_ROOT . 'thumbs/' . $name . '.jpg"');
		sysCmd('rm -f "' . RADIO_LOGOS_ROOT . 'thumbs/' . $name . '_sm.jpg"');
		// A demoted favorite (f -> u) keeps its RADIO/<name>.pls; remove it too so it doesn't
		// orphan in the RADIO folder. A play-only 'rb' has none (rm -f is then a harmless no-op).
		sysCmd('rm -f "' . MPD_MUSICROOT . 'RADIO/' . $name . '.pls"');
	}
	phpSession('close');
}

// Same-origin logo proxy: fetch+cache ONE favicon on demand and stream it. Search AND
// Recent both return raw favicon URLs, so every tile's <img> points here — a single
// render path. The browser loads logos in parallel and a slow/dead host only delays its
// own tile. Content-Type is set from the bytes (getimagesize), not the cache filename,
// so JPG/PNG are served correctly; 302s to the default cover on miss.
function rbServeLogo($url) {
	if (session_status() === PHP_SESSION_ACTIVE) {
		session_write_close(); // release the session lock so parallel image requests don't serialise
	}
	$url = trim($url);
	$file = '';
	if ($url !== '' && preg_match('#^https?://#i', $url) && !str_contains($url, 'encrypted-tbn0.gstatic.com')) {
		$hash = md5($url);
		$path = RADIOBROWSER_IMAGE_CACHE . '/' . $hash . '.png';
		if (file_exists($path) && (time() - filemtime($path) < RADIOBROWSER_CACHE_TTL_STATIC)) {
			$file = $path;
		} else {
			$data = rbHttpGet($url, 4);
			if ($data !== false && strlen($data) > RADIOBROWSER_IMAGE_MIN_SIZE && strlen($data) < RADIOBROWSER_IMAGE_MAX_SIZE) {
				if (@file_put_contents($path, $data)) {
					$file = $path;
				}
			}
		}
	}
	if ($file === '') {
		header('Location: /' . DEFAULT_RADIO_COVER);
		exit;
	}
	$info = @getimagesize($file);
	$type = ($info && !empty($info['mime'])) ? $info['mime'] : 'image/png';
	header('Content-Type: ' . $type);
	header('Cache-Control: public, max-age=86400');
	header('Content-Length: ' . filesize($file));
	readfile($file);
	exit;
}

// Register a radio-browser station locally WITHOUT playing it: ensure the 3 logo files
// exist, persist it as cfg_radio type='rb' (played/history — not shown in the Radio view)
// if new, and set the session var so moOde's native now-playing/playqueue renderer
// resolves name/format/logo. Shared by 'play' and by 'register' (the latter is fired
// when a Radio Browser tile's context menu opens, so the native queue actions resolve a
// not-yet-added station instead of crashing on an unknown stream). Returns normalised fields.
function rbRegisterStation($station) {
	$url = trim($station['url'] ?? '');
	$name = rbSafeName($station['name'] ?? DEFAULT_STATION_NAME);
	$favicon = trim($station['favicon'] ?? '');
	$bitrate = (string)(int)($station['bitrate'] ?? 0);
	$format = trim($station['codec'] ?? '');
	$homepage = trim($station['homepage'] ?? '');

	rbEnsureLogo($name, $favicon);

	$dbh = sqlConnect();
	$exists = sqlQuery("SELECT id FROM cfg_radio WHERE station='" . SQLite3::escapeString($url) . "' LIMIT 1", $dbh);
	if (!is_array($exists)) {
		$vals = 'NULL,' .
			"'" . SQLite3::escapeString($url) . "'," .
			"'" . SQLite3::escapeString($name) . "'," .
			"'rb','local'," .
			"\"" . SQLite3::escapeString(trim($station['tags'] ?? '')) . "\"," .
			"''," .
			"'" . SQLite3::escapeString(trim($station['language'] ?? '')) . "'," .
			"'" . SQLite3::escapeString(trim($station['country'] ?? '')) . "'," .
			"'" . SQLite3::escapeString(trim($station['state'] ?? '')) . "'," .
			"'" . SQLite3::escapeString($bitrate) . "'," .
			"'" . SQLite3::escapeString($format) . "'," .
			"'No'," .
			"'" . SQLite3::escapeString($homepage) . "'," .
			"'No'";
		sqlQuery('INSERT INTO cfg_radio VALUES (' . $vals . ')', $dbh);
	}

	phpSession('open');
	$_SESSION[$url] = array(
		'name' => $name, 'type' => 'rb', 'logo' => 'local',
		'bitrate' => $bitrate, 'format' => $format,
		'home_page' => $homepage, 'monitor' => 'No'
	);
	phpSession('close');

	return array('name' => $name, 'format' => $format, 'bitrate' => $bitrate, 'homepage' => $homepage);
}
