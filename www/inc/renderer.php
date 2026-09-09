<?php
/*
 * SPDX-License-Identifier: GPL-3.0-or-later
 * Copyright 2014 The moOde audio player project / Tim Curtis
*/

require_once __DIR__ . '/common.php';
require_once __DIR__ . '/cdsp.php';
require_once __DIR__ . '/multiroom.php';
require_once __DIR__ . '/session.php';
require_once __DIR__ . '/sql.php';

// Bluetooth
// Write the pairing agent's capability file. On ('1') -> DisplayYesNo (the agent asks
// the user to confirm the pairing code); off -> NoInputNoOutput (Just Works). Read by
// bt-agent.service via EnvironmentFile; the caller restarts bt-agent to apply it.
function applyBtPairingConfirm($confirm) {
	$capability = $confirm == '1' ? 'DisplayYesNo' : 'NoInputNoOutput';
	file_put_contents(BT_AGENT_ENV, 'BT_AGENT_CAPABILITY=' . $capability . "\n");
}
function startBluetooth() {
	sysCmd('systemctl start hciuart');
	sysCmd('systemctl start bluetooth');

	// Check for first run (no MAC addr yet) fail
	$result = sysCmd('systemctl status bluetooth | grep -i failed');
	//DEBUG:workerLog(print_r($result, true));
	if (!empty($result)) {
		// Stop/start
		stopBluetooth();
		sysCmd('systemctl start bluetooth');
	}

	// Check for successful daemon startup
	$result = sysCmd('pgrep bluetoothd');
	if (empty($result)) {
		$status = 'ERROR: Bluetooth startup failed';
	} else {
		// Check for controller MAC address
		$result = sysCmd('ls /var/lib/bluetooth');
		if (empty($result)) {
			$status = 'ERROR: Bluetooth MAC address not found';
		} else {
			// All good
			sysCmd('systemctl start bt-agent');
			sysCmd('systemctl start bluealsa');
			sysCmd('/var/www/util/blu-control.sh -i');
			$status = 'started';
		}
	}

	return $status;
}
function stopBluetooth() {
	sysCmd('systemctl stop bt-agent');
	sysCmd('systemctl stop bluealsa');
	sysCmd('systemctl stop bluetooth');
	sysCmd('killall -s 9 bluealsa-aplay');
}

// AirPlay
function startAirPlay() {
	if ($_SESSION['airplaysvc_type'] == '2') {
		sysCmd('systemctl start nqptp');
	}

	// Verbose logging
	if ($_SESSION['debuglog'] == '1') {
		$logging = '-v';
		$logFile = SHAIRPORT_SYNC_LOG;
	} else {
		$logging = '';
		$logFile = '/dev/null';
	}

	// Output device
	// TODO: Still necessary with AirPlay 5
	// NOTE: Specifying Loopback instead of _audioout when Multiroom TX is On greatly reduces audio glitches
	$device = $_SESSION['audioout'] == 'Local' ? ($_SESSION['multiroom_tx'] == 'On' ? 'plughw:Loopback,0' : '_audioout') : 'btstream';

	// NOTE: All other params are in /etc/shairport-sync.conf
	$cmd = '/usr/bin/shairport-sync ' . $logging .
		' -a "' . $_SESSION['airplayname'] . '" ' .
		'-- -d ' . $device . ' > ' . $logFile . ' 2>&1 &';

	// Start AirPlay receiver
	debugLog('startAirPlay(): (' . $cmd . ')');
	sysCmd($cmd);

	// Wait until metadata pipe is ready
	$maxRetries = 3;
	for ($i = 0; $i < $maxRetries; $i++) {
		$result = sysCmd('ls -1 /tmp/shairport-sync-metadata | wc -l')[0];
		//debugLog('result=' . $result);

		if ($result != 0) {
			break;
		}
		debugLog('startAirPlay(): Retry ' . ($i + 1) . ' waiting for metadata pipe');
		sleep(1);
	}

	// Start AirPlay metadata reader
	$cmd = '/var/www/daemon/aplmeta-reader.sh > /dev/null 2>&1 &';
	debugLog('startAirPlay(): (' . $cmd . ')');
	sysCmd($cmd);

	// Truncate metadata file
	sysCmd('truncate ' . APLMETA_CACHE_FILE . ' --size 0');
}
function stopAirPlay() {
	$maxRetries = 3;
	// Stop metadata reader components
	for ($i = 0; $i < $maxRetries; $i++) {
		sysCmd('pkill -f -9  aplmeta-reader.sh');
		sysCmd('pkill -f -9  shairport-sync-metadata-reader');
		sysCmd('pkill -f -9  aplmeta.py');
		sysCmd('pkill -f -9  cat');
		// Use the 15 char names from PS -A for some of these
		$result1 = sysCmd('pgrep -cx "aplmeta-reader."')[0]; // aplmeta.sh
		$result2 = sysCmd('pgrep -cx "shairport-sync-"')[0]; // shairport-sync-metadata-reader
		$result3 = sysCmd('pgrep -cx "aplmeta.py"')[0];
		$result4 = sysCmd('pgrep -cfax "cat /tmp/shairport-sync-metadata"')[0];

		// DEBUG
		/*workerLog('result1=' . $result1);
		workerLog('result2=' . $result2);
		workerLog('result3=' . $result3);
		workerLog('result4=' . $result4);
		}*/

		if ($result1 == 0 && $result2 == 0 && $result3 == 0 && $result4 == 0) {
			break;
		}
		workerLog('worker: Retry ' . ($i + 1) . ' stopping AirPlay metadata reader components');
		sleep(1);
	}
	// Stop shairport-sync
	for ($i = 0; $i < $maxRetries; $i++) {
		$result = sysCmd('pkill -c -f -9 "[s]hairport-sync"');
		//workerLog(print_r($result, true));

		$result = sysCmd('pgrep -c -f "[L]C_ALL=C /usr/bin/shairport-sync"')[0];
		//workerLog(print_r($result, true));
		if ($result == 0) {
			break;
		}
		workerLog('worker: Retry ' . ($i + 1) . ' stopping AirPlay (shairport-sync)');
		sleep(1);
	}
	// Stop nqptp
	sysCmd('systemctl stop nqptp');

	// Local
	sysCmd('/var/www/util/vol.sh -restore');
	if (CamillaDSP::isMPD2CamillaDSPVolSyncEnabled()) {
		sysCmd('systemctl restart mpd2cdspvolume');
	}
	// Multiroom receivers
	if ($_SESSION['multiroom_tx'] == "On" ) {
		updReceiverVol('-restore');
	}

	phpSession('write', 'aplactive', '0');
	$GLOBALS['aplactive'] = '0';
	sendFECmd('aplactive0');
}
function getAirPlayVersion($type = 'full') {
	$version = sysCmd('shairport-sync -V | cut -f 1 -d "-"')[0];
	// $type: 'full' or 'major'
	return ($type == 'full' ? $version : substr($version, 0, 1));
}
function isAirPlayInstalled() {
	$installedVersion = sysCmd('dpkg-query --showformat=\'${Version}\n\' --show shairport-sync | grep moode')[0];
	return (empty($installedVersion) ? false : true);
}
function isAirPlayUpgradable() {
	// Ex: 5.0.2-1moode1
	$installedVersion = sysCmd('dpkg-query --showformat=\'${Version}\n\' --show shairport-sync | grep moode')[0];
	$availableVersion = sqlQuery("SELECT version FROM cfg_plugin WHERE component='renderer' AND type='airplay'", sqlConnect())[0]['version'];
	return ($installedVersion == $availableVersion ? false : true);
}

// Spotify Connect
function startSpotify() {
	$result = sqlRead('cfg_spotify', sqlConnect());
	$cfgSpotify = array();
	foreach ($result as $row) {
		$cfgSpotify[$row['param']] = $row['value'];
	}

	// Output device
	$device = $_SESSION['audioout'] == 'Local' ? '_audioout' : 'btstream';

	// Options
	$dither = empty($cfgSpotify['dither']) ? '' : ' --dither ' . $cfgSpotify['dither'];
	$normalization = $cfgSpotify['volume_normalization'] == 'Yes' ?
		' --enable-volume-normalisation ' .
		' --normalisation-method ' . $cfgSpotify['normalization_method'] .
		' --normalisation-gain-type ' . $cfgSpotify['normalization_gain_type'] .
		' --normalisation-pregain ' .  $cfgSpotify['normalization_pregain'] .
		' --normalisation-threshold ' . $cfgSpotify['normalization_threshold'] .
		' --normalisation-attack ' . $cfgSpotify['normalization_attack'] .
		' --normalisation-release ' . $cfgSpotify['normalization_release'] .
		' --normalisation-knee ' . $cfgSpotify['normalization_knee']
		: '';

	$autoplay = $cfgSpotify['autoplay'] == 'Yes' ? ' --autoplay on' : '';
	$zeroconf = $cfgSpotify['zeroconf'] == 'manual' ? ' --zeroconf-port ' . $cfgSpotify['zeroconf_port'] : '';

	// Logging
	$logging = $_SESSION['debuglog'] == '1' ? ' -v > ' . LIBRESPOT_LOG : ' > /dev/null';

 	// NOTE: We use --disable-audio-cache because the audio file cache eats disk space.
	$cmd = 'librespot' .
		' --name "' . $_SESSION['spotifyname'] . '"' .
		' --bitrate ' . $cfgSpotify['bitrate'] .
		' --format ' . $cfgSpotify['format'] .
		$dither .
		' --mixer softvol' .
		' --initial-volume ' . $cfgSpotify['initial_volume'] .
		' --volume-ctrl ' . $cfgSpotify['volume_curve'] .
		' --volume-range ' . $cfgSpotify['volume_range'] .
		$normalization .
		$autoplay .
		$zeroconf .
		' --cache /var/local/www/spotify_cache --disable-audio-cache --backend alsa --device "' . $device . '"' .
		' --onevent /var/local/www/commandw/spotevent.sh' .
		$logging . ' 2>&1 &';

	debugLog('startSpotify(): (' . $cmd . ')');
	sysCmd($cmd);

	// Truncate metadata file
	sysCmd('truncate ' . SPOTMETA_CACHE_FILE . ' --size 0');
}
function stopSpotify() {
	sysCmd('killall -s9 librespot');

	// Local
	sysCmd('/var/www/util/vol.sh -restore');
	if (CamillaDSP::isMPD2CamillaDSPVolSyncEnabled()) {
		sysCmd('systemctl restart mpd2cdspvolume');
	}
	// Multiroom receivers
	if ($_SESSION['multiroom_tx'] == "On" ) {
		updReceiverVol('-restore');
	}

	phpSession('write', 'spotactive', '0');
	$GLOBALS['spotactive'] = '0';
	sendFECmd('spotactive0');
}
function isSpotifyInstalled() {
	$installedVersion = sysCmd('dpkg-query --showformat=\'${Version}\n\' --show librespot | grep moode')[0];
	return (empty($installedVersion) ? false : true);
}
function isSpotifyUpgradable() {
	// Ex: 0.8.0-1moode1
	$installedVersion = sysCmd('dpkg-query --showformat=\'${Version}\n\' --show librespot | grep moode')[0];
	$availableVersion = sqlQuery("SELECT version FROM cfg_plugin WHERE component='renderer' AND type='spotify-connect'", sqlConnect())[0]['version'];
	return ($installedVersion == $availableVersion ? false : true);
}

// Qobuz Connect
// Copyright 2026 @PhilipVinc qbz fork of moode / https://github.com/PhilipVinc/moode
function startQobuz() {
	// Logging
	$logging = $_SESSION['debuglog'] == '1' ? ' > ' . QBZD_LOG : ' > /dev/null';
	// Settings
	$result = sqlRead('cfg_qobuz', sqlConnect());
	$cfgQobuz = array();
	foreach ($result as $row) {
		$cfgQobuz[$row['param']] = $row['value'];
	}

	$device = $_SESSION['audioout'] == 'Local' ? '_audioout' : 'btstream';
	$volMode = $_SESSION['mpdmixer'] == 'none' ? 'locked' : 'software';

	// QConnect
	sysCmd('qbzd qconnect enable');
	sysCmd('qbzd settings set qconnect.device_name "' . $_SESSION['qobuzname'] . '"');
	sysCmd('qbzd settings set qconnect.pairing on');
	sysCmd('qbzd settings set qconnect.volume_mode ' . $volMode);
	sysCmd('qbzd settings set qconnect.initial_volume ' . $cfgQobuz['initial_volume']);
	// Playback
	sysCmd('qbzd settings set playback.quality ' . $cfgQobuz['quality']);
	sysCmd('qbzd settings set playback.persist_session false');
	sysCmd('qbzd settings set playback.resume_playback_position false');
	sysCmd('qbzd settings set playback.mpris false');
	// Audio output
	sysCmd('qbzd settings set audio.device "' . $device . '"');
	sysCmd('qbzd settings set audio.backend alsa');
	sysCmd('qbzd settings set audio.alsa_plugin hw');
	sysCmd('qbzd settings set audio.alsa_hardware_volume false');	// Moode does support hardware volume for renderers
	sysCmd('qbzd settings set audio.alsa_mixer_device auto');
	// Audio other
	sysCmd('qbzd settings set audio.stream_buffer_seconds ' . $cfgQobuz['stream_buffer_seconds']);
	sysCmd('qbzd settings set audio.normalization_enabled ' . $cfgQobuz['normalization_enabled']);
	sysCmd('qbzd settings set audio.allow_quality_fallback true');
	sysCmd('qbzd settings set audio.gapless_enabled ' . $cfgQobuz['gapless_enabled']);
	sysCmd('qbzd settings set audio.quality_fallback_behavior ' . $cfgQobuz['quality_fallback_behaviour']);
	sysCmd('qbzd settings set audio.streaming_only ' . $cfgQobuz['streaming_only']);
	sysCmd('qbzd settings set audio.stream_first_track ' . $cfgQobuz['stream_first_track']);
	sysCmd('qbzd settings set audio.cache_to_disk ' . $cfgQobuz['cache_to_disk']);
	sysCmd('qbzd settings set audio.memory_cache_mb ' . $cfgQobuz['memory_cache_mb']);
	sysCmd('qbzd settings set audio.alsa_buffer_ms ' . $cfgQobuz['alsa_buffer_ms']);
	// Event script
	sysCmd('qbzd settings set hooks.script /var/local/www/commandw/qbzevent.sh');

	// Start the daemon
	$cmd = 'qbzd run' . $logging . ' 2>&1 &';
	debugLog('startQobuz(): (' . $cmd . ')');
	sysCmd($cmd);

	// Wait for the control API to come up (up to 5 secs)
	for ($i = 0; $i < 10; $i++) {
		usleep(500000);
		$result = sysCmd('curl -s -o /dev/null -w "%{http_code}" --max-time 2 http://127.0.0.1:8182/api/status');
		if (!empty($result) && $result[0] == '200') {
			break;
		}
	}
}
function stopQobuz() {
	// Graceful first: on SIGTERM qbzd leaves the Qobuz Connect session, so the
	// cloud drops this renderer. SIGKILL skips that, leaving a zombie renderer
	// registered mid-playback — the next handoff rejoins that same session, the
	// cloud replays the stale "playing <old track> at <old position>" state,
	// and the app ends up showing 0:00 with nothing playing. SIGKILL stays as
	// the fallback so a wedged daemon still releases the audio device.
	sysCmd('killall qbzd 2> /dev/null');
	for ($i = 0; $i < 15; $i++) {
		if (empty(sysCmd('pgrep -x qbzd'))) {
			break;
		}
		usleep(200000);
	}
	sysCmd('killall -s9 qbzd 2> /dev/null');

	// Local
	sysCmd('/var/www/util/vol.sh -restore');
	if (CamillaDSP::isMPD2CamillaDSPVolSyncEnabled()) {
		sysCmd('systemctl restart mpd2cdspvolume');
	}
	// Multiroom receivers
	if ($_SESSION['multiroom_tx'] == "On" ) {
		updReceiverVol('-restore');
	}

	phpSession('write', 'qbzactive', '0');
	$GLOBALS['qbzactive'] = '0';
	sendFECmd('qbzactive0');
}
function isQobuzInstalled() {
	$result = sysCmd('which qbzd');
	return empty($result) ? false : true;
}
function isQobuzUpgradable() {
	$installedVersion = sysCmd('dpkg-query --showformat=\'${Version}\n\' --show qbzd | grep moode')[0];
	$availableVersion = sqlQuery("SELECT version FROM cfg_plugin WHERE component='renderer' AND type='qobuz-connect'", sqlConnect())[0]['version'];
	return ($installedVersion == $availableVersion ? false : true);
}
function qbzdVersion() {
	return sysCmd('qbzd --version | awk \'{print $2}\'')[0];
}

// Download a diagnostic log bundle.
// - Qbzd output and event logs
// - Qbzd settings
// - Moode setings
// - Audio configuration

// Streams and exits, so it must run before any output. Nothing is written to
// the card that is not removed again on the way out.
function downloadQbzLogs($dbh) {
	$bundleName = 'qobuz-connect-' . $_SESSION['hostname'] . '-' . date('Ymd-His');
	$workDir = '/tmp/' . $bundleName;
	$archive = $workDir . '.tar.gz';

	// The report carries what is NOT already a file: the daemon's own view, the
	// settings moOde pushed, and the audio chain those settings landed in.
	$report = array();
	$report[] = 'QOBUZ CONNECT DIAGNOSTICS';
	$report[] = 'Debug logging    ' . ($_SESSION['debuglog'] == '1' ? 'On' : 'Off: ALERT, Captured log data is not valid');
	$report[] = 'Collected        ' . date('Y-m-d H:i:s T');
	$report[] = 'Pi model         ' . $_SESSION['hdwrrev'];
	$report[] = 'Moode release    ' . getMoodeRel('verbose');
	$report[] = 'Qbzd version     ' . qbzdVersion();
	$report[] = 'Renderer         ' . ($_SESSION['qobuzsvc'] == '1' ? 'On' : 'Off');
	$report[] = 'Audio output     ' . $_SESSION['audioout'];
	$report[] = 'ALSA device      ' . $_SESSION['alsa_output_mode'];
	$report[] = 'DSP              CamillaDSP=' . $_SESSION['camilladsp'] .
		' GraphicEQ=' . $_SESSION['alsaequal'] .
		' ParametricEQ=' . $_SESSION['eqfa12p'] .
		' PolarityInv=' . $_SESSION['invert_polarity'] .
		' Crossfeed=' . $_SESSION['crossfeed'];
	$report[] = 'Other            PeppyALSA=' . ($_SESSION['enable_peppyalsa'] == '1' ? 'On' : 'Off');

	$report[] = '';
	$report[] = '--- Moode settings (cfg_qobuz) ---';
	foreach (sqlRead('cfg_qobuz', $dbh) as $row) {
		$report[] = sprintf('%-26s %s', $row['param'], $row['value']);
	}

	foreach (array(
		'--- Qbzd status ---' => 'qbzd status',
		'--- Qbzd settings ---' => 'qbzd settings show',
		'--- Audio devices ---' => 'aplay -l',
		'--- Memory ---' => 'free -m',
		'--- Qbzd process ---' => 'ps -eo pid,rss,etime,comm | grep -E "qbzd|RSS"',
	) as $heading => $cmd) {
		$report[] = '';
		$report[] = $heading;
		$report = array_merge($report, sysCmd($cmd));
	}

	// $workDir is made by the web user so PHP can write the report into it; the
	// logs are root-owned, so they come across through sysCmd.
	sysCmd('rm -rf ' . $workDir . ' ' . $archive);
	@mkdir($workDir, 0755, true);
	file_put_contents($workDir . '/report.txt', implode("\n", $report) . "\n");
	foreach (array(QBZD_LOG, QBZEVENT_LOG, MOODE_LOG) as $log) {
		sysCmd('cp -f ' . $log . ' ' . $workDir . '/');
	}
	sysCmd('chmod -R a+r ' . $workDir);
	sysCmd('tar -czf ' . $archive . ' -C /tmp ' . $bundleName);
	sysCmd('rm -rf ' . $workDir);

	if (!file_exists($archive)) {
		// Notify while the session is still open, or the message is lost.
		$_SESSION['notify']['title'] = NOTIFY_TITLE_ALERT;
		$_SESSION['notify']['msg'] = 'Could not collect the diagnostics. Download cancelled.';
		phpSession('close');
	} else {
		phpSession('close');

		header('Content-Description: File Transfer');
		header('Content-Type: application/gzip');
		header('Content-Transfer-Encoding: binary');
		header('Content-Disposition: attachment; filename="' . $bundleName . '.tar.gz"');
		header('Content-Length: ' . filesize($archive));
		header('Pragma: no-cache');
		header('Expires: 0');
		readfile($archive);
		sysCmd('rm -f ' . $archive);
		exit();
	}
}

// UPnP
function startUPnP() {
	sysCmd('systemctl start upmpdcli');
}
function stopUPnP() {
	sysCmd('systemctl stop upmpdcli');
}

// Squeezelite
function startSqueezeLite() {
	sysCmd('mpc stop');

	if ($_SESSION['alsavolume'] != 'none') {
		sysCmd('/var/www/util/sysutil.sh set-alsavol ' . '"' . $_SESSION['amixname']  . '" ' . $_SESSION['alsavolume_max']);
	}

	sysCmd('systemctl start squeezelite');
}
function stopSqueezeLite() {
	sysCmd('systemctl stop squeezelite');

	sysCmd('/var/www/util/vol.sh -restore');
	if (CamillaDSP::isMPD2CamillaDSPVolSyncEnabled()) {
		sysCmd('systemctl restart mpd2cdspvolume');
	}

	phpSession('write', 'slactive', '0');
	$GLOBALS['slactive'] = '0';
	sendFECmd('slactive0');
}
function cfgSqueezelite() {
	$result = sqlRead('cfg_sl', sqlConnect());

	foreach ($result as $row) {
		$data .= $row['param'] . '=' . $row['value'] . "\n";
	}

	$fh = fopen('/etc/squeezelite.conf', 'w');
	fwrite($fh, $data);
	fclose($fh);
}

// Plexamp
function startPlexamp() {
	sysCmd('mpc stop');
	sysCmd('systemctl start plexamp');
}
function stopPlexamp() {
	sysCmd('systemctl stop plexamp');
	sysCmd('/var/www/util/vol.sh -restore');
	phpSession('write', 'paactive', '0');
	$GLOBALS['paactive'] = '0';
	sendFECmd('paactive0');
}

// RoonBridge
function startRoonBridge() {
	sysCmd('mpc stop');
	sysCmd('systemctl start roonbridge');
}
function stopRoonBridge() {
	sysCmd('systemctl stop roonbridge');
	sysCmd('/var/www/util/vol.sh -restore');
	phpSession('write', 'rbactive', '0');
	$GLOBALS['rbactive'] = '0';
	sendFECmd('rbactive0');
}

// Stop all renderers
function stopAllRenderers() {
	$renderers = array(
		'btsvc'		 => 'stopBluetooth',
		'airplaysvc' => 'stopAirPlay',
		'spotifysvc' => 'stopSpotify',
		'qobuzsvc'	 => 'stopQobuz',
		'upnpsvc'	 => 'stopUPnP',
		'slsvc'		 => 'stopSqueezeLite',
		'pasvc'		 => 'stopPlexamp',
		'rbsvc'		 => 'stopRoonBridge'
	);

	// Watchdog (so monitored renderers are not auto restarted)
	sysCmd('killall -s9 watchdog.sh');
	workerLog('stopAllRenderers(): watchdog stopped');

	// Renderers
	foreach ($renderers as $svc => $stopFunction) {
		if ($_SESSION[$svc] == '1') {
			$stopFunction();
			workerLog('stopAllRenderers(): ' . $svc . ' stopped');
		}
	}
}
