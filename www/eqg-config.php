<?php
/**
 * SPDX-License-Identifier: GPL-3.0-or-later
 * Copyright 2014 The moOde audio player project / Tim Curtis
*/

require_once __DIR__ . '/inc/common.php';
require_once __DIR__ . '/inc/mpd.php';
require_once __DIR__ . '/inc/session.php';
require_once __DIR__ . '/inc/sql.php';

$dbh = sqlConnect();
phpSession('open');

if (isset($_POST['save']) && $_POST['save'] == '1') {
	for($i = 0; $i < 9; $i++) {
		$curveValues .= $_POST['freq' . ($i + 1)] . ',';
	}
	$curveValues .= $_POST['freq10'];

	$result = sqlQuery("SELECT id FROM cfg_eqalsa WHERE curve_name='" . $_POST['curve_name'] . "'", $dbh);
	if (empty($result[0])) {
		// Add
		$newID = sqlQuery('SELECT MAX(id)+1 FROM cfg_eqalsa', $dbh);
		$result = sqlQuery("INSERT INTO cfg_eqalsa VALUES ('" . $newID[0][0] . "','" . $_POST['curve_name'] . "','" . $curveValues . "')", $dbh);
		$_GET['curve'] = $_POST['curve_name'];
		$_SESSION['notify']['title'] = NOTIFY_TITLE_INFO;
		$_SESSION['notify']['msg'] = 'New curve added.';
	} else {
		// Update
		$result = sqlQuery("UPDATE cfg_eqalsa SET curve_values='" . $curveValues . "' WHERE curve_name='" . $_POST['curve_name'] . "'" , $dbh);
		$_SESSION['notify']['title'] = NOTIFY_TITLE_INFO;
		$_SESSION['notify']['msg'] = 'Curve updated.';
	}
}

if (isset($_POST['play']) && $_POST['play'] == '1') {
	// Update alsaequal, changes take efect in real-time, no need to restart MPD
	for ($i = 1; $i <= 10; $i++) {
		sysCmd('amixer -D alsaequal cset numid=' . $i . ' ' . $_POST['freq' . $i]);
	}

	// Wait for mpd to start accepting connections
	$sock = openMpdSock('localhost', 6600);
	// Then initiate play
	sendMpdCmd($sock, 'stop');
	$resp = readMpdResp($sock);
	sendMpdCmd($sock, 'play');
	$resp = readMpdResp($sock);
	closeMpdSock($sock);

	phpSession('write', 'alsaequal', $_POST['curve_name']);
	$_SESSION['notify']['title'] = NOTIFY_TITLE_INFO;
	$_SESSION['notify']['msg'] = 'Playing curve.';
}

if (isset($_POST['new_curve'])) {
	$searchCurve = 'Flat';
} else if (isset($_POST['remove_curve'])) {
	$result = sqlQuery("DELETE FROM cfg_eqalsa WHERE curve_name='" . $_GET['curve'] . "'", $dbh);
	$searchCurve = 'Flat';
	$_SESSION['notify']['title'] = NOTIFY_TITLE_INFO;
	$_SESSION['notify']['msg'] = 'Curve removed.';
} else if (isset($_GET['curve'])) {
	$searchCurve = $_GET['curve'];
} else {
	$searchCurve = $_SESSION['alsaequal'] == 'Off' ? 'Flat' : $_SESSION['alsaequal'];
}

phpSession('close');

$_selected_curve = 'Flat';
$curveList = sqlQuery('SELECT curve_name FROM cfg_eqalsa', $dbh);

foreach ($curveList as $curve) {
	$selected = ($searchCurve == $curve['curve_name'] && $_POST['new_curve'] != '1') ? 'selected' : '';
	$_select['curve_name'] .= sprintf('<option value="%s" %s>%s</option>\n', $curve['curve_name'], $selected, $curve['curve_name']);
	if ($selected == 'selected') {
		$_selected_curve = $curve['curve_name'];
	}
}

if (isset($_POST['new_curve']) && $_POST['new_curve'] == '1') {
	$_select['curve_name'] .= sprintf('<option value="%s" %s>%s</option>\n', $_POST['new_curve_name'], 'selected', $_POST['new_curve_name']);
	$_selected_curve = $_POST['new_curve_name'];
}

$_disable_play = $_SESSION['alsaequal'] == 'Off' ? 'disabled' : '';
$_disable_rm = $_selected_curve == 'Flat' ? 'disabled' : '';
$_disable_rm_msg = $_selected_curve == 'Flat' ? 'The Flat curve cannot be removed' : '';

$result = sqlQuery("SELECT * FROM cfg_eqalsa WHERE curve_name='" . $searchCurve . "'", $dbh);
$values = explode(',', $result[0]['curve_values']);
for ($i = 0; $i < 10; $i++) {
	$_select['freq' . ($i + 1)] = $values[$i];
}

waitWorker('eqg-config');

$tpl = "eqg-config.html";
$section = basename(__FILE__, '.php');
storeBackLink($section, $tpl);

include('header.php');
eval("echoTemplate(\"" . getTemplate("templates/$tpl") . "\");");
include('footer.php');
