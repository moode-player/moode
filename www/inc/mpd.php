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

// Scan the network for hosts with open port 6600 (MPD)
function scanForMPDHosts() {
	$this_ipaddr = sysCmd('hostname -I')[0];
	$subnet = substr($this_ipaddr, 0, strrpos($this_ipaddr, '.'));
	sysCmd('nmap -Pn -p 6600 ' . $subnet . '.0/24 -oG /tmp/nmap.scan >/dev/null');
	return sysCmd('cat /tmp/nmap.scan | grep "6600/open" | cut -f 1 | cut -d " " -f 2');
}

// Return MPD socket or exit script
function getMpdSock() {
	if (false === ($sock = openMpdSock('localhost', 6600))) {
		workerLog('getMpdSock(): Connection to MPD failed');
		exit(0);
	} else {
		return $sock;
	}
}
