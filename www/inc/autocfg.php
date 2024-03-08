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
 */

/**
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

/**
 * Returns the settings for reading/writing the autoConfig file.
 * Is used by import and export of the auto-configure settings.
 *
 * Mainly it returns an array with:
 * - Which settings are supported
 * - A read handler for import
 * - A write handler for export
 */
function autoConfigSettings() {
	$debug = true;

	// Handler for setting just the session var
	function setSessionVarOnly($values) {
		$_SESSION[array_key_first($values)] = $values[array_key_first($values)];
	}

	// Handler for seting the session var and sql value
	function setphpSession($values) {
		phpSession('write', array_key_first($values), $values[array_key_first($values)]);
	}

	// Handler for seting the session var, sql value and a syscmd call to sysutil.sh
	function setphpSessionAndSysCmd($values, $cmd) {
		sysCmd('/var/www/util/sysutil.sh '. sprintf($cmd, $values[array_key_first($values)]) );
		phpSession('write', array_key_first($values), $values[array_key_first($values)]);
	}

	function setCfgMpd($values) {
		$dbh = sqlConnect();
		foreach ($values as $key => $value) {
			$query = sprintf("update cfg_mpd set value='%s' where param='%s'", $value, $key);
			$result = sqlQuery($query, $dbh);
		}
	}

	function getCfgMpd($values) {
		$dbh = sqlConnect();
		$str = '';
		foreach ($values as $key) {
			$query = "select param, value from cfg_mpd where param='" . $key . "'";
			$result = sqlQuery($query, $dbh);
			$str .= sprintf("%s = \"%s\"\n", $key, $result[0]['value']);
		}
		return $str;
	}

	// Can not be directly called as handler, but as shorthand within handler
	function setDbParams($table, $values, $prefix = '') {
		$dbh = sqlConnect();
		foreach ($values as $key => $value) {
			$param =  strlen($prefix) > 0 ? str_replace($prefix, '', $key) : $key ;
			$result = sqlUpdate($table, $dbh, $param, $value);
		}
	}

	// Can not be directly called as handler, but as shorthand with in handler
	function getDbParams($table, $values, $prefix = '') {
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

	// Configuration of the autoconfig item handling
	// - requires - array of autoconfig items that should be present (all) before the handler is executed.
	//              most item only have 1 autoconfig item, but network setting requires multiple to be present
	// - handler for setting the config item
	// - command - argument for sysutil.sh when setphpSessionAndSysCmd handler is used
	$configurationHandlers = [
		'Names',
		['requires' => ['browsertitle'], 'handler' => setphpSession],
		['requires' => ['hostname'], 'handler' => setphpSessionAndSysCmd, 'cmd' => 'chg-name host "' . $_SESSION['hostname'] . '" "%s"'],
		['requires' => ['btname'], 'handler' => setphpSessionAndSysCmd, 'cmd' => 'chg-name bluetooth "' . $_SESSION['btname'] . '" "%s"'],
		['requires' => ['airplayname'], 'handler' => setphpSession],
		['requires' => ['spotifyname'], 'handler' => setphpSession],
		['requires' => ['squeezelitename'], 'handler' => function($values) {
			$dbh = sqlConnect();
			$currentName= sqlQuery("select value from cfg_sl where param='PLAYERNAME'", $dbh)[0]['value'];
			$result = sqlQuery('update cfg_sl set value=' . "'" . $values['squeezelitename'] . "'" . ' where param=' . "'PLAYERNAME'", $dbh);
			sysCmd('/var/www/util/sysutil.sh chg-name squeezelite "' . $currentName . '" ' . '"' . $values['squeezelitename'] . '"');
		}, 'custom_write' => function($values) {
			$result = sqlQuery("select value from cfg_sl where param='PLAYERNAME'", sqlConnect());
			return "squeezelitename = \"" . $result[0]['value'] . "\"\n";
		}],
		['requires' => ['upnpname'], 'handler' => setphpSessionAndSysCmd, 'cmd' => 'chg-name upnp "' . $_SESSION['upnpname'] . '" "%s"'],
		['requires' => ['dlnaname'], 'handler' => setphpSessionAndSysCmd, 'cmd' => 'chg-name dlna "' . $_SESSION['dlnaname'] . '" "%s"'],

		'System',
		['requires' => ['updater_auto_check'], 'handler' => setSessionVarOnly],
		['requires' => ['timezone'], 'handler' => setphpSessionAndSysCmd, 'cmd' => 'set-timezone %s'],
		['requires' => ['keyboard'], 'handler' => setphpSessionAndSysCmd, 'cmd' => 'set-keyboard %s'],
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
		['requires' => ['led_state'], 'handler' => setphpSession],
		['requires' => ['ipaddr_timeout'], 'handler' => setphpSession],
		['requires' => ['eth0chk'], 'handler' => setphpSession],

		'Local Display',
		['requires' => ['localui'], 'handler' => setphpSession],

		'File Sharing',
		['requires' => ['fs_smb'], 'handler' => setphpSession],
		['requires' => ['fs_nfs'], 'handler' => setphpSession],
		['requires' => ['fs_nfs_access'], 'handler' => setphpSession],
		['requires' => ['fs_nfs_options'], 'handler' => setphpSession],
		['requires' => ['lcdup'], 'handler' => setphpSession],

		'GPIO Buttons',
		['requires' => ['gpio_svc'], 'handler' => setphpSession],
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
		['requires' => ['shellinabox'], 'handler' => setphpSession],

		'Logs',
		['requires' => ['reduce_sys_logging'], 'handler' => function($values) {
			$cmd = $values['reduce_sys_logging'] == '1' ? 'disable' : 'enable';
			sysCmd('systemctl '. $cmd . ' rsyslog');
			$_SESSION['reduce_sys_logging'] = $values['reduce_sys_logging'];
		}],
		// NOTE: Not restored so we can avoid unnecessary logging in case user forgets they turned it on
		//['requires' => ['debuglog'], 'handler' => setSessionVarOnly],

		'I2S Device',
		['requires' => ['i2soverlay'], 'handler' => function($values) {
			phpSession('write', 'i2soverlay', $values['i2soverlay']);
		}],
		['requires' => ['i2sdevice'], 'handler' => function($values) {
			$value = $values['i2sdevice'] == 'none' ? 'None': $values['i2sdevice'];
			phpSession('write', 'i2sdevice', $value);
			cfgI2sOverlay($value);
		}],

		'ALSA',
		['requires' => ['alsa_output_mode'], 'handler' => setphpSession],
		['requires' => ['alsa_loopback'], 'handler' => function($values) {
			phpSession('write', 'alsa_loopback', $values['alsa_loopback']);
			$values['alsa_loopback'] == 'On' ? sysCmd("sed -i '0,/_audioout__ {/s//_audioout {/' /etc/alsa/conf.d/_sndaloop.conf") :
				sysCmd("sed -i '0,/_audioout {/s//_audioout__ {/' /etc/alsa/conf.d/_sndaloop.conf");
		}],

		'Multiroom',
		['requires' => ['multiroom_tx'], 'handler' => setphpSession],
		['requires' => ['multiroom_rx'], 'handler' => setphpSession],
		['requires' => ['multiroom_tx_bfr', 'multiroom_tx_host', 'multiroom_tx_port', 'multiroom_tx_sample_rate', 'multiroom_tx_channels', 'multiroom_tx_frame_size', 'multiroom_tx_bitrate',
					    'multiroom_rx_bfr', 'multiroom_rx_host', 'multiroom_rx_port',  'multiroom_rx_jitter_bfr', 'multiroom_rx_sample_rate', 'multiroom_rx_channels', 'multiroom_initial_volume'],
         'optionals' => ['multiroom_tx_rtprio', 'multiroom_tx_query_timeout', 'multiroom_rx_frame_size', 'multiroom_rx_rtprio', 'multiroom_rx_alsa_output_mode', 'multiroom_rx_mastervol_opt_in', 'multiroom_initial_volume'],
			'handler' => function($values, $optionals) {
				$mergedValues = array_merge($values, $optionals);
				setDbParams('cfg_multiroom', $mergedValues, 'multiroom_');
			}, 'custom_write' => function($values) {
				return getDbParams('cfg_multiroom', $values, 'multiroom_');
		}],

		'MPD Config',
		['requires' => ['mixer_type'], 'handler' => setCfgMpd, 'custom_write' => getCfgMpd],
		['requires' => ['device'], 'handler' => setCfgMpd, 'custom_write' => getCfgMpd],
		['requires' => ['audio_output_format'], 'handler' => setCfgMpd, 'custom_write' => getCfgMpd],
		['requires' => ['selective_resample_mode'], 'handler' => setCfgMpd, 'custom_write' => getCfgMpd],
		['requires' => ['sox_quality'], 'handler' => setCfgMpd, 'custom_write' => getCfgMpd],
		['requires' => ['sox_multithreading'], 'handler' => setCfgMpd, 'custom_write' => getCfgMpd],
		['requires' => ['sox_precision', 'sox_phase_response', 'sox_passband_end', 'sox_stopband_begin', 'sox_attenuation', 'sox_flags'],
			'handler' => setCfgMpd, 'custom_write' => getCfgMpd],
		['requires' => ['dop'], 'handler' => setCfgMpd, 'custom_write' => getCfgMpd],
		['requires' => ['replaygain'], 'handler' => setCfgMpd, 'custom_write' => getCfgMpd],
		['requires' => ['replaygain_preamp'], 'handler' => setCfgMpd, 'custom_write' => getCfgMpd],
		['requires' => ['volume_normalization'], 'handler' => setCfgMpd, 'custom_write' => getCfgMpd],
		['requires' => ['audio_buffer_size'], 'handler' => setCfgMpd, 'custom_write' => getCfgMpd],
		['requires' => ['max_output_buffer_size'], 'handler' => setCfgMpd, 'custom_write' => getCfgMpd],
		['requires' => ['max_playlist_length'], 'handler' => setCfgMpd, 'custom_write' => getCfgMpd],
		['requires' => ['input_cache'], 'handler' => setCfgMpd, 'custom_write' => getCfgMpd],
		['requires' => ['log_level'], 'handler' => setCfgMpd, 'custom_write' => getCfgMpd],
		['requires' => ['stop_dsd_silence'], 'handler' => setCfgMpd, 'custom_write' => getCfgMpd],
		['requires' => ['thesycon_dsd_workaround'], 'handler' => setCfgMpd, 'custom_write' => getCfgMpd],

		'MPD Options',
		['requires' => ['autoplay'], 'handler' => setphpSession],
		['requires' => ['mpdcrossfade'], 'handler' => setphpSession],
		['requires' => ['crossfeed'], 'handler' => setphpSession],
		['requires' => ['invert_polarity'], 'handler' => setphpSession],
		['requires' => ['volume_step_limit'], 'handler' => setphpSession],
		['requires' => ['volume_mpd_max'], 'handler' => setphpSession],
		['requires' => ['volume_db_display'], 'handler' => setphpSession],
		['requires' => ['ashufflesvc'], 'handler' => setphpSession],
		['requires' => ['ashuffle_mode'], 'handler' => setphpSession],
		['requires' => ['ashuffle_filter'], 'handler' => setphpSession],
		['requires' => ['mpd_httpd'], 'handler' => function($values) {
			$cmd = $values['mpd_httpd'] == '1' ? 'mpc enable "' . HTTP_SERVER . '"' : 'mpc disable "' . HTTP_SERVER . '"';
			sysCmd($cmd);
			phpSession('write', 'mpd_httpd', $values['mpd_httpd']);
		}],
		['requires' => ['mpd_httpd_port'], 'handler' => setphpSession],
		['requires' => ['mpd_httpd_encoder'], 'handler' => setphpSession],

		'CamillaDSP',
		['requires' => ['camilladsp'], 'handler' => setphpSession],
		['requires' => ['camilladsp_volume_sync'], 'handler' => setphpSession],
		['requires' => ['camilladsp_quickconv'], 'handler' => setphpSession],
		['requires' => ['cdsp_fix_playback'], 'handler' => setphpSession],

		'Parametric EQ',
		['requires' => ['eqfa12p'], 'handler' => setphpSession],
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
		['requires' => ['alsaequal'], 'handler' => setphpSession],
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
		['requires' => ['btsvc'], 'handler' => setphpSession],
		['requires' => ['rsmafterbt'], 'handler' => setphpSession],
		['requires' => ['airplaysvc'], 'handler' => setphpSession],
		['requires' => ['rsmafterapl'], 'handler' => setphpSession],
		['requires' => ['spotifysvc'], 'handler' => setphpSession],
		['requires' => ['rsmafterspot'], 'handler' => setphpSession],
		['requires' => ['slsvc'], 'handler' => setphpSession],
		['requires' => ['rsmaftersl'], 'handler' => setphpSession],
		['requires' => ['rbsvc'], 'handler' => setphpSession],
		['requires' => ['rsmafterrb'], 'handler' => setphpSession],

		'Bluetooth',
		['requires' => ['pairing_agent'], 'handler' => setphpSession],
		 // NOTE: The btmulti var was removed in 8.2.3 because the sharing feature became obsolete
		['requires' => ['btmulti'], 'handler' => setphpSession],
		['requires' => ['bluez_pcm_buffer'], 'handler' => function($values) {
			phpSession('write', 'bluez_pcm_buffer', $values['bluez_pcm_buffer']);
			sysCmd("sed -i '/BUFFERTIME/c\BUFFERTIME=" . $values['bluez_pcm_buffer'] . "' /etc/bluealsaaplay.conf");
		}],
		['requires' => ['audioout'], 'handler' => setphpSession],
		['requires' => ['bt_alsa_output_mode'], 'handler' => setSessionVarOnly],

		'AirPlay',
		['requires' => ['airplay_interpolation', 'airplay_output_format', 'airplay_output_rate', 'airplay_allow_session_interruption',
			'airplay_session_timeout', 'airplay_audio_backend_latency_offset_in_seconds', 'airplay_audio_backend_buffer_desired_length_in_seconds'],
			'handler' => function($values) {
				setDbParams('cfg_airplay', $values, 'airplay_');
			}, 'custom_write' => function($values) {
				return getDbParams('cfg_airplay', $values, 'airplay_');
		}],

		'Spotify',
		['requires' => ['spotify_bitrate', 'spotify_initial_volume', 'spotify_volume_curve', 'spotify_volume_normalization', 'spotify_normalization_pregain',
			'spotify_autoplay'],
			'optionals' => ['spotify_normalization_method', 'spotify_normalization_gain_type', 'spotify_normalization_threshold','spotify_normalization_attack',
			'spotify_normalization_release', 'spotify_normalization_knee', 'spotify_format', 'spotify_dither', 'spotify_volume_range'],
			'handler' => function($values, $optionals) {
				$mergedValues = array_merge($values, $optionals);
				setDbParams('cfg_spotify', $mergedValues, 'spotify_');
			}, 'custom_write' => function($values) {
				return getDbParams('cfg_spotify', $values, 'spotify_');
		}],

		'Squeezelite',
		['requires' => ['squeezelite_PLAYERNAME', 'squeezelite_AUDIODEVICE', 'squeezelite_ALSAPARAMS', 'squeezelite_OUTPUTBUFFERS',
			'squeezelite_TASKPRIORITY', 'squeezelite_CODECS', 'squeezelite_OTHEROPTIONS'],
			'handler' => function($values) {
				setDbParams('cfg_sl', $values, 'squeezelite_');
			}, 'custom_write' => function($values) {
				return getDbParams('cfg_sl', $values, 'squeezelite_');
		}],

		'UPnP/DLNA',
		['requires' => ['upnpsvc'], 'handler' => setphpSession],
		['requires' => ['dlnasvc'], 'handler' => setphpSession],
		['requires' => ['upnpav'], 'handler' => function($values) {
			setDbParams('cfg_upnp', $values);
		}, 'custom_write' => function($values) {
			return getDbParams('cfg_upnp', $values);
		}],
		['requires' => ['openhome'], 'handler' => function($values) {
			setDbParams('cfg_upnp', $values);
		}, 'custom_write' => function($values) {
			return getDbParams('cfg_upnp', $values);
		}],
		['requires' => ['checkcontentformat'], 'handler' => function($values) {
			setDbParams('cfg_upnp', $values);
		}, 'custom_write' => function($values) {
			return getDbParams('cfg_upnp', $values);
		}],

		'Network (eth0)',
		['requires' => ['ethmethod', 'ethipaddr', 'ethnetmask', 'ethgateway', 'ethpridns', 'ethsecdns'], 'handler' => function($values) {
			$value = array('method' => $values['ethmethod'], 'ipaddr' => $values['ethipaddr'], 'netmask' => $values['ethnetmask'],
				'gateway' => $values['ethgateway'], 'pridns' => $values['ethpridns'], 'secdns' => $values['ethsecdns']);
			sqlUpdate('cfg_network', sqlConnect(), 'eth0', $value);
			cfgNetIfaces();
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
		['requires' => ['wlanssid', 'wlanpwd', 'wlansec'],
		'optionals' => ['wlanmethod', 'wlanipaddr', 'wlannetmask', 'wlangateway', 'wlanpridns', 'wlansecdns', 'wlancountry', 'wlanpsk'],
		'handler' => function($values, $optionals) {
			$dbh = sqlConnect();
			$psk = (key_exists('wlanpsk', $optionals) && !empty($optionals['wlanpsk'])) ? $optionals['wlanpsk'] :
				genWpaPSK($values['wlanssid'], $values['wlanpwd']);
			$cfgNetwork = sqlQuery('select * from cfg_network', $dbh);
			$value = array('method' => $cfgNetwork[1]['method'], 'ipaddr' => $cfgNetwork[1]['ipaddr'],
				'netmask' => $cfgNetwork[1]['netmask'],	'gateway' => $cfgNetwork[1]['gateway'],
				'pridns' => $cfgNetwork[1]['pridns'], 'secdns' => $cfgNetwork[1]['secdns'],
				'wlanssid' => $values['wlanssid'], 'wlansec' => $values['wlansec'], 'wlanpwd' => $psk,
				'wlan_psk' => $psk, 'wlan_channel' => '', 'wlan_country' =>  $cfgNetwork[1]['wlan_country']);
			if (key_exists('wlanmethod', $optionals)) {$value['method'] = $optionals['wlanmethod'];}
			if (key_exists('wlanipaddr', $optionals)) {$value['ipaddr'] = $optionals['wlanipaddr'];}
			if (key_exists('wlannetmask', $optionals)) {$value['netmask'] = $optionals['wlannetmask'];}
			if (key_exists('wlangateway', $optionals)) {$value['gateway'] = $optionals['wlangateway'];}
			if (key_exists('wlanpridns', $optionals)) {$value['pridns'] = $optionals['wlanpridns'];}
			if (key_exists('wlansecdns', $optionals)) {$value['secdns'] = $optionals['wlansecdns'];}
			if (key_exists('wlancountry', $optionals)) {$value['wlan_country'] = $optionals['wlancountry'];}
			sqlUpdate('cfg_network', $dbh, 'wlan0', $value);
			cfgNetIfaces();
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
			$str .= "wlansec = \"" . $result[0]['wlansec'] . "\"\n";
			$str .= "wlanpwd = \"" . "" . "\"\n"; // Keep empty
			$str .= "wlanpsk = \"" . $result[0]['wlan_psk'] . "\"\n";
			$str .= "wlancountry = \"" . $result[0]['wlan_country'] . "\"\n";
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
			cfgNetIfaces();
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
				'wlanssid' => $values['apdssid'], 'wlansec' => '', 'wlanpwd' => $psk, 'wlan_psk' =>  $psk,
				'wlan_country' => '', 'wlan_channel' => $values['apdchan'], 'wlan_router' => 'Off'); // Always set router_mode to Off
			sqlUpdate('cfg_network', sqlConnect(), 'apd0', $value);
			cfgHostApd();
		}, 'custom_write' => function($values) {
			$result = sqlQuery("select * from cfg_network where iface='apd0'", sqlConnect());
			$str = '';
			$str .= "apdssid = \"" . $result[0]['wlanssid'] . "\"\n";
			$str .= "apdpwd = \"" . "" . "\"\n"; // Keep empty
			$str .= "apdpsk = \"" . $result[0]['wlan_psk'] . "\"\n";
			$str .= "apdchan = \"" . $result[0]['wlan_channel'] . "\"\n";
			$str .= "apdrouter = \"" . 'Off' . "\"\n";
			return $str;
		}],

		// Preferences
		'Appearance',
		['requires' => ['themename'], 'handler' => setphpSession],
		['requires' => ['accent_color'], 'handler' => setphpSession],
		['requires' => ['alphablend'], 'handler' => setphpSession],
		['requires' => ['adaptive'], 'handler' => setphpSession],
		['requires' => ['cover_backdrop'], 'handler' => setphpSession],
		['requires' => ['cover_blur'], 'handler' => setphpSession],
		['requires' => ['cover_scale'], 'handler' => setphpSession],
		['requires' => ['font_size'], 'handler' => setphpSession],

		'Playback',
		['requires' => ['playlist_art'], 'handler' => setphpSession],
		['requires' => ['extra_tags'], 'handler' => setphpSession],
		['requires' => ['playhist'], 'handler' => setphpSession],
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
		['requires' => ['show_cvpb'], 'handler' => setphpSession],

		'Library',
		['requires' => ['library_onetouch_album'], 'handler' => setphpSession],
		['requires' => ['library_onetouch_radio'], 'handler' => setphpSession],
		['requires' => ['library_onetouch_pl'], 'handler' => setphpSession],
		['requires' => ['library_albumview_sort'], 'handler' => setphpSession],
		['requires' => ['library_tagview_sort'], 'handler' => setphpSession],
		['requires' => ['library_track_play'], 'handler' => setphpSession],
		['requires' => ['library_recently_added'], 'handler' => setphpSession],
		['requires' => ['library_encoded_at'], 'handler' => setphpSession],
		['requires' => ['library_covsearchpri'], 'handler' => setphpSession],
		['requires' => ['library_thmgen_scan'], 'handler' => setphpSession],
		['requires' => ['library_hiresthm'], 'handler' => setphpSession],
		['requires' => ['library_thumbnail_columns'], 'handler' => setphpSession],

		'Library (Advanced)',
		['requires' => ['library_tagview_genre'], 'handler' => function($values) {
			$value = $values['library_tagview_genre'] == 'Genres' ? 'Genre' :
				($values['library_tagview_genre'] == 'Composers' ? 'Composer' : $values['library_tagview_genre']);
			phpSession('write', 'library_tagview_genre', $value);
		}],
		//['requires' => ['library_tagview_genre'], 'handler' => setphpSession],
		['requires' => ['library_tagview_artist'], 'handler' => setphpSession],
		['requires' => ['library_misc_options'], 'handler' => setphpSession],
		['requires' => ['library_ignore_articles'], 'handler' => setphpSession],
		['requires' => ['library_show_genres'], 'handler' => setphpSession],
		['requires' => ['library_tagview_covers'], 'handler' => setphpSession],
		['requires' => ['library_ellipsis_limited_text'], 'handler' => setphpSession],
		['requires' => ['library_utf8rep'], 'handler' => setphpSession],

		'CoverView',
		['requires' => ['scnsaver_timeout'], 'handler' => setphpSession], // Timed display
		['requires' => ['auto_coverview'], 'handler' => setphpSession], // Automatic display
		['requires' => ['scnsaver_style'], 'handler' => setphpSession],
		['requires' => ['scnsaver_mode'], 'handler' => setphpSession],
		['requires' => ['scnsaver_layout'], 'handler' => setphpSession],
		['requires' => ['scnsaver_xmeta'], 'handler' => setphpSession],

		'Internal',
		['requires' => ['first_use_help'], 'handler' => function($values) {
			phpSession('write', 'first_use_help', ($values['first_use_help'] == 'Yes' ? 'y,y' : 'n,n'));
		}, 'custom_write' => function($values) {
			$value = $_SESSION['first_use_help'] == 'n,n' ? "No" : "Yes";
			return "first_use_help = \"" . $value . "\"\n";
		}],

		'Sources',
		['requires' => ['fs_mountmon'], 'handler' => setSessionVarOnly],
		['requires' => ['usb_auto_updatedb'], 'handler' => setphpSession],
		['requires' => ['cuefiles_ignore'], 'handler' => setphpSession],
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

/**
 * Import auto-configure settings
 */
function autoConfig($cfgfile) {
	autoCfgLog('autocfg: Auto-configure initiated');

	try {
		$autocfg = parse_ini_file($cfgfile, false);

		$available_configs = array_keys($autocfg);

		autoCfgLog('autocfg: Configuration file parsed');
		$configurationHandlers = autoConfigSettings(); // contains supported configuration items

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

/**
 * Generates an autoconfig file as string based on the current settings
 */
function autoConfigExtract($currentSettings) {
	$autoConfigString = <<<EOT
	; #########################################
	; Copy this file to /boot/moodecfg.ini
	; It will be processed at startup and the
	; system will automatically Restart.
	;
	; All param = "value" pairs must be present.
	; Set wlanssid = blank to start AP mode.
	; Example: wlanssid = ""
	;
	; Moode Release : %s
	; Create date	: %s
	;
	; ##########################################

	EOT;

	$autoConfigString = sprintf($autoConfigString, getMoodeRel('verbose'), date('Y-m-d H:i:s'));

	$configurationHandlers = autoConfigSettings(); // Contains supported configuration items
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
