#!/bin/bash
#
# SPDX-License-Identifier: GPL-3.0-or-later
# Copyright 2014 The moOde audio player project / Tim Curtis
#

PIPE_VU="/tmp/peppymeter"
PIPE_SP="/tmp/peppyspectrum"
PEPPYMETER_DIR="/opt/peppymeter/"
PEPPYSPECTRUM_DIR="/opt/peppyspectrum/"
CURRENT_DIR=$(pwd)

function recreate_pipes () {
	[ -e $PIPE_VU ] && rm $PIPE_VU
	[ -e $PIPE_SP ] && rm $PIPE_SP
	mkfifo $PIPE_VU $PIPE_SP
	chown root:root $PIPE_VU $PIPE_SP
	chmod 0666 $PIPE_VU $PIPE_SP
}
function start_peppymeter () {
	cd $PEPPYMETER_DIR
	DISPLAY=:0 python peppymeter.py >/dev/null 2>&1 &
	cd $CURRENT_DIR
}
function stop_peppymeter () {
	pkill -9 -f peppymeter.py
}
function start_peppyspectrum () {
	cd $PEPPYSPECTRUM_DIR
	DISPLAY=:0 python spectrum.py >/dev/null 2>&1 &
	cd $CURRENT_DIR
}
function stop_peppyspectrum () {
	pkill -9 -f spectrum.py
}
function print_help () {
	echo -e "Usage: peppy-display.sh [OPTION]"
	echo
	echo "OPTIONS"
	echo -e " --meter [on|off|restart]\tControl PeppyMeter display"
	echo -e " --spectrum [on|off|restart]\tControl PeppySpectrum display"
	echo -e " --help\t\t\t\tPrint help"
}

# Check for sudo
if [[ $EUID -ne 0 ]]; then
   echo "Use sudo to run this script"
   exit 1
fi

# Options
if [ -z $1 ] || [ $1 = "--help" ]; then
	print_help
# PeppyMeter
elif [ $1 = "--meter" ]; then
	if [ -z $2 ]; then
		print_help
	elif [ $2 = "on" ]; then
		recreate_pipes
		start_peppymeter
	elif [ $2 = "off" ]; then
		stop_peppymeter
	elif [ $2 = "restart" ]; then
		stop_peppymeter
		sleep 1
		recreate_pipes
		start_peppymeter
	else
		print_help
	fi
# PeppySpectrum
elif [ $1 = "--spectrum" ]; then
	if [ -z $2 ]; then
		print_help
	elif [ $2 = "on" ]; then
		recreate_pipes
		start_peppyspectrum
	elif [ $2 = "off" ]; then
		stop_peppyspectrum
	elif [ $2 = "restart" ]; then
		stop_peppyspectrum
		sleep 1
		recreate_pipes
		start_peppyspectrum
	else
		print_help
	fi
else
	print_help
	exit 1
fi
