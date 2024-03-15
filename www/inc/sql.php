<?php
/**
 * moOde audio player (C) 2014 Tim Curtis
 * http://moodeaudio.org
 *
 * tsunamp player ui (C) 2013 Andrea Coiutti & Simone De Gregori
 * http://www.tsunamp.com
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
		$filter = $param == 'all' ? ' WHERE list="yes"' : ' WHERE name="' . $param . '" AND list="yes"';
		$queryStr = 'SELECT name, alt_name, dacchip, chipoptions, iface, list, driver, drvoptions FROM ' . $table . $filter;
	} else if ($table == 'cfg_theme') {
		$queryStr = 'SELECT theme_name, tx_color, bg_color, mbg_color FROM ' . $table . ' WHERE theme_name="' . $param . '"';
	} else if ($table == 'cfg_radio') {
		$queryStr = $param == 'all' ? 'SELECT * FROM ' . $table . ' WHERE station not in ("OFFLINE", "zx reserved 499")' :
			'SELECT station, name, logo, home_page FROM ' . $table . ' WHERE station="' . $param . '"';
	} else {
		$queryStr = 'SELECT value FROM ' . $table . ' WHERE param="' . $param . '"';
	}

	return sqlQuery($queryStr, $dbh);
}

function sqlUpdate($table, $dbh, $key = '', $value) {
	switch ($table) {
		case 'cfg_system':
			$queryStr = "UPDATE " . $table . " SET value='" . SQLite3::escapeString($value) . "' WHERE param='" . $key . "'";
			break;
		case 'cfg_mpd':
			$queryStr = "UPDATE " . $table . " SET value='" . $value . "' WHERE param='" . $key . "'";
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
				"' WHERE iface='" . $key . "'";
			//workerLog('sqlUpdate: ' . $queryStr);
			break;
		case 'cfg_source':
			$queryStr = "UPDATE " . $table . " SET name='" . $value['name'] . "', type='" . $value['type'] . "', address='" .
				$value['address'] . "', remotedir='" . $value['remotedir'] . "', username='" . $value['username'] .
				"', password='" . $value['password'] . "', charset='" . $value['charset'] . "', rsize='" . $value['rsize'] .
				"', wsize='" . $value['wsize'] . "', options='" . $value['options'] . "', error='" . $value['error'] .
				"' WHERE id=" . $value['id'];
			break;
		case 'cfg_audiodev':
			$queryStr = "UPDATE " . $table . " SET chipoptions='" . $value . "' WHERE name='" . $key . "'";
			break;
		case 'cfg_radio':
			$queryStr = "UPDATE " . $table . " SET station='" . $value . "' WHERE name='" . $key . "'";
			break;
		case 'cfg_sl':
			$queryStr = "UPDATE " . $table . " SET value='" . $value . "' WHERE param='" . $key . "'";
			break;
		case 'cfg_airplay':
			$queryStr = "UPDATE " . $table . " SET value='" . $value . "' WHERE param='" . $key . "'";
			break;
		case 'cfg_spotify':
			$queryStr = "UPDATE " . $table . " SET value='" . $value . "' WHERE param='" . $key . "'";
			break;
		case 'cfg_upnp':
			$queryStr = "UPDATE " . $table . " SET value='" . $value . "' WHERE param='" . $key . "'";
			break;
		case 'cfg_gpio':
			$queryStr = "UPDATE " . $table .
				" SET enabled='" . $value['enabled'] .
				"', pin='" . $value['pin'] .
				"', command='" . trim($value['command']) .
				"', param='" . $value['param'] .
				"', value='" . $value['value'] .
				"' WHERE id='" . $key . "'";
			//workerLog('sqlUpdate: ' . $queryStr);
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
		$queryStr = "DELETE FROM " . $table . " WHERE id=" . $id;
	}

	return sqlQuery($queryStr, $dbh);
}

function sqlQuery($queryStr, $dbh) {
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
		debugLog('sqlQuery(): ' . $queryStr);
		debugLog('sqlQuery(): Query execution failed');
		return false;
	}
}
