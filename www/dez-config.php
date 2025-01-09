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
		sqlUpdate('cfg_deezer', $dbh, $key, $value);
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
$_select['email'] = $cfgDeezer['email'];
$_select['password'] = $cfgDeezer['password'];
$_show_hide_password_icon_hide = empty($cfgDeezer['password']) ? '' : 'hide';

waitWorker('dez_config');

$tpl = "dez-config.html";
$section = basename(__FILE__, '.php');
storeBackLink($section, $tpl);

include('header.php');
eval("echoTemplate(\"" . getTemplate("templates/$tpl") . "\");");
include('footer.php');
