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
	// Local pairing and a fixed account login are mutually exclusive in this
	// UI: turning pairing on drops the login (and any in-flight browser login)
	// so the renderer restart below comes up account-less. The single-slot job
	// queue is taken by the qobuzsvc restart, so log out synchronously here.
	if (isset($_POST['config']['pairing']) && $_POST['config']['pairing'] == 'Yes') {
		$prevPairing = sqlQuery("SELECT value FROM cfg_qobuz WHERE param='pairing'", $dbh);
		if (!empty($prevPairing[0]['value']) && $prevPairing[0]['value'] == 'No') {
			sysCmd('pkill -f qbzd-login 2> /dev/null');
			sysCmd('qbzd logout');
		}
	}
	foreach ($_POST['config'] as $key => $value) {
		chkValue($key, $value);
		sqlUpdate('cfg_qobuz', $dbh, $key, $value);
	}
	$notify = $_SESSION['qobuzsvc'] == '1' ?
		array('title' => NOTIFY_TITLE_INFO, 'msg' => NAME_QOBUZ . NOTIFY_MSG_SVC_RESTARTED) :
		array('title' => '', 'msg' => '');
	submitJob('qobuzsvc', '', $notify['title'], $notify['msg']);
}
if (isset($_POST['qobuz_login']) && $_POST['qobuz_login'] == '1') {
	// The button is disabled while pairing is on; guard the POST as well.
	$pairing = sqlQuery("SELECT value FROM cfg_qobuz WHERE param='pairing'", $dbh);
	if (empty($pairing[0]['value']) || $pairing[0]['value'] != 'Yes') {
		submitJob('qobuz_login', '', NOTIFY_TITLE_INFO, 'Login started, refresh this screen to view the login link');
	}
}
if (isset($_POST['qobuz_logout']) && $_POST['qobuz_logout'] == '1') {
	submitJob('qobuz_logout', '', NOTIFY_TITLE_INFO, 'Logged out from Qobuz');
}

phpSession('close');

$result = sqlRead('cfg_qobuz', $dbh);
$cfgQobuz = array();

foreach ($result as $row) {
	$cfgQobuz[$row['param']] = $row['value'];
}
// Self-heal a cfg_qobuz that predates a param: without the row, the generic
// UPDATE in the save handler would silently no-op.
$qobuzDefaults = array('pairing' => 'Yes', 'buffer_seconds' => '2', 'volume_mode' => 'auto',
	'output_mode' => 'auto', 'stream_first' => 'Yes', 'track_cache' => 'Yes',
	'quality_fallback' => 'fallback');
foreach ($qobuzDefaults as $param => $default) {
	if (!isset($cfgQobuz[$param])) {
		sqlInsert('cfg_qobuz', $dbh, "'" . $param . "', '" . $default . "'");
		$cfgQobuz[$param] = $default;
	}
}

// One control-API read for everything on this screen that needs the daemon.
$qbzStatus = array();
if ($_SESSION['qobuzsvc'] == '1') {
	$result = sysCmd('curl -s --max-time 2 http://127.0.0.1:8182/api/status');
	$decoded = json_decode(implode('', $result), true);
	if (is_array($decoded)) {
		$qbzStatus = $decoded;
	}
}

// What Output routing actually resolves to on this box. Shown because "Auto"
// is otherwise unanswerable without reading ALSA configs by hand, and the
// reason is the useful half when it does not resolve to Direct.
$routing = qobuzDirectRouting();
$_qobuz_routing_resolves = $routing['direct'] ?
	'Direct (' . $routing['device'] . ')' :
	'Software' . ($routing['reason'] == '' ? '' : ' &mdash; ' . $routing['reason']);

// Hardware volume needs both a direct handoff and a DAC that has a volume
// control; say which one is missing rather than hiding the option.
$hwVolumeReason = '';
if (!$routing['direct']) {
	$hwVolumeReason = 'n/a &mdash; using Software routing';
} else if (qobuzSess('alsavolume', 'none') == 'none') {
	$hwVolumeReason = 'n/a &mdash; this DAC has no hardware volume control';
}

// Local pairing supplies the account (the casting app hands over its own
// session), so the fixed-account Login is gated while it is on.
$_qobuz_login_disabled = '';
$_qobuz_login_hint = '';
if ($cfgQobuz['pairing'] == 'Yes') {
	$_qobuz_login_disabled = 'disabled';
	$_qobuz_login_hint = '<span class="config-help-static">No login needed: Local pairing is on, ' .
		'so the Qobuz app hands this player its own account when casting. ' .
		'To use a fixed account instead, set Local pairing to No and Save.</span>';
}

$_select['quality'] .= "<option value=\"mp3\" " . (($cfgQobuz['quality'] == 'mp3') ? "selected" : "") . ">MP3 320 kbps</option>\n";
$_select['quality'] .= "<option value=\"cd\" " . (($cfgQobuz['quality'] == 'cd') ? "selected" : "") . ">CD 16 bit / 44.1 kHz</option>\n";
$_select['quality'] .= "<option value=\"hires\" " . (($cfgQobuz['quality'] == 'hires') ? "selected" : "") . ">Hi-Res 24 bit / 96 kHz</option>\n";
$_select['quality'] .= "<option value=\"hires_plus\" " . (($cfgQobuz['quality'] == 'hires_plus') ? "selected" : "") . ">Hi-Res 24 bit / 192 kHz (Default)</option>\n";
$_select['pairing'] .= "<option value=\"Yes\" " . (($cfgQobuz['pairing'] == 'Yes') ? "selected" : "") . ">Yes (Default)</option>\n";
$_select['pairing'] .= "<option value=\"No\" "  . (($cfgQobuz['pairing'] == 'No')  ? "selected" : "") . ">No</option>\n";
// The gapless prefetch is a no-op in streaming-only mode, so with caching off
// gapless cannot happen at all. Lock the control and show the value actually in
// force rather than leaving a stored "Yes" on screen that does nothing. A
// disabled select posts nothing, so the stored preference survives untouched
// and comes back as soon as caching is turned on again.
$_gapless_disabled = $cfgQobuz['track_cache'] == 'Yes' ? '' : 'disabled';
$_gapless_hint = '';
if ($_gapless_disabled == '') {
	$_select['gapless'] .= "<option value=\"Yes\" " . (($cfgQobuz['gapless'] == 'Yes') ? "selected" : "") . ">Yes (Default)</option>\n";
	$_select['gapless'] .= "<option value=\"No\" "  . (($cfgQobuz['gapless'] == 'No')  ? "selected" : "") . ">No</option>\n";
} else {
	$_select['gapless'] .= "<option value=\"No\" selected>No</option>\n";
	$_gapless_hint = '<span class="config-help-static">Not available: Track cache is set to ' .
		'stream only, and gapless needs the next track cached ahead of time.</span>';
}
$_select['normalize_volume'] .= "<option value=\"Yes\" " . (($cfgQobuz['normalize_volume'] == 'Yes') ? "selected" : "") . ">Yes</option>\n";
$_select['normalize_volume'] .= "<option value=\"No\" "  . (($cfgQobuz['normalize_volume'] == 'No')  ? "selected" : "") . ">No (Default)</option>\n";
foreach (array('2' => '2 seconds (Default)', '5' => '5 seconds', '10' => '10 seconds') as $secs => $label) {
	$_select['buffer_seconds'] .= "<option value=\"" . $secs . "\" " .
		(($cfgQobuz['buffer_seconds'] == $secs) ? "selected" : "") . ">" . $label . "</option>\n";
}
$_select['output_mode'] .= "<option value=\"auto\" "     . (($cfgQobuz['output_mode'] == 'auto')     ? "selected" : "") . ">ALSA output mode (Auto) (Default)</option>\n";
$_select['output_mode'] .= "<option value=\"software\" " . (($cfgQobuz['output_mode'] == 'software') ? "selected" : "") . ">Software</option>\n";
$_select['output_mode'] .= "<option value=\"direct\" "   . (($cfgQobuz['output_mode'] == 'direct')   ? "selected" : "") . ">Direct</option>\n";

$_select['volume_mode'] .= "<option value=\"auto\" " . (($cfgQobuz['volume_mode'] == 'auto') ? "selected" : "") . ">Auto (Default)</option>\n";
$_select['volume_mode'] .= "<option value=\"hardware\" " . (($cfgQobuz['volume_mode'] == 'hardware') ? "selected" : "") .
	($hwVolumeReason == '' ? "" : " disabled") . ">DAC hardware volume" .
	($hwVolumeReason == '' ? "" : " (" . $hwVolumeReason . ")") . "</option>\n";
$_select['volume_mode'] .= "<option value=\"software\" " . (($cfgQobuz['volume_mode'] == 'software') ? "selected" : "") . ">Software</option>\n";
$_select['volume_mode'] .= "<option value=\"locked\" "   . (($cfgQobuz['volume_mode'] == 'locked')   ? "selected" : "") . ">Locked at 100%</option>\n";

$_select['stream_first'] .= "<option value=\"Yes\" " . (($cfgQobuz['stream_first'] == 'Yes') ? "selected" : "") . ">As soon as buffered (Default)</option>\n";
$_select['stream_first'] .= "<option value=\"No\" "  . (($cfgQobuz['stream_first'] == 'No')  ? "selected" : "") . ">After the full track downloads</option>\n";

$_select['track_cache'] .= "<option value=\"Yes\" " . (($cfgQobuz['track_cache'] == 'Yes') ? "selected" : "") . ">Cache tracks on disk (Default)</option>\n";
$_select['track_cache'] .= "<option value=\"No\" "  . (($cfgQobuz['track_cache'] == 'No')  ? "selected" : "") . ">Stream only, do not cache</option>\n";

$_select['quality_fallback'] .= "<option value=\"fallback\" " . (($cfgQobuz['quality_fallback'] == 'fallback') ? "selected" : "") . ">Play at a lower quality (Default)</option>\n";
$_select['quality_fallback'] .= "<option value=\"skip\" "     . (($cfgQobuz['quality_fallback'] == 'skip')     ? "selected" : "") . ">Skip the track</option>\n";

// Account: login status is read from the qbzd control API (only available when the service is on)
$_qobuz_login_hide = 'hide';
$_qobuz_logout_hide = 'hide';
$_qobuz_login_url_msg = '';
if ($_SESSION['qobuzsvc'] == '1') {
	$status = $qbzStatus;
	if (isset($status['auth']['state']) && $status['auth']['state'] != 'needs_auth') {
		$_qobuz_logout_hide = '';
		$_qobuz_account_status = 'Logged in' .
			(empty($status['auth']['subscription']) ? '' : ' (' . $status['auth']['subscription'] . ')');
	} else {
		$_qobuz_login_hide = '';
		$_qobuz_account_status = 'Not logged in';
		// A login in progress prints its URL to the login log
		$loginLog = file_exists(QOBUZ_LOGIN_LOG) ? file_get_contents(QOBUZ_LOGIN_LOG) : '';
		if (preg_match('#https?://\S+#', $loginLog, $matches) === 1) {
			$_qobuz_login_url_msg = '<span class="config-help-static">' .
				'<a class="target-blank-link" href="' . $matches[0] . '" target="_blank">' . $matches[0] . '</a><br>' .
				'Open this link in your browser to complete the Qobuz login, then refresh this screen.</span>';
		}
	}
} else {
	$_qobuz_account_status = 'Turn the renderer on in Renderer Config to log in.';
}

waitWorker('qbz_config');

$tpl = "qbz-config.html";
$section = basename(__FILE__, '.php');
storeBackLink($section, $tpl);

include('header.php');
eval("echoTemplate(\"" . getTemplate("templates/$tpl") . "\");");
include('footer.php');
