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
 * 2020-MM-DD TC moOde 6.7.1
 *
 */

require_once dirname(__FILE__) . '/inc/playerlib.php';

playerSession('open', '' ,'');
$dbh = cfgdb_connect();

// apply setting changes
if (isset($_POST['save']) && $_POST['save'] == '1') {
	foreach ($_POST['config'] as $key => $value) {
		cfgdb_update('cfg_spotify', $dbh, $key, $value);
	}

	// restart if indicated
	submitJob('spotifysvc', '', 'Changes saved', ($_SESSION['spotifysvc'] == '1' ? 'Spotify receiver restarted' : ''));
}

session_write_close();

// load settings
$result = cfgdb_read('cfg_spotify', $dbh);
$cfg_spotify = array();

foreach ($result as $row) {
	$cfg_spotify[$row['param']] = $row['value'];
}

// bit rate
$_select['bitrate'] .= "<option value=\"96\" " . (($cfg_spotify['bitrate'] == '96') ? "selected" : "") . ">96K</option>\n";
$_select['bitrate'] .= "<option value=\"160\" " . (($cfg_spotify['bitrate'] == '160') ? "selected" : "") . ">160K</option>\n";
$_select['bitrate'] .= "<option value=\"320\" " . (($cfg_spotify['bitrate'] == '320') ? "selected" : "") . ">320K</option>\n";
// initial volume
$_select['initial_volume'] = $cfg_spotify['initial_volume'];
// volume curve
$_select['volume_curve'] .= "<option value=\"Linear\" " . (($cfg_spotify['volume_curve'] == 'Linear') ? "selected" : "") . ">Linear</option>\n";
$_select['volume_curve'] .= "<option value=\"Logarithmic\" " . (($cfg_spotify['volume_curve'] == 'Logarithmic') ? "selected" : "") . ">Logarithmic</option>\n";
// volume normalization
$_select['volume_normalization'] .= "<option value=\"Yes\" " . (($cfg_spotify['volume_normalization'] == 'Yes') ? "selected" : "") . ">Yes</option>\n";
$_select['volume_normalization'] .= "<option value=\"No\" " . (($cfg_spotify['volume_normalization'] == 'No') ? "selected" : "") . ">No</option>\n";
// ormalization pregain
$_select['normalization_pregain'] = $cfg_spotify['normalization_pregain'];
// Autoplay
$_select['autoplay'] .= "<option value=\"Yes\" " . (($cfg_spotify['autoplay'] == 'Yes') ? "selected" : "") . ">Yes</option>\n";
$_select['autoplay'] .= "<option value=\"No\" " . (($cfg_spotify['autoplay'] == 'No') ? "selected" : "") . ">No</option>\n";

waitWorker(1, 'spo_config');

$tpl = "spo-config.html";
$section = basename(__FILE__, '.php');
storeBackLink($section, $tpl);

include('header.php');
eval("echoTemplate(\"" . getTemplate("templates/$tpl") . "\");");
include('footer.min.php');
