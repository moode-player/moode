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
FPM_LIMIT=30

FPM_CNT=$(pgrep -c -f "php-fpm: pool www")
MPD_ACTIVE=$(pgrep -c -x mpd)
HW_PARAMS_LAST=""
SQL_DB=/var/local/www/db/moode-sqlite3.db

while true; do
	# PHP-FPM
	if (( FPM_CNT > FPM_LIMIT )); then
		TIME_STAMP=$(date +'%Y%m%d %H%M%S')
		LOG_MSG=" watchdog: Info: Reducing PHP fpm worker pool"
		echo $TIME_STAMP$LOG_MSG >> /var/log/moode.log
		systemctl restart php7.4-fpm
	fi

	# MPD
	if [[ $MPD_ACTIVE = 0 ]]; then
		TIME_STAMP=$(date +'%Y%m%d %H%M%S')
		LOG_MSG=" watchdog: Error: MPD restarted (check syslog for errors)"
		echo $TIME_STAMP$LOG_MSG >> /var/log/moode.log
		systemctl start mpd
	fi

	# Wake display on play
	CARD_NUM=$(sqlite3 $SQL_DB "SELECT value FROM cfg_mpd WHERE param='device'")
	HW_PARAMS=$(cat /proc/asound/card$CARD_NUM/pcm0p/sub0/hw_params)
	TIME_STAMP=$(date +'%Y%m%d %H%M%S')
	if [[ $HW_PARAMS = "closed" || $HW_PARAMS = "" ]]; then
		LOG_MSG=" watchdog: Info: Audio output is closed or device disconnected"
		#echo $TIME_STAMP$LOG_MSG >> /var/log/moode.log
	else
		TIME_STAMP=$(date +'%Y%m%d %H%M%S')
		LOG_MSG=" watchdog: Info: Audio output is in use"
		#echo $TIME_STAMP$LOG_MSG >> /var/log/moode.log
		WAKE_DISPLAY=$(sqlite3 $SQL_DB "SELECT value FROM cfg_system WHERE param='wake_display'")
		if [[ $WAKE_DISPLAY = "1" ]]; then
			export DISPLAY=:0
			xset s reset > /dev/null 2>&1
		fi
	fi

	sleep 6
	FPM_CNT=$(pgrep -c -f "php-fpm: pool www")
	MPD_ACTIVE=$(pgrep -c -x mpd)
	HW_PARAMS_LAST=$HW_PARAMS

done > /dev/null 2>&1 &
