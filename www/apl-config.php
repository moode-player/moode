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

if (isset($_POST['save']) && $_POST['save'] == '1') {
	foreach ($_POST['config'] as $key => $value) {
		sqlUpdate('cfg_airplay', $dbh, $key, $value);

		if ($value != 'deprecated') {
			$value = is_numeric($value) ? $value : '"' . $value . '"';
			sysCmd("sed -i '/" . $key . ' =' . '/c\\' . $key . ' = ' . $value . ";' /etc/shairport-sync.conf"); // 3.3.y
		}
	}

	submitJob('airplaysvc', '', 'Setings updated', ($_SESSION['airplaysvc'] == '1' ? 'AirPlay restarted' : ''));
}

phpSession('close');

$result = sqlRead('cfg_airplay', $dbh);
$cfgAirplay = array();

foreach ($result as $row) {
	$cfgAirplay[$row['param']] = $row['value'];
}


$_select['interpolation'] .= "<option value=\"basic\" " . (($cfgAirplay['interpolation'] == 'basic') ? "selected" : "") . ">Basic</option>\n";
$_select['interpolation'] .= "<option value=\"soxr\" " . (($cfgAirplay['interpolation'] == 'soxr') ? "selected" : "") . ">SoX</option>\n";

$_select['output_format'] .= "<option value=\"S16\" " . (($cfgAirplay['output_format'] == 'S16') ? "selected" : "") . ">16 bit</option>\n";
$_select['output_format'] .= "<option value=\"S24\" " . (($cfgAirplay['output_format'] == 'S24') ? "selected" : "") . ">24 bit</option>\n";
$_select['output_format'] .= "<option value=\"S24_3LE\" " . (($cfgAirplay['output_format'] == 'S24_3LE') ? "selected" : "") . ">24 bit 3LE</option>\n";
$_select['output_format'] .= "<option value=\"S24_3BE\" " . (($cfgAirplay['output_format'] == 'S24_3BE') ? "selected" : "") . ">24 bit 3BE</option>\n";
$_select['output_format'] .= "<option value=\"S32\" " . (($cfgAirplay['output_format'] == 'S32') ? "selected" : "") . ">32 bit</option>\n";

$_select['output_rate'] .= "<option value=\"44100\" " . (($cfgAirplay['output_rate'] == '44100') ? "selected" : "") . ">44.1 kHz</option>\n";
$_select['output_rate'] .= "<option value=\"88200\" " . (($cfgAirplay['output_rate'] == '88200') ? "selected" : "") . ">88.2 kHz</option>\n";
$_select['output_rate'] .= "<option value=\"176400\" " . (($cfgAirplay['output_rate'] == '176400') ? "selected" : "") . ">176.4 kHz</option>\n";
$_select['output_rate'] .= "<option value=\"352800\" " . (($cfgAirplay['output_rate'] == '352800') ? "selected" : "") . ">352.8 kHz</option>\n";

$_select['allow_session_interruption'] .= "<option value=\"yes\" " . (($cfgAirplay['allow_session_interruption'] == 'yes') ? "selected" : "") . ">Yes</option>\n";
$_select['allow_session_interruption'] .= "<option value=\"no\" " . (($cfgAirplay['allow_session_interruption'] == 'no') ? "selected" : "") . ">No</option>\n";

$_select['session_timeout'] = $cfgAirplay['session_timeout'];
$_select['audio_backend_latency_offset_in_seconds'] = $cfgAirplay['audio_backend_latency_offset_in_seconds'];
$_select['audio_backend_buffer_desired_length_in_seconds'] = $cfgAirplay['audio_backend_buffer_desired_length_in_seconds'];

waitWorker('apl-config');

$tpl = "apl-config.html";
$section = basename(__FILE__, '.php');
storeBackLink($section, $tpl);

include('header.php');
eval("echoTemplate(\"" . getTemplate("templates/$tpl") . "\");");
include('footer.php');
