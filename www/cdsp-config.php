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
 * 2021-MM-DD TC moOde 7.x.x
 *
 */

require_once dirname(__FILE__) . '/inc/playerlib.php';
require_once dirname(__FILE__) . '/inc/cdsp.php';

playerSession('open', '' ,'');
$cdsp = new CamillaDsp($_SESSION['camilladsp'], $_SESSION['cardnum'], $_SESSION['camilladsp_quickconv']);
$selectedConfig = isset($_POST['cdsp-config']) ? $_POST['cdsp-config']: NULL;

/**
 * Post parameter processing
 */

// Save
if (isset($_POST['save']) && $_POST['save'] == '1') {
	if( isset($_POST['cdsp_basiccon_gain']) && isset($_POST['cdsp_basicconv_left']) && isset($_POST['cdsp_basicconv_right'])) {
		$gain = $_POST['cdsp_basiccon_gain'];
		$convL = $_POST['cdsp_basicconv_left'];
		$convR = $_POST['cdsp_basicconv_right'];
		$convT = $_POST['cdsp_basicconv_type'];

		$cfg = $gain . ';' . $convL . ';' . $convR . ';' . $convT;
		$cdsp->setQuickConvolutionConfig( $cdsp->stringToQuickConvolutionConfig($cfg) );
		playerSession('write', 'camilladsp_quickconv', $cfg);
	}

	if (isset($_POST['cdsp-mode'])) {
		$currentMode = $_SESSION['camilladsp'];
		playerSession('write', 'camilladsp', $_POST['cdsp-mode']);
		$cdsp->selectConfig($_POST['cdsp-mode']);
		if ($_SESSION['cdsp_fix_playback'] == 'Yes' ) {
			$cdsp->setPlaybackDevice($_SESSION['cardnum']);
		}

		if ( $_SESSION['camilladsp'] != $currentMode && ( $_SESSION['camilladsp'] == 'off' || $currentMode == 'off')) {
			submitJob('camilladsp', $_POST['cdsp-mode'], 'CamillaDSP ' . $cdsp->getConfigLabel($_POST['cdsp-mode']), '');
		} else {
			$cdsp->reloadConfig();
		}
	}

	if (isset($_POST['cdsp_playbackdevice'])) {
		$patchPlaybackDevice = $_POST['cdsp_playbackdevice'];
		playerSession('write', 'cdsp_fix_playback', $patchPlaybackDevice == "1" ? "Yes" : "No");
		if ($_SESSION['cdsp_fix_playback'] == 'Yes' ) {
		   $cdsp->setPlaybackDevice($_SESSION['cardnum']);
		}
	}
}

// Check
else if ($selectedConfig && isset($_POST['check']) && $_POST['check'] == '1') {
	$checkResult = $cdsp->checkConfigFile($selectedConfig);

	$selectedConfigLabel = $cdsp->getConfigLabel( $selectedConfig );
	if($checkResult['valid'] == True) {
		$_SESSION['notify']['title'] =   htmlentities('Pipeline configuration \"' . $selectedConfigLabel . '\" is valid');
	}else {
		$_SESSION['notify']['title'] = htmlentities('Pipeline configuration \"' . $selectedConfigLabel . '\" is NOT valid');
	}
}
// Import (Upload)
else if (isset($_FILES['pipelineconfig']) && isset($_POST['import']) && $_POST['import'] == '1') {
	$configFileBaseName = $_FILES["pipelineconfig"]["name"];
	$configFileName = $cdsp->getConfigsLocationsFileName() . $configFileBaseName;
	move_uploaded_file($_FILES["pipelineconfig"]["tmp_name"], $configFileName);

	if( $_SESSION['camilladsp'] == $configFileBaseName ) { // if upload active config, fix it
		if ($_SESSION['cdsp_fix_playback'] == 'Yes' ) {
			$cdsp->setPlaybackDevice($_SESSION['cardnum']);
		}
		$cdsp->reloadConfig();
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

	if( $_SESSION['camilladsp'] != $selectedConfig ) { // can't remove active config
		$configFileName = $cdsp->getConfigsLocationsFileName() . $selectedConfig;
		unlink($configFileName);
		$_SESSION['notify']['title'] = htmlentities('Remove configuration \"' . $selectedConfig . '\" completed');
		$selectedConfig = NULL;
	}
	else {
		$_SESSION['notify']['title'] = htmlentities('Cannot remove active configuration \"' . $selectedConfig . '\"');
	}
}
// Copy pipeline
else if ($selectedConfig && isset($_POST['copypipeline']) && $_POST['copypipeline'] == '1') {
	$cdsp->copyConfig($selectedConfig, $_POST['new-pipelinename'] . '.yml');
	$selectedConfig = $_POST['new-pipelinename'] . '.yml';
}
// Coeffs import (Upload)
else if (isset($_FILES['coeffsfile']) && isset($_POST['import']) && $_POST['import'] == '1') {
	$configFileName = $cdsp->getCoeffsLocation() . $_FILES["coeffsfile"]["name"];
	move_uploaded_file($_FILES["coeffsfile"]["tmp_name"], $configFileName);
	$_SESSION['notify']['title'] =  htmlentities('Import \"' . $_FILES["coeffsfile"]["name"] . '\" completed');
}
// Coeffs export (Download)
else if (isset($_POST['cdsp-coeffs']) && isset($_POST['export']) && $_POST['export'] == '1') {
	$configFileName = $cdsp->getCoeffsLocation() . $_POST['cdsp-coeffs'];

	header("Content-Description: File Transfer");
	header("Content-Type: application/binary");
	header("Content-Disposition: attachment; filename=\"". $_POST['cdsp-coeffs'] ."\"");

	readfile ($configFileName);
 	exit();
}
// Coeffs remove
else if (isset($_POST['cdsp-coeffs']) && isset($_POST['remove']) && $_POST['remove'] == '1') {
	$configFileName = $cdsp->getCoeffsLocation() . $_POST['cdsp-coeffs'];
	unlink($configFileName);
	$_SESSION['notify']['title'] = htmlentities('Remove configuration \"' . $_POST['cdsp-coeffs'] . '\" completed');
}
else if (isset($_POST['cdsp-coeffs']) && isset($_POST['info']) && $_POST['info'] == '1') {
	$coeffInfo = $cdsp->coeffInfo($_POST['cdsp-coeffs']);

	$coeffInfoHtml ='Info:<br/>';
	foreach ($coeffInfo as  $param=>$value) {
		$coeffInfoHtml .= ''. $param . ' = ' . $value. '<br/>';
	}
}
// camillagui status toggle
else if (isset($_POST['camillaguistatus']) && isset($_POST['updatecamillagui']) && $_POST['updatecamillagui'] == '1') {
 	$cdsp->changeCamillaStatus($_POST['camillaguistatus']);
}
else if (isset($_POST['camillaguiexpertstatus']) && isset($_POST['updatecamillaguiexpert']) && $_POST['updatecamillaguiexpert'] == '1') {
	$cdsp->setGuiExpertMode($_POST['camillaguiexpertstatus'] == '1');
}




/**
 * Generate data for html templating
 */

$configs = $cdsp->getAvailableConfigs();
foreach ($configs as $config_file=>$config_name) {
	$selected = ($_SESSION['camilladsp'] == $config_file) ? 'selected' : '';
	$_select['cdsp_mode'] .= sprintf("<option value='%s' %s>%s</option>\n", $config_file, $selected, $config_name);
}

$configs = $cdsp->getAvailableConfigsRaw();
$_selected = NULL;
foreach ($configs as $config_file=>$config_name) {
	$selected = ($selectedConfig == $config_file || ($selectedConfig == NULL && $_selected == NULL) ) ? 'selected' : '';
	$_select['cdsp_configs'] .= sprintf("<option value='%s' %s>%s</option>\n", $config_file, $selected, $config_name);
	if ($selected == 'selected') {
		$_selected = $selected;
		$selectedConfig = $config_file;
	}
}

$configs = $cdsp->getAvailableCoeffs();
$_selected_coeff = NULL;
foreach ($configs as $config_file=>$config_name) {
	$selected = ($_POST['cdsp-coeffs'] == $config_file || (isset($_POST['cdsp-coeffs']) == false && $_selected_coeff == NULL) ) ? 'selected' : '';
	$_select['cdsp_coeffs'] .= sprintf("<option value='%s' %s>%s</option>\n", $config_file, $selected, $config_file);
	if ($selected == 'selected') {
		$_selected_coeff = $config_file;
	}
}

$_select['cdsp_patch_playback_device1'] .= "<input type=\"radio\" name=\"cdsp_playbackdevice\" id=\"toggle-cdsp-playbackdevice1\" value=\"1\" " . (($_SESSION['cdsp_fix_playback'] == 'Yes') ? "checked=\"checked\"" : "") . ">\n";
$_select['cdsp_patch_playback_device0'] .= "<input type=\"radio\" name=\"cdsp_playbackdevice\" id=\"toggle-cdsp-playbackdevice2\" value=\"0\" " . (($_SESSION['cdsp_fix_playback'] == 'No') ? "checked=\"checked\"" : "") . ">\n";

$_select['version'] = $cdsp->version();


if( $_SESSION['camilladsp_quickconv'] ) {
	$quickConvConfig =$cdsp->stringToQuickConvolutionConfig($_SESSION['camilladsp_quickconv']);
	$_quickconv_gain_value = $quickConvConfig['gain'];
	$quickconv_left_value = $quickConvConfig['irl'];
	$quickconv_right_value = $quickConvConfig['irr'];
	$quickconv_ir_type_value = $quickConvConfig['irtype'];
}

foreach ($configs as $config_file=>$config_name) {
	$selected = ($quickconv_left_value == $config_file ) ? 'selected' : '';
	$_select['cdsp_quickconv_irl'] .= sprintf("<option value='%s' %s>%s</option>\n", $config_file, $selected, $config_file);
}

foreach ($configs as $config_file=>$config_name) {
	$selected = ($quickconv_right_value == $config_file ) ? 'selected' : '';
	$_select['cdsp_quickconv_irr'] .= sprintf("<option value='%s' %s>%s</option>\n", $config_file, $selected, $config_file);
}

foreach ($cdsp->impulseResponseType() as $ir_type) {
	$selected = ($quickconv_ir_type_value == $ir_type ) ? 'selected' : '';
	$_select['cdsp_quickconv_irtype'] .= sprintf("<option value='%s' %s>%s</option>\n", $ir_type, $selected, $ir_type);
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

if(count($supported_soundformats) >= 1) {
	$sound_device_sample_format = $supported_soundformats[0];
	$sound_device_type = 'hw';
}

function checkResultToHtml($checkResult) {
	$message = '';
	$checkMsgRaw = implode('<br>', $checkResult['msg']);
	if( $checkResult['valid'] == CDSP_CHECK_NOTFOUND) {
		$message = "<span style='color: red'>&#10007;</span> ".$checkMsgRaw;
	} elseif( $checkResult['valid'] == CDSP_CHECK_VALID) {
		$message = "<span style='color: green'>&check;</span> " . $checkMsgRaw;
	} else {
		$message = "<span style='color: red'>&#10007;</span> " . $checkMsgRaw;
	}
	return $message;
}

$checkMsg = '';
$checkMsgQuickConvolution = '';
if( $selectedConfig) {
	if(isset($checkResult) == false) {
		$checkResult = $cdsp->checkConfigFile($selectedConfig);
	}
	$checkMsg = checkResultToHtml($checkResult );
}

if ( $cdsp->isQuickConvolutionActive() ) {
	$checkMsgQuickConvolution = checkResultToHtml( $cdsp->checkConfigFile( $cdsp->getConfig() ) );
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
session_write_close();

waitWorker(1, 'cdsp-config');

$tpl = "cdsp-config.html";
$section = basename(__FILE__, '.php');
storeBackLink($section, $tpl);

include('header.php');
eval("echoTemplate(\"" . getTemplate("templates/$tpl") . "\");");
include('footer.php');
