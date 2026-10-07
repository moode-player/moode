<?php
/*
 * SPDX-License-Identifier: GPL-3.0-or-later
 * Copyright 2026 The moOde audio player project / Tim Curtis
*/

require_once __DIR__ . '/common.php';
require_once __DIR__ . '/session.php';
require_once __DIR__ . '/sql.php';

function getRadioCoverUrl($title, $station = 'None') {
	$title = html_entity_decode($title);

	$coverUrl = sysCmd('/var/www/util/radiocover_plus.py ' .
		'--title ' . escapeshellarg($title) . ' ' .
		'--station ' . escapeshellarg($station)
		)[0];

	return $coverUrl; // URL or 'None'
}

function getRadioCoverUrlCacheCount() {
	sqlQuery("SELECT count() FROM cfg_rcucache", sqlConnect());
}

function clearRadioCoverUrlCache() {
	sqlQuery("DELETE FROM cfg_rcucache", sqlConnect());
}
