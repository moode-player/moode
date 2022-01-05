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

require_once dirname(__FILE__) . '/inc/playerlib.php';
require_once dirname(__FILE__) . '/inc/timezone.php';
require_once dirname(__FILE__) . '/inc/keyboard.php';

playerSession('open', '' ,'');

// SOFTWARE UPDATE

// Check for software update
if (isset($_POST['checkfor_update'])) {
	$available = checkForUpd($_SESSION['res_software_upd_url'] . '/');
	$lastinstall = checkForUpd('/var/local/www/');

	// Up to date
	if ($available['Date'] == $lastinstall['Date']) {
		$_available_upd = 'Software is up to date<br>';
	}
	// Image-only release available
	elseif ($available['ImageOnly'] == 'Yes') {
		$_available_upd = 'A new image-only release of moOde is available. Visit <a href="http://moodeaudio.org" class="moode-about-link" target="_blank">moodeaudio.org</a> for more information.';
	}
	// In-place update available
	else {
		$_available_upd = $available['Date'] == 'None' ? $available['Date'] . '<br>' : 'Package date: ' . $available['Date'] .
			'<button class="btn btn-primary btn-small set-button btn-submit" id="install-update" type="submit" name="install_update" value="1">Install</button>' .
			'<button class="btn btn-primary btn-small set-button" data-toggle="modal" href="#view-pkgcontent">View</button><br>' .
			'<span class="help-block-configs help-block-margin" style="margin-bottom:5px">Progress can be monitored via SSH cmd: moodeutl -t</span>';
		$_pkg_description = $available['Description'];
		$_pkg_relnotes = $available['Relnotes'];
	}
}

// install software update
if (isset($_POST['install_update'])) {
	if ($_POST['install_update'] == 1) {
		$mount = sysCmd('mount | grep "moode.sqsh"');
		$space = sysCmd("df | grep /dev/root | awk '{print $4}'");
		# check for invalid configs
		if ($mount[0] != '/var/local/moode.sqsh on /var/www type squashfs (ro,relatime)' && ($_SESSION['feat_bitmask'] & FEAT_SQSHCHK)) {
			$_SESSION['notify']['title'] = 'Invalid configuration';
			$_SESSION['notify']['msg'] = "Cannot find compressed file system";
			$_SESSION['notify']['duration'] = 20;
		}
		elseif ($mount[0] == '/var/local/moode.sqsh on /var/www type squashfs (ro,relatime)' && !($_SESSION['feat_bitmask'] & FEAT_SQSHCHK)) {
			$_SESSION['notify']['title'] = 'Invalid configuration';
			$_SESSION['notify']['msg'] = "File system is compressed and read-only";
			$_SESSION['notify']['duration'] = 20;
		}
		elseif ($space[0] < 512000) {
			$_SESSION['notify']['title'] = 'Insufficient space';
			$_SESSION['notify']['msg'] = "Update cannot proceed without at least 500M space";
			$_SESSION['notify']['duration'] = 20;
		}
		else {
			submitJob('installupd', '', 'Software update installed', 'Restart required', 60000); // Keep message displayed
			$_available_upd = 'Software is up to date<br>';
		}
	}
}

// GENERAL

// timezone
if (isset($_POST['update_time_zone'])) {
	if (isset($_POST['timezone']) && $_POST['timezone'] != $_SESSION['timezone']) {
		submitJob('timezone', $_POST['timezone'], 'Timezone set to ' . $_POST['timezone'], '');
		playerSession('write', 'timezone', $_POST['timezone']);
	}
}

// host name
if (isset($_POST['update_host_name'])) {
	if (isset($_POST['hostname']) && $_POST['hostname'] != $_SESSION['hostname']) {
		if (preg_match("/[^A-Za-z0-9-]/", $_POST['hostname']) == 1) {
			$_SESSION['notify']['title'] = 'Invalid input';
			$_SESSION['notify']['msg'] = "Host name can only contain A-Z, a-z, 0-9 or hyphen (-).";
			$_SESSION['notify']['duration'] = 3;
		}
		else {
			submitJob('hostname', '"' . $_SESSION['hostname'] . '" ' . '"' . $_POST['hostname'] . '"', 'Host name changed', 'Restart required');
			playerSession('write', 'hostname', $_POST['hostname']);
		}
	}
}

// keyboard layout
if (isset($_POST['update_keyboard'])) {
    if (isset($_POST['keyboard']) && $_POST['keyboard'] != $_SESSION['keyboard']) {
        submitJob('keyboard', $_POST['keyboard'], 'Keyboard layout updated ', 'Restart required');
        playerSession('write', 'keyboard', $_POST['keyboard']);
    }
}

// browser title
if (isset($_POST['update_browser_title'])) {
	if (isset($_POST['browsertitle']) && $_POST['browsertitle'] != $_SESSION['browsertitle']) {
		playerSession('write', 'browsertitle', $_POST['browsertitle']);
	}
}

// SYSTEM MODIFICATIONS

// CPU governor
if (isset($_POST['update_cpugov'])) {
	submitJob('cpugov', $_POST['cpugov'], 'CPU governor updated', '');
	playerSession('write', 'cpugov', $_POST['cpugov']);
}

// Linux kernel
if (isset($_POST['update_kernel_architecture']) && $_POST['kernel_architecture'] != $_SESSION['kernel_architecture']) {
	submitJob('kernel_architecture', $_POST['kernel_architecture'], $_POST['kernel_architecture'] . ' kernel selected', 'Restart required');
	playerSession('write', 'kernel_architecture', $_POST['kernel_architecture']);
}

// USB auto-mounter
if (isset($_POST['update_usb_auto_mounter'])) {
	submitJob('usb_auto_mounter', $_POST['usb_auto_mounter'], 'USB auto-mounter updated', 'Restart required');
	playerSession('write', 'usb_auto_mounter', $_POST['usb_auto_mounter']);
}

// Integrated WiFi adapter
if (isset($_POST['p3wifi']) && $_POST['p3wifi'] != $_SESSION['p3wifi']) {
	$title = $_POST['p3wifi'] == 1 ? 'WiFi adapter on' : 'WiFi adapter off';
	submitJob('p3wifi', $_POST['p3wifi'], $title, 'Restart required');
	playerSession('write', 'p3wifi', $_POST['p3wifi']);
}

// Integrated Bluetooth adapter
if (isset($_POST['p3bt']) && $_POST['p3bt'] != $_SESSION['p3bt']) {
	$title = $_POST['p3bt'] == 1 ? 'Bluetooth adapter on' : 'Bluetooth adapter off';
	submitJob('p3bt', $_POST['p3bt'], $title, 'Restart required');
	playerSession('write', 'p3bt', $_POST['p3bt']);
}

// HDMI port
if (isset($_POST['hdmiport']) && $_POST['hdmiport'] != $_SESSION['hdmiport']) {
	$title = $_POST['hdmiport'] == 1 ? 'HDMI port on' : 'HDMI port off';
	submitJob('hdmiport', $_POST['hdmiport'], $title, '');
	playerSession('write', 'hdmiport', $_POST['hdmiport']);
}

// Activity LED (LED0)
if (isset($_POST['update_actled']) && $_POST['actled'] != explode(',', $_SESSION['led_state'])[0]) {
	$title = $_POST['actled'] == '1' ? 'Activity LED on' : 'Activity LED off';
	submitJob('actled', $_POST['actled'], $title, '');
	playerSession('write', 'led_state', $_POST['actled'] . ',' . explode(',', $_SESSION['led_state'])[1]);
}

// Power LED (LED1)
if (isset($_POST['update_pwrled']) && $_POST['pwrled'] != explode(',', $_SESSION['led_state'])[1]) {
	$title = $_POST['pwrled'] == '1' ? 'Power LED on' : 'Power LED off';
	submitJob('pwrled', $_POST['pwrled'], $title, '');
	playerSession('write', 'led_state', explode(',', $_SESSION['led_state'])[0] . ',' . $_POST['pwrled']);
}


// IP address timeout
if (isset($_POST['update_ipaddr_timeout']) && $_POST['ipaddr_timeout'] != $_SESSION['ipaddr_timeout']) {
	$_SESSION['notify']['title'] = 'IP address timeout updated';
	playerSession('write', 'ipaddr_timeout', $_POST['ipaddr_timeout']);
}

// Ethernet adapter IP check
if (isset($_POST['eth0chk']) && $_POST['eth0chk'] != $_SESSION['eth0chk']) {
	$_SESSION['notify']['title'] = 'Ethernet address wait updated';
	playerSession('write', 'eth0chk', $_POST['eth0chk']);
}

// Expand root file system
if (isset($_POST['update_expand_rootfs'])) {
	submitJob('expandrootfs', '', 'File system expanded', 'Restart required', 30);
}

// Enable usb boot
if (isset($_POST['update_usbboot'])) {
	submitJob('usbboot', '', 'USB boot enabled', 'Restart required', 30);
}

// LOCAL DISPLAY

// Local UI display
if (isset($_POST['update_localui'])) {
    if (isset($_POST['localui']) && $_POST['localui'] != $_SESSION['localui']) {
		$title = $_POST['localui'] == 1 ? 'Local UI display on' : 'Local UI display off';
        submitJob('localui', $_POST['localui'], $title, 'Restart may be required');
        playerSession('write', 'localui', $_POST['localui']);
    }
}

// Touch screen capability
if (isset($_POST['update_touchscn'])) {
    if (isset($_POST['touchscn']) && $_POST['touchscn'] != $_SESSION['touchscn']) {
        submitJob('touchscn', $_POST['touchscn'], 'Setting updated', 'Local display restarted');
        playerSession('write', 'touchscn', $_POST['touchscn']);
    }
}

// Screen blank timeout
if (isset($_POST['update_scnblank'])) {
    if (isset($_POST['scnblank']) && $_POST['scnblank'] != $_SESSION['scnblank']) {
        submitJob('scnblank', $_POST['scnblank'], 'Setting updated', 'Local display restarted');
        playerSession('write', 'scnblank', $_POST['scnblank']);
    }
}

// Wake display
if (isset($_POST['update_wake_display'])) {
    if (isset($_POST['wake_display']) && $_POST['wake_display'] != $_SESSION['wake_display']) {
		$_SESSION['notify']['title'] = $_POST['wake_display'] == '1' ? 'Wake display on' : 'Wake display off';
        playerSession('write', 'wake_display', $_POST['wake_display']);
    }
}

// Screen brightness
if (isset($_POST['update_scnbrightness'])) {
    if (isset($_POST['scnbrightness']) && $_POST['scnbrightness'] != $_SESSION['scnbrightness']) {
		submitJob('scnbrightness', $_POST['scnbrightness'], 'Setting updated');
		playerSession('write', 'scnbrightness', $_POST['scnbrightness']);
    }
}

// Pixel aspect ratio
if (isset($_POST['update_pixel_aspect_ratio'])) {
    if (isset($_POST['pixel_aspect_ratio']) && $_POST['pixel_aspect_ratio'] != $_SESSION['pixel_aspect_ratio']) {
		submitJob('pixel_aspect_ratio', $_POST['pixel_aspect_ratio'], 'Setting updated', 'Restart required');
		playerSession('write', 'pixel_aspect_ratio', $_POST['pixel_aspect_ratio']);
    }
}

// Screen rotation
if (isset($_POST['update_scnrotate'])) {
    if (isset($_POST['scnrotate']) && $_POST['scnrotate'] != $_SESSION['scnrotate']) {
		submitJob('scnrotate', $_POST['scnrotate'], 'Setting updated', 'Restart required');
		playerSession('write', 'scnrotate', $_POST['scnrotate']);
    }
}

// Browser cache
if (isset($_POST['update_clear_browser_cache'])) {
	submitJob('clearbrcache', '', 'Cache cleared', 'Refresh Browser on LocalUI');
}

// CoverView show/hide
if (isset($_POST['update_coverview_toggle'])) {
	$_SESSION['coverview_toggle'] = $_SESSION['coverview_toggle'] == '-off' ? '-on' : '-off';
	$result = sysCmd('/var/www/command/coverview.php ' . $_SESSION['coverview_toggle']);
	$_SESSION['notify']['title'] = $result[0];
}

// LOCAL SERVICES

// Metadata for external apps
if (isset($_POST['extmeta']) && $_POST['extmeta'] != $_SESSION['extmeta']) {
	$_SESSION['notify']['title'] = $_POST['extmeta'] == 1 ? 'Metadata file on' : 'Metadata file off';
	$_SESSION['notify']['duration'] = 3;
	playerSession('write', 'extmeta', $_POST['extmeta']);
}

// LCD updater
if (isset($_POST['update_lcdup'])) {
	if (isset($_POST['lcdup']) && $_POST['lcdup'] != $_SESSION['lcdup']) {
		$title = $_POST['lcdup'] == 1 ? 'LCD update engine on' : 'LCD update engine off';
		submitJob('lcdup', $_POST['lcdup'], $title, '');
		playerSession('write', 'lcdup', $_POST['lcdup']);
		playerSession('write', 'extmeta', '1'); // turn on external metadata generation
	}
}

// GPIO
if (isset($_POST['update_gpio_svc']) && $_POST['gpio_svc'] != $_SESSION['gpio_svc']) {
	$title = $_POST['gpio_svc'] == 1 ? 'GPIO button handler on' : 'GPIO button handler off';
	playerSession('write', 'gpio_svc', $_POST['gpio_svc']);
	submitJob('gpio_svc', $_POST['gpio_svc'], $title, '');
}

// Shellinabox
if (isset($_POST['shellinabox']) && $_POST['shellinabox'] != $_SESSION['shellinabox']) {
	$title = $_POST['shellinabox'] == 1 ? 'SSH server on' : 'SSH server off';
	playerSession('write', 'shellinabox', $_POST['shellinabox']);
	submitJob('shellinabox', $_POST['shellinabox'], $title, '');
}

// MAINTENANCE

// Clear system logs
if (isset($_POST['update_clear_syslogs'])) {
	submitJob('clearsyslogs', '', 'System logs cleared', '');
}

// Clear play history log
if (isset($_POST['update_clear_playhistory'])) {
	submitJob('clearplayhistory', '', 'Playback history cleared', '');
}

// Compact sqlite database
if (isset($_POST['update_compactdb'])) {
	submitJob('compactdb', '', 'SQlite DB compacted', '');
}

// Debug logging
if (isset($_POST['debuglog']) && $_POST['debuglog'] != $_SESSION['debuglog']) {
	$_SESSION['debuglog'] = $_POST['debuglog'];
	$_SESSION['notify']['title'] = $_POST['debuglog'] == 1 ? 'Debug logging on' : 'Debug logging off';
	$_SESSION['notify']['duration'] = 3;
}

session_write_close();

// Clean out any temp file leftovers from Backup/Restore screens
sysCmd('rm /tmp/backup.zip /tmp/moodecfg.ini /tmp/restore.zip /tmp/py.log /tmp/script');

// GENERAL

$_timezone['timezone'] = buildTimezoneSelect($_SESSION['timezone']);
$_select['hostname'] = $_SESSION['hostname'];
$_keyboard['keyboard'] = buildKeyboardSelect($_SESSION['keyboard']);
$_select['browsertitle'] = $_SESSION['browsertitle'];

// SYSTEM MODIFICATIONS

// CPU governor
$_select['cpugov'] .= "<option value=\"ondemand\" " . (($_SESSION['cpugov'] == 'ondemand') ? "selected" : "") . ">On-demand</option>\n";
$_select['cpugov'] .= "<option value=\"performance\" " . (($_SESSION['cpugov'] == 'performance') ? "selected" : "") . ">Performance</option>\n";

// Linux kernel
if ($_SESSION['feat_bitmask'] & FEAT_KERNEL) {
	$_feat_kernel = '';
	$_select['kernel_architecture'] .= "<option value=\"32-bit\" " . (($_SESSION['kernel_architecture'] == '32-bit') ? "selected" : "") . ">32-bit</option>\n";
	$model = substr($_SESSION['hdwrrev'], 3, 1);
	$cmmodel = substr($_SESSION['hdwrrev'], 3, 3); // Generic Pi-CM3+, Pi-CM4 for future use
	$name = $_SESSION['hdwrrev'];
	// Pi-2B rev 1.2, Allo USBridge SIG, Pi-3B/B+/A+, Pi-4B
	if ($name == 'Pi-2B 1.2 1GB' || $model == '3' || $model == '4' || $name == 'Allo USBridge SIG [CM3+ Lite 1GB v1.0]') {
		$_select['kernel_architecture'] .= "<option value=\"64-bit\" " . (($_SESSION['kernel_architecture'] == '64-bit') ? "selected" : "") . ">64-bit (Experimental)</option>\n";
	}
}
else {
	$_feat_kernel = 'hide';
}

// USB auto-mounter
$_select['usb_auto_mounter'] .= "<option value=\"udisks-glue\" " . (($_SESSION['usb_auto_mounter'] == 'udisks-glue') ? "selected" : "") . ">Udisks-glue (Default)</option>\n";
$_select['usb_auto_mounter'] .= "<option value=\"devmon\" " . (($_SESSION['usb_auto_mounter'] == 'devmon') ? "selected" : "") . ">Devmon</option>\n";

// WiFi BT
$model = substr($_SESSION['hdwrrev'], 3, 1);
$name = $_SESSION['hdwrrev'];
// Pi-Zero W, Pi-3B/B+/A+, Pi-4B
if ($name == 'Pi-Zero W 1.1 512MB' || $model == '3' || $model == '4') {
	$_wifibt_hide = '';
	$_select['p3wifi1'] .= "<input type=\"radio\" name=\"p3wifi\" id=\"togglep3wifi1\" value=\"1\" " . (($_SESSION['p3wifi'] == 1) ? "checked=\"checked\"" : "") . ">\n";
	$_select['p3wifi0'] .= "<input type=\"radio\" name=\"p3wifi\" id=\"togglep3wifi2\" value=\"0\" " . (($_SESSION['p3wifi'] == 0) ? "checked=\"checked\"" : "") . ">\n";
	$_select['p3bt1'] .= "<input type=\"radio\" name=\"p3bt\" id=\"togglep3bt1\" value=\"1\" " . (($_SESSION['p3bt'] == 1) ? "checked=\"checked\"" : "") . ">\n";
	$_select['p3bt0'] .= "<input type=\"radio\" name=\"p3bt\" id=\"togglep3bt2\" value=\"0\" " . (($_SESSION['p3bt'] == 0) ? "checked=\"checked\"" : "") . ">\n";
}
else {
	$_wifibt_hide = 'hide';
}

// hdmi port
$_select['hdmiport1'] .= "<input type=\"radio\" name=\"hdmiport\" id=\"togglehdmiport1\" value=\"1\" " . (($_SESSION['hdmiport'] == 1) ? "checked=\"checked\"" : "") . ">\n";
$_select['hdmiport0'] .= "<input type=\"radio\" name=\"hdmiport\" id=\"togglehdmiport2\" value=\"0\" " . (($_SESSION['hdmiport'] == 0) ? "checked=\"checked\"" : "") . ">\n";

// Activity LED (LED0)
$actled = explode(',', $_SESSION['led_state'])[0];
$_select['actled1'] .= "<input type=\"radio\" name=\"actled\" id=\"toggle_actled1\" value=\"1\" " . (($actled == '1') ? "checked=\"checked\"" : "") . ">\n";
$_select['actled0'] .= "<input type=\"radio\" name=\"actled\" id=\"toggle_actled2\" value=\"0\" " . (($actled == '0') ? "checked=\"checked\"" : "") . ">\n";

// Power LED (LED1)
if (substr($_SESSION['hdwrrev'], 0, 7) == 'Pi-Zero' || substr($_SESSION['hdwrrev'], 3, 1) == '1' || $_SESSION['hdwrrev'] == 'Allo USBridge SIG [CM3+ Lite 1GB v1.0]') {
	$_pwrled_hide = 'hide';
}
else {
	$_pwrled_hide = '';
	$pwrled = explode(',', $_SESSION['led_state'])[1];
	$_select['pwrled1'] .= "<input type=\"radio\" name=\"pwrled\" id=\"toggle_pwrled1\" value=\"1\" " . (($pwrled == '1') ? "checked=\"checked\"" : "") . ">\n";
	$_select['pwrled0'] .= "<input type=\"radio\" name=\"pwrled\" id=\"toggle_pwrled2\" value=\"0\" " . (($pwrled == '0') ? "checked=\"checked\"" : "") . ">\n";
}

// IP address timeout
$_select['ipaddr_timeout'] .= "<option value=\"10\" " . (($_SESSION['ipaddr_timeout'] == '10') ? "selected" : "") . ">10</option>\n";
$_select['ipaddr_timeout'] .= "<option value=\"20\" " . (($_SESSION['ipaddr_timeout'] == '20') ? "selected" : "") . ">20</option>\n";
$_select['ipaddr_timeout'] .= "<option value=\"30\" " . (($_SESSION['ipaddr_timeout'] == '30') ? "selected" : "") . ">30</option>\n";
$_select['ipaddr_timeout'] .= "<option value=\"60\" " . (($_SESSION['ipaddr_timeout'] == '60') ? "selected" : "") . ">60</option>\n";
$_select['ipaddr_timeout'] .= "<option value=\"90\" " . (($_SESSION['ipaddr_timeout'] == '90') ? "selected" : "") . ">90 (Default)</option>\n";
$_select['ipaddr_timeout'] .= "<option value=\"120\" " . (($_SESSION['ipaddr_timeout'] == '120') ? "selected" : "") . ">120</option>\n";
$_select['ipaddr_timeout'] .= "<option value=\"120\" " . (($_SESSION['ipaddr_timeout'] == '120') ? "selected" : "") . ">180</option>\n";

// eth0 check
$_select['eth0chk1'] .= "<input type=\"radio\" name=\"eth0chk\" id=\"toggleeth0chk1\" value=\"1\" " . (($_SESSION['eth0chk'] == 1) ? "checked=\"checked\"" : "") . ">\n";
$_select['eth0chk0'] .= "<input type=\"radio\" name=\"eth0chk\" id=\"toggleeth0chk2\" value=\"0\" " . (($_SESSION['eth0chk'] == 0) ? "checked=\"checked\"" : "") . ">\n";

// Expand root file system
$_select['expandrootfs1'] .= "<input type=\"radio\" name=\"expandrootfs\" id=\"toggleexpandrootfs1\" value=\"1\" " . ">\n";
$_select['expandrootfs0'] .= "<input type=\"radio\" name=\"expandrootfs\" id=\"toggleexpandrootfs2\" value=\"0\" " . "checked=\"checked\"".">\n";
$result = sysCmd('lsblk -o size -nb /dev/disk/by-label/rootfs');
$_expandrootfs_msg = $result[0] > ROOTFS_SIZE ? 'File system has been expanded' : 'File system has not been expanded yet';

// USB boot
$model = substr($_SESSION['hdwrrev'], 3, 1);
// Pi-3B/B+/A+, NOTE: Pi-4B USB boot not avail as of 2019-07-13
if ($model == '3' /*|| $model == '4'*/) {
	$_usbboot_hide = '';
	$_select['usbboot1'] .= "<input type=\"radio\" name=\"usbboot\" id=\"toggleusbboot1\" value=\"1\" " . ">\n";
	$_select['usbboot0'] .= "<input type=\"radio\" name=\"usbboot\" id=\"toggleusbboot2\" value=\"0\" " . "checked=\"checked\"".">\n";
	$result = sysCmd('vcgencmd otp_dump | grep 17:');
	$_usbboot_msg = $result[0] == '17:3020000a' ? 'USB boot is enabled' : 'USB boot is not enabled yet';

}
else {
	$_usbboot_hide = 'hide';
}

// LOCAL DISPLAY

// Local UI display
if ($_SESSION['feat_bitmask'] & FEAT_LOCALUI) {
	$_feat_localui = '';
	$_select['localui1'] .= "<input type=\"radio\" name=\"localui\" id=\"togglelocalui1\" value=\"1\" " . (($_SESSION['localui'] == 1) ? "checked=\"checked\"" : "") . ">\n";
	$_select['localui0'] .= "<input type=\"radio\" name=\"localui\" id=\"togglelocalui2\" value=\"0\" " . (($_SESSION['localui'] == 0) ? "checked=\"checked\"" : "") . ">\n";

	// Touch capability
	$_select['touchscn1'] .= "<input type=\"radio\" name=\"touchscn\" id=\"toggletouchscn1\" value=\"1\" " . (($_SESSION['touchscn'] == 1) ? "checked=\"checked\"" : "") . ">\n";
	$_select['touchscn0'] .= "<input type=\"radio\" name=\"touchscn\" id=\"toggletouchscn2\" value=\"0\" " . (($_SESSION['touchscn'] == 0) ? "checked=\"checked\"" : "") . ">\n";

	// Wake display
	$_select['wake_display1'] .= "<input type=\"radio\" name=\"wake_display\" id=\"toggle_wake_display1\" value=\"1\" " . (($_SESSION['wake_display'] == 1) ? "checked=\"checked\"" : "") . ">\n";
	$_select['wake_display0'] .= "<input type=\"radio\" name=\"wake_display\" id=\"toggle_wake_display2\" value=\"0\" " . (($_SESSION['wake_display'] == 0) ? "checked=\"checked\"" : "") . ">\n";

	// Screen blank
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

	// Backlight brightess
	$_select['scnbrightness'] = $_SESSION['scnbrightness'];

	// Pixel aspect ratio
	$_select['pixel_aspect_ratio'] .= "<option value=\"Default\" " . (($_SESSION['pixel_aspect_ratio'] == 'Default') ? "selected" : "") . ">Default</option>\n";
	$_select['pixel_aspect_ratio'] .= "<option value=\"Square\" " . (($_SESSION['pixel_aspect_ratio'] == 'Square') ? "selected" : "") . ">Square</option>\n";

	// Screen rotate
	$_select['scnrotate'] .= "<option value=\"0\" " . (($_SESSION['scnrotate'] == '0') ? "selected" : "") . ">0 Deg</option>\n";
	$_select['scnrotate'] .= "<option value=\"180\" " . (($_SESSION['scnrotate'] == '180') ? "selected" : "") . ">180 Deg</option>\n";
}
else {
	$_feat_localui = 'hide';
}

// LOCAL SERVICES

// Metadata file
$_select['extmeta1'] .= "<input type=\"radio\" name=\"extmeta\" id=\"toggleextmeta1\" value=\"1\" " . (($_SESSION['extmeta'] == 1) ? "checked=\"checked\"" : "") . ">\n";
$_select['extmeta0'] .= "<input type=\"radio\" name=\"extmeta\" id=\"toggleextmeta2\" value=\"0\" " . (($_SESSION['extmeta'] == 0) ? "checked=\"checked\"" : "") . ">\n";

// LCD updater
$_select['lcdup1'] .= "<input type=\"radio\" name=\"lcdup\" id=\"togglelcdup1\" value=\"1\" " . (($_SESSION['lcdup'] == 1) ? "checked=\"checked\"" : "") . ">\n";
$_select['lcdup0'] .= "<input type=\"radio\" name=\"lcdup\" id=\"togglelcdup2\" value=\"0\" " . (($_SESSION['lcdup'] == 0) ? "checked=\"checked\"" : "") . ">\n";

// GPIO
if ($_SESSION['feat_bitmask'] & FEAT_GPIO) {
	$_feat_gpio = '';
	$_select['gpio_svc1'] .= "<input type=\"radio\" name=\"gpio_svc\" id=\"toggle_gpio_svc1\" value=\"1\" " . (($_SESSION['gpio_svc'] == 1) ? "checked=\"checked\"" : "") . ">\n";
	$_select['gpio_svc0'] .= "<input type=\"radio\" name=\"gpio_svc\" id=\"toggle_gpio_svc2\" value=\"0\" " . (($_SESSION['gpio_svc'] == 0) ? "checked=\"checked\"" : "") . ">\n";
}
else {
	$_feat_gpio = 'hide';
}
// Shellinabox
$_select['shellinabox1'] .= "<input type=\"radio\" name=\"shellinabox\" id=\"toggleshellinabox1\" value=\"1\" " . (($_SESSION['shellinabox'] == 1) ? "checked=\"checked\"" : "") . ">\n";
$_select['shellinabox0'] .= "<input type=\"radio\" name=\"shellinabox\" id=\"toggleshellinabox2\" value=\"0\" " . (($_SESSION['shellinabox'] == 0) ? "checked=\"checked\"" : "") . ">\n";
$_select['hostip'] = getHostIp();
if ($_SESSION['shellinabox'] == '1') {
	$_ssh_btn_disable = '';
	$_ssh_link_disable = '';
}
else {
	$_ssh_btn_disable = 'disabled';
	$_ssh_link_disable = 'onclick="return false;"';
}

// MAINTENANCE

// Debug logging
$_select['debuglog1'] .= "<input type=\"radio\" name=\"debuglog\" id=\"toggledebuglog1\" value=\"1\" " . (($_SESSION['debuglog'] == 1) ? "checked=\"checked\"" : "") . ">\n";
$_select['debuglog0'] .= "<input type=\"radio\" name=\"debuglog\" id=\"toggledebuglog2\" value=\"0\" " . (($_SESSION['debuglog'] == 0) ? "checked=\"checked\"" : "") . ">\n";

waitWorker(1, 'sys-config');

$tpl = "sys-config.html";
$section = basename(__FILE__, '.php');
storeBackLink($section, $tpl);

include('header.php');
eval("echoTemplate(\"" . getTemplate("templates/$tpl") . "\");");
include('footer.php');
