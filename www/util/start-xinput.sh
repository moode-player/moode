#!/bin/bash
#
# SPDX-License-Identifier: GPL-3.0-or-later
# Copyright 2026 The moOde audio player project / Tim Curtis
#

#
# Arg $1 = log file
#

TOUCHMON_LOG=$1

export DISPLAY=:0
xinput --test-xi2 --root | unbuffer -p grep RawTouchEnd > $TOUCHMON_LOG &
