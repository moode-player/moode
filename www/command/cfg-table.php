<?php
/*
 * SPDX-License-Identifier: GPL-3.0-or-later
 * Copyright 2014 The moOde audio player project / Tim Curtis
*/

require_once __DIR__ . '/../inc/common.php';
require_once __DIR__ . '/../inc/session.php';
require_once __DIR__ . '/../inc/sql.php';

$dbh = sqlConnect();

chkVariables($_GET);
chkVariables($_POST, array('library_flatlist_filter_str'));
chkValueEx('library_flatlist_filter_str', $_POST['library_flatlist_filter_str']);

switch ($_GET['cmd']) {
	case 'get_cfg_tables':
	case 'get_cfg_tables_no_radio':
		phpSession('open_ro');
		// System settings
		$result = sqlRead('cfg_system', $dbh);
		$cfgSystem = array();
		foreach ($result as $row) {
			$cfgSystem[$row['param']] = $row['value'];
		}

		addExtraSessionVars($cfgSystem);

		$data['cfg_system'] = $cfgSystem;

		// Theme settings
		$result = sqlRead('cfg_theme', $dbh);
		$cfgTheme = array();
		foreach ($result as $row) {
			$cfgTheme[$row['theme_name']] = array('tx_color' => $row['tx_color'], 'bg_color' => $row['bg_color'],
			'mbg_color' => $row['mbg_color']);
		}
		$data['cfg_theme'] = $cfgTheme;

		// Network settings
		$result = sqlRead('cfg_network', $dbh);
		$cfgNetwork = array();
		foreach ($result as $row) {
			$cfgNetwork[$row['iface']] = array('method' => $row['method'], 'ipaddr' => $row['ipaddr'], 'netmask' => $row['netmask'],
			'gateway' => $row['gateway'], 'pridns' => $row['pridns'], 'secdns' => $row['secdns'], 'wlanssid' => $row['wlanssid'],
			'wlanuuid' => $row['wlanuuid'], 'wlanpwd' => $row['wlanpwd'], 'wlanpsk' => $row['wlanpsk'],
			'wlancc' => $row['wlancc']);
		}
		$data['cfg_network'] = $cfgNetwork;
		// Saved SSID's
		$result = sqlRead('cfg_ssid', $dbh);
		$cfgSSID = array();
		foreach ($result as $row) {
			$cfgSSID[$row['ssid']] = array('uuid' => $row['uuid'], 'psk' => $row['psk'], 'method' => $row['method'], 'ipaddr' => $row['ipaddr'], 'netmask' => $row['netmask'],
			'gateway' => $row['gateway'], 'pridns' => $row['pridns'], 'secdns' => $row['secdns']);
		}
		$data['cfg_ssid'] = $cfgSSID;

		// Radio stations
		if ($_GET['cmd'] == 'get_cfg_tables') {
			$result = sqlRead('cfg_radio', $dbh, 'all');
			$cfgRadio = array();
			foreach ($result as $row) {
				$cfgRadio[$row['station']] = array('name' => $row['name'], 'type' => $row['type'],
					'logo' => $row['logo'], 'bitrate' => $row['bitrate'], 'format' => $row['format'],
					'home_page' => $row['home_page'], 'monitor' => $row['monitor']);
			}
			$data['cfg_radio'] = $cfgRadio;
		}

		echo json_encode($data);
		break;
	case 'get_cfg_system':
		phpSession('open_ro');
		$result = sqlRead('cfg_system', $dbh);
		$cfgSystem = array();

		foreach ($result as $row) {
			$cfgSystem[$row['param']] = $row['value'];
		}

		addExtraSessionVars($cfgSystem);

		echo json_encode($cfgSystem);
		break;
	case 'get_cfg_system_value':
		$result = sqlRead('cfg_system', $dbh, $_GET['param']);
		echo json_encode($result[0]['value']);
		break;
	case 'upd_cfg_system':
		phpSession('open');

		// Update theme meta tag in header.php
		if (isset($_POST['themename']) && $_POST['themename'] != $_SESSION['themename']) {
			$result = sqlRead('cfg_theme', $dbh, $_POST['themename']);
			sysCmd('sed -i "s/theme-color.*/theme-color\" content=\"rgb(' . $result[0]['bg_color'] . ')\">/" /var/www/header.php');
		}

		// Session only vars with no mirror column in cfg_system
		if (isset($_POST['lib_scope'])) {
			$_SESSION['lib_scope'] = $_POST['lib_scope'];
			unset($_POST['lib_scope']);
		}
		if (isset($_POST['lib_active_search'])) {
			$_SESSION['lib_active_search'] = $_POST['lib_active_search'];
			unset($_POST['lib_active_search']);
		}
		if (isset($_POST['on_screen_kbd'])) {
			$_SESSION['on_screen_kbd'] = $_POST['on_screen_kbd'];
			unset($_POST['on_screen_kbd']);
		}
		if (isset($_POST['mpd_monitor_svc'])) {
			$_SESSION['mpd_monitor_svc'] = $_POST['mpd_monitor_svc'];
			unset($_POST['mpd_monitor_svc']);
		}
		if (isset($_POST['mpd_monitor_opt'])) {
			$_SESSION['mpd_monitor_opt'] = $_POST['mpd_monitor_opt'];
			unset($_POST['mpd_monitor_opt']);
		}

		// Update cfg_system
		foreach (array_keys($_POST) as $var) {
			phpSession('write', $var, $_POST[$var]);
		}

		phpSession('close');

		echo json_encode('OK');
		break;
	case 'get_theme_name':
		if (isset($_GET['theme_name'])) {
			$result = sqlRead('cfg_theme', $dbh, $_GET['theme_name']);
			echo json_encode($result[0]); // Return specific row
		} else {
			$result = sqlRead('cfg_theme', $dbh);
			echo json_encode($result); // Return all rows
		}
		break;
	default:
		echo 'Unknown command';
		break;
}

function addExtraSessionVars(&$cfgSystem) {
	$cfgSystem['debuglog'] = $_SESSION['debuglog'];
	$cfgSystem['xss_detect'] = $_SESSION['xss_detect'];
	$cfgSystem['hdwrrev'] = $_SESSION['hdwrrev'];
	$cfgSystem['raspbianver'] = $_SESSION['raspbianver'];
	$cfgSystem['kernelver'] = $_SESSION['kernelver'];
	$cfgSystem['mpdver'] = $_SESSION['mpdver'];
	$cfgSystem['ipaddress'] = $_SESSION['ipaddress'];
	$cfgSystem['bgimage'] = file_exists('/var/local/www/imagesw/bgimage.jpg') ? '../imagesw/bgimage.jpg' : '';
	$cfgSystem['rx_hostnames'] = $_SESSION['rx_hostnames'];
	$cfgSystem['rx_addresses'] = $_SESSION['rx_addresses'];
	$cfgSystem['updater_auto_check'] = $_SESSION['updater_auto_check'];
	$cfgSystem['updater_available_update'] = $_SESSION['updater_available_update'];
	$cfgSystem['lib_scope'] = $_SESSION['lib_scope'];
	$cfgSystem['lib_active_search'] = $_SESSION['lib_active_search'];
	$cfgSystem['auto_coverview'] = $_SESSION['auto_coverview'];
	$cfgSystem['on_screen_kbd'] = $_SESSION['on_screen_kbd'];
	$cfgSystem['mpd_monitor_svc'] = $_SESSION['mpd_monitor_svc'];
	$cfgSystem['mpd_monitor_opt'] = $_SESSION['mpd_monitor_opt'];
	$cfgSystem['user_id'] = $_SESSION['user_id'];
}
