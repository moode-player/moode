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
	$exitCode = 1;
	if ($mounts !== true) {
		mountmonLog('mountmon: Checking remote mounts');
		foreach ($mounts as $mp) {
			$unit = getUnitForMount($mp['name']);
			$result = sysCmd("systemctl is-failed \"$unit\"", $exitCode);
			// is-failed returns 0 if the unit is in failed state
			$fileSharingAccessible = $exitCode == 0 ? false : true;
			// Attempt remount
			if ($fileSharingAccessible === false) {
				$logs = sysCmd("journalctl -q -n 5 --no-pager --unit \"$unit\"");
				$mp['error'] = "Mount error: \n".implode("\n", $logs);
				sqlUpdate('cfg_source', $dbh, '', $mp);

				mountmonLog("- WARNING: Mount point {$mp['name']} is $result");
				mountmonLog('- Attempting to re-mount ' . $mp['name']);
				mountmonLog('- re-mount result: ' . implode("\n", sourceMount('remount', $mp['id'])));
			} else {
				mountmonLog("mountmon: Mount point {$mp['name']} seems fine");
			}
		}
	} else {
		mountmonLog('mountmon: No remote mounts are defined');
	}
}
