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

require_once dirname(__FILE__) . '/inc/playerlib.php';

playerSession('open', '' ,'');

// MULTIROOM AUDIO

// Sender
if (isset($_POST['update_multiroom_tx'])) {
	if (isset($_POST['multiroom_tx']) && $_POST['multiroom_tx'] != $_SESSION['multiroom_tx']) {
		$title = 'Multiroom sender ' . $_POST['multiroom_tx'];
		playerSession('write', 'multiroom_tx', $_POST['multiroom_tx']);
		submitJob('multiroom_tx', '', $title, '');
	}
}
if (isset($_POST['update_multiroom_tx_bfr'])) {
	if (isset($_POST['multiroom_tx_bfr']) && $_POST['multiroom_tx_bfr'] != $_cfg_multiroom['tx_bfr']) {
		$result = sdbquery("UPDATE cfg_multiroom SET value='" . $_POST['multiroom_tx_bfr'] . "' " . "WHERE param='tx_bfr'", cfgdb_connect());
		submitJob('multiroom_tx_restart', '', 'Send buffer updated', '');
	}
}
if (isset($_POST['multiroom_tx_restart'])) {
	submitJob('multiroom_tx_restart', '', 'Sender restarted', '');
}
if (isset($_POST['multiroom_tx_discover'])) {
	$array = sdbquery("SELECT value FROM cfg_system WHERE param='hostname'", cfgdb_connect());
	$thishost = strtolower($array[0]['value']);

	$_SESSION['rx_hostnames'] = '';
	$_SESSION['rx_addresses'] = '';

	$result = shell_exec("avahi-browse -a -t -r -p | awk -F '[;.]' '/IPv4/ && /moOde/ && /audio/ && /player/ && /=/ {print $7\",\"$9\".\"$10\".\"$11\".\"$12}' | sort");
	$line = strtok($result, "\n");
	while ($line) {
		list($host, $ipaddr) = explode(',', $line);
		if (strtolower($host) != $thishost) {
			if (false === ($result = file_get_contents('http://' . $ipaddr . '/command/?cmd=trx-status.php -rx'))) {
				workerLog('trx-config.php: get_rx_status failed: ' . $host);
			}
			else {
				if ($result != 'Unknown command') { // r740 or higher host
					$_SESSION['rx_hostnames'] .= $host . ', ';
					$_SESSION['rx_addresses'] .= $ipaddr . ' ';
				}
			}
		}

		$line = strtok("\n");
	}
	$_SESSION['rx_hostnames'] = rtrim($_SESSION['rx_hostnames'], ', ');
	$_SESSION['rx_addresses'] = rtrim($_SESSION['rx_addresses'], ' ');

	// Check for no receivers found
	if (empty(trim($_SESSION['rx_hostnames']))) {
		$_SESSION['rx_hostnames'] = 'No receivers found';
		$_SESSION['notify']['title'] = $_SESSION['rx_hostnames'];
	}
	else {
		$_SESSION['notify']['title'] = 'Discovery complete';
		$_SESSION['notify']['msg'] = 'Found: ' . $_SESSION['rx_hostnames'];
	}
}
if (isset($_POST['update_multiroom_initvol'])) {
	$result = sdbquery("UPDATE cfg_multiroom SET value='" . $_POST['multiroom_initvol'] . "' " . "WHERE param='initial_volume'", cfgdb_connect());
	submitJob('multiroom_initvol', $_POST['multiroom_initvol'], 'Volume levels initialized', '');
}

// Receiver
if (isset($_POST['update_multiroom_rx'])) {
	if (isset($_POST['multiroom_rx']) && $_POST['multiroom_rx'] != $_SESSION['multiroom_rx']) {
		$title = 'Multiroom receiver ' . $_POST['multiroom_rx'];
		playerSession('write', 'multiroom_rx', $_POST['multiroom_rx']);
		submitJob('multiroom_rx', '', $title, '');
	}
}
if (isset($_POST['update_multiroom_rx_bfr'])) {
	if (isset($_POST['multiroom_rx_bfr']) && $_POST['multiroom_rx_bfr'] != $_cfg_multiroom['rx_bfr']) {
		$result = sdbquery("UPDATE cfg_multiroom SET value='" . $_POST['multiroom_rx_bfr'] . "' " . "WHERE param='rx_bfr'", cfgdb_connect());
		submitJob('multiroom_rx_restart', '', 'Receive buffer updated', '');
	}
}
if (isset($_POST['update_multiroom_rx_jitter_bfr'])) {
	if (isset($_POST['multiroom_rx_jitter_bfr']) && $_POST['multiroom_rx_jitter_bfr'] != $_cfg_multiroom['rx_jitter_bfr']) {
		$result = sdbquery("UPDATE cfg_multiroom SET value='" . $_POST['multiroom_rx_jitter_bfr'] . "' " . "WHERE param='rx_jitter_bfr'", cfgdb_connect());
		submitJob('multiroom_rx_restart', '', 'Jitter buffer updated', '');
	}
}
if (isset($_POST['multiroom_rx_restart'])) {
	submitJob('multiroom_rx_restart', '', 'Receiver restarted', '');
}

session_write_close();

// MULTIROOM AUDIO

$params = cfgdb_read('cfg_multiroom', cfgdb_connect());
foreach ($params as $row) {
    $_cfg_multiroom[$row['param']] = $row['value'];
}

$_feat_multiroom = $_SESSION['feat_bitmask'] & FEAT_MULTIROOM ? '' : 'hide';
$_multiroom_tx_disable = $_SESSION['alsa_loopback'] == 'Off' ? 'disabled' : '';
$_multiroom_rx_disable = $_SESSION['alsavolume'] == 'none' ? 'disabled' : '';
$_tx_restart_btn_disable = $_SESSION['multiroom_tx'] == 'Off' ? 'disabled' : '';
$_rx_restart_btn_disable = $_SESSION['multiroom_rx'] == 'Off' ? 'disabled' : '';
$_tx_restart_link_disable = $_SESSION['multiroom_tx'] == 'Off' ? 'onclick="return false;"' : '';
$_rx_restart_link_disable = $_SESSION['multiroom_rx'] == 'Off' ? 'onclick="return false;"' : '';
$_multiroom_initvol_disable = empty($_SESSION['rx_hostnames']) ? 'disable' : '';
// Sender
$_select['multiroom_tx1'] .= "<input type=\"radio\" name=\"multiroom_tx\" id=\"toggle_multiroom_tx1\" value=\"On\" " . (($_SESSION['multiroom_tx'] == 'On') ? "checked=\"checked\"" : "") . ">\n";
$_select['multiroom_tx0'] .= "<input type=\"radio\" name=\"multiroom_tx\" id=\"toggle_multiroom_tx2\" value=\"Off\" " . (($_SESSION['multiroom_tx'] == 'Off') ? "checked=\"checked\"" : "") . ">\n";
$_select['multiroom_tx_bfr'] .= "<option value=\"16\" " . (($_cfg_multiroom['tx_bfr'] == '16') ? "selected" : "") . ">16 (Default)</option>\n";
$_select['multiroom_tx_bfr'] .= "<option value=\"32\" " . (($_cfg_multiroom['tx_bfr'] == '32') ? "selected" : "") . ">32</option>\n";
$_select['multiroom_tx_bfr'] .= "<option value=\"48\" " . (($_cfg_multiroom['tx_bfr'] == '48') ? "selected" : "") . ">48</option>\n";
$_select['multiroom_tx_bfr'] .= "<option value=\"64\" " . (($_cfg_multiroom['tx_bfr'] == '64') ? "selected" : "") . ">64</option>\n";
$_multiroom_initvol = $_cfg_multiroom['initial_volume'];
$_rx_hostnames = $_SESSION['rx_hostnames'] != 'No receivers found' ? 'Found: ' . $_SESSION['rx_hostnames'] : $_SESSION['rx_hostnames'];

// Receiver
$_select['multiroom_rx1'] .= "<input type=\"radio\" name=\"multiroom_rx\" id=\"toggle_multiroom_rx1\" value=\"On\" " . (($_SESSION['multiroom_rx'] == 'On') ? "checked=\"checked\"" : "") . ">\n";
$_select['multiroom_rx0'] .= "<input type=\"radio\" name=\"multiroom_rx\" id=\"toggle_multiroom_rx2\" value=\"Off\" " . (($_SESSION['multiroom_rx'] == 'Off') ? "checked=\"checked\"" : "") . ">\n";
$_select['multiroom_rx_bfr'] .= "<option value=\"16\" " . (($_cfg_multiroom['rx_bfr'] == '16') ? "selected" : "") . ">16 (Default)</option>\n";
$_select['multiroom_rx_bfr'] .= "<option value=\"32\" " . (($_cfg_multiroom['rx_bfr'] == '32') ? "selected" : "") . ">32</option>\n";
$_select['multiroom_rx_bfr'] .= "<option value=\"48\" " . (($_cfg_multiroom['rx_bfr'] == '48') ? "selected" : "") . ">48</option>\n";
$_select['multiroom_rx_bfr'] .= "<option value=\"64\" " . (($_cfg_multiroom['rx_bfr'] == '64') ? "selected" : "") . ">64</option>\n";
$_select['multiroom_rx_jitter_bfr'] .= "<option value=\"16\" " . (($_cfg_multiroom['rx_jitter_bfr'] == '16') ? "selected" : "") . ">16 (Default)</option>\n";
$_select['multiroom_rx_jitter_bfr'] .= "<option value=\"32\" " . (($_cfg_multiroom['rx_jitter_bfr'] == '32') ? "selected" : "") . ">32</option>\n";
$_select['multiroom_rx_jitter_bfr'] .= "<option value=\"48\" " . (($_cfg_multiroom['rx_jitter_bfr'] == '48') ? "selected" : "") . ">48</option>\n";
$_select['multiroom_rx_jitter_bfr'] .= "<option value=\"64\" " . (($_cfg_multiroom['rx_jitter_bfr'] == '64') ? "selected" : "") . ">64</option>\n";

waitWorker(1, 'trx-config');

$tpl = "trx-config.html";
$section = basename(__FILE__, '.php');
storeBackLink($section, $tpl);

include('header.php');
eval("echoTemplate(\"" . getTemplate("templates/$tpl") . "\");");
include('footer.php');
