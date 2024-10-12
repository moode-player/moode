#!/bin/bash
#
# SPDX-License-Identifier: GPL-3.0-or-later
# Copyright 2014 The moOde audio player project / Tim Curtis
#

# Arg $1 = chromium-browser version
#

MOODE_LOG="/var/log/moode.log"

message_log () {
	echo "$1"
	TIME=$(date +'%Y%m%d %H%M%S')
	echo "$TIME chromeup: $1" >> $MOODE_LOG
}

if [ -z $1 ]; then
	message_log "Missing arg: chromium version"
	exit 1
else
	CHROME_VER=$1
	message_log "Downgrading chromium"
fi

# Step 1 - APT update
message_log "- Updating package list"
apt update
if [ $? -ne 0 ] ; then
	message_log "Apt update failed"
	exit 1
fi

# Step 2 - Remove package holds for current package set
message_log "- Removing package holds"
apt-mark unhold chromium chromium-browser chromium-common chromium-sandbox rpi-chromium-mods
if [ $? -ne 0 ] ; then
	message_log "APT mark failed"
	exit 1
fi

# Step 3 - Downgrade
message_log "- Installing chromium-browser "$CHROMIUM_VER
apt -y install \
chromium-browser=$CHROME_VER \
chromium-browser-l10n=$CHROME_VER \
chromium-codecs-ffmpeg-extra=$CHROME_VER \
--allow-downgrades --allow-change-held-packages
if [ $? -ne 0 ] ; then
	message_log "APT install failed"
	exit 1
fi

# Step 4 - Remove current version package set
message_log "- Removing leftover packages"
apt -y purge \
chromium \
chromium-common \
chromium-sandbox \
rpi-chromium-mods
if [ $? -ne 0 ] ; then
	message_log "APT purge failed"
	exit 1
fi
apt -y autoremove
if [ $? -ne 0 ] ; then
	message_log "APT autoremove failed"
	exit 1
fi

# Finish up
message_log "Downgrade complete"
exit 0
