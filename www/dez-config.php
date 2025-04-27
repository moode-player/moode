<?php
/*
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
		if ($key != 'password') {
			chkValue($key, $value);
		}
		if ($key != 'password' || $value != 'Password set') {
			sqlUpdate('cfg_deezer', $dbh, $key, $value);
		}
	}
	$notify = $_SESSION['deezersvc'] == '1' ?
		array('title' => NOTIFY_TITLE_INFO, 'msg' => NAME_DEEZER . NOTIFY_MSG_SVC_RESTARTED) :
		array('title' => '', 'msg' => '');
	submitJob('deezersvc', '', $notify['title'], $notify['msg']);
}

phpSession('close');

$result = sqlRead('cfg_deezer', $dbh);
$cfgDeezer = array();

foreach ($result as $row) {
	$cfgDeezer[$row['param']] = $row['value'];
}

$_select['format'] .= "<option value=\"S16\" " . (($cfgDeezer['format'] == 'S16') ? "selected" : "") . ">S16</option>\n";
$_select['format'] .= "<option value=\"S32\" " . (($cfgDeezer['format'] == 'S32') ? "selected" : "") . ">S32 (Default)</option>\n";
$_select['format'] .= "<option value=\"F32\" " . (($cfgDeezer['format'] == 'F32') ? "selected" : "") . ">F32</option>\n";
$_select['initial_volume'] = $cfgDeezer['initial_volume'];
$_select['normalize_volume'] .= "<option value=\"Yes\" " . (($cfgDeezer['normalize_volume'] == 'Yes') ? "selected" : "") . ">Yes</option>\n";
$_select['normalize_volume'] .= "<option value=\"No\" "  . (($cfgDeezer['normalize_volume'] == 'No')  ? "selected" : "") . ">No (Default)</option>\n";
$_select['no_interruptions'] .= "<option value=\"Yes\" " . (($cfgDeezer['no_interruptions'] == 'Yes') ? "selected" : "") . ">Yes</option>\n";
$_select['no_interruptions'] .= "<option value=\"No\" "  . (($cfgDeezer['no_interruptions'] == 'No')  ? "selected" : "") . ">No (Default)</option>\n";
$_select['max_ram'] .= "<option value=\"0\" "  . (($cfgDeezer['max_ram'] == '0')  ? "selected" : "") . ">Disabled (Default)</option>\n";
$_select['max_ram'] .= "<option value=\"128\" "  . (($cfgDeezer['max_ram'] == '128')  ? "selected" : "") . ">128MB</option>\n";
$_select['max_ram'] .= "<option value=\"256\" "  . (($cfgDeezer['max_ram'] == '256')  ? "selected" : "") . ">256MB</option>\n";
$_select['max_ram'] .= "<option value=\"512\" "  . (($cfgDeezer['max_ram'] == '512')  ? "selected" : "") . ">512MB</option>\n";
$_select['dither_bits'] = $cfgDeezer['dither_bits']; // Default "Auto" is handled in inc/renderer.php startDeezerConnect()
$_select['noise_shaping'] .= "<option value=\"0\" " . (($cfgDeezer['noise_shaping'] == '0') ? "selected" : "") . ">Off</option>\n";
$_select['noise_shaping'] .= "<option value=\"1\" " . (($cfgDeezer['noise_shaping'] == '1') ? "selected" : "") . ">Minimal</option>\n";
$_select['noise_shaping'] .= "<option value=\"2\" " . (($cfgDeezer['noise_shaping'] == '2') ? "selected" : "") . ">Conservative</option>\n";
$_select['noise_shaping'] .= "<option value=\"3\" " . (($cfgDeezer['noise_shaping'] == '3') ? "selected" : "") . ">Balanced (Default)</option>\n";
$_select['noise_shaping'] .= "<option value=\"4\" " . (($cfgDeezer['noise_shaping'] == '4') ? "selected" : "") . ">Aggressive 4</option>\n";
$_select['noise_shaping'] .= "<option value=\"5\" " . (($cfgDeezer['noise_shaping'] == '5') ? "selected" : "") . ">Aggressive 5</option>\n";
$_select['noise_shaping'] .= "<option value=\"6\" " . (($cfgDeezer['noise_shaping'] == '6') ? "selected" : "") . ">Aggressive 6</option>\n";
$_select['noise_shaping'] .= "<option value=\"7\" " . (($cfgDeezer['noise_shaping'] == '7') ? "selected" : "") . ">Aggressive 7</option>\n";
$_select['email'] = $cfgDeezer['email'];
if (empty($cfgDeezer['password'])) {
	$_select['password'] = '';
	$_pwd_input_format = 'password';
} else {
	$_select['password'] = 'Password set';
	$_pwd_input_format = 'text';
}
/* TBD option
$_select['dns_options'] .= "<option value=\"default\" " . (($cfgDeezer['dns_options'] == 'default') ? "selected" : "") . ">Default</option>\n";
$_select['dns_options'] .= "<option value=\"timeout-1\" " . (($cfgDeezer['dns_options'] == 'timeout-1') ? "selected" : "") . ">Timeout-1</option>\n";
$_select['dns_options'] .= "<option value=\"no-quad-a\" " . (($cfgDeezer['dns_options'] == 'no-quad-a') ? "selected" : "") . ">No-quad-A</option>\n";
*/

waitWorker('dez_config');

$tpl = "dez-config.html";
$section = basename(__FILE__, '.php');
storeBackLink($section, $tpl);

include('header.php');
eval("echoTemplate(\"" . getTemplate("templates/$tpl") . "\");");
include('footer.php');
