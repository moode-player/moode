<?php
/*
 * SPDX-License-Identifier: GPL-3.0-or-later
 * Copyright 2014 The moOde audio player project / Tim Curtis
*/

require_once __DIR__ . '/inc/common.php';
require_once __DIR__ . '/inc/session.php';
require_once __DIR__ . '/inc/sql.php';

$dbh = sqlConnect();
phpSession('open');

if (isset($_POST['save']) && $_POST['save'] == '1') {
	$_POST['config']['upnpav'] = $_POST['config']['svctype'] == 'upnpav' ? '1' : '0';
	$_POST['config']['openhome'] = $_POST['config']['svctype'] == 'openhome' ? '1' : '0';

	foreach ($_POST['config'] as $key => $value) {
		if ($key != 'qobuzpass') {
			chkValue($value);
		}
		sqlUpdate('cfg_upnp', $dbh, $key, $value);
		if ($value != '') {
			sysCmd("sed -i '/" . $key . ' =' . '/c\\' . $key . ' = ' . $value . "' /etc/upmpdcli.conf");
		}
		else {
			sysCmd("sed -i '/" . $key . ' =' . '/c\\' . '#' . $key . ' = ' . $value . "' /etc/upmpdcli.conf");
		}
	}
	$notify = $_SESSION['upnpsvc'] == '1' ?
		array('title' => NOTIFY_TITLE_INFO, 'msg' => NAME_UPNP . NOTIFY_MSG_SVC_RESTARTED) :
		array('title' => '', 'msg' => '');
	submitJob('upnpsvc', '', $notify['title'], $notify['msg']);
}

phpSession('close');

$result = sqlRead('cfg_upnp', $dbh);
$cfgUPNP = array();

foreach ($result as $row) {
	$cfgUPNP[$row['param']] = $row['value'];
}

// General
$_select['svctype'] .= "<option value=\"upnpav\" " . (($cfgUPNP['upnpav'] == '1') ? "selected" : "") . ">UPnP-A/V</option>\n";
$_select['svctype'] .= "<option value=\"openhome\" " . (($cfgUPNP['openhome'] == '1') ? "selected" : "") . ">OpenHome</option>\n";
$_select['checkcontentformat'] .= "<option value=\"1\" " . (($cfgUPNP['checkcontentformat'] == '1') ? "selected" : "") . ">Yes</option>\n";
$_select['checkcontentformat'] .= "<option value=\"0\" " . (($cfgUPNP['checkcontentformat'] == '0') ? "selected" : "") . ">No</option>\n";

// Music services
$_select['qobuzuser'] = $cfgUPNP['qobuzuser'];
$_select['qobuzpass'] = $cfgUPNP['qobuzpass'];
$_show_hide_password_icon_hide = empty($cfgUPNP['qobuzpass']) ? '' : 'hide';
$_select['qobuzformatid'] .= "<option value=\"5\" " . (($cfgUPNP['qobuzformatid'] == '5') ? "selected" : "") . ">MP3 320K</option>\n";
$_select['qobuzformatid'] .= "<option value=\"6\" " . (($cfgUPNP['qobuzformatid'] == '6') ? "selected" : "") . ">FLAC</option>\n";
$_select['qobuzformatid'] .= "<option value=\"7\" " . (($cfgUPNP['qobuzformatid'] == '7') ? "selected" : "") . ">FLAC 24/96K</option>\n";
$_select['qobuzformatid'] .= "<option value=\"27\" " . (($cfgUPNP['qobuzformatid'] == '27') ? "selected" : "") . ">Highest resolution available</option>\n";

waitWorker('upp-config');

$tpl = "upp-config.html";
$section = basename(__FILE__, '.php');
storeBackLink($section, $tpl);

include('header.php');
eval("echoTemplate(\"" . getTemplate("templates/$tpl") . "\");");
include('footer.php');
