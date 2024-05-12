<?php
/*
 * SPDX-License-Identifier: GPL-3.0-or-later
 * Copyright 2014 The moOde audio player project / Tim Curtis
 * Copyright 2013 The tsunamp player ui / Andrea Coiutti & Simone De Gregori
*/

require_once __DIR__ . '/inc/common.php';
require_once __DIR__ . '/inc/keyboard.php';
require_once __DIR__ . '/inc/network.php';
require_once __DIR__ . '/inc/session.php';
require_once __DIR__ . '/inc/sql.php';
require_once __DIR__ . '/inc/timezone.php';

const TMP_NGINX_CRT_FILE = '/tmp/moode.crt';
const TMP_NGINX_KEY_FILE = '/tmp/moode.key';
const TMP_SELF_SIGNED_CER_FILE = '/tmp/moode.cer';

phpSession('open');
$dbh = sqlConnect();

// SOFTWARE UPDATE

// Auto check for update
if (isset($_POST['update_updater_auto_check'])) {
	if (isset($_POST['updater_auto_check']) && $_POST['updater_auto_check'] != $_SESSION['updater_auto_check']) {
		$_SESSION['updater_auto_check'] = $_POST['updater_auto_check'];
		submitJob('updater_auto_check', $_POST['updater_auto_check']);
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
			$_SESSION['notify']['title'] = NOTIFY_TITLE_ALERT;
			$_SESSION['notify']['msg'] = 'Invalid configuration. Cannot find compressed file system.';
			$_SESSION['notify']['duration'] = NOTIFY_DURATION_LONG;
		} else if ($mount[0] == '/var/local/moode.sqsh on /var/www type squashfs (ro,relatime)' && !($_SESSION['feat_bitmask'] & FEAT_SQSHCHK)) {
			$_SESSION['notify']['title'] = NOTIFY_TITLE_ALERT;
			$_SESSION['notify']['msg'] = 'Invalid configuration. File system is compressed and read-only.';
			$_SESSION['notify']['duration'] = NOTIFY_DURATION_LONG;
		} else if ($space[0] < 512000) {
			$_SESSION['notify']['title'] = NOTIFY_TITLE_ALERT;
			$_SESSION['notify']['msg'] = 'Insufficient disk space. Update cannot proceed without at least 500M of disk space available.';
			$_SESSION['notify']['duration'] = NOTIFY_DURATION_LONG;
		} else {
			submitJob('install_update');
			header('location: sys-status.php');
		}
	}
}

// GENERAL

if (isset($_POST['update_host_name'])) {
	if (isset($_POST['hostname']) && $_POST['hostname'] != $_SESSION['hostname']) {
		if (preg_match("/[^A-Za-z0-9-]/", $_POST['hostname']) == 1) {
			$_SESSION['notify']['title'] = NOTIFY_TITLE_ALERT;
			$_SESSION['notify']['msg'] =  'Invalid input. Host name can only contain A-Z, a-z, 0-9 or hyphen (-).';
		} else {
			submitJob('hostname', '"' . $_SESSION['hostname'] . '" ' . '"' . $_POST['hostname'] . '"', NOTIFY_TITLE_INFO, NOTIFY_MSG_SYSTEM_RESTART_REQD);
			phpSession('write', 'hostname', $_POST['hostname']);
		}
	}
}

if (isset($_POST['update_browser_title'])) {
	if (isset($_POST['browsertitle']) && $_POST['browsertitle'] != $_SESSION['browsertitle']) {
		phpSession('write', 'browsertitle', $_POST['browsertitle']);
	}
}

if (isset($_POST['update_time_zone'])) {
	if (isset($_POST['timezone']) && $_POST['timezone'] != $_SESSION['timezone']) {
		submitJob('timezone', $_POST['timezone']);
		phpSession('write', 'timezone', $_POST['timezone']);
	}
}

if (isset($_POST['update_keyboard'])) {
    if (isset($_POST['keyboard']) && $_POST['keyboard'] != $_SESSION['keyboard']) {
        submitJob('keyboard', $_POST['keyboard'], NOTIFY_TITLE_INFO, NOTIFY_MSG_SYSTEM_RESTART_REQD);
        phpSession('write', 'keyboard', $_POST['keyboard']);
    }
}

// STARTUP OPTIONS

if (isset($_POST['update_worker_responsiveness']) && $_SESSION['worker_responsiveness'] != $_POST['worker_responsiveness']) {
	$_SESSION['worker_responsiveness'] = $_POST['worker_responsiveness'];
	submitJob('worker_responsiveness', $_POST['worker_responsiveness'], NOTIFY_TITLE_INFO, NOTIFY_MSG_SYSTEM_RESTART_REQD);
}

if (isset($_POST['update_cpugov'])) {
	submitJob('cpugov', $_POST['cpugov']);
	phpSession('write', 'cpugov', $_POST['cpugov']);
}

if (isset($_POST['update_pi_audio_driver'])) {
	$_SESSION['pi_audio_driver'] = $_POST['pi_audio_driver'];
	$queueArgs = $_POST['pi_audio_driver'] == PI_VC4_KMS_V3D ? '' : '#';
	submitJob('pi_audio_driver', $queueArgs, NOTIFY_TITLE_INFO, NOTIFY_MSG_SYSTEM_RESTART_REQD);
}

if (isset($_POST['update_pci_express'])) {
	$_SESSION['pci_express'] = $_POST['pci_express'];
	submitJob('pci_express', $_POST['pci_express'], NOTIFY_TITLE_INFO, NOTIFY_MSG_SYSTEM_RESTART_REQD);
}

if (isset($_POST['reduce_power']) && $_POST['reduce_power'] != $_SESSION['reduce_power']) {
	submitJob('reduce_power', $_POST['reduce_power'], NOTIFY_TITLE_INFO, NOTIFY_MSG_SYSTEM_RESTART_REQD);
	phpSession('write', 'reduce_power', $_POST['reduce_power']);
}

if (isset($_POST['p3wifi']) && $_POST['p3wifi'] != $_SESSION['p3wifi']) {
	submitJob('p3wifi', $_POST['p3wifi'], NOTIFY_TITLE_INFO, NOTIFY_MSG_SYSTEM_RESTART_REQD);
	phpSession('write', 'p3wifi', $_POST['p3wifi']);
}

if (isset($_POST['p3bt']) && $_POST['p3bt'] != $_SESSION['p3bt']) {
	submitJob('p3bt', $_POST['p3bt'], NOTIFY_TITLE_INFO, NOTIFY_MSG_SYSTEM_RESTART_REQD);
	phpSession('write', 'p3bt', $_POST['p3bt']);
}

if (isset($_POST['update_actled']) && $_POST['actled'] != explode(',', $_SESSION['led_state'])[0]) {
	submitJob('actled', $_POST['actled']);
	phpSession('write', 'led_state', $_POST['actled'] . ',' . explode(',', $_SESSION['led_state'])[1]);
}

if (isset($_POST['update_pwrled']) && $_POST['pwrled'] != explode(',', $_SESSION['led_state'])[1]) {
	submitJob('pwrled', $_POST['pwrled']);
	phpSession('write', 'led_state', explode(',', $_SESSION['led_state'])[0] . ',' . $_POST['pwrled']);
}

if (isset($_POST['update_ipaddr_timeout']) && $_POST['ipaddr_timeout'] != $_SESSION['ipaddr_timeout']) {
	phpSession('write', 'ipaddr_timeout', $_POST['ipaddr_timeout']);
}

if (isset($_POST['eth0chk']) && $_POST['eth0chk'] != $_SESSION['eth0chk']) {
	phpSession('write', 'eth0chk', $_POST['eth0chk']);
}

// FILE SHARING

if (isset($_POST['update_fs_smb'])) {
	if (isset($_POST['fs_smb']) && $_POST['fs_smb'] != $_SESSION['fs_smb']) {
		phpSession('write', 'fs_smb', $_POST['fs_smb']);
		submitJob('fs_smb', $_POST['fs_smb']);
	}
}

if (isset($_POST['update_fs_nfs'])) {
	if (isset($_POST['fs_nfs']) && $_POST['fs_nfs'] != $_SESSION['fs_nfs']) {
		phpSession('write', 'fs_nfs', $_POST['fs_nfs']);
		submitJob('fs_nfs', $_POST['fs_nfs']);
	}
}
if (isset($_POST['update_fs_nfs_access'])) {
	if (isset($_POST['fs_nfs_access']) && $_POST['fs_nfs_access'] != $_SESSION['fs_nfs_access']) {
		phpSession('write', 'fs_nfs_access', $_POST['fs_nfs_access']);
		submitJob('fs_nfs_access', 'restart');
	}
}
if (isset($_POST['update_fs_nfs_options'])) {
	if (isset($_POST['fs_nfs_options']) && $_POST['fs_nfs_options'] != $_SESSION['fs_nfs_options']) {
		phpSession('write', 'fs_nfs_options', $_POST['fs_nfs_options']);
		submitJob('fs_nfs_options', 'restart');
	}
}

if (isset($_POST['update_dlna_settings'])) {
	$currentDlnaName = $_SESSION['dlnaname'];
	if (isset($_POST['dlnaname']) && $_POST['dlnaname'] != $_SESSION['dlnaname']) {
		$update = true;
		$msg = NAME_DLNA . NOTIFY_MSG_SVC_RESTARTED;
		phpSession('write', 'dlnaname', $_POST['dlnaname']);
	}
	if (isset($_POST['dlnasvc']) && $_POST['dlnasvc'] != $_SESSION['dlnasvc']) {
		$update = true;
		$msg = $_POST['dlnasvc'] == '0' ?
			NAME_DLNA . 'DNLA server off. Database has been cleared' :
			NAME_DLNA . NOTIFY_MSG_SVC_RESTARTED . ' Database rebuild initiated...';
		phpSession('write', 'dlnasvc', $_POST['dlnasvc']);
	}
	if (isset($update)) {
		$notify = array('title' => NOTIFY_TITLE_INFO, 'msg' => $msg);
		submitJob('minidlna', '"' . $currentDlnaName . '" ' . '"' . $_POST['dlnaname'] . '"', $notify['title'], $notify['msg']);
	}
}
if (isset($_POST['rebuild_dlnadb'])) {
	submitJob('dlnarebuild', '', NOTIFY_TITLE_INFO, 'Database rebuild initiated...');
}

// SECURITY

if (isset($_POST['update_shellinabox']) && $_POST['shellinabox'] != $_SESSION['shellinabox']) {
	phpSession('write', 'shellinabox', $_POST['shellinabox']);
	submitJob('shellinabox', $_POST['shellinabox']);
}

// HTTPS mode
if (isset($_POST['update_nginx_https_only']) && $_POST['nginx_https_only'] != $_SESSION['nginx_https_only']) {
	$_SESSION['nginx_https_only'] = $_POST['nginx_https_only'];
	$notify = $_POST['nginx_https_only'] == '0' ?
		array('title' => NOTIFY_TITLE_INFO, 'msg' => NOTIFY_MSG_SYSTEM_RESTART_REQD) :
		array('title' => NOTIFY_TITLE_INFO, 'msg' => 'Download the certificate, install it into the OS certificate store then restart.');
	$duration = $_POST['nginx_https_only'] == '0' ? NOTIFY_DURATION_DEFAULT : 30;
	submitJob('nginx_https_only', $_POST['nginx_https_only'], $notify['title'], $notify['msg'], $duration);
}
// NGINX certificate type
if (isset($_POST['update_nginx_cert_type']) && $_POST['nginx_cert_type'] != $_SESSION['nginx_cert_type']) {
	$_SESSION['nginx_cert_type'] = $_POST['nginx_cert_type'];
}
// HTTP Strict Transport Security (HSTS)
//SAVE:if (isset($_POST['update_nginx_hsts_policy']) && $_POST['nginx_hsts_policy'] != $_SESSION['nginx_hsts_policy']) {
//	$_SESSION['nginx_hsts_policy'] = $_POST['nginx_hsts_policy'];
//	$str = 'add_header Strict-Transport-Security "max-age=31536000; includeSubDomains; preload" always;';
//	$cmd = $_SESSION['nginx_hsts_policy'] == '1' ?
//		's/^#add_header Strict-Transport-Security.*/' . $str . '/' :
//		's/^add_header Strict-Transport-Security.*/#' . $str . '/';
//	sysCmd("sed -i '" . $cmd . "' /etc/nginx/ssl.conf");
//}
// Download self-signed certificate
if (isset($_POST['download_self_signed_cert'])) {
	if (file_exists('/etc/ssl/certs/moode.crt')) {
		// Create Distinguished Encoding Rules (DER) encoded file
		$userID = getUserID();
		sysCmd('openssl x509 -outform der -in /etc/ssl/certs/moode.crt -out ' . TMP_SELF_SIGNED_CER_FILE);
		sysCmd('chown ' . $userID . ':' . $userID . ' ' . TMP_SELF_SIGNED_CER_FILE);

		$fileName = $_SESSION['hostname'] . '.local.cer';
		phpSession('close');

		header("Content-Description: File Transfer");
		header("Content-type: application/octet-stream");
		header("Content-Transfer-Encoding: binary");
		header("Content-Disposition: attachment; filename=\"" . $fileName . "\"");
		header("Content-length: " . filesize(TMP_SELF_SIGNED_CER_FILE));
		header("Pragma: no-cache");
		header("Expires: 0");
		readfile (TMP_SELF_SIGNED_CER_FILE);
		sysCmd('rm ' . TMP_SELF_SIGNED_CER_FILE);
		exit();
	} else {
		$_SESSION['notify']['title'] = NOTIFY_TITLE_ALERT;
		$_SESSION['msg'] = "Certificate file missing. Download cancelled.";
	}
}
// Upload manually generated certificate .crt and .key files
if (isset($_POST['upload_nginx_cert_files'])) {
	//workerLog(print_r($_FILES, true));
	if (empty($_FILES['nginx_cert_files']['name'][0]) || empty($_FILES['nginx_cert_files']['name'][1])) {
		$_SESSION['notify']['title'] = NOTIFY_TITLE_ALERT;
		$_SESSION['msg'] = 'Missing certificate file. Both the .crt and .key files must be selected and uploaded.';
	} else {
		$file0 = $_FILES['nginx_cert_files']['name'][0];
		$file1 = $_FILES['nginx_cert_files']['name'][1];
		$_uploaded_cert_files = 'Uploaded: <b>' . $file0 . ', ' . $file1 . '</b>';
		if (substr($file0, -4) == '.crt' && substr($file1, -4) == '.key') {
			rename($_FILES['nginx_cert_files']['tmp_name'][0], TMP_NGINX_CRT_FILE);
			rename($_FILES['nginx_cert_files']['tmp_name'][1], TMP_NGINX_KEY_FILE);
		} else if (substr($file0, -4) == '.key' && substr($file1, -4) == '.crt') {
			rename($_FILES['nginx_cert_files']['tmp_name'][1], TMP_NGINX_CRT_FILE);
			rename($_FILES['nginx_cert_files']['tmp_name'][0], TMP_NGINX_KEY_FILE);
		} else {
			$_SESSION['notify']['title'] = NOTIFY_TITLE_ALERT;
			$_SESSION['msg'] = 'Missing certificate file. Either the .crt or .key file is missing.';
		}
	}
}
// Install manually generated certificate
if (isset($_POST['nginx_install_cert']) && $_POST['nginx_install_cert'] == 1) {
	if (!file_exists(TMP_NGINX_CRT_FILE) || !file_exists(TMP_NGINX_KEY_FILE)) {
		$_SESSION['notify']['title'] = NOTIFY_TITLE_ALERT;
		$_SESSION['msg'] = 'Certificate file(s) missing. Installation cancelled.';
	} else {
		sysCmd('mv ' . TMP_NGINX_CRT_FILE . ' /etc/ssl/certs/');
		sysCmd('mv ' . TMP_NGINX_KEY_FILE . ' /etc/ssl/private/');
		$_SESSION['notify']['title'] = NOTIFY_TITLE_INFO;
		$_SESSION['msg'] = 'Certificate installed.';
	}
}

// LOGS

if (isset($_POST['download_logs']) && $_POST['download_logs'] == '1') {
	$fileName = 'moode.log';
	$fileLocation = '/var/log/';
	phpSession('close');

	header("Content-Description: File Transfer");
	header("Content-Type: application/log");
	header("Content-Disposition: attachment; filename=\"". $fileName ."\"");
	readfile ($fileLocation . $fileName);
 	exit();
}

if (isset($_POST['update_clear_syslogs'])) {
	submitJob('clearsyslogs', '', NOTIFY_TITLE_INFO, 'System logs cleared');
}

if (isset($_POST['update_clear_playhistory'])) {
	submitJob('clearplayhistory', '', NOTIFY_TITLE_INFO, 'Playback history cleared');
}

if (isset($_POST['update_debuglog']) && $_POST['debuglog'] != $_SESSION['debuglog']) {
	$_SESSION['debuglog'] = $_POST['debuglog'];
}

phpSession('close');

// Clean out any temp file leftovers from Backup/Restore screens
sysCmd('rm /tmp/backup.zip /tmp/moodecfg.ini /tmp/restore.zip /tmp/py.log /tmp/script');

// SOFTWARE UPDATE

$autoClick = " onchange=\"autoClick('#btn-set-updater-auto-check');\"";
$_select['updater_auto_check_on']  .= "<input type=\"radio\" name=\"updater_auto_check\" id=\"toggle-updater-auto-check-1\" value=\"On\" " . (($_SESSION['updater_auto_check'] == 'On') ? "checked=\"checked\"" : "") . $autoClick . ">\n";
$_select['updater_auto_check_off'] .= "<input type=\"radio\" name=\"updater_auto_check\" id=\"toggle-updater-auto-check-2\" value=\"Off\" " . (($_SESSION['updater_auto_check'] == 'Off') ? "checked=\"checked\"" : "") . $autoClick . ">\n";

// GENERAL

$_select['hostname'] = $_SESSION['hostname'];
$_select['browsertitle'] = $_SESSION['browsertitle'];
$_timezone['timezone'] = buildTimezoneSelect($_SESSION['timezone']);
$_keyboard['keyboard'] = buildKeyboardSelect($_SESSION['keyboard']);

// STARTUP OPTIONS

$_select['worker_responsiveness'] .= "<option value=\"Default\" " . (($_SESSION['worker_responsiveness'] == 'Default') ? "selected" : "") . ">Default</option>\n";
$_select['worker_responsiveness'] .= "<option value=\"Boosted\" " . (($_SESSION['worker_responsiveness'] == 'Boosted') ? "selected" : "") . ">Boosted</option>\n";

$_select['cpugov'] .= "<option value=\"ondemand\" " . (($_SESSION['cpugov'] == 'ondemand') ? "selected" : "") . ">On-demand</option>\n";
$_select['cpugov'] .= "<option value=\"performance\" " . (($_SESSION['cpugov'] == 'performance') ? "selected" : "") . ">Performance</option>\n";

$piModel = substr($_SESSION['hdwrrev'], 3, 1);
$piName = $_SESSION['hdwrrev'];

// Pi-5
if ($piModel == '5') {
	$_reduce_power_hide = '';
	$autoClick = " onchange=\"autoClick('#btn-set-reduce-power');\"";
	$_select['reduce_power_on']  .= "<input type=\"radio\" name=\"reduce_power\" id=\"toggle-reduce-power-1\" value=\"on\" " . (($_SESSION['reduce_power'] == 'on') ? "checked=\"checked\"" : "") . $autoClick . ">\n";
	$_select['reduce_power_off'] .= "<input type=\"radio\" name=\"reduce_power\" id=\"toggle-reduce-power-2\" value=\"off\" " . (($_SESSION['reduce_power'] == 'off') ? "checked=\"checked\"" : "") . $autoClick . ">\n";
	$_pci_express_hide = '';
	$_select['pci_express'] .= "<option value=\"off\" " . (($_SESSION['pci_express'] == 'off') ? "selected" : "") . ">Off</option>\n";
	$_select['pci_express'] .= "<option value=\"gen2\" " . (($_SESSION['pci_express'] == 'gen2') ? "selected" : "") . ">Gen 2.0</option>\n";
	$_select['pci_express'] .= "<option value=\"gen3\" " . (($_SESSION['pci_express'] == 'gen3') ? "selected" : "") . ">Gen 3.0</option>\n";
	$_pi_audio_driver_hide = 'hide';
} else {
	$_reduce_power_hide = 'hide';
	$_pci_express_hide = 'hide';
	$_pi_audio_driver_hide = '';
	$_select['pi_audio_driver'] .= "<option value=\"" . PI_VC4_KMS_V3D . "\" " . (($_SESSION['pi_audio_driver'] == PI_VC4_KMS_V3D) ? "selected" : "") . ">Kernel mode (Default)</option>\n";
	$_select['pi_audio_driver'] .= "<option value=\"" . PI_SND_BCM2835 . "\" " . (($_SESSION['pi_audio_driver'] == PI_SND_BCM2835) ? "selected" : "") . ">Firmware mode (Legacy)</option>\n";
}

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

$actled = explode(',', $_SESSION['led_state'])[0];
$autoClick = " onchange=\"autoClick('#btn-set-actled');\"";
$_select['actled_on']  .= "<input type=\"radio\" name=\"actled\" id=\"toggle-actled-1\" value=\"1\" " . (($actled == '1') ? "checked=\"checked\"" : "") . $autoClick . ">\n";
$_select['actled_off'] .= "<input type=\"radio\" name=\"actled\" id=\"toggle-actled-2\" value=\"0\" " . (($actled == '0') ? "checked=\"checked\"" : "") . $autoClick . ">\n";

if ($PiModel == '1' ||
	$piModel == '5' ||
	str_contains($_SESSION['hdwrrev'], 'Pi-Zero') ||
	str_contains($_SESSION['hdwrrev'], 'Allo USBridge SIG')) {
	$_pwrled_hide = 'hide';
} else {
	$_pwrled_hide = '';
	$pwrled = explode(',', $_SESSION['led_state'])[1];
	$autoClick = " onchange=\"autoClick('#btn-set-pwrled');\"";
	$_select['pwrled_on']  .= "<input type=\"radio\" name=\"pwrled\" id=\"toggle-pwrled-1\" value=\"1\" " . (($pwrled == '1') ? "checked=\"checked\"" : "") . $autoClick . ">\n";
	$_select['pwrled_off'] .= "<input type=\"radio\" name=\"pwrled\" id=\"toggle-pwrled-2\" value=\"0\" " . (($pwrled == '0') ? "checked=\"checked\"" : "") . $autoClick . ">\n";
}

$_select['ipaddr_timeout'] .= "<option value=\"10\" " . (($_SESSION['ipaddr_timeout'] == '10') ? "selected" : "") . ">10 secs</option>\n";
$_select['ipaddr_timeout'] .= "<option value=\"30\" " . (($_SESSION['ipaddr_timeout'] == '30') ? "selected" : "") . ">30 secs</option>\n";
$_select['ipaddr_timeout'] .= "<option value=\"60\" " . (($_SESSION['ipaddr_timeout'] == '60') ? "selected" : "") . ">60 secs</option>\n";
$_select['ipaddr_timeout'] .= "<option value=\"90\" " . (($_SESSION['ipaddr_timeout'] == '90') ? "selected" : "") . ">90 secs (Default)</option>\n";
$_select['ipaddr_timeout'] .= "<option value=\"120\" " . (($_SESSION['ipaddr_timeout'] == '120') ? "selected" : "") . ">2 mins</option>\n";
$_select['ipaddr_timeout'] .= "<option value=\"180\" " . (($_SESSION['ipaddr_timeout'] == '180') ? "selected" : "") . ">3 mins</option>\n";
$_select['ipaddr_timeout'] .= "<option value=\"300\" " . (($_SESSION['ipaddr_timeout'] == '300') ? "selected" : "") . ">5 mins</option>\n";

$autoClick = " onchange=\"autoClick('#btn-set-eth0chk');\"";
$_select['eth0chk_on']  .= "<input type=\"radio\" name=\"eth0chk\" id=\"toggle-eth0chk-1\" value=\"1\" " . (($_SESSION['eth0chk'] == 1) ? "checked=\"checked\"" : "") . $autoClick . ">\n";
$_select['eth0chk_off'] .= "<input type=\"radio\" name=\"eth0chk\" id=\"toggle-eth0chk-2\" value=\"0\" " . (($_SESSION['eth0chk'] == 0) ? "checked=\"checked\"" : "") . $autoClick . ">\n";

// FILE SHARING

$autoClick = " onchange=\"autoClick('#btn-set-fs-smb');\"";
$_select['fs_smb_on']  .= "<input type=\"radio\" name=\"fs_smb\" id=\"toggle-fs-smb-1\" value=\"On\" " . (($_SESSION['fs_smb'] == 'On') ? "checked=\"checked\"" : "") . $autoClick . ">\n";
$_select['fs_smb_off'] .= "<input type=\"radio\" name=\"fs_smb\" id=\"toggle-fs-smb-2\" value=\"Off\" " . (($_SESSION['fs_smb'] == 'Off') ? "checked=\"checked\"" : "") . $autoClick . ">\n";

$autoClick = " onchange=\"autoClick('#btn-set-fs-nfs');\"";
$_select['fs_nfs_on']  .= "<input type=\"radio\" name=\"fs_nfs\" id=\"toggle-fs-nfs-1\" value=\"On\" " . (($_SESSION['fs_nfs'] == 'On') ? "checked=\"checked\"" : "") . $autoClick . ">\n";
$_select['fs_nfs_off'] .= "<input type=\"radio\" name=\"fs_nfs\" id=\"toggle-fs-nfs-2\" value=\"Off\" " . (($_SESSION['fs_nfs'] == 'Off') ? "checked=\"checked\"" : "") . $autoClick . ">\n";
$_select['fs_nfs_access'] = $_SESSION['fs_nfs_access'];
$_select['fs_nfs_options'] = $_SESSION['fs_nfs_options'];
// Subnet
$iface = !empty(sysCmd('ip addr list | grep eth0 | grep inet')) ? 'eth0' : 'wlan0';
$netMask = sysCmd("ifconfig " . $iface . " | awk 'NR==2{print $4}'")[0];
$ipAddrParts = explode('.', $_SESSION['ipaddress']);
$_this_subnet = $ipAddrParts[0] . '.' . $ipAddrParts[1] . '.' . $ipAddrParts[2] . '.0/' . CIDR_TABLE[$netMask];

$_feat_minidlna = $_SESSION['feat_bitmask'] & FEAT_MINIDLNA ? '' : 'hide';
$_dlna_btn_disable = $_SESSION['dlnasvc'] == '1' ? '' : 'disabled';
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
// HTTPS mode
if ($_SESSION['feat_bitmask'] & FEAT_HTTPS) {
	$_feat_https = '';
	if ($_SESSION['nginx_cert_type'] == 'automatic') {
		$_manual_cert = 'hide';
		$_automatic_cert = '';
	} else {
		$_manual_cert = '';
		$_automatic_cert = 'hide';
	}
	// HTTPS mode
	$autoClick = " onchange=\"autoClick('#btn-set-nginx-https-only');\"";
	$_select['nginx_https_only_on']  .= "<input type=\"radio\" name=\"nginx_https_only\" id=\"toggle-nginx-https-only-1\" value=\"1\" " . (($_SESSION['nginx_https_only'] == 1) ? "checked=\"checked\"" : "") . $autoClick . ">\n";
	$_select['nginx_https_only_off'] .= "<input type=\"radio\" name=\"nginx_https_only\" id=\"toggle-nginx-https-only-2\" value=\"0\" " . (($_SESSION['nginx_https_only'] == 0) ? "checked=\"checked\"" : "") . $autoClick . ">\n";
	// NGINX certificate type
	$_select['nginx_cert_type'] .= "<option value=\"automatic\" " . (($_SESSION['nginx_cert_type'] == 'automatic') ? "selected" : "") . ">Automatic</option>\n";
	$_select['nginx_cert_type'] .= "<option value=\"manual\" " . (($_SESSION['nginx_cert_type'] == 'manual') ? "selected" : "") . ">Manual</option>\n";
	// HTTP Strict Transport Security (HSTS)
	/*SAVE:$autoClick = " onchange=\"autoClick('#btn-set-nginx-hsts-policy');\" " . $_https_btn_disabled;
	$_select['nginx_hsts_policy_on']  .= "<input type=\"radio\" name=\"nginx_hsts_policy\" id=\"toggle-nginx-hsts-policy-1\" value=\"1\" " . (($_SESSION['nginx_hsts_policy'] == 1) ? "checked=\"checked\"" : "") . $autoClick . ">\n";
	$_select['nginx_hsts_policy_off'] .= "<input type=\"radio\" name=\"nginx_hsts_policy\" id=\"toggle-nginx-hsts-policy-2\" value=\"0\" " . (($_SESSION['nginx_hsts_policy'] == 0) ? "checked=\"checked\"" : "") . $autoClick . ">\n";*/
} else {
	$_feat_https = 'hide';
}

// LOGS

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
