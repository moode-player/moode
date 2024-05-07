<?php
/**
 * SPDX-License-Identifier: GPL-3.0-or-later
 * Copyright 2014 The moOde audio player project / Tim Curtis
*/

require_once __DIR__ . '/inc/common.php';
require_once __DIR__ . '/inc/session.php';

if (file_exists(UPDATER_LOG)) {
	$result = sysCmd('cat ' . UPDATER_LOG);
	$_updater_log = sysCmd('cat /var/log/moode.log | grep "Downloading"')[0] . '<br>';
	for ($i = 0; $i < count($result); $i++) {
		$_updater_log .= $result[$i] . '<br>';
	}
	$_updater_log = empty($_updater_log) ? 'Log is empty' : $_updater_log;
} else {
	$_updater_log = 'Log file does not exist';
}

$tpl = "sys-status.html";
$section = basename(__FILE__, '.php');
storeBackLink($section, $tpl);

include('header.php');
eval("echoTemplate(\"" . getTemplate("templates/$tpl") . "\");");
include('footer.min.php');
