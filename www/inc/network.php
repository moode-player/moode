<?php
/*
 * SPDX-License-Identifier: GPL-3.0-or-later
 * Copyright 2014 The moOde audio player project / Tim Curtis
*/

require_once __DIR__ . '/common.php';
require_once __DIR__ . '/sql.php';

// Configure networks
function cfgNetworks() {
	$dbh = sqlConnect();
	$cfgNetwork = sqlQuery('SELECT * FROM cfg_network', $dbh); // [0] eth0, [1] wlan0, [2] apd0

	// Purge existing connections
	sysCmd('rm -f /etc/NetworkManager/system-connections/*');

	// Ethernet
	$fh = fopen('/etc/NetworkManager/system-connections/Ethernet.nmconnection', 'w');
	$data  = "#########################################\n";
	$data .= "# This file is managed by moOde          \n";
	$data .= "# Ethernet                               \n";
	$data .= "#########################################\n\n";
	$data .= "[connection]\n";
	$data .= "id=Ethernet" . "\n";
	$data .= "uuid=f8eba0b7-862d-4ccc-b93a-52815eb9c28d" . "\n";
	$data .= "type=ethernet\n";
	$data .= "interface-name=eth0\n";
	$data .= "autoconnect=true\n";
	$data .= "autoconnect-priority=100\n";
	$data .= "[ethernet]\n";
	$data .= "[ipv4]\n";
	$data .= getIPv4AddressBlock($cfgNetwork[0]);
	$data .= "[ipv6]\n";
	$data .= "addr-gen-mode=default\n";
	$data .= "method=auto\n";
	fwrite($fh, $data);
	fclose($fh);

	// Wireless: Configured SSID
	$fh = fopen('/etc/NetworkManager/system-connections/' . $cfgNetwork[1]['wlanssid'] . '.nmconnection', 'w');
	$data  = "#########################################\n";
	$data .= "# This file is managed by moOde          \n";
	$data .= "# Wireless: Configured SSID              \n";
	$data .= "#########################################\n\n";
	$data .= "[connection]\n";
	$data .= "id=" . $cfgNetwork[1]['wlanssid'] . "\n";
	$data .= "uuid=" . $cfgNetwork[1]['wlanuuid'] . "\n";
	$data .= "type=wifi\n";
	$data .= "interface-name=wlan0\n";
	$data .= "autoconnect=true\n";
	$data .= "autoconnect-priority=100\n";
	$data .= "[wifi]\n";
	$data .= "mode=infrastructure\n";
	$data .= "ssid=" . $cfgNetwork[1]['wlanssid'] . "\n";
	$data .= "hidden=false\n";
	$data .= "[wifi-security]\n";
	$data .= "key-mgmt=wpa-psk\n";
	$data .= "psk=" . $cfgNetwork[1]['wlanpsk'] . "\n";
	$data .= "[ipv4]\n";
	$data .= getIPv4AddressBlock($cfgNetwork[1]);
	$data .= "[ipv6]\n";
	$data .= "addr-gen-mode=default\n";
	$data .= "method=auto\n";
	fwrite($fh, $data);
	fclose($fh);

	// Wireless: Saved SSID(s) if any
	$cfgSSID = sqlQuery("SELECT * FROM cfg_ssid WHERE ssid != '" . SQLite3::escapeString($cfgNetwork[1]['wlanssid']) . "'", $dbh);
	foreach($cfgSSID as $row) {
		$fh = fopen('/etc/NetworkManager/system-connections/' . $row['ssid'] . '.nmconnection', 'w');
		$data  = "#########################################\n";
		$data .= "# This file is managed by moOde          \n";
		$data .= "# Wireless: Saved SSID                   \n";
		$data .= "#########################################\n\n";
		$data .= "[connection]\n";
		$data .= "id=" . $row['ssid'] . "\n";
		$data .= "uuid=" . $row['uuid'] . "\n";
		$data .= "type=wifi\n";
		$data .= "interface-name=wlan0\n";
		$data .= "autoconnect=true\n";
		$data .= "autoconnect-priority=20\n";
		$data .= "[wifi]\n";
		$data .= "mode=infrastructure\n";
		$data .= "ssid=" . $row['ssid'] . "\n";
		$data .= "hidden=false\n";
		$data .= "[wifi-security]\n";
		$data .= "key-mgmt=wpa-psk\n";
		$data .= "psk=" . $row['ssid'] . "\n";
		$data .= "[ipv4]\n";
		// TODO: Allow static ip address
		// Use same param names as in cfg_network
		$data .= "method=auto\n";
		$data .= "[ipv6]\n";
		$data .= "addr-gen-mode=default\n";
		$data .= "method=auto\n";
		fwrite($fh, $data);
		fclose($fh);
	}

	// Wireless: Hotspot
	$fh = fopen('/etc/NetworkManager/system-connections/Hotspot.nmconnection', 'w');
	$data  = "#########################################\n";
	$data .= "# This file is managed by moOde          \n";
	$data .= "# Wireless: Hotspot                      \n";
	$data .= "#########################################\n\n";
	$data .= "[connection]\n";
	$data .= "id=" . $cfgNetwork[2]['wlanssid'] . "\n";
	$data .= "uuid=" . $cfgNetwork[2]['wlanuuid'] . "\n";
	$data .= "type=wifi\n";
	$data .= "interface-name=wlan0\n";
	$data .= "autoconnect=false\n";
	$data .= "[wifi]\n";
	$data .= "mode=ap\n";
	$data .= "ssid=" . $cfgNetwork[2]['wlanssid'] . "\n";
	$data .= "[wifi-security]\n";
	$data .= "group=ccmp\n";
	$data .= "pairwise=ccmp\n";
	$data .= "proto=rsn\n";
	$data .= "key-mgmt=wpa-psk\n";
	$data .= "psk=" . $cfgNetwork[2]['wlanpsk'] . "\n";
	$data .= "[ipv4]\n";
	$data .= "method=shared\n";
	$data .= "address1=" . $_SESSION['ap_network_addr'] . "\n";
	$data .= "[ipv6]\n";
	$data .= "addr-gen-mode=default\n";
	$data .= "method=ignore\n";
	fwrite($fh, $data);
	fclose($fh);

	// Set permissions
	sysCmd('chmod 0600 /etc/NetworkManager/system-connections/*');
	// Set regulatory domain
	sysCmd('iw reg set "' . $cfgNetwork[1]['wlancc'] . '" >/dev/null 2>&1');
}

// Helper functions
function getIPv4AddressBlock($cfgNetwork) {
	if ($cfgNetwork['method'] == 'dhcp') {
		$data = "method=auto\n";
	} else {
		// Static
		$data =  "method=manual\n";
		$data .= "address1=" . $cfgNetwork['ipaddr'] . '/' . CIDR_TABLE[$cfgNetwork['netmask']] . "\n";
		$data .= "gateway=" . $cfgNetwork['gateway'] . "\n";
		$data .= "dns=" . $cfgNetwork['pridns'] .
			(empty($cfgNetwork['secdns']) ? '' : ',' . $cfgNetwork['secdns']) . "\n";
	}

	return $data;
}
function activateHotspot() {
	$connectedSSID = sysCmd("iwconfig wlan0 | grep 'ESSID' | awk -F':' '{print $2}' | awk -F'\"' '{print $2}'")[0];
	$hotspotSSID = sysCmd("cat /etc/NetworkManager/system-connections/Hotspot.nmconnection 2>&1 | awk -F\"=\" '/ssid=/ {print $2;exit;}'")[0];
	sysCmd('nmcli c down ' . $connectedSSID);
	sysCmd('nmcli c up ' . $hotspotSSID);
}

// Wait up to timeout seconds for IP address to be assigned to the interface
function checkForIpAddr($iface, $timeoutSecs, $sleepTime = 2) {
	$maxLoops = $timeoutSecs / $sleepTime;
	$ipAddr = '';

	for ($i = 0; $i < $maxLoops; $i++) {
		$result = sysCmd('ip addr list ' . $iface . " | grep \"inet \" | cut -d' ' -f6 | cut -d/ -f1");
		if (!empty($result)) {
			$ipAddr = $result[0];
			break;
		} else {
			debugLog('worker: ' . $iface .' check '. ($i + 1) . ' for IP address');
			sleep($sleepTime);
		}
	}

	return $ipAddr;
}

function getHostIp() {
	$eth0Ip = '';
	$wlan0Ip = '';

	// Check both interfaces
	$eth0 = sysCmd('ip addr list | grep eth0');
	if (!empty($eth0)) {
		$eth0Ip = sysCmd("ip addr list eth0 | grep \"inet \" | cut -d' ' -f6 | cut -d/ -f1");
	}
	$wlan0 = sysCmd('ip addr list | grep wlan0');
	if (!empty($wlan0)) {
		$wlan0Ip = sysCmd("ip addr list wlan0 | grep \"inet \" | cut -d' ' -f6 | cut -d/ -f1");
	}

	// Use Ethernet address if present
	if (!empty($eth0Ip)) {
		$hostIp = $eth0Ip[0];
	} else if (!empty($wlan0Ip)) {
		$hostIp = $wlan0Ip[0];
	} else {
		$hostIp = '127.0.0.1';
	}

	return $hostIp;
}

function genWpaPSK($ssid, $passphrase) {
	$fh = fopen('/tmp/passphrase', 'w');
	fwrite($fh, $passphrase . "\n");
	fclose($fh);

	$result = sysCmd('wpa_passphrase "' . $ssid . '" < /tmp/passphrase');
	sysCmd('rm /tmp/passphrase');

	$psk = explode('=', $result[4]);
	return $psk[1];
}

// For Bookworm nmconnection files
function genUUID() {
	return sysCmd('cat /proc/sys/kernel/random/uuid')[0];
}

// Pi integrated WiFi adapter enable/disable
function ctlWifi($ctl) {
	$value = $ctl == '0' ? '' : '#';
	updBootConfigTxt('upd_disable_wifi', $value);
}

// Pi integrated Bluetooth adapter enable/disable
function ctlBt($ctl) {
	$value = $ctl == '0' ? '' : '#';
	updBootConfigTxt('upd_disable_bt', $value);
}
