#!/bin/bash
#
# moOde audio player (C) 2014 Tim Curtis
# http://moodeaudio.org
#
# This Program is free software; you can redistribute it and/or modify
# it under the terms of the GNU General Public License as published by
# the Free Software Foundation; either version 3, or (at your option)
# any later version.
#
# This Program is distributed in the hope that it will be useful,
# but WITHOUT ANY WARRANTY; without even the implied warranty of
# MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
# GNU General Public License for more details.
#
# You should have received a copy of the GNU General Public License
# along with this program.  If not, see <http://www.gnu.org/licenses/>.
#
# 2020-MM-DD TC moOde 6.7.1
#

SQLDB=/var/local/www/db/moode-sqlite3.db

if [[ $1 = "set-timezone" ]]; then
	timedatectl set-timezone "$2"
	exit
fi

# set keyboard layout
if [[ $1 = "set-keyboard" ]]; then
	sed -i "/XKBLAYOUT=/c\XKBLAYOUT=\"$2\"" /etc/default/keyboard
    exit
fi

if [[ $1 = "chg-name" ]]; then
	if [ $2 = "host" ]; then
		sed -i "s/$3/$4/" /etc/hostname
		sed -i "s/$3/$4/" /etc/hosts
	fi

	# DELETE
	#if [[ $2 = "browsertitle" ]]; then
	#	sed -i "s/<title>$3/<title>$4/" /var/www/header.php
	#fi

	if [[ $2 = "squeezelite" ]]; then
		sed -i "s/PLAYERNAME=$3/PLAYERNAME=$4/" /etc/squeezelite.conf
	fi

	if [[ $2 = "upnp" ]]; then
		sed -i "s/friendlyname = $3/friendlyname = $4/" /etc/upmpdcli.conf
		sed -i "s/ohproductroom = $3/ohproductroom = $4/" /etc/upmpdcli.conf
	fi

	if [[ $2 = "dlna" ]]; then
		sed -i "s/friendly_name=$3/friendly_name=$4/" /etc/minidlna.conf
	fi

	if [[ $2 = "mpdzeroconf" ]]; then
		sed -i "s/zeroconf_name $3/zeroconf_name $4/" /etc/mpd.conf
	fi

	if [[ $2 = "bluetooth" ]]; then
		# bluez 5.43, 5.49
		sed -i "s/PRETTY_HOSTNAME=$3/PRETTY_HOSTNAME=$4/" /etc/machine-info
		# bluez 5.49
		sed -i "s/Name = $3/Name = $4/" /etc/bluetooth/main.conf
	fi

	exit
fi

# NOTE may need a redo for the new card numbering scheme involving HDMI
# card 0 = i2s or onboard, card 1 = usb
# save alsa state after set-alsavol to support hotplug for card 1 USB audio device
if [[ $1 = "get-alsavol" || $1 = "set-alsavol" ]]; then
	#TMP=$(cat /proc/asound/card1/id 2>/dev/null)
	#if [[ $TMP = "" ]]; then CARD_NUM=0; else CARD_NUM=1; fi

	# Use configured card number
	CARD_NUM=$(sqlite3 $SQLDB "select value from cfg_system where param='cardnum'")

	if [[ $1 = "get-alsavol" ]]; then
		# add quotes to sget $2 so mixer names with embedded spaces are parsed
		awk -F"[][]" '/%/ {print $2; count++; if (count==1) exit}' <(amixer -c $CARD_NUM sget "$2")
		exit
	else
		# set-alsavol
		amixer -c $CARD_NUM sset "$2" "$3%" >/dev/null

		# store alsa state if card 1 to preverve volume in case hotplug
		if [[ $CARD_NUM -eq 1 ]]; then
			alsactl store 1
		fi

		exit
	fi
fi

# Get alsa mixer name
if [[ $1 = "get-mixername" ]]; then
	CARD_NUM=$(sqlite3 $SQLDB "select value from cfg_system where param='cardnum'")
	#awk -F"'" '/Simple mixer control/{print "(" $2 ")";}' <(amixer -c $CARD_NUM)
	amixer -c $CARD_NUM | awk 'BEGIN{FS="\n"; RS="Simple mixer control"} $0 ~ "pvolume" {print $1}' | awk -F"'" '{print "(" $2 ")";}'
	exit
fi

# Get/Set for Allo Piano 2.1 DAC
if [[ $1 = "get-piano-dualmode" || $1 = "set-piano-dualmode" || $1 = "get-piano-submode" || $1 = "set-piano-submode" || $1 = "get-piano-lowpass" || $1 = "set-piano-lowpass" || $1 = "get-piano-subvol" || $1 = "set-piano-subvol" ]]; then
	if [[ $1 = "get-piano-dualmode" ]]; then
		awk -F"'" '/Item0/ {print $2; count++; if (count==1) exit}' <(amixer -c 0 sget "Dual Mode")
		exit
	elif [[ $1 = "set-piano-dualmode" ]]; then
		amixer -c 0 sset "Dual Mode" "$2" >/dev/null
		exit
	elif [[ $1 = "get-piano-submode" ]]; then
		awk -F"'" '/Item0/ {print $2; count++; if (count==1) exit}' <(amixer -c 0 sget "Subwoofer mode")
		exit
	elif [[ $1 = "set-piano-submode" ]]; then
		amixer -c 0 sset "Subwoofer mode" "$2" >/dev/null
		exit
	elif [[ $1 = "get-piano-lowpass" ]]; then
		awk -F"'" '/Item0/ {print $2; count++; if (count==1) exit}' <(amixer -c 0 sget "Lowpass")
		exit
	elif [[ $1 = "set-piano-lowpass" ]]; then
		amixer -c 0 sset "Lowpass" "$2" >/dev/null
		exit
	elif [[ $1 = "get-piano-subvol" ]]; then
		awk -F"[][]" '/%/ {print $2; count++; if (count==1) exit}' <(amixer -c 0 sget "Subwoofer")
		exit
	elif [[ $1 = "set-piano-subvol" ]]; then
		amixer -c 0 sset "Subwoofer" "$2%" >/dev/null
		exit
	fi
fi

if [[ $1 = "clear-syslogs" ]]; then
	# Operating logs
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
	truncate /var/log/php7.3-fpm.log --size 0
	truncate /var/log/php_errors.log --size 0
	truncate /var/log/regen_ssh_keys.log --size 0
	truncate /var/log/samba/log.nmbd --size 0
	truncate /var/log/samba/log.smbd --size 0
	truncate /var/log/shairport-sync.log --size 0
	truncate /var/log/syslog --size 0
	truncate /var/log/user.log --size 0
	truncate /var/log/wtmp --size 0

	# Rotated logs from settings in /etc/logrotate.d
	rm /var/log/*.log.* 2> /dev/null
	rm /var/log/debug.* 2> /dev/null
	rm /var/log/messages.* 2> /dev/null
	rm /var/log/syslog.* 2> /dev/null
	rm /var/log/btmp.* 2> /dev/null
	rm /var/log/apt/*.log.* 2> /dev/null
	rm /var/log/nginx/*.log.* 2> /dev/null
	rm /var/log/samba/log.*.* 2> /dev/null

	exit
fi

if [[ $1 = "clear-playhistory" ]]; then
	TIMESTAMP=$(date +'%Y%m%d %H%M%S')
	LOGMSG=" Log initialized"
	echo $TIMESTAMP$LOGMSG > /var/local/www/playhistory.log
	exit
fi

if [[ $1 = "clear-history" ]]; then
	truncate /home/pi/.bash_history --size 0
	exit
fi

# card 0 = i2s or onboard, card 1 = usb
if [[ $1 = "unmute-default" ]]; then
    amixer scontrols | sed -e 's/^Simple mixer control//' | while read line; do
        amixer -c 0 sset "$line" unmute;
        amixer -c 1 sset "$line" unmute;
        done
    exit
fi

# unmute IQaudIO Pi-AMP+, Pi-DigiAMP+
if [[ $1 = "unmute-pi-ampplus" || $1 = "unmute-pi-digiampplus" ]]; then
	echo "22" >/sys/class/gpio/export
	echo "out" >/sys/class/gpio/gpio22/direction
	echo "1" >/sys/class/gpio/gpio22/value
	exit
fi

# Udisks-glue add/remove samba share blocks
# - auto update MPD db
# - $2 = mount point (/media/DISK_LABEL)
if [[ $1 = "smbadd" ]]; then
	if [[ $(grep -w -c "$2" /etc/samba/smb.conf) = 0 ]]; then
		sed -i "$ a[$(basename "$2")]\ncomment = USB Storage\npath = $2\nread only = No\nguest ok = Yes" /etc/samba/smb.conf
		systemctl restart smbd
		systemctl restart nmbd
		# r44a
		RESULT=$(sqlite3 $SQLDB "select value from cfg_system where param='usb_auto_updatedb'")
		if [[ $RESULT = "1" ]]; then
			mpc update USB
			truncate /var/local/www/libcache.json --size 0
		fi
	fi
	exit
fi

if [[ $1 = "smbrem" ]]; then
	sed -i "/$(basename "$2")]/,/guest/ d" /etc/samba/smb.conf
	systemctl restart smbd
	systemctl restart nmbd
	# r44a
	RESULT=$(sqlite3 $SQLDB "select value from cfg_system where param='usb_auto_updatedb'")
	if [[ $RESULT = "1" ]]; then
		mpc update USB
		truncate /var/local/www/libcache.json --size 0
	fi
    exit
fi

# Devmon add/remove samba share blocks
# - auto update MPD db
# - $2 = mount point: /media/DISK_LABEL
# - $3 = device: /dev/sda1, sdb1, sdc1
if [[ $1 = "smb_add" ]]; then
	if [[ $(grep -w -c "$2" /etc/samba/smb.conf) = 0 ]]; then
		sed -i "$ a# $3\n[$(basename "$2")]\ncomment = USB Storage\npath = $2\nread only = No\nguest ok = Yes" /etc/samba/smb.conf
		systemctl restart smbd
		systemctl restart nmbd
		# r44a
		RESULT=$(sqlite3 $SQLDB "select value from cfg_system where param='usb_auto_updatedb'")
		if [[ $RESULT = "1" ]]; then
			mpc update USB
			truncate /var/local/www/libcache.json --size 0
		fi
	fi
	exit
fi

# $2 = device w/o the number: /dev/sda, sdb, sdc
if [[ $1 = "smb_remove" ]]; then
	sed -i "\|$2|,\|guest| d" /etc/samba/smb.conf
	systemctl restart smbd
	systemctl restart nmbd
	# r44a
	RESULT=$(sqlite3 $SQLDB "select value from cfg_system where param='usb_auto_updatedb'")
	if [[ $RESULT = "1" ]]; then
		mpc update USB
		truncate /var/local/www/libcache.json --size 0
	fi
    exit
fi

# check for directory existance
if [[ $1 = "check-dir" ]]; then
	if [ -d "$2" ]; then
		echo "exists"
	fi
    exit
fi

# clear chrome browser cache
if [[ $1 = "clearbrcache" ]]; then
	rm -rf /home/pi/.cache/chromium
	# this will delete installed extensions like xontab kbdchr
	#rm -rf /home/pi/.config/chromium/Default
    exit
fi
