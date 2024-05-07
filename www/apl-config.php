<?php
/**
 * SPDX-License-Identifier: GPL-3.0-or-later
 * Copyright 2014 The moOde audio player project / Tim Curtis
*/

require_once __DIR__ . '/inc/common.php';
require_once __DIR__ . '/inc/session.php';
require_once __DIR__ . '/inc/sql.php';

$dbh = sqlConnect();
phpSession('open');

if (isset($_POST['save']) && $_POST['save'] == '1') {
	foreach ($_POST['config'] as $key => $value) {
		sqlUpdate('cfg_airplay', $dbh, $key, $value);

		if ($value != 'deprecated') {
			$value = is_numeric($value) ? $value : '"' . $value . '"';
			sysCmd("sed -i '/" . $key . ' =' . '/c\\' . $key . ' = ' . $value . ";' /etc/shairport-sync.conf"); // 3.3.y
		}
	}
	$notify = $_SESSION['airplaysvc'] == '1' ?
		array('title' => NOTIFY_TITLE_INFO, 'msg' => NAME_AIRPLAY . NOTIFY_MSG_SVC_RESTARTED) :
		array('title' => '', 'msg' => '');
	submitJob('airplaysvc', '', $notify['title'], $notify['msg']);
}

phpSession('close');

$result = sqlRead('cfg_airplay', $dbh);
$cfgAirplay = array();

foreach ($result as $row) {
	$cfgAirplay[$row['param']] = $row['value'];
}


$_select['interpolation'] .= "<option value=\"basic\" " . (($cfgAirplay['interpolation'] == 'basic') ? "selected" : "") . ">Basic</option>\n";
$_select['interpolation'] .= "<option value=\"soxr\" " . (($cfgAirplay['interpolation'] == 'soxr') ? "selected" : "") . ">SoX</option>\n";

$_select['output_format'] .= "<option value=\"S16\" " . (($cfgAirplay['output_format'] == 'S16') ? "selected" : "") . ">16 bit</option>\n";
$_select['output_format'] .= "<option value=\"S24\" " . (($cfgAirplay['output_format'] == 'S24') ? "selected" : "") . ">24 bit</option>\n";
$_select['output_format'] .= "<option value=\"S24_3LE\" " . (($cfgAirplay['output_format'] == 'S24_3LE') ? "selected" : "") . ">24 bit 3LE</option>\n";
$_select['output_format'] .= "<option value=\"S24_3BE\" " . (($cfgAirplay['output_format'] == 'S24_3BE') ? "selected" : "") . ">24 bit 3BE</option>\n";
$_select['output_format'] .= "<option value=\"S32\" " . (($cfgAirplay['output_format'] == 'S32') ? "selected" : "") . ">32 bit</option>\n";

$_select['output_rate'] .= "<option value=\"44100\" " . (($cfgAirplay['output_rate'] == '44100') ? "selected" : "") . ">44.1 kHz</option>\n";
$_select['output_rate'] .= "<option value=\"88200\" " . (($cfgAirplay['output_rate'] == '88200') ? "selected" : "") . ">88.2 kHz</option>\n";
$_select['output_rate'] .= "<option value=\"176400\" " . (($cfgAirplay['output_rate'] == '176400') ? "selected" : "") . ">176.4 kHz</option>\n";
$_select['output_rate'] .= "<option value=\"352800\" " . (($cfgAirplay['output_rate'] == '352800') ? "selected" : "") . ">352.8 kHz</option>\n";

$_select['allow_session_interruption'] .= "<option value=\"yes\" " . (($cfgAirplay['allow_session_interruption'] == 'yes') ? "selected" : "") . ">Yes</option>\n";
$_select['allow_session_interruption'] .= "<option value=\"no\" " . (($cfgAirplay['allow_session_interruption'] == 'no') ? "selected" : "") . ">No</option>\n";

$_select['session_timeout'] = $cfgAirplay['session_timeout'];
$_select['audio_backend_latency_offset_in_seconds'] = $cfgAirplay['audio_backend_latency_offset_in_seconds'];
$_select['audio_backend_buffer_desired_length_in_seconds'] = $cfgAirplay['audio_backend_buffer_desired_length_in_seconds'];

waitWorker('apl-config');

$tpl = "apl-config.html";
$section = basename(__FILE__, '.php');
storeBackLink($section, $tpl);

include('header.php');
eval("echoTemplate(\"" . getTemplate("templates/$tpl") . "\");");
include('footer.php');
