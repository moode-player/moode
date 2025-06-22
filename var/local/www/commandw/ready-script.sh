#!/bin/bash
#
# SPDX-License-Identifier: GPL-3.0-or-later
# Copyright 2014 The moOde audio player project / Tim Curtis
#
# This script is run by worker.php after startup has completed
# NOTE: The script is run as a background task to avoid blocking worker.php
# $1 = MPD URI for ReadyChime.flac track
# $2 = Track title
# $3 = Wait before start (secs)
#

#
# To use this script for something other than the Default action
# add your own code below followed by an exit statement
#
#exit 0

#
# Default action is to play the "System Ready" chime
#

READY_CHIME_URI="$1"
READY_CHIME_TITLE="$2"
WAIT_SECS="$3"
MOODE_LOG="/var/log/moode.log"

moode_log () {
	echo "$1"
	TIME=$(date +'%Y%m%d %H%M%S')
	echo "$TIME ready-script: $1" >> $MOODE_LOG
}

# Begin
moode_log "Started"

# Wait before continuing
moode_log "Wait $WAIT_SECS seconds..."
sleep $WAIT_SECS

# Search the Queue first
ITEM=1
FOUND=0
QUEUE=$(mpc playlist)
while IFS= read -r LINE; do
  if [ "$LINE" == "$READY_CHIME_TITLE" ]; then
    FOUND=1
    break
  else
    ((ITEM++))
  fi
done <<< "$QUEUE"
# Remove if found
if [ $FOUND == 1 ]; then
  mpc -q del $ITEM
fi

# Add to end of Queue then play
mpc add "$READY_CHIME_URI"
ITEM=$(mpc status %length%)
mpc -q play $ITEM
moode_log "Play $READY_CHIME_URI"

# We are done
moode_log "Finished"
