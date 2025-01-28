#!/bin/bash
#
# SPDX-License-Identifier: GPL-3.0-or-later
# Copyright 2014 The moOde audio player project / Tim Curtis
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
#sleep 1

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
		RESULT=$(curl -G -S -s --data-urlencode "cmd=trx_control -set-alsavol" http://$IP_ADDR/command/)
		if [[ $RESULT != "" ]]; then
			RESULT=$(curl -G -S -s --data-urlencode "cmd=trx_control -set-alsavol" http://$IP_ADDR/command/)
			if [[ $RESULT != "" ]]; then
				echo $(date +'%Y%m%d %H%M%S') "Event: trx_control -set-alsavol failed: $IP_ADDR" >> $LOGFILE
			fi
		fi
	done
fi
