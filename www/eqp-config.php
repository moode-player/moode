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

require_once dirname(__FILE__) . '/inc/playerlib.php';
require_once dirname(__FILE__) . '/inc/eqp.php';

playerSession('open', '' ,'');
$dbh = cfgdb_connect();
$eqp12 = Eqp12($dbh);

$curve_config = NULL;

function postData2Config($data, $bands) {
	$config = [];
	$config['bands'] = [];
	for($i = 1; $i <= $bands; $i++) {
		$bandconfig =[];
		$bandconfig['enabled'] = $data['band' . $i . '_enabled'] ;
        $bandconfig['frequency'] =  $data['band' . $i . '_freq'];
        $bandconfig['bandwidth'] = (float)$data['band' . $i . '_q'];
		$bandconfig['gain'] =(float)$data['band' . $i . '_gain'];
		array_push($config['bands'],$bandconfig);
	}
	$config['master_gain']= (float)$data['master_gain'];
	return $config;
}
// apply setting changes
if (isset($_POST['save']) && $_POST['save'] == '1') {
	// format individual band params
	$curve_id = intval($_POST['curve_id']);
	$config = postData2Config($_POST, 12);
	$eqp12->setpreset($curve_id, NULL, $config);

	if($curve_id == $eqp12->getActivePresetIndex() ) {
		$eqp12->applyConfig($config);
		sysCmd('systemctl restart mpd');
		// // wait for mpd to start accepting connections
		$sock = openMpdSock('localhost', 6600);
		// initiate play
		sendMpdCmd($sock, 'stop');
		$resp = readMpdResp($sock);
		sendMpdCmd($sock, 'play');
		$resp = readMpdResp($sock);
		closeMpdSock($sock);
	}
	// // add or update
	$_SESSION['notify']['title'] = 'Curve updated';
	// 	$_SESSION['notify']['title'] = 'New curve added';
}

// play/test curve
if (isset($_POST['play']) && $_POST['play'] == '1') {
	$config = postData2Config($_POST, 12);
	$eqp12->applyConfig($config);
	sysCmd('systemctl restart mpd');
	// // wait for mpd to start accepting connections
	$sock = openMpdSock('localhost', 6600);
	// initiate play
	sendMpdCmd($sock, 'stop');
	$resp = readMpdResp($sock);
	sendMpdCmd($sock, 'play');
	$resp = readMpdResp($sock);
	closeMpdSock($sock);

	// playerSession('write', 'eqfa4p', $_POST['curve_name']);
	$curve_config = $config;
	$_SESSION['notify']['title'] = 'Playing curve';
}

workerLog('newcurvename=(' . $_POST['newcurvename'] . '), rmcurve=(' . $_POST['rmcurve'] . '), curve=(' .  $_GET['curve'] . ')');
// add, remove, change, refresh

if (isset($_POST['newcurvename']) && $_POST['newcurvename'] == '1') {
	$new_curve_id = $eqp12->setpreset(NULL, $_POST['new-curvename'], $eqp12->getpreset($eqp12->getActivePresetIndex())  );
	if( $new_curve_id) {
		$_selected_curve_id = $new_curve_id;
		$_SESSION['notify']['title'] = 'New curve added';
	}
}
elseif (isset($_POST['rmcurve'])) {
	$current_id = $_GET['curve'];
	if( $current_id!=1 ) {
		$eqp12->unsetpreset($current_id);
		$_selected_curve_id = 1;
		$_SESSION['notify']['title'] = 'Curve removed';
	}
}
elseif (isset($_GET['curve'])) {
	$_selected_curve_id = $_GET['curve'];
}
else {
	//$_search_curve = $_SESSION['eqfa4p'] == 'Off' ? 'Default curve' : $_SESSION['eqfa4p'];
	$_selected_curve_id = $eqp12->getActivePresetIndex();
}

session_write_close();

// // load curve list
if(!$_selected_curve_id) {
	$_selected_curve_id = 1;
}

$curveList = $eqp12->getPresets();
foreach ($curveList as $curve_id=>$curve_name) {
	$selected = ($_selected_curve_id == $curve_id) ? 'selected' : '';
	$_select['curve_name'] .= sprintf("<option value='%s' %s>%s</option>\n", $curve_id, $selected, $curve_name);
	if ($selected == 'selected') {
		$_selected_curve = $curve_name;
	}
}

// set control states
$_disable_play = $_SESSION['eqfa4p'] == 'Off' ? 'disabled' : '';

$_disable_rm = $_selected_curve_id == 1 ? 'disabled' : '';
$_disable_rm_msg = $_selected_curve_id == 1 ? 'The Default curve cannot be removed' : '';

//load curve params
if( !$curve_config) {
	$curve_config = $eqp12->getpreset($_selected_curve_id);
}

$_select['master_gain'] = $curve_config['master_gain'];

foreach($curve_config['bands'] as $band_key=>$band_config) {
	$i = $band_key + 1;
	$_select['band' . $i . '_enabled'] .= sprintf('<option value="%s"%s>%s</option>\n', '1', $band_config['enabled'] == 1 ? 'selected' : '', 'Yes');
	$_select['band' . $i . '_enabled'] .= sprintf('<option value="%s"%s>%s</option>\n', '0', $band_config['enabled'] == 0 ? 'selected' : '', 'No');
	$_select['band' . $i . '_freq'] = $band_config['frequency'];
	$_select['band' . $i . '_q'] = $band_config['bandwidth'];
	$_select['band' . $i . '_gain'] = $band_config['gain'];
}

waitWorker(1, 'eqp-config');

$tpl = "eqp-config.html";
$section = basename(__FILE__, '.php');
storeBackLink($section, $tpl);

include('header.php');
eval("echoTemplate(\"" . getTemplate("templates/$tpl") . "\");");
include('footer.min.php');
