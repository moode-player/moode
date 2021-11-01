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
 */

set_include_path('/var/www/inc');
require_once 'playerlib.php';

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
