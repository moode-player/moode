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
require_once __DIR__ . '/inc/sql.php';

$dbh = sqlConnect();
phpSession('open_ro');

if (isset($_POST['save']) && $_POST['save'] == '1') {
	foreach (array_keys($_POST['config']) as $key) {
		sqlUpdate('cfg_gpio', $dbh, $key, $_POST['config'][$key]);
	}
	submitJob('gpio_svc', $_SESSION['gpio_svc'], 'Settings updated', ($_SESSION['gpio_svc'] == '1' ? 'GPIO controller restarted' : ''));
}

$result = sqlRead('cfg_gpio', $dbh);
$cfgGPIO = array();
foreach ($result as $row) {
	$cfgGPIO[$row['id']] = array('enabled' => $row['enabled'], 'pin' => $row['pin'], 'command' => $row['command'], 'param' => $row['param'], 'value' => $row['value']);
}

// Switch 1
$_select['sw_1_onoff_on']  .= "<input type=\"radio\" name=\"config[1][enabled]\" id=\"toggle-sw-1-onoff-1\" value=\"1\" " . (($cfgGPIO['1']['enabled'] == 1) ? "checked=\"checked\"" : "") . ">\n";
$_select['sw_1_onoff_off'] .= "<input type=\"radio\" name=\"config[1][enabled]\" id=\"toggle-sw-1-onoff-2\" value=\"0\" " . (($cfgGPIO['1']['enabled'] == 0) ? "checked=\"checked\"" : "") . ">\n";
$_select['sw_1_pin'] = $cfgGPIO['1']['pin'];
$_select['sw_1_cmd'] = $cfgGPIO['1']['command'];
// Switch 2
$_select['sw_2_onoff_on']  .= "<input type=\"radio\" name=\"config[2][enabled]\" id=\"toggle-sw-2-onoff-1\" value=\"1\" " . (($cfgGPIO['2']['enabled'] == 1) ? "checked=\"checked\"" : "") . ">\n";
$_select['sw_2_onoff_off'] .= "<input type=\"radio\" name=\"config[2][enabled]\" id=\"toggle-sw-2-onoff-2\" value=\"0\" " . (($cfgGPIO['2']['enabled'] == 0) ? "checked=\"checked\"" : "") . ">\n";
$_select['sw_2_pin'] = $cfgGPIO['2']['pin'];
$_select['sw_2_cmd'] = $cfgGPIO['2']['command'];
// Switch 3
$_select['sw_3_onoff_on']  .= "<input type=\"radio\" name=\"config[3][enabled]\" id=\"toggle-sw-3-onoff-1\" value=\"1\" " . (($cfgGPIO['3']['enabled'] == 1) ? "checked=\"checked\"" : "") . ">\n";
$_select['sw_3_onoff_off'] .= "<input type=\"radio\" name=\"config[3][enabled]\" id=\"toggle-sw-3-onoff-2\" value=\"0\" " . (($cfgGPIO['3']['enabled'] == 0) ? "checked=\"checked\"" : "") . ">\n";
$_select['sw_3_pin'] = $cfgGPIO['3']['pin'];
$_select['sw_3_cmd'] = $cfgGPIO['3']['command'];
// Switch 4
$_select['sw_4_onoff_on']  .= "<input type=\"radio\" name=\"config[4][enabled]\" id=\"toggle-sw-4-onoff-1\" value=\"1\" " . (($cfgGPIO['4']['enabled'] == 1) ? "checked=\"checked\"" : "") . ">\n";
$_select['sw_4_onoff_off'] .= "<input type=\"radio\" name=\"config[4][enabled]\" id=\"toggle-sw-4-onoff-2\" value=\"0\" " . (($cfgGPIO['4']['enabled'] == 0) ? "checked=\"checked\"" : "") . ">\n";
$_select['sw_4_pin'] = $cfgGPIO['4']['pin'];
$_select['sw_4_cmd'] = $cfgGPIO['4']['command'];
// Switch 5
$_select['sw_5_onoff_on']  .= "<input type=\"radio\" name=\"config[5][enabled]\" id=\"toggle-sw-5-onoff-1\" value=\"1\" " . (($cfgGPIO['5']['enabled'] == 1) ? "checked=\"checked\"" : "") . ">\n";
$_select['sw_5_onoff_off'] .= "<input type=\"radio\" name=\"config[5][enabled]\" id=\"toggle-sw-5-onoff-2\" value=\"0\" " . (($cfgGPIO['5']['enabled'] == 0) ? "checked=\"checked\"" : "") . ">\n";
$_select['sw_5_pin'] = $cfgGPIO['5']['pin'];
$_select['sw_5_cmd'] = $cfgGPIO['5']['command'];
// Switch 6
$_select['sw_6_onoff_on']  .= "<input type=\"radio\" name=\"config[6][enabled]\" id=\"toggle-sw-6-onoff-1\" value=\"1\" " . (($cfgGPIO['6']['enabled'] == 1) ? "checked=\"checked\"" : "") . ">\n";
$_select['sw_6_onoff_off'] .= "<input type=\"radio\" name=\"config[6][enabled]\" id=\"toggle-sw-6-onoff-2\" value=\"0\" " . (($cfgGPIO['6']['enabled'] == 0) ? "checked=\"checked\"" : "") . ">\n";
$_select['sw_6_pin'] = $cfgGPIO['6']['pin'];
$_select['sw_6_cmd'] = $cfgGPIO['6']['command'];
// Switch 7
$_select['sw_7_onoff_on']  .= "<input type=\"radio\" name=\"config[7][enabled]\" id=\"toggle-sw-7-onoff-1\" value=\"1\" " . (($cfgGPIO['7']['enabled'] == 1) ? "checked=\"checked\"" : "") . ">\n";
$_select['sw_7_onoff_off'] .= "<input type=\"radio\" name=\"config[7][enabled]\" id=\"toggle-sw-7-onoff-2\" value=\"0\" " . (($cfgGPIO['7']['enabled'] == 0) ? "checked=\"checked\"" : "") . ">\n";
$_select['sw_7_pin'] = $cfgGPIO['7']['pin'];
$_select['sw_7_cmd'] = $cfgGPIO['7']['command'];
// Switch 8
$_select['sw_8_onoff_on']  .= "<input type=\"radio\" name=\"config[8][enabled]\" id=\"toggle-sw-8-onoff-1\" value=\"1\" " . (($cfgGPIO['8']['enabled'] == 1) ? "checked=\"checked\"" : "") . ">\n";
$_select['sw_8_onoff_off'] .= "<input type=\"radio\" name=\"config[8][enabled]\" id=\"toggle-sw-8-onoff-2\" value=\"0\" " . (($cfgGPIO['8']['enabled'] == 0) ? "checked=\"checked\"" : "") . ">\n";
$_select['sw_8_pin'] = $cfgGPIO['8']['pin'];
$_select['sw_8_cmd'] = $cfgGPIO['8']['command'];
// Debounce
$_select['bounce_time'] = $cfgGPIO['99']['value'];

waitWorker('gpio-config');

$tpl = "gpio-config.html";
$section = basename(__FILE__, '.php');
storeBackLink($section, $tpl);

include('header.php');
eval("echoTemplate(\"" . getTemplate("templates/$tpl") . "\");");
include('footer.php');
