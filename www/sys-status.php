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

require_once __DIR__ . '/inc/common.php';
require_once __DIR__ . '/inc/session.php';

if (file_exists(UPDATER_LOG)) {
	$result = sysCmd('cat ' . UPDATER_LOG);
	$_updater_log = sysCmd('cat /var/log/moode.log | grep "Downloading"')[0] . '<br>';
	for ($i = 0; $i < count($result); $i++) {
		$_updater_log .= $result[$i] . '<br>';
	}
	$_updater_log = empty($_updater_log) ? 'Log is empty' : $_updater_log;
} else {
	$_updater_log = 'Log file does not exist';
}

$tpl = "sys-status.html";
$section = basename(__FILE__, '.php');
storeBackLink($section, $tpl);

include('header.php');
eval("echoTemplate(\"" . getTemplate("templates/$tpl") . "\");");
include('footer.min.php');
