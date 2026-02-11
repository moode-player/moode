<?php
/*
 * SPDX-License-Identifier: GPL-3.0-or-later
 * Copyright 2014 The moOde audio player project / Tim Curtis
 * Copyright 2013 The tsunamp player ui / Andrea Coiutti & Simone De Gregori
*/

require_once __DIR__ . '/inc/common.php';
require_once __DIR__ . '/inc/mpd.php';
require_once __DIR__ . '/inc/music-library.php';
require_once __DIR__ . '/inc/music-source.php';
require_once __DIR__ . '/inc/session.php';
require_once __DIR__ . '/inc/sql.php';

$dbh = sqlConnect();
phpSession('open');

chkVariables($_GET);
chkVariables($_POST, array('password'));

// For save, remove actions
$initiateLibraryUpd = false;

//----------------------------------------------------------------------------//
// Library Config
//----------------------------------------------------------------------------//

// NAS source re-mount
if (isset($_POST['remount_nas_sources'])) {
	$result = sqlRead('cfg_source', $dbh);
	if ($result === true) {
		$_SESSION['notify']['title'] = NOTIFY_TITLE_ALERT;
		$_SESSION['notify']['msg'] = 'No NAS sources have been configured.';
	} else {
		$resultUnmount = nasSourceMount('unmountall');
		$resultMount = nasSourceMount('mountall');
		$_SESSION['notify']['title'] = NOTIFY_TITLE_INFO;
		$_SESSION['notify']['msg'] = 'Re-mounting NAS sources...';
	}
}
// NAS source mount monitor
if (isset($_POST['update_fs_mountmon'])) {
	if (isset($_POST['fs_mountmon']) && $_POST['fs_mountmon'] != $_SESSION['fs_mountmon']) {
		$_SESSION['fs_mountmon'] = $_POST['fs_mountmon'];
		submitJob('fs_mountmon', $_POST['fs_mountmon']);
	}
}
// Folder view only
if (isset($_POST['update_lib_fv_only'])) {
	unset($_GET['cmd']);
	if ($_POST['lib_fv_only'] == 'on') {
		clearLibCacheAll();
		phpSession('write', 'current_view', 'folder');
	}
	$_SESSION['lib_fv_only'] = $_POST['lib_fv_only'];
}
// Regenerate MPD database
if (isset($_POST['regen_library'])) {
	unset($_GET['cmd']);
	submitJob('regen_library', '', NOTIFY_TITLE_INFO,
		'Regenerating the database. Stay on this screen until the progress spinner disappears.<br><br>Click VIEW STATUS for progress.',
		NOTIFY_DURATION_INFINITE);
}
// Clear library cache
if (isset($_POST['clear_libcache'])) {
	unset($_GET['cmd']);
	clearLibCacheAll();
	$_SESSION['notify']['title'] = NOTIFY_TITLE_INFO;
	$_SESSION['notify']['msg'] = 'Library tag cache has been cleared. It will be created when returning to Playback or Library view.';
}
// Scan or ignore .cue files by adding or removing *.cue from /var/lib/mpd/music/.mpdignore
if (isset($_POST['update_cuefiles_ignore'])) {
	unset($_GET['cmd']);
	if (isset($_POST['cuefiles_ignore']) && $_POST['cuefiles_ignore'] != $_SESSION['cuefiles_ignore']) {
		phpSession('write', 'cuefiles_ignore', $_POST['cuefiles_ignore']);
		submitJob('mpd_ignore', ($_POST['cuefiles_ignore'] . ',cue'), NOTIFY_TITLE_INFO, NOTIFY_MPD_UPDATE_LIBRARY);
	}
}
// Scan or ignore moode files by adding or removing the filenames from /var/lib/mpd/music/.mpdignore
if (isset($_POST['update_moodefiles_ignore'])) {
	unset($_GET['cmd']);
	if (isset($_POST['moodefiles_ignore']) && $_POST['moodefiles_ignore'] != $_SESSION['moodefiles_ignore']) {
		phpSession('write', 'moodefiles_ignore', $_POST['moodefiles_ignore']);
		submitJob('mpd_ignore', ($_POST['moodefiles_ignore'] . ',moode'), NOTIFY_TITLE_INFO, NOTIFY_MPD_UPDATE_LIBRARY);
	}
}
// Regenerate thumbnail cache
if (isset($_POST['regen_thmcache'])) {
	unset($_GET['cmd']);
	$result = sysCmd('pgrep -l thumb-gen.php');
	if (strpos($result[0], 'thumb-gen.php') !== false) {
		$_SESSION['notify']['title'] = NOTIFY_TITLE_ALERT;
		$_SESSION['notify']['msg'] = 'The Thumbnail Generator is currently running.';
	} else {
		$_SESSION['thmcache_status'] = 'Regenerating the thumbnail cache...';
		submitJob('regen_thmcache', '', NOTIFY_TITLE_INFO, 'Regenerating the thumbnail cache. Click VIEW STATUS for progress.');
	}
}

//----------------------------------------------------------------------------//
// NAS Source Config
//----------------------------------------------------------------------------//

// Remove NAS source
if (isset($_POST['remove_nas_source']) && $_POST['remove_nas_source'] == 1) {
	$initiateLibraryUpd = true;
	$_POST['mount']['action'] = 'remove_nas_source';
	$_POST['mount']['id'] = $_SESSION['nas_src_mpid'];
	submitJob('nas_source_cfg', $_POST);
}
// Save NAS source
if (isset($_POST['save_nas_source']) && $_POST['save_nas_source'] == 1) {
	// Validate
	$id = sqlQuery("SELECT id from cfg_source WHERE name='" . $_POST['mount']['name'] . "'", $dbh);
	$address = explode('/', $_POST['mount']['address'], 2);
	$_POST['mount']['address'] = $address[0];
	$_POST['mount']['remotedir'] = $address[1];

	if (empty(trim($_POST['mount']['address']))) {
		$_SESSION['notify']['title'] = NOTIFY_TITLE_ALERT;
		$_SESSION['notify']['msg'] = 'Path cannot be blank.';
	} else if (empty(trim($_POST['mount']['remotedir']))) {
		$_SESSION['notify']['title'] = NOTIFY_TITLE_ALERT;
		$_SESSION['notify']['msg'] = 'Share cannot be blank.';
	} else if ($_POST['mount']['type'] == LIB_MOUNT_TYPE_SMB && empty(trim($_POST['mount']['username']))) {
		$_SESSION['notify']['title'] = NOTIFY_TITLE_ALERT;
		$_SESSION['notify']['msg'] = 'Userid cannot be blank.';
	} else if ($_POST['mount']['action'] == 'add_nas_source' && !empty($id[0])) {
		$_SESSION['notify']['title'] = NOTIFY_TITLE_ALERT;
		$_SESSION['notify']['msg'] = 'Name already exists.';
	} else if (empty(trim($_POST['mount']['name']))) {
		$_SESSION['notify']['title'] = NOTIFY_TITLE_ALERT;
		$_SESSION['notify']['msg'] = 'Name cannot be blank.';
	} else {
		$initiateLibraryUpd = true;
		// SMB and NFS defaults if blank
		if (empty(trim($_POST['mount']['rsize']))) {$_POST['mount']['rsize'] = 61440;}
		if (empty(trim($_POST['mount']['wsize']))) {$_POST['mount']['wsize'] = 65536;}
		if (empty(trim($_POST['mount']['options']))) {
			if ($_POST['mount']['type'] == LIB_MOUNT_TYPE_SMB) {
				$_POST['mount']['options'] = "vers=1.0,ro,noserverino,cache=none,dir_mode=0777,file_mode=0777";
			} else if ($_POST['mount']['type'] == LIB_MOUNT_TYPE_NFS) {
				$_POST['mount']['options'] = "soft,timeo=10,retrans=1,ro,nolock";
			}
		}
		// $array['mount']['key'] must be in column order for subsequent table insert
		// Table cols = id, name, type, address, remotedir, username, password, charset, rsize, wsize, options, error
		// New id is auto generated, action = add_nas_source, edit_nas_source, remove_nas_source
		$array['mount']['action'] = $_POST['mount']['action'];
		$array['mount']['id'] = $_POST['mount']['id'];
		$array['mount']['name'] = $_POST['mount']['name'];
		$array['mount']['type'] = $_POST['mount']['type'];
		$array['mount']['address'] = $_POST['mount']['address'];
		$array['mount']['remotedir'] = $_POST['mount']['remotedir'];
		$array['mount']['username'] = $_POST['mount']['username'];
		$array['mount']['password'] = $_POST['mount']['password'];
		$array['mount']['charset'] = $_POST['mount']['charset'];
		$array['mount']['rsize'] = $_POST['mount']['rsize'];
		$array['mount']['wsize'] = $_POST['mount']['wsize'];
		$array['mount']['options'] = $_POST['mount']['options'];

		submitJob('nas_source_cfg', $array);
	}
}
// NAS scanner
if (isset($_POST['scan']) && $_POST['scan'] == 1) {
	$_GET['cmd'] = $_SESSION['nas_src_action'];
	$_GET['id'] = $_SESSION['nas_src_mpid'];
	// SMB (Samba)
	if ($_POST['mount']['type'] == LIB_MOUNT_TYPE_SMB) {
		$nmbLookupResult = sysCmd("nmblookup -S -T '*' | grep '*<00>' | cut -f 1 -d '*'");
		sort($nmbLookupResult, SORT_NATURAL | SORT_FLAG_CASE);

		foreach ($nmbLookupResult as $line) {
			$nmbLookupPart = explode(', ', $line); // [0] = host.domain, [1] = IP address
			$smbClientResult = sysCmd("smbclient -N -g -L " . trim($nmbLookupPart[1]) . " | grep Disk | cut -f 2 -d '|'");
			sort($smbClientResult, SORT_NATURAL | SORT_FLAG_CASE);
			$host = strtoupper(explode('.', $nmbLookupPart[0])[0] );

			foreach ($smbClientResult as $share) {
				$_address .= sprintf('<option value="%s" %s>%s</option>\n', $host . '/' . $share, '', $host . '/' . $share);
			}
		}
	}
	// NFS
	if ($_POST['mount']['type'] == LIB_MOUNT_TYPE_NFS) {
		$thisIpAddr = getThisIpAddr();
		$subnet = substr($thisIpAddr, 0, strrpos($thisIpAddr, '.'));
		$port = '2049'; // NFSv4

		sysCmd('nmap -Pn -p ' . $port . ' ' . $subnet . '.0/24 -oG /tmp/nmap.scan >/dev/null');
		$hosts = sysCmd('cat /tmp/nmap.scan | grep "' . $port . '/open" | cut -f 1 | cut -d " " -f 2');

		foreach ($hosts as $ipAddr) {
			$shares = sysCmd('showmount --exports --no-headers ' . $ipAddr . ' | cut -d" " -f1');
			foreach ($shares as $share) {
				$_address .= sprintf('<option value="%s" %s>%s</option>\n', $ipAddr . $share, '', $ipAddr . $share);
			}
		}
	}
}
// Manual entry
if (isset($_POST['manualentry']) && $_POST['manualentry'] == 1) {
	$_GET['cmd'] = $_SESSION['nas_src_action'];
	$_GET['id'] = $_SESSION['nas_src_mpid'];
}

//----------------------------------------------------------------------------//
// NVMe Source Config
//----------------------------------------------------------------------------//

// Remove NVMe source
if (isset($_POST['remove_nvme_source']) && $_POST['remove_nvme_source'] == 1) {
	$initiateLibraryUpd = true;
	$_POST['mount']['action'] = 'remove_nvme_source';
	$_POST['mount']['id'] = $_SESSION['nvme_src_mpid'];
	submitJob('nvme_source_cfg', $_POST);
}
// Save NVMe source
if (isset($_POST['save_nvme_source']) && $_POST['save_nvme_source'] == 1) {
	// Validate
	$id = sqlQuery("SELECT id from cfg_source WHERE name='" . $_POST['mount']['name'] . "'", $dbh);
	$driveStatus = explode(',', $_POST['mount']['drive'])[1]; // device,label or device,status
	if ($driveStatus == 'none') {
		$_SESSION['notify']['title'] = NOTIFY_TITLE_ALERT;
		$_SESSION['notify']['msg'] = 'No drive was selected.';
	} else if ($_POST['mount']['action'] == 'add_nvme_source' && !empty($id[0])) {
		$_SESSION['notify']['title'] = NOTIFY_TITLE_ALERT;
		$_SESSION['notify']['msg'] = 'Name already exists.';
	} else if ($driveStatus == LIB_DRIVE_UNFORMATTED) {
		$_SESSION['notify']['title'] = NOTIFY_TITLE_ALERT;
		$_SESSION['notify']['msg'] = 'Drive must be formatted first.';
	} else if ($driveStatus == LIB_DRIVE_NOT_EXT4) {
		$_SESSION['notify']['title'] = NOTIFY_TITLE_ALERT;
		$_SESSION['notify']['msg'] = 'Drive must be ext4 format.';
	} else if ($driveStatus == LIB_DRIVE_NO_LABEL) {
		$_SESSION['notify']['title'] = NOTIFY_TITLE_ALERT;
		$_SESSION['notify']['msg'] = 'Drive must have a volume label.';
	} else if (empty(trim($_POST['mount']['name']))) {
		$_SESSION['notify']['title'] = NOTIFY_TITLE_ALERT;
		$_SESSION['notify']['msg'] = 'Name cannot be blank.';
	} else {
		$initiateLibraryUpd = true;
		// $array['mount']['key'] must be in column order for subsequent table insert
		// Table cols = id, name, type, address, remotedir, username, password, charset, rsize, wsize, options, error
		// New id is auto generated, action = add_nvme_source, edit_nvme_source, remove_nvme_source
		$array['mount']['action'] = $_POST['mount']['action'];
		$array['mount']['id'] = $_POST['mount']['id'];
		$array['mount']['name'] = $_POST['mount']['name'];
		$array['mount']['type'] = LIB_MOUNT_TYPE_NVME;
		$array['mount']['address'] = $_POST['mount']['drive']; // device,label or device,status
		$array['mount']['remotedir'] = '';
		$array['mount']['username'] = '';
		$array['mount']['password'] = '';
		$array['mount']['charset'] = '';
		$array['mount']['rsize'] = '';
		$array['mount']['wsize'] = '';
		$array['mount']['options'] = 'noexec,nodev,noatime,nodiratime';

		submitJob('nvme_source_cfg', $array);
	}
}

//----------------------------------------------------------------------------//
// NVMe format drive
//----------------------------------------------------------------------------//

if (isset($_POST['nvme_format_drive'])) {
	if (empty(trim($_POST['nvme_drive_label']))) {
		$_SESSION['notify']['title'] = NOTIFY_TITLE_ALERT;
		$_SESSION['notify']['msg'] = 'Volume label cannot be blank.';
	} else if (!empty(sysCmd('mount | grep -ow "' . explode(',', $_POST['nvme_drive'])[0] . '"'))) {
		$_SESSION['notify']['title'] = NOTIFY_TITLE_ALERT;
		$_SESSION['notify']['msg'] = 'Drive must be un-mounted first.';
	} else {
		$_nvme_drive = $_POST['nvme_drive'];
		$_nvme_drive_label = $_POST['nvme_drive_label'];
		submitJob('nvme_format_drive', $_POST['nvme_drive'] . ',' . $_POST['nvme_drive_label'],
			NOTIFY_TITLE_INFO,
			'Drive format complete. Return to Library Config, restart the system then mount the drive.',
			NOTIFY_DURATION_MEDIUM);
	}
}

//----------------------------------------------------------------------------//
// SATA Source Config
//----------------------------------------------------------------------------//

// Remove SATA source
if (isset($_POST['remove_sata_source']) && $_POST['remove_sata_source'] == 1) {
	$initiateLibraryUpd = true;
	$_POST['mount']['action'] = 'remove_sata_source';
	$_POST['mount']['id'] = $_SESSION['sata_src_mpid'];
	submitJob('sata_source_cfg', $_POST);
}
// Save SATA source
if (isset($_POST['save_sata_source']) && $_POST['save_sata_source'] == 1) {
	// Validate
	$id = sqlQuery("SELECT id from cfg_source WHERE name='" . $_POST['mount']['name'] . "'", $dbh);
	$driveStatus = explode(',', $_POST['mount']['drive'])[1]; // device,label or device,status
	if ($driveStatus == 'none') {
		$_SESSION['notify']['title'] = NOTIFY_TITLE_ALERT;
		$_SESSION['notify']['msg'] = 'No drive was selected.';
	} else if ($_POST['mount']['action'] == 'add_sata_source' && !empty($id[0])) {
		$_SESSION['notify']['title'] = NOTIFY_TITLE_ALERT;
		$_SESSION['notify']['msg'] = 'Name already exists.';
	} else if ($driveStatus == LIB_DRIVE_UNFORMATTED) {
		$_SESSION['notify']['title'] = NOTIFY_TITLE_ALERT;
		$_SESSION['notify']['msg'] = 'Drive must be formatted first.';
	} else if ($driveStatus == LIB_DRIVE_NO_LABEL) {
		$_SESSION['notify']['title'] = NOTIFY_TITLE_ALERT;
		$_SESSION['notify']['msg'] = 'Drive must have a volume label.';
	} else if (empty(trim($_POST['mount']['name']))) {
		$_SESSION['notify']['title'] = NOTIFY_TITLE_ALERT;
		$_SESSION['notify']['msg'] = 'Name cannot be blank.';
	} else {
		$initiateLibraryUpd = true;
		// $array['mount']['key'] must be in column order for subsequent table insert
		// Table cols = id, name, type, address, remotedir, username, password, charset, rsize, wsize, options, error
		// New id is auto generated, action = add_sata_source, edit_sata_source, remove_sata_source
		$array['mount']['action'] = $_POST['mount']['action'];
		$array['mount']['id'] = $_POST['mount']['id'];
		$array['mount']['name'] = $_POST['mount']['name'];
		$array['mount']['type'] = LIB_MOUNT_TYPE_SATA;
		$array['mount']['address'] = $_POST['mount']['drive']; // device,label or device,status
		$array['mount']['remotedir'] = '';
		$array['mount']['username'] = '';
		$array['mount']['password'] = '';
		$array['mount']['charset'] = '';
		$array['mount']['rsize'] = '';
		$array['mount']['wsize'] = '';
		$array['mount']['options'] = 'noexec,nodev,noatime,nodiratime';

		submitJob('sata_source_cfg', $array);
	}
}

phpSession('close');

waitWorker('lib-config');

if ($initiateLibraryUpd == true) {
	phpSession('open');
	$_SESSION['notify']['title'] = NOTIFY_TITLE_INFO;
	if (isset($_POST['save_nas_source'])) {
		$_SESSION['notify']['msg'] = 'NAS source has been saved. Update or regenerate the Music database if the NAS mount was successful.';
	} else if (isset($_POST['remove_nas_source'])) {
		$_SESSION['notify']['msg'] = 'NAS source has been removed. Update or regenerate the Music database.';
	} else if (isset($_POST['save_nvme_source'])) {
		$_SESSION['notify']['msg'] = 'NVMe source has been saved. Update or regenerate the Music database if the NVMe mount was successful.';
	} else if (isset($_POST['remove_nvme_source'])) {
		$_SESSION['notify']['msg'] = 'NVMe source has been removed. Update or regenerate the Music database.';
	} else if (isset($_POST['save_sata_source'])) {
		$_SESSION['notify']['msg'] = 'SATA source has been saved. Update or regenerate the Music database if the SATA mount was successful.';
	} else if (isset($_POST['remove_sata_source'])) {
		$_SESSION['notify']['msg'] = 'SATA source has been removed. Update or regenerate the Music database.';
	}
	phpSession('close');
	unset($_GET['cmd']);
}

//----------------------------------------------------------------------------//
// Populate form fields
//----------------------------------------------------------------------------//

// LIB CONFIG
if (!isset($_GET['cmd'])) {
	$tpl = "lib-config.html";

	// NAS mounts
	$mounts = sqlQuery("SELECT * FROM cfg_source WHERE type in ('" . LIB_MOUNT_TYPE_NFS . "', '" . LIB_MOUNT_TYPE_SMB . "')", $dbh);
	foreach ($mounts as $mp) {
		$icon = nasMountExists($mp['name']) ? LIB_MOUNT_OK : LIB_MOUNT_FAILED;
		$driveStats = $icon == LIB_MOUNT_OK ? formatDriveStats('/mnt/NAS/' . $mp['name']) : '';
		$mpType = $mp['type'] == 'cifs' ? 'smb' : $mp['type'];
		$_nas_mounts .= "<a href=\"lib-config.php?cmd=edit_nas_source&id=" .
			$mp['id'] . "\" class='btn-large config-btn config-btn-music-source'> " . $icon . " " .
			$mp['name'] . " (" . $mp['address'] . " | " . $mpType . ")" . $driveStats . "</a>";
	}

	if ($mounts === true) {
		$_nas_mounts .= '<span class="btn-large config-btn config-btn-music-source config-btn-music-source-none">None configured</span>';
	} else if ($mounts === false) {
		$_nas_mounts .= '<span class="btn-large config-btn config-btn-music-source">Query failed</span>';
	}
	// Mount monitor
	$autoClick = " onchange=\"autoClick('#btn-set-fs-mountmon');\"";
	$_select['fs_mountmon_on']  .= "<input type=\"radio\" name=\"fs_mountmon\" id=\"toggle-fs-mount-monitor-1\" value=\"On\" " . (($_SESSION['fs_mountmon'] == 'On') ? "checked=\"checked\"" : "") . $autoClick . ">\n";
	$_select['fs_mountmon_off'] .= "<input type=\"radio\" name=\"fs_mountmon\" id=\"toggle-fs-mount-monitor-2\" value=\"Off\" " . (($_SESSION['fs_mountmon'] == 'Off') ? "checked=\"checked\"" : "") . $autoClick . ">\n";

	// NVMe mounts
	$mounts = sqlQuery("SELECT * FROM cfg_source WHERE type = '" . LIB_MOUNT_TYPE_NVME . "'", $dbh);
	foreach ($mounts as $mp) {
		$icon = nvmeMountExists($mp['name']) ? LIB_MOUNT_OK : LIB_MOUNT_FAILED;
		$driveStats = $icon == LIB_MOUNT_OK ? formatDriveStats('/mnt/NVME/' . $mp['name']) : '';
		$_nvme_mounts .= "<a href=\"lib-config.php?cmd=edit_nvme_source&id=" . $mp['id'] .
			"\" class='btn-large config-btn config-btn-music-source'> " . $icon . " " .
			$mp['name'] . ' (' . explode(',', $mp['address'])[0] . ' | ext4)' . $driveStats . "</a>";
	}
	if ($mounts === true) {
		$_nvme_mounts .= '<span class="btn-large config-btn config-btn-music-source config-btn-music-source-none">None configured</span>';
	} else if ($mounts === false) {
		$_nvme_mounts .= '<span class="btn-large config-btn config-btn-music-source">Query failed</span>';
	}

	// SATA mounts
	$mounts = sqlQuery("SELECT * FROM cfg_source WHERE type = '" . LIB_MOUNT_TYPE_SATA . "'", $dbh);
	foreach ($mounts as $mp) {
		$icon = sataMountExists($mp['name']) ? LIB_MOUNT_OK : LIB_MOUNT_FAILED;
		$driveStats = $icon == LIB_MOUNT_OK ? formatDriveStats('/mnt/SATA/' . $mp['name']) : '';
		$device = explode(',', $mp['address'])[0];
		$_sata_mounts .= "<a href=\"lib-config.php?cmd=edit_sata_source&id=" . $mp['id'] .
			"\" class='btn-large config-btn config-btn-music-source'> " . $icon . " " .
			$mp['name'] . ' (' . $device . ' | ' . getDriveFormat($device) . ')' . $driveStats . "</a>";
	}
	if ($mounts === true) {
		$_sata_mounts .= '<span class="btn-large config-btn config-btn-music-source config-btn-music-source-none">None configured</span>';
	} else if ($mounts === false) {
		$_sata_mounts .= '<span class="btn-large config-btn config-btn-music-source">Query failed</span>';
	}

	// USB auto-mounts
	$mounts = sysCmd('ls -1 /media');
	foreach ($mounts as $mountName) {
		$icon = usbMountExists($mountName) ? LIB_MOUNT_OK : LIB_MOUNT_FAILED;
		$driveStats = $icon == LIB_MOUNT_OK ? formatDriveStats('/media/' . $mountName) : '';
		$device = sysCmd('mount | grep "/media/' . $mountName . '"' . " | awk '{print $1}'")[0];
		$_usb_mounts .= '<span class="btn-large config-btn config-btn-music-source" style="color:var(--accentxts);"> ' . $icon . ' ' .
			$mountName . ' (' . $device . ' | ' . getDriveFormat($device) . ')' . $driveStats . '</span>';
	}
	if (empty($mounts)) {
		$_usb_mounts .= '<span class="btn-large config-btn config-btn-music-source config-btn-music-source-none">None auto-mounted</span>';
	}

	// Folder view only
	$autoClick = " onchange=\"autoClick('#btn-set-lib-fv-only');\"";
	$_select['lib_fv_only_on'] = "<input type=\"radio\" name=\"lib_fv_only\" id=\"toggle-lib-fv-only-1\" value=\"on\" " . (($_SESSION['lib_fv_only'] == 'on') ? "checked=\"checked\"" : "") . $autoClick . ">\n";
	$_select['lib_fv_only_off'] = "<input type=\"radio\" name=\"lib_fv_only\" id=\"toggle-lib-fv-only-2\" value=\"off\" " . (($_SESSION['lib_fv_only'] == 'off') ? "checked=\"checked\"" : "") . $autoClick . ">\n";

	// Ignore .cue files
	$autoClick = " onchange=\"autoClick('#btn-set-cuefiles-ignore');\"";
	$_select['cuefiles_ignore_on'] = "<input type=\"radio\" name=\"cuefiles_ignore\" id=\"toggle-cuefiles-ignore-1\" value=\"1\" " . (($_SESSION['cuefiles_ignore'] == '1') ? "checked=\"checked\"" : "") . $autoClick . ">\n";
	$_select['cuefiles_ignore_off'] = "<input type=\"radio\" name=\"cuefiles_ignore\" id=\"toggle-cuefiles-ignore-2\" value=\"0\" " . (($_SESSION['cuefiles_ignore'] == '0') ? "checked=\"checked\"" : "") . $autoClick . ">\n";

	// Ignore moode files
	$autoClick = " onchange=\"autoClick('#btn-set-moodefiles-ignore');\"";
	$_select['moodefiles_ignore_on'] = "<input type=\"radio\" name=\"moodefiles_ignore\" id=\"toggle-moodefiles-ignore-1\" value=\"1\" " . (($_SESSION['moodefiles_ignore'] == '1') ? "checked=\"checked\"" : "") . $autoClick . ">\n";
	$_select['moodefiles_ignore_off'] = "<input type=\"radio\" name=\"moodefiles_ignore\" id=\"toggle-moodefiles-ignore-2\" value=\"0\" " . (($_SESSION['moodefiles_ignore'] == '0') ? "checked=\"checked\"" : "") . $autoClick . ">\n";

	// DB update status
	if (false !== ($sock = openMpdSock('localhost', 6600))) {
		$stats = getMpdStats($sock);
		closeMpdSock($sock);
		$msg = $stats['artists'] . ' artists, ' . $stats['albums'] . ' albums, ' .  $stats['songs'] . ' songs';
	} else {
		$msg = 'CRITICAL ERROR: chkLibraryUpdate() failed: Unable to connect to MPD';
	}
	$_dbupdate_status = $_SESSION['mpd_dbupdate_status'] == '0' ? $msg : 'Files indexed: ' . $_SESSION['mpd_dbupdate_status'];

	// Thumbcache status
	$_thmcache_status = $_SESSION['thmcache_status'];
}

// NAS SOURCE CONFIG
if (isset($_GET['cmd']) && ($_GET['cmd'] == 'edit_nas_source' || $_GET['cmd'] == 'add_nas_source')) {
	$tpl = 'lib-nas-config.html';

	if (isset($_GET['id']) && !empty($_GET['id'])) {
		// Edit
		$_action = 'edit_nas_source';
		$_id = $_GET['id'];
		$mounts = sqlQuery("SELECT * FROM cfg_source WHERE type in ('" . LIB_MOUNT_TYPE_NFS . "', '" . LIB_MOUNT_TYPE_SMB . "')", $dbh);

		foreach ($mounts as $mp) {
			if ($mp['id'] == $_id) {
				$_protocol = "<option value=\"" . ($mp['type'] == LIB_MOUNT_TYPE_SMB ? LIB_MOUNT_TYPE_SMB . "\">SMB (Samba)</option>" : "nfs\">NFS</option>");
				$server = isset($_POST['nas_manualserver']) && !empty(trim($_POST['nas_manualserver'])) ? $_POST['nas_manualserver'] : $mp['address'] . '/' . $mp['remotedir'];
				$_address .= sprintf('<option value="%s" %s>%s</option>\n', $server, 'selected', $server);
				$_username = $mp['username'];
				if (empty($mp['password'])) {
					$_password = '';
					$_pwd_input_format = 'password';
				} else {
					$_password = 'Password set';
					$_pwd_input_format = 'text';
				}
				$_name = $mp['name'];
				$_charset = $mp['charset'];
				$_rsize = $mp['rsize'];
				$_wsize = $mp['wsize'];
				$_options = $mp['options'];
				$_error = $mp['error'];
				$_scan_btn_hide = '';
				$_edit_server_hide = '';
				$_userid_pwd_hide = $mp['type'] == LIB_MOUNT_TYPE_NFS ? 'hide' : '';
				$_advanced_options_hide = '';
				$_rw_size_hide = $mp['type'] == LIB_MOUNT_TYPE_NFS ? 'hide' : '';
				if (empty($_error)) {
					$_hide_nas_mount_error = 'style="display:none;"';
				} else {
					$_hide_nas_mount_error = '';
					$_nas_mount_error_msg = LIB_MOUNT_FAILED . 'Click to view the mount error.';
					$_moode_log = "\n" . implode("\n", sysCmd('cat ' . MOODE_LOG . ' | grep -A 1 "TRY: mount"'));
				}
			}
		}

		phpSession('open');
		$_SESSION['nas_src_action'] = $_action;
		$_SESSION['nas_src_mpid'] = $_id;
		phpSession('close');
	} else if ($_GET['cmd'] == 'add_nas_source') {
		// Add
		$_action = 'add_nas_source';
		$_hide_remove_nas_source = 'hide';
		$_hide_nas_mount_error = 'style="display:none;"';
		$_pwd_input_format = 'password';

		// Manual server entry/edit or scanner
		if (isset($_POST['nas_manualserver']) || isset($_POST['scan'])) {
			if ($_POST['mounttype'] == LIB_MOUNT_TYPE_SMB || $_POST['mount']['type'] == LIB_MOUNT_TYPE_SMB) {
				$_protocol = "<option value=\"" . LIB_MOUNT_TYPE_SMB . "\" selected>SMB (Samba)</option>\n";
				$_protocol .= "<option value=\"nfs\">NFS</option>\n";
				$_scan_btn_hide = '';
				$_edit_server_hide = '';
				$_userid_pwd_hide = '';
				$_advanced_options_hide = '';
				$_rw_size_hide = '';
				$_options = 'ro,noserverino,cache=none,dir_mode=0777,file_mode=0777';
			} else if ($_POST['mounttype'] == LIB_MOUNT_TYPE_NFS || $_POST['mount']['type'] == LIB_MOUNT_TYPE_NFS) {
				$_protocol = "<option value=\"cifs\">SMB (Samba)</option>\n";
				$_protocol .= "<option value=\"nfs\" selected>NFS</option>\n";
				$_scan_btn_hide = '';
				$_edit_server_hide = '';
				$_userid_pwd_hide = 'hide';
				$_advanced_options_hide = '';
				$_rw_size_hide = 'hide';
				$_options = 'soft,timeo=10,retrans=1,ro,nolock';
			}
		} else {
			// CIFS (default))
			$_protocol = "<option value=\"cifs\" selected>SMB (Samba)</option>\n";
			$_protocol .= "<option value=\"nfs\">NFS</option>\n";
			$_options = 'ro,noserverino,cache=none,dir_mode=0777,file_mode=0777';
		}

		$server = isset($_POST['nas_manualserver']) && !empty(trim($_POST['nas_manualserver'])) ? $_POST['nas_manualserver'] : ' '; // Space for select
		$_address .= sprintf('<option value="%s" %s>%s</option>\n', $server, 'selected', $server);
		$_rsize = '61440';
		$_wsize = '65536';

		phpSession('open');
		$_SESSION['nas_src_action'] = $_action;
		$_SESSION['nas_src_mpid'] = '';
		phpSession('close');
	}
}

// NVME SOURCE CONFIG
if (isset($_GET['cmd']) && ($_GET['cmd'] == 'edit_nvme_source' || $_GET['cmd'] == 'add_nvme_source')) {
	$tpl = 'lib-nvme-config.html';

	if (isset($_GET['id']) && !empty($_GET['id'])) {
		// Edit
		$_action = 'edit_nvme_source';
		$_id = $_GET['id'];
		$mounts = sqlQuery("SELECT * FROM cfg_source WHERE type = '" . LIB_MOUNT_TYPE_NVME . "'", $dbh);

		foreach ($mounts as $mp) {
			if ($mp['id'] == $_id) {
				$parts = explode(',', $mp['address']);
				$selectName = $parts[0] . ' (' . $parts[1] . ')';
				$_nvme_drives .= sprintf('<option value="%s" %s>%s</option>\n', $mp['address'], '', $selectName);
				$_name = $mp['name'];
				$_error = $mp['error'];
				if (empty($_error)) {
					$_hide_nvme_mount_error = 'style="display:none;"';
				} else {
					$_hide_nvme_mount_error = '';
					$_nvme_mount_error_msg = LIB_MOUNT_FAILED . 'Click to view the mount error.';
					$_moode_log = "\n" . implode("\n", sysCmd('cat ' . MOODE_LOG . ' | grep -A 1 "TRY: mount"'));
				}
			}
		}
		phpSession('open');
		$_SESSION['nvme_src_action'] = $_action;
		$_SESSION['nvme_src_mpid'] = $_id;
		phpSession('close');
	} else if ($_GET['cmd'] == 'add_nvme_source') {
		// Add
		$_action = 'add_nvme_source';
		$_hide_remove_nvme_source = 'hide';
		$_hide_nvme_mount_error = 'style="display:none;"';

		// NVMe drives
		$drives = nvmeListDrives();
		if (empty($drives)) {
			$_nvme_drives = sprintf('<option value="%s" %s>%s</option>\n', 'none,none', 'selected', 'None');
		} else {
			foreach ($drives as $device => $status) {
				$selected = '';
				$_nvme_drives .= sprintf('<option value="%s" %s>%s</option>\n',
					$device . ',' . $status, $selected, $device . ' (' . $status . ')');
			}
		}

		phpSession('open');
		$_SESSION['nvme_src_action'] = $_action;
		$_SESSION['nvme_src_mpid'] = $_id;
		phpSession('close');
	}
}
// NVME FORMAT DRIVE
if (isset($_GET['cmd']) && $_GET['cmd'] == 'format_nvme_drive') {
	$tpl = 'lib-nvme-format.html';

	// NVMe drives
	$drives = nvmeListDrives();
	if (empty($drives)) {
		$_nvme_drives = sprintf('<option value="%s" %s>%s</option>\n', 'none,none', 'selected', 'None');
	} else {
		foreach ($drives as $device => $status) {
			$selected = '';
			$_nvme_drives .= sprintf('<option value="%s" %s>%s</option>\n',
				$device . ',' . $status, $selected, $device . ' (' . $status . ')');
		}
	}
}

// SATA SOURCE CONFIG
if (isset($_GET['cmd']) && ($_GET['cmd'] == 'edit_sata_source' || $_GET['cmd'] == 'add_sata_source')) {
	$tpl = 'lib-sata-config.html';

	if (isset($_GET['id']) && !empty($_GET['id'])) {
		// Edit
		$_action = 'edit_sata_source';
		$_id = $_GET['id'];
		$mounts = sqlQuery("SELECT * FROM cfg_source WHERE type = '" . LIB_MOUNT_TYPE_SATA . "'", $dbh);

		foreach ($mounts as $mp) {
			if ($mp['id'] == $_id) {
				$parts = explode(',', $mp['address']);
				$selectName = $parts[0] . ' (' . $parts[1] . ')';
				$_sata_drives .= sprintf('<option value="%s" %s>%s</option>\n', $mp['address'], '', $selectName);
				$_name = $mp['name'];
				$_error = $mp['error'];
				if (empty($_error)) {
					$_hide_sata_mount_error = 'style="display:none;"';
				} else {
					$_hide_sata_mount_error = '';
					$_sata_mount_error_msg = LIB_MOUNT_FAILED . 'Click to view the mount error.';
					$_moode_log = "\n" . implode("\n", sysCmd('cat ' . MOODE_LOG . ' | grep -A 1 "TRY: mount"'));
				}
			}
		}
		phpSession('open');
		$_SESSION['sata_src_action'] = $_action;
		$_SESSION['sata_src_mpid'] = $_id;
		phpSession('close');
	} else if ($_GET['cmd'] == 'add_sata_source') {
		// Add
		$_action = 'add_sata_source';
		$_hide_remove_sata_source = 'hide';
		$_hide_sata_mount_error = 'style="display:none;"';

		// SATA drives
		$drives = sataListDrives();
		if (empty($drives)) {
			$_sata_drives = sprintf('<option value="%s" %s>%s</option>\n', 'none,none', 'selected', 'None');
		} else {
			foreach ($drives as $device => $status) {
				$selected = '';
				$_sata_drives .= sprintf('<option value="%s" %s>%s</option>\n',
					$device . ',' . $status, $selected, $device . ' (' . $status . ')');
			}
		}

		phpSession('open');
		$_SESSION['sata_src_action'] = $_action;
		$_SESSION['sata_src_mpid'] = $_id;
		phpSession('close');
	}
}

$section = basename(__FILE__, '.php');
storeBackLink($section, $tpl);

include('header.php');
eval("echoTemplate(\"".getTemplate("templates/$tpl")."\");");
include('footer.php');
