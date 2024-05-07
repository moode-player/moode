#!/bin/bash
#
# SPDX-License-Identifier: GPL-3.0-or-later
# Copyright 2014 The moOde audio player project / Tim Curtis
# Copyright 2017 Klaus Schulz
#

# Bluetooth controller management
# Written by Klaus Schulz
# Rev 1.0-beta1: 06-Oct-2017
# Rev 1.0-beta2: 19-Oct-2017
# - some terminology changes and cleanup
# - kill bluealsa-aplay (go offline!)to avoid playback during config
# - show and kill active connections - "online"
# - kill active connections before scanning
# - TODO: How to avoid double source connection??
#
# This script is designed to work with bluez and bluez-alsa
# It performs bluetooth controller initialization and provides management of bluetooth sources
# All related data files will reside under /var/lib/bluetooth
#

REV=1.6

# Check for sudo
[[ $EUID -ne 0 ]] && { echo "Use sudo to run the script" ; exit 1 ; } ;
#which expect >/dev/null || { echo "** expect must be installed to run the script!" ; exit 1 ; } ;

# Sleep times in seconds
SCAN_DURATION=20
WAIT_FOR_PAIR=5
WAIT_FOR_CONNECT=5
WAIT_FOR_REMOVE=0.5

# Set to the value of the TemporaryTimeout param in /etc/bluetooth/main.conf
TRUSTED_DEVICE_TIMEOUT=90

# Initialze bluetooth controller
INIT() {
echo "** Initializing Bluetooth controller"
echo "**"
bluetoothctl << EOF
power on
discoverable on
pairable on
agent on
default-agent
EOF
echo "**"
echo "** Controller initialized"
}

# Scan for only BR/EDR devices
SCAN_BREDR() {
echo "** Scanning for only BR/EDR devices (${SCAN_DURATION} seconds)"
echo "**"
expect <(cat <<EOF
log_user 0
set timeout -1
match_max 100000
spawn bluetoothctl
expect "*# "
send "menu scan\r"
expect "*# "
send "clear\r"
expect "*# "
send "transport bredr\r"
expect "*# "
send "back\r"
expect "*# "
send "scan on\r"
expect "Discovery started\r"
expect "*# "
sleep $SCAN_DURATION
send "scan off\r"
expect "Discovery stopped\r"
expect "*# "
send "quit\r"
expect eof
EOF
)
}

# Scan for both BR/EDR and LE devices
SCAN_DUAL() {
echo "** Scanning for BR/EDR and LE devices (${SCAN_DURATION} seconds)"
echo "**"
expect <(cat <<EOF
log_user 0
set timeout -1
match_max 100000
spawn bluetoothctl
expect "*# "
send "menu scan\r"
expect "*# "
send "clear\r"
expect "*# "
send "back\r"
expect "*# "
send "scan on\r"
expect "Discovery started\r"
expect "*# "
sleep $SCAN_DURATION
send "scan off\r"
expect "Discovery stopped\r"
expect "*# "
send "quit\r"
expect eof
EOF
)
}

# Trust scanned devices
TRUST() {
	echo "** Trust expires in $TRUSTED_DEVICE_TIMEOUT seconds"
	unset btdev
	mapfile -t btdev < <(echo -e "devices\nquit"  | bluetoothctl | grep "^Device" |  while IFS= read -r line ; do echo "$line" |cut -d " " -f2- ; done )
	for i in "${btdev[@]}" ; do
	   echo "** $i"
	   y="$( echo $i | cut -d " " -f1)"
	   echo -e "trust $y\nquit"  | bluetoothctl >/dev/null
	   #sleep 1
	done
	echo "**"
}

# List discovered devices
LIST_DISCOVERED() {
	unset btdev
	mapfile -t btdev < <(echo -e "devices\nquit"  | bluetoothctl | grep "^Device" |  while IFS= read -r line ; do echo "$line" |cut -d " " -f2- ; done )
	echo "** Discovered devices"
	echo "**"
	for i in "${btdev[@]}" ; do
	   echo "** $i"
	done
	echo "**"
}

# List paired devices
LIST_PAIRED() {
	unset btdev
	mapfile -t btdev < <(echo -e "devices Paired\nquit"  | bluetoothctl | grep "^Device" |  while IFS= read -r line ; do echo "$line" |cut -d " " -f2- ; done )
	echo "** Paired devices"
	echo "**"
	for i in "${btdev[@]}" ; do
	   echo "** $i"
	done
	echo "**"
}

# List connected devices
LIST_CONNECTED() {
	echo "** Connected devices"
	echo "**"
	unset btdev
	mapfile -t btdev < <(echo -e "devices Paired\nquit"  | bluetoothctl | grep "^Device" |  while IFS= read -r line ; do echo "$line" |cut -d " " -f2- ; done )
	for i in "${btdev[@]}" ; do
	   MAC=$(echo "$i" | cut -f 1 -d " ")
	   statconnected="$( echo -e "info $MAC\nquit"  | bluetoothctl | grep "Connected" | cut -f 2 -d " ")"
	   if [ "$statconnected" == "yes" ] ; then echo "** $i" ; fi
	done
	echo "**"
}

# Remove all devices
REMOVE_ALL() {
	echo "** Removing all devices"
	unset btdev
	mapfile -t btdev < <(echo -e "devices\nquit"  | bluetoothctl | grep "^Device" |  while IFS= read -r line ; do echo "$line" |cut -d " " -f2- ; done )
	for i in "${btdev[@]}" ; do
	   echo "** $i"
	   y="$( echo $i | cut -d " " -f1)"
	   echo -e "remove $y\nquit"  | bluetoothctl >/dev/null
	   sleep $WAIT_FOR_REMOVE
	done
	echo "** All devices removed"
}

# Disconnect all devices
DISCONNECT_ALL() {
	echo "** Disconnecting all devices"
	unset btdev
	mapfile -t btdev < <(echo -e "devices Paired\nquit"  | bluetoothctl | grep "^Device" |  while IFS= read -r line ; do echo "$line" |cut -d " " -f2- ; done )
	for i in "${btdev[@]}" ; do
	   MAC=$(echo "$i" | cut -f 1 -d " ")
	   statconnected="$( echo -e "info $MAC\nquit"  | bluetoothctl | grep "Connected" | cut -f 2 -d " ")"
	   if [ "$statconnected" == "yes" ] ; then echo -e "disconnect $MAC\nquit"  | bluetoothctl >/dev/null 2>&1 ; fi
	done
	# Ensure no orphaned instances
	killall bluealsa-aplay 2> /dev/null
	echo "** All devices disconnected"
}

# Remove paired device
REMOVE_DEVICE() {
	echo "** Removing device $DEVICE"
	echo -e "remove $DEVICE\nquit"  | bluetoothctl >/dev/null
	#sleep 1
	echo "** Device $DEVICE removed"
}

# Disconenect device
DISCONNECT_DEVICE() {
	echo "** Disconnecting device $DEVICE"
	echo -e "disconnect $DEVICE\nquit"  | bluetoothctl >/dev/null
	#sleep 1
	echo "** Device $DEVICE disconnected"
}

# Pair with device
PAIRWITH_DEVICE() {
echo "** Pairing with device $DEVICE"
expect <(cat <<EOF
log_user 0
set timeout -1
match_max 100000
spawn bluetoothctl
expect "*# "
send "pair $DEVICE\r"
expect "Attempting to pair with $DEVICE\r"
expect "*# "
sleep $WAIT_FOR_PAIR
send "quit\r"
expect eof
EOF
)
echo "** Device $DEVICE paired"
}

# Connect to device
CONNECTTO_DEVICE() {
echo "** Connecting to device $DEVICE"
expect <(cat <<EOF
log_user 0
set timeout -1
match_max 100000
spawn bluetoothctl
expect "*# "
send "connect $DEVICE\r"
expect "Attempting to connect to $DEVICE\r"
expect "*# "
sleep $WAIT_FOR_CONNECT
send "quit\r"
expect eof
EOF
)
echo "** Device $DEVICE connected"
}

# Print help to terminal
HELP_TERM() {
	echo "** blu-control.sh version $REV"
	echo "**"
	echo "** Bluetooth has a range of around 30 feet (10 meters) but range"
	echo "** will vary depending on obstacles (metal, wall, etc.), device signal"
	echo "** strength and quality, and level of electromagnetic interferrence."
	echo "**"
	echo "** Usage: blu-control.sh [OPTION]"
	echo "**"
	echo "** -i Initialize/reset controller"
	echo "** -s Scan (BR/EDR only) and trust devices"
	echo "** -S Scan (LE and BR/EDR) and trust devices"
	echo "** -p List paired devices"
	echo "** -c List connected devices"
	echo "** -l List trusted devices"
	echo "** -d Disconnect device <MAC addr>"
	echo "** -r Remove paired device <MAC addr>"
	echo "** -P Pair with device <MAC addr>"
	echo "** -C Connect to device <MAC addr>"
	echo "** -D Disconnect all devices"
	echo "** -R Remove all devices"
	echo "** -h Help"
}

# Format help for html presentation
HELP_HTML() {
	echo "1) Put your device in discovery mode and wait until it discovers Moode Bluetooth. You may have to turn Bluetooth off/on on your device to accomplish this."
	echo
	echo -e "2) To send audio from your device to moOde:<br>First turn on the Pairing agent in Audio Config and then initiate the connection on your device. Your device should automatically pair and connect. You can verify that your device has been successfully paired and connected by submitting \"LIST paired\" or \"LIST connected\" commands."
	echo
	echo -e "3) To send audio from moOde to your device:<br>First submit a SCAN command and verify that your device appears in the scan results. The SCAN may have to be run multiple times. Next select the device in the dropdown list, PAIR it then select \"MPD audio output->Bluetooth\" from the dropdown then CONNECT."
	echo "<br>Note: Bluetooth has a range of around 30 feet (10 meters) but range will vary depending on obstacles (metal, wall, etc.), device signal strength and quality, and level of electromagnetic interferrence."
}

#
# Main
#

case $1 in

	-i) INIT
		exit 0
		;;
	-s) SCAN_BREDR
		TRUST
		#echo "** Scan complete"
		exit 0
		;;
	-S) SCAN_DUAL
		TRUST
		#echo "** Scan complete"
		exit 0
		;;
	-l) LIST_DISCOVERED
		exit 0
		;;
	-p) LIST_PAIRED
		exit 0
		;;
	-c) LIST_CONNECTED
		exit 0
		;;
	-R) REMOVE_ALL
		sleep 1
		LIST_PAIRED
		exit 0
		;;
	-r) DEVICE=$2
		REMOVE_DEVICE
		exit 0
		;;
	-D) DISCONNECT_ALL
		sleep 1
		LIST_CONNECTED
		exit 0
		;;
	-d) DEVICE=$2
		DISCONNECT_DEVICE
		exit 0
		;;
	-P) DEVICE=$2
		PAIRWITH_DEVICE
		exit 0
		;;
	-C) DEVICE=$2
		CONNECTTO_DEVICE
		exit 0
		;;
	-h) HELP_TERM
		exit 0
		;;
	-H) HELP_HTML
		exit 0
		;;
	*)  HELP_TERM
		exit 0
		;;
esac

echo "**"
exit 0
