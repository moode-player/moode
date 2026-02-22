#!/usr/bin/php
<?php
/*
 * SPDX-License-Identifier: GPL-3.0-or-later
 * Copyright 2014 The moOde audio player project / Tim Curtis
 * Copyright 2015 Cover art extraction routines / Andreas Goetz (cpuidle@gmx.de)
*/

require_once __DIR__ . '/../inc/common.php';
require_once __DIR__ . '/../inc/session.php';
require_once __DIR__ . '/../inc/sql.php';

session_id(phpSession('get_sessionid'));
phpSession('open');
//$scanFormats = $_SESSION['library_thmgen_scan'];
$scanFormats = 'All';
$ignoreMoodeFiles = $_SESSION['moodefiles_ignore'];
phpSession('close');

// Generate the file list
$result = shell_exec('/var/www/util/list-songfiles.sh "' . $scanFormats . '" | sort');
if (is_null($result) || substr($result, 0, 2) == 'OK') {
	echo 'libstats: no files found';
	exit(0);
}

// Generate the counts
$albumCnt = 0;
$line = strtok($result, "\n");
while ($line) {
	$fileA = explode(': ', $line, 2)[1];
	$dirA = dirname($fileA);

	$line = strtok("\n");

	$fileB = explode(': ', $line, 2)[1];
	$dirB = dirname($fileB);

	if ($dirA != $dirB) {
		++$albumCnt;
	}
}

$trackCnt = sysCmd("mpc search '(Title !=\"\")' | wc -l")[0];
if ($ignoreMoodeFiles == '1') {
	$albumCnt = $albumCnt - 2;
}

echo 'Albums:' . $albumCnt . ' Tracks:' . $trackCnt . "\n";
