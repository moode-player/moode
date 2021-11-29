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
if [[ $PLAYER_EVENT != "started" ]] && [[ $PLAYER_EVENT != "stopped" ]]; then
	#echo "Exit: "$PLAYER_EVENT >> /home/pi/spotevent.log
	exit 0
fi
#echo "Event: "$PLAYER_EVENT >> /home/pi/spotevent.log

SQLDB=/var/local/www/db/moode-sqlite3.db
RESULT=$(sqlite3 $SQLDB "select value from cfg_system where param='alsavolume_max' or param='alsavolume' or param='amixname' or param='mpdmixer' or param='rsmafterspot' or param='inpactive' or param='multiroom_tx'")
readarray -t arr <<<"$RESULT"
ALSAVOLUME_MAX=${arr[0]}
ALSAVOLUME=${arr[1]}
AMIXNAME=${arr[2]}
MPDMIXER=${arr[3]}
RSMAFTERSPOT=${arr[4]}
INPACTIVE=${arr[5]}
MULTIROOM_TX=${arr[6]}
RX_ADDRESSES=$(sudo moodeutl -d | grep rx_addresses | cut -d'|' -f2)

if [[ $INPACTIVE == '1' ]]; then
	exit 1
fi

if [[ $PLAYER_EVENT == "started" ]]; then
	/usr/bin/mpc stop > /dev/null

	# Allow time for ui update
	sleep 1

	$(sqlite3 $SQLDB "update cfg_system set value='1' where param='spotactive'")

	# Local
	if [[ $ALSAVOLUME != "none" ]]; then
		/var/www/command/util.sh set-alsavol "$AMIXNAME" $ALSAVOLUME_MAX
	fi

	# Multiroom receivers
	if [[ $MULTIROOM_TX == "On" ]]; then
		for IP_ADDR in $RX_ADDRESSES; do
			RESULT=$(curl -G -S -s --data-urlencode "cmd=trx-status.php -set-alsavol $ALSAVOLUME_MAX" http://$IP_ADDR/command/)
			if [[ $RESULT != "" ]]; then
				RESULT=$(curl -G -S -s --data-urlencode "cmd=trx-status.php -set-alsavol $ALSAVOLUME_MAX" http://$IP_ADDR/command/)
				if [[ $RESULT != "" ]]; then
					echo echo $(date +%F" "%T)"spotevent.sh trx-status.php -set-alsavol failed: $IP_ADDR" >> /home/pi/renderer_error.log
				fi
			fi
		done
	fi
fi

if [[ $PLAYER_EVENT == "stopped" ]]; then
	$(sqlite3 $SQLDB "update cfg_system set value='0' where param='spotactive'")

	# Local
	# Restore 0dB hardware volume when mpd configured as below
	if [[ $MPDMIXER == "software" || $MPDMIXER == "none" ]]; then
		if [[ $ALSAVOLUME != "none" ]]; then
			/var/www/command/util.sh set-alsavol "$AMIXNAME" $ALSAVOLUME_MAX
		fi
	fi
	/var/www/vol.sh -restore

	# Multiroom receivers
	if [[ $MULTIROOM_TX == "On" ]]; then
		for IP_ADDR in $RX_ADDRESSES; do
			RESULT=$(curl -G -S -s --data-urlencode "cmd=vol.sh -restore" http://$IP_ADDR/command/)
			if [[ $RESULT != "" ]]; then
				RESULT=$(curl -G -S -s --data-urlencode "cmd=vol.sh -restore" http://$IP_ADDR/command/)
				if [[ $RESULT != "" ]]; then
					echo $(date +%F" "%T)" spotevent.sh vol.sh -restore failed: $IP_ADDR" >> /home/pi/renderer_error.log
				fi
			fi
		done
	fi

	if [[ $RSMAFTERSPOT == "Yes" ]]; then
		/usr/bin/mpc play > /dev/null
	fi
fi
