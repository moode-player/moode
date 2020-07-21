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
	// format individual band params
	for($i = 1; $i <= 4; $i++) {
		$_POST['band' . $i . '_params'] = $_POST['band' . $i . '_enabled'] . ' ' . $_POST['band' . $i . '_freq'] . ' ' . (float)$_POST['band' . $i . '_q'] . ' ' . (float)$_POST['band' . $i . '_gain'];
	}

	// add or update
	$result = sdbquery("SELECT id FROM cfg_eqfa4p WHERE curve_name='" . $_POST['curve_name'] . "'", $dbh);
	if (empty($result[0])) {
		// add
		$newid = sdbquery('SELECT MAX(id)+1 FROM cfg_eqfa4p', $dbh);
		$result = sdbquery("INSERT INTO cfg_eqfa4p VALUES ('" . $newid[0][0] . "','" . $_POST['curve_name'] . "','" . (float)$_POST['master_gain'] . "','" . $_POST['band1_params']  . "','" . $_POST['band2_params'] . "','" . $_POST['band3_params'] . "','" . $_POST['band4_params'] . "')", $dbh);
		$_GET['curve'] = $_POST['curve_name'];
		$_SESSION['notify']['title'] = 'New curve added';
	}
	else {
		// update
		$value = array('master_gain' => (float)$_POST['master_gain'], 'band1_params' => $_POST['band1_params'], 'band2_params' => $_POST['band2_params'], 'band3_params' => $_POST['band3_params'], 'band4_params' => $_POST['band4_params']);
		cfgdb_update('cfg_eqfa4p', $dbh, $_POST['curve_name'], $value);
		$_SESSION['notify']['title'] = 'Curve updated';
	}
}

// play/test curve
if (isset($_POST['play']) && $_POST['play'] == '1') {
	// format individual band params
	for($i = 1; $i <= 4; $i++) {
		$_POST['band' . $i . '_params'] = $_POST['band' . $i . '_enabled'] . ' ' . $_POST['band' . $i . '_freq'] . ' ' . (float)$_POST['band' . $i . '_q'] . ' ' . (float)$_POST['band' . $i . '_gain'];
	}

	$params = $_POST['band1_params'] . '  ' . $_POST['band2_params'] . '  ' . $_POST['band3_params'] . '  ' . $_POST['band4_params'] . '  ' . (float)$_POST['master_gain'];
	sysCmd('sed -i "/controls/c\ \t\t\tcontrols [ ' . $params . ' ]" ' . ALSA_PLUGIN_PATH . '/eqfa4p.conf');

	sysCmd('systemctl restart mpd');

	// wait for mpd to start accepting connections
	$sock = openMpdSock('localhost', 6600);
	// initiate play
	sendMpdCmd($sock, 'stop');
	$resp = readMpdResp($sock);
	sendMpdCmd($sock, 'play');
	$resp = readMpdResp($sock);
	closeMpdSock($sock);

	playerSession('write', 'eqfa4p', $_POST['curve_name']);

	$_SESSION['notify']['title'] = 'Playing curve';
}

//workerLog('newcurvename=(' . $_POST['newcurvename'] . '), rmcurve=(' . $_POST['rmcurve'] . '), curve=(' .  $_GET['curve'] . ')');
// add, remove, change, refresh
if (isset($_POST['newcurvename'])) {
	$_search_curve = 'Default';
}
elseif (isset($_POST['rmcurve'])) {
	$result = sdbquery("DELETE FROM cfg_eqfa4p WHERE curve_name='" . $_GET['curve'] . "'", $dbh);
	$_search_curve = 'Default';
	$_SESSION['notify']['title'] = 'Curve removed';
}
elseif (isset($_GET['curve'])) {
	$_search_curve = $_GET['curve'];
}
else {
	$_search_curve = $_SESSION['eqfa4p'] == 'Off' ? 'Default' : $_SESSION['eqfa4p'];
}

session_write_close();

// load curve list
$_selected_curve = 'Default';
$curveList = sdbquery('SELECT curve_name FROM cfg_eqfa4p', $dbh);
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
$_disable_play = $_SESSION['eqfa4p'] == 'Off' ? 'disabled' : '';
$_disable_rm = $_selected_curve == 'Default' ? 'disabled' : '';
$_disable_rm_msg = $_selected_curve == 'Default' ? 'Default curve cannot be removed' : '';

// load curve params
$result = sdbquery("SELECT * FROM cfg_eqfa4p WHERE curve_name='" . $_search_curve . "'", $dbh);

$_select['master_gain'] = $result[0]['master_gain'];

for($i = 1; $i <= 4; $i++) {
	$params = explode(' ', $result[0]['band' . $i . '_params']);

	$_select['band' . $i . '_enabled'] .= sprintf('<option value="%s"%s>%s</option>\n', '1', $params[0] == '1' ? 'selected' : '', 'Yes');
	$_select['band' . $i . '_enabled'] .= sprintf('<option value="%s"%s>%s</option>\n', '0', $params[0] == '0' ? 'selected' : '', 'No');
	$_select['band' . $i . '_freq'] = $params[1];
	$_select['band' . $i . '_q'] = $params[2];
	$_select['band' . $i . '_gain'] = $params[3];
}

waitWorker(1, 'eqp-config');

$tpl = "eqp-config.html";
$section = basename(__FILE__, '.php');
storeBackLink($section, $tpl);

include('header.php');
eval("echoTemplate(\"" . getTemplate("templates/$tpl") . "\");");
include('footer.min.php');
