<?php
/**
 * moOde audio player (C) 2014 Tim Curtis
 * http://moodeaudio.org
 *
 * tsunamp player ui (C) 2013 Andrea Coiutti & Simone De Gregori
 * http://www.tsunamp.com
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

// Music source config
function sourceCfg($queueArgs) {
	$action = $queueArgs['mount']['action'];
	unset($queueArgs['mount']['action']);

	switch ($action) {
		case 'add':
			$dbh = sqlConnect();
			unset($queueArgs['mount']['id']);

			foreach ($queueArgs['mount'] as $key => $value) {
				$values .= "'" . SQLite3::escapeString($value) . "',";
			}
			// error column
			$values .= "''";

			sqlInsert('cfg_source', $dbh, $values);
			$newMountId = $dbh->lastInsertId();

			$result = sourceMount('mount', $newMountId);
			break;
		case 'edit':
			$dbh = sqlConnect();
			$mp = sqlRead('cfg_source', $dbh, '', $queueArgs['mount']['id']);
			// save the edits here in case the mount fails
			sqlUpdate('cfg_source', $dbh, '', $queueArgs['mount']);

			// cifs and nfs
			if ($mp[0]['type'] == 'cifs') {
				sysCmd('umount -l "/mnt/NAS/' . $mp[0]['name'] . '"'); // lazy umount
			}
			else {
				sysCmd('umount -f "/mnt/NAS/' . $mp[0]['name'] . '"'); // force unmount (for unreachable NFS)
			}
			// empty check to ensure /mnt/NAS is never deleted
			if (!empty($mp[0]['name']) && $mp[0]['name'] != $queueArgs['mount']['name']) {
				sysCmd('rmdir "/mnt/NAS/' . $mp[0]['name'] . '"');
				sysCmd('mkdir "/mnt/NAS/' . $queueArgs['mount']['name'] . '"');
			}

			$result = sourceMount('mount', $queueArgs['mount']['id']);
			break;
		case 'delete':
			$dbh = sqlConnect();
			$mp = sqlRead('cfg_source', $dbh, '', $queueArgs['mount']['id']);

			// cifs and nfs
			if ($mp[0]['type'] == 'cifs') {
				sysCmd('umount -l "/mnt/NAS/' . $mp[0]['name'] . '"'); // lazy umount
			}
			else {
				sysCmd('umount -f "/mnt/NAS/' . $mp[0]['name'] . '"'); // force unmount (for unreachable NFS)
			}
			// empty check to ensure /mnt/NAS is never deleted
			if (!empty($mp[0]['name'])) {
				sysCmd('rmdir "/mnt/NAS/' . $mp[0]['name'] . '"');
			}

			$result = (sqlDelete('cfg_source', $dbh, $queueArgs['mount']['id'])) ? true : false;
			break;
	}

	return $result;
}

// Music source mount
function sourceMount($action, $id = '') {
	switch ($action) {
		case 'mount':
			$dbh = sqlConnect();
			$mp = sqlRead('cfg_source', $dbh, '', $id);

			// cifs and nfs
			if ($mp[0]['type'] == 'cifs') {
				$options = $mp[0]['options'];
				if(strpos($options, 'vers=') === false) {
					$version = detectCifsProtocol($mp[0]['address']);
					if($version) {
						$options = 'vers=' . $version . ',' . $options;
					}
				}
				$mountStr = "mount -t cifs \"//" .
					$mp[0]['address'] . "/" .
					$mp[0]['remotedir'] . "\" -o username=\"" .
					$mp[0]['username'] . "\",password=\"" .
					$mp[0]['password'] . "\",rsize=" .
					$mp[0]['rsize'] . ",wsize=" .
					$mp[0]['wsize'] . ",iocharset=" .
					$mp[0]['charset'] . "," .
					$options . " \"/mnt/NAS/" .
					$mp[0]['name'] . "\"";
			} else {
				$mountStr = "mount -t nfs -o " .
				$mp[0]['options'] . " \"" .
				$mp[0]['address'] . ":/" .
				$mp[0]['remotedir'] . "\" \"/mnt/NAS/" .
				$mp[0]['name'] . "\"";
			}

			sysCmd('mkdir "/mnt/NAS/' . $mp[0]['name'] . '"');
			$result = sysCmd($mountStr);

			if (empty($result)) {
				if (!empty($mp[0]['error'])) {
					$mp[0]['error'] = '';
					sqlUpdate('cfg_source', $dbh, '', $mp[0]);
				}

				$return = true;
			} else {
				// Empty check to ensure /mnt/NAS is never deleted
				if (!empty($mp[0]['name'])) {
					sysCmd('rmdir "/mnt/NAS/' . $mp[0]['name'] . '"');
				}
				$mp[0]['error'] = 'Mount error';
				workerLog('sourceMount(): Mount error: (' . implode("\n", $result) . ')');
				sqlUpdate('cfg_source', $dbh, '', $mp[0]);

				$return = false;
			}

			debugLog('sourceMount(): Command=(' . $mountStr . ')');
			break;
		case 'mountall':
			$dbh = sqlConnect();
			$mounts = sqlRead('cfg_source', $dbh);

			foreach ($mounts as $mp) {
				if (!mountExists($mp['name'])) {
					$return = sourceMount('mount', $mp['id']);
				}
			}
			// Logged during worker startup
			$return = $mounts === true ? 'none configured' : ($mounts === false ? 'mountall failed' : 'mountall initiated');
			break;
		case 'unmountall':
			$dbh = sqlConnect();
			$mounts = sqlRead('cfg_source', $dbh);

			foreach ($mounts as $mp) {
				// cifs and nfs
				if (mountExists($mp['name'])) {
					if ($mp['type'] == 'cifs') {
						sysCmd('umount -f "/mnt/NAS/' . $mp['name'] . '"'); // change from -l (lazy) to force unmount
					} else {
						sysCmd('umount -f "/mnt/NAS/' . $mp['name'] . '"'); // force unmount (for unreachable NFS)
					}
				}
			}

			// logged during worker startup
			$return = $mounts === true ? 'none configured' : ($mounts === false ? 'unmountall failed' : 'unmountall initiated');
			break;
	}

	// returns true/false for 'mount' or a log message for 'mountall' and 'unmountall'
	return $return;
}

/**
 * Detect highest available suported cifs protocol of source
 */
function detectCifsProtocol($host) {
	$output = sysCmd("nmap -Pn " . $host . " -p 139 --script smb-protocols |grep \|");
	$parts = explode('  ', end($output));
	$version = NULL;
	if (count($parts) >= 2)  {
		$version = trim($parts[2]);
		$CIFVERSIONLUT = Array(
			"2.02" => "2.0",
			"2.10" => "2.1",
			"3.00" => "3.0",
			"3.02" => "3.0.2",
			"3.11" => "3.1.1"
		);

		if (strpos($version, 'SMBv1')) {
			$version = '1.0';
		} else if (array_key_exists($version, $CIFVERSIONLUT)) {
			$version = $CIFVERSIONLUT[$version];
		} else {
			$version = NULL;
		}
	}

	return $version;
}

function mountExists($mountName) {
	$result = sysCmd('mount | grep -ow ' . '"/mnt/NAS/' . $mountName .'"');
	if (!empty($result)) {
		return true;
	} else {
		return false;
	}
}
