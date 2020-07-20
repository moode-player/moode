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
 * 2020-MM-DD TC moOde 6.7.1
 *
 */

require_once dirname(__FILE__) . '/../inc/playerlib.php';

//
// STARTUP SEQUENCE
//

sysCmd('truncate ' . MOODE_LOG . ' --size 0');
$dbh = cfgdb_connect();

//
workerLog('worker: -- Start');
$result = sdbquery("UPDATE cfg_system SET value='0' WHERE param='wrkready'", $dbh);
//

// Daemonize ourselves
$lock = fopen('/run/worker.pid', 'c+');
if (!flock($lock, LOCK_EX | LOCK_NB)) {
	workerLog('worker: Already running');
	exit("Already running\n");
}

switch ($pid = pcntl_fork()) {
	case -1:
		$logmsg = 'worker: Unable to fork';
		workerLog($logmsg);
		exit($logmsg . "\n");
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
	$logmsg = 'worker: Could not setsid';
	workerLog($logmsg);
	exit($logmsg . "\n");
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

// Ensure critical files are factory default
$result = integrityCheck();
if ($result === false) {
	workerLog('worker: Integrity check (failed:' . $_SESSION['ic_return_code'] . ')');
	workerLog('worker: Exited');
	exit;
}
else {
	workerLog('worker: Integrity check ('. $result .')');
}

// Load cfg_system into session
playerSession('open', '', '');
loadRadio();
workerLog('worker: Session loaded');
workerLog('worker: Debug logging (' . ($_SESSION['debuglog'] == '1' ? 'on' : 'off') . ')');

// Verify device configuration
//$card0 = trim(file_get_contents('/proc/asound/card0/id'));
//$card1 = trim(file_get_contents('/proc/asound/card1/id'));
$mpd_device = sdbquery("SELECT value FROM cfg_mpd WHERE param='device'", $dbh);
for ($i = 0; $i < 4; $i++) {
	$card_id = trim(file_get_contents('/proc/asound/card' . $i . '/id'));
	$cards[$i] = empty($card_id) ? 'empty' : $card_id;
}
workerLog('worker: Device raw: (0:' . $cards[0] . '|1:' . $cards[1]. '|2:' . $cards[2]. '|3:' . $cards[3]);
workerLog('worker: Device i2s: (' . $_SESSION['i2sdevice'] . ')');
workerLog('worker: Device mpd: (' . $mpd_device[0]['value'] . ':' . $_SESSION['adevname'] . ')');
workerLog('worker: Device ses: (' . $_SESSION['cardnum'] . '|' . $_SESSION['adevname'] . '|' . $_SESSION['amixname'] . '|' . $_SESSION['alsavolume'] . '%)');
if ($_SESSION['i2sdevice'] != 'none' && $mpd_device[0]['value'] != '0') {
	workerLog('worker: ERROR: Device raw/mpd card mismatch');
}

// Zero out ALSA volume
if ($_SESSION['alsavolume'] != 'none') {
	$amixname = getMixerName($_SESSION['i2sdevice']);
	sysCmd('/var/www/command/util.sh set-alsavol ' . '"' . $amixname . '"' . ' 0');
	$result = sysCmd('/var/www/command/util.sh get-alsavol ' . '"' . $amixname . '"');
	workerLog('worker: ALSA ' . $amixname . ' volume set to (' . $result[0] . ')');
}
else {
	workerLog('worker: ALSA ' . $amixname . ' volume (None)');
}

//
workerLog('worker: -- System');
//

// Store platform data
playerSession('write', 'hdwrrev', getHdwrRev());
playerSession('write', 'kernelver', strtok(shell_exec('uname -r'),"\n") . ' ' . strtok(shell_exec("uname -v | awk '{print $1}'"),"\n"));
playerSession('write', 'procarch', strtok(shell_exec('uname -m'),"\n"));
$mpdver = explode(" ", strtok(shell_exec('mpd -V | grep "Music Player Daemon"'),"\n"));
playerSession('write', 'mpdver', $mpdver[3]);
$result = sysCmd('cat /etc/debian_version');
$_SESSION['raspbianver'] = $result[0];
$_SESSION['moode_release'] = getMoodeRel(); // rNNN format

// Log platform data
workerLog('worker: Host     (' . $_SESSION['hostname'] . ')');
workerLog('worker: moOde    (' . getMoodeRel('verbose') . ')'); // major.minor.patch yyyy-mm-dd
workerLog('worker: RaspiOS  (' . $_SESSION['raspbianver'] . ')');
workerLog('worker: Kernel   (' . $_SESSION['kernelver'] . ')');
workerLog('worker: Platform (' . $_SESSION['hdwrrev'] . ')');
workerLog('worker: ARM arch (' . $_SESSION['procarch'] . ', ' . $_SESSION['kernel_architecture'] .' kernel)');
workerLog('worker: MPD ver  (' . $_SESSION['mpdver'] . ')');
workerLog('worker: CPU gov  (' . $_SESSION['cpugov'] . ')');

// Boot device config
$model = substr($_SESSION['hdwrrev'], 3, 1);
// 3B/B+/A+, NOTE: 4B USB boot not avail as of 2019-07-13
if ($model == '3' /*|| $model == '4'*/) {
	$result = sysCmd('vcgencmd otp_dump | grep 17:');
	if ($result[0] == '17:3020000a') {
		$msg = 'USB boot enabled';
		sysCmd('sed -i /program_usb_boot_mode/d ' . '/boot/config.txt');
	}
	else {
		$msg = 'USB boot not enabled yet';
	}
	workerLog('worker: ' . $msg);
}
else {
	workerLog('worker: USB boot not available');
}
// File system expansion status
$result = sysCmd("df | grep root | awk '{print $2}'");
$msg = $result[0] > 3500000 ? 'File system expanded' : 'File system not expanded yet';
workerLog('worker: ' . $msg);
// Turn on/off hdmi port
$cmd = $_SESSION['hdmiport'] == '1' ? 'tvservice -p' : 'tvservice -o';
sysCmd($cmd . ' > /dev/null');
workerLog('worker: HDMI port ' . ($_SESSION['hdmiport'] == '1' ? 'on' : 'off'));

// Ensure certain files exist
if (!file_exists('/var/local/www/currentsong.txt')) {sysCmd('touch /var/local/www/currentsong.txt');}
if (!file_exists(LIBCACHE_JSON)) {sysCmd('touch ' . LIBCACHE_JSON);}
if (!file_exists('/var/local/www/sysinfo.txt')) {sysCmd('touch /var/local/www/sysinfo.txt');}
if (!file_exists(MOODE_LOG)) {sysCmd('touch ' . MOODE_LOG);}
if (!file_exists(THMCACHE_DIR)) {sysCmd('mkdir ' . THMCACHE_DIR);}
if (!file_exists('/var/local/www/playhistory.log')) {
	sysCmd('touch /var/local/www/playhistory.log');
	sysCmd('/var/www/command/util.sh clear-playhistory');
}
sysCmd('chmod 0777 ' . MPD_MUSICROOT . 'RADIO/*.*');
sysCmd('chmod 0777 /var/local/www/currentsong.txt');
sysCmd('chmod 0777 ' . LIBCACHE_JSON);
sysCmd('chmod 0777 /var/local/www/playhistory.log');
sysCmd('chmod 0777 /var/local/www/sysinfo.txt');
sysCmd('chmod 0666 ' . MOODE_LOG);
// Delete any tmp files left over from New/Edit radio station
sysCmd('rm /var/local/www/imagesw/radio-logos/' . TMP_STATION_PREFIX . '* > /dev/null 2>&1');
sysCmd('rm /var/local/www/imagesw/radio-logos/thumbs/' . TMP_STATION_PREFIX . '* > /dev/null 2>&1');
workerLog('worker: File check (OK)');

//
workerLog('worker: -- Network');
//

// Check ETH0
$eth0 = sysCmd('ip addr list | grep eth0');
if (!empty($eth0)) {
	workerLog('worker: eth0 exists');
	// Wait for address (default), setting is on system config
	if ($_SESSION['eth0chk'] == '1') {
		$eth0ip = waitForIpAddr('eth0', 5);
	}
	else {
		$eth0ip = sysCmd("ip addr list eth0 | grep \"inet \" |cut -d' ' -f6|cut -d/ -f1");
	}
}
else {
	$eth0ip = '';
	workerLog('worker: eth0 does not exist');
}
!empty($eth0ip[0]) ? log_network_info('eth0') : workerLog('worker: eth0 address not assigned');

// Check WLAN0
$wlan0ip = '';
$wlan0 = sysCmd('ip addr list | grep wlan0');
if (!empty($wlan0[0])) {
	workerLog('worker: wlan0 exists');

	$result = sdbquery('SELECT * FROM cfg_network', $dbh);
	workerLog('worker: wifi country (' . $result[1]['wlan_country'] . ')');

	 // Case: no ssid
	if (empty($result[1]['wlanssid']) || $result[1]['wlanssid'] == 'None (activates AP mode)') {
		$ssidblank = true;
		workerLog('worker: wlan0 SSID is blank');
		// CASE: no eth0 addr
		if (empty($eth0ip[0])) {
			workerLog('worker: wlan0 AP mode started');
			$_SESSION['apactivated'] = true;
			activateApMode();
		}
		// Case: eth0 addr exists
		else {
			workerLog('worker: eth0 addr exists, AP mode not started');
			$_SESSION['apactivated'] = false;
		}
	}
	// Case: ssid exists
	else {
		workerLog('worker: wlan0 trying SSID (' . $result[1]['wlanssid'] . ')');
		$ssidblank = false;
		$_SESSION['apactivated'] = false;
	}

	// Wait for ip address
	if ($_SESSION['apactivated'] == true || $ssidblank == false) {
		$wlan0ip = waitForIpAddr('wlan0', 5);
		// Case: ssid blank, ap mode activated
		// Case: ssid exists, ap mode fall back if no ip address after trying ssid
		if ($ssidblank == false) {
			if (empty($wlan0ip[0])) {
				workerLog('worker: wlan0 no IP addr for SSID (' . $result[1]['wlanssid'] . ')');
				if (empty($eth0ip[0])) {
					workerLog('worker: wlan0 AP mode started');
					$_SESSION['apactivated'] = true;
					activateApMode();
					$wlan0ip = waitForIpAddr('wlan0', 3);
				}
				else {
					workerLog('worker: eth0 address exists so AP mode not started');
					$_SESSION['apactivated'] = false;
				}
			}
		}
	}
	!empty($wlan0ip[0]) ? log_network_info('wlan0') : ($_SESSION['apactivated'] == true ? workerLog('worker: wlan0 unable to start AP mode') : workerLog('worker: wlan0 address not assigned'));

	// Reset dhcpcd.conf in case a hard reboot or poweroff occurs
	resetApMode();
}
else {
	workerLog('worker: wlan0 does not exist' . ($_SESSION['wifibt'] == '0' ? ' (off)' : ''));
	$_SESSION['apactivated'] = false;
}
// Store ipaddress, prefer wlan0 address
if (!empty($wlan0ip[0])) {
	$_SESSION['ipaddress'] = $wlan0ip[0];
}
elseif (!empty($eth0ip[0])) {
	$_SESSION['ipaddress'] = $eth0ip[0];
}
else {
	$_SESSION['ipaddress'] = '0.0.0.0';
	workerLog('worker: no active network interface');
}

//
workerLog('worker: -- Audio');
//

// Update MPD config
updMpdConf($_SESSION['i2sdevice']);
workerLog('worker: MPD conf updated');

// Ensure audio output is unmuted
if ($_SESSION['i2sdevice'] == 'IQaudIO Pi-AMP+') {
	sysCmd('/var/www/command/util.sh unmute-pi-ampplus');
	workerLog('worker: IQaudIO Pi-AMP+ unmuted');
}
else if ($_SESSION['i2sdevice'] == 'IQaudIO Pi-DigiAMP+') {
	sysCmd('/var/www/command/util.sh unmute-pi-digiampplus');
	workerLog('worker: IQaudIO Pi-DigiAMP+ unmuted');
}
else {
	sysCmd('/var/www/command/util.sh unmute-default');
	workerLog('worker: ALSA outputs unmuted');
}

// Log audio device info
workerLog('worker: ALSA card number (' . $_SESSION['cardnum'] . ')');
if ($_SESSION['i2sdevice'] == 'none') {
	workerLog('worker: Audio output (' . getDeviceNames()[$_SESSION['cardnum']] . ')');
}
else {
	workerLog('worker: Audio output (' . $_SESSION['i2sdevice'] . ')');
}

$_SESSION['audio_formats'] = sysCmd('moodeutl -f')[0];
workerLog('worker: Audio formats (' . $_SESSION['audio_formats'] . ')');

// Might need this at some point
$device_name = getDeviceNames()[$_SESSION['cardnum']];
if ($_SESSION['i2sdevice'] == 'none' && $device_name != 'Headphone jack' && $device_name != 'HDMI-1' && $device_name != 'HDMI-2') {
	$usb_audio = true;
}
else {
	$usb_audio = false;
}

// Store alsa mixer name for use by util.sh get/set-alsavol and vol.sh
playerSession('write', 'amixname', getMixerName($_SESSION['i2sdevice']));
workerLog('worker: ALSA mixer name (' . $_SESSION['amixname'] . ')');
workerLog('worker: MPD volume control (' . $_SESSION['mpdmixer'] . ')');

// Check for presence of hardware volume controller
$result = sysCmd('/var/www/command/util.sh get-alsavol ' . '"' . $_SESSION['amixname'] . '"');
if (substr($result[0], 0, 6 ) == 'amixer') {
	playerSession('write', 'alsavolume', 'none'); // hardware volume controller not detected
	workerLog('worker: Hdwr volume controller not detected');
}
else {
	$result[0] = str_replace('%', '', $result[0]);
	playerSession('write', 'alsavolume', $result[0]); // volume level
	workerLog('worker: Hdwr volume controller exists');
	workerLog('worker: Max ALSA volume (' . $_SESSION['alsavolume_max'] . '%)');
}

// Configure Allo Piano 2.1
if ($_SESSION['i2sdevice'] == 'Allo Piano 2.1 Hi-Fi DAC') {
	$dualmode = sysCmd('/var/www/command/util.sh get-piano-dualmode');
	$submode = sysCmd('/var/www/command/util.sh get-piano-submode');
	// determine output mode
	if ($dualmode[0] != 'None') {
		$outputmode = $dualmode[0];
	}
	else {
		$outputmode = $submode[0];
	}
	// Used in mpdcfg job and index.php
	$_SESSION['piano_dualmode'] = $dualmode[0];
	workerLog('worker: Piano output mode (' . $outputmode . ')');

	// Workaround: bump one of the channels to initialize volume
	sysCmd('amixer -c0 sset "Digital" 0');
	sysCmd('speaker-test -c 2 -s 2 -r 48000 -F S16_LE -X -f 24000 -t sine -l 1');
	// Reset Main vol back to 100% (0dB) if indicated
	if (($_SESSION['mpdmixer'] == 'software' || $_SESSION['mpdmixer'] == 'disabled') && $_SESSION['piano_dualmode'] != 'None') {
		sysCmd('amixer -c0 sset "Digital" 100%');
	}
	workerLog('worker: Piano 2.1 initialized');
}

// Reset renderer active flags
workerLog('worker: Reset renderer active flags');
$result = sdbquery("UPDATE cfg_system SET value='0' WHERE param='btactive' OR param='airplayactv' OR param='spotactive' OR param='slactive' OR param='inpactive'", $dbh);

//
workerLog('worker: -- MPD');
//

// Start MPD
#updMpdConf($_SESSION['i2sdevice']);
#workerLog('worker: MPD conf updated');
sysCmd("systemctl start mpd");
workerLog('worker: MPD started');
$sock = openMpdSock('localhost', 6600);
workerLog($sock === false ? 'worker: MPD connection refused' : 'worker: MPD accepting connections');
// Ensure valid mpd output config
workerLog('worker: Configure MPD outputs');
$mpdoutput = configMpdOutputs();
sysCmd('mpc enable only ' . $mpdoutput);
setMpdHttpd();
// Report mpd outputs
sendMpdCmd($sock, 'outputs');
$result = parseMpdOutputs(readMpdResp($sock));
workerLog('worker: ' . $result[0]); // ALSA default
workerLog('worker: ' . $result[1]); // ALSA crossfeed
workerLog('worker: ' . $result[2]); // ALSA parametric eq
workerLog('worker: ' . $result[3]); // ALSA graphic eq
workerLog('worker: ' . $result[4]); // ALSA polarity inversion
workerLog('worker: ' . $result[5]); // ALSA bluetooth
workerLog('worker: ' . $result[6]); // MPD httpd
// MPD crossfade
workerLog('worker: MPD crossfade (' . ($_SESSION['mpdcrossfade'] == '0' ? 'off' : $_SESSION['mpdcrossfade'] . ' secs')  . ')');

//
workerLog('worker: -- Feature availability');
//

// Configure audio source
if ($_SESSION['feat_bitmask'] & FEAT_INPSOURCE) {
	workerLog('worker: Source select (available)');
	$audio_source = $_SESSION['audioin'] == 'Local' ? 'MPD' : ($_SESSION['audioin'] == 'Analog' ? 'Analog input' : 'S/PDIF input');
	workerLog('worker: Source select (source: ' . $audio_source . ')');
	$audio_output = $_SESSION['i2sdevice'] == 'none' ? getDeviceNames()[$_SESSION['cardnum']] : $_SESSION['i2sdevice'];
	workerLog('worker: Source select (output: ' . $audio_output . ')');

	if ($_SESSION['i2sdevice'] == 'HiFiBerry DAC+ ADC' || strpos($_SESSION['i2sdevice'], 'Audiophonics ES9028/9038 DAC') !== -1) {
		setAudioIn($_SESSION['audioin']);
	}
}
else {
	workerLog('worker: Source select (n/a)');
}

// Start bluetooth controller and pairing agent
if ($_SESSION['feat_bitmask'] & FEAT_BLUETOOTH) {
	workerLog('worker: Bluetooth (available)');
	if (isset($_SESSION['btsvc']) && $_SESSION['btsvc'] == 1) {
		workerLog('worker: Bluetooth (started)');
		startBt();

		if (isset($_SESSION['pairing_agent']) && $_SESSION['pairing_agent'] == 1) {
			workerLog('worker: Bluetooth pairing agent (started)');
			sysCmd('/var/www/command/bt-agent.py --agent --disable_pair_mode_switch --pair_mode --wait_for_bluez >/dev/null 2>&1 &');
		}
	}
}
else {
	workerLog('worker: Bluetooth (n/a)');
}

// Start airplay renderer
if ($_SESSION['feat_bitmask'] & FEAT_AIRPLAY) {
	workerLog('worker: Airplay renderer (available)');
	if (isset($_SESSION['airplaysvc']) && $_SESSION['airplaysvc'] == 1) {
		startSps();
		workerLog('worker: Airplay renderer (started)');
	}
}
else {
	workerLog('worker: Airplay renderer (n/a)');
}

// Start Spotify renderer
if ($_SESSION['feat_bitmask'] & FEAT_SPOTIFY) {
	workerLog('worker: Spotify renderer (available)');
	if (isset($_SESSION['spotifysvc']) && $_SESSION['spotifysvc'] == 1) {
		startSpotify();
		workerLog('worker: Spotify renderer (started)');
	}
}
else {
	workerLog('worker: Spotify renderer (n/a)');
}

// Start squeezelite renderer
if ($_SESSION['feat_bitmask'] & FEAT_SQUEEZELITE) {
	workerLog('worker: Squeezelite renderer (available)');
	if (isset($_SESSION['slsvc']) && $_SESSION['slsvc'] == 1) {
		cfgSqueezelite();
		startSqueezeLite();
		workerLog('worker: Squeezelite renderer (started)');
	}
}
else {
	workerLog('worker: Squeezelite renderer (n/a)');
}

// Start UPnP renderer
if ($_SESSION['feat_bitmask'] & FEAT_UPMPDCLI) {
	workerLog('worker: UPnP renderer (available)');
	if (isset($_SESSION['upnpsvc']) && $_SESSION['upnpsvc'] == 1) {
		sysCmd('systemctl start upmpdcli');
		workerLog('worker: UPnP renderer (started)');
	}
}
else {
	workerLog('worker: UPnP renderer (n/a)');
}

// start minidlna
if ($_SESSION['feat_bitmask'] & FEAT_MINIDLNA) {
	workerLog('worker: DLNA server (available)');
	if (isset($_SESSION['dlnasvc']) && $_SESSION['dlnasvc'] == 1) {
		startMiniDlna();
		workerLog('worker: DLNA server (started)');
	}
}
else {
	workerLog('worker: DLNA Server (n/a)');
}

// Start upnp browser
if ($_SESSION['feat_bitmask'] & FEAT_DJMOUNT) {
	workerLog('worker: UPnP browser (available)');
	if (isset($_SESSION['upnp_browser']) && $_SESSION['upnp_browser'] == 1) {
		sysCmd('djmount -o allow_other,nonempty,iocharset=utf-8 /mnt/UPNP');
		workerLog('worker: UPnP browser (started)');
	}
}
else {
	workerLog('worker: UPnP browser (n/a)');
}

// Start audio scrobbler
if ($_SESSION['feat_bitmask'] & FEAT_MPDAS) {
	workerLog('worker: Audio scrobbler (available)');
	if (isset($_SESSION['mpdassvc']) && $_SESSION['mpdassvc'] == 1) {
		sysCmd('/usr/local/bin/mpdas > /dev/null 2>&1 &');
		workerLog('worker: Audio scrobbler (started)');
	}
}
else {
	workerLog('worker: Audio scrobbler (n/a)');
}

// Start gpio button handler
if ($_SESSION['feat_bitmask'] & FEAT_GPIO) {
	workerLog('worker: GPIO button handler (available)');
	if (isset($_SESSION['gpio_svc']) && $_SESSION['gpio_svc'] == 1) {
		startGpioSvc();
		workerLog('worker: GPIO button handler (started)');
	}
}
else {
	workerLog('worker: GPIO button handler (n/a)');
}

//
workerLog('worker: -- Music sources');
//

// List usb sources
$usbdrives = sysCmd('ls /media');
if ($usbdrives[0] == '') {
	workerLog('worker: USB sources (none attached)');
}
else {
	foreach ($usbdrives as $usbdrive) {
		workerLog('worker: USB source ' . '(' . $usbdrive . ')');
	}
}

// Mount nas and upnp sources
$result = sourceMount('mountall');
workerLog('worker: NAS and UPnP sources (' . $result . ')');

//
workerLog('worker: -- Miscellaneous');
//

// Start rotary encoder
if (isset($_SESSION['rotaryenc']) && $_SESSION['rotaryenc'] == 1) {
	sysCmd('systemctl start rotenc');
	workerLog('worker: Rotary encoder on (' . $_SESSION['rotenc_params'] . ')');
}

// Log USB volume knob on/off state
workerLog('worker: USB volume knob (' . ($_SESSION['usb_volknob'] == '1' ? 'On' : 'Off') . ')');

// Start lcd updater engine
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

// LED states
if (substr($_SESSION['hdwrrev'], 0, 7) == 'Pi-Zero') {
	$led0_trigger = explode(',', $_SESSION['led_state'])[0] == '0' ? 'none' : 'mmc0';
	$led0_brightness = explode(',', $_SESSION['led_state'])[0] == '0' ? '1' : '0';
	sysCmd('echo ' . $led0_trigger . ' | sudo tee /sys/class/leds/led0/trigger > /dev/null 2>&1');
	sysCmd('echo ' . $led0_brightness . ' | sudo tee /sys/class/leds/led0/brightness > /dev/null 2>&1');
	workerLog('worker: LED0 (' . ($led0_trigger == 'none' ? 'Off' : 'On') . ')');
	workerLog('worker: LED1 (sysclass does not exist)');
}
elseif ($_SESSION['hdwrrev'] == 'Allo USBridge SIG [CM3+ Lite 1GB v1.0]' || substr($_SESSION['hdwrrev'], 3, 1) == '1') {
	$led0_trigger = explode(',', $_SESSION['led_state'])[0] == '0' ? 'none' : 'mmc0';
	sysCmd('echo ' . $led0_trigger . ' | sudo tee /sys/class/leds/led0/trigger > /dev/null 2>&1');
	workerLog('worker: LED0 (' . ($led0_trigger == 'none' ? 'Off' : 'On') . ')');
	workerLog('worker: LED1 (sysclass does not exist)');
}
else {
	$led0_trigger = explode(',', $_SESSION['led_state'])[0] == '0' ? 'none' : 'mmc0';
	$led1_brightness = explode(',', $_SESSION['led_state'])[1] == '0' ? '0' : '255';
	sysCmd('echo ' . $led0_trigger . ' | sudo tee /sys/class/leds/led0/trigger > /dev/null 2>&1');
	sysCmd('echo ' . $led1_brightness . ' | sudo tee /sys/class/leds/led1/brightness > /dev/null 2>&1');
	workerLog('worker: LED0 (' . ($led0_trigger == 'none' ? 'Off' : 'On') . ')');
	workerLog('worker: LED1 (' . ($led1_brightness == '0' ? 'Off' : 'On') . ')');
}

// Since we initially set alsa volume to 0 at the beginning of startup it must be reset
if ($_SESSION['alsavolume'] != 'none') {
	if ($_SESSION['mpdmixer'] == 'software' || $_SESSION['mpdmixer'] == 'disabled') {
		$result = sysCmd('/var/www/command/util.sh set-alsavol ' . '"' . $_SESSION['amixname']  . '" ' . $_SESSION['alsavolume_max']);
	}
}

// Restore MPD volume level
workerLog('worker: Saved MPD vol level (' . $_SESSION['volknob_mpd'] . ')');
workerLog('worker: Preamp volume level (' . $_SESSION['volknob_preamp'] . ')');
$volume = $_SESSION['volknob_mpd'] != '0' ? $_SESSION['volknob_mpd'] : $_SESSION['volknob'];
sysCmd('/var/www/vol.sh ' . $volume);
workerLog('worker: MPD volume level (' . $volume . ') restored');
if ($_SESSION['alsavolume'] != 'none') {
	$result = sysCmd('/var/www/command/util.sh get-alsavol ' . '"' . $_SESSION['amixname'] . '"');
	workerLog('worker: ALSA ' . $_SESSION['amixname'] . ' volume (' . $result[0] . ')');
}
else {
	workerLog('worker: ALSA volume level (None)');
}

// Auto-play: start auto-shuffle random play or play last played item
if ($_SESSION['autoplay'] == '1') {
	workerLog('worker: Auto-play (On)');
	if ($_SESSION['ashuffle'] == '1') {
		workerLog('worker: Starting auto-shuffle');
		if (!empty($_SESSION['ashuffle_filter']) && $_SESSION['ashuffle_filter'] != 'None') {
			$cmd = 'mpc search ' . $_SESSION['ashuffle_filter'] . ' | /usr/local/bin/ashuffle --queue_buffer 1 --file - > /dev/null 2>&1 &';
		}
		else {
			$cmd = '/usr/local/bin/ashuffle --queue_buffer 1 > /dev/null 2>&1 &';
		}

		sysCmd($cmd);
	}
	else {
		$status = parseStatus(getMpdStatus($sock));
		sendMpdCmd($sock, 'playid ' . $status['songid']);
		$resp = readMpdResp($sock);
		workerLog('worker: Auto-playing id (' . $status['songid'] . ')');
	}
}
else {
	workerLog('worker: Auto-play (Off)');
	sendMpdCmd($sock, 'stop');
	$resp = readMpdResp($sock);
	// Turn off Auto-shuffle based random play if it's on
	if ($_SESSION['ashuffle'] == '1') {
		playerSession('write', 'ashuffle', '0');
		sendMpdCmd($sock, 'consume 0');
		$resp = readMpdResp($sock);
		workerLog('worker: Random Play reset to (Off)');
	}
}

// Start localui
if ($_SESSION['localui'] == '1') {
	sysCmd('systemctl start localui');
	workerLog('worker: LocalUI started');
}

// Reducing 3B+ eth port speed fixes audio glitches when using certain usb dacs
if (substr($_SESSION['hdwrrev'], 0, 6) == 'Pi-3B+' && $_SESSION['eth_port_fix'] == '1' && !empty($eth0ip[0])) {
	sysCmd('ethtool -s eth0 speed 100 duplex full');
	workerLog('worker: eth0 port fix applied');
	workerLog('worker: eth0 (100 Mb/s full duplex)');
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
$inpactive = '0';

// Library update, MPD database regen
$check_library_update = '0';
$check_library_regen = 0;

// Maintenance task
$maint_interval = $_SESSION['maint_interval'];
$maint_interval_formatted = $maint_interval == 3600 ? '1 hr' : ($maint_interval < 3600 ? ($maint_interval / 60) . ' mins' : ($maint_interval / 3600) . ' hrs');
workerLog('worker: Maintenance interval (' . $maint_interval_formatted . ')');

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

// Close MPD socket
closeMpdSock($sock);

// Close session
session_write_close();

// Check permissions on the session file
phpSessionCheck();

// Auto-configure if indicated
// NOTE: This is done near the end of startup because autoConfig() uses the wpa_passphrase utility which requires
// sufficient kernel entropy in order to generate the PSK. If there is not enough entropy, wpa_passphrase returns
// the input password instead of a PSK.
if (file_exists('/boot/moodecfg.txt')) {
	sysCmd('truncate ' . AUTOCFG_LOG . ' --size 0');

	autoConfig('/boot/moodecfg.txt');

	sysCmd('sync');
	autoCfgLog('autocfg: System rebooted');
	sysCmd('reboot');
}

// Start watchdog monitor
sysCmd('/var/www/command/watchdog.sh > /dev/null 2>&1 &');
workerLog('worker: Watchdog started');

//
workerLog('worker: Ready');
$result = sdbquery("UPDATE cfg_system SET value='1' WHERE param='wrkready'", $dbh);
//

//
// BEGIN WORKER JOB LOOP
//

while (true) {
	sleep(3);

	session_start();

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
	if ($_SESSION['i2sdevice'] == 'HiFiBerry DAC+ ADC' || strpos($_SESSION['i2sdevice'], 'Audiophonics ES9028/9038 DAC') !== -1) {
		chkInpActive();
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

	session_write_close();
}

//
// WORKER FUNCTIONS
//

function chkScnSaver() {
	// Activate if timeout is set and no other overlay is active
	if ($GLOBALS['scnsaver_timeout'] != 'Never' && $_SESSION['btactive'] == '0' && $GLOBALS['aplactive'] == '0' && $GLOBALS['spotactive'] == '0' && $GLOBALS['slactive'] == '0'  && $GLOBALS['inpactive'] == '0') {
		if ($GLOBALS['scnactive'] == '0') {
			$GLOBALS['scnsaver_timeout'] = $GLOBALS['scnsaver_timeout'] - 3;
			if ($GLOBALS['scnsaver_timeout'] <= 0) {
				$GLOBALS['scnsaver_timeout'] = $_SESSION['scnsaver_timeout']; // reset timeout
				$GLOBALS['scnactive'] = '1';
				sendEngCmd('scnactive1');
			}
		}
	}
}

function chkMaintenance() {
	$GLOBALS['maint_interval'] = $GLOBALS['maint_interval'] - 3;
	if ($GLOBALS['maint_interval'] <= 0) {
		// Clear logs
		$result = sysCmd('/var/www/command/util.sh "clear-syslogs"');
		if (!empty($result)) {
			workerLog('Maintenance: Problem clearing logs');
		}

		// Compact SQLite database
		$result = sysCmd('sqlite3 /var/local/www/db/moode-sqlite3.db "vacuum"');
		if (!empty($result)) {
			workerLog('Maintenance: Problem compacting SQLite database');
		}

		// Prune temp or intermediate resources
		sysCmd('find /var/www -type l -delete');
		sysCmd('rm /var/local/www/imagesw/stations.zip > /dev/null 2>&1');

		// Check for low disk soace
		$free_space = sysCmd("df | grep /dev/root | awk '{print $4}'");
		if ($free_space[0] < 512000) {
			workerLog('Maintenance: Free disk space < 512M required for in-place updates');
		}

		$GLOBALS['maint_interval'] = $_SESSION['maint_interval'];
		//workerLog('worker: Maintenance completed');
	}
}

function chkBtActive() {
	$result = sdbquery("SELECT value FROM cfg_system WHERE param='inpactive'", $GLOBALS['dbh']);
	if ($result[0]['value'] == '1') {
		return; // Bail if input source is active
	}

	$result = sysCmd('pgrep -l bluealsa-aplay');
	if (strpos($result[0], 'bluealsa-aplay') !== false) {
		// do this section only once
		if ($_SESSION['btactive'] == '0') {
			playerSession('write', 'btactive', '1');
			$GLOBALS['scnsaver_timeout'] = $_SESSION['scnsaver_timeout']; // reset timeout
			if ($_SESSION['alsavolume'] != 'none') {
				sysCmd('/var/www/command/util.sh set-alsavol ' . '"' . $_SESSION['amixname']  . '" ' . $_SESSION['alsavolume_max']);
			}
		}
		sendEngCmd('btactive1'); // Placing here enables each conected device to be printed to the indicator overlay
	}
	else {
		// do this section only once
		if ($_SESSION['btactive'] == '1') {
			playerSession('write', 'btactive', '0');
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
	$result = sdbquery("SELECT value FROM cfg_system WHERE param='airplayactv'", $GLOBALS['dbh']);
	if ($result[0]['value'] == '1') {
		// do this section only once
		if ($GLOBALS['aplactive'] == '0') {
			$GLOBALS['aplactive'] = '1';
			$GLOBALS['scnsaver_timeout'] = $_SESSION['scnsaver_timeout']; // reset timeout
			sendEngCmd('aplactive1');
		}
	}
	else {
		// Do this section only once
		if ($GLOBALS['aplactive'] == '1') {
			$GLOBALS['aplactive'] = '0';
			sendEngCmd('aplactive0');
		}
	}
}

function chkSpotActive() {
	// Get directly from sql since external spotevent.sh script does not update the session
	$result = sdbquery("SELECT value FROM cfg_system WHERE param='spotactive'", $GLOBALS['dbh']);
	if ($result[0]['value'] == '1') {
		// Do this section only once
		if ($GLOBALS['spotactive'] == '0') {
			$GLOBALS['spotactive'] = '1';
			$GLOBALS['scnsaver_timeout'] = $_SESSION['scnsaver_timeout']; // reset timeout
			sendEngCmd('spotactive1');
		}
	}
	else {
		// Do this section only once
		if ($GLOBALS['spotactive'] == '1') {
			$GLOBALS['spotactive'] = '0';
			sendEngCmd('spotactive0');
		}
	}
}

function chkSlActive() {
	// Get directly from sql since external slpower.sh script does not update the session
	$result = sdbquery("SELECT value FROM cfg_system WHERE param='slactive'", $GLOBALS['dbh']);
	if ($result[0]['value'] == '1') {
		// Do this section only once
		if ($GLOBALS['slactive'] == '0') {
			$GLOBALS['slactive'] = '1';
			$GLOBALS['scnsaver_timeout'] = $_SESSION['scnsaver_timeout']; // reset timeout
			sendEngCmd('slactive1');
		}
	}
	else {
		// Do this section only once
		if ($GLOBALS['slactive'] == '1') {
			$GLOBALS['slactive'] = '0';
			sendEngCmd('slactive0');
		}
	}
}

function chkInpActive() {
	//$result = sysCmd('pgrep -l alsaloop');
	//if (strpos($result[0], 'alsaloop') !== false) {
	if ($_SESSION['audioin'] != 'Local') {
		// Do this section only once
		if ($GLOBALS['inpactive'] == '0') {
			playerSession('write', 'inpactive', '1');
			$GLOBALS['inpactive'] = '1';
			$GLOBALS['scnsaver_timeout'] = $_SESSION['scnsaver_timeout'];
			sendEngCmd('inpactive1');
		}
	}
	else {
		// Do this section only once
		if ($GLOBALS['inpactive'] == '1') {
			playerSession('write', 'inpactive', '0');
			$GLOBALS['inpactive'] = '0';
			sendEngCmd('inpactive0');
		}
	}
}

function updExtMetaFile() {
	// Output rate
	$hwparams = parseHwParams(shell_exec('cat /proc/asound/card' . $_SESSION['cardnum'] . '/pcm0p/sub0/hw_params'));
	if ($hwparams['status'] == 'active') {
		$hwparams_format = $hwparams['format'] . ' bit, ' . $hwparams['rate'] . ' kHz, ' . $hwparams['channels'];
		$hwparams_calcrate = ', ' . $hwparams['calcrate'] . ' Mbps';
	}
	else {
		$hwparams_format = '';
		$hwparams_calcrate = '0 bps';
	}
	// Currentsong.txt
	$filemeta = parseDelimFile(file_get_contents('/var/local/www/currentsong.txt'), '=');
	//workerLog($filemeta['file'] . ' | ' . $hwparams_calcrate);

	if ($GLOBALS['aplactive'] == '1' || $GLOBALS['spotactive'] == '1' || $GLOBALS['slactive'] == '1' || $GLOBALS['inpactive'] == '1' || ($_SESSION['btactive'] && $_SESSION['audioout'] == 'Local')) {
			//workerLog('renderer active');
			// Renderer active
			if ($GLOBALS['aplactive'] == '1') {
				$renderer = 'Airplay Active';
			}
			elseif ($GLOBALS['spotactive'] == '1') {
				$renderer = 'Spotify Active';
			}
			elseif ($GLOBALS['slactive'] == '1') {
				$renderer = 'Squeezelite Active';
			}
			elseif ($GLOBALS['inpactive'] == '1') {
				$renderer = $_SESSION['audioin'] .' Input Active';
			}
			else {
				$renderer = 'Bluetooth Active';
			}
			// Write file only if something has changed
			if ($filemeta['file'] != $renderer && $hwparams_calcrate != '0 bps') {
				//workerLog('writing file');
				$fh = fopen('/var/local/www/currentsong.txt', 'w') or exit('file open failed on /var/local/www/currentsong.txt');
				$data = 'file=' . $renderer . "\n";
				$data .= 'outrate=' . $hwparams_format . $hwparams_calcrate . "\n"; ;
				fwrite($fh, $data);
				fclose($fh);
			}
	}
	else {
		//workerLog('mpd active');
		// MPD active
		$sock = openMpdSock('localhost', 6600);
		$current = parseStatus(getMpdStatus($sock));
		$current = enhanceMetadata($current, $sock, 'worker_php');
		closeMpdSock($sock);

		//workerLog('updExtMetaFile(): currentencoded=' . $_SESSION['currentencoded']);

		// Write file only if something has changed
		if ($current['title'] != $filemeta['title'] || $current['album'] != $filemeta['album'] || $_SESSION['volknob'] != $filemeta['volume'] ||
			$_SESSION['volmute'] != $filemeta['mute'] || $current['state'] != $filemeta['state'] || $filemeta['outrate'] != $hwparams_format . $hwparams_calcrate) {
			//workerLog('writing file');
			$fh = fopen('/var/local/www/currentsong.txt', 'w') or exit('file open failed on /var/local/www/currentsong.txt');
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
			$data .= 'outrate=' . $hwparams_format . $hwparams_calcrate . "\n"; ;
			$data .= 'volume=' . $_SESSION['volknob'] . "\n";
			$data .= 'mute=' . $_SESSION['volmute'] . "\n";
			$data .= 'state=' . $current['state'] . "\n";
			fwrite($fh, $data);
			fclose($fh);
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

	}
	else if ($curtime == $GLOBALS['clkradio_stop_time'] && $GLOBALS['clkradio_stop_days'][$curday] == '1') {
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
				$action = 'reboot';
				$delay = 45; // To ensure that after reboot $curtime != clkradio_stop_time
			}
			elseif ($_SESSION['clkradio_action'] == 'Shutdown') {
				$action = 'poweroff';
				$delay = 0;
			}

			sleep($delay);
			sysCmd('/var/local/www/commandw/restart.sh ' . $action);
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
				$action = 'reboot';
				$delay = 45; // To ensure that after reboot $curtime != clkradio_stop_time
			}
			elseif ($_SESSION['clkradio_action'] == 'Shutdown') {
				$action = 'poweroff';
				$delay = 0;
			}

			sleep($delay);
			sysCmd('/var/local/www/commandw/restart.sh ' . $action);
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
	$song = parseCurrentSong($sock);
	closeMpdSock($sock);

	// iTunes aac file
	if (isset($song['Name']) && getFileExt($song['file']) == 'm4a') {
		$artist = isset($song['Artist']) ? $song['Artist'] : 'Unknown artist';
		$title = $song['Name'];
		$album = isset($song['Album']) ? $song['Album'] : 'Unknown album';

		// search string
		if ($artist == 'Unknown artist' && $album == 'Unknown album') {$searchstr = $title;}
		else if ($artist == 'Unknown artist') {$searchstr = $album . '+' . $title;}
		else if ($album == 'Unknown album') {$searchstr = $artist . '+' . $title;}
		else {$searchstr = $artist . '+' . $album;}

	}
	// Radio station
	else if (isset($song['Name']) || (substr($song['file'], 0, 4) == 'http' && !isset($song['Artist']))) {
		$artist = 'Radio station';

		if (!isset($song['Title']) || trim($song['Title']) == '') {
			$title = $song['file'];
		}
		else {
			// Use custom name if indicated
			$title = $_SESSION[$song['file']]['name'] == 'Classic And Jazz' ? 'CLASSIC & JAZZ (Paris - France)' : $song['Title'];
		}

		if (isset($_SESSION[$song['file']])) {
			$album = $_SESSION[$song['file']]['name'];
		}
		else {
			$album = isset($song['Name']) ? $song['Name'] : 'Unknown station';
		}

		// Search string
		if (substr($title, 0, 4) == 'http') {
			$searchstr = $title;
		}
		else {
			$searchstr = str_replace('-', ' ', $title);
			$searchstr = str_replace('&', ' ', $searchstr);
			$searchstr = preg_replace('!\s+!', '+', $searchstr);
		}
	}
	// Song file or upnp url
	else {
		$artist = isset($song['Artist']) ? $song['Artist'] : 'Unknown artist';
		$title = isset($song['Title']) ? $song['Title'] : pathinfo(basename($song['file']), PATHINFO_FILENAME);
		$album = isset($song['Album']) ? $song['Album'] : 'Unknown album';

		// Search string
		if ($artist == 'Unknown artist' && $album == 'Unknown album') {$searchstr = $title;}
		else if ($artist == 'Unknown artist') {$searchstr = $album . '+' . $title;}
		else if ($album == 'Unknown album') {$searchstr = $artist . '+' . $title;}
		else {$searchstr = $artist . '+' . $album;}
	}

	// Search url
	$searcheng = 'http://www.google.com/search?q=';
	$searchurl = '<a href="' . $searcheng . $searchstr . '" class="playhistory-link" target="_blank"><i class="fas fa-external-link-square"></i></a>';

	// Update playback history log
	if ($title != '' && $title != $_SESSION['phistsong']) {
		$_SESSION['phistsong'] = $title; // store title as-is
		cfgdb_update('cfg_system', $GLOBALS['dbh'], 'phistsong', str_replace("'", "''", $title)); // write to cfg db using sql escaped single quotes

		$historyitem = '<li class="playhistory-item"><div>' . date('Y-m-d H:i') . $searchurl . $title . '</div><span>' . $artist . ' - ' . $album . '</span></li>';
		$result = updPlayHist($historyitem);
	}
}

// Check for library update complete
function chkLibraryUpdate() {
	//workerLog('chkLibraryUpdate');
	$sock = openMpdSock('localhost', 6600);
	$status = parseStatus(getMpdStatus($sock));
	closeMpdSock($sock);

	if (!isset($status['updating_db'])) {
		sendEngCmd('libupd_done');
		$GLOBALS['check_library_update'] = '0';
		workerLog('worker: Job update_library done');
	}
}

// Check for library regen complete
function chkLibraryRegen() {
	//workerLog('chkLibraryRegen');
	$sock = openMpdSock('localhost', 6600);
	$status = parseStatus(getMpdStatus($sock));
	closeMpdSock($sock);

	if (!isset($status['updating_db'])) {
		sendEngCmd('libregen_done');
		$GLOBALS['check_library_regen'] = '0';
		workerLog('worker: Job regen_library done');
	}
}

// Log info for the active interface (eth0 or wlan0)
function log_network_info($interface) {
	workerLog('worker: IP addr (' . sysCmd("ifconfig " . $interface . " | awk 'NR==2{print $2}'")[0] . ')');
	workerLog('worker: Netmask (' . sysCmd("ifconfig " . $interface . " | awk 'NR==2{print $4}'")[0] . ')');
	workerLog('worker: Gateway (' . sysCmd("netstat -nr | awk 'NR==3 {print $2}'")[0] . ')');
	$line3 = sysCmd("cat /etc/resolv.conf | awk 'NR==3 {print $2}'")[0]; // nameserver
	$line2 = sysCmd("cat /etc/resolv.conf | awk 'NR==2 {print $2}'")[0]; // domain
	$primary_dns = !empty($line3) ? $line3 : $line2;
	$domain_name = !empty($line3) ? $line2 : 'None';
	workerLog('worker: Pri DNS (' . $primary_dns . ')');
	workerLog('worker: Domain  (' . $domain_name . ')');
}

//
// PROCESS SUBMITTED JOBS
//

function runQueuedJob() {
	$_SESSION['w_lock'] = 1;

	// No need to log screen saver resets
	if ($_SESSION['w_queue'] != 'resetscnsaver') {
		workerLog('worker: Job ' . $_SESSION['w_queue']);
	}

	switch ($_SESSION['w_queue']) {
		// Screen saver reset job
		case 'resetscnsaver':
			$GLOBALS['scnsaver_timeout'] = $_SESSION['scnsaver_timeout'];
			$GLOBALS['scnactive'] = '0';
			// NOTE: We might use this in the future
			//sendEngCmd('scnactive0,' . $_SESSION['w_queueargs']); // w_queueargs contains the client IP address
			break;

		// Nenu, Update library, Context menu, Update this folder
		case 'update_library':
			clearLibCache();
			$sock = openMpdSock('localhost', 6600);
			$cmd = empty($_SESSION['w_queueargs']) ? 'update' : 'update "' . html_entity_decode($_SESSION['w_queueargs']) . '"';
			sendMpdCmd($sock, $cmd);
			$resp = readMpdResp($sock);
			closeMpdSock($sock);
			// Launch thumbcache updater
			$result = sysCmd('pgrep -l thmcache.php');
			if (strpos($result[0], 'thmcache.php') === false) {
				sysCmd('/var/www/command/thmcache.php > /dev/null 2>&1 &');
				//workerLog('update_library, thmcache.php launched');
			}
			$GLOBALS['check_library_update'] = '1';
			break;

		// lib-config jobs
		case 'sourcecfg':
			clearLibCache();
			sourceCfg($_SESSION['w_queueargs']);
			break;
		case 'regen_library':
			clearLibCache();
			$sock = openMpdSock('localhost', 6600);
			sendMpdCmd($sock, 'rescan');
			$resp = readMpdResp($sock);
			closeMpdSock($sock);
			// Launch thumbcache updater
			$result = sysCmd('pgrep -l thmcache.php');
			if (strpos($result[0], 'thmcache.php') === false) {
				sysCmd('/var/www/command/thmcache.php > /dev/null 2>&1 &');
				//workerLog('regen_library, thmcache.php launched');
			}
			$GLOBALS['check_library_regen'] = '1';
			break;
		case 'regen_thmcache':
			sysCmd('rm -rf ' . THMCACHE_DIR);
			sysCmd('mkdir ' . THMCACHE_DIR);
			sysCmd('/var/www/command/thmcache.php > /dev/null 2>&1 &');
			break;

		// mpd-config jobs
		case 'mpdrestart':
			sysCmd('mpc stop');
			sysCmd('systemctl restart mpd');
			break;
		case 'mpdcfg':
			sysCmd('mpc stop');

			// Update config file
			updMpdConf($_SESSION['i2sdevice']);

			// Reset hardware volume to 0dB (100) if indicated
			if (($_SESSION['mpdmixer'] == 'software' || $_SESSION['mpdmixer'] == 'disabled') && $_SESSION['alsavolume'] != 'none') {
				sysCmd('/var/www/command/util.sh set-alsavol ' . '"' . $_SESSION['amixname']  . '" ' . $_SESSION['alsavolume_max']);
			}

			// Restart mpd and pick up conf changes
			sysCmd('systemctl restart mpd');

			// Wait for mpd to start accepting connections
			$sock = openMpdSock('localhost', 6600);
			closeMpdSock($sock);

			// Set knob and MPD/hardware volume to 0
			sysCmd('/var/www/vol.sh 0');

			// Restart renderers if device num changed
			if ($_SESSION['w_queueargs'] == 'devicechg') {
				if ($_SESSION['airplaysvc'] == 1) {
					sysCmd('killall shairport-sync');
					startSps();
				}
				if ($_SESSION['spotifysvc'] == 1) {
					sysCmd('killall librespot');
					startSpotify();
				}
			}
			break;

		// snd-config jobs
		case 'i2sdevice':
			sysCmd('/var/www/vol.sh 0'); // Set knob and MPD/hardware volume to 0
			playerSession('write', 'autoplay', '0'); // to prevent play before MPD setting applied
			cfgI2sOverlay($_SESSION['w_queueargs']);
			break;
		case 'alsavolume_max':
			if (($_SESSION['mpdmixer'] == 'software' || $_SESSION['mpdmixer'] == 'disabled') && $_SESSION['alsavolume'] != 'none') {
				sysCmd('/var/www/command/util.sh set-alsavol ' . '"' . $_SESSION['amixname']  . '" ' . $_SESSION['w_queueargs']);
			}
			break;
		case 'rotaryenc':
			sysCmd('systemctl stop rotenc');
			sysCmd('sed -i "/ExecStart/c\ExecStart=' . '/usr/local/bin/rotenc ' . $_SESSION['rotenc_params'] . '"' . ' /lib/systemd/system/rotenc.service');
			sysCmd('systemctl daemon-reload');

			if ($_SESSION['w_queueargs'] == '1') {
				sysCmd('systemctl start rotenc');
			}
			break;
		case 'usb_volknob':
			if ($_SESSION['w_queueargs'] == '1') {
				sysCmd('systemctl enable triggerhappy');
				sysCmd('systemctl start triggerhappy');
			}
			else {
				sysCmd('systemctl stop triggerhappy');
				sysCmd('systemctl disable triggerhappy');
			}
			break;
		case 'invert_polarity':
			sysCmd('mpc stop');
			$cmd = $_SESSION['w_queueargs'] == '1' ? 'mpc enable only 5' : 'mpc enable only 1';
			sysCmd($cmd);
			setMpdHttpd();
			break;
		case 'crossfeed':
			sysCmd('mpc stop');

			if ($_SESSION['w_queueargs'] == 'Off') {
				sysCmd('mpc enable only 1');
			}
			else {
				sysCmd('sed -i "/controls/c\ \t\t\tcontrols [ ' . $_SESSION['w_queueargs'] . ' ]" ' . ALSA_PLUGIN_PATH . '/crossfeed.conf');
				sysCmd('mpc enable only 2');
			}
			setMpdHttpd();
			break;
		case 'mpd_httpd':
			$cmd = $_SESSION['w_queueargs'] == '1' ? 'mpc enable 7' : 'mpc disable 7';
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
		case 'eqfa4p':
			// Old,new curve name
			$setting = explode(',', $_SESSION['w_queueargs']);

			if ($setting[1] == 'Off') {
				sysCmd('mpc stop');
				sysCmd('mpc enable only 1');
			}
			else {
				// Check old curve name and stop playback if eq being turned on for first time
				if ($setting[0] == 'Off') {
					sysCmd('mpc stop');
				}

				$result = sdbquery("SELECT * FROM cfg_eqfa4p WHERE curve_name='" . $setting[1] . "'", $GLOBALS['dbh']);
				$params = $result[0]['band1_params'] . '  ' . $result[0]['band2_params'] . '  ' . $result[0]['band3_params'] . '  ' . $result[0]['band4_params'] . '  ' . $result[0]['master_gain'];

				sysCmd('sed -i "/controls/c\ \t\t\tcontrols [ ' . $params . ' ]" ' . ALSA_PLUGIN_PATH . '/eqfa4p.conf');
				sysCmd('mpc enable only 3');
			}

			setMpdHttpd();
			sysCmd('systemctl restart mpd');
			// Restart airplay and spotify
			stopSps();
			if ($_SESSION['airplaysvc'] == 1) {startSps();}
			stopSpotify();
			if ($_SESSION['spotifysvc'] == 1) {startSpotify();}
			break;
		case 'alsaequal':
			// Old,new curve name
			$setting = explode(',', $_SESSION['w_queueargs']);

			if ($setting[1] == 'Off') {
				sysCmd('mpc stop');
				sysCmd('mpc enable only 1');
			}
			else {
				// Check old curve name and stop playback if eq being turned on for first time
				if ($setting[0] == 'Off') {
					sysCmd('mpc stop');
				}

				$result = sdbquery("SELECT curve_values FROM cfg_eqalsa WHERE curve_name='" . $setting[1] . "'", $GLOBALS['dbh']);
				$curve = explode(',', $result[0]['curve_values']);
				foreach ($curve as $key => $value) {
					sysCmd('amixer -D alsaequal cset numid=' . ($key + 1) . ' ' . $value);
				}
				sysCmd('mpc enable only 4');
			}
			setMpdHttpd();
			sysCmd('systemctl restart mpd');
			// Restart airplay and spotify
			stopSps();
			if ($_SESSION['airplaysvc'] == 1) {startSps();}
			stopSpotify();
			if ($_SESSION['spotifysvc'] == 1) {startSpotify();}
			break;
		case 'mpdassvc':
			sysCmd('killall -s 9 mpdas > /dev/null');
			cfgAudioScrobbler();
			if ($_SESSION['w_queueargs'] == 1) {
				sysCmd('/usr/local/bin/mpdas > /dev/null 2>&1 &');
			}
			break;
		case 'mpdcrossfade':
			sysCmd('mpc crossfade ' . $_SESSION['w_queueargs']);
			break;
		case 'btsvc':
			sysCmd('/var/www/command/util.sh chg-name bluetooth ' . $_SESSION['w_queueargs']);
			sysCmd('systemctl stop bluealsa');
			sysCmd('systemctl stop bluetooth');
			sysCmd('killall bluealsa-aplay');
			sysCmd('/var/www/vol.sh -restore');
			// Reset to inactive
			playerSession('write', 'btactive', '0');
			sendEngCmd('btactive0');

			if ($_SESSION['btsvc'] == 1) {
				startBt();
			}
			else {
				sysCmd('killall -s 9 bt-agent.py');
			}
			break;
		case 'pairing_agent':
			$cmd = $_SESSION['w_queueargs'] == 1 ? '/var/www/command/bt-agent.py --agent --disable_pair_mode_switch --pair_mode --wait_for_bluez >/dev/null 2>&1 &' : 'killall -s 9 bt-agent.py';
			sysCmd($cmd);
			break;
		case 'btmulti':
			if ($_SESSION['btmulti'] == 1) {
				sysCmd("sed -i '/AUDIODEV/c\AUDIODEV=btaplay_dmix' /etc/bluealsaaplay.conf");
			}
			else {
				sysCmd("sed -i '/AUDIODEV/c\AUDIODEV=plughw:" . $_SESSION['cardnum'] . ",0' /etc/bluealsaaplay.conf");
			}
			break;
		case 'airplaysvc':
			stopSps(); //r45b
			if ($_SESSION['airplaysvc'] == 1) {startSps();}
			break;
		case 'spotifysvc':
			stopSpotify();
			if ($_SESSION['spotifysvc'] == 1) {startSpotify();}
			break;
		case 'spotify_clear_credentials':
			sysCmd('rm /var/local/www/spotify_cache/credentials.json');
			stopSpotify();
			if ($_SESSION['spotifysvc'] == 1) {startSpotify();}
			break;
		case 'slsvc':
			if ($_SESSION['slsvc'] == '1') {
				sysCmd('mpc stop');
				if ($_SESSION['alsavolume'] != 'none') {
					sysCmd('/var/www/command/util.sh set-alsavol ' . '"' . $_SESSION['amixname']  . '" ' . $_SESSION['alsavolume_max']);
				}
				cfgSqueezelite();
				startSqueezeLite();
			}
			else {
				sysCmd('killall -s 9 squeezelite');
				sysCmd('/var/www/vol.sh -restore');
				// reset to inactive
				playerSession('write', 'slactive', '0');
				$GLOBALS['slactive'] = '0';
				sendEngCmd('slactive0');
			}
			break;
		case 'slrestart':
			if ($_SESSION['slsvc'] == '1') {
				sysCmd('mpc stop');
				startSqueezeLite();
			}
			break;
		case 'slcfgupdate':
			cfgSqueezelite();
			if ($_SESSION['slsvc'] == '1') {
				sysCmd('mpc stop');
				startSqueezeLite();
			}
			break;
		case 'upnpsvc':
			sysCmd('/var/www/command/util.sh chg-name upnp ' . $_SESSION['w_queueargs']);
			sysCmd('systemctl stop upmpdcli');
			if ($_SESSION['upnpsvc'] == 1) {sysCmd('systemctl start upmpdcli');}
			break;
		case 'minidlna':
			sysCmd('/var/www/command/util.sh chg-name dlna ' . $_SESSION['w_queueargs']);
			sysCmd('systemctl stop minidlna');
			if ($_SESSION['dlnasvc'] == 1) {
				startMiniDlna();
			}
			else {
				syscmd('rm -rf /var/cache/minidlna/* > /dev/null');
			}
			break;
		case 'dlnarebuild':
			sysCmd('systemctl stop minidlna');
			syscmd('rm -rf /var/cache/minidlna/* > /dev/null');
			sleep(2);
			startMiniDlna();
			break;
		case 'upnp_browser':
			sysCmd('fusermount -u /mnt/UPNP > /dev/null 2>&1');
			if ($_SESSION['upnp_browser'] == 1) {sysCmd('djmount -o allow_other,nonempty,iocharset=utf-8 /mnt/UPNP');}
			break;

		// net-config jobs
		case 'netcfg':
			cfgNetIfaces();
			resetApMode();
			cfgHostApd();
			break;

		// sys-config jobs
		case 'installupd':
			sysCmd('/var/www/command/updater.sh ' . getPkgId() . ' > /dev/null 2>&1');
			break;
		case 'timezone':
			sysCmd('/var/www/command/util.sh set-timezone ' . $_SESSION['w_queueargs']);
			break;
		case 'hostname':
			sysCmd('/var/www/command/util.sh chg-name host ' . $_SESSION['w_queueargs']);
			break;
		case 'mpdver':
			sysCmd('mpc stop');
			sysCmd('cp /var/local/www/mpd_versions/mpd-' . $_SESSION['w_queueargs'] . ' /home/pi');
			sysCmd('mv /home/pi/mpd-' . $_SESSION['w_queueargs'] . ' /usr/local/bin/mpd');
			updMpdConf($_SESSION['i2sdevice']);
			sysCmd('systemctl restart mpd');
			break;
		case 'cpugov':
			sysCmd('sh -c ' . "'" . 'echo "' . $_SESSION['w_queueargs'] . '" | tee /sys/devices/system/cpu/cpu*/cpufreq/scaling_governor' . "'");
			break;
		case 'kernel_architecture':
			$cmd = $_SESSION['w_queueargs'] == '32-bit' ? 'sed -i /arm_64bit/d ' . '/boot/config.txt' : 'echo arm_64bit=1 >> ' . '/boot/config.txt';
			sysCmd($cmd);
			break;
		case 'usb_auto_mounter':
			if ($_SESSION['w_queueargs'] == 'udisks-glue') {
				sysCmd('sed -e "/udisks-glue/ s/^#*//" -i /etc/rc.local');
				sysCmd('sed -e "/devmon/ s/^#*/#/" -i /etc/rc.local');
				sysCmd('systemctl enable udisks');
				sysCmd('systemctl disable udisks2');
			}
			elseif ($_SESSION['w_queueargs'] == 'devmon') {
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
				$led0_trigger = $_SESSION['w_queueargs'] == '0' ? 'none' : 'mmc0';
				$led0_brightness = $_SESSION['w_queueargs'] == '0' ? '1' : '0';
				sysCmd('echo ' . $led0_trigger . ' | sudo tee /sys/class/leds/led0/trigger > /dev/null 2>&1');
				sysCmd('echo ' . $led0_brightness . ' | sudo tee /sys/class/leds/led0/brightness > /dev/null 2>&1');
			}
			else {
				$led0_trigger = $_SESSION['w_queueargs'] == '0' ? 'none' : 'mmc0';
				sysCmd('echo ' . $led0_trigger . ' | sudo tee /sys/class/leds/led0/trigger > /dev/null 2>&1');
			}
			break;
		case 'pwrled': // LED1
			$led1_brightness = $_SESSION['w_queueargs'] == '0' ? '0' : '255';
			sysCmd('echo ' . $led1_brightness . ' | sudo tee /sys/class/leds/led1/brightness > /dev/null 2>&1');
			break;
		case 'maxusbcurrent':
			$cmd = $_SESSION['w_queueargs'] == 1 ? 'echo max_usb_current=1 >> ' . '/boot/config.txt' : 'sed -i /max_usb_current/d ' . '/boot/config.txt';
			sysCmd($cmd);
			break;
		case 'uac2fix':
			$cmd = $_SESSION['w_queueargs'] == 1 ? 'sed -i "s/dwc_otg.lpm_enable=0/dwc_otg.lpm_enable=0 dwc_otg.fiq_fsm_mask=0x3/" /boot/cmdline.txt' : 'sed -i "s/ dwc_otg.fiq_fsm_mask=0x3//" /boot/cmdline.txt';
			sysCmd($cmd);
			break;
		case 'expandrootfs':
			sysCmd('/var/www/command/resizefs.sh start');
			break;
		case 'usbboot':
			sysCmd('sed -i /program_usb_boot_mode/d ' . '/boot/config.txt'); // remove first to prevent duplicate adds
			sysCmd('echo program_usb_boot_mode=1 >> ' . '/boot/config.txt');
			break;
		case 'localui':
			sysCmd('sudo systemctl ' . ($_SESSION['w_queueargs'] == '1' ? 'start' : 'stop') . ' localui');
			break;
		case 'touchscn':
			$param = $_SESSION['w_queueargs'] == '0' ? ' -- -nocursor' : '';
			sysCmd('sed -i "/ExecStart=/c\ExecStart=/usr/bin/xinit' .$param . '" /lib/systemd/system/localui.service');
			if ($_SESSION['localui'] == '1') {
				sysCmd('systemctl daemon-reload');
				sysCmd('systemctl restart localui');
			}
			break;
		case 'scnblank':
			sysCmd('sed -i "/xset s/c\xset s ' . $_SESSION['w_queueargs'] . '" /home/pi/.xinitrc');
			if ($_SESSION['localui'] == '1') {
				sysCmd('systemctl restart localui');
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
			}
			else {
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
			sysCmd('/var/www/command/util.sh clearbrcache');
			break;
		case 'keyboard':
			sysCmd('/var/www/command/util.sh set-keyboard ' . $_SESSION['w_queueargs']);
			break;
		case 'lcdup':
			$_SESSION['w_queueargs'] == 1 ? startLcdUpdater() : sysCmd('killall inotifywait > /dev/null 2>&1 &');
			break;
		case 'gpio_svc':
			sysCmd('killall -s 9 gpio-buttons.py');
			if ($_SESSION['w_queueargs'] == 1) {
				startGpioSvc();
			}
			break;
		case 'shellinabox':
			sysCmd('systemctl stop shellinabox');
			if ($_SESSION['w_queueargs'] == '1') {
				sysCmd('systemctl start shellinabox');
			}
			break;
		case 'clearsyslogs':
			sysCmd('/var/www/command/util.sh clear-syslogs');
			break;
		case 'clearplayhistory':
			sysCmd('/var/www/command/util.sh clear-playhistory');
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

		// command/moode jobs
		case 'setbgimage':
			$imgdata = base64_decode($_SESSION['w_queueargs'], true);
			if ($imgdata === false) {
				workerLog('worker: setbgimage: base64_decode failed');
			}
			else {
				$fh = fopen('/var/local/www/imagesw/bgimage.jpg', 'w');
				fwrite($fh, $imgdata);
				fclose($fh);
			}
			break;
		case 'setlogoimage':
			$queueargs = explode(',', $_SESSION['w_queueargs'], 2);
			$station_name = $queueargs[0];
			$imgdata = base64_decode($queueargs[1], true);
			if ($imgdata === false) {
				workerLog('worker: setlogoimage: base64_decode failed');
			}
			else {
				// main image
				$file = '/var/local/www/imagesw/radio-logos/' . TMP_STATION_PREFIX . $station_name . '.jpg';
				$fh = fopen($file, 'w');
				fwrite($fh, $imgdata);
				fclose($fh);

				// thumbnail
				$imgstr = file_get_contents($file);
				$image = imagecreatefromstring($imgstr);
				$thm_w = 200;
				$thm_q = 75;

				// image h/w
				$img_w = imagesx($image);
				$img_h = imagesy($image);
				// thumbnail height
				$thm_h = ($img_h / $img_w) * $thm_w;

				if (($thumb = imagecreatetruecolor($thm_w, $thm_h)) === false) {
					workerLog('setlogoimage: error 1: ' . $file);
					break;
				}
				if (imagecopyresampled($thumb, $image, 0, 0, 0, 0, $thm_w, $thm_h, $img_w, $img_h) === false) {
					workerLog('setlogoimage: error 2: ' . $file);
					break;
				}
				if (imagedestroy($image) === false) {
					workerLog('setlogoimage: error 3: ' . $file);
					break;
				}
				if (imagejpeg($thumb, '/var/local/www/imagesw/radio-logos/thumbs/' . TMP_STATION_PREFIX . $station_name . '.jpg', $thm_q) === false) {
					workerLog('setlogoimage: error 4: ' . $file);
					break;
				}
				if (imagedestroy($thumb) === false) {
					workerLog('setlogoimage: error 5: ' . $file);
					break;
				}
			}
			break;
		case 'import_stations':
			if (false === ($zip_data = base64_decode($_SESSION['w_queueargs'], true))) {
				workerLog('moode.php: import_stations base64_decode failed');
			}
			else {
				$file = '/var/local/www/station_import.zip';
				if (false === ($fh = fopen($file, 'w'))) {
					workerLog('moode.php: file create failed on ' . $file);
				}
				else {
					if (false === ($bytes_written = fwrite($fh, $zip_data))) {
						workerLog('moode.php: file write failed on ' . $file);
					}
					else {
						// Import station data
						sysCmd('/var/www/command/import_stations.sh');
						// Update MPD RADIO folder
						$sock = openMpdSock('localhost', 6600);
						sendMpdCmd($sock, 'update RADIO');
						$resp = readMpdResp($sock);
						closeMpdSock($sock);
						$GLOBALS['check_library_update'] = '1';
						// Update the session
						loadRadio();
					}
					fclose($fh);
				}
			}
			break;

		// Other jobs
		case 'reboot':
		case 'poweroff':
			resetApMode();
			sysCmd('/var/local/www/commandw/restart.sh ' . $_SESSION['w_queue']);
			break;
		case 'updclockradio':
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
