<?php
/*
 * SPDX-License-Identifier: GPL-3.0-or-later
 * Copyright 2014 The moOde audio player project / Tim Curtis
 * Copyright 2020 @bitlab (@bitkeeper Git)
*/

/**
 * Contains all functionality related the auto-configure settings implementation.
 *
 * NOTE: The Session must be opened by caller prior to calling
 * - autoConfigSettings()
 * - autoConfigExtract()
 * - autoConfig()
 */

require_once __DIR__ . '/common.php';
require_once __DIR__ . '/audio.php';
require_once __DIR__ . '/mpd.php';
require_once __DIR__ . '/music-source.php';
require_once __DIR__ . '/network.php';
require_once __DIR__ . '/session.php';
require_once __DIR__ . '/sql.php';

// Returns an array of settings and handlers for reading/writing the autoConfig file
// - the settings that are supported
// - a read handler for import
// - a write handler for export
function autoConfigSettings() {
	$debug = true;

	// Set just the session var
	function setSessVarOnly($values) {
		$_SESSION[array_key_first($values)] = $values[array_key_first($values)];
	}
	// Set session var and sql value
	function setSessVarSql($values) {
		phpSession('write', array_key_first($values), $values[array_key_first($values)]);
	}
	// Set session var, sql value and run a sysCmd call to sysutil.sh
	function setSessVarSqlSysCmd($values, $cmd) {
		sysCmd('/var/www/util/sysutil.sh '. sprintf($cmd, $values[array_key_first($values)]) );
		phpSession('write', array_key_first($values), $values[array_key_first($values)]);
	}
	// Set cfg_mpd params
	function setCfgMpdParams($values) {
		$dbh = sqlConnect();
		foreach ($values as $key => $value) {
			$query = sprintf("UPDATE cfg_mpd SET value='%s' WHERE param='%s'", $value, $key);
			$result = sqlQuery($query, $dbh);
		}
	}
	// Get cfg_mpd params
	function getCfgMpdParams($values) {
		$dbh = sqlConnect();
		$str = '';
		foreach ($values as $key) {
			$query = "SELECT param, value FROM cfg_mpd WHERE param='" . $key . "'";
			$result = sqlQuery($query, $dbh);
			$str .= sprintf("%s = \"%s\"\n", $key, $result[0]['value']);
		}
		return $str;
	}
	// Set table params: Can not be directly called as handler, but as shorthand within a handler
	function setCfgTableParams($table, $values, $prefix = '') {
		$dbh = sqlConnect();
		foreach ($values as $key => $value) {
			$param = strlen($prefix) > 0 ? str_replace($prefix, '', $key) : $key;
			$result = sqlUpdate($table, $dbh, $param, $value);
		}
	}
	// Get table params: Can not be directly called as handler, but as shorthand within a handler
	function getCfgTableParams($table, $values, $prefix = '') {
		$dbh = sqlConnect();
		$str ='';
		foreach ($values as $key) {
			$param =  strlen($prefix) > 0 ? str_replace($prefix, '', $key) : $key;
			$query = 'SELECT param, value FROM ' . $table . ' WHERE param="' . $param . '"';
			$result = sqlQuery($query, $dbh);
			if ($result) {
				$str .= sprintf("%s = \"%s\"\n", $key, $result[0]['value']);
			}
		}
		return $str;
	}

	// Settings array
	// 'requires' array of autoconfig items that should ALL be present before the handler is executed
	// 'handler'  function for setting the config item
	// 'cmd'      special arg for sysutil.sh when setSessVarSqlSysCmd handler is used
	//
	// NOTE: When adding new session-only vars also add them to util/autocfg-gen.php
	//
	$configHandlers = [
		//
		// Host and renderer names
		//
		'Names',
		['requires' => ['browsertitle'], 'handler' => 'setSessVarSql'],
		['requires' => ['hostname'], 'handler' => 'setSessVarSqlSysCmd', 'cmd' => 'chg-name host "' . $_SESSION['hostname'] . '" "%s"'],
		['requires' => ['btname'], 'handler' => 'setSessVarSqlSysCmd', 'cmd' => 'chg-name bluetooth "' . $_SESSION['btname'] . '" "%s"'],
		['requires' => ['airplayname'], 'handler' => 'setSessVarSql'],
		['requires' => ['spotifyname'], 'handler' => 'setSessVarSql'],
		['requires' => ['squeezelitename'], 'handler' => function($values) {
			$dbh = sqlConnect();
			$currentName= sqlQuery("select value from cfg_sl where param='PLAYERNAME'", $dbh)[0]['value'];
			$result = sqlQuery('update cfg_sl set value=' . "'" . $values['squeezelitename'] . "'" . ' where param=' . "'PLAYERNAME'", $dbh);
			sysCmd('/var/www/util/sysutil.sh chg-name squeezelite "' . $currentName . '" ' . '"' . $values['squeezelitename'] . '"');
		}, 'custom_write' => function($values) {
			$result = sqlQuery("select value from cfg_sl where param='PLAYERNAME'", sqlConnect());
			return "squeezelitename = \"" . $result[0]['value'] . "\"\n";
		}],
		['requires' => ['upnpname'], 'handler' => 'setSessVarSqlSysCmd', 'cmd' => 'chg-name upnp "' . $_SESSION['upnpname'] . '" "%s"'],
		['requires' => ['dlnaname'], 'handler' => 'setSessVarSqlSysCmd', 'cmd' => 'chg-name dlna "' . $_SESSION['dlnaname'] . '" "%s"'],
		//
		// System settings
		//
		'System',
		['requires' => ['updater_auto_check'], 'handler' => 'setSessVarOnly'],
		['requires' => ['timezone'], 'handler' => 'setSessVarSqlSysCmd', 'cmd' => 'set-timezone %s'],
		['requires' => ['keyboard'], 'handler' => 'setSessVarSqlSysCmd', 'cmd' => 'set-keyboard %s'],
		['requires' => ['worker_responsiveness'], 'handler' => function($values) {
			$_SESSION['worker_responsiveness'] = $values['worker_responsiveness'];
			if ($values['worker_responsiveness'] == 'Default') {
				$workerSleep = 3000000;
				$waitworkerSleep = 1000000;
			} else {
				$workerSleep = 1500000;
				$waitworkerSleep = 750000;
			}
			sysCmd('sed -i "/const WORKER_SLEEP/c\const WORKER_SLEEP = ' . $workerSleep . ';" /var/www/inc/sleep-interval.php');
			sysCmd('sed -i "/const WAITWORKER_SLEEP/c\const WAITWORKER_SLEEP = ' . $waitworkerSleep . ';" /var/www/inc/sleep-interval.php');
		}],
		['requires' => ['cpugov'], 'handler' => function($values) {
			phpSession('write', 'cpugov', $values['cpugov']);
			sysCmd('sh -c ' . "'" . 'echo "' . $values['cpugov'] . '" | tee /sys/devices/system/cpu/cpu*/cpufreq/scaling_governor' . "'");
		}],
		['requires' => ['pi_audio_driver'], 'handler' => function($values) {
			$_SESSION['pi_audio_driver'] = $values['pi_audio_driver'];
			$value = $values['pi_audio_driver'] == PI_VC4_KMS_V3D ? '' : '#';
			updBootConfigTxt('upd_pi_audio_driver', $value);
		}],
		['requires' => ['pci_express'], 'handler' => function($values) {
			$_SESSION['pci_express'] = $values['pci_express'];
			$value = $values['pci_express'];
			updBootConfigTxt('upd_pci_express', $value);
		}],
		['requires' => ['p3wifi'], 'handler' => function($values) {
			phpSession('write', 'p3wifi', $values['p3wifi']);
			$value = $values['p3wifi'] == '0' ? '' : '#';
			updBootConfigTxt('upd_disable_wifi', $value);
		}],
		['requires' => ['p3bt'], 'handler' => function($values) {
			phpSession('write', 'p3bt', $values['p3bt']);
			$value = $values['p3bt'] == '0' ? '' : '#';
			updBootConfigTxt('upd_disable_bt', $value);
		}],
		['requires' => ['led_state'], 'handler' => 'setSessVarSql'],
		['requires' => ['ipaddr_timeout'], 'handler' => 'setSessVarSql'],
		['requires' => ['eth0chk'], 'handler' => 'setSessVarSql'],
		'File Sharing',
		['requires' => ['fs_smb'], 'handler' => 'setSessVarSql'],
		['requires' => ['fs_nfs'], 'handler' => 'setSessVarSql'],
		['requires' => ['fs_nfs_access'], 'handler' => 'setSessVarSql'],
		['requires' => ['fs_nfs_options'], 'handler' => 'setSessVarSql'],
		'Peripherals',
		['requires' => ['localui'], 'handler' => 'setSessVarSql'],
		['requires' => ['wake_display'], 'handler' => 'setSessVarSql'],
		['requires' => ['touchscn'], 'handler' => function($values) {
			$_SESSION['touchscn'] = $values['touchscn'];
			$param = $values['touchscn'] == '0' ? ' -- -nocursor' : '';
			sysCmd('sed -i "/ExecStart=/c\ExecStart=/usr/bin/xinit' . $param . '" /lib/systemd/system/localui.service');
		}],
		['requires' => ['on_screen_kbd'], 'handler' => 'setSessVarOnly'],
		['requires' => ['scnblank'], 'handler' => function($values) {
			phpSession('write', 'scnblank', $values['scnblank']);
			sysCmd('sed -i "/xset s/c\xset s ' . $values['scnblank'] . '" ' . $_SESSION['home_dir'] . '/.xinitrc');
		}],
		['requires' => ['hdmi_enable_4kp60'], 'handler' => function($values) {
			$_SESSION['hdmi_enable_4kp60'] = $values['hdmi_enable_4kp60'];
			$value = $values['hdmi_enable_4kp60'] == 'on' ? '1' : '0';
			updBootConfigTxt('upd_hdmi_enable_4kp60', $value);
		}],
		['requires' => ['scnbrightness'], 'handler' => function($values) {
			phpSession('write', 'scnbrightness', $values['scnbrightness']);
			sysCmd('/bin/su -c "echo '. $values['scnbrightness'] . ' > /sys/class/backlight/rpi_backlight/brightness"');
		}],
		['requires' => ['pixel_aspect_ratio'], 'handler' => function($values) {
			phpSession('write', 'pixel_aspect_ratio', $values['pixel_aspect_ratio']);
			$value = $values['pixel_aspect_ratio'] == 'Square' ? '' : '#';
			updBootConfigTxt('upd_framebuffer_settings', $value);
		}],
		['requires' => ['scnrotate'], 'handler' => function($values) {
			phpSession('write', 'scnrotate', $values['scnrotate']);
			$value = $values['scnrotate'] == '180' ? '' : '#';
			updBootConfigTxt('upd_lcd_rotate', $value);
		}],

		['requires' => ['lcdup'], 'handler' => 'setSessVarSql'],
		'Security',
		['requires' => ['shellinabox'], 'handler' => 'setSessVarSql'],
		//
		// Networking
		//
		'Network (eth0)',
		['requires' => ['ethmethod', 'ethipaddr', 'ethnetmask', 'ethgateway', 'ethpridns', 'ethsecdns'], 'handler' => function($values) {
			$value = array('method' => $values['ethmethod'], 'ipaddr' => $values['ethipaddr'], 'netmask' => $values['ethnetmask'],
				'gateway' => $values['ethgateway'], 'pridns' => $values['ethpridns'], 'secdns' => $values['ethsecdns']);
			sqlUpdate('cfg_network', sqlConnect(), 'eth0', $value);
			cfgNetworks();
		}, 'custom_write' => function($values) {
			$result = sqlQuery("select * from cfg_network where iface='eth0'", sqlConnect());
			$str = '';
			$str .= "ethmethod = \"" . $result[0]['method'] . "\"\n";
			$str .= "ethipaddr = \"" . $result[0]['ipaddr'] . "\"\n";
			$str .= "ethnetmask = \"" . $result[0]['netmask'] . "\"\n";
			$str .= "ethgateway = \"" . $result[0]['gateway'] . "\"\n";
			$str .= "ethpridns = \"" . $result[0]['pridns'] . "\"\n";
			$str .= "ethsecdns = \"" . $result[0]['secdns'] . "\"\n";
			return $str;
		}],
		'Network (wlan0)',
		['requires' => ['wlanssid', 'wlanpwd', 'wlanuuid'],
		'optionals' => ['wlanmethod', 'wlanipaddr', 'wlannetmask', 'wlangateway', 'wlanpridns', 'wlansecdns', 'wlancountry', 'wlanpsk'],
		'handler' => function($values, $optionals) {
			$dbh = sqlConnect();
			$psk = (key_exists('wlanpsk', $optionals) && !empty($optionals['wlanpsk'])) ? $optionals['wlanpsk'] :
				genWpaPSK($values['wlanssid'], $values['wlanpwd']);
			$cfgNetwork = sqlQuery('select * from cfg_network', $dbh);
			$value = array('method' => $cfgNetwork[1]['method'], 'ipaddr' => $cfgNetwork[1]['ipaddr'],
				'netmask' => $cfgNetwork[1]['netmask'],	'gateway' => $cfgNetwork[1]['gateway'],
				'pridns' => $cfgNetwork[1]['pridns'], 'secdns' => $cfgNetwork[1]['secdns'],
				'wlanssid' => $values['wlanssid'], 'wlanuuid' => $values['wlanuuid'], 'wlanpwd' => $psk,
				'wlanpsk' => $psk, 'wlancc' =>  $cfgNetwork[1]['wlancc']);
			if (key_exists('wlanmethod', $optionals)) {$value['method'] = $optionals['wlanmethod'];}
			if (key_exists('wlanipaddr', $optionals)) {$value['ipaddr'] = $optionals['wlanipaddr'];}
			if (key_exists('wlannetmask', $optionals)) {$value['netmask'] = $optionals['wlannetmask'];}
			if (key_exists('wlangateway', $optionals)) {$value['gateway'] = $optionals['wlangateway'];}
			if (key_exists('wlanpridns', $optionals)) {$value['pridns'] = $optionals['wlanpridns'];}
			if (key_exists('wlansecdns', $optionals)) {$value['secdns'] = $optionals['wlansecdns'];}
			if (key_exists('wlancountry', $optionals)) {$value['wlancc'] = $optionals['wlancountry'];}
			sqlUpdate('cfg_network', $dbh, 'wlan0', $value);
			cfgNetworks();
		},
		'custom_write' => function($values) {
			$result = sqlQuery("select * from cfg_network where iface='wlan0'", sqlConnect());
			$str = '';
			$str .= "wlanmethod = \"" . $result[0]['method'] . "\"\n";
			$str .= "wlanipaddr = \"" . $result[0]['ipaddr'] . "\"\n";
			$str .= "wlannetmask = \"" . $result[0]['netmask'] . "\"\n";
			$str .= "wlangateway = \"" . $result[0]['gateway'] . "\"\n";
			$str .= "wlanpridns = \"" . $result[0]['pridns'] . "\"\n";
			$str .= "wlansecdns = \"" . $result[0]['secdns'] . "\"\n";
			$str .= "wlanssid = \"" . $result[0]['wlanssid'] . "\"\n";
			$str .= "wlanuuid = \"" . $result[0]['wlanuuid'] . "\"\n";
			$str .= "wlanpwd = \"" . "" . "\"\n"; // Keep empty
			$str .= "wlanpsk = \"" . $result[0]['wlanpsk'] . "\"\n";
			$str .= "wlancountry = \"" . $result[0]['wlancc'] . "\"\n";
			return $str;
		}],
		['requires' => ['ssid_ssid', 'ssid_uuid', 'ssid_psk'],
		'handler' => function($values) {
			$dbh = sqlConnect();
			sqlDelete('cfg_ssid', $dbh);
			$count = count($values['ssid_ssid']);
			for ($i = 0; $i < $count; $i++) {
				$value = "\"" .
					$values['ssid_ssid'][$i] . "\", \"" .
					$values['ssid_uuid'][$i] . "\", \"" .
					$values['ssid_psk'][$i] . "\", " .
					// method, ipaddr, netmask, gateway, pridns, secdns
					"\"\", \"\", \"\", \"\", \"\", \"\"";
				sqlInsert('cfg_ssid', $dbh, $value);
			}
			cfgNetworks();
		}, 'custom_write' => function($values) {
			$result = sqlRead('cfg_ssid', sqlConnect());
			$format = "ssid_%s[%d] = \"%s\"\n";
			$str = '';
			foreach ($result as $i => $row) {
				$str .= sprintf($format, 'ssid', $i, $row['ssid']);
				$str .= sprintf($format, 'uuid', $i, $row['uuid']);
				$str .= sprintf($format, 'psk', $i, $row['psk']);
				// TODO: Add method, ipaddr, netmask, gateway, pridns, secdns
			}
			return $str;
		}],
		'Network (apd0)',
		['requires' => ['apdssid', 'apdpwd', 'apduuid', 'apdaddr'],
		'optionals' => ['apdpsk'],
		'handler' => function($values, $optionals) {
			$dbh = sqlConnect();
			$psk = (key_exists('apdpsk', $optionals) && !empty($optionals['apdpsk'])) ? $optionals['apdpsk'] :
				genWpaPSK($values['apdssid'], $values['apdpwd']);
			$value = array('method' => '', 'ipaddr' => '', 'netmask' => '', 'gateway' => '', 'pridns' => '', 'secdns' => '',
				'wlanssid' => $values['apdssid'], 'wlanuuid' => $values['apduuid'], 'wlanpwd' => $psk, 'wlanpsk' =>  $psk,
				'wlancc' => '');
			sqlUpdate('cfg_network', $dbh, 'apd0', $value);
			sqlUpdate('cfg_system', $dbh, 'ap_network_addr', $values['apdaddr']);
			cfgNetworks();
		}, 'custom_write' => function($values) {
			$result = sqlQuery("select * from cfg_network where iface='apd0'", sqlConnect());
			$str = '';
			$str .= "apdssid = \"" . $result[0]['wlanssid'] . "\"\n";
			$str .= "apdpwd = \"" . "" . "\"\n"; // Keep empty
			$str .= "apduuid = \"" . $result[0]['wlanuuid'] . "\"\n";
			$str .= "apdpsk = \"" . $result[0]['wlanpsk'] . "\"\n";
			$result = sqlQuery("SELECT value FROM cfg_system WHERE param='ap_network_addr'", sqlConnect());
			$str .= "apdaddr = \"" . $result[0]['value'] . "\"\n";
			return $str;
		}],
		//
		// Audio config
		//
		'Audio Device',
		['requires' => ['adevname'], 'handler' => 'setSessVarSql'],
		['requires' => ['mpdmixer'], 'handler' => 'setSessVarSql'],
		['requires' => ['i2soverlay'], 'handler' => 'setSessVarSql'],
		['requires' => ['i2sdevice'], 'handler' => function($values) {
			phpSession('write', 'i2sdevice', $values['i2sdevice']);
			// NOTE: Passing arg = 'autocfg' prevents reset to PI_HDMI1 in cfgI2SDevice()
			$arg = ($values['i2soverlay'] == 'None' && $values['i2sdevice'] == 'None') ? 'autocfg' : '';
			cfgI2SDevice($arg);
		}],
		'ALSA',
		['requires' => ['cardnum'], 'handler' => 'setSessVarSql'],
		['requires' => ['amixname'], 'handler' => 'setSessVarSql'],
		['requires' => ['alsa_output_mode'], 'handler' => 'setSessVarSql'],
		['requires' => ['alsavolume_max'], 'handler' => 'setSessVarSql'],
		['requires' => ['alsa_loopback'], 'handler' => function($values) {
			phpSession('write', 'alsa_loopback', $values['alsa_loopback']);
			$values['alsa_loopback'] == 'On' ? sysCmd("sed -i '0,/_audioout__ {/s//_audioout {/' /etc/alsa/conf.d/_sndaloop.conf") :
				sysCmd("sed -i '0,/_audioout {/s//_audioout__ {/' /etc/alsa/conf.d/_sndaloop.conf");
		}],
		'MPD',
		['requires' => ['device'], 'handler' => 'setCfgMpdParams', 'custom_write' => 'getCfgMpdParams'],
		['requires' => ['mixer_type'], 'handler' => 'setCfgMpdParams', 'custom_write' => 'getCfgMpdParams'],
		['requires' => ['audio_output_format'], 'handler' => 'setCfgMpdParams', 'custom_write' => 'getCfgMpdParams'],
		['requires' => ['selective_resample_mode'], 'handler' => 'setCfgMpdParams', 'custom_write' => 'getCfgMpdParams'],
		['requires' => ['sox_quality'], 'handler' => 'setCfgMpdParams', 'custom_write' => 'getCfgMpdParams'],
		['requires' => ['sox_multithreading'], 'handler' => 'setCfgMpdParams', 'custom_write' => 'getCfgMpdParams'],
		['requires' => ['sox_precision', 'sox_phase_response', 'sox_passband_end', 'sox_stopband_begin', 'sox_attenuation', 'sox_flags'],
			'handler' => 'setCfgMpdParams', 'custom_write' => 'getCfgMpdParams'],
		['requires' => ['dop'], 'handler' => 'setCfgMpdParams', 'custom_write' => 'getCfgMpdParams'],
		['requires' => ['replaygain'], 'handler' => 'setCfgMpdParams', 'custom_write' => 'getCfgMpdParams'],
		['requires' => ['replaygain_preamp'], 'handler' => 'setCfgMpdParams', 'custom_write' => 'getCfgMpdParams'],
		['requires' => ['volume_normalization'], 'handler' => 'setCfgMpdParams', 'custom_write' => 'getCfgMpdParams'],
		['requires' => ['audio_buffer_size'], 'handler' => 'setCfgMpdParams', 'custom_write' => 'getCfgMpdParams'],
		['requires' => ['max_output_buffer_size'], 'handler' => 'setCfgMpdParams', 'custom_write' => 'getCfgMpdParams'],
		['requires' => ['max_playlist_length'], 'handler' => 'setCfgMpdParams', 'custom_write' => 'getCfgMpdParams'],
		['requires' => ['input_cache'], 'handler' => 'setCfgMpdParams', 'custom_write' => 'getCfgMpdParams'],
		['requires' => ['log_level'], 'handler' => 'setCfgMpdParams', 'custom_write' => 'getCfgMpdParams'],
		['requires' => ['stop_dsd_silence'], 'handler' => 'setCfgMpdParams', 'custom_write' => 'getCfgMpdParams'],
		['requires' => ['thesycon_dsd_workaround'], 'handler' => 'setCfgMpdParams', 'custom_write' => 'getCfgMpdParams'],
		'MPD Options',
		['requires' => ['autoplay'], 'handler' => 'setSessVarSql'],
		['requires' => ['mpdcrossfade'], 'handler' => 'setSessVarSql'],
		['requires' => ['crossfeed'], 'handler' => 'setSessVarSql'],
		['requires' => ['invert_polarity'], 'handler' => 'setSessVarSql'],
		['requires' => ['volume_step_limit'], 'handler' => 'setSessVarSql'],
		['requires' => ['volume_mpd_max'], 'handler' => 'setSessVarSql'],
		['requires' => ['volume_db_display'], 'handler' => 'setSessVarSql'],
		['requires' => ['ashufflesvc'], 'handler' => 'setSessVarSql'],
		['requires' => ['ashuffle_mode'], 'handler' => 'setSessVarOnly'],
		['requires' => ['ashuffle_window'], 'handler' => 'setSessVarOnly'],
		['requires' => ['ashuffle_filter'], 'handler' => 'setSessVarOnly'],
		['requires' => ['mpd_httpd'], 'handler' => function($values) {
			$cmd = $values['mpd_httpd'] == '1' ? 'mpc enable "' . HTTP_SERVER . '"' : 'mpc disable "' . HTTP_SERVER . '"';
			sysCmd($cmd);
			phpSession('write', 'mpd_httpd', $values['mpd_httpd']);
		}],
		['requires' => ['mpd_httpd_port'], 'handler' => 'setSessVarSql'],
		['requires' => ['mpd_httpd_encoder'], 'handler' => 'setSessVarSql'],
		//
		// DSP and Equalizers
		//
		'CamillaDSP',
		['requires' => ['camilladsp'], 'handler' => 'setSessVarSql'],
		['requires' => ['camilladsp_volume_sync'], 'handler' => 'setSessVarSql'],
		['requires' => ['camilladsp_quickconv'], 'handler' => 'setSessVarSql'],
		['requires' => ['cdsp_fix_playback'], 'handler' => 'setSessVarSql'],
		'Parametric EQ',
		['requires' => ['eqfa12p'], 'handler' => 'setSessVarSql'],
		['requires' => ['eqp12_curve_name', 'eqp12_settings', 'eqp12_active'], 'handler' => function($values) {
			require_once __DIR__ . '/eqp.php';
			$eqp = Eqp12(sqlConnect());
			$eqp->import($values);
		}, 'custom_write' => function($values) {
			require_once __DIR__ . '/eqp.php';
			$eqp = Eqp12(sqlConnect());
			$str = $eqp->export();
			return $str;
		}],
		'Graphic EQ',
		['requires' => ['alsaequal'], 'handler' => 'setSessVarSql'],
		['requires' => ['eqg_curve_name', 'eqg_curve_values'], 'handler' => function($values) {
			$dbh = sqlConnect();
			$result = sqlQuery('delete from cfg_eqalsa', $dbh);
			$count = count($values['eqg_curve_name']);
			for ($i = 0; $i < $count; $i++) {
				$curveName = $values['eqg_curve_name'][$i];
				$curveValues = $values['eqg_curve_values'][$i];
				$query ="INSERT INTO cfg_eqalsa (curve_name, curve_values) VALUES ('" . $curveName . "', '" . $curveValues . "')";
				$result = sqlQuery($query, $dbh);
			}
		}, 'custom_write' => function($values) {
			$result = sqlRead('cfg_eqalsa', sqlConnect());
			$format = "eqg_%s[%d] = \"%s\"\n";
			$str = '';
			foreach ($result as $i => $row) {
				$str .= sprintf($format, 'curve_name', $i, $row['curve_name']);
				$str .= sprintf($format, 'curve_values', $i, $row['curve_values']);
			}
			return $str;
		}],
		//
		// Audio renderers
		//
		'Renderers',
		['requires' => ['btsvc'], 'handler' => 'setSessVarSql'],
		['requires' => ['rsmafterbt'], 'handler' => 'setSessVarSql'],
		['requires' => ['airplaysvc'], 'handler' => 'setSessVarSql'],
		['requires' => ['rsmafterapl'], 'handler' => 'setSessVarSql'],
		['requires' => ['spotifysvc'], 'handler' => 'setSessVarSql'],
		['requires' => ['rsmafterspot'], 'handler' => 'setSessVarSql'],
		['requires' => ['slsvc'], 'handler' => 'setSessVarSql'],
		['requires' => ['rsmaftersl'], 'handler' => 'setSessVarSql'],
		['requires' => ['pasvc'], 'handler' => 'setSessVarSql'],
		['requires' => ['rsmafterpa'], 'handler' => 'setSessVarSql'],
		['requires' => ['rbsvc'], 'handler' => 'setSessVarSql'],
		['requires' => ['rsmafterrb'], 'handler' => 'setSessVarSql'],
		'Bluetooth',
		['requires' => ['bt_pin_code'], 'handler' => 'setSessVarOnly'],
		['requires' => ['alsavolume_max_bt'], 'handler' => 'setSessVarOnly'],
		['requires' => ['cdspvolume_max_bt'], 'handler' => 'setSessVarOnly'],
		['requires' => ['bluez_pcm_buffer'], 'handler' => function($values) {
			phpSession('write', 'bluez_pcm_buffer', $values['bluez_pcm_buffer']);
			sysCmd("sed -i '/BUFFERTIME/c\BUFFERTIME=" . $values['bluez_pcm_buffer'] . "' /etc/bluealsaaplay.conf");
		}],
		['requires' => ['audioout'], 'handler' => 'setSessVarSql'],
		['requires' => ['alsa_output_mode_bt'], 'handler' => 'setSessVarOnly'],
		'AirPlay',
		['requires' => ['airplay_interpolation', 'airplay_output_format', 'airplay_output_rate', 'airplay_allow_session_interruption',
			'airplay_session_timeout', 'airplay_audio_backend_latency_offset_in_seconds', 'airplay_audio_backend_buffer_desired_length_in_seconds'],
			'handler' => function($values) {
				setCfgTableParams('cfg_airplay', $values, 'airplay_');
			}, 'custom_write' => function($values) {
				return getCfgTableParams('cfg_airplay', $values, 'airplay_');
		}],
		'Spotify Connect',
		['requires' => ['spotify_bitrate', 'spotify_initial_volume', 'spotify_volume_curve', 'spotify_volume_normalization', 'spotify_normalization_pregain',
			'spotify_autoplay'],
			'optionals' => ['spotify_normalization_method', 'spotify_normalization_gain_type', 'spotify_normalization_threshold','spotify_normalization_attack',
			'spotify_normalization_release', 'spotify_normalization_knee', 'spotify_format', 'spotify_dither', 'spotify_volume_range'],
			'handler' => function($values, $optionals) {
				$mergedValues = array_merge($values, $optionals);
				setCfgTableParams('cfg_spotify', $mergedValues, 'spotify_');
			}, 'custom_write' => function($values) {
				return getCfgTableParams('cfg_spotify', $values, 'spotify_');
		}],
		'Squeezelite',
		['requires' => ['squeezelite_PLAYERNAME', 'squeezelite_AUDIODEVICE', 'squeezelite_ALSAPARAMS', 'squeezelite_OUTPUTBUFFERS',
			'squeezelite_TASKPRIORITY', 'squeezelite_CODECS', 'squeezelite_OTHEROPTIONS'],
			'handler' => function($values) {
				setCfgTableParams('cfg_sl', $values, 'squeezelite_');
			}, 'custom_write' => function($values) {
				return getCfgTableParams('cfg_sl', $values, 'squeezelite_');
		}],
		'UPnP/DLNA',
		['requires' => ['upnpsvc'], 'handler' => 'setSessVarSql'],
		['requires' => ['dlnasvc'], 'handler' => 'setSessVarSql'],
		['requires' => ['upnpav'], 'handler' => function($values) {
			setCfgTableParams('cfg_upnp', $values);
		}, 'custom_write' => function($values) {
			return getCfgTableParams('cfg_upnp', $values);
		}],
		['requires' => ['openhome'], 'handler' => function($values) {
			setCfgTableParams('cfg_upnp', $values);
		}, 'custom_write' => function($values) {
			return getCfgTableParams('cfg_upnp', $values);
		}],
		['requires' => ['checkcontentformat'], 'handler' => function($values) {
			setCfgTableParams('cfg_upnp', $values);
		}, 'custom_write' => function($values) {
			return getCfgTableParams('cfg_upnp', $values);
		}],
		['requires' => ['qobuzuser'], 'handler' => function($values) {
			setCfgTableParams('cfg_upnp', $values);
		}, 'custom_write' => function($values) {
			return getCfgTableParams('cfg_upnp', $values);
		}],
		['requires' => ['qobuzpass'], 'handler' => function($values) {
			setCfgTableParams('cfg_upnp', $values);
		}, 'custom_write' => function($values) {
			return getCfgTableParams('cfg_upnp', $values);
		}],
		['requires' => ['qobuzformatid'], 'handler' => function($values) {
			setCfgTableParams('cfg_upnp', $values);
		}, 'custom_write' => function($values) {
			return getCfgTableParams('cfg_upnp', $values);
		}],
		//
		// Preferences
		//
		'Appearance',
		['requires' => ['themename'], 'handler' => 'setSessVarSql'],
		['requires' => ['accent_color'], 'handler' => 'setSessVarSql'],
		['requires' => ['alphablend'], 'handler' => 'setSessVarSql'],
		['requires' => ['adaptive'], 'handler' => 'setSessVarSql'],
		['requires' => ['cover_backdrop'], 'handler' => 'setSessVarSql'],
		['requires' => ['cover_blur'], 'handler' => 'setSessVarSql'],
		['requires' => ['cover_scale'], 'handler' => 'setSessVarSql'],
		['requires' => ['font_size'], 'handler' => 'setSessVarSql'],
		'Playback',
		['requires' => ['playlist_art'], 'handler' => 'setSessVarSql'],
		['requires' => ['extra_tags'], 'handler' => 'setSessVarSql'],
		['requires' => ['playhist'], 'handler' => 'setSessVarSql'],
		['requires' => ['show_npicon'], 'handler' => function($values) {
			if ($values['show_npicon'] == 'Yes') {
				$value = 'Waveform';
			} else if ($values['show_npicon'] == 'No') {
				$value = 'None';
			} else {
				$value = $values['show_npicon'];
			}
			phpSession('write', 'show_npicon', $value);
		}],
		['requires' => ['show_cvpb'], 'handler' => 'setSessVarSql'],
		'Library',
		['requires' => ['library_onetouch_album'], 'handler' => 'setSessVarSql'],
		['requires' => ['library_onetouch_radio'], 'handler' => 'setSessVarSql'],
		['requires' => ['library_onetouch_pl'], 'handler' => 'setSessVarSql'],
		['requires' => ['library_albumview_sort'], 'handler' => 'setSessVarSql'],
		['requires' => ['library_tagview_sort'], 'handler' => 'setSessVarSql'],
		['requires' => ['library_track_play'], 'handler' => 'setSessVarSql'],
		['requires' => ['library_recently_added'], 'handler' => 'setSessVarSql'],
		['requires' => ['library_encoded_at'], 'handler' => 'setSessVarSql'],
		['requires' => ['library_covsearchpri'], 'handler' => 'setSessVarSql'],
		['requires' => ['library_thmgen_scan'], 'handler' => 'setSessVarSql'],
		['requires' => ['library_hiresthm'], 'handler' => 'setSessVarSql'],
		['requires' => ['library_thumbnail_columns'], 'handler' => 'setSessVarSql'],
		'Library (Advanced)',
		['requires' => ['library_tagview_genre'], 'handler' => function($values) {
			$value = $values['library_tagview_genre'] == 'Genres' ? 'Genre' :
				($values['library_tagview_genre'] == 'Composers' ? 'Composer' : $values['library_tagview_genre']);
			phpSession('write', 'library_tagview_genre', $value);
		}],
		//['requires' => ['library_tagview_genre'], 'handler' => 'setSessVarSql'],
		['requires' => ['library_tagview_artist'], 'handler' => 'setSessVarSql'],
		['requires' => ['library_misc_options'], 'handler' => 'setSessVarSql'],
		['requires' => ['library_ignore_articles'], 'handler' => 'setSessVarSql'],
		['requires' => ['library_show_genres'], 'handler' => 'setSessVarSql'],
		['requires' => ['library_tagview_covers'], 'handler' => 'setSessVarSql'],
		['requires' => ['library_ellipsis_limited_text'], 'handler' => 'setSessVarSql'],
		['requires' => ['library_utf8rep'], 'handler' => 'setSessVarSql'],
		'CoverView',
		['requires' => ['scnsaver_timeout'], 'handler' => 'setSessVarSql'],
		['requires' => ['auto_coverview'], 'handler' => 'setSessVarSql'],
		['requires' => ['scnsaver_style'], 'handler' => 'setSessVarSql'],
		['requires' => ['scnsaver_mode'], 'handler' => 'setSessVarSql'],
		['requires' => ['scnsaver_layout'], 'handler' => 'setSessVarSql'],
		['requires' => ['scnsaver_xmeta'], 'handler' => 'setSessVarSql'],
		//
		// Multiroom
		//
		'Multiroom',
		['requires' => ['multiroom_tx'], 'handler' => 'setSessVarSql'],
		['requires' => ['multiroom_rx'], 'handler' => 'setSessVarSql'],
		['requires' => ['multiroom_tx_bfr', 'multiroom_tx_host', 'multiroom_tx_port', 'multiroom_tx_sample_rate', 'multiroom_tx_channels', 'multiroom_tx_frame_size', 'multiroom_tx_bitrate',
			'multiroom_rx_bfr', 'multiroom_rx_host', 'multiroom_rx_port',  'multiroom_rx_jitter_bfr', 'multiroom_rx_sample_rate', 'multiroom_rx_channels', 'multiroom_initial_volume'],
         'optionals' => ['multiroom_tx_rtprio', 'multiroom_tx_query_timeout', 'multiroom_rx_frame_size', 'multiroom_rx_rtprio', 'multiroom_rx_alsa_output_mode', 'multiroom_rx_mastervol_opt_in'],
			'handler' => function($values, $optionals) {
				$mergedValues = array_merge($values, $optionals);
				setCfgTableParams('cfg_multiroom', $mergedValues, 'multiroom_');
			}, 'custom_write' => function($values) {
				return getCfgTableParams('cfg_multiroom', $values, 'multiroom_');
		}],
		//
		// Miscellaneous
		//
		'GPIO Buttons',
		['requires' => ['gpio_svc'], 'handler' => 'setSessVarSql'],
		['requires' => ['gpio_button'], 'handler' => function($values) {
			$dbh = sqlConnect();
			// Buttons: id 1 - 8
			for ($i = 1; $i <= 8; $i++) {
				$val = explode('|', $values['gpio_button'][$i]);
				$query = "update cfg_gpio set " .
					"pin='" . $val[0] . "'," .
					"enabled='" . $val[1] . "'," .
					"command='" . $val[2] . "'," .
					"param='" . $val[3] . "'," .
					"value='" . $val[4] . "' " .
					"where id='" . $i . "'";
				$result = sqlQuery($query, $dbh);
			}
			// Bounce time: id 99
			$val = explode('|', $values['gpio_button'][99]);
			$query = "update cfg_gpio set " .
				"param='" . $val[3] . "'," .
				"value='" . $val[4] . "' " .
				"where id='99'";
			$result = sqlQuery($query, $dbh);
		}, 'custom_write' => function($values) {
			$result = sqlRead('cfg_gpio', sqlConnect());
			$format = "gpio_button[%s] = \"%s|%s|%s|%s|%s\"\n";
			$str = '';
			foreach ($result as $row) {
				$str .= sprintf($format, $row['id'], $row['pin'], $row['enabled'], $row['command'], $row['param'], $row['value']);
			}
			return $str;
		}],
		'Music Sources',
		['requires' => ['fs_mountmon'], 'handler' => 'setSessVarOnly'],
		['requires' => ['usb_auto_updatedb'], 'handler' => 'setSessVarSql'],
		['requires' => ['cuefiles_ignore'], 'handler' => 'setSessVarSql'],
		// Sources are using the array construction of the ini reader
		// source_name[0] = ...
		['requires' => ['source_name', 'source_type', 'source_address', 'source_remotedir', 'source_username', 'source_password',
			'source_charset', 'source_rsize', 'source_wsize', 'source_wsize', 'source_options'], 'handler' => function($values) {
			// Remove existing mounts
			sqlDelete('cfg_source', sqlConnect());
			// Add new ones from ini file
			$count = count($values['source_name']);
			$keys = array_keys($values);
			for ($i = 0; $i < $count; $i++) {
				$mount = ['mount' => ['action' => 'add']];
				foreach ($keys as $key) {
					$mount['mount'][substr($key, 7)] = $values[$key][$i];
				}
				sourceCfg($mount);
			}
		}, 'custom_write' => function($values) {
			$result = sqlRead('cfg_source', sqlConnect());
			$format = "source_%s[%d] = \"%s\"\n";
			$str = '';
			foreach ($result as $i => $mp) {
				$str .= sprintf($format, 'name', $i, $mp['name']);
				$str .= sprintf($format, 'type', $i, $mp['type']);
				$str .= sprintf($format, 'address', $i, $mp['address']);
				$str .= sprintf($format, 'remotedir', $i, $mp['remotedir']);
				$str .= sprintf($format, 'username', $i, $mp['username']);
				$str .= sprintf($format, 'password', $i, $mp['password']);
				$str .= sprintf($format, 'charset', $i, $mp['charset']);
				$str .= sprintf($format, 'rsize', $i, $mp['rsize']);
				$str .= sprintf($format, 'wsize', $i, $mp['wsize']);
				$str .= sprintf($format, 'options', $i, $mp['options']);
			}
			return $str;
		}]
	];

	return $configHandlers;
}

//
// Import and apply settings from auto-config file
//
function autoConfig($cfgFile) {
	autoCfgLog('autocfg: Auto-configure initiated');

	try {
		$autoCfgIni = parse_ini_file($cfgFile, false);
		$availableConfigs = array_keys($autoCfgIni);
		autoCfgLog('autocfg: Configuration file parsed');

		$configHandlers = autoConfigSettings();
		foreach ($configHandlers as $config) {
			$requires = array();
			$optionals = array();

			if (is_string($config)) {
				// Print new section header
				autoCfgLog('autocfg: - ' . $config);
			} else if (!array_diff_key(array_flip($config['requires']), $autoCfgIni)) {
				// Check if all required keys are present
				// Create dict key/value of required settings
				$requires = array_intersect_key($autoCfgIni, array_flip($config['requires']));
				// Create dict key/value of optionals that are present
				$optionals = array_key_exists('optionals', $config) ?
					array_intersect_key($autoCfgIni, array_flip($config['optionals'])) : [];
				$mergedKeys = array_merge($config['requires'], (array_key_exists('optionals', $config) ?
					array_keys($optionals) : []));
				// Copy all key/value sets
				foreach ($mergedKeys as $configName) {
					$value = $autoCfgIni[$configName];
					autoCfgLog('autocfg: ' . $configName . ': ' . $value);
					// Remove used autoconfig
					unset($availableConfigs[$configName]);
					unset($autoCfgIni[$configName]);
				}
				// Call handler
				if (array_key_exists('cmd', $config)) {
					$config['handler'] ($requires, $config['cmd']);
				} else {
					if (array_key_exists( 'optionals', $config)) {
						$config['handler'] ($requires, $optionals);
					} else {
						$config['handler'] ($requires);
					}
				}
			}
			// Detect requires with multiple keys which are not all present
			else if (count($config['requires']) >= 2 && count(array_diff_key(array_flip($config['requires']), $autoCfgIni)) >= 1) {
				$incompleteSet = " [ ";
				foreach ($config['requires'] as $configRequires) {
					$incompleteSet = $incompleteSet . " ". $configRequires;
				}
				$incompleteSet = $incompleteSet . " ]";
				autoCfgLog('autocfg: Warning incomplete set ' . $incompleteSet . ' detected.');
			}
		}

		$scriptKey = 'script';
		if (array_key_exists($scriptKey, $autoCfgIni)) {
			autoCfgLog('autocfg: ' . $scriptKey . ':' . $script);
			$script = $autoCfgIni[$scriptKey];

			if (file_exists($script)) {
				$output = sysCmd($script);
				foreach ($output as $line) {
					autoCfgLog($line);
				}
			} else {
				autoCfgLog('autocfg: Error script not found!');
			}
			unset($autoCfgIni[$scriptKey]);
		}

		// Check for unused but supplied autocfg settings
		if (empty($availableConfigs)) {
			foreach ($availableConfigs as $configName) {
				autoCfgLog('autocfg: Warning: ' . $configName . ': is unused, incomplete or wrong name.');
			}
		}
	}
	catch (Exception $e) {
		autoCfgLog('autocfg: Caught exception: ' . $e->getMessage());
	}

 	sysCmd('rm ' . $cfgFile);
	autoCfgLog('autocfg: Configuration file deleted');
	autoCfgLog('autocfg: Auto-configure complete');
}

//
// Generates an auto-config file containing the current settings
//
function autoConfigExtract($currentSettings) {
	$autoConfigString = <<<EOT
	; ###############################################
	; Copy this file to /boot/moodecfg.ini
	;
	; It will be applied during startup followed by
	; an automatic reboot to finalize the settings.
	;
	; Created: %s
	; Release: %s
	;
	; ###############################################

	EOT;

	$autoConfigString = sprintf($autoConfigString, date('Y-m-d H:i:s'), getMoodeRel('verbose'));

	$configHandlers = autoConfigSettings();
	foreach ($configHandlers as &$config) {
		// Print new section header
		if (is_string($config)) {
			$autoConfigString = $autoConfigString . "\n[" . $config. "]\n";
		} else {
			if (!array_key_exists('custom_write', $config)) {
				foreach ($config['requires'] as $configName) {
					$configKey = array_key_exists('session_var', $config) ? $config['session_var'] : $configName;
					if (array_key_exists($configKey, $currentSettings)) {
						if ($configKey == 'ashuffle_filter') {
							$currentSettings[$configKey] = str_replace('"', '\"', $currentSettings[$configKey]);
						}
						$autoConfigString = $autoConfigString . $configKey . " = \"" . $currentSettings[$configKey] . "\"\n";
					}
				}
			} else {
				$autoConfigString = $autoConfigString . $config['custom_write'] (
					array_merge($config['requires'], array_key_exists('optionals', $config) ? $config['optionals'] : [])
				);
			}
		}
	}

	return $autoConfigString . "\n";
}
