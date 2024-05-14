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
		RESULT=$(curl -G -S -s --data-urlencode "cmd=set_volume -restore" http://$IP_ADDR/command/)
		if [[ $RESULT != "" ]]; then
			RESULT=$(curl -G -S -s --data-urlencode "cmd=set_volume -restore" http://$IP_ADDR/command/)
			if [[ $RESULT != "" ]]; then
				echo $(date +'%Y%m%d %H%M%S') "Event: set_volume -restore failed: $IP_ADDR" >> $LOGFILE
			fi
		fi
	done
fi

if [[ $RSMAFTERAPL == "Yes" ]]; then
	/usr/bin/mpc play > /dev/null
fi
