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
 */

require_once __DIR__ . '/inc/common.php';
require_once __DIR__ . '/inc/network.php';
require_once __DIR__ . '/inc/session.php';
require_once __DIR__ . '/inc/sql.php';

$dbh = sqlConnect();
phpSession('open');

// Get current settings: [0] = eth0, [1] = wlan0, [2] = apd0
$cfgNetwork = sqlQuery('SELECT * FROM cfg_network', $dbh);

// Reset eth0 and wlan0 to defaults
if (isset($_POST['reset']) && $_POST['reset'] == 1) {
	// eth0
	$value = array('method' => 'dhcp', 'ipaddr' => '', 'netmask' => '', 'gateway' => '', 'pridns' => '', 'secdns' => '', 'wlanssid' => '',
		'wlansec' => '', 'wlanpwd' => '', 'wlan_psk' => '', 'wlan_country' => '', 'wlan_channel' => '');
	sqlUpdate('cfg_network', $dbh, 'eth0', $value);

	// wlan0
	$value['wlanssid'] = 'None (activates AP mode)';
	$value['wlansec'] = 'wpa';
	$value['wlan_country'] = $cfgNetwork[1]['wlan_country']; // Preserve country code
	sqlUpdate('cfg_network', $dbh, 'wlan0', $value);

	submitJob('netcfg', '', 'Network config reset', 'Restart required');
}

// Update interfaces
if (isset($_POST['save']) && $_POST['save'] == 1) {
	// eth0
	$value = array('method' => $_POST['eth0method'], 'ipaddr' => $_POST['eth0ipaddr'], 'netmask' => $_POST['eth0netmask'],
		'gateway' => $_POST['eth0gateway'], 'pridns' => $_POST['eth0pridns'], 'secdns' => $_POST['eth0secdns'], 'wlanssid' => '',
		'wlansec' => '', 'wlanpwd' => '', 'wlan_psk' => '', 'wlan_country' => '', 'wlan_channel' => '');
	sqlUpdate('cfg_network', $dbh, 'eth0', $value);

	// wlan0
	$method = $_POST['wlan0ssid'] == 'None (activates AP mode)' ? 'dhcp' : $_POST['wlan0method'];

	if ($_POST['wlan0ssid'] != $cfgNetwork[1]['wlanssid'] || $_POST['wlan0pwd'] != $cfgNetwork[1]['wlan_psk']) {
		$psk = genWpaPSK($_POST['wlan0ssid'], $_POST['wlan0pwd']);
	} else {
		$psk = $cfgNetwork[1]['wlan_psk']; // Existing
	}

	// Update cfg_network
	$value = array('method' => $method, 'ipaddr' => $_POST['wlan0ipaddr'], 'netmask' => $_POST['wlan0netmask'],
		'gateway' => $_POST['wlan0gateway'], 'pridns' => $_POST['wlan0pridns'], 'secdns' => $_POST['wlan0secdns'],
		'wlanssid' => $_POST['wlan0ssid'], 'wlansec' => $_POST['wlan0sec'], 'wlanpwd' => $psk, 'wlan_psk' => $psk,
		'wlan_country' => $_POST['wlan0country'], 'wlan_channel' => '');
	sqlUpdate('cfg_network', $dbh, 'wlan0', $value);

	// Add/update cfg_ssid
	if ($_POST['wlan0ssid'] != 'None (activates AP mode)') {
		$cfgSsid = sqlQuery("SELECT * FROM cfg_ssid WHERE ssid='" . SQLite3::escapeString($_POST['wlan0ssid']) . "'", $dbh);
		if ($cfgSsid === true) {
			// Add
			$values =
				"'"	. SQLite3::escapeString($_POST['wlan0ssid']) . "'," .
				"'" . $_POST['wlan0sec'] . "'," .
				"'" . $psk . "'";
			$result = sqlQuery('INSERT INTO cfg_ssid VALUES (NULL,' . $values . ')', $dbh);
		} else {
			// Update
			$result = sqlQuery("UPDATE cfg_ssid SET " .
				"ssid='" . SQLite3::escapeString($_POST['wlan0ssid']) . "'," .
				"sec='" . $_POST['wlan0sec'] . "'," .
				"psk='" . $psk . "' " .
				"where id='" . $cfgSsid[0]['id'] . "'" , $dbh);
		}
	}

	// apd0
	if ($_POST['wlan0apdssid'] != $cfgNetwork[2]['wlanssid'] || $_POST['wlan0apdpwd'] != $cfgNetwork[2]['wlan_psk']) {
		$psk = genWpaPSK($_POST['wlan0apdssid'], $_POST['wlan0apdpwd']);
	} else {
		$psk = $cfgNetwork[2]['wlan_psk']; // Existing
	}

	$value = array('method' => '', 'ipaddr' => '', 'netmask' => '', 'gateway' => '', 'pridns' => '', 'secdns' => '',
		'wlanssid' => $_POST['wlan0apdssid'], 'wlansec' => '', 'wlanpwd' => $psk, 'wlan_psk' => $psk,
		'wlan_country' => '', 'wlan_channel' => $_POST['wlan0apdchan'], 'wlan_router' => $_POST['wlan0apd_router']);
	sqlUpdate('cfg_network', $dbh, 'apd0', $value);

	submitJob('netcfg', '', 'Settings updated', 'Restart required');
}

// Update saved networks
if (isset($_POST['update-saved-networks']) && $_POST['update-saved-networks'] == 1) {
	$cfgSsid = sqlQuery("SELECT * FROM cfg_ssid WHERE ssid != '" . $cfgNetwork[1]['wlanssid'] . "'", $dbh);
	if ($cfgSsid !== true) {
		$itemDeleted = false;
		for ($i = 0; $i < count($cfgSsid); $i++) {
			$_post_ssid = 'cfg-ssid-' . $cfgSsid[$i]['id'];
			if (isset($_POST[$_post_ssid]) && $_POST[$_post_ssid] == 'on') {
				$result = sqlQuery("DELETE FROM cfg_ssid WHERE id = '" . $cfgSsid[$i]['id'] . "'", $dbh);
				$itemDeleted = true;
			}
		}

		if ($itemDeleted) {
			submitJob('netcfg', '', 'Settigs updated', 'Restart required');
		}
	}
}

phpSession('close');

//
// Populate form fields
//

// Get updated settings: [0] = eth0, [1] = wlan0, [2] = apd0
$cfgNetwork = sqlQuery('SELECT * FROM cfg_network', $dbh);

// List saved networks excluding the currently configured SSID
$cfgSsid = sqlQuery("SELECT * FROM cfg_ssid WHERE ssid != '" . SQLite3::escapeString($cfgNetwork[1]['wlanssid']) . "'", $dbh);
if ($cfgSsid === true) {
	$_saved_networks = '<p style="text-align:center;">There are no saved networks</p>';
} else {
	for ($i=0; $i < count($cfgSsid); $i++) {
		$_saved_networks .= '<div class="control-group">';
		$_saved_networks .= '<label class="control-label">' . $cfgSsid[$i]['ssid'] . '</label>';
		$_saved_networks .= '<div class="controls">';
		$_saved_networks .= '<input name="cfg-ssid-' . $cfgSsid[$i]['id'] . '" class="checkbox-ctl saved-ssid-modal-delete" type="checkbox"><em>Delete</em>';
		$_saved_networks .= '</div>';
		$_saved_networks .= '</div>';
	}
}

// ETH0
$_eth0method .= "<option value=\"dhcp\" "   . ($cfgNetwork[0]['method'] == 'dhcp' ? 'selected' : '') . " >DHCP</option>\n";
$_eth0method .= "<option value=\"static\" " . ($cfgNetwork[0]['method'] == 'static' ? 'selected' : '') . " >STATIC</option>\n";

// Display IP address or message
$ipAddr = sysCmd("ip addr list eth0 |grep \"inet \" |cut -d' ' -f6|cut -d/ -f1");
$_eth0currentip = empty($ipAddr[0]) ? 'Not in use' : $ipAddr[0];

// Static IP
$_eth0ipaddr = $cfgNetwork[0]['ipaddr'];
//$_eth0netmask = $cfgNetwork[0]['netmask'];
$_eth0netmask .= "<option value=\"24\" " . ($cfgNetwork[0]['netmask'] == '24' ? 'selected' : '') . " >255.255.255.0</option>\n";
$_eth0netmask .= "<option value=\"16\" " . ($cfgNetwork[0]['netmask'] == '16' ? 'selected' : '') . " >255.255.0.0</option>\n";
$_eth0gateway = $cfgNetwork[0]['gateway'];
$_eth0pridns = $cfgNetwork[0]['pridns'];
$_eth0secdns = $cfgNetwork[0]['secdns'];

// WLAN0
$_wlan0method .= "<option value=\"dhcp\" " . ($cfgNetwork[1]['method'] == 'dhcp' ? 'selected' : '') . " >DHCP</option>\n";
$_wlan0method .= "<option value=\"static\" " . ($cfgNetwork[1]['method'] == 'static' ? 'selected' : '') . " >STATIC</option>\n";

// Get IP address if any
$ipAddr = sysCmd("ip addr list wlan0 |grep \"inet \" |cut -d' ' -f6|cut -d/ -f1");

// Get link quality and signal level
if (!empty($ipAddr[0])) {
	$ssid = sysCmd("iwconfig wlan0 | grep 'ESSID' | awk -F':' '{print $2}' | awk -F'\"' '{print $2}'");
	$bssid = sysCmd('iw dev wlan0 link | grep -i connected | cut -d" " -f3');
	$signal = sysCmd('iwconfig wlan0 | grep -i quality');
	$array = explode('=', $signal[0]);
	$qual = explode('/', $array[1]);
	$quality = round((100 * $qual[0]) / $qual[1]);
	$lev = explode('/', $array[2]);
	$level = strpos($lev[0], 'dBm') !== false ? $lev[0] : $lev[0] . '%';
}
// Determine message to display
if ($_SESSION['apactivated'] == true) {
	$_wlan0currentip = empty($ipAddr[0]) ? 'Unable to activate AP mode' : $ipAddr[0] . ' AP mode active';
} else {
	$_wlan0currentip = empty($ipAddr[0]) ? 'Not in use' :
	'Address: ' . $ipAddr[0] . '<br>' .
	'Network: ' . $ssid[0] . ' (' . $bssid[0] . ')<br>' .
	'Quality: ' . $quality . '% level ' . $level;
}

// SSID, scanner, security protocol, password
if (isset($_POST['scan']) && $_POST['scan'] == '1') {
	$result = sysCmd("iwlist wlan0 scan | grep ESSID | sed 's/ESSID://; s/\"//g'"); // Do twice to improve results
	$result = sysCmd("iwlist wlan0 scan | grep ESSID | sed 's/ESSID://; s/\"//g'");
	sort($result, SORT_NATURAL | SORT_FLAG_CASE);
	$array = array();
	$array[0] = 'None (activates AP mode)';
	$ssidList = array_merge($array, $result);

	foreach ($ssidList as $ssid) {
		$ssid = trim($ssid);
		// Additional filtering
		if (!empty($ssid) && false === strpos($ssid, '\x')) {
			$selected = ($cfgNetwork[1]['wlanssid'] == $ssid) ? 'selected' : '';
			$_wlan0ssid .= sprintf('<option value="%s" %s>%s</option>\n', $ssid, $selected, $ssid);
		}
	}
} else {
	if (isset($_POST['manualssid']) && $_POST['manualssid'] == '1' && !empty($_POST['wlan0otherssid'])) {
		$_wlan0ssid = sprintf('<option value="%s" %s>%s</option>\n', 'None (activates AP mode)', '', 'None (activates AP mode)');
		$_wlan0ssid .= sprintf('<option value="%s" %s>%s</option>\n', $_POST['wlan0otherssid'], 'selected', htmlentities($_POST['wlan0otherssid']));
	} else if ($cfgNetwork[1]['wlanssid'] == 'None (activates AP mode)') {
		$_wlan0ssid .= sprintf('<option value="%s" %s>%s</option>\n', $cfgNetwork[1]['wlanssid'], 'selected', $cfgNetwork[1]['wlanssid']);
	} else {
		$_wlan0ssid = sprintf('<option value="%s" %s>%s</option>\n', 'None (activates AP mode)', '', 'None (activates AP mode)');
		$_wlan0ssid .= sprintf('<option value="%s" %s>%s</option>\n', $cfgNetwork[1]['wlanssid'], 'selected', htmlentities($cfgNetwork[1]['wlanssid']));
	}
}
$_wlan0sec .= "<option value=\"wpa\"" . ($cfgNetwork[1]['wlansec'] == 'wpa' ? 'selected' : '') . ">WPA/WPA2-Personal</option>\n";
$_wlan0sec .= "<option value=\"wpa23\"" . ($cfgNetwork[1]['wlansec'] == 'wpa23' ? 'selected' : '') . ">WPA3-Personal Transition Mode</option>\n";
// TBD $_wlan0sec .= "<option value=\"wpa3\"" . ($cfgNetwork[1]['wlansec'] == 'wpa3' ? 'selected' : '') . ">WPA3 Personal</option>\n";
$_wlan0sec .= "<option value=\"none\"" . ($cfgNetwork[1]['wlansec'] == 'none' ? 'selected' : '') . ">No security</option>\n";
$_wlan0pwd = $cfgNetwork[1]['wlanpwd'];

// WiFi country code
$zoneList = sysCmd("cat /usr/share/zoneinfo/iso3166.tab | tail -n +26 | tr '\t' ','");
$zoneListSorted = array();
for ($i = 0; $i < count($zoneList); $i++) {
	$country = explode(',', $zoneList[$i]);
	if ($country[1] == 'Britain (UK)') {$country[1] = 'United Kingdom (UK)';}
	$zoneListSorted[$i] = $country[1] . ',' . $country[0];
}
sort($zoneListSorted);
foreach ($zoneListSorted as $zone) {
	$country = explode(',', $zone);
	$selected = ($country[1] == $cfgNetwork[1]['wlan_country']) ? 'selected' : '';
	$_wlan0country .= sprintf('<option value="%s" %s>%s</option>\n', $country[1], $selected, $country[0]);
}

// Static ip
$_wlan0ipaddr = $cfgNetwork[1]['ipaddr'];
//$_wlan0netmask = $cfgNetwork[1]['netmask'];
$_wlan0netmask .= "<option value=\"24\" " . ($cfgNetwork[1]['netmask'] == '24' ? 'selected' : '') . " >255.255.255.0</option>\n";
$_wlan0netmask .= "<option value=\"16\" " . ($cfgNetwork[1]['netmask'] == '16' ? 'selected' : '') . " >255.255.0.0</option>\n";
$_wlan0gateway = $cfgNetwork[1]['gateway'];
$_wlan0pridns = $cfgNetwork[1]['pridns'];
$_wlan0secdns = $cfgNetwork[1]['secdns'];

// Access point
$_wlan0apdssid = $cfgNetwork[2]['wlanssid'];
$_wlan0apdchan = $cfgNetwork[2]['wlan_channel'];
$_wlan0apdpwd = $cfgNetwork[2]['wlanpwd'];
$_select['wlan0apd_router_on']  .= "<input type=\"radio\" name=\"wlan0apd_router\" id=\"toggle-wlan0apd-router-1\" value=\"On\" " . (($cfgNetwork[2]['wlan_router'] == 'On') ? "checked=\"checked\"" : "") . ">\n";
$_select['wlan0apd_router_off'] .= "<input type=\"radio\" name=\"wlan0apd_router\" id=\"toggle-wlan0apd-router-2\" value=\"Off\" " . (($cfgNetwork[2]['wlan_router'] == 'Off') ? "checked=\"checked\"" : "") . ">\n";
if (empty($_wlan0apdpwd)) {
	phpSession('open');
	$_SESSION['notify']['title'] = 'Notice';
	$_SESSION['notify']['msg'] = 'An Access Point password needs to be entered';
	$_SESSION['notify']['duration'] = 10;
	phpSession('close');
}

waitWorker('net-config');

$tpl = "net-config.html";
$section = basename(__FILE__, '.php');
storeBackLink($section, $tpl);

include('header.php');
eval("echoTemplate(\"" . getTemplate("templates/$tpl") . "\");");
include('footer.php');
