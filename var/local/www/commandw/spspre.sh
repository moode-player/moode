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

RESULT=$(sqlite3 $SQLDB "select value from cfg_system where param='alsavolume_max' or param='alsavolume' or param='amixname' or param='inpactive'")
readarray -t arr <<<"$RESULT"
ALSAVOLUME_MAX=${arr[0]}
ALSAVOLUME=${arr[1]}
AMIXNAME=${arr[2]}
INPACTIVE=${arr[3]}

if [[ $INPACTIVE == '1' ]]; then
	exit 1
fi

/usr/bin/mpc stop > /dev/null

# Allow time for ui update
sleep 1

$(sqlite3 $SQLDB "update cfg_system set value='1' where param='airplayactv'")

if [[ $ALSAVOLUME != "none" ]]; then
	/var/www/command/util.sh set-alsavol "$AMIXNAME" $ALSAVOLUME_MAX
fi
