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

SQLDB=/var/local/www/db/moode-sqlite3.db

RESULT=$(sqlite3 $SQLDB "SELECT value FROM cfg_system WHERE param IN ('alsavolume_max','alsavolume','amixname','rsmaftersl','wrkready','inpactive')")
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
	# See if -V hardware mixer is present in OTHEROPTIONS
	VOPT=$(sqlite3 $SQLDB "SELECT * FROM cfg_sl WHERE value LIKE '%-V%'")
	if [[ $1 = "0" ]] ; then
		echo Power off
		$(sqlite3 $SQLDB "UPDATE cfg_system SET value='0' WHERE param='slactive'")
		if [[ $VOPT != "" ]] ; then
			ALSAVOL=$(/var/www/util/sysutil.sh get-alsavol "`/var/www/util/sysutil.sh get-mixername| sed 's/[()]/"/g'`")
			if [[ $ALSAVOL == "0%" ]] ; then
				/var/www/vol.sh -restore
			fi
		else
			/var/www/vol.sh -restore
		fi
		if [[ $RSMAFTERSL == "Yes" ]]; then
			echo Resume MPD
			/usr/bin/mpc play > /dev/null
		fi
	elif [[ $1 = "1" ]] ; then
		echo Power on
		$(sqlite3 $SQLDB "UPDATE cfg_system SET value='1' WHERE param='slactive'")
		/usr/bin/mpc stop > /dev/null
		sleep 1
		if [[ $ALSAVOLUME != "none" && $VOPT == "" ]]; then
			/var/www/util/sysutil.sh set-alsavol "$AMIXNAME" $ALSAVOLUME_MAX
		fi
	else
		# Value 2 is returned
	    echo Power button state $1
	fi
else
	echo Worker not ready
fi

exit 0
