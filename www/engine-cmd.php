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
}
else {
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

// Get names of connected bluetooth devices
if ($cmd == 'btactive1') {
	$result = sysCmd('/var/www/util/blu-control.sh -c');
	$cmd .= ',';
	for ($i = 2; $i < count($result); $i++) {
		if ($result[$i] != '**') {
			$cmd .= substr($result[$i], 21) . ';';
		}
	}
	$cmd = rtrim($cmd, ';');
}
elseif ($cmd == 'inpactive1') {
	$result = sqlQuery("SELECT value FROM cfg_system WHERE param='audioin'", sqlConnect());
	$cmd .= ',' . $result[0]['value'];
}

//workerLog('engineCmd(): Returning cmd to client');
echo json_encode($cmd);
