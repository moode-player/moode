#!/usr/bin/php
<?php
/*
 * SPDX-License-Identifier: GPL-3.0-or-later
 * Copyright 2014 The moOde audio player project / Tim Curtis
*/

require_once __DIR__ . '/../inc/common.php';

$option = isset($argv[1]) ? $argv[1] : '';

if ($option == '') {
	echo
"Usage: send-fecmd.php [command]
Send a command to the front-end\n";
} else {
	sendFECmd($argv[1]);
}
