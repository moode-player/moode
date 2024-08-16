<?php
/*
 * SPDX-License-Identifier: GPL-3.0-or-later
 * Copyright 2014 The moOde audio player project / Tim Curtis
*/

require_once __DIR__ . '/../inc/common.php';
require_once __DIR__ . '/../inc/mpd.php';
require_once __DIR__ . '/../inc/session.php';
require_once __DIR__ . '/../inc/sql.php';

chkVariables($_GET);
chkVariables($_POST, array('path'));

switch ($_GET['cmd']) {
	case 'set_ralogo_image':
		if (submitJob($_GET['cmd'], $_POST['name'] . ',' . $_POST['blob'], '', '')) {
			echo json_encode('job submitted');
		}
		else {
			echo json_encode('worker busy');
		}
		break;
	case 'new_station':
	case 'upd_station':
		$stName = $_POST['path']['name'];
		$stFile = MPD_MUSICROOT . 'RADIO/' . $stName . '.pls';
		$stUrl = $_POST['path']['url'];
		$stRowId = $_POST['path']['id'];

		$msg = validateInput($_GET['cmd'], $stName, $stFile, $stUrl, $stRowId);
		if ($msg == 'OK') {
			putStationContents($_GET['cmd'], $_POST['path'], $stFile);
			putStationCover($stName);
		} else {
			sleep(1); // For smoother messaging on client
		}

		echo json_encode($msg);
		break;
	case 'del_station':
		$stPls = $_POST['path'];
		$stName = substr($_POST['path'], 6, -4); // Trim RADIO/ and .pls
		deleteStation($stName, $stPls);
		break;
	case 'get_stations':
		echo json_encode(getStations());
		break;
	case 'get_station_contents':
		$stName = substr($_POST['path'], 6, -4);
		echo json_encode(getStationContents($stName));
		break;
	case 'put_radioview_show_hide':
		putRadioViewShowHide($_POST['block'], $_POST['type']);
		break;

	case 'mpd_monitor_svc':
		sysCmd('killall -s 9 mpdmon.php');
		//workerLog($_POST['svc'] . '|' . $_POST['opt']);
		if ($_POST['svc'] == 'On') {
			sysCmd('/var/www/daemon/mpdmon.php "' . $_POST['opt'] . '" > /dev/null 2>&1 &');
		}
		break;
	default:
		echo 'Unknown command';
		break;
}

// Close MPD socket
if (isset($sock) && $sock !== false) {
	closeMpdSock($sock);
}

// Return list of stations
function getStations() {
	return sqlRead('cfg_radio', sqlConnect(), 'all');
}

// Return station metadata
function getStationContents($stName) {
	$result = sqlQuery("SELECT * FROM cfg_radio WHERE name='" . SQLite3::escapeString($stName) . "'", sqlConnect());
	$contents = array(
		'id' => $result[0]['id'],
		'station' => $result[0]['station'],
		'name' => $result[0]['name'],
		'type' => $result[0]['type'],
		'logo' =>  $result[0]['logo'],
		'genre' => $result[0]['genre'],
		'broadcaster' => $result[0]['broadcaster'],
		'language' => $result[0]['language'],
		'country' => $result[0]['country'],
		'region' => $result[0]['region'],
		'bitrate' => $result[0]['bitrate'],
		'format' => $result[0]['format'],
		'geo_fenced' => $result[0]['geo_fenced'],
		'home_page' => $result[0]['home_page'],
		'monitor' => $result[0]['monitor']
	);
	return $contents;
}

// Check imput fields
function validateInput($cmd, $stName, $stFile, $stUrl, $stRowId) {
	$dbh = sqlConnect();
	$stName = SQLite3::escapeString($stName);
	$stUrl = SQLite3::escapeString($stUrl);

	if ($cmd == 'new_station') {
		if (file_exists($stFile)) {
			$msg = 'A station .pls file with the same name already exists';
		} else if (true !== sqlQuery("SELECT id FROM cfg_radio WHERE station='" . $stUrl . "'", $dbh)) {
			$msg = 'A station with same URL already exists in the database';
		} else if (true !== sqlQuery("SELECT id FROM cfg_radio WHERE name='" . $stName . "'", $dbh)) {
			$msg = 'A station with the same name already exists in the database';
		} else {
			$msg = 'OK';
		}
	}

	if ($cmd == 'upd_station') {
		// NOTE: Client prevents pls name change so check for existing station with same URL
		if (true !== sqlQuery("SELECT id FROM cfg_radio WHERE id != '" . $stRowId . "' " .
			"AND station = '" . $stUrl . "'", $dbh)) {
			$msg = 'A station with same URL already exists';
		} else {
			$msg = 'OK';
		}
	}

	return $msg;
}

// Create/update station metadata and pls file
function putStationContents($cmd, $path, $stFile) {
	$dbh = sqlConnect();

	if ($cmd == 'new_station') {
		// NOTE: Values have to be in column order and NULL causes id to be bumped
		$values =
			'NULL,' .
			"'"	. SQLite3::escapeString($path['url']) . "'," .
			"'" . SQLite3::escapeString($path['name']) . "'," .
			"'"	. $path['type'] . "'," .
			"'"	. 'local' . "'," .
			"\"" . $path['genre'] . "\"," . // Use double quotes since we may have g1, g2, g3
			"'" . $path['broadcaster'] . "'," .
			"'" . $path['language'] . "'," .
			"'" . $path['country'] . "'," .
			"'" . $path['region'] . "'," .
			"'" . $path['bitrate'] . "'," .
			"'" . $path['format'] . "'," .
			"'" . $path['geo_fenced'] . "'," .
			"'" . SQLite3::escapeString($path['home_page']) . "'," .
			"'" . $path['monitor'] . "'";
		$result = sqlQuery('INSERT INTO cfg_radio VALUES ' . '(' . $values . ')', $dbh);
	}

	if ($cmd == 'upd_station') {
		// NOTE: Values have to be in column order
		$values =
			"station='" . SQLite3::escapeString($path['url']) . "'," .
			"name='" . SQLite3::escapeString($path['name']) . "'," .
			"type='" . $path['type'] . "'," .
			"logo='local'," .
			"genre=\"" . $path['genre'] . "\"," . // Use double quotes since we may have g1, g2, g3
			"broadcaster='" . $path['broadcaster'] . "'," .
			"language='" . $path['language'] . "'," .
			"country='" . $path['country'] . "'," .
			"region='" . $path['region'] . "'," .
			"bitrate='" . $path['bitrate'] . "'," .
			"format='" . $path['format'] . "'," .
			"geo_fenced='" . $path['geo_fenced'] . "'," .
			"home_page='" . SQLite3::escapeString($path['home_page']) . "'," .
			"monitor='" . $path['monitor'] . "'";
		$result = sqlQuery('UPDATE cfg_radio SET ' . $values . " WHERE id='" . $path['id'] . "'", $dbh);
	}

	// Add/update session var
	phpSession('open');
	$_SESSION[$path['url']] = array(
		'name' => $path['name'],
		'type' => $path['type'],
		'logo' => 'local',
		'bitrate' => $path['bitrate'],
		'format' => $path['format'],
		'home_page' => $path['home_page'],
		'monitor' => $path['monitor']
	);
	phpSession('close');

	// Write pls file and set permissions
	$contents = '[playlist]' . "\n";
	$contents .= 'File1='. $path['url'] . "\n";
	$contents .= 'Title1='. $path['name'] . "\n";
	$contents .= 'Length1=-1' . "\n";
	$contents .= 'NumberOfEntries=1' . "\n";
	$contents .= 'Version=2' . "\n";

	if (false === (file_put_contents($stFile, $contents))) {
		workerLog('radio.php: file write failed on ' . $stFile);
		exit(0);
	}

	sysCmd('chmod 0777 "' . $stFile . '"');
	sysCmd('chown root:root "' . $stFile . '"');
	// Update time stamp on files so mpd picks up the change and commits the update
	sysCmd('find ' . MPD_MUSICROOT . 'RADIO -name *.pls -exec touch {} \+');

	// MPD update the radio folder
	$sock = getMpdSock();
	sendMpdCmd($sock, 'update RADIO');
	readMpdResp($sock);
}

// Add/update cover image
function putStationCover($stName) {
	$stTmpImage = RADIO_LOGOS_ROOT . TMP_IMAGE_PREFIX . $stName . '.jpg';
	$stTmpImageThm = RADIO_LOGOS_ROOT . 'thumbs/' . TMP_IMAGE_PREFIX . $stName . '.jpg';
	$stTmpImageThmSm = RADIO_LOGOS_ROOT . 'thumbs/' . TMP_IMAGE_PREFIX . $stName . '_sm.jpg';

	$stCoverImage = RADIO_LOGOS_ROOT . $stName . '.jpg';
	$stCoverImageThm = RADIO_LOGOS_ROOT . 'thumbs/' .  $stName . '.jpg';
	$stCoverImageThmSm = RADIO_LOGOS_ROOT . 'thumbs/' .  $stName . '_sm.jpg';

	$defaultImage = DEFAULT_NOTFOUND_COVER;
	sendFECmd('set_cover_image1'); // Show spinner
	sleep(3); // Allow time for set_ralogo_image job to create __tmp__ image file

	if (file_exists($stTmpImage)) {
		sysCmd('mv "' . $stTmpImage . '" "' . $stCoverImage . '"');
		sysCmd('mv "' . $stTmpImageThm . '" "' . $stCoverImageThm . '"');
		sysCmd('mv "' . $stTmpImageThmSm . '" "' . $stCoverImageThmSm . '"');
	} else if (!file_exists($stCoverImage)) {
		sysCmd('cp "' . $defaultImage . '" "' . $stCoverImage . '"');
		sysCmd('cp "' . $defaultImage . '" "' . $stCoverImageThm . '"');
		sysCmd('cp "' . $defaultImage . '" "' . $stCoverImageThmSm . '"');
	}

	sendFECmd('set_cover_image0'); // Hide spinner
}

// Delete station file, cover image, session var and SQL row
function deleteStation($stationName, $stationPls) {
	$dbh = sqlConnect();

	// Delete session var
	$result = sqlQuery("SELECT station FROM cfg_radio WHERE name='" . SQLite3::escapeString($stationName) . "'", $dbh);
	phpSession('open');
	unset($_SESSION[$result[0]['station']]);
	phpSession('close');

	// Delete row
	$result = sqlQuery("DELETE FROM cfg_radio WHERE name='" . SQLite3::escapeString($stationName) . "'", $dbh);

	// Delete pls and logo image files
	sysCmd('rm "' . MPD_MUSICROOT . $stationPls . '"');
	sysCmd('rm "' . '/var/local/www/imagesw/radio-logos/' . $stationName . '.jpg"');
	sysCmd('rm "' . '/var/local/www/imagesw/radio-logos/thumbs/' . $stationName . '.jpg"');
	sysCmd('rm "' . '/var/local/www/imagesw/radio-logos/thumbs/' . $stationName . '_sm.jpg"');

	// Update time stamp on files so MPD picks up the change
	sysCmd('find ' . MPD_MUSICROOT . 'RADIO -name *.pls -exec touch {} \+');

	// Update radio folder
	$sock = getMpdSock();
	sendMpdCmd($sock, 'update RADIO');
	readMpdResp($sock);
}

// Update radio view show/hide stations
function putRadioViewShowHide($stBlock, $stType) {
	$dbh = sqlConnect();

	if ($stBlock == 'Moode') {
		$whereClause = "WHERE id < '499' AND type != 'f'";
	} else if ($stBlock == 'Moode geo-fenced') {
		$whereClause = "WHERE id < '499' AND type != 'f' AND geo_fenced = 'Yes'";
	} else if ($stBlock == 'Other') {
		$whereClause = "WHERE id > '499' AND type != 'f'";
	}

	$result = sqlQuery("UPDATE cfg_radio SET type='" . $stType . "' " . $whereClause, $dbh);

	// Update cfg_system and reset show/hide param to "No action"
	$result = sqlRead('cfg_system', $dbh, 'radioview_show_hide');
	$showHide = explode(',', $result[0]['value']);
	strpos($stBlock, 'Moode') !== false ? $showHide[0] = 'No action' : $showHide[1] = 'No action';
	phpSession('open');
	phpSession('write', 'radioview_show_hide', $showHide[0] . ',' . $showHide[1]);
	phpSession('close');
}
