<?php
/*
 * SPDX-License-Identifier: GPL-3.0-or-later
 * Copyright 2014 The moOde audio player project / Tim Curtis
*/

require_once __DIR__ . '/common.php';
require_once __DIR__ . '/mpd.php';
require_once __DIR__ . '/multiroom.php';

// Discover players and update the cache
// - Rebuild: only the players found
// - Update: also keep the cached players, offline ones are marked as such
function updateDashboardCache($rebuild = false) {
	$cache = getDashboardCache();
	$cachedHosts = array_column($cache, 'host', 'ipaddr');
	$thisHost = sqlQuery("SELECT value FROM cfg_system WHERE param='hostname'", sqlConnect())[0]['value'];
	$thisIpAddrs = explode(' ', trim(sysCmd('hostname -I')[0]));

	// This player is queried locally
	$players = array($thisHost => getDashboardPlayer($thisHost, getThisIpAddr(), true));

	foreach (scanForMoodeHosts() as $host => $ipAddr) {
		if (!in_array($ipAddr, $thisIpAddrs)) {
			$player = getDashboardPlayer($cachedHosts[$ipAddr] ?? $host, $ipAddr);
			$players[$player['host']] = $player;
		}
	}

	if ($rebuild === false) {
		foreach ($cache as $cached) {
			if (!isset($players[$cached['host']]) && !in_array($cached['ipaddr'], $thisIpAddrs) &&
				!in_array($cached['ipaddr'], array_column($players, 'ipaddr'))) {
				$player = getDashboardPlayer($cached['host'], $cached['ipaddr']);
				$players[$player['host']] = $player;
			}
		}
	}

	$players = array_values($players);
	sort($players);
	file_put_contents(DASHBOARD_CACHE_FILE, json_encode($players), LOCK_EX);

	return $players;
}

function getDashboardCache() {
	$players = json_decode((string)@file_get_contents(DASHBOARD_CACHE_FILE), true);
	return is_array($players) ? $players : array();
}

function getDashboardPlayer($host, $ipAddr, $local = false) {
	$status = $local ? (sysCmd('/var/www/util/trx-control.php -all')[0] ?? false) : sendTrxControlCmd($ipAddr, '-all');

	if ($status === false) {
		debugLog('dashboard: trx_control -all failed: ' . $ipAddr);
		$badge = 'offline';
	} else if ($status != 'Unknown command') {
		$allStatus = explode(';', $status);
		// rx, On/Off/Disabled/Unknown, volume, volume_mute_1/0, mastervol_opt_in_1/0, hostname, multicast_addr
		// tx, On/Off/Disabled/Unknown, volume, volume_mute_1/0,                     , hostname, multicast_addr
		$rxStatus = explode(',', $allStatus[0]);
		$txStatus = explode(',', $allStatus[1]);
		if ($rxStatus[1] == 'On') {
			$badge = 'receiver';
		} else if ($txStatus[1] == 'On') {
			$badge = 'sender';
		} else {
			$badge = '';
		}

		$host = $rxStatus[5]; // Can use rxStatus[5] or txStatus[5]
	} else {
		$badge = '';
	}

	return array('host' => (string)$host, 'ipaddr' => $ipAddr, 'badge' => $badge);
}
