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
# 2018-01-26 TC moOde 4.0
#

FPMLIMIT=40
FPMCNT=$(pgrep -c -f "php-fpm: pool www")
MPDACTIVE=$(pgrep -c -x mpd)

while true; do
	# PHP
	if (( FPMCNT > FPMLIMIT )); then
		TIMESTAMP=$(date +'%Y%m%d %H%M%S')
		LOGMSG=" watchdog: PHP restart (fpm child limit > "$FPMLIMIT")"
		echo $TIMESTAMP$LOGMSG >> /var/log/moode.log
		systemctl restart php7.0-fpm
	fi

	# MPD
	if [[ $MPDACTIVE = 0 ]]; then
		TIMESTAMP=$(date +'%Y%m%d %H%M%S')
		LOGMSG=" watchdog: MPD restart (check syslog for MPD errors)"
		echo $TIMESTAMP$LOGMSG >> /var/log/moode.log
		systemctl start mpd
	fi

	# for SACD ISO - (2CH-DST)
	export master_pid=$(pidof mpd||pgrep mpd||cat /var/run/mpd/pid)
	export lastsong_cksum
	decoder_threads=( $(ps -q $master_pid -eL -o etimes,lwp,psr,comm,args | awk '($5~"mpd" && $4~"decoder:sacdiso" ){print $1,$2}' | sort -nr | cut -d' ' -f 2) )
	# if echo currentsong | socat  TCP4:127.0.0.1:6600 stdio | grep '^Album' | grep -q 2CH-DST ; then
	if [[ ${#decoder_threads[@]} -gt 1 ]]; then
	  currentsong_cksum=$(echo currentsong | socat  TCP4:127.0.0.1:6600 stdio | md5sum)
	  currentsong_cksum=${currentsong_cksum%  -}
	  if [[ "${currentsong_cksum}" != "${lastsong_cksum}" ]]; then
	    ccc=0
	    for tid in ${decoder_threads[@]:1}
	    do
	      # sudo taskset -p --cpu-list $(( ccc%2 + 2 ))  $tid && ((ccc++))  # cpu 2-3 ; dstdec_threads "2"
	      sudo taskset -p --cpu-list $(( ccc%4 ))  $tid && ((ccc++))  # cpu 0-3 ; dstdec_threads "4"
	      sudo chrt --other -p 0  $tid
	    done
	    export lastsong_cksum=${currentsong_cksum}
	  fi
	fi

	sleep 6
	FPMCNT=$(pgrep -c -f "php-fpm: pool www")
	MPDACTIVE=$(pgrep -c -x mpd)

done > /dev/null 2>&1 &
