#!/usr/bin/php
<?php
/*
 * SPDX-License-Identifier: GPL-3.0-or-later
 * Copyright 2014 The moOde audio player project / Tim Curtis
*/

require_once __DIR__ . '/../inc/common.php';
require_once __DIR__ . '/../inc/session.php';
require_once __DIR__ . '/../inc/sql.php';

// Called from udev: usb-audio-hotplug.php add|remove cardnum
$action = isset($argv[1]) ? $argv[1] : '';
$cardNum = isset($argv[2]) ? $argv[2] : '';
if (($action != 'add' && $action != 'remove') || !ctype_digit($cardNum)) {
	echo "Usage: usb-audio-hotplug.php add|remove cardnum\n";
	exit(1);
}

// Devices present at startup are handled by the worker
if (sqlQuery("SELECT value FROM cfg_system WHERE param='wrkready'", sqlConnect())[0]['value'] != '1') {
	exit(0);
}

session_id(phpSession('get_sessionid'));
for ($i = 0; $i < 10; $i++) {
	phpSession('open');
	if ($_SESSION['w_lock'] != 1 && $_SESSION['w_queue'] == '') {
		submitJob('usb_audio_hotplug', $action . ',' . $cardNum);
		phpSession('close');
		exit(0);
	}
	phpSession('close');
	sleep(1);
}
