<?php
/*
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
		chkValue($key, $value);
		sqlUpdate('cfg_sendspin', $dbh, $key, $value);
	}
	$notify = $_SESSION['sendspinsvc'] == '1' ?
		array('title' => NOTIFY_TITLE_INFO, 'msg' => NAME_SENDSPIN . NOTIFY_MSG_SVC_RESTARTED) :
		array('title' => '', 'msg' => '');
	submitJob('sendspincfgupdate', '', $notify['title'], $notify['msg']);
}

phpSession('close');

$result = sqlRead(table: 'cfg_sendspin', dbh: $dbh, format: 'array');
$cfgSENDSPIN = array(); 

foreach ($result as $row) {
	$cfgSENDSPIN[$row['param']] = $row['value'];
}

$_sendspin_select['last_server_url'] = $cfgSENDSPIN['last_server_url']; // Sendspin Server URL, empty for auto-detect
// $_sendspin_select['static_delay_ms'] = $cfgSENDSPIN['static_delay_ms'];
$_sendspin_select['name'] = $cfgSENDSPIN['name'];
// $_sendspin_select['client_id'] = $cfgSENDSPIN['client_id'];
$_sendspin_select['audio_device'] = $cfgSENDSPIN['audio_device'];

waitWorker('sendspin_config');

$tpl = "sen-config.html";
$section = basename(__FILE__, '.php');
storeBackLink($section, $tpl);

include('header.php');
eval("echoTemplate(\"" . getTemplate("templates/$tpl") . "\");");
include('footer.php');
