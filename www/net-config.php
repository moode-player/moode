<?php
/*
 * SPDX-License-Identifier: GPL-3.0-or-later
 * Copyright 2014 The moOde audio player project / Tim Curtis
*/

require_once __DIR__ . '/inc/common.php';
require_once __DIR__ . '/inc/network.php';
require_once __DIR__ . '/inc/session.php';
require_once __DIR__ . '/inc/sql.php';

$dbh = sqlConnect();
phpSession('open');

chkVariables($_POST, array('wlan0ssid', 'wlan0pwd', 'wlan0apdpwd'));

// Get current settings: [0] = eth0, [1] = wlan0, [2] = apd0
$cfgNetwork = sqlQuery('SELECT * FROM cfg_network', $dbh);

// Reset eth0 and wlan0 to defaults
if (isset($_POST['reset']) && $_POST['reset'] == 1) {
	// eth0
	$value = array('method' => 'dhcp', 'ipaddr' => '', 'netmask' => '', 'gateway' => '', 'pridns' => '', 'secdns' => '', 'wlanssid' => '',
		'wlanuuid' => '', 'wlanpwd' => '', 'wlanpsk' => '', 'wlancc' => '');
	sqlUpdate('cfg_network', $dbh, 'eth0', $value);

	// wlan0
	$value['wlanssid'] = 'None';
	$value['wlanuuid'] = '';
	$value['wlancc'] = $cfgNetwork[1]['wlancc']; // Preserve country code
	sqlUpdate('cfg_network', $dbh, 'wlan0', $value);

	submitJob('netcfg', '', NOTIFY_TITLE_INFO, NOTIFY_MSG_SYSTEM_RESTART_REQD);
}

// Update interfaces
if (isset($_POST['save']) && $_POST['save'] == 1) {
	// Ethernet (eth0)
	$value = array('method' => $_POST['eth0method'], 'ipaddr' => $_POST['eth0ipaddr'], 'netmask' => $_POST['eth0netmask'],
		'gateway' => $_POST['eth0gateway'], 'pridns' => $_POST['eth0pridns'], 'secdns' => $_POST['eth0secdns'], 'wlanssid' => '',
		'wlanuuid' => '', 'wlanpwd' => '', 'wlanpsk' => '', 'wlancc' => '');
	sqlUpdate('cfg_network', $dbh, 'eth0', $value);

	// Wireless (wlan0)
	// This values may be overridden if saved SSID exists (see $('#wlan0ssid').change in scripts-configs.js)
	$method = ($_POST['wlan0ssid'] == 'Activate Hotspot' || $_POST['wlan0ssid'] == 'None') ?
		'dhcp' : $_POST['wlan0method'];
	// Always generate a new UUID to make things simpler
	$uuid = genUUID();
	// Pre-shared key (PSK)
	if ($_POST['wlan0ssid'] != $cfgNetwork[1]['wlanssid'] || $_POST['wlan0pwd'] != $cfgNetwork[1]['wlanpsk']) {
		// SSID or password changed
		if (strlen($_POST['wlan0pwd']) < 64) {
			// Convert plain text password to PSK
			$psk = genWpaPSK($_POST['wlan0ssid'], $_POST['wlan0pwd']);
		} else {
			// Use PSK from saved SSID
			$psk = $_POST['wlan0pwd'];
		}
	} else {
		// Use PSK from configured SSID
		$psk = $cfgNetwork[1]['wlanpsk'];
	}

	// cfg_network
	$value = array('method' => $method, 'ipaddr' => $_POST['wlan0ipaddr'], 'netmask' => $_POST['wlan0netmask'],
		'gateway' => $_POST['wlan0gateway'], 'pridns' => $_POST['wlan0pridns'], 'secdns' => $_POST['wlan0secdns'],
		'wlanssid' => $_POST['wlan0ssid'], 'wlanuuid' => $uuid, 'wlanpwd' => $psk, 'wlanpsk' => $psk,
		'wlancc' => $_POST['wlan0country']);
	sqlUpdate('cfg_network', $dbh, 'wlan0', $value);

	// cfg_ssid
	if ($_POST['wlan0ssid'] != 'Activate Hotspot' && $_POST['wlan0ssid'] != 'None') {
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

	// apd0 (Hotspot)
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

	// Generate .nmconnection files
	submitJob('netcfg', '', NOTIFY_TITLE_INFO, NOTIFY_MSG_SYSTEM_RESTART_REQD);
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
			submitJob('netcfg', '', NOTIFY_TITLE_INFO, NOTIFY_MSG_SYSTEM_RESTART_REQD);
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
	for ($i = 0; $i < count($cfgSSID); $i++) {
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
	$netmaskText = $netmask == '255.255.255.0' ? '255.255.255.0 (Default)' : $netmask;
	$selected = (($netmask == '255.255.255.0' && $cfgNetwork[0]['netmask'] == '') || $netmask == $cfgNetwork[0]['netmask']) ? 'selected' : '';
	$_eth0netmask .= "<option value=\"" . $netmask . "\" " . $selected . " >" . $netmaskText . "</option>\n";
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
	if ($_SESSION['apactivated'] === true) {
		$_wlan0stats = $ipAddr[0] . ' Hotspot active';
	} else {
		// Network
		$ssid = sysCmd("iwconfig wlan0 | grep 'ESSID' | awk -F':' '{print $2}' | awk -F'\"' '{print $2}'");
		$bssid = sysCmd('iw dev wlan0 link | grep -i connected | cut -d" " -f3');
		// Connection
		$con = explode(':', sysCmd('nmcli -f CHAN,RATE,SECURITY -t dev wifi')[0]);
		// Quality
		$signal = sysCmd('iwconfig wlan0 | grep -i quality');
		$array = explode('=', $signal[0]);
		$qual = explode('/', $array[1]);
		$quality = round((100 * $qual[0]) / $qual[1]);
		$lev = explode('/', $array[2]);
		$level = strpos($lev[0], 'dBm') !== false ? $lev[0] : $lev[0] . '%';

		$_wlan0stats =
			'Address: ' . $ipAddr[0] . '<br>' .
			'Network: ' . $ssid[0] . ' (' . $bssid[0] . '), ' . $con[2] . '<br>' .
			'Channel: ' . $con[0] . ', ' . $con[1] . ', qual ' .  $quality . '%, level ' . $level;
	}
} else {
	$_wlan0stats = $_SESSION['apactivated'] === true ? 'Unable to activate Hotspot' : 'Not in use';
}

// Scanner
if (isset($_POST['scan']) && $_POST['scan'] == '1') {
	$result = sysCmd('nmcli -f SSID d wifi list ifname wlan0 | awk \'!(NF && seen[$1]++) {print}\'');
	sort($result, SORT_NATURAL | SORT_FLAG_CASE);
	$array = array();
	$array[0] = 'None';
	$array[1] = 'Activate Hotspot';
	$ssidList = array_merge($array, $result);
	foreach ($ssidList as $ssid) {
		$ssid = trim($ssid);
		// Additional filtering
		if (!empty($ssid) && $ssid != 'SSID' && $ssid != '--' && substr_count($ssid, ':') == 0) {
			$selected = ($cfgNetwork[1]['wlanssid'] == $ssid) ? 'selected' : '';
			$_wlan0ssid .= sprintf('<option value="%s" %s>%s</option>\n', $ssid, $selected, $ssid);
		}
	}
// SSID list
} else {
	$cfgSSID = sqlQuery("SELECT * FROM cfg_ssid", $dbh);
	if ($cfgSSID === true) {
		$cfgSSIDItems = '';
		$cfgSSIDItemsFiltered = '';
	} else {
		foreach($cfgSSID as $row) {
			$cfgSSIDItems .= sprintf('<option value="%s" %s>%s</option>\n', $row['ssid'], '', htmlentities($row['ssid']));
		}
		foreach($cfgSSID as $row) {
			if ($row['ssid'] != $cfgNetwork[1]['wlanssid']) {
				$cfgSSIDItemsFiltered .= sprintf('<option value="%s" %s>%s</option>\n', $row['ssid'], '', htmlentities($row['ssid']));
			}
		}
	}
	if (isset($_POST['manualssid']) && $_POST['manualssid'] == '1' && !empty($_POST['wlan0otherssid'])) {
		$_wlan0ssid = sprintf('<option value="%s" %s>%s</option>\n', 'None', '', 'None');
		$_wlan0ssid .= sprintf('<option value="%s" %s>%s</option>\n', 'Activate Hotspot', '', 'Activate Hotspot');
		$_wlan0ssid .= sprintf('<option value="%s" %s>%s</option>\n', $_POST['wlan0otherssid'], 'selected', htmlentities($_POST['wlan0otherssid']));
		$_wlan0ssid .= $cfgSSIDItems;
	} else if ($cfgNetwork[1]['wlanssid'] == 'None') {
		$_wlan0ssid = sprintf('<option value="%s" %s>%s</option>\n', 'None', 'selected', 'None');
		$_wlan0ssid .= sprintf('<option value="%s" %s>%s</option>\n', 'Activate Hotspot', '', 'Activate Hotspot');
		$_wlan0ssid .= $cfgSSIDItems;
	} else if ($cfgNetwork[1]['wlanssid'] == 'Activate Hotspot') {
		$_wlan0ssid = sprintf('<option value="%s" %s>%s</option>\n', 'None', '', 'None');
		$_wlan0ssid .= sprintf('<option value="%s" %s>%s</option>\n', 'Activate Hotspot', 'selected', 'Activate Hotspot');
		$_wlan0ssid .= $cfgSSIDItems;
	} else {
		$_wlan0ssid = sprintf('<option value="%s" %s>%s</option>\n', 'None', '', 'None');
		$_wlan0ssid .= sprintf('<option value="%s" %s>%s</option>\n', 'Activate Hotspot', '', 'Activate Hotspot');
		$_wlan0ssid .= sprintf('<option value="%s" %s>%s</option>\n', $cfgNetwork[1]['wlanssid'], 'selected', htmlentities($cfgNetwork[1]['wlanssid']));
		$_wlan0ssid .= $cfgSSIDItemsFiltered;
	}
}
// Password (PSK)
// TODO: load psk from cfg_ssid
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
	$netmaskText = $netmask == '255.255.255.0' ? '255.255.255.0 (Default)' : $netmask;
	$selected = (($netmask == '255.255.255.0' && $cfgNetwork[1]['netmask'] == '') || $netmask == $cfgNetwork[1]['netmask']) ? 'selected' : '';
	$_wlan0netmask .= "<option value=\"" . $netmask . "\" " . $selected . " >" . $netmaskText . "</option>\n";
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
	$_SESSION['notify']['title'] = NOTIFY_TITLE_ALERT;
	$_SESSION['notify']['msg'] = 'A Hotspot password needs to be entered. This is to ensure moOde can be accessed when Ethernet and WiFi are not available.';
	phpSession('close');
}

waitWorker('net-config');

$tpl = "net-config.html";
$section = basename(__FILE__, '.php');
storeBackLink($section, $tpl);

include('header.php');
eval("echoTemplate(\"" . getTemplate("templates/$tpl") . "\");");
include('footer.php');
