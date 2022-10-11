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

require_once __DIR__ . '/inc/common.php';
require_once __DIR__ . '/inc/mpd.php';
require_once __DIR__ . '/inc/music-library.php';
require_once __DIR__ . '/inc/music-source.php';
require_once __DIR__ . '/inc/session.php';
require_once __DIR__ . '/inc/sql.php';

$dbh = sqlConnect();
phpSession('open');

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
		$_SESSION['notify']['title'] = 'Settings updated';
		phpSession('write', 'usb_auto_updatedb', $_POST['usb_auto_updatedb']);
	}
}
// Ignore cuefiles on library scan
if (isset($_POST['update_cuefiles_ignore'])) {
	if (isset($_POST['cuefiles_ignore']) && $_POST['cuefiles_ignore'] != $_SESSION['cuefiles_ignore']) {
		$_SESSION['notify']['title'] = 'Settings updated';
		phpSession('write', 'cuefiles_ignore', $_POST['cuefiles_ignore']);
		setCuefilesIgnore($_SESSION['cuefiles_ignore']);
	}
}
// Re-mount nas sources
if (isset($_POST['remount_sources'])) {
	$result = sqlRead('cfg_source', $dbh);
	if ($result === true) {
		$_SESSION['notify']['title'] = 'No sources configured';
	} else {
		$resultUnmount = sourceMount('unmountall');
		$resultMount = sourceMount('mountall');
		//workerLog('lib-config: remount_sources: (' . $resultUnmount . ', ' . $resultMount . ')');
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
	$result = sysCmd('pgrep -l thumb-gen.php');
	if (strpos($result[0], 'thumb-gen.php') !== false) {
		$_SESSION['notify']['title'] = 'Process is currently running';
	} else {
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
	// Validate
	$id = sqlQuery("SELECT id from cfg_source WHERE name='" . $_POST['mount']['name'] . "'", $dbh);
	$name = strtolower($_POST['mount']['name']);
	$address = explode('/', $_POST['mount']['address'], 2);
	$_POST['mount']['address'] = $address[0];
	$_POST['mount']['remotedir'] = $address[1];

	if (empty(trim($_POST['mount']['address']))) {
		$_SESSION['notify']['title'] = 'Path cannot be blank';
		$_SESSION['notify']['duration'] = 5;
	} else if (empty(trim($_POST['mount']['remotedir']))) {
		$_SESSION['notify']['title'] = 'Share cannot be blank';
		$_SESSION['notify']['duration'] = 5;
	} else if ($_POST['mount']['type'] == 'cifs' && empty(trim($_POST['mount']['username']))) {
		$_SESSION['notify']['title'] = 'Userid cannot be blank';
		$_SESSION['notify']['duration'] = 5;
	} else if ($_POST['mount']['action'] == 'add' && !empty($id[0])) {
		$_SESSION['notify']['title'] = 'Name already exists';
		$_SESSION['notify']['duration'] = 5;
	} else if (empty(trim($_POST['mount']['name']))) {
		$_SESSION['notify']['title'] = 'Name cannot be blank';
		$_SESSION['notify']['duration'] = 5;
	} else {
		$initiateLibraryUpd = true;
		// CIFS and NFS defaults if blank
		if (empty(trim($_POST['mount']['rsize']))) {$_POST['mount']['rsize'] = 61440;}
		if (empty(trim($_POST['mount']['wsize']))) {$_POST['mount']['wsize'] = 65536;}
		if (empty(trim($_POST['mount']['options']))) {
			if ($_POST['mount']['type'] == 'cifs') {
				$_POST['mount']['options'] = "vers=1.0,ro,noserverino,dir_mode=0777,file_mode=0777";
			} else if ($_POST['mount']['type'] == 'nfs') {
				$_POST['mount']['options'] = "soft,timeo=10,retrans=1,ro,nolock";
			}
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

	// SMB (Samba)
	if ($_POST['mount']['type'] == 'cifs') {
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
	if ($_POST['mount']['type'] == 'nfs') {
		$thisIpAddr = sysCmd('hostname -I')[0];
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
	$_GET['cmd'] = $_SESSION['src_action'];
	$_GET['id'] = $_SESSION['src_mpid'];
}

phpSession('close');

waitWorker(1, 'lib-config');

// Update library if indicated after sourcecfg job completes
if ($initiateLibraryUpd == true) {
	//$title = isset($_POST['save']) ? 'Music source saved' : 'Music source removed';
	//submitJob('update_library', '', $title, 'Updating library...');
	phpSession('open');
	$_SESSION['notify']['title'] = isset($_POST['save']) ? 'Music source saved' : 'Music source removed';
	phpSession('close');
	unset($_GET['cmd']);
}

// LIB CONFIG FORM
if (!isset($_GET['cmd'])) {
	$tpl = "lib-config.html";

	// Display list of music sources if any
	$mounts = sqlRead('cfg_source', $dbh);
	foreach ($mounts as $mp) {
		$icon = mountExists($mp['name']) ? "<i class='fas fa-check green sx'></i>" : "<i class='fas fa-times red sx'></i>";
		$_mounts .= "<p><a href=\"lib-config.php?cmd=edit&id=" . $mp['id'] . "\" class='btn btn-large' style='width:70%;background-color:#333;text-align:left;'> " . $icon . " " . $mp['name'] . " (" . $mp['address'] . ") </a></p>";
	}

	// Messages
	if ($mounts === true) {
		$_mounts .= '<p class="btn btn-large" style="width:70%;background-color:#333;text-align:left;">None configured</p><p></p>';
	} else if ($mounts === false) {
		$_mounts .= '<p class="btn btn-large" style="width:70%;background-color:#333;text-align:left;">Query failed</p>';
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
		$mounts = sqlRead('cfg_source',$dbh);

		foreach ($mounts as $mp) {
			if ($mp['id'] == $_id) {
				$_protocol = "<option value=\"" . ($mp['type'] == 'cifs' ? "cifs\">SMB (Samba)</option>" : "nfs\">NFS</option>");
				$server = isset($_POST['nas_manualserver']) && !empty(trim($_POST['nas_manualserver'])) ? $_POST['nas_manualserver'] : $mp['address'] . '/' . $mp['remotedir'];
				$_address .= sprintf('<option value="%s" %s>%s</option>\n', $server, 'selected', $server);
				$_username = $mp['username'];
				$_password = $mp['password'];
				$_name = $mp['name'];
				$_charset = $mp['charset'];
				$_rsize = $mp['rsize'];
				$_wsize = $mp['wsize'];
				$_options = $mp['options'];
				$_error = $mp['error'];
				$_scan_btn_hide = '';
				$_edit_server_hide = '';
				$_userid_pwd_hide = $mp['type'] == 'nfs' ? 'hide' : '';
				$_advanced_options_hide = '';
				$_rw_size_hide = $mp['type'] == 'nfs' ? 'hide' : '';
				if (empty($_error)) {
					$_hide_error = 'hide';
				} else {
					$_moode_log = "\n" . file_get_contents(MOODE_LOG);
				}
			}
		}

		$_action = 'edit';

		phpSession('open');
		$_SESSION['src_action'] = $_action;
		$_SESSION['src_mpid'] = $_id;
		phpSession('close');
	} else if ($_GET['cmd'] == 'add') {
		// Create
		$_hide_remove = 'hide';
		$_hide_error = 'hide';

		//workerLog(print_r($_POST, true));

		// Manual server entry/edit or scanner
		if (isset($_POST['nas_manualserver']) || isset($_POST['scan'])) {
			if ($_POST['mounttype'] == 'cifs' || $_POST['mount']['type'] == 'cifs') {
				$_protocol = "<option value=\"cifs\" selected>SMB (Samba)</option>\n";
				$_protocol .= "<option value=\"nfs\">NFS</option>\n";
				$_scan_btn_hide = '';
				$_edit_server_hide = '';
				$_userid_pwd_hide = '';
				$_advanced_options_hide = '';
				$_rw_size_hide = '';
				$_options = 'ro,noserverino,dir_mode=0777,file_mode=0777';
			} else if ($_POST['mounttype'] == 'nfs' || $_POST['mount']['type'] == 'nfs') {
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
			$_options = 'ro,noserverino,dir_mode=0777,file_mode=0777';
		}

		$server = isset($_POST['nas_manualserver']) && !empty(trim($_POST['nas_manualserver'])) ? $_POST['nas_manualserver'] : ' '; // Space for select
		$_address .= sprintf('<option value="%s" %s>%s</option>\n', $server, 'selected', $server);
		$_rsize = '61440';
		$_wsize = '65536';

		$_action = 'add';

		phpSession('open');
		$_SESSION['src_action'] = $_action;
		$_SESSION['src_mpid'] = '';
		phpSession('close');
	}
}

$section = basename(__FILE__, '.php');
storeBackLink($section, $tpl);

include('header.php');
eval("echoTemplate(\"".getTemplate("templates/$tpl")."\");");
include('footer.php');
