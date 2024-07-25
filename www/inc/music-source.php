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
			nvmeUpdNFSExports($queueArgs['mount']['name'], $action);
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
			nvmeUpdNFSExports($mp[0]['name'], 'remove_nvme_source');
			nvmeUpdNFSExports($queueArgs['mount']['name'], 'add_nvme_source');
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
			nvmeUpdNFSExports($mp[0]['name'], $action);
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

function nvmeUpdNFSExports($mountName, $action) {
	$dbh = sqlConnect();

	switch ($action) {
		case 'add_nvme_source':
			// Example: /srv/nfs/nvme/Music	192.168.1.0/24(rw,sync,no_subtree_check,no_root_squash)
			$access = sqlQuery("SELECT value FROM cfg_system WHERE param='fs_nfs_access'", $dbh)[0]['value'];
			$options = sqlQuery("SELECT value FROM cfg_system WHERE param='fs_nfs_options'", $dbh)[0]['value'];
			sysCmd("sed -i '$ a/srv/nfs/nvme/" . $mountName . "\t" . $access . '(' . $options . ")\n' /etc/exports");
			break;
		case 'remove_nvme_source':
			sysCmd('sed -i "/' . $mountName . '/ d" /etc/exports');
			sysCmd('sed -i "/^$/d" /etc/exports'); // Remove any trailing blank lines
			break;
	}

	$fsNFS = sqlQuery("SELECT value FROM cfg_system WHERE param='fs_nfs'", $dbh)[0]['value'];
	if ($fsNFS == 'On') {
		sysCmd('systemctl restart nfs-kernel-server');
	}
}

function nvmeListDrives() {
	$drives = array();
	$devices = sysCmd('ls -1 /dev/');

	foreach ($devices as $device) {
		// Check for /dev/nvme0n1 and similar
		if (str_contains($device, 'nvme') && strlen($device) > 5) {
			// Check for ext4 format
			$format = getDriveFormat('/dev/' . $device);
			if (empty($format)) {
				$status = LIB_NVME_UNFORMATTED;
			} else if ($format != 'ext4') {
				$status = LIB_NVME_NOT_EXT4;
			} else {
				// Get drive label
				$label = getDrivelabel('/dev/' . $device);
				if (empty($label)) {
					$status = LIB_NVME_NO_LABEL;
				} else {
					$status = $label;
				}
			}

			$drives['/dev/' . $device] = $status;
		}
	}

	return $drives;
}

function getDriveFormat($device) {
	$format = sysCmd('blkid ' . $device . " | awk -F'TYPE=' '{print $2}' | awk -F'\"' '{print $2}'");
	return empty($format) ? '' : $format[0];
}

function getDriveLabel($device) {
	$label = sysCmd('blkid ' . $device . " | awk -F'LABEL=' '{print $2}' | awk -F'\"' '{print $2}'");
	return empty($label) ? '' : $label[0];
}

function nvmeFormatDrive($device, $label) {
	sysCmd('mkfs -t ext4 ' . $device);
	sysCmd('e2label ' . $device . ' "' . $label . '"');
}
