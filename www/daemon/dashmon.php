#!/usr/bin/php
<?php
/*
 * SPDX-License-Identifier: GPL-3.0-or-later
 * Copyright 2014 The moOde audio player project / Tim Curtis
*/

require_once __DIR__ . '/../inc/common.php';
require_once __DIR__ . '/../inc/dashboard.php';
require_once __DIR__ . '/../inc/sql.php';

$dbh = sqlConnect();

// Wait for worker startup to complete
while (sqlQuery("SELECT value FROM cfg_system WHERE param='wrkready'", $dbh)[0]['value'] != '1') {
	sleep(3);
}
sleep(5);

while (true) {
	updateDashboardCache();
	sleep(DASHMON_SLEEP);
}
