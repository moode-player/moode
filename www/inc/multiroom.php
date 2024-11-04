<?php
/*
 * SPDX-License-Identifier: GPL-3.0-or-later
 * Copyright 2014 The moOde audio player project / Tim Curtis
*/

require_once __DIR__ . '/audio.php';
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
	sendFECmd('rxactive0');
}

// NOTE: The trx-control.php utility checks to see if Receiver opted in for Master volume
function updReceiverVol($volCmd, $masterVolChange = false) {
	$rxHostNames = explode(', ', $_SESSION['rx_hostnames']);
	$rxAddresses = explode(' ', $_SESSION['rx_addresses']);
	$trxControlCmd = $masterVolChange ? '-set-mpdvol-from-master' : '-set-mpdvol';
	$timeout = getStreamTimeout();

	$count = count($rxAddresses);
	for ($i = 0; $i < $count; $i++) {
		if (false === sendTrxControlCmd($rxAddresses[$i], $trxControlCmd . ' ' . $volCmd)) {
			workerLog('updReceiverVol(): ' .  $trxControlCmd . ' ' . $volCmd . ': ' . $rxHostNames[$i] . ' failed');
		} else {
			debugLog('updReceiverVol(): ' .  $trxControlCmd . ' ' . $volCmd . ': ' . $rxHostNames[$i] . ' success');
		}
	}
}

function sendTrxControlCmd($ipAddress, $cmd) {
	$maxLoops = 3;
	$timeout = getStreamTimeout();

	for ($i = 0; $i < $maxLoops; $i++) {
		if (false !== ($result = file_get_contents('http://' . $ipAddress . '/command/?cmd=' . rawurlencode('trx_control ' . $cmd), false, $timeout))) {
			break;
		}
	}

	return $result;
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
		$result = sysCmd('modprobe -r -f snd_dummy');
		debugLog('unloadSndDummy(): ' . ($i + 1) . ': ' . $result[0]);

		$result = sysCmd('lsmod | grep -e "^snd_dummy"');

		if (empty($result)) {
			debugLog('unloadSndDummy(): Successfully unloaded');
			break;
		}

		sleep(1);
	}
}
