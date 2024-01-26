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

debug_log "Event: Run spspre.sh"

SQLDB=/var/local/www/db/moode-sqlite3.db
RESULT=$(sqlite3 $SQLDB "SELECT value FROM cfg_system WHERE param IN ('volknob','alsavolume_max','alsavolume','amixname','camilladsp_volume_sync','inpactive','multiroom_tx')")
readarray -t arr <<<"$RESULT"
VOLKNOB=${arr[0]}
ALSAVOLUME_MAX=${arr[1]}
ALSAVOLUME=${arr[2]}
AMIXNAME=${arr[3]}
CDSP_VOLSYNC=${arr[4]}
INPACTIVE=${arr[5]}
MULTIROOM_TX=${arr[6]}
RX_ADDRESSES=$(sudo moodeutl -d -gv rx_addresses)

if [[ $INPACTIVE == '1' ]]; then
	exit 1
fi

$(sqlite3 $SQLDB "UPDATE cfg_system SET value='1' WHERE param='aplactive'")
/usr/bin/mpc stop > /dev/null

# Local
if [[ $CDSP_VOLSYNC == "on" ]]; then
	# Set 0dB CDSP volume
	sed -i '0,/- -.*/s//- 0.0/' /var/lib/cdsp/statefile.yml
elif [[ $ALSAVOLUME != "none" ]]; then
	# Set 0dB ALSA volume
	/var/www/util/sysutil.sh set-alsavol "$AMIXNAME" $ALSAVOLUME_MAX
fi

# Multiroom receivers
if [[ $MULTIROOM_TX == "On" ]]; then
	for IP_ADDR in $RX_ADDRESSES; do
		RESULT=$(curl -G -S -s --data-urlencode "cmd=trx-control.php -set-alsavol $ALSAVOLUME_MAX" http://$IP_ADDR/command/)
		if [[ $RESULT != "" ]]; then
			RESULT=$(curl -G -S -s --data-urlencode "cmd=trx-control.php -set-alsavol $ALSAVOLUME_MAX" http://$IP_ADDR/command/)
			if [[ $RESULT != "" ]]; then
				echo $(date +'%Y%m%d %H%M%S') "Event: trx-control.php -set-alsavol failed: $IP_ADDR" >> $LOGFILE
			fi
		fi
	done
fi
