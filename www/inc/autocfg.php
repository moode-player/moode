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
 * - Which settings are support
 * - A read handler for import
 * - A write handler for export
 */
function autoConfigSettings() {
	$debug = true;
	// Handler is config item name is just setting the playSession
	function setphpSession($values) {
		phpSession('write', array_key_first($values), $values[array_key_first($values)]);
	}

	// Handler is config item name is just setting the playSession and a syscmd call to sysutil.sh
	function setphpSessionAndSysCmd($values, $command ) {
		sysCmd('/var/www/util/sysutil.sh '.sprintf($command, $values[array_key_first($values)]) );
		phpSession('write', array_key_first($values), $values[array_key_first($values)]);
	}

	function setCfgMpd($values) {
		$dbh = sqlConnect();
		$total_query ='';
		foreach ($values  as $key=>$value) {
			$query = sprintf('update cfg_mpd set value="%s" where param="%s"; ', $value, $key);
			$result = sqlQuery($query, $dbh);
		}
	}

	function getCfgMpd($values) {
		$dbh = sqlConnect();
		$result ='';
		foreach ($values  as $key) {
			$query = 'select param,value from cfg_mpd where param="' . $key . '"';
			$rows = sqlQuery($query, $dbh);
			$result = $result . sprintf("%s = \"%s\"\n", $key, $rows[0]['value']);
		}
		return $result;
	}

	// Can not be directly called as handler, but as shorthand with in handler
	function setDbParams($dbtable, $values, $prefix = '') {
		$dbh = sqlConnect();
		$total_query ='';
		foreach ($values  as $key=>$value) {
			$param =  strlen($prefix) > 0 ? str_replace($prefix, "", $key) : $key ;
			sqlUpdate($dbtable, $dbh, $param, $value);
		}
	}

	// Can not be directly called as handler, but as shorthand with in handler
	function getDbParams($dbtable, $values, $prefix = '') {
		$dbh = sqlConnect();
		$result ='';
		foreach ($values  as $key) {
			$param =  strlen($prefix) > 0 ? str_replace($prefix, "", $key) : $key ;
			$query = 'select param,value from '.$dbtable.' where param="' . $param . '"';
			$rows = sqlQuery($query, $dbh);
			if ($rows) {
				$result = $result . sprintf("%s = \"%s\"\n", $key, $rows[0]['value']);
			}
		}
		return $result;
	}

	// Configuration of the autoconfig item handling
	// - requires - array of autoconfig items that should be present (all) before the handler is executed.
	//              most item only have 1 autoconfig item, but network setting requires multiple to be present
	// - handler for setting the config item
	// - command - argument for sysutil.sh when setphpSessionAndSysCmd handler is used.
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
			$dbh = sqlConnect();
			$result = sqlQuery("select value from cfg_sl where param='PLAYERNAME'", $dbh)[0]['value'];
			return "squeezelitename = \"".$result."\"\n";
		}],
		['requires' => ['upnpname'], 'handler' => setphpSessionAndSysCmd, 'cmd' => 'chg-name upnp "' . $_SESSION['upnpname'] . '" "%s"'],
		['requires' => ['dlnaname'], 'handler' => setphpSessionAndSysCmd, 'cmd' => 'chg-name dlna "' . $_SESSION['dlnaname'] . '" "%s"'],

		'System',
		['requires' => ['timezone'], 'handler' => setphpSessionAndSysCmd, 'cmd' => 'set-timezone %s'],
		['requires' => ['keyboard'], 'handler' => setphpSessionAndSysCmd, 'cmd' => 'set-keyboard %s'],
		// TODO: Decide use the same value as in the database or make Captalized ?then also required a custom writer?
		// ['requires' => ['cpugov'], 'handler' => function($values) {
		// TODO: Use native of the caption one ?, is not the same as in session. give problems with extraction
		// phpSession('write', 'cpugov', $values['cpugov'] == 'Performance' ? 'performance' : 'ondemand');
		//}],
		['requires' => ['cpugov'], 'handler' => setphpSession],
		['requires' => ['hdmiport'], 'handler' => setphpSession],
		['requires' => ['ipaddr_timeout'], 'handler' => setphpSession],
		['requires' => ['eth0chk'], 'handler' => setphpSession],
		['requires' => ['led_state'], 'handler' => setphpSession],
		['requires' => ['localui'], 'handler' => setphpSession],
		['requires' => ['p3wifi'], 'handler' => function($values) {
			ctlWifi($values['p3wifi']);
			phpSession('write', 'p3wifi', $values['p3wifi']);
		}],
		['requires' => ['p3bt'], 'handler' => function($values) {
			ctlBt($values['p3bt']);
			phpSession('write', 'p3bt', $values['p3bt']);
		}],

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

		'MPD',
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

		'DSP',
		['requires' => ['alsaequal'], 'handler' => setphpSession],
		['requires' => ['eqfa12p'], 'handler' => setphpSession],
		['requires' => ['camilladsp_quickconv'], 'handler' => setphpSession],
		['requires' => ['cdsp_fix_playback'], 'handler' => setphpSession],
		['requires' => ['camilladsp'], 'handler' => setphpSession],
		['requires' => ['camilladsp_volume_sync'], 'handler' => setphpSession],

		'Parametric EQ',
		['requires' => [ 'eqp12_curve_name', 'eqp12_settings', 'eqp12_active'], 'handler' => function($values) {
			require_once __DIR__ . '/eqp.php';
			$dbh = sqlConnect();
			$eqp = Eqp12($dbh);
			$eqp->import($values);
		}, 'custom_write' => function($values) {
			require_once __DIR__ . '/eqp.php';
			$dbh = sqlConnect();
			$eqp = Eqp12($dbh);
			$eqp_export = $eqp->export();
			return $eqp_export ;
		}],

		'Graphic EQ',
		['requires' => [ 'eqg_curve_name', 'eqg_curve_values'], 'handler' => function($values) {
			$dbh = sqlConnect();
			$curve_count = count($values['eqg_curve_name']);
			$querystr = 'DELETE FROM cfg_eqalsa;';
			$result = sqlQuery($querystr, $dbh);
			for($index =0; $index< $curve_count; $index++) {
				$curve_name = $values['eqg_curve_name'][$index];
				$curve_values = $values['eqg_curve_values'][$index];
				$querystr ="INSERT INTO cfg_eqalsa (curve_name, curve_values) VALUES ('" . $curve_name . "', '" . $curve_values . "');";
				$result = sqlQuery($querystr, $dbh);
			}
		}, 'custom_write' => function($values) {
			$dbh = sqlConnect();
			$mounts = sqlRead('cfg_eqalsa', $dbh);
			$stringformat = "eqg_%s[%d] = \"%s\"\n";
			$eqg_export = "";
			foreach ($mounts  as $index=>$mp) {
				$eqg_export =  $eqg_export . sprintf($stringformat, 'curve_name', $index, $mp['curve_name']);
				$eqg_export =  $eqg_export . sprintf($stringformat, 'curve_values', $index, $mp['curve_values']);
			}
			return $eqg_export;
		}],

		'Renderers',
		['requires' => ['btsvc'], 'handler' => setphpSession],
		['requires' => ['pairing_agent'], 'handler' => setphpSession],
		['requires' => ['btmulti'], 'handler' => setphpSession],
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
		['requires' => ['bluez_pcm_buffer'], 'handler' => setphpSession],
		['requires' => ['audioout'], 'handler' => setphpSession],

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
			$dbh = sqlConnect();
			$netcfg = sqlQuery('select * from cfg_network', $dbh);
			$value = array('method' => $values['ethmethod'], 'ipaddr' => $values['ethipaddr'], 'netmask' => $values['ethnetmask'],
				'gateway' => $values['ethgateway'], 'pridns' => $values['ethpridns'], 'secdns' => $values['ethsecdns']);
			sqlUpdate('cfg_network', $dbh, 'eth0', $value);
			cfgNetIfaces();
		}, 'custom_write' => function($values) {
			$dbh = sqlConnect();
			$row = sqlQuery("select * from cfg_network where iface='eth0'", $dbh)[0];
			$result="";
			$result = $result."ethmethod = \"" . $row['method'] . "\"\n";
			$result = $result."ethipaddr = \"" . $row['ipaddr'] . "\"\n";
			$result = $result."ethnetmask = \"" . $row['netmask'] . "\"\n";
			$result = $result."ethgateway = \"" . $row['gateway'] . "\"\n";
			$result = $result."ethpridns = \"" . $row['pridns'] . "\"\n";
			$result = $result."ethsecdns = \"" . $row['secdns'] . "\"\n";
			return $result;
		}],

		'Network (wlan0)',
		['requires' => ['wlanssid', 'wlanpwd', 'wlansec'],
		'optionals' => ['wlanmethod', 'wlanipaddr', 'wlannetmask', 'wlangateway', 'wlanpridns', 'wlansecdns', 'wlancountry', 'wlanpsk'],
		'handler' => function($values, $optionals) {
			$dbh = sqlConnect();
			$psk = (key_exists('wlanpsk', $optionals) && !empty($optionals['wlanpsk'])) ? $optionals['wlanpsk'] :
				genWpaPSK($values['wlanssid'], $values['wlanpwd']);
			$netcfg = sqlQuery('select * from cfg_network', $dbh);
			$value = array('method' => $netcfg[1]['method'], 'ipaddr' => $netcfg[1]['ipaddr'], 'netmask' => $netcfg[1]['netmask'],
				'gateway' => $netcfg[1]['gateway'], 'pridns' => $netcfg[1]['pridns'], 'secdns' => $netcfg[1]['secdns'],
				'wlanssid' => $values['wlanssid'], 'wlansec' => $values['wlansec'], 'wlanpwd' => $psk, 'wlan_psk' => $psk,
				'wlan_channel' => '', 'wlan_country' =>  $netcfg[1]['wlan_country']);
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
			$dbh = sqlConnect();
			$row = sqlQuery("select * from cfg_network where iface='wlan0'", $dbh)[0];
			$result="";
			$result = $result."wlanmethod = \"" . $row['method'] . "\"\n";
			$result = $result."wlanipaddr = \"" . $row['ipaddr'] . "\"\n";
			$result = $result."wlannetmask = \"" . $row['netmask'] . "\"\n";
			$result = $result."wlangateway = \"" . $row['gateway'] . "\"\n";
			$result = $result."wlanpridns = \"" . $row['pridns'] . "\"\n";
			$result = $result."wlansecdns = \"" . $row['secdns'] . "\"\n";
			$result = $result."wlanssid = \"" . $row['wlanssid'] . "\"\n";
			$result = $result."wlansec = \"" . $row['wlansec'] . "\"\n";
			$result = $result."wlanpwd = \"" . "" . "\"\n"; // Keep empty
			$result = $result."wlanpsk = \"" . $row['wlan_psk'] . "\"\n";
			$result = $result."wlancountry = \"" . $row['wlan_country'] . "\"\n";
			return $result;
		}],

		['requires' => ['ssid_ssid', 'ssid_sec', 'ssid_psk'],
		'handler' => function($values) {
			$dbh = sqlConnect();
			sqlDelete('cfg_ssid', $dbh);
			$ssid_count = count($values['ssid_ssid']);
			for($index = 0; $index < $ssid_count; $index++) {
				$value_str = "\"" . $values['ssid_ssid'][$index] . "\", \""  .  $values['ssid_sec'][$index]	. "\", \"" . $values['ssid_psk'][$index] . "\"";
				sqlInsert('cfg_ssid', $dbh, $value_str);
			}
			cfgNetIfaces();
		}, 'custom_write' => function($values) {
			$dbh = sqlConnect();
			$ssids = sqlRead('cfg_ssid', $dbh);
			$stringformat = "ssid_%s[%d] = \"%s\"\n";
			$ssid_export = "";
			foreach ($ssids  as $index=>$mp) {
				$ssid_export =  $ssid_export . sprintf($stringformat, 'ssid', $index, $mp['ssid']);
				$ssid_export =  $ssid_export . sprintf($stringformat, 'sec', $index, $mp['sec']);
				$ssid_export =  $ssid_export . sprintf($stringformat, 'psk', $index, $mp['psk']);
			}
			return $ssid_export;
		}],

		'Network (apd0)',
		['requires' => ['apdssid', 'apdpwd', 'apdchan'],
		'optionals' => ['apdpsk'],
		'handler' => function($values, $optionals) {
			$dbh = sqlConnect();
			$psk = (key_exists('apdpsk', $optionals) && !empty($optionals['apdpsk'])) ? $optionals['apdpsk'] :
				genWpaPSK($values['apdssid'], $values['apdpwd']);
			$value = array('method' => '', 'ipaddr' => '', 'netmask' => '', 'gateway' => '', 'pridns' => '', 'secdns' => '',
				'wlanssid' => $values['apdssid'], 'wlansec' => '', 'wlanpwd' => $psk, 'wlan_psk' =>  $psk,
				'wlan_country' => '', 'wlan_channel' => $values['apdchan'], 'wlan_router' => 'Off'); // Always set router_mode to Off
			sqlUpdate('cfg_network', $dbh, 'apd0', $value);
			cfgHostApd();
		},
		'custom_write' => function($values) {
			$dbh = sqlConnect();
			$row = sqlQuery("select * from cfg_network where iface='apd0'", $dbh)[0];
			$result = $result . "apdssid = \"" . $row['wlanssid'] . "\"\n";
			$result = $result . "apdpwd = \"" . "" . "\"\n"; // Keep empty
			$result = $result . "apdpsk = \"" . $row['wlan_psk'] . "\"\n";
			$result = $result . "apdchan = \"" . $row['wlan_channel'] . "\"\n";
			$result = $result . "apdrouter = \"" . 'Off' . "\"\n";
			return $result;
		}],

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
		['requires' => ['show_npicon'], 'handler' => setphpSession],
		['requires' => ['show_cvpb'], 'handler' => setphpSession],

		'Library',
		['requires' => ['library_onetouch_album'], 'handler' => setphpSession],
		['requires' => ['library_onetouch_radio'], 'handler' => setphpSession],
		['requires' => ['library_onetouch_pl'], 'handler' => setphpSession],
		['requires' => ['library_albumview_sort'], 'handler' => setphpSession],
		['requires' => ['library_tagview_sort'], 'handler' => setphpSession],
		['requires' => ['library_recently_added'], 'handler' => setphpSession],
		['requires' => ['library_encoded_at'], 'handler' => setphpSession],
		['requires' => ['library_covsearchpri'], 'handler' => setphpSession],
		['requires' => ['library_thmgen_scan'], 'handler' => setphpSession],
		['requires' => ['library_hiresthm'], 'handler' => setphpSession],
		['requires' => ['library_thumbnail_columns'], 'handler' => setphpSession],

		'Library (Advanced)',
		['requires' => ['library_tagview_genre'], 'handler' => setphpSession],
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
			return "first_use_help = \"".$value."\"\n";
		}],

		'Sources',
		['requires' => ['usb_auto_updatedb'], 'handler' => setphpSession],
		['requires' => ['cuefiles_ignore'], 'handler' => setphpSession],
		// Sources are using the array construction of the ini reader
		// source_name[0] = ...
		['requires' => ['source_name', 'source_type', 'source_address', 'source_remotedir', 'source_username', 'source_password',
			'source_charset', 'source_rsize', 'source_wsize', 'source_wsize', 'source_options'], 'handler' => function($values) {
			// Remove existing mounts
			$dbh = sqlConnect();
			$existing_mounts = sqlRead('cfg_source', $dbh);
			foreach ($existing_mounts  as $mount) {
				$mount['action'] = 'delete';
				sourceCfg($mount);
			}
			// Add new ones from import
			$source_count = count($values['source_name']);
			$keys = array_keys($values);
			for($index = 0; $index < $source_count; $index++) {
				$mount = ['mount' => ['action' => 'add']];
				foreach($keys as $key) {
					$mount['mount'][substr($key, 7)] = $values[$key][$index];
				}
				sourceCfg($mount);
			}
		}, 'custom_write' => function($values) {
			$dbh = sqlConnect();
			$mounts = sqlRead('cfg_source', $dbh);
			$stringformat = "source_%s[%d] = \"%s\"\n";
			$source_export = "";
			foreach ($mounts  as $index=>$mp) {
				$source_export =  $source_export . sprintf($stringformat, 'name', $index, $mp['name']);
				$source_export =  $source_export . sprintf($stringformat, 'type', $index, $mp['type']);
				$source_export =  $source_export . sprintf($stringformat, 'address', $index, $mp['address']);
				$source_export =  $source_export . sprintf($stringformat, 'remotedir', $index, $mp['remotedir']);
				$source_export =  $source_export . sprintf($stringformat, 'username', $index, $mp['username']);
				$source_export =  $source_export . sprintf($stringformat, 'password', $index, $mp['password']);
				$source_export =  $source_export . sprintf($stringformat, 'charset', $index, $mp['charset']);
				$source_export =  $source_export . sprintf($stringformat, 'rsize', $index, $mp['rsize']);
				$source_export =  $source_export . sprintf($stringformat, 'wsize', $index, $mp['wsize']);
				$source_export =  $source_export . sprintf($stringformat, 'options', $index, $mp['options']);
				}
			return $source_export;
		}]
	];

	return $configurationHandlers;
}

/**
 * Import auto-configure settings.
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
			}
			// Check if all required cfgkeys are present
			elseif (!array_diff_key(array_flip($config['requires']), $autocfg)) {
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
					}
					else {
						$config['handler'] ($values);
					}
				}
				else {
					$config['handler'] ($values, $config['cmd']);
				}
			}
			// Detect reuires with multiple keys which are no all present in provided configs
			elseif (count($config['requires']) >= 2 and count(array_diff_key(array_flip($config['requires']), $autocfg)) >= 1) {
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
			}
			else {
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
function autoconfigExtract() {
	$autoconfigstring = <<<EOT
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

	$autoconfigstring = sprintf($autoconfigstring, getMoodeRel('verbose'), date('Y-m-d H:i:s'));

	$configurationHandlers = autoConfigSettings(); // Contains supported configuration items
	foreach ($configurationHandlers as &$config) {
		$values = array();
		// Print new section header
		if (is_string($config)) {
			$autoconfigstring = $autoconfigstring . "\n[" . $config. "]\n";
		}
		else {
			if (!array_key_exists('custom_write', $config)) {
				foreach ($config['requires'] as $config_name) {
					$config_key = array_key_exists('session_var', $config) ? $config['session_var'] : $config_name;
					if (array_key_exists($config_key, $_SESSION)) {
						$autoconfigstring = $autoconfigstring . $config_key . " = \"" . $_SESSION[$config_key] . "\"\n";
					}
				}
			}
			else {
				$autoconfigstring = $autoconfigstring . $config['custom_write'] (
					array_merge($config['requires'], array_key_exists('optionals', $config) ? $config['optionals'] : [])
				);
			}
		}
	}

	return $autoconfigstring . "\n";
}

?>
