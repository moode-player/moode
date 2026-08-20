#!/bin/bash
#
# SPDX-License-Identifier: GPL-3.0-or-later
# Copyright 2014 The moOde audio player project / Tim Curtis
#
# Qobuz Connect (qbzd) event hook. qbzd forks this script once per daemon
# event with the event described in QBZ_* environment variables (set via
# QBZD_HOOK, see startQobuz() in inc/renderer.php).
#

LOGFILE="/var/log/moode_qbzevent.log"
DEBUG=$(sudo moodeutl -d -gv debuglog)
SQLDB=/var/local/www/db/moode-sqlite3.db

QBZMETA_CACHE_FILE="/var/local/www/qbzmeta.json"
QBZD_API="http://127.0.0.1:8182"
# Budget for retrying a start that failed because the audio device was still
# busy (e.g. shairport-sync releasing it late). qbzd does not retry on its own.
RETRY_FILE="/tmp/qbzevent-retry-budget"
RETRY_BUDGET=5

debug_log () {
	if [[ $DEBUG == '0' ]]; then
		return 0
	fi
	echo "$1"
	TIME=$(date +'%Y%m%d %H%M%S')
	echo "$TIME $1" >> $LOGFILE
}

PLAYER_EVENTS=(
PlaybackStateChanged
TrackStarted
QconnectSessionChanged
PlaybackError
)

MATCH=0
for MATCH_EVENT in "${PLAYER_EVENTS[@]}"
do
	if [[ $QBZ_EVENT == $MATCH_EVENT ]]; then
		MATCH=1
		debug_log "Process: "$QBZ_EVENT
	fi
done
# Exit and log if not a match
if [[ $MATCH == 0 ]]; then
	debug_log "Logged:  "$QBZ_EVENT
	exit 0
fi

# qbzd forks this script once per event without waiting so invocations can
# overlap: serialize them (and their cfg_system updates) to preserve ordering
exec 9> /tmp/qbzevent.lock
flock 9

# cfg_system (rows come back in id order)
RESULT=$(sqlite3 -cmd '.timeout 5000' $SQLDB "SELECT value FROM cfg_system WHERE param IN ('alsavolume_max','alsavolume','amixname','camilladsp_volume_sync','inpactive','multiroom_tx','rsmafterqbz','qbzactive') ORDER BY id")
readarray -t arr <<<"$RESULT"
ALSAVOLUME_MAX=${arr[0]}
ALSAVOLUME=${arr[1]}
AMIXNAME=${arr[2]}
CDSP_VOLSYNC=${arr[3]}
INPACTIVE=${arr[4]}
MULTIROOM_TX=${arr[5]}
RSMAFTERQBZ=${arr[6]}
QBZACTIVE=${arr[7]}
RX_ADDRESSES=$(sudo moodeutl -d -gv rx_addresses)

if [[ $INPACTIVE == '1' ]]; then
	exit 1
fi

activate () {
	if [[ $QBZACTIVE == '1' ]]; then
		return 0
	fi
	QBZACTIVE='1'
	$(sqlite3 -cmd '.timeout 5000' $SQLDB "UPDATE cfg_system SET value='1' WHERE param='qbzactive'")
	# Take over the audio device
	/usr/bin/mpc stop > /dev/null
	# Send to front-end
	/var/www/util/send-fecmd.php "qbzactive1"
	# Arm the failed-start retry budget, but never replenish it here: a retry
	# goes through loading (re-activation) again and would otherwise reset its
	# own budget. Only a real start (TrackStarted) replenishes.
	if [[ ! -f $RETRY_FILE ]]; then
		echo $RETRY_BUDGET > $RETRY_FILE
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
}

# True while qbzd still owns playback: playing, or paused with a live Connect
# session (the session keeps the audio device across a pause, AirPlay parity).
# Fail-safe: an unreachable control API answers "not active" so a dead daemon
# always releases the UI.
still_active () {
	local STATE
	STATE=$(curl -s --max-time 2 $QBZD_API/api/status | \
		jq -r '"\(.playback.state) \(.qconnect.session_active)"' 2>/dev/null)
	case "$STATE" in
		"playing true"|"paused true") return 0 ;;
		*) return 1 ;;
	esac
}

deactivate () {
	if [[ $QBZACTIVE == '0' ]]; then
		return 0
	fi
	# Verify against the daemon before tearing the render down. Transient
	# stopped/error events fire during normal operation — a gapless track
	# transition, a superseded stream being abandoned on track change — and
	# acting on them dropped the overlay mid-playback, so every later UI
	# rebuild (click, resize, page load) showed the library panel instead.
	debug_log "Deact?:  from $QBZ_EVENT (state=$QBZ_STATE session_active=$QBZ_SESSION_ACTIVE)"
	if still_active; then
		debug_log "Keep:    render (daemon still active)"
		return 0
	fi
	QBZACTIVE='0'
	# Worker picks this up and sends qbzactive0 to front-end
	$(sqlite3 -cmd '.timeout 5000' $SQLDB "UPDATE cfg_system SET value='0' WHERE param='qbzactive'")
	# NOTE: the retry budget survives deactivation on purpose. A failed start
	# arrives as loading -> paused -> PlaybackError, so the budget must still
	# be there when the PlaybackError event is processed after the pause.

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
}

if [[ $QBZ_EVENT == "PlaybackStateChanged" ]]; then
	if [[ $QBZ_STATE == "playing" || $QBZ_STATE == "loading" ]]; then
		activate
	elif [[ $QBZ_STATE == "stopped" ]]; then
		deactivate
	fi
	# NOTE: paused KEEPS the render (AirPlay/Spotify parity — their flags are
	# session-scoped). The session still owns the audio device while paused,
	# and any renderUI rebuild (page load, window resize) reads qbzactive from
	# cfg_system — deactivating on pause made the overlay vanish on reload or
	# resize whenever playback happened to be paused.
fi

if [[ $QBZ_EVENT == "QconnectSessionChanged" ]]; then
	if [[ $QBZ_SESSION_ACTIVE == "false" ]]; then
		WAS_ACTIVE=$QBZACTIVE
		deactivate
		# Session gone: no more start attempts to retry
		rm -f $RETRY_FILE
		if [[ $WAS_ACTIVE == '1' && $RSMAFTERQBZ == "Yes" ]]; then
			/usr/bin/mpc play > /dev/null
		fi
	fi
fi

# A start attempt can fail because the audio device is still busy: retry
if [[ $QBZ_EVENT == "PlaybackError" ]]; then
	debug_log "Error:   "$QBZ_MESSAGE
	RETRIES_LEFT=$(cat $RETRY_FILE 2> /dev/null || echo 0)
	MPD_PLAYING=$(/usr/bin/mpc status | grep playing)
	if [[ $RETRIES_LEFT -gt 0 && -z $MPD_PLAYING ]]; then
		echo $((RETRIES_LEFT - 1)) > $RETRY_FILE
		sleep 1
		qbzd play -q
	else
		# Out of retries (or MPD grabbed the device): since paused no longer
		# deactivates, release the render here or a failed start would leave
		# the UI locked on a silent session.
		deactivate
	fi
fi

# Update metadata and send to front-end
if [[ $QBZ_EVENT == "TrackStarted" ]]; then
	activate
	# Playback started: replenish the failed-start retry budget
	echo $RETRY_BUDGET > $RETRY_FILE
	SFORMAT="FLAC $QBZ_BIT_DEPTH/$QBZ_SAMPLE_RATE kHz"
	# Output format from the control API. /api/status, NOT /api/now-playing: the
	# latter is login-gated and the pairing flow never logs in (the casting app
	# hands over its own session), so it answers needs_auth and this fell back to
	# claiming the OUTPUT was FLAC. /api/status also carries the rate the DEVICE
	# runs at (qbzd 2.0.2.moode25+), which is the number that differs from the
	# source whenever something in the chain resamples.
	AUDIO_JSON=$(curl -s --max-time 2 $QBZD_API/api/status | jq -c '.audio // {}' 2>/dev/null)
	OFORMAT=$(jq -rn --argjson a "${AUDIO_JSON:-{\}}" '
		(if $a.output_sample_rate != null then $a.output_sample_rate else $a.sample_rate end) as $r
		| if $a.bit_depth != null and $r != null then "PCM \($a.bit_depth)/\($r / 1000) kHz" else empty end
	' 2>/dev/null)
	if [[ -z $OFORMAT ]]; then
		# Never fall back to SFORMAT: the decoder always emits PCM, so echoing the
		# source container here is the one answer that is certainly wrong.
		OFORMAT="PCM $QBZ_BIT_DEPTH/$QBZ_SAMPLE_RATE kHz"
	fi
	METADATA_JSON=$(jq -n -c \
		--arg a "update_qbzmeta" \
		--arg b "$QBZ_TITLE" \
		--arg c "$QBZ_ARTIST" \
		--arg d "$QBZ_ALBUM" \
		--arg e "$QBZ_DURATION" \
		--arg f "$QBZ_COVER_URL" \
		--arg g "$SFORMAT" \
		--arg h "$OFORMAT" \
		'{fecmd: $a, title: $b, artist: $c, album: $d, duration: $e, cover_url: $f, sformat: $g, oformat: $h}')
	echo -e "$METADATA_JSON" > $QBZMETA_CACHE_FILE
	/var/www/util/send-fecmd.php "$METADATA_JSON"
fi
