#!/usr/bin/php
<?php
/*
 * SPDX-License-Identifier: GPL-3.0-or-later
 * Copyright 2014 The moOde audio player project / Tim Curtis
 * Copyright 2015 Cover art extraction routines / Andreas Goetz (cpuidle@gmx.de)
*/

require_once __DIR__ . '/../inc/common.php';
require_once __DIR__ . '/../inc/mpd.php';
require_once __DIR__ . '/../inc/music-library.php';

// Connect to MPD
if (false !== ($sock = openMpdSock('localhost', 6600))) {
	$stats = getLibraryStats($sock);
	echo $stats . "\n";
	closeMpdSock($sock);
} else {
	echo 'libstats.php: Connection to MPD failed';
}
