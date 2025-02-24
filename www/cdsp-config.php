<?php
/*
 * SPDX-License-Identifier: GPL-3.0-or-later
 * Copyright 2014 The moOde audio player project / Tim Curtis
 * Copyright 2021 @bitlab (@bitkeeper Git)
*/

require_once __DIR__ . '/inc/common.php';
require_once __DIR__ . '/inc/audio.php';
require_once __DIR__ . '/inc/cdsp.php';
require_once __DIR__ . '/inc/session.php';
require_once __DIR__ . '/inc/sql.php';

phpSession('open');

chkVariables($_POST);

$cdsp = new CamillaDsp($_SESSION['camilladsp'], $_SESSION['cardnum'], $_SESSION['camilladsp_quickconv']);
$selectedConfig = isset($_POST['cdsp_config']) ? $_POST['cdsp_config'] : null;
$selectedCoeff = isset($_POST['cdsp_coeffs']) ? $_POST['cdsp_coeffs'] : null;

// CONFIGURATION

if (isset($_POST['save']) && $_POST['save'] == '1') {
	// Signal processing
	if (isset($_POST['cdsp_mode'])) {
		$currentMode = $_SESSION['camilladsp'];
		$newMode = $_POST['cdsp_mode'];
		phpSession('write', 'camilladsp', $_POST['cdsp_mode']);
		$cdsp->selectConfig($_POST['cdsp_mode']);
	}
	// Audio device
	if (isset($_POST['cdsp_use_default_device'])) {
		$useDefaultDevice = $_POST['cdsp_use_default_device'];
		phpSession('write', 'cdsp_fix_playback', $useDefaultDevice == "1" ? "Yes" : "No");
	}
	if ($_SESSION['cdsp_fix_playback'] == 'Yes') {
		$cdsp->setPlaybackDevice($_SESSION['cardnum'], $_SESSION['alsa_output_mode']);
	}
	// Log level
	if ($_POST['log_level'] != $cdsp->getLogLevel()) {
		$cdsp->setLogLevel($_POST['log_level']);
	}
	// Quick convolution filter
	if (isset($_POST['cdsp_qc_gain']) && isset($_POST['cdsp_qc_ir_left']) && isset($_POST['cdsp_qc_ir_right'])) {
		$gain = $_POST['cdsp_qc_gain'];
		$convL = $_POST['cdsp_qc_ir_left'];
		$convR = $_POST['cdsp_qc_ir_right'];
		$convT = $_POST['cdsp_qc_ir_type'];
		$cfg = $gain . ',' . $convL . ',' . $convR . ',' . $convT;
		$cdsp->setQuickConvolutionConfig($cdsp->stringToQuickConvolutionConfig($cfg));
		phpSession('write', 'camilladsp_quickconv', $cfg);
	}

	// Update the configuration
	$cdsp->updCDSPConfig($newMode, $currentMode, $cdsp);
}

// PIPELINE EDITOR
else if (isset($_POST['camillaguistatus']) && isset($_POST['update_camillagui']) && $_POST['update_camillagui'] == '1') {
	// Pipeline editor status toggle
 	$cdsp->changeCamillaStatus($_POST['camillaguistatus']);
} else if (isset($_POST['camillaguiexpertstatus']) && isset($_POST['update_camillagui_expert']) && $_POST['update_camillagui_expert'] == '1') {
	// Pipeline editor expert mode toggle
	$cdsp->setGuiExpertMode($_POST['camillaguiexpertstatus'] == '1');

// FILE MANAGEMENT

// CONFIGURATION
} else if ($selectedConfig && isset($_POST['remove']) && $_POST['remove'] == '1') {
	// Remove
	if ($_SESSION['camilladsp'] != $selectedConfig . '.yml') { // Can't remove active config
		$configFileName = $cdsp->getConfigsLocationsFileName() . $selectedConfig . '.yml';
		unlink($configFileName);
		$_SESSION['notify']['title'] = NOTIFY_TITLE_INFO;
		$_SESSION['notify']['msg'] = 'Configuration removed.';
		$selectedConfig = null;
	} else {
		$_SESSION['notify']['title'] = NOTIFY_TITLE_ALERT;
		$_SESSION['notify']['msg'] = 'Cannot remove active configuration.';
	}
} else if ($selectedConfig && isset($_POST['copy_pipeline']) && $_POST['copy_pipeline'] == '1') {
	// Copy
	$cdsp->copyConfig($selectedConfig . '.yml', $_POST['copyto_pipeline_name'] . '.yml');
	$selectedConfig = $_POST['copyto_pipeline_name'] . '.yml';
	$_SESSION['notify']['title'] = NOTIFY_TITLE_INFO;
	$_SESSION['notify']['msg'] = 'Configuration copied.';
} else if (isset($_POST['create_new_pipeline']) && $_POST['create_new_pipeline'] == '1') {
	// New
	$cdsp->newConfig($_POST['new_pipeline_name'] . '.yml');
	$selectedConfig = $_POST['new_pipeline_name'] . '.yml';
	$_SESSION['notify']['title'] = NOTIFY_TITLE_INFO;
	$_SESSION['notify']['msg'] = 'Configuration created.';
} else if ($selectedConfig && isset($_POST['export']) && $_POST['export'] == '1') {
	// Download
	$configFileName = $cdsp->getConfigsLocationsFileName() . $selectedConfig;
	header("Content-Description: File Transfer");
	header("Content-Type: application/yaml");
	header("Content-Disposition: attachment; filename=\"". $selectedConfig ."\"");
	readfile ($configFileName);
 	exit();
} else if (isset($_FILES['pipeline_config']) && isset($_POST['import']) && $_POST['import'] == '1') {
	// Upload
	$configFileBaseName = $_FILES["pipeline_config"]["name"];
	$configFileName = $cdsp->getConfigsLocationsFileName() . $configFileBaseName;
	move_uploaded_file($_FILES["pipeline_config"]["tmp_name"], $configFileName);
	if ($_SESSION['camilladsp'] == $configFileBaseName) { // if upload active config, fix it
		if ($_SESSION['cdsp_fix_playback'] == 'Yes') {
			$cdsp->setPlaybackDevice($_SESSION['cardnum'], $_SESSION['alsa_output_mode']);
		}
		$cdsp->reloadConfig();
	} else {
		$cdsp->patchRelConvPath($configFileBaseName);
	}
	$selectedConfig = $configFileBaseName;
	$_SESSION['notify']['title'] = NOTIFY_TITLE_INFO;
	$_SESSION['notify']['msg'] = 'Upload completed.';
} else if ($selectedConfig && isset($_POST['check']) && $_POST['check'] == '1') {
	// Check
	$checkResult = $cdsp->checkConfigFile($selectedConfig);
	$selectedConfigLabel = str_replace('.yml', '', $cdsp->getConfigLabel($selectedConfig));
	if($checkResult['valid'] == true) {
		$_SESSION['notify']['title'] = NOTIFY_TITLE_INFO;
		$_SESSION['notify']['msg'] = 'Configuration is valid.';
	} else {
		$_SESSION['notify']['title'] = NOTIFY_TITLE_ALERT;
		$_SESSION['notify']['msg'] = 'Configuration has errors.';
	}
} else if ($selectedConfig && isset($_POST['upgrade']) && $_POST['upgrade'] == '1') {
	// Upgrade
	$checkResult = $cdsp->upgradeConfigFile($selectedConfig);
	$selectedConfigLabel = str_replace('.yml', '', $cdsp->getConfigLabel($selectedConfig));
	if($checkResult['valid'] == true) {
		$_SESSION['notify']['title'] = NOTIFY_TITLE_INFO;
		$_SESSION['notify']['msg'] = 'Configuration is valid.';
	} else {
		$_SESSION['notify']['title'] = NOTIFY_TITLE_ALERT;
		$_SESSION['notify']['msg'] = 'Configuration has errors.';
	}

// CONVOLUTION
} else if ($selectedCoeff && isset($_POST['remove']) && $_POST['remove'] == '1') {
	// Remove
	$configFileName = $cdsp->getCoeffsLocation() . $selectedCoeff;
	unlink($configFileName);
	$_SESSION['notify']['title'] = NOTIFY_TITLE_INFO;
	$_SESSION['notify']['msg'] = 'Configuration removed';
	$selectedCoeff = null;
} else if ($selectedCoeff && isset($_POST['export']) && $_POST['export'] == '1') {
	// Download
	$configFileName = $cdsp->getCoeffsLocation() . $selectedCoeff;
	header("Content-Description: File Transfer");
	header("Content-Type: application/binary");
	header("Content-Disposition: attachment; filename=\"". $selectedCoeff ."\"");
	readfile ($configFileName);
 	exit();
} else if (isset($_FILES['coeffs_file']) && isset($_POST['import']) && $_POST['import'] == '1') {
	// Upload
	$configFileName = $cdsp->getCoeffsLocation() . $_FILES["coeffs_file"]["name"];
	move_uploaded_file($_FILES["coeffs_file"]["tmp_name"], $configFileName);
	$_SESSION['notify']['title'] = NOTIFY_TITLE_INFO;
	$_SESSION['notify']['msg'] = 'Upload complete.';
	$selectedCoeff = $_FILES["coeffs_file"]["name"];

/*DELETE:// SAMPLE CONFIGURATIONS
} else if (isset($_POST['install_sample_configs']) && $_POST['install_sample_configs'] == '1') {
	// Update to latest sample configs
	$result = sqlQuery("SELECT plugin FROM cfg_plugin WHERE component='camilladsp' AND type='sample-configs'", sqlConnect());
	submitJob('install_plugin', 'camilladsp,' . $result[0]['plugin'], NOTIFY_TITLE_INFO, 'Sample configs updated. ' . NOTIFY_MSG_SYSTEM_RESTART_REQD);
*/
} else if ($selectedCoeff && isset($_POST['info']) && $_POST['info'] == '1') {
	// Placeholder, no implementation required
}

phpSession('close');

// HTML TEMPLATE

$_select['version'] = str_replace('CamillaDSP', '', $cdsp->version());

if ($_SESSION['invert_polarity'] != '0' ||
 	$_SESSION['crossfeed'] != 'Off' ||
 	$_SESSION['eqfa12p'] != 'Off' ||
 	$_SESSION['alsaequal'] != 'Off' ||
	$_SESSION['audioout'] == 'Bluetooth' ||
	$_SESSION['multiroom_tx'] == 'On' ||
	$_SESSION['multiroom_rx'] == 'On')
{
	$_save_disabled = 'disabled';
	$_camilladsp_set_disabled_message = '';
} else {
	$_save_disabled = '';
	$_camilladsp_set_disabled_message = 'hide';
}

// CONFIGURATION

// Signal processing
$configs = $cdsp->getAvailableConfigs();
foreach ($configs as $configFile => $configName) {
	$selected = ($_SESSION['camilladsp'] == $configFile) ? 'selected' : '';
	$_select['cdsp_mode'] .= sprintf("<option value='%s' %s>%s</option>\n", $configFile, $selected, ucfirst($configName));
}
$_config_description = $cdsp->getConfigDescription($_SESSION['camilladsp']);
$configs = $cdsp->getAvailableConfigsRaw();
$_selected_config = null;
foreach ($configs as $configFile => $configName) {
	$selected = (($selectedConfig == $configFile || ($selectedConfig == null) && $_selected_config == null)) ? 'selected' : '';
	$_select['cdsp_config'] .= sprintf("<option value='%s' %s>%s</option>\n", $configFile, $selected, ucfirst($configName));
	if ($selected == 'selected') {
		$_selected_config = $configName;
		$selectedConfig = $configFile;
	}
}
// Audio device
$_select['cdsp_use_default_device_on'] .= "<input type=\"radio\" name=\"cdsp_use_default_device\" id=\"toggle-cdsp-use-default-device-1\" value=\"1\" " . (($_SESSION['cdsp_fix_playback'] == 'Yes') ? "checked=\"checked\"" : "") . ">\n";
$_select['cdsp_use_default_device_off']  .= "<input type=\"radio\" name=\"cdsp_use_default_device\" id=\"toggle-cdsp-use-default-device-2\" value=\"0\" " . (($_SESSION['cdsp_fix_playback'] == 'No') ? "checked=\"checked\"" : "") . ">\n";
if ($_SESSION['cdsp_fix_playback'] == 'No') {
	$_alsa_output_mode = '(Defined in Pipeline editor)';
} else if (substr($_SESSION['hdwrrev'], 3, 1) >= 3 && isHDMIDevice($_SESSION['adevname'])) {
	$_alsa_output_mode = $_SESSION['alsa_output_mode'];
} else {
	$_alsa_output_mode = $_SESSION['alsa_output_mode'] . ':' . $_SESSION['cardnum'] . ',0';
}
// Logging level
$cdsp_log_level = $cdsp->getLogLevel();
$_cdsp_log_level .= "<option value=\"default\" " . (($cdsp_log_level == 'default') ? "selected" : "") . " >Default</option>\n";
$_cdsp_log_level .= "<option value=\"verbose\" " . (($cdsp_log_level == 'verbose') ? "selected" : "") . " >Verbose</option>\n";

// CONVOLUTION

$configs = $cdsp->getAvailableCoeffs();
$_selected_coeff = null;
foreach ($configs as $configFile => $configName) {
	$selected = ($selectedCoeff == $configFile || ($selectedCoeff == null && $_selected_coeff == null)) ? 'selected' : '';
	$_select['cdsp_coeffs'] .= sprintf("<option value='%s' %s>%s</option>\n", $configFile, $selected, ucfirst($configFile));
	if ($selected == 'selected') {
		$_selected_coeff = $configFile;
	}
}
$btn_conv_style = 'style="display: none;"';
if ($_selected_coeff) {
	$coeffInfo = $cdsp->coeffInfo($_selected_coeff);
	$_coeff_info_html = '';
	foreach ($coeffInfo as $param => $value) {
		$_coeff_info_html .= '<tr><td>' . ucfirst($param) . '</td><td>' . $value . '</td></tr>';
	}
}
if ($_SESSION['camilladsp_quickconv']) {
	$quickConvConfig =$cdsp->stringToQuickConvolutionConfig($_SESSION['camilladsp_quickconv']);
	$_cdsp_qc_gain = $quickConvConfig['gain'];
	$quickConvIRL = $quickConvConfig['irl'];
	$quickConvIRR = $quickConvConfig['irr'];
	$quickConvIRType = $quickConvConfig['irtype'];
}
foreach ($configs as $configFile => $configName) {
	$selected = ($quickConvIRL == $configFile) ? 'selected' : '';
	$_select['cdsp_qc_ir_left'] .= sprintf("<option value='%s' %s>%s</option>\n", $configFile, $selected, ucfirst($configFile));
}
foreach ($configs as $configFile => $configName) {
	$selected = ($quickConvIRR == $configFile) ? 'selected' : '';
	$_select['cdsp_qc_ir_right'] .= sprintf("<option value='%s' %s>%s</option>\n", $configFile, $selected, ucfirst($configFile));
}
foreach ($cdsp->impulseResponseType() as $irType) {
	$selected = ($quickConvIRType == $irType) ? 'selected' : '';
	$_select['cdsp_qc_ir_type'] .= sprintf("<option value='%s' %s>%s</option>\n", $irType, $selected, $irType);
}

// PIPELINE EDITOR

$camillaGuiStatus = $cdsp->getCamillaGuiStatus();
$camillaGuiClickHandler = " onchange=\"autoClick('#btn-update-camilla-gui');\"";
$camillaGuiExpertClickHandler = " onchange=\"autoClick('#btn-update-camilla-gui-expert');\"";
$_select['camillagui_on'] .= "<input type=\"radio\" name=\"camillaguistatus\" id=\"toggle-camillagui-1\" value=\"1\" " . (($camillaGuiStatus == CGUI_CHECK_ACTIVE) ? "checked=\"checked\"" : $camillaGuiClickHandler) . " >\n";
$_select['camillagui_off'] .= "<input type=\"radio\" name=\"camillaguistatus\" id=\"toggle-camillagui-2\" value=\"0\" " . (($camillaGuiStatus != CGUI_CHECK_ACTIVE) ? "checked=\"checked\"" : $camillaGuiClickHandler) . " >\n";
$_select['camillaguiexpert_on'] .= "<input type=\"radio\" name=\"camillaguiexpertstatus\" id=\"toggle-camillaguiexpert-1\" value=\"1\" " . (($cdsp->getGuiExpertMode() == true) ? "checked=\"checked\"" : $camillaGuiExpertClickHandler) . " >\n";
$_select['camillaguiexpert_off'] .= "<input type=\"radio\" name=\"camillaguiexpertstatus\" id=\"toggle-camillaguiexpert-2\" value=\"0\" " . (($cdsp->getGuiExpertMode() != true) ? "checked=\"checked\"" : $camillaGuiExpertClickHandler) . " >\n";
$_open_camillagui_disabled = $camillaGuiStatus == CGUI_CHECK_ACTIVE ? '' : 'disabled';
$_camillagui_notfound_show = $camillaGuiStatus == CGUI_CHECK_NOTFOUND ? '' : 'hide';
$_camillagui_status_problems = $camillaGuiStatus == CGUI_CHECK_ACTIVE || $camillaGuiStatus == CGUI_CHECK_INACTIVE || $camillaGuiStatus == CGUI_CHECK_NOTFOUND? 'hide' : '';

/* DELETE: (does not appear to be used anywhere)
// Extract settings needed to show camilladsp configuration template:
// Get current output hardware device
$current_sound_device_number = $_SESSION['cardnum'];
$alsa_to_camilla_sample_formats = $cdsp->alsaToCamillaSampleFormatLut();
// Get best available output sample format
$supported_soundformats = $cdsp->detectSupportedSoundFormats();
$sound_device_supported_sample_formats = '';
foreach ($supported_soundformats as $cdsp_format) {
	$sound_device_supported_sample_formats .= $cdsp_format . ' ';
}
if (count($supported_soundformats) >= 1) {
	$sound_device_sample_format = $supported_soundformats[0];
	$sound_device_type = 'hw';
}
*/

// FILE MANAGEMENT

// Auto-check config and convolution files
$_check_msg_config = '';
$_check_msg_quick_convolution = '';
if ($selectedConfig) {
	if (!isset($checkResult)) {
		$checkResult = $cdsp->checkConfigFile($selectedConfig);
	}
	$_check_msg_config = checkResultToHtml($checkResult);
}
if ($cdsp->isQuickConvolutionActive()) {
	$_check_msg_quick_convolution =
		'<span class="config-help-static">' .
			checkResultToHtml($cdsp->checkConfigFile($cdsp->getConfig())) .
		'</span>';
}
/*DELETE:// Sample configs plugin version
$result = sqlQuery("SELECT plugin FROM cfg_plugin WHERE component='camilladsp' AND type='sample-configs'", sqlConnect());
$_sample_configs_version = $result[0]['plugin'];
*/

// EXTENSIONS

// The extension mechanism is intended for dynamically adding function plugins for generating CamillaDSP configurations
$extensions_config = '/var/local/www/cdsp_extensions.json';
$extensions_html = '';
if (file_exists($extensions_config)) {
	$_cdsp_extensions_show = '';
	$extensions = json_decode(file_get_contents($extensions_config), true);
	$extension_template = '
	<label class="control-label">%s</label>
	<div class="controls">
		<a href="%s"><button class="btn btn-primary btn-medium" style="margin-top:0px;">Open</button></a>
		<div style="display: inline-block; vertical-align: top; margin-top: 2px;">
			<a aria-label="Help" class="info-toggle" data-cmd="info-%s" href="#notarget"><i class="fa-regular fa-sharp fa-info-circle"></i></a>
		</div>
		<span id="info-%s" class="help-block-configs help-block-margin legend-info-help hide">
			%s<br>
		</span>
	</div>
	<br/>';
	foreach ($extensions as $extension) {
		$extensions_html .= sprintf($extension_template ,  $extension['title'], $extension['url'], $extension['label'], $extension['label'],  'help help'); // $extension['help']);
		$extensions_html .= "\n";
	}
} else {
	$_cdsp_extensions_show = 'hide';
}

setAltBackLink();

waitWorker('cdsp-config');

$tpl = "cdsp-config.html";
$section = basename(__FILE__, '.php');
storeBackLink($section, $tpl);

include('header.php');
eval("echoTemplate(\"" . getTemplate("templates/$tpl") . "\");");
include('footer.php');

function checkResultToHtml($checkResult) {
	$message = '';
	$checkMsgRaw = implode('<br>', $checkResult['msg']);
	if ($checkResult['valid'] == CDSP_CHECK_NOTFOUND) {
		$message = "<span style='color: red'>&#10007;</span> ". $checkMsgRaw;
	} else if ($checkResult['valid'] == CDSP_CHECK_VALID) {
		$message = "<span style='color: green'>&check;</span> " . $checkMsgRaw;
	} else {
		$message = "<span style='color: red'>&#10007;</span> " . $checkMsgRaw;
	}
	return $message;
}
