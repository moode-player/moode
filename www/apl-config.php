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
 * 2018-04-02 TC moOde 4.1 remove sps metadata
 *
 */

require_once dirname(__FILE__) . '/inc/playerlib.php';

playerSession('open', '' ,''); 
$dbh = cfgdb_connect();

// apply setting changes
if (isset($_POST['apply']) && $_POST['apply'] == '1') {
	foreach ($_POST['config'] as $key => $value) {
		cfgdb_update('cfg_airplay', $dbh, $key, $value);

		if ($key == 'airplayvol' || $key == 'rsmaftersps') {
			playerSession('write', $key, $value);
		}
		else {
			$value = is_numeric($value) ? $value : '"' . $value . '"';
			sysCmd("sed -i '/" . $key . ' =' . '/c\\' . $key . ' = ' . $value . ";' /usr/local/etc/shairport-sync.conf");
		}
	}

	// restart if indicated
	submitJob('airplaysvc', '', 'Settings updated', ($_SESSION['airplaysvc'] == '1' ? 'Airplay receiver restarted' : ''));
}
	
session_write_close();

// load settings
$result = cfgdb_read('cfg_airplay', $dbh);
$cfg_airplay = array();

foreach ($result as $row) {
	$cfg_airplay[$row['param']] = $row['value'];
}

// volume mixer
$_select['airplayvol'] .= "<option value=\"auto\" " . (($_SESSION['airplayvol'] == 'auto') ? "selected" : "") . ">Auto</option>\n";
$_select['airplayvol'] .= "<option value=\"software\" " . (($_SESSION['airplayvol'] == 'software') ? "selected" : "") . ">Software</option>\n";
// resume MPD after sps ends
$_select['rsmaftersps'] .= "<option value=\"Yes\" " . (($_SESSION['rsmaftersps'] == 'Yes') ? "selected" : "") . ">Yes</option>\n";
$_select['rsmaftersps'] .= "<option value=\"No\" " . (($_SESSION['rsmaftersps'] == 'No') ? "selected" : "") . ">No</option>\n";
// output bit depth
$_select['output_format'] .= "<option value=\"S16\" " . (($cfg_airplay['output_format'] == 'S16') ? "selected" : "") . ">16 bit</option>\n";
$_select['output_format'] .= "<option value=\"S24\" " . (($cfg_airplay['output_format'] == 'S24') ? "selected" : "") . ">24 bit</option>\n";
$_select['output_format'] .= "<option value=\"S24_3LE\" " . (($cfg_airplay['output_format'] == 'S24_3LE') ? "selected" : "") . ">24 bit 3LE</option>\n";
$_select['output_format'] .= "<option value=\"S24_3BE\" " . (($cfg_airplay['output_format'] == 'S24_3BE') ? "selected" : "") . ">24 bit 3BE</option>\n";
$_select['output_format'] .= "<option value=\"S32\" " . (($cfg_airplay['output_format'] == 'S32') ? "selected" : "") . ">32 bit</option>\n";
// output rate
$_select['output_rate'] .= "<option value=\"44100\" " . (($cfg_airplay['output_rate'] == '44100') ? "selected" : "") . ">44.1 kHz</option>\n";
$_select['output_rate'] .= "<option value=\"88200\" " . (($cfg_airplay['output_rate'] == '88200') ? "selected" : "") . ">88.2 kHz</option>\n";
$_select['output_rate'] .= "<option value=\"176400\" " . (($cfg_airplay['output_rate'] == '176400') ? "selected" : "") . ">176.4 kHz</option>\n";
$_select['output_rate'] .= "<option value=\"352800\" " . (($cfg_airplay['output_rate'] == '352800') ? "selected" : "") . ">352.8 kHz</option>\n";
// session interruption
$_select['allow_session_interruption'] .= "<option value=\"yes\" " . (($cfg_airplay['allow_session_interruption'] == 'yes') ? "selected" : "") . ">Yes</option>\n";
$_select['allow_session_interruption'] .= "<option value=\"no\" " . (($cfg_airplay['allow_session_interruption'] == 'no') ? "selected" : "") . ">No</option>\n";
// session timeout
$_select['session_timeout'] = $cfg_airplay['session_timeout'];
// audio bacnend latency offset (secs)
$_select['audio_backend_latency_offset_in_seconds'] = $cfg_airplay['audio_backend_latency_offset_in_seconds'];
// audio buffer length (secs)
$_select['audio_backend_buffer_desired_length_in_seconds'] = $cfg_airplay['audio_backend_buffer_desired_length_in_seconds'];

$section = basename(__FILE__, '.php');
$tpl = "apl-config.html";
include('/var/local/www/header.php'); 
waitWorker(1);
eval("echoTemplate(\"" . getTemplate("templates/$tpl") . "\");");
include('footer.php');
