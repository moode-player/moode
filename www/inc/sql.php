<?php
/*
 * SPDX-License-Identifier: GPL-3.0-or-later
 * Copyright 2014 The moOde audio player project / Tim Curtis
 * Copyright 2013 The tsunamp player ui / Andrea Coiutti & Simone De Gregori
*/

require_once __DIR__ . '/common.php';

function sqlConnect() {
	if ($dbh = new PDO(SQLDB)) {
		return $dbh;
	} else {
		workerLog('sqlConnect(): Cannot open SQLite database');
		return false;
	}
}

function sqlRead($table, $dbh, $param = '', $id = '') {
	if (empty($param) && empty($id)) {
		$queryStr = 'SELECT * FROM ' . $table;
	} else if (!empty($id)) {
		$queryStr = "SELECT * FROM " . $table . " WHERE id='" . $id . "'";
	} else if ($param == 'mpdconf') {
		$queryStr = "SELECT param, value FROM cfg_mpd WHERE value!=''";
	} else if ($table == 'cfg_audiodev') {
		$filter = $param == 'all' ? " WHERE list='yes'" : " WHERE name='" . $param . "' AND list='yes'";
		$queryStr = 'SELECT name, alt_name, dacchip, chipoptions, iface, list, driver, drvoptions FROM ' . $table . $filter;
	} else if ($table == 'cfg_outputdev') {
		$queryStr = 'SELECT * FROM ' . $table . " WHERE device_name='" . $param . "'";
	} else if ($table == 'cfg_theme') {
		$queryStr = 'SELECT theme_name, tx_color, bg_color, mbg_color FROM ' . $table . " WHERE theme_name='" . $param . "'";
	} else if ($table == 'cfg_radio') {
		$queryStr = $param == 'all' ? 'SELECT * FROM ' . $table . " WHERE station not in ('OFFLINE', 'zx reserved 499')" :
			'SELECT station, name, logo, home_page FROM ' . $table . " WHERE station='" . $param . "'";
	} else {
		$queryStr = 'SELECT value FROM ' . $table . " WHERE param='" . $param . "'";
	}

	return sqlQuery($queryStr, $dbh);
}

function sqlUpdate($table, $dbh, $key = '', $value) {
	switch ($table) {
		// Special handling
		case 'cfg_system':
		case 'cfg_mqtt':
			$queryStr = "UPDATE " . $table .
				" SET value='" . SQLite3::escapeString($value) .
				"' WHERE param='" . SQLite3::escapeString($key) . "'";
			break;
		case 'cfg_network':
			$queryStr = "UPDATE " . $table .
				" SET method='" . SQLite3::escapeString($value['method']) .
				"', ipaddr='" . SQLite3::escapeString($value['ipaddr']) .
				"', netmask='" . SQLite3::escapeString($value['netmask']) .
				"', gateway='" . SQLite3::escapeString($value['gateway']) .
				"', pridns='" . SQLite3::escapeString($value['pridns']) .
				"', secdns='" . SQLite3::escapeString($value['secdns']) .
				"', wlanssid='" . SQLite3::escapeString($value['wlanssid']) .
				"', wlanuuid='" . SQLite3::escapeString($value['wlanuuid']) .
				"', wlanpwd='" . SQLite3::escapeString($value['wlanpwd']) .
				"', wlanpsk='" . SQLite3::escapeString($value['wlanpsk']) .
				"', wlancc='" . SQLite3::escapeString($value['wlancc']) .
				"', wlansec='" . SQLite3::escapeString($value['wlansec']) .
				"' WHERE iface='" . SQLite3::escapeString($key) . "'";
			break;
		case 'cfg_source':
			$queryStr = "UPDATE " . $table .
				" SET name='" . SQLite3::escapeString($value['name']) .
				"', type='" . SQLite3::escapeString($value['type']) .
				"', address='" . SQLite3::escapeString($value['address']) .
				"', remotedir='" . SQLite3::escapeString($value['remotedir']) .
				"', username='" . SQLite3::escapeString($value['username']) .
				($value['password'] == 'Password set' ? '' : "', password='" . SQLite3::escapeString($value['password'])) .
				"', charset='" . SQLite3::escapeString($value['charset']) .
				"', rsize='" . SQLite3::escapeString($value['rsize']) .
				"', wsize='" . SQLite3::escapeString($value['wsize']) .
				"', options='" . SQLite3::escapeString($value['options']) .
				"', error='" . SQLite3::escapeString($value['error']) .
				"' WHERE id='" . SQLite3::escapeString($value['id']) . "'";
			break;
		case 'cfg_audiodev':
			$queryStr = "UPDATE " . $table .
				" SET chipoptions='" . SQLite3::escapeString($value) .
				"' WHERE name='" . SQLite3::escapeString($key) . "'";
			break;
		case 'cfg_outputdev':
			$queryStr = "UPDATE " . $table .
				" SET mpd_volume_type='" . SQLite3::escapeString($value['mpd_volume_type']) .
				"', alsa_output_mode='" . SQLite3::escapeString($value['alsa_output_mode']) .
				"', alsa_max_volume='" . SQLite3::escapeString($value['alsa_max_volume']) .
				"' WHERE device_name='" . SQLite3::escapeString($key) . "'";
			break;
		case 'cfg_radio':
			$queryStr = "UPDATE " . $table .
				" SET station='" . SQLite3::escapeString($value) .
				"' WHERE name='" . SQLite3::escapeString($key) . "'";
			break;
		case 'cfg_gpio':
			$queryStr = "UPDATE " . $table .
				" SET enabled='" . SQLite3::escapeString($value['enabled']) .
				"', pin='" . SQLite3::escapeString($value['pin']) .
				"', pull='" . SQLite3::escapeString($value['pull']) .
				"', command='" . trim(SQLite3::escapeString($value['command'])) .
				"', param='" . SQLite3::escapeString($value['param']) .
				"', value='" . SQLite3::escapeString($value['value']) .
				"' WHERE id='" . SQLite3::escapeString($key) . "'";
			break;
		// Standard param|value tables
		default:
			$queryStr = "UPDATE " . $table .
				" SET value='" . SQLite3::escapeString($value) .
				"' WHERE param='" . SQLite3::escapeString($key) . "'";
			break;
	}

	return sqlQuery($queryStr, $dbh);
}

function sqlInsert($table, $dbh, $values) {
	// NOTE: NULL causes id column to be set to the next number
	$queryStr = "INSERT INTO " . $table . " VALUES (NULL, " . $values . ")";
	return sqlQuery($queryStr, $dbh);
}

function sqlDelete($table, $dbh, $id = '') {
	if (empty($id)) {
		$queryStr = "DELETE FROM " . $table;
	} else {
		$queryStr = "DELETE FROM " . $table . " WHERE id='" . $id . "'";
	}

	return sqlQuery($queryStr, $dbh);
}

function sqlQuery($queryStr, $dbh) {
	$whereClause = (false !== ($pos = stripos($queryStr, 'where'))) ? substr($queryStr, $pos + 6) : 'No WHERE clause';
	// DEBUG
	//workerLog('DBG: sqlQuery(): ' . $queryStr);
	//workerLog('DBG: sqlQuery(): ' . (empty($whereClause) ? 'No where clause' : $whereClause));
	// Avoid log spam
	if ($whereClause != "param='debuglog'") {
		chkSQL($whereClause);
	}

	$query = $dbh->prepare($queryStr);

	if ($query->execute()) {
		$dbh = null;
		$rows = array();

		foreach ($query as $row) {
			array_push($rows, $row);
		}

		if (empty($rows)) {
			// Query successful, no rows
			return true;
		} else {
			// Query successful, at lease one row
			return $rows;
		}
	} else {
		// Query execution failed (should never happen)
		workerLog('DBG: sqlQuery(): Query execution failed');
		workerLog('DBG: sqlQuery(): ' . $queryStr);
		return false;
	}
}
