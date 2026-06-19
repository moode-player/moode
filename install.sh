#!/bin/bash
#
# SPDX-License-Identifier: GPL-3.0-or-later
#
# moOde audio player - experimental installer for generic Debian x86_64 (and
# other non-Pi platforms such as Armbian on arm64).
#
# This is NOT the official image build. moOde normally ships as a Raspberry Pi
# OS image produced by the pi-gen based imgbuild repo. This script instead
# installs the moOde stack on top of an already running Debian 13 (Trixie),
# relying on the runtime isPi() platform detection added to the codebase so the
# Pi-only logic (config.txt overlays, vcgencmd, GPIO/I2S HATs, LED/fan control)
# is skipped automatically.
#
# Scope: core functionality only - WebUI + MPD + ALSA output via USB / HDMI /
# onboard audio. Advanced features (Bluetooth, AirPlay, UPnP, DLNA, Squeezelite,
# Roon, multiroom) are installed only when enabled in the CONFIG section below.
#
# Requirements:
#   - Fresh Debian 13 (Trixie) x86_64 (or arm64), with internet access.
#   - A normal login user at UID 1000 (the first user created by the Debian
#     installer). worker.php derives the player user from `grep 1000:1000`.
#   - Run as root:  sudo ./install.sh
#   - The frontend must be built first on a machine with Node 18:
#       npm install && npx gulp deploy --test --all
#     which produces build/dist/. This script deploys from there.
#
# This installer is idempotent: re-running it re-copies files and re-applies
# config without destroying an existing database (unless --reset-db is passed).
#

set -euo pipefail

# Deterministic command output. The installer greps the output of tools like
# `apt-cache policy` to make decisions; on a non-English box those strings are
# LOCALISED (e.g. fr_FR prints "Candidat :" not "Candidate:"), which silently
# broke the upmpdcli candidate check -> UPnP wrongly skipped on every French
# install even though the package + repo + gpg key were all fine. Force C.UTF-8
# (English messages, UTF-8 kept) for the whole run so every such grep is stable -
# the same reason moOde's own sysCmd() always runs commands under LC_ALL=C.
export LC_ALL=C.UTF-8 LANG=C.UTF-8

#----------------------------------------------------------------------------#
# CONFIG
#----------------------------------------------------------------------------#

# Renderer/feature groups. moOde installs almost everything by default in its
# image, so the stock-Debian renderers default to ON here to match (set to 0 for
# a leaner install). AirPlay + Spotify follow moOde's on-demand model (installed
# when the feature is enabled in the UI), so they stay OFF here.
INSTALL_BLUETOOTH=1      # bluez, bluez-alsa for BT audio
INSTALL_AIRPLAY=0        # shairport-sync - on-demand (enabled via the UI)
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

# Mirror the whole run to a log file beside the script (discoverable - it sits in the
# clone dir, typically under the player's home - rather than in /var/log, which holds
# moOde's RUNTIME logs (moode*.log), not this installer's output). Still prints to the
# terminal via tee. Override the path with INSTALL_LOG=/path ./install.sh.
INSTALL_LOG="${INSTALL_LOG:-$REPO_DIR/install.log}"
exec > >(tee "$INSTALL_LOG") 2>&1
printf '\033[1;32m==>\033[0m install log: %s (%s)\n' "$INSTALL_LOG" "$(date '+%Y-%m-%d %H:%M:%S')"

#----------------------------------------------------------------------------#
# Phase 0 - Preflight checks
#----------------------------------------------------------------------------#

log "Phase 0: preflight checks"

# Must run as root. On a minimal Debian, sudo may not be installed and the player
# user is not yet a sudoer (this installer adds them to the sudo group), so on the
# very first run use `su` / a root shell; subsequent runs can use sudo.
[ "$(id -u)" -eq 0 ] || die "Run as root: 'sudo $0' or, on a fresh minimal Debian without sudo, 'su -c \"$0 $*\"'"

if [ -f /etc/debian_version ]; then
	DEB_MAJOR="$(cut -d. -f1 /etc/debian_version 2>/dev/null || echo '?')"
	log "Debian version: $(cat /etc/debian_version)"
	[ "$DEB_MAJOR" = "13" ] || warn "Tested on Debian 13 (Trixie); detected '$DEB_MAJOR'. Continuing."
else
	warn "Not a Debian system (no /etc/debian_version). Continuing at your own risk."
fi

PLAYER_USER="$(awk -F: '$3==1000{print $1; exit}' /etc/passwd || true)"
[ -n "$PLAYER_USER" ] || die "No UID 1000 user found. Create your normal login user first."
log "Player user (UID 1000): $PLAYER_USER"

[ -f "$SQLDB_SCHEMA" ] || die "Missing DB schema: $SQLDB_SCHEMA"

#----------------------------------------------------------------------------#
# Phase 0b - Build the web app (gulp) if needed
#----------------------------------------------------------------------------#
# install.sh deploys the web app from build/dist/, which is gulp output (not
# committed). Rather than make the user build it by hand, build it here when it is
# missing (a fresh `git clone`) or when --update forces a refresh. The frontend
# build needs Node 18 specifically (the gulp 4 pipeline); pin the exact validated
# 18.20.8 via nvm pulled from nodejs.org (which keeps every release forever, unlike
# the now-EOL Node 18 apt repos). nvm + node + node_modules are kept so --update
# rebuilds are fast and reproducible (npm ci from the committed package-lock.json).
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
	# `gulp deploy` only COPIES the bundles from app.dest; it does NOT build them.
	# `gulp build` is what minifies+bundles CSS/JS into app.dest. On a fresh clone
	# (app.dest empty) deploy alone ships only un-bundled assets -> an unstyled
	# "pure HTML" WebUI. So build THEN deploy.
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
	# sudo: moOde's entire privilege model runs on it (sysCmd() = `sudo ...`, the
	# worker/web's passwordless www-data sudoers). RaspiOS and Debian cloud images
	# ship it, but a minimal Debian install with a root password set does NOT, so
	# the worker would be unable to run any privileged op. Install it explicitly.
	sudo
	nginx
	php-fpm php-cli php-sqlite3 php-curl php-gd php-xml php-zip php-mbstring php-yaml
	mpd mpc
	alsa-utils
	# ALSA DSP plugins for moOde's audio effects (Configure > Audio):
	#   libasound2-plugin-equal - the `type equal` plugin behind the Graphic EQ
	#                             (alsaequal), driving the CAPS Eq10 LADSPA filter
	#   caps                    - CAPS LADSPA plugin pack (Eq10 for the Graphic EQ,
	#                             EqFA4p). NB: the 12-band Parametric EQ (eqfa12p ->
	#                             label EqFA12p, id 2611) is a moOde extension NOT in
	#                             stock caps - it needs caps=*moode1 (built like
	#                             alsa-cdsp); Graphic EQ + crossfeed work with stock.
	#   bs2b-ladspa             - Bauer stereophonic-to-binaural (the Crossfeed DSP)
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
	# WiFi tooling moOde shells out to: iw (scan), wpa_passphrase (wpasupplicant),
	# and the AP/Hotspot path; net-tools for the netstat/ifconfig calls. dnsmasq-base
	# is required by NetworkManager's Hotspot (ipv4.method=shared) to hand out DHCP/DNS
	# to AP clients - without it the headless WiFi-fallback Hotspot associates but
	# assigns no IP (only Recommended by network-manager, so not pulled in by default);
	# wireless-regdb backs `iw reg set <country>` for channel/regulatory compliance.
	iw wpasupplicant net-tools dnsmasq-base wireless-regdb
	# Format/fsck tools for USB/SATA music drives (mkfs.vfat, mkfs.exfat). moOde's
	# own "Format" action makes ext4 (e2fsprogs, base). The userspace/FUSE drivers
	# needed to MOUNT exfat/ntfs (exfat-fuse, ntfs-3g) are added conditionally just
	# before the install below - only for filesystems the running kernel can't
	# mount itself.
	dosfstools exfatprogs
	# USB auto-mount: moOde uses udisks-glue, which needs udisks1 (gone from
	# Trixie). udevil ships `devmon`, a drop-in automount daemon that mounts
	# removable drives to /media/<LABEL> and runs hooks on mount/unmount.
	udevil
	# moOde scripts call `python` (not python3), e.g. util/sysinfo.sh
	python-is-python3
	# HDMI-CEC control (Configure > Peripherals > HDMI displays): provides cec-ctl,
	# which inc/peripheral.php cecControl() and watchdog.sh use to power the attached
	# display on/wake it. NOT gated/guarded - the CEC toggle is always available; on
	# x86 boxes whose GPU exposes a /dev/cec adapter it works, elsewhere it's a no-op.
	v4l-utils
	# --- Parity with the Pi moode-player package deps: stock Debian, no patch ---
	# Media/metadata CLI tools moOde shells out to: sox (CamillaDSP resample path,
	# inc/cdsp.php), inotifywait (inotify-tools; worker file watches) + ffmpeg/flac/
	# id3v2 used across coverart, metadata and format handling.
	ffmpeg flac sox id3v2 inotify-tools
	# CJK + extra fonts so non-Latin track/station names render in the WebUI and the
	# Peppy display instead of tofu boxes (fonts-lato already arrives as a dep).
	fonts-arphic-ukai fonts-arphic-uming fonts-ipafont-gothic fonts-ipafont-mincho fonts-unfonts-core
	# Library (re)sharing servers + Windows discovery (wsdd2) + web terminal
	# (shellinabox). INSTALLED like the Pi but left DISABLED (see Phase 7) - the
	# worker starts them on demand from the UI sharing/SSH settings and disables
	# smbd/nmbd/wsdd2 itself if it ever finds them enabled.
	samba nfs-kernel-server wsdd2 shellinabox
	# Misc tools/libs moOde and its scripts use: jq (JSON), dos2unix (playlist
	# import), sysstat (system stats), tree, python3-musicpd (MPD client lib),
	# python3-setuptools, lsb-release, xfsprogs (mount/fsck XFS music drives),
	# avahi-utils (avahi CLI).
	jq dos2unix sysstat tree python3-musicpd python3-setuptools lsb-release xfsprogs avahi-utils
)

OPT_PKGS=()
[ "$INSTALL_BLUETOOTH"   = 1 ] && OPT_PKGS+=(bluez bluez-alsa-utils bluez-tools)  # bluez-tools: bt-agent
[ "$INSTALL_AIRPLAY"     = 1 ] && OPT_PKGS+=(shairport-sync)
[ "$INSTALL_UPNP"        = 1 ] && OPT_PKGS+=(upmpdcli upmpdcli-tidal upmpdcli-qobuz)
[ "$INSTALL_DLNA"        = 1 ] && OPT_PKGS+=(minidlna)
[ "$INSTALL_SQUEEZELITE" = 1 ] && OPT_PKGS+=(squeezelite)
# log2ram spares a flash-based rootfs (SD card / eMMC on SBCs like Armbian) from
# /var/log write wear: it keeps logs in tmpfs and syncs to disk periodically.
# Pointless on x86 SSD/NVMe. The package is in Debian main and ships EXACTLY the
# units moOde's worker toggles (log2ram.service + log2ram-daily.timer), so the
# existing 'log2ram' job handler and the Configure > System "Log to RAM" control
# (shown when /etc/log2ram.conf exists) work unchanged - same as on the Pi.
# Default 'auto': install only when the root filesystem sits on an mmcblk device.
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

# The repo block below needs curl + gnupg to fetch and dearmor the signing key,
# but this runs BEFORE the CORE_PKGS install and a minimal Debian (root pw, no
# tasksel desktop) ships neither (same gap class as `sudo`). Bootstrap them up
# front, else the key dearmor fails and UPnP is silently skipped.
apt-get update
apt-get install -y ca-certificates curl gnupg

# UPnP/OpenHome renderer (upmpdcli) and its libupnpp/libnpupnp deps are not in
# Debian; add the upstream lesbonscomptes apt repo so they (and future updates)
# install via apt. Suite tracks the running distro codename (trixie, bookworm...).
if [ "$INSTALL_UPNP" = 1 ]; then
	SUITE="$(. /etc/os-release 2>/dev/null; echo "${VERSION_CODENAME:-trixie}")"
	if curl -fsSL https://www.lesbonscomptes.com/pages/lesbonscomptes.gpg \
		| gpg --batch --yes --dearmor -o /usr/share/keyrings/lesbonscomptes.gpg 2>/dev/null
	then
		# deb822 .sources (upstream's current published format). Drop any stale
		# .list from an older installer run so we don't end up with both.
		rm -f /etc/apt/sources.list.d/upmpdcli.list
		# lesbonscomptes serves two pools: downloads/debian (amd64/i386) and
		# downloads/raspbian (arm64/armhf). Pick by arch - on Armbian arm64 the
		# debian pool has NO package, so `apt install upmpdcli` would abort the whole
		# install under set -e. (Verified: the raspbian pool ships upmpdcli arm64.)
		case "$(dpkg --print-architecture)" in
			arm64|armhf) UPNP_POOL=raspbian ;;
			*)           UPNP_POOL=debian ;;
		esac
		printf 'Types: deb\nURIs: https://www.lesbonscomptes.com/upmpdcli/downloads/%s/\nSuites: %s\nComponents: main\nSigned-By: /usr/share/keyrings/lesbonscomptes.gpg\n' \
			"$UPNP_POOL" "$SUITE" > /etc/apt/sources.list.d/upmpdcli.sources
		log "Added upmpdcli apt repo ($UPNP_POOL/$SUITE)"
		# Safety net: keep the upmpdcli packages queued only if the repo offers an
		# installable candidate for this arch. Retry the update a few times (the new
		# repo's first fetch can blip on a flaky network - seen on Armbian) so a
		# transient failure doesn't wrongly skip UPnP. If still no candidate, drop all
		# upmpdcli pkgs + the repo so `apt install` never aborts the run under set -e.
		# Wait for the new repo's metadata (its first fetch can blip on a flaky network -
		# seen on Armbian), then install only the upmpdcli* packages that actually have an
		# installable candidate. The repo normally offers all three (upmpdcli +
		# upmpdcli-tidal + upmpdcli-qobuz - small arch:all Python cdplugins), but its
		# contents can vary by suite/arch and a single missing package would abort the
		# whole run under set -e. Drop every upmpdcli* from OPT_PKGS, then re-add only the
		# available ones, and say plainly which get installed.
		UPNP_OK=0
		_uplog="$(mktemp)"
		for _try in 1 2 3; do
			# Update ONLY the upmpdcli source (not the full sources list) and KEEP its
			# output. Isolating the source makes the check immune to other repos and the
			# captured log makes a real failure diagnosable instead of a bare "no
			# candidate". The ACTUAL failure mode seen on armhf: apt fetched + verified
			# the InRelease fine (key is good) but the Packages-index download BLIPPED,
			# and on a plain re-`update` apt sees the InRelease unchanged ("Atteint") and
			# SKIPS re-fetching Packages -> the candidate stays absent forever and UPnP is
			# wrongly skipped (the pkg DOES exist: raspbian pool ships upmpdcli armhf+arm64,
			# debian pool ships amd64). Two-part fix: (a) Acquire::Retries makes apt re-try
			# the transient index download WITHIN one update; (b) wipe this repo's cached
			# lists each pass so a stale/partial index can never pin the retry to a no-op.
			rm -f /var/lib/apt/lists/*lesbonscomptes* /var/lib/apt/lists/partial/*lesbonscomptes* 2>/dev/null
			apt-get update -o Dir::Etc::sourcelist="sources.list.d/upmpdcli.sources" \
				-o Dir::Etc::sourceparts="-" -o APT::Get::List-Cleanup="0" \
				-o Acquire::Retries=3 >"$_uplog" 2>&1 || true
			apt-cache policy upmpdcli 2>/dev/null | grep -q 'Candidate: [0-9]' && { UPNP_OK=1; break; }
			sleep 3
		done
		_keep=(); for p in "${OPT_PKGS[@]}"; do case "$p" in upmpdcli|upmpdcli-tidal|upmpdcli-qobuz) ;; *) _keep+=("$p");; esac; done; OPT_PKGS=("${_keep[@]}")
		if [ "$UPNP_OK" = 1 ]; then
			_upnp=()
			for p in upmpdcli upmpdcli-tidal upmpdcli-qobuz; do
				if apt-cache policy "$p" 2>/dev/null | grep -q 'Candidate: [0-9]'; then
					_upnp+=("$p")
				else
					warn "UPnP: '$p' is not in the upmpdcli repo for $(dpkg --print-architecture); skipping just that package"
				fi
			done
			OPT_PKGS+=("${_upnp[@]}")
			log "UPnP (upmpdcli): installing ${_upnp[*]}"
		else
			warn "upmpdcli has no candidate for $(dpkg --print-architecture) after 3 tries; UPnP skipped"
			echo "---- apt-get update output for the upmpdcli repo (last try) ----"
			cat "$_uplog"
			echo "---------------------------------------------------------------"
			rm -f /etc/apt/sources.list.d/upmpdcli.sources
		fi
		rm -f "$_uplog"
	else
		warn "upmpdcli repo setup failed; UPnP will be skipped"
		_keep=(); for p in "${OPT_PKGS[@]}"; do case "$p" in upmpdcli|upmpdcli-tidal|upmpdcli-qobuz) ;; *) _keep+=("$p");; esac; done; OPT_PKGS=("${_keep[@]}")
	fi
fi

apt-get update
# Userspace/FUSE filesystem drivers for mounting USB/SATA music drives: install
# one ONLY for a filesystem the running kernel can't mount itself. The in-kernel
# vfat/exfat (and sometimes ntfs3) drivers in linux-image-amd64 mount directly,
# so the FUSE packages are pure fallback for stripped kernels. NB detection must
# check /proc/filesystems (built-in OR loaded) and modules.builtin too - a
# built-in filesystem is reported ABSENT by `modprobe -qn` (it has no .ko). moOde
# mounts via `mount -t <blkid-fstype>`: NTFS needs the ntfs-3g mount helper
# (mount.ntfs), and the kernel ntfs3 driver registers as `ntfs3`, which does NOT
# satisfy `mount -t ntfs` - so ntfs is keyed on a plain `ntfs` fs being present.
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

$APT_INSTALL "${CORE_PKGS[@]}" ${OPT_PKGS[@]+"${OPT_PKGS[@]}"} ${FS_PKGS[@]+"${FS_PKGS[@]}"}

# Detect the actual php-fpm version/socket so the nginx config matches Debian's
# packaged PHP (the shipped configs assume php8.4).
PHP_VER="$(ls /etc/php/ 2>/dev/null | sort -V | tail -1 || true)"
[ -n "$PHP_VER" ] || die "php-fpm not found after install"
PHP_SOCK="/run/php/php${PHP_VER}-fpm.sock"
log "PHP-FPM version: $PHP_VER (socket $PHP_SOCK)"

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

if ! command -v alsacap >/dev/null 2>&1 || [ ! -x /usr/bin/trx-tx ]; then
	$APT_INSTALL build-essential autoconf automake libtool pkg-config git \
		libasound2-dev libopus-dev libortp-dev
	HLP_WRK="$(mktemp -d)"

	# alsacap (bubbapizza/alsacap, autotools)
	if ! command -v alsacap >/dev/null 2>&1; then
		if git clone -q https://github.com/bubbapizza/alsacap.git "$HLP_WRK/alsacap" \
			&& ( cd "$HLP_WRK/alsacap" && ./bootstrap && ./configure && make ) >/dev/null 2>&1; then
			install -m 755 "$HLP_WRK/alsacap/src/alsacap" /usr/bin/alsacap
			log "Built alsacap"
		else
			warn "alsacap build failed (audio format detection will be degraded)"
		fi
	fi

	# trx 0.6 (bitkeeper/trx). Newer Debian oRTP needs libbctoolbox linked too.
	if [ ! -x /usr/bin/trx-tx ]; then
		if git clone -q -b 0.6 https://github.com/bitkeeper/trx.git "$HLP_WRK/trx" \
			&& ( cd "$HLP_WRK/trx" && make LDLIBS_ORTP="-lortp -lbctoolbox" ) >/dev/null 2>&1; then
			install -m 755 "$HLP_WRK/trx/tx" /usr/bin/trx-tx
			install -m 755 "$HLP_WRK/trx/rx" /usr/bin/trx-rx
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
# moOde's CamillaDSP feature is built from three custom (non-Debian) pieces:
#   camilladsp      Rust DSP engine     -> /usr/local/bin/camilladsp (release binary)
#   alsa-cdsp       ALSA 'cdsp' plugin  -> libasound_module_pcm_cdsp.so (built)
#   mpd2cdspvolume  MPD<->CDSP vol sync -> python service (optional volume sync)
# The ALSA conf we deploy (etc/alsa/conf.d/camilladsp.conf) routes audio through
# 'type cdsp' -> /usr/local/bin/camilladsp, so the engine + plugin must both
# exist for the feature to open. Default is camilladsp='off', so normal playback
# works without any of this; this just makes the feature functional when enabled.
# Versions are pinned to moOde's image manifest (camilladsp 4.1.3, pycamilladsp
# 4.0.0 - see moode-player/imgbuild stage3 ...01-packages).

log "Phase 1c: CamillaDSP (DSP / parametric EQ)"

CDSP_VER="4.1.3"
case "$(dpkg --print-architecture)" in
	amd64) CDSP_ASSET="camilladsp-linux-amd64.tar.gz" ;;
	arm64) CDSP_ASSET="camilladsp-linux-aarch64.tar.gz" ;;
	armhf) CDSP_ASSET="camilladsp-linux-armv7.tar.gz" ;;   # 32-bit ARM SBCs (e.g. Allwinner H3, Cortex-A7)
	*)     CDSP_ASSET="" ;;
esac

# 1) camilladsp engine (release binary, pinned to moOde's pkgbuild version)
if [ ! -x /usr/local/bin/camilladsp ] && [ -n "$CDSP_ASSET" ]; then
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
if [ ! -f "$CDSP_PLUGIN_DIR/libasound_module_pcm_cdsp.so" ]; then
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
		log "Built alsa-cdsp ALSA plugin"
	else
		warn "alsa-cdsp build failed (CamillaDSP output will not open when enabled)"
	fi
	rm -rf "$CDSP_BLD"
fi

# 3) Python CamillaDSP stack + camillagui web GUI - moOde's own noarch packages
#    (Architecture: all), fetched as .deb from moOde's cloudsmith pool and dpkg-
#    installed exactly as on the Pi. All three are arch-independent (Python lib +
#    static React build + Python backend), so the Pi's .debs install as-is on x86:
#    NO React/npm build, NO pip. Runtime deps are stock Debian (via apt).
#      python3-camilladsp 4.0.0      -> client lib (mpd2cdspvolume, camillagui)
#      python3-camilladsp-plot 4.1.0 -> pipeline plot (util/camillaplot_pipeline.py).
#                                       Needs matplotlib (used at runtime, not
#                                       declared by the .deb) -> install it too.
#      camillagui 4.1.0              -> HEnquist web GUI at /opt/camillagui; the
#                                       worker (cdsp.php) enable/starts the service
#                                       per the UI toggle and nginx proxies its port.
#    (The camilladsp ENGINE itself is the upstream release binary from step 1 -
#    moOde only adds a cargo-deb packaging patch, the DSP code is stock upstream.)
if [ ! -d /opt/camillagui ] || ! python3 -c 'import camilladsp, camilladsp_plot' 2>/dev/null; then
	$APT_INSTALL python3-aiohttp python3-websocket python3-jsonschema python3-numpy \
		python3-yaml python3-mpd python3-matplotlib
	CG_TMP="$(mktemp -d)"
	CG_POOL="https://dl.cloudsmith.io/public/moodeaudio/m8y/deb/raspbian/pool/trixie/main"
	if curl -fsSL -o "$CG_TMP/1.deb" "$CG_POOL/p/py/python3-camilladsp_4.0.0-1moode1/python3-camilladsp_4.0.0-1moode1_all.deb" \
		&& curl -fsSL -o "$CG_TMP/2.deb" "$CG_POOL/p/py/python3-camilladsp-plot_4.1.0-1moode1/python3-camilladsp-plot_4.1.0-1moode1_all.deb" \
		&& curl -fsSL -o "$CG_TMP/3.deb" "$CG_POOL/c/ca/camillagui_4.1.0-1moode1/camillagui_4.1.0-1moode1_all.deb" \
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
if [ ! -x /usr/local/bin/mpd2cdspvolume ]; then
	M2C_TMP="$(mktemp -d)"
	if git clone -q -b v2.0.0 \
			https://github.com/bitkeeper/mpd2cdspvolume.git "$M2C_TMP/src"; then
		install -m 755 "$M2C_TMP/src/mpd2cdspvolume.py" /usr/local/bin/mpd2cdspvolume
		# config holds user settings (snd-config.php seds dynamic_range) - preserve on re-run
		[ -f /etc/mpd2cdspvolume.config ] || install -m 644 "$M2C_TMP/src/etc/mpd2cdspvolume.config" /etc/mpd2cdspvolume.config
		install -m 644 "$M2C_TMP/src/etc/mpd2cdspvolume.conf"   /usr/lib/tmpfiles.d/mpd2cdspvolume.conf
		install -m 644 "$M2C_TMP/src/etc/mpd2cdspvolume.service" /lib/systemd/system/mpd2cdspvolume.service
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
# moOde's Deezer renderer is the 'pleezer' binary (worker launches it directly
# via inc/renderer.php when the Deezer service is enabled; not a systemd unit,
# so nothing to disable - it is off until enabled in the UI). pleezer ships no
# release binary and is not on crates.io, so build it from its pinned git tag.
# It needs Rust 1.85 + edition 2024, which Debian Trixie's cargo provides
# (1.85.0), so no toolchain juggling - just apt's cargo. Pinned to moOde's
# pkgbuild version. Idempotent: skipped when the binary already exists.

log "Phase 1d: Deezer renderer (pleezer)"

PLEEZER_VER="0.19.1"
if [ ! -x /usr/local/bin/pleezer ]; then
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

# cargo-deb for the on-demand Spotify (librespot) build. moOde's pkgbuild
# build.sh runs `cargo install cargo-deb` if absent, which grabs the latest
# (3.7.0) - and that fails to compile on Debian's Rust 1.85 (`let` expressions
# unstable). So pre-install a 1.85-compatible cargo-deb (2.12.1, MSRV 1.71) onto
# the system PATH; rbl_check_cargo then finds it (`cargo-deb --version`) and
# skips the broken install. Not arch-gated: Armbian arm64 on Debian cargo 1.85
# hits the same wall. Idempotent.
if ! command -v cargo-deb >/dev/null 2>&1; then
	$APT_INSTALL cargo git pkg-config libssl-dev
	cargo install --root /usr/local --locked --version 2.12.1 cargo-deb >/dev/null 2>&1 \
		&& log "Installed cargo-deb 2.12.1 (for on-demand librespot build)" \
		|| warn "cargo-deb install failed (Spotify on-demand build may fail)"
fi

#----------------------------------------------------------------------------#
# Phase 1e - caps with 12-band parametric EQ (eqfa12p)
#----------------------------------------------------------------------------#
# moOde's Parametric EQ (Configure > Audio) uses the LADSPA plugin EqFA12p (id
# 2611), a 12-band extension of CAPS' EqFA4p by @bitkeeper. It is NOT in Debian's
# stock `caps` (which carries only Eq10/EqFA4p), so the eqfa12p ALSA device fails
# to load and MPD play errors with `_audioout: No such file or directory`. The Pi
# image ships caps=0.9.26-1moode1 (held); reproduce it by rebuilding Debian's caps
# source with moOde's own pkgbuild patch (same recipe as moode-player/pkgbuild
# packages/caps, minus its Pi-only cloudsmith repo plumbing). Stock `caps` from
# Phase 1 (Graphic EQ/Eq10 + crossfeed) stays installed if this build fails - only
# the Parametric EQ is then unavailable. Idempotent: skipped once caps is moode-
# tagged. Not arch-gated: on Armbian arm64 moOde's caps deb is equally absent.

log "Phase 1e: caps with 12-band parametric EQ (eqfa12p)"

if ! dpkg-query -W -f='${Version}' caps 2>/dev/null | grep -q moode; then
	# debhelper is caps' declared build-dep (debian/control: debhelper >= 10) and is
	# NOT pulled in by build-essential/devscripts on a minimal system - it happened to
	# be present on amd64 but was absent on a fresh Armbian arm64, where the build then
	# aborted at `dpkg-checkbuilddeps: unmet build dependencies: debhelper (>= 10)`
	# (stock caps stayed -> no EqFA12p -> no Parametric EQ). List it explicitly.
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
				dch -b -v 0.9.26-1moode1 -D unstable "Add 12-band eqfa12p parametric EQ (moOde patch)" \
			&& dpkg-buildpackage -b -us -uc ) > "$REPO_DIR/build-caps.log" 2>&1 \
		&& CAPS_DEB="$(ls "$CAPS_BLD"/caps_0.9.26-1moode1_*.deb 2>/dev/null | head -1)" \
		&& [ -n "$CAPS_DEB" ] \
		&& apt-get install -y --allow-downgrades "$CAPS_DEB" >/dev/null 2>&1; then
		apt-mark hold caps >/dev/null 2>&1 || true
		log "Built caps 0.9.26-1moode1 (12-band parametric EQ)"
	else
		warn "caps moode build failed (Parametric EQ unavailable; Graphic EQ + crossfeed still work; see $REPO_DIR/build-caps.log)"
	fi
	rm -rf "$CAPS_BLD"
fi

#----------------------------------------------------------------------------#
# Phase 1f - mpd with moOde's selective-resample patch
#----------------------------------------------------------------------------#
# moOde patches MPD for "Selective resampling" (Configure > Audio: upsample only
# certain source rates / adhere to base clock). The mpd.conf moOde generates emits
# `selective_resample_mode "N"` (N != 0); STOCK mpd rejects it as an unrecognized
# parameter and FAILS TO START -> the WebUI shows "Socket open failed (1001)". For
# audio parity with the Pi, build moOde's EXACT patched mpd from their published
# source package. moOde ships only arm binaries, but Debian source packages are
# arch-independent, so add moOde's cloudsmith SOURCE repo (deb-src ONLY - never
# their arm binaries) and build the moode1 source for this arch. Held afterwards
# (like the Pi) so apt won't pull Debian's stock mpd back. Idempotent: skipped
# once mpd is moode-tagged. The basic SoX resampler (selective mode = Disabled)
# already works on stock mpd, so a build failure only costs selective resampling.

log "Phase 1f: mpd with moOde selective-resample patch"

MPD_MOODE_VER="0.24.12-1moode1"
if ! dpkg-query -W -f='${Version}' mpd 2>/dev/null | grep -q moode; then
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
			&& mk-build-deps --install --remove --tool "apt-get -y --no-install-recommends" \
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
# Peppy's ALSA chain (peppy.conf) loads libpeppyalsa.so - a `type meter` scope
# that tees the PCM stream to a FIFO for the visualizer. Not in Debian; build
# moOde's exact patched source (the upstream peppyalsa has a 32-bit-only int vs
# long bug - snd_config_get_integer wants long* - that errors on the 64-bit build;
# moOde's peppy_alsa_fixes patch fixes it). Same moodeaudio deb-src path as mpd.
PEPPY_LIB="/usr/lib/$(dpkg-architecture -qDEB_HOST_MULTIARCH 2>/dev/null)/libpeppyalsa.so"
if [ "$INSTALL_LOCALDISPLAY" = 1 ] && [ ! -f "$PEPPY_LIB" ]; then
	log "Phase 1g: peppyalsa plugin (libpeppyalsa.so)"
	apt-get install -y build-essential autoconf automake libtool libasound2-dev libfftw3-dev dpkg-dev devscripts >/dev/null 2>&1
	PEPPY_BLD="$(mktemp -d)"
	(
		cd "$PEPPY_BLD" || exit 1
		# moOde's patched source (peppy_alsa_fixes_by_kent_reed.patch applies on extract);
		# build with autotools directly (their debian/rules produces no artifacts here).
		apt-get source peppy-alsa >/dev/null 2>&1
		cd peppy-alsa-*/ || exit 1
		autoreconf -fi >/dev/null 2>&1 && ./configure >/dev/null 2>&1 && make >/dev/null 2>&1
		install -m 644 .libs/libpeppyalsa.so.[0-9]*.[0-9]* "$PEPPY_LIB"
	)
	rm -rf "$PEPPY_BLD"
	[ -f "$PEPPY_LIB" ] && log "Built libpeppyalsa.so -> $PEPPY_LIB" \
		|| warn "peppyalsa build failed; Peppy Meter/Spectrum will be unavailable"
fi

#----------------------------------------------------------------------------#
# Phase 1h - ashuffle (Random/Shuffle advanced queue feature)
#----------------------------------------------------------------------------#
# moOde's Random modes (inc/music-library.php, inc/queue.php -> /usr/bin/ashuffle)
# use ashuffle, which is not in Debian. moOde ships ashuffle=3.14.9-1moode1, but the
# moode1 delta is ONLY Debian packaging (a debian/rules submodule-init hack + control
# metadata) - the code is stock upstream v3.14.9. So build that exact tag with meson
# (it vendors abseil + googletest as git submodules, hence --recursive) and install
# the single binary where moOde calls it. Idempotent: skip if already the right version.
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
# moOde's cfg_sl OTHEROPTIONS passes `-S <script>` (run a script on LMS power
# on/off -> resume MPD after Squeezelite, the "rsmaftersl" feature). That option
# exists only in a NEWER upstream squeezelite snapshot than Debian ships AND is
# compiled under `#if GPIO`. Debian's stock squeezelite (older snapshot, no GPIO)
# REJECTS `-S` -> "Option error: -S" -> the service fails to start. Build moOde's
# exact source (Debian packaging + newer git snapshot) from their cloudsmith
# deb-src - same path as mpd (Phase 1f). Keep -DGPIO (needed for -S) but DROP
# -DRPI: -DRPI only adds the `-G` direct-Pi-GPIO-pin relay (Pi hardware) and pulls
# gpiod.h/libgpiod; on x86 it is useless. With GPIO-but-not-RPI gpio.c needs no
# libgpiod (gpiod.h is itself `#if RPI`). Held afterwards like mpd. Idempotent:
# skipped once squeezelite is moode-tagged. A build failure only costs the LMS
# power-resume option (stock squeezelite, sans -S, is kept).
if [ "$INSTALL_SQUEEZELITE" = 1 ]; then
	log "Phase 1i: squeezelite with moOde LMS Power Script (-S) support"
	SL_MOODE_VER="2.0.0-1541+git20250609.72e1fd8-1moode1"
	if ! dpkg-query -W -f='${Version}' squeezelite 2>/dev/null | grep -q moode; then
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
				&& mk-build-deps --install --remove --tool "apt-get -y --no-install-recommends" \
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
# Phase 2 - Deploy the web application tree
#----------------------------------------------------------------------------#

log "Phase 2: deploying application files"

# Web root, moodeutl CLI and the var/local/www payload (db schema, imagesw...)
rsync -a "$DIST_DIR/var/www/"        /var/www/
rsync -a "$DIST_DIR/usr/local/bin/"  /usr/local/bin/
rsync -a "$DIST_DIR/var/local/www/"  /var/local/www/
chmod +x /usr/local/bin/moodeutl /var/www/daemon/worker.php 2>/dev/null || true

# System helper scripts and assets shipped in the repo (referenced by worker
# as /usr/share/moode-player/... and others). Copy the static usr/ tree.
rsync -a "$REPO_DIR/usr/share/" /usr/share/ 2>/dev/null || true

#----------------------------------------------------------------------------#
# Phase 3 - nginx + php configuration
#----------------------------------------------------------------------------#

log "Phase 3: configuring nginx and php-fpm"

# Deploy moOde's actual nginx + php configuration rather than patching Debian's
# defaults. These configs carry settings the moOde runtime depends on — most
# critically PHP's session handling: session.save_path = /var/local/php and the
# session id format (sid_length = 26, sid_bits_per_character = 5). Debian's
# defaults (32/4, hex) reject moOde's stored session id, so PHP keeps generating
# fresh empty sessions: the worker and web never share state, config fields
# render blank and queued jobs (e.g. timezone) are never processed.

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

# Deploy BOTH site configs under the exact names the worker toggles between
# (.overwrite stripped -> moode-http.conf / moode-https.conf), like the Pi image.
# The worker's nginx_https_only handler does `ln -s .../moode-https.conf` (HTTPS
# on) or `.../moode-http.conf` (off); deploying only one (or under a different
# name) means toggling HTTPS mode points sites-enabled at a missing file and
# nginx ends up with no server -> dead WebUI. Enable moode-http.conf now (HTTP,
# HTTPS stays off). Remove any earlier moode.conf so two `default_server` blocks
# don't collide on :80.
install -m 644 "$REPO_DIR/etc/nginx/sites-available/moode-http.overwrite.conf" \
	/etc/nginx/sites-available/moode-http.conf
install -m 644 "$REPO_DIR/etc/nginx/sites-available/moode-https.overwrite.conf" \
	/etc/nginx/sites-available/moode-https.conf
rm -f /etc/nginx/sites-available/moode.conf /etc/nginx/sites-enabled/moode.conf
rm -f /etc/nginx/sites-enabled/default
ln -sf /etc/nginx/sites-available/moode-http.conf /etc/nginx/sites-enabled/moode-http.conf
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

# --- ALSA output plugin configs ---
# MPD's mpd.conf references the ALSA device "_audioout", which is defined in
# /etc/alsa/conf.d together with the DSP/loopback/bluetooth plugin chains. These
# ship in the repo and are normally placed by the moOde image build; without
# them MPD cannot open its output and fails to start. Deploy them, stripping the
# ".overwrite" marker the image build uses for its final filenames.
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
# Graphic EQ (alsaequal): the `type equal` plugin persists its 10-band control
# state to /opt/alsaequal/alsaequal.bin, which is opened READ-WRITE by EVERY
# process that opens the EQ device - both MPD (user mpd, on playback) and the web
# UI's `amixer -D alsaequal cset` (root via sudo). If the .bin is owned root and
# not writable by mpd, MPD's open fails with "_audioout: Operation not permitted"
# (EPERM) and playback dies. Create the dir world-writable and pre-seed the .bin
# 0666 so whichever side writes first cannot lock the other out. (On the Pi the
# image build provides /opt/alsaequal.)
install -d -m 0777 /opt/alsaequal
if [ -f /etc/alsa/conf.d/alsaequal.conf ]; then
	amixer -D alsaequal cset numid=1 66 >/dev/null 2>&1 || true   # creates the .bin
	[ -f /opt/alsaequal/alsaequal.bin ] && chmod 0666 /opt/alsaequal/alsaequal.bin
fi

# Crossfeed (bs2b): moOde's crossfeed.conf hardcodes the arm64 LADSPA path
# (/usr/lib/aarch64-linux-gnu/ladspa/), since the Pi image is arm64. Repoint it
# at THIS host's real multiarch ladspa dir (where bs2b-ladspa installed bs2b.so)
# so the plugin loads on x86 - and harmlessly stays correct on arm64/Armbian.
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
# Deploy moOde's smb.conf ALWAYS (not only when the SMB server is enabled): the
# SMB *client* tools used to browse/scan remote NAS shares (nmblookup, smbclient
# - moodeutl -c/-C, lib-config "Remote host") refuse to run without /etc/samba/
# smb.conf. When the server is enabled it also serves [Playlists] -> /var/lib/
# mpd/playlists and [OSDisk] -> /mnt/OSDISK (created in Phase 5b).
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

# Make the player user a sudoer. On Raspberry Pi OS the default user is in the
# 'sudo' group out of the box; a minimal Debian install does NOT add the first
# user to sudo, so without this the operator can't run admin tasks or re-run this
# installer with `sudo`. (moOde's runtime relies on the www-data NOPASSWD rule
# above, not on the player user's sudo - this is for the human operator / parity
# with the Pi.) Idempotent; the sudo package is installed in Phase 1.
if id -nG "$PLAYER_USER" 2>/dev/null | grep -qw sudo; then
	log "Player user '$PLAYER_USER' already in sudo group"
else
	usermod -aG sudo "$PLAYER_USER" && log "Added '$PLAYER_USER' to sudo group"
fi

# /etc/machine-info: PRETTY_HOSTNAME, used by systemd and (chiefly) bluez's
# hostname plugin as the advertised Bluetooth name. moOde ships it; deploy the
# .overwrite by convention, idempotent so a runtime-renamed value survives a
# re-run. (The Bluetooth block in Phase 3 also seeds it when BT is enabled; that
# echo now no-ops once this is in place.)
grep -q '^PRETTY_HOSTNAME=' /etc/machine-info 2>/dev/null \
	|| install -m 644 "$REPO_DIR/etc/machine-info.overwrite" /etc/machine-info

# --- .overwrite files intentionally NOT deployed on x86 (audit - do NOT "fix") --
# The repo follows moOde's .overwrite convention (deploy with .overwrite stripped
# to the mirrored path). Every platform-neutral one IS deployed (Phase 3, here,
# the ALSA conf.d loop, the renderer/BT blocks...). The following are SKIPPED on
# purpose - deploying them would break x86 or is meaningless off the Pi:
#   etc/rc.local.overwrite            - the Pi starts worker.php as root from
#       rc.local (+ udisks-glue, cpugov); x86 runs it as the moode-worker systemd
#       unit (www-data). Deploying it would double-start the worker and invoke the
#       absent udisks-glue.
#   etc/udisks-glue.overwrite.conf    - config for udisks-glue, which is gone from
#       Trixie; x86 auto-mounts USB via devmon (udevil). Config for an absent daemon.
#   etc/rpi/swap.conf.d/fixedswapsize.overwrite.conf - the /etc/rpi swap mechanism
#       is Raspberry-Pi-only; the directory/tooling does not exist on x86.
#   etc/update-motd.d/00-moodeos-header.overwrite - SSH login banner that calls
#       pirev.py (Pi model string); Pi-flavoured cosmetic, left at Debian default.
# (pam.d/sudo is handled below - by editing Debian's file in place, not by the
#  Raspbian .overwrite, which would drop Debian's pam_limits line.)

# pam.d/sudo: cut the sudo "session opened for user root" spam. The worker runs as
# www-data and routes EVERY privileged op through sudo (sysCmd), so pam_unix emits a
# "session opened/closed for user root" pair on each call - ~48k entries/day here,
# bloating the journal (Debian Trixie is journald-only, no rsyslog/auth.log, but the
# spam lands in the journal all the same) and burying real auth events. moOde's fix
# (its pam.d/sudo) inserts a pam_succeed_if rule that skips the session stack when
# the TARGET is root. We do NOT drop in moOde's Raspbian file verbatim - Debian's
# /etc/pam.d/sudo carries an extra `session required pam_limits.so` that the Raspbian
# one lacks - so instead we INSERT just that one rule into Debian's own file (keeping
# pam_limits + whatever else Debian ships). High blast radius (a bad pam.d/sudo
# breaks sudo -> breaks every sysCmd -> dead WebUI), so do it defensively: back up,
# edit, then re-test sudo as the unprivileged player + www-data accounts via runuser
# (which does NOT go through pam-sudo, so a restore is always possible even if the new
# config broke sudo) and roll back on failure. Idempotent (keyed on the rule text).
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

# Renderer / Bluetooth service unit OVERRIDES. moOde ships systemd units that
# REPLACE the stock package units so each service runs with moOde's settings once
# the worker enables it (Configure > Audio / Bluetooth). The matching ALSA configs
# (e.g. 20-bluealsa.conf) deploy with the conf.d batch above, but the *.conf are
# useless if the service that consumes them still runs the stock unit. Install to
# /etc/systemd/system (highest precedence: overrides the package units in /usr/lib
# & /lib, and the squeezelite SysV-init generator unit). The squeezelite env file
# (/etc/squeezelite.conf) is written by moOde when the service is enabled
# (inc/renderer.php); the Bluetooth side needs more files seeded (see below).
if [ "$INSTALL_BLUETOOTH" = 1 ]; then
	# Without these, enabling Bluetooth runs the stock bluealsa (no aptX/LDAC,
	# D-Bus-activated defaults) and there is no BT-speaker output or pairing agent.
	install -m 644 "$REPO_DIR/etc/systemd/system/bluealsa.overwrite.service" /etc/systemd/system/bluealsa.service
	install -m 644 "$REPO_DIR/etc/systemd/system/bluealsa-aplay@.service"     /etc/systemd/system/bluealsa-aplay@.service
	install -m 644 "$REPO_DIR/etc/systemd/system/bt-agent.service"            /etc/systemd/system/bt-agent.service
	# A2DP playback routing: startBluetooth() starts bluealsa/bt-agent but NOT the
	# player - bluealsa-aplay@<MAC> is started per device by a udev rule ->
	# a2dp-autoconnect when a phone connects (and stopped on disconnect; the rule
	# also `systemctl restart mpd` to free the exclusive DAC). The Pi image ships
	# these three; without them a phone pairs+connects but no audio reaches the DAC
	# (bluealsa-aplay never runs). bluealsaaplay.conf is the env file the @-unit
	# reads (AUDIODEV=_audioout); it must pre-exist (moOde sed-edits it, never
	# creates it).
	install -m 644 "$REPO_DIR/etc/bluealsaaplay.conf"                     /etc/bluealsaaplay.conf
	install -m 755 "$REPO_DIR/usr/local/bin/a2dp-autoconnect"             /usr/local/bin/a2dp-autoconnect
	install -m 644 "$REPO_DIR/etc/udev/rules.d/10-a2dp-autoconnect.rules" /etc/udev/rules.d/10-a2dp-autoconnect.rules
	udevadm control --reload-rules 2>/dev/null || true
	# BT controller name + device class. moOde renames the adapter with `sysutil.sh
	# chg-name bluetooth`, which sed-edits `Name =` in /etc/bluetooth/main.conf AND
	# `PRETTY_HOSTNAME=` in /etc/machine-info (bluez's hostname plugin OVERRIDES the
	# main.conf Name with PRETTY_HOSTNAME, so the latter is what's actually
	# advertised). Stock Debian ships `#Name = BlueZ` (commented) and no
	# machine-info, so both seds no-op and the BT name never changes (it falls back
	# to the system hostname) - and the audio device Class is never set. Deploy
	# moOde's own main.conf (Name = Moode Bluetooth + Class 0x2c041c "audio") and
	# seed machine-info to the same default so chg-name + the worker hostname-import
	# work. Idempotent: keyed on moOde's Class marker / an existing PRETTY_HOSTNAME,
	# so a runtime-renamed controller survives re-runs.
	grep -q 'Class = 0x2c041c' /etc/bluetooth/main.conf 2>/dev/null \
		|| install -m 644 "$REPO_DIR/etc/bluetooth/main.sed.conf" /etc/bluetooth/main.conf
	install -m 644 "$REPO_DIR/etc/bluetooth/pin.conf" /etc/bluetooth/pin.conf
	grep -q '^PRETTY_HOSTNAME=' /etc/machine-info 2>/dev/null \
		|| echo "PRETTY_HOSTNAME=Moode Bluetooth" >> /etc/machine-info
	log "Deployed Bluetooth service units + A2DP autoconnect + controller name/class"
fi
if [ "$INSTALL_SQUEEZELITE" = 1 ]; then
	# The squeezelite package ships its own unit (Debian-style: /etc/default/
	# squeezelite, SL_NAME/...) which ignores moOde's config. Override it with
	# moOde's native unit (in /etc, which wins over the package's /lib unit), which
	# reads the PLAYERNAME/AUDIODEVICE/... that inc/renderer.php writes to the env
	# file. (Phase 1i replaces stock squeezelite with moOde's -S-capable build.)
	install -m 644 "$REPO_DIR/lib/systemd/system/squeezelite.overwrite.service" /etc/systemd/system/squeezelite.service
	log "Deployed Squeezelite service unit (reads /etc/squeezelite.conf)"
fi
# upmpdcli (UPnP): the lesbonscomptes package ships a STOCK /etc/upmpdcli.conf. moOde
# needs its own (friendlyname/avfriendlyname/ohproductroom template that sysutil.sh
# chg-name + upp-config.php sed-edit, upnpav=1/openhome=0/checkcontentformat=1, and
# iconpath=moode_audio.png) - without it the UPnP name/icon are wrong and chg-name's
# sed finds no matching line. Deploy moOde's conf + the renderer icon. Idempotent
# (keyed on the moОde icon marker so a UI-customised conf survives re-runs).
if [ "$INSTALL_UPNP" = 1 ] && dpkg-query -W -f='${Status}' upmpdcli 2>/dev/null | grep -q ' installed'; then
	grep -q 'moode_audio.png' /etc/upmpdcli.conf 2>/dev/null \
		|| install -m 644 "$REPO_DIR/etc/upmpdcli.sed.conf" /etc/upmpdcli.conf
	install -d -m 755 /usr/share/upmpdcli
	install -m 644 "$REPO_DIR/usr/share/upmpdcli/moode_audio.png" /usr/share/upmpdcli/moode_audio.png
	log "Deployed moOde upmpdcli.conf + renderer icon"
fi
# Plexamp renderer unit (parity: the Pi ships it unconditionally; stays disabled
# until the Plexamp plugin is installed from the UI). The upstream unit hardcodes the
# Pi build user `pi` and /home/pi; rewrite to the real player account so it is correct
# if/when Plexamp is installed under the player's home.
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

# PHP opcache tuning
if [ -f "$REPO_DIR/etc/php/8.4/mods-available/opcache.sed.ini" ]; then
	install -m 644 "$REPO_DIR/etc/php/8.4/mods-available/opcache.sed.ini" \
		"/etc/php/$PHP_VER/mods-available/opcache.ini"
fi

# Triggerhappy: USB volume-knob / media-key handling (vol.sh)
install -d -m 755 /etc/triggerhappy/triggers.d
install -m 644 "$REPO_DIR/etc/triggerhappy/triggers.d/media.conf" /etc/triggerhappy/triggers.d/media.conf
# Debian's triggerhappy unit runs thd as `nobody`, which drops the trigger
# commands' privileges to nobody too. media.conf -> vol.sh writes volknob to the
# www-data-owned SQLite DB; nobody can't create the journal in /var/local/www/db
# (g+w www-data, not world) -> "attempt to write a readonly database" -> the USB
# volume knob changes the live MPD volume but never persists it (UI desyncs, no
# restore at boot). Run thd as www-data instead (the uid that owns the DB/session,
# matching the x86 worker model). thd opens the input devices as root before
# dropping privs, and hotplugged devices are passed in via triggerhappy's udev
# th-cmd helper (also root), so www-data needs no input-group membership.
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

# automount.sh / music-source.php re-share mounted USB/NVMe/SATA drives over NFS
# (as well as SMB) by appending `/srv/nfs/<kind>/<label>` lines to /etc/exports
# with `sed '$ a'`, which needs an existing anchor line; the worker's fs_nfs_*
# handler also errors on an empty /etc/exports. nfs-kernel-server ships its
# default header via ucf only if the file is absent, so a leftover-empty
# /etc/exports (from the earlier client-only era of this installer) stays empty.
# Deploy the package's own template when the file is missing OR empty - this is
# byte-identical to what the Pi gets, and never clobbers a populated exports.
if [ ! -s /etc/exports ]; then
	if [ -f /usr/share/nfs-kernel-server/conffiles/etc.exports ]; then
		install -m 644 /usr/share/nfs-kernel-server/conffiles/etc.exports /etc/exports
	else
		: > /etc/exports   # NFS server absent: keep the file present for the hook
	fi
fi

# The exported paths resolve through symlinks the Pi image ships in /srv/nfs
# (/srv/nfs/usb -> /media, nvme -> /mnt/NVME, sata -> /mnt/SATA, matching
# LIB_MOUNT_ROOT_NVME/SATA). moOde's source never creates them, so replicate the
# image-build artifact here, else exportfs can't resolve /srv/nfs/<kind>/<label>.
install -d -m 755 /srv/nfs
[ -e /srv/nfs/usb ]  || ln -s /media    /srv/nfs/usb
[ -e /srv/nfs/nvme ] || ln -s /mnt/NVME /srv/nfs/nvme
[ -e /srv/nfs/sata ] || ln -s /mnt/SATA /srv/nfs/sata

# Network interface naming + management. moOde's whole networking model is baked
# to eth0/wlan0: cfg_network rows are positional ([0]=eth0 [1]=wlan0 [2]=apd0),
# the .nmconnection keyfiles hardcode interface-name=eth0/wlan0, the SSID scan
# runs `nmcli ... ifname wlan0`, and the Network config reads `ip addr list
# eth0|wlan0`. The Pi names its onboard NICs eth0/wlan0 natively; a Debian x86 box
# instead gets predictable names (enp2s0/wlp1s0) AND configures ethernet via
# ifupdown, so NetworkManager never manages it -> the Network config shows nothing
# and the SSID scan returns nothing. Make x86 match the Pi (both take effect on
# the next reboot): (1) disable predictable naming so NICs come up as eth0/wlan0,
# (2) stop ifupdown claiming the primary NIC so NM owns eth0/wlan0.
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
	# Armbian (u-boot, no GRUB): set net.ifnames=0 via the kernel cmdline in
	# armbianEnv.txt so the onboard NIC comes up as eth0 (Armbian names it end0/
	# enxMAC otherwise) - moOde's UI is hardwired to eth0/wlan0. Effective next boot.
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

# NetworkManager owns the NICs, so systemd-networkd has nothing to configure. Armbian
# (and some Debian images) leave systemd-networkd-wait-online.service enabled, where it
# then waits its full timeout for interfaces networkd does not manage and exits 1 -> a
# permanently failed unit + ~20s added to every boot. Mask it; NetworkManager-wait-online
# already gates network-online.target. (networkd itself is left alone - idle/unmanaged.)
systemctl disable --now systemd-networkd-wait-online.service >/dev/null 2>&1 || true
systemctl mask systemd-networkd-wait-online.service >/dev/null 2>&1 || true

# Make sure NM treats nothing as unmanaged (cloud images mark the networkd NIC so).
install -d -m 755 /etc/NetworkManager/conf.d
printf '[keyfile]\nunmanaged-devices=none\n' > /etc/NetworkManager/conf.d/10-moode-manage-all.conf
if [ -f /etc/NetworkManager/NetworkManager.conf ]; then
	# Some images ship [ifupdown] managed=false; flip it so NM owns ifupdown NICs too.
	sed -i 's/^\(\s*\)managed=false/\1managed=true/' /etc/NetworkManager/NetworkManager.conf
fi

# WiFi creds migration. A Debian netinst that joined WiFi writes the SSID/PSK as an
# ifupdown "wpa-ssid"/"wpa-psk" stanza in /etc/network/interfaces (mode 0600).
# Reducing that file to loopback (above) makes NetworkManager own wlan0 but with NO
# WiFi profile -> a headless box silently drops off the network on the next reboot
# (no Ethernet, no screen = locked out). Migrate the inline creds to an NM keyfile so
# the connection survives the cutover. No interface-name is pinned because
# net.ifnames=0 renames wlpXsY -> wlan0 (the keyfile then matches whatever wlan NIC
# comes up). Idempotent: skip if a keyfile for this SSID already exists.
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
# Phase 4 - SQLite configuration database
#----------------------------------------------------------------------------#

log "Phase 4: configuration database"

install -d -m 755 /var/local/www/db

if [ -f "$SQLDB" ] && [ "$RESET_DB" -ne 1 ]; then
	log "Existing DB kept: $SQLDB (use --reset-db to recreate)"
else
	[ -f "$SQLDB" ] && cp -a "$SQLDB" "$SQLDB.bak.$(date +%s)" && warn "Backed up old DB"
	rm -f "$SQLDB"
	sqlite3 "$SQLDB" < "$SQLDB_SCHEMA"
	log "Created DB from schema"
fi

# CPU governor: the schema seeds cpugov='ondemand' (the Pi's governor). On x86/
# Armbian the available governors depend on the cpufreq driver - e.g. an Intel
# CPU in intel_pstate passive mode (intel_cpufreq) exposes only
# performance/schedutil, so 'ondemand' doesn't exist. The sys-config.php dropdown
# already builds from scaling_available_governors, but keep the PERSISTED value
# honest too so autocfg never tries to 'tee' an invalid governor and the stored
# value matches reality. Only rewrite when the seeded value isn't available here;
# fall back to whatever governor the kernel is actually running. (Skipped when the
# host has no cpufreq sysfs, e.g. inside a plain VM.) The schema default is left
# untouched so Pi behaviour / --reset-db semantics stay byte-identical.
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

# moOde PHP session directory (session.save_path). The worker runs as www-data
# (Phase 6) so it shares these session files with php-fpm directly.
#
# Owner MUST be www-data, not root. The dir is sticky + world-writable (1777,
# like /tmp) and modern Debian sets the kernel hardening fs.protected_regular=2,
# which forbids a process from opening a file it does NOT own for WRITE (O_RDWR)
# inside a sticky world/group-writable dir - UNLESS the file is owned by the
# directory's owner. PHP opens session files O_RDWR. moOde scripts launched via
# sysCmd() run under sudo (as root); with a root-owned dir, root cannot open the
# www-data-owned session files (EACCES) -> $_SESSION is empty for every root-run
# helper (thumb-gen.php hangs "Regenerating the thumbnail cache...", moodeutl
# -gv returns blank, etc.). Making www-data own the dir satisfies the
# "file owned by dir owner" exception, so both www-data and root can read/write
# the sessions. (On Raspberry Pi OS the worker is root so this never surfaced.)
install -d -m 1777 /var/local/php
chown www-data:www-data /var/local/php

# A few files the worker writes DIRECTLY (not via sudo) live in root-owned
# directories that www-data cannot create files in. Pre-create them owned by
# www-data so the worker (running as www-data) can write them:
#   /etc/mpd.conf, /etc/mpd.moode.conf  - regenerated by updMpdConf() each boot
#   /var/log/moode*.log                 - written by workerLog()
# touch (not install /dev/null) so existing content is preserved on re-runs.
# Renderer config files in /etc are also written DIRECTLY (non-sudo) by the
# www-data worker/UI, so they need the same treatment as mpd.conf:
#   /etc/squeezelite.conf   - renderer.php writes it when Squeezelite is configured
#   /etc/deezer/deezer.toml - renderer.php updateDeezCredentials(), ALSO called by
#                             autoConfig()'s Deezer handler on every config restore
# As www-data the fopen() fails and the following fwrite/ftruncate(false) raises an
# uncaught TypeError (crash). Pre-create them (and /etc/deezer) www-data-owned;
# harmless empty files when the renderer is unused.
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

# moOde's MPD music_directory is /var/lib/mpd/music; the library "root" folders
# (OSDISK, RADIO, NAS, ...) appear beneath it. On the Raspberry Pi image these
# are created by the image build - replicate the local ones here so the WebUI
# library, the default playlist and the Samba shares have real content. MPD
# follows the symlinks out to /mnt (follow_outside_symlinks defaults to yes).
MPD_MUSIC=/var/lib/mpd/music
install -d -m 0755 "$MPD_MUSIC"

# OSDISK: local on-disk music store + recorder target (cfg_system recorder_
# storage). On the Pi this is a data partition; on a generic PC it is just a
# directory on the root fs. Seed the shipped default content (Stereo Test,
# System Sounds/ReadyChime) only when missing so user files are never clobbered.
install -d -m 0775 /mnt/OSDISK
[ -d "$REPO_DIR/osdisk" ] && cp -an "$REPO_DIR/osdisk/." /mnt/OSDISK/ 2>/dev/null || true
ln -sfn /mnt/OSDISK "$MPD_MUSIC/OSDISK"

# NAS mount root (mountmon manages the per-share submounts beneath it)
install -d -m 0755 /mnt/NAS
ln -sfn /mnt/NAS "$MPD_MUSIC/NAS"

# SATA / NVMe locally-attached internal drives (lib-config "Locally attached
# drives"). moOde mounts each under /mnt/SATA/<name> resp. /mnt/NVME/<name> and
# exposes them as the SATA/NVME library roots (ROOT_DIRECTORIES). sataSourceMount/
# nvmeSourceMount do `mkdir "<root>/<name>"` WITHOUT -p, so the root dir must
# pre-exist - else the mount fails "mount point does not exist" (cfg_source shows
# "Mount error"). The Pi image creates these roots; the x86 installer must too.
install -d -m 0755 /mnt/SATA
ln -sfn /mnt/SATA "$MPD_MUSIC/SATA"
install -d -m 0755 /mnt/NVME
ln -sfn /mnt/NVME "$MPD_MUSIC/NVME"

# USB: auto-mounted removable drives land in /media/<LABEL> (devmon, Phase 6/7).
# The library exposes them as the "USB" root folder via this symlink.
install -d -m 0755 /media
ln -sfn /media "$MPD_MUSIC/USB"
# A Debian install from USB/optical media leaves a CD-ROM apt mount point behind:
# an empty /media/cdrom dir + apt.conf.d/00CDMountPoint (the cdrom: line in
# sources.list is already commented out). moOde lists every /media entry as an
# auto-mounted USB drive (lib-config.php: `ls -1 /media`), so the stray dir shows
# up as a phantom drive "cdrom ( | swap)". Remove the artifacts so /media holds
# only real devmon mounts, matching the Pi image (no source change needed).
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
# AirPlay (shairport-sync + nqptp) and Spotify (librespot) stay on-demand, exactly
# like moOde: a worker job runs util/plugin-updater.sh, which wgets
# $res_plugin_upd_url/<component>/<plugin>/update-<plugin>.zip, unzips it and runs
# update/install.sh. That install.sh clones moode-player/pkgbuild and BUILDS the
# package natively (-> a moode-tagged .deb, which is what isAirPlayInstalled()/
# isSpotifyInstalled() require: `dpkg-query ... | grep moode`). It needs two small
# x86 fixups: (1) it copies/installs a hardcoded `<pkg>_<ver>_arm64.deb`; (2) it
# resolves the home dir with `moodeutl -d -gv home_dir`, which reads the PHP
# session - empty here because the script runs as root (via the worker's sudo)
# and Debian's PHP session handler refuses a www-data-owned session file (our
# worker runs as www-data). home_dir is deterministic, so we resolve it the same
# way moOde's own moodeutl getUserID() does: /home/<first /home entry>.
#
# So for non-arm64 we mirror the (tiny, install.sh-only) plugin zips locally with
# those two points patched, serve them over the existing nginx `location /` (root
# /var/www, no nginx config change), and repoint res_plugin_upd_url at the local
# copy. On arm64 (Armbian) the upstream repo works unchanged, so we leave it
# alone. Regenerated from upstream each run -> never stale; the patches are
# narrow (arch token + home_dir line) so they track moOde's install.sh.

PKG_ARCH="$(dpkg --print-architecture)"
if [ "$PKG_ARCH" != arm64 ]; then
	log "Phase 5c: on-demand renderer plugins (AirPlay/Spotify) x86 mirror"
	$APT_INSTALL zip unzip >/dev/null 2>&1 || true
	PLUG_BASE="https://raw.githubusercontent.com/moode-player/plugins/main"
	PLUG_DST="/var/www/plugins-x86"
	PLUG_TMP="$(mktemp -d)"
	plug_ok=1
	# Renderer plugins (arch-patched). Add the Peppy "moOde meters" skin pack when
	# the local display is installed - it is platform-independent (PNG/config only),
	# so the arch/home_dir seds below are harmless no-ops, and mirroring it locally
	# stops Configure > Peripherals "Install moOde meters" from hanging on a plugin
	# the upstream-repointed updater would fetch (it wgets the mirror with no timeout).
	PLUG_ENTRIES="renderer/v5-shairport-sync renderer/v8-librespot"
	[ "$INSTALL_LOCALDISPLAY" = 1 ] && PLUG_ENTRIES="$PLUG_ENTRIES peppydisplay/v4-moode-meters"
	for entry in $PLUG_ENTRIES; do
		plugin="${entry##*/}"                       # e.g. v5-shairport-sync
		mkdir -p "$PLUG_DST/$entry"
		if wget -q "$PLUG_BASE/$entry/update-$plugin.zip" -O "$PLUG_TMP/p.zip"; then
			rm -rf "$PLUG_TMP/x"; mkdir -p "$PLUG_TMP/x"
			if ( cd "$PLUG_TMP/x" \
				&& unzip -q -o "$PLUG_TMP/p.zip" \
				&& sed -i "s/_arm64\.deb/_${PKG_ARCH}.deb/g" update/install.sh \
				&& sed -i 's|^HOME_DIR=\$(moodeutl -d -gv home_dir)|HOME_DIR=/home/$(ls /home/ 2>/dev/null \| head -1)|' update/install.sh \
				&& rm -f "$PLUG_DST/$entry/update-$plugin.zip" \
				&& zip -q -r "$PLUG_DST/$entry/update-$plugin.zip" update ); then
				# success marker (plugin-updater.sh fetches it after install; content
				# is irrelevant). Mirror upstream's if present, else synthesise one.
				wget -q "$PLUG_BASE/$entry/update-$plugin.txt" \
					-O "$PLUG_DST/$entry/update-$plugin.txt" 2>/dev/null \
					|| date > "$PLUG_DST/$entry/update-$plugin.txt"
			else
				warn "Failed to repackage $plugin (its on-demand install may fail)"; plug_ok=0
			fi
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

#----------------------------------------------------------------------------#
# Phase 5d - Local display (moOde WebUI / Peppy kiosk on an attached HDMI screen)
#----------------------------------------------------------------------------#
# moOde's "moOde WebUI" and "Peppy Meter/Spectrum" local display is an X11 +
# Chromium kiosk: localdisplay.service -> xinit -> ~/.xinitrc -> chromium --kiosk.
# The Pi image ships the whole X stack; a headless Debian does not. Install it and
# wire the pieces so Configure > Peripherals works. The worker OWNS the service
# (starts/stops it on the toggle), so it is deployed but NOT enabled here.
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

	# x86 ~/.xinitrc: moOde's Pi xinitrc.default probes the screen with kmsprint and
	# /boot/firmware/config.txt (Pi-only); deploy an x86 equivalent that uses xrandr.
	# The --app / --kiosk lines and the WebUI/Peppy branch mirror the Pi script so
	# the worker's runtime sed edits (--app URL, --kiosk GPU flag) still apply.
	cat > "/home/$PLAYER_USER/.xinitrc" <<'EOF'
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
	chmod 0755 "/home/$PLAYER_USER/.xinitrc"
	chown "$PLAYER_USER:$PLAYER_USER" "/home/$PLAYER_USER/.xinitrc"

	# armhf SBC kiosk (seen on Allwinner H3 / Mali-400 lima): the Chromium kiosk paints
	# a blank WHITE page unless the sandbox is disabled - on this 32-bit ARM kernel the
	# sandbox can't start the renderer (no JS error, just no frame). x86 and arm64 (H6,
	# Orange Pi 3 LTS) are fine, so scope --no-sandbox to armhf. The kiosk only loads
	# localhost (trusted), so this is an acceptable trade-off. (The Pi is untouched.)
	# Insert after 'exec chromium'; the worker only rewrites the --app/--kiosk lines so
	# this line survives its regen.
	case "$(uname -m)" in
		armv6l|armv7l)
			sed -i '/^[[:space:]]*exec chromium/a\	--no-sandbox \\' "/home/$PLAYER_USER/.xinitrc"
			log "armhf: added --no-sandbox to the Chromium kiosk (renderer starts blank otherwise)"
			;;
	esac

	# Peppy Meter/Spectrum visualizer: the apps (read the FIFO that libpeppyalsa
	# feeds, render via pygame/SDL on the X display) are upstream, not in Debian -
	# clone them to /opt like the Pi image. moOde's config templates go to
	# /etc/peppy*/config.txt (the worker reads/edits these); symlink the apps'
	# own config.txt at them so those edits take effect.
	$APT_INSTALL python3-pygame python3-pil git   # PeppySpectrum needs PIL (Pillow)
	for r in PeppyMeter:peppymeter PeppySpectrum:peppyspectrum; do
		repo="${r%%:*}"; dst="/opt/${r##*:}"
		[ -e "$dst/.git" ] || { rm -rf "$dst"; git clone --depth 1 "https://github.com/project-owner/$repo.git" "$dst" >/dev/null 2>&1; }
	done
	# moOde ships its OWN spectrum.py (not upstream's): upstream runs the draw/
	# update_ui loop in a background Thread, which on x86/X11/SDL2 never presents
	# -> a fully BLACK spectrum (pygame/SDL must render on the MAIN thread; the Pi
	# KMS path tolerated it, X11 does not). moOde's spectrum.py (PeppySpectrum
	# issue #1 fix) comments out the update_ui thread and calls clean_draw_update()
	# directly in the main loop. Overlay it on the upstream clone exactly like
	# moOde's pkgbuild build.sh does (`cp spectrum.py .`). Idempotent (plain
	# overwrite). Meter is unaffected - moOde uses upstream peppymeter.py as-is.
	if [ -d /opt/peppyspectrum ]; then
		if curl -fsSL "https://raw.githubusercontent.com/moode-player/pkgbuild/main/packages/peppy-spectrum/spectrum.py" \
				-o /opt/peppyspectrum/spectrum.py; then
			log "Peppy Spectrum: applied moOde's main-thread draw fix (spectrum.py)"
		else
			warn "could not fetch moOde spectrum.py; Spectrum may render black on x86"
		fi
	fi
	install -d -m 755 /etc/peppymeter /etc/peppyspectrum
	install -m 644 "$REPO_DIR/etc/peppymeter/config.sed.txt"    /etc/peppymeter/config.txt
	install -m 644 "$REPO_DIR/etc/peppyspectrum/config.sed.txt" /etc/peppyspectrum/config.txt
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
# Run the worker as www-data, the SAME user as php-fpm and nginx. This is
# REQUIRED for PHP session sharing: Debian's PHP "files" session handler only
# lets a process open a session file owned by its own uid (in particular root
# refuses to read a www-data-owned session file). On Raspberry Pi OS the worker
# runs as root and creates the session first at boot, so the root/www-data split
# happens to work there; on Debian x86 that same split silently breaks the
# session - worker and web never share state, so config fields render blank and
# queued jobs are never processed. Every privileged operation the worker needs
# already goes through sudo() (see sysCmd in inc/common.php), so it does not need
# to run as root.
User=www-data
Group=www-data
# /run/worker.pid lives in root-owned /run, which www-data cannot create files
# in. Pre-create it owned by the service user. The leading + makes systemd run
# this line with full privileges (as root) even though User= is www-data.
ExecStartPre=+/usr/bin/install -m 660 -o www-data -g www-data /dev/null /run/worker.pid
# Same idea for /var/log/moode.log. The worker truncates it via sudo (root) at the
# very start (worker.php: truncate MOODE_LOG --size 0); if the file is ABSENT at
# that instant, root creates it root:root and the www-data worker's first
# workerLog() can no longer reopen it -> fatal fwrite() -> startup crash-loop ->
# wrkready stuck 0 -> blank WebUI. The file can go missing under log2ram (SD/eMMC
# boards) across a network reconfigure/restart - seen on the OPi3 LTS after setting
# WiFi/hotspot; never on x86 (no log2ram) nor on the Pi (worker is root, so a
# root-owned log is fine). Guarantee it exists AND is www-data-owned before the
# worker runs (create if absent, else just re-own/re-mode; never truncate so an
# existing crash log is preserved). Leading + = run as root despite User=www-data.
ExecStartPre=+/bin/sh -c 'test -e /var/log/moode.log || /usr/bin/install -m 666 -o www-data -g www-data /dev/null /var/log/moode.log; chown www-data:www-data /var/log/moode.log; chmod 666 /var/log/moode.log'
ExecStart=/var/www/daemon/worker.php
Restart=on-failure
RestartSec=5

[Install]
WantedBy=multi-user.target
EOF

# www-data's passwordless sudo (required by the worker/web) is granted by
# moOde's own /etc/sudoers.d/010_www-data-nopasswd, deployed in Phase 3b.

# USB auto-mount daemon. Replaces moOde's udisks-glue (launched from rc.local on
# the Pi; needs udisks1, gone from Trixie) with devmon (from udevil). devmon
# mounts removable drives to /media/<LABEL> and runs hooks on (un)mount. Runs as
# root: the hooks call automount.sh, which edits /etc/samba/smb.conf + /etc/
# exports and restarts smbd/nfs (udisks-glue is root on the Pi for the same
# reason). %%d -> /media/<LABEL>; also poke MPD so the USB root rescans. The
# library exposes the drives via the /var/lib/mpd/music/USB -> /media symlink.
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

systemctl daemon-reload

#----------------------------------------------------------------------------#
# Phase 7 - Enable and start services
#----------------------------------------------------------------------------#

log "Phase 7: starting services"

systemctl enable --now nginx "php${PHP_VER}-fpm" avahi-daemon
# Do NOT let systemd autostart mpd. Match the Pi, where mpd.service is NOT enabled:
# the worker starts mpd itself (worker.php: `systemctl start mpd`) during its own
# startup, which runs AFTER network-online (moode-worker is After=network-online).
# Why this matters:
#  - If systemd autostarts mpd at boot it comes up BEFORE the network. mpd restores
#    its saved playback state (state_file + restore_paused); if the current song is a
#    RADIO STREAM mpd opens the URL immediately, DNS isn't up yet -> "Could not
#    resolve host" -> mpd drops the current song. The worker's later `systemctl start
#    mpd` is then a no-op (already running) so it never recovers: dead player at boot.
#  - Worker-started mpd also means a purely LOCAL, OFFLINE box never blocks on the
#    network to play its USB/local library (no network-online coupling on mpd itself).
# Disable the service AND the socket (socket activation would start mpd early too),
# exactly as moOde's own package postinstall does. Also remove a network-online
# drop-in left by an earlier version of this installer.
rm -f /etc/systemd/system/mpd.service.d/override.conf 2>/dev/null || true
systemctl disable --now mpd.service mpd.socket >/dev/null 2>&1 || true
systemctl daemon-reload
# Restart the config-bearing services so a re-run applies updated configs
# (nginx.conf, php.ini/pool, avahi service files) rather than leaving the old
# ones loaded. mpd is (re)started by the worker during its own startup.
systemctl restart nginx "php${PHP_VER}-fpm" avahi-daemon 2>/dev/null || true

# Name resolution (WINS/NetBIOS). Tolerant: a failure here must not abort install.
systemctl enable --now winbind 2>/dev/null || true

# USB auto-mount daemon (devmon). enable --now so already-inserted drives mount
# at install time and future insertions are handled. restart on re-run to pick
# up an updated unit. Tolerant: never abort the install on failure.
systemctl enable moode-devmon.service 2>/dev/null || true
systemctl restart moode-devmon.service 2>/dev/null || true

# Renderer / bridge services (Squeezelite, Bluetooth, UPnP, DLNA, AirPlay) are
# controlled by the worker per UI config and are all OFF by default (slsvc,
# btsvc, upnpsvc, dlnasvc... = 0). Their Debian packages auto-enable their units
# on install, which would leave e.g. bluealsa-aplay running and hijack the audio
# chain to "Bluetooth -> Device" instead of the default "MPD -> plughw -> Device"
# (and squeezelite would hold the DAC). Disable+stop them so the worker is the
# sole controller and the default chain is MPD; the worker starts each on demand.
for svc in squeezelite bluetooth bluealsa bluealsa-aplay bt-agent minidlna upmpdcli shairport-sync; do
	systemctl disable --now "$svc" 2>/dev/null || true
done

# Triggerhappy (USB volume knob / media keys) is likewise worker-controlled and
# OFF by default: usb_volknob is a SESSION-only flag (default '0'; not a
# cfg_system row), and its real persistence IS triggerhappy's systemd enable
# state - the worker's usb_volknob job does `systemctl enable+start` / `disable+
# stop`. Debian's package auto-enables the unit on install, which would make the
# knob active by default; disable it so the worker is the sole controller.
systemctl disable --now triggerhappy 2>/dev/null || true

# fluidsynth: a MIDI software synth pulled in transitively (libfluidsynth-dev is
# an mpd build-dep; the fluidsynth binary package ships a systemd *user* service
# enabled globally via /etc/systemd/user/default.target.wants/). On login it
# auto-starts and grabs the default ALSA device - i.e. the USB DAC (card 0) -
# which both contends for the DAC and makes moOde's format probe (alsacap /
# `moodeutl -f`) report "Device is busy, unable to detect formats". moOde never
# uses the fluidsynth daemon (MPD's MIDI decoder uses libfluidsynth3 directly),
# so disable the global user-service autostart and stop any running instance.
systemctl --global disable fluidsynth.service 2>/dev/null || true
pkill -x fluidsynth 2>/dev/null || true

# shellinabox: moOde drives it via its OWN systemd unit, which runs shellinaboxd
# with -t (--disable-ssl => plain HTTP on the LAN) + moOde's terminal CSS. The
# Debian package instead ships only an init.d script; systemd-sysv-generator
# turns that into a unit that runs WITH SSL and the stock CSS. Deploy moOde's
# native unit so it overrides the sysv-generated one (systemd prefers a real
# .service over a generated one of the same name) - else the WebSSH "Open" link
# (http://host:4200) hits an HTTPS-only daemon and renders a blank page. The
# unit is byte-identical to the Pi; the moOde CSS it references is already in
# /var/www/css (web app deploy, Phase 2).
install -m 644 "$REPO_DIR/lib/systemd/system/shellinabox.service" /lib/systemd/system/shellinabox.service
systemctl daemon-reload

# File sharing servers + Windows discovery + web terminal (smbd/nmbd/wsdd2/
# nfs-kernel-server/shellinabox): same story as the renderers above - their Debian
# packages auto-enable+start the units on install, but moOde is the SOLE controller.
# The worker (re)started below owns their RUNNING state per the UI config: it starts
# smbd/nmbd only if fs_smb=='On', nfs-server only if fs_nfs=='On', shellinabox per
# the SSH/terminal feature - and worker.php actively disables wsdd2/smbd/nmbd if it
# finds them enabled. So boot-disable + stop them HERE (before the worker), matching
# the Pi image (installed, disabled); the worker then brings up exactly what the
# config asks for. Never enable/start at install - that would ignore the config.
for svc in smbd nmbd wsdd2 nfs-kernel-server shellinabox; do
	systemctl disable --now "$svc" 2>/dev/null || true
done

if [ "$NO_WORKER" -eq 1 ]; then
	warn "Skipping worker (--no-worker). Start it later: systemctl start moode-worker"
else
	# Use restart (not just enable --now): on a re-run the worker may already be
	# running against the OLD database. It sets cfg_system.wrkready=1 only during
	# startup, and engine-mpd.php returns an empty response (breaking the WebUI)
	# until it is 1, so the worker must re-initialise against the freshly written
	# config - especially after --reset-db, which resets wrkready back to 0.
	systemctl enable moode-worker.service
	systemctl restart moode-worker.service
fi

# --------------------------------------------------------------------------
# Config-file parity guard (vs the Pi moode-player package conffiles)
# --------------------------------------------------------------------------
# moOde on the Pi ships a fixed set of default config files (the package's dpkg
# conffiles). This installer reproduces that set; the check below WARNS (never
# aborts) if any expected default is missing after an install, so drift - a
# broken deploy step, or a new upstream conffile after rebasing on moOde - is
# caught here instead of surfacing later as a runtime crash. Deliberately
# EXCLUDED (Pi hardware, must not exist on x86): I2S audio overlays (/boot
# config.txt) and /etc/X11/xorg.conf.d/99-vc4.conf (Pi VC4 GPU).
# /etc/moode-apt-mark.conf is package-update infra handled differently on x86.
EXPECTED_CONF=(
	/etc/alsa/conf.d/_audioout.conf /etc/alsa/conf.d/_peppyout.conf
	/etc/alsa/conf.d/_sndaloop.conf /etc/alsa/conf.d/alsaequal.conf
	/etc/alsa/conf.d/btstream.conf /etc/alsa/conf.d/crossfeed.conf
	/etc/alsa/conf.d/eqfa12p.conf /etc/alsa/conf.d/invpolarity.conf
	/etc/alsa/conf.d/peppy.conf.hide /etc/alsa/conf.d/trx_send.conf
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
		/etc/bluealsaaplay.conf /etc/bluetooth/pin.conf /etc/bluetooth/main.conf
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
if [ "${#_missing[@]}" -gt 0 ]; then
	warn "Config-file parity: ${#_missing[@]} expected default file(s) MISSING:"
	for f in "${_missing[@]}"; do warn "  - $f"; done
else
	log "Config-file parity: all ${#EXPECTED_CONF[@]} expected default config files present"
fi

IP="$(hostname -I | awk '{print $1}')"
log "Done."
echo
echo "  WebUI:   http://${IP:-<this-host>}/"
echo "  Worker:  journalctl -u moode-worker -f"
echo "  Logs:    /var/log/moode.log  (moodeutl -l)"
echo
warn "First boot: open the WebUI, go to Configure > Audio and pick your USB/HDMI"
warn "output device. Pi-only options (I2S, GPIO, LCD) are hidden on this platform."
echo
# net.ifnames=0 (written to GRUB by Phase 3b) renames enpXsY/wlpXsY -> eth0/wlan0,
# but only on the NEXT boot - until then NetworkManager's eth0/wlan0 keyfiles don't
# match the live interface and the local-display kiosk / cold-boot service order
# aren't exercised. A reboot is required. The rename can also change the DHCP lease,
# so the IP address may differ after reboot; reconnect by hostname (.local) if so.
CUR_IFACE="$(ip -o -4 route show default 2>/dev/null | awk '{print $5; exit}')"
case "$CUR_IFACE" in
	eth*|wlan*) warn "REBOOT RECOMMENDED to finish applying all settings: sudo reboot" ;;
	*)          warn "REBOOT REQUIRED: 'sudo reboot' now. Interface '${CUR_IFACE:-enpXsY}' will be" ;;
esac
warn "renamed to eth0/wlan0 (net.ifnames=0), which CAN CHANGE THIS HOST'S IP ADDRESS."
warn "If ${IP:-the current IP} stops responding after the reboot, reconnect at:"
warn "  http://$(hostname).local/"
