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
 * 2019-04-12 TC moOde 5.0
 *
 */

require_once dirname(__FILE__) . '/../inc/playerlib.php';

if (false === ($sock = openMpdSock('localhost', 6600))) {
	$msg = 'command/moode: Connection to MPD failed'; 
	workerLog($msg);
	exit($msg . "\n");	
}
else {
	playerSession('open', '' ,''); 
	$dbh = cfgdb_connect();
	session_write_close();
}

$jobs = array('reboot', 'poweroff', 'updclockradio');
$playqueue_cmds = array('add', 'play', 'clradd', 'clrplay', 'addall', 'playall', 'clrplayall');

if (isset($_GET['cmd']) && $_GET['cmd'] === '') {
	echo 'command missing';
}
else {
	// these get sent to worker.php 
	if (in_array($_GET['cmd'], $jobs)) {
		if (submitJob($_GET['cmd'], '', '', '')) {
			echo json_encode('job submitted');
		}
		else {
			echo json_encode('worker busy');
		}
	}
	// send client ip to worker
	elseif ($_GET['cmd'] == 'resetscnsaver') {
		if (submitJob($_GET['cmd'], $_SERVER['REMOTE_ADDR'], '', '')) {
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

	// these are handled here in moode.php
	else {
		// turn off auto-shuffle or random play when playqueue cmds submitted
		if (in_array($_GET['cmd'], $playqueue_cmds)) {
			if ($_SESSION['ashuffle'] == '1') {
				sysCmd('killall -s 9 ashuffle > /dev/null');
				sendMpdCmd($sock, 'consume 0');
				$resp = readMpdResp($sock);
				playerSession('write', 'ashuffle', '0');
			}
			else {
				sendMpdCmd($sock, 'random 0');
				$resp = readMpdResp($sock);
			}			
		}

		switch ($_GET['cmd']) {
			// MISC
			// return client ip
			case 'clientip':
				echo json_encode($_SERVER['REMOTE_ADDR']);
				break;
			// remove background image
			case 'rmbgimage':
				sysCmd('rm /var/local/www/imagesw/bgimage.jpg');
				echo json_encode('OK'); //r44c
				break;
			// toggle auto-shuffle on/off
			case 'ashuffle':
				playerSession('write', 'ashuffle', $_GET['ashuffle']);

				// filter and queue_buffer
				if (!empty($_SESSION['ashuffle_filter']) && $_SESSION['ashuffle_filter'] != 'None') {
					$cmd = 'mpc search ' . $_SESSION['ashuffle_filter'] . ' | /usr/local/bin/ashuffle --queue_buffer 1 --file - > /dev/null 2>&1 &';
				}
				else {
					$cmd = '/usr/local/bin/ashuffle --queue_buffer 1 > /dev/null 2>&1 &';
				}
				
				//workerlog($cmd);
				$_GET['ashuffle'] == '1' ? sysCmd($cmd) : sysCmd('killall -s 9 ashuffle > /dev/null');

				echo json_encode('toggle ashuffle ' . $_GET['ashuffle']);
				break;

			// MPD
			case 'updvolume':
				playerSession('write', 'volknob', $_POST['volknob']);
				sendMpdCmd($sock, 'setvol ' . $_POST['volknob']);
				$resp = readMpdResp($sock);
				// intentionally omit the echo to cause ajax abort with JSON parse error.
				// This causes $('.volumeknob').knob change action to also abort which prevents
				// knob update and subsequent bounce back to +10 level. Knob will get updated
				// to +10 level in renderUIVol() routine as a result of MPD idle timeout.
				//echo json_encode('OK');
				break;
			case 'getmpdstatus':
				echo json_encode(parseStatus(getMpdStatus($sock)));
				break;
			case 'playlist':
				echo json_encode(getPLInfo($sock));
				break;
			case 'update':
				if (isset($_POST['path']) && $_POST['path'] != '') {
					clearLibCache();
					sendMpdCmd($sock, 'update "' . html_entity_decode($_POST['path']) . '"');
					echo json_encode(readMpdResp($sock));
				}
				break;

			// SQL DATA

			// session vars
			case 'read_cfg_all':
				// cfg_system
				$result = cfgdb_read('cfg_system', $dbh);
				$array_cfg_system = array();
				foreach ($result as $row) {$array_cfg_system[$row['param']] = $row['value'];}				
				$array_cfg_system['mooderel'] = $_SESSION['mooderel']; // add extra vars
				$array_cfg_system['pkgdate'] = $_SESSION['pkgdate'];
				$array_cfg_system['raspbianver'] = $_SESSION['raspbianver'];
				$array_cfg_system['ipaddress'] = $_SESSION['ipaddress'];
				$array_cfg_system['bgimage'] = file_exists('/var/local/www/imagesw/bgimage.jpg') ? '../imagesw/bgimage.jpg' : '';
				// cfg_theme
				$result = cfgdb_read('cfg_theme', $dbh);
				$array_cfg_theme = array();				
				foreach ($result as $row) {$array_cfg_theme[$row['theme_name']] = array('tx_color' => $row['tx_color'], 'bg_color' => $row['bg_color'], 'mbg_color' => $row['mbg_color']);}
				// cfg_radio
				$result = cfgdb_read('cfg_radio', $dbh);
				$array_cfg_radio = array();
				foreach ($result as $row) {$array_cfg_radio[$row['station']] = array('name' => $row['name']);}				

				$data['cfg_system'] = $array_cfg_system; 
				$data['cfg_radio'] = $array_cfg_radio; 
				$data['cfg_theme'] = $array_cfg_theme; 

				echo json_encode($data);
				break;

			// system settings
			case 'readcfgsystem':
				$result = cfgdb_read('cfg_system', $dbh);
				$array = array();
				
				foreach ($result as $row) {
					$array[$row['param']] = $row['value'];
				}
				// add extra session vars so they can be available to client
				$array['mooderel'] = $_SESSION['mooderel'];
				$array['pkgdate'] = $_SESSION['pkgdate'];
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
			// themes
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
				} else {
					$result = cfgdb_read('cfg_theme', $dbh);				
					echo json_encode($result); // return all rows
				}
				break;
			// radio stations
			case 'readcfgradio':
				$result = cfgdb_read('cfg_radio', $dbh);
				$array = array();
				
				foreach ($result as $row) {
					$array[$row['station']] = array('name' => $row['name']);
				}
				
				echo json_encode($array);
				break;	
			// audio devices
			case 'readaudiodev':
				if (isset($_POST['name'])) {
					$result = cfgdb_read('cfg_audiodev', $dbh, $_POST['name']);				
					echo json_encode($result[0]); // return specific row
				} else {
					$result = cfgdb_read('cfg_audiodev', $dbh, 'all');
					echo json_encode($result); // return all rows
				}
				break;
			// playback history
			case 'readplayhistory':
				echo json_encode(parsePlayHist(shell_exec('cat /var/local/www/playhistory.log')));
				break;

			// PLAYBACK PANEL
			case 'delplitem':
				if (isset($_GET['range']) && $_GET['range'] != '') {
					sendMpdCmd($sock, 'delete ' . $_GET['range']);
					echo json_encode(readMpdResp($sock));
				}
				break;			
			case 'moveplitem':
				if (isset($_GET['range']) && $_GET['range'] != '') {
					sendMpdCmd($sock, 'move ' . $_GET['range'] . ' ' . $_GET['newpos']);					
					echo json_encode(readMpdResp($sock));
				}
				break;
			// get playlist item 'file' for clock radio
			case 'getplitemfile':
				if (isset($_GET['songpos']) && $_GET['songpos'] != '') {
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
				}
				break;			
	        case 'savepl':
	            if (isset($_GET['plname']) && $_GET['plname'] != '') {
	                sendMpdCmd($sock, 'rm "' . html_entity_decode($_GET['plname']) . '"');
					$resp = readMpdResp($sock);
	                
	                sendMpdCmd($sock, 'save "' . html_entity_decode($_GET['plname']) . '"');
	                echo json_encode(readMpdResp($sock));
	            }
				break;
			case 'getfavname':
				$result = cfgdb_read('cfg_system', $dbh, 'favorites_name');
				echo json_encode($result[0]['value']);
				break;			
			case 'setfav':
	            if (isset($_GET['favname']) && $_GET['favname'] != '') {
					$file = '/var/lib/mpd/playlists/' . $_GET['favname'] . '.m3u';
					if (!file_exists($file)) {
						sysCmd('touch "' . $file . '"');
						sysCmd('chmod 777 "' . $file . '"');
						sysCmd('chown root:root "' . $file . '"');
						sendMpdCmd($sock, 'update /var/lib/mpd/playlists');
						readMpdResp($sock);
					}
					else { // ensure permissions
						sysCmd('chmod 777 "' . $file . '"');
						sysCmd('chown root:root "' . $file . '"');
					}
					playerSession('write', 'favorites_name', $_GET['favname']);
					echo json_encode('OK');
				}
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
					else { // ensure permissions
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

			// BROWSE, RADIO PANELS
			case 'add':
				if (isset($_POST['path']) && $_POST['path'] != '') {
					echo json_encode(addToPL($sock, $_POST['path']));
				}
				break;
			case 'play':
				if (isset($_POST['path']) && $_POST['path'] != '') {
					$status = parseStatus(getMpdStatus($sock));
					$pos = $status['playlistlength'] ;
					
					addToPL($sock, $_POST['path']);
					
					sendMpdCmd($sock, 'play ' . $pos);
					echo json_encode(readMpdResp($sock));
				}
				break;
			case 'clradd':
				if (isset($_POST['path']) && $_POST['path'] != '') {
					sendMpdCmd($sock,'clear');
					$resp = readMpdResp($sock);
					
					addToPL($sock,$_POST['path']);
					playerSession('write', 'toggle_song', '0'); // reset toggle_song

					echo json_encode($resp);
				}
				break;				
			case 'clrplay':
				if (isset($_POST['path']) && $_POST['path'] != '') {
					sendMpdCmd($sock,'clear');
					$resp = readMpdResp($sock);
					
					addToPL($sock,$_POST['path']);
					playerSession('write', 'toggle_song', '0'); // reset toggle_song
					
					sendMpdCmd($sock, 'play');
					echo json_encode(readMpdResp($sock));
				}
				break;				
			case 'lsinfo':
				if (isset($_POST['path']) && $_POST['path'] != '') {
					echo json_encode(searchDB($sock, 'lsinfo', $_POST['path']));
				} else {
					echo json_encode(searchDB($sock, 'lsinfo'));
				}
				break;
			case 'search':
				if (isset($_POST['query']) && $_POST['query'] != '' && isset($_GET['tagname']) && $_GET['tagname'] != '') {
					echo json_encode(searchDB($sock, $_GET['tagname'], $_POST['query']));
				}
				break;
			case 'listsavedpl':
				if (isset($_POST['path']) && $_POST['path'] != '') {
					echo json_encode(listSavedPL($sock, $_POST['path']));
				}
				break;
			case 'delsavedpl':
				if (isset($_POST['path']) && $_POST['path'] != '') {
					echo json_encode(delPLFile($sock, $_POST['path']));
				}
				break;
			case 'readstationfile':
				echo json_encode(parseStationFile(shell_exec('cat "' . MPD_MUSICROOT . $_POST['path'] . '"')));
				break;
			case 'newstation':
			case 'updstation':
				if (isset($_POST['path']) && $_POST['path'] != '') {
					$station_name = $_POST['path'];

					if ($_GET['cmd'] == 'newstation') {
						// cant have same name as existing station
						$result = sdbquery("SELECT id FROM cfg_radio WHERE name='" . $station_name . "'", $dbh);

						// true = query successful but no results, array = results, false = query bombed (not likely)
						if ($result === true) {
							// add new row to sql table
							$values = "'" . $_POST['url'] . "'," . "'" . $station_name . "','u','local'";
							$result = sdbquery('INSERT INTO cfg_radio VALUES (NULL,' . $values . ')', $dbh); // NULL causes the Id column to be set to the next number
						}
					}
					else {
						// if name changed then its same as an add and we have to check the name doesnt already exist
						// if only the url changed then we update
						$result = cfgdb_update('cfg_radio', $dbh, $station_name, $_POST['url']);
					}

					// add session var
					session_start();
					$_SESSION[$_POST['url']] = array('name' => $station_name, 'type' => 'u', 'logo' => 'local');
					session_write_close();

					$file =  MPD_MUSICROOT . 'RADIO/' . $station_name . '.pls';
					$fh = fopen($file, 'w') or exit('moode.php: file create failed on ' . $file);
	
					$data = '[playlist]' . "\n";
					$data .= 'numberofentries=1' . "\n";
					$data .= 'File1='. $_POST['url'] . "\n";
					$data .= 'Title1='. $station_name . "\n";
					$data .= 'Length1=-1' . "\n";
					$data .= 'version=2' . "\n";
	
					fwrite($fh, $data);
					fclose($fh);
	
					sysCmd('chmod 777 "' . $file . '"');
					sysCmd('chown root:root "' . $file . '"');
	
					// update time stamp on files so mpd picks up the change and commits the update
					sysCmd('find ' . MPD_MUSICROOT . 'RADIO -name *.pls -exec touch {} \+');
	
					sendMpdCmd($sock, 'update RADIO'); //r45p specify RADIO folder
					readMpdResp($sock);
					
					echo json_encode('OK');
				}
				break;
			case 'delstation':
				if (isset($_POST['path']) && $_POST['path'] != '') {
					$station_name = substr($_POST['path'], 6, -4); // trim 'RADIO/' and '.pls' from path
					workerLog($_GET['cmd'] . ', ' . $station_name);

					// remove row and delete file
					$result = sdbquery("DELETE FROM cfg_radio WHERE name='" . $station_name . "'", $dbh);
					sysCmd('rm "' . MPD_MUSICROOT . $_POST['path'] . '"');
					sysCmd('rm "' . '/var/www/images/radio-logos/' . $station_name . '.jpg' . '"');
					sysCmd('rm "' . '/var/www/images/radio-logos/thumbs/' . $station_name . '.jpg' . '"');
					
					// update time stamp on files so mpd picks up the change and commits the update
					sysCmd('find ' . MPD_MUSICROOT . 'RADIO -name *.pls -exec touch {} \+');
					
					sendMpdCmd($sock, 'update RADIO');
					readMpdResp($sock);
					
					echo json_encode('OK');
				}
				break;
				
			// LIBRARY PANEL
	        case 'addall':
	            if (isset($_POST['path']) && $_POST['path'] != '') {
	                echo json_encode(addallToPL($sock, $_POST['path']));
				}
				break;
	        case 'playall':
	            if (isset($_POST['path']) && $_POST['path'] != '') {
					$status = parseStatus(getMpdStatus($sock));
					$pos = $status['playlistlength'];
					
	            	addallToPL($sock, $_POST['path']);
					usleep(500000); // needed after bulk add to pl
					
					playerSession('write', 'toggle_song', $pos); // reset toggle_song

					sendMpdCmd($sock, 'play ' . $pos);
					echo json_encode(readMpdResp($sock));
	            }
				break;
	        case 'clrplayall':
	            if (isset($_POST['path']) && $_POST['path'] != '') {
					sendMpdCmd($sock,'clear');
					$resp = readMpdResp($sock);
							            
	            	addallToPL($sock, $_POST['path']);		            
					//usleep(500000); // needed after bulk add to pl

					playerSession('write', 'toggle_song', '0'); // reset toggle_song

					sendMpdCmd($sock, 'play'); // defaults to pos 0
					echo json_encode(readMpdResp($sock));
				}
				break;
	        case 'loadlib':
				echo loadLibrary($sock);
	        	break;
			case 'clrlibcache':
				clearLibCache();
				echo json_encode('OK');
				break;

			// SOURCES CONFIG
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
		}
	}		
}

closeMpdSock($sock);
