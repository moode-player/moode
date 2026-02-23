#!/usr/bin/php
<?php
/*
 * SPDX-License-Identifier: GPL-3.0-or-later
 * Copyright 2014 The moOde audio player project / Tim Curtis
 * Copyright 2015 Cover art extraction routines / Andreas Goetz (cpuidle@gmx.de)
*/

require_once __DIR__ . '/../inc/common.php';
require_once __DIR__ . '/../inc/mpd.php';

// Connect to MPD
if (false === ($sock = openMpdSock('localhost', 6600))) {
	echo 'libstats.php: Connection to MPD failed';
	exit;
}

// Generate the file list
$fileList = sysCmd("mpc search '(Title !=\"\")'");

// Scan the list and generate counts
$trackCount = 0;
$albumCount = 0;
$albumKeys = array();
foreach ($fileList as $file) {
	// Tracks
	$trackCount++;
	// Albums
	sendMpdCmd($sock, 'lsinfo "' . $file . '"');
	$tags = parseLsinfoAsArray(readMpdResp($sock));

	$album = $tags['Album'] ? $tags['Album'] : 'Unknown Album';
	$albumartist = $tags['AlbumArtist'] ? $tags['AlbumArtist'] :
		($tags['Artist'] ? (count($tags['Artist']) == 1 ? $tags['Artist'][0] :
		'Unknown AlbumArtist') : 'Unknown AlbumArtist');

	// Create unique album key
	$albumKey = $album . '@' . $albumartist;
	if  (!in_array($albumKey, $albumKeys)) {
		array_push($albumKeys, $albumKey);
	}
}

$albumCount = count($albumKeys);

// Print counts
echo 'Albums:' . $albumCount . ' Tracks:' . $trackCount . "\n";

closeMpdSock($sock);

function parseLsinfoAsArray($resp) {
	$array = array();
	$array['Genre'] = array();
	$array['Artist'] = array();

	$line = strtok($resp, "\n");
	while ($line) {
		list($param, $value) = explode(': ', $line, 2);

		switch ($param) {
			case 'Genre':
				array_push($array['Genre'], $value);
				break;
			case 'Artist':
			case 'Performer':
			case 'Conductor':
			case 'Composer':
				array_push($array['Artist'], $value);
				break;
			default:
				$array[$param] = $value;
				break;
		}

		$line = strtok("\n");
	}

	return $array;
}
