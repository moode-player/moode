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

$(sqlite3 $SQLDB "update cfg_system set value='0' where param='airplayactv'")

RESULT=$(sqlite3 $SQLDB "select value from cfg_system where param in ('alsavolume', 'mpdmixer', 'rsmafterapl', 'inpactive')")
readarray -t arr <<<"$RESULT"
ALSAVOLUME=${arr[0]}
MPDMIXER=${arr[1]}
RSMAFTERAPL=${arr[2]}
INPACTIVE=${arr[3]}

if [[ $INPACTIVE == '1' ]]; then
	exit 1
fi

# Restore 0dB hardware volume when MPD configured as below
if [[ $MPDMIXER == "software" || $MPDMIXER == "disabled" ]]; then
	if [[ $ALSAVOLUME != "none" ]]; then
		/var/www/command/util.sh set-alsavol-to-max
	fi
fi

/var/www/vol.sh -restore

if [[ $RSMAFTERAPL == "Yes" ]]; then
	/usr/bin/mpc play > /dev/null
fi
