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
 * 2018-07-11 TC moOde 4.2
 * - minor wording updates
 * - remove FEAT_INPUTSEL
 *
 */

require_once dirname(__FILE__) . '/inc/playerlib.php';

playerSession('open', '' ,'');

if (isset($_POST['update_audioout']) && $_POST['audioout'] != $_SESSION['audioout']) {
	playerSession('write', 'audioout', $_POST['audioout']);
	submitJob('audioout', $_POST['audioout'], 'Output set to ' . $_POST['audioout'], '');
}
if (isset($_POST['update_audioin']) && $_POST['audioin'] != $_SESSION['audioin']) {
	playerSession('write', 'audioin', $_POST['audioin']);
	submitJob('audioin', $_POST['audioin'], 'Input set to ' . $_POST['audioin'], '');
}

session_write_close();

// output select
$_select['audioout'] .= "<option value=\"Local\" " . (($_SESSION['audioout'] == 'Local') ? "selected" : "") . ">Audio output -> Local</option>\n";
$_select['audioout'] .= "<option value=\"Bluetooth\" " . (($_SESSION['audioout'] == 'Bluetooth') ? "selected" : "") . ">Audio output -> Bluetooth</option>\n";
// input select examples
$_select['audioin'] .= "<option value=\"None\" " . (($_SESSION['audioin'] == 'None') ? "selected" : "") . ">None</option>\n";
$_select['audioin'] .= "<option value=\"Analog\" " . (($_SESSION['audioin'] == 'Analog') ? "selected" : "") . ">Analog input</option>\n";
$_select['audioin'] .= "<option value=\"S/PDIF\" " . (($_SESSION['audioin'] == 'S/PDIF') ? "selected" : "") . ">S/PDIF input</option>\n";

$section = basename(__FILE__, '.php');
$tpl = "sel-config.html";
include('/var/local/www/header.php'); 
waitWorker(1);
eval("echoTemplate(\"" . getTemplate("templates/$tpl") . "\");");
include('footer.php');
