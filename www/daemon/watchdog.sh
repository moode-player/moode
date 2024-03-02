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

# $1: Loop sleep time (secs)
if [ -z $1 ]; then WATCHDOG_SLEEP=6; else WATCHDOG_SLEEP=$1; fi

PHP_VER="8.2"

# NOTE: The FPM limits are for moderating resource usage in the PHP-FPM pool
FPM_MAX_LIMIT=64
FPM_MIN_LIMIT=32

FPM_CNT=$(pgrep -c -f "php-fpm: pool www")
MPD_RUNNING=$(pgrep -c -x mpd)
SQLDB=/var/local/www/db/moode-sqlite3.db

message_log () {
	TIME=$(date +'%Y%m%d %H%M%S')
	echo "$TIME watchdog: $1" >> /var/log/moode.log
}

wake_display () {
	WAKE_DISPLAY=$(sqlite3 $SQLDB "SELECT value FROM cfg_system WHERE param='wake_display'")
	if [[ $WAKE_DISPLAY = "1" ]]; then
		export DISPLAY=:0
		xset s reset > /dev/null 2>&1
	fi
}

while true; do
	# PHP-FPM
	if (( FPM_CNT > FPM_MIN_LIMIT )); then
		#message_log "Info: Reducing PHP-FPM worker pool"
		/var/www/util/send-engcmd.php reduce_fpm_pool
	fi

	if (( FPM_CNT > FPM_MAX_LIMIT )); then
		message_log "Info: Resetting PHP-FPM worker pool"
		systemctl restart php$PHP_VER-fpm
	fi

	# MPD
	if [[ $MPD_RUNNING = 0 ]]; then
		message_log "Error: MPD restarted (check syslog for messages)"
		systemctl start mpd
	fi

	# Wake local display on play
	MULTIROOM_TX=$(sqlite3 $SQLDB "SELECT value FROM cfg_system WHERE param='multiroom_tx'")
	if [[ $MULTIROOM_TX = "On" ]]; then
		LOOPBACK_HW_PARAMS=$(cat /proc/asound/card2/pcm0p/sub0/hw_params)
		if [[ $LOOPBACK_HW_PARAMS = "closed" ]]; then
			MSG="Info: Multiroom sender is not transmitting"
		else
			MSG="Info: Multiroom sender is transmitting"
			wake_display
		fi
	else
		LOCAL_CARD_NUM=$(sqlite3 $SQLDB "SELECT value FROM cfg_mpd WHERE param='device'")
		LOCAL_HW_PARAMS=$(cat /proc/asound/card$LOCAL_CARD_NUM/pcm0p/sub0/hw_params)
		if [[ $LOCAL_HW_PARAMS = "closed" || $LOCAL_HW_PARAMS = "" ]]; then
			MSG="Info: Local audio output is closed or audio device is disconnected"
		else
			MSG="Info: Local audio output is active"
			wake_display
		fi
	fi

	# DEBUG
	#message_log "$MSG"

	sleep $WATCHDOG_SLEEP
	FPM_CNT=$(pgrep -c -f "php-fpm: pool www")
	MPD_RUNNING=$(pgrep -c -x mpd)

done > /dev/null 2>&1 &
