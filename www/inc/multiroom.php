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

function startMultiroomSender() {
	$params = sqlRead('cfg_multiroom', sqlConnect());
	foreach ($params as $row) {
	    $cfgMultiroom[$row['param']] = $row['value'];
	}
	$cmd = 'trx-tx -d trx_send -h ' . $cfgMultiroom['tx_host'] . ' -p ' . $cfgMultiroom['tx_port'] . ' -m ' . $cfgMultiroom['tx_bfr'] .
		' -f ' . $cfgMultiroom['tx_frame_size'] . ' -R ' . $cfgMultiroom['tx_rtprio'] . ' -D /tmp/trx-txpid  >/dev/null';
	$result = shell_exec($cmd);
	debugLog($cmd);
}

function stopMultiroomSender() {
	sysCmd('killall trx-tx');
}

function startMultiroomReceiver() {
	$params = sqlRead('cfg_multiroom', sqlConnect());
	foreach ($params as $row) {
	    $cfgMultiroom[$row['param']] = $row['value'];
	}

	$cmd = 'trx-rx -d ' . $cfgMultiroom['rx_alsa_output_mode'] . ':' . $_SESSION['cardnum'] . ',0 -h ' . $cfgMultiroom['rx_host'] .
		' -p ' . $cfgMultiroom['rx_port'] . ' -m ' . $cfgMultiroom['rx_bfr'] . ' -j ' . $cfgMultiroom['rx_jitter_bfr'] .
		' -f ' . $cfgMultiroom['rx_frame_size'] . ' -R ' . $cfgMultiroom['rx_rtprio'] . ' -D /tmp/trx-rxpid  >/dev/null';
	$result = shell_exec($cmd);
	debugLog($cmd);
}

function stopMultiroomReceiver() {
	sysCmd('killall trx-rx');
	phpSession('write', 'rxactive', '0');
	sendEngCmd('rxactive0');
}

function updReceiverVol ($cmd) {
	$rxHostNames = explode(', ', $_SESSION['rx_hostnames']);
	$rxAddresses = explode(' ', $_SESSION['rx_addresses']);

	$count = count($rxAddresses);
	for ($i = 0; $i < $count; $i++) {
		// NOTE: set-mpdvol checks to see if Receiver opted in for Master volume
		if (false === ($result = file_get_contents('http://' . $rxAddresses[$i]  . '/command/?cmd=trx-status.php -set-mpdvol ' . $cmd))) {
			if (false === ($result = file_get_contents('http://' . $rxAddresses[$i]  . '/command/?cmd=trx-status.php -set-mpdvol ' . $cmd))) {
				debugLog('updReceiverVol(): remote volume cmd (' . $cmd . ') failed: ' . $rxHostNames[$i]);
			}
		}
	}
}

function loadSndDummy () {
	// Load driver and return card number
	sysCmd('modprobe snd-dummy');
	$result = sysCmd("cat /proc/asound/Dummy/pcm0p/info | awk -F': ' '/card/{print $2}'");
	return $result[0];
}

function unloadSndDummy () {
	sysCmd('sudo modprobe -r snd-dummy');
}

// Returns the specified timeout for use in file_get_contents($URL) calls
function getStreamTimeout() {
	$result = sdbquery("SELECT value FROM cfg_multiroom WHERE param='tx_query_timeout'", sqlConnect());
	$timeout = $result[0]['value'];
	$options = array('http' => array('timeout' => $timeout . '.0')); // Wait up to $timeout seconds (float)
	return stream_context_create($options);
}
