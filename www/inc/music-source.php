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
require_once __DIR__ . '/common.php';
require_once __DIR__ . '/sql.php';

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

			// CIFS and NFS
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

			// CIFS and NFS
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

// Music source mount using CIFS (SMB) and NFS protocols
function sourceMount($action, $id = '', $log = '') {
	$dbh = sqlConnect();

	switch ($action) {
		case 'mount':
			$mp = sqlRead('cfg_source', $dbh, '', $id);

			// Construct the mount string
			if ($mp[0]['type'] == 'cifs') {
				$options = $mp[0]['options'];
				if(strpos($options, 'vers=') === false) {
					$version = detectSMBVersion($mp[0]['address']);
					if(!empty($version)) {
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

			// Attempt the mount
			sysCmd('mkdir "/mnt/NAS/' . $mp[0]['name'] . '"');
			$result = sysCmd($mountStr);

			if (empty($result)) {
				if (!empty($mp[0]['error'])) {
					$mp[0]['error'] = '';
					sqlUpdate('cfg_source', $dbh, '', $mp[0]);
				}

				$return = true;
			} else {
				// Empty check to ensure /mnt/NAS/ itself is never deleted
				if (!empty($mp[0]['name'])) {
					sysCmd('rmdir "/mnt/NAS/' . $mp[0]['name'] . '"');
				}
				$mp[0]['error'] = 'Mount error';
				if ($log == '') {
					// Mounts performed by Library or Music Source Config
					workerLog('worker: Try (' . $mountStr . ')');
					workerLog('worker: Err (' . implode("\n", $result) . ')');
				} else {
					// Mounts performed by the monitor daemon ($log = 'mountmonlog')
					mountmonLog('- Try (' . $mountStr . ')');
					mountmonLog('- Err (' . implode("\n", $result) . ')');
				}
				sqlUpdate('cfg_source', $dbh, '', $mp[0]);

				$return = false;
			}

			// Log the mount string if debug logging on and mount appeared to be successful
			if ($return === true) {
				debugLog('worker: Mount (' . $mountStr . ')');
			}
			break;
		case 'unmount':
			$mp = sqlRead('cfg_source', $dbh, '', $id);
			if (mountExists($mp[0]['name'])) {
				if ($mp[0]['type'] == 'cifs') {
					sysCmd('umount -f "/mnt/NAS/' . $mp[0]['name'] . '"'); // -l (lazy) -f (force)
				} else {
					sysCmd('umount -f "/mnt/NAS/' . $mp[0]['name'] . '"');
				}
			}
			$return = true;
			break;
		case 'mountall':
			$mounts = sqlRead('cfg_source', $dbh);

			foreach ($mounts as $mp) {
				if (!mountExists($mp['name'])) {
					$return = sourceMount('mount', $mp['id'], 'workerlog');
				}
			}
			// For logging
			$return = $mounts === true ? 'None configured' : ($mounts === false ? 'sqlRead() failed' : 'Mount all submitted');
			break;
		case 'unmountall':
			$mounts = sqlRead('cfg_source', $dbh);

			foreach ($mounts as $mp) {
				if (mountExists($mp['name'])) {
					if ($mp['type'] == 'cifs') {
						sysCmd('umount -f "/mnt/NAS/' . $mp['name'] . '"');
					} else {
						sysCmd('umount -f "/mnt/NAS/' . $mp['name'] . '"');
					}
				}
			}

			// For logging
			$return = $mounts === true ? 'None configured' : ($mounts === false ? 'sqlRead() failed' : 'Unmount all submitted');
			break;
	}

	// Returns true/false for 'mount and unmount' or a message for 'mountall' and 'unmountall'
	return $return;
}

// Detect highest suported SMB protocol version
function detectSMBVersion($host) {
	$output = sysCmd("nmap -Pn " . $host . " -p 139 --script smb-protocols |grep \|");
	$parts = explode('  ', end($output));

	if (count($parts) >= 2)  {
		$version = trim($parts[2]);
		if (str_contains($version, 'SMBv1')) {
			$version = '1.0';
		} else if (array_key_exists($version, SMB_VERSIONS)) {
			$version = SMB_VERSIONS[$version];
		} else {
			$version = '';
		}
	} else {
		$version = '';
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
