#!/bin/bash
#
# SPDX-License-Identifier: GPL-3.0-or-later
# Copyright 2014 The moOde audio player project / Tim Curtis
#
# Spotify Soloist event reader
# Soloist has no event hook. Its events are read from the local WebSocket API
# which 'soloist ctl trace' prints one per line as '<epoch_ms> <json>'.
#

LOGFILE="/var/log/moode_solevent.log"
DEBUG=$(moodeutl -d -gv debuglog)
SOLMETA_CACHE_FILE="/var/local/www/solmeta.json"
SQLDB=/var/local/www/db/moode-sqlite3.db
# Soloist data dir, passed by startSoloist() (ctl reads the WebSocket port there)
SOLOIST_CTL="/usr/local/bin/soloist ctl -D $1"

debug_log () {
	if [[ $DEBUG == '0' ]]; then
		return 0
	fi
	echo "$1"
	TIME=$(date +'%Y%m%d %H%M%S')
	echo "$TIME $1" >> $LOGFILE
}

# Soloist is the active Spotify Connect device
ACTIVE=0
# Activated, the renderer gets 0dB at the first 'playing'
PENDING_0DB=0
# Current track
TITLE=""
ARTIST=""
ALBUM=""
DURATION_MS=""
COVER=""
PLAYSTATE="Pause"

get_cfg () {
	# Rows come back in id order
	RESULT=$(sqlite3 $SQLDB "SELECT value FROM cfg_system WHERE param IN ('alsavolume_max','alsavolume','amixname','camilladsp_volume_sync','inpactive','multiroom_tx','rsmaftersol')")
	readarray -t arr <<<"$RESULT"
	ALSAVOLUME_MAX=${arr[0]}
	ALSAVOLUME=${arr[1]}
	AMIXNAME=${arr[2]}
	CDSP_VOLSYNC=${arr[3]}
	INPACTIVE=${arr[4]}
	MULTIROOM_TX=${arr[5]}
	RSMAFTERSOL=${arr[6]}
	INITIAL_VOLUME=$(sqlite3 $SQLDB "SELECT value FROM cfg_soloist WHERE param='initial_volume'")
}

connect () {
	debug_log "Process: connect"
	ACTIVE=1
	PENDING_0DB=1
	$(sqlite3 $SQLDB "UPDATE cfg_system SET value='1' WHERE param='solactive'")
	/usr/bin/mpc stop > /dev/null
	# Send to front-end
	/var/www/util/send-fecmd.php "solactive1"
}

set_0db () {
	# Soloist starts with the volume of its new PipeWire stream (moOde sets it to the
	# initial volume). Recover from a full-scale start before going to 0dB.
	sleep 1
	VOLUME=$($SOLOIST_CTL now --json 2>/dev/null | jq -r '.volume // empty')
	if [[ $VOLUME == "100" && $INITIAL_VOLUME != "100" ]]; then
		debug_log "- Volume: 100 at start, set to $INITIAL_VOLUME"
		$SOLOIST_CTL volume $INITIAL_VOLUME
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
		RX_ADDRESSES=$(moodeutl -d -gv rx_addresses)
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

disconnect () {
	debug_log "Process: disconnect"
	ACTIVE=0
	PENDING_0DB=0
	TITLE=""
	# Worker picks this up and sends solactive0 to front-end
	$(sqlite3 $SQLDB "UPDATE cfg_system SET value='0' WHERE param='solactive'")

	# Truncate metadata file
	truncate $SOLMETA_CACHE_FILE --size 0

	# Local
	/var/www/util/vol.sh -restore

	if [[ $CDSP_VOLSYNC == "on" ]]; then
		# Restore CDSP volume
		systemctl restart mpd2cdspvolume
	fi

	# Multiroom receivers
	if [[ $MULTIROOM_TX == "On" ]]; then
		RX_ADDRESSES=$(moodeutl -d -gv rx_addresses)
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

	if [[ $RSMAFTERSOL == "Yes" ]]; then
		/usr/bin/mpc play > /dev/null
	fi
}

send_meta () {
	if [[ $TITLE == "" ]]; then
		debug_log "- Update cache: skipped (no track)"
		return 0
	fi

	# Source format not included in metadata feed yet
	SFORMAT="Up to 24/44.1 kHz"

	OFORMAT=$(/var/www/util/get-oformat.php)
	METADATA_JSON=$(jq -n -c \
		--arg a "update_solmeta" \
		--arg b "$TITLE" \
		--arg c "$ARTIST" \
		--arg d "$ALBUM" \
		--arg e "$DURATION_MS" \
		--arg f "$COVER" \
		--arg g "$SFORMAT" \
		--arg h "$OFORMAT" \
		--arg i "$PLAYSTATE" \
		'{fecmd: $a, title: $b, artist: $c, album: $d, duration: $e, cover_url: $f, sformat: $g, oformat: $h, playstate: $i}')
	debug_log "- Update cache: $TITLE, playstate=$PLAYSTATE"
	echo -e "$METADATA_JSON" > $SOLMETA_CACHE_FILE
	/var/www/util/send-fecmd.php "$METADATA_JSON"
}

# One line per event: '<epoch_ms> <json>'
# Fields (unit separator delimited): type, is_active, status, title, artist, album, duration (ms), cover url
JQ_FILTER='
	(.item // {}) as $item |
	[
		.type,
		(if has("is_active") then (.is_active | tostring) else "" end),
		(.status // ""),
		($item.decorations.identity.name // ""),
		([$item.decorations.creators[]?.entity.decorations.identity.name] | join(", ")),
		($item.decorations.parent.entity.decorations.identity.name // ""),
		($item.decorations.playback.duration_ms // "" | tostring),
		(($item.decorations.visual_identity.cover // []) | (map(select(.size == "large")) + .)[0].url // "")
	] | map(tostring | gsub("[\\n\\r]"; " ")) | join("\u001f")'

# Reconnect while the daemon runs (the WebSocket may come up after this script)
while pgrep -x soloist > /dev/null; do
while read -r TIMESTAMP EVENT_JSON; do
	IFS=$'\x1f' read -r TYPE IS_ACTIVE STATUS ITEM_TITLE ITEM_ARTIST ITEM_ALBUM ITEM_DURATION ITEM_COVER \
		< <(echo "$EVENT_JSON" | jq -r "$JQ_FILTER" 2>/dev/null)
	if [[ $TYPE == "" || $TYPE == "position_sync" || $TYPE == "queue_changed" ]]; then
		continue
	fi
	debug_log "Event: $TYPE active=$IS_ACTIVE status=$STATUS"

	get_cfg
	if [[ $INPACTIVE == '1' ]]; then
		continue
	fi

	# Connect/disconnect follow the active device, not the login: the stored
	# session stays logged in when the app moves playback to another device
	if [[ $IS_ACTIVE == "true" && $ACTIVE == 0 ]]; then
		connect
		# Playback transferred while playing: its last state came before activation
		IFS=$'\x1f' read -r TYPE IS_ACTIVE STATUS ITEM_TITLE ITEM_ARTIST ITEM_ALBUM ITEM_DURATION ITEM_COVER \
			< <($SOLOIST_CTL now --json 2>/dev/null | jq -r "$JQ_FILTER" 2>/dev/null)
		debug_log "- Current state: $TYPE status=$STATUS"
	elif [[ $IS_ACTIVE == "false" && $ACTIVE == 1 ]]; then
		disconnect
		continue
	fi
	# An inactive device still reports the state of the remote session
	if [[ $ACTIVE == 0 ]]; then
		continue
	fi

	# Track
	if [[ $ITEM_TITLE != "" ]]; then
		TITLE=$ITEM_TITLE
		ARTIST=$ITEM_ARTIST
		ALBUM=$ITEM_ALBUM
		DURATION_MS=$ITEM_DURATION
		COVER=$ITEM_COVER
	fi
	# Playstate
	if [[ $STATUS == "playing" ]]; then
		PLAYSTATE="Resume"
		if [[ $PENDING_0DB == 1 ]]; then
			PENDING_0DB=0
			set_0db
		fi
	elif [[ $STATUS == "paused" || $STATUS == "idle" ]]; then
		PLAYSTATE="Pause"
	fi

	# playback_state follows each playback_changed with the full state
	if [[ $TYPE == "track_changed" || $TYPE == "playback_state" ]]; then
		send_meta
	fi
done < <($SOLOIST_CTL trace 2>/dev/null)
sleep 1
done
