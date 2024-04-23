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
		'wlanuuid' => '', 'wlanpwd' => '', 'wlanpsk' => '', 'wlancc' => '');
	sqlUpdate('cfg_network', $dbh, 'eth0', $value);

	// wlan0
	$value['wlanssid'] = 'Activate Hotspot';
	$value['wlanuuid'] = '';
	$value['wlancc'] = $cfgNetwork[1]['wlancc']; // Preserve country code
	sqlUpdate('cfg_network', $dbh, 'wlan0', $value);

	submitJob('netcfg', '', 'Network config reset', 'Restart required');
}

// Update interfaces
if (isset($_POST['save']) && $_POST['save'] == 1) {
	// eth0
	$value = array('method' => $_POST['eth0method'], 'ipaddr' => $_POST['eth0ipaddr'], 'netmask' => $_POST['eth0netmask'],
		'gateway' => $_POST['eth0gateway'], 'pridns' => $_POST['eth0pridns'], 'secdns' => $_POST['eth0secdns'], 'wlanssid' => '',
		'wlanuuid' => '', 'wlanpwd' => '', 'wlanpsk' => '', 'wlancc' => '');
	sqlUpdate('cfg_network', $dbh, 'eth0', $value);

	// wlan0
	$method = $_POST['wlan0ssid'] == 'Activate Hotspot' ? 'dhcp' : $_POST['wlan0method'];
	$uuid = genUUID();
	if ($_POST['wlan0ssid'] != $cfgNetwork[1]['wlanssid'] || $_POST['wlan0pwd'] != $cfgNetwork[1]['wlanpsk']) {
		$psk = genWpaPSK($_POST['wlan0ssid'], $_POST['wlan0pwd']);
	} else {
		$psk = $cfgNetwork[1]['wlanpsk'];
	}

	// cfg_network
	$value = array('method' => $method, 'ipaddr' => $_POST['wlan0ipaddr'], 'netmask' => $_POST['wlan0netmask'],
		'gateway' => $_POST['wlan0gateway'], 'pridns' => $_POST['wlan0pridns'], 'secdns' => $_POST['wlan0secdns'],
		'wlanssid' => $_POST['wlan0ssid'], 'wlanuuid' => $uuid, 'wlanpwd' => $psk, 'wlanpsk' => $psk,
		'wlancc' => $_POST['wlan0country']);
	sqlUpdate('cfg_network', $dbh, 'wlan0', $value);

	// cfg_ssid
	if ($_POST['wlan0ssid'] != 'Activate Hotspot') {
		$cfgSSID = sqlQuery("SELECT * FROM cfg_ssid WHERE ssid='" . SQLite3::escapeString($_POST['wlan0ssid']) . "'", $dbh);
		if ($cfgSSID === true) {
			$values =
				"'"	. SQLite3::escapeString($_POST['wlan0ssid']) . "', " .
				"'" . $uuid . "', " .
				"'" . $psk . "', " .
				"'" . $method . "', " .
				"'" . $_POST['wlan0ipaddr'] . "', " .
				"'" . $_POST['wlan0netmask'] . "', " .
				"'" . $_POST['wlan0gateway'] . "', " .
				"'" . $_POST['wlan0pridns'] . "', " .
				"'" . $_POST['wlan0secdns'] . "'";
			$result = sqlQuery("INSERT INTO cfg_ssid VALUES " . '(NULL,' . $values . ')', $dbh);
		} else {
			$result = sqlQuery("UPDATE cfg_ssid SET " .
				"uuid='" .    $uuid . "', " .
				"psk='" .     $psk . "', " .
				"method='" .  $method . "', " .
				"ipaddr='" .  $_POST['wlan0ipaddr'] . "', " .
				"netmask='" . $_POST['wlan0netmask'] . "', " .
				"gateway='" . $_POST['wlan0gateway'] . "', " .
				"pridns='" .  $_POST['wlan0pridns'] . "', " .
				"secdns='" .  $_POST['wlan0secdns'] . "' " .
				"WHERE id='" . $cfgSSID[0]['id'] . "'" , $dbh);
		}
	}

	// apd0
	if ($_POST['wlan0apdssid'] != $cfgNetwork[2]['wlanssid'] || $_POST['wlan0apdpwd'] != $cfgNetwork[2]['wlanpsk']) {
		$uuid = genUUID();
		$psk = genWpaPSK($_POST['wlan0apdssid'], $_POST['wlan0apdpwd']);
	} else {
		$uuid = $cfgNetwork[2]['wlanuuid'];
		$psk = $cfgNetwork[2]['wlanpsk'];
	}
	$value = array('method' => '', 'ipaddr' => '', 'netmask' => '', 'gateway' => '', 'pridns' => '', 'secdns' => '',
		'wlanssid' => $_POST['wlan0apdssid'], 'wlanuuid' => $uuid, 'wlanpwd' => $psk, 'wlanpsk' => $psk,
		'wlancc' => '');
	sqlUpdate('cfg_network', $dbh, 'apd0', $value);

	// Generate nmconnect files
	submitJob('netcfg', '', 'Settings updated', 'Restart required');
}

// Update saved networks
if (isset($_POST['update-saved-networks']) && $_POST['update-saved-networks'] == 1) {
	$cfgSSID = sqlQuery("SELECT * FROM cfg_ssid WHERE ssid != '" . $cfgNetwork[1]['wlanssid'] . "'", $dbh);
	if ($cfgSSID !== true) {
		$itemDeleted = false;
		for ($i = 0; $i < count($cfgSSID); $i++) {
			$_post_ssid = 'cfg-ssid-' . $cfgSSID[$i]['id'];
			if (isset($_POST[$_post_ssid]) && $_POST[$_post_ssid] == 'on') {
				$result = sqlQuery("DELETE FROM cfg_ssid WHERE id = '" . $cfgSSID[$i]['id'] . "'", $dbh);
				$itemDeleted = true;
			}
		}

		if ($itemDeleted) {
			submitJob('netcfg', '', 'Settings updated', 'Restart required');
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
$cfgSSID = sqlQuery("SELECT * FROM cfg_ssid WHERE ssid != '" . SQLite3::escapeString($cfgNetwork[1]['wlanssid']) . "'", $dbh);
if ($cfgSSID === true) {
	$_saved_networks = '<p style="text-align:center;">There are no saved networks</p>';
} else {
	for ($i=0; $i < count($cfgSSID); $i++) {
		$_saved_networks .= '<div class="control-group">';
		$_saved_networks .= '<label class="control-label">' . $cfgSSID[$i]['ssid'] . '</label>';
		$_saved_networks .= '<div class="controls">';
		$_saved_networks .= '<input name="cfg-ssid-' . $cfgSSID[$i]['id'] . '" class="checkbox-ctl saved-ssid-modal-delete" type="checkbox"><em>Delete</em>';
		$_saved_networks .= '</div>';
		$_saved_networks .= '</div>';
	}
}

// Ethernet

$_eth0method .= "<option value=\"dhcp\" "   . ($cfgNetwork[0]['method'] == 'dhcp' ? 'selected' : '') . " >DHCP</option>\n";
$_eth0method .= "<option value=\"static\" " . ($cfgNetwork[0]['method'] == 'static' ? 'selected' : '') . " >STATIC</option>\n";
// Display IP address or message
$ipAddr = sysCmd("ip addr list eth0 |grep \"inet \" |cut -d' ' -f6|cut -d/ -f1");
$_eth0currentip = empty($ipAddr[0]) ? 'Not in use' : $ipAddr[0];
// Static IP
$_eth0ipaddr = $cfgNetwork[0]['ipaddr'];
foreach(array_keys(CIDR_TABLE) as $netmask) {
	$_eth0netmask .= "<option value=\"" . $netmask . "\" " . ($cfgNetwork[0]['netmask'] == $netmask ? 'selected' : '') . " >" . $netmask . "</option>\n";
}
$_eth0gateway = $cfgNetwork[0]['gateway'];
$_eth0pridns = $cfgNetwork[0]['pridns'];
$_eth0secdns = $cfgNetwork[0]['secdns'];

// Wireless
$_wlan0method .= "<option value=\"dhcp\" " . ($cfgNetwork[1]['method'] == 'dhcp' ? 'selected' : '') . " >DHCP</option>\n";
$_wlan0method .= "<option value=\"static\" " . ($cfgNetwork[1]['method'] == 'static' ? 'selected' : '') . " >STATIC</option>\n";
// Get IP address if any
$ipAddr = sysCmd("ip addr list wlan0 |grep \"inet \" |cut -d' ' -f6|cut -d/ -f1");
// Get link quality and signal level
if (!empty($ipAddr[0])) {
	if ($_SESSION['apactivated'] == true) {
		$_wlan0stats = $ipAddr[0] . ' Hotspot active';
	} else {
		$ssid = sysCmd("iwconfig wlan0 | grep 'ESSID' | awk -F':' '{print $2}' | awk -F'\"' '{print $2}'");
		$bssid = sysCmd('iw dev wlan0 link | grep -i connected | cut -d" " -f3');
		$signal = sysCmd('iwconfig wlan0 | grep -i quality');
		$array = explode('=', $signal[0]);
		$qual = explode('/', $array[1]);
		$quality = round((100 * $qual[0]) / $qual[1]);
		$lev = explode('/', $array[2]);
		$level = strpos($lev[0], 'dBm') !== false ? $lev[0] : $lev[0] . '%';
		$_wlan0stats =
			'Address: ' . $ipAddr[0] . '<br>' .
			'Network: ' . $ssid[0] . ' (' . $bssid[0] . ')<br>' .
			'Quality: ' . $quality . '% level ' . $level;
	}
} else {
	$_wlan0stats = $_SESSION['apactivated'] == true ? 'Unable to activate Hotspot' : 'Not in use';
}

// Scanner
if (isset($_POST['scan']) && $_POST['scan'] == '1') {
	$result = sysCmd('nmcli d wifi list | awk \'!(NF && seen[$2]++) {print $2}\'');
	sort($result, SORT_NATURAL | SORT_FLAG_CASE);
	$array = array();
	$array[0] = 'Activate Hotspot';
	$ssidList = array_merge($array, $result);
	foreach ($ssidList as $ssid) {
		$ssid = trim($ssid);
		// Additional filtering
		if (!empty($ssid) && $ssid != '--' && substr_count($ssid, ':') == 0) {
			$selected = ($cfgNetwork[1]['wlanssid'] == $ssid) ? 'selected' : '';
			$_wlan0ssid .= sprintf('<option value="%s" %s>%s</option>\n', $ssid, $selected, $ssid);
		}
	}
// SSID list
} else {
	if (isset($_POST['manualssid']) && $_POST['manualssid'] == '1' && !empty($_POST['wlan0otherssid'])) {
		$_wlan0ssid = sprintf('<option value="%s" %s>%s</option>\n', 'Activate Hotspot', '', 'Activate Hotspot');
		$_wlan0ssid .= sprintf('<option value="%s" %s>%s</option>\n', $_POST['wlan0otherssid'], 'selected', htmlentities($_POST['wlan0otherssid']));
	} else if ($cfgNetwork[1]['wlanssid'] == 'Activate Hotspot') {
		$_wlan0ssid .= sprintf('<option value="%s" %s>%s</option>\n', $cfgNetwork[1]['wlanssid'], 'selected', $cfgNetwork[1]['wlanssid']);
	} else {
		$_wlan0ssid = sprintf('<option value="%s" %s>%s</option>\n', 'Activate Hotspot', '', 'Activate Hotspot');
		$_wlan0ssid .= sprintf('<option value="%s" %s>%s</option>\n', $cfgNetwork[1]['wlanssid'], 'selected', htmlentities($cfgNetwork[1]['wlanssid']));
	}
}
// Password (PSK)
$_wlan0pwd = empty($_POST['wlan0otherssid']) ? $cfgNetwork[1]['wlanpwd'] : '';
// Country code
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
	$selected = ($country[1] == $cfgNetwork[1]['wlancc']) ? 'selected' : '';
	$_wlan0country .= sprintf('<option value="%s" %s>%s</option>\n', $country[1], $selected, $country[0]);
}
// Static IP
$_wlan0ipaddr = $cfgNetwork[1]['ipaddr'];
foreach(array_keys(CIDR_TABLE) as $netmask) {
	$_wlan0netmask .= "<option value=\"" . $netmask . "\" " . ($cfgNetwork[1]['netmask'] == $netmask ? 'selected' : '') . " >" . $netmask . "</option>\n";
}
$_wlan0gateway = $cfgNetwork[1]['gateway'];
$_wlan0pridns = $cfgNetwork[1]['pridns'];
$_wlan0secdns = $cfgNetwork[1]['secdns'];

// Hotspot

$_wlan0apdssid = $cfgNetwork[2]['wlanssid'];
$_wlan0apdpwd = $cfgNetwork[2]['wlanpwd'];
$_ap_network = 'http://' . explode('/', $_SESSION['ap_network_addr'])[0];
$_ap_host = 'http://' . $_wlan0apdssid . '.local';
if (empty($_wlan0apdpwd)) {
	phpSession('open');
	$_SESSION['notify']['title'] = 'Notice';
	$_SESSION['notify']['msg'] = 'A Hotspot password needs to be entered';
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
