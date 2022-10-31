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
	workerLog('worker: Already running');
	exit("Already running\n");
}

switch ($pid = pcntl_fork()) {
	case -1:
		$logMsg = 'worker: Unable to fork';
		workerLog($logMsg);
		exit($logMsg . "\n");
	case 0: // child process
		break;
	default: // parent process
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

$stdIn = fopen('/dev/null', 'r'); // set fd/0
$stdOut = fopen('/dev/null', 'w'); // set fd/1
$stdErr = fopen('php://stdout', 'w'); // a hack to duplicate fd/1 to 2

pcntl_signal(SIGTSTP, SIG_IGN);
pcntl_signal(SIGTTOU, SIG_IGN);
pcntl_signal(SIGTTIN, SIG_IGN);
pcntl_signal(SIGHUP, SIG_IGN);
workerLog('worker: Successfully daemonized');

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
sysCmd('chmod 0777 /var/local/www/currentsong.txt');
sysCmd('chmod 0777 ' . LIBCACHE_BASE . '_*');
sysCmd('chmod 0777 /var/local/www/playhistory.log');
sysCmd('chmod 0777 /var/local/www/sysinfo.txt');
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
workerLog('worker: Session vacuumed');

// Load cfg_system and cfg_radio into session
phpSession('load_system');
phpSession('load_radio');
workerLog('worker: Session loaded');

// Debug logging
if (!isset($_SESSION['debuglog'])) {
	$_SESSION['debuglog'] = '0';
}
workerLog('worker: Debug logging (' . ($_SESSION['debuglog'] == '1' ? 'ON' : 'OFF') . ')');

// Mount monitor
if (!isset($_SESSION['fs_mountmon'])) {
	$_SESSION['fs_mountmon'] = 'Off';
}

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

// Store platform data
phpSession('write', 'hdwrrev', getHdwrRev());
phpSession('write', 'mpdver', explode(" ", strtok(shell_exec('mpd -V | grep "Music Player Daemon"'),"\n"))[3]);
phpSession('write', 'kernel_architecture', strtok(shell_exec('uname -m'),"\n") == 'aarch64' ? '64-bit' : '32-bit');
$_SESSION['kernelver'] = strtok(shell_exec('uname -r'),"\n") . ' ' . strtok(shell_exec("uname -v | awk '{print $1}'"),"\n");
$_SESSION['procarch'] = strtok(shell_exec('uname -m'),"\n");
$_SESSION['raspbianver'] = sysCmd('cat /etc/debian_version')[0];
$_SESSION['moode_release'] = getMoodeRel(); // rNNN format

// Log platform data
workerLog('worker: Host      (' . $_SESSION['hostname'] . ')');
workerLog('worker: moOde     (' . getMoodeRel('verbose') . ')'); // major.minor.patch yyyy-mm-dd
workerLog('worker: RaspiOS   (' . $_SESSION['raspbianver'] . ')');
workerLog('worker: Kernel    (' . $_SESSION['kernelver'] . ')');
workerLog('worker: Platform  (' . $_SESSION['hdwrrev'] . ')');
workerLog('worker: ARM arch  (' . $_SESSION['procarch'] . ', ' . $_SESSION['kernel_architecture'] . ')');
workerLog('worker: MPD ver   (' . $_SESSION['mpdver'] . ')');
workerLog('worker: CPU gov   (' . $_SESSION['cpugov'] . ')');

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
	$led0Trigger = explode(',', $_SESSION['led_state'])[0] == '0' ? 'none' : 'mmc0';
	sysCmd('echo ' . $led0Trigger . ' | sudo tee /sys/class/leds/led0/trigger > /dev/null');
	workerLog('worker: Sys LED0  (' . ($led0Trigger == 'none' ? 'Off' : 'On') . ')');
	workerLog('worker: Sys LED1  (sysclass does not exist)');
} else if ($_SESSION['hdwrrev'] == 'Allo USBridge SIG [CM3+ Lite 1GB v1.0]' || substr($_SESSION['hdwrrev'], 3, 1) == '1') {
	$led0Trigger = explode(',', $_SESSION['led_state'])[0] == '0' ? 'none' : 'mmc0';
	sysCmd('echo ' . $led0Trigger . ' | sudo tee /sys/class/leds/led0/trigger > /dev/null');
	workerLog('worker: Sys LED0  (' . ($led0Trigger == 'none' ? 'Off' : 'On') . ')');
	workerLog('worker: Sys LED1  (sysclass does not exist)');
} else {
	$led1Brightness = explode(',', $_SESSION['led_state'])[1] == '0' ? '0' : '255';
	$led0Trigger = explode(',', $_SESSION['led_state'])[0] == '0' ? 'none' : 'mmc0';
	sysCmd('echo ' . $led1Brightness . ' | sudo tee /sys/class/leds/led1/brightness > /dev/null');
	sysCmd('echo ' . $led0Trigger . ' | sudo tee /sys/class/leds/led0/trigger > /dev/null');
	workerLog('worker: Sys LED0  (' . ($led0Trigger == 'none' ? 'Off' : 'On') . ')');
	workerLog('worker: Sys LED1  (' . ($led1Brightness == '0' ? 'Off' : 'On') . ')');
}

//
workerLog('worker: --');
workerLog('worker: -- Network');
workerLog('worker: --');
//

// Check eth0
$eth0 = sysCmd('ip addr list | grep eth0');
if (!empty($eth0)) {
	workerLog('worker: eth0 adapter exists');
	// Check for IP address if indicated
	workerLog('worker: eth0 check for address (' . ($_SESSION['eth0chk'] == '1' ? 'Yes' : 'No') . ')');
	if ($_SESSION['eth0chk'] == '1') {
		workerLog('worker: eth0 address check (' . $_SESSION['ipaddr_timeout'] . ' secs)');
		$eth0Ip = checkForIpAddr('eth0', $_SESSION['ipaddr_timeout']);
	} else {
		$eth0Ip = sysCmd("ip addr list eth0 | grep \"inet \" |cut -d' ' -f6|cut -d/ -f1");
	}
} else {
	$eth0Ip = '';
	workerLog('worker: eth0 adapter does not exist');
}

// Log network info
!empty($eth0Ip) ? logNetworkInfo('eth0') : workerLog('worker: eth0 address not assigned');

// Check wlan0
$wlan0Ip = '';
$wlan0 = sysCmd('ip addr list | grep wlan0');
$cfgNetwork = sqlQuery('SELECT * FROM cfg_network', $dbh);
$cfgSSID = sqlQuery('SELECT COUNT(*) FROM cfg_ssid', $dbh);
if (!empty($wlan0)) {
	workerLog('worker: wlan0 adapter exists');
	workerLog('worker: wlan0 country (' . $cfgNetwork[1]['wlan_country'] . ')');

	// Case: saved SSID(s)
	if ($cfgSSID[0]['COUNT(*)'] > 1 && $cfgNetwork[1]['wlanssid'] != 'None (activates AP mode)') {
		workerLog('worker: wlan0 trying saved SSID(s)');
		$wlan0Ip = checkForIpAddr('wlan0', $_SESSION['ipaddr_timeout']);
	}

	if (!empty($wlan0Ip)) {
		// Case: IP address already assigned (configured or saved SSID)
		$ssidBlank = false;
		$_SESSION['apactivated'] = false;
	} else if ($cfgNetwork[1]['wlanssid'] == 'None (activates AP mode)') {
		// Case: no configured SSID and saved SSID (if any) did not associate
		$ssidBlank = true;
		workerLog('worker: wlan0 SSID is "None (activates AP mode)"');
		if (empty($eth0Ip)) {
			// Case: no eth0 addr
			workerLog('worker: wlan0 AP mode started');
			$_SESSION['apactivated'] = true;
			activateApMode();
		} else {
			// Case: eth0 addr exists
			if ($cfgNetwork[2]['wlan_router'] == 'On') {
				workerLog('worker: wlan0 AP mode started');
				$_SESSION['apactivated'] = true;
				activateApMode();
			} else {
				workerLog('worker: wlan0 AP mode not started (eth0 active but Router mode is Off)');
				$_SESSION['apactivated'] = false;
			}
		}
	} else {
		// Case: configured SSID exists
		workerLog('worker: wlan0 trying configured SSID (' . $cfgNetwork[1]['wlanssid'] . ')');
		$ssidBlank = false;
		$_SESSION['apactivated'] = false;
	}

	// Check for IP address
	if ($_SESSION['apactivated'] == true || $ssidBlank == false) {
		$wlan0Ip = checkForIpAddr('wlan0', $_SESSION['ipaddr_timeout']);
		// Case: SSID blank, AP mode activated
		// Case: SSID exists, AP mode fall back if no ip address after trying SSID
		if ($ssidBlank == false) {
			if (empty($wlan0Ip)) {
				workerLog('worker: wlan0 no IP addr for SSID (' . $cfgNetwork[1]['wlanssid'] . ')');
				if (empty($eth0Ip)) {
					workerLog('worker: wlan0 AP mode started');
					$_SESSION['apactivated'] = true;
					activateApMode();
					$wlan0Ip = checkForIpAddr('wlan0', $_SESSION['ipaddr_timeout']);
				} else {
					// Case: eth0 addr exists
					if ($cfgNetwork[2]['wlan_router'] == 'On') {
						workerLog('worker: wlan0 AP mode started');
						$_SESSION['apactivated'] = true;
						activateApMode();
					} else {
						workerLog('worker: wlan0 AP mode not started (eth0 active but Router mode is Off)');
						$_SESSION['apactivated'] = false;
					}
				}
			} else {
				$result = sysCmd("iwconfig wlan0 | grep 'ESSID' | awk -F':' '{print $2}' | awk -F'\"' '{print $2}'");
				workerLog('worker: wlan0 connected SSID is (' . $result[0] . ')');
			}
		}
	}

	// Log network info
	if (!empty($wlan0Ip)) {
		logNetworkInfo('wlan0');
	} else {
		if ($_SESSION['apactivated'] == true) {
			workerLog('worker: wlan0 AP mode address not assigned');
			$_SESSION['apactivated'] = false;
		} else {
			workerLog('worker: wlan0 address not assigned');
		}
	}

	// Reset dhcpcd.conf in case a hard reboot or poweroff occurs
	resetApMode();

	// Disable power save for integrated adapter
	if ($piModelNum == '3' || $piModelNum == '4' || substr($_SESSION['hdwrrev'], 0, 7) == 'Pi-Zero') {
		sysCmd('/sbin/iwconfig wlan0 power off');
		workerLog('worker: wlan0 power save disabled');
	}
} else {
	workerLog('worker: wlan0 adapter does not exist' . ($_SESSION['wifibt'] == '0' ? ' (off)' : ''));
	$_SESSION['apactivated'] = false;
}

// AP Router mode
workerLog('worker: apd0 router mode (' . $cfgNetwork[2]['wlan_router'] . ')');
if ($cfgNetwork[2]['wlan_router'] == 'On') {
	if ($_SESSION['apactivated'] == true) {
		sysCmd('systemctl start nftables');
		if (!empty($eth0Ip)) {
			workerLog('worker: wlan0 Router mode started');
		} else {
			workerLog('worker: wlan0 Router mode started but no Ethernet address');
		}
	} else {
		workerLog('worker: wlan0 unable to start Router mode');
	}
} else if (!empty($wlan0Ip) && !empty($eth0Ip)) {
	workerLog('worker: wlan0 and eth0 active but Router mode is Off');
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
$validIPAddress = ($_SESSION['ipaddress'] != '0.0.0.0' && $wlan0Ip[0] != '172.24.1.1');
$_SESSION['updater_available_update'] = updaterAutoCheck($validIPAddress);

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
			if( $_SESSION['inplace_upd_applied'] != '1' ) {
				// Skip update otherwise USB mixer name is not preserved if device unplugged or turned off
				$mpdConfUpdMsg = 'MPD conf update skipped (USB device)';
			} else {
				$mpdConfUpdMsg = 'MPD conf updated (USB device + In-place update)';
				$updateMpdConf = true;
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
workerLog('worker: ALSA mixer name (' . $_SESSION['amixname'] . ')');
workerLog('worker: MPD mixer type (' . ($_SESSION['mpdmixer'] == 'none' ? 'fixed 0dB' : $_SESSION['mpdmixer']) . ')');

// Check for presence of hardware volume controller
$result = sysCmd('/var/www/util/sysutil.sh get-alsavol ' . '"' . $_SESSION['amixname'] . '"');
if (substr($result[0], 0, 6 ) == 'amixer') {
	phpSession('write', 'alsavolume', 'none'); // Hardware volume controller not detected
	workerLog('worker: Hdwr volume controller not detected');
} else {
	$result[0] = str_replace('%', '', $result[0]);
	phpSession('write', 'alsavolume', $result[0]); // volume level
	workerLog('worker: Hdwr volume controller exists');
	workerLog('worker: Max ALSA volume (' . $_SESSION['alsavolume_max'] . '%)');
}

// Report ALSA output mode
$alsaOutputMode = $_SESSION['alsa_output_mode'] == 'plughw' ? 'Default: plughw' : 'Direct: hw';
workerLog('worker: ALSA output mode (' . $alsaOutputMode . ')');

// Start ALSA loopback
if ($_SESSION['alsa_loopback'] == 'On') {
	sysCmd('modprobe snd-aloop');
} else {
	sysCmd('modprobe -r snd-aloop');
}
workerLog('worker: ALSA loopback (' . $_SESSION['alsa_loopback'] . ')');

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
	// Used in mpdcfg job and index.php
	$_SESSION['piano_dualmode'] = $dualMode[0];
	workerLog('worker: Piano 2.1 output mode (' . $outputMode . ')');

	// Workaround: bump one of the channels to initialize volume
	sysCmd('amixer -c0 sset "Digital" 0');
	sysCmd('speaker-test -c 2 -s 2 -r 48000 -F S16_LE -X -f 24000 -t sine -l 1');
	// Reset Main vol back to 100% (0dB) if indicated
	if (($_SESSION['mpdmixer'] == 'software' || $_SESSION['mpdmixer'] == 'none') && $_SESSION['piano_dualmode'] != 'None') {
		sysCmd('amixer -c0 sset "Digital" 100%');
	}
	workerLog('worker: Piano 2.1 initialized');
}

// Start Allo Boss2 OLED display
if ($_SESSION['i2sdevice'] == 'Allo Boss 2 DAC' && !file_exists('/home/pi/boss2oled_no_load')) {
	sysCmd('systemctl start boss2oled');
	workerLog('worker: Boss 2 OLED started');
}

// Reset renderer active flags
workerLog('worker: Reset renderer active flags');
$result = sqlQuery("UPDATE cfg_system SET value='0' WHERE param='btactive' OR param='aplactive'
	OR param='spotactive' OR param='slactive' OR param='rbactive' OR param='inpactive'", $dbh);

// CamillaDSP
$cdsp = new CamillaDsp($_SESSION['camilladsp'], $_SESSION['cardnum'], $_SESSION['camilladsp_quickconv']);
$cdsp->selectConfig($_SESSION['camilladsp']);
if ($_SESSION['cdsp_fix_playback'] == 'Yes' ) {
	$cdsp->setPlaybackDevice($_SESSION['cardnum'], $_SESSION['alsa_output_mode']);
}
unset($cdsp);
workerLog('worker: CamillaDSP (' . $_SESSION['camilladsp'] . ')');

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

//
workerLog('worker: --');
workerLog('worker: -- Music sources');
workerLog('worker: --');
//

// USB sources
workerLog('worker: USB sources');
$usbDrives = sysCmd('ls /media');
if ($usbDrives[0] == '') {
	workerLog('worker: No drives found');
} else {
	foreach ($usbDrives as $usbDrive) {
		workerLog('worker: ' . $usbDrive);
	}
}

// NAS sources
workerLog('worker: NAS sources');
$mounts = sqlRead('cfg_source', $dbh);
foreach ($mounts as $mp) {
	workerLog('worker: ' . $mp['name']);
}
$result = sourceMount('mountall');
workerLog('worker: ' . $result);

//
workerLog('worker: --');
workerLog('worker: -- Feature availability');
workerLog('worker: --');
//

// Configure audio source
if ($_SESSION['feat_bitmask'] & FEAT_INPSOURCE) {
	workerLog('worker: Source select (available)');
	$audioSource = $_SESSION['audioin'] == 'Local' ? 'MPD' : ($_SESSION['audioin'] == 'Analog' ? 'Analog input' : 'S/PDIF input');
	workerLog('worker: Source select (source: ' . $audioSource . ')');
	$audio_output = ($_SESSION['i2sdevice'] == 'None' && $_SESSION['i2soverlay'] == 'None') ? getAlsaDeviceNames()[$_SESSION['cardnum']] :
		($_SESSION['i2sdevice'] != 'None' ? $_SESSION['i2sdevice'] : $_SESSION['i2soverlay']);
	workerLog('worker: Source select (output: ' . $audio_output . ')');

	if ($_SESSION['i2sdevice'] == 'HiFiBerry DAC+ ADC' || strpos($_SESSION['i2sdevice'], 'Audiophonics ES9028/9038 DAC') !== -1) {
		setAudioIn($_SESSION['audioin']);
	} else {
		// Reset saved MPD volume
		phpSession('write', 'volknob_mpd', '0');
	}
} else {
	workerLog('worker: Source select (n/a)');
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
	$_SESSION['airplay_protocol'] = getAirplayProtocolVer();

	if (isset($_SESSION['airplaysvc']) && $_SESSION['airplaysvc'] == 1) {
		$started = ': started';
		startAirplay();
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
if (($_SESSION['feat_bitmask'] & FEAT_RECORDER) && $_SESSION['recorder_status'] != 'Not installed') {
	if ($_SESSION['recorder_status'] == 'On') {
		$started = ': started';
		sysCmd('mpc enable "' . STREAM_RECORDER . '"');
	} else {
		$started = '';
	}
	workerLog('worker: Stream recorder (available' . $started . ')');
} else {
	workerLog('worker: Stream recorder (n/a)');
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
	workerLog('worker: LCD updater engine started');
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
if ($_SESSION['alsavolume'] != 'none') {
	if ($_SESSION['mpdmixer'] == 'software' || $_SESSION['mpdmixer'] == 'none') {
		$result = sysCmd('/var/www/util/sysutil.sh set-alsavol ' . '"' . $_SESSION['amixname']  . '" ' . $_SESSION['alsavolume_max']);
	}
}
$volume = $_SESSION['volknob_mpd'] != '0' ? $_SESSION['volknob_mpd'] : $_SESSION['volknob'];
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
if ($_SESSION['localui'] == '1') {
	startLocalUI();
	workerLog('worker: LocalUI started');
}
workerLog('worker: CoverView toggle (' . $_SESSION['toggle_coverview'] . ')');
// On-screen keyboard
if (!isset($_SESSION['on_screen_kbd'])) {
	$_SESSION['on_screen_kbd'] = 'Enable';
}
workerLog('worker: On-screen keyboard (' . ($_SESSION['on_screen_kbd'] == 'Enable' ? 'Off' : 'On') . ')');

// TRX Config advanced options toggle
$_SESSION['tx_adv_toggle'] = 'Advanced (&plus;)';
$_SESSION['rx_adv_toggle'] = 'Advanced (&plus;)';

// Library scope
if (!isset($_SESSION['lib_scope'])) {
	$_SESSION['lib_scope'] = 'all';
}
workerLog('worker: Library scope (' . $_SESSION['lib_scope'] . ')');

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
	$restoreLog = '/home/pi/backup_restore.log';
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

	autoConfig('/boot/moodecfg.ini');

	sysCmd('sync');
	autoCfgLog('autocfg: System restarted');
	sysCmd('reboot');
} else if ($restoreBackup) {
	sysCmd('reboot');
}

// Start mount monitor
sysCmd('killall -s 9 mountmon.php');
if ($_SESSION['fs_mountmon'] == 'On') {
	sysCmd('/var/www/daemon/mountmon.php > /dev/null 2>&1 &');
}
workerLog('worker: Mount monitor ' . ($_SESSION['fs_mountmon'] == 'On' ? 'started' : '(Off)'));

// Start watchdog monitor
sysCmd('killall -s 9 watchdog.sh');
$result = sqlQuery("UPDATE cfg_system SET value='1' WHERE param='wrkready'", $dbh);
sysCmd('/var/www/daemon/watchdog.sh > /dev/null 2>&1 &');
workerLog('worker: Watchdog started');
workerLog('worker: Ready');

//
// BEGIN WORKER JOB LOOP
//

while (true) {
	sleep(3);

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
			$GLOBALS['scnsaver_timeout'] = $GLOBALS['scnsaver_timeout'] - 3;
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
	$GLOBALS['maint_interval'] = $GLOBALS['maint_interval'] - 3;

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
		// Enable it by creating the /home/pi/localui_maint file
		if ($_SESSION['localui'] == '1' && file_exists('/home/pi/localui_maint')) {
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
		return; // Bail if input source is active
	}

	$result = sysCmd('pgrep -l bluealsa-aplay');
	if (strpos($result[0], 'bluealsa-aplay') !== false) {
		// Do this section only once
		if ($_SESSION['btactive'] == '0') {
			phpSession('write', 'btactive', '1');
			$GLOBALS['scnsaver_timeout'] = $_SESSION['scnsaver_timeout']; // reset timeout
			sysCmd('mpc stop'); // For added robustness
			if ($_SESSION['alsavolume'] != 'none') {
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
			sysCmd('/var/www/vol.sh -restore');
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
	sysCmd('amixer -c 0 sset Digital ' . $masterVol);
}

function updExtMetaFile() {
	// Output rate
	$hwParams = getAlsaHwParams($_SESSION['cardnum']);
	if ($hwParams['status'] == 'active') {
		$hwParamsFormat = $hwParams['format'] . ' bit, ' . $hwParams['rate'] . ' kHz, ' . $hwParams['channels'];
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
	//workerLog($fileMeta['file'] . ' | ' . $hwParamsCalcrate);

	if ($GLOBALS['aplactive'] == '1' || $GLOBALS['spotactive'] == '1' || $GLOBALS['slactive'] == '1'
		|| $GLOBALS['rbactive'] == '1' || $GLOBALS['inpactive'] == '1' || ($_SESSION['btactive'] && $_SESSION['audioout'] == 'Local')) {
		//workerLog('worker: Renderer active');
		if ($GLOBALS['aplactive'] == '1') {
			$renderer = 'Airplay Active';
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
            chmod('/var/local/www/currentsong.txt', 0777);
		}
	} else {
		//workerLog('worker: MPD active');
		$sock = openMpdSock('localhost', 6600);
		$current = getMpdStatus($sock);
		$current = enhanceMetadata($current, $sock, 'worker_php');
		closeMpdSock($sock);

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
			$data .= 'encoded=' . getEncodedAt($current, 'default') . "\n";
			$data .= 'bitrate=' . $current['bitrate'] . "\n";
			$data .= 'outrate=' . $hwParamsFormat . $hwParamsCalcrate . "\n"; ;
			$data .= 'volume=' . $_SESSION['volknob'] . "\n";
			$data .= 'mute=' . $_SESSION['volmute'] . "\n";
			$data .= 'state=' . $current['state'] . "\n";
			fwrite($fh, $data);
			fclose($fh);
			rename('/tmp/currentsong.txt', '/var/local/www/currentsong.txt');
            chmod('/var/local/www/currentsong.txt', 0777);
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
	$searchurl = '<a href="' . $searcheng . $searchstr . '" class="playhistory-link" target="_blank"><i class="fas fa-external-link-square"></i></a>';

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

			if ($available['Date'] == $thisReleaseDate) {
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
	sysCmd('/var/www/daemon/gpio_buttons.py > /dev/null &');
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

		// lib-config jobs
		case 'sourcecfg':
			clearLibCacheAll();
			sourceCfg($_SESSION['w_queueargs']);
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

			// Reset hardware volume to 0dB (100) if indicated
			if (($_SESSION['mpdmixer'] == 'software' || $_SESSION['mpdmixer'] == 'none') && $_SESSION['alsavolume'] != 'none') {
				sysCmd('/var/www/util/sysutil.sh set-alsavol ' . '"' . $_SESSION['amixname']  . '" ' . $_SESSION['alsavolume_max']);
			}

			// Parse quereargs: [0] = device number changed 1/0, [1] = mixer change 'fixed', 'hardware', 'software', 0
			$queue_args = explode(',', $_SESSION['w_queueargs']);
			$device_chg = $queue_args[0];
			$mixer_chg = $queue_args[1];

			// Restart MPD
			sysCmd('systemctl restart mpd');
			$sock = openMpdSock('localhost', 6600); // Ensure MPD ready to accept connections
			closeMpdSock($sock);

			if ($mixer_chg == 'fixed') {
				// Mixer changed to Fixed (0dB)
				sysCmd('/var/www/vol.sh 0');
				sendEngCmd('refresh_screen');
			} else if ($mixer_chg == 'software' || $mixer_chg == 'hardware' || $mixer_chg == 'null') {
				// Mixer changed from Fixed (0dB)
				sysCmd('/var/www/vol.sh restore');
				sendEngCmd('refresh_screen');
			} else {
				// $mixer_chg == 0 (No change or change between hardware and software)
				sysCmd('/var/www/vol.sh restore');
			}

			// Was playing and mixer type not changed to Fixed (0dB) start play
			if (!empty($playing) && $mixer_chg != 'fixed') {
				sysCmd('mpc play');
			}

			// Restart renderers if device (cardnum) changed
			if ($device_chg == true) {
				if ($_SESSION['airplaysvc'] == 1) {
					sysCmd('killall shairport-sync');
					startAirplay();
				}
				if ($_SESSION['spotifysvc'] == 1) {
					sysCmd('killall librespot');
					startSpotify();
				}
			}

			// DEBUG:
			$alsa_vol = $_SESSION['alsavolume'] == 'none' ? 'none' : sysCmd('/var/www/util/sysutil.sh get-alsavol ' . '"' . $_SESSION['amixname'] . '"')[0];
			$playing = !empty(sysCmd('mpc status | grep "\[playing\]"')) ? 'playing' : 'paused';
			workerLog('worker: Job mpdcfg: devchg|mixchg (' . $device_chg . '|' . $mixer_chg . '), alsavol (' . $alsa_vol . '), playstate (' . $playing . ')');
			break;

		// snd-config jobs
		case 'i2sdevice':
			sysCmd('/var/www/vol.sh 0'); // Set knob and MPD/hardware volume to 0
			phpSession('write', 'autoplay', '0'); // Prevent play before MPD setting applied
			cfgI2sOverlay($_SESSION['w_queueargs']);
			break;
		case 'alsavolume_max':
			if (($_SESSION['mpdmixer'] == 'software' || $_SESSION['mpdmixer'] == 'none') && $_SESSION['alsavolume'] != 'none') {
				sysCmd('/var/www/util/sysutil.sh set-alsavol ' . '"' . $_SESSION['amixname']  . '" ' . $_SESSION['w_queueargs']);
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
				stopAirplay();
				startAirplay();
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
		// ALSA DSP's
		case 'alsaequal':
		case 'camilladsp':
		case 'crossfeed':
		case 'eqfa12p':
		case 'invpolarity':
			$playing = sysCmd('mpc status | grep "\[playing\]"');
			sysCmd('mpc stop');

			if ($_SESSION['w_queue'] == 'alsaequal') {
				$queueargs = explode(',', $_SESSION['w_queueargs']); // Split out old,new curve names
				if ($_SESSION['alsaequal'] != 'Off') {
					$result = sqlQuery("SELECT curve_values FROM cfg_eqalsa WHERE curve_name='" . $queueargs[1] . "'", $GLOBALS['dbh']);
					$curve = explode(',', $result[0]['curve_values']);
					foreach ($curve as $key => $value) {
						sysCmd('amixer -D alsaequal cset numid=' . ($key + 1) . ' ' . $value);
					}
				}
				$output = $_SESSION['alsaequal'] != 'Off' ? "\"alsaequal\"" : "\"" . $_SESSION['alsa_output_mode'] . ':' . $_SESSION['cardnum'] . ",0\"";
				sysCmd("sed -i '/slave.pcm/c\slave.pcm " . $output . "' " . ALSA_PLUGIN_PATH . '/_audioout.conf');
				sysCmd("sed -i '/a { channels 2 pcm/c\a { channels 2 pcm " . $output . " }' " . ALSA_PLUGIN_PATH . '/_sndaloop.conf');
			} else if ($_SESSION['w_queue'] == 'camilladsp') {
				$output = $_SESSION['w_queueargs'] != 'off' ? "\"camilladsp\"" : "\"" . $_SESSION['alsa_output_mode'] . ':' . $_SESSION['cardnum'] . ",0\"";
				sysCmd("sed -i '/slave.pcm/c\slave.pcm " . $output . "' " . ALSA_PLUGIN_PATH . '/_audioout.conf');
				sysCmd("sed -i '/a { channels 2 pcm/c\a { channels 2 pcm " . $output . " }' " . ALSA_PLUGIN_PATH . '/_sndaloop.conf');
			} else if ($_SESSION['w_queue'] == 'crossfeed') {
				$output = $_SESSION['w_queueargs'] != 'Off' ? "\"crossfeed\"" : "\"" . $_SESSION['alsa_output_mode'] . ':' . $_SESSION['cardnum'] . ",0\"";
				sysCmd("sed -i '/slave.pcm/c\slave.pcm " . $output . "' " . ALSA_PLUGIN_PATH . '/_audioout.conf');
				sysCmd("sed -i '/a { channels 2 pcm/c\a { channels 2 pcm " . $output . " }' " . ALSA_PLUGIN_PATH . '/_sndaloop.conf');
				if ($_SESSION['w_queueargs'] != 'Off') {
					sysCmd('sed -i "/controls/c\controls [ ' . $_SESSION['w_queueargs'] . ' ]" ' . ALSA_PLUGIN_PATH . '/crossfeed.conf');
				}
			} else if ($_SESSION['w_queue'] == 'eqfa12p') {
				$queueargs = explode(',', $_SESSION['w_queueargs']); // Split out old,new curve names
				if ($_SESSION['eqfa12p'] != 'Off') {
					$curr = intval($queueargs[1]);
					$eqfa12p = Eqp12(sqlConnect());
					$config = $eqfa12p->getpreset($curr);
					$eqfa12p->applyConfig($config);
					unset($eqfa12p);
				}
				$output = $_SESSION['eqfa12p'] != 'Off' ? "\"eqfa12p\"" : "\"" . $_SESSION['alsa_output_mode'] . ':' . $_SESSION['cardnum'] . ",0\"";
				sysCmd("sed -i '/slave.pcm/c\slave.pcm " . $output . "' " . ALSA_PLUGIN_PATH . '/_audioout.conf');
				sysCmd("sed -i '/a { channels 2 pcm/c\a { channels 2 pcm " . $output . " }' " . ALSA_PLUGIN_PATH . '/_sndaloop.conf');
			} else if ($_SESSION['w_queue'] == 'invpolarity') {
				$output = $_SESSION['w_queueargs'] == '1' ? "\"invpolarity\"" : "\"" . $_SESSION['alsa_output_mode'] . ':' . $_SESSION['cardnum'] . ",0\"";
				sysCmd("sed -i '/slave.pcm/c\slave.pcm " . $output . "' " . ALSA_PLUGIN_PATH . '/_audioout.conf');
				sysCmd("sed -i '/a { channels 2 pcm/c\a { channels 2 pcm " . $output . " }' " . ALSA_PLUGIN_PATH . '/_sndaloop.conf');
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
				stopAirplay();
				startAirplay();
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
		case 'btmulti':
			if ($_SESSION['btmulti'] == 1) {
				sysCmd("sed -i '/AUDIODEV/c\AUDIODEV=btaplay_dmix' /etc/bluealsaaplay.conf");
			} else {
				sysCmd("sed -i '/AUDIODEV/c\AUDIODEV=" . $_SESSION['alsa_output_mode'] . ":" . $_SESSION['cardnum'] . ",0' /etc/bluealsaaplay.conf");
			}
			break;
		case 'airplaysvc':
			stopAirplay();
			if ($_SESSION['airplaysvc'] == 1) {
				startAirplay();
			}

			if ($_SESSION['w_queueargs'] == 'disconnect-renderer' && $_SESSION['rsmafterapl'] == 'Yes') {
				sysCmd('mpc play');
			}
			break;
		case 'airplay_protocol':
			if ($_SESSION['w_queueargs'] != getAirplayProtocolVer()) { // Compare submitted to current
				workerLog('worker: Updating package list...');
				sysCmd('apt update > /dev/null 2>&1');
				if ($_SESSION['w_queueargs'] == '1') {
					$package = 'shairport-sync=3.3.8-1moode1';
					$options = '-o Dpkg::Options::="--force-confdef" -o Dpkg::Options::="--force-confold" --allow-downgrades';
				} else {
					$package = 'shairport-sync=4.1.0~git20221009.e7c6c4b-1moode1';
				    $options = '-o Dpkg::Options::="--force-confdef" -o Dpkg::Options::="--force-confold"';
				}
				// Install package
				workerLog('worker: Selected AirPlay ' . $_SESSION['w_queueargs'] . ' protocol');
				workerLog('worker: Installing ' . $package);
				sysCmd('moode-apt-mark unhold > /dev/null');
				sysCmd('apt -y ' . $options . ' install ' . $package);
				sysCmd('moode-apt-mark hold > /dev/null');
				workerLog('worker: Installation complete');
				// Restart AirPlay if indicated
				stopAirplay();
				if ($_SESSION['airplaysvc'] == 1) {
					startAirplay();
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
				stopAirplay();
				startAirplay();
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
				//stopAirplay();
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
			$result = sysCmd('/var/www/util/system-updater.sh ' . getPkgId());
			$last_message = explode(', ', end($result));
			$_SESSION['notify']['title'] = $last_message[0];
			$_SESSION['notify']['msg'] = ucfirst($last_message[1]);
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
				$led0Trigger = $_SESSION['w_queueargs'] == '0' ? 'none' : 'mmc0';
				sysCmd('echo ' . $led0Trigger . ' | sudo tee /sys/class/leds/led0/trigger > /dev/null');
			} else {
				$led0Trigger = $_SESSION['w_queueargs'] == '0' ? 'none' : 'mmc0';
				sysCmd('echo ' . $led0Trigger . ' | sudo tee /sys/class/leds/led0/trigger > /dev/null');
			}
			break;
		case 'pwrled': // LED1
			$led1Brightness = $_SESSION['w_queueargs'] == '0' ? '0' : '255';
			sysCmd('echo ' . $led1Brightness . ' | sudo tee /sys/class/leds/led1/brightness > /dev/null');
			break;
		case 'usbboot':
			sysCmd('sed -i /program_usb_boot_mode/d ' . '/boot/config.txt'); // Remove first to prevent duplicate adds
			sysCmd('echo program_usb_boot_mode=1 >> ' . '/boot/config.txt');
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
			sysCmd('sed -i "/xset s/c\xset s ' . $_SESSION['w_queueargs'] . '" /home/pi/.xinitrc');
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
		case 'fs_mountmon':
			sysCmd('killall -s 9 mountmon.php');
			if ($_SESSION['w_queueargs'] == 'On') {
				sysCmd('/var/www/daemon/mountmon.php > /dev/null 2>&1 &');
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
		case 'nettime': // not working...
			sysCmd('systemctl stop ntp');
			sysCmd('ntpd -qgx > /dev/null 2>&1 &');
			sysCmd('systemctl start ntp');
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
			$queueargs = explode(',', $_SESSION['w_queueargs'], 2);
			$img_name = $queueargs[0];
			$img_data = base64_decode($queueargs[1], true);

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
