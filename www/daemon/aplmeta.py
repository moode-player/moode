#!/usr/bin/python3
#
# SPDX-License-Identifier: GPL-3.0-or-later
# Copyright 2014 The moOde audio player project / Tim Curtis
#

#
# Caller: After starting shairport-sync
# cat /tmp/shairport-sync-metadata | shairport-sync-metadata-reader | /var/www/daemon/aplmeta.py
#

import sys
import subprocess
import re
import os
import glob

#
# Globals
#

artist = None
title = None
album = None
duration = None

#
# Functions
#

# Get specified metadata key,value pairs
def get_metadata(line):
    match = re.match('^(Title|Artist|Album Name|Track length): \"(.*?)\"\.$', line)
    if match:
        return match.group(1), match.group(2)
    else:
        return None, None

# Update global vars
def update_globals(key, val):
    global artist, album, title
    if key == "Title":
         title = val
    elif key == "Artist":
        artist = val
    elif key == "Album Name":
        album = val
    elif key == "Track length":
         duration = val

#
# Main loop
#

try:
    while True:
        line = sys.stdin.readline()

		# Update specified globals
        key, val = get_metadata(line)
        if key and val:
            update_globals(key, val)

		# When all globals are set, send metadata to front-end for display
		if artist and title and album:
			# 1. Get cover file name
			# from: /tmp/shairport-sync/.cache/coverart/ (default in conf file)
			# example: cover-c5e0731b10a3758fc217716d5a64d589.jpg
			# Prolly edit shairport-sync.conf: cover_art_cache_directory = "/var/local/www/imagesw/airplay-covers";
			# cover_url = "/var/local/www/imagesw/airplay-covers" + cover_file

            # 2. Write metadata file
			# /var/local/www/aplmeta.txt
			# title + "~~~" + artist + "~~~" + album + "~~~" + duration + "~~~" + cover_url + format + "~~~" + decoder + "~~~" +

			# 3. Send FE command
			# /var/www/util/send-fecmd.php "update_aplmeta,$METADATA"

			# 4. Reset globals
			# artist = None
			# title = None
			# album = None
			# duration = None

except KeyboardInterrupt:
    sys.stdout.flush()
    pass
