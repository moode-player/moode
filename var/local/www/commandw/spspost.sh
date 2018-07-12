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
# 2018-07-11 TC moOde 4.2
# - minor format cleanup
# - update to vol.sh restore
#

SQLDB=/var/local/www/db/moode-sqlite3.db

# set airplay active flag false
$(sqlite3 $SQLDB "update cfg_system set value='0' where param='airplayactv'")

RESULT=$(sqlite3 $SQLDB "select value from cfg_system where param='alsavolume' or param='amixname' or param='mpdmixer' or param='rsmafterapl'")
# friendly names
readarray -t arr <<<"$RESULT"
ALSAVOLUME=${arr[0]}
AMIXNAME=${arr[1]}
MPDMIXER=${arr[2]}
RSMAFTERAPL=${arr[3]}

# restore 0dB (100%) hardware volume when mpd configured as below
if [[ $MPDMIXER == "software" || $MPDMIXER == "disabled" ]]; then
	if [[ $ALSAVOLUME != "none" ]]; then
		/var/www/command/util.sh set-alsavol "$AMIXNAME" 100
	fi
fi

# restore volume
/var/www/vol.sh restore

# resume playback if indicated
if [[ $RSMAFTERAPL == "Yes" ]]; then
	/usr/bin/mpc play > /dev/null
fi
