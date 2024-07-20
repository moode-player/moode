<?php
/*
 * SPDX-License-Identifier: GPL-3.0-or-later
 * Copyright 2014 The moOde audio player project / Tim Curtis
 * Copyright 2013 The tsunamp player ui / Andrea Coiutti & Simone De Gregori
*/

require_once __DIR__ . '/common.php';
require_once __DIR__ . '/sql.php';

//----------------------------------------------------------------------------//
// NAS SOURCES
//----------------------------------------------------------------------------//

// NAS source config
function nasSourceCfg($queueArgs) {
	$action = $queueArgs['mount']['action'];
	unset($queueArgs['mount']['action']);

	switch ($action) {
		case 'add_nas_source':
			$dbh = sqlConnect();
			unset($queueArgs['mount']['id']);

			foreach ($queueArgs['mount'] as $key => $value) {
				$values .= "'" . SQLite3::escapeString($value) . "',";
			}
			// Error column
			$values .= "''";

			sqlInsert('cfg_source', $dbh, $values);
			$newMountId = $dbh->lastInsertId();

			$result = nasSourceMount('mount', $newMountId);
			break;
		case 'edit_nas_source':
			$dbh = sqlConnect();
			$mp = sqlRead('cfg_source', $dbh, '', $queueArgs['mount']['id']);
			// Save the edits here in case the mount fails
			sqlUpdate('cfg_source', $dbh, '', $queueArgs['mount']);

			// Unmount
			if ($mp[0]['type'] == LIB_MOUNT_TYPE_SMB) {
				// Lazy umount
				sysCmd('umount -l "/mnt/NAS/' . $mp[0]['name'] . '"');
			}
			else {
				// Force unmount (for unreachable NFS)
				sysCmd('umount -f "/mnt/NAS/' . $mp[0]['name'] . '"');
			}
			// Delete and recreate the mount dir in case name was changed
			// Empty check to ensure /mnt/NAS is never deleted
			if (!empty($mp[0]['name']) && $mp[0]['name'] != $queueArgs['mount']['name']) {
				sysCmd('rmdir "/mnt/NAS/' . $mp[0]['name'] . '"');
				sysCmd('mkdir "/mnt/NAS/' . $queueArgs['mount']['name'] . '"');
			}

			$result = nasSourceMount('mount', $queueArgs['mount']['id']);
			break;
		case 'remove_nas_source':
			$dbh = sqlConnect();
			$mp = sqlRead('cfg_source', $dbh, '', $queueArgs['mount']['id']);

			// Unmount
			if ($mp[0]['type'] == LIB_MOUNT_TYPE_SMB) {
				sysCmd('umount -l "/mnt/NAS/' . $mp[0]['name'] . '"');
			}
			else {
				sysCmd('umount -f "/mnt/NAS/' . $mp[0]['name'] . '"');
			}
			// Delete the mount dir
			// Empty check to ensure /mnt/NAS is never deleted
			if (!empty($mp[0]['name'])) {
				sysCmd('rmdir "/mnt/NAS/' . $mp[0]['name'] . '"');
			}

			$result = (sqlDelete('cfg_source', $dbh, $queueArgs['mount']['id'])) ? true : false;
			break;
	}

	return $result;
}

// NAS source mount using SMB and NFS protocols
function nasSourceMount($action, $id = '', $log = '') {
	$dbh = sqlConnect();

	switch ($action) {
		case 'mount':
			$mp = sqlRead('cfg_source', $dbh, '', $id);

			// Construct the mount string
			if ($mp[0]['type'] == LIB_MOUNT_TYPE_SMB) {
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

			if (nasMountExists($mp[0]['name'])) {
				if ($mp[0]['type'] == LIB_MOUNT_TYPE_SMB) {
					sysCmd('umount -f "/mnt/NAS/' . $mp[0]['name'] . '"'); // -l (lazy) -f (force)
				} else {
					sysCmd('umount -f "/mnt/NAS/' . $mp[0]['name'] . '"');
				}
			}

			$return = true;
			break;
		case 'mountall':
			$mounts = sqlQuery("SELECT * FROM cfg_source WHERE type in ('" . LIB_MOUNT_TYPE_NFS . "', '" . LIB_MOUNT_TYPE_SMB . "')", $dbh);

			foreach ($mounts as $mp) {
				if (!nasMountExists($mp['name'])) {
					$return = nasSourceMount('mount', $mp['id'], 'workerlog');
				}
			}
			// For logging
			$return = $mounts === true ? 'None configured' : ($mounts === false ? 'sqlRead() failed' : 'Mount all submitted');
			break;
		case 'unmountall':
			$mounts = sqlQuery("SELECT * FROM cfg_source WHERE type in ('" . LIB_MOUNT_TYPE_NFS . "', '" . LIB_MOUNT_TYPE_SMB . "')", $dbh);

			foreach ($mounts as $mp) {
				if (nasMountExists($mp['name'])) {
					if ($mp['type'] == LIB_MOUNT_TYPE_SMB) {
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

function nasMountExists($mountName) {
	$result = sysCmd('mount | grep -ow ' . '"/mnt/NAS/' . $mountName .'"');
	return empty($result) ? false : true;
}

//----------------------------------------------------------------------------//
// NVMe SOURCES
//----------------------------------------------------------------------------//

function nvmeSourceCfg($queueArgs) {
	$action = $queueArgs['mount']['action'];
	unset($queueArgs['mount']['action']);

	switch ($action) {
		case 'add_nvme_source':
			$dbh = sqlConnect();
			unset($queueArgs['mount']['id']);

			foreach ($queueArgs['mount'] as $key => $value) {
				$values .= "'" . SQLite3::escapeString($value) . "',";
			}
			// Error column
			$values .= "''";

			sqlInsert('cfg_source', $dbh, $values);
			$newMountId = $dbh->lastInsertId();

			$result = nvmeSourceMount('mount', $newMountId);
			break;
		case 'edit_nvme_source':
			$dbh = sqlConnect();
			$mp = sqlRead('cfg_source', $dbh, '', $queueArgs['mount']['id']);
			// Save the edits here in case the mount fails
			sqlUpdate('cfg_source', $dbh, '', $queueArgs['mount']);

			// Unmount
			sysCmd('umount -f "/mnt/NVME/' . $mp[0]['name'] . '"');
			// Delete and recreate the mount dir in case the name was changed
			// Empty check to ensure /mnt/NVME is never deleted
			if (!empty($mp[0]['name']) && $mp[0]['name'] != $queueArgs['mount']['name']) {
				sysCmd('rmdir "/mnt/NVME/' . $mp[0]['name'] . '"');
				sysCmd('mkdir "/mnt/NVME/' . $queueArgs['mount']['name'] . '"');
			}

			$result = nvmeSourceMount('mount', $queueArgs['mount']['id']);
			break;
		case 'remove_nvme_source':
			$dbh = sqlConnect();
			$mp = sqlRead('cfg_source', $dbh, '', $queueArgs['mount']['id']);

			// Unmount
			sysCmd('umount -f "/mnt/NVME/' . $mp[0]['name'] . '"');
			// Delete the mount dir
			// Empty check to ensure /mnt/NVME is never deleted
			if (!empty($mp[0]['name'])) {
				sysCmd('rmdir "/mnt/NVME/' . $mp[0]['name'] . '"');
			}

			$result = (sqlDelete('cfg_source', $dbh, $queueArgs['mount']['id'])) ? true : false;
			break;
	}

	return $result;
}

function nvmeSourceMount($action, $id = '', $log = '') {
	$dbh = sqlConnect();

	switch ($action) {
		case 'mount':
			$mp = sqlRead('cfg_source', $dbh, '', $id);

			// Construct the mount string
			$mountStr = 'mount -t ext4 -o ' .
			$mp[0]['options'] . ' ' .
			explode(',', $mp[0]['address'])[0] . ' ' .
			'"/mnt/NVME/' .
			$mp[0]['name'] . '"';

			// Attempt the mount
			sysCmd('mkdir "/mnt/NVME/' . $mp[0]['name'] . '"');
			$result = sysCmd($mountStr);

			if (empty($result)) {
				if (!empty($mp[0]['error'])) {
					$mp[0]['error'] = '';
					sqlUpdate('cfg_source', $dbh, '', $mp[0]);
				}
				$return = true;
			} else {
				// Empty check to ensure /mnt/NVME/ itself is never deleted
				if (!empty($mp[0]['name'])) {
					sysCmd('rmdir "/mnt/NVME/' . $mp[0]['name'] . '"');
				}
				$mp[0]['error'] = 'Mount error';
				workerLog('worker: Try (' . $mountStr . ')');
				workerLog('worker: Err (' . implode("\n", $result) . ')');
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

			if (nvmeMountExists($mp[0]['name'])) {
				sysCmd('umount -f "/mnt/NVME/' . $mp[0]['name'] . '"');
			}

			$return = true;
			break;
		case 'mountall':
			$mounts = sqlQuery("SELECT * FROM cfg_source WHERE type = '" . LIB_MOUNT_TYPE_NVME . "'", $dbh);

			foreach ($mounts as $mp) {
				if (!nvmeMountExists($mp['name'])) {
					$return = nvmeSourceMount('mount', $mp['id'], 'workerlog');
				}
			}
			// For logging
			$return = $mounts === true ? 'None configured' : ($mounts === false ? 'sqlRead() failed' : 'Mount all submitted');
			break;
		case 'unmountall':
			$mounts = sqlQuery("SELECT * FROM cfg_source WHERE type = '" . LIB_MOUNT_TYPE_NVME . "'", $dbh);

			foreach ($mounts as $mp) {
				if (nvmeMountExists($mp['name'])) {
					sysCmd('umount -f "/mnt/NVME/' . $mp['name'] . '"');
				}
			}

			// For logging
			$return = $mounts === true ? 'None configured' : ($mounts === false ? 'sqlRead() failed' : 'Unmount all submitted');
			break;
	}

	// Returns true/false for 'mount and unmount' or a message for 'mountall' and 'unmountall'
	return $return;
}

function nvmeMountExists($mountName) {
	$result = sysCmd('mount | grep -ow ' . '"/mnt/NVME/' . $mountName .'"');
	return empty($result) ? false : true;
}

function nvmeListDrives() {
	$drives = array();
	$devices = sysCmd('ls -1 /dev/');

	foreach ($devices as $device) {
		if (str_contains($device, 'nvme')) {
			$label = trim(sysCmd('blkid /dev/' . $device . " | awk '/LABEL/ {print $3}' | cut -d '=' -f 2")[0], '"');
			$drives['/dev/' . $device] = empty($label) ? 'No disk label' : $label;
		}
	}

	return $drives;
}
