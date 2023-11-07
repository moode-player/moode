<?php
/**
 * moOde audio player (C) 2014 Tim Curtis
 * http://moodeaudio.org
 *
 * This Program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 3, or (at your option)
 * any later version.
 *
 * This Program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program.  If not, see <http://www.gnu.org/licenses/>.
 *
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

	$result = sysCmd('pgrep bluetoothd');
	if (empty($result)) {
		$status = 'Error: Unable to start Bluetooth';
	} else {
		$result = sysCmd('ls /var/lib/bluetooth');
		if (empty($result)) {
			$status = 'Error: No MAC address found for Bluetooth controller';
		} else {
			sysCmd('systemctl start bluealsa');
			sysCmd('/var/www/util/blu-control.sh -i');
			$status = 'started';
		}
	}

	return $status;
}

function startAirPlay() {
	if (getAirPlayProtocolVer() == '2') {
		sysCmd('systemctl start nqptp');
	}

	// Verbose logging
	if ($_SESSION['debuglog'] == '1') {
		$logging = '-vv';
		$logFile = '/var/log/shairport-sync.log';
	}
	else {
		$logging = '';
		$logFile = '/dev/null';
	}

	// Output device
	// NOTE: Specifying Loopback instead of _audioout when Multiroom TX is On greatly reduces audio glitches
	$device = $_SESSION['audioout'] == 'Local' ? ($_SESSION['multiroom_tx'] == 'On' ? 'plughw:Loopback,0' : '_audioout') : 'btstream';

	// Interpolation param handled in config file
	$cmd = '/usr/bin/shairport-sync ' . $logging .
		' -a "' . $_SESSION['airplayname'] . '" ' .
		'-- -d ' . $device . ' > ' . $logFile . ' 2>&1 &';

	debugLog('startAirPlay(): (' . $cmd . ')');
	sysCmd($cmd);
}

function stopAirPlay() {
	sysCmd('killall shairport-sync');

	if (getAirPlayProtocolVer() == '2') {
		sysCmd('systemctl stop nqptp');
	}

	// Local
	if (isMPD2CamillaDSPVolSyncEnabled()) {
		$dbh = sqlConnect();
		$result = sqlQuery("SELECT value FROM cfg_system WHERE param='volknob_mpd'", $dbh);
		sqlQuery("UPDATE cfg_system SET value='" . $result[0]['value'] . "' WHERE param='volknob'", $dbh);
		sysCmd('/var/www/vol.sh -restore');
		sysCmd('systemctl restart mpd2cdspvolume');
	} else {
		sysCmd('/var/www/vol.sh -restore');
	}

	// Multiroom receivers
	if ($_SESSION['multiroom_tx'] == "On" ) {
		updReceiverVol('-restore');
	}

	// Reset to inactive
	phpSession('write', 'aplactive', '0');
	$GLOBALS['aplactive'] = '0';
	sendEngCmd('aplactive0');
}

function getAirPlayProtocolVer() {
	return empty(sysCmd('shairport-sync -V | grep "AirPlay2"')) ? '1' : '2';
}

function startSpotify() {
	$result = sqlRead('cfg_spotify', sqlConnect());
	$cfgSpotify = array();
	foreach ($result as $row) {
		$cfgSpotify[$row['param']] = $row['value'];
	}

	// Output device
	// NOTE: Specifying Loopback instead of _audioout when Multiroom TX is On greatly reduces audio glitches
	$device = $_SESSION['audioout'] == 'Local' ? ($_SESSION['multiroom_tx'] == 'On' ? 'plughw:Loopback,0' : '_audioout') : 'btstream';

	// Access point port
	// NOTE: This is to force AP fallback by specifying a random port number other than 80, 443 or 4070.
	$ap_port = ' --ap-port 13561 ';

	// Options
	$dither = empty($cfgSpotify['dither']) ? '' : ' --dither ' . $cfgSpotify['dither'];
	$initial_volume = $cfgSpotify['initial_volume'] == "-1" ? '' : ' --initial-volume ' . $cfgSpotify['initial_volume'];
	$volume_normalization = $cfgSpotify['volume_normalization'] == 'Yes' ?
		' --enable-volume-normalisation ' .
		' --normalisation-method ' . $cfgSpotify['normalization_method'] .
		' --normalisation-gain-type ' . $cfgSpotify['normalization_gain_type'] .
		' --normalisation-pregain ' .  $cfgSpotify['normalization_pregain'] .
		' --normalisation-threshold ' . $cfgSpotify['normalization_threshold'] .
		' --normalisation-attack ' . $cfgSpotify['normalization_attack'] .
		' --normalisation-release ' . $cfgSpotify['normalization_release'] .
		' --normalisation-knee ' . $cfgSpotify['normalization_knee']
		: '';
	$autoplay = $cfgSpotify['autoplay'] == 'Yes' ? ' --autoplay' : '';

 	// NOTE: We use --disable-audio-cache because the audio file cache eats disk space.
	$cmd = 'librespot' .
		' --name "' . $_SESSION['spotifyname'] . '"' .
		' --bitrate ' . $cfgSpotify['bitrate'] .
		' --format ' . $cfgSpotify['format'] .
		$ap_port .
		$dither .
		' --mixer softvol' .
		$initial_volume .
		' --volume-ctrl ' . $cfgSpotify['volume_curve'] .
		' --volume-range ' . $cfgSpotify['volume_range'] .
		$volume_normalization .
		$autoplay .
		' --cache /var/local/www/spotify_cache --disable-audio-cache --backend alsa --device "' . $device . '"' .
		' --onevent /var/local/www/commandw/spotevent.sh' .
		' > /dev/null 2>&1 &';
		//' -v > /var/log/moode_librespot.log 2>&1 &'; // For debug

	debugLog('startSpotify(): (' . $cmd . ')');
	sysCmd($cmd);
}

function stopSpotify() {
	sysCmd('killall librespot');

	// Local
	if (isMPD2CamillaDSPVolSyncEnabled()) {
		$dbh = sqlConnect();
		$result = sqlQuery("SELECT value FROM cfg_system WHERE param='volknob_mpd'", $dbh);
		sqlQuery("UPDATE cfg_system SET value='" . $result[0]['value'] . "' WHERE param='volknob'", $dbh);
		sysCmd('/var/www/vol.sh -restore');
		sysCmd('systemctl restart mpd2cdspvolume');
	} else {
		sysCmd('/var/www/vol.sh -restore');
	}

	// Multiroom receivers
	if ($_SESSION['multiroom_tx'] == "On" ) {
		updReceiverVol('-restore');
	}

	// Reset to inactive
	phpSession('write', 'spotactive', '0');
	$GLOBALS['spotactive'] = '0';
	sendEngCmd('spotactive0');
}

function startSqueezeLite() {
	sysCmd('mpc stop');

	if ($_SESSION['alsavolume'] != 'none') {
		sysCmd('/var/www/util/sysutil.sh set-alsavol ' . '"' . $_SESSION['amixname']  . '" ' . $_SESSION['alsavolume_max']);
	}
	sysCmd('systemctl start squeezelite');
}

function stopSqueezeLite() {
	sysCmd('systemctl stop squeezelite');
	sysCmd('/var/www/vol.sh -restore');
	// Reset to inactive
	phpSession('write', 'slactive', '0');
	$GLOBALS['slactive'] = '0';
	sendEngCmd('slactive0');
}

function cfgSqueezelite() {
	// Update AUDIODEVICE param
	$dbh = sqlConnect();
	$alsaOutput = $_SESSION['alsa_output_mode'] . ':' . $_SESSION['cardnum'] . ',0';
	sqlUpdate('cfg_sl', $dbh, 'AUDIODEVICE', $alsaOutput);

	// Load settings
	$result = sqlRead('cfg_sl', $dbh);

	// Generate config file output
	foreach ($result as $row) {
		$data .= $row['param'] . '=' . $row['value'] . "\n";
	}

	$fh = fopen('/etc/squeezelite.conf', 'w');
	fwrite($fh, $data);
	fclose($fh);
}

function startRoonBridge() {
	sysCmd('mpc stop');
	sysCmd('systemctl start roonbridge');
}

function stopRoonBridge () {
	sysCmd('systemctl stop roonbridge');
	sysCmd('/var/www/vol.sh -restore');
	// Reset to inactive
	phpSession('write', 'rbactive', '0');
	$GLOBALS['rbactive'] = '0';
	sendEngCmd('rbactive0');
}

function startUPnP() {
	sysCmd('systemctl start upmpdcli');
}
