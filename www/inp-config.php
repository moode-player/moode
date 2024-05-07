<?php
/**
 * SPDX-License-Identifier: GPL-3.0-or-later
 * Copyright 2014 The moOde audio player project / Tim Curtis
*/

require_once __DIR__ . '/inc/common.php';
require_once __DIR__ . '/inc/session.php';

phpSession('open');

if (isset($_POST['update_audio_input']) && $_POST['audio_input'] != $_SESSION['audioin']) {
	if ($_POST['audio_input'] != 'Local' && $_SESSION['mpdmixer'] != 'hardware' && $_SESSION['mpdmixer'] != 'none') {
		$_SESSION['notify']['title'] = NOTIFY_TITLE_ALERT;
		$_SESSION['notify']['msg'] = 'Volume type must first be set to Hardware or Fixed (0dB).';
		$_SESSION['notify']['duration'] = NOTIFY_DURATION_MEDIUM;
	} else {
		phpSession('write', 'audioin', $_POST['audio_input']);
		submitJob('audioin', $_POST['audio_input']);
	}
}

if (isset($_POST['update_resume_mpd']) && $_POST['resume_mpd'] != $_SESSION['rsmafterinp']) {
	phpSession('write', 'rsmafterinp', $_POST['resume_mpd']);
}

if (isset($_POST['update_audio_output']) && $_POST['audio_output'] != $_SESSION['audioout']) {
	phpSession('write', 'audioout', $_POST['audio_output']);
	submitJob('audioout', $_POST['audio_output']);
}

phpSession('close');

// Input source
$_select['audio_input'] .= "<option value=\"Local\" " . (($_SESSION['audioin'] == 'Local') ? "selected" : "") . ">MPD</option>\n";
if ($_SESSION['i2sdevice'] == 'HiFiBerry DAC+ ADC') {
	$_select['audio_input'] .= "<option value=\"Analog\" " . (($_SESSION['audioin'] == 'Analog') ? "selected" : "") . ">Analog input</option>\n";
} else if ($_SESSION['i2sdevice'] == 'Audiophonics ES9028/9038 DAC') {
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
