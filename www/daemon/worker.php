#!/usr/bin/php
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

require_once __DIR__ . '/../inc/common.php';
require_once __DIR__ . '/../inc/alsa.php';
require_once __DIR__ . '/../inc/audio.php';
require_once __DIR__ . '/../inc/autocfg.php';
require_once __DIR__ . '/../inc/cdsp.php';
require_once __DIR__ . '/../inc/eqp.php';
require_once __DIR__ . '/../inc/mpd.php';
require_once __DIR__ . '/../inc/multiroom.php';
require_once __DIR__ . '/../inc/music-library.php';
require_once __DIR__ . '/../inc/music-source.php';
require_once __DIR__ . '/../inc/network.php';
require_once __DIR__ . '/../inc/renderer.php';
require_once __DIR__ . '/../inc/session.php';
require_once __DIR__ . '/../inc/sql.php';

//----------------------------------------------------------------------------//
// STARTUP SEQUENCE
//----------------------------------------------------------------------------//

sysCmd('truncate ' . MOODE_LOG . ' --size 0');
$dbh = sqlConnect();
$result = sqlQuery("UPDATE cfg_system SET value='0' WHERE param='wrkready'", $dbh);
$moodeSeries = substr(getMoodeRel(), 1, 1); // rNNN format

//----------------------------------------------------------------------------//
workerLog('worker: --');
workerLog('worker: -- Start moOde ' . $moodeSeries .  ' series');
workerLog('worker: --');
//----------------------------------------------------------------------------//

// Daemonize ourselves
$lock = fopen('/run/worker.pid', 'c+');
if (!flock($lock, LOCK_EX | LOCK_NB)) {
	$logMsg = 'worker: Already running';
	workerLog($logMsg);
	exit($logMsg . "\n");
}

switch ($pid = pcntl_fork()) {
	case -1:
		$logMsg = 'worker: Unable to fork';
		workerLog($logMsg);
		exit($logMsg . "\n");
	case 0: // Child process
		break;
	default: // Parent process
		fseek($lock, 0);
		ftruncate($lock, 0);
		fwrite($lock, $pid);
		fflush($lock);
		exit;
}

if (posix_setsid() === -1) {
	$logMsg = 'worker: Could not setsid';
	workerLog($logMsg);
	exit($logMsg . "\n");
}

fclose(STDIN);
fclose(STDOUT);
fclose(STDERR);

$stdIn = fopen('/dev/null', 'r'); // Set fd/0
$stdOut = fopen('/dev/null', 'w'); // Set fd/1
$stdErr = fopen('php://stdout', 'w'); // A hack to duplicate fd/1 to 2

pcntl_signal(SIGTSTP, SIG_IGN);
pcntl_signal(SIGTTOU, SIG_IGN);
pcntl_signal(SIGTTIN, SIG_IGN);
pcntl_signal(SIGHUP, SIG_IGN);
workerLog('worker: Successfully daemonized');

// Check for login user ID
if (empty(getUserID())) {
	$logMsg = 'worker: Error: Login User ID does not exist, unable to continue';
	workerLog($logMsg);
	exit($logMsg . "\n");
}

// Check for Linux startup complete
workerLog('worker: Wait for Linux startup');
$maxLoops = 30;
$sleepTime = 6;
$linuxStartupComplete = false;
for ($i = 0; $i < $maxLoops; $i++) {
	$result = sysCmd('systemctl is-system-running');
	if ($result[0] == 'running' || $result[0] == 'degraded') {
		$linuxStartupComplete = true;
		break;
	} else {
		debugLog('worker: Wait ' . ($i + 1) . ' for Linux startup');
		sleep($sleepTime);
	}
}
if ($linuxStartupComplete === true) {
	workerLog('worker: Linux startup complete');
} else {
	$logMsg = 'worker: Error: Linux startup failed to complete after waiting ' . ($maxLoops * $sleepTime) . ' seconds';
	workerLog($logMsg);
	exit($logMsg . "\n");
}

// Import boot config.txt (first boot)
$lines = file(BOOT_CONFIG_TXT);
if (str_contains($lines[1], 'This file is managed by moOde')) {
	workerLog('worker: Boot config is ok');
} else {
	sysCmd('cp -f /usr/share/moode-player/boot/firmware/config.txt /boot/firmware/');
	sysCmd('reboot');
}

// Boot file recovery (rare)
if (file_exists(BOOT_CONFIG_TXT) && count(file(BOOT_CONFIG_TXT)) > 10) {
	// Backup
	sysCmd('cp ' . BOOT_CONFIG_TXT . ' ' . BOOT_CONFIG_BKP);
	workerLog('worker: Boot config backed up');
} else {
	// Restore
	sysCmd('cp ' . BOOT_CONFIG_BKP . ' ' . BOOT_CONFIG_TXT);
	workerLog('worker: Warning: Boot config restored');
	workerLog('worker: Warning: Restart required');
}

// Prune old session vars
// Example command
//sysCmd('moodeutl -D session_var_name');

// Open session and load cfg_system and cfg_radio
phpSession('load_system');
phpSession('load_radio');
workerLog('worker: Session loaded');

// Ensure package holds are in effect
sysCmd('moode-apt-mark hold > /dev/null 2>&1');
workerLog('worker: Package locks applied');

// Ensure certain files exist and with the correct permissions
if (!file_exists(PLAY_HISTORY_LOG)) {
	sysCmd('touch ' . PLAY_HISTORY_LOG);
	// This sets the "Log initialized" header
	sysCmd('/var/www/util/sysutil.sh clear-playhistory');
}
sysCmd('touch ' . LIBCACHE_BASE . '_all.json');
sysCmd('touch ' . LIBCACHE_BASE . '_folder.json');
sysCmd('touch ' . LIBCACHE_BASE . '_format.json');
sysCmd('touch ' . LIBCACHE_BASE . '_hdonly.json');
sysCmd('touch ' . LIBCACHE_BASE . '_lossless.json');
sysCmd('touch ' . LIBCACHE_BASE . '_lossy.json');
sysCmd('touch ' . LIBCACHE_BASE . '_tag.json');
sysCmd("echo -n '" . json_encode(['filter_type' => 'full_lib', 'filter_str' => '']) . "'" .
	' | tee "' . LIBSEARCH_BASE . LIB_FULL_LIBRARY . '.json" > /dev/null');
sysCmd('touch /var/local/www/sysinfo.txt');
sysCmd('touch /var/local/www/currentsong.txt');
sysCmd('touch ' . SHAIRPORT_SYNC_LOG);
sysCmd('touch ' . LIBRESPOT_LOG);
sysCmd('touch ' . SPOTEVENT_LOG);
sysCmd('touch ' . SPSEVENT_LOG);
sysCmd('touch ' . SLPOWER_LOG);
sysCmd('truncate ' . MOUNTMON_LOG . ' --size 0');
sysCmd('mkdir ' . THMCACHE_DIR . ' > /dev/null 2>&1');
// Delete any tmp files left over from New/Edit station or playlist
sysCmd('rm /var/local/www/imagesw/radio-logos/' . TMP_IMAGE_PREFIX . '* > /dev/null 2>&1');
sysCmd('rm /var/local/www/imagesw/radio-logos/thumbs/' . TMP_IMAGE_PREFIX . '* > /dev/null 2>&1');
sysCmd('rm /var/local/www/imagesw/playlist-covers/' . TMP_IMAGE_PREFIX . '* > /dev/null 2>&1');
// Set permissions
sysCmd('chmod 0777 ' . SQLDB_PATH);
sysCmd('chmod 0777 ' . MPD_PLAYLIST_ROOT);
sysCmd('chmod 0777 ' . MPD_PLAYLIST_ROOT . '*.*');
sysCmd('chmod 0777 ' . MPD_MUSICROOT . 'RADIO/*.*');
sysCmd('chmod 0777 ' . LIBCACHE_BASE . '_*');
sysCmd('chmod 0666 /var/log/moode_playhistory.log');
sysCmd('chmod 0666 /var/local/www/currentsong.txt');
sysCmd('chmod 0666 /var/local/www/sysinfo.txt');
sysCmd('chmod 0666 ' . SHAIRPORT_SYNC_LOG);
sysCmd('chmod 0666 ' . LIBRESPOT_LOG);
sysCmd('chmod 0666 ' . SPOTEVENT_LOG);
sysCmd('chmod 0666 ' . SPSEVENT_LOG);
sysCmd('chmod 0666 ' . SLPOWER_LOG);
sysCmd('chmod 0666 ' . MOODE_LOG);
sysCmd('chmod 0666 ' . MOUNTMON_LOG);
workerLog('worker: File check complete');

// Debug logging
if (!isset($_SESSION['debuglog'])) {
	$_SESSION['debuglog'] = '0';
}
workerLog('worker: Debug logging ' . ($_SESSION['debuglog'] == '1' ? 'on' : 'off'));

//----------------------------------------------------------------------------//
workerLog('worker: --');
workerLog('worker: -- System');
workerLog('worker: --');
//----------------------------------------------------------------------------//

// Pi Imager: Import hostname
$importedHostName = sysCmd('cat /etc/hostname')[0];
if ($importedHostName != $_SESSION['hostname']) { // != 'moode'
	/* Defaults
	hostname		moode
	browsertitle 	Moode Player
	btname			Moode Bluetooth
	airplayname		Moode AirPlay
	spotifyname		Moode Spotify
	upnpname		Moode UPNP
	dlnaname		Moode DLNA
	squeezelite		Moode		In cfg_sl PLAYERNAME and squeezelite.conf, no session var
	mpdzeroconf 	Moode MPD	In mpd.conf, no session var
	*/
	// Host
	phpSession('write', 'hostname', $importedHostName);
	// Browser title
	phpSession('write', 'browsertitle', ucfirst($importedHostName) . ' Player');
	// Bluetooth
	$newName = ucfirst($importedHostName) . ' Bluetooth';
	sysCmd('/var/www/util/sysutil.sh chg-name bluetooth "' . 'Moode Bluetooth" "' . $newName . '"');
	phpSession('write', 'btname', $newName);
	// Airplay
	phpSession('write', 'airplayname', ucfirst($importedHostName) . ' AirPlay');
	// Spotify Connect
	phpSession('write', 'spotifyname', ucfirst($importedHostName) . ' Spotify');
	// Squeezelite
	$newName = ucfirst($importedHostName);
	$result = sqlQuery("UPDATE cfg_sl SET value='" . $newName . "' WHERE param='PLAYERNAME'", $dbh);
	sysCmd('/var/www/util/sysutil.sh chg-name squeezelite "' . 'Moode" "' . $newName . '"');
	// UPnP
	$newName = ucfirst($importedHostName) . ' UPNP';
	sysCmd('/var/www/util/sysutil.sh chg-name upnp "' . 'Moode UPNP" "' . $newName . '"');
	phpSession('write', 'upnpname', $newName);
	// DLNA
	$newName = ucfirst($importedHostName) . ' DLNA';
	sysCmd('/var/www/util/sysutil.sh chg-name dlna "' . 'Moode DLNA" "' . $newName . '"');
	phpSession('write', 'dlnaname', $newName);
	// MPD Zeroconf
	$newName = ucfirst($importedHostName) . ' MPD';
	$result = sqlQuery("UPDATE cfg_mpd SET value='" . $newName . "' WHERE param='zeroconf_name'", $dbh);
	sysCmd('/var/www/util/sysutil.sh chg-name mpdzeroconf "' . 'Moode MPD" "' . $newName . '"');

	workerLog('worker: Hostname imported');
}

// Pi Imager: Import time zone and keyboard layout
$timeZone = sysCmd("timedatectl show | awk -F\"=\" '/Timezone/{print $2;exit;}'");
$keyboard = sysCmd("cat /etc/default/keyboard | awk -F\"=\" '/XKBLAYOUT/{print $2;exit;}'");
phpSession('write', 'timezone', $timeZone[0]);
phpSession('write', 'keyboard', trim($keyboard[0], "\""));

// Store platform data
$_SESSION['hdwrrev'] = getHdwrRev();
$_SESSION['moode_release'] = getMoodeRel(); // rNNN format
// get-osinfo: 'RPiOS: 11.8 (Bullseye 64-bit) | Linux: 6.1.21 (64-bit)'
$osInfo = explode(' | ', sysCmd('/var/www/util/sysutil.sh "get-osinfo"')[0]);
$_SESSION['raspbianver'] = explode(': ', $osInfo[0])[1];
$_SESSION['kernelver'] = explode(': ', $osInfo[1])[1];
$_SESSION['mpdver'] = sysCmd("mpd -V | grep 'Music Player Daemon' | awk '{print $4}'")[0];
$_SESSION['user_id'] = getUserID();
$_SESSION['home_dir'] = '/home/' . $_SESSION['user_id'];

// Log platform data
workerLog('worker: Host name:     ' . $_SESSION['hostname']);
workerLog('worker: RPi model:     ' . $_SESSION['hdwrrev']);
workerLog('worker: moOde release: ' . getMoodeRel('verbose')); // major.minor.patch yyyy-mm-dd
workerLog('worker: RaspiOS:       ' . $_SESSION['raspbianver']);
workerLog('worker: Linux Kernel:  ' . $_SESSION['kernelver']);
workerLog('worker: MPD version:   ' . $_SESSION['mpdver']);
workerLog('worker: User id:       ' . $_SESSION['user_id']);
workerLog('worker: Home folder:   ' . $_SESSION['home_dir']);
workerLog('worker: Time zone:     ' . $_SESSION['timezone']);
workerLog('worker: Kbd layout:    ' . $_SESSION['keyboard']);

// USB boot
$piModel = substr($_SESSION['hdwrrev'], 3, 1);
if ($piModel == '3') { // 3B, B+, A+
	$result = sysCmd('vcgencmd otp_dump | grep 17:');
	if ($result[0] == '17:3020000a') {
		sysCmd('sed -i /program_usb_boot_mode/d ' . BOOT_CONFIG_TXT);
		$msg = 'enabled';
	} else {
		$msg = 'not enabled yet';
	}
	workerLog('worker: USB boot:      ' . $msg);
} else if ($piModel == '4') { // 4, 400
	$bootloaderMinDate = new DateTime("Sep 3 2020");
	$bootloaderActualDate = new DateTime(sysCmd("vcgencmd bootloader_version | awk 'NR==1 {print $1\" \" $2\" \" $3}'")[0]);
	if ($bootloaderActualDate >= $bootloaderMinDate) {
		$msg = 'enabled';
	} else {
		$msg = 'not enabled yet';
	}
	workerLog('worker: USB boot:      ' . $msg);
} else if ($piModel == '5') { // 5
	workerLog('worker: USB boot:      enabled');
} else {
	workerLog('worker: USB boot:      n/a');
}

// HDMI port(s)
// They are always on in Bookworm
workerLog('worker: HDMI ports(s): on');

// LED states
if ($piModel == '1' || str_contains($_SESSION['hdwrrev'], 'Pi-Zero') || str_contains($_SESSION['hdwrrev'], 'Allo USBridge SIG')) {
	// Pi boards w/o a sysclass entry for LED1
	$led0Trigger = explode(',', $_SESSION['led_state'])[0] == '0' ? 'none' : 'actpwr';
	sysCmd('echo ' . $led0Trigger . ' | sudo tee /sys/class/leds/ACT/trigger > /dev/null');
	workerLog('worker: Sys LED0:      ' . ($led0Trigger == 'none' ? 'off' : 'on'));
	workerLog('worker: Sys LED1:      sysclass does not exist');
} else if ($piModel == '5') {
	// Pi-5 dual-color (red/green) power/activity LED
	$led0Trigger = explode(',', $_SESSION['led_state'])[0] == '0' ? 'none' : 'mmc0';
	sysCmd('echo ' . $led0Trigger . ' | sudo tee /sys/class/leds/ACT/trigger > /dev/null');
	workerLog('worker: Sys LED0:      ' . ($led0Trigger == 'none' ? 'off' : 'on'));
	workerLog('worker: Sys LED1:      on');
} else {
	// All other Pi boards have 2 LED's (power and activity)
	$led0Trigger = explode(',', $_SESSION['led_state'])[0] == '0' ? 'none' : 'mmc0';
	$led1Brightness = explode(',', $_SESSION['led_state'])[1] == '0' ? '0' : '255';
	sysCmd('echo ' . $led0Trigger . ' | sudo tee /sys/class/leds/ACT/trigger > /dev/null');
	sysCmd('echo ' . $led1Brightness . ' | sudo tee /sys/class/leds/PWR/brightness > /dev/null');
	workerLog('worker: Sys LED0:      ' . ($led0Trigger == 'none' ? 'off' : 'on'));
	workerLog('worker: Sys LED1:      ' . ($led1Brightness == '0' ? 'off' : 'on'));
}

// Pi-5 POWER_OFF_ON_HALT (shutdown wattage)
if (!isset($_SESSION['reduce_power'])) {
	$_SESSION['reduce_power'] = 'n/a';
}
if ($piModel == '5') {
	$result = sysCmd('rpi-eeprom-config | grep "POWER_OFF_ON_HALT" | cut -d "=" -f 2')[0] == '1' ? 'on' : 'off';
	$_SESSION['reduce_power'] = $result;
} else {
	$_SESSION['reduce_power'] = 'n/a';
}
workerLog('worker: Reduce power:  ' . $_SESSION['reduce_power']);

// CPU governor
workerLog('worker: CPU governor:  ' . $_SESSION['cpugov']);

//----------------------------------------------------------------------------//
workerLog('worker: --');
workerLog('worker: -- Network');
workerLog('worker: --');
//----------------------------------------------------------------------------//

// Pi Imager: Import SSID/PSK/Country, rename to SSID.nmconnection
if (file_exists('/etc/NetworkManager/system-connections/preconfigured.nmconnection')) {
	$ssid = sysCmd("cat /etc/NetworkManager/system-connections/preconfigured.nmconnection 2>&1 | awk -F\"=\" '/ssid=/ {print $2;exit;}'");
	$uuid = sysCmd("cat /etc/NetworkManager/system-connections/preconfigured.nmconnection 2>&1 | awk -F\"=\" '/uuid=/ {print $2;exit;}'");
	$psk = sysCmd("cat /etc/NetworkManager/system-connections/preconfigured.nmconnection 2>&1 | awk -F\"=\" '/psk=/ {print $2;exit;}'");
	$country = sysCmd("iw reg get 2>&1 | awk -F\" \" '/country/ {print $2;exit;}' | cut -d \":\" -f 1");

	if (empty($ssid) || empty($psk)) {
		workerLog('worker: Warning: WiFi SSID/PSK import failed');
	} else {
		// Update wlan0 SSID, UUID, PSK and Country
		sqlQuery('UPDATE cfg_network SET ' .
			"wlanssid='" . $ssid[0] . "', " .
			"wlanuuid='" . $uuid[0] . "', " .
			"wlanpwd='" . $psk[0] . "', " .
			"wlanpsk='" . $psk[0] . "', " .
			"wlancc='" . $country[0] . "' " .
			"WHERE id='2'", $dbh);
		// Update apd0 SSID and UUID
		// NOTE: PSK set to blank because plaintext WiFi password needed for generating a PSK is not available from Pi Imager
		sqlQuery('UPDATE cfg_network SET ' .
			"wlanssid='" . ucfirst($_SESSION['hostname']) . "', " .
			"wlanuuid='" . genUUID() . "', " .
			"wlanpwd='', " .
			"wlanpsk='' " .
			"WHERE id='3'", $dbh);
		// Generate conf files
		cfgNetworks();
		workerLog('worker: WiFi SSID/PSK imported');
	}
}

// Ethernet
workerLog('worker: Eth0');
$eth0 = sysCmd('ip addr list | grep eth0');
if (empty($eth0)) {
	workerLog('worker: Ethernet: adapter does not exist');
	$eth0Ip = '';
} else {
	workerLog('worker: Ethernet: adapter exists');

	if ($_SESSION['eth0chk'] == '1') {
		workerLog('worker: Ethernet: timeout up to ' . $_SESSION['ipaddr_timeout'] . ' secs');
		$eth0Ip = checkForIpAddr('eth0', $_SESSION['ipaddr_timeout']);
	} else {
		workerLog('worker: Ethernet: timeout off');
		$eth0Ip = sysCmd("ip addr list eth0 | grep \"inet \" |cut -d' ' -f6|cut -d/ -f1");
	}

	if (empty($eth0Ip)) {
		workerLog('worker: Ethernet: address not assigned');
	} else {
		logNetworkInfo('eth0');
	}
}

// Wireless (wlan0)
workerLog('worker: Wlan0');
$wlan0 = sysCmd('ip addr list | grep wlan0');
if (empty($wlan0)) {
	workerLog('worker: Wireless: adapter does not exist');
} else {
	$cfgNetwork = sqlQuery('SELECT * FROM cfg_network', $dbh);
	$cfgSSID = sysCmd("moodeutl -q \"SELECT ssid FROM cfg_ssid WHERE ssid NOT IN ('" .
		$cfgNetwork[1]['wlanssid'] . "', 'Activate Hotspot')\"");
	$altSSIDList = empty($cfgSSID) ? 'none' : implode(',', $cfgSSID);
	workerLog('worker: Wireless: adapter exists');
	workerLog('worker: Wireless: country ' . $cfgNetwork[1]['wlancc']);
	workerLog('worker: Wireless: SSID    ' . $cfgNetwork[1]['wlanssid']);
	workerLog('worker: Wireless: other   ' . $altSSIDList);

	// Check for wlan0 IP address
	$wlan0Ip = '';
	$_SESSION['apactivated'] = false;
	if ($cfgNetwork[1]['wlanssid'] != 'Activate Hotspot') {
		workerLog('worker: Wireless: timeout up to ' . $_SESSION['ipaddr_timeout'] . ' secs');
		$wlan0Ip = checkForIpAddr('wlan0', $_SESSION['ipaddr_timeout']);
	}

	// Hotspot activation
	if (empty($wlan0Ip)) {
		workerLog('worker: Wireless: address not assigned');
		workerLog('worker: Wireless: Hotspot activating');
		activateHotspot();
		workerLog('worker: Wireless: timeout up to ' . $_SESSION['ipaddr_timeout'] . ' secs');
		$wlan0Ip = checkForIpAddr('wlan0', $_SESSION['ipaddr_timeout']);
		if (empty($wlan0Ip)) {
			$_SESSION['apactivated'] = false;
			workerLog('worker: Wireless: address not assigned');
		} else {
			$_SESSION['apactivated'] = true;
			workerLog('worker: Wireless: Hotspot activated using SSID ' . $cfgNetwork[2]['wlanssid']);
		}
	} else {
		$result = sysCmd("iwconfig wlan0 | grep 'ESSID' | awk -F':' '{print $2}' | awk -F'\"' '{print $2}'");
		workerLog('worker: Wireless: connect to ' . $result[0]);
	}

	if (!empty($wlan0Ip)) {
		if ($piModel >= 3 || substr($_SESSION['hdwrrev'], 0, 7) == 'Pi-Zero') {
			sysCmd('/sbin/iwconfig wlan0 power off');
			workerLog('worker: Wireless: pwrsave off');
		}
		logNetworkInfo('wlan0');
	}
}

// Store IP address (prefer wlan0 address)
if (!empty($wlan0Ip)) {
	$_SESSION['ipaddress'] = $wlan0Ip[0];
} else if (!empty($eth0Ip)) {
	$_SESSION['ipaddress'] = $eth0Ip[0];
} else {
	$_SESSION['ipaddress'] = '0.0.0.0';
	workerLog('worker: No active network interface');
}

//----------------------------------------------------------------------------//
workerLog('worker: --');
workerLog('worker: -- File sharing');
workerLog('worker: --');
//----------------------------------------------------------------------------//

// SMB
if ($_SESSION['fs_smb'] == 'On') {
	sysCmd('systemctl start smbd');
	sysCmd('systemctl start nmbd');
}
// NFS
if ($_SESSION['fs_nfs'] == 'On') {
	sysCmd('systemctl start nfs-server');
}
// DLNA
if ($_SESSION['feat_bitmask'] & FEAT_MINIDLNA) {
	if (isset($_SESSION['dlnasvc']) && $_SESSION['dlnasvc'] == 1) {
		$status = 'on';
		startMiniDlna();
	} else {
		$status = 'off';
	}
} else {
	$status = 'n/a';
}
workerLog('worker: SMB file sharing:  ' . lcfirst($_SESSION['fs_smb']));
workerLog('worker: NFS file sharing:  ' . lcfirst($_SESSION['fs_nfs']));
workerLog('worker: DLNA file sharing: ' . $status);

//----------------------------------------------------------------------------//
workerLog('worker: --');
workerLog('worker: -- 3rd party components');
workerLog('worker: --');
//----------------------------------------------------------------------------//
// Reconfigure certain 3rd party components
// RoonBridge
// Their installer sets the systemd unit to enabled but we need it disabled because we start/stop it via System Config setting
if (file_exists('/opt/RoonBridge/start.sh') === true) {
	$_SESSION['roonbridge_installed'] = 'yes';
	if (sysCmd('systemctl is-enabled roonbridge')[0] == 'enabled') {
		sysCmd('systemctl disable roonbridge');
		sysCmd('systemctl stop roonbridge');
		workerLog('worker: RoonBridge:      installed');
		workerLog('worker: RoonBridge:      systemd unit set to disabled');
	}
} else {
	$_SESSION['roonbridge_installed'] = 'no';
	workerLog('worker: RoonBridge:      not installed');
}
// Allo Boss 2 OLED
// Their installer adds lines to rc.local which are not needed because we start/stop it via systemd unit
if (!empty(sysCmd('grep "boss2" /etc/rc.local')[0])) {
	sleep(1); // Allow rc.local script time to exit after starting worker.php
	sysCmd('sed -i /boss2/d /etc/rc.local');
	workerLog('worker: Allo Boss2 OLED: detected a manually installed script');
	workerLog('worker: Allo Boss2 OLED: rc.local lines removed');
}
workerLog('worker: Allo Boss2 OLED: pre-installed script ok');

//----------------------------------------------------------------------------//
workerLog('worker: --');
workerLog('worker: -- I2S devices (special setup)');
workerLog('worker: --');
//----------------------------------------------------------------------------//

// Configure Allo Piano 2.1
if ($_SESSION['i2sdevice'] == 'Allo Piano 2.1 Hi-Fi DAC') {
	$dualMode = sysCmd('/var/www/util/sysutil.sh get-piano-dualmode');
	$subMode = sysCmd('/var/www/util/sysutil.sh get-piano-submode');
	// Determine output mode
	if ($dualMode[0] != 'None') {
		$outputMode = $dualMode[0];
	} else {
		$outputMode = $subMode[0];
	}
	workerLog('worker: Allo Piano 2.1:   mode set to ' . $outputMode);

	// Workaround: Send brief inaudible PCM to one of the channels to initialize volume
	sysCmd('amixer -M -c 0 sset "Master" 0');
	sysCmd('speaker-test -c 2 -s 2 -r 48000 -F S16_LE -X -f 24000 -t sine -l 1');
	workerLog('worker: Allo Piano 2.1:   volume initialized');
} else {
	workerLog('worker: Allo Piano 2.1:   not detected');
}

// Start Allo Boss2 OLED display
if ($_SESSION['i2sdevice'] == 'Allo Boss 2 DAC' && !file_exists($_SESSION['home_dir'] . '/boss2oled_no_load')) {
	sysCmd('systemctl start boss2oled');
	workerLog('worker: Allo Boss 2:      OLED started');
} else {
	workerLog('worker: Allo Boss 2:      not detected');
}

// Ensure audio output is unmuted for these devices
if ($_SESSION['i2sdevice'] == 'IQaudIO Pi-AMP+') {
	sysCmd('/var/www/util/sysutil.sh unmute-pi-ampplus');
	workerLog('worker: IQaudIO AMP+:     unmuted');
} else if ($_SESSION['i2sdevice'] == 'IQaudIO Pi-DigiAMP+') {
	sysCmd('/var/www/util/sysutil.sh unmute-pi-digiampplus');
	workerLog('worker: IQaudIO DigiAMP+: unmuted');
} else {
	workerLog('worker: IQaudIO AMP*:     not detected');
}

//----------------------------------------------------------------------------//
workerLog('worker: --');
workerLog('worker: -- Renderer check');
workerLog('worker: --');
//----------------------------------------------------------------------------//
// Renderers
// If any flags are 1 then a reboot/poweroff may have occured while the renderer was active
// In this case ALSA or CamillaDSP volume may be at 100% so let's reset volume to 0.
$result = sqlQuery("SELECT value from cfg_system WHERE param in ('btactive', 'aplactive', 'spotactive',
	'slactive', 'rbactive', 'inpactive')", $dbh);
if ($result[0]['value'] == '1' || $result[1]['value'] == '1' || $result[2]['value'] == '1' ||
	$result[3]['value'] == '1' || $result[4]['value'] == '1' || $result[5]['value'] == '1') {
	// Set Knob volume to 0 for vol.sh downstream
	phpSession('write', 'volknob', '0');
	$result = sqlQuery("UPDATE cfg_system SET value='0' WHERE param='btactive' OR param='aplactive'
		OR param='spotactive' OR param='slactive' OR param='rbactive' OR param='inpactive'", $dbh);
	workerLog('worker: Active flags: at least one true');
	workerLog('worker: Reset flags:  all reset to false');
	workerLog('worker: MPD volume:   set to 0');
}
workerLog('worker: Active flags: all false');
workerLog('worker: Reset flags:  skipped');

//----------------------------------------------------------------------------//
workerLog('worker: --');
workerLog('worker: -- ALSA debug');
workerLog('worker: --');
//----------------------------------------------------------------------------//

// Loopback driver
if ($_SESSION['alsa_loopback'] == 'On') {
	sysCmd('modprobe snd-aloop');
} else {
	sysCmd('modprobe -r snd-aloop');
}

// Dummy PCM driver
if ($_SESSION['feat_bitmask'] & FEAT_MULTIROOM) {
	if (isset($_SESSION['multiroom_tx']) && $_SESSION['multiroom_tx'] == 'On') {
		loadSndDummy();
	}
}

$pad = 12;
$cards = getAlsaCardIDs();
foreach($cards as &$card) {
	$card = str_pad($card, $pad);
}
workerLog('worker: Cards:  0:' . $cards[0] . ' | 1:' . $cards[1]. ' | 2:' . $cards[2]. ' | 3:' . $cards[3]);
workerLog('worker:         4:' . $cards[4] . ' | 5:' . $cards[5]. ' | 6:' . $cards[6]. ' | 7:' . $cards[7]);

$mixers = array();
foreach ($cards as $card) {
	$value = sysCmd('amixer -c ' . $card . ' | awk \'BEGIN{FS="\n"; RS="Simple mixer control"} $0 ~ "pvolume" {print $1}\' | awk -F"\'" \'{print "(" $2 ")";}\'');
	$mixerName = (empty($value[0]) || str_contains($value[0], 'Invalid card number')) ? 'none' : $value[0];
	array_push($mixers, str_pad($mixerName, $pad));
}
workerLog('worker: Mixers: 0:' . $mixers[0] . ' | 1:' . $mixers[1] . ' | 2:' . $mixers[2] . ' | 3:' . $mixers[3]);
workerLog('worker:         4:' . $mixers[4] . ' | 5:' . $mixers[5] . ' | 6:' . $mixers[6] . ' | 7:' . $mixers[7]);

//----------------------------------------------------------------------------//
workerLog('worker: --');
workerLog('worker: -- Audio configuration');
workerLog('worker: --');
//----------------------------------------------------------------------------//

// Audio device and output interface
workerLog('worker: Audio device:  ' . $_SESSION['adevname']);
$audioOutput = getConfiguredAudioOutput();
workerLog('worker: Audio output:  ' . $audioOutput);

// Check for reassigned card number
$actualCardNum = getAlsaCardNumForDevice($_SESSION['adevname']);
if ($actualCardNum == $_SESSION['cardnum']) {
	workerLog('worker: ALSA card:     ' . $_SESSION['cardnum'] . ' (has not changed)');
	workerLog('worker: MPD config:    update not needed');
} else {
	workerLog('worker: ALSA card:     ' . $actualCardNum . ' (reasigned from ' . $_SESSION['cardnum'] . ')');
	// Update card number
	phpSession('write', 'cardnum', $actualCardNum);
	$result = sqlQuery("UPDATE cfg_mpd SET value='" . $actualCardNum . "' WHERE param='device'", sqlConnect());
	// MPD conf update
	if ($_SESSION['multiroom_tx'] == 'On') {
		workerLog('worker: MPD config:    update not needed (trx sender on)');
	} else {
		updMpdConf();
		workerLog('worker: MPD config:    updated');
	}
}
// ALSA mixer
$_SESSION['amixname'] = getAlsaMixerName($_SESSION['adevname']);
workerLog('worker: ALSA mixer     ' . ($_SESSION['amixname'] == 'none' ? 'none exists' : $_SESSION['amixname']));
// ALSA volume
$result = sysCmd('/var/www/util/sysutil.sh get-alsavol ' . '"' . $_SESSION['amixname'] . '"');
if (substr($result[0], 0, 6 ) == 'amixer') {
	phpSession('write', 'alsavolume', 'none');
} else {
	sysCmd('amixer sset ' . '"' . $_SESSION['amixname'] . '"' . ' on' );
	$result[0] = str_replace('%', '', $result[0]);
	phpSession('write', 'alsavolume', $result[0]);
}
if ($_SESSION['alsavolume'] != 'none') {
	if ($_SESSION['mpdmixer'] != 'hardware') {
		setALSAVolTo0dB($_SESSION['alsavolume_max']);
	}
	$result = sysCmd('/var/www/util/sysutil.sh get-alsavol ' . '"' . $_SESSION['amixname'] . '"');
	workerLog('worker: ALSA volume:   ' . $result[0]);
} else {
	workerLog('worker: ALSA volume:   controller not detected');
}
// ALSA maxvol
workerLog('worker: ALSA maxvol:   ' . $_SESSION['alsavolume_max'] . '%');
// ALSA output
workerLog('worker: ALSA output:   ' . ALSA_OUTPUT_MODE_NAME[$_SESSION['alsa_output_mode']] . ' (' . $_SESSION['alsa_output_mode'] . ')');
// ALSA loopback
workerLog('worker: ALSA loopback: ' . lcfirst($_SESSION['alsa_loopback']));
// MPD mixer
$mixerType = ucfirst($_SESSION['mpdmixer']);
$mixerType = CamillaDSP::isMPD2CamillaDSPVolSyncEnabled() ? 'CamillaDSP' : $mixerType;
$mixerType = $mixerType == 'None' ? 'Fixed (0dB)' : $mixerType;
workerLog('worker: MPD mixer      ' . $mixerType);
// Ensure mpdmixer_local = mpdmixer
if ($_SESSION['audioout'] == 'Local') {
	phpSession('write', 'mpdmixer_local', $_SESSION['mpdmixer']);
}
// MPD volumes
// Source select (Analog or S/PDIF) or MPD volume
if (in_array($_SESSION['i2sdevice'], SRC_SELECT_DEVICES)) {
 	if ($_SESSION['audioin'] == 'Local') {
		$volKnob = $_SESSION['volknob_mpd'] != '-1' ? $_SESSION['volknob_mpd'] : $_SESSION['volknob'];
	} else {
		$volKnob = $_SESSION['volknob_preamp'];
	}
} else {
	$volKnob = $_SESSION['volknob_mpd'] != '-1' ? $_SESSION['volknob_mpd'] : $_SESSION['volknob'];
	phpSession('write', 'volknob_mpd', '-1');
	phpSession('write', 'volknob_preamp', '0');
}
sysCmd('/var/www/vol.sh ' . $volKnob);
workerLog('worker: MPD volume:    ' . $volKnob);
workerLog('worker: Saved MPD vol: ' . $_SESSION['volknob_mpd']);
workerLog('worker: Saved SRC vol: ' . $_SESSION['volknob_preamp']);
// Audio formats
if ($cards[$_SESSION['cardnum']] == ALSA_EMPTY_CARD) {
	workerLog('worker: Audio formats: Warning: card ' . $_SESSION['cardnum'] . ' is empty');
} else {
	$_SESSION['audio_formats'] = sysCmd('moodeutl -f')[0];
	workerLog('worker: Audio formats: ' . $_SESSION['audio_formats']);
}
// CamillaDSP
if (!isset($_SESSION['camilladsp_volume_range'])) {
	$_SESSION['camilladsp_volume_range'] = '60';
}
$cdsp = new CamillaDsp($_SESSION['camilladsp'], $_SESSION['cardnum'], $_SESSION['camilladsp_quickconv']);
$cdsp->selectConfig($_SESSION['camilladsp']);
if ($_SESSION['cdsp_fix_playback'] == 'Yes' ) {
	$cdsp->setPlaybackDevice($_SESSION['cardnum'], $_SESSION['alsa_output_mode']);
}
unset($cdsp);
workerLog('worker: CamillaDSP:    ' . rtrim($_SESSION['camilladsp'], '.yml'));
$result = sysCmd("cat /var/lib/cdsp/statefile.yml | grep 'volume' -A1 | grep -e '- ' | awk '/- /{print $2}'");
workerLog('worker: CDSP volume:   ' . number_format($result[0], 1) . ' dB');
workerLog('worker: CDSP volrange: ' . $_SESSION['camilladsp_volume_range'] . ' dB');

//----------------------------------------------------------------------------//
workerLog('worker: --');
workerLog('worker: -- MPD startup');
workerLog('worker: --');
//----------------------------------------------------------------------------//

// MPD conf checks
if (!file_exists('/etc/mpd.conf')) {
	// Missing mpd.conf (rare)
	workerLog('worker: MPD config:         Warning: mpd.conf missing, creating it');
	updMpdConf();
} else {
	// First boot or mangled file
	$lines = file(MPD_CONF);
	if (!str_contains($lines[1], 'This file is managed by moOde')) {
		workerLog('worker: MPD config:         Creating managed mpd.conf');
		updMpdConf();
	}
}

// Start MPD
sysCmd("systemctl start mpd");
workerLog('worker: MPD service:        started');
$sock = openMpdSock('localhost', 6600);
workerLog($sock === false ?
	'worker: MPD port 6600:      connection refused' :
	'worker: MPD port 6600:      accepting connections');
// Ensure valid MPD output config
$mpdOutput = configMpdOutput();
sysCmd('mpc enable only "' . $mpdOutput .'"');
setMpdHttpd();
// Report MPD outputs
$mpdOutputs = getMpdOutputs($sock);
foreach ($mpdOutputs as $mpdOutput) {
	workerLog('worker: MPD ' . $mpdOutput);
}
// MPD crossfade
workerLog('worker: MPD crossfade:      ' . ($_SESSION['mpdcrossfade'] == '0' ? 'off' : $_SESSION['mpdcrossfade'] . ' secs'));
sendMpdCmd($sock, 'crossfade ' . $_SESSION['mpdcrossfade']);
$resp = readMpdResp($sock);
// Ignore CUE files
setCuefilesIgnore($_SESSION['cuefiles_ignore']);
workerLog('worker: MPD ignore CUE:     ' . ($_SESSION['cuefiles_ignore'] == '1' ? 'yes' : 'no'));
// On first boot update SDCARD dir to pick up Stereo Test file and then clear/load Default Playlist
if ($_SESSION['first_use_help'] == 'y,y') {
	sendMpdCmd($sock, 'update SDCARD');
	$resp = readMpdResp($sock);
	workerLog('worker: MPD first boot:     SDCARD scanned');
	sleep(1);
	sendMpdCmd($sock, 'clear');
	$resp = readMpdResp($sock);
	sendMpdCmd($sock, 'load "Default Playlist"');
	$resp = readMpdResp($sock);
	workerLog('worker: MPD first boot:     default playlist loaded');
}
// MPD/CamillaDSP volume sync
workerLog('worker: MPD CDSP volsync:   ' . lcfirst($_SESSION['camilladsp_volume_sync']));
$serviceCmd = CamillaDSP::isMPD2CamillaDSPVolSyncEnabled() ? 'start' : 'stop';
sysCmd('systemctl ' . $serviceCmd .' mpd2cdspvolume');

//----------------------------------------------------------------------------//
workerLog('worker: --');
workerLog('worker: -- Music sources');
workerLog('worker: --');
//----------------------------------------------------------------------------//

// USB sources
// USB auto-mounter (udisks-glue (Default) or devmon for < Pi-3)
workerLog('worker: USB auto-mount: ' . $_SESSION['usb_auto_mounter']);
$usbDrives = sysCmd('ls /media');
if (empty($usbDrives)) {
	workerLog('worker: USB drives:     no drives found');
} else {
	foreach ($usbDrives as $usbDrive) {
		workerLog('worker: USB drive:      ' . $usbDrive);
	}
}
// NAS sources
$mounts = sqlRead('cfg_source', $dbh);
if ($mounts === true) { // Empty result
	workerLog('worker: NAS sources:    no music sources defined');
} else {
	foreach ($mounts as $mp) {
		workerLog('worker: NAS source:     ' . $mp['name']);
	}
	$result = sourceMount('mountall');
	workerLog('worker: NAS mount:      ' . lcfirst($result));
}

//----------------------------------------------------------------------------//
workerLog('worker: --');
workerLog('worker: -- Feature availability');
workerLog('worker: --');
//----------------------------------------------------------------------------//

// Configure input select
if ($_SESSION['feat_bitmask'] & FEAT_INPSOURCE) {
	$status = 'available';
	$src = $_SESSION['audioin'] == 'Local' ? 'MPD' : $_SESSION['audioin'];
	$output = $_SESSION['adevname'];
	$status .= ', src ' . $src . ', out ' . $output;
	if (in_array($_SESSION['i2sdevice'], SRC_SELECT_DEVICES)) {
		setAudioIn($_SESSION['audioin']);
	}
} else {
	$status = 'n/a';
}
workerLog('worker: Input select:    ' . $status);

// Start bluetooth controller and pairing agent
if ($_SESSION['feat_bitmask'] & FEAT_BLUETOOTH) {
	if (isset($_SESSION['btsvc']) && $_SESSION['btsvc'] == 1) {
		$status = startBluetooth();
		if ($status == 'started') {
			if (isset($_SESSION['pairing_agent']) && $_SESSION['pairing_agent'] == 1) {
				sysCmd('/var/www/daemon/blu_agent.py --agent --disable_pair_mode_switch --pair_mode --wait_for_bluez >/dev/null 2>&1 &');
				$status .= ', Pairing Agent started';
			}
		}
	} else {
		$status = 'available';
	}
} else {
	$status = 'n/a';
}
// Bluetooth SBC quality
if (!isset($_SESSION['bluez_sbc_quality'])) {
	$_SESSION['bluez_sbc_quality'] = 'xq+';
}
// Bluetooth ALSA output mode
if (!isset($_SESSION['bt_alsa_output_mode'])) {
	$_SESSION['bt_alsa_output_mode'] = '_audioout';
}
$status .= ', ALSA mode ' . BT_ALSA_OUTPUT_MODE_NAME[$_SESSION['bt_alsa_output_mode']];
workerLog('worker: Bluetooth:       ' . $status);

// Start airplay renderer
if ($_SESSION['feat_bitmask'] & FEAT_AIRPLAY) {
	if (isset($_SESSION['airplaysvc']) && $_SESSION['airplaysvc'] == 1) {
		$status = 'started';
		startAirPlay();
	} else {
		$status = 'available';
	}
} else {
	$status = 'n/a';
}
workerLog('worker: AirPlay:         ' . $status);

// Start Spotify renderer
if ($_SESSION['feat_bitmask'] & FEAT_SPOTIFY) {
	if (isset($_SESSION['spotifysvc']) && $_SESSION['spotifysvc'] == 1) {
		$status = 'started';
		startSpotify();
	} else {
		$status = 'available';
	}
} else {
	$status = 'n/a';
}
workerLog('worker: Spotify Connect: ' . $status);

// Start Squeezelite renderer
if ($_SESSION['feat_bitmask'] & FEAT_SQUEEZELITE) {
	if (isset($_SESSION['slsvc']) && $_SESSION['slsvc'] == 1) {
		$status = 'started';
		cfgSqueezelite();
		startSqueezeLite();
	} else {
		$status = 'available';
	}
} else {
	$status = 'n/a';
}
workerLog('worker: Squeezelite:     ' . $status);

// Start RroonBridge renderer
if ($_SESSION['feat_bitmask'] & FEAT_ROONBRIDGE) {
	if ($_SESSION['roonbridge_installed'] == 'yes') {
		if (isset($_SESSION['rbsvc']) && $_SESSION['rbsvc'] == 1) {
			$status = 'started';
			startRoonBridge();
		} else {
			$status = 'available';
		}
	} else {
		$status = 'not installed';
	}
} else {
	$status = 'n/a';
}
workerLog('worker: RoonBridge:      ' . $status);

// Start Multiroom audio
if ($_SESSION['feat_bitmask'] & FEAT_MULTIROOM) {
	// Sender
	if (isset($_SESSION['multiroom_tx']) && $_SESSION['multiroom_tx'] == 'On') {
		$statusTx = 'started';
		startMultiroomSender();
	} else {
		$statusTx = 'available';
	}
	// Receiver
	if (isset($_SESSION['multiroom_rx']) && $_SESSION['multiroom_rx'] == 'On') {
		$statusRx = 'started';
		startMultiroomReceiver();
	} else {
		$statusRx = 'available';
	}
	// Status
	if ($statusTx == 'available' && $statusRx == 'available') {
		$status = 'available';
	} else if ($statusTx == 'started' && $statusRx == 'started') {
		// NOTE: Having both Tx and Rx running on the same host is not supported but may be in the future
		$status = 'started sender and receiver';
	} else if ($statusTx == 'started') {
		$status = 'started sender';
	} else {
		$status = 'started receiver';
	}
} else {
	$status = 'n/a';
}
workerLog('worker: Multiroom:       ' . $status);

// Start UPnP renderer
if ($_SESSION['feat_bitmask'] & FEAT_UPMPDCLI) {
	if (isset($_SESSION['upnpsvc']) && $_SESSION['upnpsvc'] == 1) {
		$status = 'started';
		startUPnP();
	} else {
		$status = 'available';
	}
} else {
	$status = 'n/a';
}
workerLog('worker: UPnP client:     ' . $status);

// Start GPIO button handler
if ($_SESSION['feat_bitmask'] & FEAT_GPIO) {
	if (isset($_SESSION['gpio_svc']) && $_SESSION['gpio_svc'] == 1) {
		$status = 'started';
		startGpioBtnHandler();
	} else {
		$status = 'available';
	}
} else {
	$status = 'n/a';
}
workerLog('worker: GPIO buttons:    ' . $status);

// Start HTTPS mode
if ($_SESSION['feat_bitmask'] & FEAT_HTTPS) {
	if (!isset($_SESSION['nginx_https_only'])) {
		$_SESSION['nginx_https_only'] = '0';
		$_SESSION['nginx_cert_type'] = 'automatic';
		$_SESSION['nginx_hsts_policy'] = '0';
	}
	if ($_SESSION['nginx_https_only'] == '1') {
		$status = 'started';
		if (!file_exists('/etc/ssl/certs/moode.crt')) {
			$status .= ', Error: no certificate file found';
		} else {
			$cmd = 'openssl x509 -text -noout -in /etc/ssl/certs/moode.crt | grep "Subject: CN" | cut -d "=" -f 2';
			$CN = trim(sysCmd($cmd)[0]);
			// Check for host name change
			if ($CN != $_SESSION['hostname'] . '.local') {
				if ($_SESSION['nginx_cert_type'] == 'automatic') {
					sysCmd('/var/www/util/gen-cert.sh');
					sysCmd('systemctl restart nginx');
					$status .= ', Self-signed cert created for host: ' . $_SESSION['hostname'];
				} else {
					$status .= ', Warning: Manually created cert exists but CN does not match host: ' . $_SESSION['hostname'];
				}
			} else {
				$status .= ', Using ' . ucfirst($_SESSION['nginx_cert_type']) . ' cert for host: ' . $_SESSION['hostname'];
			}
		}
	} else {
		$status = 'available';
	}
} else {
	$status = 'n/a';
}
workerLog('worker: HTTPS mode:      ' . $status);

// Start stream recorder
if ($_SESSION['feat_bitmask'] & FEAT_RECORDER) {
	if ($_SESSION['recorder_status'] == 'Not installed') {
		$status = 'not installed';
	} else if ($_SESSION['recorder_status'] == 'On') {
		$status = 'started';
		sysCmd('mpc enable "' . STREAM_RECORDER . '"');
	} else {
		$status = 'available';
	}
} else {
	$status = 'n/a';
}
workerLog('worker: Stream recorder: ' . $status);

//----------------------------------------------------------------------------//
workerLog('worker: --');
workerLog('worker: -- Peripherals');
workerLog('worker: --');
//----------------------------------------------------------------------------//

// Start local display
sysCmd("sed -i '/User=/c \User=" . $_SESSION['user_id'] . "' /lib/systemd/system/localui.service");
sysCmd('systemctl daemon-reload');
if ($_SESSION['localui'] == '1') {
	startLocalUI();
}
workerLog('worker: Local display:   ' . ($_SESSION['localui'] == '1' ? 'on' : 'off'));
// On-screen keyboard (Enable is text on the button)
if (!isset($_SESSION['on_screen_kbd'])) {
	$_SESSION['on_screen_kbd'] = 'Enable';
}
workerLog('worker: On-screen kbd:   ' . ($_SESSION['on_screen_kbd'] == 'Enable' ? 'off' : 'on'));
// Toggle CoverView (System Config)
if (!isset($_SESSION['toggle_coverview'])) {
	$_SESSION['toggle_coverview'] = '-off';
} else {
	$_SESSION['toggle_coverview'] = $_SESSION['auto_coverview'];
}

// Start rotary encoder
if (!isset($_SESSION['rotaryenc'])) {
	$_SESSION['rotaryenc'] = '0';
}
if ($_SESSION['rotaryenc'] == '1') {
	sysCmd('systemctl start rotenc');
}
workerLog('worker: Rotary encoder:  ' . ($_SESSION['rotaryenc'] == '1' ? 'on' : 'off'));

// Log USB volume knob on/off state
workerLog('worker: USB volume knob: ' . ($_SESSION['usb_volknob'] == '1' ? 'on' : 'off'));

// Start LCD updater engine
if ($_SESSION['lcdup'] == '1') {
	startLcdUpdater();
}
workerLog('worker: LCD updater:     ' . ($_SESSION['lcdup'] == '1' ? 'on' : 'off'));

//----------------------------------------------------------------------------//
workerLog('worker: --');
workerLog('worker: -- Miscellaneous');
workerLog('worker: --');
//----------------------------------------------------------------------------//

// Software update auto-check
if (!isset($_SESSION['updater_auto_check'])) {
	$_SESSION['updater_auto_check'] = 'Off';
}
$validIPAddress = ($_SESSION['ipaddress'] != '0.0.0.0' && $wlan0Ip[0] != '172.24.1.1');
// NOTE: updaterAutoCheck() logs status
$_SESSION['updater_available_update'] = updaterAutoCheck($validIPAddress);

// Automatic CoverView (Preferences)
workerLog('worker: Auto-CoverView:    ' . ($_SESSION['auto_coverview'] == '-on' ? 'on' : 'off'));

// CoverView screen saver timeout
workerLog('worker: CoverView timeout: ' . $_SESSION['scnsaver_timeout']);

// Auto-shuffle
workerLog('worker: Auto-shuffle:      ' . ($_SESSION['ashufflesvc'] == '1' ? 'on' : 'off'));

// Auto-play: start auto-shuffle random play or auto-play last played item
if ($_SESSION['autoplay'] == '1') {
	if ($_SESSION['ashuffle'] == '1') {
		workerLog('worker: Auto-play:         on, via auto-shuffle');
		startAutoShuffle();
	} else {
		$status = getMpdStatus($sock);
		//workerLog(print_r($status, true));
		sendMpdCmd($sock, 'playid ' . $status['songid']);
		$resp = readMpdResp($sock);
		workerLog('worker: Auto-play:         on, via playid ' . $status['songid']);
	}
} else {
	sendMpdCmd($sock, 'stop');
	$resp = readMpdResp($sock);
	// Turn off Auto-shuffle based random play if it's on
	if ($_SESSION['ashuffle'] == '1') {
		phpSession('write', 'ashuffle', '0');
		sendMpdCmd($sock, 'consume 0');
		$resp = readMpdResp($sock);
		workerLog('worker: Auto-play:         off, random toggle reset to off');
	} else {
		workerLog('worker: Auto-play:         off');
	}
}
// Start Web SSH server
if ($_SESSION['shellinabox'] == '1') {
	sysCmd('systemctl start shellinabox');
}
workerLog('worker: Web SSH server:    ' .($_SESSION['shellinabox'] == '1' ? 'on' : 'off'));

// Maintenance task
workerLog('worker: Maintenance task:  ' . ($_SESSION['maint_interval'] / 60) . ' mins');

// Reset view to Playback (assumes the WebUI is up and connected)
$view = explode(',', $_SESSION['current_view'])[0] != 'playback' ? 'playback,' . $_SESSION['current_view'] : $_SESSION['current_view'];
sendEngCmd('reset_view');
workerLog('worker: Current view:      reset to Playback');

// Reset in-place update flag
phpSession('write', 'inplace_upd_applied', '0');

//----------------------------------------------------------------------------//
// Initialize some session vars
//----------------------------------------------------------------------------//

// TRX Config adv options toggle
$_SESSION['rx_adv_toggle'] = 'Show';
$_SESSION['tx_adv_toggle'] = 'Show';

// Library list positions
phpSession('write', 'lib_pos', '-1,-1,-1');
phpSession('write', 'radio_pos', '-1');
phpSession('write', 'playlist_pos', '-1');

// Library scope
if (!isset($_SESSION['lib_scope'])) {
	$_SESSION['lib_scope'] = 'all';
}

// Library active search
if (!isset($_SESSION['lib_active_search'])) {
	$_SESSION['lib_active_search'] = 'None';
}

// Worker sleep interval
if (!isset($_SESSION['worker_responsiveness'])) {
	$_SESSION['worker_responsiveness'] = 'Default';
}
// Mount monitor
if (!isset($_SESSION['fs_mountmon'])) {
	$_SESSION['fs_mountmon'] = 'Off';
}

// MPD radio stream monitor
if (!isset($_SESSION['mpd_monitor_svc'])) {
	$_SESSION['mpd_monitor_svc'] = 'Off';
	$_SESSION['mpd_monitor_opt'] = '6,Yes,3'; // sleep_interval,resume_play,msg_threshold
}

//----------------------------------------------------------------------------//
// Globals section
// NOTE: These globals are used in the worker event loop functions
//----------------------------------------------------------------------------//

// Clock radio
$clkradio_start_time = substr($_SESSION['clkradio_start'], 0, 8); // Parse out the time (HH,MM,AP)
$clkradio_stop_time = substr($_SESSION['clkradio_stop'], 0, 8);
$clkradio_start_days = explode(',', substr($_SESSION['clkradio_start'], 9)); // Parse out the days (M,T,W,T,F,S,S)
$clkradio_stop_days = explode(',', substr($_SESSION['clkradio_stop'], 9));

// Renderer active
$aplactive = '0';
$spotactive = '0';
$slactive = '0';
$rbactive = '0';
$inpactive = '0';

// Library update, MPD database regen
$check_library_update = '0';
$check_library_regen = 0;

// Maintenance task
$maint_interval = $_SESSION['maint_interval'];

// Screen saver
$scnactive = '0';
$scnsaver_timeout = $_SESSION['scnsaver_timeout'];

// Inizialize job queue
$_SESSION['w_queue'] = '';
$_SESSION['w_queueargs'] = '';
$_SESSION['w_lock'] = 0;
$_SESSION['w_active'] = 0;

//----------------------------------------------------------------------------//
// Finish regular startup
//----------------------------------------------------------------------------//

// Close MPD socket
closeMpdSock($sock);

// Close session
phpSession('close');

// Check and report permissions on the session file
phpSessionCheck();

//----------------------------------------------------------------------------//
// Auto-config section
//----------------------------------------------------------------------------//

// Auto restore backup if present
// Do it just before autocfg so an autocfg always overrules backup settings
$restoreBackup = false;
if (file_exists('/boot/moodebackup.zip')) {
	$restoreLog = '/var/log/moode_backup_restore.log';
	sysCmd('/var/www/util/backup_manager.py --restore /boot/moodebackup.zip > ' . $restoreLog);
	sysCmd('rm /boot/moodebackup.zip');
	sysCmd('sync');
	$restoreBackup = true; // Don't reboot here in case autocfg is also present
}
// Auto-configure if indicated
// NOTE: This is done near the end of startup because autoConfig() uses the wpa_passphrase utility which requires
// sufficient kernel entropy in order to generate the PSK. If there is not enough entropy, wpa_passphrase returns
// the input password instead of a PSK.
if (file_exists('/boot/moodecfg.ini')) {
	sysCmd('truncate ' . AUTOCFG_LOG . ' --size 0');

	phpSession('open');
	autoConfig('/boot/moodecfg.ini');
	phpSession('close');

	sysCmd('sync');
	autoCfgLog('autocfg: System restarted');
	sysCmd('reboot');
} else if ($restoreBackup) {
	sysCmd('reboot');
}

//----------------------------------------------------------------------------//
workerLog('worker: --');
workerLog('worker: -- Startup complete ');
workerLog('worker: --');
//----------------------------------------------------------------------------//

// Start mount monitor
sysCmd('killall -s 9 mountmon.php');
if ($_SESSION['fs_mountmon'] == 'On') {
	sysCmd('/var/www/daemon/mountmon.php > /dev/null 2>&1 &');
}
workerLog('worker: Mount monitor:    ' . ($_SESSION['fs_mountmon'] == 'On' ? 'started' : 'off'));

// Start MPD radio stream monitor
sysCmd('killall -s 9 mpdmon.php');
if ($_SESSION['mpd_monitor_svc'] == 'On') {
	sysCmd('/var/www/daemon/mpdmon.php "' . $_SESSION['mpd_monitor_opt'] . '" > /dev/null 2>&1 &');
}
workerLog('worker: Radio monitor:    ' . ($_SESSION['mpd_monitor_svc'] == 'On' ? 'started' : 'off'));

// Start watchdog monitor
sysCmd('killall -s 9 watchdog.sh');
$result = sqlQuery("UPDATE cfg_system SET value='1' WHERE param='wrkready'", $dbh);
sysCmd('/var/www/daemon/watchdog.sh ' . WATCHDOG_SLEEP . ' > /dev/null 2>&1 &');
workerLog('worker: Watchdog monitor: started');

// Sleep intervals
workerLog('worker: Responsiveness:   ' . $_SESSION['worker_responsiveness']);
debugLog('worker: Sleep intervals:  ' .
	'worker=' . WORKER_SLEEP / 1000000 . ', ' .
	'waitworker=' . WAITWORKER_SLEEP / 1000000 . ', ' .
	'watchdog=' . WATCHDOG_SLEEP . ', ' .
	'mountmon=' . MOUNTMON_SLEEP . ', ' .
	'mpdmon=' . explode(',', $_SESSION['mpd_monitor_opt'])[0] . ', ' .
	'gpiobuttons=' . GPIOBUTTONS_SLEEP
);

// Worker ready
workerLog('worker: Ready');

//----------------------------------------------------------------------------//
// WORKER EVENT LOOP
//----------------------------------------------------------------------------//

while (true) {
	usleep(WORKER_SLEEP);

	phpSession('open');

	if ($_SESSION['scnsaver_timeout'] != 'Never') {
		chkScnSaver();
	}
	if ($_SESSION['maint_interval'] != 0) {
		chkMaintenance();
	}
	if ($_SESSION['btsvc'] == '1' && $_SESSION['audioout'] == 'Local') {
		chkBtActive();
	}
	if ($_SESSION['airplaysvc'] == '1') {
		chkAplActive();
	}
	if ($_SESSION['spotifysvc'] == '1') {
		chkSpotActive();
	}
	if ($_SESSION['slsvc'] == '1') {
		chkSlActive();
	}
	if ($_SESSION['rbsvc'] == '1') {
		chkRbActive();
	}
	if ($_SESSION['multiroom_rx'] == 'On') {
		chkRxActive();
	}
	if ($_SESSION['i2sdevice'] == 'HiFiBerry DAC+ ADC' || strpos($_SESSION['i2sdevice'], 'Audiophonics ES9028/9038 DAC') !== -1) {
		chkInpActive();
	}
	if ($_SESSION['i2sdevice'] == 'Allo Boss 2 DAC') {
		updBoss2DopVolume();
	}
	if ($_SESSION['extmeta'] == '1') {
		updExtMetaFile();
	}
 	if ($_SESSION['clkradio_mode'] == 'Clock Radio') {
		chkClockRadio();
	}
 	if ($_SESSION['clkradio_mode'] == 'Sleep Timer') {
		chkSleepTimer();
	}
	if ($_SESSION['playhist'] == 'Yes') {
		updPlayHistory();
	}
	if ($GLOBALS['check_library_update'] == '1') {
		chkLibraryUpdate();
	}
	if ($GLOBALS['check_library_regen'] == '1') {
		chkLibraryRegen();
	}
	if ($_SESSION['w_active'] == 1 && $_SESSION['w_lock'] == 0) {
		runQueuedJob();
	}

	phpSession('close', '', '', ', caller: worker.php end of job while loop');
}

//----------------------------------------------------------------------------//
// WORKER FUNCTIONS
//----------------------------------------------------------------------------//

// Activate if timeout is set and no other overlay is active and MPD is not playing
function chkScnSaver() {
	$sock = openMpdSock('localhost', 6600);
	$mpdState = getMpdStatus($sock)['state'];
	closeMpdSock($sock);
	if ($mpdState == 'play') {
		$GLOBALS['scnsaver_timeout'] = $_SESSION['scnsaver_timeout'];
	}

	if ($GLOBALS['scnsaver_timeout'] != 'Never' && $_SESSION['btactive'] == '0' && $GLOBALS['aplactive'] == '0'
		&& $GLOBALS['spotactive'] == '0' && $GLOBALS['slactive'] == '0' && $GLOBALS['rbactive'] == '0'
		&& $_SESSION['rxactive'] == '0' && $GLOBALS['inpactive'] == '0' && $mpdState != 'play') {
		if ($GLOBALS['scnactive'] == '0') {
			$GLOBALS['scnsaver_timeout'] = $GLOBALS['scnsaver_timeout'] - (WORKER_SLEEP / 1000000);
			if ($GLOBALS['scnsaver_timeout'] <= 0) {
				$GLOBALS['scnsaver_timeout'] = $_SESSION['scnsaver_timeout']; // Reset timeout
				$GLOBALS['scnactive'] = '1';
				sendEngCmd('scnactive1');
			}
		}
	}
	//workerLog($mpdState . ', ' . $GLOBALS['scnsaver_timeout'] . ', ' . $GLOBALS['scnactive']);
}

function chkMaintenance() {
	$GLOBALS['maint_interval'] = $GLOBALS['maint_interval'] - (WORKER_SLEEP / 1000000);

	if ($GLOBALS['maint_interval'] <= 0) {
		// Clear logs
		$result = sysCmd('/var/www/util/sysutil.sh "clear-syslogs"');
		if (!empty($result)) {
			workerLog('worker: Maintenance: Warning: Problem clearing system logs');
			workerLog('worker: Maintenance: ' . $result[0]);
		}

		// Compact SQLite database
		$result = sysCmd('sqlite3 /var/local/www/db/moode-sqlite3.db "vacuum"');
		if (!empty($result)) {
			workerLog('worker: Maintenance: Warning: Problem compacting SQLite database');
			workerLog('worker: Maintenance: ' . $result[0]);
		}

		// Purge temp or unwanted resources
		sysCmd('find /var/www/ -type l -delete'); // There shouldn't be any symlinks in the web root

		// Purge spurious session files
		// These files are created when chromium starts/restarts
		// The only valid file is the one corresponding to $_SESSION['sessionid']
		$dir = '/var/local/php/';
		$files = scandir($dir);
		foreach ($files as $file) {
			if (substr($file, 0, 5) == 'sess_' && $file != 'sess_' . $_SESSION['sessionid']) {
				debugLog('worker: Maintenance: Purged spurious session file (' . $file . ')');
				syscmd('rm ' . $dir . $file);
			}
		}

		$GLOBALS['maint_interval'] = $_SESSION['maint_interval'];

		debugLog('worker: Maintenance completed');
	}
}

function chkBtActive() {
	$result = sqlQuery("SELECT value FROM cfg_system WHERE param='inpactive'", $GLOBALS['dbh']);
	if ($result[0]['value'] == '1') {
		return; // Bail if Input is active
	}

	$result = sysCmd('pgrep -l bluealsa-aplay');
	if (strpos($result[0], 'bluealsa-aplay') !== false) {
		// Do this section only once
		if ($_SESSION['btactive'] == '0') {
			phpSession('write', 'btactive', '1');
			$GLOBALS['scnsaver_timeout'] = $_SESSION['scnsaver_timeout'];
			sysCmd('mpc stop'); // For added robustness
			sendEngCmd('btactive1');

			// Local
			if ($_SESSION['alsavolume'] != 'none') {
		        setALSAVolTo0dB($_SESSION['alsavolume_max']);
			}
	        if ($_SESSION['camilladsp'] != 'off') {
	            CamillaDSP::setCDSPVolTo0dB('-6.0'); // Attenuate a bit for Bluetooth volume
	        }

			// Multiroom receivers
			if ($_SESSION['multiroom_tx'] == 'On') {
				$rxHostNames = explode(', ', $_SESSION['rx_hostnames']);
				$rxAddresses = explode(' ', $_SESSION['rx_addresses']);
				for ($i = 0; $i < count($rxAddresses); $i++) {
					if (false === ($result = file_get_contents('http://' . $rxAddresses[$i] . '/command/?cmd=' . rawurlencode('trx-control.php -set-alsavol ' . $_SESSION['alsavolume_max'])))) {
		    			if (false === ($result = file_get_contents('http://' . $rxAddresses[$i] . '/command/?cmd=' . rawurlencode('trx-control.php -set-alsavol ' . $_SESSION['alsavolume_max'])))) {
		    				workerLog("worker: chkBtActive(): send 'set-alsavol alsavolume_max' failed: " . $rxHostNames[$i]);
		    			}
		    		}
				}
			}
		}
	} else {
		// Do this section only once
		if ($_SESSION['btactive'] == '1') {
			phpSession('write', 'btactive', '0');
			sendEngCmd('btactive0');

			// Local
			sysCmd('/var/www/vol.sh -restore');

			// Multiroom receivers
			if ($_SESSION['multiroom_tx'] == 'On') {
				$rxHostNames = explode(', ', $_SESSION['rx_hostnames']);
				$rxAddresses = explode(' ', $_SESSION['rx_addresses']);
				for ($i = 0; $i < count($rxAddresses); $i++) {
					if (false === ($result = file_get_contents('http://' . $rxAddresses[$i] . '/command/?cmd=' . rawurlencode('vol.sh -restore')))) {
		    			if (false === ($result = file_get_contents('http://' . $rxAddresses[$i] . '/command/?cmd=' . rawurlencode('vol.sh -restore')))) {
		    				workerLog("worker: chkBtActive(): send 'vol.sh -restore' failed: " . $rxHostNames[$i]);
		    			}
		    		}
				}
			}

			if ($_SESSION['rsmafterbt'] == '1') {
				sysCmd('mpc play');
			}
		}
	}
}

function chkAplActive() {
	// Get directly from sql since external spspre.sh and spspost.sh scripts don't update the session
	$result = sqlQuery("SELECT value FROM cfg_system WHERE param='aplactive'", $GLOBALS['dbh']);
	if ($result[0]['value'] == '1') {
		// Do this section only once
		if ($GLOBALS['aplactive'] == '0') {
			$GLOBALS['aplactive'] = '1';
			$GLOBALS['scnsaver_timeout'] = $_SESSION['scnsaver_timeout'];
			sendEngCmd('aplactive1');
		}
	} else {
		// Do this section only once
		if ($GLOBALS['aplactive'] == '1') {
			$GLOBALS['aplactive'] = '0';
			sendEngCmd('aplactive0');
		}
	}
}

function chkSpotActive() {
	// Get directly from sql since external spotevent.sh script does not update the session
	$result = sqlQuery("SELECT value FROM cfg_system WHERE param='spotactive'", $GLOBALS['dbh']);
	if ($result[0]['value'] == '1') {
		// Do this section only once
		if ($GLOBALS['spotactive'] == '0') {
			$GLOBALS['spotactive'] = '1';
			$GLOBALS['scnsaver_timeout'] = $_SESSION['scnsaver_timeout'];
			sendEngCmd('spotactive1');
		}
	} else {
		// Do this section only once
		if ($GLOBALS['spotactive'] == '1') {
			$GLOBALS['spotactive'] = '0';
			sendEngCmd('spotactive0');
		}
	}
}

function chkSlActive() {
	// Get directly from sql since external slpower.sh script does not update the session
	$result = sqlQuery("SELECT value FROM cfg_system WHERE param='slactive'", $GLOBALS['dbh']);
	if ($result[0]['value'] == '1') {
		// Do this section only once
		if ($GLOBALS['slactive'] == '0') {
			$GLOBALS['slactive'] = '1';
			$GLOBALS['scnsaver_timeout'] = $_SESSION['scnsaver_timeout'];
			sendEngCmd('slactive1');
		}
	} else {
		// Do this section only once
		if ($GLOBALS['slactive'] == '1') {
			$GLOBALS['slactive'] = '0';
			sendEngCmd('slactive0');
		}
	}
}

function chkRbActive() {
	$result = sysCmd('pgrep -c mono-sgen');
	if ($result[0] > 0) {
		$rnd_not_playing = ($_SESSION['btactive'] == '0' && $GLOBALS['aplactive'] == '0' && $GLOBALS['spotactive'] == '0'
			&& $GLOBALS['slactive'] == '0' && $_SESSION['rxactive'] == '0' && $GLOBALS['inpactive'] == '0');
		$mpd_not_playing = empty(sysCmd('mpc status | grep playing')[0]) ? true : false;
		$alsa_out_active = sysCmd('cat /proc/asound/card' . $_SESSION['cardnum'] . '/pcm0p/sub0/hw_params')[0] == 'closed' ? false : true;
		//workerLog('rnp:' . ($rnd_not_playing ? 'T' : 'F') . '|' . 'mnp:' . ($mpd_not_playing ? 'T' : 'F') . '|' . 'aoa:' . ($alsa_out_active ? 'T' : 'F'));
		if ($rnd_not_playing && $mpd_not_playing && $alsa_out_active) {
			// Do this section only once
			if ($GLOBALS['rbactive'] == '0') {
				$GLOBALS['rbactive'] = '1';
				phpSession('write', 'rbactive', '1');
				$GLOBALS['scnsaver_timeout'] = $_SESSION['scnsaver_timeout'];
				sendEngCmd('rbactive1');
			}
		} else {
			// Do this section only once
			if ($GLOBALS['rbactive'] == '1') {
				$GLOBALS['rbactive'] = '0';
				phpSession('write', 'rbactive', '0');
				sendEngCmd('rbactive0');
				sysCmd('/var/www/vol.sh -restore');
				if ($_SESSION['rsmafterrb'] == 'Yes') {
					sysCmd('mpc play');
				}
			}
		}
	}
}

// Multiroom receiver
function chkRxActive() {
	$result = sysCmd('pgrep -c rx');
	if ($result[0] > 0) {
		// Do this section only once
		if ($_SESSION['rxactive'] == '0') {
			phpSession('write', 'rxactive', '1');
			$GLOBALS['scnsaver_timeout'] = $_SESSION['scnsaver_timeout'];
			sendEngCmd('rxactive1');
		}
	}
}

function chkInpActive() {
	//$result = sysCmd('pgrep -l alsaloop');
	//if (strpos($result[0], 'alsaloop') !== false) {
	if ($_SESSION['audioin'] != 'Local') {
		// Do this section only once
		if ($GLOBALS['inpactive'] == '0') {
			phpSession('write', 'inpactive', '1');
			$GLOBALS['inpactive'] = '1';
			$GLOBALS['scnsaver_timeout'] = $_SESSION['scnsaver_timeout'];
			sendEngCmd('inpactive1');
		}
	} else {
		// Do this section only once
		if ($GLOBALS['inpactive'] == '1') {
			phpSession('write', 'inpactive', '0');
			$GLOBALS['inpactive'] = '0';
			sendEngCmd('inpactive0');
		}
	}
}

function updBoss2DopVolume () {
	$masterVol = sysCmd('/var/www/util/sysutil.sh get-alsavol Master')[0];
	$digitalVol = sysCmd('/var/www/util/sysutil.sh get-alsavol Digital')[0];

	if ($digitalVol != $masterVol) {
		sysCmd('amixer -c 0 sset Digital ' . $masterVol);
		//workerLog('Boss 2 Digital volume sync');
	}
}

function updExtMetaFile() {
	$fileData = parseDelimFile(file_get_contents('/var/local/www/currentsong.txt'), '=');

	if ($GLOBALS['aplactive'] == '1') {
		$renderer = 'AirPlay Active';
	} else if ($GLOBALS['spotactive'] == '1') {
		$renderer = 'Spotify Active';
	} else if ($GLOBALS['slactive'] == '1') {
		$renderer = 'Squeezelite Active';
	} else if ($GLOBALS['rbactive'] == '1') {
		$renderer = 'Roonbridge Active';
	} else if ($GLOBALS['inpactive'] == '1') {
		$renderer = $_SESSION['audioin'] . ' Input Active';
	} else if ($_SESSION['btactive'] == '1' && $_SESSION['audioout'] == 'Local') {
		$renderer = 'Bluetooth Active';
	} else {
		$renderer = '';
	}

	if (!empty($renderer)) {
		//workerLog('worker: Renderer active');
		$hwParams = getAlsaHwParams(getAlsaCardNumForDevice($_SESSION['adevname']));

		if ($hwParams['status'] == 'active') {
			$hwParamsFormat = 'PCM ' . $hwParams['format'] . '/' . $hwParams['rate'] . ' kHz, 2ch';
		} else if ($_SESSION['multiroom_tx'] == 'On') {
			$hwParamsFormat = 'PCM 16/48 kHz, 2ch (Multiroom sender)';
		} else {
			$hwParamsFormat = 'Not playing';
		}

		if ($fileData['file'] != $renderer) {
			//workerLog('worker: Update currentsong.txt file (Renderer)');
			$fh = fopen('/tmp/currentsong.txt', 'w');
			$data = 'file=' . $renderer . "\n";
			$data .= 'outrate=' . $hwParamsFormat . "\n"; ;
			fwrite($fh, $data);
			fclose($fh);
			rename('/tmp/currentsong.txt', '/var/local/www/currentsong.txt');
            chmod('/var/local/www/currentsong.txt', 0666);
		}
	} else {
		//workerLog('worker: MPD active');
		$sock = openMpdSock('localhost', 6600);
		$status = getMpdStatus($sock);
		$current = enhanceMetadata($status, $sock, 'worker_php');
		closeMpdSock($sock);

		if (
			$fileData['state'] != $current['state'] ||
			$fileData['title'] != $current['title'] ||
			$fileData['album'] != $current['album'] ||
			$fileData['volume'] != $_SESSION['volknob'] ||
			$fileData['mute'] != $_SESSION['volmute'] ||
			$fileData['outrate'] != $current['output']
		) {
			//workerLog('worker: Update currentsong.txt file (MPD)');
			$fh = fopen('/tmp/currentsong.txt', 'w');
			// Default
			$data = 'file=' . $current['file'] . "\n";
			$data .= 'artist=' . $current['artist'] . "\n";
			$data .= 'album=' . $current['album'] . "\n";
			$data .= 'title=' . $current['title'] . "\n";
			$data .= 'coverurl=' . $current['coverurl'] . "\n";
			// Xtra tags
			$data .= 'track=' . $current['track'] . "\n";
			$data .= 'date=' . $current['date'] . "\n";
			$data .= 'composer=' . $current['composer'] . "\n";
			// Other
			$data .= 'encoded=' . $current['encoded'] . "\n";
			$data .= 'bitrate=' . $current['bitrate'] . "\n";
			//$data .= 'outrate=' . $current['output'] . $hwParamsCalcrate . "\n"; ;
			$data .= 'outrate=' . $current['output'] . "\n"; ;
			$data .= 'volume=' . $_SESSION['volknob'] . "\n";
			$data .= 'mute=' . $_SESSION['volmute'] . "\n";
			$data .= 'state=' . $current['state'] . "\n";
			fwrite($fh, $data);
			fclose($fh);
			rename('/tmp/currentsong.txt', '/var/local/www/currentsong.txt');
            chmod('/var/local/www/currentsong.txt', 0666);
		}
	}
}

function chkClockRadio() {
	$curtime = date('h,i,A'); // HH,MM,AP
	$curday = date('N') - 1; // 0-6 where 0 = Monday
	$retrystop = 2;

	if ($curtime == $GLOBALS['clkradio_start_time'] && $GLOBALS['clkradio_start_days'][$curday] == '1') {
		$GLOBALS['clkradio_start_time'] = ''; // Reset so this section is only done once
		$sock = openMpdSock('localhost', 6600);

		// Find playlist item
		sendMpdCmd($sock, 'playlistfind file ' . '"' . $_SESSION['clkradio_item'] . '"');
		$resp = readMpdResp($sock);
		$array = array();
		$line = strtok($resp, "\n");
		while ($line) {
			list($element, $value) = explode(': ', $line, 2);
			$array[$element] = $value;
			$line = strtok("\n");
		}

		// Send play cmd
		sendMpdCmd($sock, 'play ' . $array['Pos']);
		$resp = readMpdResp($sock);
		closeMpdSock($sock);

		// Set volume
		sysCmd('/var/www/vol.sh ' . $_SESSION['clkradio_volume']);

	} else if ($curtime == $GLOBALS['clkradio_stop_time'] && $GLOBALS['clkradio_stop_days'][$curday] == '1') {
		//workerLog('chkClockRadio(): stoptime=(' . $GLOBALS['clkradio_stop_time'] . ')');
		$GLOBALS['clkradio_stop_time'] = '';  // Reset so this section is only done once
		$sock = openMpdSock('localhost', 6600);

		// Send several stop commands for robustness
		while ($retrystop > 0) {
			sendMpdCmd($sock, 'stop');
			$resp = readMpdResp($sock);
			usleep(250000);
			--$retrystop;
		}
		closeMpdSock($sock);
		//workerLog('chkClockRadio(): $curtime=(' . $curtime . '), $curday=(' . $curday . ')');
		//workerLog('chkClockRadio(): stop command sent');

		// Action after stop
		if ($_SESSION['clkradio_action'] != "None") {
			if ($_SESSION['clkradio_action'] == 'Restart') {
				sleep(45); // To ensure that after reboot $curtime != clkradio_stop_time
				sysCmd('/var/local/www/commandw/restart.sh reboot');
			} else if ($_SESSION['clkradio_action'] == 'Shutdown') {
				sysCmd('/var/local/www/commandw/restart.sh poweroff');
			} else if ($_SESSION['clkradio_action'] == 'Update Library') {
				workerLog('update library');
				submitJob('update_library', '', '', '');
			}
		}
	}

	// Reload start/stop time globals
	if ($curtime != substr($_SESSION['clkradio_start'], 0, 8) && $GLOBALS['clkradio_start_time'] == '') {
		$GLOBALS['clkradio_start_time'] = substr($_SESSION['clkradio_start'], 0, 8);
		//workerLog('chkClockRadio(): starttime global reloaded');
	}

	if ($curtime != substr($_SESSION['clkradio_stop'], 0, 8) && $GLOBALS['clkradio_stop_time'] == '') {
		$GLOBALS['clkradio_stop_time'] = substr($_SESSION['clkradio_stop'], 0, 8);
		//workerLog('chkClockRadio(): stoptime global reloaded');
	}
}

function chkSleepTimer() {
	$curtime = date('h,i,A'); // HH,MM,AP
	$curday = date('N') - 1; // 0-6 where 0 = Monday
	$retrystop = 2;

	if ($curtime == $GLOBALS['clkradio_stop_time'] && $GLOBALS['clkradio_stop_days'][$curday] == '1') {
		//workerLog('chkSleepTimer(): stoptime=(' . $GLOBALS['clkradio_stop_time'] . ')');
		$GLOBALS['clkradio_stop_time'] = '';  // Reset so this section is only done once
		$sock = openMpdSock('localhost', 6600);

		// Send several stop commands for robustness
		while ($retrystop > 0) {
			sendMpdCmd($sock, 'stop');
			$resp = readMpdResp($sock);
			usleep(250000);
			--$retrystop;
		}

		sendMpdCmd($sock, 'stop');
		$resp = readMpdResp($sock);
		closeMpdSock($sock);
		//workerLog('chkSleepTimer(): $curtime=(' . $curtime . '), $curday=(' . $curday . ')');
		//workerLog('chkSleepTimer(): stop command sent');

		// Action after stop
		if ($_SESSION['clkradio_action'] != "None") {
			if ($_SESSION['clkradio_action'] == 'Restart') {
				sleep(45); // To ensure that after reboot $curtime != clkradio_stop_time
				sysCmd('/var/local/www/commandw/restart.sh reboot');
			} else if ($_SESSION['clkradio_action'] == 'Shutdown') {
				sysCmd('/var/local/www/commandw/restart.sh poweroff');
			} else if ($_SESSION['clkradio_action'] == 'Update Library') {
				workerLog('update library');
				submitJob('update_library', '', '', '');
			}
		}
	}

	// Reload stop time global
	if ($curtime != substr($_SESSION['clkradio_stop'], 0, 8) && $GLOBALS['clkradio_stop_time'] == '') {
		$GLOBALS['clkradio_stop_time'] = substr($_SESSION['clkradio_stop'], 0, 8);
		//workerLog('chkSleepTimer(): stoptime global reloaded');
	}
}

function updPlayHistory() {
	$sock = openMpdSock('localhost', 6600);
	$song = getCurrentSong($sock);
	closeMpdSock($sock);

	if (isset($song['Name']) && getFileExt($song['file']) == 'm4a') {
		// iTunes aac file
		$artist = isset($song['Artist']) ? $song['Artist'] : 'Unknown artist';
		$title = $song['Name'];
		$album = isset($song['Album']) ? $song['Album'] : 'Unknown album';

		// Search string
		if ($artist == 'Unknown artist' && $album == 'Unknown album') {
			$searchstr = $title;
		} else if ($artist == 'Unknown artist') {
			$searchstr = $album . '+' . $title;
		} else if ($album == 'Unknown album') {
			$searchstr = $artist . '+' . $title;
		} else {
			$searchstr = $artist . '+' . $album;
		}

	} else if (isset($song['Name']) || (substr($song['file'], 0, 4) == 'http' && !isset($song['Artist']))) {
		// Radio station
		$artist = 'Radio station';

		if (!isset($song['Title']) || trim($song['Title']) == '') {
			$title = $song['file'];
		} else {
			// Use custom name if indicated
			$title = $_SESSION[$song['file']]['name'] == 'Classic And Jazz' ? 'CLASSIC & JAZZ (Paris - France)' : $song['Title'];
		}

		if (isset($_SESSION[$song['file']])) {
			$album = $_SESSION[$song['file']]['name'];
		} else {
			$album = isset($song['Name']) ? $song['Name'] : 'Unknown station';
		}

		// Search string
		if (substr($title, 0, 4) == 'http') {
			$searchstr = $title;
		} else {
			$searchstr = str_replace('-', ' ', $title);
			$searchstr = str_replace('&', ' ', $searchstr);
			$searchstr = preg_replace('!\s+!', '+', $searchstr);
		}
	} else {
		// Song file or upnp url
		$artist = isset($song['Artist']) ? $song['Artist'] : 'Unknown artist';
		$title = isset($song['Title']) ? $song['Title'] : pathinfo(basename($song['file']), PATHINFO_FILENAME);
		$album = isset($song['Album']) ? $song['Album'] : 'Unknown album';

		// Search string
		if ($artist == 'Unknown artist' && $album == 'Unknown album') {
			$searchstr = $title;
		} else if ($artist == 'Unknown artist') {
			$searchstr = $album . '+' . $title;
		} else if ($album == 'Unknown album') {
			$searchstr = $artist . '+' . $title;
		} else {
			$searchstr = $artist . '+' . $album;
		}
	}

	// Search url
	$searcheng = 'http://www.google.com/search?q=';
	$searchurl = '<a href="' . $searcheng . $searchstr . '" class="playhistory-link target-blank-link" target="_blank"><i class="fa-solid fa-sharp fa-external-link-square"></i></a>';

	// Update playback history log
	if ($title != '' && $title != $_SESSION['phistsong']) {
		$_SESSION['phistsong'] = $title; // Store title as-is
		sqlUpdate('cfg_system', $GLOBALS['dbh'], 'phistsong', str_replace("'", "''", $title)); // Use SQL escaped single quotes

		$historyItem = '<li class="playhistory-item"><div>' . date('Y-m-d H:i') . $searchurl . $title . '</div><span>' . $artist . ' - ' . $album . '</span></li>';
        if (false === $fh = fopen(PLAY_HISTORY_LOG, 'a')) {
            workerLog('worker: addPlayHistoryItem(): File open failed on ' . PLAY_HISTORY_LOG);
        } else {
            fwrite($fh, $historyItem . "\n");
        	fclose($fh);
        }
	}
}

// Check for library update complete
function chkLibraryUpdate() {
	//workerLog('chkLibraryUpdate');
	$sock = openMpdSock('localhost', 6600);
	$status = getMpdStatus($sock);
	$stats = getMpdStats($sock);
	closeMpdSock($sock);

	if (!isset($status['updating_db'])) {
		sendEngCmd('libupd_done');
		$GLOBALS['check_library_update'] = '0';
		workerLog('mpdindex: Done: indexed ' . $stats['artists'] . ' artists, ' . $stats['albums'] . ' albums, ' .  $stats['songs'] . ' songs');
		workerLog('worker: Job update_library done');
	}
}

// Check for library regen complete
function chkLibraryRegen() {
	//workerLog('chkLibraryRegen');
	$sock = openMpdSock('localhost', 6600);
	$status = getMpdStatus($sock);
	closeMpdSock($sock);

	if (!isset($status['updating_db'])) {
		sendEngCmd('libregen_done');
		$GLOBALS['check_library_regen'] = '0';
		workerLog('worker: Job regen_library done');
	}
}

// Return hardware revision
function getHdwrRev() {
	$array = explode("\t", sysCmd('/var/www/util/pirev.py')[0]);
	$piModel = $array[1];
	$rev = $array[2];
	$ram = $array[3];

	if ($piModel == 'CM3+') {
		$hdwrRev = 'Allo USBridge SIG [CM3+ Lite 1GB v1.0]';
	} else {
		$hdwrRev = 'Pi-' . $piModel . ' ' . $rev . ' ' . $ram;
	}

	return $hdwrRev;
}

// Log info for the active interface (eth0 or wlan0)
function logNetworkInfo($interface) {
	$ifaceName = $interface == 'eth0' ? 'Ethernet: ' : 'Wireless: ';

	$result = sqlQuery("SELECT iface, method FROM cfg_network WHERE iface!='apd0'", $GLOBALS['dbh']);
	$method = $result[0]['iface'] == $interface ? $result[0]['method'] : $result[0]['method'];

	$domainName = sysCmd("cat /etc/resolv.conf | awk '/^search/ {print $2; exit}'")[0]; // First entry of possibly many
	$primaryDns = sysCmd("cat /etc/resolv.conf | awk '/^nameserver/ {print $2; exit}'")[0]; // First entry of possibly many
	$primaryDns = !empty($primaryDns) ? $primaryDns : 'none found';
	$domainName = !empty($domainName) ? $domainName : 'none found';

	workerLog('worker: ' . $ifaceName . 'method  ' . $method);
 	workerLog('worker: ' . $ifaceName . 'address ' . sysCmd("ifconfig " . $interface . " | awk 'NR==2{print $2}'")[0]);
	workerLog('worker: ' . $ifaceName . 'netmask ' . sysCmd("ifconfig " . $interface . " | awk 'NR==2{print $4}'")[0]);
	workerLog('worker: ' . $ifaceName . 'gateway ' . sysCmd("netstat -nr | awk 'NR==3 {print $2}'")[0]);
	workerLog('worker: ' . $ifaceName . 'pri DNS ' . $primaryDns);
	workerLog('worker: ' . $ifaceName . 'domain  ' . $domainName);
}

function updaterAutoCheck($validIPAddress) {
	if ($_SESSION['updater_auto_check'] == 'On') {
		workerLog('worker: Software update:   Automatic check on');

		if ($validIPAddress === true) {
			workerLog('worker: Software update:   Checking for available update...');
			$available = checkForUpd($_SESSION['res_software_upd_url'] . '/');
			$thisReleaseDate = explode(" ", getMoodeRel('verbose'))[1];

			if (false === ($availableDate = strtotime($available['Date']))) {
				$msg = 'Invalid remote release date';
			} else if (false === ($thisDate = strtotime($thisReleaseDate))) {
				$msg = 'Invalid local release date ' . $thisReleaseDate;
			} else if ($availableDate <= $thisDate) {
				$msg = 'Software is up to date';
			} else {
				$msg = 'Release ' . $available['Release'] . ', ' . $available['Date'] . ' is available';
			}
			workerLog('worker: Software update:   ' . $msg);
		} else {
			$msg = 'No local IP address or Hotspot is on';
			workerLog('worker: Software update:   Unable to check, ' . $msg);
		}
	} else {
		$msg = 'Automatic check off';
		workerLog('worker: Software update:   ' . $msg);
	}

	return $msg;
}

// DLNA server
function startMiniDlna() {
	sysCmd('systemctl start minidlna');
}

// LCD updater
function startLcdUpdater() {
	sysCmd('/var/www/daemon/lcd-updater.sh');
}

// GPIO button handler
function startGpioBtnHandler() {
	sysCmd('/var/www/daemon/gpio_buttons.py ' . GPIOBUTTONS_SLEEP . ' > /dev/null &');
}

// LocalUI display
function startLocalUI() {
	sysCmd('systemctl start localui');
}
function stopLocalUI() {
	sysCmd('systemctl stop localui');
}

//----------------------------------------------------------------------------//
// PROCESS SUBMITTED JOBS
//----------------------------------------------------------------------------//

function runQueuedJob() {
	$_SESSION['w_lock'] = 1;

	// No need to log screen saver resets
	if ($_SESSION['w_queue'] != 'reset_screen_saver') {
		workerLog('worker: Job ' . $_SESSION['w_queue']);
		if ($_SESSION['w_queue'] == 'update_library') {
			workerLog('mpdindex: Start');
		}
	}

	switch ($_SESSION['w_queue']) {
		// Screen saver reset job
		case 'reset_screen_saver':
			$GLOBALS['scnsaver_timeout'] = $_SESSION['scnsaver_timeout'];
			$GLOBALS['scnactive'] = '0';
			break;

		// Nenu, Update library, Context menu, Update this folder
		case 'update_library':
			// Update library
			clearLibCacheAll();
			$cmd = empty($_SESSION['w_queueargs']) ? 'update' : 'update "' . html_entity_decode($_SESSION['w_queueargs']) . '"';
			workerLog('mpdindex: Cmd (' . $cmd . ')');
			$sock = openMpdSock('localhost', 6600);
			sendMpdCmd($sock, $cmd);
			$resp = readMpdResp($sock);
			closeMpdSock($sock);

			// Start thumbnail generator
			$result = sysCmd('pgrep -l thumb-gen.php');
			if (strpos($result[0], 'thumb-gen.php') === false) {
				sysCmd('/var/www/util/thumb-gen.php > /dev/null 2>&1 &');
			}
			$GLOBALS['check_library_update'] = '1';
			break;

		// Library saved searches
		case 'create_saved_search':
			$fh = fopen(LIBSEARCH_BASE . $_SESSION['w_queueargs'] . '.json', 'w');
			$data = json_encode(['filter_type' => $_SESSION['library_flatlist_filter'], 'filter_str' => $_SESSION['library_flatlist_filter_str']]);
			fwrite($fh, $data);
			fclose($fh);
			sysCmd('chmod 0777 "' . $file . '"');
			break;

		// lib-config jobs
		case 'sourcecfg':
			clearLibCacheAll();
			sourceCfg($_SESSION['w_queueargs']);
			break;
		case 'fs_mountmon':
			sysCmd('killall -s 9 mountmon.php');
			if ($_SESSION['w_queueargs'] == 'On') {
				sysCmd('/var/www/daemon/mountmon.php > /dev/null 2>&1 &');
			}
			break;
		case 'regen_library':
			clearLibCacheAll();
			$sock = openMpdSock('localhost', 6600);
			sendMpdCmd($sock, 'rescan');
			$resp = readMpdResp($sock);
			closeMpdSock($sock);
			// Launch thumbcache updater
			$result = sysCmd('pgrep -l thumb-gen.php');
			if (strpos($result[0], 'thumb-gen.php') === false) {
				sysCmd('/var/www/util/thumb-gen.php > /dev/null 2>&1 &');
				//workerLog('regen_library, thumb-gen.php launched');
			}
			$GLOBALS['check_library_regen'] = '1';
			break;
		case 'regen_thmcache':
			sysCmd('rm -rf ' . THMCACHE_DIR);
			sysCmd('mkdir ' . THMCACHE_DIR);
			sysCmd('/var/www/util/thumb-gen.php > /dev/null 2>&1 &');
			break;
		case 'cuefiles_ignore':
			setCuefilesIgnore($_SESSION['w_queueargs']);
			clearLibCacheAll();
			sysCmd('mpc stop');
			sysCmd('systemctl restart mpd');
			break;
		// mpd-config jobs
		case 'mpdrestart':
			sysCmd('mpc stop');
			sysCmd('systemctl restart mpd');
			break;
		case 'mpdcfg':
			$playing = sysCmd('mpc status | grep "\[playing\]"');
			sysCmd('mpc stop');

			// Update mpd.conf file
			updMpdConf();

			// Store audio formats
			$_SESSION['audio_formats'] = sysCmd('moodeutl -f')[0];

			// Set ALSA volume to 0dB (100%)
			if ($_SESSION['alsavolume'] != 'none' && $_SESSION['mpdmixer'] != 'hardware') {
				setALSAVolTo0dB($_SESSION['alsavolume_max']);
			}

			// Parse queue args:
			$queueArgs = explode(',', $_SESSION['w_queueargs']);
			$deviceChange = $queueArgs[0];
			$mixerChange = $queueArgs[1];

			// Start Camilla volume sync if indicated
			$serviceCmd = CamillaDSP::isMPD2CamillaDSPVolSyncEnabled() ? 'start' : 'stop';
			sysCmd('systemctl ' . $serviceCmd .' mpd2cdspvolume');

			// Restart MPD
			sysCmd('systemctl restart mpd');
			$sock = openMpdSock('localhost', 6600); // Ensure MPD ready to accept connections
			closeMpdSock($sock);

			// Volume type (MPD mixer) change
			if ($mixerChange == '0') {
				// No mixer change
				$startPlay = true;
			} else {
				// Mixer change
				$startPlay = false;
				if ($mixerChange == 'camilladsp') {
					sysCmd('/var/www/vol.sh -restore');
				} else {
					// hardware, software, fixed
					if ($_SESSION['camilladsp'] != 'off') {
						CamillaDSP::setCDSPVolTo0dB();
					}
					sysCmd('/var/www/vol.sh -restore');
				}
				sendEngCmd('refresh_screen'); // Refresh connected clients
			}

			// Start play if was playing
			if (!empty($playing) && $startPlay == true) {
				sysCmd('mpc play');
			}

			// Restart renderers if device (cardnum) changed
			if ($deviceChange == '1') {
				// TODO: Bluetooth?

				if ($_SESSION['airplaysvc'] == 1) {
					sysCmd('killall shairport-sync');
					startAirPlay();
				}
				if ($_SESSION['spotifysvc'] == 1) {
					sysCmd('killall librespot');
					startSpotify();
				}
				if ($_SESSION['slsvc'] == 1) {
					sysCmd('killall squeezelite');
					cfgSqueezelite();
					startSqueezelite();
				}
			}

			// DEBUG:
			debugLog('worker: Job mpdcfg: ' .
				'MIXER:' . $mixerChange . ', ' .
				'KNOB:' . $_SESSION['volknob'] . ', ' .
				'CAMILLADSP:' . ($_SESSION['camilladsp'] != 'off' ? 'on' : 'off') . ', ' .
				'ALSAVOL:' . ($_SESSION['alsavolume'] == 'none' ? 'none' : sysCmd('/var/www/util/sysutil.sh get-alsavol ' . '"' . $_SESSION['amixname'] . '"')[0]) . ', ' .
				'PLAY:' . ((!empty($playing) && $startPlay == true) ? 'yes' : 'no') . ', ' .
				'DEVCHG:' . ($deviceChange == '0' ? 'no' : 'yes'));
			break;
		// snd-config jobs
		case 'i2sdevice':
			cfgI2SDevice();
			break;
		case 'alsavolume_max':
			setALSAVolTo0dB($_SESSION['w_queueargs']);
			break;
		case 'alsa_output_mode':
		case 'alsa_loopback':
			$playing = sysCmd('mpc status | grep "\[playing\]"');
			sysCmd('mpc stop');

			if ($_SESSION['w_queue'] == 'alsa_output_mode') {
				// Update ALSA and BT confs
				// NOTE: w_queueargs contains $old_output_mode or ''
				updAudioOutAndBtOutConfs($_SESSION['cardnum'], $_SESSION['alsa_output_mode']);
				updDspAndBtInConfs($_SESSION['cardnum'], $_SESSION['alsa_output_mode'], $_SESSION['w_queueargs']);
			} else {
				// ALSA loopback
				if ($_SESSION['w_queueargs'] == 'On') {
					sysCmd("sed -i '0,/_audioout__ {/s//_audioout {/' /etc/alsa/conf.d/_sndaloop.conf");
					sysCmd('modprobe snd-aloop');
				} else {
					sysCmd("sed -i '0,/_audioout {/s//_audioout__ {/' /etc/alsa/conf.d/_sndaloop.conf");
					sysCmd('modprobe -r snd-aloop');
				}
			}

			// Restart MPD
			sysCmd('systemctl restart mpd');
			$sock = openMpdSock('localhost', 6600); // Ensure MPD ready to accept connections
			closeMpdSock($sock);
			if (!empty($playing)) {
				sysCmd('mpc play');
			}
			// Restart renderers
			if ($_SESSION['airplaysvc'] == 1) {
				stopAirPlay();
				startAirPlay();
			}
			if ($_SESSION['spotifysvc'] == 1) {
				stopSpotify();
				startSpotify();
			}
			if ($_SESSION['slsvc'] == 1) {
				stopSqueezelite();
				cfgSqueezelite();
				startSqueezelite();
			}
			// Reenable HTTP server
			setMpdHttpd();
			break;
		case 'rotaryenc':
			sysCmd('systemctl stop rotenc');
			sysCmd('sed -i "/ExecStart/c\ExecStart=' . '/var/www/daemon/rotenc.py ' . $_SESSION['rotenc_params'] . '"' . ' /lib/systemd/system/rotenc.service');
			sysCmd('systemctl daemon-reload');

			if ($_SESSION['w_queueargs'] == '1') {
				sysCmd('systemctl start rotenc');
			}
			break;
		case 'usb_volknob':
			if ($_SESSION['w_queueargs'] == '1') {
				sysCmd('systemctl enable triggerhappy');
				sysCmd('systemctl start triggerhappy');
			} else {
				sysCmd('systemctl stop triggerhappy');
				sysCmd('systemctl disable triggerhappy');
			}
			break;
		case 'mpd_httpd':
			$cmd = $_SESSION['w_queueargs'] == '1' ? 'mpc enable "' . HTTP_SERVER . '"' : 'mpc disable "' . HTTP_SERVER . '"';
			sysCmd($cmd);
			break;
		case 'mpd_httpd_port':
			updMpdConf();
			sysCmd('systemctl restart mpd');
			break;
		case 'mpd_httpd_encoder':
			updMpdConf();
			sysCmd('systemctl restart mpd');
			break;
		case 'alsaequal':
		case 'camilladsp':
		case 'crossfeed':
		case 'eqfa12p':
		case 'invpolarity':
			// Save play state
			$playing = sysCmd('mpc status | grep "\[playing\]"');
			sysCmd('mpc stop');
			// Switch to selected DSP
			switch ($_SESSION['w_queue']) {
				case 'alsaequal':
					$queueArgs = explode(',', $_SESSION['w_queueargs']); // Split out old,new curve names
					if ($_SESSION['alsaequal'] != 'Off') {
						$result = sqlQuery("SELECT curve_values FROM cfg_eqalsa WHERE curve_name='" . $queueArgs[1] . "'", $GLOBALS['dbh']);
						$curve = explode(',', $result[0]['curve_values']);
						foreach ($curve as $key => $value) {
							sysCmd('amixer -D alsaequal cset numid=' . ($key + 1) . ' ' . $value);
						}
					}
					$output = $_SESSION['alsaequal'] != 'Off' ? "\"alsaequal\"" : "\"" . $_SESSION['alsa_output_mode'] . ':' . $_SESSION['cardnum'] . ",0\"";
					sysCmd("sed -i '/slave.pcm/c\slave.pcm " . $output . "' " . ALSA_PLUGIN_PATH . '/_audioout.conf');
					sysCmd("sed -i '/a { channels 2 pcm/c\a { channels 2 pcm " . $output . " }' " . ALSA_PLUGIN_PATH . '/_sndaloop.conf');
					break;
				case 'camilladsp':
					$queueArgs = explode(',', $_SESSION['w_queueargs']);
					$output = $queueArgs[0] != 'off' ? "\"camilladsp\"" : "\"" . $_SESSION['alsa_output_mode'] . ':' . $_SESSION['cardnum'] . ",0\"";
					sysCmd("sed -i '/slave.pcm/c\slave.pcm " . $output . "' " . ALSA_PLUGIN_PATH . '/_audioout.conf');
					sysCmd("sed -i '/a { channels 2 pcm/c\a { channels 2 pcm " . $output . " }' " . ALSA_PLUGIN_PATH . '/_sndaloop.conf');
					if (!empty($queueArgs[1])) {
						// Reconfigure MPD mixer
						if ($queueArgs[1] == 'change_mixer_to_camilladsp') {
							$volSync = 'on';
							$serviceCmd = 'start';
							$mixerType = 'camilladsp';
						} else if ($queueArgs[1] == 'change_mixer_to_default') {
							$volSync = 'off';
							$serviceCmd = 'stop';
							$mixerType = $_SESSION['alsavolume'] != 'none' ? 'hardware' : 'software';
						}
						changeMPDMixer($mixerType);

						// CamillaDSP volume sync
						phpSession('write', 'camilladsp_volume_sync', $volSync);
						sysCmd('systemctl '. $serviceCmd .' mpd2cdspvolume');
						// Squeezelite (hogs the output so restart to pick up change in _audioout)
						if ($_SESSION['slsvc'] == '1') {
							sysCmd('systemctl restart squeezelite');
						}
						// MPD
						sysCmd('systemctl restart mpd');
						$sock = openMpdSock('localhost', 6600); // Ensure MPD ready to accept connections
						closeMpdSock($sock);
						// Set volume level
						sysCmd('/var/www/vol.sh -restore' );
					}
					// Notification
					sendEngCmd('cdsp_config_updated');
					break;
				case 'crossfeed':
					$output = $_SESSION['w_queueargs'] != 'Off' ? "\"crossfeed\"" : "\"" . $_SESSION['alsa_output_mode'] . ':' . $_SESSION['cardnum'] . ",0\"";
					sysCmd("sed -i '/slave.pcm/c\slave.pcm " . $output . "' " . ALSA_PLUGIN_PATH . '/_audioout.conf');
					sysCmd("sed -i '/a { channels 2 pcm/c\a { channels 2 pcm " . $output . " }' " . ALSA_PLUGIN_PATH . '/_sndaloop.conf');
					if ($_SESSION['w_queueargs'] != 'Off') {
						sysCmd('sed -i "/controls/c\controls [ ' . $_SESSION['w_queueargs'] . ' ]" ' . ALSA_PLUGIN_PATH . '/crossfeed.conf');
					}
					break;
				case 'eqfa12p':
					$queueArgs = explode(',', $_SESSION['w_queueargs']); // Split out old,new curve names
					if ($_SESSION['eqfa12p'] != 'Off') {
						$curr = intval($queueArgs[1]);
						$eqfa12p = Eqp12(sqlConnect());
						$config = $eqfa12p->getpreset($curr);
						$eqfa12p->applyConfig($config);
						unset($eqfa12p);
					}
					$output = $_SESSION['eqfa12p'] != 'Off' ? "\"eqfa12p\"" : "\"" . $_SESSION['alsa_output_mode'] . ':' . $_SESSION['cardnum'] . ",0\"";
					sysCmd("sed -i '/slave.pcm/c\slave.pcm " . $output . "' " . ALSA_PLUGIN_PATH . '/_audioout.conf');
					sysCmd("sed -i '/a { channels 2 pcm/c\a { channels 2 pcm " . $output . " }' " . ALSA_PLUGIN_PATH . '/_sndaloop.conf');
					break;
				case 'invpolarity':
					$output = $_SESSION['w_queueargs'] == '1' ? "\"invpolarity\"" : "\"" . $_SESSION['alsa_output_mode'] . ':' . $_SESSION['cardnum'] . ",0\"";
					sysCmd("sed -i '/slave.pcm/c\slave.pcm " . $output . "' " . ALSA_PLUGIN_PATH . '/_audioout.conf');
					sysCmd("sed -i '/a { channels 2 pcm/c\a { channels 2 pcm " . $output . " }' " . ALSA_PLUGIN_PATH . '/_sndaloop.conf');
					break;
			}

			// Restart MPD
			// NOTE: Don't restart if already done in the camillaDSP section
			if ($_SESSION['w_queue'] != 'camilladsp' || ($_SESSION['w_queue'] == 'camilladsp' && empty($queueArgs[1]))) {
				sysCmd('systemctl restart mpd');
				$sock = openMpdSock('localhost', 6600); // Ensure MPD ready to accept connections
				closeMpdSock($sock);
			}
			// Resume playback
			if (!empty($playing)) {
				sysCmd('mpc play');
			}
			// Restart renderers
			if ($_SESSION['airplaysvc'] == 1) {
				stopAirPlay();
				startAirPlay();
			}
			if ($_SESSION['spotifysvc'] == 1) {
				stopSpotify();
				startSpotify();
			}
			// Reenable HTTP server
			setMpdHttpd();

			debugLog('worker: Job ' . $_SESSION['w_queue'] . ': ' .
				'CAMILLA:' . ($_SESSION['camilladsp'] != 'off' ? 'on' : 'off') . ', ' .
				'CFG_MPD:' . $cfgMPD['mixer_type'] . ', ' .
				'SESSION:' . $_SESSION['mpdmixer']
			);

			break;
		case 'mpdcrossfade':
			sysCmd('mpc crossfade ' . $_SESSION['w_queueargs']);
			break;
		case 'btsvc':
			sysCmd('/var/www/util/sysutil.sh chg-name bluetooth ' . $_SESSION['w_queueargs']);
			sysCmd('systemctl stop bluealsa');
			sysCmd('systemctl stop bluetooth');
			sysCmd('killall bluealsa-aplay');
			sysCmd('/var/www/vol.sh -restore');
			// Reset to inactive
			phpSession('write', 'btactive', '0');
			sendEngCmd('btactive0');

			if ($_SESSION['btsvc'] == 1) {
				$status = startBluetooth();
				if ($status == 'started') {
					if ($_SESSION['pairing_agent'] == '1') {
						sysCmd('killall -s 9 blu_agent.py');
						sysCmd('/var/www/daemon/blu_agent.py --agent --disable_pair_mode_switch --pair_mode --wait_for_bluez >/dev/null 2>&1 &');
					}
				} else {
					workerLog('worker: ' . $status);
				}
			} else {
				sysCmd('killall -s 9 blu_agent.py');
				phpSession('write', 'pairing_agent', '0');
			}
			break;
		case 'pairing_agent':
			sysCmd('killall -s 9 blu_agent.py');
			if ($_SESSION['pairing_agent'] == '1') {
				sysCmd('/var/www/daemon/blu_agent.py --agent --disable_pair_mode_switch --pair_mode --wait_for_bluez >/dev/null 2>&1 &');
			}
			break;
		case 'airplaysvc':
			stopAirPlay();
			if ($_SESSION['airplaysvc'] == 1) {
				startAirPlay();
			}

			if ($_SESSION['w_queueargs'] == 'disconnect_renderer' && $_SESSION['rsmafterapl'] == 'Yes') {
				sysCmd('mpc play');
			}
			break;
		case 'spotifysvc':
			stopSpotify();
			if ($_SESSION['spotifysvc'] == 1) {
				startSpotify();
			}

			if ($_SESSION['w_queueargs'] == 'disconnect_renderer' && $_SESSION['rsmafterspot'] == 'Yes') {
				sysCmd('mpc play');
			}
			break;
		case 'spotify_clear_credentials':
			sysCmd('rm /var/local/www/spotify_cache/credentials.json');
			stopSpotify();
			if ($_SESSION['spotifysvc'] == 1) {
				startSpotify();
			}
			break;
		case 'slsvc':
			if ($_SESSION['slsvc'] == '1') {
				cfgSqueezelite();
				startSqueezeLite();
			} else {
				stopSqueezeLite();
			}

			if ($_SESSION['w_queueargs'] == 'disconnect_renderer' && $_SESSION['rsmaftersl'] == 'Yes') {
				sysCmd('mpc play');
			}
			break;
		case 'slrestart':
			if ($_SESSION['slsvc'] == '1') {
				sysCmd('systemctl stop squeezelite');
				startSqueezeLite();
			}
			break;
		case 'slcfgupdate':
			cfgSqueezelite();
			if ($_SESSION['slsvc'] == '1') {
				sysCmd('systemctl stop squeezelite');
				startSqueezeLite();
			}
			break;
		case 'rbsvc':
			if ($_SESSION['rbsvc'] == '1') {
				startRoonBridge();
			} else {
				stopRoonBridge();
			}

			if ($_SESSION['w_queueargs'] == 'disconnect_renderer' && $_SESSION['rsmafterrb'] == 'Yes') {
				sysCmd('mpc play');
			}
			break;
		case 'rbrestart':
			sysCmd('mpc stop');
			stopRoonBridge();
			if ($_SESSION['rbsvc'] == '1') {
				startRoonBridge();
			}
			break;

		case 'multiroom_tx':
			if ($_SESSION['multiroom_tx'] == 'On') {
				$cardNum = loadSndDummy(); // Reconfigure to Dummy sound driver
				phpSession('write', 'cardnum', $cardNum);
				phpSession('write', 'adevname', TRX_SENDER_NAME);
				sqlUpdate('cfg_mpd', sqlConnect(), 'device', $cardNum);

				updAudioOutAndBtOutConfs($cardNum, 'hw');
				updDspAndBtInConfs($cardNum, 'hw');
				sysCmd('systemctl restart mpd');

				startMultiroomSender();
			} else {
				stopMultiroomSender();
				unloadSndDummy();
				// Reset to Pi HDMI 1
				$cardNum = getAlsaCardNumForDevice(PI_HDMI1);
				phpSession('write', 'cardnum', $cardNum);
				phpSession('write', 'adevname', PI_HDMI1);
				sqlUpdate('cfg_mpd', sqlConnect(), 'device', $cardNum);

				updAudioOutAndBtOutConfs($_SESSION['cardnum'], $_SESSION['alsa_output_mode']);
				updDspAndBtInConfs($_SESSION['cardnum'], $_SESSION['alsa_output_mode']);

				sysCmd('systemctl restart mpd');
			}

			// Restart renderers
			if ($_SESSION['airplaysvc'] == 1) {
				stopAirPlay();
				startAirPlay();
			}
			if ($_SESSION['spotifysvc'] == 1) {
				stopSpotify();
				startSpotify();
			}
			break;
		case 'multiroom_tx_restart':
			stopMultiroomSender();
			if ($_SESSION['multiroom_tx'] == 'On') {
				startMultiroomSender();
			}
			break;
		case 'multiroom_rx':
			if ($_SESSION['multiroom_rx'] == 'On') {
				sysCmd('mpc stop');

				// TODO: Turn off session based renderers?
				//stopAirPlay();
				//stopSpotify();
				//phpSession('write', 'airplaysvc', '0');
				//phpSession('write', 'spotifysvc', '0');

				startMultiroomReceiver();
			} else {
				stopMultiroomReceiver();
			}
			break;
		case 'multiroom_rx_restart':
			stopMultiroomReceiver();
			if ($_SESSION['multiroom_rx'] == 'On') {
				startMultiroomReceiver();
			}
			break;
		case 'multiroom_initvol':
			$level = $_SESSION['w_queueargs'];
			sysCmd('/var/www/vol.sh ' . $level); // Sender
			updReceiverVol($level); // Receivers
			break;

		case 'upnpsvc':
			sysCmd('/var/www/util/sysutil.sh chg-name upnp ' . $_SESSION['w_queueargs']);
			sysCmd('systemctl stop upmpdcli');
			if ($_SESSION['upnpsvc'] == 1) {
				startUPnP();
			}
			break;
		case 'minidlna':
			sysCmd('/var/www/util/sysutil.sh chg-name dlna ' . $_SESSION['w_queueargs']);
			sysCmd('systemctl stop minidlna');
			if ($_SESSION['dlnasvc'] == 1) {
				startMiniDlna();
			} else {
				syscmd('rm -rf /var/cache/minidlna/* > /dev/null');
			}
			break;
		case 'dlnarebuild':
			sysCmd('systemctl stop minidlna');
			syscmd('rm -rf /var/cache/minidlna/* > /dev/null');
			sleep(2);
			startMiniDlna();
			break;

		// net-config jobs
		case 'netcfg':
			cfgNetworks();
			break;

		// sys-config jobs
		case 'install_update':
			$result = sysCmd('/var/www/util/system-updater.sh ' . getPkgId() . ' > /dev/null 2>&1 &');
			break;
		case 'install_plugin':
			// $args = component_name,plugin_name
			$args = explode(',', $_SESSION['w_queueargs']);
			sysCmd('/var/www/util/plugin-updater.sh "' . $args[0] . '" "' . $args[1] . '"' . ' > /dev/null 2>&1');
			workerLog('worker: Plugin: ' . $args[1] . ' installed');
			break;
		case 'timezone':
			sysCmd('/var/www/util/sysutil.sh set-timezone ' . $_SESSION['w_queueargs']);
			break;
		case 'hostname':
			// Change host name
			sysCmd('/var/www/util/sysutil.sh chg-name host ' . $_SESSION['w_queueargs']);
			// Change MPD zeroconf name
			// NOTE: w_queueargs = "$_SESSION['hostname']" "$_POST['hostname']"
			$name = explode('"', $_SESSION['w_queueargs']); // [1]: $_SESSION['hostname'], [3]: $_POST['hostname']
			$result = sqlQuery("UPDATE cfg_mpd SET value='" . ucfirst($name[3]) . " MPD' WHERE param='zeroconf_name'", $GLOBALS['dbh']);
			sysCmd('/var/www/util/sysutil.sh chg-name mpdzeroconf "' . ucfirst($name[1]) . ' MPD" "' . ucfirst($name[3]) . ' MPD"');
			break;
		case 'updater_auto_check':
			$_SESSION['updater_auto_check'] = $_SESSION['w_queueargs'];
			$validIPAddress = ($_SESSION['ipaddress'] != '0.0.0.0' && $GLOBALS['wlan0Ip'][0] != '172.24.1.1');
			$_SESSION['updater_available_update'] = updaterAutoCheck($validIPAddress);
			break;
		case 'worker_responsiveness':
			if ($_SESSION['w_queueargs'] == 'Default') {
				$workerSleep = 3000000;
				$waitworkerSleep = 1000000;
			} else {
				$workerSleep = 1500000;
				$waitworkerSleep = 750000;
			}
			sysCmd('sed -i "/const WORKER_SLEEP/c\const WORKER_SLEEP = ' . $workerSleep . ';" /var/www/inc/sleep-interval.php');
			sysCmd('sed -i "/const WAITWORKER_SLEEP/c\const WAITWORKER_SLEEP = ' . $waitworkerSleep . ';" /var/www/inc/sleep-interval.php');
			break;
		case 'reduce_power':
			$value = $_SESSION['w_queueargs'] == 'on' ? '1' : '0';
			sysCmd('rpi-eeprom-config --out /tmp/boot.conf > /dev/null 2>&1');
			sysCmd('sed -i s/^POWER_OFF_ON_HALT=.*/POWER_OFF_ON_HALT=' . $value . '/ /tmp/boot.conf > /dev/null 2>&1');
			sysCmd('rpi-eeprom-config --apply /tmp/boot.conf > /dev/null 2>&1');
			break;
		case 'cpugov':
			sysCmd('sh -c ' . "'" . 'echo "' . $_SESSION['w_queueargs'] . '" | tee /sys/devices/system/cpu/cpu*/cpufreq/scaling_governor' . "'");
			break;
		case 'usb_auto_mounter':
			if ($_SESSION['w_queueargs'] == 'udisks-glue') {
				sysCmd('sed -e "/udisks-glue/ s/^#*//" -i /etc/rc.local');
				sysCmd('sed -e "/devmon/ s/^#*/#/" -i /etc/rc.local');
				sysCmd('systemctl enable udisks');
				sysCmd('systemctl disable udisks2');
			} else if ($_SESSION['w_queueargs'] == 'devmon') {
				sysCmd('sed -e "/udisks-glue/ s/^#*/#/" -i /etc/rc.local');
				sysCmd('sed -e "/devmon/ s/^#*//" -i /etc/rc.local');
				sysCmd('systemctl disable udisks');
				sysCmd('systemctl enable udisks2');
			}
			break;
		case 'usbboot':
			sysCmd('sed -i /program_usb_boot_mode/d ' . BOOT_CONFIG_TXT); // Remove first to prevent duplicate adds
			sysCmd('echo program_usb_boot_mode=1 >> ' . BOOT_CONFIG_TXT);
			break;
		case 'p3wifi':
			ctlWifi($_SESSION['w_queueargs']);
			break;
		case 'p3bt':
			ctlBt($_SESSION['w_queueargs']);
			break;
		case 'actled': // LED0
			if (substr($_SESSION['hdwrrev'], 0, 7) == 'Pi-Zero') {
				$led0Trigger = $_SESSION['w_queueargs'] == '0' ? 'none' : 'actpwr';
				sysCmd('echo ' . $led0Trigger . ' | sudo tee /sys/class/leds/ACT/trigger > /dev/null');
			} else {
				$led0Trigger = $_SESSION['w_queueargs'] == '0' ? 'none' : 'actpwr';
				sysCmd('echo ' . $led0Trigger . ' | sudo tee /sys/class/leds/ACT/trigger > /dev/null');
			}
			break;
		case 'pwrled': // LED1
			$led1Brightness = $_SESSION['w_queueargs'] == '0' ? '0' : '255';
			sysCmd('echo ' . $led1Brightness . ' | sudo tee /sys/class/leds/PWR/brightness > /dev/null');
			break;
		// HTTPS mode
		case 'nginx_https_only':
			if ($_SESSION['w_queueargs'] == '1') {
				$cmd = 'openssl x509 -text -noout -in /etc/ssl/certs/moode.crt | grep "Subject: CN" | cut -d "=" -f 2';
				$CN = trim(sysCmd($cmd)[0]);
				// Check for host name change
				if ($CN != $_SESSION['hostname'] . '.local') {
					if ($_SESSION['nginx_cert_type'] == 'automatic') {
						sysCmd('/var/www/util/gen-cert.sh');
						sysCmd('systemctl restart nginx');
						$status .= ', Self-signed cert created for host: ' . $_SESSION['hostname'];
					} else {
						$status .= ', Warning: Manually created cert exists but CN does not match host: ' . $_SESSION['hostname'];
					}
				} else {
					$status .= ', Using ' . ucfirst($_SESSION['nginx_cert_type']) . ' cert for host: ' . $_SESSION['hostname'];
				}

				workerLog('worker: ' . $status);

				// Enable HTTPS
				sysCmd('rm -f /etc/nginx/sites-enabled/*');
				sysCmd('ln -s /etc/nginx/sites-available/moode-https.conf /etc/nginx/sites-enabled/moode-https.conf');
			} else {
				// Enable HTTP
				sysCmd('rm -f /etc/nginx/sites-enabled/*');
				sysCmd('ln -s /etc/nginx/sites-available/moode-http.conf /etc/nginx/sites-enabled/moode-http.conf');
			}
			break;
		case 'localui':
			if ($_SESSION['w_queueargs'] == '1') {
				startLocalUI();
			} else {
				stopLocalUI();
			}
			break;
		case 'localui_restart':
			stopLocalUI();
			startLocalUI();
			break;
		case 'touchscn':
			$param = $_SESSION['w_queueargs'] == '0' ? ' -- -nocursor' : '';
			sysCmd('sed -i "/ExecStart=/c\ExecStart=/usr/bin/xinit' .$param . '" /lib/systemd/system/localui.service');
			if ($_SESSION['localui'] == '1') {
				sysCmd('systemctl daemon-reload');
				stopLocalUI();
				startLocalUI();
			}
			break;
		case 'scnblank':
			sysCmd('sed -i "/xset s/c\xset s ' . $_SESSION['w_queueargs'] . '" ' . $_SESSION['home_dir'] . '/.xinitrc');
			if ($_SESSION['localui'] == '1') {
				stopLocalUI();
				startLocalUI();
			}
		case 'scnbrightness':
			sysCmd('/bin/su -c "echo '. $_SESSION['w_queueargs'] . ' > /sys/class/backlight/rpi_backlight/brightness"');
			break;
		case 'pixel_aspect_ratio':
			if ($_SESSION['w_queueargs'] == 'Square') {
				sysCmd('sed -i /framebuffer_/d ' . BOOT_CONFIG_TXT); // Remove first to prevent any chance of duplicate adds
				sysCmd('echo framebuffer_width=800 >> ' . BOOT_CONFIG_TXT);
				sysCmd('echo framebuffer_height=444 >> ' . BOOT_CONFIG_TXT);
				sysCmd('echo framebuffer_aspect=-1 >> ' . BOOT_CONFIG_TXT);
			} else {
				sysCmd('sed -i /framebuffer_/d ' . BOOT_CONFIG_TXT);
			}
			break;
		case 'scnrotate':
			sysCmd('sed -i /lcd_rotate/d ' . BOOT_CONFIG_TXT);
			if ($_SESSION['w_queueargs'] == '180') {
				sysCmd('echo lcd_rotate=2 >> ' . BOOT_CONFIG_TXT);
			}
			break;
		case 'clearbrcache':
			sysCmd('/var/www/util/sysutil.sh clearbrcache');
			break;
		case 'fs_smb':
			$cmd = $_SESSION['w_queueargs'] == 'On' ? 'start' : 'stop';
			sysCmd('systemctl ' . $cmd . ' smbd');
			sysCmd('systemctl ' . $cmd . ' nmbd');
			break;
		case 'fs_nfs':
			$cmd = $_SESSION['w_queueargs'] == 'On' ? 'start' : 'stop';
			sysCmd('systemctl ' . $cmd . ' nfs-server');
			break;
		case 'fs_nfs_access':
		case 'fs_nfs_options':
			$file = '/etc/exports';
			if (false === ($contents = file_get_contents($file))) {
				workerLog('worker: Error: File read failed on ' . $file);
			} else if (empty($contents)) {
				workerLog('worker: Error: File ' . $file . ' is empty');
			} else {
				$output = '';
				$line = strtok($contents, "\n");
				while ($line) {
					if (substr($line, 0, 1) == '#') {
						$output .= $line . "\n";
					} else if (substr($line, 0, 9) == '/srv/nfs/') {
						$parts = explode("\t", $line);
						$output .= $parts[0] . "\t" . $_SESSION['fs_nfs_access'] . '(' . $_SESSION['fs_nfs_options'] . ')' . "\n";
					}

					$line = strtok("\n");
				}

				if (false === (file_put_contents($file, $output))) {
					workerLog('worker: Error: File write failed on ' . $file);
				}
			}

			// Restart
			if ($_SESSION['fs_nfs'] == 'On') {
				sysCmd('systemctl ' . $_SESSION['w_queueargs'] . ' nfs-server');
			}
			break;
		case 'keyboard':
			sysCmd('/var/www/util/sysutil.sh set-keyboard ' . $_SESSION['w_queueargs']);
			break;
		case 'lcdup':
			if ($_SESSION['w_queueargs'] == 1) {
				startLcdUpdater();
			} else {
				sysCmd('killall lcdup.sh > /dev/null 2>&1');
 				sysCmd('killall inotifywait > /dev/null 2>&1');
			}
			break;
		case 'gpio_svc':
			sysCmd('killall -s 9 gpio_buttons.py');
			if ($_SESSION['w_queueargs'] == 1) {
				startGpioBtnHandler();
			}
			break;
		case 'shellinabox':
			sysCmd('systemctl stop shellinabox');
			if ($_SESSION['w_queueargs'] == '1') {
				sysCmd('systemctl start shellinabox');
			}
			break;
		case 'clearsyslogs':
			sysCmd('/var/www/util/sysutil.sh clear-syslogs');
			break;
		case 'clearplayhistory':
			sysCmd('/var/www/util/sysutil.sh clear-playhistory');
			break;
		case 'compactdb':
			sysCmd('sqlite3 /var/local/www/db/moode-sqlite3.db "vacuum"');
			break;

		// inp-config jobs
		case 'audioin':
			setAudioIn($_SESSION['w_queueargs']);
			break;
		case 'audioout':
			setAudioOut($_SESSION['w_queueargs']);
			break;

		// command jobs
		case 'set_bg_image':
			$imgdata = base64_decode($_SESSION['w_queueargs'], true);
			if ($imgdata === false) {
				workerLog('worker: set_bg_image: base64_decode failed');
			} else {
				$fh = fopen('/var/local/www/imagesw/bgimage.jpg', 'w');
				fwrite($fh, $imgdata);
				fclose($fh);
			}
			break;
		case 'set_ralogo_image':
		case 'set_plcover_image':
			$job = $_SESSION['w_queue'];
			$queueArgs = explode(',', $_SESSION['w_queueargs'], 2);
			$img_name = $queueArgs[0];
			$img_data = base64_decode($queueArgs[1], true);

			if ($job == 'set_ralogo_image') {
				$img_dir = RADIO_LOGOS_ROOT;
				$thm_dir = 'thumbs/';
			} else {
 				$img_dir = PLAYLIST_COVERS_ROOT;
				$thm_dir = '';
			}

			if ($img_data === false) {
				workerLog('worker: '. $job .': base64_decode failed');
			} else {
				// Imported image
				$file = $img_dir . TMP_IMAGE_PREFIX . $img_name . '.jpg';
				$fh = fopen($file, 'w');
				fwrite($fh, $img_data);
				fclose($fh);
				sysCmd('chmod 0777 "' . $file . '"');

				// Thumbnail
				$img_str = file_get_contents($file);
				$image = imagecreatefromstring($img_str);
				$thm_w = 200;
				$thm_q = 75;

				// Image h/w
				$img_w = imagesx($image);
				$img_h = imagesy($image);
				// Thumbnail height
				$thm_h = ($img_h / $img_w) * $thm_w;

				// Standard thumbnail
				if (($thumb = imagecreatetruecolor($thm_w, $thm_h)) === false) {
					workerLog('worker: '. $job .': error 1a: imagecreatetruecolor() ' . $file);
					break;
				}
				if (imagecopyresampled($thumb, $image, 0, 0, 0, 0, $thm_w, $thm_h, $img_w, $img_h) === false) {
					workerLog('worker: '. $job .': error 2a: imagecopyresampled() ' . $file);
					break;
				}
				if (imagejpeg($thumb, $img_dir . $thm_dir . TMP_IMAGE_PREFIX . $img_name . '.jpg', $thm_q) === false) {
					workerLog('worker: '. $job .': error 4a: imagejpeg() ' . $file);
					break;
				}
				if (imagedestroy($thumb) === false) {
					workerLog('worker: '. $job .': error 5a: imagedestroy() ' . $file);
					break;
				}

				if ($job == 'set_ralogo_image') {
					// Small thumbnail
					if (($thumb = imagecreatetruecolor(THM_SM_W, THM_SM_H)) === false) {
						workerLog('worker: '. $job .': error 1b: imagecreatetruecolor() ' . $file);
						break;
					}
					if (imagecopyresampled($thumb, $image, 0, 0, 0, 0, THM_SM_W, THM_SM_H, $img_w, $img_h) === false) {
						workerLog('worker: '. $job .': error 2b: imagecopyresampled() ' . $file);
						break;
					}
					if (imagedestroy($image) === false) {
						workerLog('worker: '. $job .': error 3b: imagedestroy() ' . $file);
						break;
					}
					if (imagejpeg($thumb, $img_dir . $thm_dir . TMP_IMAGE_PREFIX . $img_name . '_sm.jpg', THM_SM_Q) === false) {
						workerLog('worker: '. $job .': error 4b: imagejpeg() ' . $file);
						break;
					}
					if (imagedestroy($thumb) === false) {
						workerLog('worker: '. $job .': error 5b: imagedestroy() ' . $file);
						break;
					}
				}
			}

			sysCmd('chmod 0777 "' . $img_dir . $thm_dir . TMP_IMAGE_PREFIX . '"*');

			break;

		// Other jobs
		case 'reboot':
		case 'poweroff':
			$result = sqlQuery("UPDATE cfg_system SET value='0' WHERE param='wrkready'", sqlConnect());
			sourceMount('unmountall');
			workerLog('worker: NAS sources unmounted');
			sysCmd('/var/local/www/commandw/restart.sh ' . $_SESSION['w_queue']);
			break;
		case 'upd_clock_radio':
			$GLOBALS['clkradio_start_time'] = substr($_SESSION['clkradio_start'], 0, 8);
			$GLOBALS['clkradio_stop_time'] = substr($_SESSION['clkradio_stop'], 0, 8);
			$GLOBALS['clkradio_start_days'] = explode(',', substr($_SESSION['clkradio_start'], 9));
			$GLOBALS['clkradio_stop_days'] = explode(',', substr($_SESSION['clkradio_stop'], 9));
			break;
	}

	// Reset job queue
	$_SESSION['w_queue'] = '';
	$_SESSION['w_queueargs'] = '';
	$_SESSION['w_lock'] = 0;
	$_SESSION['w_active'] = 0;
}
