#!/usr/bin/php
<?php
/**
 * SPDX-License-Identifier: GPL-3.0-or-later
 * Copyright 2014 The moOde audio player project / Tim Curtis
*/

require_once __DIR__ . '/inc/common.php';
require_once __DIR__ . '/inc/session.php';

$option = isset($argv[1]) ? $argv[1] : '';

switch ($option) {
	case '--help':
	echo
"Usage: Library update\n";
		break;
	default:
		break;
}

session_id(phpSession('get_sessionid'));
phpSession('open');
submitJob('update_library');
phpSession('close');
