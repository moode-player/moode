#!/usr/bin/php
<?php
/**
 * SPDX-License-Identifier: GPL-3.0-or-later
 * Copyright 2014 The moOde audio player project / Tim Curtis
 * Copyright 2020 @bitlab (@bitkeeper Git)
*/

require_once __DIR__ . '/../inc/common.php';
require_once __DIR__ . '/../inc/session.php';
require_once __DIR__ . '/../inc/sql.php';
require_once __DIR__ . '/../inc/autocfg.php';

if (file_exists('/boot/moodecfg.ini')) {
	session_id(phpSession('get_sessionid'));
	phpSession('open');
	sysCmd('truncate ' . AUTOCFG_LOG . ' --size 0');
	autoConfig('/boot/moodecfg.ini');
	sysCmd('sync');
	phpSession('close');
} else {
	autoCfgLog('autocfg-import: No settings file "/boot/moodecfg.ini" to import\n');
	print("No settings file \"/boot/moodecfg.ini\" to import\n");
}

?>
