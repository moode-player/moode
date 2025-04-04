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

PGM_VERSION = '1.0.0'
COVERS_LOCAL_ROOT = '/var/local/www/imagesw/airplay-covers/'
COVERS_WEB_ROOT = 'imagesw/airplay-covers/'
APLMETA_FILE = '/var/local/www/aplmeta.txt'

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
    if key == 'Title':
         title = val
    elif key == 'Artist':
        artist = val
    elif key == 'Album Name':
        album = val
    elif key == 'Track length':
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
		if artist and title and album and duration:
			# Get cover file name
			# from: /tmp/shairport-sync/.cache/coverart/ (default in conf file)
			# example: cover-c5e0731b10a3758fc217716d5a64d589.jpg
			# shairport-sync.conf: cover_art_cache_directory = "/var/local/www/imagesw/airplay-covers";
			# cover_url = "imagesw/airplay-covers" + cover_file_name
			cover_path = glob.glob(COVERS_LOCAL_ROOT + '*')
			cover_url = COVERS_WEB_ROOT + os.path.basename(cover_path[0])

            # Write metadata file
			# /var/local/www/aplmeta.txt
			# title + "~~~" + artist + "~~~" + album + "~~~" + duration + "~~~" + cover_url + "~~~" + format
			# Note format is "ALAC or AAC"
			format = 'ALAC or AAC'
			metadata = title + '~~~' + artist + '~~~' + album + '~~~' + duration + '~~~' + cover_url + '~~~' + format
			file = open(APLMETA_FILE, 'w')
			file.write(metadata)
			file.close()

			# Send FE command
			subprocess.call("/var/www/util/send-fecmd.php \"update_aplmeta," + metadata + "\"")

			# Reset globals
			artist = None
			title = None
			album = None
			duration = None
except KeyboardInterrupt:
    sys.stdout.flush()
    pass
