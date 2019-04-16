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
# 2019-04-12 TC moOde 5.0
#

FPMLIMIT=40
FPMCNT=$(pgrep -c -f "php-fpm: pool www")
MPDACTIVE=$(pgrep -c -x mpd)
SPOTACTIVE=$(pgrep -c -x librespot)
SQLDB=/var/local/www/db/moode-sqlite3.db

while true; do

	# PHP
	if (( FPMCNT > FPMLIMIT )); then
		TIMESTAMP=$(date +'%Y%m%d %H%M%S')
		LOGMSG=" watchdog: PHP restarted (fpm child limit > "$FPMLIMIT")"
		echo $TIMESTAMP$LOGMSG >> /var/log/moode.log
		systemctl restart php7.0-fpm
	fi

	# MPD
	if [[ $MPDACTIVE = 0 ]]; then
		TIMESTAMP=$(date +'%Y%m%d %H%M%S')
		LOGMSG=" watchdog: MPD restarted (check syslog for errors)"
		echo $TIMESTAMP$LOGMSG >> /var/log/moode.log
		systemctl start mpd
	fi

	# LIBRESPOT
	RESULT=$(sqlite3 $SQLDB "select value from cfg_system where param='spotifysvc'")
	if [[ $RESULT = "1" ]]; then
		if [[ $SPOTACTIVE = 0 ]]; then
			TIMESTAMP=$(date +'%Y%m%d %H%M%S')
			LOGMSG=" watchdog: LIBRESPOT restarted (check syslog for errors)"
			echo $TIMESTAMP$LOGMSG >> /var/log/moode.log
			/var/www/command/restart-spot.php
		fi
	fi
		
	sleep 6
	FPMCNT=$(pgrep -c -f "php-fpm: pool www")
	MPDACTIVE=$(pgrep -c -x mpd)
	SPOTACTIVE=$(pgrep -c -x librespot)

done > /dev/null 2>&1 &
