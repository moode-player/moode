#!/bin/bash
#
# SPDX-License-Identifier: GPL-3.0-or-later
# Copyright 2014 The moOde audio player project / Tim Curtis
# Copyright 2013 tsunamp player ui / Andrea Coiutti & Simone De Gregori
#

# $1: Loop sleep time (secs)
if [ -z $1 ]; then WATCHDOG_SLEEP=6; else WATCHDOG_SLEEP=$1; fi

PHP_VER="8.2"

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

wake_display () {
	WAKE_DISPLAY=$(sqlite3 $SQLDB "SELECT value FROM cfg_system WHERE param='wake_display'")
	if [[ $WAKE_DISPLAY = "1" ]]; then
		cec-ctl --skip-info --to 0 --cec-version-1.4 --image-view-on
		export DISPLAY=:0
		xset s reset > /dev/null 2>&1
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
			message_log "Started MPD after crash detected (check system journal)"
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
				message_log "Started Multiroom receiver after crash detected"
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
				message_log "Started Spotify Connect after crash detected"
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
			MSG="Multiroom sender is not transmitting"
		else
			MSG="Multiroom sender is transmitting"
			wake_display
		fi
	else
		LOCAL_CARD_NUM=$(sqlite3 $SQLDB "SELECT value FROM cfg_mpd WHERE param='device'")
		HW_PARAMS=$(cat /proc/asound/card$LOCAL_CARD_NUM/pcm0p/sub0/hw_params)
		if [[ $HW_PARAMS = "closed" || $HW_PARAMS = "" ]]; then
			MSG="Local audio output is closed or audio device is disconnected"
		else
			MSG="Local audio output is active"
			wake_display
		fi
	fi

	# DEBUG
	#message_log "$MSG"

	sleep $WATCHDOG_SLEEP
	FPM_CNT=$(pgrep -c -f "php-fpm: pool www")
	MPD_RUNNING=$(pgrep -c -x mpd)
	TRX_RX_RUNNING=$(pgrep -c -x trx-rx)
	LIBRESPOT_RUNNING=$(pgrep -c -x librespot)

done > /dev/null 2>&1 &
