<?php
/*
 * SPDX-License-Identifier: GPL-3.0-or-later
 * Copyright 2014 The moOde audio player project / Tim Curtis
 * Copyright 2013 The tsunamp player ui / Andrea Coiutti & Simone De Gregori
*/

require_once __DIR__ . '/inc/common.php';
require_once __DIR__ . '/inc/peripheral.php';
require_once __DIR__ . '/inc/session.php';
require_once __DIR__ . '/inc/sql.php';

phpSession('open');

chkVariables($_POST);

// ATTACHED DISPLAYS

// moOde WebUI

if (isset($_POST['update_local_display'])) {
    if (isset($_POST['local_display']) && $_POST['local_display'] != $_SESSION['local_display']) {
        if ($_POST['local_display'] == '1') {
            submitJob('local_display', $_POST['local_display'], NOTIFY_TITLE_INFO, NOTIFY_MSG_LOCALDISPLAY_STARTING);
        } else {
            submitJob('local_display', $_POST['local_display']);
        }
        phpSession('write', 'local_display', $_POST['local_display']);
    }
}

if (isset($_POST['update_local_display_url'])) {
    if (isset($_POST['local_display_url']) && $_POST['local_display_url'] != $_SESSION['local_display_url']) {
		phpSession('write', 'local_display_url', $_POST['local_display_url']);
        submitJob('local_display_url', $_POST['local_display_url'], NOTIFY_TITLE_INFO, NAME_LOCALDISPLAY . NOTIFY_MSG_SVC_RESTARTED);
    }
}

if (isset($_POST['update_toggle_coverview'])) {
	$_SESSION['toggle_coverview'] = $_SESSION['toggle_coverview'] == '-off' ? '-on' : '-off';
	$result = sysCmd('/var/www/util/coverview.php ' . $_SESSION['toggle_coverview']);
}

if (isset($_POST['update_restart_local_display'])) {
	submitJob('local_display_restart', '', NOTIFY_TITLE_INFO, NAME_LOCALDISPLAY . NOTIFY_MSG_SVC_MANUAL_RESTART);
}

if (isset($_POST['update_disable_gpu_chromium'])) {
    if (isset($_POST['disable_gpu_chromium']) && $_POST['disable_gpu_chromium'] != $_SESSION['disable_gpu_chromium']) {
        $_SESSION['disable_gpu_chromium'] = $_POST['disable_gpu_chromium'];
        if ($_SESSION['local_display'] == '1') {
            submitJob('disable_gpu_chromium', $_POST['disable_gpu_chromium'], NOTIFY_TITLE_INFO, NAME_LOCALDISPLAY . NOTIFY_MSG_SVC_RESTARTED);
        } else {
            submitJob('disable_gpu_chromium', $_POST['disable_gpu_chromium']);
        }
    }
}

// PeppyMeter

if (isset($_POST['update_peppy_display'])) {
	if (isset($_POST['peppy_display']) && $_POST['peppy_display'] != $_SESSION['peppy_display']) {
		if ($_POST['peppy_display'] == '1') {
			if (($_SESSION['alsaequal'] != 'Off' || $_SESSION['eqfa12p'] != 'Off') && $_SESSION['alsa_output_mode'] == 'plughw') {
				$_SESSION['notify']['title'] = NOTIFY_TITLE_ALERT;
				$_SESSION['notify']['msg'] = 'When G-EQ or P-EQ is on, ALSA output mode cannot be "Default".';
			} else {
				$title = NOTIFY_TITLE_INFO;
				$msg = NOTIFY_MSG_PEPPYDISPLAY_STARTING;
				submitJob('peppy_display', $_POST['peppy_display'], NOTIFY_TITLE_INFO, NOTIFY_MSG_PEPPYDISPLAY_STARTING);
				phpSession('write', 'peppy_display', $_POST['peppy_display']);
			}
		} else {
			submitJob('peppy_display', $_POST['peppy_display']);
			phpSession('write', 'peppy_display', $_POST['peppy_display']);
		}
	}
}

if (isset($_POST['update_peppy_display_type'])) {
	if (isset($_POST['peppy_display_type']) && $_POST['peppy_display_type'] != $_SESSION['peppy_display_type']) {
		phpSession('write', 'peppy_display_type', $_POST['peppy_display_type']);
		submitJob('peppy_display_type', '', NOTIFY_TITLE_INFO, NAME_PEPPYDISPLAY . NOTIFY_MSG_SVC_RESTARTED);
	}
}

if (isset($_POST['update_restart_peppy_display'])) {
	submitJob('peppy_display_restart', '', NOTIFY_TITLE_INFO, NAME_PEPPYDISPLAY . NOTIFY_MSG_SVC_MANUAL_RESTART);
}

// General

if (isset($_POST['update_scn_blank'])) {
    if (isset($_POST['scn_blank']) && $_POST['scn_blank'] != $_SESSION['scn_blank']) {
        $_SESSION['scn_blank'] = $_POST['scn_blank'];
        submitJob('scn_blank', $_POST['scn_blank'], NOTIFY_TITLE_INFO, NAME_LOCALDISPLAY . NOTIFY_MSG_SVC_RESTARTED);
    }
}

if (isset($_POST['update_wake_display'])) {
    if (isset($_POST['wake_display']) && $_POST['wake_display'] != $_SESSION['wake_display']) {
        phpSession('write', 'wake_display', $_POST['wake_display']);
    }
}

if (isset($_POST['update_scn_cursor'])) {
    if (isset($_POST['scn_cursor']) && $_POST['scn_cursor'] != $_SESSION['scn_cursor']) {
        $_SESSION['scn_cursor'] = $_POST['scn_cursor'];
        submitJob('scn_cursor', $_POST['scn_cursor'], NOTIFY_TITLE_INFO, NAME_LOCALDISPLAY . NOTIFY_MSG_SVC_RESTARTED);
    }
}

if (isset($_POST['update_on_screen_kbd'])) {
    if (isset($_POST['on_screen_kbd']) && $_POST['on_screen_kbd'] != $_SESSION['on_screen_kbd']) {
		$_SESSION['on_screen_kbd'] = $_POST['on_screen_kbd'];
    }
}

// HDMI displays

if (isset($_POST['update_hdmi_scn_orient'])) {
    if (isset($_POST['hdmi_scn_orient']) && $_POST['hdmi_scn_orient'] != $_SESSION['hdmi_scn_orient']) {
        phpSession('write', 'hdmi_scn_orient', $_POST['hdmi_scn_orient']);
        submitJob('hdmi_scn_orient', $_POST['hdmi_scn_orient'], NOTIFY_TITLE_INFO, NAME_LOCALDISPLAY . NOTIFY_MSG_SVC_RESTARTED);
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

// DSI displays (Pi Touch1/Touch2)

if (isset($_POST['update_dsi_scn_type'])) {
    if (isset($_POST['dsi_scn_type']) && $_POST['dsi_scn_type'] != $_SESSION['dsi_scn_type']) {
        phpSession('write', 'dsi_scn_type', $_POST['dsi_scn_type']);

        // Reset dsi port, brightness and rotation
        phpSession('write', 'dsi_port', '1');
        $_SESSION['dsi_scn_brightness'] = ($_POST['dsi_scn_type'] != '2' ? '255' : '31');
        phpSession('write', 'dsi_scn_rotate', '0');

        submitJob('dsi_scn_type', $_POST['dsi_scn_type'], NOTIFY_TITLE_INFO, NOTIFY_MSG_SYSTEM_RESTART_REQD);
    }
}

if (isset($_POST['update_dsi_port'])) {
    if (isset($_POST['dsi_port']) && $_POST['dsi_port'] != $_SESSION['dsi_port']) {
        phpSession('write', 'dsi_port', $_POST['dsi_port']);
    	submitJob('dsi_port','', NOTIFY_TITLE_INFO, NAME_LOCALDISPLAY . NOTIFY_MSG_SVC_RESTARTED);
    }
}

if (isset($_POST['update_dsi_scn_brightness'])) {
    if (isset($_POST['dsi_scn_brightness']) && $_POST['dsi_scn_brightness'] != $_SESSION['dsi_scn_brightness']) {
        $_SESSION['dsi_scn_brightness'] = $_POST['dsi_scn_brightness'];
    	submitJob('dsi_scn_brightness', $_POST['dsi_scn_brightness']);
    }
}

// NOTE: Touch1 (square pixels): no solution yet with the KMS driver
/*if (isset($_POST['update_pixel_aspect_ratio'])) {
    if (isset($_POST['pixel_aspect_ratio']) && $_POST['pixel_aspect_ratio'] != $_SESSION['pixel_aspect_ratio']) {
		submitJob('pixel_aspect_ratio', $_POST['pixel_aspect_ratio'], NOTIFY_TITLE_INFO, NOTIFY_MSG_SYSTEM_RESTART_REQD);
		phpSession('write', 'pixel_aspect_ratio', $_POST['pixel_aspect_ratio']);
    }
}*/

if (isset($_POST['update_dsi_scn_rotate'])) {
    if (isset($_POST['dsi_scn_rotate']) && $_POST['dsi_scn_rotate'] != $_SESSION['dsi_scn_rotate']) {
        phpSession('write', 'dsi_scn_rotate', $_POST['dsi_scn_rotate']);
        if ($_SESSION['dsi_scn_type'] == '1') {
            submitJob('dsi_scn_rotate', $_POST['dsi_scn_rotate'], NOTIFY_TITLE_INFO, NOTIFY_MSG_SYSTEM_RESTART_REQD);
        } else if ($_SESSION['dsi_scn_type'] == '2' || $_SESSION['dsi_scn_type'] == 'other') {
            submitJob('dsi_scn_rotate', $_POST['dsi_scn_rotate'], NOTIFY_TITLE_INFO, NAME_LOCALDISPLAY . NOTIFY_MSG_SVC_RESTARTED);
        }
    }
}

// VOLUME CONTROLLERS

// Triggerhappy / USB volume knob
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

// OTHER PERIPHERALS

// GPIO buttons
if (isset($_POST['update_gpio_svc']) && $_POST['gpio_svc'] != $_SESSION['gpio_svc']) {
	phpSession('write', 'gpio_svc', $_POST['gpio_svc']);
	submitJob('gpio_svc', $_POST['gpio_svc']);
}
// LCD updater
if (isset($_POST['update_lcdup'])) {
	if (isset($_POST['lcdup']) && $_POST['lcdup'] != $_SESSION['lcdup']) {
		submitJob('lcdup', $_POST['lcdup']);
		$_SESSION['lcdup'] = $_POST['lcdup'];
		phpSession('write', 'extmeta', '1'); // Turn on external metadata generation
	}
}

phpSession('close');

// WebUI and Peppy on/off disables
$_webui_on_off_disable = $_SESSION['peppy_display'] == '1' ? 'disabled' : '';
$_peppy_on_off_disable = ($_SESSION['local_display'] == '1' || allowPeppyInAlsaChain() == false) ? 'disabled' : '';

// ATTACHED DISPLAYS

// moOde WebUI
if ($_SESSION['feat_bitmask'] & FEAT_LOCALDISPLAY) {
	$_feat_localdisplay = '';
	if ($_SESSION['local_display'] == '1') {
		$_webui_ctl_disable = '';
		$_webui_link_disable = '';
        $_screen_res_local_display = '<span class="config-help-static">Resolution: '
            . sysCmd("kmsprint | awk '$1 == \"FB\" {print $3}' | awk -F\"x\" '{print $1\"x\"$2}'")[0]
            . '<a aria-label="Refresh" href="per-config.php"><i class="fa-solid fa-sharp fa-redo dx"></i></a>'
            . '</span>';
	} else {
		$_webui_ctl_disable = 'disabled';
		$_webui_link_disable = 'onclick="return false;"';
        $_screen_res_local_display = '';
	}

	$autoClick = " onchange=\"autoClick('#btn-set-local-display');\" " . $_webui_on_off_disable;
	$_select['local_display_on']  .= "<input type=\"radio\" name=\"local_display\" id=\"toggle-local-display-1\" value=\"1\" " . (($_SESSION['local_display'] == 1) ? "checked=\"checked\"" : "") . $autoClick . ">\n";
	$_select['local_display_off'] .= "<input type=\"radio\" name=\"local_display\" id=\"toggle-local-display-2\" value=\"0\" " . (($_SESSION['local_display'] == 0) ? "checked=\"checked\"" : "") . $autoClick . ">\n";

	$_select['local_display_url'] = $_SESSION['local_display_url'];

	$_coverview_onoff = $_SESSION['toggle_coverview'] == '-off' ? 'Off' : 'On';

	$autoClick = " onchange=\"autoClick('#btn-set-disable-gpu-chromium');\"";
	$_select['disable_gpu_chromium_on']  .= "<input type=\"radio\" name=\"disable_gpu_chromium\" id=\"toggle-disable-gpu-chromium-1\" value=\"on\" " . (($_SESSION['disable_gpu_chromium'] == 'on') ? "checked=\"checked\"" : "") . $autoClick . ">\n";
	$_select['disable_gpu_chromium_off'] .= "<input type=\"radio\" name=\"disable_gpu_chromium\" id=\"toggle-disable-gpu-chromium-2\" value=\"off\" " . (($_SESSION['disable_gpu_chromium'] == 'off') ? "checked=\"checked\"" : "") . $autoClick . ">\n";
} else {
	$_feat_localdisplay = 'hide';
}

// PeppyMeter

if ($_SESSION['feat_bitmask'] & FEAT_PEPPYDISPLAY) {
	$_feat_peppydisplay = '';
	if ($_SESSION['peppy_display'] == '1') {
		$_peppy_ctl_disable = '';
		$_peppy_link_disable = '';
        $_screen_res_peppy_display = '<span class="config-help-static">Resolution: '
            . sysCmd("kmsprint | awk '$1 == \"FB\" {print $3}' | awk -F\"x\" '{print $1\"x\"$2}'")[0]
            . '<a aria-label="Refresh" href="per-config.php"><i class="fa-solid fa-sharp fa-redo dx"></i></a>'
            . '</span>';
	} else {
		$_peppy_ctl_disable = 'disabled';
		$_peppy_link_disable = 'onclick="return false;"';
        $_screen_res_peppy_display = '';
	}
	# Display on|off
	$autoClick = " onchange=\"autoClick('#btn-set-peppy-display');\" " . $_peppy_on_off_disable;
	$_select['peppy_display_on']  .= "<input type=\"radio\" name=\"peppy_display\" id=\"toggle-peppy-display-1\" value=\"1\" " . (($_SESSION['peppy_display'] == 1) ? "checked=\"checked\"" : "") . $autoClick . ">\n";
	$_select['peppy_display_off'] .= "<input type=\"radio\" name=\"peppy_display\" id=\"toggle-peppy-display-2\" value=\"0\" " . (($_SESSION['peppy_display'] == 0) ? "checked=\"checked\"" : "") . $autoClick . ">\n";
	# Display type
	$_select['peppy_display_type'] .= "<option value=\"meter\" " . (($_SESSION['peppy_display_type'] == 'meter') ? "selected" : "") . ">Meter</option>\n";
	$_select['peppy_display_type'] .= "<option value=\"spectrum\" " . (($_SESSION['peppy_display_type'] == 'spectrum') ? "selected" : "") . ">Spectrum</option>\n";
} else {
	$_feat_peppydisplay = 'hide';
}

// General

$_select['scn_blank'] .= "<option value=\"off\" " . (($_SESSION['scn_blank'] == 'off') ? "selected" : "") . ">Never</option>\n";
$_select['scn_blank'] .= "<option value=\"10\" " . (($_SESSION['scn_blank'] == '10') ? "selected" : "") . ">10 Secs</option>\n";
$_select['scn_blank'] .= "<option value=\"20\" " . (($_SESSION['scn_blank'] == '20') ? "selected" : "") . ">20 Secs</option>\n";
$_select['scn_blank'] .= "<option value=\"30\" " . (($_SESSION['scn_blank'] == '30') ? "selected" : "") . ">30 Secs</option>\n";
$_select['scn_blank'] .= "<option value=\"60\" " . (($_SESSION['scn_blank'] == '60') ? "selected" : "") . ">1 Min</option>\n";
$_select['scn_blank'] .= "<option value=\"120\" " . (($_SESSION['scn_blank'] == '120') ? "selected" : "") . ">2 Mins</option>\n";
$_select['scn_blank'] .= "<option value=\"300\" " . (($_SESSION['scn_blank'] == '300') ? "selected" : "") . ">5 Mins</option>\n";
$_select['scn_blank'] .= "<option value=\"600\" " . (($_SESSION['scn_blank'] == '600') ? "selected" : "") . ">10 Mins</option>\n";
$_select['scn_blank'] .= "<option value=\"1200\" " . (($_SESSION['scn_blank'] == '1200') ? "selected" : "") . ">20 Mins</option>\n";
$_select['scn_blank'] .= "<option value=\"1800\" " . (($_SESSION['scn_blank'] == '1800') ? "selected" : "") . ">30 Mins</option>\n";
$_select['scn_blank'] .= "<option value=\"3600\" " . (($_SESSION['scn_blank'] == '3600') ? "selected" : "") . ">1 Hour</option>\n";

$autoClick = " onchange=\"autoClick('#btn-set-wake-display');\" " . $_ctl_disable;
$_select['wake_display_on']  .= "<input type=\"radio\" name=\"wake_display\" id=\"toggle-wake-display-1\" value=\"1\" " . (($_SESSION['wake_display'] == 1) ? "checked=\"checked\"" : "") . $autoClick . ">\n";
$_select['wake_display_off'] .= "<input type=\"radio\" name=\"wake_display\" id=\"toggle-wake-display-2\" value=\"0\" " . (($_SESSION['wake_display'] == 0) ? "checked=\"checked\"" : "") . $autoClick . ">\n";

$autoClick = " onchange=\"autoClick('#btn-set-scn-cursor');\" " . $_ctl_disable;
$_select['scn_cursor_on']  .= "<input type=\"radio\" name=\"scn_cursor\" id=\"toggle-scn-cursor-1\" value=\"1\" " . (($_SESSION['scn_cursor'] == 1) ? "checked=\"checked\"" : "") . $autoClick . ">\n";
$_select['scn_cursor_off'] .= "<input type=\"radio\" name=\"scn_cursor\" id=\"toggle-scn-cursor-2\" value=\"0\" " . (($_SESSION['scn_cursor'] == 0) ? "checked=\"checked\"" : "") . $autoClick . ">\n";

$autoClick = " onchange=\"autoClick('#btn-set-on-screen-kbd');\" " . $_ctl_disable;
$_select['on_screen_kbd_on']  .= "<input type=\"radio\" name=\"on_screen_kbd\" id=\"toggle-on-screen-kbd-1\" value=\"On\" " . (($_SESSION['on_screen_kbd'] == 'On') ? "checked=\"checked\"" : "") . $autoClick . ">\n";
$_select['on_screen_kbd_off'] .= "<input type=\"radio\" name=\"on_screen_kbd\" id=\"toggle-on-screen-kbd-2\" value=\"Off\" " . (($_SESSION['on_screen_kbd'] == 'Off') ? "checked=\"checked\"" : "") . $autoClick . ">\n";

// HDMI displays

$_select['hdmi_scn_orient'] .= "<option value=\"landscape\" " . (($_SESSION['hdmi_scn_orient'] == 'landscape') ? "selected" : "") . ">Landscape</option>\n";
$_select['hdmi_scn_orient'] .= "<option value=\"portrait\" " . (($_SESSION['hdmi_scn_orient'] == 'portrait') ? "selected" : "") . ">Portrait</option>\n";

$autoClick = " onchange=\"autoClick('#btn-set-hdmi-cec');\"";
$_select['hdmi_cec_on']  .= "<input type=\"radio\" name=\"hdmi_cec\" id=\"toggle-hdmi-cec-1\" value=\"on\" " . (($_SESSION['hdmi_cec'] == 'on') ? "checked=\"checked\"" : "") . $autoClick . ">\n";
$_select['hdmi_cec_off'] .= "<input type=\"radio\" name=\"hdmi_cec\" id=\"toggle-hdmi-cec-2\" value=\"off\" " . (($_SESSION['hdmi_cec'] == 'off') ? "checked=\"checked\"" : "") . $autoClick . ">\n";

$piModel = substr($_SESSION['hdwrrev'], 3, 1);
$_hdmi_4kp60_btn_disable = $piModel == '4' ? '' : 'disabled';
$autoClick = " onchange=\"autoClick('#btn-set-hdmi-enable-4kp60');\" " . $_hdmi_4kp60_btn_disable;
$_select['hdmi_enable_4kp60_on']  .= "<input type=\"radio\" name=\"hdmi_enable_4kp60\" id=\"toggle-hdmi-enable-4kp60-1\" value=\"on\" " . (($_SESSION['hdmi_enable_4kp60'] == 'on') ? "checked=\"checked\"" : "") . $autoClick . ">\n";
$_select['hdmi_enable_4kp60_off'] .= "<input type=\"radio\" name=\"hdmi_enable_4kp60\" id=\"toggle-hdmi-enable-4kp60-2\" value=\"off\" " . (($_SESSION['hdmi_enable_4kp60'] == 'off') ? "checked=\"checked\"" : "") . $autoClick . ">\n";

// DSI displays (Pi Touch1/Touch2)

$_dsi_ctl_disable = $_SESSION['dsi_scn_type'] == 'none' ? 'disabled' : '';
// NOTE: The option 'none' is used in xinitrc to determine whether HDMI or DSI configuration is used
$_select['dsi_scn_type'] .= "<option value=\"none\" " . (($_SESSION['dsi_scn_type'] == 'none') ? "selected" : "") . ">None</option>\n";
$_select['dsi_scn_type'] .= "<option value=\"1\" " . (($_SESSION['dsi_scn_type'] == '1') ? "selected" : "") . ">Pi Touch 1</option>\n";
$_select['dsi_scn_type'] .= "<option value=\"2\" " . (($_SESSION['dsi_scn_type'] == '2') ? "selected" : "") . ">Pi Touch 2</option>\n";
$_select['dsi_scn_type'] .= "<option value=\"other\" " . (($_SESSION['dsi_scn_type'] == 'other') ? "selected" : "") . ">Other</option>\n";

$_select['dsi_port'] .= "<option value=\"1\" " . (($_SESSION['dsi_port'] == '1') ? "selected" : "") . ">DSI-1</option>\n";
$_select['dsi_port'] .= "<option value=\"2\" " . (($_SESSION['dsi_port'] == '2') ? "selected" : "") . ">DSI-2</option>\n";

// NOTE: Touch1 (square pixels): no solution yet with the KMS driver
//$_select['pixel_aspect_ratio'] .= "<option value=\"Default\" " . (($_SESSION['pixel_aspect_ratio'] == 'Default') ? "selected" : "") . ">Default</option>\n";
//$_select['pixel_aspect_ratio'] .= "<option value=\"Square\" " . (($_SESSION['pixel_aspect_ratio'] == 'Square') ? "selected" : "") . ">Square</option>\n";

$_select['dsi_scn_brightness'] = $_SESSION['dsi_scn_brightness'];

if ($_SESSION['dsi_scn_type'] == '1' || $_SESSION['dsi_scn_type'] == 'none') {
    $_dsi_scn_brightness_min = '0';
    $_dsi_scn_brightness_max = '255';
    $_select['dsi_scn_rotate'] .= "<option value=\"0\" " . (($_SESSION['dsi_scn_rotate'] == '0') ? "selected" : "") . ">0 Deg</option>\n";
    $_select['dsi_scn_rotate'] .= "<option value=\"180\" " . (($_SESSION['dsi_scn_rotate'] == '180') ? "selected" : "") . ">180 Deg</option>\n";
} else if ($_SESSION['dsi_scn_type'] == '2' || $_SESSION['dsi_scn_type'] == 'other') {
    if ($_SESSION['dsi_scn_type'] == '2') {
        $_dsi_scn_brightness_min = '1';
        $_dsi_scn_brightness_max = '31';
    } else if ($_SESSION['dsi_scn_type'] == 'other') {
        $_dsi_scn_brightness_min = '0';
        $_dsi_scn_brightness_max = '255';
    }
    $_select['dsi_scn_rotate'] .= "<option value=\"0\" " . (($_SESSION['dsi_scn_rotate'] == '0') ? "selected" : "") . ">0 Deg</option>\n";
	$_select['dsi_scn_rotate'] .= "<option value=\"90\" " . (($_SESSION['dsi_scn_rotate'] == '90') ? "selected" : "") . ">90 Deg</option>\n";
    $_select['dsi_scn_rotate'] .= "<option value=\"180\" " . (($_SESSION['dsi_scn_rotate'] == '180') ? "selected" : "") . ">180 Deg</option>\n";
    $_select['dsi_scn_rotate'] .= "<option value=\"270\" " . (($_SESSION['dsi_scn_rotate'] == '270') ? "selected" : "") . ">270 Deg</option>\n";
}

// VOLUME CONTROLLERS

// Triggerhappy / USB volume knob
$autoClick = " onchange=\"autoClick('#btn-set-usb-volknob');\"";
$_select['usb_volknob_on']  .= "<input type=\"radio\" name=\"usb_volknob\" id=\"toggle-usb-volknob-1\" value=\"1\" " . (($_SESSION['usb_volknob'] == 1) ? "checked=\"checked\"" : "") . $autoClick . ">\n";
$_select['usb_volknob_off'] .= "<input type=\"radio\" name=\"usb_volknob\" id=\"toggle-usb-volknob-2\" value=\"0\" " . (($_SESSION['usb_volknob'] == 0) ? "checked=\"checked\"" : "") . $autoClick . ">\n";

// Rotary encoder
$autoClick = " onchange=\"autoClick('#btn-set-rotaryenc');\"";
$_select['rotaryenc_on']  .= "<input type=\"radio\" name=\"rotaryenc\" id=\"toggle-rotaryenc-1\" value=\"1\" " . (($_SESSION['rotaryenc'] == 1) ? "checked=\"checked\"" : "") . $autoClick . ">\n";
$_select['rotaryenc_off'] .= "<input type=\"radio\" name=\"rotaryenc\" id=\"toggle-rotaryenc-2\" value=\"0\" " . (($_SESSION['rotaryenc'] == 0) ? "checked=\"checked\"" : "") . $autoClick . ">\n";
$_select['rotenc_params'] = $_SESSION['rotenc_params'];

// OTHER PERIPHERALS

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
