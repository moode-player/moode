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
# Arg $1 = component name
# Arg $2 = plugin name
#

COMPONENT=$1
PLUGIN=$2

MOODE_LOG="/var/log/moode.log"
UPDATER_LOG="/var/log/moode_plugin.log"

message_log () {
	echo "$1"
	TIME=$(date +'%Y%m%d %H%M%S')
	echo "$TIME updater: $1" >> $MOODE_LOG
	echo "$TIME updater: $1" >> $UPDATER_LOG
}

# Get base URL to the plugins update repo
SQLDB=/var/local/www/db/moode-sqlite3.db
UPD_URL=$(sqlite3 $SQLDB "SELECT value FROM cfg_system WHERE param='res_plugin_upd_url'")

# Initialize the updater log
rm -f $UPDATER_LOG
touch $UPDATER_LOG
chmod 0666 $UPDATER_LOG

cd /var/local/www

# Download and unzip
message_log "Downloading package [update-$PLUGIN.zip]"
wget -q "$UPD_URL/$COMPONENT/$PLUGIN/update-$PLUGIN.zip" -O "update-$PLUGIN.zip" > /dev/null 2>&1
if [ $? -ne 0 ] ; then
	message_log "Package not found, update cancelled"
	exit 1
else
	unzip -q -o "update-$PLUGIN.zip"
	rm "update-$PLUGIN.zip"
fi

# Install plugin update
chmod -R 0755 update
update/install.sh "$PLUGIN"

# Report status
if [ $? -ne 0 ] ; then
	message_log "Install failed, update cancelled"
	rm -rf update
	exit 1
else
	# Download update-$PLUGIN.txt to mark success
	wget -q "$UPD_URL/$COMPONENT/$PLUGIN/update-$PLUGIN.txt" -O "update-$PLUGIN.txt"
	message_log "Update installed, restart required"
	rm -rf update
	exit 0
fi

# Finish up
cd ~/
