<?php
/*
 * SPDX-License-Identifier: GPL-3.0-or-later
 * Copyright 2014 The moOde audio player project / Tim Curtis
*/

require_once __DIR__ . '/inc/common.php';
require_once __DIR__ . '/inc/peripheral.php';
require_once __DIR__ . '/inc/session.php';

phpSession('open_ro');

if (isset($_POST['save']) && $_POST['save'] == '1') {
	foreach ($_POST['settings'] as $key => $value) {
		chkValue($key, $value);
		switch ($key) {
			case 'screen_width':
				$param = 'screen.width';
				sysCmd("sed -i 's/^" . $param . '.*/' . $param . ' = ' . $value . "/' " . PEPPY_METER_ETC_DIR . '/config.txt');
				break;
			case 'screen_height':
				$param = 'screen.height';
				sysCmd("sed -i 's/^" . $param . '.*/' . $param . ' = ' . $value . "/' " . PEPPY_METER_ETC_DIR . '/config.txt');
				break;
			case 'random_interval':
				$param = 'random.meter.interval';
				sysCmd("sed -i 's/^" . $param . '.*/' . $param . ' = ' . $value . "/' " . PEPPY_METER_ETC_DIR . '/config.txt');
				$param = 'update.period';
				sysCmd("sed -i 's/^" . $param . '.*/' . $param . ' = ' . $value . "/' " . PEPPY_SPECTRUM_ETC_DIR . '/config.txt');
				break;
			case 'meter_folder':
				$param = 'meter.folder';
				sysCmd("sed -i 's/^" . $param . '.*/' . $param . ' = ' . $value . "/' " . PEPPY_METER_ETC_DIR . '/config.txt');
				break;
			case 'meter_name':
				$param = 'meter =';
				sysCmd("sed -i 's/^" . $param . '.*/' . $param . ' ' . $value . "/' " . PEPPY_METER_ETC_DIR . '/config.txt');
				break;
			case 'meter_normalization':
				$param = 'volume.max.in.pipe';
				sysCmd("sed -i 's/^" . $param . '.*/' . $param . ' = ' . $value . "/' " . PEPPY_METER_ETC_DIR . '/config.txt');
				break;
			case 'spectrum_folder':
				$param = 'spectrum.folder';
				sysCmd("sed -i 's/^" . $param . '.*/' . $param . ' = ' . $value . "/' " . PEPPY_SPECTRUM_ETC_DIR . '/config.txt');
				break;
			case 'spectrum_name':
				$param = 'spectrum =';
				$value = $value == 'random' ? '' : $value;
				sysCmd("sed -i 's/^" . $param . '.*/' . $param . ' ' . $value . "/' " . PEPPY_SPECTRUM_ETC_DIR . '/config.txt');
				break;
		}
	}

	if ($_SESSION['peppy_display'] == '1') {
		$notify = array('title' => NOTIFY_TITLE_INFO, 'msg' => NAME_PEPPYDISPLAY . NOTIFY_MSG_SVC_RESTARTED);
		submitJob('peppy_display_restart', '', $notify['title'], $notify['msg']);
	}
}

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
$_select['meter_normalization'] .= "<option value=\"100.0\" " . (($configMeter['volume.max.in.pipe'] == '100.0') ? "selected" : "") . ">100 (Default) </option>\n";
$_select['meter_normalization'] .= "<option value=\"90.0\" " . (($configMeter['volume.max.in.pipe'] == '90.0') ? "selected" : "") . ">90</option>\n";
$_select['meter_normalization'] .= "<option value=\"80.0\" " . (($configMeter['volume.max.in.pipe'] == '80.0') ? "selected" : "") . ">80</option>\n";
$_select['meter_normalization'] .= "<option value=\"70.0\" " . (($configMeter['volume.max.in.pipe'] == '70.0') ? "selected" : "") . ">70</option>\n";
$_select['meter_normalization'] .= "<option value=\"60.0\" " . (($configMeter['volume.max.in.pipe'] == '60.0') ? "selected" : "") . ">60</option>\n";
$_select['meter_normalization'] .= "<option value=\"50.0\" " . (($configMeter['volume.max.in.pipe'] == '50.0') ? "selected" : "") . ">50</option>\n";
$_select['meter_normalization'] .= "<option value=\"40.0\" " . (($configMeter['volume.max.in.pipe'] == '40.0') ? "selected" : "") . ">40</option>\n";
$_select['meter_normalization'] .= "<option value=\"30.0\" " . (($configMeter['volume.max.in.pipe'] == '30.0') ? "selected" : "") . ">30</option>\n";
$_select['meter_normalization'] .= "<option value=\"20.0\" " . (($configMeter['volume.max.in.pipe'] == '20.0') ? "selected" : "") . ">20</option>\n";
$_select['meter_normalization'] .= "<option value=\"10.0\" " . (($configMeter['volume.max.in.pipe'] == '10.0') ? "selected" : "") . ">10</option>\n";

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

waitWorker('peppy-config');

$tpl = "peppy-config.html";
$section = basename(__FILE__, '.php');
storeBackLink($section, $tpl);

include('header.php');
eval("echoTemplate(\"" . getTemplate("templates/$tpl") . "\");");
include('footer.php');
