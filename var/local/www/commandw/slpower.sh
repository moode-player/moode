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

RESULT=$(sqlite3 $SQLDB "select value from cfg_system where param in ('alsavolume', 'rsmaftersl', 'wrkready', 'inpactive')")
# friendly names
readarray -t arr <<<"$RESULT"
ALSAVOLUME=${arr[0]}
RSMAFTERSL=${arr[1]}
WRKREADY=${arr[2]}
INPACTIVE=${arr[3]}

if [[ $INPACTIVE == '1' ]]; then
	exit 1
fi

if [[ $WRKREADY == "1" ]]; then
	echo Worker ready
	# See if -V hardware mixer is present in OTHEROPTIONS
	VOPT=$(sqlite3 $SQLDB "select * from cfg_sl where value like '%-V%'")
	if [[ $1 = "0" ]] ; then
		echo Power off
		$(sqlite3 $SQLDB "update cfg_system set value='0' where param='slactive'")
		if [[ $VOPT != "" ]] ; then
			ALSAVOL=$(/var/www/command/util.sh get-alsavol "`/var/www/command/util.sh get-mixername| sed 's/[()]/"/g'`")
			if [[ $ALSAVOL == "0%" ]] ; then
				/var/www/vol.sh -restore
			fi
		else
			/var/www/vol.sh -restore
		fi
		if [[ $RSMAFTERSL == "Yes" ]]; then
			/usr/bin/mpc play > /dev/null
		fi
	elif [[ $1 = "1" ]] ; then
		echo Power on
		/usr/bin/mpc stop > /dev/null
		sleep 1
		$(sqlite3 $SQLDB "update cfg_system set value='1' where param='slactive'")
		if [[ $ALSAVOLUME != "none" && $VOPT == "" ]]; then
			/var/www/command/util.sh set-alsavol-to-max
		fi
	else
		# Value 2 is returned
	    echo Power button state $1
	fi
else
	echo Worker not ready
fi

exit 0
