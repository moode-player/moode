<?php
/*
 * SPDX-License-Identifier: GPL-3.0-or-later
 * Copyright 2014 The moOde audio player project / Tim Curtis
*/

require_once __DIR__ . '/inc/common.php';
require_once __DIR__ . '/inc/dashboard.php';
require_once __DIR__ . '/inc/sql.php';

$thisIpAddr = getThisIpAddr();
$thisHost = sqlQuery("SELECT value FROM cfg_system WHERE param='hostname'", sqlConnect())[0]['value'];

if (isset($_GET['cmd']) && !empty($_GET['cmd'])) {
	chkValue('cmd', $_GET['cmd']);

	if ($_GET['cmd'] == 'discover_players') {
		updateDashboardCache();
	} else if (!isset($_POST['ipaddr'])) {
		workerLog('dashboard.php: No destination IP addresses for command ' . $_GET['cmd']);
		exit(0);
	} else if ($_GET['cmd'] == 'remove_players') {
		chkVariables($_POST);
		removeDashboardPlayers($_POST['host']);
	} else {
		chkVariables($_POST);
		$count = count($_POST['ipaddr']);
		for ($i = 0; $i < $count; $i++) {
			if (!empty($_POST['ipaddr'][$i]) && $_POST['ipaddr'][$i] != $thisIpAddr && $_POST['host'][$i] != $thisHost) {
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

// Get cached players
$playersArray = getDashboardCache();

// Output for ul
$_players = '';
$playerBadges = array(
	'offline' => '<i class="dashboard-badge dashboard-red-menu-icon fa-regular fa-sharp fa-diamond-exclamation"></i>',
	'receiver' => '<i class="dashboard-badge fa-solid fa-sharp fa-speaker"></i>',
	'sender' => '<i class="dashboard-badge fa-solid fa-sharp fa-play"></i>'
);
$i = 0;
foreach ($playersArray as $player) {
	$playerBadge = $playerBadges[$player['badge']] ?? '';
	if ($player['host'] == $thisHost) {
		$_players .= sprintf(
			'<li><a href="#notarget" class="btn btn-large target-blank-link disabled" ' .
			'data-host="%s" data-ipaddr="%s" target="_blank" onclick="return false;">' .
			'<i class="fa-solid fa-sharp fa-sitemap"></i>%s<br>%s<br><span class="dashboard-ipaddr">%s</span></a></li>',
			$player['host'], $player['ipaddr'], $playerBadge, $player['host'], $player['ipaddr']
		);
	} else {
		if ($player['badge'] == 'offline') {
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
			$player['host'], $player['ipaddr'], $playerBadge, $player['host'], $player['ipaddr']
		);
	}

	$i++;
}

// Check for no players found
if (empty($playersArray)) {
	$_players = '<li id="dashboard-no-players-found">No players found, try again</li>';
	$_ctl_hide = 'hide';
} else {
	$_ctl_hide = '';
}

$tpl = 'dashboard.html';
eval('echoTemplate("' . getTemplate("templates/$tpl") . '");');
