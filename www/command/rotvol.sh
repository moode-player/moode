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
# 2018-01-26 TC moOde 4.0
# 2018-04-02 TC moOde 4.1 remove vol warning
#

SQLDB=/var/local/www/db/moode-sqlite3.db

RESULT=$(sqlite3 $SQLDB "select value from cfg_system where id in ('32', '33', '36', '37', '77')")

# friendly names
readarray -t arr <<<"$RESULT"
VOLKNOB=${arr[0]}
VOLMUTE=${arr[1]}
AMIXNAME=${arr[3]}
MPDMIXER=${arr[4]}
CARDNUM=${arr[5]}
# cardnum 0 = i2s or onboard, cardnum 1 = usb 

# volume step
if [[ $1 = "up" ]]; then
	LEVEL=$(($VOLKNOB + $2))
elif [[ $1 = "dn" ]]; then
	LEVEL=$(($VOLKNOB - $2))
fi
	
# range check
if (( $LEVEL < 0 )); then
	LEVEL=0
elif (( $LEVEL > 100 )); then
	LEVEL=100
fi

# update knob level
sqlite3 $SQLDB "update cfg_system set value=$LEVEL where id='32'"

# mute if indicated
if [[ $VOLMUTE = "1" ]]; then
	mpc volume 0 >/dev/null
	exit 1
fi

# volume: update MPD volume --> MPD idle timeout --> UI updated
mpc volume $LEVEL >/dev/null
