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

LOGFILE="/var/log/moode_spsevent.log"
DEBUG=$(sudo moodeutl -d -gv debuglog)

debug_log () {
	if [[ $DEBUG == '0' ]]; then
		return 0
	fi
	echo "$1"
	TIME=$(date +'%Y%m%d %H%M%S')
	echo "$TIME $1" >> $LOGFILE
}

debug_log "Event: Run spspost.sh"

SQLDB=/var/local/www/db/moode-sqlite3.db
$(sqlite3 $SQLDB "UPDATE cfg_system SET value='0' WHERE param='aplactive'")
RESULT=$(sqlite3 $SQLDB "SELECT value FROM cfg_system WHERE param IN ('alsavolume_max','alsavolume','amixname','mpdmixer','rsmafterapl','camilladsp_volume_sync','inpactive','volknob_mpd','multiroom_tx')")
readarray -t arr <<<"$RESULT"
ALSAVOLUME_MAX=${arr[0]}
ALSAVOLUME=${arr[1]}
AMIXNAME=${arr[2]}
MPDMIXER=${arr[3]}
RSMAFTERAPL=${arr[4]}
CDSP_VOLSYNC=${arr[5]}
INPACTIVE=${arr[6]}
VOLKNOB_MPD=${arr[7]}
MULTIROOM_TX=${arr[8]}
RX_ADDRESSES=$(sudo moodeutl -d -gv rx_addresses)

if [[ $INPACTIVE == '1' ]]; then
	exit 1
fi

# Local
/var/www/util/vol.sh -restore

if [[ $CDSP_VOLSYNC == "on" ]]; then
	# Restore CDSP volume
	systemctl restart mpd2cdspvolume
fi

# Multiroom receivers
if [[ $MULTIROOM_TX == "On" ]]; then
	for IP_ADDR in $RX_ADDRESSES; do
		RESULT=$(curl -G -S -s --data-urlencode "cmd=vol.sh -restore" http://$IP_ADDR/command/)
		if [[ $RESULT != "" ]]; then
			RESULT=$(curl -G -S -s --data-urlencode "cmd=vol.sh -restore" http://$IP_ADDR/command/)
			if [[ $RESULT != "" ]]; then
				echo $(date +'%Y%m%d %H%M%S') "Event: vol.sh -restore failed: $IP_ADDR" >> $LOGFILE
			fi
		fi
	done
fi

if [[ $RSMAFTERAPL == "Yes" ]]; then
	/usr/bin/mpc play > /dev/null
fi
