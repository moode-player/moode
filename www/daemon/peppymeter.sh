#!/bin/bash
#
# SPDX-License-Identifier: GPL-3.0-or-later
# Copyright 2014 The moOde audio player project / Tim Curtis
#

PIPE_VU="/tmp/peppymeter-vu"
PIPE_SP="/tmp/peppymeter-sp"
PEPPY_DIR="/opt/PeppyMeter/"
CURRENT_DIR=$(pwd)

function start_peppymeter () {
	# Recreate named pipes
	[ -e $PIPE_VU ] && rm $PIPE_VU
	[ -e $PIPE_SP ] && rm $PIPE_SP
	mkfifo $PIPE_VU $PIPE_SP
	chown root:root $PIPE_VU $PIPE_SP
	chmod 0666 $PIPE_VU $PIPE_SP
	# Start PeppyMeter
	cd $PEPPY_DIR
	DISPLAY=:0 python peppymeter.py >/dev/null 2>&1 &
	cd $CURRENT_DIR
}
function stop_peppymeter () {
	pkill -9 -f peppymeter.py
}

# Check for sudo
if [[ $EUID -ne 0 ]]; then
   echo "Use sudo to run this script"
   exit 1
fi

# Options
if [ -z $1 ]; then
	echo -e "Usage: peppymeter.sh [OPTION]"
	echo
	echo "OPTIONS"
	echo -e " -s --start\t\tStart PeppyMeter in the background"
 	echo -e " -t --stop\t\tStop Peppymeter (terminate the script)"
	echo -e " -r --restart\t\tStop/Start Peppymeter"
	exit 1
elif [ $1 = "--start" ] || [ $1 = "-s" ]; then
	start_peppymeter
elif [ $1 = "--stop" ] || [ $1 = "-t" ]; then
	stop_peppymeter
elif [ $1 = "--restart" ] || [ $1 = "-r" ]; then
	stop_peppymeter
	sleep 1
	start_peppymeter
else
	echo "Options are [-s --start | -t --stop | -r --restart]"
	exit 1
fi
