<?php
/*
 * SPDX-License-Identifier: GPL-3.0-or-later
 * Copyright 2014 The moOde audio player project / Tim Curtis
*/

require_once __DIR__ . '/inc/common.php';
require_once __DIR__ . '/inc/peripheral.php';
require_once __DIR__ . '/inc/session.php';
require_once __DIR__ . '/inc/sql.php';

phpSession('open');

if (isset($_POST['save']) && $_POST['save'] == '1') {
	if (empty($_POST['settings']['screen_width']) || empty($_POST['settings']['screen_height'])) {
		// Validation check
		$_SESSION['notify']['title'] = NOTIFY_TITLE_ALERT;
		$_SESSION['notify']['msg'] = 'Screen width and height cannot be blank.';
	} else {
		// Update settings
		putPeppyConfig($_POST['settings']);
		if ($_SESSION['peppy_display'] == '1') {
			$notify = array('title' => NOTIFY_TITLE_INFO, 'msg' => NAME_PEPPYDISPLAY . NOTIFY_MSG_SVC_RESTARTED);
			submitJob('peppy_display_restart', '', $notify['title'], $notify['msg']);
		}
	}
} else if (isset($_POST['install_moode_meters']) && $_POST['install_moode_meters'] == '1') {
	// Install latest moode meters
	submitJob('install_moode_meters','',NOTIFY_TITLE_INFO, 'Meters installed. Click <i class="fa-solid fa-sharp fa-redo dx"></i> to refresh the Folder list.');
}

phpSession('close');

// Load etc configs
$configMeter = getPeppyConfig('meter');
$configSpectrum = getPeppyConfig('spectrum');

// General settings
$_select['screen_width'] = $configMeter['screen.width'];
$_select['screen_height'] = $configMeter['screen.height'];
$_select['random_interval'] = $configMeter['random.meter.interval'];

// Meter settings
$folders = getPeppyFolderList('meter');
foreach($folders as $folderPath) {
	$folder = rtrim(ltrim($folderPath, PEPPY_METER_OPT_DIR . '/'), '/');
	if (!str_contains($folder, 'pycache')) { // Exclude __pycache__
		$_select['meter_folder'] .= "<option value=\"" . $folder . "\" " . (($folder == $configMeter['meter.folder']) ? "selected" : "") . ">" . $folder . "</option>\n";
	}
}
$_meter_list = getPeppyFolderContents('meter', $configMeter['meter.folder']);
$_select['meter_name'] = $configMeter['meter'];
$_select['meter_normalization'] = $configMeter['volume.max.in.pipe'];
$_select['frame_rate'] .= "<option value=\"30\" " . (($configMeter['frame.rate'] == '30') ? "selected" : "") . ">30 (Default)</option>\n";
$_select['frame_rate'] .= "<option value=\"60\" " . (($configMeter['frame.rate'] == '60') ? "selected" : "") . ">60</option>\n";
$_select['polling_interval'] .= "<option value=\"0.05\" " . (($configMeter['polling.interval'] == '0.05') ? "selected" : "") . ">0.05</option>\n";
$_select['polling_interval'] .= "<option value=\"0.04\" " . (($configMeter['polling.interval'] == '0.04') ? "selected" : "") . ">0.04</option>\n";
$_select['polling_interval'] .= "<option value=\"0.03\" " . (($configMeter['polling.interval'] == '0.03') ? "selected" : "") . ">0.03</option>\n";
$_select['polling_interval'] .= "<option value=\"0.02\" " . (($configMeter['polling.interval'] == '0.02') ? "selected" : "") . ">0.02</option>\n";
$_select['polling_interval'] .= "<option value=\"0.015\" " . (($configMeter['polling.interval'] == '0.015') ? "selected" : "") . ">0.015</option>\n";
$_select['polling_interval'] .= "<option value=\"0.01\" " . (($configMeter['polling.interval'] == '0.01') ? "selected" : "") . ">0.01</option>\n";
$_select['smooth_buffer_size'] = $configMeter['smooth.buffer.size'];

// Spectrum settings
$folders = getPeppyFolderList('spectrum');
foreach($folders as $folderPath) {
	$folder = rtrim(ltrim($folderPath, PEPPY_SPECTRUM_OPT_DIR . '/'), '/');
	if (!str_contains($folder, 'pycache')) { // Exclude __pycache__
		$_select['spectrum_folder'] .= "<option value=\"" . $folder . "\" " . (($folder == $configSpectrum['spectrum.folder']) ? "selected" : "") . ">" . $folder . "</option>\n";
	}
}
$_spectrum_list = getPeppyFolderContents('spectrum', $configSpectrum['spectrum.folder']);
$_select['spectrum_name'] = $configSpectrum['spectrum'] == '' ? 'random' : $configSpectrum['spectrum'];

// Latest moode meters
$_latest_moode_meters = sqlQuery("SELECT plugin FROM cfg_plugin WHERE component='peppydisplay' AND type='moode-meters'", sqlConnect())[0]['plugin'];

waitWorker('peppy-config');

$tpl = "peppy-config.html";
$section = basename(__FILE__, '.php');
storeBackLink($section, $tpl);

include('header.php');
eval("echoTemplate(\"" . getTemplate("templates/$tpl") . "\");");
include('footer.php');
