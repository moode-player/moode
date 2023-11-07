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
		sqlUpdate('cfg_spotify', $dbh, $key, $value);
	}

	submitJob('spotifysvc', '', 'Settings updated', ($_SESSION['spotifysvc'] == '1' ? 'Spotify receiver restarted' : ''));
}

phpSession('close');

$result = sqlRead('cfg_spotify', $dbh);
$cfgSpotify = array();

foreach ($result as $row) {
	$cfgSpotify[$row['param']] = $row['value'];
}

$_select['bitrate'] .= "<option value=\"96\" " . (($cfgSpotify['bitrate'] == '96') ? "selected" : "") . ">96</option>\n";
$_select['bitrate'] .= "<option value=\"160\" " . (($cfgSpotify['bitrate'] == '160') ? "selected" : "") . ">160 (Default)</option>\n";
$_select['bitrate'] .= "<option value=\"320\" " . (($cfgSpotify['bitrate'] == '320') ? "selected" : "") . ">320</option>\n";

$_select['format'] .= "<option value=\"S16\" " . (($cfgSpotify['format'] == 'S16') ? "selected" : "") . ">S16 (Default)</option>\n";
$_select['format'] .= "<option value=\"S24\" " . (($cfgSpotify['format'] == 'S24') ? "selected" : "") . ">S24</option>\n";
$_select['format'] .= "<option value=\"S24_3\" " . (($cfgSpotify['format'] == 'S24_3') ? "selected" : "") . ">S24_3</option>\n";
$_select['format'] .= "<option value=\"S32\" " . (($cfgSpotify['format'] == 'S32') ? "selected" : "") . ">S32</option>\n";
$_select['format'] .= "<option value=\"F32\" " . (($cfgSpotify['format'] == 'F32') ? "selected" : "") . ">F32</option>\n";
$_select['format'] .= "<option value=\"F64\" " . (($cfgSpotify['format'] == 'F64') ? "selected" : "") . ">F64</option>\n";

$_select['dither'] .= "<option value=\"\" " . (($cfgSpotify['dither'] == '') ? "selected" : "") . ">Automatic (Default)</option>\n";
$_select['dither'] .= "<option value=\"none\" " . (($cfgSpotify['dither'] == 'none') ? "selected" : "") . ">None</option>\n";
$_select['dither'] .= "<option value=\"gpdf\" " . (($cfgSpotify['dither'] == 'gpdf') ? "selected" : "") . ">Gaussian</option>\n";
$_select['dither'] .= "<option value=\"tpdf\" " . (($cfgSpotify['dither'] == 'tpdf') ? "selected" : "") . ">Triangular</option>\n";
$_select['dither'] .= "<option value=\"tpdf_hp\" " . (($cfgSpotify['dither'] == 'tpdf_hp') ? "selected" : "") . ">Triangular (High Pass)</option>\n";

$_select['initial_volume'] = $cfgSpotify['initial_volume'];

$_select['volume_curve'] .= "<option value=\"log\" " . (($cfgSpotify['volume_curve'] == 'log') ? "selected" : "") . ">Logarithmic (Default)</option>\n";
$_select['volume_curve'] .= "<option value=\"cubic\" " . (($cfgSpotify['volume_curve'] == 'cubic') ? "selected" : "") . ">Cubic</option>\n";
$_select['volume_curve'] .= "<option value=\"linear\" " . (($cfgSpotify['volume_curve'] == 'linear') ? "selected" : "") . ">Linear</option>\n";
$_select['volume_curve'] .= "<option value=\"fixed\" " . (($cfgSpotify['volume_curve'] == 'fixed') ? "selected" : "") . ">Fixed</option>\n";

$_select['volume_range'] = $cfgSpotify['volume_range'];

$_select['volume_normalization'] .= "<option value=\"Yes\" " . (($cfgSpotify['volume_normalization'] == 'Yes') ? "selected" : "") . ">Yes</option>\n";
$_select['volume_normalization'] .= "<option value=\"No\" " . (($cfgSpotify['volume_normalization'] == 'No') ? "selected" : "") . ">No</option>\n";
$_select['normalization_method'] .= "<option value=\"dynamic\" " . (($cfgSpotify['normalization_method'] == 'dynamic') ? "selected" : "") . ">Dynamic (Default)</option>\n";
$_select['normalization_method'] .= "<option value=\"basic\" " . (($cfgSpotify['normalization_method'] == 'basic') ? "selected" : "") . ">Basic</option>\n";
$_select['normalization_gain_type'] .= "<option value=\"auto\" " . (($cfgSpotify['normalization_gain_type'] == 'auto') ? "selected" : "") . ">Automatic (Default)</option>\n";
$_select['normalization_gain_type'] .= "<option value=\"album\" " . (($cfgSpotify['normalization_gain_type'] == 'album') ? "selected" : "") . ">Album</option>\n";
$_select['normalization_gain_type'] .= "<option value=\"track\" " . (($cfgSpotify['normalization_gain_type'] == 'track') ? "selected" : "") . ">Track</option>\n";
$_select['normalization_pregain'] = $cfgSpotify['normalization_pregain'];
$_select['normalization_threshold'] = $cfgSpotify['normalization_threshold'];
$_select['normalization_attack'] = $cfgSpotify['normalization_attack'];
$_select['normalization_release'] = $cfgSpotify['normalization_release'];
$_select['normalization_knee'] = $cfgSpotify['normalization_knee'];

$_select['autoplay'] .= "<option value=\"Yes\" " . (($cfgSpotify['autoplay'] == 'Yes') ? "selected" : "") . ">Yes</option>\n";
$_select['autoplay'] .= "<option value=\"No\" " . (($cfgSpotify['autoplay'] == 'No') ? "selected" : "") . ">No</option>\n";

waitWorker('spo_config');

$tpl = "spo-config.html";
$section = basename(__FILE__, '.php');
storeBackLink($section, $tpl);

include('header.php');
eval("echoTemplate(\"" . getTemplate("templates/$tpl") . "\");");
include('footer.php');
