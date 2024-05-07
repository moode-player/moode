#!/bin/bash
#
# SPDX-License-Identifier: GPL-3.0-or-later
# Copyright 2014 The moOde audio player project / Tim Curtis
#
RESULT=$(sqlite3 /var/local/www/db/moode-sqlite3.db "SELECT value FROM cfg_system WHERE param IN ('volknob', 'mpdmixer', 'volume_mpd_max')")
if [[ $RESULT = "" ]]; then	exit 1; fi
readarray -t arr <<<"$RESULT"
VOLKNOB=${arr[0]}
MPDMIXER=${arr[1]}
VOLUME_MPD_MAX=${arr[2]}
if [[ $MPDMIXER = "none" ]]; then exit 0; fi
if [[ $1 = "-up" ]]; then LEVEL=$(($VOLKNOB + $2)); elif [[ $1 = "-dn" ]]; then LEVEL=$(($VOLKNOB - $2)); fi
if (( $LEVEL > $VOLUME_MPD_MAX )); then LEVEL=$VOLUME_MPD_MAX; fi
if (( $LEVEL < 0 )); then LEVEL=0; elif (( $LEVEL > 100 )); then LEVEL=100; fi
sqlite3 /var/local/www/db/moode-sqlite3.db "update cfg_system set value=$LEVEL where param='volknob'"
mpc volume $LEVEL >/dev/null
