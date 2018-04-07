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
 * 2018-01-26 TC moOde 4.0
 * 2018-04-02 TC moOde 4.1
 * - exclude self
 * - browse for custom descriptor
 *
 */

require_once dirname(__FILE__) . '/inc/playerlib.php';

$result = sysCmd("avahi-browse -a -t | awk '/IPv4/ && /moOde audio player:/ {print $7}' | sort");
$array = sdbquery("SELECT value FROM cfg_system WHERE param='hostname'", cfgdb_connect());
$thishost = strtolower($array[0]['value']);

foreach ($result as $host) {
	if (strtolower($host) != $thishost) { // tpc r41
		$ipaddr = sysCmd("getent hosts " . $host . " | awk '{print $1}'");
		$_players .= sprintf('<li><a href="http://%s" class="btn btn-large" style="margin-bottom: 5px;"><i class="icon-sitemap" style="font-size: 24px;"></i><br>%s</a></li>', $ipaddr[0], $host);
	}
}

$tpl = 'players.html';
eval('echoTemplate("' . getTemplate("templates/$tpl") . '");');
