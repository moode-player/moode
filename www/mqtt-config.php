<?php
/*
 * SPDX-License-Identifier: GPL-3.0-or-later
 * Copyright 2014 The moOde audio player project / Tim Curtis
 * Copyright 2026 @Gjuju
*/

require_once __DIR__ . '/inc/common.php';
require_once __DIR__ . '/inc/session.php';
require_once __DIR__ . '/inc/sql.php';

$dbh = sqlConnect();
phpSession('open');

// CSRF token
$_csrf_token = htmlspecialchars($_SESSION['csrf_token'], ENT_QUOTES);
if (!empty($_POST)) {
	chkCsrfToken($_POST['csrf_token']);
}

// Clear acts as Save
if (isset($_POST['clear'])) {
	$_POST['save'] = '1';
}
if (isset($_POST['save']) && $_POST['save'] == '1') {
	foreach ($_POST['config'] as $key => $value) {
		if ($key != 'password') {
			chkValue($key, $value);
		}
		if ($key == 'password') {
			$value = secretFieldPost($key, $value);
			if ($value === null) {
				continue;
			}
		}
		sqlUpdate('cfg_mqtt', $dbh, $key, $value);
	}
	$notify = $_SESSION['mqttsvc'] == '1' ?
		array('title' => NOTIFY_TITLE_INFO, 'msg' => NAME_MQTT . NOTIFY_MSG_SVC_RESTARTED) :
		array('title' => '', 'msg' => '');
	submitJob('mqttsvc', '', $notify['title'], $notify['msg']);
}

phpSession('close');

$result = sqlRead('cfg_mqtt', $dbh);
$cfgMQTT = array();
foreach ($result as $row) {
	$cfgMQTT[$row['param']] = $row['value'];
}

// Broker
$_select['host'] = $cfgMQTT['host'];
$_select['port'] = $cfgMQTT['port'];
$_select['username'] = $cfgMQTT['username'];
list($_placeholder['password'], $_clear_hide['password']) = secretFieldView(!empty($cfgMQTT['password']));
$_select['tls'] .= "<option value=\"yes\" " . (($cfgMQTT['tls'] == 'yes') ? "selected" : "") . ">Yes</option>\n";
$_select['tls'] .= "<option value=\"no\" " . (($cfgMQTT['tls'] == 'no') ? "selected" : "") . ">No (Default)</option>\n";
$_select['tls_server_name'] = $cfgMQTT['tls_server_name'];

// Identity
$_select['instance'] = $cfgMQTT['instance'];
$_select['friendly_name'] = $cfgMQTT['friendly_name'];

// Advanced
$_select['topic_prefix'] = $cfgMQTT['topic_prefix'];
$_select['discovery_prefix'] = $cfgMQTT['discovery_prefix'];
$_select['poll_interval'] = $cfgMQTT['poll_interval'];
$_select['audio_off_delay'] = $cfgMQTT['audio_off_delay'];
$_select['volume_step'] = $cfgMQTT['volume_step'];

waitWorker('mqtt-config');

$tpl = "mqtt-config.html";
$section = basename(__FILE__, '.php');
storeBackLink($section, $tpl);

include('header.php');
eval("echoTemplate(\"" . getTemplate("templates/$tpl") . "\");");
include('footer.php');
