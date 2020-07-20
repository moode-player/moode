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
 * 2020-MM-DD TC moOde 6.7.1
 *
 */

require_once dirname(__FILE__) . '/inc/playerlib.php';

playerSession('open', '' ,'');
$dbh = cfgdb_connect();

// apply setting changes
if (isset($_POST['save']) && $_POST['save'] == '1') {
	// format curve values
	for($i = 0; $i < 9; $i++) {
		$curve_values .= $_POST['freq' . ($i + 1)] . ',';
	}
	$curve_values .= $_POST['freq10'];
	//workerLog($curve_values);

	// add or update
	$result = sdbquery("SELECT id FROM cfg_eqalsa WHERE curve_name='" . $_POST['curve_name'] . "'", $dbh);
	if (empty($result[0])) {
		// add
		$newid = sdbquery('SELECT MAX(id)+1 FROM cfg_eqalsa', $dbh);
		$result = sdbquery("INSERT INTO cfg_eqalsa VALUES ('" . $newid[0][0] . "','" . $_POST['curve_name'] . "','" . $curve_values . "')", $dbh);
		$_GET['curve'] = $_POST['curve_name'];
		$_SESSION['notify']['title'] = 'New curve added';
	}
	else {
		// update
		$result = sdbquery("UPDATE cfg_eqalsa SET curve_values='" . $curve_values . "' WHERE curve_name='" . $_POST['curve_name'] . "'" , $dbh);
		$_SESSION['notify']['title'] = 'Curve updated';
	}
}

// play/test curve
if (isset($_POST['play']) && $_POST['play'] == '1') {
	// update alsaequal, changes take efect in real-time, no need to restart MPD
	for($i = 1; $i <= 10; $i++) {
		sysCmd('amixer -D alsaequal cset numid=' . $i . ' ' . $_POST['freq' . $i]);
	}

	// wait for mpd to start accepting connections
	$sock = openMpdSock('localhost', 6600);
	// initiate play
	sendMpdCmd($sock, 'stop');
	$resp = readMpdResp($sock);
	sendMpdCmd($sock, 'play');
	$resp = readMpdResp($sock);
	closeMpdSock($sock);

	playerSession('write', 'alsaequal', $_POST['curve_name']);

	$_SESSION['notify']['title'] = 'Playing curve';
}

// add, remove, change, refresh
if (isset($_POST['newcurvename'])) {
	$_search_curve = 'Flat';
}
elseif (isset($_POST['rmcurve'])) {
	$result = sdbquery("DELETE FROM cfg_eqalsa WHERE curve_name='" . $_GET['curve'] . "'", $dbh);
	$_search_curve = 'Flat';
	$_SESSION['notify']['title'] = 'Curve removed';
}
elseif (isset($_GET['curve'])) {
	$_search_curve = $_GET['curve'];
}
else {
	$_search_curve = $_SESSION['alsaequal'] == 'Off' ? 'Flat' : $_SESSION['alsaequal'];
}

session_write_close();

// load curve list
$_selected_curve = 'Flat';
$curveList = sdbquery('SELECT curve_name FROM cfg_eqalsa', $dbh);
foreach ($curveList as $curve) {
	$selected = ($_search_curve == $curve['curve_name'] && $_POST['newcurvename'] != '1') ? 'selected' : '';
	$_select['curve_name'] .= sprintf('<option value="%s" %s>%s</option>\n', $curve['curve_name'], $selected, $curve['curve_name']);
	if ($selected == 'selected') {
		$_selected_curve = $curve['curve_name'];
	}
}

if (isset($_POST['newcurvename']) && $_POST['newcurvename'] == '1') {
	$_select['curve_name'] .= sprintf('<option value="%s" %s>%s</option>\n', $_POST['new-curvename'], 'selected', $_POST['new-curvename']);
	$_selected_curve = $_POST['new-curvename'];
}

// set control states
$_disable_play = $_SESSION['alsaequal'] == 'Off' ? 'disabled' : '';
$_disable_rm = $_selected_curve == 'Flat' ? 'disabled' : '';
$_disable_rm_msg = $_selected_curve == 'Flat' ? 'Flat curve cannot be removed' : '';

// load curve values
$result = sdbquery("SELECT * FROM cfg_eqalsa WHERE curve_name='" . $_search_curve . "'", $dbh);

$values = explode(',', $result[0]['curve_values']);
for ($i = 0; $i < 10; $i++) {
	$_select['freq' . ($i + 1)] = $values[$i];
}

waitWorker(1, 'eqg-config');

$tpl = "eqg-config.html";
$section = basename(__FILE__, '.php');
storeBackLink($section, $tpl);

include('header.php');
eval("echoTemplate(\"" . getTemplate("templates/$tpl") . "\");");
include('footer.min.php');
