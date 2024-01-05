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

RESULT=$(sqlite3 $SQLDB "SELECT value FROM cfg_system WHERE param IN ('volknob','alsavolume_max','alsavolume','amixname','camilladsp_volume_sync','inpactive','multiroom_tx')")
readarray -t arr <<<"$RESULT"
VOLKNOB=${arr[0]}
ALSAVOLUME_MAX=${arr[1]}
ALSAVOLUME=${arr[2]}
AMIXNAME=${arr[3]}
CDSP_VOLSYNC=${arr[4]}
INPACTIVE=${arr[5]}
MULTIROOM_TX=${arr[6]}
RX_ADDRESSES=$(sudo moodeutl -d | grep rx_addresses | cut -d'|' -f2)

if [[ $INPACTIVE == '1' ]]; then
	exit 1
fi

$(sqlite3 $SQLDB "UPDATE cfg_system SET value='1' WHERE param='aplactive'")

/usr/bin/mpc stop > /dev/null
# Allow time for UI update
sleep 1

# Local
if [[ $CDSP_VOLSYNC == "on" ]]; then
	# Save knob level then set camilladsp volume to 100% (0dB)
	$(sqlite3 $SQLDB "UPDATE cfg_system SET value='$VOLKNOB' WHERE param='volknob_mpd'")
	/var/www/vol.sh 100
elif [[ $ALSAVOLUME != "none" ]]; then
	# Set 0dB ALSA volume
	/var/www/util/sysutil.sh set-alsavol "$AMIXNAME" $ALSAVOLUME_MAX
fi

# Multiroom receivers
if [[ $MULTIROOM_TX == "On" ]]; then
	for IP_ADDR in $RX_ADDRESSES; do
		RESULT=$(curl -G -S -s --data-urlencode "cmd=trx-control.php -set-alsavol $ALSAVOLUME_MAX" http://$IP_ADDR/command/)
		if [[ $RESULT != "" ]]; then
			RESULT=$(curl -G -S -s --data-urlencode "cmd=trx-control.php -set-alsavol $ALSAVOLUME_MAX" http://$IP_ADDR/command/)
			if [[ $RESULT != "" ]]; then
				echo echo $(date +%F" "%T)"spspre.sh trx-control.php -set-alsavol failed: $IP_ADDR" >> /var/log/moode_spsevent.log
			fi
		fi
	done
fi
