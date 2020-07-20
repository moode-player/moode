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

// get current settings: [0] = eth0, [1] = wlan0, [2] = apd0
$netcfg = sdbquery('select * from cfg_network', $dbh);

// reset eth0 and wlan0 to defaults
if (isset($_POST['reset']) && $_POST['reset'] == 1) {
	// eth0
	$value = array('method' => 'dhcp', 'ipaddr' => '', 'netmask' => '', 'gateway' => '', 'pridns' => '', 'secdns' => '', 'wlanssid' => '',
		'wlansec' => '', 'wlanpwd' => '', 'wlan_psk' => '', 'wlan_country' => '', 'wlan_channel' => '');
	cfgdb_update('cfg_network', $dbh, 'eth0', $value);

	// wlan0
	$value['wlanssid'] = 'None (activates AP mode)';
	$value['wlansec'] = 'wpa';
	$value['wlan_country'] = $netcfg[1]['wlan_country']; // preserve country code
	cfgdb_update('cfg_network', $dbh, 'wlan0', $value);

	// submit job
	submitJob('netcfg', '', 'Network config reset', 'Reboot required');
}

// update interfaces
if (isset($_POST['save']) && $_POST['save'] == 1) {
	// eth0
	$value = array('method' => $_POST['eth0method'], 'ipaddr' => $_POST['eth0ipaddr'], 'netmask' => $_POST['eth0netmask'],
		'gateway' => $_POST['eth0gateway'], 'pridns' => $_POST['eth0pridns'], 'secdns' => $_POST['eth0secdns'], 'wlanssid' => '',
		'wlansec' => '', 'wlanpwd' => '', 'wlan_psk' => '', 'wlan_country' => '', 'wlan_channel' => '');
	cfgdb_update('cfg_network', $dbh, 'eth0', $value);

	// wlan0
	$method = (empty($_POST['wlan0ssid']) || $_POST['wlan0ssid'] == 'None (activates AP mode)') ? 'dhcp' : $_POST['wlan0method'];

	if ($_POST['wlan0ssid'] != $netcfg[1]['wlanssid'] || $_POST['wlan0pwd'] != $netcfg[1]['wlan_psk']) {
		$psk = genWpaPSK($_POST['wlan0ssid'], $_POST['wlan0pwd']);
	}
	else {
		$psk = $netcfg[1]['wlan_psk']; // existing
	}

	$value = array('method' => $method, 'ipaddr' => $_POST['wlan0ipaddr'], 'netmask' => $_POST['wlan0netmask'],
		'gateway' => $_POST['wlan0gateway'], 'pridns' => $_POST['wlan0pridns'], 'secdns' => $_POST['wlan0secdns'],
		'wlanssid' => $_POST['wlan0ssid'], 'wlansec' => $_POST['wlan0sec'], 'wlanpwd' => $psk, 'wlan_psk' => $psk,
		'wlan_country' => $_POST['wlan0country'], 'wlan_channel' => '');
	cfgdb_update('cfg_network', $dbh, 'wlan0', $value);

	// apd0
	if ($_POST['wlan0apdssid'] != $netcfg[2]['wlanssid'] || $_POST['wlan0apdpwd'] != $netcfg[2]['wlan_psk']) {
		$psk = genWpaPSK($_POST['wlan0apdssid'], $_POST['wlan0apdpwd']);
	}
	else {
		$psk = $netcfg[2]['wlan_psk']; // existing
	}

	$value = array('method' => '', 'ipaddr' => '', 'netmask' => '', 'gateway' => '', 'pridns' => '', 'secdns' => '',
		'wlanssid' => $_POST['wlan0apdssid'], 'wlansec' => '', 'wlanpwd' => $psk, 'wlan_psk' => $psk,
		'wlan_country' => '', 'wlan_channel' => $_POST['wlan0apdchan']);
	cfgdb_update('cfg_network', $dbh, 'apd0', $value);

	// submit job
	submitJob('netcfg', '', 'Changes saved', 'Reboot required');
}

// populate form fields

// get updated settings: [0] = eth0, [1] = wlan0, [2] = apd0
$netcfg = sdbquery('select * from cfg_network', $dbh);

// ETH0
$_eth0method .= "<option value=\"dhcp\" "   . ($netcfg[0]['method'] == 'dhcp' ? 'selected' : '') . " >DHCP</option>\n";
$_eth0method .= "<option value=\"static\" " . ($netcfg[0]['method'] == 'static' ? 'selected' : '') . " >STATIC</option>\n";

// display ipaddr or message
$ipaddr = sysCmd("ip addr list eth0 |grep \"inet \" |cut -d' ' -f6|cut -d/ -f1");
$_eth0currentip = empty($ipaddr[0]) ? 'Not in use' : $ipaddr[0];

// static ip
$_eth0ipaddr = $netcfg[0]['ipaddr'];
//$_eth0netmask = $netcfg[0]['netmask'];
$_eth0netmask .= "<option value=\"24\" " . ($netcfg[0]['netmask'] == '24' ? 'selected' : '') . " >255.255.255.0</option>\n";
$_eth0netmask .= "<option value=\"16\" " . ($netcfg[0]['netmask'] == '16' ? 'selected' : '') . " >255.255.0.0</option>\n";
$_eth0gateway = $netcfg[0]['gateway'];
$_eth0pridns = $netcfg[0]['pridns'];
$_eth0secdns = $netcfg[0]['secdns'];

// WLAN0
$_wlan0method .= "<option value=\"dhcp\" " . ($netcfg[1]['method'] == 'dhcp' ? 'selected' : '') . " >DHCP</option>\n";
$_wlan0method .= "<option value=\"static\" " . ($netcfg[1]['method'] == 'static' ? 'selected' : '') . " >STATIC</option>\n";

// get ipaddr if any
$ipaddr = sysCmd("ip addr list wlan0 |grep \"inet \" |cut -d' ' -f6|cut -d/ -f1");

// get link quality and signal level
if (!empty($ipaddr[0])) {
	$signal = sysCmd('iwconfig wlan0 | grep -i quality');
	$array = explode('=', $signal[0]);
	$qual = explode('/', $array[1]);
	$quality = round((100 * $qual[0]) / $qual[1]);
	$lev = explode('/', $array[2]);
	$level = strpos($lev[0], 'dBm') !== false ? $lev[0] : $lev[0] . '%';
}

// determine message to display
if ($_SESSION['apactivated'] == true) {
	$_wlan0currentip = empty($ipaddr[0]) ? 'Unable to activate AP mode' : $ipaddr[0] . ' - AP mode active';
}
else {
	$_wlan0currentip = empty($ipaddr[0]) ? 'Not in use' : $ipaddr[0] . ' - quality ' . $quality . '%, level ' . $level;
}

// ssid, scanner, security protocol, password
if (isset($_POST['scan']) && $_POST['scan'] == '1') {
	$result = sysCmd("iwlist wlan0 scan | grep ESSID | sed 's/ESSID://; s/\"//g'"); // do twice to improve results
	$result = sysCmd("iwlist wlan0 scan | grep ESSID | sed 's/ESSID://; s/\"//g'");
	$array = array();
	$array[0] = 'None (activates AP mode)';
	$ssidList = array_merge($array, $result);

	foreach ($ssidList as $ssid) {
		$ssid = trim($ssid);
		// additional filtering
		if (!empty($ssid) && false === strpos($ssid, '\x')) {
			$selected = ($netcfg[1]['wlanssid'] == $ssid) ? 'selected' : '';
			$_wlan0ssid .= sprintf('<option value="%s" %s>%s</option>\n', $ssid, $selected, $ssid);
		}
	}
}
else {
	if (isset($_POST['manualssid']) && $_POST['manualssid'] == '1') {
		$_wlan0ssid = sprintf('<option value="%s" %s>%s</option>\n', 'None (activates AP mode)', '', 'None (activates AP mode)');
		$_wlan0ssid .= sprintf('<option value="%s" %s>%s</option>\n', $_POST['wlan0otherssid'], 'selected', $_POST['wlan0otherssid']);
	}
	else if ($netcfg[1]['wlanssid'] == 'None (activates AP mode)') {
		$_wlan0ssid .= sprintf('<option value="%s" %s>%s</option>\n', $netcfg[1]['wlanssid'], 'selected', $netcfg[1]['wlanssid']);
	}
	else {
		$_wlan0ssid = sprintf('<option value="%s" %s>%s</option>\n', 'None (activates AP mode)', '', 'None (activates AP mode)');
		$_wlan0ssid .= sprintf('<option value="%s" %s>%s</option>\n', $netcfg[1]['wlanssid'], 'selected', $netcfg[1]['wlanssid']);
	}
}
$_wlan0sec .= "<option value=\"wpa\"" . ($netcfg[1]['wlansec'] == 'wpa' ? 'selected' : '') . ">WPA/WPA2 Personal</option>\n";
$_wlan0sec .= "<option value=\"none\"" . ($netcfg[1]['wlansec'] == 'none' ? 'selected' : '') . ">No security</option>\n";
$_wlan0pwd = $netcfg[1]['wlanpwd']; // old: htmlentities($netcfg[1]['wlanpwd'])

// wifi country code
$zonelist = sysCmd("cat /usr/share/zoneinfo/iso3166.tab | tail -n +26 | tr '\t' ','");
$zonelist_sorted = array();
for ($i = 0; $i < count($zonelist); $i++) {
	$country = explode(',', $zonelist[$i]);
	if ($country[1] == 'Britain (UK)') {$country[1] = 'United Kingdom (UK)';}
	$zonelist_sorted[$i] = $country[1] . ',' . $country[0];
}
sort($zonelist_sorted);
foreach ($zonelist_sorted as $zone) {
	$country = explode(',', $zone);
	$selected = ($country[1] == $netcfg[1]['wlan_country']) ? 'selected' : '';
	$_wlan0country .= sprintf('<option value="%s" %s>%s</option>\n', $country[1], $selected, $country[0]);
}

// static ip
$_wlan0ipaddr = $netcfg[1]['ipaddr'];
//$_wlan0netmask = $netcfg[1]['netmask'];
$_wlan0netmask .= "<option value=\"24\" "   . ($netcfg[1]['netmask'] == '24' ? 'selected' : '') . " >255.255.255.0</option>\n";
$_wlan0netmask .= "<option value=\"16\" " . ($netcfg[1]['netmask'] == '16' ? 'selected' : '') . " >255.255.0.0</option>\n";
$_wlan0gateway = $netcfg[1]['gateway'];
$_wlan0pridns = $netcfg[1]['pridns'];
$_wlan0secdns = $netcfg[1]['secdns'];

// access point
$_wlan0apdssid = $netcfg[2]['wlanssid'];
$_wlan0apdchan = $netcfg[2]['wlan_channel'];
$_wlan0apdpwd = $netcfg[2]['wlanpwd'];

session_write_close();

waitWorker(1, 'net-config');

$tpl = "net-config.html";
$section = basename(__FILE__, '.php');
storeBackLink($section, $tpl);

include('header.php');
eval("echoTemplate(\"" . getTemplate("templates/$tpl") . "\");");
include('footer.min.php');
