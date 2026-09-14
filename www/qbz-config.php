<?php
/*
 * SPDX-License-Identifier: GPL-3.0-or-later
 * Copyright 2014 The moOde audio player project / Tim Curtis
 * Copyright 2026 @PhilipVinc qbz fork of moode / https://github.com/PhilipVinc/moode
*/

require_once __DIR__ . '/inc/common.php';
require_once __DIR__ . '/inc/renderer.php';
require_once __DIR__ . '/inc/session.php';
require_once __DIR__ . '/inc/sql.php';

$dbh = sqlConnect();
phpSession('open');

// Download diagnostic logs
if (isset($_POST['download_qbz_logs']) && $_POST['download_qbz_logs'] == '1') {
	// NOTE: Exists script after downloading
	downloadQbzLogs($dbh);
}

// Save settings
if (isset($_POST['save']) && $_POST['save'] == '1') {
	$msg = '';
	foreach ($_POST['config'] as $key => $value) {
		chkValue($key, $value);
		sqlUpdate('cfg_qobuz', $dbh, $key, $value);
	}
	if ($_SESSION['qobuzsvc'] == '1') {
		$notify = array('title' => NOTIFY_TITLE_INFO, 'msg' => NAME_QOBUZ . NOTIFY_MSG_SVC_RESTARTED);
		submitJob('qobuzsvc', '', $notify['title'], $notify['msg']);
	}
}

phpSession('close');

$result = sqlRead('cfg_qobuz', $dbh);
$cfgQobuz = array();
foreach ($result as $row) {
	$cfgQobuz[$row['param']] = $row['value'];
}
// Self-heal a cfg_qobuz that predates a param. Without the row, the generic
// UPDATE in the save handler above silently no-ops and every read of the param
// here is an undefined key, so a player upgraded in place would show a blank
// control that never takes. Any param added to this page must be added here too.
$qobuzDefaults = array(
	'quality' => 'hires_plus',
	'stream_buffer_seconds' => '2',
	'dac_keepalive_ms' => 'off',
	'initial_volume' => '10',
	'normalization_enabled' => 'false',
	'normalization_target_lufs' => '-14',
	'stream_first_track' => 'true',
	'streaming_only' => 'false',
	'gapless_enabled' => 'true',
	'cache_to_disk' => 'false',
	'memory_cache_mb' => 'auto',
	'disk_cache_mb' => 'auto',
	'alsa_buffer_ms' => 'auto'
);
foreach ($qobuzDefaults as $param => $default) {
	if (!isset($cfgQobuz[$param])) {
		sqlInsert('cfg_qobuz', $dbh, "'" . $param . "', '" . $default . "'");
		$cfgQobuz[$param] = $default;
	}
}

// GENERAL
// Quality
$_select['quality'] .= "<option value=\"mp3\" " . (($cfgQobuz['quality'] == 'mp3') ? "selected" : "") . ">MP3 320 kbps</option>\n";
$_select['quality'] .= "<option value=\"cd\" " . (($cfgQobuz['quality'] == 'cd') ? "selected" : "") . ">CD 16/44.1K</option>\n";
$_select['quality'] .= "<option value=\"hires\" " . (($cfgQobuz['quality'] == 'hires') ? "selected" : "") . ">Hi-Res 24/96K</option>\n";
$_select['quality'] .= "<option value=\"hires_plus\" " . (($cfgQobuz['quality'] == 'hires_plus') ? "selected" : "") . ">Hi-Res 24/192K (Default)</option>\n";
// Audio buffer
$_select['stream_buffer_seconds'] .= "<option value=\"2\" " . (($cfgQobuz['stream_buffer_seconds'] == '2') ? "selected" : "") . ">2 seconds (Default)</option>\n";
$_select['stream_buffer_seconds'] .= "<option value=\"5\" " . (($cfgQobuz['stream_buffer_seconds'] == '5') ? "selected" : "") . ">5 seconds</option>\n";
$_select['stream_buffer_seconds'] .= "<option value=\"10\" " . (($cfgQobuz['stream_buffer_seconds'] == '10') ? "selected" : "") . ">10 seconds</option>\n";

// VOLUME
// Initial volume. "Off" leaves the player where it is; the daemon takes that
// literally and applies nothing on connect.
$_select['initial_volume'] .= "<option value=\"off\" " . (($cfgQobuz['initial_volume'] == 'off') ? "selected" : "") . ">Off</option>\n";
$_select['initial_volume'] .= "<option value=\"10\" " . (($cfgQobuz['initial_volume'] == '10') ? "selected" : "") . ">10%</option>\n";
$_select['initial_volume'] .= "<option value=\"20\" " . (($cfgQobuz['initial_volume'] == '20') ? "selected" : "") . ">20%</option>\n";
$_select['initial_volume'] .= "<option value=\"30\" " . (($cfgQobuz['initial_volume'] == '30') ? "selected" : "") . ">30%</option>\n";
$_select['initial_volume'] .= "<option value=\"40\" " . (($cfgQobuz['initial_volume'] == '40') ? "selected" : "") . ">40%</option>\n";
$_select['initial_volume'] .= "<option value=\"50\" " . (($cfgQobuz['initial_volume'] == '50') ? "selected" : "") . ">50%</option>\n";
$_select['initial_volume'] .= "<option value=\"60\" " . (($cfgQobuz['initial_volume'] == '60') ? "selected" : "") . ">60%</option>\n";
$_select['initial_volume'] .= "<option value=\"70\" " . (($cfgQobuz['initial_volume'] == '70') ? "selected" : "") . ">70%</option>\n";
$_select['initial_volume'] .= "<option value=\"80\" " . (($cfgQobuz['initial_volume'] == '80') ? "selected" : "") . ">80%</option>\n";
$_select['initial_volume'] .= "<option value=\"90\" " . (($cfgQobuz['initial_volume'] == '90') ? "selected" : "") . ">90%</option>\n";
$_select['initial_volume'] .= "<option value=\"100\" " . (($cfgQobuz['initial_volume'] == '100') ? "selected" : "") . ">100%</option>\n";
// Volume normalization
$_select['normalization_enabled'] .= "<option value=\"true\" " . (($cfgQobuz['normalization_enabled'] == 'true') ? "selected" : "") . ">Yes</option>\n";
$_select['normalization_enabled'] .= "<option value=\"false\" "  . (($cfgQobuz['normalization_enabled'] == 'false')  ? "selected" : "") . ">No (Default)</option>\n";
// Normalization target. Only reachable when normalization is on, so it follows
// that control the way Gapless follows the Track cache.
$_normalization_disabled = $cfgQobuz['normalization_enabled'] == 'true' ? '' : 'disabled';
$_normalization_hint = '';
if ($_normalization_disabled == '') {
	$_select['normalization_target_lufs'] .= "<option value=\"-14\" " . (($cfgQobuz['normalization_target_lufs'] == '-14') ? "selected" : "") . ">-14 LUFS, streaming (Default)</option>\n";
	$_select['normalization_target_lufs'] .= "<option value=\"-18\" " . (($cfgQobuz['normalization_target_lufs'] == '-18') ? "selected" : "") . ">-18 LUFS, audiophile</option>\n";
	$_select['normalization_target_lufs'] .= "<option value=\"-23\" " . (($cfgQobuz['normalization_target_lufs'] == '-23') ? "selected" : "") . ">-23 LUFS, broadcast</option>\n";
} else {
	$_select['normalization_target_lufs'] .= "<option value=\"" . $cfgQobuz['normalization_target_lufs'] . "\" selected>-14 LUFS, streaming (Default)</option>\n";
	$_normalization_hint = '<span class="config-help-static">The target applies when Volume normalization is set to Yes.</span>';
}
// DAC keep-alive
$_select['dac_keepalive_ms'] .= "<option value=\"off\" " . (($cfgQobuz['dac_keepalive_ms'] == 'off') ? "selected" : "") . ">Off (Default)</option>\n";
$_select['dac_keepalive_ms'] .= "<option value=\"50\" " . (($cfgQobuz['dac_keepalive_ms'] == '50') ? "selected" : "") . ">50 ms</option>\n";
$_select['dac_keepalive_ms'] .= "<option value=\"100\" " . (($cfgQobuz['dac_keepalive_ms'] == '100') ? "selected" : "") . ">100 ms</option>\n";

// PLAYBACK
// Start playback
$_select['stream_first_track'] .= "<option value=\"true\" " . (($cfgQobuz['stream_first_track'] == 'true') ? "selected" : "") . ">When buffered (Default)</option>\n";
$_select['stream_first_track'] .= "<option value=\"false\" "  . (($cfgQobuz['stream_first_track'] == 'false')  ? "selected" : "") . ">After downloaded</option>\n";
// Track cache
$_select['streaming_only'] .= "<option value=\"true\" " . (($cfgQobuz['streaming_only'] == 'true') ? "selected" : "") . ">Disable</option>\n";
$_select['streaming_only'] .= "<option value=\"false\" "  . (($cfgQobuz['streaming_only'] == 'false')  ? "selected" : "") . ">Enable (Default)</option>\n";
/* Auto set this during startup based on physical memory
// Cache location
$_select['cache_to_disk'] .= "<option value=\"false\" " . (($cfgQobuz['cache_to_disk'] == 'false') ? "selected" : "") . ">Memory (Default)</option>\n";
$_select['cache_to_disk'] .= "<option value=\"true\" " . (($cfgQobuz['cache_to_disk'] == 'true') ? "selected" : "") . ">Disk</option>\n";
*/
// Cache size (for memory cache)
$_select['memory_cache_mb'] .= "<option value=\"auto\" " . (($cfgQobuz['memory_cache_mb'] == 'auto') ? "selected" : "") . ">Auto (Default)</option>\n";
$_select['memory_cache_mb'] .= "<option value=\"512\" " . (($cfgQobuz['memory_cache_mb'] == '512') ? "selected" : "") . ">512 MB</option>\n";
$_select['memory_cache_mb'] .= "<option value=\"1024\" " . (($cfgQobuz['memory_cache_mb'] == '1024') ? "selected" : "") . ">1 GB</option>\n";
$_select['memory_cache_mb'] .= "<option value=\"2048\" " . (($cfgQobuz['memory_cache_mb'] == '2048') ? "selected" : "") . ">2 GB</option>\n";
// Cache size (for disk cache). Only meaningful on the boards that use the disk,
// which is the same threshold worker.php sets Cache location by.
$_disk_cache_disabled = $cfgQobuz['cache_to_disk'] == 'true' ? '' : 'disabled';
$_disk_cache_hint = '';
if ($_disk_cache_disabled == '') {
	$_select['disk_cache_mb'] .= "<option value=\"auto\" " . (($cfgQobuz['disk_cache_mb'] == 'auto') ? "selected" : "") . ">Auto, 800 MB (Default)</option>\n";
	$_select['disk_cache_mb'] .= "<option value=\"400\" " . (($cfgQobuz['disk_cache_mb'] == '400') ? "selected" : "") . ">400 MB</option>\n";
	$_select['disk_cache_mb'] .= "<option value=\"2000\" " . (($cfgQobuz['disk_cache_mb'] == '2000') ? "selected" : "") . ">2 GB</option>\n";
	$_select['disk_cache_mb'] .= "<option value=\"8000\" " . (($cfgQobuz['disk_cache_mb'] == '8000') ? "selected" : "") . ">8 GB</option>\n";
} else {
	$_select['disk_cache_mb'] .= "<option value=\"" . $cfgQobuz['disk_cache_mb'] . "\" selected>Auto, 800 MB (Default)</option>\n";
	$_disk_cache_hint = '<span class="config-help-static">This player caches tracks in memory, so nothing is written to the card.</span>';
}
// Gapless playback. The track cache is the only thing that rules it out. Memory
// does not: a player too small to hold a second track in memory hands the next
// one to the renderer as a file on the card instead, and moOde already caches
// to the card on every board under QOBUZ_DISK_CACHE_MAX_MB. So every player
// gets gapless as long as it is caching somewhere.
$_gapless_disabled = $cfgQobuz['streaming_only'] == 'false' ? '' : 'disabled';
$_gapless_hint = '';
if ($_gapless_disabled != '') {
	$_gapless_hint = '<span class="config-help-static">Gapless playback requires the Track cache to be enabled.</span>';
}
if ($_gapless_disabled == '') {
	$_select['gapless_enabled'] .= "<option value=\"true\" " . (($cfgQobuz['gapless_enabled'] == 'true') ? "selected" : "") . ">Yes (Default)</option>\n";
	$_select['gapless_enabled'] .= "<option value=\"false\" "  . (($cfgQobuz['gapless_enabled'] == 'false')  ? "selected" : "") . ">No</option>\n";
} else {
	$_select['gapless_enabled'] .= "<option value=\"false\" selected>No</option>\n";
}
// ALSA buffer length (ms)
$_select['alsa_buffer_ms'] .= "<option value=\"auto\" " . (($cfgQobuz['alsa_buffer_ms'] == 'auto') ? "selected" : "") . ">Auto (Default)</option>\n";
$_select['alsa_buffer_ms'] .= "<option value=\"250\" " . (($cfgQobuz['alsa_buffer_ms'] == '250') ? "selected" : "") . ">250 ms</option>\n";
$_select['alsa_buffer_ms'] .= "<option value=\"500\" " . (($cfgQobuz['alsa_buffer_ms'] == '500') ? "selected" : "") . ">500 ms</option>\n";
$_select['alsa_buffer_ms'] .= "<option value=\"1000\" " . (($cfgQobuz['alsa_buffer_ms'] == '1000') ? "selected" : "") . ">1 sec</option>\n";
$_select['alsa_buffer_ms'] .= "<option value=\"2000\" " . (($cfgQobuz['alsa_buffer_ms'] == '2000') ? "selected" : "") . ">2 sec</option>\n";

waitWorker('qbz_config');

$tpl = "qbz-config.html";
$section = basename(__FILE__, '.php');
storeBackLink($section, $tpl);

include('header.php');
eval("echoTemplate(\"" . getTemplate("templates/$tpl") . "\");");
include("footer.min.php");
