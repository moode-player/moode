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

if (isset($_POST['update_audioin']) && $_POST['audioin'] != $_SESSION['audioin']) {
	if ($_POST['update_audioin'] != 'Local' && $_SESSION['mpdmixer'] == 'software') {
		$_SESSION['notify']['title'] = 'MPD Volume control must first be set to Hardware or Disabled (0dB)';
		$_SESSION['notify']['duration'] = 6;
	}
	else {
		playerSession('write', 'audioin', $_POST['audioin']);
		submitJob('audioin', $_POST['audioin'], 'Input set to ' . $_POST['audioin'], '');
	}
}
if (isset($_POST['update_rsmafterinp']) && $_POST['rsmafterinp'] != $_SESSION['rsmafterinp']) {
	playerSession('write', 'rsmafterinp', $_POST['rsmafterinp']);
	$_SESSION['notify']['title'] = 'Setting updated';
}
if (isset($_POST['update_audioout']) && $_POST['audioout'] != $_SESSION['audioout']) {
	playerSession('write', 'audioout', $_POST['audioout']);
	submitJob('audioout', $_POST['audioout'], 'Output set to ' . $_POST['audioout'], '');
}

session_write_close();

// Input source
$_select['audioin'] .= "<option value=\"Local\" " . (($_SESSION['audioin'] == 'Local') ? "selected" : "") . ">Local (MPD)</option>\n";
if ($_SESSION['i2sdevice'] == 'HiFiBerry DAC+ ADC') {
	$_select['audioin'] .= "<option value=\"Analog\" " . (($_SESSION['audioin'] == 'Analog') ? "selected" : "") . ">Analog input</option>\n";
}
elseif ($_SESSION['i2sdevice'] == 'Audiophonics ES9028/9038 DAC' || $_SESSION['i2sdevice'] == 'Audiophonics ES9028/9038 DAC (Pre 2019)') {
	$_select['audioin'] .= "<option value=\"S/PDIF\" " . (($_SESSION['audioin'] == 'S/PDIF') ? "selected" : "") . ">S/PDIF input</option>\n";
}

// Resume MPD after changing to Local
$_select['rsmafterinp'] .= "<option value=\"Yes\" " . (($_SESSION['rsmafterinp'] == 'Yes') ? "selected" : "") . ">Yes</option>\n";
$_select['rsmafterinp'] .= "<option value=\"No\" " . (($_SESSION['rsmafterinp'] == 'No') ? "selected" : "") . ">No</option>\n";

// Output device
$_select['audioout'] .= "<option value=\"Local\" " . (($_SESSION['audioout'] == 'Local') ? "selected" : "") . ">Local device</option>\n";
//$_select['audioout'] .= "<option value=\"Bluetooth\" " . (($_SESSION['audioout'] == 'Bluetooth') ? "selected" : "") . ">Bluetooth stream</option>\n";

waitWorker(1, 'inp-config');

$tpl = "inp-config.html";
$section = basename(__FILE__, '.php');
storeBackLink($section, $tpl);

include('header.php');
eval("echoTemplate(\"" . getTemplate("templates/$tpl") . "\");");
include('footer.min.php');
