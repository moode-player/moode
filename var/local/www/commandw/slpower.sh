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
# NOTES:
# 1) The script exits if an Input source (Analog or S/PDIF) is active.
# 2) The script exits if worker.php startup is not complete.
# 3) When squeezelite is first started and initiates sync up with LMS the
# script gets run several times. An example sequence is below.
# Run script
# Power button state (2)
# Run script
# Power off
# Run script
# Power on
#

#echo "Run script" >> /var/log/moode_slpower.log

SQLDB=/var/local/www/db/moode-sqlite3.db
RESULT=$(sqlite3 $SQLDB "SELECT value FROM cfg_system WHERE param IN ('volknob','alsavolume_max','alsavolume','amixname','mpdmixer','camilladsp_volume_sync','rsmaftersl','wrkready','inpactive','volknob_mpd')")
readarray -t arr <<<"$RESULT"
VOLKNOB=${arr[0]}
ALSAVOLUME_MAX=${arr[1]}
ALSAVOLUME=${arr[2]}
AMIXNAME=${arr[3]}
MPDMIXER=${arr[4]}
CDSP_VOLSYNC=${arr[5]}
RSMAFTERSL=${arr[6]}
WRKREADY=${arr[7]}
INPACTIVE=${arr[8]}
VOLKNOB_MPD=${arr[9]}

if [[ $INPACTIVE == "1" ]]; then
	echo "Input source (Analog or S/PDIF) active, exit 1"
	#echo "Input source (Analog or S/PDIF) active, exit 1" >> /var/log/moode_slpower.log
	exit 1
fi

if [[ $WRKREADY == "0" ]]; then
	echo "Worker startup not complete, exit 0"
	#echo "Worker startup not complete, exit 0" >> /var/log/moode_slpower.log
else
	# See if -V hardware mixer is present in OTHEROPTIONS
	VOPT=$(sqlite3 $SQLDB "SELECT * FROM cfg_sl WHERE value LIKE '%-V%'")

	if [[ $1 == "0" ]]; then
		echo "Power off"
		#echo "Power off" >> /var/log/moode_slpower.log
		$(sqlite3 $SQLDB "UPDATE cfg_system SET value='0' WHERE param='slactive'")

		if [[ $CDSP_VOLSYNC == "on" ]] && [[ $VOLKNOB_MPD != "-1"  ]]; then
			# Restore knob volume to saved MPD volume
			$(sqlite3 $SQLDB "UPDATE cfg_system SET value='$VOLKNOB_MPD' WHERE param='volknob'")
			/var/www/vol.sh -restore
			systemctl restart mpd2cdspvolume
		else
			# Restore knob volume
			if [[ $VOPT != "" ]]; then
				ALSAVOL=$(/var/www/util/sysutil.sh get-alsavol "$AMIXNAME")
				if [[ $ALSAVOL == "0%" ]] ; then
					/var/www/vol.sh -restore
				fi
			else
				/var/www/vol.sh -restore
			fi
		fi

		if [[ $RSMAFTERSL == "Yes" ]]; then
			echo "Resume MPD"
			#echo "Resume MPD" >> /var/log/moode_slpower.log
			/usr/bin/mpc play > /dev/null
		fi
	elif [[ $1 == "1" ]]; then
		echo "Power on"
		#echo "Power on" >> /var/log/moode_slpower.log
		$(sqlite3 $SQLDB "UPDATE cfg_system SET value='1' WHERE param='slactive'")
		/usr/bin/mpc stop > /dev/null
		sleep 1
		if [[ $CDSP_VOLSYNC == "on" ]]; then
			# Save knob level then set camilladsp volume to 100% (0dB)
			$(sqlite3 $SQLDB "UPDATE cfg_system SET value='$VOLKNOB' WHERE param='volknob_mpd'")
			/var/www/vol.sh 100
		elif [[ $ALSAVOLUME != "none" && $VOPT == "" ]]; then
			# Set 0dB ALSA volume
			/var/www/util/sysutil.sh set-alsavol "$AMIXNAME" $ALSAVOLUME_MAX
		fi
	else
		# Value 2 is sometimes returned (when squeezelite is first started and LMS is already running?)
		echo "Power button state ($1)"
	    #echo "Power button state ($1)" >> /var/log/moode_slpower.log
	fi
fi

exit 0
