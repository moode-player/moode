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
 * 2019-05-07 TC moOde 5.2
 *
 */

require_once dirname(__FILE__) . '/inc/playerlib.php';
require_once dirname(__FILE__) . '/inc/timezone.php';
require_once dirname(__FILE__) . '/inc/keyboard.php';

playerSession('open', '' ,'');

// SOFTWARE UPDATE AND IMAGE BUILDER DOWNLOAD

// check for software update
if (isset($_POST['checkfor_update'])) {
	$available = checkForUpd($_SESSION['res_software_upd_url'] . '/');
	$lastinstall = checkForUpd('/var/local/www/');

	// up to date
	if ($available['pkgdate'] == $lastinstall['pkgdate']) {
	//if ($available['pkgdate'] != $lastinstall['pkgdate']) { // set to != for testing
		$_available_upd = 'Software is up to date<br>';
	}
	else {
		// update available 
		$_available_upd .= '<u><em>Available</u></em><br>';
		$_available_upd .= $available['pkgdate'] == 'None' ? $available['pkgdate'] . '<br>' : 'Package date: ' . $available['pkgdate'] . 
		//$_available_upd .= $available['pkgdate'] != 'None' ? $available['pkgdate'] . '<br>' : 'Package date: ' . $available['pkgdate'] .  // set to != for testing
			'<button class="btn btn-primary btn-small set-button btn-submit" id="install-update" type="submit" name="install_update" value="1">Install</button>' .
			'<button class="btn btn-primary btn-small set-button" data-toggle="modal" href="#view-pkgcontent">View</button><br>' . 
			'<span class="help-block-configs help-block-margin" style="margin-bottom:5px">Progress can be monitored via SSH cmd: moodeutl -t</span>'; //r45a

		$_pkg_description = $available['pkgdesc'];
		$cnt = $available['linecnt'];
		for ($i = 1; $i <= $cnt; $i++) {
			$_pkg_content .= '<li>' . $available[$i] . '</li>';
		}

		// last installed
		$_lastinstall_upd .= '<u><em>Last installed</u></em><br>'; 
		$_lastinstall_upd .= $lastinstall['pkgdate'] == 'None' ? $lastinstall['pkgdate'] : 'Package date: ' . $lastinstall['pkgdate'];
		$_lastinstall_upd .= '<br>';
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
		elseif ($space[0] < 500000) {
			$_SESSION['notify']['title'] = 'Insufficient space';
			$_SESSION['notify']['msg'] = "Update cannot proceed without at least 500M space";
			$_SESSION['notify']['duration'] = 20;
		}
		else {
			submitJob('installupd', '', 'Software update installed', 'Reboot required', 60);
			$_available_upd = 'Software is up to date<br>';
			$_lastinstall_upd = '';
		}
	}
}

// GEBERAL

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
		} else {
			submitJob('hostname', '"' . $_SESSION['hostname'] . '" ' . '"' . $_POST['hostname'] . '"', 'Host name changed', 'Reboot required');
			playerSession('write', 'hostname', $_POST['hostname']);
		}
	}
}

// keyboard layout
if (isset($_POST['update_keyboard'])) {
    if (isset($_POST['keyboard']) && $_POST['keyboard'] != $_SESSION['keyboard']) {
        submitJob('keyboard', $_POST['keyboard'], 'Keyboard layout updated ', 'Reboot required');
        playerSession('write', 'keyboard', $_POST['keyboard']);
    } 
}

// browser title
if (isset($_POST['update_browser_title'])) {
	if (isset($_POST['browsertitle']) && $_POST['browsertitle'] != $_SESSION['browsertitle']) {
		submitJob('browsertitle', '"' . $_SESSION['browsertitle'] . '" ' . '"' . $_POST['browsertitle'] . '"', 'Browser title changed', 'Refresh Browser');
		playerSession('write', 'browsertitle', $_POST['browsertitle']);
	} 
}

// SYSTEM MODIFICATIONS

// cpu governor
if (isset($_POST['update_cpugov'])) {
	submitJob('cpugov', $_POST['cpugov'], 'CPU governor updated', '');
	playerSession('write', 'cpugov', $_POST['cpugov']);
} 

// integrated WiFi adapter 
if (isset($_POST['p3wifi']) && $_POST['p3wifi'] != $_SESSION['p3wifi']) {
	$title = $_POST['p3wifi'] == 1 ? 'WiFi adapter on' : 'WiFi adapter off';
	submitJob('p3wifi', $_POST['p3wifi'], $title, 'Reboot required');
	playerSession('write', 'p3wifi', $_POST['p3wifi']);
}

// integrated Bluetooth adapter 
if (isset($_POST['p3bt']) && $_POST['p3bt'] != $_SESSION['p3bt']) {
	$title = $_POST['p3bt'] == 1 ? 'Bluetooth adapter on' : 'Bluetooth adapter off';
	submitJob('p3bt', $_POST['p3bt'], $title, 'Reboot required');
	playerSession('write', 'p3bt', $_POST['p3bt']);
}

// HDMI port
if (isset($_POST['hdmiport']) && $_POST['hdmiport'] != $_SESSION['hdmiport']) {
	$title = $_POST['hdmiport'] == 1 ? 'HDMI port on' : 'HDMI port off';
	submitJob('hdmiport', $_POST['hdmiport'], $title, '');
	playerSession('write', 'hdmiport', $_POST['hdmiport']);
}

// eth0 check
if (isset($_POST['eth0chk']) && $_POST['eth0chk'] != $_SESSION['eth0chk']) {
	$_SESSION['notify']['title'] = $_POST['eth0chk'] == 1 ? 'Eth0 IP check on' : 'Eth0 IP check off';
	$_SESSION['notify']['msg'] = 'Reboot required';
	playerSession('write', 'eth0chk', $_POST['eth0chk']);
}

// set USB curent to 2X (1200 mA)
if (isset($_POST['maxusbcurrent']) && $_POST['maxusbcurrent'] != $_SESSION['maxusbcurrent']) {
	$title = $_POST['maxusbcurrent'] == 1 ? 'USB current 2x on' : 'USB current 2x off';
	submitJob('maxusbcurrent', $_POST['maxusbcurrent'], $title, 'Reboot required');
	playerSession('write', 'maxusbcurrent', $_POST['maxusbcurrent']);
}

// uac2 fix
if (isset($_POST['update_uac2fix'])) {
	if (isset($_POST['uac2fix']) && $_POST['uac2fix'] != $_SESSION['uac2fix']) {
		$title = $_POST['uac2fix'] == 1 ? 'USB(UAC2) fix on' : 'USB(UAC2) fix off';
		submitJob('uac2fix', $_POST['uac2fix'], $title, 'Reboot required');
		playerSession('write', 'uac2fix', $_POST['uac2fix']);
	} 
}

// eth port fix
if (isset($_POST['update_eth_port_fix'])) {
	if (isset($_POST['eth_port_fix']) && $_POST['eth_port_fix'] != $_SESSION['eth_port_fix']) {
		$_SESSION['notify']['title'] = $_POST['eth_port_fix'] == 1 ? 'Ethernet port fix on' : 'Ethernet port fix off';
		$_SESSION['notify']['msg'] = 'Reboot required';
		playerSession('write', 'eth_port_fix', $_POST['eth_port_fix']);
	} 
}

// expand root file system
if (isset($_POST['update_expand_rootfs'])) {
	submitJob('expandrootfs', '', 'File system expanded', 'Reboot required', 30);
}

// enable usb boot
if (isset($_POST['update_usbboot'])) {
	submitJob('usbboot', '', 'USB boot enabled', 'Reboot required', 30);
}

// mpd engine timeout
/*if (isset($_POST['update_mpdtimeout']) && $_POST['mpdtimeout'] != $_SESSION['engine_mpd_sock_timeout']) {
	$_SESSION['notify']['title'] = 'MPD engine timeout updated';
	$_SESSION['notify']['msg'] = 'Refresh Browse to activate';
	$_SESSION['notify']['duration'] = 6;
	playerSession('write', 'engine_mpd_sock_timeout', $_POST['mpdtimeout']);
} r45b deprecate */

// LOCAL DISPLAY

// local UI display 
if (isset($_POST['update_localui'])) {
    if (isset($_POST['localui']) && $_POST['localui'] != $_SESSION['localui']) {
		$title = $_POST['localui'] == 1 ? 'Local UI display on' : 'Local UI display off';
        submitJob('localui', $_POST['localui'], $title, 'Reboot may be required');
        playerSession('write', 'localui', $_POST['localui']);
    } 
}

// touch screen capability
if (isset($_POST['update_touchscn'])) {
    if (isset($_POST['touchscn']) && $_POST['touchscn'] != $_SESSION['touchscn']) {
        submitJob('touchscn', $_POST['touchscn'], 'Setting updated', 'Local display restarted');
        playerSession('write', 'touchscn', $_POST['touchscn']);
    } 
}

// screen blank timeout
if (isset($_POST['update_scnblank'])) {
    if (isset($_POST['scnblank']) && $_POST['scnblank'] != $_SESSION['scnblank']) {
        submitJob('scnblank', $_POST['scnblank'], 'Setting updated', 'Local display restarted');
        playerSession('write', 'scnblank', $_POST['scnblank']);
    } 
}

// screen blank timeout
if (isset($_POST['update_scnbrightness'])) {
    if (isset($_POST['scnbrightness']) && $_POST['scnbrightness'] != $_SESSION['scnbrightness']) {
		submitJob('scnbrightness', $_POST['scnbrightness'], 'Setting updated');
		playerSession('write', 'scnbrightness', $_POST['scnbrightness']);
    }
}

// screen rotation
if (isset($_POST['update_scnrotate'])) {
    if (isset($_POST['scnrotate']) && $_POST['scnrotate'] != $_SESSION['scnrotate']) {
		submitJob('scnrotate', $_POST['scnrotate'], 'Setting updated', 'Reboot required');
		playerSession('write', 'scnrotate', $_POST['scnrotate']);
    } 
}

// browser cache
if (isset($_POST['update_clear_browser_cache'])) {
	submitJob('clearbrcache', '', 'Cache cleared', 'Refresh Browser on LocalUI');
}

// LOCAL SERVICES

// metadata for external apps
if (isset($_POST['extmeta']) && $_POST['extmeta'] != $_SESSION['extmeta']) {
	$_SESSION['notify']['title'] = $_POST['extmeta'] == 1 ? 'Metadata file on' : 'Metadata file off';
	$_SESSION['notify']['duration'] = 3;
	playerSession('write', 'extmeta', $_POST['extmeta']);
}

// lcd updater
if (isset($_POST['update_lcdup'])) {
	if (isset($_POST['lcdupscript']) && $_POST['lcdupscript'] != $_SESSION['lcdupscript']) {
		$_SESSION['notify']['title'] = 'Script path updated';
		$_SESSION['notify']['duration'] = 3;
		playerSession('write', 'lcdupscript', $_POST['lcdupscript']);
	} 

	if (isset($_POST['lcdup']) && $_POST['lcdup'] != $_SESSION['lcdup']) {
		$title = $_POST['lcdup'] == 1 ? 'LCD update engine on' : 'LCD update engine off';
		submitJob('lcdup', $_POST['lcdup'], $title, '');
		playerSession('write', 'lcdup', $_POST['lcdup']);
		playerSession('write', 'extmeta', '1'); // turn on external metadata generation
	} 
}

// gpio
if (isset($_POST['update_gpio_svc']) && $_POST['gpio_svc'] != $_SESSION['gpio_svc']) {
	$title = $_POST['gpio_svc'] == 1 ? 'GPIO button handler on' : 'GPIO button handler off';
	playerSession('write', 'gpio_svc', $_POST['gpio_svc']);
	submitJob('gpio_svc', $_POST['gpio_svc'], $title, '');
}

// shellinabox
if (isset($_POST['shellinabox']) && $_POST['shellinabox'] != $_SESSION['shellinabox']) {
	$title = $_POST['shellinabox'] == 1 ? 'SSH server on' : 'SSH server off';
	playerSession('write', 'shellinabox', $_POST['shellinabox']);
	submitJob('shellinabox', $_POST['shellinabox'], $title, '');
}

// MAINTENANCE

// clear system logs
if (isset($_POST['update_clear_syslogs'])) {
	submitJob('clearsyslogs', '', 'System logs cleared', '');
}

// clear play history log
if (isset($_POST['update_clear_playhistory'])) {
	submitJob('clearplayhistory', '', 'Playback history cleared', '');
}

// compact sqlite database
if (isset($_POST['update_compactdb'])) {
	submitJob('compactdb', '', 'SQlite DB compacted', '');
}

// debug logging
if (isset($_POST['debuglog']) && $_POST['debuglog'] != $_SESSION['debuglog']) {
	$_SESSION['notify']['title'] = $_POST['debuglog'] == 1 ? 'Debug logging on' : 'Debug logging off';
	$_SESSION['notify']['duration'] = 3;
	playerSession('write', 'debuglog', $_POST['debuglog']);
}

session_write_close();

// GENERAL

$_timezone['timezone'] = buildTimezoneSelect($_SESSION['timezone']);
$_select['hostname'] = $_SESSION['hostname'];
$_keyboard['keyboard'] = buildKeyboardSelect($_SESSION['keyboard']);
$_select['browsertitle'] = $_SESSION['browsertitle'];

// SYSTEM MODIFICATIONS

// cpu governor
$_select['cpugov'] .= "<option value=\"ondemand\" " . (($_SESSION['cpugov'] == 'ondemand') ? "selected" : "") . ">On-demand</option>\n";
$_select['cpugov'] .= "<option value=\"performance\" " . (($_SESSION['cpugov'] == 'performance') ? "selected" : "") . ">Performance</option>\n";

// wifi bt 
if (substr($_SESSION['hdwrrev'], 0, 4) == 'Pi-3' || substr($_SESSION['hdwrrev'], 0, 9) == 'Pi-Zero W') { // r44f change from Pi-3B to Pi-3 to cover 3B, 3B+ and 3A+
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

// eth0 check
$_select['eth0chk1'] .= "<input type=\"radio\" name=\"eth0chk\" id=\"toggleeth0chk1\" value=\"1\" " . (($_SESSION['eth0chk'] == 1) ? "checked=\"checked\"" : "") . ">\n";
$_select['eth0chk0'] .= "<input type=\"radio\" name=\"eth0chk\" id=\"toggleeth0chk2\" value=\"0\" " . (($_SESSION['eth0chk'] == 0) ? "checked=\"checked\"" : "") . ">\n";

// max usb current 2x
if (substr($_SESSION['hdwrrev'], 0, 4) != 'Pi-3' && substr($_SESSION['hdwrrev'], 0, 7) != 'Pi-Zero') { // r44f change from Pi-3B to Pi-3 to cover 3B, 3B+ and 3A+
	$_maxcurrent_hide = '';
	$_select['maxusbcurrent1'] .= "<input type=\"radio\" name=\"maxusbcurrent\" id=\"togglemaxusbcurrent1\" value=\"1\" " . (($_SESSION['maxusbcurrent'] == 1) ? "checked=\"checked\"" : "") . ">\n";
	$_select['maxusbcurrent0'] .= "<input type=\"radio\" name=\"maxusbcurrent\" id=\"togglemaxusbcurrent2\" value=\"0\" " . (($_SESSION['maxusbcurrent'] == 0) ? "checked=\"checked\"" : "") . ">\n";
}
else {
	$_maxcurrent_hide = 'hide';
}

// usb (uac2) fix
$_select['uac2fix1'] .= "<input type=\"radio\" name=\"uac2fix\" id=\"toggleuac2fix1\" value=\"1\" " . (($_SESSION['uac2fix'] == 1) ? "checked=\"checked\"" : "") . ">\n";
$_select['uac2fix0'] .= "<input type=\"radio\" name=\"uac2fix\" id=\"toggleuac2fix2\" value=\"0\" " . (($_SESSION['uac2fix'] == 0) ? "checked=\"checked\"" : "") . ">\n";

// eth port fix
if (substr($_SESSION['hdwrrev'], 0, 6) == 'Pi-3B+') {
	$_eth_port_fix_hide = '';
	$_select['eth_port_fix1'] = "<input type=\"radio\" name=\"eth_port_fix\" id=\"toggle_eth_port_fix0\" value=\"1\" " . (($_SESSION['eth_port_fix'] == '1') ? "checked=\"checked\"" : "") . ">\n";
	$_select['eth_port_fix0'] = "<input type=\"radio\" name=\"eth_port_fix\" id=\"toggle_eth_port_fix1\" value=\"0\" " . (($_SESSION['eth_port_fix'] == '0') ? "checked=\"checked\"" : "") . ">\n";
}
else {
	$_eth_port_fix_hide = 'hide';
}

// android 'add to home'
$result = sysCmd('grep "add2home_off" /var/local/www/header.php');
$_select['add2home1'] .= "<input type=\"radio\" name=\"add2home\" id=\"toggleadd2home1\" value=\"1\" " . ((empty($result[0])) ? "checked=\"checked\"" : "") . ">\n";
$_select['add2home0'] .= "<input type=\"radio\" name=\"add2home\" id=\"toggleadd2home2\" value=\"0\" " . ((!empty($result[0])) ? "checked=\"checked\"" : "") . ">\n";

// expand root file system
$_select['expandrootfs1'] .= "<input type=\"radio\" name=\"expandrootfs\" id=\"toggleexpandrootfs1\" value=\"1\" " . ">\n";
$_select['expandrootfs0'] .= "<input type=\"radio\" name=\"expandrootfs\" id=\"toggleexpandrootfs2\" value=\"0\" " . "checked=\"checked\"".">\n";
$result = sysCmd("df | grep root | awk '{print $2}'");
$_expandrootfs_msg = $result[0] > 3000000 ? 'File system has been expanded' : 'File system has not been expanded yet'; 

// usb boot
if (substr($_SESSION['hdwrrev'], 0, 4) == 'Pi-3') { // r44f change from Pi-3B to Pi-3 to cover 3B, 3B+ and 3A+
	$_usbboot_hide = '';
	$_select['usbboot1'] .= "<input type=\"radio\" name=\"usbboot\" id=\"toggleusbboot1\" value=\"1\" " . ">\n";
	$_select['usbboot0'] .= "<input type=\"radio\" name=\"usbboot\" id=\"toggleusbboot2\" value=\"0\" " . "checked=\"checked\"".">\n";
	$result = sysCmd('vcgencmd otp_dump | grep 17:');
	$_usbboot_msg = $result[0] == '17:3020000a' ? 'USB boot is enabled' : 'USB boot is not enabled yet';

}
else {
	$_usbboot_hide = 'hide';
}

// mpd engine timeout, r45b deprecate
/*$_select['mpdtimeout'] .= "<option value=\"600000\" " . (($_SESSION['engine_mpd_sock_timeout'] == '600000') ? "selected" : "") . ">Never</option>\n";
$_select['mpdtimeout'] .= "<option value=\"18000\" " . (($_SESSION['engine_mpd_sock_timeout'] == '18000') ? "selected" : "") . ">5 Hours</option>\n";
$_select['mpdtimeout'] .= "<option value=\"3600\" " . (($_SESSION['engine_mpd_sock_timeout'] == '3600') ? "selected" : "") . ">1 Hour</option>\n";
$_select['mpdtimeout'] .= "<option value=\"1800\" " . (($_SESSION['engine_mpd_sock_timeout'] == '1800') ? "selected" : "") . ">30 Mins</option>\n";*/

// LOCAL DISPLAY

// local UI display
if ($_SESSION['feat_bitmask'] & FEAT_LOCALUI) {
	$_feat_localui = '';
	$_select['localui1'] .= "<input type=\"radio\" name=\"localui\" id=\"togglelocalui1\" value=\"1\" " . (($_SESSION['localui'] == 1) ? "checked=\"checked\"" : "") . ">\n";
	$_select['localui0'] .= "<input type=\"radio\" name=\"localui\" id=\"togglelocalui2\" value=\"0\" " . (($_SESSION['localui'] == 0) ? "checked=\"checked\"" : "") . ">\n";
	
	// touch capability
	$_select['touchscn1'] .= "<input type=\"radio\" name=\"touchscn\" id=\"toggletouchscn1\" value=\"1\" " . (($_SESSION['touchscn'] == 1) ? "checked=\"checked\"" : "") . ">\n";
	$_select['touchscn0'] .= "<input type=\"radio\" name=\"touchscn\" id=\"toggletouchscn2\" value=\"0\" " . (($_SESSION['touchscn'] == 0) ? "checked=\"checked\"" : "") . ">\n";
	
	// screen blank
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

	// backlight brightess
	$_select['scnbrightness'] = $_SESSION['scnbrightness'];

	// screen rotate
	$_select['scnrotate'] .= "<option value=\"0\" " . (($_SESSION['scnrotate'] == '0') ? "selected" : "") . ">0 Deg</option>\n";
	$_select['scnrotate'] .= "<option value=\"180\" " . (($_SESSION['scnrotate'] == '180') ? "selected" : "") . ">180 Deg</option>\n";
}
else {
	$_feat_localui = 'hide';
}

// LOCAL SERVICES

// metadata file
$_select['extmeta1'] .= "<input type=\"radio\" name=\"extmeta\" id=\"toggleextmeta1\" value=\"1\" " . (($_SESSION['extmeta'] == 1) ? "checked=\"checked\"" : "") . ">\n";
$_select['extmeta0'] .= "<input type=\"radio\" name=\"extmeta\" id=\"toggleextmeta2\" value=\"0\" " . (($_SESSION['extmeta'] == 0) ? "checked=\"checked\"" : "") . ">\n";

// lcd updater
$_select['lcdup1'] .= "<input type=\"radio\" name=\"lcdup\" id=\"togglelcdup1\" value=\"1\" " . (($_SESSION['lcdup'] == 1) ? "checked=\"checked\"" : "") . ">\n";
$_select['lcdup0'] .= "<input type=\"radio\" name=\"lcdup\" id=\"togglelcdup2\" value=\"0\" " . (($_SESSION['lcdup'] == 0) ? "checked=\"checked\"" : "") . ">\n";
$_select['lcdupscript'] = $_SESSION['lcdupscript'];

// gpio
if ($_SESSION['feat_bitmask'] & FEAT_GPIO) {
	$_feat_gpio = '';
	$_select['gpio_svc1'] .= "<input type=\"radio\" name=\"gpio_svc\" id=\"toggle_gpio_svc1\" value=\"1\" " . (($_SESSION['gpio_svc'] == 1) ? "checked=\"checked\"" : "") . ">\n";
	$_select['gpio_svc0'] .= "<input type=\"radio\" name=\"gpio_svc\" id=\"toggle_gpio_svc2\" value=\"0\" " . (($_SESSION['gpio_svc'] == 0) ? "checked=\"checked\"" : "") . ">\n";
}
else {
	$_feat_gpio = 'hide';
}
// shellinabox
$_select['shellinabox1'] .= "<input type=\"radio\" name=\"shellinabox\" id=\"toggleshellinabox1\" value=\"1\" " . (($_SESSION['shellinabox'] == 1) ? "checked=\"checked\"" : "") . ">\n";
$_select['shellinabox0'] .= "<input type=\"radio\" name=\"shellinabox\" id=\"toggleshellinabox2\" value=\"0\" " . (($_SESSION['shellinabox'] == 0) ? "checked=\"checked\"" : "") . ">\n";
$_select['hostip'] = getHostIp();

// MAINTENANCE

// debug logging
$_select['debuglog1'] .= "<input type=\"radio\" name=\"debuglog\" id=\"toggledebuglog1\" value=\"1\" " . (($_SESSION['debuglog'] == 1) ? "checked=\"checked\"" : "") . ">\n";
$_select['debuglog0'] .= "<input type=\"radio\" name=\"debuglog\" id=\"toggledebuglog2\" value=\"0\" " . (($_SESSION['debuglog'] == 0) ? "checked=\"checked\"" : "") . ">\n";

waitWorker(1, 'sys-config');

$tpl = "sys-config.html";
$section = basename(__FILE__, '.php');
storeBackLink($section, $tpl);

include('/var/local/www/header.php'); 
eval("echoTemplate(\"" . getTemplate("templates/$tpl") . "\");");
include('footer.php');
