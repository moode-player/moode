#!/bin/bash
#
# SPDX-License-Identifier: GPL-3.0-or-later
# Copyright 2014 The moOde audio player project / Tim Curtis
#
# Radio Browser maintenance helper.
# Usage: radio-browser.sh [--clear-recents|--fix-permissions|--flush-cache|--test-api]

RBCACHE="/var/local/www/rbcache"
RECENT="$RBCACHE/recently_played.json"
IMGCACHE="/var/local/www/imagesw/radio-logos/cache"
API="all.api.radio-browser.info"
UA="moode-radio-browser/1.0"

usage() {
	echo "Usage: $(basename "$0") [--clear-recents|--fix-permissions|--flush-cache|--test-api]"
	exit 1
}

need_root() {
	[[ $EUID -ne 0 ]] && { echo "Use sudo to run $1" ; exit 1 ; }
}

case "$1" in
	--clear-recents)
		need_root "$1"
		rm -f "$RECENT" && echo "Recently played cleared."
		;;
	--fix-permissions)
		need_root "$1"
		mkdir -p "$RBCACHE" "$IMGCACHE"
		chown -R www-data:www-data "$RBCACHE" "$IMGCACHE"
		chmod -R 0775 "$RBCACHE" "$IMGCACHE"
		echo "Permissions fixed on $RBCACHE and $IMGCACHE."
		;;
	--flush-cache)
		need_root "$1"
		# API/response + logo caches (keeps recently_played.json; use --clear-recents for that)
		find "$RBCACHE" -maxdepth 1 -type f -name '*.json' ! -name 'recently_played.json' -delete 2>/dev/null
		rm -f "$IMGCACHE"/* 2>/dev/null
		echo "Cache flushed (API responses + logos)."
		;;
	--test-api)
		start=$(date +%s%3N)
		count=$(curl -fsS --max-time 10 -A "$UA" "https://$API/json/servers" | grep -o '"name"' | wc -l)
		rc=$?
		end=$(date +%s%3N)
		if [[ $rc -eq 0 && $count -gt 0 ]]; then
			echo "API OK: $count servers via $API ($((end - start)) ms)."
		else
			echo "API FAILED via $API."
			exit 1
		fi
		;;
	*)
		usage
		;;
esac
