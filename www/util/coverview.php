#!/usr/bin/php
<?php
/*
 * SPDX-License-Identifier: GPL-3.0-or-later
 * Copyright 2014 The moOde audio player project / Tim Curtis
*/

require_once __DIR__ . '/../inc/common.php';

$option = isset($argv[1]) ? $argv[1] : '';

switch ($option) {
	case '--help':
	echo
"Usage: coverview [OPTION]
CoverView screen saver

With no OPTION print the help text and exit.

 -on\t\tShow CoverView screen saver
 -off\t\tHide CoverView screen saver
 --help\t\tPrint this help text\n";
		break;
	case '-on':
		sendEngCmd('scnactive1');
		echo "CoverView on\n";
		break;
	case '-off':
		sendEngCmd('scnactive0');
		echo "CoverView off\n";
		break;
	default:
		echo "Missing option [-on | -off | --help]\n";
		break;
}
