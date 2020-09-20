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
 * 2020-MM-DD TC moOde 7.0.0
 *
 */

require_once dirname(__FILE__) . '/../inc/playerlib.php';

//workerLog('moode.php: cmd=(' . $_GET['cmd'] . ')');
if (isset($_GET['cmd']) && $_GET['cmd'] === '') {
	workerLog('moode.php: error: $_GET cmd is empty or missing');
	exit(0);
}

playerSession('open', '' ,'');
$dbh = cfgdb_connect();
session_write_close();

$jobs = array('reboot', 'poweroff', 'updclockradio', 'update_library');
$playqueue_cmds = array('add_item', 'play_item', 'add_item_next', 'play_item_next', 'clear_add_item', 'clear_play_item',
	'add_group', 'play_group', 'add_group_next', 'play_group_next', 'clear_add_group', 'clear_play_group');
$other_mpd_cmds = array('updvolume' ,'getmpdstatus', 'playlist', 'delplitem', 'moveplitem', 'getplitemfile', 'savepl', 'listsavedpl',
	'delsavedpl', 'setfav', 'addfav', 'lsinfo', 'search', 'newstation', 'updstation', 'delstation', 'loadlib', 'track_info');
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
elseif ($_GET['cmd'] == 'setlogoimage') {
	if (submitJob($_GET['cmd'], $_POST['name'] . ',' . $_POST['blob'], '', '')) {
		echo json_encode('job submitted');
	}
	else {
		echo json_encode('worker busy');
	}
}
elseif ($_GET['cmd'] == 'import_stations') {
	if (submitJob($_GET['cmd'], $_POST['blob'], '', '')) {
		echo json_encode('job submitted');
	}
	else {
		echo json_encode('worker busy');
	}
}
elseif ($_GET['cmd'] == 'disconnect-renderer') {
	if ($_POST['job'] == 'slsvc') {
		session_start();
		playerSession('write', 'slsvc', '0');
		session_write_close();
	}
	// Pass 'disconnect-renderer' string as a queue arg and then test for it in worker so that MPD play can be resumed.
	if (submitJob($_POST['job'], $_GET['cmd'], '', '')) {
		echo json_encode('job submitted');
	}
	else {
		echo json_encode('worker busy');
	}
}
// Commands sent to MPD
elseif (in_array($_GET['cmd'], $playqueue_cmds) || in_array($_GET['cmd'], $other_mpd_cmds)) {
	if (false === ($sock = openMpdSock('localhost', 6600))) {
		workerLog('moode.php: MPD connect failed: cmd=(' . $_GET['cmd'] . ')');
		exit(0);
	}

	// Turn off Auto-shuffle before processing Queue
	if (in_array($_GET['cmd'], $playqueue_cmds) && $_SESSION['ashuffle'] == '1') {
		playerSession('write', 'ashuffle', '0');
		sysCmd('killall -s 9 ashuffle > /dev/null');
		$turn_consume_off = true; // Also turn off Consume mode
	}

	//workerLog($_GET['cmd'] . '|' . $_POST['path']);
	switch ($_GET['cmd']) {
		case 'updvolume':
			playerSession('write', 'volknob', $_POST['volknob']);
			sendMpdCmd($sock, 'setvol ' . $_POST['volknob']);
			$resp = readMpdResp($sock);
			echo json_encode('OK');
			break;

		// Queue commands for single items: Songs, Radio stations, Stored playlists, Directories
		case 'add_item':
		case 'add_item_next':
			$status = parseStatus(getMpdStatus($sock));
			$resp = addItemToQueue($sock, $_POST['path']);
			if ($_GET['cmd'] == 'add_item_next') {
				sendMpdCmd($sock, 'move ' . $status['playlistlength'] . ' ' . ($status['song'] + 1));
				$resp = readMpdResp($sock);
			}
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
				$resp = addItemToQueue($sock, $_POST['path']);
				if ($_GET['cmd'] == 'play_item_next') {
					$pos = $status['song'] + 1;
					sendMpdCmd($sock, 'move ' . $status['playlistlength'] . ' ' . $pos);
					$resp = readMpdResp($sock);
				}
				else {
					$pos = $status['playlistlength'];
				}
				usleep(500000); // NOTE: This brief delay allows the WebUI some time to process and display the Queue
				sendMpdCmd($sock, 'play ' . $pos);
				$resp = readMpdResp($sock);
			}
			break;
		case 'clear_add_item':
		case 'clear_play_item':
			sendMpdCmd($sock,'clear');
			$resp = readMpdResp($sock);

			addItemToQueue($sock, $_POST['path']);
			playerSession('write', 'toggle_song', '0');

			if ($_GET['cmd'] == 'clear_play_item') {
				sendMpdCmd($sock, 'play');
				$resp = readMpdResp($sock);
			}
			break;
		case 'track_info':
			sendMpdCmd($sock,'lsinfo "' . $_POST['path'] .'"');
			echo json_encode(readMpdResp($sock));
			break;

		// Queue commands for a group of songs: Genre, Artist or Albums in Tag/Album view
		case 'add_group':
		case 'add_group_next':
			$status = parseStatus(getMpdStatus($sock));
			$resp = addGroupToQueue($sock, $_POST['path']);
			if ($_GET['cmd'] == 'add_group_next') {
				sendMpdCmd($sock, 'move ' . $status['playlistlength'] . ':' . ($status['playlistlength'] + count($_POST['path'])) . ' ' . ($status['song'] + 1));
				$resp = readMpdResp($sock);
			}
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
			}
			// Otherwise play the group after adding it to the Queue
			else {
				$status = parseStatus(getMpdStatus($sock));
				$resp = addGroupToQueue($sock, $_POST['path']);
				if ($_GET['cmd'] == 'play_group_next') {
					$pos = $status['song'] + 1;
					sendMpdCmd($sock, 'move ' . $status['playlistlength'] . ':' . ($status['playlistlength'] + count($_POST['path'])) . ' ' . ($status['song'] + 1));
					$resp = readMpdResp($sock);
				}
				else {
					$pos = $status['playlistlength'];
				}
			}

			usleep(500000); // NOTE: This brief delay allows the WebUI some time to process and display the Queue
			sendMpdCmd($sock, 'play ' . $pos);
			$resp = readMpdResp($sock);
			playerSession('write', 'toggle_song', $pos);
			break;
		case 'clear_add_group':
        case 'clear_play_group':
			sendMpdCmd($sock,'clear');
			$resp = readMpdResp($sock);

        	addGroupToQueue($sock, $_POST['path']);
			playerSession('write', 'toggle_song', '0');

			if ($_GET['cmd'] == 'clear_play_group') {
				sendMpdCmd($sock, 'play'); // Defaults to pos 0
				$resp = readMpdResp($sock);
			}
			break;
		case 'getmpdstatus':
			echo json_encode(parseStatus(getMpdStatus($sock)));
			break;
		case 'playlist':
			echo json_encode(getPLInfo($sock));
			break;
		case 'delplitem':
			sendMpdCmd($sock, 'delete ' . $_GET['range']);
			break;
		case 'moveplitem':
			sendMpdCmd($sock, 'move ' . $_GET['range'] . ' ' . $_GET['newpos']);
			break;
		case 'getplitemfile': // For Clock Radio
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
        case 'savepl':
            sendMpdCmd($sock, 'rm "' . html_entity_decode($_GET['plname']) . '"');
			$resp = readMpdResp($sock);

            sendMpdCmd($sock, 'save "' . html_entity_decode($_GET['plname']) . '"');
            echo json_encode(readMpdResp($sock));
			break;
		case 'setfav':
			$file = '/var/lib/mpd/playlists/' . $_GET['favname'] . '.m3u';
			if (!file_exists($file)) {
				sysCmd('touch "' . $file . '"');
				sysCmd('chmod 777 "' . $file . '"');
				sysCmd('chown root:root "' . $file . '"');
				sendMpdCmd($sock, 'update /var/lib/mpd/playlists');
				readMpdResp($sock);
			}
			else { // Ensure corrent permissions
				sysCmd('chmod 777 "' . $file . '"');
				sysCmd('chown root:root "' . $file . '"');
			}
			playerSession('write', 'favorites_name', $_GET['favname']);
			echo json_encode('OK');
			break;
		case 'addfav':
            if (isset($_GET['favitem']) && $_GET['favitem'] != '' && $_GET['favitem'] != 'null') {
				$file = '/var/lib/mpd/playlists/' . $_SESSION['favorites_name'] . '.m3u';
				if (!file_exists($file)) {
					sysCmd('touch "' . $file . '"');
					sysCmd('chmod 777 "' . $file . '"');
					sysCmd('chown root:root "' . $file . '"');
					sendMpdCmd($sock, 'update /var/lib/mpd/playlists');
					readMpdResp($sock);
				}
				else { // Ensure correct permissions
					sysCmd('chmod 777 "' . $file . '"');
					sysCmd('chown root:root "' . $file . '"');
				}
				$result = sysCmd('fgrep "' . $_GET['favitem'] . '" "' . $file . '"');
				if (empty($result[0])) {
					sysCmd('echo "' . $_GET['favitem'] . '" >> "' . $file . '"');
				}
				echo json_encode('OK');
			}
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
		case 'listsavedpl':
			echo json_encode(listSavedPL($sock, $_POST['path']));
			break;
		case 'delsavedpl':
			echo json_encode(delPLFile($sock, $_POST['path']));
			break;
		case 'newstation':
		case 'updstation':
			$name = $_POST['path']['name'];
			$return_msg = 'OK';

			// Add new station
			if ($_GET['cmd'] == 'newstation') {
				// Check for existing pls file
				if (file_exists(MPD_MUSICROOT . 'RADIO/' . $_POST['path']['name'] . '.pls')) {
					$return_msg = 'A station .pls file with the same name already exists';
				}
				// Check for existing url or name in the table
				// NOTE: true = no results, array = results, false = query bombed (unlikely)
				else {
					$result = sdbquery("select id from cfg_radio where station='" . SQLite3::escapeString($_POST['path']['url']) . "'", $dbh);
					if ($result !== true) {
						$return_msg = 'A station with same URL already exists in the SQL table';
					}
					else {
						$result = sdbquery("select id from cfg_radio where name='" . SQLite3::escapeString($_POST['path']['name']) . "'", $dbh);
						if ($result !== true) {
							$return_msg = 'A station with the same name already exists in the SQL table';
						}
					}
				}

				if ($return_msg == 'OK') {
					// Add new row, NULL causes Id column to be set to next number
					// NOTE: $values have to be in column order
					$values =
						"'"	. $_POST['path']['url'] . "'," .
						"'" . SQLite3::escapeString($_POST['path']['name']) . "'," .
						"'"	. $_POST['path']['type'] . "'," .
						"'"	. 'local' . "'," .
						"\"" . $_POST['path']['genre'] . "\"," . // Use double quotes since we may have g1, g2, g3
						"'" . $_POST['path']['broadcaster'] . "'," .
						"'" . $_POST['path']['language'] . "'," .
						"'" . $_POST['path']['country'] . "'," .
						"'" . $_POST['path']['region'] . "'," .
						"'" . $_POST['path']['bitrate'] . "'," .
						"'" . $_POST['path']['format'] . "'," .
						"'" . $_POST['path']['geo_fenced'] . "'," .
						"'" . $_POST['path']['reserved1'] . "'," .
						"'" . $_POST['path']['reserved2'] . "'";
					$result = sdbquery('insert into cfg_radio values (NULL,' . $values . ')', $dbh);
				}

				echo json_encode($return_msg);
			}
			// Update station
			else {
				// NOTE: Client prevents pls name change so check for existing station with same URL
				$result = sdbquery("select id from cfg_radio where id != '" . $_POST['path']['id'] . "' and station = '" . SQLite3::escapeString($_POST['path']['url']) . "'", $dbh);
				if ($result !== true) {
					$return_msg = 'A station with same URL already exists';
				}

				if ($return_msg == 'OK') {
					$columns =
					"station='" . $_POST['path']['url'] . "'," .
					"name='" . SQLite3::escapeString($_POST['path']['name']) . "'," .
					"type='" . $_POST['path']['type'] . "'," .
					"logo='local'," .
					"genre=\"" . $_POST['path']['genre'] . "\"," . // Use double quotes since we may have g1, g2, g3
					"broadcaster='" . $_POST['path']['broadcaster'] . "'," .
					"language='" . $_POST['path']['language'] . "'," .
					"country='" . $_POST['path']['country'] . "'," .
					"region='" . $_POST['path']['region'] . "'," .
					"bitrate='" . $_POST['path']['bitrate'] . "'," .
					"format='" . $_POST['path']['format'] . "'," .
					"geo_fenced='" . $_POST['path']['geo_fenced'] . "'," .
					"reserved1='" . $_POST['path']['reserved0'] . "'," .
					"reserved2='" . $_POST['path']['reserved0'] . "'";
					$result = sdbquery('UPDATE cfg_radio SET ' . $columns . ' WHERE id=' . $_POST['path']['id'], $dbh);
				}
				echo json_encode($return_msg);
			}

			if ($return_msg == 'OK') {
				// Add/update session var
				session_start();
				$_SESSION[$_POST['path']['url']] = array(
					'name' => $_POST['path']['name'],
					'type' => $_POST['path']['type'],
					'logo' => 'local',
					'bitrate' => $_POST['path']['bitrate'],
					'format' => $_POST['path']['format']
				);
				session_write_close();

				// Write pls file and set permissions
				$file =  MPD_MUSICROOT . 'RADIO/' . $_POST['path']['name'] . '.pls';
				if (false === ($fh = fopen($file, 'w'))) {
					workerLog('moode.php: file create failed on ' . $file);
					break;
				}

				$data = '[playlist]' . "\n";
				$data .= 'File1='. $_POST['path']['url'] . "\n";
				$data .= 'Title1='. $_POST['path']['name'] . "\n";
				$data .= 'Length1=-1' . "\n";
				$data .= 'NumberOfEntries=1' . "\n";
				$data .= 'Version=2' . "\n";

				if (false === ($bytes_written = fwrite($fh, $data))) {
					workerLog('moode.php: file write failed on ' . $file);
					break;
				}

				fclose($fh);
				sysCmd('chmod 777 "' . $file . '"');
				sysCmd('chown root:root "' . $file . '"');

				// Write logo image
				sendEngCmd('set_logo_image1'); // Show spinner
				sleep(3); // Allow time for setlogoimage job to complete which creates tmp new image file

				// Write new image if one exists
				if (file_exists('/var/local/www/imagesw/radio-logos/' . TMP_STATION_PREFIX . $_POST['path']['name'] . '.jpg')) {
					sysCmd('mv "/var/local/www/imagesw/radio-logos/' . TMP_STATION_PREFIX . $_POST['path']['name'] . '.jpg" "/var/local/www/imagesw/radio-logos/' . $_POST['path']['name'] . '.jpg"');
					sysCmd('mv "/var/local/www/imagesw/radio-logos/thumbs/' . TMP_STATION_PREFIX . $_POST['path']['name'] . '.jpg" "/var/local/www/imagesw/radio-logos/thumbs/' . $_POST['path']['name'] . '.jpg"');
					sysCmd('mv "/var/local/www/imagesw/radio-logos/thumbs/' . TMP_STATION_PREFIX . $_POST['path']['name'] . '_sm.jpg" "/var/local/www/imagesw/radio-logos/thumbs/' . $_POST['path']['name'] . '_sm.jpg"');
				}
				// Write default logo image if an image does not already exist
					else if (!file_exists('/var/local/www/imagesw/radio-logos/' . $_POST['path']['name'] . '.jpg')) {
					sysCmd('cp /var/www/images/notfound.jpg ' . '"/var/local/www/imagesw/radio-logos/' . $_POST['path']['name'] . '.jpg"');
					sysCmd('cp /var/www/images/notfound.jpg ' . '"/var/local/www/imagesw/radio-logos/thumbs/' . $_POST['path']['name'] . '.jpg"');
					sysCmd('cp /var/www/images/notfound.jpg ' . '"/var/local/www/imagesw/radio-logos/thumbs/' . $_POST['path']['name'] . '_sm.jpg"');
				}

				// Update time stamp on files so mpd picks up the change and commits the update
				sysCmd('find ' . MPD_MUSICROOT . 'RADIO -name *.pls -exec touch {} \+');

				sendMpdCmd($sock, 'update RADIO');
				readMpdResp($sock);
				sendEngCmd('set_logo_image0'); // Hide spinner
			}
			break;
		case 'delstation':
			// Get the row id
			$station_file = parseStationFile(shell_exec('cat "' . MPD_MUSICROOT . $_POST['path'] . '"'));
			$result = sdbquery("select id,name from cfg_radio where station='" . SQLite3::escapeString($station_file['File1']) . "'", $dbh);

			// Delete session var
			session_start();
			foreach ($_SESSION as $key => $value) {
				if ($value['name'] == $result[0]['name']) {
					unset($_SESSION[$key]);
				}
			}
			session_write_close();

			// Delete row
			$result = sdbquery("delete from cfg_radio where id='" . $result[0]['id'] . "'", $dbh);

			// Delete pls and logo image files
			$station_name = substr($_POST['path'], 6, -4); // Trim RADIO/ and .pls
			sysCmd('rm "' . MPD_MUSICROOT . $_POST['path'] . '"');
			sysCmd('rm "' . '/var/local/www/imagesw/radio-logos/' . $station_name . '.jpg' . '"');
			sysCmd('rm "' . '/var/local/www/imagesw/radio-logos/thumbs/' . $station_name . '.jpg' . '"');
			sysCmd('rm "' . '/var/local/www/imagesw/radio-logos/thumbs/' . $station_name . '_sm.jpg' . '"');

			// Update time stamp on files so mpd picks up the change
			sysCmd('find ' . MPD_MUSICROOT . 'RADIO -name *.pls -exec touch {} \+');

			sendMpdCmd($sock, 'update RADIO');
			readMpdResp($sock);
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
			$array_cfg_system['raspbianver'] = $_SESSION['raspbianver'];
			$array_cfg_system['ipaddress'] = $_SESSION['ipaddress'];
			$array_cfg_system['bgimage'] = file_exists('/var/local/www/imagesw/bgimage.jpg') ? '../imagesw/bgimage.jpg' : '';
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
					$array_cfg_radio[$row['station']] = array('name' => $row['name'], 'type' => $row['type'], 'logo' => $row['logo']);
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

			echo json_encode($array);
			break;
		case 'updcfgsystem':
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
		case 'read_cfg_radio':
			//$result = sdbquery("select * from cfg_radio where station not in ('DELETED', 'zx reserved 499')", $dbh);
			$result = cfgdb_read('cfg_radio', $dbh, 'all');
			echo json_encode($result);
			break;
		case 'upd_cfg_radio_show_hide':
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
			break;
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

		// Get Favorites name for display in modal
		case 'getfavname':
			$result = cfgdb_read('cfg_system', $dbh, 'favorites_name');
			echo json_encode($result[0]['value']);
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
		case 'readstationfile':
			$station_file = parseStationFile(shell_exec('cat "' . MPD_MUSICROOT . $_POST['path'] . '"'));
			//echo json_encode($station_file);
			$result = sdbquery("SELECT * FROM cfg_radio WHERE station='" . SQLite3::escapeString($station_file['File1']) . "'", $dbh);
			$array = array('id' => $result[0]['id'], 'station' => $result[0]['station'], 'name' => $result[0]['name'], 'type' => $result[0]['type'],
			 	'logo' =>  $result[0]['logo'], 'genre' => $result[0]['genre'], 'broadcaster' => $result[0]['broadcaster'], 'language' => $result[0]['language'],
				'country' => $result[0]['country'], 'region' => $result[0]['region'], 'bitrate' => $result[0]['bitrate'], 'format' => $result[0]['format'],
			 	'geo_fenced' => $result[0]['geo_fenced'], 'reserved1' => $result[0]['reserved1'], 'reserved2' => $result[0]['reserved2']);
			echo json_encode($array);
			break;
		// Remove background image
		case 'rmbgimage':
			sysCmd('rm /var/local/www/imagesw/bgimage.jpg');
			echo json_encode('OK'); //r44c
			break;
		case 'readplayhistory':
			echo json_encode(parsePlayHist(shell_exec('cat /var/local/www/playhistory.log')));
			break;
		case 'export_stations':
			syscmd('sqlite3 /var/local/www/db/moode-sqlite3.db -csv "select * from cfg_radio" > /var/local/www/db/cfg_radio.csv');
			sysCmd('zip -q -r ' . EXPORT_DIR . '/stations.zip /var/lib/mpd/music/RADIO/* /var/local/www/imagesw/radio-logos/* /var/local/www/db/cfg_radio.csv');
			syscmd('rm /var/local/www/db/cfg_radio.csv');
			break;
		case 'clear_libcache':
			clearLibCache();
			break;

		// Return client IP address
		// NOTE: We may use this in the future
		case 'clientip':
			echo json_encode($_SERVER['REMOTE_ADDR']);
			break;
	}
}

closeMpdSock($sock);
