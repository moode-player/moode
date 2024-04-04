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

LOGFILE="/var/log/moode_slpower.log"
DEBUG=$(sudo moodeutl -d -gv debuglog)

debug_log () {
	if [[ $DEBUG == '0' ]]; then
		return 0
	fi
	echo "$1"
	TIME=$(date +'%Y%m%d %H%M%S')
	echo "$TIME $1" >> $LOGFILE
}

#debug_log "Run script"

SQLDB=/var/local/www/db/moode-sqlite3.db
RESULT=$(sqlite3 $SQLDB "SELECT value FROM cfg_system WHERE param IN ('volknob','alsavolume_max','alsavolume','amixname','mpdmixer','camilladsp_volume_sync','rsmaftersl','wrkready','inpactive')")
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

if [[ $INPACTIVE == "1" ]]; then
	debug_log "Input source (Analog or S/PDIF) active, exit 1"
	exit 1
fi

if [[ $WRKREADY == "0" ]]; then
	debug_log "Worker startup not complete, exit 0"
else
	# See if -V hardware mixer is present in OTHEROPTIONS
	VOPT=$(sqlite3 $SQLDB "SELECT * FROM cfg_sl WHERE value LIKE '%-V%'")

	if [[ $1 == "0" ]]; then
		debug_log "Event: Power off"
		$(sqlite3 $SQLDB "UPDATE cfg_system SET value='0' WHERE param='slactive'")

		# Local
		/var/www/util/vol.sh -restore

		if [[ $CDSP_VOLSYNC == "on" ]]; then
			# Restore CDSP volume
			systemctl restart mpd2cdspvolume
		fi

		if [[ $RSMAFTERSL == "Yes" ]]; then
			debug_log "Resume MPD"
			/usr/bin/mpc play > /dev/null
		fi
	elif [[ $1 == "1" ]]; then
		debug_log "Event: Power on"
		$(sqlite3 $SQLDB "UPDATE cfg_system SET value='1' WHERE param='slactive'")
		/usr/bin/mpc stop > /dev/null
		sleep 1

		# Local
		if [[ $CDSP_VOLSYNC == "on" ]]; then
			# Set 0dB CDSP volume
			sed -i '0,/- -.*/s//- 0.0/' /var/lib/cdsp/statefile.yml
		elif [[ $ALSAVOLUME != "none" && $VOPT == "" ]]; then
			# Set 0dB ALSA volume
			/var/www/util/sysutil.sh set-alsavol "$AMIXNAME" $ALSAVOLUME_MAX
		fi
	else
		# Value 2 is aparantly returned when squeezelite is first started and LMS is already running
	    debug_log "Event: ($1)"
	fi
fi

exit 0
