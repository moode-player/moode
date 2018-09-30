#!/bin/bash
#
# moOde audio player (C) 2014 Tim Curtis
# http://moodeaudio.org
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
# 2018-09-27 TC moOde 4.3
# - initial version
#

SQLDB=/var/local/www/db/moode-sqlite3.db

RESULT=$(sqlite3 $SQLDB "select value from cfg_system where param='alsavolume' or param='amixname' or param='mpdmixer' or param='rsmafterspot'")
readarray -t arr <<<"$RESULT"
ALSAVOLUME=${arr[0]}
AMIXNAME=${arr[1]}
MPDMIXER=${arr[2]}
RSMAFTERSPOT=${arr[3]}
	
if [[ $PLAYER_EVENT == "start" ]]; then	
	/usr/bin/mpc stop > /dev/null	
	# allow time for ui update
	sleep 1
	# set active flag true
	$(sqlite3 $SQLDB "update cfg_system set value='1' where param='spotactive'")

	if [[ $ALSAVOLUME != "none" ]]; then
		/var/www/command/util.sh set-alsavol "$AMIXNAME" 100
	fi
fi

if [[ $PLAYER_EVENT == "stop" ]]; then
	# set active flag false
	$(sqlite3 $SQLDB "update cfg_system set value='0' where param='spotactive'")
	
	# restore 0dB (100%) hardware volume when mpd configured as below
	if [[ $MPDMIXER == "software" || $MPDMIXER == "disabled" ]]; then
		if [[ $ALSAVOLUME != "none" ]]; then
			/var/www/command/util.sh set-alsavol "$AMIXNAME" 100
		fi
	fi
	
	# restore volume
	/var/www/vol.sh restore
	
	# resume playback if indicated
	if [[ $RSMAFTERSPOT == "Yes" ]]; then
		/usr/bin/mpc play > /dev/null
	fi
fi
