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

require_once __DIR__ . '/inc/common.php';
require_once __DIR__ . '/inc/eqp.php';
require_once __DIR__ . '/inc/mpd.php';
require_once __DIR__ . '/inc/session.php';
require_once __DIR__ . '/inc/sql.php';

$dbh = sqlConnect();
phpSession('open');

$eqp12 = Eqp12($dbh);

$curveConfig = null;
$_selected_curve_id = null;

function postData2Config($data, $bands) {
	$config = [];
	$config['bands'] = [];

	for($i = 1; $i <= $bands; $i++) {
		$bandConfig = [];
		$bandConfig['enabled'] = $data['band' . $i . '_enabled'] ;
        $bandConfig['frequency'] = $data['band' . $i . '_freq'];
        $bandConfig['q'] = (float)$data['band' . $i . '_q'];
		$bandConfig['gain'] = (float)$data['band' . $i . '_gain'];
		array_push($config['bands'], $bandConfig);
	}
	$config['master_gain'] = (float)$data['master_gain'];
	return $config;
}

if (isset($_POST['curve_id']) && isset($_POST['save']) && $_POST['save'] == '1') {
	$curveID = intval($_POST['curve_id']);
	$config = postData2Config($_POST, 12);
	$eqp12->setpreset($curveID, null, $config);
	$_selected_curve_id = $curveID;

	if ($curveID == $eqp12->getActivePresetIndex()) {
		$playing = sysCmd('mpc status | grep "\[playing\]"');
		$eqp12->applyConfig($config);
		sysCmd('systemctl restart mpd');
		// Wait for mpd to start accepting connections
		$sock = openMpdSock('localhost', 6600);
		closeMpdSock($sock);
		// Then initiate play
		if (!empty($playing)) {
			sysCmd('mpc play');
		}
	}

	$_SESSION['notify']['title'] = 'Curve updated';
}

if (isset($_POST['play']) && $_POST['play'] == '1') {
	$config = postData2Config($_POST, 12);
	$eqp12->applyConfig($config);
	sysCmd('systemctl restart mpd');
	// Wait for mpd to start accepting connections
	$sock = openMpdSock('localhost', 6600);
	// Initiate play
	sendMpdCmd($sock, 'stop');
	$resp = readMpdResp($sock);
	sendMpdCmd($sock, 'play');
	$resp = readMpdResp($sock);
	closeMpdSock($sock);

	$curveConfig = $config;
	$_SESSION['notify']['title'] = 'Playing curve';
}

// Add, remove, change, refresh
if (isset($_POST['curve_id']) && isset($_POST['newcurvename']) && $_POST['newcurvename'] == '1') {
	$curveID = intval($_POST['curve_id']);
	$newCurveID = $eqp12->setpreset(null, $_POST['new_curve_name'], $eqp12->getpreset($curveID));
	if ($newCurveID) {
		$_selected_curve_id = $newCurveID;
		$_SESSION['notify']['title'] = 'New curve added';
	}
} else if (isset($_POST['curve_id']) && isset($_POST['rmcurve'])) {
	$currentID = intval($_POST['curve_id']);
	if ($currentID && $currentID != 1) {
		$eqp12->unsetpreset($currentID);
		$_selected_curve_id = 1;
		$_SESSION['notify']['title'] = 'Curve removed';
	}
} else if ($_selected_curve_id == null and isset($_GET['curve'])) {
	$_selected_curve_id = $_GET['curve'];
} else if ($_selected_curve_id == null) {
	$_selected_curve_id = $eqp12->getActivePresetIndex();
}

phpSession('close');

// Load curve list
if (!$_selected_curve_id) {
	$_selected_curve_id = 1;
}

$curveList = $eqp12->getPresets();
foreach ($curveList as $curveID => $curveName) {
	$selected = ($_selected_curve_id == $curveID) ? 'selected' : '';
	$_select['curve_name'] .= sprintf("<option value='%s' %s>%s</option>\n", $curveID, $selected, $curveName);
	if ($selected == 'selected') {
		$_selected_curve = $curveName;
	}
}

// Set control states
$_disable_play = $_SESSION['eqfa12p'] == 'Off' ? 'disabled' : '';
$_disable_rm = $_selected_curve_id == 1 ? 'disabled' : '';
$_disable_rm_msg = $_selected_curve_id == 1 ? 'The Default curve cannot be removed' : '';

// Load curve params
if (!$curveConfig) {
	$curveConfig = $eqp12->getpreset($_selected_curve_id);
}

$_select['master_gain'] = $curveConfig['master_gain'];

foreach($curveConfig['bands'] as $bandKey => $bandConfig) {
	$i = $bandKey + 1;
	$_select['band' . $i . '_enabled'] .= sprintf('<option value="%s"%s>%s</option>\n', '1', $bandConfig['enabled'] == 1 ? 'selected' : '', 'Yes');
	$_select['band' . $i . '_enabled'] .= sprintf('<option value="%s"%s>%s</option>\n', '0', $bandConfig['enabled'] == 0 ? 'selected' : '', 'No');
	$_select['band' . $i . '_freq'] = $bandConfig['frequency'];
	$_select['band' . $i . '_q'] = $bandConfig['q'];
	$_select['band' . $i . '_gain'] = $bandConfig['gain'];
}

waitWorker('eqp-config');

$tpl = "eqp-config.html";
$section = basename(__FILE__, '.php');
storeBackLink($section, $tpl);

include('header.php');
eval("echoTemplate(\"" . getTemplate("templates/$tpl") . "\");");
include('footer.php');
