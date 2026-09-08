#!/bin/bash
#
# SPDX-License-Identifier: GPL-3.0-or-later
# Copyright 2014 The moOde audio player project / Tim Curtis
#

LOGFILE="/var/log/moode_spotevent.log"
DEBUG=$(sudo moodeutl -d -gv debuglog)
SPOTMETA_CACHE_FILE="/var/local/www/spotmeta.json"
SQLDB=/var/local/www/db/moode-sqlite3.db

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
track_changed
playing
paused
stopped
)

MATCH=0
for MATCH_EVENT in "${PLAYER_EVENTS[@]}"
do
	if [[ $PLAYER_EVENT == $MATCH_EVENT ]]; then
		MATCH=1
		debug_log "Process: "$PLAYER_EVENT
	fi
done
# Exit and log if not a match
if [[ $MATCH == 0 ]]; then
	debug_log "Logged:  "$PLAYER_EVENT
	exit 0
fi

# cfg_system
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

# Source format
BITRATE=$(sqlite3 $SQLDB "SELECT value FROM cfg_spotify WHERE param='bitrate'")
SFORMAT="Vorbis "$BITRATE" kbps"
# Initial playstate
PLAYSTATE="Pause"

if [[ $INPACTIVE == '1' ]]; then
	exit 1
fi

# Connect
if [[ $PLAYER_EVENT == "session_connected" ]]; then
	$(sqlite3 $SQLDB "UPDATE cfg_system SET value='1' WHERE param='spotactive'")
	/usr/bin/mpc stop > /dev/null
	# Send to front-end
	/var/www/util/send-fecmd.php "spotactive1"

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

# Disconnect
if [[ $PLAYER_EVENT == "session_disconnected" ]]; then
	# Worker picks this up and sends spotactive0 to front-end
	$(sqlite3 $SQLDB "UPDATE cfg_system SET value='0' WHERE param='spotactive'")

	# Truncate metadata file
	truncate /var/local/www/spotmeta.json --size 0

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

# Track change
if [[ $PLAYER_EVENT == "track_changed" ]]; then
	ARTIST=$(echo -e -n "$ARTISTS" | tr "\n" ";" | cut -d';' -f1)
	COVER=$(echo -e -n "$COVERS" | tr "\n" ";" | cut -d';' -f1)
	OFORMAT=$(/var/www/util/get-oformat.php)
	METADATA_JSON=$(jq -n -c \
		--arg a "update_spotmeta" \
		--arg b "$NAME" \
		--arg c "$ARTIST" \
		--arg d "$ALBUM" \
		--arg e "$DURATION_MS" \
		--arg f "$COVER" \
		--arg g "$SFORMAT" \
		--arg h "$OFORMAT" \
		--arg i "$PLAYSTATE" \
		'{fecmd: $a, title: $b, artist: $c, album: $d, duration: $e, cover_url: $f, sformat: $g, oformat: $h, playstate: $i}')
	echo -e "$METADATA_JSON" > $SPOTMETA_CACHE_FILE
	/var/www/util/send-fecmd.php "$METADATA_JSON"
fi

# Playstate
if [[ $PLAYER_EVENT == "paused" || $PLAYER_EVENT == "playing" ]]; then
	if [[ $PLAYER_EVENT == "paused" ]]; then
		PLAYSTATE="Pause"
	else
		PLAYSTATE="Resume"
	fi
	# Read cache into env vars
	while IFS== read -r key value; do
		export "$key=$value"
		debug_log "- Read cache: $key=$value"
	done < <(jq -r 'to_entries[] | "\(.key)=\(.value)"' $SPOTMETA_CACHE_FILE)

	# Update cache/send to front-end
	if [[ "$cover_url" == "" ]]; then
		debug_log "- Cover URL: empty"
		debug_log "- Update cache: skipped"
	else
		OFORMAT=$(/var/www/util/get-oformat.php)
		METADATA_JSON=$(jq -n -c \
			--arg a "update_spotmeta" \
			--arg b "$title" \
			--arg c "$artist" \
			--arg d "$album" \
			--arg e "$duration" \
			--arg f "$cover_url" \
			--arg g "$SFORMAT" \
			--arg h "$OFORMAT" \
			--arg i "$PLAYSTATE" \
			'{fecmd: $a, title: $b, artist: $c, album: $d, duration: $e, cover_url: $f, sformat: $g, oformat: $h, playstate: $i}')
		debug_log "- Update cache: playstate=$PLAYSTATE"
		echo -e "$METADATA_JSON" > $SPOTMETA_CACHE_FILE
		debug_log "- Send to front-end"
		/var/www/util/send-fecmd.php "$METADATA_JSON"
	fi
fi
