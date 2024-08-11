<?php
/*
 * SPDX-License-Identifier: GPL-3.0-or-later
 * Copyright 2014 The moOde audio player project / Tim Curtis
*/

require_once __DIR__ . '/../inc/common.php';
require_once __DIR__ . '/../inc/session.php';
require_once __DIR__ . '/../inc/cdsp.php';
require_once __DIR__ . '/../inc/mpd.php';

chkVariables($_GET);
chkVariables($_POST);

switch ($_GET['cmd']) {
	case 'cdsp_set_config':
		if (isset($_POST['cdspconfig']) && !empty($_POST['cdspconfig'])) {
			phpSession('open');
			$cdsp = new CamillaDsp($_SESSION['camilladsp'], $_SESSION['cardnum'], $_SESSION['camilladsp_quickconv']);
			$currentMode = $_SESSION['camilladsp'];
			$newMode = $_POST['cdspconfig'];
			phpSession('write', 'camilladsp', $newMode);
			phpSession('close');

			$cdsp->selectConfig($newMode);

			if ($_SESSION['cdsp_fix_playback'] == 'Yes') {
				$cdsp->setPlaybackDevice($_SESSION['cardnum'], $_SESSION['alsa_output_mode']);
			}

			$cdsp->updCDSPConfig($newMode, $currentMode, $cdsp);
			unset($cdsp);
		} else {
			sendFECmd('cdsp_config_update_failed');
			workerLog('camilla.php: Error: $_POST[cdspconfig] missing or empty');
		}
		break;
	case 'cdsp_get_config_desc':
		phpSession('open_ro');
		$cdsp = new CamillaDsp($_SESSION['camilladsp'], $_SESSION['cardnum'], $_SESSION['camilladsp_quickconv']);
		echo json_encode($cdsp->getConfigDescription($_GET['selected_config']));
		unset($cdsp);
		break;
	default:
		echo 'Unknown command';
		break;
}
