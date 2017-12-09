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
# 2017-12-07 TC moOde 4.0
#

SQLDB=/var/local/www/db/moode-sqlite3.db

if [[ -z $1 ]]; then
	echo $(sqlite3 $SQLDB "select value from cfg_system where id='32'")
	exit 0
fi

if [[ $1 = "-help" ]]; then
	echo "vol.sh with no arguments will print the current volume level"
	echo "vol.sh restore will set alsa/mpd volume based on current knob setting"
	echo "vol.sh <level between 0-100>, mute (toggle), up <step> or dn <step>, -help"
	exit 1
fi

# get config settings
RESULT=$(sqlite3 $SQLDB "select value from cfg_system where id in ('32', '33', '34', '36', '37', '77')")

# friendly names
readarray -t arr <<<"$RESULT"
VOLKNOB=${arr[0]}
VOLMUTE=${arr[1]}
VOLWARNING=${arr[2]}
AMIXNAME=${arr[3]}
MPDMIXER=${arr[4]}
CARDNUM=${arr[5]}

# card 0 = i2s or onboard, card 1 = usb 
#TMP=$(cat /proc/asound/card1/id 2>/dev/null)
#if [[ $TMP = "" ]]; then CARDNUM=0; else CARDNUM=1; fi

REGEX='^[+-]?[0-9]+$'

# mute toggle
if [[ $1 = "mute" ]]; then
	if [[ $VOLMUTE = "1" ]]; then
		sqlite3 $SQLDB "update cfg_system set value='0' where id='33'"
		VOLMUTE=0
		LEVEL=$VOLKNOB 
	else
		sqlite3 $SQLDB "update cfg_system set value='1' where id='33'"
		VOLMUTE=1
	fi
else
	# restore alsa/mpd volume
	if [[ $1 = "restore" ]]; then
		LEVEL=$VOLKNOB
	# volume step
	elif [[ $1 = "up" ]]; then
		LEVEL=$(($VOLKNOB + $2))
	elif [[ $1 = "dn" ]]; then
		LEVEL=$(($VOLKNOB - $2))
	# volume level
	else
		LEVEL=$1
	fi

	# numeric check
	if ! [[ $LEVEL =~ $REGEX ]]; then
		echo "Level must only contain digits 0-9"
		exit 1
	fi
	
	# range check
	if (( $LEVEL < 0 )); then
		LEVEL=0
	elif (( LEVEL > VOLWARNING )); then
		echo "Volume exceeds warning limit $VOLWARNING"
		exit 1
	else
		# update knob level
		sqlite3 $SQLDB "update cfg_system set value=$LEVEL where id='32'"
	fi
fi

# mute if indicated
if [[ $VOLMUTE = "1" ]]; then
	mpc volume 0 >/dev/null
	exit 1
fi

# set volume level
if [[ $MPDMIXER = "hardware" ]]; then
	# hardware volume: update ALSA volume --> MPD volume --> MPD idle timeout --> UI updated
	amixer -c $CARDNUM sset "$AMIXNAME" -M $LEVEL% > /dev/null
else
	# software volume: update MPD volume --> MPD idle timeout --> UI updated
	mpc volume $LEVEL >/dev/null
fi
