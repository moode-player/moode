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
 * 2019-04-12 TC moOde 5.0
 *
 */

set_include_path('/var/www/inc');
require_once 'playerlib.php';

// clear active state
session_id(playerSession('getsessionid'));
session_start();
playerSession('write', 'spotactive', '0');
session_write_close();

// dismiss active screen
sendEngCmd('spotactive0');
$GLOBALS['spotactive'] = '0';

// restore MPD volume and start librespot
sysCmd('/var/www/vol.sh -restore');
startSpotify();
