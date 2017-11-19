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
 * 2017-11-11 TC moOde 4.0
 *
 */

require_once dirname(__FILE__) . '/inc/playerlib.php';

if (false === ($sock = openMpdSock('localhost', 6600))) {
	$msg = 'src-config: Connection to MPD failed'; 
	workerLog($msg);
	exit($msg . "\n");
}
else {
	playerSession('open', '' ,''); 
	$dbh = cfgdb_connect();
	session_write_close();
}

// update mpd database
if (isset($_POST['updatempd'])) {
	submitJob('updmpddb', '', 'DB update initiated...', '');
}

// rescan mpd database
if (isset($_POST['rescanmpd'])) {
	submitJob('rescanmpddb', '', 'DB rescan initiated...', '');
}

// re-mount nas sources
if (isset($_POST['remount'])) {
	$result_unmount = wrk_sourcemount('unmountall');
	$result_mount = wrk_sourcemount('mountall');
	$_SESSION['notify']['title'] = 'Re-mount initiated...';
	$_SESSION['notify']['duration'] = 6;
}

// reset library cache
if (isset($_POST['resetcache'])) {
	sysCmd('truncate /var/local/www/libcache.json --size 0');
	$_SESSION['notify']['title'] = 'Cache has been reset';
	$_SESSION['notify']['msg'] = 'Open the Library to reload the cache';
	$_SESSION['notify']['duration'] = 6;
}

// nas-config form submit
if(isset($_POST['mount']) && !empty($_POST['mount'])) {
	$_POST['mount']['remotedir'] = str_replace('\\', '/', $_POST['mount']['remotedir']); // convert slashes
	// defaults
	if ($_POST['mount']['wsize'] == '') {$_POST['mount']['wsize'] = 1048576;}
	if ($_POST['mount']['rsize'] == '') {$_POST['mount']['rsize'] = 61440;}
	// options
	if ($_POST['mount']['options'] == '') {
		if ($_POST['mount']['type'] == 'cifs') {
			$_POST['mount']['options'] = "ro,dir_mode=0777,file_mode=0777";
		}
		else {
			$_POST['mount']['options'] = "ro,nolock";
		}
	}
	// delete nas source
	if (isset($_POST['delete']) && $_POST['delete'] == 1) {
		$_POST['mount']['action'] = 'delete';
		submitJob('sourcecfg', $_POST, 'Mount point removed', 'DB update initiated...');
	}
	// save nas source
	else {
		if (strpos($_POST['mount']['name'], 'NAS') !== false || strpos($_POST['mount']['name'], 'RADIO') !== false ||strpos($_POST['mount']['name'], 'SDCARD') !== false) {
			$_SESSION['notify']['title'] = 'Source name invalid';
			$_SESSION['notify']['msg'] = 'NAS, RADIO and SDCARD cannot be used in Source name';
			$_SESSION['notify']['duration'] = 20;
		}
		elseif (empty($_POST['mount']['name'])) {
			$_SESSION['notify']['title'] = 'Source name cannot be blank';
			$_SESSION['notify']['duration'] = 20;
		}
		else {
			submitJob('sourcecfg', $_POST, 'NAS config saved', 'DB update initiated...');
		}
	}
}

// also does db update after sourcecfg job completes
waitWorker(1, 'src-config');

// display list of nas sources if any
$mounts = cfgdb_read('cfg_source',$dbh);
$tpl = "src-config.html";

foreach ($mounts as $mp) {
	if (mountExists($mp['name'])) {
		$icon = "<i class='icon-ok green sx'></i>";
	}
	else {
		$icon = "<i class='icon-remove red sx'></i>";
	}

	$_mounts .= "<p><a href=\"src-config.php?cmd=edit&id=" . $mp['id'] . "\" class='btn btn-large' style='width: 240px;'> " . $icon . " " . $mp['name'] . " (" . $mp['address'] . ") </a></p>";
}

// messages
if ($mounts === true) {
	$_mounts .= '<p class="btn btn-large" style="width: 240px;">None configured</p><p></p>';
	$_remount_disable = 'disabled';
}
elseif ($mounts === false) {
	$_mounts .= '<p class="btn btn-large" style="width: 240px;">Query failed</p>';
	$_remount_disable = '';
}

$section = basename(__FILE__, '.php');

include('/var/local/www/header.php'); 

// edit or create a nas source
if (isset($_GET['cmd']) && !empty($_GET['cmd'])) {
	// edit 
	if (isset($_GET['id']) && !empty($_GET['id'])) {
		$_id = $_GET['id'];
		foreach ($mounts as $mp) {
			if ($mp['id'] == $_id) {
				$_name = $mp['name'];
				$_address = $mp['address'];
				$_source_select['type'] = "<option value=\"" . ($mp['type'] == 'cifs' ? "cifs\">SMB/CIFS</option>" : "NFS\">NFS</option>");	
				$_remotedir = $mp['remotedir'];
				$_userid_pwd_hide = $mp['type'] == 'nfs' ? 'hide' : '';
				$_username = $mp['username'];
				$_password = $mp['password'];
				$_charset = $mp['charset'];
				$_rsize = $mp['rsize'];
				$_wsize = $mp['wsize'];
				$_options = $mp['options'];
				$_error = $mp['error'];
				if (empty($_error)) {
					$_hide_error = 'hide';
				}
				else {
					$_moode_log = "\n" . file_get_contents('/var/log/moode.log');
				}
			}
		}
		$_title = 'Settings';
		$_action = 'edit';
	}
	// create
	elseif ($_GET['cmd'] == 'add') {
		$_title = 'Settings';
		$_action = 'add';
		$_hide_remove = 'hide';
		$_hide_error = 'hide';
		$_source_select['type'] .= "<option value=\"cifs\">SMB/CIFS</option>\n";	
		$_source_select['type'] .= "<option value=\"nfs\">NFS</option>\n";	
		$_rsize = '1048576';
		$_wsize = '65536';
		$_options = 'ro,dir_mode=0777,file_mode=0777';
	}

	$tpl = 'nas-config.html';
} 

eval("echoTemplate(\"".getTemplate("templates/$tpl")."\");");

include('footer.php');
