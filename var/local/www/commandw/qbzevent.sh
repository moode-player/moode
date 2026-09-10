#!/bin/bash
#
# SPDX-License-Identifier: GPL-3.0-or-later
# Copyright 2014 The moOde audio player project / Tim Curtis
# Copyright 2026 @PhilipVinc fork of moode / https://github.com/PhilipVinc/moode
#
# Qbzd event script
# The qbzd daemon runs this script for each event emitted and stores the event
# data in QBZ_* environment variables.
#
# NOTE: qbzd does not offer an option to wait for script completion which means
# events can overlap causing loss of event data. Script locking is used as a
# workaround to help serialize the events.

# Remove this when qbzd implements a 'wait_for_script_completion' option
exec 9> /tmp/qbzevent.lock
flock 9

LOGFILE="/var/log/moode_qbzevent.log"
DEBUG=$(sudo moodeutl -d -gv debuglog)
SQLDB=/var/local/www/db/moode-sqlite3.db

QBZMETA_CACHE_FILE="/var/local/www/qbzmeta.json"
QBZD_API="http://127.0.0.1:8182"

debug_log () {
	if [[ $DEBUG == '0' ]]; then
		return 0
	fi
	echo "$1"
	TIME=$(date +'%Y%m%d %H%M%S')
	echo "$TIME $1" >> $LOGFILE
}

PLAYER_EVENTS=(
QconnectSessionChanged
PlaybackStateChanged
TrackStarted
PlaybackError
)

# Fill the metadata vars from the daemon's queue.
#
# TrackStarted is the only writer of the cache, and the cache is truncated when
# a session ends. That leaves a gap: the Qobuz app hands a session over PAUSED,
# so the daemon loads the track and waits for a play command -- no TrackStarted
# is emitted, and the renderer screen has nothing to show for as long as
# playback has not been started. The daemon knows the track the whole time, so
# ask it rather than giving up. A daemon that answers nothing leaves the vars
# untouched and the caller skips exactly as before.
read_metadata_from_daemon () {
	local META BITS RATE
	META=$(curl -s --max-time 2 $QBZD_API/api/queue | jq -r '.current_track // empty |
		[.title, .artist, .album, (.duration_secs | tostring), (.artwork_url // ""),
		((.bit_depth // "") | tostring), ((.sample_rate // "") | tostring)] | @tsv' 2>/dev/null)
	if [[ -z $META ]]; then
		debug_log "- Daemon metadata: no current track"
		return
	fi
	IFS=$'\t' read -r title artist album duration cover_url BITS RATE <<< "$META"
	# sample_rate is reported in Hz; TrackStarted formats it in kHz
	if [[ -n $BITS && -n $RATE ]]; then
		RATE=$(awk -v r="$RATE" 'BEGIN {printf "%g", (r >= 1000 ? r / 1000 : r)}')
		sformat="FLAC $BITS/$RATE kHz"
	fi
	debug_log "- Daemon metadata: $title / $artist"
}

MATCH=0
for MATCH_EVENT in "${PLAYER_EVENTS[@]}"
do
	if [[ $QBZ_EVENT == $MATCH_EVENT ]]; then
		MATCH=1
		debug_log "Process: "$QBZ_EVENT
		if [[ $QBZ_EVENT == "QconnectSessionChanged" ]]; then
			#debug_log "- Env: QBZ_SESSION_ACTIVE="$QBZ_SESSION_ACTIVE
			#debug_log "- Env: QBZ_STATE="$QBZ_STATE
			if [[ $QBZ_SESSION_ACTIVE == 'true' && $QBZ_STATE == 'connected' ]]; then
				debug_log "- CLIENT CONNECTED"
			fi
		elif [[ $QBZ_EVENT == "PlaybackStateChanged" ]]; then
			debug_log "- PLAYSTATE="$QBZ_STATE
			if [[ $QBZ_STATE == "stopped" ]]; then
				debug_log "- CLIENT DISCONNECTED"
			fi
		elif [[ $QBZ_EVENT == "TrackStarted" ]]; then
			debug_log "- METADATA RECEIVED"
			debug_log "- Env: QBZ_TITLE=$QBZ_TITLE"
			debug_log "- Env: QBZ_ARTIST=$QBZ_ARTIST"
			debug_log "- Env: QBZ_ALBUM=$QBZ_ALBUM"
			debug_log "- Env: QBZ_DURATION=$QBZ_DURATION"
			debug_log "- Env: QBZ_COVER_URL=$QBZ_COVER_URL"
			debug_log "- Env: SFORMAT=FLAC $QBZ_BIT_DEPTH/$QBZ_SAMPLE_RATE kHz"
		elif [[ $QBZ_EVENT == "PlaybackError" ]]; then
			debug_log "- Env: QBZ_STATE="$QBZ_STATE
		fi
	fi
done
# Exit and log if not a match
if [[ $MATCH == 0 ]]; then
	debug_log "Logged:  "$QBZ_EVENT
	exit 0
fi

# cfg_system
RESULT=$(sqlite3 $SQLDB "SELECT value FROM cfg_system WHERE param IN ('alsavolume_max','alsavolume','amixname','rsmafterqbz','qbzactive','camilladsp_volume_sync','inpactive','multiroom_tx')")
readarray -t arr <<<"$RESULT"
ALSAVOLUME_MAX=${arr[0]}
ALSAVOLUME=${arr[1]}
AMIXNAME=${arr[2]}
RSMAFTERQBZ=${arr[3]}
QBZACTIVE=${arr[4]}
CDSP_VOLSYNC=${arr[5]}
INPACTIVE=${arr[6]}
MULTIROOM_TX=${arr[7]}
RX_ADDRESSES=$(sudo moodeutl -d -gv rx_addresses)

if [[ $INPACTIVE == '1' ]]; then
	exit 1
fi

# Connect
if [[ $QBZ_EVENT == "QconnectSessionChanged" && $QBZ_SESSION_ACTIVE == "true" && $QBZ_STATE == "connected" ]]; then
	$(sqlite3 $SQLDB "UPDATE cfg_system SET value='1' WHERE param='qbzactive'")
	/usr/bin/mpc stop > /dev/null
	# Send to front-end
	/var/www/util/send-fecmd.php "qbzactive1"
	# The overlay is up now, but nothing has told the front end what is on it:
	# the cache is pushed by TrackStarted and by a play/pause change only, so a
	# session that reconnects mid-track leaves the renderer screen empty until
	# the next track starts. Re-send what is already cached.
	if [[ -s $QBZMETA_CACHE_FILE ]]; then
		/var/www/util/send-fecmd.php "$(cat $QBZMETA_CACHE_FILE)"
	fi

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
if [[ $QBZ_EVENT == "PlaybackStateChanged" &&  $QBZ_STATE == "stopped" ]]; then
	# Worker picks this up and sends qbzactive0 to front-end
	$(sqlite3 $SQLDB "UPDATE cfg_system SET value='0' WHERE param='qbzactive'")

	# Truncate metadata file
	truncate /var/local/www/qbzmeta.json --size 0

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

	if [[ $RSMAFTERQBZ == "Yes" ]]; then
		/usr/bin/mpc play > /dev/null
	fi
fi

# Track started
if [[ $QBZ_EVENT == "TrackStarted" ]]; then
	SFORMAT="FLAC $QBZ_BIT_DEPTH/$QBZ_SAMPLE_RATE kHz"
	OFORMAT=$(/var/www/util/get-oformat.php)
	PLAYSTATE="Resume"
	METADATA_JSON=$(jq -n -c \
		--arg a "update_qbzmeta" \
		--arg b "$QBZ_TITLE" \
		--arg c "$QBZ_ARTIST" \
		--arg d "$QBZ_ALBUM" \
		--arg e "$QBZ_DURATION" \
		--arg f "$QBZ_COVER_URL" \
		--arg g "$SFORMAT" \
		--arg h "$OFORMAT" \
		--arg i "$PLAYSTATE" \
		'{fecmd: $a, title: $b, artist: $c, album: $d, duration: $e, cover_url: $f, sformat: $g, oformat: $h, playstate: $i}')
	echo -e "$METADATA_JSON" > $QBZMETA_CACHE_FILE
	/var/www/util/send-fecmd.php "$METADATA_JSON"
fi

# Playstate paused/playing
if [[ $QBZ_EVENT == "PlaybackStateChanged" ]]; then
	if [[ $QBZ_STATE == "paused" ]]; then
		PLAYSTATE="Pause"
	elif [[ $QBZ_STATE == "playing" ]]; then
		PLAYSTATE="Resume"
	fi
	# Read cache into env vars
	while IFS== read -r key value; do
		export "$key=$value"
		debug_log "- Read cache: $key=$value"
	done < <(jq -r 'to_entries[] | "\(.key)=\(.value)"' $QBZMETA_CACHE_FILE)

	# Nothing cached yet: the session was handed over paused, so no TrackStarted
	# has run. Ask the daemon instead of leaving the screen blank.
	if [[ "$cover_url" == "" ]]; then
		read_metadata_from_daemon
	fi

	# Update cache/send to front-end
	if [[ "$cover_url" == "" ]]; then
		debug_log "- Cover URL: empty"
		debug_log "- Update cache: skipped"
	else
		OFORMAT=$(/var/www/util/get-oformat.php)
		METADATA_JSON=$(jq -n -c \
			--arg a "update_qbzmeta" \
			--arg b "$title" \
			--arg c "$artist" \
			--arg d "$album" \
			--arg e "$duration" \
			--arg f "$cover_url" \
			--arg g "$sformat" \
			--arg h "$OFORMAT" \
			--arg i "$PLAYSTATE" \
			'{fecmd: $a, title: $b, artist: $c, album: $d, duration: $e, cover_url: $f, sformat: $g, oformat: $h, playstate: $i}')
		debug_log "- Update cache: playstate=$PLAYSTATE"
		echo -e "$METADATA_JSON" > $QBZMETA_CACHE_FILE
		debug_log "- Send to front-end"
		/var/www/util/send-fecmd.php "$METADATA_JSON"
	fi
fi
