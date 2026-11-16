#!/bin/bash
#
# SPDX-License-Identifier: GPL-3.0-or-later
# Copyright 2014 The moOde audio player project / Tim Curtis
#
# Boot splash (Plymouth): moOde logotype with pulsing dots on boot, reboot and shutdown,
# shown until moOde is ready (or the local display takes over the screen).
# The plymouth package is installed on first use (internet needed). Changes take effect
# after a restart. Progress/result for System Config: $STATUS (installing, on, off,
# failed).
#
# Usage: bootsplash.sh on|off|status
#
# Kernel/boot messages are hidden with cmdline.txt parameters; only the parameters this
# script added are removed again (list in $ADDED).
#
THEME_DIR=/usr/share/plymouth/themes/moode
LOGO=/var/www/images/moode-logotype.png
CMDLINE=/boot/firmware/cmdline.txt
CONFIG=/boot/firmware/config.txt
ADDED=/var/local/www/bootsplash-cmdline
PLYMOUTHD_CONF=/etc/plymouth/plymouthd.conf
TMPFILES=/etc/tmpfiles.d/moode-bootsplash.conf
STATUS=/var/local/www/bootsplash-status
PARAMS=(quiet splash plymouth.ignore-serial-consoles logo.nologo vt.global_cursor_default=0 loglevel=3 systemd.show_status=false rd.udev.log_level=3)

log() { echo "$(date +'%Y%m%d %H%M%S') bootsplash: $1" >> /var/log/moode.log; }
dropin() { # unit, content ('' removes it)
	local f="/etc/systemd/system/$1.d/moode-bootsplash.conf"
	if [[ -z "$2" ]]; then
		rm -f "$f"
		rmdir --ignore-fail-on-non-empty "/etc/systemd/system/$1.d" 2>/dev/null || true
	else
		mkdir -p "/etc/systemd/system/$1.d"
		printf '# Managed by /var/www/util/bootsplash.sh\n%s\n' "$2" > "$f"
	fi
}
has_word() { [[ " $1 " == *" $2 "* ]]; }
remove_word() { awk -v w="$2" '{ out=""; for (i = 1; i <= NF; i++) if ($i != w) out = out (out == "" ? "" : " ") $i; print out }' <<<"$1"; }

on() {
	if ! command -v plymouth-set-default-theme >/dev/null; then
		echo installing > "$STATUS"
		log "installing the plymouth package"
		if ! { timeout 600 apt-get update -qq &&
			DEBIAN_FRONTEND=noninteractive timeout 600 apt-get install -y -qq --no-install-recommends plymouth; } \
			>> /var/log/moode.log 2>&1; then
			echo failed > "$STATUS"
			log "plymouth installation failed (no internet connection?)"
			exit 1
		fi
	fi
	cp "$LOGO" "$THEME_DIR/logo.png"
	plymouth-set-default-theme moode

	# Display driver: with KMS Plymouth waits for the GPU driver, which comes up late
	# without an initramfs. Without KMS (e.g. the original 7" Touch Display) there is no
	# DRM device; Plymouth then only uses the framebuffer after its device timeout, and
	# its evdev input code crashes on the framebuffer, so use the console input there.
	# (plymouth.ignore-udev does not work: the framebuffer renderer then opens /dev/dri/card0)
	local line timeout quit_args
	line=$(head -n1 "$CMDLINE")
	if grep -q '^[[:space:]]*dtoverlay=vc4-kms-v3d' "$CONFIG"; then
		timeout=30
		quit_args='quit --retain-splash'
		if grep -qx plymouth.use-legacy-input "$ADDED" 2>/dev/null; then
			line=$(remove_word "$line" plymouth.use-legacy-input)
			sed -i '/^plymouth.use-legacy-input$/d' "$ADDED"
		fi
	else
		timeout=1
		quit_args='quit' # --retain-splash crashes the framebuffer renderer
		PARAMS+=(plymouth.use-legacy-input)
	fi
	mkdir -p /etc/plymouth
	printf '# Managed by /var/www/util/bootsplash.sh\n[Daemon]\nTheme=moode\nShowDelay=0\nDeviceTimeout=%s\n' "$timeout" > "$PLYMOUTHD_CONF"

	# Kernel parameters for a text-free boot screen; boot text goes to tty3
	touch "$ADDED"
	local p
	for p in "${PARAMS[@]}"; do
		if ! has_word "$line" "$p"; then
			line="$line $p"
			grep -qxF -- "$p" "$ADDED" || echo "$p" >> "$ADDED"
		fi
	done
	if has_word "$line" console=tty1; then
		line=" $line "; line=${line// console=tty1 / console=tty3 }; line=${line# }; line=${line% }
		grep -qx 'console=tty3' "$ADDED" || echo 'console=tty3' >> "$ADDED"
	fi
	printf '%s\n' "$line" > "$CMDLINE"

	# End the splash when moOde is ready instead of when Linux has booted
	dropin plymouth-quit.service $'[Service]\nExecStart=\nExecStart=/bin/true'
	dropin plymouth-quit-wait.service $'[Service]\nExecStart=\nExecStart=/bin/true'
	dropin localdisplay.service "[Service]
ExecStartPre=-+/usr/bin/plymouth $quit_args"
	dropin getty@tty1.service $'[Unit]\nConditionPathExists=!/run/bootsplash-active'
	echo 'f /run/bootsplash-active 0644 root root -' > "$TMPFILES"
	systemctl daemon-reload
	systemctl enable bootsplash-quit.service
	echo on > "$STATUS"
	log "on"
}

off() {
	local line p
	if [[ -f $ADDED ]]; then
		line=$(head -n1 "$CMDLINE")
		while read -r p; do
			[[ -n $p ]] || continue
			if [[ $p == console=tty3 ]]; then
				line=" $line "; line=${line// console=tty3 / console=tty1 }; line=${line# }; line=${line% }
			else
				line=$(remove_word "$line" "$p")
			fi
		done < "$ADDED"
		printf '%s\n' "$line" > "$CMDLINE"
		rm -f "$ADDED"
	fi
	systemctl disable bootsplash-quit.service 2>/dev/null
	for unit in plymouth-quit.service plymouth-quit-wait.service localdisplay.service getty@tty1.service; do
		dropin "$unit" ''
	done
	rm -f "$TMPFILES" "$PLYMOUTHD_CONF" /run/bootsplash-active
	systemctl daemon-reload
	if command -v plymouth-set-default-theme >/dev/null && [[ "$(plymouth-set-default-theme)" == moode ]]; then
		plymouth-set-default-theme --reset
	fi
	echo off > "$STATUS"
	log "off"
}

case "$1" in
	on) on ;;
	off) off ;;
	status) cat "$STATUS" 2>/dev/null || echo off ;;
	*) echo "Usage: $0 on|off|status"; exit 1 ;;
esac
