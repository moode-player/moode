<?php
/*
 * SPDX-License-Identifier: GPL-3.0-or-later
 * Copyright 2014 The moOde audio player project / Tim Curtis
*/

require_once __DIR__ . '/../inc/common.php';
require_once __DIR__ . '/../inc/mpd.php';
require_once __DIR__ . '/../inc/multiroom.php';
require_once __DIR__ . '/../inc/session.php';
require_once __DIR__ . '/../inc/sql.php';

phpSession('open_ro');
$timeout = getStreamTimeout();

switch ($_GET['cmd']) {
	case 'get_rx_status':
        // NOTE: This is called from playerlib.js: multiroom-rx-modal
        if (!isset($_SESSION['rx_hostnames'])) {
    		$rxStatus = 'Discovery has not been run';
    	} else if ($_SESSION['rx_hostnames'] == 'No receivers found') {
    		$rxStatus = 'No receivers found';
    	} else {
    		$rxHostNames = explode(', ', $_SESSION['rx_hostnames']);
    		$rxAddresses = explode(' ', $_SESSION['rx_addresses']);
    		$rxStatus = '';

    		$count = count($rxAddresses);
    		for ($i = 0; $i < $count; $i++) {
				debugLog('multiroom.php: get_rx_status: ' . $rxHostNames[$i]);
    			if (false === ($status = file_get_contents('http://' . $rxAddresses[$i] . '/command/?cmd=' . rawurlencode('trx-control.php -rx'), false, $timeout))) {
    				$rxStatus .= 'rx,Unknown,?,?,?,' . $rxHostNames[$i] . ':';
    				workerLog('multiroom.php: get_rx_status: ' . $rxHostNames[$i] . ' failed');
    			} else {
    				// rx, On/Off/Disabled/Unknown, volume, mute_1/0, mastervol_opt_in_1/0, hostname
					$rxStatus .= $status . ':';
					debugLog('multiroom.php: get_rx_status: ' . $rxHostNames[$i] . ' ' . $status);
    			}
    		}

    		$rxStatus = empty($rxStatus) ? 'No receivers found' : rtrim($rxStatus, ':');
    	}

    	echo json_encode($rxStatus);
		break;
    case 'set_rx_status':
        $item = $_POST['item'];
    	$rxHostNames = explode(', ', $_SESSION['rx_hostnames']);
    	$rxAddresses = explode(' ', $_SESSION['rx_addresses']);

    	if (isset($_POST['onoff'])) {
			if (false === ($result = file_get_contents('http://' . $rxAddresses[$item] . '/command/?cmd=' . rawurlencode('trx-control.php -rx ' . $_POST['onoff']), false, $timeout))) {
				workerLog('multiroom.php: set_rx_status onoff failed: ' . $rxHostNames[$item]);
			}
    	} else if (isset($_POST['volume'])) {
			if (false === ($result = file_get_contents('http://' . $rxAddresses[$item] . '/command/?cmd=' . rawurlencode('trx-control.php -set-mpdvol ' . $_POST['volume']), false, $timeout))) {
				workerLog('multiroom.php: set_rx_status volume failed: ' . $rxHostNames[$item]);
			}
    	} else if (isset($_POST['mute'])) { // Toggle mute
			if (false === ($result = file_get_contents('http://' . $rxAddresses[$item] . '/command/?cmd=' . rawurlencode('vol.sh  -mute'), false, $timeout))) {
				workerLog('multiroom.php: set_rx_status mute failed: ' . $rxHostNames[$item]);
			}
    	}

    	echo json_encode('OK');
        break;
    case 'tx_adv_toggle':
	case 'rx_adv_toggle':
		phpSession('open');
		$_SESSION[$_GET['cmd']] = $_POST['adv_toggle'];
        phpSession('close');
		break;
	default:
		echo 'Unknown command';
		break;
}
