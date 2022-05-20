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

set_include_path('/var/www/inc');
require_once 'common.php';
require_once 'session.php';
require_once 'sql.php';

$dbh = sqlConnect();

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

		// Add extra vars
		$cfgSystem['debuglog'] = $_SESSION['debuglog'];
		$cfgSystem['kernelver'] = $_SESSION['kernelver'];
		$cfgSystem['procarch'] = $_SESSION['procarch'];
		$cfgSystem['raspbianver'] = $_SESSION['raspbianver'];
		$cfgSystem['ipaddress'] = $_SESSION['ipaddress'];
		$cfgSystem['bgimage'] = file_exists('/var/local/www/imagesw/bgimage.jpg') ? '../imagesw/bgimage.jpg' : '';
		$cfgSystem['rx_hostnames'] = $_SESSION['rx_hostnames'];
		$cfgSystem['rx_addresses'] = $_SESSION['rx_addresses'];
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
			'wlansec' => $row['wlansec'], 'wlanpwd' => $row['wlanpwd'], 'wlan_psk' => $row['wlan_psk'],
			'wlan_country' => $row['wlan_country'], 'wlan_channel' => $row['wlan_channel']);
		}
		$data['cfg_network'] = $cfgNetwork;

		// Radio stations
		if ($_GET['cmd'] == 'get_cfg_tables') {
			$result = sqlRead('cfg_radio', $dbh, 'all');
			$cfgRadio = array();
			foreach ($result as $row) {
				$cfgRadio[$row['station']] = array('name' => $row['name'], 'type' => $row['type'], 'logo' => $row['logo'], 'home_page' => $row['home_page']);
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
		// Add extra vars
		$cfgSystem['raspbianver'] = $_SESSION['raspbianver'];
		$cfgSystem['ipaddress'] = $_SESSION['ipaddress'];
		$cfgSystem['bgimage'] = file_exists('/var/local/www/imagesw/bgimage.jpg') ? '../imagesw/bgimage.jpg' : '';
		$cfgSystem['rx_hostnames'] = $_SESSION['rx_hostnames'];
		$cfgSystem['rx_addresses'] = $_SESSION['rx_addresses'];

		echo json_encode($cfgSystem);
		break;
	case 'upd_cfg_system':
		phpSession('open');

		// Update theme meta tag in header.php
		if (isset($_POST['themename']) && $_POST['themename'] != $_SESSION['themename']) {
			$result = sqlRead('cfg_theme', $dbh, $_POST['themename']);
			sysCmd("sed -i '/<meta name=\"theme-color\" content=/c\ \t<meta name=\"theme-color\" content=" . "\"rgb(" .
				$result[0]['bg_color'] . ")\">'" . ' /var/www/header.php');
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
}
