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

// Deezer Connect
function startDeezer() {
	$result = sqlRead('cfg_deezer', sqlConnect());
	$cfgDeezer = array();
	foreach ($result as $row) {
		$cfgDeezer[$row['param']] = $row['value'];
	}

	// Output device
	$device = $_SESSION['audioout'] == 'Local' ? '_audioout' : 'btstream'; // <= 0.18.0

	// Options
	$normalization = $cfgDeezer['normalize_volume'] == 'Yes' ? ' --normalize-volume' : '';
	$interruption = $cfgDeezer['no_interruption'] == 'Yes' ? ' --no_interruption' : '';
	$ramCache = $cfgDeezer['max_ram'] == '0' ? '' : ' --max-ram ' . $cfgDeezer['max_ram'];
	$ditherBits = empty($cfgDeezer['dither_bits']) ? '' : ' --dither-bits ' . $cfgDeezer['dither_bits'];
	$rate = '';
	$format = $cfgDeezer['format'];
	// Logging
	$logging = $_SESSION['debuglog'] == '1' ? ' -v > ' . PLEEZER_LOG : ' > /dev/null';

 	// Command
	$cmd = 'pleezer' .
		' --name "' . $_SESSION['deezername'] . '"' .
		' --device-type "' . 'web' . '"' .
		' --device "' . 'ALSA|' . $device . '|' . $rate . '|' . $format . '"' .
		' --initial-volume "' . $cfgDeezer['initial_volume'] . '"' .
		' --secrets "' . DEEZ_CREDENTIALS_FILE . '"' .
		$normalization .
		$interruption .
		$ramCache .
		$ditherBits .
		' --noise-shaping ' . $cfgDeezer['noise_shaping'] .
		' --hook /var/local/www/commandw/deezevent.sh' .
		$logging . ' 2>&1 &';

	debugLog('startDeezer(): (' . $cmd . ')');
	sysCmd($cmd);
}
function stopDeezer() {
	sysCmd('killall -s9 pleezer');

	// Local
	sysCmd('/var/www/util/vol.sh -restore');
	if (CamillaDSP::isMPD2CamillaDSPVolSyncEnabled()) {
		sysCmd('systemctl restart mpd2cdspvolume');
	}
	// Multiroom receivers
	if ($_SESSION['multiroom_tx'] == "On" ) {
		updReceiverVol('-restore');
	}

	phpSession('write', 'deezactive', '0');
	$GLOBALS['deezactive'] = '0';
	sendFECmd('deezactive0');
}
function updateDeezCredentials($email, $password) {
	// Truncate the file
	$fh = fopen(DEEZ_CREDENTIALS_FILE, 'w');
	ftruncate($fh, 0);
	// Write new contents
	$data .= "email = \"" . $email . "\"\n";
	$data .= "password = \"" . $password . "\"\n";
	fwrite($fh, $data);
	fclose($fh);
}

// Hz as a compact kHz string: 96000 -> "96", 44100 -> "44.1". formatRate() in
// music-library.php is a lookup table that returns NULL for a rate it does not
// list, and pulling that include in for one number is not worth it.
function qobuzKhz($hz) {
	return rtrim(rtrim(number_format($hz / 1000, 1, '.', ''), '0'), '.');
}

// $_SESSION lookup with a default. Several of the DSP flags below do not exist
// as cfg_system rows on every moOde release (10.3.2 has no crossfeed, eqfa12p,
// invert_polarity or enable_peppyalsa), and an undefined key compared against
// 'Off' reads as ON — which would block a direct handoff for a feature that is
// not even installed. Defaults are therefore all "feature absent", except the
// ALSA output mode, where an unknown value must NOT be taken for Direct.
function qobuzSess($key, $default) {
	return isset($_SESSION[$key]) && $_SESSION[$key] !== '' ? $_SESSION[$key] : $default;
}

// Whether Qobuz playback should be handed straight to the DAC, and why not when
// it should not. Shared by startQobuz(), which applies it, and qbz-config.php /
// audioinfo.php, which display it — a second copy of this predicate would drift
// and make the UI describe something the daemon is not doing.
//
// The rule behind "auto": moOde's _audioout is a bare hardware passthrough only
// when nothing is inserted into it (see updAudioOutAndBtOutConfs() in audio.php).
// In exactly that case qbzd can open the DAC itself and lose nothing, which is
// what lets a track's own rate reach the hardware instead of whatever rate the
// shared chain happens to be running at.
function qobuzDirectRouting() {
	$cfgQobuz = array();
	foreach (sqlRead('cfg_qobuz', sqlConnect()) as $row) {
		$cfgQobuz[$row['param']] = $row['value'];
	}
	$mode = isset($cfgQobuz['output_mode']) ? $cfgQobuz['output_mode'] : 'auto';
	$device = 'hw:' . qobuzSess('cardnum', '0') . ',0';
	$alsaMode = qobuzSess('alsa_output_mode', 'plughw');

	if ($mode == 'software') {
		return array('direct' => false, 'device' => '', 'reason' => 'Output routing is set to Software');
	}

	// Hard blockers: there is no hw: device to hand over, whatever was asked for.
	$hard = '';
	if (qobuzSess('audioout', 'Local') != 'Local') {
		$hard = 'audio output is Bluetooth';
	} else if (qobuzSess('multiroom_tx', 'Off') == 'On') {
		$hard = 'Multiroom sender is on';
	} else if ($alsaMode == 'iec958') {
		$hard = 'ALSA output mode is S/PDIF';
	}
	if ($hard != '') {
		return array('direct' => false, 'device' => '', 'reason' => $hard);
	}

	if ($mode == 'direct') {
		return array('direct' => true, 'device' => $device, 'reason' => '');
	}

	// Auto: take the DAC only when moOde's own chain is empty. Each of these
	// means the chain has something in it that a direct handoff would bypass.
	$soft = '';
	if ($alsaMode != 'hw') {
		$modeName = isset(ALSA_OUTPUT_MODE_NAME[$alsaMode]) ? ALSA_OUTPUT_MODE_NAME[$alsaMode] : $alsaMode;
		$soft = 'ALSA output mode is ' . $modeName . ', not Direct';
	} else if (qobuzSess('alsaequal', 'Off') != 'Off') {
		$soft = 'Graphic EQ is on';
	} else if (qobuzSess('camilladsp', 'off') != 'off') {
		$soft = 'CamillaDSP is on';
	} else if (qobuzSess('crossfeed', 'Off') != 'Off') {
		$soft = 'Crossfeed is on';
	} else if (qobuzSess('eqfa12p', 'Off') != 'Off') {
		$soft = 'Parametric EQ is on';
	} else if (qobuzSess('invert_polarity', '0') != '0') {
		$soft = 'Polarity inversion is on';
	} else if (qobuzSess('peppy_display', '0') == '1' || qobuzSess('enable_peppyalsa', '0') == '1') {
		$soft = 'PeppyALSA is on';
	}
	if ($soft != '') {
		return array('direct' => false, 'device' => '', 'reason' => $soft);
	}

	return array('direct' => true, 'device' => $device, 'reason' => '');
}

// Qobuz Connect
function startQobuz() {
	$result = sqlRead('cfg_qobuz', sqlConnect());
	$cfgQobuz = array();
	foreach ($result as $row) {
		$cfgQobuz[$row['param']] = $row['value'];
	}

	// Output device: ALSA hint names defined in /etc/alsa/conf.d/qbzd-devices.conf
	$device = $_SESSION['audioout'] == 'Local' ? 'Moode Audio Output' : 'Moode Bluetooth Stream';

	// Logging
	$logging = $_SESSION['debuglog'] == '1' ? ' > ' . QBZD_LOG : ' > /dev/null';

	// Apply settings BEFORE the daemon starts: the pairing listener, its mDNS
	// device name, and the qconnect startup mode are read once at boot. The
	// settings CLI writes the stores directly (creating them if needed); its
	// running-daemon nudge is a harmless no-op while qbzd is down.
	// Output routing. These three settings are coupled: the bit-perfect path is
	// only reached with backend=alsa AND a device id is_hw_device() accepts, so
	// they are always written together and never exposed separately.
	$routing = qobuzDirectRouting();
	if ($routing['direct']) {
		sysCmd('qbzd settings set audio.backend alsa');
		sysCmd('qbzd settings set audio.device "' . $routing['device'] . '"');
		sysCmd('qbzd settings set audio.alsa_plugin hw');
	} else {
		// Explicit, not just "leave it": a box that once went direct must come
		// back to the shared chain when DSP is switched on, or the DSP silently
		// stops applying to Qobuz.
		sysCmd('qbzd settings set audio.backend system');
		sysCmd('qbzd settings set audio.device "' . $device . '"');
	}
	sysCmd('qbzd settings set playback.quality ' . $cfgQobuz['quality']);
	// Gapless needs the NEXT track in the cache — the prefetch is a no-op in
	// streaming-only mode — so it cannot work on a box that does not cache,
	// whatever the Gapless setting says.
	$cacheTracks = (isset($cfgQobuz['track_cache']) ? $cfgQobuz['track_cache'] : 'No') == 'Yes';
	sysCmd('qbzd settings set audio.gapless_enabled ' .
		(($cacheTracks && $cfgQobuz['gapless'] == 'Yes') ? 'true' : 'false'));
	sysCmd('qbzd settings set audio.streaming_only ' . ($cacheTracks ? 'false' : 'true'));
	sysCmd('qbzd settings set audio.stream_first_track ' .
		((isset($cfgQobuz['stream_first']) ? $cfgQobuz['stream_first'] : 'Yes') == 'Yes' ? 'true' : 'false'));
	sysCmd('qbzd settings set audio.normalization_enabled ' . ($cfgQobuz['normalize_volume'] == 'Yes' ? 'true' : 'false'));
	sysCmd('qbzd settings set playback.mpris false');
	sysCmd('qbzd settings set qconnect.device_name "' . $_SESSION['qobuzname'] . '"');
	sysCmd('qbzd settings set qconnect.pairing ' . (($cfgQobuz['pairing'] ?? 'Yes') == 'Yes' ? 'on' : 'off'));
	sysCmd('qbzd settings set audio.stream_buffer_seconds ' . ($cfgQobuz['buffer_seconds'] ?? '2'));
	// Volume. Hardware volume needs a direct handoff AND a DAC with a mixer
	// control; anywhere else audio.alsa_hardware_volume silently does nothing,
	// so resolve it here rather than trusting the stored value. 'auto' takes
	// hardware whenever it is available, because software volume in direct mode
	// scales every sample and throws away the bit-perfection just gained.
	$hwVolume = $routing['direct'] && qobuzSess('alsavolume', 'none') != 'none';
	$volumeMode = isset($cfgQobuz['volume_mode']) ? $cfgQobuz['volume_mode'] : 'auto';
	if ($volumeMode == 'auto') {
		$volumeMode = $hwVolume ? 'hardware' : 'software';
	} else if ($volumeMode == 'hardware' && !$hwVolume) {
		$volumeMode = 'software';
	}
	sysCmd('qbzd settings set qconnect.volume_mode ' . ($volumeMode == 'locked' ? 'locked' : 'software'));
	sysCmd('qbzd settings set audio.alsa_hardware_volume ' . ($volumeMode == 'hardware' ? 'true' : 'false'));
	// Non-interactive quality fallback: the stock value is "ask", which cannot
	// work on a headless box — there is nobody to answer, so a track the DAC
	// cannot do at full rate has no defined outcome. Play it at a supported
	// rate instead of failing.
	sysCmd('qbzd settings set audio.allow_quality_fallback true');
	sysCmd('qbzd settings set audio.quality_fallback_behavior ' .
		((isset($cfgQobuz['quality_fallback']) ? $cfgQobuz['quality_fallback'] : 'fallback') == 'skip' ?
			'always_skip' : 'always_fallback'));
	// Do not restore a local queue on start. As a Connect renderer the queue
	// belongs to the controlling app; a restored one is invisible to it and
	// surfaced as the daemon spontaneously streaming a track nobody asked for
	// after a restart.
	sysCmd('qbzd settings set playback.persist_session false');
	sysCmd('qbzd settings set playback.resume_playback_position false');
	sysCmd('qbzd qconnect enable');

	// QBZD_HOOK: qbzd forks the script once per daemon event (QBZ_* env vars)
	$cmd = 'QBZD_HOOK=/var/local/www/commandw/qbzevent.sh qbzd run' . $logging . ' 2>&1 &';
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
// Version of the installed qbzd. A moOde build stamps its build id into the
// binary (2.0.2.moode7), so ask the binary first — it describes whatever is
// actually on disk, even if it was replaced by hand. Older binaries report only
// their Cargo version (a bare 2.0.2), and for those the id the installer
// recorded at install time is the better answer.
function qbzdVersion() {
	$result = sysCmd('qbzd --version | awk \'{print $2}\'');
	$reported = empty($result[0]) ? '' : $result[0];
	if (strpos($reported, 'moode') !== false) {
		return $reported;
	}
	if (file_exists(QBZD_BUILD_FILE)) {
		$build = trim(file_get_contents(QBZD_BUILD_FILE));
		if ($build != '') {
			return $build;
		}
	}
	return $reported == '' ? 'unknown' : $reported;
}
// True when the installed qbzd is a moOde fork build rather than an upstream
// release — the Qobuz Connect pairing work is not upstream yet.
function isQbzdForkBuild() {
	return strpos(qbzdVersion(), 'moode') !== false;
}
function isQobuzInstalled() {
	$result = sysCmd('which qbzd');
	return empty($result) ? false : true;
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
		'deezersvc'  => 'stopDeezer',
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
