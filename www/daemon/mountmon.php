#!/usr/bin/php
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

require_once __DIR__ . '/../inc/common.php';
require_once __DIR__ . '/../inc/music-source.php';
require_once __DIR__ . '/../inc/session.php';
require_once __DIR__ . '/../inc/sql.php';

$dbh = sqlConnect();

while (true) {
	session_id(phpSession('get_sessionid'));
	phpSession('open_ro');

	sleep(30);
	$mounts = sqlRead('cfg_source', $dbh);
	if ($mounts !== true) {
		mountmonLog('mountmon: Checking remote mounts');
		foreach ($mounts as $mp) {
			// See if host is up
			//mountmonLog('- Checking host ' . $mp['address'] . ' for mount ' . $mp['name']);
			$result = sysCmd('ping -A -4 -c 1 ' .  $mp['address'] . ' 2>&1 | grep "Destination Host Unreachable\|Name or service not known"');
			if (empty($result)) {
				mountmonLog('- Host ' . $mp['address'] . ' for mount point ' . $mp['name'] . ' appears to be up');
				// See if file sharing service is accessible on the host
				if ($mp['type'] == 'cifs') {
					//mountmonLog('- Checking SMB mount ' . $mp['name']);
					$result = sysCmd('ls /mnt/NAS/' . $mp['name'] .' 2>&1 | grep "Host is down\|Stale file handle"');
					$fileSharingAccessible = !empty($result) ? false : true;
				}
				if ($mp['type'] == 'nfs') {
					//mountmonLog('- Checking NFS mount ' . $mp['name']);
					$port = '2049';
					sysCmd('nmap -Pn -p ' . $port . ' ' . $mp['address'] . ' -oG /tmp/nmap.scan >/dev/null');
					$result = sysCmd('cat /tmp/nmap.scan | grep "' . $port . '/open" | cut -f 1 | cut -d " " -f 2');
					$fileSharingAccessible = !empty($result) ? true : false;
				}

				// Attempt remount
				if ($fileSharingAccessible === false) {
					mountmonLog('- WARNING: Mount point ' . $mp['name'] . ' is unreachable');
				} else {
					mountmonLog('- File sharing is accessible for ' . $mp['name']);
					// Check for "Stale file handle" (NFS) or "Host is down" (SMB) return messages
					// NOTE: This check can sometimes result in long timeouts or even a hang
					//mountmonLog('- Checking ' . $mp['name'] . ' for stale file handle');
					$result = sysCmd('ls /mnt/NAS/' . $mp['name'] . ' 2>&1 | grep "Host is down\|Stale file handle"');
					if (!empty($result)) {
						mountmonLog('- Attempting to re-mount ' . $mp['name'] . ' (stale file handle)');
						sourceMount('unmountall');
						sourceMount('mountall');
					} else {
						mountmonLog('- Mount ' . $mp['name'] . ' appears to be OK');
					}
				}
			} else {
				mountmonLog('- WARNING: Host ' . $mp['address'] . ' for mount point ' . $mp['name'] . ' is unreachable');
			}
		}
	} else {
		mountmonLog('mountmon: No remote mounts are defined');
	}
}
