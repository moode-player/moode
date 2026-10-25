#!/bin/bash
#
# SPDX-License-Identifier: GPL-3.0-or-later
# Copyright 2014 The moOde audio player project / Tim Curtis
#

#
# From cfg_plugin
# Arg $1 = component
# Arg $2 = type
# Arg $3 = plugin
#

COMPONENT=$1
TYPE=$2
PLUGIN=$3

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
# Get plugin suffix (default is '', for test zips its '-test1')
SUFFIX=$(sqlite3 $SQLDB "SELECT value FROM cfg_system WHERE param='pkgid_suffix'")

# Initialize the updater log
message_log "Start"
truncate $UPDATER_LOG --size 0
chmod 0666 $UPDATER_LOG

# Check for renderer package in home directory
if [ $COMPONENT = "renderer" ]; then
	RESULT=$(moodeutl --installpkg $TYPE 2>&1)
	if [[ $RESULT = *"Package installed"* ]]; then
		message_log "Finished"
		exit 0
	fi
fi

# Download and unzip
cd /var/local/www
message_log "Downloading plugin [update-$PLUGIN$SUFFIX.zip]"
wget -q "$UPD_URL/$COMPONENT/$PLUGIN/update-$PLUGIN$SUFFIX.zip" -O "update-$PLUGIN.zip" > /dev/null 2>&1
if [ $? -ne 0 ] ; then
	message_log "Plugin zip not found, update cancelled"
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
	message_log "Finished"
	rm -rf update
	exit 0
fi
