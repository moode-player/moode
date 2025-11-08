#!/bin/bash
#
# SPDX-License-Identifier: GPL-3.0-or-later
# Copyright 2014 The moOde audio player project / Tim Curtis
#
# Arg $1 = chromium version
#
# Moode10: Save in case needed in future
#

MOODE_LOG="/var/log/moode.log"
CHROMEUP_LOG="/var/log/moode_chromeup.log"

message_log () {
	echo "$1"
	TIME=$(date +'%Y%m%d %H%M%S')
	echo "$TIME chromeup: $1" >> $MOODE_LOG
	echo "$TIME chromeup: $1" >> $CHROMEUP_LOG
}

# Initialize the chromeup log
rm -f $CHROMEUP_LOG
touch $CHROMEUP_LOG
chmod 0666 $CHROMEUP_LOG

if [ -z $1 ]; then
	message_log "Missing arg: chromium package version"
	exit 1
else
	CHROMIUM_VER=$1
	message_log "Downgrading chromium"
fi

# Step 1 - APT update
message_log "- Updating package list"
apt update
if [ $? -ne 0 ]; then
	message_log "Apt update failed"
	exit 1
fi

# Step 2 - Remove package holds for stock >v130 package set
message_log "- Removing package holds"
apt-mark unhold chromium chromium-common chromium-l10n chromium-sandbox rpi-chromium-mods
if [ $? -ne 0 ]; then
	message_log "APT unhold failed"
	exit 1
fi

# Step 3 - Downgrade to v126 package set
message_log "- Installing chromium-browser "$CHROMIUM_VER
apt -y install \
chromium-browser=$CHROMIUM_VER \
chromium-browser-l10n=$CHROMIUM_VER \
chromium-codecs-ffmpeg-extra=$CHROMIUM_VER \
--allow-downgrades --allow-change-held-packages
if [ $? -ne 0 ]; then
	message_log "APT install failed"
	exit 1
fi

# Step 4 - Remove stock >v130 package set
message_log "- Removing leftover packages"
apt -y purge \
chromium \
chromium-common \
chromium-l10n \
chromium-sandbox \
rpi-chromium-mods
if [ $? -ne 0 ]; then
	message_log "APT purge failed"
	exit 1
fi
apt -y autoremove
if [ $? -ne 0 ]; then
	message_log "APT autoremove failed"
	exit 1
fi

# Finish up
message_log "Downgrade complete"
exit 0
