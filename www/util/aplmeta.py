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
import json
from datetime import datetime

#
# Globals
#

PGM_VERSION = '5.0.0'
DEBUG = 0
# Files and dirs
COVERS_LOCAL_ROOT = '/var/local/www/imagesw/airplay-covers/'
COVERS_WEB_ROOT = 'imagesw/airplay-covers/'
APLMETA_CACHE_FILE = '/var/local/www/aplmeta.json'
# Source and outout formats
DEFAULT_DURATION = '0'
DEFAULT_SFORMAT = 'ALAC/AAC'
DEFAULT_OFORMAT = '16/44.1K 2ch'
# Sample rates
RATE_TABLE = {'44100': '44.1K', '48000': '48K', '96000': '96K', '19200': '192K'}

# Current
state = None
title = None
artist = None
album = None
duration = DEFAULT_DURATION
sformat = DEFAULT_SFORMAT
oformat = DEFAULT_OFORMAT
volume = None
bundle = None
session = None
# Last
last_artist = None
last_title = None
last_album = None
last_sformat = None

#
# Functions
#

# Debug logger
def debug_msg(msg, line_ending = '\n'):
	global DEBUG
	if DEBUG > 0:
		time_stamp = datetime.now().strftime("%H:%M:%S")
		print(time_stamp + ' DEBUG: ' + msg, end = line_ending)

# Parse source format string
# - ALAC/44100/S16_LE/2
# - ALAC/48000/S24_LE/2
# - AAC/44100/F24/2
# - AAC/48000/F24/2
# - AAC/48000/F24/5.1
# - AAC/48000/F24/7.1
# - None (0)
def parseSourceFormat(match):
	global RATE_TABLE
	parts = match.group(2).strip('"').split('/')
	codec = parts[0]
	rate = RATE_TABLE[parts[1]]
	bits = parts[2][1:3]
	channels = parts[3]
	return codec + ' ' + bits + '/' + rate + ' ' + channels + 'ch'

# Parse output format string
# - 44100/S16_LE/2
def parseOutputFormat(match):
	global RATE_TABLE
	parts = match.group(2).strip('"').split('/')
	rate = RATE_TABLE[parts[0]]
	bits = parts[1][1:3]
	channels = parts[2]
	return bits + '/' + rate + ' ' + channels + 'ch'

# Get specified metadata key,value pairs
def get_metadata(line):
	# State
	# - 'Enter Active State.'
	match = re.match(r'^(Enter Active State)', line)
	if match:
		return match.group(1), 'No value'

	# Title/Artist/Album
	# - 'Title: "Symphony in D".'
	# - 'Artist" "Alex Cortiz".'
	# - 'Album Name: "Magnifico!, Vol. 2 (Bonus Tracks)".'
	match = re.match(r'^(Title|Artist|Album Name): \"(.*?)\"\.$', line)
	if match:
		return match.group(1), match.group(2)

	# Duration
	# - 'Track length: 320506 milliseconds.'
	match = re.match(r'^(Track length): (.*?)\.$', line)
	if match:
		return match.group(1), match.group(2).split(' ')[0]

	# Source format
	# - 'Source Format "AAC/48000/F24/2".'
	match = re.match(r'^(Source Format) \"(.*?)\"\.$', line)
	if match:
		return match.group(1), parseSourceFormat(match)

	# Output format
	# - 'Output Format "44100/S16_LE/2".'
	match = re.match(r'^(Output Format) \"(.*?)\"\.$', line)
	if match:
		return match.group(1), parseOutputFormat(match)

	# Volume
	#  - 'Volume: "-14.62,-27.81,-96.30,0.00".'
	match = re.match(r'^(Volume): \"(.*?)\"\.$', line)
	if match:
		return match.group(1), match.group(2)

	# Bundle
	# - 'Metadata bundle "1899156916" start.'
	# - 'Metadata bundle "1899156916" end.'
	match = re.match(r'^(Metadata bundle) (.*?)\.$', line)
	if match:
		return match.group(1), match.group(2).split(' ')[1]

	# Session
	# - 'Play Session Begin.'
	# - 'Play Session End.'
	match = re.match(r'^(Play Session) (.*?)\.$', line)
	if match:
		return match.group(1), match.group(2)

	# Unknown
	# - 'XXX Could not decipher:'
	# - 'XXX Could not recognize:'
	match = re.match(r'^(XXX Could not decipher|XXX Could not recognize):', line)
	if match:
		return match.group(1), 'Value omitted'
	else:
		return None, None

# Update global vars
def update_globals(key, val):
	global state, title, artist, album, duration, sformat, oformat, bundle, session, volume
	if key == 'Enter Active State':
		state = 'active'
		bundle = None
	elif key == 'Title':
		title = val
	elif key == 'Artist':
		artist = val
	elif key == 'Album Name':
		album = val
	elif key == 'Track length':
		duration = val
	elif key == 'Source Format':
		sformat = val
		if state != 'active':
			bundle = 'end'
	elif key == 'Output Format':
		oformat = val
	elif key == 'Volume':
		volume = val
	elif key == 'Metadata bundle':
		bundle = val
	elif key == 'Play Session':
		session = val
		if session == 'End':
			duration = DEFAULT_DURATION
			sformat = DEFAULT_SFORMAT
			oformat = DEFAULT_OFORMAT

#
# Main
#

# Get debug level
if len(sys.argv) > 1:
	if sys.argv[1] == '--version':
		print('aplmeta.py version ' + PGM_VERSION)
		exit()
	else:
		DEBUG = int(sys.argv[1])

# Set encoding and error handling
sys.stdin.reconfigure(encoding='utf-8', errors='ignore')

# Forever loop
try:
	debug_msg('Entering loop...')
	while True:
		line = sys.stdin.readline()
		if DEBUG > 1:
			debug_msg(line, '')

		# Update globals
		key, val = get_metadata(line)
		if key and val != None:
			if key != 'XXX Could not decipher':
				update_globals(key, val)
			debug_msg('- key|val: ' + key + '|' + val)

		# Wnen end metadata bundle, process changes
		if bundle == 'end':
			if not title:
				title = ''
			if not artist:
				artist = 'Status'
			if not album:
				album = 'AirPlay Source'

			debug_msg('- title|artist|album: (' + title + '|' + artist + '|' + album + ')')

			# Send to front-end if changed from last
			if title != last_title or artist != last_artist or album != last_album or sformat != last_sformat:
				# Get cover file:
				# - Only one file will exist because retain_cover_art = "no"
				# - Delay to allow shairport-sync time to write the file
				time.sleep(2)
				cover_path = glob.glob(COVERS_LOCAL_ROOT + '*')

				if not cover_path:
					cover_file = 'notfound.jpg'
					debug_msg('- Cover file not found')
				else:
					# Construct cover URL
					cover_file = os.path.basename(cover_path[0])
					cover_url = COVERS_WEB_ROOT + cover_file
					debug_msg('- Cover: ' + cover_file)

					# Write metadata cache file
					debug_msg('- Write metadata to cache file')
					metadata = {
						'fecmd': 'update_aplmeta',
						'title': title,
						'artist': artist,
						'album': album,
						'duration': duration,
						'cover_url': cover_url,
						'sformat': sformat,
						'oformat': oformat
					}
					metadata_json = json.dumps(metadata)
					file = open(APLMETA_CACHE_FILE, 'w')
					file.write(metadata_json + "\n")
					file.close()

					# Send command and metadata to front-end
					debug_msg('- Send metadata to front-end')
					subprocess.call(['/var/www/util/send-fecmd.php', metadata_json])

					# Update globals
					debug_msg('- Reset state and bundle')
					state = None
					bundle = None
					debug_msg('- Update last to current')
					last_title = title
					last_artist = artist
					last_album = album
					last_sformat = sformat

except KeyboardInterrupt:
	debug_msg('Exception branch')
	sys.stdout.flush()
	print("\n")
