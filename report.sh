#!/bin/bash
#
# SPDX-License-Identifier: GPL-3.0-or-later
# Copyright 2026 The moode-nopi project / Julien Gainza
#
# report.sh - collect a redacted diagnostics bundle for moode-nopi support.
#
# Run this on the box when you hit a problem and want help. It gathers the
# health-checks needed to debug the non-Pi port (platform, services, worker /
# session, MPD / audio, DSP, network, moode-tagged package versions, logs) into
# a SINGLE text file.
#
# Secrets are redacted: network info comes from nmcli WITHOUT --show-secrets,
# credential files are never read, and a backstop filter masks any
# password / psk / token / key value that might slip through. Nothing leaves
# the box unless you pass --upload, and even then you should review the file
# first.

set -u

SELF_VERSION="1.0"
DB="/var/local/www/db/moode-sqlite3.db"
OUT="/tmp/nopi-report-$(date +%Y%m%d-%H%M%S).txt"
DO_UPLOAD=0

# install.sh writes its log next to itself in the clone dir (install-nopi.log);
# the VM/override case puts it in /var/log. Boxes installed before the rename
# have the legacy name install.log. Pick the first that exists.
SCRIPT_DIR="$(cd "$(dirname "$(readlink -f "$0")")" && pwd)"
INSTALL_LOG=""
for c in "$SCRIPT_DIR/install-nopi.log" "$SCRIPT_DIR/install.log" \
         /var/log/install-nopi.log /var/log/install.log; do
	[[ -r "$c" ]] && { INSTALL_LOG="$c"; break; }
done
[[ -n "$INSTALL_LOG" ]] || INSTALL_LOG="$SCRIPT_DIR/install-nopi.log"

usage() {
	cat <<'EOF'
report.sh - moode-nopi diagnostics collector

Usage:
  sudo ./report.sh            Write a redacted report to /tmp and print the path
  sudo ./report.sh --upload   Also upload it to a no-account paste and print the URL
  ./report.sh --help          Show this help

The report is plain text. Review it before sharing; secrets are redacted
automatically but a quick skim never hurts. Attach the file (or the --upload
URL) to your GitHub issue/discussion.
EOF
	exit 0
}

for arg in "$@"; do
	case "$arg" in
		--upload|-u) DO_UPLOAD=1 ;;
		-h|--help)   usage ;;
		*) echo "Unknown option: $arg (try --help)" >&2; exit 1 ;;
	esac
done

if [[ $EUID -ne 0 ]]; then
	echo "report.sh needs root (logs, configs, journal). Re-running with sudo..." >&2
	exec sudo "$0" "$@"
fi

# --- helpers ---------------------------------------------------------------

# section TITLE  -> a labelled header in the report
section() { printf '\n========== %s ==========\n' "$1"; }

# run "label" cmd args...  -> show the command and its output (stderr folded in)
run() {
	local label="$1"; shift
	printf -- '--- %s ---\n$ %s\n' "$label" "$*"
	"$@" 2>&1 || printf '(command failed: exit %s)\n' "$?"
	printf '\n'
}

# tailf "label" FILE N  -> last N lines of a file if it exists
tailf() {
	local label="$1" file="$2" n="${3:-200}"
	printf -- '--- %s (tail -n %s %s) ---\n' "$label" "$n" "$file"
	if [[ -r "$file" ]]; then tail -n "$n" "$file"; else printf '(not present)\n'; fi
	printf '\n'
}

# REDACTION BACKSTOP: runs over the ENTIRE assembled report, so even unexpected
# leaks are caught. Local/private IPs (192.168/10/172.16-31/127, fe80 link-local,
# fc/fd ULA) are intentionally KEPT — they aid debugging and are not sensitive.
# Passes (order matters):
#  0. strip ANSI colour escapes (install.sh's green "==>" markers leave litter).
#  1. value following any sensitive key (= : space, quoted or not), case-insens.
#  2. MAC addresses (xx:xx:xx:xx:xx:xx) — device fingerprint.
#  3. GLOBAL unicast IPv6 only (2000::/3, FIRST hextet 2xxx/3xxx) — an
#     ISP-assigned address geolocates the user. The leading delimiter
#     (^|non-hex-non-colon) anchors the 2/3 to the start of the address, so an
#     interior hextet of a link-local (fe80::215:…) or ULA (fd…) is NOT matched,
#     and single-colon timestamps (HH:MM:SS) never match either.
redact() {
	sed -E \
		-e 's/\x1b\[[0-9;]*m//g' \
		-e 's/((pass(word|wd)?|psk|pre-shared-key|wpa-psk|secret|token|api[_-]?key|sharepassword|smbpass|client[_-]?secret|access[_-]?token)["'\'' ]*[:=][[:space:]]*["'\'']?)[^[:space:]"'\'']+/\1***REDACTED***/Ig' \
		-e 's/([0-9a-fA-F]{2}:){5}[0-9a-fA-F]{2}/**:MAC:**/g' \
		-e 's/(^|[^0-9A-Fa-f:])([23][0-9A-Fa-f]{3}:([0-9A-Fa-f]{0,4}:){1,6}[0-9A-Fa-f]{0,4})/\1***global-IPv6***/g'
}

# isPi() replica of www/inc/common.php: a Raspberry Pi is identified by its
# device-tree model containing "Raspberry Pi" (falls back to the cpuinfo Model
# line). On x86 the file is absent -> false. Returns 0 (true) when on a Pi.
is_pi() {
	local model=""
	[[ -r /proc/device-tree/model ]] && model="$(tr -d '\0' < /proc/device-tree/model 2>/dev/null)"
	[[ -z "$model" ]] && model="$(awk -F': ' '/^Model/{print $2}' /proc/cpuinfo 2>/dev/null)"
	[[ "$model" == *"Raspberry Pi"* ]]
}

# Supported base: the Debian 13 "Trixie" family ONLY — Debian, Armbian Trixie, or
# Raspberry Pi OS (Raspbian) Trixie. Mirrors install.sh's preflight gate. Other
# bases (Ubuntu...) are out of scope. Returns 0 (true) when supported.
supported_base() {
	local id codename major
	id="$(. /etc/os-release 2>/dev/null; echo "$ID")"
	codename="$(. /etc/os-release 2>/dev/null; echo "$VERSION_CODENAME")"
	major="$([[ -f /etc/debian_version ]] && cut -d. -f1 /etc/debian_version 2>/dev/null || echo '?')"
	{ [[ "$id" == debian ]] || [[ "$id" == raspbian ]]; } && { [[ "$codename" == trixie ]] || [[ "$major" == 13 ]]; }
}

# --- collection (everything below is appended to one buffer) ---------------

collect() {
	printf 'moode-nopi diagnostics report\n'
	printf 'Generated : %s\n' "$(date -Is)"
	printf 'Tool      : report.sh %s\n' "$SELF_VERSION"
	printf 'Host      : %s\n' "$(hostname 2>/dev/null)"

	# Eligibility verdict FIRST so triage is instant. moode-nopi supports NON-Pi
	# hardware on the Debian 13 (Trixie) family ONLY. A real Pi is upstream moOde's
	# domain; a non-Trixie base (Ubuntu...) is out of scope. KEEP only if both hold.
	section "SUPPORT ELIGIBILITY"
	_pi=no;   is_pi && _pi=yes
	_base=no; supported_base && _base=yes
	if [ "$_pi" = yes ]; then
		printf 'isPi()            = TRUE  (Raspberry Pi detected)\n'
	else
		printf 'isPi()            = FALSE (non-Pi hardware)\n'
	fi
	if [ "$_base" = yes ]; then
		printf 'Base OS           = SUPPORTED (Debian 13 Trixie family)\n'
	else
		printf 'Base OS           = UNSUPPORTED (%s)\n' "$(. /etc/os-release 2>/dev/null; echo "${PRETTY_NAME:-unknown}")"
	fi
	if [ "$_pi" = no ] && [ "$_base" = yes ]; then
		printf 'Verdict           = Eligible moode-nopi case -> KEEP the issue.\n'
	else
		printf 'Verdict           = OUT OF SCOPE -> likely REJECT the issue.\n'
		[ "$_pi" = yes ]  && printf '                    - Real Raspberry Pi: upstream moOde domain (use the moOde forum).\n'
		[ "$_base" = no ] && printf '                    - Unsupported base: Debian 13 Trixie only (Debian/Armbian/RaspiOS).\n'
	fi
	printf '\n'

	section "PLATFORM / OS"
	run "device-tree model" sh -c '[ -r /proc/device-tree/model ] && tr -d "\0" < /proc/device-tree/model && echo || echo "(absent - not a device-tree platform, e.g. x86)"'
	run "kernel"            uname -a
	run "architecture"      dpkg --print-architecture
	run "debian version"    sh -c 'cat /etc/debian_version 2>/dev/null'
	run "os-release"        sh -c 'cat /etc/os-release 2>/dev/null'
	run "moode-nopi version" sh -c 'cat /var/local/www/nopi_version 2>/dev/null || echo "(none)"'
	run "uptime / load"     uptime

	section "SERVICES"
	run "core stack active" systemctl is-active moode-worker nginx mpd $(systemctl list-units --type=service --no-legend 'php*-fpm*' 2>/dev/null | awk '{print $1}')
	for svc in moode-worker mpd nginx; do
		run "status $svc" systemctl --no-pager --full status "$svc" -n 5
	done
	run "renderer/helper units" sh -c 'systemctl list-units --type=service --no-legend "bluealsa*" "shairport*" "librespot*" "squeezelite*" "upmpdcli*" "camilladsp*" "pleezer*" 2>/dev/null'

	section "WORKER / SESSION"
	run "worker.php user (must be www-data)" sh -c 'ps -o user= -C worker.php 2>/dev/null || echo "(worker.php not running)"'
	run "wrkready (must be 1)" sh -c "sqlite3 '$DB' \"SELECT value FROM cfg_system WHERE param='wrkready'\" 2>/dev/null"
	run "php session dir perms (want www-data)" sh -c 'ls -ld /var/local/php 2>/dev/null'
	tailf "moode.log" /var/log/moode.log 120

	section "INSTALL LOG"
	tailf "install-nopi.log" "$INSTALL_LOG" 200

	section "JOURNAL (worker + mpd)"
	run "journalctl worker+mpd" journalctl -u moode-worker -u mpd -n 200 --no-pager

	section "MPD / AUDIO"
	run "mpc status"   mpc status
	run "mpc outputs"  mpc outputs
	run "aplay -l"     aplay -l
	run "asound cards" sh -c 'cat /proc/asound/cards 2>/dev/null'
	# mpd.conf can carry an MPD password -> redaction backstop handles it
	run "mpd.conf"     sh -c 'cat /etc/mpd.conf 2>/dev/null'

	section "DSP / ALSA CHAIN"
	run "alsa conf.d"  sh -c 'ls -l /etc/alsa/conf.d/ 2>/dev/null'
	run "camilladsp version" sh -c '/usr/local/bin/camilladsp --version 2>/dev/null || echo "(not installed)"'
	run "camilladsp working_config" sh -c 'head -80 /usr/share/camilladsp/working_config.yml 2>/dev/null'

	section "NETWORK (secrets masked by nmcli)"
	# nmcli does NOT print secrets without --show-secrets, which we never pass.
	run "nmcli device"     nmcli device status
	run "nmcli connections" nmcli -t -f NAME,UUID,TYPE,DEVICE connection show
	run "ip addr"   ip -br addr
	run "ip route"  ip route

	section "MOODE-TAGGED PACKAGES"
	# Per-package so one not-installed/non-dpkg name (camilladsp & ashuffle ship
	# as /usr/local/bin or /usr/bin binaries, shairport-sync is on-demand) does
	# not fail the whole query. Binaries are also probed by --version below.
	run "dpkg versions" sh -c 'for p in mpd caps upmpdcli shairport-sync librespot squeezelite ashuffle bluez-alsa-utils; do v="$(dpkg-query -W -f="\${Version}" "$p" 2>/dev/null)"; printf "%-18s %s\n" "$p" "${v:-(not a dpkg package / not installed)}"; done'
	run "binary versions" sh -c 'for b in /usr/local/bin/camilladsp /usr/local/bin/pleezer /usr/bin/ashuffle; do [ -x "$b" ] && printf "%-22s %s\n" "$(basename "$b")" "$("$b" --version 2>/dev/null | head -1)" || printf "%-22s %s\n" "$(basename "$b")" "(not present)"; done'

	section "CONFIG (cfg_system, sensitive keys redacted)"
	# Pull param/value but drop rows whose param name looks sensitive; the
	# backstop redact() is a second line of defence over the whole buffer.
	run "cfg_system" sh -c "sqlite3 -separator '=' '$DB' \"SELECT param,value FROM cfg_system WHERE param NOT LIKE '%pass%' AND param NOT LIKE '%psk%' AND param NOT LIKE '%token%' AND param NOT LIKE '%secret%' AND param NOT LIKE '%passwd%' ORDER BY param\" 2>/dev/null"
}

# --- write + (optionally) upload -------------------------------------------

collect | redact > "$OUT"
chmod 600 "$OUT"

echo "Report written: $OUT"
echo "Size: $(wc -l < "$OUT") lines, $(du -h "$OUT" | cut -f1)"
echo
echo ">> REVIEW the file before sharing it. Secrets are redacted automatically,"
echo ">> but please skim it for anything you consider private."

if [[ $DO_UPLOAD -eq 1 ]]; then
	echo
	echo "Uploading to a no-account paste service..."
	url=""
	# paste.rs (curl) first: reliable and curl is present everywhere (ARM boards
	# may lack nc). termbin (nc) then 0x0.st as fallbacks.
	if command -v curl >/dev/null 2>&1; then
		url="$(curl -fsS --data-binary @"$OUT" https://paste.rs/ 2>/dev/null)"
		[[ "$url" == http* ]] || url=""
	fi
	if [[ -z "$url" ]] && command -v nc >/dev/null 2>&1; then
		url="$(nc termbin.com 9999 < "$OUT" 2>/dev/null | tr -d '\0')"
	fi
	if [[ -z "$url" ]] && command -v curl >/dev/null 2>&1; then
		url="$(curl -fsS -A 'moode-nopi-report/1.0' -F"file=@$OUT" https://0x0.st 2>/dev/null)"
		[[ "$url" == http* ]] || url=""
	fi
	if [[ -n "$url" ]]; then
		echo
		echo "Uploaded. Share this URL in your issue/discussion:"
		echo "    $url"
	else
		echo "Upload failed (curl=$(command -v curl >/dev/null 2>&1 && echo yes || echo NO)," \
		     "nc=$(command -v nc >/dev/null 2>&1 && echo yes || echo NO), or network/services down)."
		echo "Attach the local file to your issue instead:"
		echo "    $OUT"
	fi
fi
