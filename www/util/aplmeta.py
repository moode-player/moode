#!/usr/bin/python3
#
# SPDX-License-Identifier: GPL-3.0-or-later
# Copyright 2014 The moOde audio player project / Tim Curtis
#

#
# Caller: After starting shairport-sync
# cat /tmp/shairport-sync-metadata | shairport-sync-metadata-reader | /var/www/daemon/aplmeta.py
#
# DEBUG
# /var/www/daemon/aplmeta.py 1 (2 for more detail)
#
# Shairport-sync.conf
# metadata = {
# enabled = "yes";
# include_cover_art = "yes";
# cover_art_cache_directory = "/var/local/www/imagesw/airplay-covers";
# pipe_name = "/tmp/shairport-sync-metadata";
# diagnostics = {
# retain_cover_art = "no"; // artwork is deleted when its corresponding track has been played. Set this to "yes" to retain all artwork permanently. Warning -- your directory might fill up.
#

import sys
import subprocess
import re
import os
import glob
import time
from datetime import datetime

#
# Globals
#

PGM_VERSION = '1.0.0'
DEBUG = 0
COVERS_LOCAL_ROOT = '/var/local/www/imagesw/airplay-covers/'
COVERS_WEB_ROOT = 'imagesw/airplay-covers/'
APLMETA_FILE = '/var/local/www/aplmeta.txt'

artist = None
title = None
album = None
duration = '0'

#
# Functions
#

# Debug logger
def debug_msg(msg, line_ending = '\n'):
	global DEBUG
	if DEBUG > 0:
		time_stamp = datetime.now().strftime("%H:%M:%S")
		print(time_stamp + ' DEBUG: ' + msg, end = line_ending);

# Get specified metadata key,value pairs
def get_metadata(line):
	match = re.match('^(Title|Artist|Album Name): \"(.*?)\"\.$', line)
	if match:
		return match.group(1), match.group(2)
	else:
		match = re.match('^(Track length): (.*?)\.$', line)
		if match:
			return match.group(1), match.group(2).split(' ')[0]
		else:
			return None, None

# Update global vars
def update_globals(key, val):
	global artist, album, title, duration
	if key == 'Title':
		title = val
	elif key == 'Artist':
		artist = val
	elif key == 'Album Name':
		album = val
	elif key == 'Track length':
		duration = val

#
# Main
#

# Get debug level
if len(sys.argv) > 1:
	DEBUG = int(sys.argv[1])

# Forever loop
try:
	while True:
		line = sys.stdin.readline()
		if DEBUG > 1:
			debug_msg(line, '')

		# Update specified globals
		key, val = get_metadata(line)
		if key and val:
			debug_msg('--> key|val=' + key + '|' + val)
			update_globals(key, val)

		# When all globals are set, send metadata to front-end for display
		if artist and title and album:
			# Get cover file:
			# - Only one file will exist because retain_cover_art = "no"
			# - We need a delay to allow shairport-sync time to write the file
			debug_msg('--> Get cover file...')
			time.sleep(1)
			cover_path = glob.glob(COVERS_LOCAL_ROOT + '*')

			if not cover_path:
				cover_file = 'notfound.jpg'
				debug_msg('--> Cover file not found')
			else:
				# Construct cover URL
				cover_file = os.path.basename(cover_path[0])
				cover_url = COVERS_WEB_ROOT + cover_file
				debug_msg('--> Cover ' + cover_file)

				# Write metadata file
				debug_msg('--> Write metadata file')
				format = 'ALAC or AAC'
				metadata = title + '~~~' + artist + '~~~' + album + '~~~' + duration + '~~~' + cover_url + '~~~' + format
				file = open(APLMETA_FILE, 'w')
				file.write(metadata + "\n")
				file.close()

				# Send FE command
				debug_msg('--> Send FE command')
				subprocess.call(['/var/www/util/send-fecmd.php','update_aplmeta,' + metadata])

				# Reset globals
				debug_msg('--> Reset globals')
				artist = None
				title = None
				album = None
				duration = '0'

except KeyboardInterrupt:
	sys.stdout.flush()
	print("\n")
