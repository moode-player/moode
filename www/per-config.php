<?php
/*
 * SPDX-License-Identifier: GPL-3.0-or-later
 * Copyright 2014 The moOde audio player project / Tim Curtis
 * Copyright 2013 The tsunamp player ui / Andrea Coiutti & Simone De Gregori
*/

require_once __DIR__ . '/inc/common.php';
require_once __DIR__ . '/inc/session.php';
require_once __DIR__ . '/inc/sql.php';

phpSession('open');

chkVariables($_POST);

// LOCAL DISPLAY

if (isset($_POST['update_localui'])) {
    if (isset($_POST['localui']) && $_POST['localui'] != $_SESSION['localui']) {
        if ($_POST['localui'] == '1') {
            submitJob('localui', $_POST['localui'], NOTIFY_TITLE_INFO, NOTIFY_MSG_LOCALUI_STARTING);
        } else {
            submitJob('localui', $_POST['localui']);
        }
        phpSession('write', 'localui', $_POST['localui']);
    }
}

if (isset($_POST['update_wake_display'])) {
    if (isset($_POST['wake_display']) && $_POST['wake_display'] != $_SESSION['wake_display']) {
        phpSession('write', 'wake_display', $_POST['wake_display']);
    }
}

if (isset($_POST['update_touchscn'])) {
    if (isset($_POST['touchscn']) && $_POST['touchscn'] != $_SESSION['touchscn']) {
        submitJob('touchscn', $_POST['touchscn'], NOTIFY_TITLE_INFO, NAME_LOCAL_DISPLAY . NOTIFY_MSG_SVC_RESTARTED);
        phpSession('write', 'touchscn', $_POST['touchscn']);
    }
}

if (isset($_POST['update_on_screen_kbd'])) {
    if (isset($_POST['on_screen_kbd']) && $_POST['on_screen_kbd'] != $_SESSION['on_screen_kbd']) {
		$_SESSION['on_screen_kbd'] = $_POST['on_screen_kbd'];
    }
}

if (isset($_POST['update_scnblank'])) {
    if (isset($_POST['scnblank']) && $_POST['scnblank'] != $_SESSION['scnblank']) {
        submitJob('scnblank', $_POST['scnblank'], NOTIFY_TITLE_INFO, NAME_LOCAL_DISPLAY . NOTIFY_MSG_SVC_RESTARTED);
        phpSession('write', 'scnblank', $_POST['scnblank']);
    }
}

if (isset($_POST['update_hdmi_scn_orient'])) {
    if (isset($_POST['hdmi_scn_orient']) && $_POST['hdmi_scn_orient'] != $_SESSION['hdmi_scn_orient']) {
        phpSession('write', 'hdmi_scn_orient', $_POST['hdmi_scn_orient']);
        submitJob('hdmi_scn_orient', $_POST['hdmi_scn_orient'], NOTIFY_TITLE_INFO, NAME_LOCAL_DISPLAY . NOTIFY_MSG_SVC_RESTARTED);
    }
}

if (isset($_POST['update_hdmi_cec'])) {
    if (isset($_POST['hdmi_cec']) && $_POST['hdmi_cec'] != $_SESSION['hdmi_cec']) {
        phpSession('write', 'hdmi_cec', $_POST['hdmi_cec']);
        $_SESSION['notify']['title'] = NOTIFY_TITLE_INFO;
        $_SESSION['notify']['msg'] = NOTIFY_MSG_SYSTEM_RESTART_REQD;
    }
}

if (isset($_POST['update_hdmi_enable_4kp60'])) {
    if (isset($_POST['hdmi_enable_4kp60']) && $_POST['hdmi_enable_4kp60'] != $_SESSION['hdmi_enable_4kp60']) {
        $_SESSION['hdmi_enable_4kp60'] = $_POST['hdmi_enable_4kp60'];
        submitJob('hdmi_enable_4kp60', $_POST['hdmi_enable_4kp60'], NOTIFY_TITLE_INFO, NOTIFY_MSG_SYSTEM_RESTART_REQD);
    }
}

if (isset($_POST['update_disable_gpu_chromium'])) {
    if (isset($_POST['disable_gpu_chromium']) && $_POST['disable_gpu_chromium'] != $_SESSION['disable_gpu_chromium']) {
        $_SESSION['disable_gpu_chromium'] = $_POST['disable_gpu_chromium'];
        if ($_SESSION['localui'] == '1') {
            submitJob('disable_gpu_chromium', $_POST['disable_gpu_chromium'], NOTIFY_TITLE_INFO, NAME_LOCAL_DISPLAY . NOTIFY_MSG_SVC_RESTARTED);
        } else {
            submitJob('disable_gpu_chromium', $_POST['disable_gpu_chromium']);
        }
    }
}

if (isset($_POST['update_rpi_scntype'])) {
    if (isset($_POST['rpi_scntype']) && $_POST['rpi_scntype'] != $_SESSION['rpi_scntype']) {
		$_SESSION['rpi_scntype'] = $_POST['rpi_scntype'];
    }
}

if (isset($_POST['update_rpi_backlight'])) {
    if (isset($_POST['rpi_backlight']) && $_POST['rpi_backlight'] != $_SESSION['rpi_backlight']) {
        $_SESSION['rpi_backlight'] = $_POST['rpi_backlight'];
		submitJob('rpi_backlight', $_POST['rpi_backlight'], NOTIFY_TITLE_INFO, NOTIFY_MSG_SYSTEM_RESTART_REQD);
    }
}

if (isset($_POST['update_scnbrightness'])) {
    if (isset($_POST['scnbrightness']) && $_POST['scnbrightness'] != $_SESSION['scnbrightness']) {
		submitJob('scnbrightness', $_POST['scnbrightness']);
		phpSession('write', 'scnbrightness', $_POST['scnbrightness']);
    }
}

// No solution with KMS driver as of r902
/*if (isset($_POST['update_pixel_aspect_ratio'])) {
    if (isset($_POST['pixel_aspect_ratio']) && $_POST['pixel_aspect_ratio'] != $_SESSION['pixel_aspect_ratio']) {
		submitJob('pixel_aspect_ratio', $_POST['pixel_aspect_ratio'], NOTIFY_TITLE_INFO, NOTIFY_MSG_SYSTEM_RESTART_REQD);
		phpSession('write', 'pixel_aspect_ratio', $_POST['pixel_aspect_ratio']);
    }
}*/

if (isset($_POST['update_scnrotate'])) {
    if (isset($_POST['scnrotate']) && $_POST['scnrotate'] != $_SESSION['scnrotate']) {
		submitJob('scnrotate', $_POST['scnrotate'], NOTIFY_TITLE_INFO, NOTIFY_MSG_SYSTEM_RESTART_REQD);
		phpSession('write', 'scnrotate', $_POST['scnrotate']);
    }
}

if (isset($_POST['update_toggle_coverview'])) {
	$_SESSION['toggle_coverview'] = $_SESSION['toggle_coverview'] == '-off' ? '-on' : '-off';
	$result = sysCmd('/var/www/util/coverview.php ' . $_SESSION['toggle_coverview']);
}

if (isset($_POST['update_restart_localui'])) {
	submitJob('localui_restart', '', NOTIFY_TITLE_INFO, NAME_LOCAL_DISPLAY . NOTIFY_MSG_SVC_MANUAL_RESTART);
}

// OTHER PERIPHERALS

// USB volume knob
if (isset($_POST['update_usb_volknob']) && $_POST['usb_volknob'] != $_SESSION['usb_volknob']) {
	submitJob('usb_volknob', $_POST['usb_volknob']);
	$_SESSION['usb_volknob'] = $_POST['usb_volknob'];
}
// Rotary encoder service
if (isset($_POST['update_rotenc'])) {
	if (isset($_POST['rotaryenc']) && $_POST['rotaryenc'] != $_SESSION['rotaryenc']) {
		$_SESSION['rotaryenc'] = $_POST['rotaryenc'];
		submitJob('rotaryenc', $_POST['rotaryenc']);
	}
}
// Rotary encoder settings
if (isset($_POST['update_rotenc_params'])) {
	if (isset($_POST['rotenc_params']) && $_POST['rotenc_params'] != $_SESSION['rotenc_params']) {
		phpSession('write', 'rotenc_params', $_POST['rotenc_params']);
		submitJob('rotaryenc', $_POST['rotaryenc']);
	}
}
// GPIO buttons
if (isset($_POST['update_gpio_svc']) && $_POST['gpio_svc'] != $_SESSION['gpio_svc']) {
	phpSession('write', 'gpio_svc', $_POST['gpio_svc']);
	submitJob('gpio_svc', $_POST['gpio_svc']);
}
// LCD updater
if (isset($_POST['update_lcdup'])) {
	if (isset($_POST['lcdup']) && $_POST['lcdup'] != $_SESSION['lcdup']) {
		submitJob('lcdup', $_POST['lcdup']);
		phpSession('write', 'lcdup', $_POST['lcdup']);
		phpSession('write', 'extmeta', '1'); // Turn on external metadata generation
	}
}

phpSession('close');

// LOCAL DISPLAY

if ($_SESSION['feat_bitmask'] & FEAT_LOCALUI) {
	$_feat_localui = '';
	if ($_SESSION['localui'] == '1') {
		$_ctl_disabled = '';
		$_link_disabled = '';
        $_rpi_scntype_disable = $_SESSION['scnrotate'] == '0' ? '' : 'disabled';
        $_screen_res = '<span class="config-help-static">Resolution: '
            . sysCmd("kmsprint | awk '$1 == \"FB\" {print $3}' | awk -F\"x\" '{print $1\"x\"$2}'")[0]
            . '<a aria-label="Refresh" href="per-config.php"><i class="fa-solid fa-sharp fa-redo dx"></i></a>'
            . '</span>';
	} else {
		$_ctl_disabled = 'disabled';
		$_link_disabled = 'onclick="return false;"';
        $_rpi_scntype_disable = 'disabled';
        $_screen_size = '';
	}
    $piModel = substr($_SESSION['hdwrrev'], 3, 1);
    $_hdmi_4kp60_btn_disable = $piModel == '4' ? '' : 'disabled';

	$autoClick = " onchange=\"autoClick('#btn-set-localui');\"";
	$_select['localui_on']  .= "<input type=\"radio\" name=\"localui\" id=\"toggle-localui-1\" value=\"1\" " . (($_SESSION['localui'] == 1) ? "checked=\"checked\"" : "") . $autoClick . ">\n";
	$_select['localui_off'] .= "<input type=\"radio\" name=\"localui\" id=\"toggle-localui-2\" value=\"0\" " . (($_SESSION['localui'] == 0) ? "checked=\"checked\"" : "") . $autoClick . ">\n";

	$autoClick = " onchange=\"autoClick('#btn-set-wake-display');\" " . $_ctl_disabled;
	$_select['wake_display_on']  .= "<input type=\"radio\" name=\"wake_display\" id=\"toggle-wake-display-1\" value=\"1\" " . (($_SESSION['wake_display'] == 1) ? "checked=\"checked\"" : "") . $autoClick . ">\n";
	$_select['wake_display_off'] .= "<input type=\"radio\" name=\"wake_display\" id=\"toggle-wake-display-2\" value=\"0\" " . (($_SESSION['wake_display'] == 0) ? "checked=\"checked\"" : "") . $autoClick . ">\n";

	$autoClick = " onchange=\"autoClick('#btn-set-touchscn');\" " . $_ctl_disabled;
	$_select['touchscn_on']  .= "<input type=\"radio\" name=\"touchscn\" id=\"toggle-touchscn-1\" value=\"1\" " . (($_SESSION['touchscn'] == 1) ? "checked=\"checked\"" : "") . $autoClick . ">\n";
	$_select['touchscn_off'] .= "<input type=\"radio\" name=\"touchscn\" id=\"toggle-touchscn-2\" value=\"0\" " . (($_SESSION['touchscn'] == 0) ? "checked=\"checked\"" : "") . $autoClick . ">\n";

    $autoClick = " onchange=\"autoClick('#btn-set-on-screen-kbd');\" " . $_ctl_disabled;
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

    $_select['hdmi_scn_orient'] .= "<option value=\"landscape\" " . (($_SESSION['hdmi_scn_orient'] == 'landscape') ? "selected" : "") . ">Landscape</option>\n";
    $_select['hdmi_scn_orient'] .= "<option value=\"portrait\" " . (($_SESSION['hdmi_scn_orient'] == 'portrait') ? "selected" : "") . ">Portrait</option>\n";

    $autoClick = " onchange=\"autoClick('#btn-set-hdmi-cec');\" " . $_hdmi_cec_btn_disable;
	$_select['hdmi_cec_on']  .= "<input type=\"radio\" name=\"hdmi_cec\" id=\"toggle-hdmi-cec-1\" value=\"on\" " . (($_SESSION['hdmi_cec'] == 'on') ? "checked=\"checked\"" : "") . $autoClick . ">\n";
	$_select['hdmi_cec_off'] .= "<input type=\"radio\" name=\"hdmi_cec\" id=\"toggle-hdmi-cec-2\" value=\"off\" " . (($_SESSION['hdmi_cec'] == 'off') ? "checked=\"checked\"" : "") . $autoClick . ">\n";

    $autoClick = " onchange=\"autoClick('#btn-set-hdmi-enable-4kp60');\" " . $_hdmi_4kp60_btn_disable;
	$_select['hdmi_enable_4kp60_on']  .= "<input type=\"radio\" name=\"hdmi_enable_4kp60\" id=\"toggle-hdmi-enable-4kp60-1\" value=\"on\" " . (($_SESSION['hdmi_enable_4kp60'] == 'on') ? "checked=\"checked\"" : "") . $autoClick . ">\n";
	$_select['hdmi_enable_4kp60_off'] .= "<input type=\"radio\" name=\"hdmi_enable_4kp60\" id=\"toggle-hdmi-enable-4kp60-2\" value=\"off\" " . (($_SESSION['hdmi_enable_4kp60'] == 'off') ? "checked=\"checked\"" : "") . $autoClick . ">\n";

    $autoClick = " onchange=\"autoClick('#btn-set-disable-gpu-chromium');\"";
	$_select['disable_gpu_chromium_on']  .= "<input type=\"radio\" name=\"disable_gpu_chromium\" id=\"toggle-disable-gpu-chromium-1\" value=\"on\" " . (($_SESSION['disable_gpu_chromium'] == 'on') ? "checked=\"checked\"" : "") . $autoClick . ">\n";
	$_select['disable_gpu_chromium_off'] .= "<input type=\"radio\" name=\"disable_gpu_chromium\" id=\"toggle-disable-gpu-chromium-2\" value=\"off\" " . (($_SESSION['disable_gpu_chromium'] == 'off') ? "checked=\"checked\"" : "") . $autoClick . ">\n";

    $_select['rpi_scntype'] .= "<option value=\"1\" " . (($_SESSION['rpi_scntype'] == '1') ? "selected" : "") . ">Pi Touch</option>\n";
    $_select['rpi_scntype'] .= "<option value=\"2\" " . (($_SESSION['rpi_scntype'] == '2') ? "selected" : "") . ">Pi Touch 2</option>\n";

    $autoClick = " onchange=\"autoClick('#btn-set-rpi-backlight');\"";
	$_select['rpi_backlight_on']  .= "<input type=\"radio\" name=\"rpi_backlight\" id=\"toggle-rpi-backlight-1\" value=\"on\" " . (($_SESSION['rpi_backlight'] == 'on') ? "checked=\"checked\"" : "") . $autoClick . ">\n";
	$_select['rpi_backlight_off'] .= "<input type=\"radio\" name=\"rpi_backlight\" id=\"toggle-rpi-backlight-2\" value=\"off\" " . (($_SESSION['rpi_backlight'] == 'off') ? "checked=\"checked\"" : "") . $autoClick . ">\n";

	$_select['scnbrightness'] = $_SESSION['scnbrightness'];

    // No solution with KMS driver as of r902
	//$_select['pixel_aspect_ratio'] .= "<option value=\"Default\" " . (($_SESSION['pixel_aspect_ratio'] == 'Default') ? "selected" : "") . ">Default</option>\n";
	//$_select['pixel_aspect_ratio'] .= "<option value=\"Square\" " . (($_SESSION['pixel_aspect_ratio'] == 'Square') ? "selected" : "") . ">Square</option>\n";

    if ($_SESSION['rpi_scntype'] == '1') {
        $_select['scnrotate'] .= "<option value=\"0\" " . (($_SESSION['scnrotate'] == '0') ? "selected" : "") . ">0 Deg</option>\n";
        $_select['scnrotate'] .= "<option value=\"180\" " . (($_SESSION['scnrotate'] == '180') ? "selected" : "") . ">180 Deg</option>\n";
    } else {
        $_select['scnrotate'] .= "<option value=\"0\" " . (($_SESSION['scnrotate'] == '0') ? "selected" : "") . ">0 Deg</option>\n";
    	$_select['scnrotate'] .= "<option value=\"90\" " . (($_SESSION['scnrotate'] == '90') ? "selected" : "") . ">90 Deg</option>\n";
        $_select['scnrotate'] .= "<option value=\"180\" " . (($_SESSION['scnrotate'] == '180') ? "selected" : "") . ">180 Deg</option>\n";
        $_select['scnrotate'] .= "<option value=\"270\" " . (($_SESSION['scnrotate'] == '270') ? "selected" : "") . ">270 Deg</option>\n";
    }

	$_coverview_onoff = $_SESSION['toggle_coverview'] == '-off' ? 'Off' : 'On';
} else {
	$_feat_localui = 'hide';
}

// OTHER PERIPHERALS

// USB volume knob
$autoClick = " onchange=\"autoClick('#btn-set-usb-volknob');\"";
$_select['usb_volknob_on']  .= "<input type=\"radio\" name=\"usb_volknob\" id=\"toggle-usb-volknob-1\" value=\"1\" " . (($_SESSION['usb_volknob'] == 1) ? "checked=\"checked\"" : "") . $autoClick . ">\n";
$_select['usb_volknob_off'] .= "<input type=\"radio\" name=\"usb_volknob\" id=\"toggle-usb-volknob-2\" value=\"0\" " . (($_SESSION['usb_volknob'] == 0) ? "checked=\"checked\"" : "") . $autoClick . ">\n";

// Rotary encoder
$autoClick = " onchange=\"autoClick('#btn-set-rotaryenc');\"";
$_select['rotaryenc_on']  .= "<input type=\"radio\" name=\"rotaryenc\" id=\"toggle-rotaryenc-1\" value=\"1\" " . (($_SESSION['rotaryenc'] == 1) ? "checked=\"checked\"" : "") . $autoClick . ">\n";
$_select['rotaryenc_off'] .= "<input type=\"radio\" name=\"rotaryenc\" id=\"toggle-rotaryenc-2\" value=\"0\" " . (($_SESSION['rotaryenc'] == 0) ? "checked=\"checked\"" : "") . $autoClick . ">\n";
$_select['rotenc_params'] = $_SESSION['rotenc_params'];

// GPIO buttons
if ($_SESSION['feat_bitmask'] & FEAT_GPIO) {
	$_feat_gpio = '';
	$autoClick = " onchange=\"autoClick('#btn-set-gpio-svc');\"";
	$_select['gpio_svc_on']  .= "<input type=\"radio\" name=\"gpio_svc\" id=\"toggle-gpio-svc-1\" value=\"1\" " . (($_SESSION['gpio_svc'] == 1) ? "checked=\"checked\"" : "") . $autoClick . ">\n";
	$_select['gpio_svc_off'] .= "<input type=\"radio\" name=\"gpio_svc\" id=\"toggle-gpio-svc-2\" value=\"0\" " . (($_SESSION['gpio_svc'] == 0) ? "checked=\"checked\"" : "") . $autoClick . ">\n";
} else {
	$_feat_gpio = 'hide';
}

// LCD updater
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
