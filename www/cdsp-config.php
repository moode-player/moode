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
require_once __DIR__ . '/inc/cdsp.php';

phpSession('open');

$cdsp = new CamillaDsp($_SESSION['camilladsp'], $_SESSION['cardnum'], $_SESSION['camilladsp_quickconv']);
$selectedConfig = isset($_POST['cdsp_config']) ? $_POST['cdsp_config'] : null;
$selectedCoeff = isset($_POST['cdsp_coeffs']) ? $_POST['cdsp_coeffs'] : null;

if (isset($_POST['save']) && $_POST['save'] == '1') {
	if (isset($_POST['cdsp_qc_gain']) && isset($_POST['cdsp_qc_ir_left']) && isset($_POST['cdsp_qc_ir_right'])) {
		$gain = $_POST['cdsp_qc_gain'];
		$convL = $_POST['cdsp_qc_ir_left'];
		$convR = $_POST['cdsp_qc_ir_right'];
		$convT = $_POST['cdsp_qc_ir_type'];
		$cfg = $gain . ';' . $convL . ';' . $convR . ';' . $convT;
		$cdsp->setQuickConvolutionConfig($cdsp->stringToQuickConvolutionConfig($cfg));
		phpSession('write', 'camilladsp_quickconv', $cfg);
	}

	if (isset($_POST['cdsp_use_default_device'])) {
		$useDefaultDevice = $_POST['cdsp_use_default_device'];
		phpSession('write', 'cdsp_fix_playback', $useDefaultDevice == "1" ? "Yes" : "No");
	}

	if (isset($_POST['cdsp_mode'])) {
		$currentMode = $_SESSION['camilladsp'];
		phpSession('write', 'camilladsp', $_POST['cdsp_mode']);
		$cdsp->selectConfig($_POST['cdsp_mode']);
	}

	if ($_SESSION['cdsp_fix_playback'] == 'Yes') {
		$cdsp->setPlaybackDevice($_SESSION['cardnum'], $_SESSION['alsa_output_mode']);
	}

	if ($_SESSION['camilladsp'] != $currentMode && ($_SESSION['camilladsp'] == 'off' || $currentMode == 'off')) {
		submitJob('camilladsp', $_POST['cdsp_mode'], 'CamillaDSP ' . $cdsp->getConfigLabel($_POST['cdsp_mode']), '');
	} else {
		$cdsp->reloadConfig();
	}

	if ($_POST['log_level'] != $cdsp->getLogLevel()) {
		$cdsp->setLogLevel($_POST['log_level']);
	}
}
// Check
else if ($selectedConfig && isset($_POST['check']) && $_POST['check'] == '1') {
	$checkResult = $cdsp->checkConfigFile($selectedConfig);

	$selectedConfigLabel = $cdsp->getConfigLabel($selectedConfig);
	if($checkResult['valid'] == True) {
		$_SESSION['notify']['title'] =   htmlentities('Pipeline configuration \"' . $selectedConfigLabel . '\" is valid');
	} else {
		$_SESSION['notify']['title'] = htmlentities('Pipeline configuration \"' . $selectedConfigLabel . '\" is not valid');
	}
}
// Import (Upload)
else if (isset($_FILES['pipeline_config']) && isset($_POST['import']) && $_POST['import'] == '1') {
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
	$_SESSION['notify']['title'] =  htmlentities('Import \"' . $configFileBaseName . '\" completed');
}
// Export (Download)
else if ($selectedConfig && isset($_POST['export']) && $_POST['export'] == '1') {
	$configFileName = $cdsp->getConfigsLocationsFileName() . $selectedConfig;

	header("Content-Description: File Transfer");
	header("Content-Type: application/yaml");
	header("Content-Disposition: attachment; filename=\"". $selectedConfig ."\"");

	readfile ($configFileName);
 	exit();
}
// Remove
else if ($selectedConfig && isset($_POST['remove']) && $_POST['remove'] == '1') {
	if ($_SESSION['camilladsp'] != $selectedConfig) { // Can't remove active config
		$configFileName = $cdsp->getConfigsLocationsFileName() . $selectedConfig;
		unlink($configFileName);
		$_SESSION['notify']['title'] = htmlentities('Remove configuration \"' . $selectedConfig . '\" completed');
		$selectedConfig = null;
	}
	else {
		$_SESSION['notify']['title'] = htmlentities('Cannot remove active configuration \"' . $selectedConfig . '\"');
	}
}
// New pipeline
else if (isset($_POST['create_new_pipeline']) && $_POST['create_new_pipeline'] == '1') {
	$cdsp->newConfig($_POST['new_pipeline_name'] . '.yml');
	$selectedConfig = $_POST['new_pipeline_name'] . '.yml';
}
// Copy pipeline
else if ($selectedConfig && isset($_POST['copy_pipeline']) && $_POST['copy_pipeline'] == '1') {
	$cdsp->copyConfig($selectedConfig, $_POST['copyto_pipeline_name'] . '.yml');
	$selectedConfig = $_POST['copyto_pipeline_name'] . '.yml';
}
// Coeffs import (Upload)
else if (isset($_FILES['coeffs_file']) && isset($_POST['import']) && $_POST['import'] == '1') {
	$configFileName = $cdsp->getCoeffsLocation() . $_FILES["coeffs_file"]["name"];
	move_uploaded_file($_FILES["coeffs_file"]["tmp_name"], $configFileName);
	$_SESSION['notify']['title'] =  htmlentities('Import \"' . $_FILES["coeffs_file"]["name"] . '\" completed');

	$selectedCoeff = $_FILES["coeffs_file"]["name"];
}
// Coeffs export (Download)
else if ($selectedCoeff && isset($_POST['export']) && $_POST['export'] == '1') {
	$configFileName = $cdsp->getCoeffsLocation() . $selectedCoeff;

	header("Content-Description: File Transfer");
	header("Content-Type: application/binary");
	header("Content-Disposition: attachment; filename=\"". $selectedCoeff ."\"");

	readfile ($configFileName);
 	exit();
}
// Coeffs remove
else if ($selectedCoeff && isset($_POST['remove']) && $_POST['remove'] == '1') {
	$configFileName = $cdsp->getCoeffsLocation() . $selectedCoeff;
	unlink($configFileName);
	$_SESSION['notify']['title'] = htmlentities('Remove configuration \"' . $selectedCoeff . '\" completed');
	$selectedCoeff = null;
}
else if ($selectedCoeff && isset($_POST['info']) && $_POST['info'] == '1') {
// no implementation required, just a placeholder
}

// camillagui status toggle
else if (isset($_POST['camillaguistatus']) && isset($_POST['update_camillagui']) && $_POST['update_camillagui'] == '1') {
 	$cdsp->changeCamillaStatus($_POST['camillaguistatus']);
}
else if (isset($_POST['camillaguiexpertstatus']) && isset($_POST['update_camillagui_expert']) && $_POST['update_camillagui_expert'] == '1') {
	$cdsp->setGuiExpertMode($_POST['camillaguiexpertstatus'] == '1');
}

phpSession('close');

/**
 * Generate data for html templating
 */

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

$configs = $cdsp->getAvailableConfigs();
foreach ($configs as $configFile => $configName) {
	$selected = ($_SESSION['camilladsp'] == $configFile) ? 'selected' : '';
	$_select['cdsp_mode'] .= sprintf("<option value='%s' %s>%s</option>\n", $configFile, $selected, $configName);
}

$configs = $cdsp->getAvailableConfigsRaw();
$_selected_config = null;
foreach ($configs as $configFile => $configName) {
	$selected = (($selectedConfig == $configFile || ($selectedConfig == null) && $_selected_config == null)) ? 'selected' : '';
	$_select['cdsp_config'] .= sprintf("<option value='%s' %s>%s</option>\n", $configFile, $selected, $configName);
	if ($selected == 'selected') {
		$_selected_config = $configFile;
		//$_selected_config = $selected;
		$selectedConfig = $configFile;
	}
}

$configs = $cdsp->getAvailableCoeffs();
$_selected_coeff = null;
foreach ($configs as $configFile => $configName) {
	$selected = ($selectedCoeff == $configFile || ($selectedCoeff == null && $_selected_coeff == null)) ? 'selected' : '';
	$_select['cdsp_coeffs'] .= sprintf("<option value='%s' %s>%s</option>\n", $configFile, $selected, $configFile);
	if ($selected == 'selected') {
		$_selected_coeff = $configFile;
	}
}
$btn_conv_style = 'style="display: none;"';
if ($_selected_coeff) {
	$coeffInfo = $cdsp->coeffInfo($_selected_coeff);
	foreach ($coeffInfo as  $param => $value) {
		$_coeff_info_html .= ucfirst($param) . ' = ' . $value . '<br/>';
	}
	$_coeff_info_html = rtrim($_coeff_info_html, '<br/>');
}

$_select['cdsp_use_default_device_yes'] .= "<input type=\"radio\" name=\"cdsp_use_default_device\" id=\"toggle-cdsp-use-default-device-1\" value=\"1\" " . (($_SESSION['cdsp_fix_playback'] == 'Yes') ? "checked=\"checked\"" : "") . ">\n";
$_select['cdsp_use_default_device_no']  .= "<input type=\"radio\" name=\"cdsp_use_default_device\" id=\"toggle-cdsp-use-default-device-2\" value=\"0\" " . (($_SESSION['cdsp_fix_playback'] == 'No') ? "checked=\"checked\"" : "") . ">\n";

$_select['version'] = $cdsp->version();

if ($_SESSION['camilladsp_quickconv']) {
	$quickConvConfig =$cdsp->stringToQuickConvolutionConfig($_SESSION['camilladsp_quickconv']);
	$_cdsp_qc_gain = $quickConvConfig['gain'];
	$quickConvIRL = $quickConvConfig['irl'];
	$quickConvIRR = $quickConvConfig['irr'];
	$quickConvIRType = $quickConvConfig['irtype'];
}

foreach ($configs as $configFile => $configName) {
	$selected = ($quickConvIRL == $configFile) ? 'selected' : '';
	$_select['cdsp_qc_ir_left'] .= sprintf("<option value='%s' %s>%s</option>\n", $configFile, $selected, $configFile);
}

foreach ($configs as $configFile => $configName) {
	$selected = ($quickConvIRR == $configFile) ? 'selected' : '';
	$_select['cdsp_qc_ir_right'] .= sprintf("<option value='%s' %s>%s</option>\n", $configFile, $selected, $configFile);
}

foreach ($cdsp->impulseResponseType() as $irType) {
	$selected = ($quickConvIRType == $irType) ? 'selected' : '';
	$_select['cdsp_qc_ir_type'] .= sprintf("<option value='%s' %s>%s</option>\n", $irType, $selected, $irType);
}

// Extract settings needed to show camilladsp configuration template:

//Get current output hardware device
$current_sound_device_number = $_SESSION['cardnum'];
$alsa_to_camilla_sample_formats = $cdsp->alsaToCamillaSampleFormatLut();

//Get best available output sample format
$supported_soundformats = $cdsp->detectSupportedSoundFormats();

$sound_device_supported_sample_formats = '';
foreach ($supported_soundformats as $cdsp_format) {
	$sound_device_supported_sample_formats .= $cdsp_format . ' ';
}

if (count($supported_soundformats) >= 1) {
	$sound_device_sample_format = $supported_soundformats[0];
	$sound_device_type = 'hw';
}

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

$camillaGuiStatus = $cdsp->getCamillaGuiStatus();
$camillaGuiClickHandler = " onchange=\"$('#btn-updat-camilla-gui').click();\"";
$camillaGuiExpertClickHandler = " onchange=\"$('#btn-updat-camilla-gui-expert').click();\"";
$_select['camillagui1'] .= "<input type=\"radio\" name=\"camillaguistatus\" id=\"toggle-camillagui1\" value=\"1\" " . (($camillaGuiStatus == CGUI_CHECK_ACTIVE) ? "checked=\"checked\"" : $camillaGuiClickHandler) . " >\n";
$_select['camillagui0'] .= "<input type=\"radio\" name=\"camillaguistatus\" id=\"toggle-camillagui2\" value=\"0\" " . (($camillaGuiStatus != CGUI_CHECK_ACTIVE) ? "checked=\"checked\"" : $camillaGuiClickHandler) . " >\n";
$_select['camillaguiexpert1'] .= "<input type=\"radio\" name=\"camillaguiexpertstatus\" id=\"toggle-camillaguiexpert1\" value=\"1\" " . (($cdsp->getGuiExpertMode() == true) ? "checked=\"checked\"" : $camillaGuiExpertClickHandler) . " >\n";
$_select['camillaguiexpert0'] .= "<input type=\"radio\" name=\"camillaguiexpertstatus\" id=\"toggle-camillaguiexpert2\" value=\"0\" " . (($cdsp->getGuiExpertMode() != true) ? "checked=\"checked\"" : $camillaGuiExpertClickHandler) . " >\n";

$_open_camillagui_disabled = $camillaGuiStatus == CGUI_CHECK_ACTIVE ? '': 'disabled';
$_camillagui_notfound_show = $camillaGuiStatus == CGUI_CHECK_NOTFOUND ? '': 'hide';
$_camillagui_status_problems = $camillaGuiStatus == CGUI_CHECK_ACTIVE || $camillaGuiStatus == CGUI_CHECK_INACTIVE || $camillaGuiStatus == CGUI_CHECK_NOTFOUND? 'hide': '';

// The extension mechanism is intended for dynamic adding function plugins for generating CamillaDSP configurations
$extensions_config = '/var/local/www/cdsp_extensions.json';
$extensions_html = '';
if(file_exists($extensions_config)) {
	$_cdsp_extensions_show = '';
	$extensions = json_decode(file_get_contents($extensions_config), true);

	$extension_template ='
	<label class="control-label">%s</label>
	<div class="controls">
		<a href="%s"><button class="btn btn-primary btn-medium" style="margin-top:0px;">Open</button></a>
		<div style="display: inline-block; vertical-align: top; margin-top: 2px;">
			<a aria-label="Help" class="info-toggle" data-cmd="info-%s" href="#notarget"><i class="fas fa-info-circle"></i></a>
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

$cdsp_log_level = $cdsp->getLogLevel();
$_cdsp_log_level .= "<option value=\"default\" " . (($cdsp_log_level == 'default') ? "selected" : "") . " >Default</option>\n";
$_cdsp_log_level .= "<option value=\"verbose\" " . (($cdsp_log_level == 'verbose') ? "selected" : "") . " >Verbose</option>\n";

setAltBackLink();

waitWorker(1, 'cdsp-config');

$tpl = "cdsp-config.html";
$section = basename(__FILE__, '.php');
storeBackLink($section, $tpl);

include('header.php');
eval("echoTemplate(\"" . getTemplate("templates/$tpl") . "\");");
include('footer.php');
