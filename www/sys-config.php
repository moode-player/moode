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

if (isset($_POST['update_dlna_settings'])) {
	$currentDlnaName = $_SESSION['dlnaname'];
	if (isset($_POST['dlnaname']) && $_POST['dlnaname'] != $_SESSION['dlnaname']) {
		$title = 'Settings updated';
		$msg = '';
		phpSession('write', 'dlnaname', $_POST['dlnaname']);
	}
	if (isset($_POST['dlnasvc']) && $_POST['dlnasvc'] != $_SESSION['dlnasvc']) {
		$title = 'Settings updated';
		$msg = $_POST['dlnasvc'] == 1 ? 'Database rebuild initiated' : '';
		phpSession('write', 'dlnasvc', $_POST['dlnasvc']);
	}
	if (isset($title)) {
		submitJob('minidlna', '"' . $currentDlnaName . '" ' . '"' . $_POST['dlnaname'] . '"', $title, $msg);
	}
}
if (isset($_POST['rebuild_dlnadb'])) {
	if ($_SESSION['dlnasvc'] == 1) {
		submitJob('dlnarebuild', '', 'Database rebuild initiated...');
	}
	else {
		$_SESSION['notify']['title'] = 'Turn DLNA server on';
		$_SESSION['notify']['msg'] = 'Database rebuild will initiate';
	}
}

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

$piModel = substr($_SESSION['hdwrrev'], 3, 1);
$piName = $_SESSION['hdwrrev'];
// Pi-Zero W, Pi=Zero 2 W, Pi-3B/B+/A+, Pi-4B, Pi-5B
if (
	stripos($piName, 'Pi-Zero W') !== false ||
	stripos($piName, 'Pi-Zero 2 W') !== false ||
	$piModel >= 3
) {
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

$piModel = substr($_SESSION['hdwrrev'], 3, 1);
if ($piModel == '3') { // Pi-3B, B+, A+
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
	// NOTE: USB boot is enabled by default on Pi-4B, Pi-400 (Sep 3 2020 or later boot loader) and Pi-5B.
	$_usbboot_hide = 'hide';
}

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

$_feat_minidlna = $_SESSION['feat_bitmask'] & FEAT_MINIDLNA ? '' : 'hide';
$autoClick = " onchange=\"autoClick('#btn-set-dlnasvc');\"";
$_select['dlnasvc_on']  .= "<input type=\"radio\" name=\"dlnasvc\" id=\"toggle-dlnasvc-1\" value=\"1\" " . (($_SESSION['dlnasvc'] == '1') ? "checked=\"checked\"" : "") . $autoClick . ">\n";
$_select['dlnasvc_off'] .= "<input type=\"radio\" name=\"dlnasvc\" id=\"toggle-dlnasvc-2\" value=\"0\" " . (($_SESSION['dlnasvc'] == '0') ? "checked=\"checked\"" : "") . $autoClick . ">\n";
$_select['dlnaname'] = $_SESSION['dlnaname'];
$_select['hostip'] = getHostIp();


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
