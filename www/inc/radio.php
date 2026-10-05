<?php
/*
 * SPDX-License-Identifier: GPL-3.0-or-later
 * Copyright 2026 The moOde audio player project / Tim Curtis
*/

require_once __DIR__ . '/common.php';
require_once __DIR__ . '/session.php';
require_once __DIR__ . '/sql.php';

function getRadioCoverUrl($title, $station = 'None') {
	$dbh = sqlConnect();
	$title = html_entity_decode($title);

	// Check cache
	$cachedUrl = sqlQuery("SELECT cover_url FROM cfg_rcucache WHERE title='" . SQLite3::escapeString($title) . "'", $dbh);
	if (!empty($cachedUrl[0])) {
		return $cachedUrl[0]['cover_url'];
	}

	// Search for cover
	$coverUrl = sysCmd('/var/www/util/radiocover_plus.py ' .
		'--title ' . escapeshellarg($title) . ' ' .
		'--station ' . escapeshellarg($station)
		)[0];

	// Update cache
	if (!empty($coverUrl) && $coverUrl != 'None') {
		$id = sqlQuery("SELECT id FROM cfg_rcucache WHERE title='" . SQLite3::escapeString($title) . "'", $dbh);
		if (empty($id[0])) {
			sqlQuery("INSERT OR IGNORE INTO cfg_rcucache VALUES " .
				'(NULL,' . "'" . SQLite3::escapeString($title) . "', '" . $coverUrl . "'" . ')', $dbh);
		}
	}

	return $coverUrl;
}

function getRadioCoverUrlCacheCount() {
	sqlQuery("SELECT count() FROM cfg_rcucache", sqlConnect());
}

function clearRadioCoverUrlCache() {
	sqlQuery("DELETE FROM cfg_rcucache", sqlConnect());
}
