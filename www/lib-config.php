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

require_once dirname(__FILE__) . '/inc/playerlib.php';

if (false === ($sock = openMpdSock('localhost', 6600))) {
	$msg = 'lib-config: Connection to MPD failed';
	workerLog($msg);
	exit($msg . "\n");
}
else {
	playerSession('open', '' ,'');
	$dbh = cfgdb_connect();
}

// For save/remove actions
$initiateLibraryUpd = false;

// LIB CONFIG POSTS

// Rescan mpd database
if (isset($_POST['regen_library'])) {
	submitJob('regen_library', '', 'Regenerating library...', 'Stay on this screen until the progress spinner is cleared');
}
// Auto-update mpd db on usb insert or remove
if (isset($_POST['update_usb_auto_updatedb'])) {
	if (isset($_POST['usb_auto_updatedb']) && $_POST['usb_auto_updatedb'] != $_SESSION['usb_auto_updatedb']) {
		$_SESSION['notify']['title'] = $_POST['usb_auto_updatedb'] == '1' ? 'MPD auto-update on' : 'MPD auto-update off';
		$_SESSION['notify']['duration'] = 3;
		playerSession('write', 'usb_auto_updatedb', $_POST['usb_auto_updatedb']);
	}
}
// Ignore cuefiles on library scan
if (isset($_POST['update_cuefiles_ignore'])) {
	if (isset($_POST['cuefiles_ignore']) && $_POST['cuefiles_ignore'] != $_SESSION['cuefiles_ignore']) {
		$_SESSION['notify']['title'] = $_POST['cuefiles_ignore'] == '1' ? 'MPD ignore CUE files' : 'MPD ignore CUE files';
		$_SESSION['notify']['duration'] = 3;
		playerSession('write', 'cuefiles_ignore', $_POST['cuefiles_ignore']);
		setCuefilesIgnore($_SESSION['cuefiles_ignore']);
	}
}
// Re-mount nas sources
if (isset($_POST['remount_sources'])) {
	$result = cfgdb_read('cfg_source', $dbh);
	if ($result === true) {
		$_SESSION['notify']['title'] = 'No sources configured';
	}
	else {
		$result_unmount = sourceMount('unmountall');
		$result_mount = sourceMount('mountall');
		//workerLog('lib-config: remount_sources: (' . $result_unmount . ', ' . $result_mount . ')');
		$_SESSION['notify']['title'] = 'Re-mounting music sources...';
	}
}
// Clear library cache
if (isset($_POST['clear_libcache'])) {
	clearLibCacheAll();
	$_SESSION['notify']['title'] = 'Library tag cache cleared';
}
// Regenerate thumbnail cache
if (isset($_POST['regen_thmcache'])) {
	$result = sysCmd('pgrep -l thmcache.php');
	if (strpos($result[0], 'thmcache.php') !== false) {
		$_SESSION['notify']['title'] = 'Process is currently running';
	}
	else {
		$_SESSION['thmcache_status'] = 'Regenerating thumbnail cache...';
		submitJob('regen_thmcache', '', 'Regenerating thumbnail cache...', '');
	}
}

// SOURCE CONFIG POSTS

// Remove source
if (isset($_POST['delete']) && $_POST['delete'] == 1) {
	$initiateLibraryUpd = true;
	$_POST['mount']['action'] = 'delete';
	$_POST['mount']['id'] = $_SESSION['src_mpid'];
	submitJob('sourcecfg', $_POST, '', '');
}
// Save source
if (isset($_POST['save']) && $_POST['save'] == 1) {
	// validate
	$id = sdbquery("SELECT id from cfg_source WHERE name='" . $_POST['mount']['name'] . "'", $dbh);
	$name = strtolower($_POST['mount']['name']);
	$address = explode('/', $_POST['mount']['address'], 2);
	$_POST['mount']['address'] = $address[0];
	$_POST['mount']['remotedir'] = $address[1];

	// Server
	if (empty(trim($_POST['mount']['address']))) {
		$_SESSION['notify']['title'] = 'Path cannot be blank';
		$_SESSION['notify']['duration'] = 5;
	}
	// Share
	elseif ($_POST['mount']['type'] != 'upnp' && empty(trim($_POST['mount']['remotedir']))) {
		$_SESSION['notify']['title'] = 'Share cannot be blank';
		$_SESSION['notify']['duration'] = 5;
	}
	// Userid
	elseif ($_POST['mount']['type'] == 'cifs' && empty(trim($_POST['mount']['username']))) {
		$_SESSION['notify']['title'] = 'Userid cannot be blank';
		$_SESSION['notify']['duration'] = 5;
	}
	// Name
	elseif ($_POST['mount']['action'] == 'add' && !empty($id[0])) {
		$_SESSION['notify']['title'] = 'Name already exists';
		$_SESSION['notify']['duration'] = 5;
	}
	elseif (empty(trim($_POST['mount']['name']))) {
		$_SESSION['notify']['title'] = 'Name cannot be blank';
		$_SESSION['notify']['duration'] = 5;
	}
	// Ok so save
	else {
		$initiateLibraryUpd = true;
		// CIFS and NFS defaults if blank
		if ($_POST['mount']['type'] != 'upnp') {
			if (empty(trim($_POST['mount']['rsize']))) {$_POST['mount']['rsize'] = 61440;}
			if (empty(trim($_POST['mount']['wsize']))) {$_POST['mount']['wsize'] = 65536;}
			if (empty(trim($_POST['mount']['options']))) {
				if ($_POST['mount']['type'] == 'cifs') {
					$_POST['mount']['options'] = "vers=1.0,ro,dir_mode=0777,file_mode=0777";
				}
				elseif ($_POST['mount']['type'] == 'nfs') {
					$_POST['mount']['options'] = "ro,nolock";
				}
			}
		}
		// UPnP
		else {
			//$_POST['mount']['remotedir'] = '';
			$_POST['mount']['username'] = '';
			$_POST['mount']['password'] = '';
			$_POST['mount']['charset'] = '';
			$_POST['mount']['rsize'] = '';
			$_POST['mount']['wsize'] = '';
			$_POST['mount']['options'] = '';
		}
		// $array['mount']['key'] must be in column order for subsequent table insert
		// Table cols = id, name, type, address, remotedir, username, password, charset, rsize, wsize, options, error
		// New id is auto generated, action = add, edit, delete
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

		submitJob('sourcecfg', $array, '', '');
	}
}
// Scanner
if (isset($_POST['scan']) && $_POST['scan'] == 1) {
	$_GET['cmd'] = $_SESSION['src_action'];
	$_GET['id'] = $_SESSION['src_mpid'];
	// Samba
	if ($_POST['mount']['type'] == 'cifs') {
		$nmblookup_result = sysCmd("nmblookup -S -T '*' | grep '*<00>' | cut -f 1 -d '*'");
		sort($nmblookup_result, SORT_NATURAL | SORT_FLAG_CASE);
		foreach ($nmblookup_result as $nmblookup_line) {
			$nmblookup_part = explode(', ', $nmblookup_line); // [0] = host.domain, [1] = IP address

			$smbclient_result = sysCmd("smbclient -N -g -L " . trim($nmblookup_part[1]) . " | grep Disk | cut -f 2 -d '|'");
			sort($smbclient_result, SORT_NATURAL | SORT_FLAG_CASE);
			$host = strtoupper(explode('.', $nmblookup_part[0])[0] );
			foreach ($smbclient_result as $share) {
				$_address .= sprintf('<option value="%s" %s>%s</option>\n', $host . '/' .$share, '', $host . '/' .$share);
			}
		}
	}
	// UPnP
	elseif ($_POST['mount']['type'] == 'upnp') {
		$path = trim($_POST['mount']['address']);

		if ($path == '..') {
			// '.' means we are at /mnt/upnp
			$path = dirname($_SESSION['saved_upnp_path']) == '.' ? '' : dirname($_SESSION['saved_upnp_path']);
		}

		$result = sysCmd('find "/mnt/UPNP/' . $path . '" -maxdepth 1 -type d');
		$_address = sprintf('<option value="%s" %s>%s</option>\n', '..', '', '..');

		foreach ($result as $dir) {
			$dir = substr($dir, 10); // strip out /mnt/UPNP/
			if (!empty($dir) && substr($dir, 0, 1) != '.' && stripos($dir, '_search') === false && stripos($dir, '/.') === false) {
				$_address .= sprintf('<option value="%s" %s>%s</option>\n', $dir, '', $dir);
			}
		}

		if ($path != '..' && $path != '.') {
			session_start();
			$_SESSION['saved_upnp_path'] = $path;
			session_write_close();
		}
	}
}
// Manual entry
if (isset($_POST['manualentry']) && $_POST['manualentry'] == 1) {
	$_GET['cmd'] = $_SESSION['src_action'];
	$_GET['id'] = $_SESSION['src_mpid'];
}

session_write_close();

// Update library if indicated after sourcecfg job completes
waitWorker(1, 'lib-config');
if ($initiateLibraryUpd == true) {
	//$title = isset($_POST['save']) ? 'Music source saved' : 'Music source removed';
	//submitJob('update_library', '', $title, 'Updating library...');
	session_start();
	$_SESSION['notify']['title'] = isset($_POST['save']) ? 'Music source saved' : 'Music source removed';
	$_SESSION['notify']['duration'] = 3;
	session_write_close();
	unset($_GET['cmd']);
}

// LIB CONFIG FORM
if (!isset($_GET['cmd'])) {
	$tpl = "lib-config.html";

	// Display list of music sources if any
	$mounts = cfgdb_read('cfg_source', $dbh);
	foreach ($mounts as $mp) {
		// UPnP
		if ($mp['type'] == 'upnp') {
			$result = sysCmd('"/var/lib/mpd/music/' . $mp['address'] . '"');
			$icon = $result[0] != '' ? "<i class='fas fa-check green sx'></i>" : "<i class='fas fa-times red sx'></i>";
		}
		// CIFS and NFS
		else {
			$icon = mountExists($mp['name']) ? "<i class='fas fa-check green sx'></i>" : "<i class='fas fa-times red sx'></i>";
		}
		$_mounts .= "<p><a href=\"lib-config.php?cmd=edit&id=" . $mp['id'] . "\" class='btn btn-large' style='width:240px;background-color:#333;text-align:left;'> " . $icon . " " . $mp['name'] . " (" . $mp['address'] . ") </a></p>";
	}

	// Messages
	if ($mounts === true) {
		$_mounts .= '<p class="btn btn-large" style="width: 240px; background-color: #333;">None configured</p><p></p>';
	}
	elseif ($mounts === false) {
		$_mounts .= '<p class="btn btn-large" style="width: 240px; background-color: #333;">Query failed</p>';
	}

	// Auto-updatedb on usb insert/remove
	$_select['usb_auto_updatedb1'] = "<input type=\"radio\" name=\"usb_auto_updatedb\" id=\"toggle_usb_auto_updatedb0\" value=\"1\" " . (($_SESSION['usb_auto_updatedb'] == '1') ? "checked=\"checked\"" : "") . ">\n";
	$_select['usb_auto_updatedb0'] = "<input type=\"radio\" name=\"usb_auto_updatedb\" id=\"toggle_usb_auto_updatedb1\" value=\"0\" " . (($_SESSION['usb_auto_updatedb'] == '0') ? "checked=\"checked\"" : "") . ">\n";

	// Ignore cuefiles on lbirary scan
	$_select['cuefiles_ignore1'] = "<input type=\"radio\" name=\"cuefiles_ignore\" id=\"toggle_cuefiles_ignore0\" value=\"1\" " . (($_SESSION['cuefiles_ignore'] == '1') ? "checked=\"checked\"" : "") . ">\n";
	$_select['cuefiles_ignore0'] = "<input type=\"radio\" name=\"cuefiles_ignore\" id=\"toggle_cuefiles_ignore1\" value=\"0\" " . (($_SESSION['cuefiles_ignore'] == '0') ? "checked=\"checked\"" : "") . ">\n";

	// Thumbcache status
	$_thmcache_status = $_SESSION['thmcache_status'];
}

// SOURCE CONFIG FORM
if (isset($_GET['cmd']) && !empty($_GET['cmd'])) {
	$tpl = 'src-config.html';

	// Edit
	if (isset($_GET['id']) && !empty($_GET['id'])) {
		$_id = $_GET['id'];
		$mounts = cfgdb_read('cfg_source',$dbh);

		foreach ($mounts as $mp) {
			if ($mp['id'] == $_id) {
				$_protocol = "<option value=\"" . ($mp['type'] == 'cifs' ? "cifs\">SMB (Samba)</option>" : ($mp['type'] == 'nfs' ? "nfs\">NFS</option>" : "upnp\">UPnP</option>"));
				$server = isset($_POST['nas_manualserver']) && !empty(trim($_POST['nas_manualserver'])) ? $_POST['nas_manualserver'] : $mp['address'] . '/' . $mp['remotedir'];
				$_address .= sprintf('<option value="%s" %s>%s</option>\n', $server, 'selected', $server);
				$_scan_btn_hide = $mp['type'] == 'nfs' ? 'hide' : '';
				$_edit_server_hide = $mp['type'] == 'upnp' ? 'hide' : '';
				$_userid_pwd_hide = $mp['type'] != 'cifs' ? 'hide' : '';
				$_advanced_options_hide = $mp['type'] == 'upnp' ? 'hide' : '';
				$_username = $mp['username'];
				$_password = $mp['password'];
				$_name = $mp['name'];
				$_charset = $mp['charset'];
				$_rsize = $mp['rsize'];
				$_wsize = $mp['wsize'];
				$_options = $mp['options'];
				$_error = $mp['error'];
				if (empty($_error)) {
					$_hide_error = 'hide';
				}
				else {
					$_moode_log = "\n" . file_get_contents(MOODE_LOG);
				}
			}
		}

		$_action = 'edit';

		session_start();
		$_SESSION['src_action'] = $_action;
		$_SESSION['src_mpid'] = $_id;
		session_write_close();
	}
	// Create
	elseif ($_GET['cmd'] == 'add') {
		$_hide_remove = 'hide';
		$_hide_error = 'hide';

		// Manual server entry/edit for cifs and nfs
		if (isset($_POST['nas_manualserver'])) {
			if ($_POST['mounttype'] == 'cifs' || empty($_POST['mounttype'])) {
				$_protocol = "<option value=\"cifs\" selected>SMB (Samba)</option>\n";
				$_protocol .= "<option value=\"nfs\">NFS</option>\n";
				$_protocol .= "<option value=\"upnp\">UPnP</option>\n";
				$_scan_btn_hide = '';
				$_edit_server_hide = '';
				$_userid_pwd_hide = '';
				$_advanced_options_hide = '';
				$_options = 'ro,dir_mode=0777,file_mode=0777';
			}
			elseif ($_POST['mounttype'] == 'nfs') {
				$_protocol = "<option value=\"cifs\">SMB (Samba)</option>\n";
				$_protocol .= "<option value=\"nfs\" selected>NFS</option>\n";
				$_protocol .= "<option value=\"upnp\">UPnP</option>\n";
				$_scan_btn_hide = 'hide';
				$_edit_server_hide = '';
				$_userid_pwd_hide = 'hide';
				$_advanced_options_hide = '';
				$_options = 'ro,nolock';
			}
		}
		// UPnP
		elseif ($_POST['mount']['type'] == 'upnp') {
			$_protocol = "<option value=\"cifs\">SMB (Samba)</option>\n";
			$_protocol .= "<option value=\"nfs\">NFS</option>\n";
			$_protocol .= "<option value=\"upnp\" selected>UPnP</option>\n";
			$_edit_server_hide = 'hide';
			$_userid_pwd_hide = 'hide';
			$_advanced_options_hide = 'hide';
		}
		// CIFS and NFS
		else {
			$_protocol = "<option value=\"cifs\" selected>SMB (Samba)</option>\n";
			$_protocol .= "<option value=\"nfs\">NFS</option>\n";
			$_protocol .= "<option value=\"upnp\">UPnP</option>\n";
			$_options = 'ro,dir_mode=0777,file_mode=0777';
		}

		$server = isset($_POST['nas_manualserver']) && !empty(trim($_POST['nas_manualserver'])) ? $_POST['nas_manualserver'] : ' '; // space for select
		$_address .= sprintf('<option value="%s" %s>%s</option>\n', $server, 'selected', $server);
		$_rsize = '61440';
		$_wsize = '65536';

		$_action = 'add';

		session_start();
		$_SESSION['src_action'] = $_action;
		$_SESSION['src_mpid'] = '';
		session_write_close();
	}
}

$section = basename(__FILE__, '.php');
storeBackLink($section, $tpl);

include('header.php');
eval("echoTemplate(\"".getTemplate("templates/$tpl")."\");");
include('footer.php');
