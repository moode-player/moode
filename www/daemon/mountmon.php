#!/usr/bin/php
<?php
/*
 * SPDX-License-Identifier: GPL-3.0-or-later
 * Copyright 2014 The moOde audio player project / Tim Curtis
*/

require_once __DIR__ . '/../inc/common.php';
require_once __DIR__ . '/../inc/music-source.php';
require_once __DIR__ . '/../inc/session.php'; // Needed
require_once __DIR__ . '/../inc/sql.php';

$dbh = sqlConnect();
session_id(phpSession('get_sessionid'));

while (true) {
	phpSession('open_ro'); // Needed for mountmonLog() which checks $_SESSION['debuglog']

	sleep(MOUNTMON_SLEEP);
	$mounts = sqlRead('cfg_source', $dbh);
	if ($mounts !== true) {
		mountmonLog('mountmon: Checking mount points');
		foreach ($mounts as $mp) {
			// See if host is up
			//mountmonLog('- Checking host ' . $mp['address'] . ' for mount ' . $mp['name']); // DEBUG
			$result = sysCmd('ping -A -4 -c 1 ' .  $mp['address'] . ' 2>&1 | grep "Destination Host Unreachable\|Name or service not known"');
			if (empty($result)) {
				mountmonLog('- Remote host for ' . $mp['name'] . ' appears to be up');
				// See if mount dir exists. It may not since umount/rmdir is done at shutdown/reboot
				if (file_exists('/mnt/NAS/' . $mp['name'])) {
					// See if file sharing service is accessible on the host
					if ($mp['type'] == 'cifs') {
						//mountmonLog('- Checking SMB mount'); // DEBUG
						$result = sysCmd('ls /mnt/NAS/' . $mp['name'] .' 2>&1 | grep "Host is down\|Stale file handle"');
						$fileSharingAccessible = !empty($result) ? false : true;
					}
					if ($mp['type'] == 'nfs') {
						//mountmonLog('- Checking NFS mount'); // DEBUG
						$port = '2049';
						sysCmd('nmap -Pn -p ' . $port . ' ' . $mp['address'] . ' -oG /tmp/nfs_nmap.scan >/dev/null');
						$result = sysCmd('cat /tmp/nfs_nmap.scan | grep "' . $port . '/open" | cut -f 1 | cut -d " " -f 2');
						$fileSharingAccessible = !empty($result) ? true : false;
					}

					// Attempt remount
					if ($fileSharingAccessible === false) {
						mountmonLog('- Warning: File sharing is not accessible');
					} else {
						mountmonLog('- File sharing is accessible');
						// Check for "Stale file handle" (NFS) or "Host is down" (SMB) return messages
						// NOTE: This check can sometimes result in long timeouts or even a hang
						//mountmonLog('- Checking for stale file handle'); // DEBUG
						$result = sysCmd('ls /mnt/NAS/' . $mp['name'] . ' 2>&1 | grep "Host is down\|Stale file handle"');
						if (!empty($result)) {
							mountmonLog('- Re-mounting ' . $mp['name'] . ' (stale file handle)');
							sourceMount('unmount', $mp['id']);
							sourceMount('mount', $mp['id'], 'mountmonlog');
						} else {
							mountmonLog('- Mount appears to be OK');
						}
					}
				} else {
					mountmonLog('- Re-mounting ' . $mp['name'] . ' (mount dir did not exist)');
					sourceMount('mount', $mp['id'], 'mountmonlog');
				}
			} else {
				mountmonLog('- Warning: Remote host for ' . $mp['name'] . ' is unreachable');
			}
		}
	} else {
		mountmonLog('mountmon: No mount points exist');
	}
}
