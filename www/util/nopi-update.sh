#!/bin/bash
#
# SPDX-License-Identifier: GPL-3.0-or-later
#
# moode-nopi: in-place update to the latest release (origin/main), the WebUI
# counterpart of `git reset --hard origin/main && sudo ./install.sh --update`.
# Launched detached by nopi-update.php through systemd-run, so the restarts of
# nginx, php-fpm and moode-worker done by install.sh do not kill it.
#

REPO_URL="https://github.com/Gjuju/moode-nopi.git"
SQLDB=/var/local/www/db/moode-sqlite3.db
MOODE_LOG="/var/log/moode.log"
UPDATER_LOG="/var/log/moode_update.log"

message_log () {
	echo "$1"
	TIME=$(date +'%Y%m%d %H%M%S')
	echo "$TIME updater: $1" >> $MOODE_LOG
	echo "$TIME updater: $1" >> $UPDATER_LOG
}

# Initialize the updater log
rm -f $UPDATER_LOG
touch $UPDATER_LOG
chmod 0666 $UPDATER_LOG

# Clone location, stamped by install.sh
REPO_DIR=$(cat /var/local/www/nopi_repo 2>/dev/null)
if [ ! -x "$REPO_DIR/install.sh" ]; then
	message_log "moode-nopi clone not found, update cancelled (run install.sh --update once by hand)"
	exit 1
fi
OWNER=$(stat -c %U "$REPO_DIR")
git_owner () { sudo -H -u "$OWNER" git -C "$REPO_DIR" "$@"; }

# Fetch and check out origin/main
message_log "Fetching moode-nopi origin/main"
if ! git_owner fetch --force --tags "$REPO_URL" "+refs/heads/main:refs/remotes/origin/main" \
	|| ! git_owner checkout -f -B main origin/main; then
	message_log "Git update failed, update cancelled"
	exit 1
fi

# Install update
message_log "Installing $(git_owner describe --tags --always), this takes several minutes"
if ! "$REPO_DIR/install.sh" --update; then
	message_log "Install failed, see $REPO_DIR/install-nopi.log"
	exit 1
fi

# Reload the local display, which keeps the page loaded before the update
if systemctl is-active --quiet localdisplay; then
	for i in $(seq 1 60); do
		[ "$(sqlite3 -cmd '.timeout 5000' $SQLDB "SELECT value FROM cfg_system WHERE param='wrkready'")" = 1 ] && break
		sleep 1
	done
	systemctl restart localdisplay
	message_log "Local display restarted"
fi

message_log "Update installed"
exit 0
