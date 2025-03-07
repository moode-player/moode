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
			$queryStr = "UPDATE " . $table .
				" SET value='" . SQLite3::escapeString($value) .
				"' WHERE param='" . $key . "'";
			break;
		case 'cfg_network':
			$queryStr = "UPDATE " . $table .
				" SET method='" . $value['method'] .
				"', ipaddr='" . $value['ipaddr'] .
				"', netmask='" . $value['netmask'] .
				"', gateway='" . $value['gateway'] .
				"', pridns='" . $value['pridns'] .
				"', secdns='" . $value['secdns'] .
				"', wlanssid='" . SQLite3::escapeString($value['wlanssid']) .
				"', wlanuuid='" . $value['wlanuuid'] .
				"', wlanpwd='" . SQLite3::escapeString($value['wlanpwd']) .
				"', wlanpsk='" . $value['wlanpsk'] .
				"', wlancc='" . $value['wlancc'] .
				"', wlansec='" . $value['wlansec'] .
				"' WHERE iface='" . $key . "'";
			break;
		case 'cfg_source':
			$queryStr = "UPDATE " . $table .
				" SET name='" . $value['name'] .
				"', type='" . $value['type'] .
				"', address='" . $value['address'] .
				"', remotedir='" . $value['remotedir'] .
				"', username='" . $value['username'] .
				"', password='" . $value['password'] .
				"', charset='" . $value['charset'] .
				"', rsize='" . $value['rsize'] .
				"', wsize='" . $value['wsize'] .
				"', options='" . $value['options'] .
				"', error='" . $value['error'] .
				"' WHERE id=" . $value['id'];
			break;
		case 'cfg_audiodev':
			$queryStr = "UPDATE " . $table .
				" SET chipoptions='" . $value .
				"' WHERE name='" . $key . "'";
			break;
		case 'cfg_outputdev':
			$queryStr = "UPDATE " . $table .
				" SET mpd_volume_type='" . $value['mpd_volume_type'] .
				"', alsa_output_mode='" . $value['alsa_output_mode'] .
				"', alsa_max_volume='" . $value['alsa_max_volume'] .
				"' WHERE device_name='" . $key . "'";
			break;
		case 'cfg_radio':
			$queryStr = "UPDATE " . $table .
				" SET station='" . $value .
				"' WHERE name='" . $key . "'";
			break;
		case 'cfg_gpio':
			$queryStr = "UPDATE " . $table .
				" SET enabled='" . $value['enabled'] .
				"', pin='" . $value['pin'] .
				"', pull='" . $value['pull'] .
				"', command='" . trim($value['command']) .
				"', param='" . $value['param'] .
				"', value='" . $value['value'] .
				"' WHERE id='" . $key . "'";
			break;
		// Standard param|value tables
		default:
			$queryStr = "UPDATE " . $table .
				" SET value='" . $value .
				"' WHERE param='" . $key . "'";
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
