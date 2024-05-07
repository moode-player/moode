#!/bin/bash
#
# SPDX-License-Identifier: GPL-3.0-or-later
# Copyright 2014 The moOde audio player project / Tim Curtis
#

# Arg $1 = package id to be updated.
# It is set by function getPkgId()in module inc/commpn.php and is also
# used to name the update zip and txt files ex: update-moode.zip
#

MOODE_LOG="/var/log/moode.log"
UPDATER_LOG="/var/log/moode_update.log"

message_log () {
	echo "$1"
	TIME=$(date +'%Y%m%d %H%M%S')
	echo "$TIME updater: $1" >> $MOODE_LOG
	echo "$TIME updater: $1" >> $UPDATER_LOG
}

# Get URL to the location of the update zip
SQLDB=/var/local/www/db/moode-sqlite3.db
UPD_URL=$(sqlite3 $SQLDB "SELECT value FROM cfg_system WHERE param='res_software_upd_url'")

# Initialize the updater log
rm -f $UPDATER_LOG
touch $UPDATER_LOG
chmod 0666 $UPDATER_LOG

cd /var/local/www

# Download and unzip
message_log "Downloading package [update-$1.zip]"
wget -q $UPD_URL/update-$1.zip -O update-$1.zip > /dev/null 2>&1
if [ $? -ne 0 ] ; then
	message_log "Package not found, update cancelled"
	exit 1
else
	unzip -q -o update-$1.zip
	rm update-$1.zip
fi

# Install update
chmod -R 0755 update
update/install.sh

# Report status
# NOTE: The "inplace_upd_applied" var is checked and reset in daemon/worker.php
if [ $? -ne 0 ] ; then
	$(sqlite3 $SQLDB "UPDATE cfg_system SET value='0' WHERE param='inplace_upd_applied'")
	message_log "Install failed, update cancelled"
	rm -rf update
	exit 1
else
	# Download update-$1.txt to mark successful update
	wget -q $UPD_URL/update-$1.txt -O update-$1.txt
	$(sqlite3 $SQLDB "UPDATE cfg_system SET value='1' WHERE param='inplace_upd_applied'")
	message_log "Update installed, restart required"
	rm -rf update
	exit 0
fi

# Finish up
cd ~/
