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

require_once dirname(__FILE__) . '/../inc/playerlib.php';

//workerLog('moode.php: cmd=(' . $_GET['cmd'] . ')');
if (isset($_GET['cmd']) && $_GET['cmd'] === '') {
	workerLog('moode.php: Error: $_GET cmd is empty or missing');
	exit(0);
}

playerSession('open', '' ,'');
$dbh = cfgdb_connect();
session_write_close();

if (false === ($sock = openMpdSock('localhost', 6600))) {
	workerLog('moode.php: MPD connect failed: cmd=(' . $_GET['cmd'] . ')');
	exit(0);
}

$jobs = array(
	'reboot', 'poweroff', 'updclockradio', 'update_library'
);
$playqueue_cmds = array(
	'add_item', 'play_item', 'clear_play_item', 'add_item_next', 'play_item_next', /*'clear_add_item',*/
	'add_group', 'play_group', 'clear_play_group', 'add_group_next', 'play_group_next'/*, 'clear_add_group'*/
);
$other_mpd_cmds = array(
	'updvolume', 'mutetxvol' , 'getmpdstatus', 'lsinfo', 'search', 'loadlib',
	'get_playqueue', 'delete_playqueue_item', 'move_playqueue_item', 'get_playqueue_item_file',
	'station_info', 'track_info',
	'mutetxvol', 'upd_tx_adv_toggle', 'upd_rx_adv_toggle'
);
$turn_consume_off = false;

// Jobs sent to worker.php
if (in_array($_GET['cmd'], $jobs)) {
	$queue_args = ($_GET['cmd'] == 'update_library' && isset($_POST['path']) && $_POST['path'] != '') ? $_POST['path'] : '';
	if (submitJob($_GET['cmd'], $queue_args, '', '')) {
		echo json_encode('job submitted');
	}
	else {
		echo json_encode('worker busy');
	}
}
elseif ($_GET['cmd'] == 'resetscnsaver') {
	if (submitJob($_GET['cmd'], $_SERVER['REMOTE_ADDR'], '', '')) { // NOTE: Worker does not use the client IP anymore
		echo json_encode('job submitted');
	}
	else {
		echo json_encode('worker busy');
	}
}
elseif ($_GET['cmd'] == 'setbgimage') {
	if (submitJob($_GET['cmd'], $_POST['blob'], '', '')) {
		echo json_encode('job submitted');
	}
	else {
		echo json_encode('worker busy');
	}
}
elseif ($_GET['cmd'] == 'disconnect-renderer') {
	// Squeezelite and rx hog the audio output so needs to be turned off in order to released it
	if ($_POST['job'] == 'slsvc') {
		session_start();
		playerSession('write', $_POST['job'], '0');
		session_write_close();
	}
	if ($_POST['job'] == 'multiroom_rx') {
		session_start();
		playerSession('write', $_POST['job'], 'Off');
		session_write_close();
	}

	// Airplay, Spotify and RoonBridge are session based and so they can simply be restarted to effect a disconnect
	// NOTE: Pass 'disconnect-renderer' string as a queue arg and then test for it in worker so that MPD play can be resumed if indicated
	if (submitJob($_POST['job'], $_GET['cmd'], '', '')) {
		echo json_encode('job submitted');
	}
	else {
		echo json_encode('worker busy');
	}
}
// Multiroom receiver status
// NOTE: This is called from playerlib.js: multiroom-rx-modal
elseif ($_GET['cmd'] == 'get_rx_status') {
	if (!isset($_SESSION['rx_hostnames'])) {
		$rx_status = 'Discovery has not been run';
	}
	elseif ($_SESSION['rx_hostnames'] == 'No receivers found') {
		$rx_status = 'No receivers found';
	}
	else {
		$rx_hostnames = explode(', ', $_SESSION['rx_hostnames']);
		$rx_addresses = explode(' ', $_SESSION['rx_addresses']);

		$rx_status = '';
		$timeout = getStreamTimeout();
		$count = count($rx_addresses);
		for ($i = 0; $i < $count; $i++) {
			if (false === ($result = file_get_contents('http://' . $rx_addresses[$i] . '/command/?cmd=trx-status.php -rx', false, $timeout))) {
				$rx_status .= 'rx,Unknown,?,?,?,' . $rx_hostnames[$i] . ':';
				debugLog('moode.php: get_rx_status failed: ' . $rx_hostnames[$i]);
			}
			else {
				// rx, On/Off/Disabled/Unknown, volume, mute_1/0, mastervol_opt_in_1/0, hostname
				$rx_status .= $result . ',' . $rx_hostnames[$i] . ':';
			}
		}

		$rx_status = empty($rx_status) ? 'No receivers found' : rtrim($rx_status, ':');
	}

	echo json_encode($rx_status);
}
elseif ($_GET['cmd'] == 'set_rx_status') {
	$item = $_POST['item'];
	$rx_hostnames = explode(', ', $_SESSION['rx_hostnames']);
	$rx_addresses = explode(' ', $_SESSION['rx_addresses']);

	if (isset($_POST['onoff'])) {
		if (false === ($result = file_get_contents('http://' . $rx_addresses[$item] . '/command/?cmd=trx-status.php -rx ' . $_POST['onoff']))) {
			if (false === ($result = file_get_contents('http://' . $rx_addresses[$item] . '/command/?cmd=trx-status.php -rx ' . $_POST['onoff']))) {
				workerLog('moode.php: set_rx_status onoff failed: ' . $rx_hostnames[$item]);
			}
		}
	}
	elseif (isset($_POST['volume'])) {
		if (false === ($result = file_get_contents('http://' . $rx_addresses[$item] . '/command/?cmd=vol.sh ' . $_POST['volume']))) {
			if (false === ($result = file_get_contents('http://' . $rx_addresses[$item] . '/command/?cmd=vol.sh ' . $_POST['volume']))) {
				workerLog('moode.php: set_rx_status volume failed: ' . $rx_hostnames[$item]);
			}
		}
	}
	elseif (isset($_POST['mute'])) { // Toggle mute
		if (false === ($result = file_get_contents('http://' . $rx_addresses[$item] . '/command/?cmd=vol.sh  -mute'))) {
			if (false === ($result = file_get_contents('http://' . $rx_addresses[$item] . '/command/?cmd=vol.sh  -mute'))) {
				workerLog('moode.php: set_rx_status mute failed: ' . $rx_hostnames[$item]);
			}
		}
	}

	echo json_encode('OK');
}
// Commands sent to MPD
elseif (in_array($_GET['cmd'], $playqueue_cmds) || in_array($_GET['cmd'], $other_mpd_cmds)) {
	/*if (false === ($sock = openMpdSock('localhost', 6600))) {
		workerLog('moode.php: MPD connect failed: cmd=(' . $_GET['cmd'] . ')');
		exit(0);
	}*/

	// Turn off Auto-shuffle before processing Queue
	if (in_array($_GET['cmd'], $playqueue_cmds) && $_SESSION['ashuffle'] == '1') {
		playerSession('write', 'ashuffle', '0');
		sysCmd('killall -s 9 ashuffle > /dev/null');
		$turn_consume_off = true; // Also turn off Consume mode
	}
	//workerLog($_GET['cmd'] . '|' . $_POST['path']);
	switch ($_GET['cmd']) {
		case 'updvolume':
			$session_volknob = $_SESSION['volknob'];

			// Update local MPD volume
			playerSession('write', 'volknob', $_POST['volknob']);
			sendMpdCmd($sock, 'setvol ' . $_POST['volknob']);
			$resp = readMpdResp($sock);

			// Update Receiver MPD volume
			if ($_SESSION['multiroom_tx'] == 'On') {
				$voldiff = $session_volknob - $_POST['volknob'];

				if ($_POST['event'] == 'unmute') {
					$tx_volcmd = '-mute'; // Toggle mute off
				}
				elseif ($voldiff == 0) {
					$tx_volcmd = $_POST['volknob'];
				}
				else {
					$tx_volcmd = $voldiff < 0 ? '-up ' . abs($voldiff) : '-dn ' . $voldiff;
				}

				updReceiverVol($tx_volcmd);
			}

			echo json_encode('OK');
			break;

		case 'mutetxvol':
			// Mute Receiver MPD volumes
			updReceiverVol('-mute');

			echo json_encode('OK');
			break;

		// Queue commands for single items: Songs, Radio stations, Stored playlists, Directories
		case 'add_item':
		case 'add_item_next':
			$status = parseStatus(getMpdStatus($sock));
			$cmds = array(addItemToQueue($_POST['path']));
			if ($_GET['cmd'] == 'add_item_next') {
				array_push($cmds, 'move ' . $status['playlistlength'] . ' ' . ($status['song'] + 1));
			}
			chainMpdCmds($sock, $cmds);
			break;
		case 'play_item':
		case 'play_item_next':
			// Search the Queue for the item
			$search = strpos($_POST['path'], 'RADIO') !== false ? parseDelimFile(file_get_contents(MPD_MUSICROOT . $_POST['path']), '=')['File1'] : $_POST['path'];
			$result = findInQueue($sock, 'file', $search);
			// Play already Queued item
			if (isset($result['Pos'])) {
				sendMpdCmd($sock, 'play ' . $result['Pos']);
				$resp = readMpdResp($sock);
			}
			// Otherwise play the item after adding it to the Queue
			else {
				$status = parseStatus(getMpdStatus($sock));
				$cmds = array(addItemToQueue($_POST['path']));
				if ($_GET['cmd'] == 'play_item_next') {
					$pos = isset($status['song']) ? $status['song'] + 1 : $status['playlistlength'];
					array_push($cmds, 'move ' . $status['playlistlength'] . ' ' . $pos);
				}
				else {
					$pos = $status['playlistlength'];
				}
				array_push($cmds, 'play ' . $pos);
				chainMpdCmds($sock, $cmds);
			}
			break;
		/*case 'clear_add_item':*/
		case 'clear_play_item':
			$cmds = array('clear');
			array_push($cmds, addItemToQueue($_POST['path']));
			if ($_GET['cmd'] == 'clear_play_item') {
				array_push($cmds, 'play');
			}
			chainMpdCmds($sock, $cmds);
			playerSession('write', 'toggle_songid', '0');
		    break;
		// Queue commands for a group of songs: Genre, Artist or Albums in Tag/Album view
		case 'add_group':
		case 'add_group_next':
			$status = parseStatus(getMpdStatus($sock));
			$cmds = addGroupToQueue($_POST['path']);
			if ($_GET['cmd'] == 'add_group_next') {
				array_push($cmds, 'move ' . $status['playlistlength'] . ':' .
					($status['playlistlength'] + count($_POST['path'])) . ' ' . ($status['song'] + 1));
			}
			chainMpdCmds($sock, $cmds);
			break;
        case 'play_group':
		case 'play_group_next':
			// Search the Queue for the group
			sendMpdCmd($sock, 'lsinfo "' . $_POST['path'][0] . '"');
			$album = parseDelimFile(readMpdResp($sock), ': ')['Album'];
			$result = findInQueue($sock, 'album', $album);
			// Group is already in the Queue if first and last file exist sequentially
			$last = count($_POST['path']) - 1;
			if ($_POST['path'][0] == $result[0]['file'] && $_POST['path'][$last] == $result[$last]['file']) {
				$pos = $result[0]['Pos'];
				sendMpdCmd($sock, 'play ' . $pos);
				$resp = readMpdResp($sock);
			}
			// Otherwise play the group after adding it to the Queue
			else {
				$status = parseStatus(getMpdStatus($sock));
				$cmds = addGroupToQueue($_POST['path']);
				if ($_GET['cmd'] == 'play_group_next') {
					$pos = isset($status['song']) ? $status['song'] + 1 : $status['playlistlength'];
					if ($pos != 0) {
						array_push($cmds, 'move ' . $status['playlistlength'] . ':' .
							($status['playlistlength'] + count($_POST['path'])) . ' ' . ($status['song'] + 1));
					}
				}
				else {
					$pos = $status['playlistlength'];
				}
				array_push($cmds, 'play ' . $pos);
				chainMpdCmds($sock, $cmds);
			}

			playerSession('write', 'toggle_songid', $pos);
			break;
		/*case 'clear_add_group':*/
        case 'clear_play_group':
			$cmds = array_merge(array('clear'), addGroupToQueue($_POST['path']));

			if ($_GET['cmd'] == 'clear_play_group') {
				array_push($cmds, 'play'); // Defaults to pos 0
			}

			chainMpdCmds($sock, $cmds);
			playerSession('write', 'toggle_songid', '0');
			break;

		// Audioinfo
		case 'station_info':
			echo json_encode(parseStationInfo($_POST['path']));
			break;
		case 'track_info':
			sendMpdCmd($sock,'lsinfo "' . $_POST['path'] .'"');
			echo json_encode(parseTrackInfo(readMpdResp($sock)));
			break;

		case 'getmpdstatus':
			echo json_encode(parseStatus(getMpdStatus($sock)));
			break;
		case 'get_playqueue':
			echo json_encode(get_playqueue($sock));
			break;
		case 'delete_playqueue_item':
			sendMpdCmd($sock, 'delete ' . $_GET['range']);
			break;
		case 'move_playqueue_item':
			sendMpdCmd($sock, 'move ' . $_GET['range'] . ' ' . $_GET['newpos']);
			break;
		case 'get_playqueue_item_file': // For Clock Radio
			sendMpdCmd($sock, 'playlistinfo ' . $_GET['songpos']);
			$resp = readMpdResp($sock);

			$array = array();
			$line = strtok($resp, "\n");

			while ($line) {
				list($element, $value) = explode(': ', $line, 2);
				$array[$element] = $value;
				$line = strtok("\n");
			}

			echo json_encode($array['file']);
			break;
		case 'loadlib':
			//sleep(8); // To simulate a long Library load
			echo loadLibrary($sock);
        	break;
		case 'lsinfo':
			// NOTE: empty or no path indicates call is to return the root list
			if (isset($_POST['path']) && $_POST['path'] != '') {
				echo json_encode(searchDB($sock, 'lsinfo', $_POST['path']));
			}
			else {
				echo json_encode(searchDB($sock, 'lsinfo'));
			}
			break;
		case 'search':
			if (isset($_POST['query']) && $_POST['query'] != '' && isset($_GET['tagname']) && $_GET['tagname'] != '') {
				echo json_encode(searchDB($sock, $_GET['tagname'], $_POST['query']));
			}
			break;
	}

	// Turn off Consume mode if indicated
	if (in_array($_GET['cmd'], $playqueue_cmds) && $turn_consume_off === true) {
		sendMpdCmd($sock, 'consume 0');
		$resp = readMpdResp($sock);
		$turn_consume_off = false;
	}
}
// Other commands
else {
	switch ($_GET['cmd']) {
		case 'read_cfgs':
		case 'read_cfgs_no_radio':
			// System settings
			$result = cfgdb_read('cfg_system', $dbh);
			$array_cfg_system = array();
			foreach ($result as $row) {
				$array_cfg_system[$row['param']] = $row['value'];
			}

			// Add extra vars
			$array_cfg_system['debuglog'] = $_SESSION['debuglog'];
			$array_cfg_system['kernelver'] = $_SESSION['kernelver'];
			$array_cfg_system['procarch'] = $_SESSION['procarch'];
			$array_cfg_system['raspbianver'] = $_SESSION['raspbianver'];
			$array_cfg_system['ipaddress'] = $_SESSION['ipaddress'];
			$array_cfg_system['bgimage'] = file_exists('/var/local/www/imagesw/bgimage.jpg') ? '../imagesw/bgimage.jpg' : '';
			$array_cfg_system['rx_hostnames'] = $_SESSION['rx_hostnames'];
			$array_cfg_system['rx_addresses'] = $_SESSION['rx_addresses'];
			$data['cfg_system'] = $array_cfg_system;

			// Theme settings
			$result = cfgdb_read('cfg_theme', $dbh);
			$array_cfg_theme = array();
			foreach ($result as $row) {
				$array_cfg_theme[$row['theme_name']] = array('tx_color' => $row['tx_color'], 'bg_color' => $row['bg_color'],
				'mbg_color' => $row['mbg_color']);
			}
			$data['cfg_theme'] = $array_cfg_theme;

			// Network settings
			$result = cfgdb_read('cfg_network', $dbh);
			$array_cfg_network = array();
			foreach ($result as $row) {
				$array_cfg_network[$row['iface']] = array('method' => $row['method'], 'ipaddr' => $row['ipaddr'], 'netmask' => $row['netmask'],
				'gateway' => $row['gateway'], 'pridns' => $row['pridns'], 'secdns' => $row['secdns'], 'wlanssid' => $row['wlanssid'],
				'wlansec' => $row['wlansec'], 'wlanpwd' => $row['wlanpwd'], 'wlan_psk' => $row['wlan_psk'],
				'wlan_country' => $row['wlan_country'], 'wlan_channel' => $row['wlan_channel']);
			}
			$data['cfg_network'] = $array_cfg_network;

			// Radio stations
			if ($_GET['cmd'] == 'read_cfgs') {
				$result = cfgdb_read('cfg_radio', $dbh, 'all');
				$array_cfg_radio = array();
				foreach ($result as $row) {
					$array_cfg_radio[$row['station']] = array('name' => $row['name'], 'type' => $row['type'], 'logo' => $row['logo'], 'home_page' => $row['home_page']);
				}
				$data['cfg_radio'] = $array_cfg_radio;
			}

			echo json_encode($data);
			break;
		case 'readcfgsystem':
			$result = cfgdb_read('cfg_system', $dbh);
			$array = array();

			foreach ($result as $row) {
				$array[$row['param']] = $row['value'];
			}
			// Add extra vars
			$array['raspbianver'] = $_SESSION['raspbianver'];
			$array['ipaddress'] = $_SESSION['ipaddress'];
			$array['bgimage'] = file_exists('/var/local/www/imagesw/bgimage.jpg') ? '../imagesw/bgimage.jpg' : '';
			$array['rx_hostnames'] = $_SESSION['rx_hostnames'];
			$array['rx_addresses'] = $_SESSION['rx_addresses'];

			echo json_encode($array);
			break;
		case 'updcfgsystem':
			// Update theme meta tag in header.php
			if (isset($_POST['themename']) && $_POST['themename'] != $_SESSION['themename']) {
				$result = cfgdb_read('cfg_theme', $dbh, $_POST['themename']);
				workerLog(print_r($_SESSION['themename'] . '|' . $_POST['themename'] . '|' . $result[0]['bg_color'], true));
				sysCmd("sed -i '/<meta name=\"theme-color\" content=/c\ \t<meta name=\"theme-color\" content=" . "\"rgb(" . $result[0]['bg_color'] . ")\">'" . ' /var/www/header.php');
			}

			foreach (array_keys($_POST) as $var) {
				playerSession('write', $var, $_POST[$var]);
			}

			echo json_encode('OK');
			break;
		case 'readcfgtheme':
			$result = cfgdb_read('cfg_theme', $dbh);
			$array = array();

			foreach ($result as $row) {
				$array[$row['theme_name']] = array('tx_color' => $row['tx_color'], 'bg_color' => $row['bg_color'], 'mbg_color' => $row['mbg_color']);
			}

			echo json_encode($array);
			break;
		case 'readthemename':
			if (isset($_POST['theme_name'])) {
				$result = cfgdb_read('cfg_theme', $dbh, $_POST['theme_name']);
				echo json_encode($result[0]); // return specific row
			}
			else {
				$result = cfgdb_read('cfg_theme', $dbh);
				echo json_encode($result); // return all rows
			}
			break;
		/*DELETE case 'read_cfg_radio':
			//$result = sdbquery("select * from cfg_radio where station not in ('DELETED', 'zx reserved 499')", $dbh);
			$result = cfgdb_read('cfg_radio', $dbh, 'all');
			echo json_encode($result);
			break;*/
		/*DELETE case 'upd_cfg_radio_show_hide':
			if ($_POST['stationBlock'] == 'Moode') {
				$where_clause = "where id < '499' and type != 'f'";
			}
			elseif ($_POST['stationBlock'] == 'Moode geo-fenced') {
				$where_clause = "where id < '499' and type != 'f' and geo_fenced = 'Yes'";
			}
			elseif ($_POST['stationBlock'] == 'Other') {
				$where_clause = "where id > '499' and type != 'f'";
			}
			$result = sdbquery("update cfg_radio set type='" . $_POST['stationType'] . "' " . $where_clause, $dbh);
			// Update cfg_system and reset show/hide
			$result = cfgdb_read('cfg_system', $dbh, 'radioview_show_hide');
			$radioview_show_hide = explode(',', $result[0]['value']);
			strpos($_POST['stationBlock'], 'Moode') !== false ?  $radioview_show_hide[0] = 'No action' : $radioview_show_hide[1] = 'No action';
			playerSession('write', 'radioview_show_hide', $radioview_show_hide[0] . ',' . $radioview_show_hide[1]);
			break;*/
		case 'readaudiodev':
			if (isset($_POST['name'])) {
				$result = cfgdb_read('cfg_audiodev', $dbh, $_POST['name']);
				echo json_encode($result[0]); // return specific row
			}
			else {
				$result = cfgdb_read('cfg_audiodev', $dbh, 'all');
				echo json_encode($result); // return all rows
			}
			break;

		// Toggle auto-shuffle on/off
		case 'ashuffle':
			playerSession('write', 'ashuffle', $_GET['ashuffle']);
			$_GET['ashuffle'] == '1' ? startAutoShuffle() : stopAutoShuffle();
			echo json_encode('toggle ashuffle ' . $_GET['ashuffle']);
			break;
		case 'thmcachestatus':
			if (isset($_SESSION['thmcache_status']) && !empty($_SESSION['thmcache_status'])) {
				$status = $_SESSION['thmcache_status'];
			}
			else {
				$result = sysCmd('ls ' . THMCACHE_DIR);
			    if ($result[0] == '') {
					$status = 'Cache is empty';
				}
				elseif (strpos($result[0], 'ls: cannot access') !== false) {
					$status = 'Cache directory missing. It will be recreated automatically.';
				}
				else {
					$stat = stat(THMCACHE_DIR);
					$status = 'Cache was last updated on ' . date("Y-m-d H:i:s", $stat['mtime']);
				}
			}
			echo json_encode($status);
			break;

		// Remove background image
		case 'rmbgimage':
			sysCmd('rm /var/local/www/imagesw/bgimage.jpg');
			echo json_encode('OK');
			break;

		case 'readplayhistory':
			echo json_encode(parsePlayHist(shell_exec('cat /var/local/www/playhistory.log')));
			break;
		case 'clear_libcache_all':
			clearLibCacheAll();
			break;
		case 'clear_libcache_filtered':
			clearLibCacheFiltered();
			break;
		case 'upd_tx_adv_toggle':
		case 'upd_rx_adv_toggle':
			//workerLog($_GET['cmd'] . '|' . $_POST['adv_toggle']);
			session_start();
			$_SESSION[$_GET['cmd']] = $_POST['adv_toggle'];
			session_write_close();
			break;

		// Return client IP address
		// NOTE: We may use this in the future
		case 'clientip':
			echo json_encode($_SERVER['REMOTE_ADDR']);
			break;

		case 'camilladsp_setconfig':
			if (isset($_POST['cdspconfig'])) {
				require_once dirname(__FILE__) . '/../inc/cdsp.php';
				$cdsp = new CamillaDsp($_SESSION['camilladsp'], $_SESSION['cardnum'], $_SESSION['camilladsp_quickconv']);
				$currentMode = $_SESSION['camilladsp'];
				$newMode = $_POST['cdspconfig'];

				session_start();
				playerSession('write', 'camilladsp', $newMode);
				session_write_close();

				$cdsp->selectConfig($newMode);
				if ($_SESSION['cdsp_fix_playback'] == 'Yes') {
					$cdsp->setPlaybackDevice($_SESSION['cardnum'], $_SESSION['alsa_output_mode']);
				}

				if ($_SESSION['camilladsp'] != $currentMode && ( $_SESSION['camilladsp'] == 'off' || $currentMode == 'off')) {
					submitJob('camilladsp', $newMode, '', '');
				}
				else {
					$cdsp->reloadConfig();
				}
			}
			else {
				workerLog('moode.php Error: missing camilladsp config name');
			}
			break;
	}
}

closeMpdSock($sock);
