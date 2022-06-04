#!/bin/bash
#
# moOde audio player (C) 2014 Tim Curtis
# http://moodeaudio.org
#
# tsunamp player ui (C) 2013 Andrea Coiutti & Simone De Gregori
# http://www.tsunamp.com
#
# This Program is free software; you can redistribute it and/or modify
# it under the terms of the GNU General Public License as published by
# the Free Software Foundation; either version 3, or (at your option)
# any later version.
#
# This Program is distributed in the hope that it will be useful,
# but WITHOUT ANY WARRANTY; without even the implied warranty of
# MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
# GNU General Public License for more details.
#
# You should have received a copy of the GNU General Public License
# along with this program.  If not, see <http://www.gnu.org/licenses/>.
#

# This limit is designed to moderate PHP resource usage and should be around 2X higher (based on my experience)
# than the typical number of fpm child workers that are spawned when there are two connected clients operating
# mainly in playback/library mode. The limit will be exceeded when doing a lot of page refreshes which can
# easily occur when spending time doing initial configuration (the Config pages). Restarting PHP when the
# limit is exceeded should not have any adverse effect.
FPM_LIMIT=45

FPM_CNT=$(pgrep -c -f "php-fpm: pool www")
MPD_LOADED=$(pgrep -c -x mpd)
SQLDB=/var/local/www/db/moode-sqlite3.db

message_log () {
	TIME=$(date +'%Y%m%d %H%M%S')
	echo "$TIME watchdog: $1" >> /var/log/moode.log
}

while true; do
	# PHP-FPM
	if (( FPM_CNT > FPM_LIMIT )); then
		message_log "Info: Reducing PHP fpm worker pool"
		systemctl restart php7.4-fpm
	fi

	# MPD
	if [[ $MPD_LOADED = 0 ]]; then
		message_log "Error: MPD restarted (check syslog for errors)"
		systemctl start mpd
	fi

	# Wake display on play
	# NOTE: Player apps include MPD and any of the renderers
	MULTIROOM_TX=$(sqlite3 $SQLDB "SELECT value FROM cfg_system WHERE param='multiroom_tx'")
	if [[ $MULTIROOM_TX = "On" ]]; then
		TRX_CARD_NUM="3"
		TRX_HW_PARAMS=$(cat /proc/asound/card$TRX_CARD_NUM/pcm0p/sub0/hw_params)

		if [[ $TRX_HW_PARAMS = "closed" ]]; then
			NOP=""
			#message_log "Info: Multiroom sender is not transmitting"
		else
			#message_log "Info: Multiroom sender is transmitting"
			WAKE_DISPLAY=$(sqlite3 $SQLDB "SELECT value FROM cfg_system WHERE param='wake_display'")
			if [[ $WAKE_DISPLAY = "1" ]]; then
				export DISPLAY=:0
				xset s reset > /dev/null 2>&1
			fi
		fi
	else
		LOCAL_CARD_NUM=$(sqlite3 $SQLDB "SELECT value FROM cfg_mpd WHERE param='device'")
		LOCAL_HW_PARAMS=$(cat /proc/asound/card$LOCAL_CARD_NUM/pcm0p/sub0/hw_params)

		if [[ $LOCAL_HW_PARAMS = "closed" || $LOCAL_HW_PARAMS = "" ]]; then
			NOP=""
			#message_log "Info: Local audio output is closed or audio device is disconnected"
		else
			#message_log "Info: Local audio output is active"
			WAKE_DISPLAY=$(sqlite3 $SQLDB "SELECT value FROM cfg_system WHERE param='wake_display'")
			if [[ $WAKE_DISPLAY = "1" ]]; then
				export DISPLAY=:0
				xset s reset > /dev/null 2>&1
			fi
		fi
	fi

	sleep 6
	FPM_CNT=$(pgrep -c -f "php-fpm: pool www")
	MPD_LOADED=$(pgrep -c -x mpd)

done > /dev/null 2>&1 &
