<?php
/*
 * SPDX-License-Identifier: GPL-3.0-or-later
 * Copyright 2014 The moOde audio player project / Tim Curtis
*/

require_once __DIR__ . '/inc/common.php';
require_once __DIR__ . '/inc/renderer.php';
require_once __DIR__ . '/inc/session.php';
require_once __DIR__ . '/inc/sql.php';

$dbh = sqlConnect();
phpSession('open');

if (isset($_POST['save']) && $_POST['save'] == '1') {
	$validKeys = array_column(sqlRead('cfg_soloist', $dbh), 'param');
	$volError = false;
	foreach ($_POST['config'] as $key => $value) {
		chkKey($key, $validKeys);
		chkValue($key, $value);
		if ($key == 'initial_volume' && (!ctype_digit($value) || (int)$value > 100)) {
			$volError = true;
			continue;
		}
		sqlUpdate('cfg_soloist', $dbh, $key, $value);
	}
	// API key: an empty field keeps the saved key
	$keyError = false;
	if (!empty($_POST['soloistkey'])) {
		$keyError = setSoloistApiKey($_POST['soloistkey']) === false;
	}
	if ($keyError || $volError) {
		$_SESSION['notify']['title'] = NOTIFY_TITLE_ALERT;
		$_SESSION['notify']['msg'] = $keyError ? 'Invalid API key.' :
			'Initial volume must be 0 - 100.';
	} else if ($_SESSION['soloistsvc'] == '1') {
		submitJob('soloistsvc', '', NOTIFY_TITLE_INFO, NAME_SOLOIST . NOTIFY_MSG_SVC_RESTARTED);
	}
}

phpSession('close');

$result = sqlRead('cfg_soloist', $dbh);
$cfgSoloist = array();
foreach ($result as $row) {
	$cfgSoloist[$row['param']] = $row['value'];
}

$_select['initial_volume'] = $cfgSoloist['initial_volume'];
if (getSoloistApiKey() === false) {
	$_soloistkey_status = '<span class="config-help-static">An API key has not been saved.</span>';
	$_soloistkey_placeholder = '';
} else {
	$_soloistkey_status = '';
	$_soloistkey_placeholder = '••••••••••••••••';
}

waitWorker('sol_config');

$tpl = "sol-config.html";
$section = basename(__FILE__, '.php');
storeBackLink($section, $tpl);

include('header.php');
eval("echoTemplate(\"" . getTemplate("templates/$tpl") . "\");");
include('footer.php');
