#!/usr/bin/php
<?php
/**
 * moOde audio player (C) 2014 Tim Curtis
 * http://moodeaudio.org
 *
 * This Program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 3, or (at your option)
 * any later version.
 *
 * This Program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program.  If not, see <http://www.gnu.org/licenses/>.
 *
 * 2018-01-26 TC moOde 4.0
 * 2018-04-02 TC moOde 4.1 remove vol warning
 *
 */
 
require_once dirname(__FILE__) . '/inc/playerlib.php';

// initialize
if (false === ($sock = openMpdSock('localhost', 6600))) {
	$msg = 'vol: Connection to MPD failed';
	workerLog($msg);
	exit($msg . "\n");	
}

if (false === ($dbh = cfgdb_connect())) {
	workerLog('vol: Connection to sqlite failed');
	exit("error: cfgdb_connect() failed\n");	
}

if (!isset($argv[1])) {
	$result = sdbquery("select value from cfg_system where id='32'", $dbh);
	exit($result[0]['value'] . "\n");
}
	
if ($argv[1] == '-help') {
	exit("vol.php with no arguments will print the current volume level\n" .
	"vol.php restore will set alsa/mpd volume based on current knob setting\n" .
	"vol.php <level between 0-100>, mute (toggle), up <step> or dn <step>, -help\n");
}

// process volume cmds

$result = sdbquery("select param, value from cfg_system where id in ('32', '33', '36', '37', '77')", $dbh);
$array = array();
foreach ($result as $row) {
	$array[$row['param']] = $row['value'];
}
// cardnum 0 = i2s or onboard, cardnum 1 = usb 

// mute toggle
if ($argv[1] == 'mute') {
	if ($array['volmute'] == '1') {
		$result = sdbquery("update cfg_system set value='0' where id='33'", $dbh);
		$volmute = '0';
		$level = $array['volknob']; 
	}
	else {
		$result = sdbquery("update cfg_system set value='1' where id='33'", $dbh);
		$volmute = '1';
	}
}
else {
	// restore alsa/mpd volume
	if ($argv[1] == 'restore') {
		$level = $array['volknob']; 
	}
	// volume step
	elseif ($argv[1] == 'up') {
		$level = $array['volknob'] + $argv[2]; 
	}
	elseif ($argv[1] == 'dn') {
		$level = $array['volknob'] - $argv[2]; 
	}
	// volume level
	else {
		$level = $argv[1]; 
	}

	// numeric check
	if (!preg_match('/^[+-]?[0-9]+$/', $level)) {
		workerLog('vol: fail numeric check)');
		exit("Level must only contain digits 0-9\n");
	}

	// range check
	if ($level < 0) {
		$level = 0;
	}
	elseif ($level > 100) {
		$level = 100;
	}

	// update knob
	$result = sdbquery("update cfg_system set value='" . $level . "' where id='32'", $dbh);
	//workerLog('vol: result=(' . $result . ')');
}

// mute if indicated
if ($volmute == '1') {
	sendMpdCmd($sock, 'setvol 0');
	$resp = readMpdResp();
	exit();
}

// volume: update MPD volume --> MPD idle timeout --> UI updated
sendMpdCmd($sock, 'setvol ' . $level);
$resp = readMpdResp();

closeMpdSock($sock);
