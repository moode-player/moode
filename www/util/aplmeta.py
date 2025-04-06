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
# /var/www/daemon/aplmeta.py 1
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

#
# Globals
#

PGM_VERSION = '1.0.0'
DEBUG = None
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

# Debug logger
def debug_msg(msg, line_ending = '\n'):
	global DEBUG
	if DEBUG:
		print('DEBUG: ' + msg, end = line_ending);

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
	DEBUG = sys.argv[1]

# Forever loop
try:
	while True:
		line = sys.stdin.readline()
		debug_msg(line, '')

		# Update specified globals
		key, val = get_metadata(line)
		if key and val:
			debug_msg('--> key|val=' + key + '|' + val)
			update_globals(key, val)

		# When all globals are set, send metadata to front-end for display
		if artist and title and album and duration:
			# Get cover file name
			# NOTE: There will only be one file per retain_cover_art = "no";
			debug_msg('--> Get cover file name')
			cover_path = glob.glob(COVERS_LOCAL_ROOT + '*')
			cover_url = COVERS_WEB_ROOT + os.path.basename(cover_path[0])

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
			duration = None
except KeyboardInterrupt:
	sys.stdout.flush()
	pass
