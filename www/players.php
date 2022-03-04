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

require_once dirname(__FILE__) . '/inc/playerlib.php';

$dbh = cfgdb_connect();

// Scan the network for hosts with open port 6600 (MPD)
$port_6600_hosts = scanForMPDHosts();

// Parse the results
$_players = '';
$timeout = getStreamTimeout();
foreach ($port_6600_hosts as $ipaddr) {
	if ($ipaddr != $this_ipaddr) {
		if (false === ($result = file_get_contents('http://' . $ipaddr . '/command/?cmd=trx-status.php -rx', false, $timeout))) {
			debugLog('trx-config.php: get_rx_status failed: ' . $ipaddr);
		}
		else {
			if ($result != 'Unknown command') {  // r740 or higher host
				$rx_status = explode(',', $result);
				// rx, On/Off/Disabled/Unknown, volume, volume,mute_1/0, mastervol_opt_in_1/0, hostname
				$multiroom_rx_indicator = $rx_status[1] == 'On' ? '<i class="players-rx-indicator fas fa-rss"></i>' : '';
				// r800 status will have a 6th element (hostname) otherwise sub in ip address
				$host = count($rx_status) > 5 ? $rx_status[5] : $ipaddr;
			}
			else {
				$multiroom_rx_indicator = '';
				$host = $ipaddr;
			}

			$_players .= sprintf('
				<li><a href="http://%s" class="btn btn-large">
				<i class="fas fa-sitemap"></i>
				<br>%s%s
				</a></li>', $ipaddr, $host, $multiroom_rx_indicator);
		}
	}
}

// Check for no players found
if (empty(trim($_players))) {
	$_players = '<li style="font-size:large">No other players found</li>';
}

$tpl = 'players.html';
eval('echoTemplate("' . getTemplate("templates/$tpl") . '");');
