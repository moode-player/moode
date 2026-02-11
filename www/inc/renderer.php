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
	sysCmd('systemctl start nqptp');

	// Verbose logging
	if ($_SESSION['debuglog'] == '1') {
		$logging = '-v';
		$logFile = SHAIRPORT_SYNC_LOG;
	} else {
		$logging = '';
		$logFile = '/dev/null';
	}

	// Output device
	// NOTE: Specifying Loopback instead of _audioout when Multiroom TX is On greatly reduces audio glitches
	$device = $_SESSION['audioout'] == 'Local' ? ($_SESSION['multiroom_tx'] == 'On' ? 'plughw:Loopback,0' : '_audioout') : 'btstream';

	// NOTE: Interpolation param handled in config file
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
}
function stopAirPlay() {
	$maxRetries = 3;
	// Metadata componenta
	for ($i = 0; $i < $maxRetries; $i++) {
		sysCmd('killall -s 9 aplmeta-reader.sh');
		sysCmd('killall -s 9 shairport-sync-metadata-reader');
		sysCmd('killall -s 9 aplmeta.py');
		sysCmd('killall -s 9 cat');
		// Use the 15 char names from PS -A for some of these
		$result1 = sysCmd('pgrep -cx aplmeta-reader.')[0]; // aplmeta-reader. sh
		$result2 = sysCmd('pgrep -cx shairport-sync-')[0]; // shairport-sync- metadata-reader
		$result3 = sysCmd('pgrep -cx aplmeta.py')[0];
		$result4 = sysCmd("pgrep -cfax \"cat /tmp/shairport-sync-metadata\"")[0];

		// DEBUG
		/*workerLog('result1=' . $result1);
		workerLog('result2=' . $result2);
		workerLog('result3=' . $result3);
		workerLog('result4=' . $result4);
		}*/

		if ($result1 == 0 && $result2 == 0 && $result3 == 0 && $result4 == 0) {
			break;
		}
		workerLog('worker: Retry ' . ($i + 1) . ' stopping AirPlay metadata reader');
		sleep(1);
	}
	// Shairport-sync
	for ($i = 0; $i < $maxRetries; $i++) {
		sysCmd('killall -s 9 shairport-sync');
		$result = sysCmd('pgrep -cx shairport-sync')[0];
		if ($result == 0) {
			break;
		}
		workerLog('worker: Retry ' . ($i + 1) . ' stopping AirPlay');
		sleep(1);
	}
	// Nqptp
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
function getSpotifyFormat() {
	$metadata = explode('~~~', file_get_contents(SPOTMETA_FILE));
	return $metadata[5];
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
function getDeezerInfo($item) {
	switch ($item) {
		case 'stream_format':
			$itemIndex = 5;
			break;
		case 'decoded_to':
			$itemIndex = 6;
			break;
	}
	$metadata = explode('~~~', file_get_contents(DEEZMETA_FILE));
	return $metadata[$itemIndex];
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

// Sendspin
function startSendspin() {
	sysCmd('mpc stop');
	sysCmd('systemctl start sendspin');
}
function stopSendspin() {
	sysCmd('systemctl stop sendspin');
}
function cfgSendspin() {
	// example, minimal 'required config' for /root/.config/sendspin/settings-daemon.json
	//
	// {
 	//  "static_delay_ms": 0.0,
  	//  "name": "moOde Sendspin Audio Client",
  	//  "client_id": "moode-sendspin",
  	//  "audio_device": "snd_rpi_hifiberry_dacplus",
  	//  "use_mpris": false
	// }

	$filename = '/root/.config/sendspin/settings-daemon.json';
	$data = sqlRead(table:'cfg_sendspin', dbh: sqlConnect(), format: 'json');

	file_put_contents($filename, $data);
}

// Stop all renderers
function stopAllRenderers() {
	$renderers = array(
		'btsvc'		 => 'stopBluetooth',
		'airplaysvc' => 'stopAirPlay',
		'spotifysvc' => 'stopSpotify',
		'deezersvc'  => 'stopDeezer',
		'upnpsvc'	 => 'stopUPnP',
		'slsvc'		 => 'stopSqueezeLite',
		'pasvc'		 => 'stopPlexamp',
		'rbsvc'		 => 'stopRoonBridge',
		'sendspinsvc' => 'stopSendspin'
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
