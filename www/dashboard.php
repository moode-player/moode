<?php
/*
 * SPDX-License-Identifier: GPL-3.0-or-later
 * Copyright 2014 The moOde audio player project / Tim Curtis
*/

require_once __DIR__ . '/inc/common.php';
require_once __DIR__ . '/inc/mpd.php';
require_once __DIR__ . '/inc/multiroom.php'; // For getStreamTimeout()
require_once __DIR__ . '/inc/sql.php';

$discoverPlayers = false;
$thisIpAddr = getThisIpAddr();

if (isset($_GET['cmd']) && !empty($_GET['cmd'])) {
	chkValue('cmd', $_GET['cmd']);
	if (str_contains($_GET['cmd'], 'discover')) {
		$discoverPlayers = true;
	} else if (!isset($_POST['ipaddr'])) {
		workerLog('dashboard.php: No destination IP addresses for command ' . $_GET['cmd']);
		exit(0);
	} else {
		chkVariables($_POST);
		$count = count($_POST['ipaddr']);
		for ($i = 0; $i < $count; $i++) {
			if (!empty($_POST['ipaddr'][$i]) && $_POST['ipaddr'][$i] != $thisIpAddr) {
				$phpScript = ($_GET['cmd'] == 'reboot' || $_GET['cmd'] == 'poweroff') ? 'system.php' : 'dashboard.php';
				if (false === ($result = file_get_contents('http://' . $_POST['ipaddr'][$i] .
					'/command/' . $phpScript . '?cmd=' . rawurlencode($_GET['cmd'])))) {
					$result = 'fail';
				} else {
					$result = 'sent';
				}
				workerLog('dashboard.php: ' . $_POST['ipaddr'][$i] . ' (' . $_POST['host'][$i] . ') ' . $_GET['cmd'] . ' ' . $result);
			}
		}
	}
}

if ($discoverPlayers === false) {
	if (file_exists(DASHBOARD_CACHE_FILE) && filesize(DASHBOARD_CACHE_FILE) > 0) {
		// Use contents of cache file
		$_players = file_get_contents(DASHBOARD_CACHE_FILE);
	}
} else {
	// Discover players (hosts with open port 6600 MPD)
	$discoveredIPAddresses = scanForMPDHosts();
	$cachedIPAddresses = getCachedIPAddresses();

	// This option shows only new players since the last Discover
	if ($_GET['cmd'] == 'discover_players_update_cache') {
		$discoveredIPAddresses = array_unique(array_merge($discoveredIPAddresses, array_keys($cachedIPAddresses)));
	}

	// Parse the results
	$_players = '';
	$playersArray = array();
	$timeout = getStreamTimeout();
	foreach ($discoveredIPAddresses as $ipAddr) {
		if (false === ($status = sendTrxControlCmd($ipAddr, '-all'))) {
			debugLog('dashboard.php: sendTrxControlCmd -all failed: ' . $ipAddr);
			$playerBadge = '<i class="dashboard-badge fa-solid fa-sharp fa-xmark" style="color:red"></i>';
			$host = $cachedIPAddresses[$ipAddr];
		} else {
			if ($status != 'Unknown command') {
				$allStatus = explode(';', $status);
				// rx, On/Off/Disabled/Unknown, volume, volume_mute_1/0, mastervol_opt_in_1/0, hostname, multicast_addr
				// tx, On/Off/Disabled/Unknown, volume, volume_mute_1/0,                     , hostname, multicast_addr
				$rxStatus = explode(',', $allStatus[0]);
				$txStatus = explode(',', $allStatus[1]);
				if ($rxStatus[1] == 'On') {
					$playerBadge = '<i class="dashboard-badge fa-solid fa-sharp fa-speaker"></i>';
				} else if ($txStatus[1] == 'On') {
					$playerBadge = '<i class="dashboard-badge fa-solid fa-sharp fa-play"></i>';
				} else {
					$playerBadge = '';
				}

				$host = $rxStatus[5]; // Can use rxStatus[5] or txStatus[5]

			} else {
				$playerBadge = '';
				$host = $ipAddr;
			}
		}

		array_push($playersArray, array('host' => $host, 'ipaddr' => $ipAddr, 'badge' => $playerBadge));
	}

	// Sort results
	sort($playersArray);

	// Output for ul
	$i = 0;
	foreach ($playersArray as $player) {
		if ($player['ipaddr'] == $thisIpAddr) {
			$_players .= sprintf(
				'<li><a href="#notarget" class="btn btn-large target-blank-link" data-host="%s" data-ipaddr="%s" target="_blank" ' .
				'onclick="return false;" disabled>' .
				'<i class="fa-solid fa-sharp fa-sitemap"></i>%s<br>%s<br><span class="dashboard-ipaddr">%s</span></a></li>',
				$player['host'], $player['ipaddr'], $player['badge'], $player['host'], $player['ipaddr']
			);
		} else {
			if (str_contains($player['badge'], 'fa-xmark')) {
				$href = "#notarget";
				$onclick = 'onclick="return false;"';
			} else {
				$href = 'http://' . $player['ipaddr'];
				$onclick = '';
			}
			$_players .= sprintf(
				'<li><a href="' . $href . '" class="btn btn-large target-blank-link" data-host="%s" data-ipaddr="%s" target="_blank" ' .
				$onclick . '>' .
				'<i class="fa-solid fa-sharp fa-sitemap"></i>%s<br>' .
				'<input id="player-' . $i . '" class="checkbox-ctl-dashboard player-checkbox" type="checkbox" data-item="' . $i .
				'">%s<br><span class="dashboard-ipaddr">%s</span></a></li>',
				$player['host'], $player['ipaddr'], $player['badge'], $player['host'], $player['ipaddr']
			);
		}

		$i++;
	}

}

// Check for no players found
if (empty(trim($_players))) {
	$_players = '<li id="dashboard-no-players-found">No players found</li>';
	$_dashboard_command_div_hide = 'hide';
} else {
	$_dashboard_command_div_hide = '';
}

// Write cache file
sysCmd("echo -n '" . $_players . "' | sudo tee " . DASHBOARD_CACHE_FILE);

// Close the "Discovering players..." notification
sendFECmd('close_notification');

$tpl = 'dashboard.html';
eval('echoTemplate("' . getTemplate("templates/$tpl") . '");');

function getCachedIPAddresses() {
	$cachedPlayers = array_filter(explode('<li>', file_get_contents(DASHBOARD_CACHE_FILE)));
	$cachedIPAddresses = array();
	foreach ($cachedPlayers as $line) {
		$htmlParts = explode(' ', $line);
		foreach ($htmlParts as $part) {
			if (str_contains($part, 'data-ipaddr')) {
				$ipAddr = trim(explode('=', $part)[1], '"');
			}
			if (str_contains($part, 'data-host')) {
				$hostName = trim(explode('=', $part)[1], '"');
			}
		}
		$cachedIPAddresses[$ipAddr] = $hostName;
	}

	return $cachedIPAddresses;
}
