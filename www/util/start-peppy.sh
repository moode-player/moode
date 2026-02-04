#!/bin/bash
#
# SPDX-License-Identifier: GPL-3.0-or-later
# Copyright 2026 The moOde audio player project / Tim Curtis
#

#
# Arg $1 = meter|spectrum
#

TYPE=$1

export DISPLAY=:0
if [ $TYPE = 'meter' ]; then
	cd /opt/peppymeter && python3 peppymeter.py >/dev/null &
elif [ $TYPE = 'spectrum' ]; then
	cd /opt/peppyspectrum && python3 spectrum.py >/dev/null &
else
	echo "Valid args are meter|spectrum"
fi
