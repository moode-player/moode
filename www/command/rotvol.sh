#!/bin/bash
# moOde audio player (C) 2014 Tim Curtis, GPLv3, moOde 4.3
VOLKNOB=$(sqlite3 /var/local/www/db/moode-sqlite3.db "select value from cfg_system where id='32'")
if [[ $1 = "up" ]]; then LEVEL=$(($VOLKNOB + $2)); elif [[ $1 = "dn" ]]; then LEVEL=$(($VOLKNOB - $2)); fi
if (( $LEVEL < 0 )); then LEVEL=0; elif (( $LEVEL > 100 )); then LEVEL=100; fi
sqlite3 /var/local/www/db/moode-sqlite3.db "update cfg_system set value=$LEVEL where id='32'"
mpc volume $LEVEL >/dev/null
