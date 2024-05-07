<?php
/*
 * SPDX-License-Identifier: GPL-3.0-or-later
 * Copyright 2014 The moOde audio player project / Tim Curtis
*/

require_once __DIR__ . '/inc/common.php';
require_once __DIR__ . '/inc/sql.php';

if (false === ($sock = socket_create_listen(0))) {
	workerLog('engineCmd(): Socket create failed');
	echo json_encode('socket create failed');
	exit();
}

socket_getsockname($sock, $addr, $port);
//workerLog('engineCmd(): Listening on port (' . $port . '}');

if (false === ($fp = fopen(PORT_FILE, 'a'))) {
	workerLog('engineCmd(): File create failed');
	echo json_encode('file create failed');
	exit();
} else {
	//workerLog('engineCmd(): Updating portfile (add ' . $port . ')');
	fwrite($fp, $port . "\n");
	fclose($fp);
}

while($sockres = socket_accept($sock)) {
	socket_getpeername($sockres, $raddr, $rport);
	//workerLog('engineCmd(): Connection from: ' . $raddr . ':' . $rport);

	//workerLog('engineCmd(): Waiting for command...');
	$data = socket_read($sockres, 1024);

	// Trim some chrs in case we connect via telnet for testing
	$cmd = str_replace(array("\r\n","\r","\n"), '', $data);
	//workerLog('engineCmd(): Received cmd: ' . $cmd);
	break;
}

//workerLog('engineCmd(): Closing socket: ' . $port);
socket_close($sock);

//workerLog('engineCmd(): Updating portfile (remove ' . $port . ')');
sysCmd('sed -i /' . $port . '/d ' . PORT_FILE);

// Special cmd handling

if ($cmd == 'inpactive1') {
	$result = sqlQuery("SELECT value FROM cfg_system WHERE param='audioin'", sqlConnect());
	$cmd .= ',' . $result[0]['value'];
}

//workerLog('engineCmd(): Returning cmd to client');
echo json_encode($cmd);
