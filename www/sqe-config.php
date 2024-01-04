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

require_once __DIR__ . '/inc/alsa.php';
require_once __DIR__ . '/inc/common.php';
require_once __DIR__ . '/inc/session.php';
require_once __DIR__ . '/inc/sql.php';

$dbh = sqlConnect();
phpSession('open');

if (isset($_POST['save']) && $_POST['save'] == '1') {
	foreach ($_POST['config'] as $key => $value) {
		sqlUpdate('cfg_sl', $dbh, $key, SQLite3::escapeString($value));
	}

	submitJob('slcfgupdate', '', 'Settings updated', ($_SESSION['slsvc'] == '1' ? 'Squeezelite restarted' : ''));
}

phpSession('close');

$result = sqlRead('cfg_sl', $dbh);
$cfgSL = array();

foreach ($result as $row) {
	$cfgSL[$row['param']] = $row['value'];
}

$_sl_select['renderer_name'] = $cfgSL['PLAYERNAME'];
$_sl_select['alsa_params'] = $cfgSL['ALSAPARAMS'];
$_sl_select['output_buffers'] = $cfgSL['OUTPUTBUFFERS'];
$_sl_select['task_priority'] = $cfgSL['TASKPRIORITY'];
$_sl_select['audio_codecs'] = $cfgSL['CODECS'];
$_sl_select['other_options'] = htmlentities($cfgSL['OTHEROPTIONS']);

waitWorker('sqe_config');

$tpl = "sqe-config.html";
$section = basename(__FILE__, '.php');
storeBackLink($section, $tpl);

include('header.php');
eval("echoTemplate(\"" . getTemplate("templates/$tpl") . "\");");
include('footer.php');
