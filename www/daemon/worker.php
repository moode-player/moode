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

//
// STARTUP SEQUENCE
//

sysCmd('truncate ' . MOODE_LOG . ' --size 0');
$dbh = sqlConnect();
$result = sqlQuery("UPDATE cfg_system SET value='0' WHERE param='wrkready'", $dbh);
$moodeSeries = substr(getMoodeRel(), 1, 1); // rNNN format

//
workerLog('worker: --');
workerLog('worker: -- Start moOde ' . $moodeSeries .  ' series');
workerLog('worker: --');
//

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
	$logMsg = 'worker: ERROR: Login User ID does not exist, unable to continue';
	workerLog($logMsg);
	exit($logMsg . "\n");
}

// Check for Linux startup complete
workerLog('worker: Waiting for Linux startup...');
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
	$logMsg = 'worker: ERROR: Linux startup failed to complete after waiting ' . ($maxLoops * $sleepTime) . ' seconds';
	workerLog($logMsg);
	exit($logMsg . "\n");
}

// Boot file recovery (rare)
if (file_exists(BOOT_CONFIG_TXT) && count(file(BOOT_CONFIG_TXT)) > 10) {
	// Backup
	sysCmd('cp ' . BOOT_CONFIG_TXT . ' ' . BOOT_CONFIG_BKP);
	workerLog('worker: Boot config backed up');
} else {
	// Restore
	sysCmd('cp ' . BOOT_CONFIG_BKP . ' ' . BOOT_CONFIG_TXT);
	workerLog('worker: WARNING: Boot config restored');
	workerLog('worker: WARNING: Restart required');
}

// Ensure package holds are in effect
sysCmd('moode-apt-mark hold > /dev/null 2>&1');

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
sysCmd('touch /var/log/shairport-sync.log');
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
sysCmd('chmod 0666 /var/log/shairport-sync.log');
sysCmd('chmod 0666 ' . MOODE_LOG);
sysCmd('chmod 0666 ' . MOUNTMON_LOG);
workerLog('worker: File check (OK)');

// Prune old session vars
sysCmd('moodeutl -D airplayactv');
sysCmd('moodeutl -D AVAILABLE');
sysCmd('moodeutl -D eth_port_fix');
sysCmd('moodeutl -D card_error');
sysCmd('moodeutl -D cdsp_from_link');
sysCmd('moodeutl -D saved_upnp_path');
sysCmd('moodeutl -D upnp_browser');
sysCmd('moodeutl -D btmulti');
sysCmd('moodeutl -D upd_rx_adv_toggle');
sysCmd('moodeutl -D upd_tx_adv_toggle');
sysCmd('moodeutl -D piano_dualmode');
sysCmd('moodeutl -D wrkready');
workerLog('worker: Session vacuumed');

// Open session and load cfg_system and cfg_radio
phpSession('load_system');
phpSession('load_radio');
workerLog('worker: Session loaded');

// Debug logging
if (!isset($_SESSION['debuglog'])) {
	$_SESSION['debuglog'] = '0';
}
workerLog('worker: Debug logging (' . ($_SESSION['debuglog'] == '1' ? 'ON' : 'OFF') . ')');

// Reduce system logging
if (!isset($_SESSION['reduce_sys_logging'])) {
	$_SESSION['reduce_sys_logging'] = '0';
}
workerLog('worker: Reduced system logging (' . ($_SESSION['reduce_sys_logging'] == '1' ? 'ON' : 'OFF') . ')');

// Reconfigure certain 3rd party installs
// RoonBridge
// NOTE: Their installer sets the systemd unit to enabled but we need it disabled because we start/stop it via System Config setting
if (file_exists('/opt/RoonBridge/start.sh') === true) {
	$_SESSION['roonbridge_installed'] = 'yes';
	if (sysCmd('systemctl is-enabled roonbridge')[0] == 'enabled') {
		sysCmd('systemctl disable roonbridge');
		sysCmd('systemctl stop roonbridge');
		workerLog('worker: RoonBridge systemd unit set to disabled');
	}
} else {
	$_SESSION['roonbridge_installed'] = 'no';
}
// Allo Boss 2 OLED
// NOTE: Their installer adds lines to rc.local which are not needed because we start/stop it via systemd unit
if (!empty(sysCmd('grep "boss2" /etc/rc.local')[0])) {
	sleep(1); // Allow rc.local script time to exit
	sysCmd('sed -i /boss2/d /etc/rc.local');
	workerLog('worker: Allo Boss2 rc.local lines removed');
}

//
workerLog('worker: --');
workerLog('worker: -- Audio debug');
workerLog('worker: --');
//

// Verify audio device configuration
$mpdDevice = sqlQuery("SELECT value FROM cfg_mpd WHERE param='device'", $dbh);
$cards = getAlsaCards();
workerLog('worker: ALSA cards: (0:' . $cards[0] . ' | 1:' . $cards[1]. ' | 2:' . $cards[2]. ' | 3:' . $cards[3]);
workerLog('worker: MPD config: (' . $mpdDevice[0]['value'] . ':' . $_SESSION['adevname'] .
	' | mixer:(' . $_SESSION['amixname'] . ') | card:' . $mpdDevice[0]['value'] . ')');

// Check for device not found
if ($_SESSION['i2sdevice'] == 'None' && $_SESSION['i2soverlay'] == 'None' && $cards[$mpdDevice[0]['value']] == 'empty') {
	workerLog('worker: WARNING: No device found at MPD configured card ' . $mpdDevice[0]['value']);
}

// Zero out ALSA volume
$alsaMixerName = getAlsaMixerName($_SESSION['i2sdevice']);
if ($alsaMixerName != 'Invalid card number.') {
	workerLog('worker: ALSA mixer actual (' . $alsaMixerName . ')');
	if ($alsaMixerName != 'none') {
		sysCmd('amixer sset ' . '"' . $alsaMixerName . '"' . ' on' ); // Ensure state is 'on'
		sysCmd('/var/www/util/sysutil.sh set-alsavol ' . '"' . $alsaMixerName . '"' . ' 0');
		$result = sysCmd('/var/www/util/sysutil.sh get-alsavol ' . '"' . $alsaMixerName . '"');
		workerLog('worker: ALSA ' . trim($alsaMixerName) . ' volume set to (' . $result[0] . ')');
	} else {
		phpSession('write', 'alsavolume', 'none'); // Hardware volume controller not detected
		phpSession('write', 'amixname', 'none');
		workerLog('worker: ALSA volume (none)');
	}
}

//
workerLog('worker: --');
workerLog('worker: -- System');
workerLog('worker: --');
//

// Pi Imager: Import time zone and keyboard layout
$timeZone = sysCmd("timedatectl show | awk -F\"=\" '/Timezone/{print $2;exit;}'");
$keyboard = sysCmd("cat /etc/default/keyboard | awk -F\"=\" '/XKBLAYOUT/{print $2;exit;}'");
phpSession('write', 'timezone', $timeZone[0]);
phpSession('write', 'keyboard', trim($keyboard[0], "\""));

// Store platform data
phpSession('write', 'hdwrrev', getHdwrRev());
$_SESSION['moode_release'] = getMoodeRel(); // rNNN format
$_SESSION['raspbianver'] = sysCmd('cat /etc/debian_version')[0];
$_SESSION['kernelver'] = sysCmd("uname -vr | awk '{print $1\" \"$2}'")[0];
$_SESSION['procarch'] = sysCmd('uname -m')[0];
$_SESSION['mpdver'] = sysCmd("mpd -V | grep 'Music Player Daemon' | awk '{print $4}'")[0];
$_SESSION['user_id'] = getUserID();
$_SESSION['home_dir'] = '/home/' . $_SESSION['user_id'];

// Log platform data
workerLog('worker: Host      (' . $_SESSION['hostname'] . ')');
workerLog('worker: Hardware  (' . $_SESSION['hdwrrev'] . ')');
workerLog('worker: moOde     (' . getMoodeRel('verbose') . ')'); // major.minor.patch yyyy-mm-dd
workerLog('worker: RaspiOS   (' . $_SESSION['raspbianver'] . ')');
workerLog('worker: Kernel    (' . $_SESSION['kernelver'] . ')');
workerLog('worker: Procarch  (' . $_SESSION['procarch'] . ', ' . ($_SESSION['procarch'] == 'aarch64' ? '64-bit' : '32-bit') . ')');
workerLog('worker: MPD ver   (' . $_SESSION['mpdver'] . ')');
workerLog('worker: CPU gov   (' . $_SESSION['cpugov'] . ')');
workerLog('worker: Userid    (' . $_SESSION['user_id'] . ')');
workerLog('worker: Homedir   (' . $_SESSION['home_dir'] . ')');
workerLog('worker: Timezone  (' . $_SESSION['timezone'] . ')');
workerLog('worker: Keyboard  (' . $_SESSION['keyboard'] . ')');
// USB boot
$piModelNum = substr($_SESSION['hdwrrev'], 3, 1);
if ($piModelNum == '3') { // 3B, B+, A+
	$result = sysCmd('vcgencmd otp_dump | grep 17:');
	if ($result[0] == '17:3020000a') {
		sysCmd('sed -i /program_usb_boot_mode/d ' . '/boot/config.txt');
		$msg = 'enabled';
	} else {
		$msg = 'not enabled yet';
	}
	workerLog('worker: USB boot  (' . $msg .')');
} else if ($piModelNum == '4') { // 4, 400
	$bootloaderMinDate = new DateTime("Sep 3 2020");
	$bootloaderActualDate = new DateTime(sysCmd("vcgencmd bootloader_version | awk 'NR==1 {print $1\" \" $2\" \" $3}'")[0]);
	if ($bootloaderActualDate >= $bootloaderMinDate) {
		$msg = 'enabled';
	} else {
		$msg = 'not enabled yet';
	}
	workerLog('worker: USB boot  (' . $msg .')');
} else {
	workerLog('worker: USB boot  (not available)');
}

// Rootfs expansion
// NOTE: Default for release images is to auto-expand at first boot. Development images may be set to a custom size.
$result = sysCmd('lsblk -o size -nb /dev/disk/by-label/rootfs');
$msg = $result[0] > DEV_ROOTFS_SIZE ? 'expanded' : 'not expanded';
workerLog('worker: File sys  (' . $msg . ')');

// Turn on/off HDMI port
$cmd = $_SESSION['hdmiport'] == '1' ? 'tvservice -p' : 'tvservice -o';
sysCmd($cmd . ' > /dev/null');
workerLog('worker: HDMI port (' . ($_SESSION['hdmiport'] == '1' ? 'On)' : 'Off)'));

// LED states
if (substr($_SESSION['hdwrrev'], 0, 7) == 'Pi-Zero') {
	$led0Trigger = explode(',', $_SESSION['led_state'])[0] == '0' ? 'none' : '/actpwr';
	sysCmd('echo ' . $led0Trigger . ' | sudo tee /sys/class/leds/ACT/trigger > /dev/null');
	workerLog('worker: Sys LED0  (' . ($led0Trigger == 'none' ? 'Off' : 'On') . ')');
	workerLog('worker: Sys LED1  (sysclass does not exist)');
} else if ($_SESSION['hdwrrev'] == 'Allo USBridge SIG [CM3+ Lite 1GB v1.0]' || substr($_SESSION['hdwrrev'], 3, 1) == '1') {
	$led0Trigger = explode(',', $_SESSION['led_state'])[0] == '0' ? 'none' : '/actpwr';
	sysCmd('echo ' . $led0Trigger . ' | sudo tee /sys/class/leds/ACT/trigger > /dev/null');
	workerLog('worker: Sys LED0  (' . ($led0Trigger == 'none' ? 'Off' : 'On') . ')');
	workerLog('worker: Sys LED1  (sysclass does not exist)');
} else {
	$led1Brightness = explode(',', $_SESSION['led_state'])[1] == '0' ? '0' : '255';
	$led0Trigger = explode(',', $_SESSION['led_state'])[0] == '0' ? 'none' : '/actpwr';
	sysCmd('echo ' . $led1Brightness . ' | sudo tee /sys/class/leds/PWR/brightness > /dev/null');
	sysCmd('echo ' . $led0Trigger . ' | sudo tee /sys/class/leds/ACT/trigger > /dev/null');
	workerLog('worker: Sys LED0  (' . ($led0Trigger == 'none' ? 'Off' : 'On') . ')');
	workerLog('worker: Sys LED1  (' . ($led1Brightness == '0' ? 'Off' : 'On') . ')');
}

//
workerLog('worker: --');
workerLog('worker: -- Network');
workerLog('worker: --');
//

// Pi Imager: Import SSID/PSK/Country
if (file_exists('/etc/wpa_supplicant/wpa_supplicant.conf')) {
	$moodeHeader = sysCmd('cat /etc/wpa_supplicant/wpa_supplicant.conf 2>&1 | grep "This file is automatically generated"');

	if (empty($moodeHeader)) {
		// No moOde header so lets do the import
		$ssid = sysCmd("cat /etc/wpa_supplicant/wpa_supplicant.conf 2>&1 | awk -F\"=\" '/ssid=\"/{print $2;exit;}'");
		$psk = sysCmd("cat /etc/wpa_supplicant/wpa_supplicant.conf 2>&1 | awk -F\"=\" '/psk=/ {print $2;exit;}'");
		$country = sysCmd("cat /etc/wpa_supplicant/wpa_supplicant.conf 2>&1 | awk -F\"=\" '/country=/ {print $2;exit;}'");
		if (!empty($ssid) && !empty($psk)) {
			// Update wlan0 SSID, PSK and Country
			sqlQuery("UPDATE cfg_network SET wlanssid=" . $ssid[0] . ", wlanpwd='" . $psk[0] . "', wlan_psk='" . $psk[0] . "', wlan_country='" . $country[0] . "' WHERE id='2'", $dbh);
			// Update apd0 SSID
			// NOTE: PSK set blank because plaintext WiFi password needed for generating a PSK is not available from Pi Imager
			sqlQuery("UPDATE cfg_network SET wlanssid='" . ucfirst($_SESSION['hostname']) . "', wlanpwd='', wlan_psk='' WHERE id='3'", $dbh);
			// Generate conf files
			cfgNetIfaces();
			resetApMode();
			cfgHostApd();
			workerLog('worker: WiFi SSID/PSK imported');
		} else {
			workerLog('worker: WARNING: WiFi SSID/PSK import failed');
		}
	}
}

// Check eth0
$eth0 = sysCmd('ip addr list | grep eth0');
if (empty($eth0)) {
	workerLog('worker: eth0 adapter does not exist');
	$eth0Ip = '';
} else {
	workerLog('worker: eth0 adapter exists');
	workerLog('worker: eth0 address check (' . ($_SESSION['eth0chk'] == '1' ? 'On' : 'Off') . ')');

	if ($_SESSION['eth0chk'] == '1') {
		workerLog('worker: eth0 address check (up to ' . $_SESSION['ipaddr_timeout'] . ' secs)');
		$eth0Ip = checkForIpAddr('eth0', $_SESSION['ipaddr_timeout']);
	} else {
		$eth0Ip = sysCmd("ip addr list eth0 | grep \"inet \" |cut -d' ' -f6|cut -d/ -f1");
	}

	if (empty($eth0Ip)) {
		workerLog('worker: eth0 address not assigned');
	} else {
		logNetworkInfo('eth0');
	}
}

// Check wlan0
$wlan0 = sysCmd('ip addr list | grep wlan0');
if (empty($wlan0)) {
	workerLog('worker: wlan0 adapter does not exist');
} else {
	$cfgNetwork = sqlQuery('SELECT * FROM cfg_network', $dbh);
	//DELETE: $cfgSSID = sqlQuery("SELECT ssid FROM cfg_ssid WHERE ssid NOT IN ('" . $cfgNetwork[1]['wlanssid'] . "', 'None (activates AP mode)')", $dbh);
	$cfgSSID = sysCmd("moodeutl -q \"SELECT ssid FROM cfg_ssid WHERE ssid NOT IN ('" . $cfgNetwork[1]['wlanssid'] . "', 'None (activates AP mode)')\"");
	$altSSIDList = empty($cfgSSID) ? 'None' : implode(',', $cfgSSID);
	workerLog('worker: wlan0 adapter exists');
	workerLog('worker: wlan0 country (' . $cfgNetwork[1]['wlan_country'] . ')');
	workerLog('worker: wlan0 configured SSID (' . $cfgNetwork[1]['wlanssid'] . ')');
	workerLog('worker: wlan0 SSID alternates (' . $altSSIDList . ')');
	workerLog('worker: wlan0 router mode (' . $cfgNetwork[2]['wlan_router'] . ')');

	// Check for wlan0 IP address
	$wlan0Ip = '';
	$_SESSION['apactivated'] = false;
	if ($cfgNetwork[1]['wlanssid'] != 'None (activates AP mode)') {
		workerLog('worker: wlan0 address check (up to ' . $_SESSION['ipaddr_timeout'] . ' secs)');
		$wlan0Ip = checkForIpAddr('wlan0', $_SESSION['ipaddr_timeout']);
	}

	// AP mode activation
	if (empty($wlan0Ip)) {
		workerLog('worker: wlan0 address not assigned');
		if (empty($eth0Ip) || $cfgNetwork[2]['wlan_router'] == 'On') {
			workerLog('worker: wlan0 activating AP mode');
			activateApMode();
			workerLog('worker: wlan0 address check (up to ' . $_SESSION['ipaddr_timeout'] . ' secs)');
			$wlan0Ip = checkForIpAddr('wlan0', $_SESSION['ipaddr_timeout']);
			if (empty($wlan0Ip)) {
				$_SESSION['apactivated'] = false;
				workerLog('worker: wlan0 address not assigned');
			} else {
				$_SESSION['apactivated'] = true;
				workerLog('worker: wlan0 AP mode activated (SSID ' . $cfgNetwork[2]['wlanssid'] . ')');
			}
		} else {
			workerLog('worker: wlan0 AP mode not activated, eth0 active but Router mode is Off');
			workerLog('worker: wlan0 address not assigned');
		}
	} else {
		$result = sysCmd("iwconfig wlan0 | grep 'ESSID' | awk -F':' '{print $2}' | awk -F'\"' '{print $2}'");
		workerLog('worker: wlan0 connected to SSID (' . $result[0] . ')');
	}

	// Reset dhcpcd.conf to non-AP mode addressing in case a hard reboot/poweroff occurs
	// NOTE: This does not have any effect until system is restarted
	resetApMode();

	// AP Router mode activation
	if ($cfgNetwork[2]['wlan_router'] == 'On' && $_SESSION['apactivated'] == true) {
		sysCmd('systemctl start nftables');
		workerLog('worker: wlan0 Router mode activated' . (empty($eth0Ip) ? ' but no Ethernet address' : ''));
	}

	if (!empty($wlan0Ip)) {
		if ($piModelNum == '3' || $piModelNum == '4' || substr($_SESSION['hdwrrev'], 0, 7) == 'Pi-Zero') {
			sysCmd('/sbin/iwconfig wlan0 power off');
			workerLog('worker: wlan0 power save (Disabled)');
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

//
workerLog('worker: --');
workerLog('worker: -- Software update');
workerLog('worker: --');
//
if (!isset($_SESSION['updater_auto_check'])) {
	$_SESSION['updater_auto_check'] = 'Off';
}
$validIPAddress = ($_SESSION['ipaddress'] != '0.0.0.0' && $wlan0Ip[0] != '172.24.1.1');
$_SESSION['updater_available_update'] = updaterAutoCheck($validIPAddress);

//
workerLog('worker: --');
workerLog('worker: -- File sharing');
workerLog('worker: --');
//

// SMB
if ($_SESSION['fs_smb'] == 'On') {
	sysCmd('systemctl start smbd');
	sysCmd('systemctl start nmbd');
}
// NFS
if ($_SESSION['fs_nfs'] == 'On') {
	sysCmd('systemctl start nfs-server');
}
workerLog('worker: SMB file sharing (' . $_SESSION['fs_smb'] . ')');
workerLog('worker: NFS file sharing (' . $_SESSION['fs_nfs'] . ')');

//
workerLog('worker: --');
workerLog('worker: -- Audio config');
workerLog('worker: --');
//

$updateMpdConf == false;
if (!file_exists('/etc/mpd.conf')) {
	$updateMpdConf = true;
	$mpdConfUpdMsg = 'MPD conf is missing, generate it';
} else {
	switch (playbackDestinationType()) {
		case PlaybackDestinationType.TX:
			// Skip update otherwise Multiroom Sender ALSA config gets reverted
			$mpdConfUpdMsg = 'MPD conf update skipped (Tx On)';
			break;
		case PlaybackDestinationType.USB:
			if( $_SESSION['inplace_upd_applied'] == '1' ) {
				$mpdConfUpdMsg = 'MPD conf updated (USB device + In-place update)';
				$updateMpdConf = true;
			} else {
				// Skip update otherwise USB mixer name is not preserved if device unplugged or turned off
				$mpdConfUpdMsg = 'MPD conf update skipped (USB device)';
			}
			break;
		case PlaybackDestinationType.LOCAL:
		case PlaybackDestinationType.I2S:
			$updateMpdConf = true;
			$mpdConfUpdMsg = 'MPD conf updated';
			break;
		default:
			$mpdConfUpdMsg = 'MPD conf update error: unknown playback destination type';
			break;
	}
}

if ($updateMpdConf == true) {
	updMpdConf($_SESSION['i2sdevice']);
	phpSession('write', 'inplace_upd_applied', '0');
}

workerLog('worker: ' . $mpdConfUpdMsg);

// Ensure audio output is unmuted for these devices
if ($_SESSION['i2sdevice'] == 'IQaudIO Pi-AMP+') {
	sysCmd('/var/www/util/sysutil.sh unmute-pi-ampplus');
	workerLog('worker: IQaudIO Pi-AMP+ unmuted');
} else if ($_SESSION['i2sdevice'] == 'IQaudIO Pi-DigiAMP+') {
	sysCmd('/var/www/util/sysutil.sh unmute-pi-digiampplus');
	workerLog('worker: IQaudIO Pi-DigiAMP+ unmuted');
}

// Log audio device info
workerLog('worker: ALSA card number (' . $_SESSION['cardnum'] . ')');
if ($_SESSION['i2sdevice'] == 'None' && $_SESSION['i2soverlay'] == 'None') {
	workerLog('worker: MPD audio output (' . getAlsaDeviceNames()[$_SESSION['cardnum']] . ')');
} else {
	workerLog('worker: MPD audio output (' . $_SESSION['i2sdevice'] . ')');
}
if ($cards[$mpdDevice[0]['value']] == 'empty') {
	workerLog('worker: WARNING: No device found at MPD configured card ' . $mpdDevice[0]['value']);
} else {
	$_SESSION['audio_formats'] = sysCmd('moodeutl -f')[0];
	workerLog('worker: Audio formats (' . $_SESSION['audio_formats'] . ')');
}

// Might need this at some point
$deviceName = getAlsaDeviceNames()[$_SESSION['cardnum']];
if ($_SESSION['i2sdevice'] == 'None'  && $_SESSION['i2soverlay'] == 'None' &&
	$deviceName != 'Headphone jack' && $deviceName != 'HDMI-1' && $deviceName != 'HDMI-2') {
	$usbAudio = true;
} else {
	$usbAudio = false;
}

// Store alsa mixer name for use by sysutil.sh get/set-alsavol and vol.sh
//phpSession('write', 'amixname', getAlsaMixerName($_SESSION['i2sdevice']));
// Report ALSA mixer name
workerLog('worker: ALSA mixer name (' . $_SESSION['amixname'] . ')');

// Report MPD mixer type (friendly name)
$mixerType = ucfirst($_SESSION['mpdmixer']);
$mixerType = isMPD2CamillaDSPVolSyncEnabled() ? 'CamillaDSP' : $mixerType;
$mixerType = $mixerType == 'None' ? 'Fixed 0dB' : $mixerType;
workerLog('worker: MPD mixer type (' . $mixerType . ')');

// Ensure mpdmixer_local = mpdmixer
if ($_SESSION['audioout'] == 'Local') {
	phpSession('write', 'mpdmixer_local', $_SESSION['mpdmixer']);
}

// Check for presence of hardware volume controller
$result = sysCmd('/var/www/util/sysutil.sh get-alsavol ' . '"' . $_SESSION['amixname'] . '"');
if (substr($result[0], 0, 6 ) == 'amixer') {
	phpSession('write', 'alsavolume', 'none');
	workerLog('worker: Hardware volume controller not detected');
} else {
	$result[0] = str_replace('%', '', $result[0]);
	phpSession('write', 'alsavolume', $result[0]); // volume level
	workerLog('worker: Hardware volume controller exists');
	workerLog('worker: Max ALSA volume (' . $_SESSION['alsavolume_max'] . '%)');
}

// Report ALSA output mode
workerLog('worker: ALSA output mode (' . ALSA_OUTPUT_MODE_NAME[$_SESSION['alsa_output_mode']] . ')');

// Start ALSA loopback
if ($_SESSION['alsa_loopback'] == 'On') {
	sysCmd('modprobe snd-aloop');
} else {
	sysCmd('modprobe -r snd-aloop');
}
workerLog('worker: ALSA loopback (' . $_SESSION['alsa_loopback'] . ')');

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
workerLog('worker: CamillaDSP configuration (' . rtrim($_SESSION['camilladsp'], '.yml') . ')');

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
	workerLog('worker: Piano 2.1 output mode (' . $outputMode . ')');

	// Workaround: Send brief inaudible PCM to one of the channels to initialize volume
	sysCmd('amixer -M -c 0 sset "Master" 0');
	sysCmd('speaker-test -c 2 -s 2 -r 48000 -F S16_LE -X -f 24000 -t sine -l 1');
	workerLog('worker: Piano 2.1 initialized');
}

// Start Allo Boss2 OLED display
if ($_SESSION['i2sdevice'] == 'Allo Boss 2 DAC' && !file_exists($_SESSION['home_dir'] . '/boss2oled_no_load')) {
	sysCmd('systemctl start boss2oled');
	workerLog('worker: Boss 2 OLED started');
}

// Reset renderer active flags
// If any are still 1 then a reboot or power off occured while the renderer was active
// which would leave ALSA or CamillaDSP volume at 100% so let's reset volume to 0.
$result = sqlQuery("SELECT value from cfg_system WHERE param in ('btactive', 'aplactive', 'spotactive',
	'slactive', 'rbactive', 'inpactive')", $dbh);
if ($result[0]['value'] == '1' || $result[1]['value'] == '1' || $result[2]['value'] == '1' ||
	$result[3]['value'] == '1' || $result[4]['value'] == '1' || $result[5]['value'] == '1') {
	// Set Knob volume to 0 for vol.sh downstream in this startup section
	phpSession('write', 'volknob', '0');
}
$result = sqlQuery("UPDATE cfg_system SET value='0' WHERE param='btactive' OR param='aplactive'
	OR param='spotactive' OR param='slactive' OR param='rbactive' OR param='inpactive'", $dbh);
workerLog('worker: Renderer active flags (reset)');

//
workerLog('worker: --');
workerLog('worker: -- MPD startup');
workerLog('worker: --');
//

// Start MPD
sysCmd("systemctl start mpd");
workerLog('worker: MPD started');
$sock = openMpdSock('localhost', 6600);
workerLog($sock === false ? 'worker: MPD connection refused' : 'worker: MPD accepting connections');
// Ensure valid MPD output config
$mpdOutput = configMpdOutput();
sysCmd('mpc enable only "' . $mpdOutput .'"');
setMpdHttpd();
// Report MPD outputs
$mpdOutputs = getMpdOutputs($sock);
foreach ($mpdOutputs as $mpdOutput) {
	workerLog('worker: ' . $mpdOutput);
}
// MPD crossfade
workerLog('worker: MPD crossfade (' . ($_SESSION['mpdcrossfade'] == '0' ? 'off' : $_SESSION['mpdcrossfade'] . ' secs')  . ')');
sendMpdCmd($sock, 'crossfade ' . $_SESSION['mpdcrossfade']);
$resp = readMpdResp($sock);
// Ignore CUE files
setCuefilesIgnore($_SESSION['cuefiles_ignore']);
workerLog('worker: MPD ignore CUE files (' . ($_SESSION['cuefiles_ignore'] == '1' ? 'yes' : 'no') . ')');
// Load Default PLaylist if first boot
if ($_SESSION['first_use_help'] == 'y,y') {
	sendMpdCmd($sock, 'clear');
	$resp = readMpdResp($sock);
	sendMpdCmd($sock, 'load "Default Playlist"');
	$resp = readMpdResp($sock);
	workerLog('worker: Default playlist loaded for first boot');
}
// MPD/CamillaDSP volume sync
workerLog('worker: MPD camilladsp volume sync (' . ucfirst($_SESSION['camilladsp_volume_sync']) . ')');
workerLog('worker: MPD camilladsp volume range (' . $_SESSION['camilladsp_volume_range'] . ' dB)');
$serviceCmd = isMPD2CamillaDSPVolSyncEnabled() ? 'start' : 'stop';
sysCmd('systemctl ' . $serviceCmd .' mpd2cdspvolume');

//
workerLog('worker: --');
workerLog('worker: -- Music sources');
workerLog('worker: --');
//

// USB sources
$usbDrives = sysCmd('ls /media');
if (empty($usbDrives)) {
	workerLog('worker: USB: No drives found');
} else {
	foreach ($usbDrives as $usbDrive) {
		workerLog('worker: USB: ' . $usbDrive);
	}
}
// NAS sources
$mounts = sqlRead('cfg_source', $dbh);
if ($mounts === true) { // Empty result
	workerLog('worker: NAS: No music sources defined');
} else {
	foreach ($mounts as $mp) {
		workerLog('worker: NAS: ' . $mp['name']);
	}
	$result = sourceMount('mountall');
	workerLog('worker: NAS: ' . $result);
}
//
workerLog('worker: --');
workerLog('worker: -- Feature availability');
workerLog('worker: --');
//

// Configure input select
if ($_SESSION['feat_bitmask'] & FEAT_INPSOURCE) {
	workerLog('worker: Input select (available)');
	$input = $_SESSION['audioin'] == 'Local' ? 'MPD' : ($_SESSION['audioin'] == 'Analog' ? 'Analog input' : 'S/PDIF input');
	workerLog('worker: Input (' . $input . ')');
	$output = ($_SESSION['i2sdevice'] == 'None' && $_SESSION['i2soverlay'] == 'None') ? getAlsaDeviceNames()[$_SESSION['cardnum']] :
		($_SESSION['i2sdevice'] != 'None' ? $_SESSION['i2sdevice'] : $_SESSION['i2soverlay']);
	workerLog('worker: Output (' . $output . ')');

	if ($_SESSION['i2sdevice'] == 'HiFiBerry DAC+ ADC' || strpos($_SESSION['i2sdevice'], 'Audiophonics ES9028/9038 DAC') !== -1) {
		setAudioIn($_SESSION['audioin']);
	} else {
		phpSession('write', 'volknob_mpd', '0'); // Reset saved MPD volume
	}
} else {
	workerLog('worker: Input select (n/a)');
}

// Start bluetooth controller and pairing agent
if ($_SESSION['feat_bitmask'] & FEAT_BLUETOOTH) {
	if (isset($_SESSION['btsvc']) && $_SESSION['btsvc'] == 1) {
		$status = startBluetooth();

		if ($status == 'started') {
			workerLog('worker: Bluetooth (available: ' . $status . ')');
			if (isset($_SESSION['pairing_agent']) && $_SESSION['pairing_agent'] == 1) {
				sysCmd('/var/www/daemon/blu_agent.py --agent --disable_pair_mode_switch --pair_mode --wait_for_bluez >/dev/null 2>&1 &');
				workerLog('worker: Pairing agent (started)');
			}
		} else {
			workerLog('worker: Bluetooth available: ' . $status);
		}
	} else {
		workerLog('worker: Bluetooth (available)');
	}
} else {
	workerLog('worker: Bluetooth (n/a)');
}

// Start airplay renderer
if ($_SESSION['feat_bitmask'] & FEAT_AIRPLAY) {
	$_SESSION['airplay_protocol'] = getAirPlayProtocolVer();

	if (isset($_SESSION['airplaysvc']) && $_SESSION['airplaysvc'] == 1) {
		$started = ': started';
		startAirPlay();
	} else {
		$started = '';
	}
	workerLog('worker: AirPlay renderer (available' . $started . ')');
} else {
	workerLog('worker: AirPlay renderer (n/a)');
}

// Start Spotify renderer
if ($_SESSION['feat_bitmask'] & FEAT_SPOTIFY) {
	if (isset($_SESSION['spotifysvc']) && $_SESSION['spotifysvc'] == 1) {
		$started = ': started';
		startSpotify();
	} else {
		$started = '';
	}
	workerLog('worker: Spotify renderer (available' . $started . ')');
} else {
	workerLog('worker: Spotify renderer (n/a)');
}

// Start Squeezelite renderer
if ($_SESSION['feat_bitmask'] & FEAT_SQUEEZELITE) {
	if (isset($_SESSION['slsvc']) && $_SESSION['slsvc'] == 1) {
		$started = ': started';
		cfgSqueezelite();
		startSqueezeLite();
	} else {
		$started = '';
	}
	workerLog('worker: Squeezelite (available' . $started . ')');
} else {
	workerLog('worker: Squeezelite renderer (n/a)');
}

// Start RroonBridge renderer
if ($_SESSION['feat_bitmask'] & FEAT_ROONBRIDGE) {
	if ($_SESSION['roonbridge_installed'] == 'yes') {
		if (isset($_SESSION['rbsvc']) && $_SESSION['rbsvc'] == 1) {
			$started = ': started';
			startRoonBridge();
		} else {
			$started = '';
		}
		workerLog('worker: RoonBridge renderer (available' . $started . ')');
	} else {
		workerLog('worker: RoonBridge renderer (not installed)');
	}
} else {
	workerLog('worker: RoonBridge renderer (n/a)');
}

// Start Multiroom audio
if ($_SESSION['feat_bitmask'] & FEAT_MULTIROOM) {
	// Sender
	if (isset($_SESSION['multiroom_tx']) && $_SESSION['multiroom_tx'] == 'On') {
		$started = ': started';
		loadSndDummy();
		startMultiroomSender();
	} else {
		$started = '';
	}
	workerLog('worker: Multiroom sender (available' . $started . ')');
	// Receiver
	if (isset($_SESSION['multiroom_rx']) && $_SESSION['multiroom_rx'] == 'On') {
		$started = ': started';
		startMultiroomReceiver();
	} else {
		$started = '';
	}
	workerLog('worker: Multiroom receiver (available' . $started . ')');
} else {
	workerLog('worker: Multiroom audio (n/a)');
}

// Start UPnP renderer
if ($_SESSION['feat_bitmask'] & FEAT_UPMPDCLI) {
	if (isset($_SESSION['upnpsvc']) && $_SESSION['upnpsvc'] == 1) {
		$started = ': started';
		startUPnP();
	} else {
		$started = '';
	}
	workerLog('worker: UPnP renderer (available' . $started . ')');
} else {
	workerLog('worker: UPnP renderer (n/a)');
}

// Start miniDLNA
if ($_SESSION['feat_bitmask'] & FEAT_MINIDLNA) {
	if (isset($_SESSION['dlnasvc']) && $_SESSION['dlnasvc'] == 1) {
		$started = ': started';
		startMiniDlna();
	} else {
		$started = '';
	}
	workerLog('worker: DLNA server (available' . $started . ')');
} else {
	workerLog('worker: DLNA Server (n/a)');
}

// Start GPIO button handler
if ($_SESSION['feat_bitmask'] & FEAT_GPIO) {
	if (isset($_SESSION['gpio_svc']) && $_SESSION['gpio_svc'] == 1) {
		$started = ': started';
		startGpioBtnHandler();
	} else {
		$started = '';
	}
	workerLog('worker: GPIO button handler (available' . $started . ')');
} else {
	workerLog('worker: GPIO button handler (n/a)');
}

// Start stream recorder
if ($_SESSION['feat_bitmask'] & FEAT_RECORDER) {
	if ($_SESSION['recorder_status'] == 'Not installed') {
		$started = ': Not installed';
	} else if ($_SESSION['recorder_status'] == 'On') {
		$started = ': started';
		sysCmd('mpc enable "' . STREAM_RECORDER . '"');
	} else {
		$started = '';
	}
	workerLog('worker: Stream recorder (available' . $started . ')');
} else {
	workerLog('worker: Stream recorder (n/a)');
}

// TEST: HTTPS-Only mode
if ($_SESSION['feat_bitmask'] & FEAT_HTTPS) {
	if (!isset($_SESSION['nginx_https_only'])) {
		$_SESSION['nginx_https_only'] = '0'; // Initially Off
	}

	if ($_SESSION['nginx_https_only'] == '1') {
		$cmd = 'openssl x509 -text -noout -in /etc/ssl/certs/nginx-selfsigned.crt | grep "Subject: CN" | cut -d "=" -f 2';
		$CN = trim(sysCmd($cmd)[0]);
		if ($CN != $_SESSION['hostname'] . '.local') {
			sysCmd('/var/www/util/gen-cert.sh');
			workerLog('worker: New cert created for ' . $_SESSION['hostname']);
		}
	}

	$msg = $_SESSION['nginx_https_only'] == '0' ? 'Off' : 'On';
	workerLog('worker: HTTPS-Only mode (available: ' . $msg . ')');
} else {
	workerLog('worker: HTTPS-Only mode (n/a)');
}

//
workerLog('worker: --');
workerLog('worker: -- Other');
workerLog('worker: --');
//

// Start rotary encoder
if (isset($_SESSION['rotaryenc']) && $_SESSION['rotaryenc'] == 1) {
	sysCmd('systemctl start rotenc');
	workerLog('worker: Rotary encoder on (' . $_SESSION['rotenc_params'] . ')');
}

// Log USB volume knob on/off state
workerLog('worker: USB volume knob (' . ($_SESSION['usb_volknob'] == '1' ? 'On' : 'Off') . ')');

// Start LCD updater engine
if (isset($_SESSION['lcdup']) && $_SESSION['lcdup'] == 1) {
	startLcdUpdater();
	workerLog('worker: LCD updater engine (started)');
}

// Start shellinabox
if (isset($_SESSION['shellinabox']) && $_SESSION['shellinabox'] == 1) {
	sysCmd('systemctl start shellinabox');
	workerLog('worker: Shellinabox SSH started');
}

// USB auto-mounter
workerLog('worker: USB auto-mounter (' . $_SESSION['usb_auto_mounter'] . ')');

// Restore MPD volume level
$inputSwitchDevices = array('HiFiBerry DAC+ ADC', 'Audiophonics ES9028/9038 DAC', 'Audiophonics ES9028/9038 DAC (Pre 2019)');
if (!in_array($_SESSION['i2sdevice'], $inputSwitchDevices)) {
	phpSession('write', 'volknob_mpd', '0');
	phpSession('write', 'volknob_preamp', '0');
}
workerLog('worker: Saved MPD vol level (' . $_SESSION['volknob_mpd'] . ')');
workerLog('worker: Preamp volume level (' . $_SESSION['volknob_preamp'] . ')');
// Since we initially set alsa volume to 0 at the beginning of startup it must be reset
// Set ALSA volume to 0dB (100%) depending on mixer type
if ($_SESSION['alsavolume'] != 'none') {
	setALSAVolumeForMPD($_SESSION['mpdmixer'], $_SESSION['amixname'], $_SESSION['alsavolume_max']);
}
$volume = $_SESSION['volknob_mpd'] != '0' ? $_SESSION['volknob_mpd'] : $_SESSION['volknob'];
// Restore MPD volume
sysCmd('/var/www/vol.sh ' . $volume);
workerLog('worker: MPD volume level (' . $volume . ') restored');
if ($_SESSION['alsavolume'] != 'none') {
	$result = sysCmd('/var/www/util/sysutil.sh get-alsavol ' . '"' . $_SESSION['amixname'] . '"');
	workerLog('worker: ALSA ' . trim($_SESSION['amixname']) . ' volume (' . $result[0] . ')');
} else {
	workerLog('worker: ALSA volume level (None)');
}

// Auto-play: start auto-shuffle random play or play last played item
if ($_SESSION['autoplay'] == '1') {
	workerLog('worker: Auto-play (On)');
	if ($_SESSION['ashuffle'] == '1') {
		workerLog('worker: Starting auto-shuffle');
		startAutoShuffle();
	} else {
		$status = getMpdStatus($sock);
		//workerLog(print_r($status, true));
		sendMpdCmd($sock, 'playid ' . $status['songid']);
		$resp = readMpdResp($sock);
		workerLog('worker: Auto-playing id (' . $status['songid'] . ')');
	}
} else {
	workerLog('worker: Auto-play (Off)');
	sendMpdCmd($sock, 'stop');
	$resp = readMpdResp($sock);
	// Turn off Auto-shuffle based random play if it's on
	if ($_SESSION['ashuffle'] == '1') {
		phpSession('write', 'ashuffle', '0');
		sendMpdCmd($sock, 'consume 0');
		$resp = readMpdResp($sock);
		workerLog('worker: Random Play reset to (Off)');
	}
}

// Start LocalUI
sysCmd("sed -i '/User=/c \User=" . $_SESSION['user_id'] . "' /lib/systemd/system/localui.service");
sysCmd('systemctl daemon-reload');
if ($_SESSION['localui'] == '1') {
	startLocalUI();
}
workerLog('worker: LocalUI (' . ($_SESSION['localui'] == '1' ? 'On' : 'Off') . ')');
// Toggle CoverView (System Config)
if (!isset($_SESSION['toggle_coverview'])) {
	$_SESSION['toggle_coverview'] = '-off';
} else {
	$_SESSION['toggle_coverview'] = $_SESSION['auto_coverview'];
}
// Automatic CoverView (Preferences)
workerLog('worker: Automatic CoverView (' . ($_SESSION['auto_coverview'] == '-on' ? 'On' : 'Off') . ')');
// On-screen keyboard
if (!isset($_SESSION['on_screen_kbd'])) {
	$_SESSION['on_screen_kbd'] = 'Enable';
}
workerLog('worker: On-screen keyboard (' . ($_SESSION['on_screen_kbd'] == 'Enable' ? 'Off' : 'On') . ')');

// TRX Config advanced options toggle
$_SESSION['rx_adv_toggle'] = 'Show';
$_SESSION['tx_adv_toggle'] = 'Show';

// Library scope
if (!isset($_SESSION['lib_scope'])) {
	$_SESSION['lib_scope'] = 'all';
}
workerLog('worker: Library scope (' . $_SESSION['lib_scope'] . ')');
// Library active search
if (!isset($_SESSION['lib_active_search'])) {
	$_SESSION['lib_active_search'] = 'None';
}
workerLog('worker: Library active search (' . $_SESSION['lib_active_search'] . ')');

// Reset view to Playback
workerLog('worker: View reset to (Playback)');
$view = explode(',', $_SESSION['current_view'])[0] != 'playback' ? 'playback,' . $_SESSION['current_view'] : $_SESSION['current_view'];
phpSession('write', 'current_view', $view);
sendEngCmd('refresh_screen');

// Worker sleep interval
if (!isset($_SESSION['worker_responsiveness'])) {
	$_SESSION['worker_responsiveness'] = 'Default';
}
// Mount monitor
if (!isset($_SESSION['fs_mountmon'])) {
	$_SESSION['fs_mountmon'] = 'Off';
}

//
// Globals section
//

// Clock radio
$clkradio_start_time = substr($_SESSION['clkradio_start'], 0, 8); // parse out the time (HH,MM,AP)
$clkradio_stop_time = substr($_SESSION['clkradio_stop'], 0, 8);
$clkradio_start_days = explode(',', substr($_SESSION['clkradio_start'], 9)); // parse out the days (M,T,W,T,F,S,S)
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
workerLog('worker: Maintenance interval (' . ($maint_interval / 60) . ' minutes)');

// Screen saver
$scnactive = '0';
$scnsaver_timeout = $_SESSION['scnsaver_timeout'];
workerLog('worker: Screen saver activation (' . $_SESSION['scnsaver_timeout'] . ')');

//
// End globals section
//

// Inizialize job queue
$_SESSION['w_queue'] = '';
$_SESSION['w_queueargs'] = '';
$_SESSION['w_lock'] = 0;
$_SESSION['w_active'] = 0;

// Close resources
closeMpdSock($sock);
phpSession('close');

// Check permissions on the session file
phpSessionCheck();

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

//
workerLog('worker: --');
workerLog('worker: -- Startup complete ');
workerLog('worker: --');
//

// Start mount monitor
sysCmd('killall -s 9 mountmon.php');
if ($_SESSION['fs_mountmon'] == 'On') {
	sysCmd('/var/www/daemon/mountmon.php > /dev/null 2>&1 &');
}
workerLog('worker: Mount monitor ' . ($_SESSION['fs_mountmon'] == 'On' ? '(started)' : '(off)'));

// Start watchdog monitor
sysCmd('killall -s 9 watchdog.sh');
$result = sqlQuery("UPDATE cfg_system SET value='1' WHERE param='wrkready'", $dbh);
sysCmd('/var/www/daemon/watchdog.sh ' . WATCHDOG_SLEEP . ' > /dev/null 2>&1 &');
workerLog('worker: Watchdog monitor (started)');

// Sleep intervals
workerLog('worker: Responsiveness (' . $_SESSION['worker_responsiveness'] . ')');
debugLog('worker: Sleep intervals (' .
	'worker=' . WORKER_SLEEP / 1000000 . ', ' .
	'waitworker=' . WAITWORKER_SLEEP / 1000000 . ', ' .
	'watchdog=' . WATCHDOG_SLEEP . ', ' .
	'mountmon=' . MOUNTMON_SLEEP . ', ' .
	'gpiobuttons=' . GPIOBUTTONS_SLEEP .
	')'
);

// Worker ready
workerLog('worker: Ready');

//
// BEGIN WORKER EVENT LOOP
//

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

//
// WORKER FUNCTIONS
//

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
				$GLOBALS['scnsaver_timeout'] = $_SESSION['scnsaver_timeout']; // reset timeout
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
			workerLog('worker: Maintenance: WARNING: Problem clearing system logs');
			workerLog('worker: Maintenance: ' . $result[0]);
		}

		// Compact SQLite database
		$result = sysCmd('sqlite3 /var/local/www/db/moode-sqlite3.db "vacuum"');
		if (!empty($result)) {
			workerLog('worker: Maintenance: WARNING: Problem compacting SQLite database');
			workerLog('worker: Maintenance: ' . $result[0]);
		}

		// Purge temp or unwanted resources
		sysCmd('find /var/www/ -type l -delete'); // There shouldn't be any symlinks in the web root
		sysCmd('rm ' . STATION_EXPORT_DIR . '/stations.zip > /dev/null 2>&1'); // Temp file from legacy Radio Manager export

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

		// LocalUI display (chromium browser)
		// NOTE: This is a workaround for a chromium-browser bug in < r810 that causes 100% memory utilization after ~3 hours
		// Enable it by creating the localui_maint file
		if ($_SESSION['localui'] == '1' && file_exists($_SESSION['home_dir'] . '/localui_maint')) {
			if (file_exists('home/pi/localui_refresh')) {
				debugLog('worker: Maintenance: LocalUI refresh screen');
				sendEngCmd('refresh_screen');
			} else {
				debugLog('worker: Maintenance: LocalUI restart');
				stopLocalUI();
				startLocalUI();
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
			$GLOBALS['scnsaver_timeout'] = $_SESSION['scnsaver_timeout']; // reset timeout
			sysCmd('mpc stop'); // For added robustness

			if (isMPD2CamillaDSPVolSyncEnabled()) {
				// Save knob volume and set MPD volume to 100
				sqlQuery("UPDATE cfg_system SET value='" . $_SESSION['volknob'] . "' WHERE param='volknob_mpd'", $GLOBALS['dbh']);
				sysCmd('/var/www/vol.sh 100');
			} else if ($_SESSION['alsavolume'] != 'none') {
				sysCmd('/var/www/util/sysutil.sh set-alsavol ' . '"' . $_SESSION['amixname']  . '" ' . $_SESSION['alsavolume_max']);
			}
		}
		sendEngCmd('btactive1'); // Placing here enables each conected device to be printed to the indicator overlay
	}
	else {
		// Do this section only once
		if ($_SESSION['btactive'] == '1') {
			phpSession('write', 'btactive', '0');
			sendEngCmd('btactive0');

			if (isMPD2CamillaDSPVolSyncEnabled()) {
				// Restore knob level to saved MPD level
				$result = sqlQuery("SELECT value FROM cfg_system WHERE param='volknob_mpd'", $GLOBALS['dbh']);
				sqlQuery("UPDATE cfg_system SET value='" . $result[0]['value'] . "' WHERE param='volknob'", $GLOBALS['dbh']);
				sysCmd('/var/www/vol.sh -restore');
				sysCmd('systemctl restart mpd2cdspvolume');
			} else {
				sysCmd('/var/www/vol.sh -restore');
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
			$GLOBALS['scnsaver_timeout'] = $_SESSION['scnsaver_timeout']; // reset timeout
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
			$GLOBALS['scnsaver_timeout'] = $_SESSION['scnsaver_timeout']; // reset timeout
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
			$GLOBALS['scnsaver_timeout'] = $_SESSION['scnsaver_timeout']; // reset timeout
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
				$GLOBALS['scnsaver_timeout'] = $_SESSION['scnsaver_timeout']; // reset timeout
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
			$GLOBALS['scnsaver_timeout'] = $_SESSION['scnsaver_timeout']; // Reset timeout
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
	// Output rate
	$hwParams = getAlsaHwParams($_SESSION['cardnum']);
	if ($hwParams['status'] == 'active') {
		if ($hwParams['format'] == 'DSD') {
			$hwParamsFormat = 'DSD Bitstream, ' . $hwParams['channels'];
		} else {
			$hwParamsFormat = 'PCM ' . $hwParams['format'] . ' bit ' . $hwParams['rate'] . ' kHz, ' . $hwParams['channels'];
		}
		$hwParamsCalcrate = ', ' . $hwParams['calcrate'] . ' Mbps';
	} else if ($_SESSION['multiroom_tx'] == 'On') {
		$hwParamsFormat = '';
		$hwParamsCalcrate = 'Multiroom sender';
	} else {
		$hwParamsFormat = '';
		$hwParamsCalcrate = '0 bps';
	}
	// Currentsong.txt
	$fileMeta = parseDelimFile(file_get_contents('/var/local/www/currentsong.txt'), '=');

	if ($GLOBALS['aplactive'] == '1' || $GLOBALS['spotactive'] == '1' || $GLOBALS['slactive'] == '1'
		|| $GLOBALS['rbactive'] == '1' || $GLOBALS['inpactive'] == '1' || ($_SESSION['btactive'] && $_SESSION['audioout'] == 'Local')) {
		//workerLog('worker: Renderer active');
		if ($GLOBALS['aplactive'] == '1') {
			$renderer = 'AirPlay Active';
		} else if ($GLOBALS['spotactive'] == '1') {
			$renderer = 'Spotify Active';
		} else if ($GLOBALS['slactive'] == '1') {
			$renderer = 'Squeezelite Active';
		} else if ($GLOBALS['rbactive'] == '1') {
			$renderer = 'Roonbridge Active';
		} else if ($GLOBALS['inpactive'] == '1') {
			$renderer = $_SESSION['audioin'] .' Input Active';
		} else {
			$renderer = 'Bluetooth Active';
		}
		// Write file only if something has changed
		if ($fileMeta['file'] != $renderer && $hwParamsCalcrate != '0 bps') {
			//workerLog('worker: Writing currentsong file');
			$fh = fopen('/tmp/currentsong.txt', 'w');
			$data = 'file=' . $renderer . "\n";
			$data .= 'outrate=' . $hwParamsFormat . $hwParamsCalcrate . "\n"; ;
			fwrite($fh, $data);
			fclose($fh);
			rename('/tmp/currentsong.txt', '/var/local/www/currentsong.txt');
            chmod('/var/local/www/currentsong.txt', 0666);
		}
	} else {
		//workerLog('worker: MPD active');
		$sock = openMpdSock('localhost', 6600);
		$current = getMpdStatus($sock);
		$current = enhanceMetadata($current, $sock, 'worker_php');
		closeMpdSock($sock);
		//workerLog(print_r($current, true));
		//workerLog('updExtMetaFile(): currentencoded=' . $_SESSION['currentencoded']);

		// Write file only if something has changed
		if ($current['title'] != $fileMeta['title'] || $current['album'] != $fileMeta['album'] || $_SESSION['volknob'] != $fileMeta['volume'] ||
			$_SESSION['volmute'] != $fileMeta['mute'] || $current['state'] != $fileMeta['state'] || $fileMeta['outrate'] != $hwParamsFormat . $hwParamsCalcrate) {
			//workerLog('worker: Writing currentsong file');
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
			$data .= 'outrate=' . $current['output'] . $hwParamsCalcrate . "\n"; ;
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
	$model = $array[1];
	$rev = $array[2];
	$ram = $array[3];

	if ($model == 'CM3+') {
		$hdwrRev = 'Allo USBridge SIG [CM3+ Lite 1GB v1.0]';
	} else {
		$hdwrRev = 'Pi-' . $model . ' ' . $rev . ' ' . $ram;
	}

	return $hdwrRev;
}

// Log info for the active interface (eth0 or wlan0)
function logNetworkInfo($interface) {
	workerLog('worker: IP addr (' . sysCmd("ifconfig " . $interface . " | awk 'NR==2{print $2}'")[0] . ')');
	workerLog('worker: Netmask (' . sysCmd("ifconfig " . $interface . " | awk 'NR==2{print $4}'")[0] . ')');
	workerLog('worker: Gateway (' . sysCmd("netstat -nr | awk 'NR==3 {print $2}'")[0] . ')');
	$line3 = sysCmd("cat /etc/resolv.conf | awk '/^nameserver/ {print $2; exit}'")[0]; // First nameserver entry of possibly many
	$line2 = sysCmd("cat /etc/resolv.conf | awk '/^domain/ {print $2; exit}'")[0]; // First domain entry of possibly many
	$primaryDns = !empty($line3) ? $line3 : $line2;
	$domainName = !empty($line3) ? $line2 : 'None found';
	workerLog('worker: Pri DNS (' . $primaryDns . ')');
	workerLog('worker: Domain  (' . $domainName . ')');
}

function updaterAutoCheck($validIPAddress) {
	if ($_SESSION['updater_auto_check'] == 'On') {
		workerLog('worker: Automatic check (On)');
		if ($validIPAddress === true) {
			workerLog('worker: Checking for available update...');
			$available = checkForUpd($_SESSION['res_software_upd_url'] . '/');
			$thisReleaseDate = explode(" ", getMoodeRel('verbose'))[1];

			if (false === ($availableDate = strtotime($available['Date']))) {
				$msg = 'Check for update failed';
			} else if (false === ($thisDate = strtotime($thisReleaseDate))) {
				$msg = 'Invalid release date: ' . $thisReleaseDate;
			} else if ($availableDate <= $thisDate) {
				$msg = 'Software is up to date';
			} else {
				$msg = 'Release ' . $available['Release'] . ', ' . $available['Date'] . ' is available';
			}
			workerLog('worker: ' . $msg);
		} else {
			$msg = 'No IP address or AP mode is On';
			workerLog('worker: Unable to check: ' . $msg);
		}
	} else {
		$msg = 'Automatic check (Off)';
		workerLog('worker: ' . $msg);
	}

	return $msg;
}

// Determine playback destination
class PlaybackDestinationType
{
    public const LOCAL = 1;
    public const I2S   = 2;
    public const USB   = 3;
    public const TX    = 4;
}
function playbackDestinationType() {
	$localDecvices = array('Pi HDMI 1', 'Pi HDMI 2', 'Pi Headphone jack');

	if ($_SESSION['multiroom_tx'] !== 'Off') {
		$playbackDestType = PlaybackDestinationType.TX;
	} else if ($_SESSION['i2sdevice'] != 'None' || $_SESSION['i2soverlay'] != 'None') {
		$playbackDestType = PlaybackDestinationType.I2S;
	} else if (in_array($_SESSION['adevname'], $localDecvices) ) {
		$playbackDestType = PlaybackDestinationType.LOCAL;
	} else {
		$playbackDestType = PlaybackDestinationType.USB;
	}

	return $playbackDestType;
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

//
// PROCESS SUBMITTED JOBS
//

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

			// Update config file
			updMpdConf($_SESSION['i2sdevice']);

			// Store audio formats
			$_SESSION['audio_formats'] = sysCmd('moodeutl -f')[0];

			// Set ALSA volume to 0dB (100%) depending on mixer type
			if ($_SESSION['alsavolume'] != 'none') {
				setALSAVolumeForMPD($_SESSION['mpdmixer'], $_SESSION['amixname'], $_SESSION['alsavolume_max']);
			}

			// Parse quereargs:
			// [0] = device (cardnum) change 1/0
			// [1] = mixer change 'fixed_or_null', 'hardware', 'software', 0
			$queueArgs = explode(',', $_SESSION['w_queueargs']);
			$deviceChange = $queueArgs[0];
			$mixerChange = $queueArgs[1];

			// Start Camilla volume sync if indicated
			$serviceCmd = isMPD2CamillaDSPVolSyncEnabled() ? 'start' : 'stop';
			sysCmd('systemctl ' . $serviceCmd .' mpd2cdspvolume');

			// Restart MPD
			sysCmd('systemctl restart mpd');
			$sock = openMpdSock('localhost', 6600); // Ensure MPD ready to accept connections
			closeMpdSock($sock);

			if ($mixerChange == 'fixed_or_null') {
				// Mixer changed to Fixed (0dB) or Null
				sysCmd('/var/www/vol.sh 0');
				sendEngCmd('refresh_screen'); // For Playback view when using CamillaDSP quick config
			} else if ($mixerChange == 'software' || $mixerChange == 'hardware') {
				// Mixer changed from Fixed (0dB) or Null
				sysCmd('/var/www/vol.sh restore');
				sendEngCmd('refresh_screen');
			} else { // $mixerChange == 0
				// Special mixer change action not required
				sysCmd('/var/www/vol.sh restore');
			}

			// Start play if was playing and mixer type not changed to Fixed (0dB) or Null
			if (!empty($playing) && $mixerChange != 'fixed_or_null') {
				sysCmd('mpc play');
			}

			// Restart renderers if device (cardnum) changed
			if ($deviceChange == true) {
				if ($_SESSION['airplaysvc'] == 1) {
					sysCmd('killall shairport-sync');
					startAirPlay();
				}
				if ($_SESSION['spotifysvc'] == 1) {
					sysCmd('killall librespot');
					startSpotify();
				}
			}

			// DEBUG:
			$alsaVolume = $_SESSION['alsavolume'] == 'none' ? 'none' : sysCmd('/var/www/util/sysutil.sh get-alsavol ' . '"' . $_SESSION['amixname'] . '"')[0];
			$playing = !empty(sysCmd('mpc status | grep "\[playing\]"')) ? 'playing' : 'paused';
			workerLog('worker: Job mpdcfg: devchg|mixchg (' . $deviceChange . '|' . $mixerChange . '), alsavol (' . $alsaVolume . '), playstate (' . $playing . ')');
			break;

		// snd-config jobs
		case 'i2sdevice':
			sysCmd('/var/www/vol.sh 0'); // Set knob and MPD/hardware volume to 0
			phpSession('write', 'autoplay', '0'); // Prevent play before MPD setting applied
			cfgI2sOverlay($_SESSION['w_queueargs']);
			break;
		case 'alsavolume_max':
			if ($_SESSION['alsavolume'] != 'none') {
				// NOTE: For MPD volume type null: ALSA volume is set to 0 unless CamillaDSP volume is active then its set to max
				setALSAVolumeForMPD($_SESSION['mpdmixer'], $_SESSION['amixname'], $_SESSION['w_queueargs']);
			}
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
			updMpdConf($_SESSION['i2sdevice']);
			sysCmd('systemctl restart mpd');
			break;
		case 'mpd_httpd_encoder':
			updMpdConf($_SESSION['i2sdevice']);
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
							$volume = '-restore';
							// Save knob volume for later restore
							phpSession('write', 'volknob_mpd', $_SESSION['volknob']);
						} else if ($queueArgs[1] == 'change_mixer_to_default') {
							$volSync = 'off';
							$serviceCmd = 'stop';
							$mixerType = $_SESSION['alsavolume'] != 'none' ? 'hardware' : 'software';
							$volume = '-restore';
						}
						changeMPDMixer($mixerType);
						// Start or stop MPD to CamillaDSP volume sync
						phpSession('write', 'camilladsp_volume_sync', $volSync);
						sysCmd('systemctl '. $serviceCmd .' mpd2cdspvolume');
						// Restart MPD
						sysCmd('systemctl restart mpd');
						$sock = openMpdSock('localhost', 6600); // Ensure MPD ready to accept connections
						closeMpdSock($sock);
						// Set volume level
						sysCmd('/var/www/vol.sh ' . $volume);
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

			if ($_SESSION['w_queueargs'] == 'disconnect-renderer' && $_SESSION['rsmafterapl'] == 'Yes') {
				sysCmd('mpc play');
			}
			break;
		case 'airplay_protocol':
			$previousProtocol = getAirPlayProtocolVer();
			if ($_SESSION['w_queueargs'] != $previousProtocol) { // Compare requested to previous
				workerLog('worker: Requested AirPlay ' . $_SESSION['w_queueargs']);
				workerLog('worker: Updating package list...');
				sysCmd('apt update > /dev/null 2>&1');

				// Set package version
				if ($_SESSION['w_queueargs'] == '1') {
					// Final Airplay 1 package
					$package = 'shairport-sync=3.3.8-1moode1';
					$options = '-o Dpkg::Options::="--force-confdef" -o Dpkg::Options::="--force-confold" --allow-downgrades';
				} else {
					// Latest Airplay 2 package
					$latestVersion = sysCmd("apt search shairport-sync | awk 'NR==3 {print $2}'")[0];
					$package = 'shairport-sync=' . $latestVersion;
				    $options = '-o Dpkg::Options::="--force-confdef" -o Dpkg::Options::="--force-confold"';
				}

				// Validate / install
				if (false !== strpos($package, 'moode')) {
					// Install package
					workerLog('worker: Installing ' . $package);
					sysCmd('moode-apt-mark unhold > /dev/null');
					sysCmd('apt -y ' . $options . ' install ' . $package);
					sysCmd('moode-apt-mark hold > /dev/null');
					// Check installation
					if ($_SESSION['w_queueargs'] == getAirPlayProtocolVer()) {
						$msg = 'Installation complete';
						// Restart AirPlay if indicated
						stopAirPlay();
						if ($_SESSION['airplaysvc'] == 1) {
							startAirPlay();
						}
					} else {
						$msg = 'Error: Installation did not complete successfully';
					}
				} else {
					$msg = 'Error: Unable to get latest Airplay 2 package version';
				}

				// Finish up
				workerLog('worker: ' . $msg);
				if ($msg != 'Installation complete') {
					// Revert to previous protocol version
					phpSession('write', 'airplay_protocol', $previousProtocol);
					sendEngCmd('refresh_screen');
				}
			}
			break;
		case 'spotifysvc':
			stopSpotify();
			if ($_SESSION['spotifysvc'] == 1) {
				startSpotify();
			}

			if ($_SESSION['w_queueargs'] == 'disconnect-renderer' && $_SESSION['rsmafterspot'] == 'Yes') {
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

			if ($_SESSION['w_queueargs'] == 'disconnect-renderer' && $_SESSION['rsmaftersl'] == 'Yes') {
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

			if ($_SESSION['w_queueargs'] == 'disconnect-renderer' && $_SESSION['rsmafterrb'] == 'Yes') {
				sysCmd('mpc play');
			}
			break;
		case 'rbrestart':
			sysCmd('mpc stop');
			sysCmd('systemctl stop roonbridge');
			if ($_SESSION['rbsvc'] == '1') {
				startRoonBridge();
			}
			break;

		case 'multiroom_tx':
			if ($_SESSION['multiroom_tx'] == 'On') {
				$cardnum = loadSndDummy(); // Reconfigure to dummy sound driver

				updAudioOutAndBtOutConfs($cardnum, 'hw');
				updDspAndBtInConfs($cardnum, 'hw');
				sysCmd('systemctl restart mpd');

				startMultiroomSender();
			} else {
				stopMultiroomSender();
				unloadSndDummy(); // Reconfigure back to real sound driver

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
			cfgNetIfaces();
			resetApMode();
			cfgHostApd();
			break;

		// sys-config jobs
		case 'install_update':
			$result = sysCmd('/var/www/util/system-updater.sh ' . getPkgId() . ' > /dev/null 2>&1 &');
			break;
		case 'timezone':
			sysCmd('/var/www/util/sysutil.sh set-timezone ' . $_SESSION['w_queueargs']);
			break;
		case 'hostname':
			sysCmd('/var/www/util/sysutil.sh chg-name host ' . $_SESSION['w_queueargs']);
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
			sysCmd('sed -i /program_usb_boot_mode/d ' . '/boot/config.txt'); // Remove first to prevent duplicate adds
			sysCmd('echo program_usb_boot_mode=1 >> ' . '/boot/config.txt');
			break;
		case 'p3wifi':
			ctlWifi($_SESSION['w_queueargs']);
			break;
		case 'p3bt':
			ctlBt($_SESSION['w_queueargs']);
			break;
		case 'hdmiport':
			$cmd = $_SESSION['w_queueargs'] == '1' ? 'tvservice -p' : 'tvservice -o';
			sysCmd($cmd . ' > /dev/null');
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
		// TEST:
		case 'nginx_https_only':
			if ($_SESSION['w_queueargs'] == '0') {
				sysCmd("sed -i 's/return 301/#return 301/' /etc/nginx/nginx.conf");
			} else {
				sysCmd("sed -i 's/#return 301/return 301/' /etc/nginx/nginx.conf");
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
				sysCmd('sed -i /framebuffer_/d ' . '/boot/config.txt'); // Remove first to prevent any chance of duplicate adds
				sysCmd('echo framebuffer_width=800 >> ' . '/boot/config.txt');
				sysCmd('echo framebuffer_height=444 >> ' . '/boot/config.txt');
				sysCmd('echo framebuffer_aspect=-1 >> ' . '/boot/config.txt');
			} else {
				sysCmd('sed -i /framebuffer_/d ' . '/boot/config.txt');
			}
			break;
		case 'scnrotate':
			sysCmd('sed -i /lcd_rotate/d ' . '/boot/config.txt');
			if ($_SESSION['w_queueargs'] == '180') {
				sysCmd('echo lcd_rotate=2 >> ' . '/boot/config.txt');
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
		case 'reduce_sys_logging':
			if ($_SESSION['w_queueargs'] == '1') {
				sysCmd('systemctl disable rsyslog');
			} else {
				sysCmd('systemctl enable rsyslog');
			}
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
			resetApMode();
			workerLog('worker: AP mode reset');

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
