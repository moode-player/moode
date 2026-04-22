<?php
/*
 * SPDX-License-Identifier: GPL-3.0-or-later
 * Copyright 2026 The moOde audio player project / Tim Curtis
*/

require_once __DIR__ . '/inc/common.php';
require_once __DIR__ . '/inc/session.php';

if (file_exists(PLUGIN_LOG)) {
	$result = sysCmd('cat ' . PLUGIN_LOG);
	$_plugin_log = sysCmd('cat /var/log/moode.log | grep "Downloading"')[0] . '<br>';
	for ($i = 0; $i < count($result); $i++) {
		$_plugin_log .= $result[$i] . '<br>';
	}
	$_plugin_log = empty($_plugin_log) ? 'Log is empty' : $_plugin_log;
} else {
	$_plugin_log = 'Log file does not exist';
}

$tpl = "ren-status.html";
$section = basename(__FILE__, '.php');
storeBackLink($section, $tpl);

include('header.php');
eval("echoTemplate(\"" . getTemplate("templates/$tpl") . "\");");
include('footer.min.php');
