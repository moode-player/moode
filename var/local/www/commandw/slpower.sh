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
# 2019-11-24 TC moOde 6.4.0
#

SQLDB=/var/local/www/db/moode-sqlite3.db

RESULT=$(sqlite3 $SQLDB "select value from cfg_system where param='alsavolume_max' or param='alsavolume' or param='amixname' or param='rsmaftersl' or param='wrkready' or param='inpactive'")
# friendly names
readarray -t arr <<<"$RESULT"
ALSAVOLUME_MAX=${arr[0]}
ALSAVOLUME=${arr[1]}
AMIXNAME=${arr[2]}
RSMAFTERSL=${arr[3]}
WRKREADY=${arr[4]}
INPACTIVE=${arr[5]}

if [[ $INPACTIVE == '1' ]]; then
	exit 1
fi

if [[ $WRKREADY == "1" ]]; then
	echo Worker ready
	if [[ $1 = "0" ]] ; then
		echo Power off
		$(sqlite3 $SQLDB "update cfg_system set value='0' where param='slactive'")
		/var/www/vol.sh -restore
		if [[ $RSMAFTERSL == "Yes" ]]; then
			/usr/bin/mpc play > /dev/null
		fi
	elif [[ $1 = "1" ]] ; then
		echo Power on
		/usr/bin/mpc stop > /dev/null
		sleep 1
		$(sqlite3 $SQLDB "update cfg_system set value='1' where param='slactive'")
		if [[ $ALSAVOLUME != "none" ]]; then
			/var/www/command/util.sh set-alsavol "$AMIXNAME" $ALSAVOLUME_MAX
		fi
	else
		# Value 2 is returned
	    echo Power button state $1
	fi
else
	echo Worker not ready
fi

exit 0
