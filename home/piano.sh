#!/bin/bash
#
# SPDX-License-Identifier: GPL-3.0-or-later
# Copyright 2014 The moOde audio player project / Tim Curtis
#

# Allo Piano 2.1 Hi-Fi DAC
DUALMODE=$(awk -F"'" '/Item0/ {print $2; count++; if (count==1) exit}' <(amixer -c 0 sget "Dual Mode"))
SUBWMODE=$(awk -F"'" '/Item0/ {print $2; count++; if (count==1) exit}' <(amixer -c 0 sget "Subwoofer mode"))
SUBWVOL=$(awk -F"[][]" '/%/ {print $2; count++; if (count==1) exit}' <(amixer -c 0 sget "Subwoofer"))
SUBXOVER=$(awk -F"'" '/Item0/ {print $2; count++; if (count==1) exit}' <(amixer -c 0 sget "Lowpass"))
MASTERVOL=$(awk -F"[][]" '/%/ {print $2; count++; if (count==1) exit}' <(amixer -c 0 sget "Master"))

echo "Dual mode: "$DUALMODE
echo "Subw mode: "$SUBWMODE
echo "Sub xover: "$SUBXOVER
echo "Sub level: "$SUBWVOL
echo "Mstr levl: "$MASTERVOL
