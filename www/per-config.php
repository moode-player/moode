<?php
/**
 * moOde audio player (C) 2014 Tim Curtis
 * http://moodeaudio.org
 *
 * tsunamp player ui (C) 2013 Andrea Coiutti & Simone De Gregori
 * http://www.tsunamp.com
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

phpSession('open');

// LOCAL DISPLAY

if (isset($_POST['update_localui'])) {
    if (isset($_POST['localui']) && $_POST['localui'] != $_SESSION['localui']) {
        submitJob('localui', $_POST['localui'], 'Settings updated', 'Restart may be required');
        phpSession('write', 'localui', $_POST['localui']);
    }
}

if (isset($_POST['update_wake_display'])) {
    if (isset($_POST['wake_display']) && $_POST['wake_display'] != $_SESSION['wake_display']) {
        phpSession('write', 'wake_display', $_POST['wake_display']);
		$_SESSION['notify']['title'] = 'Settings updated';
    }
}

if (isset($_POST['update_touchscn'])) {
    if (isset($_POST['touchscn']) && $_POST['touchscn'] != $_SESSION['touchscn']) {
        submitJob('touchscn', $_POST['touchscn'], 'Settings updated', 'Local display restarted');
        phpSession('write', 'touchscn', $_POST['touchscn']);
    }
}

if (isset($_POST['update_on_screen_kbd'])) {
    if (isset($_POST['on_screen_kbd']) && $_POST['on_screen_kbd'] != $_SESSION['on_screen_kbd']) {
		phpSession('write', 'on_screen_kbd', $_POST['on_screen_kbd']);
    }
}

if (isset($_POST['update_scnblank'])) {
    if (isset($_POST['scnblank']) && $_POST['scnblank'] != $_SESSION['scnblank']) {
        submitJob('scnblank', $_POST['scnblank'], 'Settings updated', 'Local display restarted');
        phpSession('write', 'scnblank', $_POST['scnblank']);
    }
}

if (isset($_POST['update_hdmi_enable_4kp60'])) {
    if (isset($_POST['hdmi_enable_4kp60']) && $_POST['hdmi_enable_4kp60'] != $_SESSION['hdmi_enable_4kp60']) {
        submitJob('hdmi_enable_4kp60', $_POST['hdmi_enable_4kp60'], 'Settings updated', 'Reboot required');
        phpSession('write', 'hdmi_enable_4kp60', $_POST['hdmi_enable_4kp60']);
    }
}

if (isset($_POST['update_scnbrightness'])) {
    if (isset($_POST['scnbrightness']) && $_POST['scnbrightness'] != $_SESSION['scnbrightness']) {
		submitJob('scnbrightness', $_POST['scnbrightness'], 'Settings updated');
		phpSession('write', 'scnbrightness', $_POST['scnbrightness']);
    }
}

if (isset($_POST['update_pixel_aspect_ratio'])) {
    if (isset($_POST['pixel_aspect_ratio']) && $_POST['pixel_aspect_ratio'] != $_SESSION['pixel_aspect_ratio']) {
		submitJob('pixel_aspect_ratio', $_POST['pixel_aspect_ratio'], 'Settings updated', 'Restart required');
		phpSession('write', 'pixel_aspect_ratio', $_POST['pixel_aspect_ratio']);
    }
}

if (isset($_POST['update_scnrotate'])) {
    if (isset($_POST['scnrotate']) && $_POST['scnrotate'] != $_SESSION['scnrotate']) {
		submitJob('scnrotate', $_POST['scnrotate'], 'Settings updated', 'Restart required');
		phpSession('write', 'scnrotate', $_POST['scnrotate']);
    }
}

if (isset($_POST['update_toggle_coverview'])) {
	$_SESSION['toggle_coverview'] = $_SESSION['toggle_coverview'] == '-off' ? '-on' : '-off';
	$result = sysCmd('/var/www/util/coverview.php ' . $_SESSION['toggle_coverview']);
}

if (isset($_POST['update_restart_localui'])) {
	submitJob('localui_restart', '', 'Local display restarted');
}

// OTHER PERIPHERALS

if (isset($_POST['update_gpio_svc']) && $_POST['gpio_svc'] != $_SESSION['gpio_svc']) {
	phpSession('write', 'gpio_svc', $_POST['gpio_svc']);
	submitJob('gpio_svc', $_POST['gpio_svc'], 'Settings updated');
}

if (isset($_POST['update_lcdup'])) {
	if (isset($_POST['lcdup']) && $_POST['lcdup'] != $_SESSION['lcdup']) {
		submitJob('lcdup', $_POST['lcdup'], 'Settings updated');
		phpSession('write', 'lcdup', $_POST['lcdup']);
		phpSession('write', 'extmeta', '1'); // Turn on external metadata generation
	}
}

phpSession('close');

// LOCAL DISPLAY

if ($_SESSION['feat_bitmask'] & FEAT_LOCALUI) {
	$_feat_localui = '';
	if ($_SESSION['localui'] == '1') {
		$_localui_btn_disable = '';
		$_localui_link_disable = '';
	} else {
		$_localui_btn_disable = 'disabled';
		$_localui_link_disable = 'onclick="return false;"';
	}
    $piModel = substr($_SESSION['hdwrrev'], 3, 1);
    $_hdmi_4kp60_btn_disable = $piModel == '4' ? '' : 'disable';

	$autoClick = " onchange=\"autoClick('#btn-set-localui');\"";
	$_select['localui_on']  .= "<input type=\"radio\" name=\"localui\" id=\"toggle-localui-1\" value=\"1\" " . (($_SESSION['localui'] == 1) ? "checked=\"checked\"" : "") . $autoClick . ">\n";
	$_select['localui_off'] .= "<input type=\"radio\" name=\"localui\" id=\"toggle-localui-2\" value=\"0\" " . (($_SESSION['localui'] == 0) ? "checked=\"checked\"" : "") . $autoClick . ">\n";

	$autoClick = " onchange=\"autoClick('#btn-set-wake-display');\" " . $_localui_btn_disable;
	$_select['wake_display_on']  .= "<input type=\"radio\" name=\"wake_display\" id=\"toggle-wake-display-1\" value=\"1\" " . (($_SESSION['wake_display'] == 1) ? "checked=\"checked\"" : "") . $autoClick . ">\n";
	$_select['wake_display_off'] .= "<input type=\"radio\" name=\"wake_display\" id=\"toggle-wake-display-2\" value=\"0\" " . (($_SESSION['wake_display'] == 0) ? "checked=\"checked\"" : "") . $autoClick . ">\n";

	$autoClick = " onchange=\"autoClick('#btn-set-touchscn');\" " . $_localui_btn_disable;
	$_select['touchscn_on']  .= "<input type=\"radio\" name=\"touchscn\" id=\"toggle-touchscn-1\" value=\"1\" " . (($_SESSION['touchscn'] == 1) ? "checked=\"checked\"" : "") . $autoClick . ">\n";
	$_select['touchscn_off'] .= "<input type=\"radio\" name=\"touchscn\" id=\"toggle-touchscn-2\" value=\"0\" " . (($_SESSION['touchscn'] == 0) ? "checked=\"checked\"" : "") . $autoClick . ">\n";

    $autoClick = " onchange=\"autoClick('#btn-set-on-screen-kbd');\" " . $_localui_btn_disable;
	$_select['on_screen_kbd_on']  .= "<input type=\"radio\" name=\"on_screen_kbd\" id=\"toggle-on-screen-kbd-1\" value=\"On\" " . (($_SESSION['on_screen_kbd'] == 'On') ? "checked=\"checked\"" : "") . $autoClick . ">\n";
	$_select['on_screen_kbd_off'] .= "<input type=\"radio\" name=\"on_screen_kbd\" id=\"toggle-on-screen-kbd-2\" value=\"Off\" " . (($_SESSION['on_screen_kbd'] == 'Off') ? "checked=\"checked\"" : "") . $autoClick . ">\n";

	$_select['scnblank'] .= "<option value=\"off\" " . (($_SESSION['scnblank'] == 'off') ? "selected" : "") . ">Never</option>\n";
	$_select['scnblank'] .= "<option value=\"10\" " . (($_SESSION['scnblank'] == '10') ? "selected" : "") . ">10 Secs</option>\n";
	$_select['scnblank'] .= "<option value=\"20\" " . (($_SESSION['scnblank'] == '20') ? "selected" : "") . ">20 Secs</option>\n";
	$_select['scnblank'] .= "<option value=\"30\" " . (($_SESSION['scnblank'] == '30') ? "selected" : "") . ">30 Secs</option>\n";
	$_select['scnblank'] .= "<option value=\"60\" " . (($_SESSION['scnblank'] == '60') ? "selected" : "") . ">1 Min</option>\n";
	$_select['scnblank'] .= "<option value=\"120\" " . (($_SESSION['scnblank'] == '120') ? "selected" : "") . ">2 Mins</option>\n";
	$_select['scnblank'] .= "<option value=\"300\" " . (($_SESSION['scnblank'] == '300') ? "selected" : "") . ">5 Mins</option>\n";
	$_select['scnblank'] .= "<option value=\"600\" " . (($_SESSION['scnblank'] == '600') ? "selected" : "") . ">10 Mins</option>\n";
	$_select['scnblank'] .= "<option value=\"1200\" " . (($_SESSION['scnblank'] == '1200') ? "selected" : "") . ">20 Mins</option>\n";
	$_select['scnblank'] .= "<option value=\"1800\" " . (($_SESSION['scnblank'] == '1800') ? "selected" : "") . ">30 Mins</option>\n";
	$_select['scnblank'] .= "<option value=\"3600\" " . (($_SESSION['scnblank'] == '3600') ? "selected" : "") . ">1 Hour</option>\n";

    $autoClick = " onchange=\"autoClick('#btn-set-hdmi-enable-4kp60');\" " . $_hdmi_4kp60_btn_disable;
	$_select['hdmi_enable_4kp60_on']  .= "<input type=\"radio\" name=\"hdmi_enable_4kp60\" id=\"toggle-hdmi-enable-4kp60-1\" value=\"on\" " . (($_SESSION['hdmi_enable_4kp60'] == 'on') ? "checked=\"checked\"" : "") . $autoClick . ">\n";
	$_select['hdmi_enable_4kp60_off'] .= "<input type=\"radio\" name=\"hdmi_enable_4kp60\" id=\"toggle-hdmi-enable-4kp60-2\" value=\"off\" " . (($_SESSION['hdmi_enable_4kp60'] == 'off') ? "checked=\"checked\"" : "") . $autoClick . ">\n";

	$_select['scnbrightness'] = $_SESSION['scnbrightness'];

	$_select['pixel_aspect_ratio'] .= "<option value=\"Default\" " . (($_SESSION['pixel_aspect_ratio'] == 'Default') ? "selected" : "") . ">Default</option>\n";
	$_select['pixel_aspect_ratio'] .= "<option value=\"Square\" " . (($_SESSION['pixel_aspect_ratio'] == 'Square') ? "selected" : "") . ">Square</option>\n";

	$_select['scnrotate'] .= "<option value=\"0\" " . (($_SESSION['scnrotate'] == '0') ? "selected" : "") . ">0 Deg</option>\n";
	$_select['scnrotate'] .= "<option value=\"180\" " . (($_SESSION['scnrotate'] == '180') ? "selected" : "") . ">180 Deg</option>\n";

	$_coverview_onoff = $_SESSION['toggle_coverview'] == '-off' ? 'Off' : 'On';
} else {
	$_feat_localui = 'hide';
}

// OTHER PERIPHERALS

// GPIO BUTTONS
if ($_SESSION['feat_bitmask'] & FEAT_GPIO) {
	$_feat_gpio = '';
	$autoClick = " onchange=\"autoClick('#btn-set-gpio-svc');\"";
	$_select['gpio_svc_on']  .= "<input type=\"radio\" name=\"gpio_svc\" id=\"toggle-gpio-svc-1\" value=\"1\" " . (($_SESSION['gpio_svc'] == 1) ? "checked=\"checked\"" : "") . $autoClick . ">\n";
	$_select['gpio_svc_off'] .= "<input type=\"radio\" name=\"gpio_svc\" id=\"toggle-gpio-svc-2\" value=\"0\" " . (($_SESSION['gpio_svc'] == 0) ? "checked=\"checked\"" : "") . $autoClick . ">\n";
} else {
	$_feat_gpio = 'hide';
}

// LCD UPDATER
$autoClick = " onchange=\"autoClick('#btn-set-lcdup');\"";
$_select['lcdup_on']  .= "<input type=\"radio\" name=\"lcdup\" id=\"toggle-lcdup-1\" value=\"1\" " . (($_SESSION['lcdup'] == 1) ? "checked=\"checked\"" : "") . $autoClick . ">\n";
$_select['lcdup_off'] .= "<input type=\"radio\" name=\"lcdup\" id=\"toggle-lcdup-2\" value=\"0\" " . (($_SESSION['lcdup'] == 0) ? "checked=\"checked\"" : "") . $autoClick . ">\n";

waitWorker('per-config');

$tpl = "per-config.html";
$section = basename(__FILE__, '.php');
storeBackLink($section, $tpl);

include('header.php');
eval("echoTemplate(\"" . getTemplate("templates/$tpl") . "\");");
include('footer.php');
