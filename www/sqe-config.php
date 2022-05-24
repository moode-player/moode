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
require_once __DIR__ . '/inc/sql.php';

$dbh = sqlConnect();
phpSession('open');

// apply setting changes to /etc/squeezelite.conf
if (isset($_POST['save']) && $_POST['save'] == '1') {
	foreach ($_POST['config'] as $key => $value) {
		if ($key == 'AUDIODEVICE') {
			$value = $_SESSION['cardnum'];
		}
		sqlUpdate('cfg_sl', $dbh, $key, SQLite3::escapeString($value));
	}

	// update conf file
	submitJob('slcfgupdate', '', 'Changes saved', ($_SESSION['slsvc'] == '1' ? 'Squeezelite restarted' : ''));
}

phpSession('close');

// load settings
$result = sqlRead('cfg_sl', $dbh);
$cfg_sl = array();

foreach ($result as $row) {
	$cfg_sl[$row['param']] = $row['value'];
}

// get device names
$dev = getAlsaDeviceNames();

// renderer name
$_sl_select['renderer_name'] = $cfg_sl['PLAYERNAME'];

// alsa params
$_sl_select['alsa_params'] = $cfg_sl['ALSAPARAMS'];

// output buffers
$_sl_select['output_buffers'] = $cfg_sl['OUTPUTBUFFERS'];

// task priority
$_sl_select['task_priority'] = $cfg_sl['TASKPRIORITY'];

// audio codecs
$_sl_select['audio_codecs'] = $cfg_sl['CODECS'];

// other options
$_sl_select['other_options'] = htmlentities($cfg_sl['OTHEROPTIONS']);

waitWorker(1, 'sqe_config');

$tpl = "sqe-config.html";
$section = basename(__FILE__, '.php');
storeBackLink($section, $tpl);

include('header.php');
eval("echoTemplate(\"" . getTemplate("templates/$tpl") . "\");");
include('footer.php');
