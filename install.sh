#!/bin/bash
#
# SPDX-License-Identifier: GPL-3.0-or-later
#
# moOde audio player - experimental installer for generic Debian x86_64 (and
# other non-Pi platforms such as Armbian on arm64).
#
# Not the official image build (moOde ships as a pi-gen image from imgbuild):
# this installs the stack on a running Debian 13 (Trixie) and relies on the
# runtime isPi() detection to skip the Pi-only logic (config.txt overlays,
# vcgencmd, GPIO/I2S HATs, LED/fan control).
#
# Requirements: Debian 13 (Trixie) x86_64/arm64 with internet, a login user at
# UID 1000 (worker.php derives the player user from `grep 1000:1000`), and root
# (`sudo ./install.sh`). Phase 0b builds build/dist/ if absent.
#
# Idempotent: re-running re-copies files and re-applies config without
# destroying an existing database (unless --reset-db is passed).
#

set -euo pipefail

# Deterministic command output: we grep tools like `apt-cache policy`, whose
# strings are LOCALISED (fr_FR prints "Candidat :", which silently skipped UPnP
# on every French install). Same reason moOde's sysCmd() forces LC_ALL=C.
export LC_ALL=C.UTF-8 LANG=C.UTF-8

#----------------------------------------------------------------------------#
# CONFIG
#----------------------------------------------------------------------------#

# Renderer/feature groups, default ON to match the moOde image (0 = leaner).
#
# AirPlay and Spotify have no flag on purpose: on-demand, built from
# moode-player/pkgbuild when enabled in the UI (Phase 5c mirrors the plugin
# zips). Never install the distro shairport-sync as a shortcut -
# isAirPlayInstalled() requires a moode-tagged version (`dpkg-query | grep
# moode`), so a distro build is invisible to the UI yet owns the conf and unit.
INSTALL_BLUETOOTH=1      # bluez, bluez-alsa for BT audio
INSTALL_UPNP=1           # upmpdcli (UPnP/OpenHome) - via upstream apt repo
INSTALL_DLNA=1           # minidlna (serve local library)
INSTALL_SQUEEZELITE=1    # squeezelite (LMS player)
INSTALL_LOCALDISPLAY=1   # moOde WebUI/Peppy local display (X + chromium kiosk on HDMI)
INSTALL_LOG2RAM=auto     # log2ram (logs to tmpfs, spares flash) - 'auto'=only on mmcblk (SD/eMMC) root; 1 force; 0 skip
# NOTE: file sharing (samba/nfs-kernel-server/wsdd2) is now always installed in
# CORE_PKGS for Pi parity, left disabled and worker-managed (no opt-out flag).

# Reset (re-create) the SQLite config DB from the shipped schema.
# Default: keep an existing DB. Pass --reset-db to force.
RESET_DB=0

# Deploy everything but do not enable/start the worker daemon. Useful for
# debugging the worker by hand. Pass --no-worker.
NO_WORKER=0

# Update mode: force a fresh web-app build from the current source tree and
# re-deploy/re-apply everything, keeping the existing config DB. Typical flow:
# `git pull` (or check out a newer *-nopi.* tag) then `./install.sh --update`.
UPDATE=0

REPO_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
DIST_DIR="$REPO_DIR/build/dist"
SQLDB="/var/local/www/db/moode-sqlite3.db"
SQLDB_SCHEMA="$REPO_DIR/var/local/www/db/moode-sqlite3.db.sql"

#----------------------------------------------------------------------------#
# Helpers
#----------------------------------------------------------------------------#

log()  { printf '\033[1;32m==>\033[0m %s\n' "$*"; }
warn() { printf '\033[1;33m[!]\033[0m %s\n' "$*"; }
die()  { printf '\033[1;31m[x]\033[0m %s\n' "$*" >&2; exit 1; }

for arg in "$@"; do
	case "$arg" in
		--reset-db) RESET_DB=1 ;;
		--no-worker) NO_WORKER=1 ;;
		--update) UPDATE=1 ;;
		*) die "Unknown argument: $arg" ;;
	esac
done

# Mirror the run to a log beside the script (the clone dir, not /var/log, which
# holds moOde's RUNTIME logs). Override with INSTALL_LOG=/path ./install.sh.
INSTALL_LOG="${INSTALL_LOG:-$REPO_DIR/install-nopi.log}"
exec > >(tee "$INSTALL_LOG") 2>&1
printf '\033[1;32m==>\033[0m install log: %s (%s)\n' "$INSTALL_LOG" "$(date '+%Y-%m-%d %H:%M:%S')"

#----------------------------------------------------------------------------#
# Phase 0 - Preflight checks
#----------------------------------------------------------------------------#

log "Phase 0: preflight checks"

# Must run as root. On a minimal Debian sudo may be absent and the player user
# not yet a sudoer (we add them), so the first run needs `su` / a root shell.
[ "$(id -u)" -eq 0 ] || die "Run as root: 'sudo $0' or, on a fresh minimal Debian without sudo, 'su -c \"$0 $*\"'"

# Debian 13 "Trixie" family ONLY (Debian, Armbian, Raspberry Pi OS). Other bases
# break pinned source builds on their newer toolchains (CMake 4 vs ashuffle's
# yaml-cpp, gcc-15) and 404 on the upmpdcli/moodeaudio repos, which publish
# Debian suites only. Warn loudly but continue, so experimenting stays possible.
OS_ID="$( [ -r /etc/os-release ] && ( . /etc/os-release 2>/dev/null; echo "$ID" ) )"
OS_CODENAME="$( [ -r /etc/os-release ] && ( . /etc/os-release 2>/dev/null; echo "$VERSION_CODENAME" ) )"
OS_PRETTY="$( [ -r /etc/os-release ] && ( . /etc/os-release 2>/dev/null; echo "$PRETTY_NAME" ) )"
DEB_MAJOR="$( [ -f /etc/debian_version ] && cut -d. -f1 /etc/debian_version 2>/dev/null || echo '?' )"
log "Base OS: ${OS_PRETTY:-unknown} (id=${OS_ID:-?}, codename=${OS_CODENAME:-?}, debian_version=$( [ -f /etc/debian_version ] && cat /etc/debian_version || echo none ))"
if { [ "$OS_ID" = debian ] || [ "$OS_ID" = raspbian ]; } && { [ "$OS_CODENAME" = trixie ] || [ "$DEB_MAJOR" = 13 ]; }; then
	: # supported base
else
	warn "================================================================"
	warn "UNSUPPORTED BASE: ${OS_PRETTY:-unknown}."
	warn "moode-nopi supports Debian 13 (Trixie) ONLY"
	warn "  — Debian / Armbian Trixie / Raspberry Pi OS Trixie."
	warn "Other bases (e.g. Ubuntu) break pinned builds & repos."
	warn "Continuing at your own risk in 5s... (Ctrl-C to abort)"
	warn "================================================================"
	sleep 5
fi

# Low-RAM build hardening (root-caused on an Orange Pi PC+ 1GB armhf). The
# on-device source builds `mktemp -d` into /tmp, which Armbian mounts as a RAM
# tmpfs (~50% of RAM): pleezer's ~650MB cargo target/ overflows a 1GB board's
# ~480MB /tmp AND steals the RAM that then OOM-kills rustc. Three gated,
# idempotent measures:
#
#  (1) /tmp on tmpfs -> TMPDIR=/var/tmp (disk). This ALONE lets a 1GB board
#      build everything at full -j(nproc). No-op where /tmp is disk (Pi OS, x86).
#  (2) RAM < 1.5GB: temporary 2G swapfile for the final single-crate rustc peak
#      (~700-900MB). Removed at end of install (zram stays for runtime).
#  (3) RAM < 900MB: cap Rust parallelism, the dependency phase won't fit either.
NOPI_BUILD_SWAP=""
MEM_TOTAL_MB="$(awk '/^MemTotal:/{print int($2/1024)}' /proc/meminfo 2>/dev/null)"
SWAP_TOTAL_MB="$(awk '/^SwapTotal:/{print int($2/1024)}' /proc/meminfo 2>/dev/null)"

# (1) keep build temp off the RAM-backed tmpfs.
# ONE tool must not inherit this: equivs-build (driven by mk-build-deps) leaves
# its dummy .deb in $TMPDIR while mk-build-deps then `dpkg --unpack`s a bare
# filename from the cwd -> "cannot access archive". Hence every source build
# below calls `env -u TMPDIR mk-build-deps`; its own tree still lands on disk.
if [ "$(findmnt -nro FSTYPE /tmp 2>/dev/null)" = tmpfs ]; then
	export TMPDIR=/var/tmp
	log "/tmp is a RAM tmpfs: building in TMPDIR=/var/tmp (disk) to avoid ENOSPC + RAM theft"
fi

if [ "${MEM_TOTAL_MB:-9999}" -lt 1536 ]; then
	# (3) very small boards: fewer parallel rustc so the dependency phase fits
	if [ "${MEM_TOTAL_MB:-9999}" -lt 900 ]; then
		export CARGO_BUILD_JOBS=2
		log "Very low RAM (${MEM_TOTAL_MB}MB): capping Rust parallelism (CARGO_BUILD_JOBS=2)"
	fi
	# (2) swapfile backstop for the final single-crate rustc peak
	if [ "${SWAP_TOTAL_MB:-0}" -lt 2048 ] && ! grep -q '^/swapfile ' /proc/swaps; then
		FREE_MB="$(df -Pm / | awk 'NR==2{print $4}')"
		if [ "${FREE_MB:-0}" -ge 3072 ]; then
			log "Low RAM (${MEM_TOTAL_MB}MB): adding a temporary 2G build swapfile (backstop for the final pleezer rustc; removed at end of install)"
			if fallocate -l 2G /swapfile 2>/dev/null \
					|| dd if=/dev/zero of=/swapfile bs=1M count=2048 status=none 2>/dev/null; then
				chmod 600 /swapfile
				mkswap /swapfile >/dev/null 2>&1
				if swapon /swapfile 2>/dev/null; then
					NOPI_BUILD_SWAP=/swapfile
				else
					warn "swapon /swapfile failed (continuing; zram should still cover it)"; rm -f /swapfile
				fi
			else
				warn "could not create /swapfile (continuing; zram should still cover it)"
			fi
		else
			warn "low RAM but <3G free on /; skipping build swapfile (zram should still cover it)"
		fi
	fi
fi

PLAYER_USER="$(awk -F: '$3==1000{print $1; exit}' /etc/passwd || true)"
[ -n "$PLAYER_USER" ] || die "No UID 1000 user found. Create your normal login user first."
log "Player user (UID 1000): $PLAYER_USER"

[ -f "$SQLDB_SCHEMA" ] || die "Missing DB schema: $SQLDB_SCHEMA"

#----------------------------------------------------------------------------#
# Phase 0b - Build the web app (gulp) if needed
#----------------------------------------------------------------------------#
# build/dist/ is gulp output, not committed: build it here when missing (fresh
# clone) or on --update. The gulp 4 pipeline needs Node 18 specifically - pin the
# validated 18.20.8 via nvm from nodejs.org (the Node 18 apt repos are EOL). nvm
# + node + node_modules are kept so --update rebuilds stay fast (npm ci).
if [ ! -d "$DIST_DIR/var/www" ] || [ "$UPDATE" = 1 ]; then
	[ "$UPDATE" = 1 ] && log "Phase 0b: --update - rebuilding the web app" \
	                  || log "Phase 0b: build/dist absent - building the web app"
	NODE_VER=18.20.8
	export NVM_DIR=/root/.nvm
	apt-get update >/dev/null 2>&1 || true
	apt-get install -y --no-install-recommends curl ca-certificates >/dev/null 2>&1 \
		|| die "Phase 0b: could not install curl (needed to fetch nvm/Node)"
	if [ ! -s "$NVM_DIR/nvm.sh" ]; then
		curl -o- https://raw.githubusercontent.com/nvm-sh/nvm/v0.40.1/install.sh | bash >/dev/null 2>&1 \
			|| die "Phase 0b: nvm install failed (no internet?)"
	fi
	# shellcheck disable=SC1091
	. "$NVM_DIR/nvm.sh"
	nvm install "$NODE_VER" >/dev/null 2>&1 && nvm use "$NODE_VER" >/dev/null 2>&1 \
		|| die "Phase 0b: Node $NODE_VER install failed"
	log "Phase 0b: Node $(node -v) - npm ci + gulp build + deploy (this takes a few minutes)"
	# `gulp deploy` only COPIES bundles from app.dest, `gulp build` is what makes
	# them: deploy alone on a fresh clone ships an unstyled WebUI. Build THEN deploy.
	( cd "$REPO_DIR" \
		&& npm ci \
		&& npx gulp build --all --force \
		&& npx gulp deploy --test --all --force ) >/dev/null 2>&1 \
		|| die "Phase 0b: web app build failed (run 'cd $REPO_DIR && npm ci && npx gulp build --all --force && npx gulp deploy --test --all --force' to see the error)"
	# Sanity: the minified bundles must exist, else the UI renders unstyled.
	[ -f "$DIST_DIR/var/www/css/styles.min.css" ] \
		|| die "Phase 0b: CSS bundle missing after build (gulp build step did not run?)"
	log "Phase 0b: web app built -> $DIST_DIR"
fi

[ -d "$DIST_DIR/var/www" ] || die "Missing build output: $DIST_DIR/var/www (build failed)"

#----------------------------------------------------------------------------#
# Phase 1 - APT packages
#----------------------------------------------------------------------------#

log "Phase 1: installing packages"

export DEBIAN_FRONTEND=noninteractive

CORE_PKGS=(
	# sudo: moOde's whole privilege model runs on it (sysCmd() = `sudo ...`). A
	# minimal Debian with a root password set does NOT ship it.
	sudo
	nginx
	php-fpm php-cli php-sqlite3 php-curl php-gd php-xml php-zip php-mbstring php-yaml
	mpd mpc
	alsa-utils
	# ALSA DSP plugins (Configure > Audio): libasound2-plugin-equal = the `type
	# equal` plugin behind the Graphic EQ; caps = the LADSPA pack (Eq10, EqFA4p) -
	# the 12-band Parametric EQ (EqFA12p, id 2611) is a moOde extension absent from
	# stock caps and needs caps=*moode1 (Phase 1e); bs2b-ladspa = Crossfeed.
	libasound2-plugin-equal caps bs2b-ladspa
	sqlite3
	avahi-daemon
	python3 python3-pip
	udisks2
	rsync curl wget
	triggerhappy
	# Remote NAS music sources (lib-config -> nasSourceMount): CIFS/SMB and NFS
	# client mounts, SMB protocol-version probing (nmap) and share browsing.
	cifs-utils nfs-common smbclient nmap
	# Name resolution for addressing NAS hosts by name (moOde's nsswitch.conf
	# adds mdns4/wins): mDNS (.local) and NetBIOS/WINS modules + winbind daemon.
	libnss-mdns winbind libnss-winbind
	# nmblookup/testparm for scanning SMB hosts (moodeutl -c, lib-config browse)
	samba-common-bin
	# Track metadata extraction used by the Library (inc/music-library.php)
	mediainfo
	# Network configuration backend: moOde manages Ethernet/WiFi/Hotspot entirely
	# through NetworkManager (nmcli + .nmconnection keyfiles in inc/network.php).
	network-manager
	# WiFi tooling moOde shells out to: iw (scan), wpa_passphrase, net-tools.
	# dnsmasq-base is only Recommended by network-manager but REQUIRED by its
	# Hotspot (ipv4.method=shared): without it the WiFi-fallback AP associates and
	# hands out no IP. wireless-regdb backs `iw reg set <country>`.
	iw wpasupplicant net-tools dnsmasq-base wireless-regdb
	# Format/fsck for USB/SATA music drives (mkfs.vfat/exfat; moOde's "Format"
	# makes ext4). The FUSE drivers to MOUNT exfat/ntfs are added conditionally
	# below, only for filesystems the running kernel can't mount itself.
	dosfstools exfatprogs
	# USB auto-mount: moOde uses udisks-glue, which needs udisks1 (gone from
	# Trixie). udevil ships `devmon`, a drop-in automount daemon that mounts
	# removable drives to /media/<LABEL> and runs hooks on mount/unmount.
	udevil
	# moOde scripts call `python` (not python3), e.g. util/sysinfo.sh
	python-is-python3
	# HDMI-CEC (Configure > Peripherals): cec-ctl, used by peripheral.php
	# cecControl() and watchdog.sh. Ungated - a no-op without a /dev/cec adapter.
	v4l-utils
	# --- Parity with the Pi moode-player package deps: stock Debian, no patch ---
	# Media/metadata CLI tools moOde shells out to: sox (CamillaDSP resample path,
	# inc/cdsp.php), inotifywait (inotify-tools; worker file watches) + ffmpeg/flac/
	# id3v2 used across coverart, metadata and format handling.
	ffmpeg flac sox id3v2 inotify-tools
	# CJK + extra fonts so non-Latin track/station names render in the WebUI and the
	# Peppy display instead of tofu boxes (fonts-lato already arrives as a dep).
	fonts-arphic-ukai fonts-arphic-uming fonts-ipafont-gothic fonts-ipafont-mincho fonts-unfonts-core
	# Sharing servers + Windows discovery + web terminal. Installed like the Pi but
	# left DISABLED (Phase 7): the worker starts them from the UI settings.
	samba nfs-kernel-server wsdd2 shellinabox
	# Misc tools/libs moOde and its scripts use: jq (JSON), dos2unix (playlist
	# import), sysstat (system stats), tree, python3-musicpd (MPD client lib),
	# python3-setuptools, lsb-release, xfsprogs (mount/fsck XFS music drives),
	# avahi-utils (avahi CLI).
	jq dos2unix sysstat tree python3-musicpd python3-setuptools lsb-release xfsprogs avahi-utils
	# zip/unzip: the on-demand renderer plugins ship as zips and moOde's own
	# plugin-updater.sh unzips them on EVERY arch. Installing them from the x86-only
	# mirror phase left arm64 without unzip, and the AirPlay install then failed with
	# a generic "Install failed, update cancelled": the updater ignores unzip's exit
	# code, deletes the archive anyway, and update/install.sh is simply not there
	# (127). Measured on the OPi3 2026-09-01.
	zip unzip
)

OPT_PKGS=()
# expect drives the bluetoothctl sessions in blu-control.sh (SCAN/PAIR/CONNECT),
# so it is named here too and not left to Phase 5d. python3-dbus + python3-gi:
# bt-pairing-agent.py imports both; the Pi image lists only python3-dbus because
# RaspiOS already carries the GObject bindings, a minimal Debian does not.
[ "$INSTALL_BLUETOOTH"   = 1 ] && OPT_PKGS+=(bluez bluez-alsa-utils bluez-tools expect python3-dbus python3-gi)
[ "$INSTALL_UPNP"        = 1 ] && OPT_PKGS+=(upmpdcli upmpdcli-tidal upmpdcli-qobuz)
[ "$INSTALL_DLNA"        = 1 ] && OPT_PKGS+=(minidlna)
[ "$INSTALL_SQUEEZELITE" = 1 ] && OPT_PKGS+=(squeezelite)
# log2ram spares a flash rootfs (SD/eMMC) from /var/log wear; pointless on
# SSD/NVMe. Debian's package ships exactly the units the worker toggles, so the
# 'log2ram' job and the Configure > System control work unchanged. 'auto' =
# install only when / sits on an mmcblk device.
if [ "$INSTALL_LOG2RAM" = auto ]; then
	case "$(findmnt -no SOURCE / 2>/dev/null)" in
		/dev/mmcblk*) INSTALL_LOG2RAM=1 ;;
		*)            INSTALL_LOG2RAM=0 ;;
	esac
fi
[ "$INSTALL_LOG2RAM" = 1 ] && OPT_PKGS+=(log2ram)

# Keep moOde's own config files on conffile conflicts and never prompt (some
# moOde configs deployed here, e.g. /etc/alsa/conf.d/20-bluealsa.conf, are also
# shipped by Debian packages such as bluez-alsa-utils -> dpkg would otherwise
# stop at an interactive conffile prompt and fail under -y).
APT_INSTALL="apt-get install -y -o Dpkg::Options::=--force-confdef -o Dpkg::Options::=--force-confold"

# The repo block below needs curl + gnupg, but runs BEFORE the CORE_PKGS install
# and a minimal Debian ships neither: without this the key dearmor fails and UPnP
# is silently skipped.
if ! command -v curl >/dev/null 2>&1 || ! command -v gpg >/dev/null 2>&1; then
	apt-get update
	apt-get install -y ca-certificates curl gnupg
fi

# upmpdcli and its libupnpp/libnpupnp deps are not in Debian; add the upstream
# lesbonscomptes repo, suite = the running codename. Skipped entirely once
# upmpdcli is installed (the .sources persists), so --update wastes no network.
if [ "$INSTALL_UPNP" = 1 ] && ! dpkg-query -W -f='${Status}' upmpdcli 2>/dev/null | grep -q ' installed'; then
	SUITE="$(. /etc/os-release 2>/dev/null; echo "${VERSION_CODENAME:-trixie}")"
	if curl -fsSL https://www.lesbonscomptes.com/pages/lesbonscomptes.gpg \
		| gpg --batch --yes --dearmor -o /usr/share/keyrings/lesbonscomptes.gpg 2>/dev/null
	then
		# The DOCUMENTED way (lesbonscomptes/pages/signatures.html): their ready-made
		# deb822 .sources + a NORMAL `apt-get update`. Do NOT go back to a
		# hand-crafted .sources + isolated `apt-get update -o Dir::Etc::sourceparts=-`:
		# that left `apt-cache policy` blind to the candidate on every box (UPnP
		# skipped) even though the index downloaded fine.
		rm -f /etc/apt/sources.list.d/upmpdcli.list   # drop any stale .list from old runs
		# lesbonscomptes ships the .sources as upmpdcli-<suite> (debian pool: amd64/i386)
		# and upmpdcli-r<suite> (raspbian pool: arm64/armhf). Pick by arch.
		case "$(dpkg --print-architecture)" in
			arm64|armhf) _rpfx=r; UPNP_POOL=raspbian ;;
			*)           _rpfx=;  UPNP_POOL=debian ;;
		esac
		if ! curl -fsSL "https://www.lesbonscomptes.com/upmpdcli/pages/upmpdcli-${_rpfx}${SUITE}.sources" \
				-o /etc/apt/sources.list.d/upmpdcli.sources 2>/dev/null; then
			# Fallback: generate the same content (http, like the official file) if the
			# ready-made .sources isn't reachable for this suite.
			printf 'Types: deb\nURIs: http://www.lesbonscomptes.com/upmpdcli/downloads/%s/\nSuites: %s\nComponents: main\nSigned-By: /usr/share/keyrings/lesbonscomptes.gpg\n' \
				"$UPNP_POOL" "$SUITE" > /etc/apt/sources.list.d/upmpdcli.sources
		fi
		log "Added upmpdcli apt repo ($UPNP_POOL/$SUITE)"
		# NORMAL full update (NOT isolated). `|| true` so a transient blip on any repo
		# doesn't abort under set -e; Acquire::Retries re-tries failed index downloads.
		apt-get update -o Acquire::Retries=3 || true
		# Queue only the upmpdcli* packages with an installable candidate for this
		# arch, so the later bulk `apt install` never aborts under set -e.
		#
		# MUST NOT pipe `apt-cache policy | grep -q`: grep exits on its first match,
		# apt-cache gets SIGPIPE and exits 141, and pipefail makes the whole pipeline
		# 141 -> the `if` is false EVEN THOUGH grep matched. That, not the repo/key/
		# locale, was the real cause of "upmpdcli has no candidate" on all 3 arches.
		# Capture to a var and match with a pipe-free bash regex instead.
		_has_cand() { local _p; _p="$(apt-cache policy "$1" 2>/dev/null || true)"; [[ "$_p" =~ Candidate:\ [0-9] ]]; }
		_keep=(); for p in "${OPT_PKGS[@]}"; do case "$p" in upmpdcli|upmpdcli-tidal|upmpdcli-qobuz) ;; *) _keep+=("$p");; esac; done; OPT_PKGS=("${_keep[@]}")
		if _has_cand upmpdcli; then
			_upnp=()
			for p in upmpdcli upmpdcli-tidal upmpdcli-qobuz; do
				if _has_cand "$p"; then
					_upnp+=("$p")
				else
					warn "UPnP: '$p' not offered by the upmpdcli repo for $(dpkg --print-architecture); skipping just that package"
				fi
			done
			OPT_PKGS+=("${_upnp[@]}")
			log "UPnP (upmpdcli): installing ${_upnp[*]}"
		else
			warn "upmpdcli has no candidate for $(dpkg --print-architecture); UPnP skipped"
			echo "---- apt-cache policy upmpdcli ----"; apt-cache policy upmpdcli 2>&1; echo "-----------------------------------"
			rm -f /etc/apt/sources.list.d/upmpdcli.sources
		fi
	else
		warn "upmpdcli repo setup failed; UPnP will be skipped"
		_keep=(); for p in "${OPT_PKGS[@]}"; do case "$p" in upmpdcli|upmpdcli-tidal|upmpdcli-qobuz) ;; *) _keep+=("$p");; esac; done; OPT_PKGS=("${_keep[@]}")
	fi
fi

apt-get update
# FUSE filesystem drivers, installed ONLY for what the running kernel can't
# mount itself. Detection must check /proc/filesystems AND modules.builtin: a
# built-in filesystem has no .ko, so `modprobe -qn` reports it ABSENT. moOde
# mounts via `mount -t <blkid-fstype>`, and the kernel driver registers as
# `ntfs3`, which does NOT satisfy `mount -t ntfs` - hence keying ntfs on plain
# `ntfs` being present (the ntfs-3g mount.ntfs helper is what serves it).
fs_supported() {
	grep -qw "$1" /proc/filesystems 2>/dev/null && return 0
	modprobe -qn "$1" 2>/dev/null && return 0
	local b="/lib/modules/$(uname -r)/modules.builtin"
	[ -f "$b" ] && grep -q "/$1\.ko" "$b" && return 0
	return 1
}
FS_PKGS=()
fs_supported exfat || FS_PKGS+=(exfat-fuse)
fs_supported ntfs  || FS_PKGS+=(ntfs-3g)
[ ${#FS_PKGS[@]} -gt 0 ] && log "Kernel lacks FS driver(s); adding userspace: ${FS_PKGS[*]}" \
	|| log "Kernel provides vfat/exfat; no userspace FS driver needed (ntfs via ntfs-3g if present)"

# Drop already-held packages from this list. We build+hold mpd/caps/squeezelite
# (Phases 1e/1f/1i); naming them on a re-run is fatal - `apt-get install -y`
# aborts with "Held packages were changed..." as soon as the repo offers what apt
# deems newer (an arm64 binNMU caps 0.9.26-1+b1 outranks our 0.9.26-1moode1).
# --allow-change-held-packages is NOT the fix: it would swap the moode-patched
# build for stock, losing EqFA12p/selective-resample. On a fresh install nothing
# is held, so the stock fallbacks install and the build phases upgrade+hold them.
_held="$(apt-mark showhold 2>/dev/null || true)"
_inst=()
for p in "${CORE_PKGS[@]}" ${OPT_PKGS[@]+"${OPT_PKGS[@]}"} ${FS_PKGS[@]+"${FS_PKGS[@]}"}; do
	_skip=
	for h in $_held; do [ "$h" = "$p" ] && { _skip=1; break; }; done
	[ -n "$_skip" ] && { log "Skipping held package in bulk install: $p (managed by its build phase)"; continue; }
	_inst+=("$p")
done
$APT_INSTALL "${_inst[@]}"

# Detect the actual php-fpm version/socket so the nginx config matches Debian's
# packaged PHP (the shipped configs assume php8.4).
PHP_VER="$(ls /etc/php/ 2>/dev/null | sort -V | tail -1 || true)"
[ -n "$PHP_VER" ] || die "php-fpm not found after install"
PHP_SOCK="/run/php/php${PHP_VER}-fpm.sock"
log "PHP-FPM version: $PHP_VER (socket $PHP_SOCK)"

# --- Build-phase version tracking -------------------------------------------
# The components built below (Phases 1b-1i) are apt-held or live outside Debian,
# so their build phase is their ONLY upgrade path: the guards must rebuild on a
# pinned-version BUMP, not merely when the artifact is absent.
#   - dpkg packages / binaries with --version: compared directly (dpkg_ver_is).
#   - versionless git-pinned artifacts: a stamp under $NOPI_BUILT_DIR records the
#     ref built from. Adopt-on-legacy - an artifact present WITHOUT a stamp is
#     recorded as current, so existing boxes don't rebuild on their first --update.
#   - unpinned sources (alsacap git-main): presence only; a bump needs a manual rm.
#   - peppy-alsa tracks git master: guard = the resolved commit, and a MISSING
#     stamp means rebuild, not adopt (Phase 1g).
NOPI_BUILT_DIR=/var/lib/moode-nopi/built
dpkg_ver_is() { [ "$(dpkg-query -W -f='${Version}' "$1" 2>/dev/null)" = "$2" ]; }
# nopi_need_build <name> <pinned> <present:0|1> -> rc 0 = build needed, 1 = up to date
nopi_need_build() {
	local name="$1" pinned="$2" present="$3" f="$NOPI_BUILT_DIR/$1"
	[ "$present" = 1 ] || return 0
	if [ ! -f "$f" ]; then mkdir -p "$NOPI_BUILT_DIR"; printf '%s\n' "$pinned" > "$f"; return 1; fi
	[ "$(cat "$f" 2>/dev/null)" = "$pinned" ] && return 1 || return 0
}
nopi_mark_built() { mkdir -p "$NOPI_BUILT_DIR"; printf '%s\n' "$2" > "$NOPI_BUILT_DIR/$1"; }

#----------------------------------------------------------------------------#
# Phase 1b - Custom helper binaries
#----------------------------------------------------------------------------#
# A couple of helpers moOde ships as custom-built packages (moode-player/
# pkgbuild) are not in Debian. Rather than commit prebuilt binaries to the tree
# (they would rot across kernels/libs) or rely on flaky on-target Rust builds,
# build these two tiny C programs from their pinned upstream sources at install
# time. Idempotent: skipped when the binaries already exist.
#   alsacap  -> ALSA format probe (moodeutl -f, sysinfo "Supported formats")
#   trx 0.6  -> Multiroom sender/receiver, installed as trx-tx / trx-rx

log "Phase 1b: custom helper binaries"

# alsacap tracks git main (unpinned) -> presence-only. trx is pinned to tag 0.6
# -> stamped, so a future re-pin triggers a rebuild (adopt-on-legacy: existing
# boxes record the current ref without rebuilding).
TRX_VER="0.6"
_need_alsacap=0; command -v alsacap >/dev/null 2>&1 || _need_alsacap=1
_need_trx=0
if nopi_need_build trx "$TRX_VER" "$([ -x /usr/bin/trx-tx ] && echo 1 || echo 0)"; then _need_trx=1; fi
if [ "$_need_alsacap" = 1 ] || [ "$_need_trx" = 1 ]; then
	$APT_INSTALL build-essential autoconf automake libtool pkg-config git \
		libasound2-dev libopus-dev libortp-dev
	HLP_WRK="$(mktemp -d)"

	# alsacap (bubbapizza/alsacap, autotools)
	if [ "$_need_alsacap" = 1 ]; then
		if git clone -q https://github.com/bubbapizza/alsacap.git "$HLP_WRK/alsacap" \
			&& ( cd "$HLP_WRK/alsacap" && ./bootstrap && ./configure && make ) >/dev/null 2>&1; then
			install -m 755 "$HLP_WRK/alsacap/src/alsacap" /usr/bin/alsacap
			log "Built alsacap"
		else
			warn "alsacap build failed (audio format detection will be degraded)"
		fi
	fi

	# trx 0.6 (bitkeeper/trx). Newer Debian oRTP needs libbctoolbox linked too.
	if [ "$_need_trx" = 1 ]; then
		if git clone -q -b "$TRX_VER" https://github.com/bitkeeper/trx.git "$HLP_WRK/trx" \
			&& ( cd "$HLP_WRK/trx" && make LDLIBS_ORTP="-lortp -lbctoolbox" ) >/dev/null 2>&1; then
			install -m 755 "$HLP_WRK/trx/tx" /usr/bin/trx-tx
			install -m 755 "$HLP_WRK/trx/rx" /usr/bin/trx-rx
			nopi_mark_built trx "$TRX_VER"
			log "Built trx (multiroom: trx-tx / trx-rx)"
		else
			warn "trx build failed (Multiroom will be unavailable)"
		fi
	fi

	rm -rf "$HLP_WRK"
else
	log "Helper binaries already present (alsacap, trx-tx)"
fi

#----------------------------------------------------------------------------#
# Phase 1c - CamillaDSP (DSP / parametric EQ)
#----------------------------------------------------------------------------#
# Three custom (non-Debian) pieces: the camilladsp Rust engine, the alsa-cdsp
# ALSA plugin, and mpd2cdspvolume. etc/alsa/conf.d/camilladsp.conf routes through
# 'type cdsp' -> /usr/local/bin/camilladsp, so engine + plugin must both exist
# for the feature to open (default camilladsp='off', so playback works without).
# Versions pinned to moOde's image manifest (imgbuild stage3 ...01-packages).

log "Phase 1c: CamillaDSP (DSP / parametric EQ)"

CDSP_VER="4.1.3"
case "$(dpkg --print-architecture)" in
	amd64) CDSP_ASSET="camilladsp-linux-amd64.tar.gz" ;;
	arm64) CDSP_ASSET="camilladsp-linux-aarch64.tar.gz" ;;
	armhf) CDSP_ASSET="camilladsp-linux-armv7.tar.gz" ;;   # 32-bit ARM SBCs (e.g. Allwinner H3, Cortex-A7)
	*)     CDSP_ASSET="" ;;
esac

# 1) camilladsp engine (release binary, pinned to moOde's pkgbuild version)
_cdsp_v="$([ -x /usr/local/bin/camilladsp ] && /usr/local/bin/camilladsp --version 2>/dev/null || true)"
if [[ "$_cdsp_v" != *"$CDSP_VER"* ]] && [ -n "$CDSP_ASSET" ]; then
	CDSP_TMP="$(mktemp -d)"
	if curl -fsSL "https://github.com/HEnquist/camilladsp/releases/download/v${CDSP_VER}/${CDSP_ASSET}" \
		| tar -xz -C "$CDSP_TMP" camilladsp 2>/dev/null && [ -f "$CDSP_TMP/camilladsp" ]; then
		install -m 755 "$CDSP_TMP/camilladsp" /usr/local/bin/camilladsp
		log "Installed camilladsp ${CDSP_VER} ($(dpkg --print-architecture))"
	else
		warn "camilladsp download failed (DSP/EQ will be unavailable)"
	fi
	rm -rf "$CDSP_TMP"
fi

# 2) alsa-cdsp ALSA plugin. moOde uses bitkeeper/alsa_cdsp branch
#    fixes/bookworm_cargs_empty, plus its cdsp4_format_fix patch so the plugin
#    emits CamillaDSP v4 sample-format names (S16LE -> S16_LE, etc.). The patch
#    is a handful of literal string swaps, applied here with sed (no patch file).
CDSP_PLUGIN_DIR="$(pkg-config --variable=libdir alsa 2>/dev/null)/alsa-lib"
ACDSP_REF="fixes/bookworm_cargs_empty+fmtfix"
if nopi_need_build alsa-cdsp "$ACDSP_REF" "$([ -f "$CDSP_PLUGIN_DIR/libasound_module_pcm_cdsp.so" ] && echo 1 || echo 0)"; then
	$APT_INSTALL build-essential git pkg-config libasound2-dev
	CDSP_BLD="$(mktemp -d)"
	if git clone -q -b fixes/bookworm_cargs_empty \
			https://github.com/bitkeeper/alsa_cdsp.git "$CDSP_BLD/alsa_cdsp" \
		&& ( cd "$CDSP_BLD/alsa_cdsp" \
			&& sed -i -e 's/"S16LE"/"S16_LE"/'   -e 's/"S24LE3"/"S24_3_LE"/' \
				   -e 's/"S24LE"/"S24_4_RJ_LE"/' -e 's/"S32LE"/"S32_LE"/' \
				   -e 's/"FLOAT32LE"/"F32_LE"/'  -e 's/"FLOAT64LE"/"F64_LE"/' \
				   libasound_module_pcm_cdsp.c \
			&& make && make install ) >/dev/null 2>&1; then
		nopi_mark_built alsa-cdsp "$ACDSP_REF"
		log "Built alsa-cdsp ALSA plugin"
	else
		warn "alsa-cdsp build failed (CamillaDSP output will not open when enabled)"
	fi
	rm -rf "$CDSP_BLD"
fi

# 3) Python CamillaDSP stack + camillagui. All three are moOde noarch .debs
#    (Architecture: all - Python lib, static React build, Python backend), so the
#    Pi's own packages install as-is here: no npm build, no pip. matplotlib is
#    used at runtime by python3-camilladsp-plot but not declared by its .deb, so
#    apt it explicitly. camillagui lands in /opt/camillagui, worker-managed.
PYCDSP_VER="4.0.0-1moode1"; PYCDSPPLOT_VER="4.1.0-1moode1"; CAMILLAGUI_VER="4.1.0-1moode1"
if [ ! -d /opt/camillagui ] || ! dpkg_ver_is python3-camilladsp "$PYCDSP_VER" \
		|| ! dpkg_ver_is python3-camilladsp-plot "$PYCDSPPLOT_VER" || ! dpkg_ver_is camillagui "$CAMILLAGUI_VER"; then
	$APT_INSTALL python3-aiohttp python3-websocket python3-jsonschema python3-numpy \
		python3-yaml python3-mpd python3-matplotlib
	CG_TMP="$(mktemp -d)"
	CG_POOL="https://dl.cloudsmith.io/public/moodeaudio/m8y/deb/raspbian/pool/trixie/main"
	if curl -fsSL -o "$CG_TMP/1.deb" "$CG_POOL/p/py/python3-camilladsp_${PYCDSP_VER}/python3-camilladsp_${PYCDSP_VER}_all.deb" \
		&& curl -fsSL -o "$CG_TMP/2.deb" "$CG_POOL/p/py/python3-camilladsp-plot_${PYCDSPPLOT_VER}/python3-camilladsp-plot_${PYCDSPPLOT_VER}_all.deb" \
		&& curl -fsSL -o "$CG_TMP/3.deb" "$CG_POOL/c/ca/camillagui_${CAMILLAGUI_VER}/camillagui_${CAMILLAGUI_VER}_all.deb" \
		&& dpkg -i --force-confold "$CG_TMP/1.deb" "$CG_TMP/2.deb" "$CG_TMP/3.deb" >/dev/null 2>&1; then
		log "Installed CamillaDSP python stack + camillagui (moOde noarch .debs)"
	else
		warn "camillagui / python3-camilladsp .deb install failed (CDSP GUI / volume sync degraded)"
	fi
	rm -rf "$CG_TMP"
	# camillagui is worker-managed (cdsp.php enable/starts it per the UI toggle);
	# its Debian postinst may auto-enable it, so leave it disabled like the renderers.
	systemctl disable --now camillagui >/dev/null 2>&1 || true
fi

# 4) mpd2cdspvolume (optional MPD<->CamillaDSP volume sync; worker starts/stops the
#    service per cfg). Pure-Python: scripts + service unit + tmpfiles + config.
M2C_VER="2.0.0"
if nopi_need_build mpd2cdspvolume "$M2C_VER" "$([ -x /usr/local/bin/mpd2cdspvolume ] && echo 1 || echo 0)"; then
	M2C_TMP="$(mktemp -d)"
	if git clone -q -b "v${M2C_VER}" \
			https://github.com/bitkeeper/mpd2cdspvolume.git "$M2C_TMP/src"; then
		install -m 755 "$M2C_TMP/src/mpd2cdspvolume.py" /usr/local/bin/mpd2cdspvolume
		# config holds user settings (snd-config.php seds dynamic_range) - preserve on re-run
		[ -f /etc/mpd2cdspvolume.config ] || install -m 644 "$M2C_TMP/src/etc/mpd2cdspvolume.config" /etc/mpd2cdspvolume.config
		install -m 644 "$M2C_TMP/src/etc/mpd2cdspvolume.conf"   /usr/lib/tmpfiles.d/mpd2cdspvolume.conf
		install -m 644 "$M2C_TMP/src/etc/mpd2cdspvolume.service" /lib/systemd/system/mpd2cdspvolume.service
		nopi_mark_built mpd2cdspvolume "$M2C_VER"
		log "Deployed mpd2cdspvolume"
	else
		warn "mpd2cdspvolume clone failed (CamillaDSP volume sync unavailable)"
	fi
	rm -rf "$M2C_TMP"
fi

# Runtime state dir for CamillaDSP / mpd2cdspvolume (the service writes the
# volume statefile here; mpd needs to own it). /usr/share/camilladsp (configs,
# coeffs, templates) is deployed with the usr/ tree in Phase 2.
install -d -o mpd -g audio /var/lib/cdsp 2>/dev/null || install -d /var/lib/cdsp

#----------------------------------------------------------------------------#
# Phase 1d - Deezer Connect (pleezer)
#----------------------------------------------------------------------------#
# The Deezer renderer is the 'pleezer' binary, launched directly by
# inc/renderer.php (no systemd unit, nothing to disable). It ships no release
# binary and is not on crates.io -> build from its pinned git tag. Needs Rust
# 1.85 + edition 2024, which Trixie's cargo provides, so no toolchain juggling.

log "Phase 1d: Deezer renderer (pleezer)"

PLEEZER_VER="0.19.1"
_plz_v="$([ -x /usr/local/bin/pleezer ] && /usr/local/bin/pleezer --version 2>/dev/null || true)"
if [[ "$_plz_v" != *"$PLEEZER_VER"* ]]; then
	$APT_INSTALL cargo git pkg-config libasound2-dev libssl-dev
	PLZ_BLD="$(mktemp -d)"
	if git clone -q -b "v${PLEEZER_VER}" \
			https://github.com/roderickvd/pleezer.git "$PLZ_BLD/pleezer" \
		&& ( cd "$PLZ_BLD/pleezer" && cargo build --release --locked ) >/dev/null 2>&1 \
		&& [ -f "$PLZ_BLD/pleezer/target/release/pleezer" ]; then
		install -m 755 "$PLZ_BLD/pleezer/target/release/pleezer" /usr/local/bin/pleezer
		log "Built pleezer ${PLEEZER_VER} (Deezer Connect)"
	else
		warn "pleezer build failed (Deezer Connect will be unavailable)"
	fi
	rm -rf "$PLZ_BLD"
fi

# cargo-deb for the on-demand librespot build. moOde's build.sh would `cargo
# install cargo-deb` and get 3.7.0, which does not compile on Debian's Rust 1.85
# (`let` expressions unstable). Pre-install 2.12.1 (MSRV 1.71) on PATH so
# rbl_check_cargo finds it and skips that. Not arch-gated - arm64 hits it too.
CARGODEB_VER="2.12.1"
_cdeb_v="$(command -v cargo-deb >/dev/null 2>&1 && cargo-deb --version 2>/dev/null || true)"
if [[ "$_cdeb_v" != *"$CARGODEB_VER"* ]]; then
	$APT_INSTALL cargo git pkg-config libssl-dev
	cargo install --root /usr/local --locked --force --version "$CARGODEB_VER" cargo-deb >/dev/null 2>&1 \
		&& log "Installed cargo-deb $CARGODEB_VER (for on-demand librespot build)" \
		|| warn "cargo-deb install failed (Spotify on-demand build may fail)"
fi

#----------------------------------------------------------------------------#
# Phase 1e - caps with 12-band parametric EQ (eqfa12p)
#----------------------------------------------------------------------------#
# The Parametric EQ uses LADSPA EqFA12p (id 2611), a 12-band extension of CAPS'
# EqFA4p absent from Debian's stock `caps`: without it the eqfa12p ALSA device
# fails to load and MPD errors `_audioout: No such file or directory`. Rebuild
# Debian's caps source with moOde's pkgbuild patch (their recipe, minus the
# Pi-only cloudsmith plumbing) to reach the Pi's held caps=0.9.26-1moode1. On
# failure stock caps stays, so only the Parametric EQ is lost. Not arch-gated.

log "Phase 1e: caps with 12-band parametric EQ (eqfa12p)"

CAPS_MOODE_VER="0.9.26-1moode1"
if ! dpkg_ver_is caps "$CAPS_MOODE_VER"; then
	apt-mark unhold caps >/dev/null 2>&1 || true   # let apt re-install over a held older build on a version bump
	# debhelper is a declared build-dep and is NOT pulled in by build-essential/
	# devscripts: absent on a fresh Armbian arm64, the build aborted at
	# `dpkg-checkbuilddeps` and left stock caps (no EqFA12p). List it explicitly.
	$APT_INSTALL build-essential dpkg-dev debhelper devscripts fakeroot ladspa-sdk quilt
	CAPS_BLD="$(mktemp -d)"
	CAPS_PATCH_URL="https://raw.githubusercontent.com/moode-player/pkgbuild/main/packages/caps/caps_12band_eqp.patch"
	CAPS_DSC_URL="http://deb.debian.org/debian/pool/main/c/caps/caps_0.9.26-1.dsc"
	if ( cd "$CAPS_BLD" \
			&& dget -qd -u "$CAPS_DSC_URL" \
			&& dpkg-source -x caps_0.9.26-1.dsc \
			&& wget -q -O caps_12band_eqp.patch "$CAPS_PATCH_URL" \
			&& cd caps-0.9.26 \
			&& patch -p1 < ../caps_12band_eqp.patch \
			&& DEBEMAIL="moode@moodeaudio.org" DEBFULLNAME="moOde" \
				dch -b -v "$CAPS_MOODE_VER" -D unstable "Add 12-band eqfa12p parametric EQ (moOde patch)" \
			&& dpkg-buildpackage -b -us -uc ) > "$REPO_DIR/build-caps.log" 2>&1 \
		&& CAPS_DEB="$(ls "$CAPS_BLD"/caps_${CAPS_MOODE_VER}_*.deb 2>/dev/null | head -1)" \
		&& [ -n "$CAPS_DEB" ] \
		&& apt-get install -y --allow-downgrades "$CAPS_DEB" >/dev/null 2>&1; then
		apt-mark hold caps >/dev/null 2>&1 || true
		log "Built caps $CAPS_MOODE_VER (12-band parametric EQ)"
	else
		warn "caps moode build failed (Parametric EQ unavailable; Graphic EQ + crossfeed still work; see $REPO_DIR/build-caps.log)"
	fi
	rm -rf "$CAPS_BLD"
fi

#----------------------------------------------------------------------------#
# Phase 1f - mpd with moOde's selective-resample patch
#----------------------------------------------------------------------------#
# moOde patches MPD for "Selective resampling". Its generated mpd.conf emits
# `selective_resample_mode "N"` (N != 0), which STOCK mpd rejects as unrecognized
# and FAILS TO START -> the WebUI shows "Socket open failed (1001)". Debian
# source packages are arch-independent, so add moOde's cloudsmith SOURCE repo
# (deb-src ONLY - their binaries are arm) and build the moode1 source here, then
# hold it like the Pi. A build failure only costs selective resampling: the basic
# SoX resampler works on stock mpd.

log "Phase 1f: mpd with moOde selective-resample patch"

MPD_MOODE_VER="0.24.13-1moode1"
if ! dpkg_ver_is mpd "$MPD_MOODE_VER"; then
	apt-mark unhold mpd >/dev/null 2>&1 || true   # let the new build replace a held older one
	# moOde's cloudsmith SOURCE repo (deb-src only; binaries there are arm-only).
	MOODE_KEYRING=/usr/share/keyrings/moodeaudio-m8y-archive-keyring.gpg
	[ -f "$MOODE_KEYRING" ] || curl -1sLf 'https://dl.cloudsmith.io/public/moodeaudio/m8y/gpg.key' \
		| gpg --batch --yes --dearmor -o "$MOODE_KEYRING" 2>/dev/null
	cat > /etc/apt/sources.list.d/moodeaudio-m8y-source.sources <<EOF
Types: deb-src
URIs: https://dl.cloudsmith.io/public/moodeaudio/m8y/deb/raspbian
Suites: trixie
Components: main
Signed-By: $MOODE_KEYRING
EOF
	apt-get update >/dev/null 2>&1 || true
	$APT_INSTALL build-essential dpkg-dev devscripts equivs fakeroot
	MPD_BLD="$(mktemp -d)"
	if ( cd "$MPD_BLD" \
			&& apt-get source "mpd=$MPD_MOODE_VER" \
			&& cd "mpd-${MPD_MOODE_VER%-*}" \
			&& env -u TMPDIR mk-build-deps --install --remove --tool "apt-get -y --no-install-recommends" \
			&& dpkg-buildpackage -b -us -uc ) > "$REPO_DIR/build-mpd.log" 2>&1 \
		&& MPD_DEB="$(ls "$MPD_BLD"/mpd_${MPD_MOODE_VER}_*.deb 2>/dev/null | head -1)" \
		&& [ -n "$MPD_DEB" ] \
		&& dpkg -i --force-confold "$MPD_DEB" >/dev/null 2>&1; then
		apt-mark hold mpd >/dev/null 2>&1 || true
		log "Built mpd $MPD_MOODE_VER (selective resample support)"
	else
		warn "mpd moode build failed (Selective resampling unavailable; stock mpd kept; see $REPO_DIR/build-mpd.log)"
	fi
	rm -rf "$MPD_BLD"
fi

#----------------------------------------------------------------------------#
# Phase 1g - peppyalsa ALSA plugin (Peppy Meter/Spectrum visualization)
#----------------------------------------------------------------------------#
# peppy.conf loads libpeppyalsa.so, a `type meter` scope teeing the PCM stream to
# a FIFO for the visualizer. Not in Debian; built from upstream master, the same
# source moOde's pkgbuild clones. Upstream now carries both deltas moOde used to
# patch in (the 64-bit/GCC 14 build fixes and the DoP level decode), so there is
# nothing left to patch.
#
# master has no version, so the guard is its resolved commit: rebuild when it
# moves, or when there is no stamp at all - a library from an earlier installer
# whose content we cannot vouch for. Deliberately NOT nopi_need_build's
# adopt-on-legacy, which only spares artifacts that are still known-correct.
PEPPY_LIB="/usr/lib/$(dpkg-architecture -qDEB_HOST_MULTIARCH 2>/dev/null)/libpeppyalsa.so"
PEPPY_URL="https://github.com/project-owner/peppyalsa.git"
PEPPY_STAMP="$NOPI_BUILT_DIR/peppy-alsa"
PEPPY_REF="$(git ls-remote "$PEPPY_URL" refs/heads/master 2>/dev/null | cut -f1)"
if [ "$INSTALL_LOCALDISPLAY" = 1 ]; then
	if [ -z "$PEPPY_REF" ] && [ -f "$PEPPY_LIB" ]; then
		log "Phase 1g: peppyalsa upstream unreachable, keeping the installed plugin"
	elif [ ! -f "$PEPPY_LIB" ] || [ "$(cat "$PEPPY_STAMP" 2>/dev/null)" != "$PEPPY_REF" ]; then
		log "Phase 1g: peppyalsa plugin (libpeppyalsa.so)"
		$APT_INSTALL build-essential autoconf automake libtool git libasound2-dev libfftw3-dev
		PEPPY_BLD="$(mktemp -d)"
		# Build with autotools directly - moOde's debian/rules produces no artifact here.
		if git clone -q --depth 1 -b master "$PEPPY_URL" "$PEPPY_BLD/peppyalsa" \
			&& ( cd "$PEPPY_BLD/peppyalsa" && autoreconf -fi && ./configure && make ) >/dev/null 2>&1 \
			&& install -m 644 "$PEPPY_BLD"/peppyalsa/.libs/libpeppyalsa.so.[0-9]*.[0-9]* "$PEPPY_LIB"; then
			nopi_mark_built peppy-alsa "$(git -C "$PEPPY_BLD/peppyalsa" rev-parse HEAD)"
			rm -f "$NOPI_BUILT_DIR/peppy-alsa-dop"
			log "Built libpeppyalsa.so ($(echo "$PEPPY_REF" | cut -c1-7)) -> $PEPPY_LIB"
		else
			warn "peppyalsa build failed; Peppy Meter/Spectrum will be unavailable"
		fi
		rm -rf "$PEPPY_BLD"
	fi
fi

#----------------------------------------------------------------------------#
# Phase 1h - ashuffle (Random/Shuffle advanced queue feature)
#----------------------------------------------------------------------------#
# The Random modes shell out to /usr/bin/ashuffle, which is not in Debian. moOde
# ships 3.14.9-1moode1 whose moode1 delta is packaging only, so build the stock
# upstream v3.14.9 tag with meson (--recursive: it vendors abseil + googletest).
if [ "$(ashuffle --version 2>/dev/null)" = 'ashuffle version: v3.14.9' ]; then
	log "Phase 1h: ashuffle v3.14.9 already installed"
else
	log "Phase 1h: ashuffle (Random/Shuffle)"
	$APT_INSTALL meson ninja-build cmake build-essential libmpdclient-dev git >/dev/null 2>&1
	ASHUF_BLD="$(mktemp -d)"
	if git clone --depth 1 --recursive --branch v3.14.9 \
			https://github.com/joshkunz/ashuffle.git "$ASHUF_BLD/src" >/dev/null 2>&1 \
		&& meson setup "$ASHUF_BLD/build" "$ASHUF_BLD/src" --buildtype=release >/dev/null 2>&1 \
		&& ninja -C "$ASHUF_BLD/build" ashuffle >/dev/null 2>&1; then
		install -m 755 "$ASHUF_BLD/build/ashuffle" /usr/bin/ashuffle
		log "Built ashuffle $(ashuffle --version 2>/dev/null) -> /usr/bin/ashuffle"
	else
		warn "ashuffle build failed; Random/Shuffle modes will be unavailable"
	fi
	rm -rf "$ASHUF_BLD"
fi

#----------------------------------------------------------------------------#
# Phase 1i - squeezelite with moOde's newer snapshot (LMS Power Script -S)
#----------------------------------------------------------------------------#
# cfg_sl OTHEROPTIONS passes `-S <script>` (the "rsmaftersl" LMS power feature).
# That option exists only in a newer upstream snapshot than Debian ships AND is
# compiled under `#if GPIO`, so stock squeezelite answers "Option error: -S" and
# the service fails to start. Build moOde's source from their cloudsmith deb-src
# (same path as mpd), keeping -DGPIO but DROPPING -DRPI: -DRPI only adds the `-G`
# direct-GPIO-pin relay and pulls libgpiod, and gpiod.h is itself `#if RPI`.
# A build failure only costs -S; stock squeezelite is kept.
if [ "$INSTALL_SQUEEZELITE" = 1 ]; then
	log "Phase 1i: squeezelite with moOde LMS Power Script (-S) support"
	SL_MOODE_VER="2.0.0-1541+git20250609.72e1fd8-1moode1"
	if ! dpkg_ver_is squeezelite "$SL_MOODE_VER"; then
		# Ensure moOde's cloudsmith SOURCE repo (deb-src only; same as Phase 1f -
		# re-asserted here in case the mpd block was skipped on a re-run).
		MOODE_KEYRING=/usr/share/keyrings/moodeaudio-m8y-archive-keyring.gpg
		[ -f "$MOODE_KEYRING" ] || curl -1sLf 'https://dl.cloudsmith.io/public/moodeaudio/m8y/gpg.key' \
			| gpg --batch --yes --dearmor -o "$MOODE_KEYRING" 2>/dev/null
		[ -f /etc/apt/sources.list.d/moodeaudio-m8y-source.sources ] || cat > /etc/apt/sources.list.d/moodeaudio-m8y-source.sources <<EOF
Types: deb-src
URIs: https://dl.cloudsmith.io/public/moodeaudio/m8y/deb/raspbian
Suites: trixie
Components: main
Signed-By: $MOODE_KEYRING
EOF
		apt-get update >/dev/null 2>&1 || true
		$APT_INSTALL build-essential dpkg-dev devscripts equivs fakeroot
		SL_BLD="$(mktemp -d)"
		if ( cd "$SL_BLD" \
				&& apt-get source "squeezelite=$SL_MOODE_VER" \
				&& cd "$(ls -d squeezelite-*/ | head -1)" \
				&& sed -i 's/ -DGPIO -DRPI/ -DGPIO/' debian/rules \
				&& env -u TMPDIR mk-build-deps --install --remove --tool "apt-get -y --no-install-recommends" \
				&& dpkg-buildpackage -b -us -uc ) >/dev/null 2>&1 \
			&& SL_DEB="$(ls "$SL_BLD"/squeezelite_${SL_MOODE_VER}_*.deb 2>/dev/null | head -1)" \
			&& [ -n "$SL_DEB" ] \
			&& dpkg -i --force-confold "$SL_DEB" >/dev/null 2>&1; then
			apt-mark hold squeezelite >/dev/null 2>&1 || true
			log "Built squeezelite $SL_MOODE_VER (LMS Power Script -S support)"
		else
			warn "squeezelite moode build failed (LMS power-resume -S unavailable; stock squeezelite kept)"
		fi
		rm -rf "$SL_BLD"
	fi
fi

#----------------------------------------------------------------------------#
# Phase 1j - alsa-lib with the PCM meter scope patches
#----------------------------------------------------------------------------#
# The `type meter` s16 scope - what libpeppyalsa (Phase 1g) taps - returns -EINVAL
# for every format it cannot convert to S16 (DSD, 3-byte packed, float), yet any
# other scope still reaches the buffer it then never allocated and assert()s on
# it: the application dies (MPD SIGABRTs mid-track on native DSD). Two patches:
# read silence instead of aborting, and recover a DSD level by bit density.
#
# Mirrors moOde's recipe (pkgbuild packages/alsa-lib/build.sh, PRs #24+#25): same
# patches, marker greps, `moode1` suffix and hold. Built the way that recipe
# settled on - upstream release tag from git plus the distro's own debian/ as a
# plain tarball. NOT a .dsc: fetching one by URL inherits that pool's signing
# key, which is what broke #24.
#
# Upstream as alsa-project/alsa-lib #516 and #517, but a merge is only step one of
# four (release, Debian packaging, base OS pickup) and Trixie keeps 1.2.14 for
# life. Drop this phase when a base OS ships a fixed alsa-lib. Any rework of
# #516/#517 must be replayed into BOTH the pkgbuild recipe and the patches here.

log "Phase 1j: alsa-lib with the PCM meter scope patches"

# Bumped BY HAND when the patch files change (as moOde does). Together with the
# distro's own version this is the whole rebuild trigger: an --update on an
# unchanged Debian rebuilds nothing.
ALSA_MOODE_REV="moode1"
ALSA_PATCH_TESTED="1.2.16.1"
ALSA_GIT="https://github.com/alsa-project/alsa-lib.git"

# Which version to rebuild. `apt-cache madison` (repository versions only), NOT
# `apt-cache policy`: once our build is installed policy reports OUR package as
# the candidate, so the target would grow a second suffix on every run.
#
# madison lists every configured suite, highest first, and we follow that: on
# Armbian Trixie backports is part of the normal configuration, so its 1.2.16.1
# is a legitimate target (and the patches were written against it).
#
# One suite must NOT win: moOde's cloudsmith publishes an arm64
# `1.2.14-1+rpt1moode1`, and rebuilding THAT stacks a second moode suffix every
# run. Skip any version already carrying our own marker.
_alsa_arch="$(dpkg --print-architecture)"
ALSA_DISTRO_VER="$(LC_ALL=C apt-cache madison libasound2t64 2>/dev/null | awk -F'|' -v a="$_alsa_arch" '
	$3 !~ (a " Packages$") { next }
	{ gsub(/ /, "", $2) }
	$2 ~ /moode/           { next }
	{ print $2; exit }' || true)"
ALSA_TARGET_VER="${ALSA_DISTRO_VER}${ALSA_MOODE_REV}"

# The hand-built /opt override (a libasound in its own prefix forced on MPD via an
# LD_LIBRARY_PATH drop-in) was the pre-packaging way to run these patches. Once
# the .deb is in it SHADOWS the package, so retire it - but only once the packaged
# library is really in place: on a box whose build failed, removing it restores
# the SIGABRT. Deleting the tree under a running MPD is safe (the mapping holds
# the inode).
#
# Detection is by CONTENT, never by name: a hand-laid override can sit in any
# unit under any name. A drop-in qualifies when the path it forces really holds a
# libasound (or no longer exists - a half-done cleanup), a /opt directory when it
# really holds one. Name matching would leave a renamed copy silently in charge,
# which is the exact failure this check exists for. Every removal is logged.
alsa_retire_dsd_override() {
	local removed=0 f d p hit
	# 1. unit drop-ins forcing a service onto a libasound built under /opt
	while IFS= read -r f; do
		[ -n "$f" ] || continue
		hit=0
		while IFS= read -r p; do
			case "$p" in
				/opt/*)
					if ls "$p"/libasound.so* >/dev/null 2>&1 || [ ! -d "$p" ]; then hit=1; fi ;;
			esac
		done < <(grep -hoE 'LD_LIBRARY_PATH=[^"[:space:]]+' "$f" | cut -d= -f2- | tr ':' '\n')
		[ "$hit" = 1 ] || continue
		d="$(dirname "$f")"; rm -f "$f"; removed=1
		log "Removed alsa override drop-in: $f"
		rmdir "$d" 2>/dev/null || true
	done < <(grep -rlE 'LD_LIBRARY_PATH=[^[:space:]]*/opt/' /etc/systemd/system/ 2>/dev/null || true)
	# 2. the same override through the dynamic linker's search path
	for f in /etc/ld.so.conf.d/*.conf; do
		[ -f "$f" ] || continue
		hit=0
		while IFS= read -r p; do
			case "$p" in
				/opt/*) if ls "$p"/libasound.so* >/dev/null 2>&1; then hit=1; fi ;;
			esac
		done < "$f"
		if [ "$hit" = 1 ]; then
			rm -f "$f"; removed=1
			log "Removed alsa override ld.so path: $f"
		fi
	done
	# 3. the trees themselves - a /opt directory is removed only when it actually
	#    holds a libasound, so /opt/alsaequal, /opt/peppymeter, /opt/peppyspectrum
	#    and /opt/camillagui can never be caught by this.
	for d in /opt/*/; do
		if ls "$d"lib/libasound.so* >/dev/null 2>&1; then
			rm -rf "${d%/}"; removed=1
			log "Removed hand-built alsa-lib tree: ${d%/}"
		fi
	done
	if [ "$removed" = 1 ]; then
		systemctl daemon-reload >/dev/null 2>&1 || true
		ldconfig 2>/dev/null || true
		log "Retired the hand-built alsa-dsd override; MPD uses the packaged libasound from its next restart"
	fi
}

if [ -z "$ALSA_DISTRO_VER" ]; then
	warn "alsa-lib: no repository version found for libasound2t64; skipping (stock library kept)"
elif dpkg_ver_is libasound2t64 "$ALSA_TARGET_VER"; then
	log "alsa-lib $ALSA_TARGET_VER already installed"
	alsa_retire_dsd_override
else
	ALSA_UPSTREAM_VER="${ALSA_DISTRO_VER%%-*}"
	# The patches were written against 1.2.14 and dry-run clean through 1.2.16.1. Past
	# that, warn - the scope may already be fixed upstream, or the hunks may need a
	# refresh - but let the build run and fail loudly on its own if it must.
	if dpkg --compare-versions "$ALSA_UPSTREAM_VER" gt "$ALSA_PATCH_TESTED"; then
		warn "alsa-lib: the distro ships $ALSA_DISTRO_VER, newer than the last tested $ALSA_PATCH_TESTED - the meter scope may already be fixed upstream, or these patches may need updating"
	fi
	# Ask apt where that binary lives rather than assembling a pool path: pools are
	# keyed by SOURCE package, so the .deb's directory also holds alsa-lib's debian
	# tarball, and any layout works (security archive, third-party mirrors).
	_alsa_deb_uri="$(LC_ALL=C apt-get --print-uris download "libasound2t64=$ALSA_DISTRO_VER" 2>/dev/null \
		| awk -F"'" '{print $2; exit}')"
	ALSA_DEBIAN_URL=""
	[ -n "$_alsa_deb_uri" ] && ALSA_DEBIAN_URL="${_alsa_deb_uri%/*}/alsa-lib_${ALSA_DISTRO_VER}.debian.tar.xz"
	if [ -n "$ALSA_DEBIAN_URL" ]; then
		$APT_INSTALL build-essential dpkg-dev devscripts equivs fakeroot git wget patch
	fi
	ALSA_BLD="$(mktemp -d)"
	if [ -n "$ALSA_DEBIAN_URL" ] && ( cd "$ALSA_BLD" \
			&& git clone -q "$ALSA_GIT" "alsa-lib-$ALSA_UPSTREAM_VER" \
			&& cd "alsa-lib-$ALSA_UPSTREAM_VER" \
			&& git checkout -q "v$ALSA_UPSTREAM_VER" \
			&& git archive --format=tar.gz --output "../alsa-lib_${ALSA_UPSTREAM_VER}.orig.tar.gz" "v$ALSA_UPSTREAM_VER" \
			&& wget -q -O ../alsa-lib.debian.tar.xz "$ALSA_DEBIAN_URL" \
			&& tar -xf ../alsa-lib.debian.tar.xz \
			&& patch -p1 < "$REPO_DIR/patches/alsa_lib_scope_no_abort.patch" \
			&& EDITOR=/bin/true dpkg-source --commit . alsa_lib_scope_no_abort.patch \
			&& patch -p1 < "$REPO_DIR/patches/alsa_lib_scope_dsd_levels.patch" \
			&& EDITOR=/bin/true dpkg-source --commit . alsa_lib_scope_dsd_levels.patch \
			&& grep -q 's16->silent' src/pcm/pcm_meter.c \
			&& grep -q 'dsd_frame_bits' src/pcm/pcm_meter.c \
			&& DEBFULLNAME='moode-nopi' DEBEMAIL='moode-nopi@localhost' \
				dch -b --newversion "$ALSA_TARGET_VER" \
				'Added PCM meter scope patches: no abort on unconvertible formats, DSD levels' \
			&& env -u TMPDIR mk-build-deps --install --remove --tool "apt-get -y --no-install-recommends" \
			&& dpkg-buildpackage -b -us -uc ) > "$REPO_DIR/build-alsa-lib.log" 2>&1; then
		# Replace only what this host already runs: libasound2t64, libasound2-data,
		# libatopology2t64 and, when present, libasound2-dev. The doc/udeb/dbgsym
		# packages the build also produces are left alone.
		_alsa_debs=(); _alsa_pkgs=()
		for _deb in "$ALSA_BLD"/*.deb; do
			[ -f "$_deb" ] || continue
			_p="$(dpkg-deb -f "$_deb" Package 2>/dev/null || true)"
			[ -n "$_p" ] || continue
			case "$(dpkg-query -W -f='${Status}' "$_p" 2>/dev/null || true)" in
				'install ok installed') _alsa_debs+=("$_deb"); _alsa_pkgs+=("$_p") ;;
			esac
		done
		# apt, not `dpkg -i`: dpkg installs each archive independently and leaves the
		# set half-done - measured, it upgraded libasound2t64 and skipped
		# libasound2-data, and since the former Depends on the latter at the same
		# version, EVERY later apt call failed with unmet dependencies. Output goes to
		# the build log, never /dev/null: this is the step that mutates the system.
		_alsa_installed=0
		if [ "${#_alsa_debs[@]}" -gt 0 ] \
			&& apt-get install -y --allow-downgrades --allow-change-held-packages \
				"${_alsa_debs[@]}" >> "$REPO_DIR/build-alsa-lib.log" 2>&1; then
			# Trust the END STATE, not the exit code: every package we replaced must
			# now carry the target version, or the set is inconsistent.
			_alsa_installed=1
			for _p in "${_alsa_pkgs[@]}"; do
				dpkg_ver_is "$_p" "$ALSA_TARGET_VER" || _alsa_installed=0
			done
		fi
		if [ "$_alsa_installed" = 1 ]; then
			# Mandatory, and not merely to keep Debian's package out: dpkg orders
			# `1.2.14-1moode1` BELOW `1.2.14-1+deb13u1` (a letter sorts before a
			# non-letter), so a point update would silently win the comparison.
			apt-mark hold "${_alsa_pkgs[@]}" >/dev/null 2>&1 || true
			# Trace WHAT was built, WHEN and FROM WHICH patches: the rebuild guard is
			# the package version alone, so nothing else on the box records which
			# revision of the two patches the installed library carries.
			mkdir -p "$NOPI_BUILT_DIR"
			{
				printf 'version:  %s\n' "$ALSA_TARGET_VER"
				printf 'built:    %s\n' "$(date -Is)"
				printf 'source:   %s tag v%s\n' "$ALSA_GIT" "$ALSA_UPSTREAM_VER"
				printf 'debian:   %s\n' "$ALSA_DEBIAN_URL"
				printf 'patches:  %s\n' "$(sha256sum "$REPO_DIR"/patches/alsa_lib_scope_*.patch 2>/dev/null \
					| awk '{n = split($2, p, "/"); printf "%s=%.12s ", p[n], $1}')"
				printf 'packages: %s\n' "${_alsa_pkgs[*]}"
			} > "$NOPI_BUILT_DIR/alsa-lib"
			log "Built alsa-lib $ALSA_TARGET_VER (meter scope: no abort, DSD levels) -> ${_alsa_pkgs[*]}"
			log "  record: $NOPI_BUILT_DIR/alsa-lib"
			alsa_retire_dsd_override
		else
			# Restore the distro packages rather than leave a half-replaced set: an
			# unconfigured libasound2t64 breaks apt for every later phase. The /opt
			# override stays armed on purpose - it is what keeps DSD from aborting MPD.
			if [ "${#_alsa_pkgs[@]}" -gt 0 ]; then
				_alsa_restore=()
				for _p in "${_alsa_pkgs[@]}"; do _alsa_restore+=("$_p=$ALSA_DISTRO_VER"); done
				apt-get install -y --allow-downgrades --allow-change-held-packages \
					"${_alsa_restore[@]}" >> "$REPO_DIR/build-alsa-lib.log" 2>&1 || true
				apt-mark unhold "${_alsa_pkgs[@]}" >/dev/null 2>&1 || true
			fi
			warn "alsa-lib install failed; distro packages ($ALSA_DISTRO_VER) restored - see $REPO_DIR/build-alsa-lib.log"
		fi
	else
		warn "alsa-lib moode build failed (native DSD + a meter scope can abort MPD; stock library kept; see $REPO_DIR/build-alsa-lib.log)"
	fi
	rm -rf "$ALSA_BLD"
fi

#----------------------------------------------------------------------------#
# Phase 1k - shairport-sync-metadata-reader (AirPlay track metadata)
#----------------------------------------------------------------------------#
# moOde's AirPlay metadata chain is `cat /tmp/shairport-sync-metadata |
# shairport-sync-metadata-reader | aplmeta.py` (www/daemon/aplmeta-reader.sh).
# The middle link is a moOde-built package only the Pi IMAGE installs - the
# AirPlay plugin zip builds shairport-sync and nqptp, nothing else - so off-Pi it
# is simply absent, and that is fatal rather than cosmetic: the chain dies on the
# first metadata byte, the FIFO loses its only reader, and shairport-sync
# segfaults ~2s later. AirPlay then connects, plays nothing and drops, with the
# watchdog restarting it in a loop. Measured on the amd64 box 2026-08-28.
#
# Patched, because the reader is not x86-clean either: read_be_uint() accumulates
# bytes through a `char`, SIGNED here, so any byte >= 0x80 sign-extends and a
# bplist size reads back as (size_t)-30 - the next memcpy segfaults. Apple sends
# such a plist (updateMRSupportedCommands) at connect time. char is unsigned on
# ARM, which is why the Pi never trips it. Replaying a captured pipe dump: 26
# lines then SIGSEGV before the patch, 1241 lines after, identical up to there.
#
# MERGED UPSTREAM 2026-09-01 (mikebrady #26, commit c144a51) - and still needed,
# because moOde pins the commit BEFORE it. So apply it only when the source does
# not already carry the fix: the day moOde bumps GIT_HASH past c144a51, `patch`
# would fail and take this whole phase down with it, leaving a box with no reader
# and AirPlay broken again. The grep asserts the OUTCOME either way. Once the pin
# has moved, drop the patch file and this conditional.
SSMR_HASH="a4a29f3"                    # moOde's pin (pkgbuild build.sh)
SSMR_PATCH_REV="nopi1"                 # bump BY HAND when the patch changes
SSMR_VER="2.0.0~git20260724.$SSMR_HASH"
SSMR_TARGET_VER="$SSMR_VER-1moode1+$SSMR_PATCH_REV"
if ! dpkg_ver_is shairport-sync-metadata-reader "$SSMR_TARGET_VER"; then
	log "Phase 1k: shairport-sync-metadata-reader $SSMR_TARGET_VER"
	$APT_INSTALL build-essential fakeroot devscripts dh-make debhelper \
		autoconf automake libtool-bin pkg-config git patch
	SSMR_BLD="$(mktemp -d)"
	SSMR_DEB="$SSMR_BLD/shairport-sync-metadata-reader_${SSMR_TARGET_VER}_$(dpkg --print-architecture).deb"
	# moOde's own recipe (clone at the pinned hash, dh_make from a git archive),
	# minus rebuilder.lib.sh: its apt_update adds cloudsmith's RASPBIAN BINARY
	# repo, which must never land on a non-Pi box. debian/control is written here
	# rather than fetched: upstream's two packaging patches are metadata only, and
	# the build must not depend on reaching raw.githubusercontent.
	if ( cd "$SSMR_BLD" \
			&& git clone -q https://github.com/mikebrady/shairport-sync-metadata-reader.git \
				"shairport-sync-metadata-reader-$SSMR_VER" \
			&& cd "shairport-sync-metadata-reader-$SSMR_VER" \
			&& git checkout -q "$SSMR_HASH" \
			&& git archive --format=tar.gz --output "../ssmr_$SSMR_VER.tar.gz" "$SSMR_HASH" \
			&& DEBFULLNAME='moode-nopi' DEBEMAIL='moode-nopi@localhost' \
				dh_make -s -p "shairport-sync-metadata-reader_$SSMR_VER" \
				-f "../ssmr_$SSMR_VER.tar.gz" -y \
			&& rm -rf debian/*.ex debian/*.EX debian/README.* \
			&& printf '%s\n' \
				'Source: shairport-sync-metadata-reader' \
				'Section: audio' \
				'Priority: optional' \
				'Maintainer: moode-nopi <moode-nopi@localhost>' \
				'Rules-Requires-Root: no' \
				'Build-Depends:' \
				' debhelper-compat (= 13),' \
				'Standards-Version: 4.7.2' \
				'Homepage: https://github.com/mikebrady/shairport-sync-metadata-reader' \
				'' \
				'Package: shairport-sync-metadata-reader' \
				'Architecture: any' \
				'Depends:' \
				' ${shlibs:Depends},' \
				' ${misc:Depends},' \
				'Description: AirPlay metadata reader' \
				' Reads the metadata pipe written by shairport-sync and prints it in a' \
				' human-readable form.' > debian/control \
			&& { grep -q 'const unsigned char \*q' utilities/bplist-print.c \
				|| { patch -p1 < "$REPO_DIR/patches/ssmr_bplist_unsigned_bytes.patch" \
					&& EDITOR=/bin/true dpkg-source --commit . ssmr_bplist_unsigned_bytes.patch; }; } \
			&& grep -q 'const unsigned char \*q' utilities/bplist-print.c \
			&& DEBFULLNAME='moode-nopi' DEBEMAIL='moode-nopi@localhost' \
				dch -b --newversion "$SSMR_TARGET_VER" \
				'Read binary plist bytes unsigned (segfault on signed-char platforms)' \
			&& dpkg-buildpackage -b -us -uc )    > "$REPO_DIR/build-ssmr.log" 2>&1 \
		&& [ -f "$SSMR_DEB" ] \
		&& apt-get install -y "$SSMR_DEB" >> "$REPO_DIR/build-ssmr.log" 2>&1 \
		&& dpkg_ver_is shairport-sync-metadata-reader "$SSMR_TARGET_VER"; then
		mkdir -p "$NOPI_BUILT_DIR"
		{
			printf 'version:  %s\n' "$SSMR_TARGET_VER"
			printf 'built:    %s\n' "$(date -Is)"
			printf 'source:   %s commit %s\n' \
				'https://github.com/mikebrady/shairport-sync-metadata-reader.git' "$SSMR_HASH"
			printf 'patches:  %s\n' "$(sha256sum "$REPO_DIR/patches/ssmr_bplist_unsigned_bytes.patch" \
				2>/dev/null | awk '{printf "ssmr_bplist_unsigned_bytes.patch=%.12s", $1}')"
		} > "$NOPI_BUILT_DIR/ssmr"
		log "Built shairport-sync-metadata-reader $SSMR_TARGET_VER -> /usr/bin"
	else
		warn "shairport-sync-metadata-reader build failed; AirPlay will connect, play nothing" \
			"and drop (see $REPO_DIR/build-ssmr.log)"
	fi
	rm -rf "$SSMR_BLD"
fi

#----------------------------------------------------------------------------#
# Phase 2 - Deploy the web application tree
#----------------------------------------------------------------------------#

log "Phase 2: deploying application files"

# Web root, moodeutl CLI and the var/local/www payload (db schema, imagesw...)
rsync -a "$DIST_DIR/var/www/"        /var/www/
rsync -a "$DIST_DIR/usr/local/bin/"  /usr/local/bin/
rsync -a "$DIST_DIR/var/local/www/"  /var/local/www/
chmod +x /usr/local/bin/moodeutl /var/www/daemon/worker.php 2>/dev/null || true

# Stamp the running moode-nopi version for the WebUI (Configure > System),
# derived offline from git metadata at deploy time. Not a checkout -> drop any
# stale stamp so the UI line stays hidden.
if NOPI_VER=$(cd "$REPO_DIR" && git describe --tags --always 2>/dev/null) && [ -n "$NOPI_VER" ]; then
	printf '%s\n' "$NOPI_VER" > /var/local/www/nopi_version
	log "Stamped moode-nopi version: $NOPI_VER"
else
	rm -f /var/local/www/nopi_version
	warn "Could not derive moode-nopi version (not a git checkout?); UI line hidden"
fi
chmod 644 /var/local/www/nopi_version 2>/dev/null || true

# System helper scripts and assets shipped in the repo (referenced by worker
# as /usr/share/moode-player/... and others). Copy the static usr/ tree.
rsync -a "$REPO_DIR/usr/share/" /usr/share/ 2>/dev/null || true

#----------------------------------------------------------------------------#
# Phase 3 - nginx + php configuration
#----------------------------------------------------------------------------#

log "Phase 3: configuring nginx and php-fpm"

# Deploy moOde's own nginx + php configs rather than patch Debian's defaults.
# Most critical is PHP session handling: save_path = /var/local/php and the id
# format (sid_length 26, sid_bits_per_character 5). Debian's defaults (32/4, hex)
# reject moOde's stored session id, so PHP keeps making fresh empty sessions -
# worker and web never share state, config fields render blank, jobs never run.

# --- nginx ---
install -m 644 "$REPO_DIR/etc/nginx/nginx.overwrite.conf"      /etc/nginx/nginx.conf
install -m 644 "$REPO_DIR/etc/nginx/proxy.conf"               /etc/nginx/proxy.conf
install -m 644 "$REPO_DIR/etc/nginx/ssl.conf"                 /etc/nginx/ssl.conf
install -m 644 "$REPO_DIR/etc/nginx/fastcgi_params.overwrite" /etc/nginx/fastcgi_params
[ -f "$REPO_DIR/etc/nginx/dhparams.pem" ] && install -m 644 "$REPO_DIR/etc/nginx/dhparams.pem" /etc/nginx/dhparams.pem

# moode-locations.conf: match the installed php-fpm socket version
sed "s#/run/php/php8.4-fpm.sock#$PHP_SOCK#g" \
	"$REPO_DIR/etc/nginx/moode-locations.conf" > /etc/nginx/moode-locations.conf
chmod 644 /etc/nginx/moode-locations.conf

# BOTH site configs, under the exact names the worker's nginx_https_only handler
# symlinks (moode-http.conf / moode-https.conf): deploying only one means the
# HTTPS toggle points sites-enabled at a missing file -> no server -> dead WebUI.
# Any earlier moode.conf is removed so two `default_server` blocks don't collide.
install -m 644 "$REPO_DIR/etc/nginx/sites-available/moode-http.overwrite.conf" \
	/etc/nginx/sites-available/moode-http.conf
install -m 644 "$REPO_DIR/etc/nginx/sites-available/moode-https.overwrite.conf" \
	/etc/nginx/sites-available/moode-https.conf
rm -f /etc/nginx/sites-available/moode.conf /etc/nginx/sites-enabled/moode.conf
rm -f /etc/nginx/sites-enabled/default
# Enable HTTP only when no site is enabled yet (fresh install). On a re-run leave
# whatever the worker set, else --update silently reverts a UI-enabled HTTPS to HTTP.
if [ ! -e /etc/nginx/sites-enabled/moode-http.conf ] && [ ! -e /etc/nginx/sites-enabled/moode-https.conf ]; then
	ln -sf /etc/nginx/sites-available/moode-http.conf /etc/nginx/sites-enabled/moode-http.conf
fi
nginx -t

# --- php (fpm + cli) ---
# moOde ships its config under etc/php/8.4 (the Debian 13 default). Deploy the
# php.ini and the fpm pool, substituting the php-fpm socket version. Extensions
# stay loaded via Debian's conf.d, which php.ini does not override.
PHP_SRC="$REPO_DIR/etc/php/8.4"
if [ -d "$PHP_SRC" ]; then
	install -m 644 "$PHP_SRC/fpm/php.sed.ini" "/etc/php/$PHP_VER/fpm/php.ini"
	install -m 644 "$PHP_SRC/cli/php.sed.ini" "/etc/php/$PHP_VER/cli/php.ini"
	sed "s#/run/php/php7.3-fpm.sock#$PHP_SOCK#g" \
		"$PHP_SRC/fpm/pool.d/www.sed.conf" > "/etc/php/$PHP_VER/fpm/pool.d/www.conf"
	# Drop any earlier cherry-picked override now that the full php.ini is in place
	rm -f "/etc/php/$PHP_VER/fpm/conf.d/99-moode.ini" "/etc/php/$PHP_VER/cli/conf.d/99-moode.ini"
else
	warn "moOde PHP configs (etc/php/8.4) not found; PHP $PHP_VER left at Debian defaults"
fi

# Debian's phpsessionclean timer deletes sess_* files older than
# session.gc_maxlifetime (1440s) in save_path - moOde's /var/local/php. The Pi
# image ships it disabled; on Debian it is enabled and Persistent=yes makes it
# catch up right after boot, when the session ctime is necessarily past the
# cutoff. The worker then recreates an empty one, so the vars living ONLY in the
# session (usb_volknob, led_state, rotaryenc) revert to defaults every reboot.
systemctl disable --now phpsessionclean.timer 2>/dev/null || true

# --- ALSA output plugin configs ---
# mpd.conf references the ALSA device "_audioout", defined in /etc/alsa/conf.d
# with the DSP/loopback/bluetooth chains: without them MPD cannot open its output
# and fails to start. Deploy them, stripping the ".overwrite" marker.
if [ -d "$REPO_DIR/etc/alsa/conf.d" ]; then
	install -d -m 755 /etc/alsa/conf.d
	for f in "$REPO_DIR"/etc/alsa/conf.d/*; do
		[ -f "$f" ] || continue
		base="$(basename "$f")"
		base="${base/.overwrite/}"   # camilladsp.overwrite.conf -> camilladsp.conf
		install -m 644 "$f" "/etc/alsa/conf.d/$base"
	done
	log "Deployed ALSA conf.d plugin configs"
else
	warn "moOde ALSA configs (etc/alsa/conf.d) not found; MPD output may fail to start"
fi

# --- DSP plugin runtime setup (Graphic EQ + Crossfeed) ---
# Graphic EQ (alsaequal): the plugin persists its 10-band state to
# /opt/alsaequal/alsaequal.bin, opened READ-WRITE by everyone who opens the EQ
# device - MPD (user mpd) and the UI's `amixer cset` (root via sudo). A root-owned
# .bin makes MPD's open fail with "_audioout: Operation not permitted" and kills
# playback, so create the dir 0777 and pre-seed the .bin 0666: whichever side
# writes first cannot lock the other out. (The Pi image provides /opt/alsaequal.)
install -d -m 0777 /opt/alsaequal
if [ -f /etc/alsa/conf.d/alsaequal.conf ]; then
	amixer -D alsaequal cset numid=1 66 >/dev/null 2>&1 || true   # creates the .bin
	[ -f /opt/alsaequal/alsaequal.bin ] && chmod 0666 /opt/alsaequal/alsaequal.bin
fi

# Crossfeed (bs2b): moOde's crossfeed.conf hardcodes the arm64 LADSPA path.
# Repoint it at THIS host's multiarch dir (a no-op on arm64/Armbian).
BS2B_SO="$(dpkg -L bs2b-ladspa 2>/dev/null | grep -m1 '/ladspa/bs2b.so')"
if [ -n "$BS2B_SO" ] && [ -f /etc/alsa/conf.d/crossfeed.conf ]; then
	sed -i "s|/usr/lib/aarch64-linux-gnu/ladspa/|$(dirname "$BS2B_SO")/|" /etc/alsa/conf.d/crossfeed.conf
	log "Crossfeed LADSPA path -> $(dirname "$BS2B_SO")/"
fi

# Peppy (libpeppyalsa): peppy.conf.hide hardcodes the arm64 path; repoint it at
# this host's multiarch dir where Phase 1g installed libpeppyalsa.so.
if [ "$INSTALL_LOCALDISPLAY" = 1 ] && [ -f /etc/alsa/conf.d/peppy.conf.hide ]; then
	sed -i "s|/usr/lib/aarch64-linux-gnu/libpeppyalsa.so|/usr/lib/$(dpkg-architecture -qDEB_HOST_MULTIARCH 2>/dev/null)/libpeppyalsa.so|" /etc/alsa/conf.d/peppy.conf.hide
	log "Peppy libpeppyalsa path -> host multiarch"
fi

# --- Samba ---
# ALWAYS, not only when the SMB server is enabled: the SMB *client* tools that
# browse remote NAS shares (nmblookup, smbclient) refuse to run without
# /etc/samba/smb.conf. With the server on it also serves [Playlists]/[OSDisk].
if [ -f "$REPO_DIR/etc/samba/smb.overwrite.conf" ]; then
	install -d -m 755 /etc/samba
	install -m 644 "$REPO_DIR/etc/samba/smb.overwrite.conf" /etc/samba/smb.conf
	log "Deployed Samba config"
fi

#----------------------------------------------------------------------------#
# Phase 3b - Base system configuration (the etc/ payload the image build
# normally installs that the core player relies on)
#----------------------------------------------------------------------------#

log "Phase 3b: base system configs"

# MPD defaults: MPDCONF location (mpd.service warns about an unset env var
# otherwise).
install -m 644 "$REPO_DIR/etc/default/mpd.sed" /etc/default/mpd

# moOde's own sudoers: 010_www-data-nopasswd grants www-data full passwordless
# sudo (the worker/web rely on this - sysCmd() always uses sudo), 010_moode sets
# the no-logfile defaults.
install -m 440 "$REPO_DIR/etc/sudoers.d/010_moode"              /etc/sudoers.d/010_moode
install -m 440 "$REPO_DIR/etc/sudoers.d/010_www-data-nopasswd"  /etc/sudoers.d/010_www-data-nopasswd

# Make the player user a sudoer: Raspberry Pi OS does it out of the box, a
# minimal Debian does not, so the operator could not re-run this installer with
# `sudo`. moOde's runtime needs the www-data NOPASSWD rule above, not this.
if id -nG "$PLAYER_USER" 2>/dev/null | grep -qw sudo; then
	log "Player user '$PLAYER_USER' already in sudo group"
else
	usermod -aG sudo "$PLAYER_USER" && log "Added '$PLAYER_USER' to sudo group"
fi

# /etc/machine-info: PRETTY_HOSTNAME, used by bluez's hostname plugin as the
# advertised Bluetooth name. Idempotent so a runtime rename survives a re-run.
grep -q '^PRETTY_HOSTNAME=' /etc/machine-info 2>/dev/null \
	|| install -m 644 "$REPO_DIR/etc/machine-info.overwrite" /etc/machine-info

# --- .overwrite files intentionally NOT deployed on x86 (audit - do NOT "fix") --
# Every platform-neutral .overwrite IS deployed (Phase 3, here, the ALSA conf.d
# loop, the renderer/BT blocks). These are SKIPPED on purpose:
#   rc.local.overwrite       - the Pi starts worker.php as root from rc.local
#       (+ udisks-glue, cpugov); here it is the moode-worker unit. Deploying it
#       would double-start the worker and invoke the absent udisks-glue.
#   udisks-glue.overwrite.conf - config for a daemon gone from Trixie (we use devmon).
#   rpi/swap.conf.d/fixedswapsize.overwrite.conf - /etc/rpi is Raspberry-Pi-only.
#   update-motd.d/00-moodeos-header.overwrite - SSH banner calling pirev.py.
# (pam.d/sudo is handled below by editing Debian's file in place: the Raspbian
#  .overwrite would drop Debian's pam_limits line.)

# pam.d/sudo: cut the "session opened for user root" spam - the worker routes
# every privileged op through sudo, so pam_unix logs a pair per call (~48k/day
# here), burying real auth events. moOde's fix is a pam_succeed_if rule skipping
# the session stack when the TARGET is root. Do NOT drop in moOde's Raspbian file
# verbatim: Debian's carries an extra `session required pam_limits.so` the
# Raspbian one lacks. Insert just the rule instead. High blast radius (a bad
# pam.d/sudo kills every sysCmd), so back up, edit, re-test sudo through runuser
# (which does NOT go through pam-sudo, so a restore is always possible) and roll
# back on failure. Idempotent, keyed on the rule text.
PAM_SUDO=/etc/pam.d/sudo
PAM_RULE='session [success=1 default=ignore] pam_succeed_if.so quiet uid = 0 user = root'
if [ -f "$PAM_SUDO" ] && ! grep -qF "$PAM_RULE" "$PAM_SUDO" \
		&& grep -q '^@include common-session-noninteractive' "$PAM_SUDO"; then
	cp -a "$PAM_SUDO" "$PAM_SUDO.moode-bak"
	sed -i "/^@include common-session-noninteractive/i $PAM_RULE" "$PAM_SUDO"
	if runuser -u "$PLAYER_USER" -- sudo -n true 2>/dev/null && runuser -u www-data -- sudo -n true 2>/dev/null; then
		log "pam.d/sudo: suppressed root-session log spam (sudo still works)"
	else
		cp -a "$PAM_SUDO.moode-bak" "$PAM_SUDO"
		warn "pam.d/sudo edit broke sudo - rolled back (root-session spam left as-is)"
	fi
fi

# Name resolution: mDNS (.local) and WINS/NetBIOS so NAS hosts can be addressed
# by name in the Library config. The NSS modules (libnss-mdns, libnss-winbind)
# are installed in Phase 1, so glibc lookups using these keywords resolve.
install -m 644 "$REPO_DIR/etc/nsswitch.sed.conf" /etc/nsswitch.conf

# Avahi service advertisements (network discovery of the player; SMB when shared).
# Both ship on the Pi unconditionally; deploy both for parity. The SMB advert is a
# harmless mDNS record when Samba is off, and avoids a missing default conf file.
install -d -m 755 /etc/avahi/services
install -m 644 "$REPO_DIR/etc/avahi/services/moode.service" /etc/avahi/services/moode.service
install -m 644 "$REPO_DIR/etc/avahi/services/samba.service" /etc/avahi/services/samba.service

# Radio Cover+ reads /etc/radiocover-plus/config.txt. Guarded on absence so
# operator edits (API tokens, provider toggles - rcp-config.php seds them in
# place) survive a re-run. Always run via sysCmd, so root ownership is fine.
install -d -m 755 /etc/radiocover-plus
[ -f /etc/radiocover-plus/config.txt ] \
	|| install -m 644 "$REPO_DIR/etc/radiocover-plus/config.txt" /etc/radiocover-plus/config.txt

# Renderer / Bluetooth unit OVERRIDES: moOde's units REPLACE the stock package
# ones, and the matching ALSA configs above are useless if the service consuming
# them still runs the stock unit. Install to /etc/systemd/system (highest
# precedence: beats /usr/lib, /lib and the squeezelite SysV generator unit).
if [ "$INSTALL_BLUETOOTH" = 1 ]; then
	# Without these, enabling Bluetooth runs the stock bluealsa (no aptX/LDAC) with
	# no BT-speaker output or pairing agent. bluealsa.service also carries runtime
	# state the UI seds in (--sbc-quality) -> carry it over the template.
	_sbc_quality=$(sed -n 's/.*--sbc-quality=\([^ ]*\).*/\1/p' /etc/systemd/system/bluealsa.service 2>/dev/null || true)
	install -m 644 "$REPO_DIR/etc/systemd/system/bluealsa.overwrite.service" /etc/systemd/system/bluealsa.service
	if [ -n "$_sbc_quality" ]; then
		sed -i "s/--sbc-quality=[^ ]*/--sbc-quality=$_sbc_quality/" /etc/systemd/system/bluealsa.service
	fi
	install -m 644 "$REPO_DIR/etc/systemd/system/bluealsa-aplay@.service"     /etc/systemd/system/bluealsa-aplay@.service
	# Always overwrite bt-agent.service: a box predating the pairing-confirmation
	# change carries a worker-rewritten unit running bluez-tools' bt-agent with a PIN
	# file, and nothing rewrites it any more -> the confirmation modal never appears.
	install -m 644 "$REPO_DIR/etc/systemd/system/bt-agent.service" /etc/systemd/system/bt-agent.service
	# A2DP playback routing: startBluetooth() starts bluealsa/bt-agent but NOT the
	# player - bluealsa-aplay@<MAC> is started per device by a udev rule ->
	# a2dp-autoconnect. Without these three a phone pairs and connects but no audio
	# reaches the DAC. bluealsaaplay.conf is the @-unit's env file and must
	# pre-exist (moOde seds it, never creates it); AUDIODEV is regenerated by
	# updDspAndBtInConfs(), but nothing regenerates BUFFERTIME (Configure >
	# Bluetooth) -> carry it over the template on a re-run.
	_bt_buffertime=$(sed -n 's/^BUFFERTIME=//p' /etc/bluealsaaplay.conf 2>/dev/null || true)
	install -m 644 "$REPO_DIR/etc/bluealsaaplay.conf"                     /etc/bluealsaaplay.conf
	if [ -n "$_bt_buffertime" ]; then
		sed -i "/BUFFERTIME/c\\BUFFERTIME=$_bt_buffertime" /etc/bluealsaaplay.conf
	fi
	install -m 755 "$REPO_DIR/usr/local/bin/a2dp-autoconnect"             /usr/local/bin/a2dp-autoconnect
	install -m 644 "$REPO_DIR/etc/udev/rules.d/10-a2dp-autoconnect.rules" /etc/udev/rules.d/10-a2dp-autoconnect.rules
	udevadm control --reload-rules 2>/dev/null || true
	# BT controller name + class. `sysutil.sh chg-name bluetooth` seds `Name =` in
	# /etc/bluetooth/main.conf AND `PRETTY_HOSTNAME=` in /etc/machine-info (bluez's
	# hostname plugin OVERRIDES the former with the latter, so PRETTY_HOSTNAME is
	# what is advertised). Stock Debian ships `#Name = BlueZ` commented and no
	# machine-info, so both seds no-op and the class is never set. Keyed on moOde's
	# Class marker / an existing PRETTY_HOSTNAME, so a rename survives re-runs.
	grep -q 'Class = 0x2c041c' /etc/bluetooth/main.conf 2>/dev/null \
		|| install -m 644 "$REPO_DIR/etc/bluetooth/main.sed.conf" /etc/bluetooth/main.conf
	# Stale PIN seed from the pre-confirmation pairing scheme: bluez ignores it now
	# that moOde's agent drives Numeric Comparison.
	rm -f /etc/bluetooth/pin.conf
	grep -q '^PRETTY_HOSTNAME=' /etc/machine-info 2>/dev/null \
		|| echo "PRETTY_HOSTNAME=Moode Bluetooth" >> /etc/machine-info
	log "Deployed Bluetooth service units + A2DP autoconnect + controller name/class"
fi
if [ "$INSTALL_SQUEEZELITE" = 1 ]; then
	# The package's own unit is Debian-style (/etc/default/squeezelite, SL_NAME) and
	# ignores moOde's config. Override with moOde's unit, which reads the
	# PLAYERNAME/AUDIODEVICE that inc/renderer.php writes to the env file.
	install -m 644 "$REPO_DIR/lib/systemd/system/squeezelite.overwrite.service" /etc/systemd/system/squeezelite.service
	log "Deployed Squeezelite service unit (reads /etc/squeezelite.conf)"
fi
# upmpdcli: the lesbonscomptes package ships a STOCK /etc/upmpdcli.conf, but moOde
# needs its own (the friendlyname/ohproductroom template that chg-name +
# upp-config.php sed, plus iconpath) - otherwise the name/icon are wrong and
# chg-name's sed finds no matching line. Keyed on the icon marker.
if [ "$INSTALL_UPNP" = 1 ] && dpkg-query -W -f='${Status}' upmpdcli 2>/dev/null | grep -q ' installed'; then
	grep -q 'moode_audio.png' /etc/upmpdcli.conf 2>/dev/null \
		|| install -m 644 "$REPO_DIR/etc/upmpdcli.sed.conf" /etc/upmpdcli.conf
	install -d -m 755 /usr/share/upmpdcli
	install -m 644 "$REPO_DIR/usr/share/upmpdcli/moode_audio.png" /usr/share/upmpdcli/moode_audio.png
	log "Deployed moOde upmpdcli.conf + renderer icon"
fi
# Plexamp renderer unit (Pi parity; disabled until the plugin is installed from
# the UI). The upstream unit hardcodes `pi` and /home/pi -> rewrite to the real
# player account.
PLAYER_HOME="$(getent passwd "$PLAYER_USER" | cut -d: -f6)"; [ -n "$PLAYER_HOME" ] || PLAYER_HOME="/home/$PLAYER_USER"
sed -e "s|^User=pi$|User=$PLAYER_USER|" \
    -e "s|/home/pi/|$PLAYER_HOME/|g" \
    "$REPO_DIR/etc/systemd/system/plexamp.service" > /etc/systemd/system/plexamp.service
chmod 644 /etc/systemd/system/plexamp.service

# USB Wi-Fi dongle modprobe options (Realtek 8192cu / 8812au): parity with the Pi,
# harmless when no such adapter is present, useful if one is plugged in.
install -d -m 755 /etc/modprobe.d
install -m 644 "$REPO_DIR/etc/modprobe.d/8192cu.conf" /etc/modprobe.d/8192cu.conf
install -m 644 "$REPO_DIR/etc/modprobe.d/8812au.conf" /etc/modprobe.d/8812au.conf

systemctl daemon-reload

# The pairing agent is a long-running python process, so Phase 2 replacing
# bt-pairing-agent.py leaves the OLD code running - nothing restarts it (the
# worker only touches bt-agent on a pairing-mode change). Restart it here, after
# the daemon-reload. Only when ALREADY running: starting it on a box with
# Bluetooth off would enable pairing behind the user's back.
if [ "$INSTALL_BLUETOOTH" = 1 ] && systemctl is-active --quiet bt-agent; then
	systemctl restart bt-agent
	log "Restarted bt-agent (pairing agent code refreshed)"
fi

# PHP opcache tuning
if [ -f "$REPO_DIR/etc/php/8.4/mods-available/opcache.sed.ini" ]; then
	install -m 644 "$REPO_DIR/etc/php/8.4/mods-available/opcache.sed.ini" \
		"/etc/php/$PHP_VER/mods-available/opcache.ini"
fi

# Triggerhappy: USB volume-knob / media-key handling (vol.sh)
install -d -m 755 /etc/triggerhappy/triggers.d
install -m 644 "$REPO_DIR/etc/triggerhappy/triggers.d/media.conf" /etc/triggerhappy/triggers.d/media.conf
# Debian's triggerhappy unit runs thd as `nobody`, which drops the trigger
# commands to nobody too: vol.sh then can't create the journal in the
# www-data-owned /var/local/www/db -> "attempt to write a readonly database" ->
# the USB knob moves the live volume but never persists it. Run thd as www-data
# instead. It needs no input group (thd opens the devices as root before dropping
# privs, hotplugs arrive via the root th-cmd helper) but it DOES need audio:
# vol.sh drives the mixer with a plain amixer, so without it a volume key moves
# volknob in the DB while the DAC stays put. Groups are fixed at process start,
# hence the restart.
usermod -aG audio www-data
systemctl is-active --quiet triggerhappy && systemctl restart triggerhappy
install -d -m 755 /etc/systemd/system/triggerhappy.service.d
cat > /etc/systemd/system/triggerhappy.service.d/override.conf <<'EOF'
[Service]
ExecStart=
ExecStart=/usr/sbin/thd --triggers /etc/triggerhappy/triggers.d/ --socket /run/thd.socket --user www-data --deviceglob /dev/input/event*
EOF

# udevil config: USB auto-mount media dir (/media) and per-filesystem mount
# options. devmon (Phase 6/7) uses udevil to mount removable drives.
if [ -f "$REPO_DIR/etc/udevil/udevil.overwrite.conf" ]; then
	install -d -m 755 /etc/udevil
	install -m 644 "$REPO_DIR/etc/udevil/udevil.overwrite.conf" /etc/udevil/udevil.conf
fi

# automount.sh / music-source.php append `/srv/nfs/<kind>/<label>` lines to
# /etc/exports with `sed '$ a'`, which needs an existing anchor line, and the
# worker's fs_nfs_* handler errors on an empty file. nfs-kernel-server only ucf's
# its header in when the file is ABSENT, so a leftover-empty /etc/exports stays
# empty. Deploy the package's own template when missing OR empty.
if [ ! -s /etc/exports ]; then
	if [ -f /usr/share/nfs-kernel-server/conffiles/etc.exports ]; then
		install -m 644 /usr/share/nfs-kernel-server/conffiles/etc.exports /etc/exports
	else
		: > /etc/exports   # NFS server absent: keep the file present for the hook
	fi
fi

# The exported paths resolve through symlinks the Pi IMAGE ships in /srv/nfs -
# moOde's source never creates them, so replicate them here or exportfs cannot
# resolve /srv/nfs/<kind>/<label>.
install -d -m 755 /srv/nfs
[ -e /srv/nfs/usb ]  || ln -s /media    /srv/nfs/usb
[ -e /srv/nfs/nvme ] || ln -s /mnt/NVME /srv/nfs/nvme
[ -e /srv/nfs/sata ] || ln -s /mnt/SATA /srv/nfs/sata

# moOde's networking model is baked to eth0/wlan0: cfg_network rows are
# positional, the keyfiles hardcode interface-name, the SSID scan runs `nmcli
# ifname wlan0`. A Debian x86 box gets predictable names (enp2s0/wlp1s0) AND
# configures ethernet via ifupdown, so NM never manages it -> the Network config
# and SSID scan both show nothing. Two fixes, both effective next boot: disable
# predictable naming, and stop ifupdown claiming the primary NIC.
if [ -f /etc/default/grub ] && command -v update-grub >/dev/null 2>&1; then
	if ! grep -q 'net.ifnames=0' /etc/default/grub; then
		sed -i 's/^GRUB_CMDLINE_LINUX="\(.*\)"/GRUB_CMDLINE_LINUX="\1 net.ifnames=0 biosdevname=0"/' /etc/default/grub
		sed -i 's/GRUB_CMDLINE_LINUX=" net.ifnames=0/GRUB_CMDLINE_LINUX="net.ifnames=0/' /etc/default/grub
		if update-grub >/dev/null 2>&1; then
			log "Disabled predictable NIC naming -> eth0/wlan0 (effective after reboot)"
		else
			warn "update-grub failed; NICs may keep enpXsY/wlpXsY names (SSID scan/Network config need eth0/wlan0)"
		fi
	fi
elif [ -f /boot/armbianEnv.txt ]; then
	# Armbian (u-boot, no GRUB): same via armbianEnv.txt, else the NIC comes up as
	# end0/enxMAC.
	if grep -q '^extraargs=' /boot/armbianEnv.txt; then
		grep -q '^extraargs=.*net\.ifnames=0' /boot/armbianEnv.txt \
			|| sed -i 's/^extraargs=\(.*\)$/extraargs=\1 net.ifnames=0 biosdevname=0/' /boot/armbianEnv.txt
	else
		echo 'extraargs=net.ifnames=0 biosdevname=0' >> /boot/armbianEnv.txt
	fi
	log "Armbian: net.ifnames=0 set in armbianEnv.txt (-> eth0/wlan0 after reboot)"
else
	warn "No GRUB and no armbianEnv.txt: ensure net.ifnames=0 so NICs are eth0/wlan0"
fi

# Hand the NICs to NetworkManager. The primary NIC is claimed by whichever backend
# the base install uses: ifupdown (/etc/network/interfaces, Debian netinst) or
# cloud-init -> netplan -> systemd-networkd (cloud images). moOde needs NM to own
# eth0/wlan0, so neutralise whichever one is in the way.

# ifupdown: reduce to loopback so it stops claiming the NIC (keep a one-time backup).
if [ -f /etc/network/interfaces ] && grep -qE '^[[:space:]]*(auto|allow-hotplug|iface)[[:space:]]+(en|eth|wl)' /etc/network/interfaces; then
	[ -f /etc/network/interfaces.moode-orig ] || cp -a /etc/network/interfaces /etc/network/interfaces.moode-orig
	cat > /etc/network/interfaces <<'EOF'
# Managed by moOde via NetworkManager (see www/inc/network.php). Only loopback is
# defined here so ifupdown does not claim eth0/wlan0 - NetworkManager owns them.
# The original Debian-install file is saved as interfaces.moode-orig.
source /etc/network/interfaces.d/*

auto lo
iface lo inet loopback
EOF
	log "Reduced /etc/network/interfaces to loopback (NetworkManager owns the NICs)"
fi

# cloud-init: stop it regenerating network config (netplan/networkd) on each boot.
if [ -d /etc/cloud/cloud.cfg.d ]; then
	printf 'network: {config: disabled}\n' > /etc/cloud/cloud.cfg.d/99-disable-network-config.cfg
fi
# netplan/systemd-networkd: drop cloud-init's generated configs so networkd no
# longer owns the NIC, and (if netplan is present) point it at NetworkManager.
if [ -d /etc/netplan ]; then
	rm -f /etc/netplan/50-cloud-init.yaml
	# Disable any other netplan that renders through systemd-networkd (e.g. Armbian's
	# 10-dhcp-all-interfaces.yaml) - it otherwise claims the NIC via networkd and NM
	# never owns eth0/wlan0 (the SSID scan + Network config then show nothing).
	for _y in /etc/netplan/*.yaml; do
		[ -e "$_y" ] || continue
		case "$_y" in */00-moode-nm.yaml) continue ;; esac
		mv -f "$_y" "$_y.moode-disabled"
	done
	install -m 600 /dev/stdin /etc/netplan/00-moode-nm.yaml <<'EOF'
network:
  version: 2
  renderer: NetworkManager
EOF
fi
rm -f /etc/systemd/network/10-netplan-*.link /etc/systemd/network/10-netplan-*.network

# NM owns the NICs, so systemd-networkd-wait-online (left enabled by Armbian and
# some Debian images) waits its full timeout on interfaces networkd does not
# manage and exits 1 -> a failed unit + ~20s per boot. Mask it;
# NetworkManager-wait-online already gates network-online.target.
systemctl disable --now systemd-networkd-wait-online.service >/dev/null 2>&1 || true
systemctl mask systemd-networkd-wait-online.service >/dev/null 2>&1 || true

# Make sure NM treats nothing as unmanaged (cloud images mark the networkd NIC so).
install -d -m 755 /etc/NetworkManager/conf.d
printf '[keyfile]\nunmanaged-devices=none\n' > /etc/NetworkManager/conf.d/10-moode-manage-all.conf
if [ -f /etc/NetworkManager/NetworkManager.conf ]; then
	# Some images ship [ifupdown] managed=false; flip it so NM owns ifupdown NICs too.
	sed -i 's/^\(\s*\)managed=false/\1managed=true/' /etc/NetworkManager/NetworkManager.conf
fi

# WiFi creds migration. A Debian netinst that joined WiFi keeps the SSID/PSK as an
# ifupdown wpa-ssid/wpa-psk stanza; reducing that file to loopback above hands
# wlan0 to NM with NO profile, so a headless box silently drops off the network on
# the next reboot. Migrate the creds to an NM keyfile. No interface-name is
# pinned: net.ifnames=0 renames wlpXsY -> wlan0, so it matches whatever comes up.
_ifsrc=/etc/network/interfaces.moode-orig
if [ -f "$_ifsrc" ] && grep -qiE '^[[:space:]]*wpa-ssid[[:space:]]' "$_ifsrc"; then
	_ssid=$(sed -n 's/^[[:space:]]*wpa-ssid[[:space:]]\+//Ip' "$_ifsrc" | head -1 | sed 's/[[:space:]]*$//; s/^"\(.*\)"$/\1/')
	_psk=$(sed -n 's/^[[:space:]]*wpa-psk[[:space:]]\+//Ip' "$_ifsrc" | head -1 | sed 's/[[:space:]]*$//; s/^"\(.*\)"$/\1/')
	_fname=$(printf '%s' "$_ssid" | tr -c 'A-Za-z0-9._-' '_')
	_kf="/etc/NetworkManager/system-connections/${_fname}.nmconnection"
	install -d -m 700 /etc/NetworkManager/system-connections
	if [ -n "$_ssid" ] && [ ! -f "$_kf" ]; then
		{
			printf '[connection]\nid=%s\ntype=wifi\nautoconnect=true\n\n' "$_ssid"
			printf '[wifi]\nmode=infrastructure\nssid=%s\n\n' "$_ssid"
			[ -n "$_psk" ] && printf '[wifi-security]\nkey-mgmt=wpa-psk\npsk=%s\n\n' "$_psk"
			printf '[ipv4]\nmethod=auto\n\n[ipv6]\nmethod=auto\n'
		} > "$_kf"
		chmod 600 "$_kf"; chown root:root "$_kf"
		log "Migrated WiFi creds (SSID '$_ssid') from ifupdown to a NetworkManager keyfile"
	fi
	unset _ssid _psk _fname _kf
fi
unset _ifsrc

#----------------------------------------------------------------------------#
# etc/ payload completeness net (no deploy - a guard against forgotten files)
#----------------------------------------------------------------------------#
# ./etc is NOT a deployable mirror of /etc: files are sed-templated, renamed
# (*.overwrite stripped), gated by feature/arch, or intentionally skipped on x86
# (see the Phase 3b audit) - each wired by hand above. This net deploys nothing,
# it only WARNS when a path under ./etc is missing from ETC_KNOWN, so a file
# arriving with an upstream merge can't silently go undeployed. Every file is
# listed once, deployed OR documented skip. Assoc array on purpose: `| grep -q`
# can return SIGPIPE-141 on a match under `set -o pipefail`.
declare -A ETC_KNOWN=()
while IFS= read -r _p; do [ -n "$_p" ] && ETC_KNOWN["$_p"]=1; done <<'EOF'
alsa/conf.d/20-bluealsa.overwrite.conf
alsa/conf.d/alsaequal.conf
alsa/conf.d/_audioout.conf
alsa/conf.d/btstream.conf
alsa/conf.d/camilladsp.overwrite.conf
alsa/conf.d/crossfeed.conf
alsa/conf.d/eqfa12p.conf
alsa/conf.d/invpolarity.conf
alsa/conf.d/peppy.conf.hide
alsa/conf.d/_peppyout.conf
alsa/conf.d/_sndaloop.conf
alsa/conf.d/trx_send.conf
avahi/services/moode.service
avahi/services/samba.service
bluealsaaplay.conf
bluetooth/main.sed.conf
deezer/deezer.toml
default/mpd.sed
machine-info.overwrite
minidlna.sed.conf
modprobe.d/8192cu.conf
modprobe.d/8812au.conf
modules.sed
nginx/dhparams.pem
nginx/fastcgi_params.overwrite
nginx/moode-locations.conf
nginx/nginx.overwrite.conf
nginx/proxy.conf
nginx/sites-available/moode-http.overwrite.conf
nginx/sites-available/moode-https.overwrite.conf
nginx/ssl.conf
nsswitch.sed.conf
pam.d/sudo.overwrite
peppymeter/config.sed.txt
peppyspectrum/config.sed.txt
php/8.4/cli/php.sed.ini
php/8.4/fpm/php.sed.ini
php/8.4/fpm/pool.d/www.sed.conf
php/8.4/mods-available/opcache.sed.ini
radiocover-plus/config.txt
rc.local.overwrite
rpi/swap.conf.d/fixedswapsize.overwrite.conf
samba/smb.overwrite.conf
shairport-sync.sed.conf
squeezelite.conf
sudoers.d/010_moode
sudoers.d/010_www-data-nopasswd
systemd/journald.sed.conf
systemd/system/bluealsa-aplay@.service
systemd/system/bluealsa.overwrite.service
systemd/system/bt-agent.service
systemd/system/plexamp.service
triggerhappy/triggers.d/media.conf
udevil/udevil.overwrite.conf
udev/rules.d/10-a2dp-autoconnect.rules
udisks-glue.overwrite.conf
update-motd.d/00-moodeos-header.overwrite
upmpdcli.sed.conf
X11/xorg.conf.d/99-vc4.conf
X11/Xwrapper.sed.config
EOF
_etc_unhandled=""; _etc_stale=""
while IFS= read -r _p; do
	# A `*.disabled` file states its own intent: kept in the tree precisely so it is
	# NOT deployed. Listing it would claim it is handled; warning would call a
	# deliberate choice an oversight. Neither - skip it.
	case "$_p" in *.disabled) continue ;; esac
	[ -n "${ETC_KNOWN[$_p]:-}" ] || _etc_unhandled="$_etc_unhandled $_p"
done < <(cd "$REPO_DIR/etc" && find . -type f -printf '%P\n')
for _p in "${!ETC_KNOWN[@]}"; do
	[ -f "$REPO_DIR/etc/$_p" ] || _etc_stale="$_etc_stale $_p"
done
if [ -n "$_etc_unhandled" ]; then
	warn "etc/ payload: file(s) in ./etc NOT wired into install.sh - review Phase 3/3b:$_etc_unhandled"
fi
if [ -n "$_etc_stale" ]; then
	warn "etc/ payload: ETC_KNOWN lists file(s) absent from ./etc (renamed/removed?):$_etc_stale"
fi
unset _p _etc_unhandled _etc_stale ETC_KNOWN

#----------------------------------------------------------------------------#
# Phase 4 - SQLite configuration database
#----------------------------------------------------------------------------#

log "Phase 4: configuration database"

install -d -m 755 /var/local/www/db

if [ -f "$SQLDB" ] && [ "$RESET_DB" -ne 1 ]; then
	log "Existing DB kept: $SQLDB (use --reset-db to recreate)"

	# DB migration (--update): a kept DB may predate params the shipped schema has
	# gained. A missing param surfaces as an empty $_SESSION value with NO error
	# (the worker runs error_reporting(E_ERROR)) - an absent 'ipaddr_timeout' made
	# checkForIpAddr() compute maxLoops = ''/2 = 0, so the worker never waited for a
	# DHCP lease and dropped straight to the Hotspot on all three test boards.
	# Backfill schema params missing here WITHOUT touching existing rows.
	#
	# EVERY param/value table, not just cfg_system: upstream ships new renderer
	# settings the same way (cfg_airplay gained 'ignore_volume_control'), and on the
	# Pi they arrive through the moode-player postinstall, which nopi never runs.
	# The list is explicit rather than derived from "has param and value columns":
	# cfg_gpio has both for something else entirely (per-pin command arguments,
	# several rows sharing an empty param) and is not a param catalogue.
	_schema_db=$(mktemp --suffix=.db)
	if sqlite3 "$_schema_db" < "$SQLDB_SCHEMA" 2>/dev/null; then
		_mig_total=0
		for _t in cfg_system cfg_mpd cfg_airplay cfg_spotify cfg_deezer cfg_sl \
			cfg_upnp cfg_multiroom; do
			_added=$(sqlite3 "$SQLDB" "ATTACH '$_schema_db' AS sch;
				INSERT INTO $_t (param, value)
					SELECT s.param, s.value FROM sch.$_t s
					WHERE s.param NOT IN (SELECT param FROM main.$_t);
				SELECT changes();" 2>/dev/null | tail -1)
			if [ -n "$_added" ] && [ "$_added" -gt 0 ] 2>/dev/null; then
				log "DB migration: backfilled $_added missing param(s) into $_t"
				_mig_total=$((_mig_total + _added))
			fi
		done
		# Missing TABLES: a newer schema can add whole tables (cfg_rcucache), and the
		# first query against a missing one throws (PDO is in exception mode) -> HTTP
		# 500. Create what the schema defines and the DB lacks, then copy its shipped
		# rows. Existing tables are never altered - additive only.
		_tbl_added=0
		while IFS= read -r _tbl; do
			[ -z "$_tbl" ] && continue
			_ddl=$(sqlite3 "$_schema_db" "SELECT sql FROM sqlite_master WHERE type='table' AND name='$_tbl';")
			[ -z "$_ddl" ] && continue
			if sqlite3 "$SQLDB" "$_ddl;" 2>/dev/null; then
				sqlite3 "$SQLDB" "ATTACH '$_schema_db' AS sch;
					INSERT INTO main.\"$_tbl\" SELECT * FROM sch.\"$_tbl\";" 2>/dev/null || true
				log "DB migration: created missing table '$_tbl'"
				_tbl_added=$((_tbl_added + 1))
			else
				warn "DB migration: failed to create missing table '$_tbl'"
			fi
		done < <(sqlite3 "$SQLDB" "ATTACH '$_schema_db' AS sch;
			SELECT name FROM sch.sqlite_master WHERE type='table' AND name NOT LIKE 'sqlite_%'
				AND name NOT IN (SELECT name FROM main.sqlite_master WHERE type='table');" 2>/dev/null)

		# Missing COLUMNS: ALTER TABLE ADD COLUMN, definition rebuilt from the schema
		# (type + NOT NULL + DEFAULT). SQLite requires a NOT NULL column to carry a
		# default; without one the ALTER fails and we warn rather than abort.
		_col_added=0
		while IFS= read -r _t; do
			[ -z "$_t" ] && continue
			_live_cols=$(sqlite3 "$SQLDB" "SELECT name FROM pragma_table_info('$_t');" 2>/dev/null)
			[ -z "$_live_cols" ] && continue
			declare -A _have=()
			while IFS= read -r _c; do [ -n "$_c" ] && _have["$_c"]=1; done <<<"$_live_cols"
			while IFS='|' read -r _cname _ctype _cnn _cdflt; do
				[ -z "$_cname" ] && continue
				[ -n "${_have[$_cname]:-}" ] && continue
				_coldef="\"$_cname\" $_ctype"
				[ "$_cnn" = "1" ] && _coldef="$_coldef NOT NULL"
				[ -n "$_cdflt" ] && _coldef="$_coldef DEFAULT $_cdflt"
				if sqlite3 "$SQLDB" "ALTER TABLE \"$_t\" ADD COLUMN $_coldef;" 2>/dev/null; then
					log "DB migration: added missing column '$_t.$_cname'"
					_col_added=$((_col_added + 1))
				else
					warn "DB migration: failed to add column '$_t.$_cname' (def: $_coldef)"
				fi
			done < <(sqlite3 "$_schema_db" "SELECT name, type, \"notnull\", IFNULL(dflt_value,'') FROM pragma_table_info('$_t');" 2>/dev/null)
			unset _have
		done < <(sqlite3 "$_schema_db" "SELECT name FROM sqlite_master WHERE type='table' AND name NOT LIKE 'sqlite_%';" 2>/dev/null)

		# cfg_plugin is a CATALOGUE of what moOde offers on demand, not user config -
		# nothing writes it, only SELECTs - so unlike the param tables above it must
		# FOLLOW the shipped schema: isAirPlayUpgradable() compares the installed
		# package against cfg_plugin.version, and the plugin's install.sh reads that
		# version to name the .deb it builds. A stale row means the box is never
		# offered the upgrade, and a forced reinstall dies on a .deb never built.
		# Realign version AND plugin (the zip name carries a major, v5-shairport-sync).
		_plug_sync=0
		_plug_added=$(sqlite3 "$SQLDB" "ATTACH '$_schema_db' AS sch;
			INSERT INTO main.cfg_plugin (component, type, plugin, version)
				SELECT s.component, s.type, s.plugin, s.version FROM sch.cfg_plugin s
				WHERE NOT EXISTS (SELECT 1 FROM main.cfg_plugin m
					WHERE m.component = s.component AND m.type = s.type);
			SELECT changes();" 2>/dev/null | tail -1 || true)
		_plug_upd=$(sqlite3 "$SQLDB" "ATTACH '$_schema_db' AS sch;
			UPDATE cfg_plugin SET
				plugin = (SELECT s.plugin FROM sch.cfg_plugin s
					WHERE s.component = cfg_plugin.component AND s.type = cfg_plugin.type),
				version = (SELECT s.version FROM sch.cfg_plugin s
					WHERE s.component = cfg_plugin.component AND s.type = cfg_plugin.type)
				WHERE EXISTS (SELECT 1 FROM sch.cfg_plugin s
					WHERE s.component = cfg_plugin.component AND s.type = cfg_plugin.type
						AND (s.plugin <> cfg_plugin.plugin OR s.version <> cfg_plugin.version));
			SELECT changes();" 2>/dev/null | tail -1 || true)
		for _n in "$_plug_added" "$_plug_upd"; do
			if [ -n "$_n" ] && [ "$_n" -gt 0 ] 2>/dev/null; then
				_plug_sync=$((_plug_sync + _n))
			fi
		done
		if [ "$_plug_sync" -gt 0 ]; then
			log "DB migration: realigned $_plug_sync cfg_plugin row(s) on the shipped schema"
		fi

		if [ "$_mig_total" -eq 0 ] && [ "$_tbl_added" -eq 0 ] && [ "$_col_added" -eq 0 ] \
			&& [ "$_plug_sync" -eq 0 ]; then
			log "DB migration: schema up to date (no backfill needed)"
		fi
	else
		warn "DB migration: could not load schema for comparison, backfill skipped"
	fi
	rm -f "$_schema_db"
	unset _schema_db _mig_total _added _t _tbl_added _tbl _ddl \
		_col_added _live_cols _c _cname _ctype _cnn _cdflt _coldef \
		_plug_sync _plug_added _plug_upd _n
else
	# A successful backup is an event, not a problem: --reset-db asked for it.
	[ -f "$SQLDB" ] && cp -a "$SQLDB" "$SQLDB.bak.$(date +%s)" && log "Backed up old DB"
	rm -f "$SQLDB"
	sqlite3 "$SQLDB" < "$SQLDB_SCHEMA"
	log "Created DB from schema"
fi

# CPU governor: the schema seeds the Pi's 'ondemand', which an Intel CPU in
# intel_pstate passive mode does not have (only performance/schedutil). The
# dropdown already builds from scaling_available_governors; keep the PERSISTED
# value honest too so autocfg never tees an invalid governor. Rewritten only when
# the seeded value is unavailable here. The schema default is left untouched so
# --reset-db semantics stay byte-identical to the Pi.
AVAIL_GOV_FILE=/sys/devices/system/cpu/cpu0/cpufreq/scaling_available_governors
if [ -r "$AVAIL_GOV_FILE" ]; then
	STORED_GOV=$(sqlite3 "$SQLDB" "SELECT value FROM cfg_system WHERE param='cpugov'")
	if ! grep -qw "$STORED_GOV" "$AVAIL_GOV_FILE"; then
		LIVE_GOV=$(cat /sys/devices/system/cpu/cpu0/cpufreq/scaling_governor 2>/dev/null)
		if [ -n "$LIVE_GOV" ]; then
			sqlite3 "$SQLDB" "UPDATE cfg_system SET value='$LIVE_GOV' WHERE param='cpugov'"
			log "Realigned cpugov '$STORED_GOV' -> '$LIVE_GOV' (available on this CPU)"
		fi
	fi
fi

#----------------------------------------------------------------------------#
# Phase 5 - Directories and permissions
#----------------------------------------------------------------------------#

log "Phase 5: permissions"

# worker.php runs as root; nginx/php run as www-data. /var/local/www holds the
# DB and session data and must be writable by www-data.
chown -R www-data:www-data /var/www /var/local/www
chmod -R g+w /var/local/www

# worker.php gates the Ready script on is_executable(); upstream ships the file
# mode 0644, so the bit has to be set here (its commandw siblings are all 0755).
chmod 755 /var/local/www/commandw/ready-script.sh

# moOde PHP session directory (session.save_path). The worker runs as www-data
# (Phase 6), so it shares these files with php-fpm directly.
#
# Owner MUST be www-data, not root. The dir is sticky + world-writable (1777) and
# Debian sets fs.protected_regular=2, which forbids opening a file you do NOT own
# for WRITE inside such a dir UNLESS it is owned by the DIRECTORY's owner - and
# PHP opens session files O_RDWR. With a root-owned dir, the root helpers launched
# via sysCmd() cannot open the www-data-owned session files (EACCES) -> $_SESSION
# empty for every one of them (thumb-gen.php hangs, moodeutl -gv returns blank).
# www-data owning the dir satisfies the exception for both. (Never surfaced on the
# Pi, where the worker is root.)
install -d -m 1777 /var/local/php
chown www-data:www-data /var/local/php

# Files the worker writes DIRECTLY (not via sudo) but that live in root-owned
# dirs where www-data cannot create anything: /etc/mpd.conf + /etc/mpd.moode.conf
# (updMpdConf() each boot), /var/log/moode*.log (workerLog()), and the renderer
# configs /etc/squeezelite.conf and /etc/deezer/deezer.toml (renderer.php, the
# latter also on every autoConfig restore). As www-data the fopen() fails and the
# following fwrite/ftruncate(false) raises an uncaught TypeError. Pre-create them
# www-data-owned; harmless empty files when the renderer is unused. `touch`, not
# install /dev/null, so existing content survives a re-run.
install -d -o www-data -g www-data /etc/deezer
for f in /etc/mpd.conf /etc/mpd.moode.conf; do
	touch "$f"; chown www-data:www-data "$f"; chmod 644 "$f"
done
# Seed the Pi DEFAULT CONTENT for these renderer config files (parity with the Pi
# image), www-data-owned, only when absent/empty so worker/UI/user edits survive
# installer re-runs. squeezelite.conf is rewritten from cfg_sl when Squeezelite is
# enabled; deezer.toml by updateDeezCredentials().
[ -s /etc/squeezelite.conf ]   || install -m 644 "$REPO_DIR/etc/squeezelite.conf"   /etc/squeezelite.conf
[ -s /etc/deezer/deezer.toml ] || install -m 644 "$REPO_DIR/etc/deezer/deezer.toml" /etc/deezer/deezer.toml
chown www-data:www-data /etc/squeezelite.conf /etc/deezer/deezer.toml
chmod 644 /etc/squeezelite.conf /etc/deezer/deezer.toml
# NOTE: moode_autocfg is written directly by autoCfgLog() from the www-data
# worker during autoConfig() (restore/auto-config at boot). It is root-truncated
# at worker.php's autocfg step but truncate preserves ownership, so pre-creating
# it www-data-owned keeps autoCfgLog()'s direct fopen() working. Without this the
# fopen() fails, fwrite(false) raises an uncaught TypeError on PHP 8, and the
# worker crash-loops every boot while /boot/moodecfg.ini is present.
for l in moode moode_playhistory moode_mountmon moode_autocfg; do
	touch "/var/log/$l.log"; chown www-data:www-data "/var/log/$l.log"; chmod 664 "/var/log/$l.log"
done

# CamillaDSP config tree (/usr/share/camilladsp, deployed in Phase 2): the web UI
# (www-data) creates/copies configs and (re)points working_config.yml, so the
# tree must be group-writable by www-data. camilladsp runs as root and reads it.
if [ -d /usr/share/camilladsp ]; then
	chown -R www-data:www-data /usr/share/camilladsp
	chmod -R g+w /usr/share/camilladsp
fi

#----------------------------------------------------------------------------#
# Phase 5b - Local music storage (OSDISK), RADIO, playlists
#----------------------------------------------------------------------------#

log "Phase 5b: local music storage"

# The library roots (OSDISK, RADIO, NAS...) live under MPD's music_directory and
# are created by the Pi IMAGE build - replicate them here. MPD follows the
# symlinks out to /mnt (follow_outside_symlinks defaults to yes).
MPD_MUSIC=/var/lib/mpd/music
install -d -m 0755 "$MPD_MUSIC"

# OSDISK: local music store + recorder target. A data partition on the Pi, just a
# directory here. Default content (Stereo Test, ReadyChime) seeded only when
# missing, so user files are never clobbered.
install -d -m 0775 /mnt/OSDISK
[ -d "$REPO_DIR/osdisk" ] && cp -an "$REPO_DIR/osdisk/." /mnt/OSDISK/ 2>/dev/null || true
ln -sfn /mnt/OSDISK "$MPD_MUSIC/OSDISK"

# NAS mount root (mountmon manages the per-share submounts beneath it)
install -d -m 0755 /mnt/NAS
ln -sfn /mnt/NAS "$MPD_MUSIC/NAS"

# SATA / NVMe internal drives: sataSourceMount/nvmeSourceMount do `mkdir
# "<root>/<name>"` WITHOUT -p, so the root must pre-exist or the mount fails
# "mount point does not exist" (cfg_source shows "Mount error").
install -d -m 0755 /mnt/SATA
ln -sfn /mnt/SATA "$MPD_MUSIC/SATA"
install -d -m 0755 /mnt/NVME
ln -sfn /mnt/NVME "$MPD_MUSIC/NVME"

# USB: auto-mounted removable drives land in /media/<LABEL> (devmon, Phase 6/7).
# The library exposes them as the "USB" root folder via this symlink.
install -d -m 0755 /media
ln -sfn /media "$MPD_MUSIC/USB"
# A Debian install from USB/optical media leaves an empty /media/cdrom +
# apt.conf.d/00CDMountPoint behind. lib-config.php lists every /media entry as an
# auto-mounted USB drive, so it shows up as a phantom "cdrom ( | swap)".
rmdir /media/cdrom 2>/dev/null || true
rm -f /etc/apt/apt.conf.d/00CDMountPoint

# RADIO: per-station .pls files generated from the cfg_radio table
install -d -m 0775 "$MPD_MUSIC/RADIO"
if python3 "$REPO_DIR/www/util/station_manager.py" --regeneratepls --db "$SQLDB" >/dev/null 2>&1; then
	log "Generated RADIO station files"
else
	warn "RADIO station generation failed (station_manager.py)"
fi

# Playlists (Default Playlist, Favorites, ...) - copy without clobbering
install -d -m 0777 /var/lib/mpd/playlists
for p in "$REPO_DIR"/var/lib/mpd/playlists/*.m3u; do
	[ -e "$p" ] && cp -n "$p" /var/lib/mpd/playlists/
done

# Ownership: mpd (group 'audio') reads the library; the worker/web (www-data)
# and Samba write OSDISK/RADIO/playlists. Group-write to the audio group lets
# both sides cooperate.
chown -R www-data:audio /mnt/OSDISK "$MPD_MUSIC/RADIO"
chmod -R g+w /mnt/OSDISK "$MPD_MUSIC/RADIO"
chown -h www-data:audio "$MPD_MUSIC/OSDISK" "$MPD_MUSIC/NAS" 2>/dev/null || true
chown mpd:audio "$MPD_MUSIC"
chown -R mpd:audio /var/lib/mpd/playlists
chmod -R 0777 /var/lib/mpd/playlists

#----------------------------------------------------------------------------#
# Phase 5c - On-demand renderer plugins (AirPlay / Spotify) for non-arm64
#----------------------------------------------------------------------------#
# AirPlay and Spotify stay on-demand like moOde: a worker job runs
# plugin-updater.sh, which wgets $res_plugin_upd_url/<component>/<plugin>/
# update-<plugin>.zip and runs its update/install.sh, which BUILDS a moode-tagged
# .deb natively (what isAirPlayInstalled() requires: `dpkg-query | grep moode`).
# Two things break off the Pi: it installs a hardcoded `<pkg>_<ver>_arm64.deb`,
# and it resolves the home dir with `moodeutl -d -gv home_dir`, which reads a PHP
# session that is empty here (the script runs as root via the worker's sudo, and
# PHP refuses the www-data-owned session file). home_dir is deterministic, so
# resolve it as moodeutl's own getUserID() does: /home/<first /home entry>.
#
# So for non-arm64 we mirror the plugin zips locally with those two points
# patched, serve them over the existing nginx `location /`, and repoint
# res_plugin_upd_url at the copy. arm64 (Armbian) works against upstream
# unchanged. EVERY run re-checks what upstream publishes and re-packs on a change
# (sha256 stamp per plugin): the zip is what carries a renderer version bump, so
# a frozen mirror would pin the box to an old AirPlay forever. The run refuses to
# publish a mirror where either patch no longer applies.

PKG_ARCH="$(dpkg --print-architecture)"
if [ "$PKG_ARCH" != arm64 ]; then
	log "Phase 5c: on-demand renderer plugins (AirPlay/Spotify) x86 mirror"
	PLUG_BASE="https://raw.githubusercontent.com/moode-player/plugins/main"
	PLUG_DST="/var/www/plugins-x86"
	PLUG_TMP="$(mktemp -d)"
	plug_ok=1
	# The Peppy "moOde meters" skin pack joins the renderer plugins when the local
	# display is installed: it is platform-independent (PNG/config only, so the seds
	# below no-op), but mirroring it locally stops Configure > Peripherals "Install
	# moOde meters" hanging - the updater wgets the mirror with no timeout.
	PLUG_ENTRIES="renderer/v5-shairport-sync renderer/v8-librespot"
	[ "$INSTALL_LOCALDISPLAY" = 1 ] && PLUG_ENTRIES="$PLUG_ENTRIES peppydisplay/v4-moode-meters"
	for entry in $PLUG_ENTRIES; do
		plugin="${entry##*/}"                       # e.g. v5-shairport-sync
		mkdir -p "$PLUG_DST/$entry"
		plug_zip="$PLUG_DST/$entry/update-$plugin.zip"
		# Keep the "which upstream zip is this mirror made from" stamp out of the
		# nginx-served tree, next to the other provenance stamps.
		plug_sum="$NOPI_BUILT_DIR/plugin-mirror-$plugin"
		# Always ask upstream what it publishes NOW, but re-pack only when the zip
		# actually changed, so --update stays cheap (the fetch is tens of kB).
		if wget -q "$PLUG_BASE/$entry/update-$plugin.zip" -O "$PLUG_TMP/p.zip"; then
			up_sum=$(sha256sum "$PLUG_TMP/p.zip" | cut -d' ' -f1)
			if [ -f "$plug_zip" ] && [ "$up_sum" = "$(cat "$plug_sum" 2>/dev/null || true)" ]; then
				continue
			fi
			rm -rf "$PLUG_TMP/x" "$PLUG_TMP/new.zip"; mkdir -p "$PLUG_TMP/x"
			# Build the patched zip aside and only swap it in once verified, so a
			# repack that goes wrong leaves the last good mirror in place.
			if ( cd "$PLUG_TMP/x" \
				&& unzip -q -o "$PLUG_TMP/p.zip" \
				&& sed -i "s/_arm64\.deb/_${PKG_ARCH}.deb/g" update/install.sh \
				&& sed -i 's|^HOME_DIR=\$(moodeutl -d -gv home_dir)|HOME_DIR=/home/$(ls /home/ 2>/dev/null \| head -1)|' update/install.sh \
				&& ! grep -q '_arm64\.deb' update/install.sh \
				&& ! grep -q '^HOME_DIR=\$(moodeutl' update/install.sh \
				&& zip -q -r "$PLUG_TMP/new.zip" update ); then
				mv -f "$PLUG_TMP/new.zip" "$plug_zip"
				mkdir -p "$NOPI_BUILT_DIR"; echo "$up_sum" > "$plug_sum"
				# success marker (plugin-updater.sh fetches it after install; content
				# is irrelevant). Mirror upstream's if present, else synthesise one.
				wget -q "$PLUG_BASE/$entry/update-$plugin.txt" \
					-O "$PLUG_DST/$entry/update-$plugin.txt" 2>/dev/null \
					|| date > "$PLUG_DST/$entry/update-$plugin.txt"
				log "Mirrored $plugin from upstream (arch-patched for $PKG_ARCH)"
			else
				# `sed -i` exits 0 even when it matches nothing, so the two checks above
				# are what catch upstream renaming either patched line - otherwise we
				# publish an unpatched mirror and find out when the on-demand install
				# fails on an _arm64.deb that was never built. They assert the OUTCOME,
				# not that our sed fired, so upstream fixing either one on its own passes.
				warn "Could not arch-patch $plugin - upstream install.sh has changed;" \
					"kept the previous mirror, check update/install.sh in the plugin zip"
				plug_ok=0
			fi
		elif [ -f "$plug_zip" ]; then
			log "Kept the existing $plugin mirror (upstream plugins repo unreachable)"
		else
			warn "Could not fetch $plugin from upstream plugins repo"; plug_ok=0
		fi
	done
	rm -rf "$PLUG_TMP"
	chown -R www-data:www-data "$PLUG_DST"
	if [ "$plug_ok" = 1 ]; then
		sqlite3 "$SQLDB" "UPDATE cfg_system SET value='http://localhost/plugins-x86' WHERE param='res_plugin_upd_url'"
		log "Repointed res_plugin_upd_url -> local arch-patched plugin mirror"
	fi
fi

# The AirPlay plugin's install.sh runs `sysutil.sh upd-shairport-sync-conf` once, at
# install time, to uncomment moOde's defaults in /etc/shairport-sync.conf. Upstream
# adds new defaults there over time (r1033 added ignore_volume_control) and pushes
# them to existing Pi installs from the moode-player postinstall, which nopi never
# runs - so a box that installed the plugin earlier keeps a commented-out line, and
# apl-config.php's `sed -i 's/^KEY = .*;/.../'` then matches nothing: the UI setting
# saves to the DB and silently does not reach shairport-sync. Re-run the function on
# every install: every sed in it requires a leading `//` before the key, so it only
# ever acts on still-commented lines and never rewrites a value the user set.
if [ -f /etc/shairport-sync.conf ] && [ -x /var/www/util/sysutil.sh ]; then
	/var/www/util/sysutil.sh upd-shairport-sync-conf
	log "Re-applied moOde defaults to /etc/shairport-sync.conf"
fi

#----------------------------------------------------------------------------#
# Phase 5d - Local display (moOde WebUI / Peppy kiosk on an attached HDMI screen)
#----------------------------------------------------------------------------#
# The local display is an X11 + Chromium kiosk: localdisplay.service -> xinit ->
# ~/.xinitrc -> chromium --kiosk. The Pi image ships the X stack, a headless
# Debian does not. The worker OWNS the service, so it is deployed but NOT enabled.
if [ "$INSTALL_LOCALDISPLAY" = 1 ]; then
	log "Phase 5d: local display (X + Chromium kiosk)"
	# X server + the setuid Xorg.wrap (xserver-xorg-legacy) that lets the non-root
	# player user start X from the service, xinit, libinput (touch), xrandr/xset
	# (x11-xserver-utils), xinput (touchmon touch detection), expect (provides
	# `unbuffer`, used by start-xinput.sh), and Debian's chromium binary.
	$APT_INSTALL xserver-xorg xserver-xorg-legacy xserver-xorg-input-libinput \
		xinit x11-xserver-utils xinput expect chromium

	# Let the player user (not root) start the X server from the service.
	cat > /etc/X11/Xwrapper.config <<'EOF'
allowed_users=anybody
needs_root_rights=yes
EOF
	# X needs the player user in these groups for DRM/KMS and input device access.
	usermod -aG video,render,input,tty "$PLAYER_USER"

	# localdisplay.service: deploy moOde's unit and point User at the player user
	# (the worker re-applies User= and the -nocursor ExecStart on every startup).
	install -m 644 "$REPO_DIR/lib/systemd/system/localdisplay.service" /lib/systemd/system/localdisplay.service
	sed -i "s/^User=.*/User=$PLAYER_USER/" /lib/systemd/system/localdisplay.service

	# x86 ~/.xinitrc: the Pi xinitrc.default probes the screen with kmsprint and
	# /boot/firmware/config.txt, so deploy an xrandr equivalent. The --app / --kiosk
	# lines and the WebUI/Peppy branch mirror the Pi script so the worker's runtime
	# sed edits still apply. Built as a candidate first: the armhf post-patch below
	# must be applied before comparing with what is already on the box.
	XINITRC="/home/$PLAYER_USER/.xinitrc"
	XINITRC_NEW="$(mktemp)"
	cat > "$XINITRC_NEW" <<'EOF'
#!/bin/bash
# moOde local display (x86) - X11 + Chromium kiosk. Managed by moOde.
# Match the Pi xinitrc.default xset/DPMS setup EXACTLY: the worker's
# chkAttachedDisplayOnOff() reads `xset q | grep Monitor` ("Monitor is On/Off") to
# track screen power in cfg_system 'local_display_onoff'. That line only exists when
# DPMS is ENABLED. Disabling DPMS (xset -dpms) removes it -> the worker reads empty
# -> local_display_onoff='off' -> playerlib's capture-phase click handler (active when
# GLOBAL.chromium && local_display_onoff=='off') swallows EVERY tap -> transport dead.
# Keep DPMS on, like the Pi, so the detection works and the screensaver manages blanking.
xset s 600 0
xset +dpms
xset dpms 600 0 0

# Primary connected output + HDMI orientation (xrandr; the Pi path uses kmsprint).
HDMI_OUT=$(xrandr 2>/dev/null | awk '/ connected/{print $1; exit}')
HDMI_SCN_ORIENT=$(moodeutl -q "SELECT value FROM cfg_system WHERE param='hdmi_scn_orient'")
if [ "$HDMI_SCN_ORIENT" = "portrait" ]; then
	xrandr --output "$HDMI_OUT" --rotate left 2>/dev/null
else
	xrandr --output "$HDMI_OUT" --rotate normal 2>/dev/null
fi
# Window size = the screen's current (native/EDID-preferred) mode.
SCREEN_RES=$(xrandr 2>/dev/null | awk '/\*/{print $1; exit}')

WEBUI_SHOW=$(moodeutl -q "SELECT value FROM cfg_system WHERE param='local_display'")
PEPPY_SHOW=$(moodeutl -q "SELECT value FROM cfg_system WHERE param='peppy_display'")
PEPPY_TYPE=$(moodeutl -q "SELECT value FROM cfg_system WHERE param='peppy_display_type'")

# Touch screen monitor (auto-switch WebUI<->Peppy). Must launch BEFORE the
# WebUI/Peppy branch below: the WebUI branch ends in `exec chromium`, which
# replaces this shell, so anything after it would never run. Mirrors the Pi
# xinitrc.default ordering.
TOUCHMON_SVC=$(moodeutl -q "SELECT value FROM cfg_system WHERE param='touchmon_svc'")
TOUCHMON_TIMEOUT=$(moodeutl -q "SELECT value FROM cfg_system WHERE param='touchmon_timeout'")
if [ "$TOUCHMON_SVC" = "1" ]; then
	/var/www/daemon/touchmon.php "$TOUCHMON_TIMEOUT" &
fi

if [ "$WEBUI_SHOW" = "1" ]; then
	$(/var/www/util/sysutil.sh clearbrcache)
	# --user-agent: moOde's web app gates the local-display kiosk features
	# (on-screen keyboard, CoverView/screensaver coordination) on the UA
	# containing 'CrOS' (playerlib.js sets GLOBAL.chromium from it - Raspberry Pi
	# OS chromium reports 'X11; CrOS aarch64'). Stock Debian chromium reports
	# 'X11; Linux x86_64' -> GLOBAL.chromium=false -> no OSK ever pops. Spoof the
	# Pi's exact chromium-126 CrOS UA so the kiosk behaves byte-identically to the
	# Pi (only the local kiosk is affected; remote browsers send their own UA).
	exec chromium \
	--app="http://localhost/" \
	--user-agent="Mozilla/5.0 (X11; CrOS aarch64 14541.0.0) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/126.0.6478.164 Safari/537.36" \
	--window-size="${SCREEN_RES/x/,}" \
	--window-position="0,0" \
	--enable-features="OverlayScrollbar" \
	--no-first-run \
	--disable-infobars \
	--disable-session-crashed-bubble \
	--ozone-platform=x11 \
	--mute-audio \
	--kiosk
elif [ "$PEPPY_SHOW" = "1" ]; then
	if [ "$PEPPY_TYPE" = 'meter' ]; then
		cd /opt/peppymeter && python3 peppymeter.py
	else
		cd /opt/peppyspectrum && python3 spectrum.py
	fi
fi
EOF

	# armhf SBC kiosk (Allwinner H3 / Mali-400 lima): the kiosk paints a blank WHITE
	# page unless the sandbox is disabled - on this 32-bit ARM kernel it cannot start
	# the renderer (no JS error, just no frame). x86 and arm64 are fine, so scope
	# --no-sandbox to armhf; the kiosk only loads localhost. Inserted after 'exec
	# chromium', which survives the worker's regen (it rewrites --app/--kiosk only).
	case "$(uname -m)" in
		armv6l|armv7l)
			sed -i '/^[[:space:]]*exec chromium/a\	--no-sandbox \\' "$XINITRC_NEW"
			log "armhf: added --no-sandbox to the Chromium kiosk (renderer starts blank otherwise)"
			;;
	esac

	# GENERATED, and must keep tracking the template above: a stale copy does not
	# fail visibly, it kills the touch screen quietly (the xset/DPMS note at the top
	# of the heredoc). Always converge, never silently. Differing is the nominal case
	# whenever the template changed, hence log and not warn.
	if [ -f "$XINITRC" ] && ! cmp -s "$XINITRC" "$XINITRC_NEW"; then
		XINITRC_BAK="$XINITRC.bak-$(date +%Y%m%d-%H%M%S)"
		cp -a "$XINITRC" "$XINITRC_BAK"
		log "Local display: .xinitrc differs from the template - previous kept as $(basename "$XINITRC_BAK")"
	fi
	if ! cmp -s "$XINITRC" "$XINITRC_NEW" 2>/dev/null; then
		install -m 0755 -o "$PLAYER_USER" -g "$PLAYER_USER" "$XINITRC_NEW" "$XINITRC"
	fi
	rm -f "$XINITRC_NEW"

	# Peppy Meter/Spectrum: the apps read the FIFO libpeppyalsa feeds and render via
	# pygame/SDL. Upstream, not in Debian - clone to /opt like the Pi image, and
	# symlink their config.txt at /etc/peppy*/config.txt (what the worker edits).
	$APT_INSTALL python3-pygame python3-pil git   # PeppySpectrum needs PIL (Pillow)
	# PeppyMeter tracks project-owner again now that the live dB-gain source
	# (volume.gain.db.source, what lets the meter follow a hardware knob; moOde feeds
	# it via /tmp/peppy_gain_db) is upstream. An existing clone is switched IN PLACE,
	# which also migrates boxes off the temporary fork branch: the moOde meter skins
	# are installed into /opt/peppymeter at runtime and untracked, so a re-clone would
	# delete them. PeppySpectrum: stock.
	PM_URL="https://github.com/project-owner/PeppyMeter.git"
	PM_BRANCH="master"
	if [ ! -e /opt/peppymeter/.git ]; then
		rm -rf /opt/peppymeter
		git clone --depth 1 --branch "$PM_BRANCH" "$PM_URL" /opt/peppymeter >/dev/null 2>&1
	elif [ "$(git -C /opt/peppymeter remote get-url origin 2>/dev/null)" != "$PM_URL" ] ||
			[ "$(git -C /opt/peppymeter branch --show-current 2>/dev/null)" != "$PM_BRANCH" ]; then
		git -C /opt/peppymeter remote set-url origin "$PM_URL"
		if git -C /opt/peppymeter fetch --depth 1 origin "$PM_BRANCH" >/dev/null 2>&1 &&
				git -C /opt/peppymeter checkout -B "$PM_BRANCH" FETCH_HEAD >/dev/null 2>&1; then
			log "PeppyMeter: switched existing clone to $PM_BRANCH (live meter gain)"
		else
			warn "PeppyMeter: could not switch to $PM_BRANCH; the meter will not follow a hardware volume knob"
		fi
	fi
	[ -e /opt/peppyspectrum/.git ] || { rm -rf /opt/peppyspectrum; git clone --depth 1 "https://github.com/project-owner/PeppySpectrum.git" /opt/peppyspectrum >/dev/null 2>&1; }
	# moOde ships its OWN spectrum.py: upstream runs the draw loop in a background
	# Thread, which on x86/X11/SDL2 never presents -> a fully BLACK spectrum (SDL
	# must render on the MAIN thread; the Pi KMS path tolerated it, X11 does not).
	# moOde's version (PeppySpectrum issue #1 fix) calls clean_draw_update() in the
	# main loop. Overlay it on the clone exactly as moOde's pkgbuild build.sh does.
	# Meter is unaffected - moOde uses upstream peppymeter.py as-is.
	if [ -d /opt/peppyspectrum ]; then
		if curl -fsSL "https://raw.githubusercontent.com/moode-player/pkgbuild/main/packages/peppy-spectrum/spectrum.py" \
				-o /opt/peppyspectrum/spectrum.py; then
			log "Peppy Spectrum: applied moOde's main-thread draw fix (spectrum.py)"
		else
			warn "could not fetch moOde spectrum.py; Spectrum may render black on x86"
		fi
	fi
	install -d -m 755 /etc/peppymeter /etc/peppyspectrum
	# Configure > Peppy seds the user's settings straight into these files, so lay the
	# template down only when there is nothing to preserve - reinstalling it every run
	# would reset skin, resolution and frame rate behind the user's back. On an
	# existing install, only add the keys the template has gained.
	for p in peppymeter peppyspectrum; do
		[ -f "/etc/$p/config.txt" ] ||
			install -m 644 "$REPO_DIR/etc/$p/config.sed.txt" "/etc/$p/config.txt"
	done
	if ! grep -q '^volume.gain.db.source' /etc/peppymeter/config.txt; then
		sed -i '/^volume.max.in.pipe/a volume.gain.db = 0\nvolume.gain.db.source = /tmp/peppy_gain_db' \
			/etc/peppymeter/config.txt
		log "Peppy Meter: added the live meter-gain keys to the existing config.txt"
	fi
	[ -d /opt/peppymeter ]   && ln -sf /etc/peppymeter/config.txt   /opt/peppymeter/config.txt
	[ -d /opt/peppyspectrum ] && ln -sf /etc/peppyspectrum/config.txt /opt/peppyspectrum/config.txt
	( [ -d /opt/peppymeter ] && [ -d /opt/peppyspectrum ] ) \
		&& log "Peppy Meter/Spectrum apps deployed (/opt/peppy*)" \
		|| warn "Peppy app clone failed; Meter/Spectrum unavailable (WebUI display still works)"

	systemctl daemon-reload
	log "Local display ready (localdisplay.service deployed + disabled; worker controls it)"
fi

#----------------------------------------------------------------------------#
# Phase 6 - systemd service for the worker daemon
#----------------------------------------------------------------------------#

log "Phase 6: worker service"

# On Raspberry Pi OS worker.php is launched from /etc/rc.local. On Debian we
# use a dedicated systemd unit instead. worker.php is the moOde startup and job
# processor daemon; it generates /etc/mpd.conf from the DB on first run.
cat > /etc/systemd/system/moode-worker.service <<EOF
[Unit]
Description=moOde audio player worker daemon
After=network-online.target nginx.service php${PHP_VER}-fpm.service mpd.service
Wants=network-online.target

[Service]
# worker.php daemonizes itself (pcntl_fork) and writes the child PID to
# /run/worker.pid, so use forking + PIDFile rather than simple (otherwise
# systemd reaps the whole cgroup when the parent exits).
Type=forking
PIDFile=/run/worker.pid
# Run the worker as www-data, the SAME user as php-fpm and nginx. REQUIRED for
# PHP session sharing: Debian's "files" session handler only lets a process open
# a session file owned by its own uid, so a root worker cannot read the
# www-data-owned session - worker and web never share state, config fields render
# blank and queued jobs never run. (On the Pi the root worker creates the session
# first at boot, so the split happens to work there.) Every privileged op already
# goes through sudo (sysCmd in inc/common.php), so root is not needed.
User=www-data
Group=www-data
# /run/worker.pid lives in root-owned /run, which www-data cannot create files
# in. Pre-create it owned by the service user. The leading + makes systemd run
# this line with full privileges (as root) even though User= is www-data.
ExecStartPre=+/usr/bin/install -m 660 -o www-data -g www-data /dev/null /run/worker.pid
# Same idea for /var/log/moode.log. The worker truncates it via sudo at startup;
# if the file is ABSENT at that instant root creates it root:root, the www-data
# worker's first workerLog() cannot reopen it -> fatal fwrite -> crash-loop ->
# wrkready stuck 0 -> blank WebUI. It can go missing under log2ram across a
# network restart (seen on the OPi3 LTS). Guarantee it exists AND is
# www-data-owned; never truncate, so an existing crash log survives.
ExecStartPre=+/bin/sh -c 'test -e /var/log/moode.log || /usr/bin/install -m 666 -o www-data -g www-data /dev/null /var/log/moode.log; chown www-data:www-data /var/log/moode.log; chmod 666 /var/log/moode.log'
ExecStart=/var/www/daemon/worker.php
# The worker STARTS the renderers at startup (worker.php: startBluetooth,
# startAirPlay, ...) without stopping them first - on the Pi it is launched from
# rc.local at boot only, so nothing is ever already running. Here it is a service,
# so every restart (install.sh --update, a manual one, Restart= after a crash)
# would leave the previous session's renderers alive and the worker would start a
# SECOND set: two readers splitting the bytes of one metadata FIFO, two
# squeezelites... Measured after an --update: two aplmeta-reader chains at once.
ExecStopPost=+/usr/local/bin/nopi-stop-renderers
Restart=on-failure
RestartSec=5

[Install]
WantedBy=multi-user.target
EOF

# Same list and the same per-renderer entry point as moOde's own
# stopAllRenderers() (inc/renderer.php), which is not reachable from the CLI on
# its own. Never fails the unit: a stop that errors would leave it in `failed`.
cat > /usr/local/bin/nopi-stop-renderers <<'EOF'
#!/bin/sh
# Stop the renderers the moOde worker started. Called from moode-worker.service.
# The watchdog goes first, or it restarts what we just stopped.
killall -s9 watchdog.sh 2>/dev/null
DB=/var/local/www/db/moode-sqlite3.db
for pair in btsvc:bluetooth airplaysvc:airplay spotifysvc:spotify \
	deezersvc:deezer upnpsvc:upnp slsvc:squeezelite pasvc:plexamp rbsvc:roonbridge; do
	param=${pair%%:*}
	renderer=${pair##*:}
	[ "$(sqlite3 "$DB" "SELECT value FROM cfg_system WHERE param='$param'" 2>/dev/null)" = 1 ] || continue
	/var/www/util/restart-renderer.php --"$renderer" --stop >/dev/null 2>&1
done
exit 0
EOF
chmod 755 /usr/local/bin/nopi-stop-renderers

# www-data's passwordless sudo (required by the worker/web) is granted by
# moOde's own /etc/sudoers.d/010_www-data-nopasswd, deployed in Phase 3b.

# USB auto-mount daemon: devmon (udevil) replaces moOde's udisks-glue, which needs
# the udisks1 gone from Trixie. Mounts to /media/<LABEL> and runs hooks. Root,
# because the hooks call automount.sh, which edits smb.conf + /etc/exports and
# restarts smbd/nfs (udisks-glue is root on the Pi for the same reason). %%d ->
# /media/<LABEL>; mpc poke so the USB root rescans.
cat > /etc/systemd/system/moode-devmon.service <<'EOF'
[Unit]
Description=moOde USB drive auto-mounter (devmon)
After=multi-user.target

[Service]
Type=simple
ExecStart=/usr/bin/devmon --exec-on-drive "/var/www/util/automount.sh add_mount_udisks %%d; /usr/bin/mpc -q update" --exec-on-unmount "/var/www/util/automount.sh remove_mount_udisks %%d; /usr/bin/mpc -q update" --exec-on-remove "/var/www/util/automount.sh remove_mount_udisks %%d; /usr/bin/mpc -q update"
Restart=on-failure
RestartSec=5

[Install]
WantedBy=multi-user.target
EOF

# DAC quiet start (Configure > Audio, 'dac_prime'). A DAC with no output mute
# relay hisses at startup until fed a PCM stream, and moOde never feeds it at boot
# (MPD restores state=stop, close_on_pause never opens the device). Play ~1s of
# silence the moment the card appears, from a udev rule on the sound-card ADD
# event (KERNEL=="controlC*" - the control node is created last, so it is the
# reliable sync point). Covers boot AND hot-plug with no card-wait loop.
#
# The rule ships DISABLED and is renamed .disabled <-> .rules AT RUNTIME BY THE
# WORKER (applyDacPrime(), from cfg_system dac_prime): the worker is the
# authority, and the persistent rule then fires at enumeration on every boot
# without waiting for it. The installer only deploys, never activates.
install -m 755 "$REPO_DIR/usr/local/bin/moode-dac-prime"                      /usr/local/bin/moode-dac-prime
install -m 644 "$REPO_DIR/etc/udev/rules.d/89-moode-dac-prime.rules.disabled" /etc/udev/rules.d/89-moode-dac-prime.rules.disabled
# Drop any stale ACTIVE copy (older/inline content) so the worker re-enables the rule
# with fresh content on next boot per the DB. Clean up the earlier boot-ordered
# variants too (systemd oneshot + inline .sh script).
systemctl disable --now moode-dac-prime.service >/dev/null 2>&1 || true
rm -f /etc/udev/rules.d/89-moode-dac-prime.rules \
	/etc/systemd/system/moode-dac-prime.service /usr/local/bin/moode-dac-prime.sh
udevadm control --reload-rules 2>/dev/null || true

systemctl daemon-reload

#----------------------------------------------------------------------------#
# Phase 7 - Enable and start services
#----------------------------------------------------------------------------#

log "Phase 7: starting services"

systemctl enable --now nginx "php${PHP_VER}-fpm" avahi-daemon
# Do NOT let systemd autostart mpd - match the Pi, where mpd.service is not
# enabled and the worker starts it during its own startup (After=network-online).
# Autostarted, mpd comes up BEFORE the network and restores its saved state: on a
# RADIO STREAM it opens the URL with no DNS yet -> "Could not resolve host" -> it
# drops the song, and the worker's later `systemctl start mpd` is a no-op, so a
# dead player at boot. It also keeps an offline box from waiting on the network to
# play its local library. Disable the SOCKET too (activation starts mpd early
# just the same), as moOde's own postinstall does.
rm -f /etc/systemd/system/mpd.service.d/override.conf 2>/dev/null || true
systemctl disable --now mpd.service mpd.socket >/dev/null 2>&1 || true
systemctl daemon-reload
# Restart the config-bearing services so a re-run applies updated configs
# (nginx.conf, php.ini/pool, avahi service files) rather than leaving the old
# ones loaded. mpd is (re)started by the worker during its own startup.
systemctl restart nginx "php${PHP_VER}-fpm" avahi-daemon 2>/dev/null || true

# Name resolution (WINS/NetBIOS), tolerant - a failure must not abort the install.
# The shipped unit only has `After=network.target`, reached under NM BEFORE any
# interface has a carrier, so winbindd binds its interface list too early and its
# broadcast lookups go out a not-yet-up interface: a NAS referenced by NETBIOS
# NAME fails to mount with "could not resolve address" (nmblookup still works, it
# re-detects interfaces per call). Order it after network-online instead.
install -d -m 755 /etc/systemd/system/winbind.service.d
cat > /etc/systemd/system/winbind.service.d/10-network-online.conf <<'EOF'
[Unit]
After=network-online.target
Wants=network-online.target
EOF
systemctl daemon-reload
systemctl enable --now winbind 2>/dev/null || true
# Re-bind to the now-up interfaces on a re-run too (the box may have been mid-boot
# with no carrier when winbind last started). Harmless when already correct.
systemctl restart winbind 2>/dev/null || true

# USB auto-mount daemon (devmon). enable --now so already-inserted drives mount
# at install time and future insertions are handled. restart on re-run to pick
# up an updated unit. Tolerant: never abort the install on failure.
systemctl enable moode-devmon.service 2>/dev/null || true
systemctl restart moode-devmon.service 2>/dev/null || true

# Renderer / bridge services are worker-controlled and OFF by default, but their
# Debian packages auto-enable their units on install: bluealsa-aplay left running
# hijacks the audio chain to "Bluetooth -> Device" instead of "MPD -> plughw ->
# Device", and squeezelite would hold the DAC. Fresh install only - on --update
# the worker owns these per config, and disable --now would STOP a renderer it
# enabled (needless flap / playback interruption).
if [ "$UPDATE" != 1 ]; then
	for svc in squeezelite bluetooth bluealsa bluealsa-aplay bt-agent minidlna upmpdcli shairport-sync; do
		systemctl disable --now "$svc" 2>/dev/null || true
	done
fi

# Triggerhappy (USB volume knob) is worker-controlled and OFF by default:
# usb_volknob is a SESSION-only flag, and its real persistence IS triggerhappy's
# systemd enable state (the worker's job does enable+start / disable+stop).
# Debian's package auto-enables the unit, which would arm the knob by default.
[ "$UPDATE" = 1 ] || systemctl disable --now triggerhappy 2>/dev/null || true

# fluidsynth arrives transitively (libfluidsynth-dev is an mpd build-dep) and its
# package ships a globally-enabled systemd USER service. On login it grabs the
# default ALSA device - the USB DAC - so it contends for the card and makes
# moOde's format probe report "Device is busy, unable to detect formats". moOde
# never uses the daemon (MPD's MIDI decoder links libfluidsynth3 directly).
if [ "$UPDATE" != 1 ]; then
	systemctl --global disable fluidsynth.service 2>/dev/null || true
	pkill -x fluidsynth 2>/dev/null || true
fi

# shellinabox: moOde's own unit runs shellinaboxd with -t (plain HTTP) + its
# terminal CSS, while Debian ships only an init.d script that systemd-sysv-
# generator turns into an SSL unit with the stock CSS - so the WebSSH "Open" link
# (http://host:4200) would hit an HTTPS-only daemon and render blank. A real
# .service overrides a generated one of the same name.
install -m 644 "$REPO_DIR/lib/systemd/system/shellinabox.service" /lib/systemd/system/shellinabox.service
systemctl daemon-reload

# Sharing servers + Windows discovery + web terminal: same story as the renderers,
# their packages auto-enable+start on install but the worker owns their running
# state per the UI config (smbd/nmbd on fs_smb, nfs-server on fs_nfs, and
# worker.php actively disables wsdd2/smbd/nmbd if it finds them enabled). Disable
# them here, before the worker, matching the Pi image: installed, disabled.
if [ "$UPDATE" != 1 ]; then
	for svc in smbd nmbd wsdd2 nfs-kernel-server shellinabox; do
		systemctl disable --now "$svc" 2>/dev/null || true
	done
fi

if [ "$NO_WORKER" -eq 1 ]; then
	warn "Skipping worker (--no-worker). Start it later: systemctl start moode-worker"
else
	# The ALSA conf.d batch (Phase 3) re-lays the stock templates, dropping the lines
	# the worker owns (_audioout's slave.pcm, peppy.conf's ctl name/card, _peppyout's
	# slave.pcm). A plain restart never rebuilds them - updMpdConf() only runs when
	# mpd.conf is missing or mangled - so the routing would stay stock until the next
	# Audio config change, Peppy meter dead meanwhile. Blanking mpd.conf makes the
	# worker take that path on startup.
	# Blank in place, never `rm`: the www-data worker fopen()s /etc/mpd.conf directly
	# and cannot create it in root-owned /etc, so deleting it turns updMpdConf() into
	# an fwrite(false) TypeError and crash-loops the worker. Two comment lines keep
	# the "managed by moOde" marker off line 2, which is what the worker tests.
	#
	# FIRST stop a worker left running by a previous install: it keeps a background
	# watchdog.sh that does `systemctl start mpd` the moment it sees MPD down. Measured
	# on a live box, MPD came back 5 s later ON THE BLANK CONFIG - 0 songs, queue not
	# restored, "no such mixer control" - and the new worker's own `systemctl start
	# mpd` is a no-op on a running MPD, so the box stays on the empty config. Kill
	# watchdog.sh by name too: a stray one from an older unit-less start is not in the
	# unit's cgroup.
	systemctl stop moode-worker.service 2>/dev/null || true
	pkill -f '/var/www/daemon/watchdog.sh' 2>/dev/null || true
	printf '#\n# Regenerated by the worker on startup (installer re-laid the ALSA templates)\n' \
		> /etc/mpd.conf
	# Stop MPD too: it reads the ALSA chain once, at start, and the worker only ever
	# `start`s it - so a hot --update would leave MPD on the stale pre-update chain
	# (peppyalsa out of the path, needles frozen). No-op on a fresh install.
	systemctl stop mpd 2>/dev/null || true
	# restart, not enable --now: on a re-run the worker may be running against the OLD
	# database. It sets wrkready=1 only during startup, and engine-mpd.php returns
	# empty (dead WebUI) until it is 1 - especially after --reset-db, which zeroes it.
	systemctl enable moode-worker.service
	systemctl restart moode-worker.service

	# The worker only ever `start`s MPD, so an MPD that came up before mpd.conf was
	# regenerated keeps the stale config for good. Wait for the managed file, then
	# try-restart: a no-op when MPD is down, a restart when it is up. Closes the race
	# for good instead of trusting that every possible starter was killed above; MPD
	# saves and restores its queue across the restart.
	for _i in $(seq 1 30); do
		if [ -s /etc/mpd.conf ] && grep -q 'managed by moOde' /etc/mpd.conf; then break; fi
		sleep 1
	done
	if grep -q 'managed by moOde' /etc/mpd.conf 2>/dev/null; then
		systemctl try-restart mpd 2>/dev/null || true
	else
		warn "Worker did not regenerate /etc/mpd.conf within 30s - check journalctl -u moode-worker"
	fi
fi

# --------------------------------------------------------------------------
# Config-file parity guard (vs the Pi moode-player package conffiles)
# --------------------------------------------------------------------------
# The Pi package ships a fixed set of default config files (its dpkg conffiles).
# This installer reproduces that set; the check below WARNS (never aborts) when
# one is missing, so drift - a broken deploy step, a new upstream conffile after a
# rebase - is caught here rather than as a runtime crash. Deliberately EXCLUDED:
# the Pi-hardware ones (I2S overlays, 99-vc4.conf) and /etc/moode-apt-mark.conf
# (package-update infra handled differently here).
EXPECTED_CONF=(
	/etc/alsa/conf.d/_audioout.conf /etc/alsa/conf.d/_peppyout.conf
	/etc/alsa/conf.d/_sndaloop.conf /etc/alsa/conf.d/alsaequal.conf
	/etc/alsa/conf.d/btstream.conf /etc/alsa/conf.d/crossfeed.conf
	/etc/alsa/conf.d/eqfa12p.conf /etc/alsa/conf.d/invpolarity.conf
	/etc/alsa/conf.d/trx_send.conf
	/etc/avahi/services/moode.service /etc/avahi/services/samba.service
	/etc/deezer/deezer.toml /etc/squeezelite.conf
	/etc/nginx/moode-locations.conf /etc/nginx/proxy.conf
	/etc/nginx/ssl.conf /etc/nginx/dhparams.pem
	/etc/triggerhappy/triggers.d/media.conf
	/etc/systemd/system/plexamp.service
	/etc/sudoers.d/010_moode /etc/sudoers.d/010_www-data-nopasswd
	/etc/modprobe.d/8192cu.conf /etc/modprobe.d/8812au.conf
	/etc/mpd.conf
)
if [ "$INSTALL_BLUETOOTH" = 1 ]; then
	EXPECTED_CONF+=(
		/etc/bluealsaaplay.conf /etc/bluetooth/main.conf
		/etc/systemd/system/bt-agent.service
		/etc/systemd/system/bluealsa-aplay@.service
		/etc/systemd/system/bluealsa.service
		/etc/udev/rules.d/10-a2dp-autoconnect.rules
	)
fi
if [ "$INSTALL_SQUEEZELITE" = 1 ]; then
	EXPECTED_CONF+=( /etc/systemd/system/squeezelite.service )
fi
_missing=()
for f in "${EXPECTED_CONF[@]}"; do [ -e "$f" ] || _missing+=("$f"); done
# peppy.conf and peppy.conf.hide are ONE conffile in two states: the worker deletes
# whichever contradicts the Peppy setting, so exactly one exists at runtime. Check
# the PAIR - listing them individually warned on every install with Peppy enabled.
if [ ! -e /etc/alsa/conf.d/peppy.conf ] && [ ! -e /etc/alsa/conf.d/peppy.conf.hide ]; then
	_missing+=("/etc/alsa/conf.d/peppy.conf (or .hide)")
fi
if [ "${#_missing[@]}" -gt 0 ]; then
	warn "Config-file parity: ${#_missing[@]} expected default file(s) MISSING:"
	for f in "${_missing[@]}"; do warn "  - $f"; done
else
	log "Config-file parity: all ${#EXPECTED_CONF[@]} expected default config files present"
fi

# --------------------------------------------------------------------------
# Runtime check: which libasound MPD actually loaded
# --------------------------------------------------------------------------
# Phase 1j packages the patched alsa-lib and retires the hand-built /opt override.
# Only the running process can prove the override is gone: integrity tools answer
# "are the FILES intact?", never "what did the PROCESS map?" - a drop-in plus a
# /opt prefix keeps `dpkg -V` clean by design, which once cost an evening of wrong
# conclusions. Read the mapping itself. Services are up by now (Phase 7).
_alsa_pkg_ver="$(dpkg-query -W -f='${Version}' libasound2t64 2>/dev/null || true)"
# Ask systemd for the PID, not pgrep: this runs right after Phase 7's restarts, and
# a name match falls into the restart window (measured: "mpd not running" while it
# was up). MainPID is 0 until the new process exists, hence the couple of seconds.
_mpd_pid=0
for _i in 1 2 3; do
	_mpd_pid="$(systemctl show -p MainPID --value mpd 2>/dev/null || echo 0)"
	if [ "${_mpd_pid:-0}" != 0 ]; then break; fi
	sleep 1
done
_alsa_mapped=""
if [ "${_mpd_pid:-0}" != 0 ] && [ -r "/proc/$_mpd_pid/maps" ]; then
	_alsa_mapped="$(awk '/libasound\.so/ {print $6; exit}' "/proc/$_mpd_pid/maps" 2>/dev/null || true)"
fi
if [ -z "$_alsa_mapped" ]; then
	log "libasound: package ${_alsa_pkg_ver:-not installed} (mpd not running - mapping not verified)"
else
	case "$_alsa_mapped" in
		/usr/lib/*)
			log "libasound: mpd loads $_alsa_mapped (package ${_alsa_pkg_ver:-unknown})" ;;
		*)
			warn "libasound: mpd loads $_alsa_mapped - NOT the packaged library"
			warn "  (package ${_alsa_pkg_ver:-unknown}). An override is still in charge:"
			warn "  systemctl cat mpd | grep -i environment ; ls /opt" ;;
	esac
fi

# Remove the temporary build swapfile we created for low-RAM boards (the heavy
# builds are done; zram remains for runtime). Only touches a swapfile WE created.
if [ -n "$NOPI_BUILD_SWAP" ] && grep -q "^$NOPI_BUILD_SWAP " /proc/swaps; then
	swapoff "$NOPI_BUILD_SWAP" 2>/dev/null && rm -f "$NOPI_BUILD_SWAP" \
		&& log "Removed temporary build swapfile ($NOPI_BUILD_SWAP)"
fi

IP="$(hostname -I | awk '{print $1}')"
log "Done."
echo
echo "  WebUI:   http://${IP:-<this-host>}/"
echo "  Worker:  journalctl -u moode-worker -f"
# Name THIS run's own log here, not just at the start: by now it is thousands of
# lines up the scrollback, and "Logs:" alone sent readers to the runtime log.
echo "  Install: $INSTALL_LOG"
echo "  Runtime: /var/log/moode.log  (moodeutl -l)"
echo
# First-run guidance, not a warning - and pointless on --update, where the output
# device was picked long ago. A yellow [!] on every single run teaches the reader to
# skip yellow lines, which is exactly how a real warning gets missed.
if [ "$UPDATE" != 1 ]; then
	log "First boot: open the WebUI, go to Configure > Audio and pick your USB/HDMI"
	log "output device. Pi-only options (I2S, GPIO, LCD) are hidden on this platform."
	echo
fi
# net.ifnames=0 (Phase 3b) renames enpXsY/end0 -> eth0/wlan0 only on the NEXT boot,
# and until then NM's eth0/wlan0 keyfiles don't match the live interface. So a
# reboot is needed only while that rename is pending - i.e. the default-route
# interface still carries a predictable name; once it is eth0/wlan0 a pure --update
# needs none. The rename can change the DHCP lease, so reconnect by .local if so.
CUR_IFACE="$(ip -o -4 route show default 2>/dev/null | awk '{print $5; exit}')"
case "$CUR_IFACE" in
	eth*|wlan*)
		if [ "$UPDATE" = 1 ]; then
			log "Update complete. No reboot needed."
		else
			warn "REBOOT RECOMMENDED to validate cold-boot startup: sudo reboot"
		fi
		;;
	*)
		warn "REBOOT REQUIRED: 'sudo reboot' now. Interface '${CUR_IFACE:-enpXsY}' will be"
		warn "renamed to eth0/wlan0 (net.ifnames=0), which CAN CHANGE THIS HOST'S IP ADDRESS."
		warn "If ${IP:-the current IP} stops responding after the reboot, reconnect at:"
		warn "  http://$(hostname).local/"
		;;
esac
