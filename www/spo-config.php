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

require_once dirname(__FILE__) . '/inc/playerlib.php';

playerSession('open', '' ,'');
$dbh = cfgdb_connect();

// Apply setting changes
if (isset($_POST['save']) && $_POST['save'] == '1') {
	foreach ($_POST['config'] as $key => $value) {
		cfgdb_update('cfg_spotify', $dbh, $key, $value);
	}

	// Restart if indicated
	submitJob('spotifysvc', '', 'Changes saved', ($_SESSION['spotifysvc'] == '1' ? 'Spotify receiver restarted' : ''));
}

session_write_close();

// Load settings
$result = cfgdb_read('cfg_spotify', $dbh);
$cfg_spotify = array();

foreach ($result as $row) {
	$cfg_spotify[$row['param']] = $row['value'];
}

// Bitrate
$_select['bitrate'] .= "<option value=\"96\" " . (($cfg_spotify['bitrate'] == '96') ? "selected" : "") . ">96</option>\n";
$_select['bitrate'] .= "<option value=\"160\" " . (($cfg_spotify['bitrate'] == '160') ? "selected" : "") . ">160 (Default)</option>\n";
$_select['bitrate'] .= "<option value=\"320\" " . (($cfg_spotify['bitrate'] == '320') ? "selected" : "") . ">320</option>\n";
// Format
$_select['format'] .= "<option value=\"S16\" " . (($cfg_spotify['format'] == 'S16') ? "selected" : "") . ">S16 (Default)</option>\n";
$_select['format'] .= "<option value=\"S24\" " . (($cfg_spotify['format'] == 'S24') ? "selected" : "") . ">S24</option>\n";
$_select['format'] .= "<option value=\"S24_3\" " . (($cfg_spotify['format'] == 'S24_3') ? "selected" : "") . ">S24_3</option>\n";
$_select['format'] .= "<option value=\"S32\" " . (($cfg_spotify['format'] == 'S32') ? "selected" : "") . ">S32</option>\n";
$_select['format'] .= "<option value=\"F32\" " . (($cfg_spotify['format'] == 'F32') ? "selected" : "") . ">F32</option>\n";
$_select['format'] .= "<option value=\"F64\" " . (($cfg_spotify['format'] == 'F64') ? "selected" : "") . ">F64</option>\n";
// Dithering options
$_select['dither'] .= "<option value=\"\" " . (($cfg_spotify['dither'] == '') ? "selected" : "") . ">Automatic (Default)</option>\n";
$_select['dither'] .= "<option value=\"none\" " . (($cfg_spotify['dither'] == 'none') ? "selected" : "") . ">None</option>\n";
$_select['dither'] .= "<option value=\"gpdf\" " . (($cfg_spotify['dither'] == 'gpdf') ? "selected" : "") . ">Gaussian</option>\n";
$_select['dither'] .= "<option value=\"tpdf\" " . (($cfg_spotify['dither'] == 'tpdf') ? "selected" : "") . ">Triangular</option>\n";
$_select['dither'] .= "<option value=\"tpdf_hp\" " . (($cfg_spotify['dither'] == 'tpdf_hp') ? "selected" : "") . ">Triangular (High Pass)</option>\n";
// Initial volume
$_select['initial_volume'] = $cfg_spotify['initial_volume'];
// Volume curve
$_select['volume_curve'] .= "<option value=\"log\" " . (($cfg_spotify['volume_curve'] == 'log') ? "selected" : "") . ">Logarithmic (Default)</option>\n";
$_select['volume_curve'] .= "<option value=\"cubic\" " . (($cfg_spotify['volume_curve'] == 'cubic') ? "selected" : "") . ">Cubic</option>\n";
$_select['volume_curve'] .= "<option value=\"linear\" " . (($cfg_spotify['volume_curve'] == 'linear') ? "selected" : "") . ">Linear</option>\n";
$_select['volume_curve'] .= "<option value=\"fixed\" " . (($cfg_spotify['volume_curve'] == 'fixed') ? "selected" : "") . ">Fixed</option>\n";
// Volume range
$_select['volume_range'] = $cfg_spotify['volume_range'];
// Volume normalization options
$_select['volume_normalization'] .= "<option value=\"Yes\" " . (($cfg_spotify['volume_normalization'] == 'Yes') ? "selected" : "") . ">Yes</option>\n";
$_select['volume_normalization'] .= "<option value=\"No\" " . (($cfg_spotify['volume_normalization'] == 'No') ? "selected" : "") . ">No</option>\n";
$_select['normalization_method'] .= "<option value=\"dynamic\" " . (($cfg_spotify['normalization_method'] == 'dynamic') ? "selected" : "") . ">Dynamic (Default)</option>\n";
$_select['normalization_method'] .= "<option value=\"basic\" " . (($cfg_spotify['normalization_method'] == 'basic') ? "selected" : "") . ">Basic</option>\n";
$_select['normalization_gain_type'] .= "<option value=\"auto\" " . (($cfg_spotify['normalization_gain_type'] == 'auto') ? "selected" : "") . ">Automatic (Default)</option>\n";
$_select['normalization_gain_type'] .= "<option value=\"album\" " . (($cfg_spotify['normalization_gain_type'] == 'album') ? "selected" : "") . ">Album</option>\n";
$_select['normalization_gain_type'] .= "<option value=\"track\" " . (($cfg_spotify['normalization_gain_type'] == 'track') ? "selected" : "") . ">Track</option>\n";
$_select['normalization_pregain'] = $cfg_spotify['normalization_pregain'];
$_select['normalization_threshold'] = $cfg_spotify['normalization_threshold'];
$_select['normalization_attack'] = $cfg_spotify['normalization_attack'];
$_select['normalization_release'] = $cfg_spotify['normalization_release'];
$_select['normalization_knee'] = $cfg_spotify['normalization_knee'];
// Autoplay after playlist completes
$_select['autoplay'] .= "<option value=\"Yes\" " . (($cfg_spotify['autoplay'] == 'Yes') ? "selected" : "") . ">Yes</option>\n";
$_select['autoplay'] .= "<option value=\"No\" " . (($cfg_spotify['autoplay'] == 'No') ? "selected" : "") . ">No</option>\n";

waitWorker(1, 'spo_config');

$tpl = "spo-config.html";
$section = basename(__FILE__, '.php');
storeBackLink($section, $tpl);

include('header.php');
eval("echoTemplate(\"" . getTemplate("templates/$tpl") . "\");");
include('footer.php');
