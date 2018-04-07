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
# 2018-01-26 TC moOde 4.0
# 2018-04-02 TC moOde 4.1 add /etc/bluetooth/main.conf to bluetooth name change
#

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

	if [[ $2 = "browsertitle" ]]; then
		sed -i "s/<title>$3/<title>$4/" /var/local/www/header.php
	fi

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

# card 0 = i2s or onboard, card 1 = usb 
# save alsa state after set-alsavol to support hotplug for card 1 USB audio device
if [[ $1 = "get-alsavol" || $1 = "set-alsavol" ]]; then
	TMP=$(cat /proc/asound/card1/id 2>/dev/null)
	if [[ $TMP = "" ]]; then CARD_NUM=0; else CARD_NUM=1; fi

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

# get alsa mixer name for card1 (USB)
if [[ $1 = "get-mixername" ]]; then
	TMP=$(cat /proc/asound/card1/id 2>/dev/null)
	if [[ $TMP = "" ]]; then CARD_NUM=0; else CARD_NUM=1; fi

	awk -F"'" '/Simple mixer control/{print $2;}' <(amixer -c $CARD_NUM)
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

# $1=colorName, $2=hexColorLight, $3=hexColorDark, $4=rgbaColorDark

# $1 = colorName
# $2 = hexColor
# $3 = rgbaColor
if [[ $1 = "alizarin" || $1 = "amethyst" || $1 = "bluejeans" || $1 = "carrot" || $1 = "emerald" || $1 = "fallenleaf" || $1 = "grass" || $1 = "herb" || $1 = "lavender" || $1 = "river" || $1 = "rose" || $1 = "silver" || $1 = "turquoise" ]]; then
	# load the alizarin files
	cp /var/www/themes/alizarin/bootstrap-select.css /var/local/www/cssw
	cp /var/www/themes/alizarin/flat-ui.css /var/local/www/cssw
	cp /var/www/themes/alizarin/panels.css /var/local/www/cssw
	cp /var/www/themes/alizarin/indextpl.html /var/local/www/templatesw
	cp /var/www/themes/alizarin/jquery.knob.js /var/local/www/jsw

	if [[ $1 != "alizarin" ]]; then
		# alizarin color -> new color
		sed -i "s/c0392b/$2/g" /var/local/www/cssw/bootstrap-select.css
		sed -i "s/c0392b/$2/g" /var/local/www/cssw/flat-ui.css
		sed -i "s/c0392b/$2/g" /var/local/www/cssw/panels.css
		sed -i "s/rgba(192,57,43,0.71)/$3/g" /var/local/www/cssw/panels.css
		sed -i "s/rgba(192,57,43,0.71)/$3/g" /var/local/www/cssw/bootstrap-select.css
		sed -i "s/c0392b/$2/g" /var/local/www/templatesw/indextpl.html
		sed -i "s/c0392b/$2/g" /var/local/www/jsw/jquery.knob.js
	fi

	# copy radio slider control image for the config pages
	cp /var/www/themes/$1-icon-on.png /var/local/www/imagesw/toggle/icon-on.png
	cp /var/www/themes/$1-icon-on-2x.png /var/local/www/imagesw/toggle/icon-on-2x.png
	exit
fi

if [[ $1 = "clear-syslogs" ]]; then
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
	truncate /var/log/php7.0-fpm.log --size 0
	truncate /var/log/php_errors.log --size 0
	truncate /var/log/regen_ssh_keys.log --size 0
	truncate /var/log/samba/log.nmbd --size 0
	truncate /var/log/samba/log.smbd --size 0
	truncate /var/log/syslog --size 0
	truncate /var/log/user.log --size 0
	truncate /var/log/wtmp --size 0
	#truncate /var/log/moode.log --size 0
	exit
fi

if [[ $1 = "clear-playhistory" ]]; then
	TIMESTAMP=$(date +'%Y%m%d %H%M%S')
	LOGMSG=" Log initialized"
	echo $TIMESTAMP$LOGMSG > /var/local/www/playhistory.log
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

# add remove samba share blocks
# $2 = %mount_point
if [[ $1 = "smbadd" ]]; then
	if [[ $(grep -w -c "$2" /etc/samba/smb.conf) = 0 ]]; then
		sed -i "$ a[$(basename "$2")]\ncomment = USB Storage\npath = $2\nread only = No\nguest ok = Yes" /etc/samba/smb.conf
		systemctl restart smbd
		systemctl restart nmbd
	fi
	exit
fi

if [[ $1 = "smbrem" ]]; then
	sed -i "/$(basename "$2")]/,/guest/ d" /etc/samba/smb.conf
	systemctl restart smbd
	systemctl restart nmbd
    exit
fi

# check for directory existance
if [[ $1 = "check-dir" ]]; then
	if [ -d "$2" ]; then 
		echo "exists"
	fi
    exit
fi
