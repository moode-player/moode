#!/bin/bash
#
# SPDX-License-Identifier: GPL-3.0-or-later
# Copyright 2014 The moOde audio player project / Tim Curtis
#

if [[ -z $1 ]]; then
	echo "args are reboot, poweroff"
	exit 0
fi

if [[ $1 = "reboot" ]]; then
	mpc stop
	systemctl stop nginx
	reboot
fi

if [[ $1 = "poweroff" ]]; then
	mpc stop
	systemctl stop nginx
	poweroff
fi

echo "args are reboot, poweroff"
exit 0
