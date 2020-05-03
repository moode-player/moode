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
# 2020-05-03 TC moOde 6.5.2
#

FPM_LIMIT=40
FPM_CNT=$(pgrep -c -f "php-fpm: pool www")
MPD_ACTIVE=$(pgrep -c -x mpd)
SPOT_ACTIVE=$(pgrep -c -x librespot)
HW_PARAMS_LAST=""
SQL_DB=/var/local/www/db/moode-sqlite3.db
SESSION_DIR=/var/local/php
SESSION_FILE=$SESSION_DIR/sess_$(sqlite3 $SQL_DB "SELECT value FROM cfg_system WHERE param='sessionid'")

while true; do
	# PHP-FPM
	if (( FPM_CNT > FPM_LIMIT )); then
		TIME_STAMP=$(date +'%Y%m%d %H%M%S')
		LOG_MSG=" watchdog: PHP restarted (fpm child limit > "$FPM_LIMIT")"
		echo $TIME_STAMP$LOG_MSG >> /var/log/moode.log
		systemctl restart php7.3-fpm
	fi

	# PHP session permissions
	PERMS=$(ls -l $SESSION_FILE | awk '{print $1 "," $3 "," $4;}')
	if [[ $PERMS != "-rw-rw-rw-,www-data,www-data" ]]; then
		TIME_STAMP=$(date +'%Y%m%d %H%M%S')
		LOG_MSG=" watchdog: PHP session permissions (reapplied)"
		echo $TIME_STAMP$LOG_MSG >> /var/log/moode.log
		chown www-data:www-data $SESSION_FILE
		chmod 0666 $SESSION_FILE
	fi

	# MPD
	if [[ $MPD_ACTIVE = 0 ]]; then
		TIME_STAMP=$(date +'%Y%m%d %H%M%S')
		LOG_MSG=" watchdog: MPD restarted (check syslog for errors)"
		echo $TIME_STAMP$LOG_MSG >> /var/log/moode.log
		systemctl start mpd
	fi

	# Librespot
	RESULT=$(sqlite3 $SQL_DB "SELECT value FROM cfg_system WHERE param='spotifysvc'")
	if [[ $RESULT = "1" ]]; then
		if [[ $SPOT_ACTIVE = 0 ]]; then
			TIME_STAMP=$(date +'%Y%m%d %H%M%S')
			LOG_MSG=" watchdog: LIBRESPOT restarted (check syslog for errors)"
			echo $TIME_STAMP$LOG_MSG >> /var/log/moode.log
			/var/www/command/restart-renderer.php -spotify
		fi
	fi

	# Audio output
	CARD_NUM=$(sqlite3 $SQL_DB "SELECT value FROM cfg_mpd WHERE param='device'")
	HW_PARAMS=$(cat /proc/asound/card$CARD_NUM/pcm0p/sub0/hw_params)
	TIME_STAMP=$(date +'%Y%m%d %H%M%S')
	if [[ $HW_PARAMS = "closed" ]]; then
		LOG_MSG=" watchdog: Info: Audio output is (closed)"
	else
		TIME_STAMP=$(date +'%Y%m%d %H%M%S')
		LOG_MSG=" watchdog: Info: Audio output is (in use)"
		# Wake display on play
		WAKE_DISPLAY=$(sqlite3 $SQL_DB "SELECT value FROM cfg_system WHERE param='wake_display'")
		if [[ $WAKE_DISPLAY = "1" ]]; then
			export DISPLAY=:0
			xset s reset > /dev/null 2>&1
		fi
	fi
	#echo $TIME_STAMP$LOG_MSG >> /var/log/moode.log

	# Wlan0
	ETH0_IP_ADDR=$(ifconfig eth0 | grep "inet ")
	if [[ $ETH0_IP_ADDR = "" ]]; then
		WLAN0_IP_ADDR=$(ifconfig wlan0 | grep "inet ")
		if [[ $WLAN0_IP_ADDR = "" ]]; then
			wpa_cli -i wlan0 scan > /dev/null 2>&1
			ip --force link set wlan0 up > /dev/null 2>&1
			TIME_STAMP=$(date +'%Y%m%d %H%M%S')
			echo $TIME_STAMP" watchdog: Wlan0 down attempting reset" >> /var/log/moode.log
		fi
	fi

	sleep 6
	FPM_CNT=$(pgrep -c -f "php-fpm: pool www")
	MPD_ACTIVE=$(pgrep -c -x mpd)
	SPOT_ACTIVE=$(pgrep -c -x librespot)
	HW_PARAMS_LAST=$HW_PARAMS

done > /dev/null 2>&1 &
