<?php
/**
 * SPDX-License-Identifier: GPL-3.0-or-later
 * Copyright 2014 The moOde audio player project / Tim Curtis
*/

require_once __DIR__ . '/inc/alsa.php';
require_once __DIR__ . '/inc/common.php';
require_once __DIR__ . '/inc/session.php';
require_once __DIR__ . '/inc/sql.php';

$dbh = sqlConnect();
phpSession('open');

if (isset($_POST['save']) && $_POST['save'] == '1') {
	foreach ($_POST['config'] as $key => $value) {
		sqlUpdate('cfg_sl', $dbh, $key, SQLite3::escapeString($value));
	}
	$notify = $_SESSION['slsvc'] == '1' ?
		array('title' => NOTIFY_TITLE_INFO, 'msg' => NAME_SQUEEZELITE . NOTIFY_MSG_SVC_RESTARTED) :
		array('title' => '', 'msg' => '');
	submitJob('slcfgupdate', '', $notify['title'], $notify['msg']);
}

phpSession('close');

$result = sqlRead('cfg_sl', $dbh);
$cfgSL = array();

foreach ($result as $row) {
	$cfgSL[$row['param']] = $row['value'];
}

$_sl_select['renderer_name'] = $cfgSL['PLAYERNAME'];
$_sl_select['alsa_params'] = $cfgSL['ALSAPARAMS'];
$_sl_select['output_buffers'] = $cfgSL['OUTPUTBUFFERS'];
$_sl_select['task_priority'] = $cfgSL['TASKPRIORITY'];
$_sl_select['audio_codecs'] = $cfgSL['CODECS'];
$_sl_select['other_options'] = htmlentities($cfgSL['OTHEROPTIONS']);

waitWorker('sqe_config');

$tpl = "sqe-config.html";
$section = basename(__FILE__, '.php');
storeBackLink($section, $tpl);

include('header.php');
eval("echoTemplate(\"" . getTemplate("templates/$tpl") . "\");");
include('footer.php');
