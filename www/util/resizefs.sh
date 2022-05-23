#!/bin/bash
# Part of raspi-config https://github.com/RPi-Distro/raspi-config
#
# See LICENSE file for copyright and license details

if [[ -z $1 ]]; then
	echo "resizefs: missing arg <start>"
	exit
fi

if [[ $1 != "start" ]]; then
	echo "resizefs: valid arg is <start>"
	exit
fi

# Max size of sd card
PART_END=""

get_init_sys() {
	if command -v systemctl > /dev/null && systemctl | grep -q '\-\.mount'; then
		SYSTEMD=1
	elif [ -f /etc/init.d/cron ] && [ ! -h /etc/init.d/cron ]; then
		SYSTEMD=0
	else
		echo "Unrecognised init system"
		exit 1
	fi
}

get_init_sys
if [ $SYSTEMD -eq 1 ]; then
	ROOT_PART=$(mount | sed -n 's|^/dev/\(.*\) on / .*|\1|p')
else
	if ! [ -h /dev/root ]; then
	  echo "/dev/root does not exist or is not a symlink. Don't know how to expand" 20 60 2
	  exit 0
	fi
	ROOT_PART=$(readlink /dev/root)
fi

# determine which type of boot device, USB or SD card
TMP=${ROOT_PART:0:3}
if [ $TMP = mmc ]; then
	BOOT_DEV=mmcblk0
	PART_NUM=${ROOT_PART#"$BOOT_DEV"p}
else
	BOOT_DEV=sda
	PART_NUM=${ROOT_PART#"$BOOT_DEV"}
fi

#PART_NUM=${ROOT_PART#mmcblk0p}
if [ "$PART_NUM" = "$ROOT_PART" ]; then
	echo "$ROOT_PART is not an SD card. Don't know how to expand" 20 60 2
	exit 0
fi

# NOTE: the NOOBS partition layout confuses parted. For now, let's only
# agree to work with a sufficiently simple partition layout
if [ "$PART_NUM" -ne 2 ]; then
	echo "Your partition layout is not currently supported by this tool. You are probably using NOOBS, in which case your root filesystem is already expanded anyway." 20 60 2
	exit 0
fi

LAST_PART_NUM=$(parted /dev/$BOOT_DEV -ms unit s p | tail -n 1 | cut -f 1 -d:)
if [ $LAST_PART_NUM -ne $PART_NUM ]; then
	echo "$ROOT_PART is not the last partition. Don't know how to expand" 20 60 2
	exit 0
fi

# Get the starting offset of the root partition
PART_START=$(parted /dev/$BOOT_DEV -ms unit s p | grep "^${PART_NUM}" | cut -f 2 -d: | sed 's/[^0-9]//g')
[ "$PART_START" ] || exit 1
# Return value will likely be error for fdisk as it fails to reload the
# partition table because the root fs is mounted
fdisk /dev/$BOOT_DEV <<EOF
p
d
$PART_NUM
n
p
$PART_NUM
$PART_START
$PART_END
p
w
EOF

# Set up an init.d script
cat <<EOF > /etc/init.d/resize2fs_once &&
#!/bin/sh
### BEGIN INIT INFO
# Provides:          resize2fs_once
# Required-Start:
# Required-Stop:
# Default-Start: 3
# Default-Stop:
# Short-Description: Resize the root filesystem to fill partition
# Description:
### END INIT INFO

. /lib/lsb/init-functions

case "\$1" in
	start)
	    log_daemon_msg "Starting resize2fs_once" &&
	    resize2fs /dev/$ROOT_PART &&
	    update-rc.d resize2fs_once remove &&
	    rm /etc/init.d/resize2fs_once &&
	    log_end_msg \$?
	    ;;
	*)
	    echo "Usage: \$0 start" >&2
	    exit 3
	    ;;
esac
EOF

chmod +x /etc/init.d/resize2fs_once &&
update-rc.d resize2fs_once defaults &&
echo -e "Root partition has been resized\nThe file system will be enlarged after reboot"

echo "SYSTEMD "$SYSTEMD
echo "BOOT_DEV "$BOOT_DEV
echo "ROOT_PART "$ROOT_PART
echo "PART_NUM "$PART_NUM
echo "LAST_PART_NUM "$LAST_PART_NUM
echo "PART_START "$PART_START
echo "PART_END "$PART_END
