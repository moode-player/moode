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

phpSession('open');

if (isset($_POST['update_audio_input']) && $_POST['audio_input'] != $_SESSION['audioin']) {
	if ($_POST['audio_input'] != 'Local' && $_SESSION['mpdmixer'] != 'hardware' && $_SESSION['mpdmixer'] != 'none') {
		$_SESSION['notify']['title'] = 'Volume type must first be set to Hardware or Fixed (0dB)';
		$_SESSION['notify']['duration'] = 6;
	} else {
		phpSession('write', 'audioin', $_POST['audio_input']);
		submitJob('audioin', $_POST['audio_input'],
			'Source set to ' . ($_POST['audio_input'] == 'Local' ? 'MPD' : $_POST['audio_input']));
	}
}

if (isset($_POST['update_resume_mpd']) && $_POST['resume_mpd'] != $_SESSION['rsmafterinp']) {
	phpSession('write', 'rsmafterinp', $_POST['resume_mpd']);
	$_SESSION['notify']['title'] = 'Settings updated';
}

if (isset($_POST['update_audio_output']) && $_POST['audio_output'] != $_SESSION['audioout']) {
	phpSession('write', 'audioout', $_POST['audio_output']);
	submitJob('audioout', $_POST['audio_output'], 'Output set to ' . $_POST['audio_output']);
}

phpSession('close');

// Input source
$_select['audio_input'] .= "<option value=\"Local\" " . (($_SESSION['audioin'] == 'Local') ? "selected" : "") . ">MPD</option>\n";
if ($_SESSION['i2sdevice'] == 'HiFiBerry DAC+ ADC') {
	$_select['audio_input'] .= "<option value=\"Analog\" " . (($_SESSION['audioin'] == 'Analog') ? "selected" : "") . ">Analog input</option>\n";
} else if ($_SESSION['i2sdevice'] == 'Audiophonics ES9028/9038 DAC' || $_SESSION['i2sdevice'] == 'Audiophonics ES9028/9038 DAC (Pre 2019)') {
	$_select['audio_input'] .= "<option value=\"S/PDIF\" " . (($_SESSION['audioin'] == 'S/PDIF') ? "selected" : "") . ">S/PDIF input</option>\n";
}

// Resume MPD after changing to Local
$_select['resume_mpd'] .= "<option value=\"Yes\" " . (($_SESSION['rsmafterinp'] == 'Yes') ? "selected" : "") . ">Yes</option>\n";
$_select['resume_mpd'] .= "<option value=\"No\" " . (($_SESSION['rsmafterinp'] == 'No') ? "selected" : "") . ">No</option>\n";

// Output device
$_select['audio_output'] .= "<option value=\"Local\" " . (($_SESSION['audioout'] == 'Local') ? "selected" : "") . ">Local audio</option>\n";
if ($_SESSION['btsvc'] == '1') {
	$_select['audio_output'] .= "<option value=\"Bluetooth\" " . (($_SESSION['audioout'] == 'Bluetooth') ? "selected" : "") . ">Bluetooth speaker</option>\n";
}

waitWorker('inp-config');

$tpl = "inp-config.html";
$section = basename(__FILE__, '.php');
storeBackLink($section, $tpl);

include('header.php');
eval("echoTemplate(\"" . getTemplate("templates/$tpl") . "\");");
include('footer.php');
