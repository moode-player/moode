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
	// Preprocess data for certain commands
	$cmd = (str_contains($argv[1], 'update_deezmeta') || str_contains($argv[1], 'update_spotmeta')) ?
		htmlspecialchars($argv[1], ENT_NOQUOTES) : $argv[1];

	// Send to front-end
	sendFECmd($cmd);
}
