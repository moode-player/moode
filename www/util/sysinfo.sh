#!/bin/bash
#
# SPDX-License-Identifier: GPL-3.0-or-later
# Copyright 2014 The moOde audio player project / Tim Curtis
# Copyright 2017 Klaus Shultz (https://soundcheck-audio.blogspot.de)
#

# Check for sudo
[[ $EUID -ne 0 ]] && { echo "Use sudo to run the script" ; exit 1 ; } ;

SYSTEM_PARAMETERS() {
	echo -e "\n\c"
	echo -e "H A R D W A R E   A N D   O S"
	echo -e "\nmoOde release\t\t= $moode_rel\c"
	echo -e "\nRaspiOS\t\t\t= $RASPIOS_VER\c"
	echo -e "\nLinux kernel\t\t= $KERNEL_VER\c"
	echo -e "\nPi model\t\t= $HDWRREV\c"
	echo -e "\nArchitecture\t\t= $ARCH\c"
	echo -e "\nHome directory\t\t= /home/$HOME_DIR\c"
	echo -e "\nSystem uptime\t\t= $UPTIME\c"
	echo -e "\nTimezone\t\t= $timezone\c"
	echo -e "\nCurrent time\t\t= $NOW\c"
	echo -e "\n\c"
	echo -e "\nHost name\t\t= $HOSTNAME\c"
	echo -e "\nEthernet address\t= $ETH0IP\c"
	echo -e "\nEthernet MAC\t\t= $ETH0MAC\c"
	echo -e "\nWLAN address\t\t= $WLAN0IP\c"
	echo -e "\nWLAN MAC\t\t= $WLAN0MAC\c"
	echo -e "\nWLAN country\t\t= $wlancountry\c"
	echo -e "\nWLAN AP SSID\t\t= $apdssid\c"
	echo -e "\nWLAN AP addr\t\t= $ap_network_addr\c"
	echo -e "\n\c"
	echo -e "\nSoC identifier\t\t= $SOC\c"
	echo -e "\nCore count\t\t= $CORES\c"
	echo -e "\nKernel timer freq\t= $HZ Hz\c"
	echo -e "\nWarranty\t\t= $WARRANTY\c"
	echo -e "\n\c"
	echo -e "\nRootFS size\t\t= $ROOTSIZE\c"
	echo -e "\nRootFS used\t\t= $ROOTUSED\c"
	echo -e "\nRootFS available\t= $ROOTAVAIL\c"
	echo -e "\nMemory total\t\t= $MEM_TOTAL MB\c"
	echo -e "\nMemory free\t\t= $MEM_AVAIL MB\c"
	echo -e "\nMemory used\t\t= $MEM_USED MB\c"
	echo -e "\nSoC temperature\t\t= $TEMP\c"
	echo -e "\nThrottled bitmask\t= $THROTTLED_BITMASK\c"
	echo -e "\nThrottled text\t\t= $THROTTLED_TEXT\c"
	echo -e "\n\c"
	echo -e "\nWorker responsiveness\t= $WORKER_RESPONSIVENESS\c"
	echo -e "\nCPU governor\t\t= $CPUGOV\c"
	echo -e "\nPCI express\t\t= $PCI_EXPRESS\c"
	echo -e "\nReduce power\t\t= $REDUCE_POWER (Pi-5 only)\c"
	echo -e "\nPi integrated WiFi\t= $piwifi\c"
	echo -e "\nPi integrated BT\t= $pibt\c"
	echo -e "\nHDMI output\t\t= $HDMI\c"
	echo -e "\nLED state\t\t= $led_state\c"
	echo -e "\nIP addr timeout\t\t= $ipaddr_timeout (secs)\c"
	echo -e "\nEthernet check\t\t= $eth0chk\c"
	if [ $(($feat_bitmask & $FEAT_HTTPS)) -ne 0 ]; then
		echo -e "\nHTTPS mode\t\t= $HTTPS_MODE\c"
	fi
	echo -e "\nSSH term server\t\t= $shellinabox\c"
	echo -e "\nLog to RAM\t\t= $LOG2RAM\c"
	echo -e "\n\c"
	echo -e "\nSMB file sharing\t= $fs_smb\c"
	echo -e "\nNFS file sharing\t= $fs_nfs\c"
	echo -e "\nNFS access\t\t= $fs_nfs_access\c"
	echo -e "\nNFS options\t\t= $fs_nfs_options\c"
	echo -e "\n\c"
	echo -e "\nPHP-FPM version\t\t= $PHPVER\c"
	echo -e "\nNGINX version\t\t= $NGINXVER\c"
	echo -e "\nSQLite3 version\t\t= $SQLITEVER\c"
	echo -e "\nPython version\t\t= $PYTHON_VER\c"
	echo -e "\nNodejs version\t\t= $NODEJS_VER\c"
	echo -e "\nPyLGPIO version\t\t= $PYTHON_LGPIO_VER\n"
}

AUDIO_PARAMETERS() {
	ALSAVER="$(dpkg -l | awk '/libasound2:/ { print  $3 }')"
 	SOXVER="$(dpkg -l | awk '/libsoxr0:/ { print  $3 }')"
	CDSPVER="$(camilladsp --version | cut -d' ' -f 2)"
	BITS="$(cat /proc/asound/card0/pcm0p/sub0/hw_params | grep -w format | cut -f 2 -d " ")"
	RATE="$(cat /proc/asound/card0/pcm0p/sub0/hw_params | grep -w rate | cut -f 2 -d " ")"
	[[ "$BITS" = "" ]] && OUTSTREAM="Closed" || OUTSTREAM="$BITS / $RATE"
	[[ $alsavolume = "none" ]] && hwvol="No" || hwvol="Yes"
	[[ "$amixname" = "" ]] && volmixer="None" || volmixer=$amixname

	echo -e "A U D I O   C O N F I G U R A T I O N"
	echo -e "\nAudio device\t\t= $adevname\c"
	echo -e "\nMixer name\t\t= $volmixer\c"
	echo -e "\nHardware mixer\t\t= $hwvol\c"
	echo -e "\nSupported formats\t= $supported_formats\c"
	echo -e "\nALSA max volume\t\t= $alsavolume_max\c"
	echo -e "\nALSA output mode\t= $alsa_output_mode\c"
	echo -e "\nALSA loopback\t\t= $alsa_loopback\c"
	echo -e "\nMPD max volume\t\t= $volume_mpd_max\c"
	echo -e "\nVolume step limit\t= $volume_step_limit\c"
	echo -e "\nDisplay dB volume\t= $volume_db_display\c"
	echo -e "\nAudio source\t\t= $audioin\c"
	echo -e "\nOutput device\t\t= $audioout\c"
	echo -e "\nResume MPD\t\t= $rsmafterinp\c"
	echo -e "\nVolume knob\t\t= $volknob\c"
	echo -e "\nVolume mute\t\t= $volmute\c"
	echo -e "\nSaved MPD vol\t\t= $volknob_mpd\c"
	echo -e "\nPreamp volume\t\t= $volknob_preamp\c"
	echo -e "\nALSA version\t\t= $ALSAVER\c"
	echo -e "\nSoX version\t\t= $SOXVER\c"
	echo -e "\nCDSP verion\t\t= $CDSPVER\c"
	echo -e "\n\c"
	if [ $(($feat_bitmask & $FEAT_BLUETOOTH)) -ne 0 ]; then
		echo -e "\nBluetooth controller\t= $btsvc\c"
	fi
	if [ $(($feat_bitmask & $FEAT_AIRPLAY)) -ne 0 ]; then
		echo -e "\nAirPlay receiver\t= $airplaysvc\c"
	fi
	if [ $(($feat_bitmask & $FEAT_SPOTIFY)) -ne 0 ]; then
		echo -e "\nSpotify receiver\t= $spotifysvc\c"
	fi
	if [ $(($feat_bitmask & $FEAT_SQUEEZELITE)) -ne 0 ]; then
		echo -e "\nSqueezelite\t\t= $slsvc\c"
	fi
	if [ $(($feat_bitmask & $FEAT_UPMPDCLI)) -ne 0 ]; then
		echo -e "\nUPnP client\t\t= $upnpsvc\c"
	fi
	if [ $(($feat_bitmask & $FEAT_MINIDLNA)) -ne 0 ]; then
		echo -e "\nDLNA server\t\t= $dlnasvc\c"
	fi
	if [ $(($feat_bitmask & $FEAT_PLEXAMP)) -ne 0 ]; then
		echo -e "\nPlexamp\t\t\t= $pasvc\c"
	fi
	if [ $(($feat_bitmask & $FEAT_ROONBRIDGE)) -ne 0 ]; then
		echo -e "\nRoonBridge\t\t= $rbsvc\c"
	fi
	if [ $(($feat_bitmask & $FEAT_GPIO)) -ne 0 ]; then
		echo -e "\nGPIO button handler\t= $gpio_svc\c"
	fi
	if [ $(($feat_bitmask & $FEAT_MULTIROOM)) -ne 0 ]; then
		echo -e "\nMultiroom sender\t= $multiroom_tx\c"
		echo -e "\nMultiroom receiver\t= $multiroom_rx\c"
		echo -e "\nReceiver hosts\t\t= $rx_hostnames\c"
		echo -e "\nReceiver addrs\t\t= $rx_addresses\c"
	fi
	echo -e "\n\c"
	echo -e "\nAuto-shuffle\t\t= $ashufflesvc\c"
	echo -e "\nAshuffle mode\t\t= $ashuffle_mode\c"
	echo -e "\nAshuffle window\t\t= $ashuffle_window\c"
	echo -e "\nAshuffle filter\t\t= $ashuffle_filter\c"
	echo -e "\nAshuffle exclude\t= $ashuffle_exclude\c"
	echo -e "\nAutoplay\t\t= $autoplay\c"
	echo -e "\nRotary encoder\t\t= $rotaryenc\c"
	echo -e "\nEncoder params\t\t= $rotenc_params\c"
	echo -e "\nUSB volume knob\t\t= $usb_volknob\c"
	echo -e "\nPolarity inversion\t= $invert_polarity\c"
	echo -e "\nCrossfeed\t\t= $crossfeed\c"
	echo -e "\nCrossfade\t\t= $mpdcrossfade\c"
	echo -e "\nParametric EQ\t\t= $eqfa12p\c"
	echo -e "\nGraphic EQ\t\t= $alsaequal\c"
	echo -e "\nCamillaDSP\t\t= $camilladsp\c"
	echo -e "\nCamillaDSP volsync\t= $camilladsp_volume_sync\c"
	echo -e "\nMPD httpd\t\t= $mpd_httpd\c"
	echo -e "\nIgnore CUE files\t= $cuefiles_ignore\n"
}

APPEARANCE_SETTINGS() {
	echo -e "P R E F E R E N C E S"
	echo -e "\nAppearance\c"
	echo -e "\n----------------------\c"
	echo -e "\nTheme\t\t\t= $themename\c"
	echo -e "\nAccent color\t\t= $accentcolor\c"
	echo -e "\nAlpha blend\t\t= $alphablend\c"
	if [ -f //var/local/www/imagesw/bgimage.jpg ]; then bgimage="Yes"; else bgimage="No"; fi
	echo -e "\nBackground image\t= $bgimage\c"
	echo -e "\nCover backdrop\t\t= $cover_backdrop\c"
	echo -e "\nCover blur\t\t= $cover_blur\c"
	echo -e "\nCover scale\t\t= $cover_scale\c"
	echo -e "\nRenderer backdrop\t= $renderer_backdrop\c"
	echo -e "\nFont size\t\t= $font_size\c"
	echo -e "\nNative lazyload\t\t= $native_lazyload\c"
	echo -e "\n\nPlayback\c"
	echo -e "\n----------------------\c"
	echo -e "\nShow Queue thumbs\t= $playlist_art\c"
	echo -e "\nShow Now-playing icon\t= $show_npicon\c"
	echo -e "\nShow CoverView playbar\t= $show_cvpb\c"
	echo -e "\nShow extra metadata\t= $xtagdisp\c"
	echo -e "\nSearch site\t\t= $search_site\c"
	echo -e "\nPlayback history log\t= $playhist\c"
	echo -e "\n\nLibrary\c"
	echo -e "\n----------------------\c"
	echo -e "\nOne touch album\t\t= $library_onetouch_album\c"
	echo -e "\nOne touch radio\t\t= $library_onetouch_radio\c"
	echo -e "\nOne touch playlist\t= $library_onetouch_pl\c"
	echo -e "\nOne touch ralbum\t= $library_onetouch_ralbum\c"
	echo -e "\nAlbumview sort order\t= by $library_albumview_sort\c"
	echo -e "\nTagview sort order\t= by $library_tagview_sort\c"
	echo -e "\nTrack play\t\t= $library_track_play\c"
	echo -e "\nRecently added\t\t= $library_recently_added\c"
	echo -e "\nShow sample rate\t= $library_encoded_at\c"
	echo -e "\nCover search pri\t= $library_covsearchpri\c"
	echo -e "\nPixel ratio\t\t= $library_pixelratio\c"
	echo -e "\nThumbgen scan fmts\t= $library_thmgen_scan\c"
	echo -e "\nThumbnail resolution\t= $library_hiresthm\c"
	echo -e "\nThumbnail columns\t= $library_thumbnail_columns\c"
	echo -e "\n\nLibrary (Advanced)\c"
	echo -e "\n----------------------\c"
	echo -e "\nTag view genre\t\t= $library_tagview_genres\c"
	echo -e "\nTag view artist\t\t= $library_tagview_artist\c"
	echo -e "\nAlbum key\t\t= $album_key\c"
	echo -e "\nInclude comment tag\t= $include_comment_tag\c"
	echo -e "\nLibrary filter\t\t= $library_flatlist_filter\c"
	echo -e "\nLibrary filter str\t= $library_flatlist_filter_str\c"
	echo -e "\nIgnore articles\t\t= $ignore_articles\c"
	echo -e "\nShow tagview genres\t= $library_show_genres\c"
	echo -e "\nShow tagview covers\t= $library_tagview_covers\c"
	echo -e "\nEllipsis limited text\t= $library_ellipsis_limited_text\c"
	echo -e "\nUTF8 character filter\t= $library_utf8rep\c"
	echo -e "\n\nCoverView\c"
	echo -e "\n----------------------\c"
	echo -e "\nTimed display\t\t= $scnsaver_timeout\c"
	echo -e "\nMPD override\t\t= $scnsaver_whenplaying\c"
	echo -e "\nAutomatic display\t= $auto_coverview\c"
	echo -e "\nBackdrop style\t\t= $scnsaver_style\c"
	echo -e "\nDisplay mode\t\t= $scnsaver_mode\c"
	echo -e "\nLayout\t\t\t= $scnsaver_layout\c"
	echo -e "\nExtra metadata\t\t= $scnsaver_xmeta\n"
}

RADIO_MANAGER_SETTINGS() {
	echo -e "R A D I O   M A N A G E R"
	echo -e "\nSort tag\t\t= $rv_sort_tag\c"
	echo -e "\nGroup method\t\t= $rv_group_method\c"
	echo -e "\nShow moOde stations\t= $rv_show_moode\c"
	echo -e "\nShow other stations\t= $rv_show_other\c"
	rv_stream_monitor=$(moodeutl -d -gv "mpd_monitor_svc")
	if [ $(($feat_bitmask & $FEAT_RECORDER)) -ne 0 ]; then
		echo -e "\nStream monitor\t\t= $rv_stream_monitor\c"
		echo -e "\nRecorder status\t\t= $rv_recorder_status\c"
		echo -e "\nRecorder storage\t= $rv_recorder_storage\n"
	else
		echo -e "\nStream monitor\t\t= $rv_stream_monitor\n"
	fi
}

PLAYLIST_MANAGER_SETTINGS() {
	echo -e "P L A Y L I S T   M A N A G E R"
	echo -e "\nSort tag\t\t= $plv_sort_tag\c"
	echo -e "\nGroup method\t\t= $plv_group_method\n"
}

MPD_SETTINGS() {
	echo -e "M P D"
	echo -e "\nVersion\t\t\t= $(mpd -V | grep 'Music Player Daemon' | awk '{print $4}')\c"
	echo -e "\nVolume type\t\t= $mixer_type\c"
	echo -e "\nSoX resampling\t\t= $audio_output_format\c"
	echo -e "\nSelective resampling\t= $selective_resample_mode\c"
	echo -e "\nSoX quality\t\t= $sox_quality\c"
	if [[ $sox_quality = "custom" ]]; then
		echo -e "\nPrecision\t\t= $sox_precision\c"
		echo -e "\nPhase response\t\t= $sox_phase_response\c"
		echo -e "\nPassband end\t\t= $sox_passband_end\c"
		echo -e "\nStopband begin\t\t= $sox_stopband_begin\c"
		echo -e "\nAttenuation\t\t= $sox_attenuation\c"
		echo -e "\nFlags\t\t\t= $sox_flags\c"
	fi
	echo -e "\nSoX multithreading\t= $sox_multithreading\c"
	echo -e "\nDSD over PCM (DoP)\t= $dop\c"
	echo -e "\nReplaygain\t\t= $replaygain\c"
	echo -e "\nReplaygain preamp\t= $replaygain_preamp\c"
	echo -e "\nVolume normalization\t= $volume_normalization\c"
	echo -e "\nAudio buffer\t\t= $audio_buffer_size (MB)\c"
	echo -e "\nOutput buffer size\t= $max_output_buffer_size (MB)\c"
	echo -e "\nMax playlist items\t= $max_playlist_length\c"
	echo -e "\nInput cache\t\t= $input_cache\c"
	echo -e "\nDevice buffer\t\t= $(( $buffer_time / 1000 )) (ms)\c"
	echo -e "\nDevice period\t\t= $(( $buffer_time / 4000 )) (ms)\n"
	#echo -e "\nALSA auto-resample\t= $auto_resample\c"
	#echo -e "\nALSA auto-channels\t= $auto_channels\c"
	#echo -e "\nALSA auto-format\t= $auto_format\c"
}
RENDERER_SETTINGS() {
	if [ $(($feat_bitmask & $FEAT_BLUETOOTH)) -ne 0 ]; then
		echo -e "B L U E T O O T H"
		echo -e "\nVersion\t\t\t= $BLUETOOTH_VER\c"
		echo -e "\nBluealsa\t\t= $BLUEALSA_VER\c"
		echo -e "\nPi-Bluetooth\t\t= $PI_BLUETOOTH_VER\c"
		echo -e "\nPairing agent\t\t= $PARING_AGENT_VER (bluez-tools)\c"
		echo -e "\nPIN code\t\t= $BT_PIN_CODE\c"
		echo -e "\nALSA max volume\t\t= $ALSAVOLUME_MAX_BT%\c"
		echo -e "\nCDSP max volume\t\t= $CDSPVOLUME_MAX_BT dB\c"
		echo -e "\nResume MPD\t\t= $rsmafterbt\c"
		echo -e "\nPCM buffer time\t\t= $bluez_pcm_buffer ($micro_symbol)\c"
		echo -e "\nALSA output mode\t= $ALSA_OUTPUT_MODE_BT\n"
	fi

	if [ $(($feat_bitmask & $FEAT_AIRPLAY)) -ne 0 ]; then
		SPSVER="$(shairport-sync -V | cut -f 1 -d '-')"
		echo -e "A I R P L A Y"
		echo -e "\nVersion\t\t\t= $SPSVER\c"
		echo -e "\nFriendly name\t\t= $airplayname\c"
		echo -e "\nInterpolation\t\t= $interpolation\c"
		echo -e "\nOutput bit depth\t= $output_format\c"
		echo -e "\nOutput sample rate\t= $output_rate\c"
		echo -e "\nSession interruption\t= $allow_session_interruption\c"
		echo -e "\nSession timeout\t\t= $session_timeout (ms)\c"
		echo -e "\nLatency offset\t\t= $audio_backend_latency_offset_in_seconds (secs)\c"
		echo -e "\nAudio buffer\t\t= $audio_backend_buffer_desired_length_in_seconds (secs)\c"
		echo -e "\nResume MPD\t\t= $rsmafterapl\n"
	fi

	if [ $(($feat_bitmask & $FEAT_SPOTIFY)) -ne 0 ]; then
		SPOTVER="$(librespot --version | awk -F" " '{print $2}')"
		echo -e "S P O T I F Y   C O N N E C T"
		echo -e "\nVersion\t\t\t= $SPOTVER\c"
		echo -e "\nFriendly name\t\t= $spotifyname\c"
		echo -e "\nBitrate (kbps)\t\t= $spot_bitrate\c"
		echo -e "\nFormat\t\t\t= $spot_format\c"
		echo -e "\nDither\t\t\t= $spot_dither\c"
		echo -e "\nVolume curve\t\t= $spot_volume_curve\c"
		echo -e "\nVolume range (dB)\t= $spot_volume_range\c"
		echo -e "\nInitial volume (%)\t= $spot_initial_volume\c"
		echo -e "\nVolume normalization\t= $spot_volume_normalization\c"
		echo -e "\nMethod\t\t\t= $spot_normalization_method\c"
		echo -e "\nGain type\t\t= $spot_normalization_gain_type\c"
		echo -e "\nPregain (dB)\t\t= $spot_normalization_pregain\c"
		echo -e "\nThreshold (dBFS)\t= $spot_normalization_threshold\c"
		echo -e "\nAttack (ms)\t\t= $spot_normalization_attack\c"
		echo -e "\nRelease (ms)\t\t= $spot_normalization_release\c"
		echo -e "\nKnee\t\t\t= $spot_normalization_knee\c"
		echo -e "\nAutoplay\t\t= $spot_autoplay\c"
		echo -e "\nResume MPD\t\t= $rsmafterspot\n"
	fi

	if [ $(($feat_bitmask & $FEAT_SQUEEZELITE)) -ne 0 ]; then
		SL=`squeezelite -? | grep "Squeezelite" | cut -f 2 -d "v" | cut -f 1 -d ","`
		squeezelite -? | grep "\-Z" >/dev/null && SLT="\"DSD/SRC enabled\"" || SLT="\"DSD/SRC disabled\""
		echo -e "S Q U E E Z E L I T E"
		echo -e "\nVersion\t\t\t= $SL $SLT\c"
		echo -e "\nFriendly name\t\t= $PLAYERNAME\c"
		echo -e "\nALSA device\t\t= $AUDIODEVICE\c"
		echo -e "\nALSA params\t\t= $ALSAPARAMS\c"
		echo -e "\nOutput buffers\t\t= $OUTPUTBUFFERS\c"
		echo -e "\nTask priority\t\t= $TASKPRIORITY\c"
		echo -e "\nCodec list\t\t= $CODECS\c"
		echo -e "\nOther options\t\t= $OTHEROPTIONS\c" | cut -c 1-45
		echo -e "Resume MPD\t\t= $rsmaftersl\n"
	fi

	if [ $(($feat_bitmask & $FEAT_PLEXAMP)) -ne 0 ]; then
		if [[ -f "/home/$HOME_DIR/plexamp/js/index.js" ]]; then
			PAVER="4.10.1"
			echo -e "P L E X A M P"
			echo -e "\nVersion\t\t\t= $PAVER\c"
			echo -e "\nALSA max volume\t\t= $ALSAVOLUME_MAX_PA%\c"
 	 		echo -e "\nResume MPD\t\t= $rsmafterpa\n"
		fi
	fi

	if [ $(($feat_bitmask & $FEAT_ROONBRIDGE)) -ne 0 ]; then
		if [[ -f /opt/RoonBridge/start.sh ]]; then
			RBVER="$(awk 'FNR==2 {print $0}' /opt/RoonBridge/Bridge/VERSION)"
			echo -e "R O O N B R D G E"
			echo -e "\nVersion\t\t\t= $RBVER\c"
 	 		echo -e "\nResume MPD\t\t= $rsmafterrb\n"
		fi
	fi

	if [ $(($feat_bitmask & $FEAT_LOCALUI)) -ne 0 ]; then
		echo -e "L O C A L   D I S P L A Y"
		echo -e "\nLocal UI display\t= $localui\c"
		echo -e "\nWake display on play\t= $wake_display\c"
		echo -e "\nMouse cursor\t\t= $touchscn\c"
		echo -e "\nOn screen keyboard\t= $on_screen_kbd\c"
		echo -e "\nScreen blank\t\t= $scnblank Secs\c"
		echo -e "\nHDMI CEC\t\t= $hdmi_cec\c"
		echo -e "\nHDMI 4K 60Hz\t\t= $hdmi_enable_4kp60\c"
		echo -e "\nDisable GPU\t\t= $disable_gpu_chromium\c"
		echo -e "\nChromium browser\t= $chromium_ver\c"
		echo -e "\nScreen type\t\t= $rpi_scntype\c"
		echo -e "\nBacklight\t\t= $rpi_backlight\c"
		echo -e "\nBrightness\t\t= $scnbrightness\c"
		echo -e "\nPixel aspect ratio\t= $pixel_aspect_ratio\c"
		echo -e "\nRotate screen\t\t= $scnrotate Deg\n"
	fi
}

MOODE_LOG() {
	echo -e "M O O D E   S T A R T U P   L O G"
	echo -e "\n\c"
	cat /var/log/moode.log
	#sed -e 's/^/    /' /var/log/moode.log > /tmp/moode.log
	#cat /tmp/moode.log
	#echo -e "\n\n"
}

#
# Constants
#

# Features availability bitmask
FEAT_HTTPS=1
FEAT_AIRPLAY=2
FEAT_MINIDLNA=4
FEAT_RECORDER=8
FEAT_SQUEEZELITE=16
FEAT_UPMPDCLI=32
FEAT_ROONBRIDGE=128
FEAT_LOCALUI=256
FEAT_SPOTIFY=2048
FEAT_GPIO=4096
FEAT_PLEXAMP=8192
FEAT_BLUETOOTH=16384
FEAT_MULTIROOM=65536

# Selective resampling bitmask
SOX_UPSAMPLE_ALL=3			# Upsample if source < target rate
SOX_UPSAMPLE_ONLY_41K=1		# Upsample only 44.1K source rate
SOX_UPSAMPLE_ONLY_4148K=2	# Upsample only 44.1K and 48K source rates
SOX_ADHERE_BASE_FREQ=8		# Resample (adhere to base freq)

# Rootfs size in bytes (3.5GB)
DEV_ROOTFS_SIZE=3670016000

#
# Gather data
#

HOSTNAME=`uname -n`
HDWRREV=$(moodeutl -d -gv hdwrrev)
RASPIOS_VER=`/var/www/util/sysutil.sh "get-osinfo" | awk '{print $2" " $3" " $4}'`
KERNEL_VER=`/var/www/util/sysutil.sh "get-osinfo" | awk '{print $7" " $8}'`
SOC=`cat /proc/device-tree/compatible | tr '\0' ' ' | awk -F, '{print $NF}'`
CORES=`grep -c ^processor /proc/cpuinfo`
[ $(uname -m) = "aarch64" ] && ARCH="aarch64 (64-bit)" || ARCH="armhf (32-bit)"
HOME_DIR=$(ls /home/)
# Similar to moodeutl
MEM_TOTAL=$(grep MemTotal /proc/meminfo | awk '{print $2}')
MEM_AVAIL=$(grep MemAvailable /proc/meminfo | awk '{print $2}')
MEM_TOTAL=$(( $MEM_TOTAL / 1000 ))
MEM_AVAIL=$(( $MEM_AVAIL / 1000 ))
MEM_USED=$(( $MEM_TOTAL - $MEM_AVAIL ))
LOG2RAM=$(moodeutl -d -gv log2ram)

WORKER_RESPONSIVENESS=$(moodeutl -d -gv worker_responsiveness)
TMP=$(moodeutl -d -gv "pci_express")
[[ "$TMP" = "off" ]] && PCI_EXPRESS="Off" || PCI_EXPRESS="$TMP"
TMP=$(moodeutl -d -gv "reduce_power")
[[ "$TMP" = "on" ]] && REDUCE_POWER="On" || REDUCE_POWER="Off"

if [ -f /sys/devices/system/cpu/cpu0/cpufreq/scaling_governor ] ; then
	CPUGOV=`cat /sys/devices/system/cpu/cpu0/cpufreq/scaling_governor`
else
	CPUGOV="NA - disabled in kernel"
fi

ETH0IP="$(ip addr list eth0 2>&1 | grep "inet " |cut -d' ' -f6|cut -d/ -f1)"
WLAN0IP="$(ip addr list wlan0 2>&1 | grep "inet " |cut -d' ' -f6|cut -d/ -f1)"
if [ "$ETH0IP" = "" ]; then
	ETH0IP="unassigned"
fi
if [ "$WLAN0IP" = "" ]; then
	WLAN0IP="unassigned"
fi
ETH0MAC="$(ip addr list eth0 2>&1 | grep "ether " |cut -d' ' -f6|cut -d/ -f1)"
WLAN0MAC="$(ip addr list wlan0 2>&1 | grep "ether " |cut -d' ' -f6|cut -d/ -f1)"
if [ "$ETH0MAC" = "" ]; then
	ETH0MAC="no adapter"
fi
if [ "$WLAN0MAC" = "" ]; then
	WLAN0MAC="no adapter"
fi

TMP=$(moodeutl -d -gv nginx_https_only)
[[ "$TMP" = "1" ]] && HTTPS_MODE="On" || HTTPS_MODE="Off"

ROOTSIZE=$(lsblk -n -o FSSIZE /dev/disk/by-label/rootfs | awk '{print $1}')
ROOTUSED=$(lsblk -n -o FSUSED /dev/disk/by-label/rootfs | awk '{print $1}')
ROOTAVAIL=$(lsblk -n -o FSAVAIL /dev/disk/by-label/rootfs | awk '{print $1}')

HDMI="On"

NOW=$(date +"%Y-%m-%d %T")
UPTIME="$(uptime -p)"

[[ $(cat /proc/cpuinfo | grep 'Revision' | cut -f 2 -d " ") == 2* ]] && WARRANTY=void || WARRANTY=OK

TEMP=`awk '{printf "%3.1f\302\260C\n", $1/1000}' /sys/class/thermal/thermal_zone0/temp`
THROTTLED_BITMASK=`vcgencmd get_throttled | cut -d"=" -f2`
THROTTLED_TEXT=""
if [[ $THROTTLED_BITMASK == "0x0" ]]; then THROTTLED_TEXT="No throttling has occurred"; fi
if (( ($THROTTLED_BITMASK & 0x1) )); then THROTTLED_TEXT="Under-voltage detected, "; fi
if (( ($THROTTLED_BITMASK & 0x2) )); then THROTTLED_TEXT=$THROTTLED_TEXT"Arm frequency capped, "; fi
if (( ($THROTTLED_BITMASK & 0x4) )); then THROTTLED_TEXT=$THROTTLED_TEXT"Currently throttled, "; fi
if (( ($THROTTLED_BITMASK & 0x8) )); then THROTTLED_TEXT=$THROTTLED_TEXT"Soft temperature limit active, "; fi
if (( ($THROTTLED_BITMASK & 0x10000) )); then THROTTLED_TEXT=$THROTTLED_TEXT"Under-voltage has occurred, "; fi
if (( ($THROTTLED_BITMASK & 0x20000) )); then THROTTLED_TEXT=$THROTTLED_TEXT"Arm frequency capping has occurred, "; fi
if (( ($THROTTLED_BITMASK & 0x40000) )); then THROTTLED_TEXT=$THROTTLED_TEXT"Throttling has occurred, "; fi
if (( ($THROTTLED_BITMASK & 0x80000) )); then THROTTLED_TEXT=$THROTTLED_TEXT"Soft temperature limit has occurred"; fi
THROTTLED_TEXT=${THROTTLED_TEXT%, }
PHPVER=$(php -v 2>&1 | awk -F "-" 'NR==1{ print $1 }' | cut -f 2 -d " ")
NGINXVER=$(nginx -v 2>&1 | awk '{ print  $3 }' | cut -c7-)
SQLITEVER=$(sqlite3 -version | awk '{ print  $1 }')
PYTHON_LGPIO_VER=$(dpkg -s python3-lgpio 2>&1| grep Version| sed -r 's/^Version[:] (.*)-.*$/\1/')
PYTHON_VER=$(python --version | awk '{ print  $2 }')
which node
if [[ $? -gt 0 ]]; then
	NODEJS_VER="Not installed"
else
	NODEJS_VER=$(node -v | cut -d'v' -f2)
fi

# Bluetooth
BLUETOOTH_VER=$(bluetoothd -v)
BLUEALSA_VER=$(bluealsa -V 2> /dev/null)
PARING_AGENT_VER=$(dpkg -l | grep bluez-tools | awk '{print $3}' | cut -d"~" -f1)
PI_BLUETOOTH_VER=$(dpkg -l | grep pi-bluetooth | awk '{print $3}')
output_mode=$(moodeutl -d -gv alsa_output_mode_bt)
[[ $output_mode = "_audioout" ]] && ALSA_OUTPUT_MODE_BT="Default (_audioout)" || ALSA_OUTPUT_MODE_BT="Compatibility (plughw)"
TMP=$(moodeutl -d -gv bt_pin_code)
[[ "$TMP" = "None" ]] && BT_PIN_CODE="None" || BT_PIN_CODE="******"
ALSAVOLUME_MAX_BT=$(moodeutl -d -gv alsavolume_max_bt)
CDSPVOLUME_MAX_BT=$(moodeutl -d -gv cdspvolume_max_bt)

# Plexamp
ALSAVOLUME_MAX_PA=$(moodeutl -d -gv alsavolume_max_pa)

# Moode release
moode_rel="$(moodeutl --mooderel | tr -d '\n')"

# Supported audio formats for the configured device
supported_formats=$(moodeutl -f);

# Moode SQL data
SQLDB=/var/local/www/db/moode-sqlite3.db

# AirPlay settings
RESULT=$(sqlite3 $SQLDB "select value from cfg_airplay")
readarray -t arr <<<"$RESULT"
interpolation=${arr[2]}
output_format=${arr[3]}
output_rate=${arr[4]}
allow_session_interruption=${arr[5]}
session_timeout=${arr[6]}
audio_backend_latency_offset_in_seconds=${arr[7]}
audio_backend_buffer_desired_length_in_seconds=${arr[8]}

# MPD settings
RESULT=$(sqlite3 $SQLDB "select value from cfg_mpd where param in (
'device',
'mixer_type',
'dop',
'audio_output_format',
'sox_quality',
'sox_multithreading',
'replaygain',
'replaygain_preamp',
'volume_normalization',
'audio_buffer_size',
'input_cache',
'max_output_buffer_size',
'auto_resample',
'auto_channels',
'auto_format',
'buffer_time',
'period_time',
'selective_resample_mode',
'sox_precision',
'sox_phase_response',
'sox_passband_end',
'sox_stopband_begin',
'sox_attenuation',
'sox_flags',
'max_playlist_length'
)")
readarray -t arr <<<"$RESULT"
device=${arr[0]}
mixer_type=${arr[1]}
dop=${arr[2]}
audio_output_format=${arr[3]}
sox_quality=${arr[4]}
[[ "${arr[5]}" = "1" ]] && sox_multithreading="off" || sox_multithreading="on"
replaygain=${arr[6]}
replaygain_preamp=${arr[7]}
volume_normalization=${arr[8]}
audio_buffer_size=$((${arr[9]}/1024))
input_cache=${arr[10]}
max_output_buffer_size=$((${arr[11]}/1024))
auto_resample=${arr[12]}
auto_channels=${arr[13]}
auto_format=${arr[14]}
buffer_time=${arr[15]}
period_time=${arr[16]}
[[ "${arr[17]}" = "0" ]] && selective_resample_mode="disabled"
[[ "${arr[17]}" = "$SOX_UPSAMPLE_ALL" ]] && selective_resample_mode="Upsample if source < target rate"
[[ "${arr[17]}" = "$SOX_UPSAMPLE_ONLY_41K" ]] && selective_resample_mode="Upsample only 44.1K source rate"
[[ "${arr[17]}" = "$SOX_UPSAMPLE_ONLY_4148K" ]] && selective_resample_mode="Upsample only 44.1K and 48K source rates"
[[ "${arr[17]}" = "$SOX_ADHERE_BASE_FREQ" ]] && selective_resample_mode="Resample (adhere to base freq)"
[[ "${arr[17]}" = "$(($SOX_UPSAMPLE_ALL + $SOX_ADHERE_BASE_FREQ))" ]] && selective_resample_mode="Upsample if source < target rate (adhere to base freq)"
sox_precision=${arr[18]}
sox_phase_response=${arr[19]}
sox_passband_end=${arr[20]}
sox_stopband_begin=${arr[21]}
sox_attenuation=${arr[22]}
sox_flags=${arr[23]}
max_playlist_length=${arr[24]}

# Spotify settings
RESULT=$(sqlite3 $SQLDB "select value from cfg_spotify")
readarray -t arr <<<"$RESULT"
spot_bitrate=${arr[0]}
spot_initial_volume=${arr[1]}
spot_volume_curve=${arr[2]}
spot_volume_normalization=${arr[3]}
spot_normalization_pregain=${arr[4]}
spot_autoplay=${arr[5]}
spot_normalization_method=${arr[6]}
spot_normalization_gain_type=${arr[7]}
spot_normalization_threshold=${arr[8]}
spot_normalization_attack=${arr[9]}
spot_normalization_release=${arr[10]}
spot_normalization_knee=${arr[11]}
spot_format=${arr[12]}
[[ "${arr[13]}" = "" ]] && spot_dither="Automatic (Default)" || spot_dither=${arr[13]}
spot_volume_range=${arr[14]}

# Squeezelite settings
RESULT=$(sqlite3 $SQLDB "select value from cfg_sl")
readarray -t arr <<<"$RESULT"
PLAYERNAME=${arr[0]}
AUDIODEVICE=${arr[1]}
ALSAPARAMS=${arr[2]}
OUTPUTBUFFERS=${arr[3]}
TASKPRIORITY=${arr[4]}
CODECS=${arr[5]}
OTHEROPTIONS=${arr[6]}

# System settings
RESULT=$(sqlite3 $SQLDB "select value from cfg_system")
readarray -t arr <<<"$RESULT"
sessionid=${arr[0]}
timezone=${arr[1]}
i2sdevice=${arr[2]}
host=${arr[3]}
browsertitle=${arr[4]}
airplayname=${arr[5]}
upnpname=${arr[6]}
dlnaname=${arr[7]}
[[ "${arr[8]}" = "1" ]] && airplaysvc="On" || airplaysvc="Off"
[[ "${arr[9]}" = "1" ]] && upnpsvc="On" || upnpsvc="Off"
[[ "${arr[10]}" = "1" ]] && dlnasvc="On" || dlnasvc="Off"
[[ "${arr[11]}" = "plughw" ]] && alsa_output_mode="Default (plughw)" || alsa_output_mode="Direct (hw)"
paactive=${arr[12]}
[[ "${arr[13]}" = "1" ]] && autoplay="On" || autoplay="Off"
if [[ -f "/opt/RoonBridge/start.sh" ]]; then
	[[ "${arr[14]}" = "1" ]] && rbsvc="On" || rbsvc="Off"
else
	rbsvc="Not installed"
fi
res_plugin_upd_url=${arr[15]}
rbactive=${arr[16]}
adevname=${arr[17]}
clkradio_mode=${arr[18]}
clkradio_item=${arr[19]}
clkradio_name=${arr[20]}
clkradio_start=${arr[21]}
clkradio_stop=${arr[22]}
clkradio_volume=${arr[23]}
clkradio_shutdown=${arr[24]}
playhist=${arr[25]}
phistsong=${arr[26]}
library_utf8rep=${arr[27]}
current_view=${arr[28]}
timecountup=${arr[29]}
accentcolor=${arr[30]}
volknob=${arr[31]}
[[ "${arr[32]}" = "1" ]] && volmute="Muted" || volmute="Unmuted"
alsavolume_max=${arr[33]}
alsavolume=${arr[34]}
amixname=${arr[35]}
mpdmixer=${arr[36]}
xtagdisp=${arr[37]}
rsmafterapl=${arr[38]}
lcdup=${arr[39]}
library_show_genres=${arr[40]}
extmeta=${arr[41]}
i2soverlay=${arr[42]}
folder_pos=${arr[43]}
[[ "${arr[44]}" = "Off" ]] && crossfeed="Off" || crossfeed=${arr[44]}
bluez_pcm_buffer=${arr[45]}
fs_nfs_options=${arr[46]}
library_onetouch_album=${arr[47]}
radio_pos=${arr[48]}
aplactive=${arr[49]}
ipaddr_timeout=${arr[50]}
[[ "${arr[51]}" = "1" ]] && ashufflesvc="On" || ashufflesvc="Off"
ashuffle=${arr[52]}
camilladsp=${arr[53]}
cdsp_fix_playback=${arr[54]}
camilladsp_quickconv=${arr[55]}
alsa_loopback=${arr[56]}
keyboard=${arr[57]}
[[ "${arr[58]}" = "1" ]] && localui="On" || localui="Off"
toggle_songid=${arr[59]}
[[ "${arr[60]}" = "1" ]] && slsvc="On" || slsvc="Off"
ap_network_addr=${arr[61]}
cpugov=${arr[62]}
if [[ -f "/home/$HOME_DIR/plexamp/js/index.js" ]]; then
	[[ "${arr[63]}" = "1" ]] && pasvc="On" || pasvc="Off"
else
	pasvc="Not installed"
fi
pkgid_suffix=${arr[64]}
lib_pos=${arr[65]}
[[ "${arr[66]}" = "0" ]] && mpdcrossfade="Off" || mpdcrossfade=${arr[66]}
[[ "${arr[67]}" = "1" ]] && eth0chk="Yes" || eth0chk="No"
usb_auto_mounter=${arr[68]}
[[ "${arr[69]}" = "1" ]] && rsmafterbt="Yes" || rsmafterbt="No"
rotenc_params=${arr[70]}
[[ "${arr[71]}" = "1" ]] && shellinabox="On" || shellinabox="Off"
alsaequal=${arr[72]}
eqfa12p=${arr[73]}
model=$(echo $HDWRREV | cut -c 4)
# TODO: Add code for Pi-5
if [[ $model == "3" || $model == "4" || $model == "5" || $model == "Z" ]]; then
	[[ "${arr[74]}" = "1" ]] && piwifi="On" || piwifi="Off"
	[[ "${arr[75]}" = "1" ]] && pibt="On" || pibt="Off"
else
	piwifi="None"
	pibt="None"
fi
cardnum=${arr[76]}
[[ "${arr[77]}" = "1" ]] && btsvc="On" || btsvc="Off"
btname=${arr[78]}
[[ "${arr[79]}" = "on" ]] && camilladsp_volume_sync='On' || camilladsp_volume_sync='Off'
feat_bitmask=${arr[80]}
if [[ "${arr[81]}" = "604800000" ]]; then
	library_recently_added="1 Week"
elif [[ "${arr[81]}" = "2592000000" ]]; then
	library_recently_added="1 Month"
elif [[ "${arr[81]}" = "7776000000" ]]; then
	library_recently_added="3 Months"
elif [[ "${arr[81]}" = "15552000000" ]]; then
	library_recently_added="6 Months"
elif [[ "${arr[81]}" = "31536000000" ]]; then
	library_recently_added="1 Year"
fi
btactive=${arr[82]}
[[ "${arr[83]}" = "1" ]] && touchscn="On" || touchscn="Off"
scnblank=${arr[84]}
scnrotate=${arr[85]}
scnbrightness=${arr[86]}
themename=${arr[87]}
res_software_upd_url=${arr[88]}
alphablend=${arr[89]}
scnsaver_whenplaying=${arr[90]}
audioout=${arr[91]}
audioin=${arr[92]}
slactive=${arr[93]}
rsmaftersl=${arr[94]}
mpdmixer_local=${arr[95]}
wrkready=${arr[96]}
scnsaver_timeout=${arr[97]}
pixel_aspect_ratio=${arr[98]}
favorites_name=${arr[99]}
[[ "${arr[100]}" = "1" ]] && spotifysvc="On" || spotifysvc="Off"
spotifyname=${arr[101]}
spotactive=${arr[102]}
rsmafterspot=${arr[103]}
library_covsearchpri=${arr[104]}
library_hiresthm=${arr[105]}
library_pixelratio=${arr[106]}
debuglog=${arr[107]}
cover_backdrop=${arr[108]}
cover_blur=${arr[109]}
cover_scale=${arr[110]}
rsmafterrb=${arr[111]}
library_tagview_artist=${arr[112]}
scnsaver_style=${arr[113]}
rsmafterpa=${arr[114]}
[[ "${arr[115]}" = "1" ]] && mpd_httpd="On" || mpd_httpd="Off"
mpd_httpd_port=${arr[116]}
mpd_httpd_encoder=${arr[117]}
[[ "${arr[118]}" = "1" ]] && invert_polarity="On" || invert_polarity="Off"
inpactive=${arr[119]}
rsmafterinp=${arr[120]}
[[ "${arr[121]}" = "1" ]] && gpio_svc="On" || gpio_svc="Off"
ignore_articles=${arr[122]}
volknob_mpd=${arr[123]}
volknob_preamp=${arr[124]}
library_albumview_sort=${arr[125]}
library_thmgen_scan=${arr[126]}
[[ "${arr[127]}" = "1" ]] && wake_display="On" || wake_display="Off"
rx_hostnames=${arr[128]}
rx_addresses=${arr[129]}
library_tagview_covers=${arr[130]}
library_tagview_sort=${arr[131]}
library_ellipsis_limited_text=${arr[132]}
appearance_modal_state=${arr[133]}
font_size=${arr[134]}
volume_step_limit=${arr[135]}
volume_mpd_max=${arr[136]}
library_thumbnail_columns=${arr[137]}
if [[ "${arr[138]}" = "9" ]]; then
	library_encoded_at="No"
elif [[ "${arr[138]}" = "0" ]]; then
	library_encoded_at="No (searchable)"
elif [[ "${arr[138]}" = "1" ]]; then
	library_encoded_at="HD only"
elif [[ "${arr[138]}" = "2" ]]; then
	library_encoded_at="Text"
elif [[ "${arr[138]}" = "3" ]]; then
	library_encoded_at="Badge"
fi
first_use_help=${arr[139]}
playlist_art=${arr[140]}
library_onetouch_ralbum=${arr[141]}
radioview_sort_group=${arr[142]}
rv_sort_tag=$(awk -F"," '{print $1}' <<< $radioview_sort_group)
rv_group_method=$(awk -F"," '{print $2}' <<< $radioview_sort_group)
radioview_show_hide=${arr[143]}
rv_show_moode=$(awk -F"," '{print $1}' <<< $radioview_show_hide)
rv_show_other=$(awk -F"," '{print $2}' <<< $radioview_show_hide)
renderer_backdrop=${arr[144]}
library_flatlist_filter=${arr[145]}
library_flatlist_filter_str=${arr[146]}
library_misc_options=${arr[147]}
include_comment_tag=$(awk -F"," '{print $1}' <<< $library_misc_options)
album_key=$(awk -F"," '{print $2}' <<< $library_misc_options)
rv_recorder_status=${arr[148]}
rv_recorder_storage=${arr[149]}
[[ "${arr[150]}" = "1" ]] && volume_db_display="On" || volume_db_display="Off"
search_site=${arr[151]}
[[ "${arr[152]}" = "1" ]] && cuefiles_ignore="Yes" || cuefiles_ignore="No"
recorder_album_tag=${arr[153]}
inplace_upd_applied=${arr[154]}
show_npicon=${arr[155]}
show_cvpb=${arr[156]}
multiroom_tx=${arr[157]}
multiroom_rx=${arr[158]}
rxactive=${arr[159]}
library_onetouch_radio=${arr[160]}
library_tagview_genres=${arr[161]}
[[ "${arr[162]}" = "-on" ]] && auto_coverview="On" || auto_coverview="Off"
maint_interval=${arr[163]}
library_track_play=${arr[164]}
playlist_pos==${arr[165]}
plview_sort_group=${arr[166]}
plv_sort_tag=$(awk -F"," '{print $1}' <<< $plview_sort_group)
plv_group_method=$(awk -F"," '{print $2}' <<< $plview_sort_group)
fs_smb=${arr[167]}
fs_nfs=${arr[168]}
fs_nfs_access=${arr[169]}
native_lazyload=${arr[170]}
library_onetouch_pl=${arr[171]}
scnsaver_mode=${arr[172]}
scnsaver_layout=${arr[173]}
scnsaver_xmeta=${arr[174]}
# Session only vars
value=$(moodeutl -d -gv rotaryenc)
[[ "$value" = "1" ]] && rotaryenc="On" || rotaryenc="Off"
ashuffle_mode=$(moodeutl -d -gv "ashuffle_mode")
ashuffle_window=$(moodeutl -d -gv "ashuffle_window")
ashuffle_filter=$(moodeutl -d -gv "ashuffle_filter")
ashuffle_exclude=$(moodeutl -d -gv "ashuffle_exclude")
on_screen_kbd=$(moodeutl -d -gv "on_screen_kbd")
hdmi_cec=$(moodeutl -d -gv "hdmi_cec")
hdmi_enable_4kp60=$(moodeutl -d -gv "hdmi_enable_4kp60")
disable_gpu_chromium=$(moodeutl -d -gv "disable_gpu_chromium")
chromium_ver=$(dpkg -l | grep -m 1 "chromium-browser" | awk '{print $3}' | cut -d":" -f 2)
rpi_scntype=$(moodeutl -d -gv "rpi_scntype")
rpi_backlight=$(moodeutl -d -gv "rpi_backlight")
value=$(moodeutl -d -gv usb_volknob)
[[ "$value" = "1" ]] && usb_volknob="On" || usb_volknob="Off"
led_state=$(moodeutl -d -gv led_state)

# Network settings
RESULT=$(sqlite3 $SQLDB "select * from cfg_network")
readarray -t arr <<<"$RESULT"
wlanssid=$(echo ${arr[1]} | cut -f 9 -d "|")
wlanuuid=$(echo ${arr[1]} | cut -f 10 -d "|")
wlancountry=$(echo ${arr[1]} | cut -f 13 -d "|")
apdssid=$(echo ${arr[2]} | cut -f 9 -d "|")

# Misc settings
modprobe configs
test -f /proc/config.gz && {
	HZ=$(zcat /proc/config.gz | grep "^CONFIG_HZ=" | cut -f 2 -d "=")
} || {
	HZ="No /proc/config.gz"
}
rmmod configs

if [[ "$1" = "html" ]]; then
	micro_symbol="&micro;s"
else
	micro_symbol="\u03bcs"
fi

#
# Generate output
#

SYSTEM_PARAMETERS
AUDIO_PARAMETERS
APPEARANCE_SETTINGS
RADIO_MANAGER_SETTINGS
PLAYLIST_MANAGER_SETTINGS
MPD_SETTINGS
RENDERER_SETTINGS
MOODE_LOG

exit 0
