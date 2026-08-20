#!/bin/bash
#
# SPDX-License-Identifier: GPL-3.0-or-later
# Copyright 2014 The moOde audio player project / Tim Curtis
#
# Install the qbzd Qobuz Connect renderer (https://github.com/vicrodh/qbz)
#
# Unlike AirPlay/Spotify which are built from source via the pkgbuild repo,
# qbzd ships prebuilt static binaries so this installer downloads the release
# for the current architecture, verifies it and installs binary + service.
#

# moOde builds come from the fork's standalone qbzd releases
# (fork-qbzd-release.yml, tags qbzd-v<version>) while Qobuz Connect pairing is
# unmerged — then revert to vicrodh/qbz and the plain v<version> tag scheme.
# The `.moodeN` suffix marks a moOde build of upstream 2.0.2: the binary reports
# only its Cargo version, so the suffix is recorded in QBZD_BUILD_FILE for the
# Renderer Config screen to display.
QBZD_VERSION="2.0.2.moode26"
QBZD_REPO="https://github.com/PhilipVinc/qbz"
QBZD_TAG="qbzd-v$QBZD_VERSION"
QBZD_BUILD_FILE="/var/local/www/qbzd-build"

# Initialize the step counter
STEP=0
TOTAL_STEPS=4

# Log files
MOODE_LOG="/var/log/moode.log"
PLUGIN_LOG="/var/log/moode_plugin.log"

cancel_update () {
	if [ $# -gt 0 ] ; then
		message_log "$1"
	fi
	message_log "** Exiting install"
	exit 1
}

message_log () {
	echo "$1"
	TIME=$(date +'%Y%m%d %H%M%S')
	echo "$TIME updater: $1" >> $MOODE_LOG
	echo "$TIME updater: $1" >> $PLUGIN_LOG
}

WD=$(mktemp -d)
cd $WD || cancel_update "** Unable to create work directory"
truncate $PLUGIN_LOG --size 0
message_log "Start install for Qobuz Connect (qbzd $QBZD_VERSION)"

# 1 - Determine architecture
STEP=$((STEP + 1))
message_log "** Step $STEP-$TOTAL_STEPS: Determine architecture"
case "$(uname -m)" in
	aarch64) ARCH="aarch64";;
	x86_64) ARCH="amd64";;
	*) cancel_update "** Unsupported architecture $(uname -m) (qbzd requires 64-bit)";;
esac
TARBALL="qbzd-$QBZD_VERSION-linux-$ARCH.tar.gz"

# 2 - Download release
STEP=$((STEP + 1))
message_log "** Step $STEP-$TOTAL_STEPS: Download $TARBALL"
wget -q "$QBZD_REPO/releases/download/$QBZD_TAG/$TARBALL" -O "$TARBALL"
if [ $? -ne 0 ]; then
	cancel_update "** Download failed"
fi
tar -zxf "$TARBALL"
if [ $? -ne 0 ]; then
	cancel_update "** Unpack failed"
fi

# 3 - Install binary
STEP=$((STEP + 1))
message_log "** Step $STEP-$TOTAL_STEPS: Install qbzd binary"
install -Dm755 "qbzd-$QBZD_VERSION-linux-$ARCH/qbzd" /usr/local/bin/qbzd
if [ $? -ne 0 ]; then
	cancel_update "** Install failed"
fi
# Record what was installed: `qbzd --version` reports the Cargo version only,
# so without this a moOde build is indistinguishable from upstream 2.0.2.
echo "$QBZD_VERSION" > $QBZD_BUILD_FILE

# 4 - Finish up
STEP=$((STEP + 1))
message_log "** Step $STEP-$TOTAL_STEPS: Finish up"
cd /
rm -rf $WD
# The standalone qbzd tarball ships a systemd unit for running the daemon on
# its own. Under moOde the worker owns the daemon lifecycle (startQobuz), so an
# enabled unit is a second instance competing for the audio device — and its
# ExecStartPre clears cfg_system.qbzactive, which blanks the Renderer Active
# overlay every restart attempt. Leave the unit file in place, just disabled.
if systemctl list-unit-files qbzd.service > /dev/null 2>&1; then
	if [ "$(systemctl is-enabled qbzd.service 2>/dev/null)" = "enabled" ] || \
	   [ "$(systemctl is-active qbzd.service 2>/dev/null)" = "active" ]; then
		message_log "** Disabling qbzd.service (moOde manages the daemon itself)"
		systemctl disable --now qbzd.service > /dev/null 2>&1
	fi
fi
systemctl daemon-reload
message_log "** Installed qbzd $QBZD_VERSION (binary reports $(/usr/local/bin/qbzd --version | awk '{print $2}'))"
message_log "Install complete: turn the renderer on in Renderer Config"
exit 0
