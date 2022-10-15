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

const MOUNT_DIR = "/mnt/NAS";
const SYSTEMD_DIR = "/etc/systemd/system";

// Music source config
function sourceCfg($queueArgs)
{
	$action = $queueArgs['mount']['action'];
	unset($queueArgs['mount']['action']);

	switch ($action) {
		case 'add':
			$dbh = sqlConnect();
			unset($queueArgs['mount']['id']);

			$values = '';
			foreach ($queueArgs['mount'] as $key => $value) {
				$values .= "'" . SQLite3::escapeString($value) . "',";
			}
			// error column
			$values .= "''";

			sqlInsert('cfg_source', $dbh, $values);
			$newMountId = $dbh->lastInsertId();

			$result = addMount($newMountId);
			break;
		case 'edit':
			$dbh = sqlConnect();
			$mp = sqlRead('cfg_source', $dbh, '', $queueArgs['mount']['id']);
			// save the edits here in case the mount fails
			sqlUpdate('cfg_source', $dbh, '', $queueArgs['mount']);

			removeMountUnits($mp[0]['name'], $mp[0]['username']);

			// empty check to ensure /mnt/NAS is never deleted
			if (!empty($mp[0]['name']) && $mp[0]['name'] != $queueArgs['mount']['name']) {
				sysCmd('rmdir "' . MOUNT_DIR . '/' . $mp[0]['name'] . '"');
			}

			$result = addMount($queueArgs['mount']['id']);
			break;
		case 'delete':
			$dbh = sqlConnect();
			$mp = sqlRead('cfg_source', $dbh, '', $queueArgs['mount']['id']);

			removeMountUnits($mp[0]['name'], $mp[0]['username']);
			// empty check to ensure /mnt/NAS is never deleted
			if (!empty($mp[0]['name'])) {
				sysCmd('rmdir "' . MOUNT_DIR . '/' . $mp[0]['name'] . '"');
			}

			$result = (sqlDelete('cfg_source', $dbh, $queueArgs['mount']['id'])) ? true : false;
			break;
	}

	return $result;
}

// Music source mount using CIFS (SMB) and NFS protocols
function sourceMount($action, $id = '', $log = '')
{
	$dbh = sqlConnect();

	switch ($action) {
		case 'mount':
			$mp = sqlRead('cfg_source', $dbh, '', $id);
			$units = array(getUnitForMount($mp[0]['name']), getUnitForMount($mp[0]['name'], 'automount'));
			$result = array();
			$exitCode = 0;

			if (!$units) {
				$result = array("No mount units found for {$mp[0]['name']}");
			} else {
				foreach ($units as $unit) {
					$out = sysCmd("systemctl enable \"$unit\"", $exitCode);
					if ($exitCode > 0) {
						$result = array_merge($result, $out);
					}
					if (str_ends_with($unit, 'automount')) {
						$out = sysCmd("systemctl start \"$unit\"", $exitCode);
						if ($exitCode > 0) {
							$result = array_merge($result, $out);
						}
					}
				}
			}

			if (empty($result)) {
				if (!empty($mp[0]['error'])) {
					$mp[0]['error'] = '';
					sqlUpdate('cfg_source', $dbh, '', $mp[0]);
				}

				$return = true;
			} else {
				// Empty check to ensure /mnt/NAS itself is never delete
				$mp[0]['error'] = 'Mount error';
				if ($log == 'workerlog') {
					workerLog('worker: Try (' . implode(",", $units) . ')');
					workerLog('worker: Err (' . implode("\n", $result) . ')');
				} else {
					mountmonLog('- Try (' . implode(",", $units) . ')');
					mountmonLog('- Err (' . implode("\n", $result) . ')');
				}
				sqlUpdate('cfg_source', $dbh, '', $mp[0]);

				$return = false;
			}

			// Log the mount string if debug logging on and mount appeared to be successful
			if ($return === true) {
				debugLog('worker: Mount (' . implode(",", $units) . ')');
			}
			break;
		case 'mountall':
			$mounts = sqlRead('cfg_source', $dbh);

			foreach ($mounts as $mp) {
				if (!mountExists($mp['name'])) {
					$return = addMount($mp['id']);
				} else {
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
					$unit = getUnitForMount($mp['name']);
					$return = implode("\n", sysCmd("systemctl stop \"$unit\""));
				}
			}

			// For logging
			$return = $mounts === true ? 'None configured' : ($mounts === false ? 'sqlRead() failed' : 'Unmount all submitted');
			break;
		case 'remount':
			$mp = sqlRead('cfg_source', $dbh, '', $id);

			if (mountExists($mp[0]['name'])) {
				$unit = getUnitForMount($mp[0]['name']);
				$return = implode("\n", sysCmd("systemctl restart \"$unit\""));
			} else {
				$return = "Mount with id '$id' not configured";
			}
			break;
	}

	// Returns true/false for 'mount and unmount' or a message for 'mountall' and 'unmountall'
	return $return;
}

// Detect highest suported CIFS protocol of source
function detectCifsProtocol($host)
{
	$output = sysCmd("nmap -Pn " . $host . " -p 139 --script smb-protocols |grep \|");
	$parts = explode('  ', end($output));
	$version = NULL;
	if (count($parts) >= 2) {
		$version = trim($parts[2]);
		$CIFVERSIONLUT = array(
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

function mountExists($mountName)
{
	$unit = getUnitForMount($mountName);
	$exitCode = 0;
	sysCmd("systemctl list-units --full -all | grep -Fq \"$unit\"", $exitCode);
	if ($exitCode == 0) {
		return true;
	} else {
		return false;
	}
}

function getUnitForMount(string $mountName, string $suffix = "mount")
{
	return generateUnitName(MOUNT_DIR . '/' . $mountName, $suffix);
}

function generateUnitName(string $mountPath, string $suffix)
{
	$exitCode = 0;
	$result = sysCmdUser("systemd-escape -p" . " \"$mountPath\"", $exitCode);
	if (!empty($result) && $exitCode === 0) {
		return $result[array_key_last($result)] . ".$suffix";
	}
	return '';
}

function generateCredFileName(string $username)
{
	return md5($username) . '.auth';
}

function generateMountUnit(string $what, string $where, string $type, string $options)
{
	return <<<UNIT
	[Mount]
	What=$what
	Where=$where
	Options=$options
	Type=$type
	ForceUnmount=true

	[Install]
	WantedBy=multi-user.target
	UNIT;
}

function generateAutoMountUnit(string $where)
{
	return <<<UNIT
	[Automount]
	Where=$where
	
	[Install]
	WantedBy=multi-user.target
	UNIT;
}

function generateCredentialsFile(string $user, string $password)
{
	return <<<CRED
	username=$user
	password=$password
	CRED;
}

function createMountUnits(string $mountPath, string $remotePath, string $type, string $options, string $user, string $pass)
{
	$credFile = generateCredFileName($user);
	$unitFiles[generateUnitName($mountPath, "mount")] = generateMountUnit($remotePath, $mountPath, $type, $options . ',' . "credentials=" . SYSTEMD_DIR . '/' . $credFile);
	$unitFiles[generateUnitName($mountPath, "automount")] = generateAutoMountUnit($mountPath);
	$unitFiles[$credFile] = generateCredentialsFile($user, $pass);

	foreach ($unitFiles as $filename => $content) {
		$target = SYSTEMD_DIR . '/' . $filename;
		$tmp = tmpfile();
		if (!$tmp) {
			return false;
		}
		$path = stream_get_meta_data($tmp)['uri'];
		if (!$path) {
			fclose($tmp);
			return false;
		}
		$written = fwrite($tmp, $content);
		if ($written === FALSE || $written == 0) {
			fclose($tmp);
			return false;
		}
		// Need to do this because php is not root but this runs with sudo
		sysCmd("cp -f \"$path\" \"$target\"");
		sysCmd("chown root:root \"$target\"");
		if (str_ends_with($target, $credFile)) {
			// make user/pass only readable for root
			sysCmd("chmod 600 \"$target\"");
		} else {
			sysCmd("chmod 644 \"$target\"");
		}
		if (!file_exists($target)) {
			fclose($tmp);
			return false;
		}
		fclose($tmp);
	}

	// This is done so that only units get returned (names that can be given to systemctl)
	unset($unitFiles[$credFile]);

	return array_keys($unitFiles);
}

function removeMountUnits(string $mountName, string $username)
{
	$units = array(getUnitForMount($mountName), getUnitForMount($mountName, 'automount'));
	$credFile = SYSTEMD_DIR . '/' . generateCredFileName($username);

	foreach ($units as $unit) {
		$unitFile = SYSTEMD_DIR . '/' . $unit;
		sysCmd("systemctl revert \"$unit\"");
		sysCmd("systemctl stop \"$unit\"");
		sysCmd("systemctl disable \"$unit\"");
		sysCmd("rm -f \"$unitFile\"");
		sysCmd("systemctl daemon-reload");
		sysCmd("systemctl reset-failed \"$unit\"");
	}

	sysCmd("rm -f \"$credFile\"");
}

function addMount(string $id)
{
	$dbh = sqlConnect();
	$mp = sqlRead('cfg_source', $dbh, '', $id);
	$remotePath = "";
	$options = $mp[0]['options'];

	// CIFS and NFS
	if ($mp[0]['type'] == 'cifs') {
		if (strpos($options, 'vers=') === false) {
			$version = detectCifsProtocol($mp[0]['address']);
			if ($version) {
				$options = 'vers=' . $version . ',' . $options;
			}
		}
		$options = "rsize=" . $mp[0]['rsize']
			. ",wsize=" . $mp[0]['wsize']
			. ",iocharset=" . $mp[0]['charset']
			. "," . $options;
		$remotePath = "//{$mp[0]['address']}/{$mp[0]['remotedir']}";
	} else { // NFS
		$remotePath = "{$mp[0]['address']}:/{$mp[0]['remotedir']}";
	}

	$createdUnits = createMountUnits(
		MOUNT_DIR . '/' . $mp[0]['name'],
		$remotePath,
		$mp[0]['type'],
		$options,
		$mp[0]['username'],
		$mp[0]['password']
	);

	$result = array();
	$exitCode = 0;
	if (!$createdUnits) {
		$result = array("Could not create systemd (auto)mount units");
	} else {
		$out = sysCmd("systemctl daemon-reload", $exitCode);
		if ($exitCode > 0) {
			$result = array_merge($result, $out);
		} elseif ($exitCode == 0) {
			foreach ($createdUnits as $unit) {
				$out = sysCmd("systemctl enable \"$unit\"", $exitCode);
				if ($exitCode > 0) {
					$result = array_merge($result, $out);
				}
				if (str_ends_with($unit, 'automount')) {
					$out = sysCmd("systemctl start \"$unit\"", $exitCode);
					if ($exitCode > 0) {
						$result = array_merge($result, $out);
					}
				}
			}
		}
	}

	if (empty($result)) {
		if (!empty($mp[0]['error'])) {
			$mp[0]['error'] = '';
			sqlUpdate('cfg_source', $dbh, '', $mp[0]);
		}

		$return = true;
	} else {
		$mp[0]['error'] = 'Create mount unit error';
		workerLog('addMount(): Create mount unit error: (' . implode("\n", $result) . ')');
		sqlUpdate('cfg_source', $dbh, '', $mp[0]);

		$return = false;
	}

	debugLog('addMount(): Cmd (' . implode(',', $createdUnits) . ')');

	return $return;
}
