#!/usr/bin/env python3
# SPDX-License-Identifier: GPL-3.0-or-later
# Copyright 2014 The moOde audio player project / Tim Curtis
# Copyright 2026 @Gjuju
#
# Bluetooth renderer metadata.
# Version 1.0.0	Original
# Version 1.0.1 Tim Curtis
# - Refactor to use only radiocover_plus.py cover lookup utility
# - Always enable, don't check whether Radio Covers feature is Yes/No
#
# Started by the worker when a Bluetooth source connects, stopped when it
# disconnects. Listens to the AVRCP track sent by the source, looks up a cover
# with radiocover_plus.py and pushes the metadata to the front-end the same way
# as the AirPlay, Spotify and Qobuz renderers.
#
# A source that sends no track title leaves the "Bluetooth Active" overlay
# unchanged. A track change cancels the cover lookup still running.
#

import json
import os
import signal
import subprocess

import dbus
import dbus.mainloop.glib
from gi.repository import GLib

BTMETA_CACHE_FILE = '/var/local/www/btmeta.json'
DEFAULT_COVER = 'images/default-album-cover.jpg'
COVER_LOOKUP_UTIL = '/var/www/util/radiocover_plus.py'
RATES = {44100: '44.1K', 48000: '48K', 88200: '88.2K', 96000: '96K'}

bus = None
track = None
playstate = 'Resume'
displayed = False
lookup = None
generation = 0

def source_format():
	try:
		om = dbus.Interface(bus.get_object('org.bluealsa', '/org/bluealsa'), 'org.freedesktop.DBus.ObjectManager')
		for ifaces in om.GetManagedObjects().values():
			pcm = ifaces.get('org.bluealsa.PCM1')
			if pcm and pcm.get('Mode') == 'source':
				rate = int(pcm['Sampling'])
				return '{} {}/{} {}ch'.format(pcm['Codec'], int(pcm['Format']) & 0xff,
					RATES.get(rate, str(rate / 1000) + 'K'), int(pcm['Channels']))
	except dbus.DBusException:
		pass
	return 'Bluetooth'

def output_format():
	return subprocess.run(['/var/www/util/get-oformat.php'], text=True, capture_output=True).stdout.strip()

def send_fecmd(cmd):
	result = subprocess.run(['/var/www/util/send-fecmd.php', cmd])

def publish(cover_url):
	global displayed
	metadata = json.dumps({
		'fecmd': 'update_btmeta',
		'title': track['title'],
		'artist': track['artist'],
		'album': track['album'],
		'duration': track['duration'],
		'cover_url': cover_url,
		'sformat': source_format(),
		'oformat': output_format(),
		'playstate': playstate
	})
	with open(BTMETA_CACHE_FILE, 'w') as file:
		file.write(metadata + '\n')
	send_fecmd(metadata)
	track['cover_url'] = cover_url
	displayed = True

def clear():
	global displayed
	if displayed:
		open(BTMETA_CACHE_FILE, 'w').close()
		send_fecmd('btactive1')
		displayed = False

def descendants(pid):
	try:
		with open(f'/proc/{pid}/task/{pid}/children') as file:
			children = [int(child) for child in file.read().split()]
	except OSError:
		return []
	return children + [grandchild for child in children for grandchild in descendants(child)]

def cancel_lookup():
	global lookup
	if lookup is not None:
		for pid in [lookup.pid] + descendants(lookup.pid):
			try:
				os.kill(pid, signal.SIGTERM)
			except ProcessLookupError:
				pass
		lookup = None

def lookup_done(pid, status, data):
	global lookup
	proc, gen = data
	proc.returncode = status
	cover_url = proc.stdout.read().strip()
	proc.stdout.close()
	if gen != generation:
		return
	lookup = None
	publish(cover_url if cover_url.startswith('http') else DEFAULT_COVER)

def start_lookup():
	global lookup
	proc = subprocess.Popen([COVER_LOOKUP_UTIL,
		'--title', track['artist'] + ' - ' + track['title'], '--station', 'Bluetooth'],
		stdout=subprocess.PIPE, stderr=subprocess.DEVNULL, text=True)
	lookup = proc
	GLib.child_watch_add(GLib.PRIORITY_DEFAULT, proc.pid, lookup_done, (proc, generation))

def on_track(avrcp):
	global track, generation
	new = {
		'title': str(avrcp.get('Title', '')),
		'artist': str(avrcp.get('Artist', '')),
		'album': str(avrcp.get('Album', '')),
		'duration': str(int(avrcp.get('Duration', 0)))
	}
	if track is not None and all(track[k] == new[k] for k in ('title', 'artist', 'album')):
		return
	track = new
	generation += 1
	cancel_lookup()

	if not track['title']:
		track = None
		clear()
	elif not track['artist']:
		publish(DEFAULT_COVER)
	else:
		start_lookup()

def on_status(status):
	global playstate
	playstate = 'Resume' if status == 'playing' else 'Pause'
	if displayed and track is not None and 'cover_url' in track:
		publish(track['cover_url'])

def on_properties_changed(iface, changed, invalidated, path=None):
	if iface != 'org.bluez.MediaPlayer1':
		return
	if 'Status' in changed:
		on_status(str(changed['Status']))
	if 'Track' in changed:
		on_track(changed['Track'])

def main():
	global bus
	dbus.mainloop.glib.DBusGMainLoop(set_as_default=True)
	bus = dbus.SystemBus()
	bus.add_signal_receiver(on_properties_changed, 'PropertiesChanged',
		'org.freedesktop.DBus.Properties', 'org.bluez', path_keyword='path')

	om = dbus.Interface(bus.get_object('org.bluez', '/'), 'org.freedesktop.DBus.ObjectManager')
	for ifaces in om.GetManagedObjects().values():
		player = ifaces.get('org.bluez.MediaPlayer1')
		if player:
			on_properties_changed('org.bluez.MediaPlayer1', player, [])

	loop = GLib.MainLoop()
	GLib.unix_signal_add(GLib.PRIORITY_DEFAULT, signal.SIGTERM, loop.quit)
	loop.run()
	cancel_lookup()

if __name__ == '__main__':
	main()
