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

/**
 * Contains all functionality related the auto-configure settings implementation.
 * (C) 2020 @bitlab (@bitkeeper Git)
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
	// Helper functions
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
			$query = sprintf("update cfg_mpd set value='%s' where param='%s'", $value, $key);
			$result = sqlQuery($query, $dbh);
		}
	}
	// Get cfg_mpd params
	function getCfgMpdParams($values) {
		$dbh = sqlConnect();
		$str = '';
		foreach ($values as $key) {
			$query = "select param, value from cfg_mpd where param='" . $key . "'";
			$result = sqlQuery($query, $dbh);
			$str .= sprintf("%s = \"%s\"\n", $key, $result[0]['value']);
		}
		return $str;
	}
	// Set table params: Can not be directly called as handler, but as shorthand within handler
	function setCfgTableParams($table, $values, $prefix = '') {
		$dbh = sqlConnect();
		foreach ($values as $key => $value) {
			$param =  strlen($prefix) > 0 ? str_replace($prefix, '', $key) : $key ;
			$result = sqlUpdate($table, $dbh, $param, $value);
		}
	}
	// Get table params: Can not be directly called as handler, but as shorthand with in handler
	function getCfgTableParams($table, $values, $prefix = '') {
		$dbh = sqlConnect();
		$str ='';
		foreach ($values as $key) {
			$param =  strlen($prefix) > 0 ? str_replace($prefix, '', $key) : $key ;
			$query = 'select param,value from '. $table .' where param="' . $param . '"';
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
	$configurationHandlers = [
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
		['requires' => ['usb_auto_mounter'], 'handler' => function($values) {
			phpSession('write', 'usb_auto_mounter', $values['usb_auto_mounter']);
			if ($values['usb_auto_mounter'] == 'udisks-glue') {
				sysCmd('sed -e "/udisks-glue/ s/^#*//" -i /etc/rc.local');
				sysCmd('sed -e "/devmon/ s/^#*/#/" -i /etc/rc.local');
				sysCmd('systemctl enable udisks');
				sysCmd('systemctl disable udisks2');
			} else if ($values['usb_auto_mounter'] == 'devmon') {
				sysCmd('sed -e "/udisks-glue/ s/^#*/#/" -i /etc/rc.local');
				sysCmd('sed -e "/devmon/ s/^#*//" -i /etc/rc.local');
				sysCmd('systemctl disable udisks');
				sysCmd('systemctl enable udisks2');
			}
		}],
		['requires' => ['p3wifi'], 'handler' => function($values) {
			ctlWifi($values['p3wifi']);
			phpSession('write', 'p3wifi', $values['p3wifi']);
		}],
		['requires' => ['p3bt'], 'handler' => function($values) {
			ctlBt($values['p3bt']);
			phpSession('write', 'p3bt', $values['p3bt']);
		}],
		['requires' => ['led_state'], 'handler' => 'setSessVarSql'],
		['requires' => ['ipaddr_timeout'], 'handler' => 'setSessVarSql'],
		['requires' => ['eth0chk'], 'handler' => 'setSessVarSql'],

		'Local Display',
		['requires' => ['localui'], 'handler' => 'setSessVarSql'],

		'File Sharing',
		['requires' => ['fs_smb'], 'handler' => 'setSessVarSql'],
		['requires' => ['fs_nfs'], 'handler' => 'setSessVarSql'],
		['requires' => ['fs_nfs_access'], 'handler' => 'setSessVarSql'],
		['requires' => ['fs_nfs_options'], 'handler' => 'setSessVarSql'],
		['requires' => ['lcdup'], 'handler' => 'setSessVarSql'],

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

		'Security',
		['requires' => ['shellinabox'], 'handler' => 'setSessVarSql'],

		'I2S Device',
		['requires' => ['i2soverlay'], 'handler' => function($values) {
			$value = $values['i2soverlay'] == 'none' ? 'None': $values['i2soverlay'];
			phpSession('write', 'i2soverlay', $value);
		}],
		['requires' => ['i2sdevice'], 'handler' => function($values) {
			$value = $values['i2sdevice'] == 'none' ? 'None': $values['i2sdevice'];
			phpSession('write', 'i2sdevice', $value);
			cfgI2SDevice();
		}],

		'ALSA',
		['requires' => ['alsa_output_mode'], 'handler' => 'setSessVarSql'],
		['requires' => ['alsa_loopback'], 'handler' => function($values) {
			phpSession('write', 'alsa_loopback', $values['alsa_loopback']);
			$values['alsa_loopback'] == 'On' ? sysCmd("sed -i '0,/_audioout__ {/s//_audioout {/' /etc/alsa/conf.d/_sndaloop.conf") :
				sysCmd("sed -i '0,/_audioout {/s//_audioout__ {/' /etc/alsa/conf.d/_sndaloop.conf");
		}],

		'Multiroom',
		['requires' => ['multiroom_tx'], 'handler' => 'setSessVarSql'],
		['requires' => ['multiroom_rx'], 'handler' => 'setSessVarSql'],
		['requires' => ['multiroom_tx_bfr', 'multiroom_tx_host', 'multiroom_tx_port', 'multiroom_tx_sample_rate', 'multiroom_tx_channels', 'multiroom_tx_frame_size', 'multiroom_tx_bitrate',
					    'multiroom_rx_bfr', 'multiroom_rx_host', 'multiroom_rx_port',  'multiroom_rx_jitter_bfr', 'multiroom_rx_sample_rate', 'multiroom_rx_channels', 'multiroom_initial_volume'],
         'optionals' => ['multiroom_tx_rtprio', 'multiroom_tx_query_timeout', 'multiroom_rx_frame_size', 'multiroom_rx_rtprio', 'multiroom_rx_alsa_output_mode', 'multiroom_rx_mastervol_opt_in', 'multiroom_initial_volume'],
			'handler' => function($values, $optionals) {
				$mergedValues = array_merge($values, $optionals);
				setCfgTableParams('cfg_multiroom', $mergedValues, 'multiroom_');
			}, 'custom_write' => function($values) {
				return getCfgTableParams('cfg_multiroom', $values, 'multiroom_');
		}],

		'MPD Config',
		['requires' => ['mixer_type'], 'handler' => 'setCfgMpdParams', 'custom_write' => 'getCfgMpdParams'],
		['requires' => ['device'], 'handler' => 'setCfgMpdParams', 'custom_write' => 'getCfgMpdParams'],
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
		['requires' => ['ashuffle_mode'], 'handler' => 'setSessVarSql'],
		['requires' => ['ashuffle_filter'], 'handler' => 'setSessVarSql'],
		['requires' => ['mpd_httpd'], 'handler' => function($values) {
			$cmd = $values['mpd_httpd'] == '1' ? 'mpc enable "' . HTTP_SERVER . '"' : 'mpc disable "' . HTTP_SERVER . '"';
			sysCmd($cmd);
			phpSession('write', 'mpd_httpd', $values['mpd_httpd']);
		}],
		['requires' => ['mpd_httpd_port'], 'handler' => 'setSessVarSql'],
		['requires' => ['mpd_httpd_encoder'], 'handler' => 'setSessVarSql'],

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
				$query ="insert into cfg_eqalsa (curve_name, curve_values) values ('" . $curveName . "', '" . $curveValues . "')";
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

		'Renderers',
		['requires' => ['btsvc'], 'handler' => 'setSessVarSql'],
		['requires' => ['rsmafterbt'], 'handler' => 'setSessVarSql'],
		['requires' => ['airplaysvc'], 'handler' => 'setSessVarSql'],
		['requires' => ['rsmafterapl'], 'handler' => 'setSessVarSql'],
		['requires' => ['spotifysvc'], 'handler' => 'setSessVarSql'],
		['requires' => ['rsmafterspot'], 'handler' => 'setSessVarSql'],
		['requires' => ['slsvc'], 'handler' => 'setSessVarSql'],
		['requires' => ['rsmaftersl'], 'handler' => 'setSessVarSql'],
		['requires' => ['rbsvc'], 'handler' => 'setSessVarSql'],
		['requires' => ['rsmafterrb'], 'handler' => 'setSessVarSql'],

		'Bluetooth',
		['requires' => ['pairing_agent'], 'handler' => 'setSessVarSql'],
		['requires' => ['bluez_pcm_buffer'], 'handler' => function($values) {
			phpSession('write', 'bluez_pcm_buffer', $values['bluez_pcm_buffer']);
			sysCmd("sed -i '/BUFFERTIME/c\BUFFERTIME=" . $values['bluez_pcm_buffer'] . "' /etc/bluealsaaplay.conf");
		}],
		['requires' => ['audioout'], 'handler' => 'setSessVarSql'],
		['requires' => ['bt_alsa_output_mode'], 'handler' => 'setSessVarOnly'],

		'AirPlay',
		['requires' => ['airplay_interpolation', 'airplay_output_format', 'airplay_output_rate', 'airplay_allow_session_interruption',
			'airplay_session_timeout', 'airplay_audio_backend_latency_offset_in_seconds', 'airplay_audio_backend_buffer_desired_length_in_seconds'],
			'handler' => function($values) {
				setCfgTableParams('cfg_airplay', $values, 'airplay_');
			}, 'custom_write' => function($values) {
				return getCfgTableParams('cfg_airplay', $values, 'airplay_');
		}],

		'Spotify',
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
			$str .= "wlansec = \"" . $result[0]['wlanuuid'] . "\"\n";
			$str .= "wlanpwd = \"" . "" . "\"\n"; // Keep empty
			$str .= "wlanpsk = \"" . $result[0]['wlanpsk'] . "\"\n";
			$str .= "wlancountry = \"" . $result[0]['wlancc'] . "\"\n";
			return $str;
		}],

		['requires' => ['ssid_ssid', 'ssid_sec', 'ssid_psk'],
		'handler' => function($values) {
			$dbh = sqlConnect();
			sqlDelete('cfg_ssid', $dbh);
			$count = count($values['ssid_ssid']);
			for ($i = 0; $i < $count; $i++) {
				$value = "\"" . $values['ssid_ssid'][$i] . "\", \""  .
					$values['ssid_sec'][$i]	. "\", \"" . $values['ssid_psk'][$i] . "\"";
				sqlInsert('cfg_ssid', $dbh, $value);
			}
			cfgNetworks();
		}, 'custom_write' => function($values) {
			$result = sqlRead('cfg_ssid', sqlConnect());
			$format = "ssid_%s[%d] = \"%s\"\n";
			$str = '';
			foreach ($result as $i => $row) {
				$str .= sprintf($format, 'ssid', $i, $row['ssid']);
				$str .= sprintf($format, 'sec', $i, $row['sec']);
				$str .= sprintf($format, 'psk', $i, $row['psk']);
			}
			return $str;
		}],

		'Network (apd0)',
		['requires' => ['apdssid', 'apdpwd', 'apdchan'],
		'optionals' => ['apdpsk'],
		'handler' => function($values, $optionals) {
			$psk = (key_exists('apdpsk', $optionals) && !empty($optionals['apdpsk'])) ? $optionals['apdpsk'] :
				genWpaPSK($values['apdssid'], $values['apdpwd']);
			$value = array('method' => '', 'ipaddr' => '', 'netmask' => '', 'gateway' => '', 'pridns' => '', 'secdns' => '',
				'wlanssid' => $values['apdssid'], 'wlanuuid' => '', 'wlanpwd' => $psk, 'wlanpsk' =>  $psk,
				'wlancc' => '');
			sqlUpdate('cfg_network', sqlConnect(), 'apd0', $value);
		}, 'custom_write' => function($values) {
			$result = sqlQuery("select * from cfg_network where iface='apd0'", sqlConnect());
			$str = '';
			$str .= "apdssid = \"" . $result[0]['wlanssid'] . "\"\n";
			$str .= "apdpwd = \"" . "" . "\"\n"; // Keep empty
			$str .= "apdpsk = \"" . $result[0]['wlanpsk'] . "\"\n";
			return $str;
		}],

		// Preferences
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

		'Internal',
		['requires' => ['first_use_help'], 'handler' => function($values) {
			phpSession('write', 'first_use_help', ($values['first_use_help'] == 'Yes' ? 'y,y' : 'n,n'));
		}, 'custom_write' => function($values) {
			$value = $_SESSION['first_use_help'] == 'n,n' ? "No" : "Yes";
			return "first_use_help = \"" . $value . "\"\n";
		}],

		'Sources',
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

	return $configurationHandlers;
}

//
// Import and apply settings from auto-config file
//
function autoConfig($cfgfile) {
	autoCfgLog('autocfg: Auto-configure initiated');

	try {
		$autocfg = parse_ini_file($cfgfile, false);

		$available_configs = array_keys($autocfg);

		autoCfgLog('autocfg: Configuration file parsed');
		$configurationHandlers = autoConfigSettings();

		$section = '';
		foreach ($configurationHandlers as $config) {
			$values = array();

			// Print new section header
			if (is_string($config)) {
				autoCfgLog('autocfg: - ' . $config);
			// Check if all required cfgkeys are present
			} else if (!array_diff_key(array_flip($config['requires']), $autocfg)) {
				// Create dict key/value of required settings
				$values = array_intersect_key($autocfg, array_flip($config['requires']));
				// Only get dict key/value of optionals that are present
				$optionals = array_intersect_key($autocfg, array_flip($config['optionals']));

				$combiner_requires_optional_keys = array_merge($config['requires'],	array_key_exists('optionals', $config) ? array_keys($optionals) : []);
				// Copy all key/value sets
				foreach ($combiner_requires_optional_keys as $config_name) {
					$value = $autocfg[$config_name];
					autoCfgLog('autocfg: ' . $config_name . ': ' . $value);
					// Remove used autoconfig
					unset($available_configs[$config_name]);
					unset($autocfg[$config_name]);
				}
				// Call handler
				if (!array_key_exists('cmd', $config)) {
					if (array_key_exists( 'optionals', $config)) {
						$config['handler'] ($values, $optionals);
					} else {
						$config['handler'] ($values);
					}
				} else {
					$config['handler'] ($values, $config['cmd']);
				}
			}
			// Detect requires with multiple keys which are no all present in provided configs
			else if (count($config['requires']) >= 2 and count(array_diff_key(array_flip($config['requires']), $autocfg)) >= 1) {
				$incompleteset = " [ ";
				foreach ($config['requires'] as $config_require) {
					$incompleteset = $incompleteset . " ". $config_require;
				}
				$incompleteset = $incompleteset . " ]";
				autoCfgLog('autocfg: Warning incomplete set ' . $incompleteset . ' detected.');
			}
		}

		$script_key = 'script';
		if (array_key_exists($script_key, $autocfg)) {
			autoCfgLog('autocfg: ' . $script_key . ':' . $script);
			$script = $autocfg[$script_key];

			if (file_exists($script)) {
				$output = sysCmd($script);
				foreach ($output as $line) {
					autoCfgLog($line);
				}
			} else {
				autoCfgLog('autocfg: Error script not found!');
			}
			unset($autocfg[$script_key]);
		}

		// Check for unused but supplied autocfg settings
		if (empty($available_configs)) {
			foreach ($available_configs as $config_name) {
				autoCfgLog('autocfg: Warning: ' . $config_name . ': is unused, incomplete or wrong name.');
			}
		}
	}
	catch (Exception $e) {
		autoCfgLog('autocfg: Caught exception: ' . $e->getMessage());
	}

 	sysCmd('rm ' . $cfgfile);
	autoCfgLog('autocfg: Configuration file deleted');
	autoCfgLog('autocfg: Auto-configure complete');
}

//
// Generates an auto-config file containing the current settings
//
function autoConfigExtract($currentSettings) {
	$autoConfigString = <<<EOT
	; #############################################
	; Copy this file to /boot/firmware/moodecfg.ini
	;
	; It will be applied during startup followed by
	; an automatic reboot to finalize the settings.
	;
	; Created: %s
	; Release: %s
	;
	; #############################################

	EOT;

	$autoConfigString = sprintf($autoConfigString, date('Y-m-d H:i:s'), getMoodeRel('verbose'));

	$configurationHandlers = autoConfigSettings();
	foreach ($configurationHandlers as &$config) {
		$values = array();
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
