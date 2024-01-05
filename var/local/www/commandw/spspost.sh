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

SQLDB=/var/local/www/db/moode-sqlite3.db

$(sqlite3 $SQLDB "UPDATE cfg_system SET value='0' WHERE param='aplactive'")

RESULT=$(sqlite3 $SQLDB "SELECT value FROM cfg_system WHERE param IN ('alsavolume_max','alsavolume','amixname','mpdmixer','rsmafterapl','camilladsp_volume_sync','inpactive','volknob_mpd','multiroom_tx')")
readarray -t arr <<<"$RESULT"
ALSAVOLUME_MAX=${arr[0]}
ALSAVOLUME=${arr[1]}
AMIXNAME=${arr[2]}
MPDMIXER=${arr[3]}
RSMAFTERAPL=${arr[4]}
CDSP_VOLSYNC=${arr[5]}
INPACTIVE=${arr[6]}
VOLKNOB_MPD=${arr[7]}
MULTIROOM_TX=${arr[8]}
RX_ADDRESSES=$(sudo moodeutl -d | grep rx_addresses | cut -d'|' -f2)

if [[ $INPACTIVE == '1' ]]; then
	exit 1
fi

# Local
if [[ $CDSP_VOLSYNC == "on" ]]; then
	# Restore knob volume to saved MPD volume
	$(sqlite3 $SQLDB "UPDATE cfg_system SET value='$VOLKNOB_MPD' WHERE param='volknob'")
	/var/www/vol.sh -restore
	systemctl restart mpd2cdspvolume
else
	# Restore knob volume
	/var/www/vol.sh -restore
fi
# TODO: Is this needed?
if [[ $MPDMIXER == "software" || $MPDMIXER == "none" ]]; then
	if [[ $ALSAVOLUME != "none" ]]; then
		# Restore 0dB ALSA volume
		/var/www/util/sysutil.sh set-alsavol "$AMIXNAME" $ALSAVOLUME_MAX
	fi
fi

# Multiroom receivers
if [[ $MULTIROOM_TX == "On" ]]; then
	for IP_ADDR in $RX_ADDRESSES; do
		RESULT=$(curl -G -S -s --data-urlencode "cmd=vol.sh -restore" http://$IP_ADDR/command/)
		if [[ $RESULT != "" ]]; then
			RESULT=$(curl -G -S -s --data-urlencode "cmd=vol.sh -restore" http://$IP_ADDR/command/)
			if [[ $RESULT != "" ]]; then
				echo $(date +%F" "%T)" spspost.sh vol.sh -restore failed: $IP_ADDR" >> /var/log/moode_spsevent.log
			fi
		fi
	done
fi

if [[ $RSMAFTERAPL == "Yes" ]]; then
	/usr/bin/mpc play > /dev/null
fi
