#!/bin/bash
#
# SPDX-License-Identifier: GPL-3.0-or-later
# Copyright 2014 The moOde audio player project / Tim Curtis
#

SQLDB=/var/local/www/db/moode-sqlite3.db

# Helper functions
function restart_samba_if_on () {
	RESULT=$(sqlite3 ${1} "SELECT value FROM cfg_system WHERE param='fs_smb'")
	if [[ $RESULT = "On" ]]; then
		systemctl restart smbd
		systemctl restart nmbd
	fi
}
function restart_nfs_if_on () {
	RESULT=$(sqlite3 ${1} "SELECT value FROM cfg_system WHERE param='fs_nfs'")
	if [[ $RESULT = "On" ]]; then
		systemctl restart nfs-kernel-server
	fi
}
# Escape a value for a sed text or /regex/ so it is matched or written literally
function sed_escape () {
	printf '%s' "$1" | sed -e 's/[]\/$*.^[\\]/\\&/g'
}

# Only USB auto-mounts under /media are handled
if [[ $2 != /media/?* ]]; then
	exit 0
fi
NAME=$(basename "$2")

# Add/remove mount: Udisks-glue
# $2 = mount point (/media/DISK_LABEL)
if [[ $1 = "add_mount_udisks" ]]; then
	# SMB
	SMB_PWD=$(moodeutl -d -gv fs_smb_pwd)
	if [[ "$SMB_PWD" = "" ]]; then
		GUEST_OK="guest ok = Yes"
	else
		GUEST_OK="#guest ok = Yes"
	fi
	if [[ $(grep -w -c -F -- "$2" /etc/samba/smb.conf) = 0 ]]; then
		sed -i "$ a[$(sed_escape "$NAME")]\ncomment = USB Storage\npath = $(sed_escape "$2")\nread only = No\n$GUEST_OK" /etc/samba/smb.conf
		restart_samba_if_on ${SQLDB}
	fi
	# NFS
	if [[ $(grep -w -c -F -- "$NAME" /etc/exports) = 0 ]]; then
		ACCESS=$(sqlite3 $SQLDB "SELECT value FROM cfg_system WHERE param='fs_nfs_access'")
		OPTIONS=$(sqlite3 $SQLDB "SELECT value FROM cfg_system WHERE param='fs_nfs_options'")
		sed -i "$ a$(sed_escape "/srv/nfs/usb/$NAME")\t$(sed_escape "$ACCESS($OPTIONS)")\n" /etc/exports
		restart_nfs_if_on ${SQLDB}
	fi

	exit 0
fi
if [[ $1 = "remove_mount_udisks" ]]; then
	# SMB
	sed -i "/$(sed_escape "$NAME")]/,/guest/ d" /etc/samba/smb.conf
	restart_samba_if_on ${SQLDB}
	# NFS
	sed -i "/$(sed_escape "$NAME")/ d" /etc/exports
	sed -i '/^$/d' /etc/exports
	restart_nfs_if_on ${SQLDB}

    exit 0
fi
