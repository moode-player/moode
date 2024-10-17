#!/bin/bash
#
# SPDX-License-Identifier: GPL-3.0-or-later
# Copyright 2014 The moOde audio player project / Tim Curtis
#

LOGFILE="/var/log/moode_spotevent.log"
DEBUG=$(sudo moodeutl -d -gv debuglog)

debug_log () {
	if [[ $DEBUG == '0' ]]; then
		return 0
	fi
	echo "$1"
	TIME=$(date +'%Y%m%d %H%M%S')
	echo "$TIME $1" >> $LOGFILE
}

PLAYER_EVENTS=(
session_connected
session_disconnected
)

MATCH=0
for EVENT in "${PLAYER_EVENTS[@]}"
do
	if [[ $PLAYER_EVENT == $EVENT ]]; then
		MATCH=1
		debug_log "Process: "$PLAYER_EVENT
	fi
done
# Exit and log if not a match
if [[ $MATCH == 0 ]]; then
	debug_log "Logged:  "$PLAYER_EVENT
	exit 0
fi

SQLDB=/var/local/www/db/moode-sqlite3.db
RESULT=$(sqlite3 $SQLDB "SELECT value FROM cfg_system WHERE param IN ('volknob','alsavolume_max','alsavolume','amixname','mpdmixer','camilladsp_volume_sync','rsmafterspot','inpactive','volknob_mpd','multiroom_tx')")
readarray -t arr <<<"$RESULT"
VOLKNOB=${arr[0]}
ALSAVOLUME_MAX=${arr[1]}
ALSAVOLUME=${arr[2]}
AMIXNAME=${arr[3]}
MPDMIXER=${arr[4]}
CDSP_VOLSYNC=${arr[5]}
RSMAFTERSPOT=${arr[6]}
INPACTIVE=${arr[7]}
VOLKNOB_MPD=${arr[8]}
MULTIROOM_TX=${arr[9]}
RX_ADDRESSES=$(sudo moodeutl -d -gv rx_addresses)

if [[ $INPACTIVE == '1' ]]; then
	exit 1
fi

if [[ $PLAYER_EVENT == "session_connected" ]]; then
	$(sqlite3 $SQLDB "UPDATE cfg_system SET value='1' WHERE param='spotactive'")
	/usr/bin/mpc stop > /dev/null
	sleep 1

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
					echo $(date +%F" "%T) "Event: trx_control -set-alsavol failed: $IP_ADDR" >> $LOGFILE
				fi
			fi
		done
	fi
fi

#if [[ $PLAYER_EVENT == "paused" ]] || [[ $PLAYER_EVENT == "stopped" ]]; then
if [[ $PLAYER_EVENT == "session_disconnected" ]]; then
	$(sqlite3 $SQLDB "UPDATE cfg_system SET value='0' WHERE param='spotactive'")

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
					echo $(date +%F" "%T) "Event: set_volume -restore failed: $IP_ADDR" >> $LOGFILE
				fi
			fi
		done
	fi

	if [[ $RSMAFTERSPOT == "Yes" ]]; then
		/usr/bin/mpc play > /dev/null
	fi
fi
