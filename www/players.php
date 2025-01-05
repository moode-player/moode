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

if (isset($_GET['cmd']) && !empty($_GET['cmd'])) {
	chkValue('cmd', $_GET['cmd']);
	if (!isset($_POST['ipaddr'])) {
		workerLog('players.php: No destination IP addresses for command ' . $_GET['cmd']);
		exit(0);
	} else if ($_GET['cmd'] == 'rediscover_players') {
		$discoverPlayers = true;
	} else {
		chkVariables($_POST);
		$count = count($_POST['ipaddr']);
		for ($i = 0; $i < $count; $i++) {
			if (false === ($result = file_get_contents('http://' . $_POST['ipaddr'][$i] .
				'/command/system.php?cmd=' . rawurlencode($_GET['cmd'])))) {
				workerLog('players.php: Error: command ' . $_GET['cmd'] . ' sent to ' . $_POST['ipaddr'][$i] . ' failed');
			} else {
				workerLog('players.php: command ' . $_GET['cmd'] . ' sent to ' . $_POST['ipaddr'][$i]);
			}
		}
		exit(0);
	}
}

if (file_exists(PLAYERS_CACHE_FILE) && filesize(PLAYERS_CACHE_FILE) > 0 && $discoverPlayers === false) {
	// Use contents of cache file
	$_players = file_get_contents(PLAYERS_CACHE_FILE);
} else {
	// Scan the network for hosts with open port 6600 (MPD)
	$port6600Hosts = scanForMPDHosts();
	$thisIpAddr = getThisIpAddr();

	// Parse the results
	$_players = '';
	$_players_command_div_hide = '';
	$timeout = getStreamTimeout();
	foreach ($port6600Hosts as $ipAddr) {
		if ($ipAddr != $thisIpAddr) {
			if (false === ($status = sendTrxControlCmd($ipAddr, '-rx'))) {
				debugLog('trx-config.php: get_rx_status failed: ' . $ipAddr);
			} else {
				if ($status != 'Unknown command') {
					$rxStatus = explode(',', $status);
					// rx, On/Off/Disabled/Unknown, volume, volume_mute_1/0, mastervol_opt_in_1/0, hostname, multicast_addr
					$rxIndicator = $rxStatus[1] == 'On' ? '<i class="players-rx-indicator fa-solid fa-sharp fa-speaker"></i>' : '';
					$host = $rxStatus[5];
				} else {
					$rxIndicator = '';
					$host = $ipAddr;
				}

				$_players .= sprintf('<li><a href="http://%s" class="btn btn-large target-blank-link" data-ipaddr="%s" target="_blank">'
					. '<i class="fa-solid fa-sharp fa-sitemap"></i>'
					. '<br>%s%s</a></li>', $ipAddr, $ipAddr, $host, $rxIndicator);
			}
		}
	}
}

// Check for no players found
if (empty(trim($_players))) {
	$_players = '<li id="players-no-players-found">No players found</li>';
	$_players_command_div_hide = 'hide';
}

// Write cache file
sysCmd("echo -n '" . $_players . "' | sudo tee " . PLAYERS_CACHE_FILE);

// Close the "Discovering players..." notification
sendFECmd('close_notification');

$tpl = 'players.html';
eval('echoTemplate("' . getTemplate("templates/$tpl") . '");');
