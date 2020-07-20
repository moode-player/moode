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
$dbh = cfgdb_connect();

// apply setting changes
if (isset($_POST['save']) && $_POST['save'] == '1') {
	foreach (array_keys($_POST['config']) as $key) {
		//workerLog($key . ', ' . $_POST['config'][$key]['enabled'] . ', ' . $_POST['config'][$key]['pin'] . ', ' . $_POST['config'][$key]['command'] . ', ' . $_POST['config'][$key]['param'] . ', ' . $_POST['config'][$key]['value']);
		cfgdb_update('cfg_gpio', $dbh, $key, $_POST['config'][$key]);
	}
	// restart if indicated
	submitJob('gpio_svc', $_SESSION['gpio_svc'], 'Changes saved', ($_SESSION['gpio_svc'] == '1' ? 'GPIO button handler restarted' : ''));
}

session_write_close();

// load gpio config
$result = cfgdb_read('cfg_gpio', $dbh);
$cfg_gpio = array();

foreach ($result as $row) {
	$cfg_gpio[$row['id']] = array('enabled' => $row['enabled'], 'pin' => $row['pin'], 'command' => $row['command'], 'param' => $row['param'], 'value' => $row['value']);
	//workerLog($row['id'] . ', ' . $row['enabled'] . ', ' . $row['pin'] . ', ' . $row['command'] . ', ' . $row['param'] . ', ' . $row['value']);
}

// sw_1
$_select['sw_1_enabled1'] .= "<input type=\"radio\" name=\"config[1][enabled]\" id=\"toggle_sw_1_enabled1\" value=\"1\" " . (($cfg_gpio['1']['enabled'] == 1) ? "checked=\"checked\"" : "") . ">\n";
$_select['sw_1_enabled0'] .= "<input type=\"radio\" name=\"config[1][enabled]\" id=\"toggle_sw_1_enabled2\" value=\"0\" " . (($cfg_gpio['1']['enabled'] == 0) ? "checked=\"checked\"" : "") . ">\n";
$_select['sw_1_pin'] = $cfg_gpio['1']['pin'];
$_select['sw_1_cmd'] = $cfg_gpio['1']['command'];
// sw_2
$_select['sw_2_enabled1'] .= "<input type=\"radio\" name=\"config[2][enabled]\" id=\"toggle_sw_2_enabled1\" value=\"1\" " . (($cfg_gpio['2']['enabled'] == 1) ? "checked=\"checked\"" : "") . ">\n";
$_select['sw_2_enabled0'] .= "<input type=\"radio\" name=\"config[2][enabled]\" id=\"toggle_sw_2_enabled2\" value=\"0\" " . (($cfg_gpio['2']['enabled'] == 0) ? "checked=\"checked\"" : "") . ">\n";
$_select['sw_2_pin'] = $cfg_gpio['2']['pin'];
$_select['sw_2_cmd'] = $cfg_gpio['2']['command'];
// sw_3
$_select['sw_3_enabled1'] .= "<input type=\"radio\" name=\"config[3][enabled]\" id=\"toggle_sw_3_enabled1\" value=\"1\" " . (($cfg_gpio['3']['enabled'] == 1) ? "checked=\"checked\"" : "") . ">\n";
$_select['sw_3_enabled0'] .= "<input type=\"radio\" name=\"config[3][enabled]\" id=\"toggle_sw_3_enabled2\" value=\"0\" " . (($cfg_gpio['3']['enabled'] == 0) ? "checked=\"checked\"" : "") . ">\n";
$_select['sw_3_pin'] = $cfg_gpio['3']['pin'];
$_select['sw_3_cmd'] = $cfg_gpio['3']['command'];
// sw_4
$_select['sw_4_enabled1'] .= "<input type=\"radio\" name=\"config[4][enabled]\" id=\"toggle_sw_4_enabled1\" value=\"1\" " . (($cfg_gpio['4']['enabled'] == 1) ? "checked=\"checked\"" : "") . ">\n";
$_select['sw_4_enabled0'] .= "<input type=\"radio\" name=\"config[4][enabled]\" id=\"toggle_sw_4_enabled2\" value=\"0\" " . (($cfg_gpio['4']['enabled'] == 0) ? "checked=\"checked\"" : "") . ">\n";
$_select['sw_4_pin'] = $cfg_gpio['4']['pin'];
$_select['sw_4_cmd'] = $cfg_gpio['4']['command'];
// sw_5
$_select['sw_5_enabled1'] .= "<input type=\"radio\" name=\"config[5][enabled]\" id=\"toggle_sw_5_enabled1\" value=\"1\" " . (($cfg_gpio['5']['enabled'] == 1) ? "checked=\"checked\"" : "") . ">\n";
$_select['sw_5_enabled0'] .= "<input type=\"radio\" name=\"config[5][enabled]\" id=\"toggle_sw_5_enabled2\" value=\"0\" " . (($cfg_gpio['5']['enabled'] == 0) ? "checked=\"checked\"" : "") . ">\n";
$_select['sw_5_pin'] = $cfg_gpio['5']['pin'];
$_select['sw_5_cmd'] = $cfg_gpio['5']['command'];
// sw_6
$_select['sw_6_enabled1'] .= "<input type=\"radio\" name=\"config[6][enabled]\" id=\"toggle_sw_6_enabled1\" value=\"1\" " . (($cfg_gpio['6']['enabled'] == 1) ? "checked=\"checked\"" : "") . ">\n";
$_select['sw_6_enabled0'] .= "<input type=\"radio\" name=\"config[6][enabled]\" id=\"toggle_sw_6_enabled2\" value=\"0\" " . (($cfg_gpio['6']['enabled'] == 0) ? "checked=\"checked\"" : "") . ">\n";
$_select['sw_6_pin'] = $cfg_gpio['6']['pin'];
$_select['sw_6_cmd'] = $cfg_gpio['6']['command'];
// sw_7
$_select['sw_7_enabled1'] .= "<input type=\"radio\" name=\"config[7][enabled]\" id=\"toggle_sw_7_enabled1\" value=\"1\" " . (($cfg_gpio['7']['enabled'] == 1) ? "checked=\"checked\"" : "") . ">\n";
$_select['sw_7_enabled0'] .= "<input type=\"radio\" name=\"config[7][enabled]\" id=\"toggle_sw_7_enabled2\" value=\"0\" " . (($cfg_gpio['7']['enabled'] == 0) ? "checked=\"checked\"" : "") . ">\n";
$_select['sw_7_pin'] = $cfg_gpio['7']['pin'];
$_select['sw_7_cmd'] = $cfg_gpio['7']['command'];
// sw_8
$_select['sw_8_enabled1'] .= "<input type=\"radio\" name=\"config[8][enabled]\" id=\"toggle_sw_8_enabled1\" value=\"1\" " . (($cfg_gpio['8']['enabled'] == 1) ? "checked=\"checked\"" : "") . ">\n";
$_select['sw_8_enabled0'] .= "<input type=\"radio\" name=\"config[8][enabled]\" id=\"toggle_sw_8_enabled2\" value=\"0\" " . (($cfg_gpio['8']['enabled'] == 0) ? "checked=\"checked\"" : "") . ">\n";
$_select['sw_8_pin'] = $cfg_gpio['8']['pin'];
$_select['sw_8_cmd'] = $cfg_gpio['8']['command'];
// debounce
//$_select['debounce_value'] = $debounce_value;
$_select['bounce_time'] = $cfg_gpio['99']['value'];

waitWorker(1, 'gpio-config');

$tpl = "gpio-config.html";
$section = basename(__FILE__, '.php');
storeBackLink($section, $tpl);

include('header.php');
eval("echoTemplate(\"" . getTemplate("templates/$tpl") . "\");");
include('footer.min.php');
