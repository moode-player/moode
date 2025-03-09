#!/bin/bash
#
# SPDX-License-Identifier: GPL-3.0-or-later
# Copyright 2014 The moOde audio player project / Tim Curtis
#

HOME_DIR=$(ls /home/)
SQLDB=/var/local/www/db/moode-sqlite3.db

if [ -z $1 ]; then
	echo "Missing arg"
	exit
fi

if [[ $1 = "set-timezone" ]]; then
	timedatectl set-timezone "$2"
	exit
fi

# Set keyboard layout
if [[ $1 = "set-keyboard" ]]; then
	sed -i "/XKBLAYOUT=/c\XKBLAYOUT=\"$2\"" /etc/default/keyboard
    exit
fi

if [[ $1 = "chg-name" ]]; then
	if [ $2 = "host" ]; then
		sed -i "s/$3/$4/" /etc/hostname
		sed -i "s/$3/$4/" /etc/hosts
		rm -rf /home/$HOME_DIR/.config/chromium/Singleton*
	fi

	if [[ $2 = "squeezelite" ]]; then
		sed -i "s/PLAYERNAME=$3/PLAYERNAME=$4/" /etc/squeezelite.conf
	fi

	if [[ $2 = "upnp" ]]; then
		sed -i "s/friendlyname = $3/friendlyname = $4/" /etc/upmpdcli.conf
		sed -i "s/avfriendlyname = $3/avfriendlyname = $4/" /etc/upmpdcli.conf
		sed -i "s/ohproductroom = $3/ohproductroom = $4/" /etc/upmpdcli.conf
	fi

	if [[ $2 = "dlna" ]]; then
		sed -i "s/friendly_name=$3/friendly_name=$4/" /etc/minidlna.conf
	fi

	if [[ $2 = "mpdzeroconf" ]]; then
		sed -i "s/zeroconf_name \"$3\"/zeroconf_name \"$4\"/" /etc/mpd.conf
	fi

	if [[ $2 = "bluetooth" ]]; then
		sed -i "s/Name = $3/Name = $4/" /etc/bluetooth/main.conf
		sed -i "s/PRETTY_HOSTNAME=$3/PRETTY_HOSTNAME=$4/" /etc/machine-info
	fi

	exit
fi

# NOTE: i2s device is always at card 0, otherwise 0:HDMI-1 | [1:Headphones | 2:USB] or 1:HDMI-2 [2:Headphones | 3:USB]
if [[ $1 = "get-alsavol" || $1 = "get-alsavol-db" || $1 = "set-alsavol" ]]; then
	# Use configured card number
	CARD_NUM=$(sqlite3 $SQLDB "select value from cfg_system where param='cardnum'")

	if [[ $1 = "get-alsavol" ]]; then
		# Enclose $2 in quotes so mixer names with embedded spaces are parsed
		awk -F"[][]" '/%/ {print $2; count++; if (count==1) exit}' <(amixer -c $CARD_NUM sget "$2")
		exit
	elif [[ $1 = "get-alsavol-db" ]]; then
		awk -F"[][]" '/%/ {print $4; count++; if (count==1) exit}' <(amixer -c $CARD_NUM sget "$2")
		exit
	else
		# Set-alsavol (Note: % is appended to LEVEL)
		ADEVNAME=$(sqlite3 $SQLDB "select value from cfg_system where param='adevname'")
		MIXER_TYPE=$(sqlite3 $SQLDB "select value from cfg_mpd where param='mixer_type'")
		if [[ $3 = "100" && ($ADEVNAME = "Pi HDMI 1" || $ADEVNAME = "Pi HDMI 2") ]]; then
			LEVEL="0dB"
		else
			LEVEL="$3%"
		fi
		# Use mapped volume option
		amixer -M -c $CARD_NUM sset "$2" $LEVEL >/dev/null
		exit
	fi
fi

# Get alsa mixer name
# NOTE: Parenthesis are used as the delimiter to ensure we can see mixer names that contain a trailing space
# NOTE: See function getAlsaMixerName() in inc/alsa.php where delimiters are stripped off
if [[ $1 = "get-mixername" ]]; then
	CARD_NUM=$(sqlite3 $SQLDB "select value from cfg_system where param='cardnum'")
	amixer -c $CARD_NUM | awk 'BEGIN{FS="\n"; RS="Simple mixer control"} $0 ~ "pvolume" {print $1}' | awk -F"'" '{print "(" $2 ")";}'
	exit
fi

# Get/Set for Allo Piano 2.1 DAC
if [[ $1 = "get-piano-dualmode" || $1 = "set-piano-dualmode" || $1 = "get-piano-submode" || $1 = "set-piano-submode" || $1 = "get-piano-lowpass" || $1 = "set-piano-lowpass" || $1 = "get-piano-subvol" || $1 = "set-piano-subvol" ]]; then
	CARD_NUM=$(sqlite3 $SQLDB "select value from cfg_system where param='cardnum'")
	if [[ $1 = "get-piano-dualmode" ]]; then
		awk -F"'" '/Item0/ {print $2; count++; if (count==1) exit}' <(amixer -c $CARD_NUM sget "Dual Mode")
		exit
	elif [[ $1 = "set-piano-dualmode" ]]; then
		amixer -c 0 sset "Dual Mode" "$2" >/dev/null
		exit
	elif [[ $1 = "get-piano-submode" ]]; then
		awk -F"'" '/Item0/ {print $2; count++; if (count==1) exit}' <(amixer -c $CARD_NUM sget "Subwoofer mode")
		exit
	elif [[ $1 = "set-piano-submode" ]]; then
		amixer -c 0 sset "Subwoofer mode" "$2" >/dev/null
		exit
	elif [[ $1 = "get-piano-lowpass" ]]; then
		awk -F"'" '/Item0/ {print $2; count++; if (count==1) exit}' <(amixer -c $CARD_NUM sget "Lowpass")
		exit
	elif [[ $1 = "set-piano-lowpass" ]]; then
		amixer -c 0 sset "Lowpass" "$2" >/dev/null
		exit
	elif [[ $1 = "get-piano-subvol" ]]; then
		awk -F"[][]" '/%/ {print $2; count++; if (count==1) exit}' <(amixer -c $CARD_NUM sget "Subwoofer")
		exit
	elif [[ $1 = "set-piano-subvol" ]]; then
		amixer -c 0 sset "Subwoofer" "$2%" >/dev/null
		exit
	fi
fi

# TODO: review this section
if [[ $1 = "clear-syslogs" ]]; then
	journalctl --vacuum-files=1 2> /dev/null
	truncate /var/log/alternatives.log --size 0
	truncate /var/log/apt/history.log --size 0
	truncate /var/log/apt/term.log --size 0
	truncate /var/log/auth.log --size 0
	truncate /var/log/bootstrap.log --size 0
	truncate /var/log/daemon.log --size 0
	truncate /var/log/debug --size 0
	truncate /var/log/dpkg.log --size 0
	truncate /var/log/faillog --size 0
	truncate /var/log/kern.log --size 0
	truncate /var/log/lastlog --size 0
	truncate /var/log/messages --size 0
	truncate /var/log/minidlna.log --size 0
	truncate /var/log/mpd/log --size 0
	truncate /var/log/nginx/access.log --size 0
	truncate /var/log/nginx/error.log --size 0
	truncate /var/log/php*-fpm.log --size 0
	truncate /var/log/php_errors.log --size 0
	truncate /var/log/regen_ssh_keys.log --size 0
	truncate /var/log/samba/log.nmbd --size 0
	truncate /var/log/samba/log.smbd --size 0
	truncate /var/log/moode_shairport-sync.log --size 0
	truncate /var/log/moode_librespot.log --size 0
	truncate /var/log/moode_mountmon.log --size 0
	truncate /var/log/moode_spotevent.log --size 0
	truncate /var/log/moode_spsevent.log --size 0
	truncate /var/log/moode_slpower.log --size 0
	truncate /var/log/user.log --size 0
	truncate /var/log/wtmp --size 0
	truncate /var/log/Xorg.*.log --size 0
	truncate /var/log/log2ram.log --size 0

	# Rotated logs from settings in /etc/logrotate.d
	rm /var/log/*.log.* 2> /dev/null
	rm /var/log/debug.* 2> /dev/null
	rm /var/log/messages.* 2> /dev/null
	rm /var/log/btmp.* 2> /dev/null
	rm /var/log/apt/*.log.* 2> /dev/null
	rm /var/log/nginx/*.log.* 2> /dev/null
	rm /var/log/samba/log* 2> /dev/null
	exit
fi

if [[ $1 = "clear-playhistory" ]]; then
	TIMESTAMP=$(date +'%Y%m%d %H%M%S')
	LOGMSG=" Log initialized"
	echo $TIMESTAMP$LOGMSG > /var/log/moode_playhistory.log
	exit
fi

if [[ $1 = "clear-history" ]]; then
	truncate "/home/$HOME_DIR/.bash_history" --size 0
	exit
fi

# Unmute IQaudIO Pi-AMP+, Pi-DigiAMP+
if [[ $1 = "unmute-pi-ampplus" || $1 = "unmute-pi-digiampplus" ]]; then
	CHIP=$(gpiodetect | grep pinctrl | awk 'NR==1 {print}' | cut -d " " -f1)
	gpioset $CHIP 22=1
	exit
fi

# Check for directory existance
if [[ $1 = "check-dir" ]]; then
	if [ -d "$2" ]; then
		echo "exists"
	fi
    exit
fi

# Clear chrome browser cache
if [[ $1 = "clearbrcache" ]]; then
	rm -rf "/home/$HOME_DIR/.cache/chromium"
	# NOTE: This deletes any 3rd party extensions like xontab but it more effectively clears cache corruption
	rm -rf "/home/$HOME_DIR/.config/chromium/Default"
    exit
fi

# Get OS info
if [[ $1 = "get-osinfo" ]]; then
	RPIOS_VER=$(cat /etc/debian_version)
	RPIOS_MVER=$(cat /etc/debian_version | cut -d "." -f 1)
	if [[ "$RPIOS_MVER" == "11" ]]; then RPIOS_NAME="Bullseye"; fi
	if [[ "$RPIOS_MVER" == "12" ]]; then RPIOS_NAME="Bookworm"; fi
	if [[ "$RPIOS_MVER" == "13" ]]; then RPIOS_NAME="Trxie"; fi
	if [[ "$RPIOS_MVER" == "14" ]]; then RPIOS_NAME="Forky"; fi
	RPIOS_ARCH=$(dpkg -l | grep nano | awk '{print $4}')
	if [ $RPIOS_ARCH = "arm64" ]; then RPIOS_BITS="64-bit"; else RPIOS_BITS="32-bit"; fi
	# Linux info
	if [ "$RPIOS_MVER" -lt "12" ]; then KERNEL_VER=$(uname -r | cut -d "-" -f 1); else KERNEL_VER=$(uname -v | cut -d ":" -f 2 | cut -d "-" -f 1); fi
	KERNEL_ARCH=$(uname -m)
	if [ $KERNEL_ARCH = "aarch64" ]; then KERNEL_BITS="64-bit"; else KERNEL_BITS="32-bit"; fi

	echo "RPiOS: $RPIOS_VER $RPIOS_NAME $RPIOS_BITS | Linux: $KERNEL_VER $KERNEL_BITS"
fi
