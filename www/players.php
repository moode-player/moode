<?php
/**
 * moOde audio player (C) 2014 Tim Curtis
 * http://moodeaudio.org
 *
 * This Program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 3, or (at your option)
 * any later version.
 *
 * This Program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program.  If not, see <http://www.gnu.org/licenses/>.
 *
 */

require_once __DIR__ . '/inc/common.php';
require_once __DIR__ . '/inc/mpd.php';
require_once __DIR__ . '/inc/multiroom.php'; // For getStreamTimeout()
require_once __DIR__ . '/inc/sql.php';

// Scan the network for hosts with open port 6600 (MPD)
$port6600Hosts = scanForMPDHosts();
$thisIpAddr = sysCmd('hostname -I')[0];

// Parse the results
$_players = '';
$timeout = getStreamTimeout();
foreach ($port6600Hosts as $ipAddr) {
	if ($ipAddr != $thisIpAddr) {
		if (false === ($status = file_get_contents('http://' . $ipAddr . '/command/?cmd=trx-control.php -rx', false, $timeout))) {
			debugLog('trx-config.php: get_rx_status failed: ' . $ipAddr);
		} else {
			if ($status != 'Unknown command') {  // r740 or higher host
				$rxStatus = explode(',', $status);
				// rx, On/Off/Disabled/Unknown, volume, volume_mute_1/0, mastervol_opt_in_1/0, hostname
				$rxIndicator = $rxStatus[1] == 'On' ? '<i class="players-rx-indicator fas fa-rss"></i>' : '';
				// NOTE: r800 status will have a 6th element (hostname) otherwise use ip address
				$host = count($rxStatus) > 5 ? $rxStatus[5] : $ipAddr;
			} else {
				$rxIndicator = '';
				$host = $ipAddr;
			}

			$_players .= sprintf('
				<li><a href="http://%s" class="btn btn-large">
				<i class="fas fa-sitemap"></i>
				<br>%s%s
				</a></li>', $ipAddr, $host, $rxIndicator);
		}
	}
}

// Check for no players found
if (empty(trim($_players))) {
	$_players = '<li style="font-size:large">No other players found</li>';
}

$tpl = 'players.html';
eval('echoTemplate("' . getTemplate("templates/$tpl") . '");');
