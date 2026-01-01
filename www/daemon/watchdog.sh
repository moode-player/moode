#!/bin/bash
#
# SPDX-License-Identifier: GPL-3.0-or-later
# Copyright 2014 The moOde audio player project / Tim Curtis
# Copyright 2013 tsunamp player ui / Andrea Coiutti & Simone De Gregori
#

# $1: Loop sleep time (secs)
if [ -z $1 ]; then WATCHDOG_SLEEP=6; else WATCHDOG_SLEEP=$1; fi

PHP_VER="8.4"

# NOTE: The FPM limits are for moderating resource usage in the PHP-FPM pool
FPM_MAX_LIMIT=64
FPM_MIN_LIMIT=32

FPM_CNT=$(pgrep -c -f "php-fpm: pool www")
MPD_RUNNING=$(pgrep -c -x mpd)
TRX_RX_RUNNING=$(pgrep -c -x trx-rx)
LIBRESPOT_RUNNING=$(pgrep -c -x librespot)
SQLDB=/var/local/www/db/moode-sqlite3.db

message_log () {
	TIME=$(date +'%Y%m%d %H%M%S')
	echo "$TIME watchdog: $1" >> /var/log/moode.log
}
debug_log () {
	TIME=$(date +'%Y%m%d %H%M%S')
	# Comment out to suppress debug messages
	#echo "$TIME watchdog: DEBUG: $1" >> /var/log/moode.log
}

wake_display () {
	WAKE_DISPLAY=$(sqlite3 $SQLDB "SELECT value FROM cfg_system WHERE param='wake_display'")
	PEPPY_ACTIVE=$(sqlite3 $SQLDB "SELECT value FROM cfg_system WHERE param='peppy_display'")
	SCN_BLANK_ACTIVE=$(sqlite3 $SQLDB "SELECT value from cfg_system WHERE param='peppy_scn_blank_active'")
	CEC_CONTROL=$(moodeutl -d -gv hdmi_cec)
	CEC_VERSION=$(moodeutl -d -gv hdmi_cec_ver)
	if [[ $WAKE_DISPLAY = "1" ]]; then
		debug_log "wake display: on"
		if [[ $CEC_CONTROL = "on" ]]; then
			[[ "$CEC_VERSION" = "1.4" ]] && CEC_VER_STRING="--cec-version-1.4" || CEC_VER_STRING=""
			debug_log "wake display: send cec-ctl --skip-info --to 0 $CEC_VER_STRING --image-view-on"
			cec-ctl --skip-info --to 0 $CEC_VER_STRING --image-view-on
		fi
		export DISPLAY=:0
		if [[ $PEPPY_ACTIVE = "1" && $SCN_BLANK_ACTIVE = "1" ]]; then
			debug_log "wake display: set peppy_scn_blank_active 0, restart localdisplay"
			$(sqlite3 $SQLDB "UPDATE cfg_system SET value='0' WHERE param='peppy_scn_blank_active'")
			systemctl restart localdisplay
		else
			debug_log "wake display: send xset s reset"
			debug_log "wake display: send dpms force on"
			xset s reset > /dev/null 2>&1
			xset dpms force on > /dev/null 2>&1
		fi
	else
		debug_log "wake display: off"
	fi
}

while true; do
	# PHP-FPM
	if (( FPM_CNT > FPM_MIN_LIMIT )); then
		#message_log "Reducing PHP-FPM worker pool to free up resources"
		/var/www/util/send-fecmd.php reduce_fpm_pool
	fi

	if (( FPM_CNT > FPM_MAX_LIMIT )); then
		message_log "PHP-FPM was restarted to free up worker pool resources"
		systemctl restart php$PHP_VER-fpm
	fi

	# MPD
	if [[ $MPD_RUNNING = 0 ]]; then
		counter=0
		while [ $counter -lt 3 ]; do
			sleep 1
			MPD_RUNNING=$(pgrep -c -x mpd)
			if [[ $MPD_RUNNING != 0 ]]; then break; fi
			((counter++))
		done
		if [[ $MPD_RUNNING = 0 ]]; then
			message_log "CRITICAL ERROR: Started MPD after crash detected"
			systemctl start mpd
		fi
	fi

	# Multiroom receiver
	MULTIROOM_RX=$(sqlite3 $SQLDB "SELECT value FROM cfg_system WHERE param='multiroom_rx'")
	if [[ $MULTIROOM_RX = "On" ]]; then
		if [[ $TRX_RX_RUNNING = 0 ]]; then
			counter=0
			while [ $counter -lt 3 ]; do
				sleep 1
				TRX_RX_RUNNING=$(pgrep -c -x trx-rx)
				if [[ $TRX_RUNNING != 0 ]]; then break; fi
				((counter++))
			done
			if [[ $TRX_RX_RUNNING = 0 ]]; then
				message_log "CRITICAL ERROR: Started Multiroom receiver after crash detected"
				/var/www/util/trx-control.php -rx On
			fi
		fi
	fi

	# Spotify Connect
	SPOTIFY_SVC=$(sqlite3 $SQLDB "SELECT value FROM cfg_system WHERE param='spotifysvc'")
	if [[ $SPOTIFY_SVC = "1" ]]; then
		if [[ $LIBRESPOT_RUNNING = 0 ]]; then
			counter=0
			while [ $counter -lt 3 ]; do
				sleep 1
				LIBRESPOT_RUNNING=$(pgrep -c -x librespot)
				if [[ $LIBRESPOT_RUNNING != 0 ]]; then break; fi
				((counter++))
			done
			if [[ $LIBRESPOT_RUNNING = 0 ]]; then
				message_log "CRITICAL ERROR: Started Spotify Connect after crash detected"
				moodeutl -R --spotify
			fi
		fi
	fi

	# Wake local display on play
	MULTIROOM_TX=$(sqlite3 $SQLDB "SELECT value FROM cfg_system WHERE param='multiroom_tx'")
	if [[ $MULTIROOM_TX = "On" ]]; then
		# Card2 will be Loopback or Dummy depending on whether there are 1 or 2 HDMI ports
		TX_CARD_NUM="card2"
		HW_PARAMS=$(cat /proc/asound/$TX_CARD_NUM/pcm0p/sub0/hw_params)
		if [[ $HW_PARAMS = "closed" ]]; then
			debug_log "Multiroom sender is not transmitting"
		else
			debug_log "Multiroom sender is transmitting, wake display"
			wake_display
		fi
	else
		debug_log "check wake on play"
		LOCAL_DISPLAY_HOST=$(sqlite3 $SQLDB "SELECT value FROM cfg_system WHERE param='local_display_url'" | awk -F"/" '{print $3}')
		if  [[ $LOCAL_DISPLAY_HOST = "localhost" ]]; then
			TYPE="local"
			CARD_NUM=$(sqlite3 $SQLDB "SELECT value FROM cfg_mpd WHERE param='device'")
			HW_PARAMS=$(cat /proc/asound/card$CARD_NUM/pcm0p/sub0/hw_params)
		else
			TYPE="remote"
			TMP=$(curl -G -S -s --data-urlencode "cmd=get_output_format" http://$LOCAL_DISPLAY_HOST/command/ | grep "Not playing")
			[ -z "$TMP" ] && HW_PARAMS="playing" || HW_PARAMS="closed"
		fi

		if [[ $HW_PARAMS = "closed" || $HW_PARAMS = "" ]]; then
			debug_log "$TYPE audio output is closed, don't wake display"
		else
			debug_log "$TYPE audio output is active, wake display"
			wake_display
		fi
	fi

	sleep $WATCHDOG_SLEEP
	FPM_CNT=$(pgrep -c -f "php-fpm: pool www")
	MPD_RUNNING=$(pgrep -c -x mpd)
	TRX_RX_RUNNING=$(pgrep -c -x trx-rx)
	LIBRESPOT_RUNNING=$(pgrep -c -x librespot)

done > /dev/null 2>&1 &
