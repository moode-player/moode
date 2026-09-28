#!/bin/bash
################################################################################
#
# moode-nopi: the moode-player package postinstall, replayed by install.sh
#
# On the Pi, moOde migrates existing installs through the on_upgrade() blocks of
# moode-player/pkgbuild packages/moode-player/postinstall.sh. nopi never installs
# that package, so its steps are ported here, one block per upstream release, in
# upstream order. Each step runs once per DB and is recorded in nopi_postinstall;
# a fresh DB records them all unrun (the schema is already current).
#
# Upstream reviewed up to: pkgbuild 2f21e8d (2026-09-27)
# Ported from r1035 on; earlier blocks are covered by install.sh's schema
# migration (Phase 4).
#
# Sourced by install.sh (Phase 4): uses $SQLDB, $SQLDB_SCHEMA, log, warn.
# Step return codes: 0 applied, 2 deferred (retried next run), other = failed.
#
################################################################################

NOPI_PI_STEPS=(
	r1035-radiocover
	r1035-thesycon
	r1035-plugin
	r1035-stations
	r1035-first-use-help
)

# Upstream import_stations(), update mode
nopi_pi_import_stations() {
	local _zip _rc=0
	_zip=$(mktemp --suffix=.zip) || return 1
	if ! curl -fsSL -o "$_zip" "$1" 2>/dev/null; then
		rm -f "$_zip"
		return 2
	fi
	/var/www/util/station_manager.py --import --scope moode --how merge "$_zip" >/dev/null 2>&1 || _rc=1
	rm -f "$_zip"
	return $_rc
}

# Reload a table's rows from the shipped schema
nopi_pi_reload_table() {
	{
		echo "BEGIN;"
		echo "DELETE FROM $1;"
		grep "^INSERT INTO $1 " "$SQLDB_SCHEMA"
		echo "COMMIT;"
	} | sqlite3 -bail "$SQLDB" >/dev/null 2>&1
}

#------------------------------------------------------------------------------#
# r1035
#------------------------------------------------------------------------------#

# Radio Cover+ logging and rcu cache
pi_r1035_radiocover() {
	sqlite3 "$SQLDB" "DELETE FROM cfg_rcucache" || return 1
	if [ -f /var/log/moode_radiocover_plus.log ]; then
		truncate /var/log/moode_radiocover_plus.log --size 0 || return 1
	fi
	if [ -f /etc/radiocover-plus/config.txt ]; then
		sed -i 's/^LOG_LEVEL=.*/LOG_LEVEL=ERROR/' /etc/radiocover-plus/config.txt || return 1
	fi
}

# Qobuz updates: not ported. qobuzsvc=0 would switch off a working pibuz, the
# cfg_qobuz reload is done by the Phase 4 realign without losing user values,
# and the qbzd paths it removes are live pibuz 2.4+ state.

# Remove deprecated MPD option "thesycon_dsd_workaround"
pi_r1035_thesycon() {
	sqlite3 "$SQLDB" "UPDATE cfg_mpd SET param='RESERVED_48', value='' WHERE param='thesycon_dsd_workaround'"
}

# Update cfg_plugin for moode-meters, pibuz and shairport-sync version bumps
pi_r1035_plugin() {
	nopi_pi_reload_table cfg_plugin
}

pi_r1035_stations() {
	nopi_pi_import_stations "https://dl.cloudsmith.io/public/moodeaudio/m8y/raw/files/moode-stations-update_10.3.5.zip"
}

# Reset first use help (upstream: every upgrade)
pi_r1035_first_use_help() {
	sqlite3 "$SQLDB" "UPDATE cfg_system SET value='n,n,y' WHERE param='first_use_help'"
}

#------------------------------------------------------------------------------#
# Runner
#------------------------------------------------------------------------------#

nopi_postinstall_init() {
	sqlite3 "$SQLDB" "CREATE TABLE IF NOT EXISTS nopi_postinstall (id TEXT PRIMARY KEY, applied TEXT)"
}

nopi_postinstall_record() {
	sqlite3 "$SQLDB" "INSERT OR IGNORE INTO nopi_postinstall (id, applied) VALUES ('$1', '$2')"
}

# Fresh DB: nothing to migrate, record every step as already applied
nopi_postinstall_mark_all() {
	local _id
	if ! nopi_postinstall_init; then
		warn "postinstall: could not create the nopi_postinstall table"
		return 0
	fi
	for _id in "${NOPI_PI_STEPS[@]}"; do
		nopi_postinstall_record "$_id" "fresh"
	done
}

# Kept DB: run every step not recorded yet
nopi_postinstall_run() {
	local _id _rc _n=0
	if ! nopi_postinstall_init; then
		warn "postinstall: could not create the nopi_postinstall table, upstream steps skipped"
		return 0
	fi
	for _id in "${NOPI_PI_STEPS[@]}"; do
		[ -n "$(sqlite3 "$SQLDB" "SELECT 1 FROM nopi_postinstall WHERE id = '$_id'")" ] && continue
		_n=$((_n + 1))
		_rc=0
		"pi_${_id//-/_}" || _rc=$?
		case $_rc in
			0)
				nopi_postinstall_record "$_id" "$(date -Is)"
				log "postinstall: applied $_id"
				;;
			2) log "postinstall: $_id deferred (not available yet, retried next run)" ;;
			*) warn "postinstall: $_id failed (retried next run)" ;;
		esac
	done
	[ "$_n" -eq 0 ] && log "postinstall: no pending upstream step"
	return 0
}
