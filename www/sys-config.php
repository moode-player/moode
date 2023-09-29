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
require_once __DIR__ . '/inc/keyboard.php';
require_once __DIR__ . '/inc/network.php';
require_once __DIR__ . '/inc/session.php';
require_once __DIR__ . '/inc/sql.php';
require_once __DIR__ . '/inc/timezone.php';

phpSession('open');
$dbh = sqlConnect();

// SOFTWARE UPDATE

// Auto check for update
if (isset($_POST['update_updater_auto_check'])) {
	if (isset($_POST['updater_auto_check']) && $_POST['updater_auto_check'] != $_SESSION['updater_auto_check']) {
		$_SESSION['updater_auto_check'] = $_POST['updater_auto_check'];
		submitJob('updater_auto_check', $_POST['updater_auto_check'], 'Settings updated');
	}
}

// Check for software update
if (isset($_POST['checkfor_update'])) {
	$available = checkForUpd($_SESSION['res_software_upd_url'] . '/');
	$thisReleaseDate = explode(" ", getMoodeRel('verbose'))[1];

	if (false === ($availableDate = strtotime($available['Date']))) {
		$_available_upd = 'Check for update failed';
	} else if (false === ($thisDate = strtotime($thisReleaseDate))) {
		$_available_upd = 'Invalid release date: ' . $thisReleaseDate;
	} else if ($availableDate <= $thisDate) {
		$_available_upd = 'Software is up to date';
	} else if ($available['ImageOnly'] == 'Yes') {
		$_available_upd = 'A new image-only release of moOde is available. Visit <a href="http://moodeaudio.org" class="moode-about-link target-blank-link" target="_blank">moodeaudio.org</a> for more information.';
	} else {
		$_available_upd = $available['Date'] == 'None' ? 'None available' :
			'<button class="btn btn-primary btn-small config-btn set-button btn-submit" id="install-update" type="submit" name="install_update" value="1">Install</button>' .
			'<button class="btn btn-primary btn-small config-btn set-button" data-toggle="modal" href="#view-pkgcontent">View</button>' .
			'<span class="config-btn-after">Release ' . $available['Release'] . ', ' . $available['Date'] . '</span>';
		$_pkg_description = $available['Description'];
		$_pkg_relnotes = $available['Relnotes'];
	}

	$_available_upd = '<span class="config-msg-static">' . $_available_upd . '</span>';
}

// Install software update
if (isset($_POST['install_update'])) {
	if ($_POST['install_update'] == 1) {
		$mount = sysCmd('mount | grep "moode.sqsh"');
		$space = sysCmd("df | grep /dev/root | awk '{print $4}'");
		# Check for invalid configs
		if ($mount[0] != '/var/local/moode.sqsh on /var/www type squashfs (ro,relatime)' && ($_SESSION['feat_bitmask'] & FEAT_SQSHCHK)) {
			$_SESSION['notify']['title'] = 'Invalid configuration';
			$_SESSION['notify']['msg'] = "Cannot find compressed file system";
			$_SESSION['notify']['duration'] = 20;
		} else if ($mount[0] == '/var/local/moode.sqsh on /var/www type squashfs (ro,relatime)' && !($_SESSION['feat_bitmask'] & FEAT_SQSHCHK)) {
			$_SESSION['notify']['title'] = 'Invalid configuration';
			$_SESSION['notify']['msg'] = "File system is compressed and read-only";
			$_SESSION['notify']['duration'] = 20;
		} else if ($space[0] < 512000) {
			$_SESSION['notify']['title'] = 'Insufficient space';
			$_SESSION['notify']['msg'] = "Update cannot proceed without at least 500M space";
			$_SESSION['notify']['duration'] = 20;
		} else {
			submitJob('install_update');
			header('location: sys-status.php');
		}
	}
}

// GENERAL

if (isset($_POST['update_time_zone'])) {
	if (isset($_POST['timezone']) && $_POST['timezone'] != $_SESSION['timezone']) {
		submitJob('timezone', $_POST['timezone'], 'Settings updated');
		phpSession('write', 'timezone', $_POST['timezone']);
	}
}

if (isset($_POST['update_host_name'])) {
	if (isset($_POST['hostname']) && $_POST['hostname'] != $_SESSION['hostname']) {
		if (preg_match("/[^A-Za-z0-9-]/", $_POST['hostname']) == 1) {
			$_SESSION['notify']['title'] = 'Invalid input';
			$_SESSION['notify']['msg'] = "Host name can only contain A-Z, a-z, 0-9 or hyphen (-).";
		} else {
			submitJob('hostname', '"' . $_SESSION['hostname'] . '" ' . '"' . $_POST['hostname'] . '"', 'Settings updated', 'Restart required');
			phpSession('write', 'hostname', $_POST['hostname']);
		}
	}
}

if (isset($_POST['update_keyboard'])) {
    if (isset($_POST['keyboard']) && $_POST['keyboard'] != $_SESSION['keyboard']) {
        submitJob('keyboard', $_POST['keyboard'], 'Settings updated', 'Restart required');
        phpSession('write', 'keyboard', $_POST['keyboard']);
    }
}

if (isset($_POST['update_browser_title'])) {
	if (isset($_POST['browsertitle']) && $_POST['browsertitle'] != $_SESSION['browsertitle']) {
		phpSession('write', 'browsertitle', $_POST['browsertitle']);
		$_SESSION['notify']['title'] = 'Settings updated';
	}
}

// STARTUP OPTIONS

if (isset($_POST['update_worker_responsiveness']) && $_SESSION['worker_responsiveness'] != $_POST['worker_responsiveness']) {
	$_SESSION['worker_responsiveness'] = $_POST['worker_responsiveness'];
	submitJob('worker_responsiveness', $_POST['worker_responsiveness'], 'Settings updated', 'Restart required');
}

if (isset($_POST['update_cpugov'])) {
	submitJob('cpugov', $_POST['cpugov'], 'Settings updated');
	phpSession('write', 'cpugov', $_POST['cpugov']);
}

if (isset($_POST['update_usb_auto_mounter'])) {
	submitJob('usb_auto_mounter', $_POST['usb_auto_mounter'], 'Settings updated', 'Restart required');
	phpSession('write', 'usb_auto_mounter', $_POST['usb_auto_mounter']);
}

if (isset($_POST['update_usbboot'])) {
	submitJob('usbboot', '', 'Settings updated', 'Restart required');
}

if (isset($_POST['p3wifi']) && $_POST['p3wifi'] != $_SESSION['p3wifi']) {
	submitJob('p3wifi', $_POST['p3wifi'], 'Settings updated', 'Restart required');
	phpSession('write', 'p3wifi', $_POST['p3wifi']);
}

if (isset($_POST['p3bt']) && $_POST['p3bt'] != $_SESSION['p3bt']) {
	submitJob('p3bt', $_POST['p3bt'], 'Settings updated', 'Restart required');
	phpSession('write', 'p3bt', $_POST['p3bt']);
}

if (isset($_POST['hdmiport']) && $_POST['hdmiport'] != $_SESSION['hdmiport']) {
	submitJob('hdmiport', $_POST['hdmiport'], 'Settings updated');
	phpSession('write', 'hdmiport', $_POST['hdmiport']);
}

if (isset($_POST['update_actled']) && $_POST['actled'] != explode(',', $_SESSION['led_state'])[0]) {
	submitJob('actled', $_POST['actled'], 'Settings updated');
	phpSession('write', 'led_state', $_POST['actled'] . ',' . explode(',', $_SESSION['led_state'])[1]);
}

if (isset($_POST['update_pwrled']) && $_POST['pwrled'] != explode(',', $_SESSION['led_state'])[1]) {
	submitJob('pwrled', $_POST['pwrled'], 'Settings updated');
	phpSession('write', 'led_state', explode(',', $_SESSION['led_state'])[0] . ',' . $_POST['pwrled']);
}

if (isset($_POST['update_ipaddr_timeout']) && $_POST['ipaddr_timeout'] != $_SESSION['ipaddr_timeout']) {
	phpSession('write', 'ipaddr_timeout', $_POST['ipaddr_timeout']);
	$_SESSION['notify']['title'] = 'Settings updated';
}

if (isset($_POST['eth0chk']) && $_POST['eth0chk'] != $_SESSION['eth0chk']) {
	phpSession('write', 'eth0chk', $_POST['eth0chk']);
	$_SESSION['notify']['title'] = 'Settings updated';
}

// TEST

if (isset($_POST['update_nginx_https_only']) && $_POST['nginx_https_only'] != $_SESSION['nginx_https_only']) {
	$_SESSION['nginx_https_only'] = $_POST['nginx_https_only'];
	submitJob('nginx_https_only', $_POST['nginx_https_only'], 'Settings updated', 'Restart required');
}

/*DELETE:// LOCAL DISPLAY

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

if (isset($_POST['update_scnblank'])) {
    if (isset($_POST['scnblank']) && $_POST['scnblank'] != $_SESSION['scnblank']) {
        submitJob('scnblank', $_POST['scnblank'], 'Settings updated', 'Local display restarted');
        phpSession('write', 'scnblank', $_POST['scnblank']);
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

if (isset($_POST['update_on_screen_kbd'])) {
    if (isset($_POST['on_screen_kbd']) && $_POST['on_screen_kbd'] != $_SESSION['on_screen_kbd']) {
		phpSession('write', 'on_screen_kbd', $_POST['on_screen_kbd']);
    }
}

if (isset($_POST['update_toggle_coverview'])) {
	$_SESSION['toggle_coverview'] = $_SESSION['toggle_coverview'] == '-off' ? '-on' : '-off';
	$result = sysCmd('/var/www/util/coverview.php ' . $_SESSION['toggle_coverview']);
}

if (isset($_POST['update_restart_localui'])) {
	submitJob('localui_restart', '', 'Local display restarted');
}
*/
// FILE SHARING

if (isset($_POST['update_fs_smb'])) {
	if (isset($_POST['fs_smb']) && $_POST['fs_smb'] != $_SESSION['fs_smb']) {
		phpSession('write', 'fs_smb', $_POST['fs_smb']);
		submitJob('fs_smb', $_POST['fs_smb'], 'Settings updated');
	}
}

if (isset($_POST['update_fs_nfs'])) {
	if (isset($_POST['fs_nfs']) && $_POST['fs_nfs'] != $_SESSION['fs_nfs']) {
		phpSession('write', 'fs_nfs', $_POST['fs_nfs']);
		submitJob('fs_nfs', $_POST['fs_nfs'], 'Settings updated');
	}
}
if (isset($_POST['update_fs_nfs_access'])) {
	if (isset($_POST['fs_nfs_access']) && $_POST['fs_nfs_access'] != $_SESSION['fs_nfs_access']) {
		phpSession('write', 'fs_nfs_access', $_POST['fs_nfs_access']);
		submitJob('fs_nfs_access', 'restart', 'Settings updated');
	}
}
if (isset($_POST['update_fs_nfs_options'])) {
	if (isset($_POST['fs_nfs_options']) && $_POST['fs_nfs_options'] != $_SESSION['fs_nfs_options']) {
		phpSession('write', 'fs_nfs_options', $_POST['fs_nfs_options']);
		submitJob('fs_nfs_options', 'restart', 'Settings updated');
	}
}

/*DELETE:// PERIPHERALS

if (isset($_POST['update_lcdup'])) {
	if (isset($_POST['lcdup']) && $_POST['lcdup'] != $_SESSION['lcdup']) {
		submitJob('lcdup', $_POST['lcdup'], 'Settings updated');
		phpSession('write', 'lcdup', $_POST['lcdup']);
		phpSession('write', 'extmeta', '1'); // Turn on external metadata generation
	}
}

if (isset($_POST['update_gpio_svc']) && $_POST['gpio_svc'] != $_SESSION['gpio_svc']) {
	phpSession('write', 'gpio_svc', $_POST['gpio_svc']);
	submitJob('gpio_svc', $_POST['gpio_svc'], 'Settings updated');
}
*/
// SECURITY

if (isset($_POST['update_shellinabox']) && $_POST['shellinabox'] != $_SESSION['shellinabox']) {
	phpSession('write', 'shellinabox', $_POST['shellinabox']);
	submitJob('shellinabox', $_POST['shellinabox'], 'Settings updated');
}

// LOGS

if (isset($_POST['download_logs']) && $_POST['download_logs'] == '1') {
	$fileName = 'moode.log';
	$fileLocation = '/var/log/';

	header("Content-Description: File Transfer");
	header("Content-Type: application/log");
	header("Content-Disposition: attachment; filename=\"". $fileName ."\"");

	readfile ($fileLocation . $fileName);
 	exit();
}

if (isset($_POST['update_clear_syslogs'])) {
	submitJob('clearsyslogs', '', 'System logs cleared');
}

if (isset($_POST['update_clear_playhistory'])) {
	submitJob('clearplayhistory', '', 'Playback history cleared');
}

if (isset($_POST['update_reduce_sys_logging']) && $_POST['reduce_sys_logging'] != $_SESSION['reduce_sys_logging']) {
	$_SESSION['reduce_sys_logging'] = $_POST['reduce_sys_logging'];
	submitJob('reduce_sys_logging', $_POST['reduce_sys_logging'], 'Settings updated', 'Restart reauired');
}

if (isset($_POST['update_debuglog']) && $_POST['debuglog'] != $_SESSION['debuglog']) {
	$_SESSION['debuglog'] = $_POST['debuglog'];
	$_SESSION['notify']['title'] = 'Settings updated';
}

phpSession('close');

// Clean out any temp file leftovers from Backup/Restore screens
sysCmd('rm /tmp/backup.zip /tmp/moodecfg.ini /tmp/restore.zip /tmp/py.log /tmp/script');

// SOFTWARE UPDATE

$autoClick = " onchange=\"autoClick('#btn-set-updater-auto-check');\"";
$_select['updater_auto_check_on']  .= "<input type=\"radio\" name=\"updater_auto_check\" id=\"toggle-updater-auto-check-1\" value=\"On\" " . (($_SESSION['updater_auto_check'] == 'On') ? "checked=\"checked\"" : "") . $autoClick . ">\n";
$_select['updater_auto_check_off'] .= "<input type=\"radio\" name=\"updater_auto_check\" id=\"toggle-updater-auto-check-2\" value=\"Off\" " . (($_SESSION['updater_auto_check'] == 'Off') ? "checked=\"checked\"" : "") . $autoClick . ">\n";

// GENERAL

$_timezone['timezone'] = buildTimezoneSelect($_SESSION['timezone']);
$_select['hostname'] = $_SESSION['hostname'];
$_keyboard['keyboard'] = buildKeyboardSelect($_SESSION['keyboard']);
$_select['browsertitle'] = $_SESSION['browsertitle'];

// STARTUP OPTIONS

$_select['worker_responsiveness'] .= "<option value=\"Default\" " . (($_SESSION['worker_responsiveness'] == 'Default') ? "selected" : "") . ">Default</option>\n";
$_select['worker_responsiveness'] .= "<option value=\"Boosted\" " . (($_SESSION['worker_responsiveness'] == 'Boosted') ? "selected" : "") . ">Boosted</option>\n";

$_select['cpugov'] .= "<option value=\"ondemand\" " . (($_SESSION['cpugov'] == 'ondemand') ? "selected" : "") . ">On-demand</option>\n";
$_select['cpugov'] .= "<option value=\"performance\" " . (($_SESSION['cpugov'] == 'performance') ? "selected" : "") . ">Performance</option>\n";

$_select['usb_auto_mounter'] .= "<option value=\"udisks-glue\" " . (($_SESSION['usb_auto_mounter'] == 'udisks-glue') ? "selected" : "") . ">Udisks-glue (Default)</option>\n";
$_select['usb_auto_mounter'] .= "<option value=\"devmon\" " . (($_SESSION['usb_auto_mounter'] == 'devmon') ? "selected" : "") . ">Devmon</option>\n";

$model = substr($_SESSION['hdwrrev'], 3, 1);
$name = $_SESSION['hdwrrev'];
// Pi-Zero W, Pi=Zero 2 W, Pi-3B/B+/A+, Pi-4B
if (stripos($name, 'Pi-Zero W') !== false || stripos($name, 'Pi-Zero 2 W') !== false || $model == '3' || $model == '4') {
	$_wifibt_hide = '';
	$autoClick = " onchange=\"autoClick('#btn-set-p3wifi');\"";
	$_select['p3wifi_on']  .= "<input type=\"radio\" name=\"p3wifi\" id=\"toggle-p3wifi-1\" value=\"1\" " . (($_SESSION['p3wifi'] == 1) ? "checked=\"checked\"" : "") . $autoClick . ">\n";
	$_select['p3wifi_off'] .= "<input type=\"radio\" name=\"p3wifi\" id=\"toggle-p3wifi-2\" value=\"0\" " . (($_SESSION['p3wifi'] == 0) ? "checked=\"checked\"" : "") . $autoClick . ">\n";
	$autoClick = " onchange=\"autoClick('#btn-set-p3bt');\"";
	$_select['p3bt_on']  .= "<input type=\"radio\" name=\"p3bt\" id=\"toggle-p3bt-1\" value=\"1\" " . (($_SESSION['p3bt'] == 1) ? "checked=\"checked\"" : "") . $autoClick . ">\n";
	$_select['p3bt_off'] .= "<input type=\"radio\" name=\"p3bt\" id=\"toggle-p3bt-2\" value=\"0\" " . (($_SESSION['p3bt'] == 0) ? "checked=\"checked\"" : "") . $autoClick . ">\n";
} else {
	$_wifibt_hide = 'hide';
}

$autoClick = " onchange=\"autoClick('#btn-set-hdmiport');\"";
$_select['hdmiport_on']  .= "<input type=\"radio\" name=\"hdmiport\" id=\"toggle-hdmiport-1\" value=\"1\" " . (($_SESSION['hdmiport'] == 1) ? "checked=\"checked\"" : "") . $autoClick . ">\n";
$_select['hdmiport_off'] .= "<input type=\"radio\" name=\"hdmiport\" id=\"toggle-hdmiport-2\" value=\"0\" " . (($_SESSION['hdmiport'] == 0) ? "checked=\"checked\"" : "") . $autoClick . ">\n";

$actled = explode(',', $_SESSION['led_state'])[0];
$autoClick = " onchange=\"autoClick('#btn-set-actled');\"";
$_select['actled_on']  .= "<input type=\"radio\" name=\"actled\" id=\"toggle-actled-1\" value=\"1\" " . (($actled == '1') ? "checked=\"checked\"" : "") . $autoClick . ">\n";
$_select['actled_off'] .= "<input type=\"radio\" name=\"actled\" id=\"toggle-actled-2\" value=\"0\" " . (($actled == '0') ? "checked=\"checked\"" : "") . $autoClick . ">\n";

if (substr($_SESSION['hdwrrev'], 0, 7) == 'Pi-Zero' || substr($_SESSION['hdwrrev'], 3, 1) == '1' || $_SESSION['hdwrrev'] == 'Allo USBridge SIG [CM3+ Lite 1GB v1.0]') {
	$_pwrled_hide = 'hide';
} else {
	$_pwrled_hide = '';
	$pwrled = explode(',', $_SESSION['led_state'])[1];
	$autoClick = " onchange=\"autoClick('#btn-set-pwrled');\"";
	$_select['pwrled_on']  .= "<input type=\"radio\" name=\"pwrled\" id=\"toggle-pwrled-1\" value=\"1\" " . (($pwrled == '1') ? "checked=\"checked\"" : "") . $autoClick . ">\n";
	$_select['pwrled_off'] .= "<input type=\"radio\" name=\"pwrled\" id=\"toggle-pwrled-2\" value=\"0\" " . (($pwrled == '0') ? "checked=\"checked\"" : "") . $autoClick . ">\n";
}

$_select['ipaddr_timeout'] .= "<option value=\"10\" " . (($_SESSION['ipaddr_timeout'] == '10') ? "selected" : "") . ">10</option>\n";
$_select['ipaddr_timeout'] .= "<option value=\"20\" " . (($_SESSION['ipaddr_timeout'] == '20') ? "selected" : "") . ">20</option>\n";
$_select['ipaddr_timeout'] .= "<option value=\"30\" " . (($_SESSION['ipaddr_timeout'] == '30') ? "selected" : "") . ">30</option>\n";
$_select['ipaddr_timeout'] .= "<option value=\"60\" " . (($_SESSION['ipaddr_timeout'] == '60') ? "selected" : "") . ">60</option>\n";
$_select['ipaddr_timeout'] .= "<option value=\"90\" " . (($_SESSION['ipaddr_timeout'] == '90') ? "selected" : "") . ">90 (Default)</option>\n";
$_select['ipaddr_timeout'] .= "<option value=\"120\" " . (($_SESSION['ipaddr_timeout'] == '120') ? "selected" : "") . ">120</option>\n";
$_select['ipaddr_timeout'] .= "<option value=\"120\" " . (($_SESSION['ipaddr_timeout'] == '120') ? "selected" : "") . ">180</option>\n";

$autoClick = " onchange=\"autoClick('#btn-set-eth0chk');\"";
$_select['eth0chk_on']  .= "<input type=\"radio\" name=\"eth0chk\" id=\"toggle-eth0chk-1\" value=\"1\" " . (($_SESSION['eth0chk'] == 1) ? "checked=\"checked\"" : "") . $autoClick . ">\n";
$_select['eth0chk_off'] .= "<input type=\"radio\" name=\"eth0chk\" id=\"toggle-eth0chk-2\" value=\"0\" " . (($_SESSION['eth0chk'] == 0) ? "checked=\"checked\"" : "") . $autoClick . ">\n";

// TEST: HTTPS-only mode

if ($_SESSION['feat_bitmask'] & FEAT_HTTPS) {
	$_feat_https = '';
	$autoClick = " onchange=\"autoClick('#btn-set-nginx-https-only');\"";
	$_select['nginx_https_only_on']  .= "<input type=\"radio\" name=\"nginx_https_only\" id=\"toggle-nginx-https-only-1\" value=\"1\" " . (($_SESSION['nginx_https_only'] == 1) ? "checked=\"checked\"" : "") . $autoClick . ">\n";
	$_select['nginx_https_only_off'] .= "<input type=\"radio\" name=\"nginx_https_only\" id=\"toggle-nginx-https-only-2\" value=\"0\" " . (($_SESSION['nginx_https_only'] == 0) ? "checked=\"checked\"" : "") . $autoClick . ">\n";
} else {
	$_feat_https = 'hide';
}

$model = substr($_SESSION['hdwrrev'], 3, 1);
if ($model == '3') { // Pi-3B, B+, A+
	$_usbboot_hide = '';
	$result = sysCmd('vcgencmd otp_dump | grep 17:');
	if ($result[0] == '17:3020000a') {
		$_usbboot_btn_disable = 'disabled';
		$_usbboot_msg = 'USB boot is already enabled';
	} else {
		$_usbboot_btn_disable = '';
		$_usbboot_msg = 'USB boot is not enabled yet';
	}
} else {
	// NOTE: USB boot is enabled by default for pi 4, 400 with Sep 3 2020 or later boot loader
	$_usbboot_hide = 'hide';
}

/*DELETE:// LOCAL DISPLAY

if ($_SESSION['feat_bitmask'] & FEAT_LOCALUI) {
	$_feat_localui = '';
	if ($_SESSION['localui'] == '1') {
		$_localui_btn_disable = '';
		$_localui_link_disable = '';
	} else {
		$_localui_btn_disable = 'disabled';
		$_localui_link_disable = 'onclick="return false;"';
	}

	$autoClick = " onchange=\"autoClick('#btn-set-localui');\"";
	$_select['localui_on']  .= "<input type=\"radio\" name=\"localui\" id=\"toggle-localui-1\" value=\"1\" " . (($_SESSION['localui'] == 1) ? "checked=\"checked\"" : "") . $autoClick . ">\n";
	$_select['localui_off'] .= "<input type=\"radio\" name=\"localui\" id=\"toggle-localui-2\" value=\"0\" " . (($_SESSION['localui'] == 0) ? "checked=\"checked\"" : "") . $autoClick . ">\n";

	$autoClick = " onchange=\"autoClick('#btn-set-wake-display');\" " . $_localui_btn_disable;
	$_select['wake_display_on']  .= "<input type=\"radio\" name=\"wake_display\" id=\"toggle-wake-display-1\" value=\"1\" " . (($_SESSION['wake_display'] == 1) ? "checked=\"checked\"" : "") . $autoClick . ">\n";
	$_select['wake_display_off'] .= "<input type=\"radio\" name=\"wake_display\" id=\"toggle-wake-display-2\" value=\"0\" " . (($_SESSION['wake_display'] == 0) ? "checked=\"checked\"" : "") . $autoClick . ">\n";

	$autoClick = " onchange=\"autoClick('#btn-set-touchscn');\" " . $_localui_btn_disable;
	$_select['touchscn_on']  .= "<input type=\"radio\" name=\"touchscn\" id=\"toggle-touchscn-1\" value=\"1\" " . (($_SESSION['touchscn'] == 1) ? "checked=\"checked\"" : "") . $autoClick . ">\n";
	$_select['touchscn_off'] .= "<input type=\"radio\" name=\"touchscn\" id=\"toggle-touchscn-2\" value=\"0\" " . (($_SESSION['touchscn'] == 0) ? "checked=\"checked\"" : "") . $autoClick . ">\n";

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

	$_select['scnbrightness'] = $_SESSION['scnbrightness'];

	$_select['pixel_aspect_ratio'] .= "<option value=\"Default\" " . (($_SESSION['pixel_aspect_ratio'] == 'Default') ? "selected" : "") . ">Default</option>\n";
	$_select['pixel_aspect_ratio'] .= "<option value=\"Square\" " . (($_SESSION['pixel_aspect_ratio'] == 'Square') ? "selected" : "") . ">Square</option>\n";

	$_select['scnrotate'] .= "<option value=\"0\" " . (($_SESSION['scnrotate'] == '0') ? "selected" : "") . ">0 Deg</option>\n";
	$_select['scnrotate'] .= "<option value=\"180\" " . (($_SESSION['scnrotate'] == '180') ? "selected" : "") . ">180 Deg</option>\n";

	$autoClick = " onchange=\"autoClick('#btn-set-on-screen-kbd');\" " . $_localui_btn_disable;
	$_select['on_screen_kbd_on']  .= "<input type=\"radio\" name=\"on_screen_kbd\" id=\"toggle-on-screen-kbd-1\" value=\"On\" " . (($_SESSION['on_screen_kbd'] == 'On') ? "checked=\"checked\"" : "") . $autoClick . ">\n";
	$_select['on_screen_kbd_off'] .= "<input type=\"radio\" name=\"on_screen_kbd\" id=\"toggle-on-screen-kbd-2\" value=\"Off\" " . (($_SESSION['on_screen_kbd'] == 'Off') ? "checked=\"checked\"" : "") . $autoClick . ">\n";

	$_coverview_onoff = $_SESSION['toggle_coverview'] == '-off' ? 'Off' : 'On';
} else {
	$_feat_localui = 'hide';
}
*/
// FILE SHARING

$autoClick = " onchange=\"autoClick('#btn-set-fs-smb');\"";
$_select['fs_smb_on']  .= "<input type=\"radio\" name=\"fs_smb\" id=\"toggle-fs-smb-1\" value=\"On\" " . (($_SESSION['fs_smb'] == 'On') ? "checked=\"checked\"" : "") . $autoClick . ">\n";
$_select['fs_smb_off'] .= "<input type=\"radio\" name=\"fs_smb\" id=\"toggle-fs-smb-2\" value=\"Off\" " . (($_SESSION['fs_smb'] == 'Off') ? "checked=\"checked\"" : "") . $autoClick . ">\n";

$autoClick = " onchange=\"autoClick('#btn-set-fs-nfs');\"";
$_select['fs_nfs_on']  .= "<input type=\"radio\" name=\"fs_nfs\" id=\"toggle-fs-nfs-1\" value=\"On\" " . (($_SESSION['fs_nfs'] == 'On') ? "checked=\"checked\"" : "") . $autoClick . ">\n";
$_select['fs_nfs_off'] .= "<input type=\"radio\" name=\"fs_nfs\" id=\"toggle-fs-nfs-2\" value=\"Off\" " . (($_SESSION['fs_nfs'] == 'Off') ? "checked=\"checked\"" : "") . $autoClick . ">\n";
$_select['fs_nfs_access'] = $_SESSION['fs_nfs_access'];
$_select['fs_nfs_options'] = $_SESSION['fs_nfs_options'];
$ipAddrParts = explode('.', $_SESSION['ipaddress']);
$_this_subnet = $ipAddrParts[0] . '.' . $ipAddrParts[1] . '.' . $ipAddrParts[2] . '.0/24';

/*DELETE:// PERIPHERALS

$autoClick = " onchange=\"autoClick('#btn-set-lcdup');\"";
$_select['lcdup_on']  .= "<input type=\"radio\" name=\"lcdup\" id=\"toggle-lcdup-1\" value=\"1\" " . (($_SESSION['lcdup'] == 1) ? "checked=\"checked\"" : "") . $autoClick . ">\n";
$_select['lcdup_off'] .= "<input type=\"radio\" name=\"lcdup\" id=\"toggle-lcdup-2\" value=\"0\" " . (($_SESSION['lcdup'] == 0) ? "checked=\"checked\"" : "") . $autoClick . ">\n";

if ($_SESSION['feat_bitmask'] & FEAT_GPIO) {
	$_feat_gpio = '';
	$autoClick = " onchange=\"autoClick('#btn-set-gpio-svc');\"";
	$_select['gpio_svc_on']  .= "<input type=\"radio\" name=\"gpio_svc\" id=\"toggle-gpio-svc-1\" value=\"1\" " . (($_SESSION['gpio_svc'] == 1) ? "checked=\"checked\"" : "") . $autoClick . ">\n";
	$_select['gpio_svc_off'] .= "<input type=\"radio\" name=\"gpio_svc\" id=\"toggle-gpio-svc-2\" value=\"0\" " . (($_SESSION['gpio_svc'] == 0) ? "checked=\"checked\"" : "") . $autoClick . ">\n";
} else {
	$_feat_gpio = 'hide';
}
*/
// SECURITY

$autoClick = " onchange=\"autoClick('#btn-set-shellinabox');\"";
$_select['shellinabox_on']  .= "<input type=\"radio\" name=\"shellinabox\" id=\"toggle-shellinabox-1\" value=\"1\" " . (($_SESSION['shellinabox'] == 1) ? "checked=\"checked\"" : "") . $autoClick . ">\n";
$_select['shellinabox_off'] .= "<input type=\"radio\" name=\"shellinabox\" id=\"toggle-shellinabox-2\" value=\"0\" " . (($_SESSION['shellinabox'] == 0) ? "checked=\"checked\"" : "") . $autoClick . ">\n";
$_select['hostip'] = getHostIp();
if ($_SESSION['shellinabox'] == '1') {
	$_webssh_open_disable = '';
	$_webssh_link_disable = '';
} else {
	$_webssh_open_disable = 'disabled';
	$_webssh_link_disable = 'onclick="return false;"';
}

// LOGS

$autoClick = " onchange=\"autoClick('#btn-set-reduce-sys-logging');\"";
$_select['reduce_sys_logging_on']  .= "<input type=\"radio\" name=\"reduce_sys_logging\" id=\"toggle-reduce-sys-logging-1\" value=\"1\" " . (($_SESSION['reduce_sys_logging'] == 1) ? "checked=\"checked\"" : "") . $autoClick . ">\n";
$_select['reduce_sys_logging_off'] .= "<input type=\"radio\" name=\"reduce_sys_logging\" id=\"toggle-reduce-sys-logging-2\" value=\"0\" " . (($_SESSION['reduce_sys_logging'] == 0) ? "checked=\"checked\"" : "") . $autoClick . ">\n";

$autoClick = " onchange=\"autoClick('#btn-set-debuglog');\"";
$_select['debuglog_on']  .= "<input type=\"radio\" name=\"debuglog\" id=\"toggle-debuglog-1\" value=\"1\" " . (($_SESSION['debuglog'] == 1) ? "checked=\"checked\"" : "") . $autoClick . ">\n";
$_select['debuglog_off'] .= "<input type=\"radio\" name=\"debuglog\" id=\"toggle-debuglog-2\" value=\"0\" " . (($_SESSION['debuglog'] == 0) ? "checked=\"checked\"" : "") . $autoClick . ">\n";

waitWorker('sys-config');

$tpl = "sys-config.html";
$section = basename(__FILE__, '.php');
storeBackLink($section, $tpl);

include('header.php');
eval("echoTemplate(\"" . getTemplate("templates/$tpl") . "\");");
include('footer.php');
