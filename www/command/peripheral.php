<?php
/*
 * SPDX-License-Identifier: GPL-3.0-or-later
 * Copyright 2014 The moOde audio player project / Tim Curtis
*/

require_once __DIR__ . '/../inc/common.php';
require_once __DIR__ . '/../inc/peripheral.php';

chkVariables($_GET);
chkVariables($_POST);

switch ($_GET['cmd']) {
	case 'get_peppy_meter_list':
	case 'get_peppy_spectrum_list':
		$type = str_contains($_GET['cmd'], 'meter') ? 'meter' : 'spectrum';
		echo json_encode(getPeppyFolderContents($type, $_GET['selected_folder']));
		break;
	default:
		echo 'Unknown command';
		break;
}
