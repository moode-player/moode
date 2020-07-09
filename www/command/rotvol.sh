#!/bin/bash
# moOde audio player (C) 2014 Tim Curtis, GPLv3
# 2020-07-09 TC moOde 6.6.0
RESULT=$(sqlite3 /var/local/www/db/moode-sqlite3.db "SELECT value FROM cfg_system WHERE id IN ('32', 137)")
if [[ $RESULT = "" ]]; then	exit 1; fi
readarray -t arr <<<"$RESULT"
VOLKNOB=${arr[0]}
MPDMAX=${arr[1]}
if [[ $1 = "-up" ]]; then LEVEL=$(($VOLKNOB + $2)); elif [[ $1 = "-dn" ]]; then LEVEL=$(($VOLKNOB - $2)); fi
if (( $LEVEL > $MPDMAX )); then LEVEL=$MPDMAX; fi
if (( $LEVEL < 0 )); then LEVEL=0; elif (( $LEVEL > 100 )); then LEVEL=100; fi
sqlite3 /var/local/www/db/moode-sqlite3.db "update cfg_system set value=$LEVEL where id='32'"
mpc volume $LEVEL >/dev/null
