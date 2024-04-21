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
require_once __DIR__ . '/session.php';
require_once __DIR__ . '/sql.php';

function startMultiroomSender() {
	$params = sqlRead('cfg_multiroom', sqlConnect());
	foreach ($params as $row) {
	    $cfgMultiroom[$row['param']] = $row['value'];
	}

	$outputDevice = 'trx_send';

	$cmd = 'trx-tx' .
		' -d ' . $outputDevice .
		' -h ' . $cfgMultiroom['tx_host'] .
		' -p ' . $cfgMultiroom['tx_port'] .
		' -m ' . $cfgMultiroom['tx_bfr'] .
		' -f ' . $cfgMultiroom['tx_frame_size'] .
		' -R ' . $cfgMultiroom['tx_rtprio'] .
		' -D /tmp/trx-txpid  >/dev/null';
	$result = shell_exec($cmd);
	debugLog($cmd);
}

function stopMultiroomSender() {
	sysCmd('killall trx-tx');
}

# TODO: Test using _audioout.conf instead of $_SESSION['cardnum'] . ',0
function startMultiroomReceiver() {
	$params = sqlRead('cfg_multiroom', sqlConnect());
	foreach ($params as $row) {
	    $cfgMultiroom[$row['param']] = $row['value'];
	}

	$outputDevice = $cfgMultiroom['rx_alsa_output_mode'] == 'iec958' ?
		getAlsaIEC958Device() : $cfgMultiroom['rx_alsa_output_mode'] . ':' . $_SESSION['cardnum'] . ',0';

	$cmd = 'trx-rx' .
		' -d ' . $outputDevice .
		' -h ' . $cfgMultiroom['rx_host'] .
		' -p ' . $cfgMultiroom['rx_port'] .
		' -m ' . $cfgMultiroom['rx_bfr'] .
		' -j ' . $cfgMultiroom['rx_jitter_bfr'] .
		' -f ' . $cfgMultiroom['rx_frame_size'] .
		' -R ' . $cfgMultiroom['rx_rtprio'] .
		' -D /tmp/trx-rxpid  >/dev/null';
	$result = shell_exec($cmd);
	debugLog($cmd);
}

function stopMultiroomReceiver() {
	sysCmd('killall trx-rx');
	phpSession('write', 'rxactive', '0');
	sendEngCmd('rxactive0');
}

// NOTE: The trx-control.php utility checks to see if Receiver opted in for Master volume
function updReceiverVol($volCmd, $masterVolChange = false) {
	$rxHostNames = explode(', ', $_SESSION['rx_hostnames']);
	$rxAddresses = explode(' ', $_SESSION['rx_addresses']);
	$trxControlCmd = $masterVolChange ? '-set-mpdvol-from-master' : '-set-mpdvol';
	$timeout = getStreamTimeout();

	$count = count($rxAddresses);
	for ($i = 0; $i < $count; $i++) {
		debugLog('updReceiverVol(): ' . $volCmd . ': ' . $rxHostNames[$i]);
		if (false === ($result = file_get_contents('http://' . $rxAddresses[$i]  . '/command/?cmd=trx-control.php ' . $trxControlCmd . ' ' . $volCmd, false, $timeout))) {
			workerLog('updReceiverVol(): ' . $volCmd . ': ' . $rxHostNames[$i] . ' failed');
		} else {
			debugLog('updReceiverVol(): ' . $volCmd . ': ' . $rxHostNames[$i] . ' success');
		}
	}
}

// For use in file_get_contents($URL) calls
function getStreamTimeout() {
	$result = sqlQuery("SELECT value FROM cfg_multiroom WHERE param='tx_query_timeout'", sqlConnect());
	$timeout = $result[0]['value'] . '.0';
	$options = array(
		'http' => array(
			'protocol_version' => (float)'1.1',
			'timeout' => (float)$timeout
		)
	);

	//workerLog(print_r($options, true));
	return stream_context_create($options);
}

function loadSndDummy() {
	// Load driver and return card number
	sysCmd('modprobe snd_dummy');
	$result = sysCmd("cat /proc/asound/Dummy/pcm0p/info | awk -F': ' '/card/{print $2}'");
	return $result[0];
}

function unloadSndDummy() {
	$maxLoops = 3;

	for ($i = 0; $i < $maxLoops; $i++) {
		sysCmd('sudo modprobe -rf snd_dummy');
		$result = sysCmd('lsmod | grep -e "^snd_dummy"');

		if (empty($result)) {
			break;
		}

		usleep(250000);
	}
}
