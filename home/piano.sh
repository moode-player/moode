#!/bin/bash
#
# SPDX-License-Identifier: GPL-3.0-or-later
# Copyright 2014 The moOde audio player project / Tim Curtis
#
SQLDB=/var/local/www/db/moode-sqlite3.db
CARD_NUM=$(sqlite3 $SQLDB "select value from cfg_system where param='cardnum'")

# Allo Piano 2.1 Hi-Fi DAC
DUALMODE=$(awk -F"'" '/Item0/ {print $2; count++; if (count==1) exit}' <(amixer -c $CARD_NUM sget "Dual Mode"))
SUBWMODE=$(awk -F"'" '/Item0/ {print $2; count++; if (count==1) exit}' <(amixer -c $CARD_NUM sget "Subwoofer mode"))
SUBWVOL=$(awk -F"[][]" '/%/ {print $2; count++; if (count==1) exit}' <(amixer -c $CARD_NUM sget "Subwoofer"))
SUBXOVER=$(awk -F"'" '/Item0/ {print $2; count++; if (count==1) exit}' <(amixer -c $CARD_NUM sget "Lowpass"))
MASTERVOL=$(awk -F"[][]" '/%/ {print $2; count++; if (count==1) exit}' <(amixer -c $CARD_NUM sget "Master"))

echo "Dual mode: "$DUALMODE
echo "Subw mode: "$SUBWMODE
echo "Sub xover: "$SUBXOVER
echo "Sub level: "$SUBWVOL
echo "Mstr levl: "$MASTERVOL
