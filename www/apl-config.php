<?php
/*
 * SPDX-License-Identifier: GPL-3.0-or-later
 * Copyright 2014 The moOde audio player project / Tim Curtis
*/

require_once __DIR__ . '/inc/common.php';
require_once __DIR__ . '/inc/renderer.php';
require_once __DIR__ . '/inc/session.php';
require_once __DIR__ . '/inc/sql.php';

$dbh = sqlConnect();
phpSession('open');

if (isset($_POST['save']) && $_POST['save'] == '1') {
	foreach ($_POST['config'] as $key => $value) {
		chkValue($key, $value);
		sqlUpdate('cfg_airplay', $dbh, $key, $value);
		$value = is_numeric($value) ? $value : '"' . $value . '"';
		sysCmd("sed -i 's/^" . $key . " = \".*\";/" . $key . ' = ' .$value . ';/ /etc/shairport-sync.conf');
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

// Show/hide options
$majorVersion = getAirPlayVersion('major');
$_version_5_show_hide = $majorVersion == '5' ? '' : 'hide';
$_not_user_configurable = 'hide';

// General
$_select['interpolation'] .= "<option value=\"auto\" " . (($cfgAirplay['interpolation'] == 'auto') ? "selected" : "") . ">Auto (Default)</option>\n";
if ($majorVersion == '5') {
	$_select['interpolation'] .= "<option value=\"vernier\" " . (($cfgAirplay['interpolation'] == 'vernier') ? "selected" : "") . ">Vernier</option>\n";
}
$_select['interpolation'] .= "<option value=\"soxr\" " . (($cfgAirplay['interpolation'] == 'soxr') ? "selected" : "") . ">SoX</option>\n";
$_select['interpolation'] .= "<option value=\"basic\" " . (($cfgAirplay['interpolation'] == 'basic') ? "selected" : "") . ">Basic</option>\n";

$_select['disable_synchronization'] .= "<option value=\"yes\" " . (($cfgAirplay['disable_synchronization'] == 'yes') ? "selected" : "") . ">Yes</option>\n";
$_select['disable_synchronization'] .= "<option value=\"no\" " . (($cfgAirplay['disable_synchronization'] == 'no') ? "selected" : "") . ">No</option>\n";
$_select['disable_standby_mode'] .= "<option value=\"always\" " . (($cfgAirplay['disable_standby_mode'] == 'always') ? "selected" : "") . ">Always</option>\n";
$_select['disable_standby_mode'] .= "<option value=\"auto\" " . (($cfgAirplay['disable_standby_mode'] == 'auto') ? "selected" : "") . ">Auto</option>\n";
$_select['disable_standby_mode'] .= "<option value=\"never\" " . (($cfgAirplay['disable_standby_mode'] == 'never') ? "selected" : "") . ">Never (Default)</option>\n";
// Not user configurable
$_select['cover_art_cache_directory'] = $cfgAirplay['cover_art_cache_directory'];
//
// Audio
$_select['audio_backend_latency_offset_in_seconds'] = $cfgAirplay['audio_backend_latency_offset_in_seconds'];
$_select['audio_backend_buffer_desired_length_in_seconds'] = $cfgAirplay['audio_backend_buffer_desired_length_in_seconds'];
$_select['output_rate'] .= "<option value=\"auto\" " . (($cfgAirplay['output_rate'] == 'auto') ? "selected" : "") . ">Auto (Default)</option>\n";
$_select['output_rate'] .= "<option value=\"44100\" " . (($cfgAirplay['output_rate'] == '44100') ? "selected" : "") . ">44.1 kHz</option>\n";
$_select['output_rate'] .= "<option value=\"88200\" " . (($cfgAirplay['output_rate'] == '88200') ? "selected" : "") . ">88.2 kHz</option>\n";
$_select['output_rate'] .= "<option value=\"176400\" " . (($cfgAirplay['output_rate'] == '176400') ? "selected" : "") . ">176.4 kHz</option>\n";
$_select['output_rate'] .= "<option value=\"352800\" " . (($cfgAirplay['output_rate'] == '352800') ? "selected" : "") . ">352.8 kHz</option>\n";
if ($majorVersion == '5') {
	$_select['output_rate'] .= "<option value=\"384000\" " . (($cfgAirplay['output_rate'] == '384000') ? "selected" : "") . ">384.0 kHz</option>\n";
}
$_select['output_format'] .= "<option value=\"auto\" " . (($cfgAirplay['output_format'] == 'auto') ? "selected" : "") . ">Auto (Default)</option>\n";
$_select['output_format'] .= "<option value=\"S16\" " . (($cfgAirplay['output_format'] == 'S16') ? "selected" : "") . ">16 bit</option>\n";
$_select['output_format'] .= "<option value=\"S24\" " . (($cfgAirplay['output_format'] == 'S24') ? "selected" : "") . ">24 bit</option>\n";
$_select['output_format'] .= "<option value=\"S24_3LE\" " . (($cfgAirplay['output_format'] == 'S24_3LE') ? "selected" : "") . ">24 bit 3LE</option>\n";
$_select['output_format'] .= "<option value=\"S24_3BE\" " . (($cfgAirplay['output_format'] == 'S24_3BE') ? "selected" : "") . ">24 bit 3BE</option>\n";
$_select['output_format'] .= "<option value=\"S32\" " . (($cfgAirplay['output_format'] == 'S32') ? "selected" : "") . ">32 bit</option>\n";
$_select['output_channels'] .= "<option value=\"auto\" " . (($cfgAirplay['output_channels'] == 'auto') ? "selected" : "") . ">Auto (Default)</option>\n";
$_select['output_channels'] .= "<option value=\"2\" " . (($cfgAirplay['output_channels'] == '2') ? "selected" : "") . ">2 Channel</option>\n";
$_select['output_channels'] .= "<option value=\"6\" " . (($cfgAirplay['output_channels'] == '6') ? "selected" : "") . ">5.1 Channel</option>\n";
$_select['output_channels'] .= "<option value=\"8\" " . (($cfgAirplay['output_channels'] == '8') ? "selected" : "") . ">7.1 Channel</option>\n";
if ($majorVersion == '5') {
	$_select['eight_channel_mode'] .= "<option value=\"on\" " . (($cfgAirplay['eight_channel_mode'] == 'on') ? "selected" : "") . ">On (Default)</option>\n";
	$_select['eight_channel_mode'] .= "<option value=\"off\" " . (($cfgAirplay['eight_channel_mode'] == 'off') ? "selected" : "") . ">Off</option>\n";
	$_select['six_channel_mode'] .= "<option value=\"on\" " . (($cfgAirplay['six_channel_mode'] == 'on') ? "selected" : "") . ">On (Default)</option>\n";
	$_select['six_channel_mode'] .= "<option value=\"off\" " . (($cfgAirplay['six_channel_mode'] == 'off') ? "selected" : "") . ">Off</option>\n";
	$_select['mixdown'] .= "<option value=\"auto\" " . (($cfgAirplay['mixdown'] == 'auto') ? "selected" : "") . ">Auto (Default)</option>\n";
	$_select['mixdown'] .= "<option value=\"stereo\" " . (($cfgAirplay['mixdown'] == 'stereo') ? "selected" : "") . ">2 Channel</option>\n";
	$_select['mixdown'] .= "<option value=\"5.1\" " . (($cfgAirplay['mixdown'] == '5.1') ? "selected" : "") . ">5.1 Channel</option>\n";
	$_select['mixdown'] .= "<option value=\"7.1\" " . (($cfgAirplay['mixdown'] == '7.1') ? "selected" : "") . ">7.1 Channel</option>\n";
	$_select['output_channel_mapping'] .= "<option value=\"auto\" " . (($cfgAirplay['output_channel_mapping'] == 'auto') ? "selected" : "") . ">Auto (Default)</option>\n";
	$_select['output_channel_mapping'] .= "<option value=\"stereo\" " . (($cfgAirplay['output_channel_mapping'] == 'stereo') ? "selected" : "") . ">2 Channel</option>\n";
	$_select['output_channel_mapping'] .= "<option value=\"5.1\" " . (($cfgAirplay['output_channel_mapping'] == '5.1') ? "selected" : "") . ">5.1 Channel</option>\n";
	$_select['output_channel_mapping'] .= "<option value=\"7.1\" " . (($cfgAirplay['output_channel_mapping'] == '7.1') ? "selected" : "") . ">7.1 Channel</option>\n";
}
// Session
// Not user configurable
$_select['run_this_before_entering_active_state'] = $cfgAirplay['run_this_before_entering_active_state'];
$_select['run_this_after_exiting_active_state'] = $cfgAirplay['run_this_after_exiting_active_state'];
//
$_select['active_state_timeout'] = $cfgAirplay['active_state_timeout'];
// Not user configurable
$_select['wait_for_completion'] .= "<option value=\"yes\" " . (($cfgAirplay['wait_for_completion'] == 'yes') ? "selected" : "") . ">Yes</option>\n";
$_select['wait_for_completion'] .= "<option value=\"no\" " . (($cfgAirplay['wait_for_completion'] == 'no') ? "selected" : "") . ">No</option>\n";
//
$_select['allow_session_interruption'] .= "<option value=\"yes\" " . (($cfgAirplay['allow_session_interruption'] == 'yes') ? "selected" : "") . ">Yes</option>\n";
$_select['allow_session_interruption'] .= "<option value=\"no\" " . (($cfgAirplay['allow_session_interruption'] == 'no') ? "selected" : "") . ">No</option>\n";
$_select['session_timeout'] = $cfgAirplay['session_timeout'];

waitWorker('apl-config');

$tpl = "apl-config.html";
$section = basename(__FILE__, '.php');
storeBackLink($section, $tpl);

include('header.php');
eval("echoTemplate(\"" . getTemplate("templates/$tpl") . "\");");
include('footer.php');
