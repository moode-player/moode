<?php
/*
 * SPDX-License-Identifier: GPL-3.0-or-later
 * Copyright 2014 The moOde audio player project / Tim Curtis
 * Copyright 2013 The tsunamp player ui / Andrea Coiutti & Simone De Gregori
*/

require_once __DIR__ . '/common.php';
require_once __DIR__ . '/alsa.php';
require_once __DIR__ . '/audio.php';
require_once __DIR__ . '/cdsp.php';
require_once __DIR__ . '/music-library.php';
require_once __DIR__ . '/session.php';
require_once __DIR__ . '/sql.php';

// NOTE: Session vars and cfg_mpd updates should be made upstream before this function is called
function updMpdConf() {
    $data  = "#########################################\n";
    $data .= "# This file is managed by moOde          \n";
    $data .= "#########################################\n\n";

	$cfgMpd = sqlQuery("SELECT param, value FROM cfg_mpd WHERE value!=''", sqlConnect());

	foreach ($cfgMpd as $cfg) {
		switch ($cfg['param']) {
			case 'device':
				$cardNum = $cfg['value'];
				break;
			case 'dop':
				$dop = $cfg['value'];
				break;
			case 'stop_dsd_silence':
				$stopDsdSilence = $cfg['value'];
				break;
			case 'thesycon_dsd_workaround':
				$thesyconDsdWorkaround = $cfg['value'];
				break;
            case 'close_on_pause':
                $closeOnPause = $cfg['value'];
                break;
			case 'mixer_type':
				$mixerType = $cfg['value'];
				break;
			case 'input_cache':
				$inputCache = $cfg['value'];
				break;
			case 'audio_output_format':
				$data .= $cfg['value'] == 'disabled' ? '' : $cfg['param'] . " \"" . $cfg['value'] . "\"\n";
				break;
			case 'sox_quality':
				$soxQuality = $cfg['value'];
				break;
			case 'sox_multithreading':
				$soxMultithreading = $cfg['value'];
				break;
			case 'replay_gain_handler':
				$replayGainHandler = $cfg['value'];
				break;
			// ALSA: Only used if not default 500000 microseconds
			case 'buffer_time':
				$bufferTimeDefault = '500000';
				$bufferTime = $cfg['value'];
				break;
			// Not used
			case 'period_time':
				$periodTime = $cfg['value'];
				break;
			case 'auto_resample': // Not used
				$autoResample = $cfg['value'];
				break;
			case 'auto_channels': // Not used
				$autoChannels = $cfg['value'];
				break;
			case 'auto_format': // Not used
				$autoFormat = $cfg['value'];
				break;
			// SoX options
			case 'sox_precision':
				$soxPrecision = $cfg['value'];
				break;
			case 'sox_phase_response':
				$soxPhaseResponse = $cfg['value'];
				break;
			case 'sox_passband_end':
				$soxPassbandEnd = $cfg['value'];
				break;
			case 'sox_stopband_begin':
				$soxStopbandBegin = $cfg['value'];
				break;
			case 'sox_attenuation':
				$soxAttenuation = $cfg['value'];
				break;
			case 'sox_flags':
				$soxFlags = $cfg['value'];
				break;
			case 'selective_resample_mode':
				$data .= $cfg['value'] != '0' ? $cfg['param'] . " \"" . $cfg['value'] . "\"\n" : '';
				break;
            case 'proxy':
				$proxy = $cfg['value'];
				break;
            case 'proxy_user':
				$proxyUser = $cfg['value'];
				break;
            case 'proxy_password':
				$proxyPassword = $cfg['value'];
				break;
			// Default param handling
			default:
				$data .= $cfg['param'] . " \"" . $cfg['value'] . "\"\n";
				break;
		}
	}

    // MPD mixer type
    $outputIface = getAudioOutputIface($_SESSION['cardnum']);
	if ($_SESSION['alsavolume'] == 'none') {
        if ($outputIface == AO_USB ||
            $outputIface == AO_HDMI ||
            $outputIface == AO_TRXSEND ||
            $mixerType == 'null' || // CamillaDSP
            $mixerType == 'none') { // Fixed (0dB)
            // NOP: Leave mixer type as-is
        } else {
            // Revert to software mixer
    		$mixerType = 'software';
    		$result = sqlQuery("UPDATE cfg_mpd SET value='software' WHERE param='mixer_type'", sqlConnect());
        }
	}
	phpSession('write', 'mpdmixer', $mixerType);

    // Ensure mpdmixer_local = mpdmixer
    if ($_SESSION['audioout'] == 'Local') {
        phpSession('write', 'mpdmixer_local', $mixerType);
    }

    // Hardware mixer (if any)
    if ($outputIface == AO_TRXSEND || $_SESSION['alsa_output_mode'] != 'iec958') {
        $hwMixer = 'hw:' . $cardNum;
    } else {
        $hwMixer = getAlsaIEC958Device();
    }

	// Input
	$data .= "max_connections \"128\"\n";
	$data .= "\n";
	$data .= "decoder {\n";
	$data .= "plugin \"ffmpeg\"\n";
	$data .= "enabled \"yes\"\n";
	$data .= "}\n\n";
	$data .= "input {\n";
	$data .= "plugin \"curl\"\n";
    $data .= "proxy \"" . $proxy . "\"\n";
    $data .= "proxy_user \"" . $proxyUser . "\"\n";
    $data .= "proxy_password \"" . $proxyPassword . "\"\n";
	$data .= "}\n\n";

	// Input cache
	if ($inputCache != 'Disabled') {
		$data .= "input_cache {\n";
		$data .= "size \"" . $inputCache . "\"\n";
		$data .= "}\n\n";
	}

	// Resampler
	$data .= "resampler {\n";
	$data .= "plugin \"soxr\"\n";
	$data .= "quality \"" . $soxQuality . "\"\n";
	$data .= "threads \"" . $soxMultithreading . "\"\n";
	if ($soxQuality == 'custom') {
		$data .= "precision \"" . $soxPrecision . "\"\n";
	    $data .= "phase_response \"" . $soxPhaseResponse . "\"\n";
	    $data .= "passband_end \"" . $soxPassbandEnd . "\"\n";
	    $data .= "stopband_begin \"" . $soxStopbandBegin . "\"\n";
	    $data .= "attenuation \"" . $soxAttenuation . "\"\n";
	    $data .= "flags \"" . $soxFlags . "\"\n";
	}
	$data .= "}\n\n";

	// ALSA default
	// MPD -> {_audioout || DSP(MPD) -> _audioout} -> {{plughw || hw} || DSP(ALSA/Camilla) -> {plughw || hw}} -> audio device
	$data .= "audio_output {\n";
	$data .= "type \"alsa\"\n";
	$data .= "name \"" . ALSA_DEFAULT . "\"\n";
	$data .= "device \"_audioout\"\n";
	$data .= "mixer_type \"" . $mixerType . "\"\n";
	$data .= $mixerType == 'hardware' ?
        "mixer_control \"" . $_SESSION['amixname'] . "\"\n" .
        "mixer_device \"" . $hwMixer . "\"\n" .
        "mixer_index \"0\"\n" :
        '';
	$data .= "dop \"" . $dop . "\"\n";
	$data .= "stop_dsd_silence \"" . $stopDsdSilence . "\"\n";
	$data .= "thesycon_dsd_workaround \"" . $thesyconDsdWorkaround . "\"\n";
    $data .= "close_on_pause \"" . $closeOnPause . "\"\n";
	$data .= $bufferTime == $bufferTimeDefault ? '' : "buffer_time \"" . $bufferTime . "\"\n";
	$data .= "}\n\n";

	// ALSA bluetooth
	$data .= "audio_output {\n";
	$data .= "type \"alsa\"\n";
	$data .= "name \"" . ALSA_BLUETOOTH . "\"\n";
	//$data .= "device \"btstream\"\n";
	$data .= "device \"_audioout\"\n";
	$data .= "mixer_type \"software\"\n";
	$data .= "}\n\n";

	// HTTP server
	$data .= "audio_output {\n";
	$data .= "type \"httpd\"\n";
	$data .= "name \"" . HTTP_SERVER . "\"\n";
	$data .= "port \"" . $_SESSION['mpd_httpd_port'] . "\"\n";
	$data .= "encoder \"" . $_SESSION['mpd_httpd_encoder'] . "\"\n";
	$data .= $_SESSION['mpd_httpd_encoder'] == 'flac' ? "compression \"0\"\n" : "bitrate \"320\"\n";
	$data .= "tags \"yes\"\n";
	$data .= "always_on \"yes\"\n";
	$data .= $_SESSION['mpd_httpd_encoder'] == 'lame' ? "format \"44100:16:2\"\n" : '';
	$data .= "}\n\n";

	// Stream recorder
	if (($_SESSION['feat_bitmask'] & FEAT_RECORDER) && $_SESSION['recorder_status'] != 'Not installed') {
		include '/var/www/inc/recorder-mpd.php';
	}

    // Developer tweak for maintaining custom mpd.conf settings
	if ($_SESSION['feat_bitmask'] & FEAT_DEVTWEAKS) {
		$fh = fopen('/etc/mpd.moode.conf', 'w');
		fwrite($fh, $data);
		fclose($fh);
		sysCmd("/var/www/util/mpdconf_merge.py /etc/mpd.moode.conf /etc/mpd.custom.conf");
	} else {
		$fh = fopen('/etc/mpd.conf', 'w');
		fwrite($fh, $data);
		fclose($fh);
	}

	// Update ALSA and Bluetooth confs
	updAudioOutAndBtOutConfs($cardNum, $_SESSION['alsa_output_mode']);
	updDspAndBtInConfs($cardNum, $_SESSION['alsa_output_mode']);
	updPeppyConfs($cardNum, $_SESSION['alsa_output_mode']);

    // Update output device cache
    updOutputDeviceCache($_SESSION['adevname']);
}

// Change to different MPD mixer type
function changeMPDMixer($mixer) {
    //workerLog('changeMPDMixer(): mixer=' . $mixer);
	$mixerType = $mixer == 'camilladsp' ? 'null' : $mixer;

	sqlUpdate('cfg_mpd', sqlConnect(), 'mixer_type', $mixerType);
	phpSession('write', 'mpdmixer', $mixerType);
	if ($_SESSION['alsavolume'] != 'none' && $mixerType != 'hardware') {
        setALSAVolTo0dB($_SESSION['alsavolume_max']);
	}
    if ($_SESSION['camilladsp'] != 'off' && $mixer != 'camilladsp') {
        CamillaDSP::setCDSPVolTo0dB();
    }

	updMpdConf();
}

// Ensure valid mpd output config
function configMpdOutput() {
    $mpdOutput = $_SESSION['audioout'] == 'Bluetooth' ? ALSA_BLUETOOTH : ALSA_DEFAULT;
	return $mpdOutput;
}

// Get MPD ouputs for logging
function getMpdOutputs($sock) {
    sendMpdCmd($sock, 'outputs');
    $resp = readMpdResp($sock);

	$array = array();
	$line = strtok($resp, "\n");

	while ($line) {
		list ($element, $value) = explode(": ", $line, 2);

		if ($element == 'outputid') {
			$id = $value;
			//$array[$id] = 'MPD output ' . ($id + 1) . ' ';
		}
		if ($element == 'outputname') {
            if ($value == 'ALSA Default') {
                $value = 'ALSA Default:   ';
            } else if ($value == 'ALSA Bluetooth') {
                $value = 'ALSA Bluetooth: ';
            } else if ($value == 'HTTP Server') {
                $value = 'HTTP Server:    ';
            } else if ($value == 'Stream Recorder') {
                $value = 'recorder:       ';
            }
			$array[$id] .= $value;
		}
		if ($element == 'outputenabled') {
			$array[$id] .= $value == '0' ? 'off' : 'on';
		}

		$line = strtok("\n");
	}

	return $array;
}

// Scan the network for hosts with open port 6600 (MPD) and return list of IP addresses
function scanForMPDHosts($retryCount = 2) {
    $thisIpAddr = getThisIpAddr();
	$subnet = substr($thisIpAddr, 0, strrpos($thisIpAddr, '.'));
	$port = '6600';

	for ($i = 0; $i < $retryCount; $i++) {
        sysCmd('nmap -Pn -p' . $port . ' --open ' . $subnet . '.0/24 -oG /tmp/mpd_nmap.scan >/dev/null');
		$ipAddresses = sysCmd('cat /tmp/mpd_nmap.scan | grep "' . $port . '/open" | cut -f 1 | cut -d " " -f 2');
		if (!empty($ipAddresses)) {
			break;
		}
	}

	return $ipAddresses;
}

// Low-level MPD socket routines
function getMpdSock($caller = 'unknown caller') {
	if (false === ($sock = openMpdSock('localhost', 6600))) {
		debugLog('getMpdSock(): Connection to MPD failed (' . $caller . ')');
		exit(0);
	} else {
		return $sock;
	}
}
function openMpdSock($host, $port) {
	$retryCount = 6;
	for ($i = 0; $i < $retryCount; $i++) {
		if (false === ($sock = @stream_socket_client('tcp://' . $host . ':' . $port, $errorno, $errorstr, 30))) {
			debugLog('openMpdSock(): Error: connection failed (' . ($i + 1) . ') ' . $errorno . ', ' . $errorstr);
		} else {
			$resp = readMpdResp($sock);
			break;
		}

		usleep(500000); // .5 secs
	}

	return $sock;
}
function readMpdResp($sock) {
	$resp = '';

	while (false !== ($str = fgets($sock, 1024)) && !feof($sock)) {
		if (strncmp(MPD_RESPONSE_OK, $str, strlen(MPD_RESPONSE_OK)) == 0) {
			$resp = $resp == '' ? $str : $resp;
			return $resp;
		}

		if (strncmp(MPD_RESPONSE_ERR, $str, strlen(MPD_RESPONSE_ERR)) == 0) {
			$msg = 'readMpdResp(): Error: response $str[0]=(' . explode("\n", $str)[0] . ')';
			debugLog($msg);
			return $msg;
		}

		$resp .= $str;
	}

	if (!feof($sock)) {
		if (stream_get_meta_data($sock)['timed_out']) {
			debugLog('readMpdResp(): Socket timed out');
			$resp = 'Socket timed out';
		} else {
			// Socket timed out or PHP/MPD connection failure
			debugLog('readMpdResp(): Error: fgets failure (' . explode("\n", $resp)[0] . ')');
		}
	}

	return $resp;
}
function closeMpdSock($sock) {
	sendMpdCmd($sock, 'close');
	fclose($sock);
}

// Send command(s) to MPD
function sendMpdCmd($sock, $cmd) {
	fputs($sock, $cmd . "\n");
}
function chainMpdCmds($sock, $cmds) {
    if ($cmds[0] == 'clear') {
        sendMpdCmd($sock, 'clear');
        $resp = readMpdResp($sock);
        array_shift($cmds); // Remove element 0
    }

    sendMpdCmd($sock, 'command_list_begin');
    foreach ($cmds as $cmd) {
        sendMpdCmd($sock, $cmd);
    }
    sendMpdCmd($sock, 'command_list_end');
    $resp = readMpdResp($sock);
}

// Get MPD status and stats
function getMpdStatus($sock) {
	sendMpdCmd($sock, 'status');
	$resp = readMpdResp($sock);
	return formatMpdStatus($resp);
}
function getMpdStats($sock) {
	sendMpdCmd($sock, 'stats');
	$resp = readMpdResp($sock);
	return parseDelimFile($resp, ': ');
}

// Get MPD currentsong data
function getCurrentSong($sock) {
	sendMpdCmd($sock, 'currentsong');
	$resp = readMpdResp($sock);

	if (is_null($resp)) {
		debugLog('getCurrentSong(): Returned null');
		return null;
	} else {
		$array = array();
		$line = strtok($resp, "\n");
		$artistCount = 0;

		while ($line) {
			list ($element, $value) = explode(': ', $line, 2);

            if ($element != 'file') {
                $value = htmlspecialchars($value, ENT_NOQUOTES);
            }

			// NOTE: Save for future use
			/*if ($element == 'Genre' || $element == 'Artist' || $element == 'Conductor' || $element == 'Performer') {
				// These tags can have multiple occurrences so let's accumulate them as a delimited string
				$array[$element] .= $value . '; ';
			} else {
				// All other tags
				$array[$element] = $value;
			}*/

			if ($element == 'Genre' || $element == 'Artist' || $element == 'AlbumArtist' || $element == 'Conductor' || $element == 'Performer') {
				// Return only the first of multiple occurrences of the following tags
				if (!isset($array[$element])) {
					$array[$element] = $value;
				}
				// Tally the number of "artists"
				if ($element == 'Artist' || $element == 'Conductor' || $element == 'Performer') {
					$artistCount++;
				}
			} else {
				// All other tags
				$array[$element] = $value;
			}

			$line = strtok("\n");
		}

		// NOTE: Save for future use
		// Strip off trailing delimiter
		/*foreach($array as $key => $value) {
			if ($key == 'Genre' || $key == 'Artist' || $key == 'Conductor' || $key == 'Performer') {
				$array[$key] = rtrim($array[$key], '; ');
			}
		}*/

		$array['artist_count'] = $artistCount;
		return $array;
	}
}

// Format MPD status output
function formatMpdStatus($resp) {
	if (is_null($resp)) {
		debugLog('formatMpdStatus(): Returned null');
		return null;
	} else {
		$status = array();
		$line = strtok($resp, "\n");

		while ($line) {
			list($element, $value) = explode(': ', $line, 2);
			$status[$element] = $value;
			$line = strtok("\n");
		}

		// Elapsed time
		// Radio - time: 293:0, elapsed: 292.501, duration not present
		// Song  - time: 4:391, elapsed: 4.156, duration: 391.466
		// Podcast same as song.
		// If state is stop then time, elapsed and duration are not present
		// Time x:y where x = elapsed ss, y = duration ss
		$time = explode(':', $status['time']);

		if ($status['state'] == 'stop') {
			$percent = '0';
			$status['elapsed'] = '0';
			$status['time'] = '0';
		} else if (!isset($status['duration']) || $status['duration'] == 0) { // @ohinckel https: //github.com/moode-player/moode/pull/13
			// Radio, UPnP
			$percent = '0';
			$status['elapsed'] = $time[0];
			$status['time'] = $time[1];
		} else {
			// Tracks, Podcast
			if ($time[0] != '0') {
				$percent = round(($time[0] * 100) / $time[1]);
				$status['elapsed'] = $time[0];
				$status['time'] = $time[1];
			} else {
				$percent = '0';
				$status['elapsed'] = $time[0];
				$status['time'] = $time[1];
			}
		}

		$status['song_percent'] = $percent;
		$status['elapsed'] = $time[0];
		$status['time'] = $time[1];

		// dsd64:2, 44100:24:2
	 	$audioFormat = explode(':', $status['audio']);

        // Format
        $status['audio_format'] = strpos($audioFormat[0], 'dsd') !== false ? strtoupper($audioFormat[0]) : 'PCM';

        // Sample rate
	 	$status['audio_sample_rate'] = formatRate($audioFormat[0]);

		// Bit depth
		if ($status['audio_format'] != 'PCM') {
			// DSD
            $status['audio_sample_depth'] = '1';
		} else {
			// Workaround for AAC files that show "f" for bit depth, assume decoded to 24 bit
		 	$status['audio_sample_depth'] = $audioFormat[1] == 'f' ? '24' : $audioFormat[1];
		}

	 	// Channels
	 	if ($status['audio_format'] != 'PCM') {
            // DSD
	 		$status['audio_channels'] = formatChannels($audioFormat[1]);
	 	} else {
		 	$status['audio_channels'] = formatChannels($audioFormat[2]);
		}

		// Bitrate
		if (!isset($status['bitrate']) || trim($status['bitrate']) == '') {
			$status['bitrate'] = '0 bps';
		} else {
			if ($status['bitrate'] == '0') {
				$status['bitrate'] = '';
				// For aiff, wav files and some radio stations ex: Czech Radio Classic
			 	//$status['bitrate'] = number_format((( (float)$audioFormat[0] * (float)$status['audio_sample_depth'] * (float)$audioFormat[2] ) / 1000000), 3, '.', '');
			} else {
			 	$status['bitrate'] = strlen($status['bitrate']) < 4 ? $status['bitrate'] : substr($status['bitrate'], 0, 1) . '.' . substr($status['bitrate'], 1, 3) ;
			 	$status['bitrate'] .= strpos($status['bitrate'], '.') === false ? ' kbps' : ' Mbps';
			}
		}
	}
	return $status;
}

function formatMpdQueryResults($resp) {
	if (is_null($resp)) {
		debugLog('formatMpdQueryResults(): Returned null');
		return null;
	} else {
		$array = array();
		$line = strtok($resp,"\n");
		$idx = -1;

		while ($line) {
			list ($element, $value) = explode(': ', $line, 2);

			if ($element == 'file') {
				$idx++;
				$array[$idx]['file'] = $value;
				$array[$idx]['fileext'] = getSongFileExt($value);
			} else if ($element == 'directory') {
				$idx++;
				$dirIdx++; // Save directory index for further processing
				$array[$idx]['directory'] = $value;
				$coverHash = getSongFileExt($value) == 'cue' ? md5(dirname($value)) : md5($value);
				$array[$idx]['cover_hash'] = file_exists(THMCACHE_DIR . $coverHash  . '_sm.jpg') ? $coverHash : '';
			} else if ($element == 'playlist') {
				if (substr($value,0, 5) == 'RADIO' || strtolower(pathinfo($value, PATHINFO_EXTENSION)) == 'cue') {
					$idx++;
					$array[$idx]['file'] = $value;
					$array[$idx]['fileext'] = getSongFileExt($value);
				} else {
					$idx++;
					$array[$idx]['playlist'] = $value;
				}
			} else {
                $value = htmlspecialchars($value, ENT_NOQUOTES);
                $array[$idx][$element] = $value;
				$array[$idx]['TimeMMSS'] = formatSongTime($array[$idx]['Time']);
			}

			$line = strtok("\n");
		}

		// Put dirs on top
		if (isset($dirIdx) && isset($array[0]['file']) ) {
			$files = array_slice($array, 0, -$dirIdx);
            $dirs = array_slice($array, -$dirIdx);
            $array = array_merge($dirs, $files);
		}
	}

	return $array;
}

function parseMpdRespAsJSON($resp) {
	$array = array();
	$line = strtok($resp, "\n");

	while ($line) {
		array_push($array, $line);
		$line = strtok("\n");
	}

	return $array;
}

function parseMpdRespAsArray($resp) {
	$array = array();
	$line = strtok($resp, "\n");

	while ($line) {
		list($param, $value) = explode(': ', $line, 2);
		$array[$param] = $value;
		$line = strtok("\n");
	}

	return $array;
}

function parseLsinfoAsArray($resp) {
	$array = array();
	$array['Genre'] = array();
	$array['Artist'] = array();

	$line = strtok($resp, "\n");
	while ($line) {
		list($param, $value) = explode(': ', $line, 2);

		switch ($param) {
			case 'Genre':
				array_push($array['Genre'], $value);
				break;
			case 'Artist':
			case 'Performer':
			case 'Conductor':
				array_push($array['Artist'], $value);
				break;
			default:
				$array[$param] = $value;
				break;
		}

		$line = strtok("\n");
	}

	return $array;
}

// Turn MPD HTTP server on/off
function setMpdHttpd () {
	$cmd = $_SESSION['mpd_httpd'] == '1' ? 'mpc enable "' . HTTP_SERVER . '"' : 'mpc disable "' . HTTP_SERVER . '"';
	sysCmd($cmd);
}

// Ignore files
function setMpdIgnore($ignore, $fileType) {
	$mpdignoreFile = MPD_MUSICROOT . '.mpdignore';

	// Create empty file if not present
	if (is_file($mpdignoreFile) === false) {
		sysCmd('touch "' . $mpdignoreFile . '"');
		sysCmd('chmod 0755 "' . $mpdignoreFile . '"');
		sysCmd('chown root:root "' . $mpdignoreFile . '"');
	}

	// By type
	if ($fileType == 'cue') {
		sysCmd("sed -i '/^\*\.cue/d' " . $mpdignoreFile);
		if ($ignore == '1') {
			sysCmd('echo "*.cue" >> ' . $mpdignoreFile);
		}
	} else if ($fileType == 'moode') {
		sysCmd("sed -i '/LRMonoPhase4.flac/d' " . $mpdignoreFile);
		sysCmd("sed -i '/ReadyChime.flac/d' " . $mpdignoreFile);
		if ($ignore == '1') {
			sysCmd('echo "LRMonoPhase4.flac" >> ' . $mpdignoreFile);
			sysCmd('echo "ReadyChime.flac" >> ' . $mpdignoreFile);
		}
	}
}

// Create enhanced MPD metadata
function enhanceMetadata($current, $sock, $caller = '') {
    // NOTE: $current is output from getMpdStatus() in engine-mpd.php
	$song = getCurrentSong($sock);
	$current['file'] = $song['file'];
	$current['thumb_hash'] = '';

	// NOTE: Any of these might be empty ''
	$current['genre'] = $song['Genre'];
	$current['track'] = $song['Track'];
    $current['date'] = getTrackYear($song);
	$current['composer'] = $song['Composer'];
	$current['conductor'] = $song['Conductor'];
	$current['performer'] = $song['Performer'];
	$current['albumartist'] = $song['AlbumArtist'];
	$current['artist_count'] = $song['artist_count'];
	$current['comment'] = $song['Comment'];

	// Cover hash and mapped db volume
	if ($caller == 'engine_mpd_php') {
		$current['cover_art_hash'] = getCoverHash($current['file']);
		$current['mapped_db_vol'] = getMappedDbVol();
	}

	if ($current['file'] == null) {
		// Null can happen when reloading sources during development
		$current['artist'] = '';
		$current['title'] = '';
		$current['album'] = '';
		$current['comment'] = '';
		$current['coverurl'] = DEFAULT_ALBUM_COVER;
	} else {
		// Only do this code block once for a given file
		if ($current['file'] != $_SESSION['currentfile']) {
			$current['encoded'] = getEncodedAt($song, 'default'); // Encoded bit depth and sample rate
			phpSession('open');
			$_SESSION['currentfile'] = $current['file'];
			$_SESSION['currentencoded'] = $current['encoded'];
			if ($caller == 'engine_mpd_php') {
				phpSession('close');
			}
		} else {
			$current['encoded'] = $_SESSION['currentencoded'];
		}

		// File extension
		$ext = getSongFileExt($song['file']);

		if (isset($song['Name']) && ($ext == 'm4a' || $ext == 'aif' || $ext == 'aiff')) {
			// iTunes aac or aiff file
			$current['artist'] = isset($song['Artist']) ? $song['Artist'] : 'Unknown artist';
			$current['title'] = $song['Name'];
			$current['album'] = isset($song['Album']) ? $song['Album'] : 'Unknown album';
			$current['coverurl'] = '/coverart.php/' . rawurlencode($song['file']);
			$current['thumb_hash'] = md5(dirname($song['file']));
		} else if (substr($song['file'], 0, 4) == 'http' && !isset($current['duration'])) {
			// Radio station
			$current['artist'] = 'Radio station';
			$current['hidef'] = ($_SESSION[$song['file']]['bitrate'] > 128 || $_SESSION[$song['file']]['format'] == 'FLAC') ? 'yes' : 'no';

			if (!isset($song['Title']) ||
                trim($song['Title']) == '-' || // NTS can return just a dash in its Title tag
                substr($song['Title'], 0, 4) == 'BBC ' || // BBC just returns the station name in the Title tag
                trim($song['Title']) == '') {
				$current['title'] = DEFAULT_RADIO_TITLE;
			} else {
				// Use custom name for certain stations if needed
				// EX: $current['title'] = strpos($song['Title'], 'Radio Active FM') !== false ? $song['file'] : $song['Title'];
				$current['title'] = $song['Title'];
			}

			if (isset($_SESSION[$song['file']])) {
				// Use transmitted name for SOMA FM stations
				$current['album'] = substr($_SESSION[$song['file']]['name'], 0, 4) == 'Soma' ?
                    (isset($song['Name']) ? $song['Name'] : $_SESSION[$song['file']]['name']) :
                    $_SESSION[$song['file']]['name'];
				// Include original station name
				if ($_SESSION[$song['file']]['logo'] == 'local') {
					// Local logo image
					$current['coverurl'] = rawurlencode(LOGO_ROOT_DIR . $_SESSION[$song['file']]['name'] . '.jpg');
				} else {
					// URL logo image
					$current['coverurl'] = rawurlencode($_SESSION[$song['file']]['logo']);
				}
				// Track cover from Apple Music (iTunes API)
				//workerLog('enhanceMetadata(): BEFORE: ' . $current['state'] . ' | ' . $current['title']);
				//workerLog('enhanceMetadata(): - URL:  ' . substr($current['coverurl'], 0, 64));
				//workerLog('enhanceMetadata(): - Hash: ' . $current['cover_art_hash']);
				if ($current['title'] != DEFAULT_RADIO_TITLE) {
					if ($_SESSION['radio_track_covers'] == 'Yes') {
						if ($current['state'] == 'play') {
							$trackCoverUrl = getTrackCoverUrl($current['title']);
							if (str_contains($trackCoverUrl, 'https://')) {
								$current['coverurl'] = $trackCoverUrl;
								$current['cover_art_hash'] = 'trackcover';
							}
						} else {
							$current['cover_art_hash'] = 'radiologo';
						}
					}
				}
				//workerLog('enhanceMetadata(): AFTER:');
				//workerLog('enhanceMetadata(): - URL:  ' . substr($current['coverurl'], 0, 64));
				//workerLog('enhanceMetadata(): - Hash: ' . $current['cover_art_hash']);

                if ($_SESSION[$song['file']]['bitrate'] == '') {
                    $current['bitrate'] == '';
                } else {
                    $current['bitrate'] = $_SESSION[$song['file']]['bitrate'] . ' kbps';
                }
			} else {
				// Not in radio station table, use transmitted name or 'Unknown'
				$current['album'] = isset($song['Name']) ? $song['Name'] : 'Unknown station';
				$current['coverurl'] = DEFAULT_RADIO_COVER;
                $current['bitrate'] = '';
			}
            //workerLog(print_r($song, true));
		} else {
			// Song file, UPnP URL or Podcast
			$current['artist'] = isset($song['Artist']) ? $song['Artist'] : 'Unknown artist';
			$current['title'] = isset($song['Title']) ? $song['Title'] : pathinfo(basename($song['file']), PATHINFO_FILENAME);
			$current['album'] = isset($song['Album']) ? $song['Album'] : 'Unknown album';
			$current['disc'] = isset($song['Disc']) ? $song['Disc'] : 'Disc tag missing';
			if (substr($song['file'], 0, 4) == 'http') {
				if (isset($_SESSION[$song['file']])) {
					// Podcast
					$current['coverurl'] = LOGO_ROOT_DIR . $_SESSION[$song['file']]['name'] . ".jpg";
					$current['artist'] = 'Radio station';
					$current['album'] = $_SESSION[$song['file']]['name'];
				} else {
					// UPnP file
					$current['coverurl'] = getUpnpCoverUrl();
				}
			} else {
				// Song file
				$current['coverurl'] = '/coverart.php/' . rawurlencode($song['file']);
				$level = stripos(dirname($song['file']), '.cue', -4) === false ? 1 : 2;
				$current['thumb_hash'] = md5(dirname($song['file'], $level));
			}

			// DEBUG
			/*if (substr($song['file'], 0, 4) == 'http') {
				workerLog('enhanceMetadata(): UPnP url');
			} else {
				workerLog('enhanceMetadata(): Song file');
			}*/
		}

		// Determine badging
		// NOTE: This is modeled after the code in getEncodedAt()
		if (!(substr($song['file'], 0, 4) == 'http' && !isset($current['duration']))) { // Not a radio station
            if (substr($song['file'], 0, 4) == 'http') { // UPnP file
                // [0] bits, [1] rate, [2] channels [3] ext, [4] duration
                $result = sysCmd('mediainfo --Inform="Audio;file:///var/www/util/mediainfo.tpl" ' . '"' . $song['file'] . '"');
                $format[0] = $result[1]; // rate
                $format[1] = $result[0]; // bits
                $format[2] = $result[2]; // channels
            } else { // Song file
                sendMpdCmd($sock, 'lsinfo "' . $song['file'] . '"');
    			$songData = parseDelimFile(readMpdResp($sock), ': ');
                // [0] rate, [1] bits, [2] channels
    			$format = explode(':', $songData['Format']);
            }

			if ($ext == 'mp3' || ($format[1] == 'f' && $format[2] <= 2)) {
				// Lossy
				$current['hidef'] = 'no';
			} else if ($ext == 'dsf' || $ext == 'dff') {
				// DSD
				$current['hidef'] = 'yes';
            } else if ($ext == 'wv') {
				// WavPack DSD
				$current['hidef'] = 'yes';
			} else {
				// PCM or Multichannel PCM
				$current['hidef'] = ($format[1] == 'f' || $format[1] > ALBUM_BIT_DEPTH_THRESHOLD ||
					$format[0] > ALBUM_SAMPLE_RATE_THRESHOLD) ? 'yes' : 'no';
			}
		}

        // ALSA output format (or 'Not playing')
        $current['output'] = getALSAOutputFormat($current['state'], $current['audio_sample_rate']);
	}

	return $current;
}

function getTrackCoverUrl($trackTitle) {
	$trackTitle = html_entity_decode($trackTitle);
	$parts = explode(' - ', $trackTitle); // $parts[0]=Artist name, $parts[1]=Track title
	$query = '?term=' . urlencode($parts[0] . ' ' . $parts[1]) . '&media=music&entity=musicTrack&limit=1';
	$apiUrl = ITUNES_API_BASE_URL . $query;

	$result = file_get_contents($apiUrl);
	if ($result === false) {
		$msg = 'Query failed: ' . $trackTitle;
		$coverUrl = '';
	} else {
		$resultArray = json_decode($result, true);
		if ($resultArray['resultCount'] == '0') {
			$msg = 'Query 0 found: ' . $trackTitle;
			$coverUrl = '';
		} else {
			$msg = 'Query successful: ' . $trackTitle;
			$coverUrl = str_replace('100x100', '1000x1000', $resultArray['results'][0]['artworkUrl100']);
		}
	}
	//debugLog('getTrackCoverUrl(): ' .
	//	$msg . "\n" .
	//	$coverUrl . (!empty($coverUrl) ? "\n" : '') .
	//	$apiUrl);

	return $coverUrl;
}

function getUpnpCoverUrl() {
	$mode = sqlQuery("SELECT value FROM cfg_upnp WHERE param='upnpav'", sqlConnect())[0]['value'] == 1 ? 'upnpav' : 'openhome';
	$result = sysCmd('/var/www/util/upnp_albumart.py "' . $_SESSION['upnpname'] . '" '. $mode);
	// If multiple url's are returned, use the first
	return explode(',', $result[0])[0];
}

function getMappedDbVol() {
	phpSession('open_ro');

	if (CamillaDsp::isMPD2CamillaDSPVolSyncEnabled()) {
		// CamillaDSP volume
		$volKnob = sqlRead('cfg_system', sqlConnect(), 'volknob')[0]['value'];
		$dynamicRange = $_SESSION['camilladsp_volume_range'];
		$mappedDbVol = CamillaDsp::calcMappedDbVol($volKnob, $dynamicRange);
		$mappedDbVol = round($mappedDbVol, 1);
		if ($mappedDbVol == '0') {
			$mappedDbVol = '0dB';
		} else if ($mappedDbVol == '-120') {
			$mappedDbVol = '-120dB';
		} else {
			$mappedDbVol = str_contains($mappedDbVol, '.') ? $mappedDbVol . 'dB' : $mappedDbVol . '.0dB';
		}
	} else {
		// MPD volume
		$mappedDbVol = sysCmd('amixer -c ' . $_SESSION['cardnum'] . ' sget "' . $_SESSION['amixname'] . '" | ' .
			"awk -F\"[][]\" '/dB/ {print $4; count++; if (count==1) exit}'")[0];
		if (empty($mappedDbVol) || $_SESSION['mpdmixer'] == 'software' || $_SESSION['mpdmixer'] == 'null') {
			$mappedDbVol = '';
		} else {
			$mappedDbVol = number_format(rtrim($mappedDbVol, 'dB'), 1);
			if ($mappedDbVol == 0) {
				$mappedDbVol = '0dB';
			} else {
				$mappedDbVol = ($mappedDbVol <= -127 ? '-127' : $mappedDbVol) . 'dB';
			}
		}
	}

	return $mappedDbVol;
}

function getCoverHash($file) {
	$ext = getSongFileExt($file);

	if (substr($file, 0, 4) != 'http' &&  $ext != 'dsf' && $ext != 'dff') {
        // PCM song files only
    	phpSession('open_ro');
    	$searchPriority = $_SESSION['library_covsearchpri'];

		$path = MPD_MUSICROOT . $file;
		$path = ensureAudioFile($path);
		$hash = false;

		// file: embedded cover
		if ($searchPriority == 'Embedded cover') { // Embedded first
			$hash = getHash($path);
		}

		if ($hash === false) {
			if (is_dir($path)) {
				// dir: cover image file
				if (substr($path, -1) !== '/') {$path .= '/';}
				$hash = parseDir($path);
			} else {
				// file: cover image file in containing dir
				$dirpath = pathinfo($path, PATHINFO_DIRNAME) . '/';
				$hash = parseDir($dirpath);
			}

			if ($hash === false) {
				if ($searchPriority == 'Cover image file') { // Embedded last
					$hash = getHash($path);
				}
			}

			if ($hash === false) {
				// Nothing found
				$hash = 'getCoverHash(): no cover found';
			}
		}
	} else {
        // This is so the front-end detects a change in the cover hash
        $hash = rand();
	}

	return $hash;
}

// Modified versions of coverart.php functions
// (C) 2015 Andreas Goetz
function rtnHash($mime, $hash) {
	switch (strtolower($mime)) {
		case "image/gif":
		case "image/jpg":
		case "image/jpeg":
		case "image/png":
		case "image/tif":
		case "image/tiff":
			return $hash;
		default :
			break;
	}

	return false;
}
function getHash($path) {
	if (!file_exists($path)) {
		return false;
	}

	$hash = false;
	$ext = pathinfo($path, PATHINFO_EXTENSION);

	switch (strtolower($ext)) {
		// Image file
		case 'gif':
		case 'jpg':
		case 'jpeg':
		case 'png':
		case 'tif':
		case 'tiff':
			$stat = stat($path);
			$hash = md5((string)file_get_contents($path, 1024) . (string)$stat['size']);
			break;

		// Embedded images
		case 'mp3':
			require_once __DIR__ . '/Zend/Media/Id3v2.php';
			try {
				$id3v2 = new Zend_Media_Id3v2($path, array('hash_only' => true));

				if (isset($id3v2->apic)) {
					$hash = rtnHash($id3v2->apic->mimeType, $id3v2->apic->imageData);
				}
			} catch (Zend_Media_Id3_Exception $e) {
				//workerLog('getCoverHash(): Zend media exception: ' . $e->getMessage());
			}
			break;

		case 'flac':
			require_once __DIR__ . '/Zend/Media/Flac.php';
			try {
				$flac = new Zend_Media_Flac($path, $hash_only = true);

				if ($flac->hasMetadataBlock(Zend_Media_Flac::PICTURE)) {
					$picture = $flac->getPicture();
					$hash = rtnHash($picture->getMimeType(), $picture->getData());
				}
			} catch (Zend_Media_Flac_Exception $e) {
				//workerLog('getCoverHash(): Zend media exception: ' . $e->getMessage());
			}
			break;

        case 'm4a':
            require_once __DIR__ . '/Zend/Media/Iso14496.php';
            try {
                $iso14496 = new Zend_Media_Iso14496($path, array('hash_only' => true));
                $picture = $iso14496->moov->udta->meta->ilst->covr;
                $mime = ($picture->getFlags() & Zend_Media_Iso14496_Box_Data::JPEG) == Zend_Media_Iso14496_Box_Data::JPEG
                    ? 'image/jpeg'
                    : (
                        ($picture->getFlags() & Zend_Media_Iso14496_Box_Data::PNG) == Zend_Media_Iso14496_Box_Data::PNG
                        ? 'image/png'
                        : null
                    );
                if ($mime) {
                    $hash = rtnHash($mime, md5($picture->getValue()));
                }
            } catch (Zend_Media_Iso14496_Exception $e) {
				//workerLog('getCoverHash(): Zend media exception: ' . $e->getMessage());
            }
            break;
	}

	return $hash;
}
function parseDir($path) {
	// Default cover files
	$covers = array(
		'Cover.jpg', 'cover.jpg', 'Cover.jpeg', 'cover.jpeg', 'Cover.png', 'cover.png', 'Cover.tif', 'cover.tif', 'Cover.tiff', 'cover.tiff',
		'Folder.jpg', 'folder.jpg', 'Folder.jpeg', 'folder.jpeg', 'Folder.png', 'folder.png', 'Folder.tif', 'folder.tif', 'Folder.tiff', 'folder.tiff'
	);
	foreach ($covers as $file) {
		$result = getHash($path . $file);
		if ($result !== false) {
			break;
		}
	}
	// All other image files
	$extensions = array('jpg', 'jpeg', 'png', 'tif', 'tiff');
	$path = str_replace('[', '\[', $path);
	$path = str_replace(']', '\]', $path);
	foreach (glob($path . '*') as $file) {
		if (is_file($file) && in_array(strtolower(pathinfo($file, PATHINFO_EXTENSION)), $extensions)) {
			$result = getHash($file);
			if ($result !== false) {
				break;
			}
		}
	}

	return $result;
}
