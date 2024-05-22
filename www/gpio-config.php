<?php
/*
 * SPDX-License-Identifier: GPL-3.0-or-later
 * Copyright 2014 The moOde audio player project / Tim Curtis
*/

require_once __DIR__ . '/inc/common.php';
require_once __DIR__ . '/inc/session.php';
require_once __DIR__ . '/inc/sql.php';

$dbh = sqlConnect();
phpSession('open_ro');

if (isset($_POST['save']) && $_POST['save'] == '1') {
	foreach (array_keys($_POST['config']) as $key) {
		if ($_POST['config'][$key]['pin'] <= '3') {
			$_POST['config'][$key]['pull'] = '22'; // Pins 2,3 have fixed pull-up resistors
		}
		sqlUpdate('cfg_gpio', $dbh, $key, $_POST['config'][$key]);
	}
	$notify = $_SESSION['gpio_svc'] == '1' ?
		array('title' => NOTIFY_TITLE_INFO, 'msg' => NAME_GPIO . NOTIFY_MSG_SVC_RESTARTED) :
		array('title' => '', 'msg' => '');
	submitJob('gpio_svc', $_SESSION['gpio_svc'], $notify['title'], $notify['msg']);

}

$result = sqlRead('cfg_gpio', $dbh);
$cfgGPIO = array();
foreach ($result as $row) {
	$cfgGPIO[$row['id']] = array('enabled' => $row['enabled'], 'pin' => $row['pin'], 'pull' => $row['pull'], 'command' => $row['command'], 'param' => $row['param'], 'value' => $row['value']);
}

// Button 1
$_select['btn_1_onoff_on']  .= "<input type=\"radio\" name=\"config[1][enabled]\" id=\"toggle-btn-1-onoff-1\" value=\"1\" " . (($cfgGPIO['1']['enabled'] == 1) ? "checked=\"checked\"" : "") . ">\n";
$_select['btn_1_onoff_off'] .= "<input type=\"radio\" name=\"config[1][enabled]\" id=\"toggle-btn-1-onoff-2\" value=\"0\" " . (($cfgGPIO['1']['enabled'] == 0) ? "checked=\"checked\"" : "") . ">\n";
$_select['btn_1_pin'] = $cfgGPIO['1']['pin'];
$_select['btn_1_pull'] .= "<option value=\"22\" " . (($cfgGPIO['1']['pull'] == '22') ? "selected" : "") . ">P-UP</option>\n";
$_select['btn_1_pull'] .= "<option value=\"21\" " . (($cfgGPIO['1']['pull'] == '21') ? "selected" : "") . ">P-DN</option>\n";
$_select['btn_1_cmd'] = $cfgGPIO['1']['command'];
// Button 2
$_select['btn_2_onoff_on']  .= "<input type=\"radio\" name=\"config[2][enabled]\" id=\"toggle-btn-2-onoff-1\" value=\"1\" " . (($cfgGPIO['2']['enabled'] == 1) ? "checked=\"checked\"" : "") . ">\n";
$_select['btn_2_onoff_off'] .= "<input type=\"radio\" name=\"config[2][enabled]\" id=\"toggle-btn-2-onoff-2\" value=\"0\" " . (($cfgGPIO['2']['enabled'] == 0) ? "checked=\"checked\"" : "") . ">\n";
$_select['btn_2_pin'] = $cfgGPIO['2']['pin'];
$_select['btn_2_pull'] .= "<option value=\"22\" " . (($cfgGPIO['2']['pull'] == '22') ? "selected" : "") . ">P-UP</option>\n";
$_select['btn_2_pull'] .= "<option value=\"21\" " . (($cfgGPIO['2']['pull'] == '21') ? "selected" : "") . ">P-DN</option>\n";
$_select['btn_2_cmd'] = $cfgGPIO['2']['command'];
// Button 3
$_select['btn_3_onoff_on']  .= "<input type=\"radio\" name=\"config[3][enabled]\" id=\"toggle-btn-3-onoff-1\" value=\"1\" " . (($cfgGPIO['3']['enabled'] == 1) ? "checked=\"checked\"" : "") . ">\n";
$_select['btn_3_onoff_off'] .= "<input type=\"radio\" name=\"config[3][enabled]\" id=\"toggle-btn-3-onoff-2\" value=\"0\" " . (($cfgGPIO['3']['enabled'] == 0) ? "checked=\"checked\"" : "") . ">\n";
$_select['btn_3_pin'] = $cfgGPIO['3']['pin'];
$_select['btn_3_pull'] .= "<option value=\"22\" " . (($cfgGPIO['3']['pull'] == '22') ? "selected" : "") . ">P-UP</option>\n";
$_select['btn_3_pull'] .= "<option value=\"21\" " . (($cfgGPIO['3']['pull'] == '21') ? "selected" : "") . ">P-DN</option>\n";
$_select['btn_3_cmd'] = $cfgGPIO['3']['command'];
// Button 4
$_select['btn_4_onoff_on']  .= "<input type=\"radio\" name=\"config[4][enabled]\" id=\"toggle-btn-4-onoff-1\" value=\"1\" " . (($cfgGPIO['4']['enabled'] == 1) ? "checked=\"checked\"" : "") . ">\n";
$_select['btn_4_onoff_off'] .= "<input type=\"radio\" name=\"config[4][enabled]\" id=\"toggle-btn-4-onoff-2\" value=\"0\" " . (($cfgGPIO['4']['enabled'] == 0) ? "checked=\"checked\"" : "") . ">\n";
$_select['btn_4_pin'] = $cfgGPIO['4']['pin'];
$_select['btn_4_pull'] .= "<option value=\"22\" " . (($cfgGPIO['4']['pull'] == '22') ? "selected" : "") . ">P-UP</option>\n";
$_select['btn_4_pull'] .= "<option value=\"21\" " . (($cfgGPIO['4']['pull'] == '21') ? "selected" : "") . ">P-DN</option>\n";
$_select['btn_4_cmd'] = $cfgGPIO['4']['command'];
// Button 5
$_select['btn_5_onoff_on']  .= "<input type=\"radio\" name=\"config[5][enabled]\" id=\"toggle-btn-5-onoff-1\" value=\"1\" " . (($cfgGPIO['5']['enabled'] == 1) ? "checked=\"checked\"" : "") . ">\n";
$_select['btn_5_onoff_off'] .= "<input type=\"radio\" name=\"config[5][enabled]\" id=\"toggle-btn-5-onoff-2\" value=\"0\" " . (($cfgGPIO['5']['enabled'] == 0) ? "checked=\"checked\"" : "") . ">\n";
$_select['btn_5_pin'] = $cfgGPIO['5']['pin'];
$_select['btn_5_pull'] .= "<option value=\"22\" " . (($cfgGPIO['5']['pull'] == '22') ? "selected" : "") . ">P-UP</option>\n";
$_select['btn_5_pull'] .= "<option value=\"21\" " . (($cfgGPIO['5']['pull'] == '21') ? "selected" : "") . ">P-DN</option>\n";
$_select['btn_5_cmd'] = $cfgGPIO['5']['command'];
// Button 6
$_select['btn_6_onoff_on']  .= "<input type=\"radio\" name=\"config[6][enabled]\" id=\"toggle-btn-6-onoff-1\" value=\"1\" " . (($cfgGPIO['6']['enabled'] == 1) ? "checked=\"checked\"" : "") . ">\n";
$_select['btn_6_onoff_off'] .= "<input type=\"radio\" name=\"config[6][enabled]\" id=\"toggle-btn-6-onoff-2\" value=\"0\" " . (($cfgGPIO['6']['enabled'] == 0) ? "checked=\"checked\"" : "") . ">\n";
$_select['btn_6_pin'] = $cfgGPIO['6']['pin'];
$_select['btn_6_pull'] .= "<option value=\"22\" " . (($cfgGPIO['6']['pull'] == '22') ? "selected" : "") . ">P-UP</option>\n";
$_select['btn_6_pull'] .= "<option value=\"21\" " . (($cfgGPIO['6']['pull'] == '21') ? "selected" : "") . ">P-DN</option>\n";
$_select['btn_6_cmd'] = $cfgGPIO['6']['command'];
// Button 7
$_select['btn_7_onoff_on']  .= "<input type=\"radio\" name=\"config[7][enabled]\" id=\"toggle-btn-7-onoff-1\" value=\"1\" " . (($cfgGPIO['7']['enabled'] == 1) ? "checked=\"checked\"" : "") . ">\n";
$_select['btn_7_onoff_off'] .= "<input type=\"radio\" name=\"config[7][enabled]\" id=\"toggle-btn-7-onoff-2\" value=\"0\" " . (($cfgGPIO['7']['enabled'] == 0) ? "checked=\"checked\"" : "") . ">\n";
$_select['btn_7_pin'] = $cfgGPIO['7']['pin'];
$_select['btn_7_pull'] .= "<option value=\"22\" " . (($cfgGPIO['7']['pull'] == '22') ? "selected" : "") . ">P-UP</option>\n";
$_select['btn_7_pull'] .= "<option value=\"21\" " . (($cfgGPIO['7']['pull'] == '21') ? "selected" : "") . ">P-DN</option>\n";
$_select['btn_7_cmd'] = $cfgGPIO['7']['command'];
// Button 8
$_select['btn_8_onoff_on']  .= "<input type=\"radio\" name=\"config[8][enabled]\" id=\"toggle-btn-8-onoff-1\" value=\"1\" " . (($cfgGPIO['8']['enabled'] == 1) ? "checked=\"checked\"" : "") . ">\n";
$_select['btn_8_onoff_off'] .= "<input type=\"radio\" name=\"config[8][enabled]\" id=\"toggle-btn-8-onoff-2\" value=\"0\" " . (($cfgGPIO['8']['enabled'] == 0) ? "checked=\"checked\"" : "") . ">\n";
$_select['btn_8_pin'] = $cfgGPIO['8']['pin'];
$_select['btn_8_pull'] .= "<option value=\"22\" " . (($cfgGPIO['8']['pull'] == '22') ? "selected" : "") . ">P-UP</option>\n";
$_select['btn_8_pull'] .= "<option value=\"21\" " . (($cfgGPIO['8']['pull'] == '21') ? "selected" : "") . ">P-DN</option>\n";
$_select['btn_8_cmd'] = $cfgGPIO['8']['command'];
// Debounce
$_select['bounce_time'] = $cfgGPIO['99']['value'];

waitWorker('gpio-config');

$tpl = "gpio-config.html";
$section = basename(__FILE__, '.php');
storeBackLink($section, $tpl);

include('header.php');
eval("echoTemplate(\"" . getTemplate("templates/$tpl") . "\");");
include('footer.php');
