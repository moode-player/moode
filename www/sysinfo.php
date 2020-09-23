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
 * 2019-11-24 TC moOde 6.4.0
 *
 */

require_once dirname(__FILE__) . '/inc/playerlib.php';

$file = '/var/local/www/sysinfo.txt';
sysCmd('/var/www/command/sysinfo.sh html > ' . $file);

$fh = fopen($file, 'r');
$text = fread($fh, filesize($file));
fclose($fh);

$tpl = 'sysinfo.html';
eval('echoTemplate("' . getTemplate("templates/$tpl") . '");');
