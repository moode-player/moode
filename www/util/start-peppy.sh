#!/bin/bash
#
# SPDX-License-Identifier: GPL-3.0-or-later
# Copyright 2026 The moOde audio player project / Tim Curtis
#

#
# Arg $1 = peppymeter.py|spectrum.py
#

PEPPY_PROGRAM=$1

export DISPLAY=:0
cd /opt/peppymeter && python3 $PEPPY_PROGRAM >/dev/null &
