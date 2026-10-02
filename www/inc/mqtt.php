<?php
/*
 * SPDX-License-Identifier: GPL-3.0-or-later
 * Copyright 2014 The moOde audio player project / Tim Curtis
 * Copyright 2026 @Gjuju
*/

require_once __DIR__ . '/common.php';
require_once __DIR__ . '/sql.php';

const MQTT_CONF = '/etc/mqtt-bridge.conf';

function startMqtt() {
	cfgMqtt();
	sysCmd('systemctl start mqtt-bridge');
}
function stopMqtt() {
	sysCmd('systemctl stop mqtt-bridge');
}
// Clear what this player published so the home automation system drops the
// device. The service must be stopped first: it republishes on every connect
function removeMqtt() {
	cfgMqtt();
	return sysCmd('python3 /var/www/daemon/mqtt-bridge.py --remove');
}

// Write the bridge config from cfg_mqtt
function cfgMqtt() {
	$dbh = sqlConnect();
	$cfg = array();
	foreach (sqlRead('cfg_mqtt', $dbh) as $row) {
		$cfg[$row['param']] = $row['value'];
	}

	// Set once from the host name then kept: they identify the player in the home
	// automation system, so a later host name change would add a second device
	if ($cfg['instance'] == '') {
		$cfg['instance'] = preg_replace('/[^a-z0-9_-]/', '', strtolower($_SESSION['hostname']));
		$cfg['instance'] = $cfg['instance'] == '' ? 'moode' : $cfg['instance'];
		sqlUpdate('cfg_mqtt', $dbh, 'instance', $cfg['instance']);
	}
	if ($cfg['friendly_name'] == '') {
		$cfg['friendly_name'] = $_SESSION['hostname'];
		sqlUpdate('cfg_mqtt', $dbh, 'friendly_name', $cfg['friendly_name']);
	}
	// One broker connection per client id, so each player gets its own
	$cfg['client_id'] = 'mqtt-bridge-' . $cfg['instance'];

	$sections = array(
		'broker' => array('host', 'port', 'username', 'password', 'client_id', 'tls', 'tls_server_name'),
		'moode' => array('instance', 'friendly_name', 'topic_prefix', 'discovery_prefix',
			'poll_interval', 'audio_off_delay', 'volume_step')
	);
	$data = '';
	foreach ($sections as $section => $keys) {
		$data .= '[' . $section . "]\n";
		foreach ($keys as $key) {
			$data .= $key . ' = ' . str_replace(array("\r", "\n"), '', $cfg[$key]) . "\n";
		}
		$data .= "\n";
	}

	// Holds the broker password: readable by the bridge (www-data) only
	$tmpFile = tempnam('/tmp', 'mqtt-bridge');
	$fh = fopen($tmpFile, 'w');
	fwrite($fh, $data);
	fclose($fh);
	sysCmd('install -m 0640 -o root -g www-data ' . $tmpFile . ' ' . MQTT_CONF);
	unlink($tmpFile);
}
