#!/usr/bin/env python3
#
# SPDX-License-Identifier: GPL-3.0-or-later
# Copyright 2014 The moOde audio player project / Tim Curtis
# Copyright 2026 @Gjuju
#
# Volume and transport control of the active renderer, called by the REST API
# (command/index.php). Prints the renderer's volume state as JSON.
#
# Usage: renderer_ctl.py <renderer> <command> [N]
#   renderer: qobuz | bluetooth | airplay | squeezelite | soloist
#   command:  get | set N | up N | dn N | mute | toggle | play | pause | previous | next
#             watch: keep RENDVOL_FILE up to date until stopped (started by the worker)
#

import json
import os
import select
import signal
import socket
import subprocess
import sys
import time
import urllib.error
import urllib.request

import dbus

DBUS_PROPS = 'org.freedesktop.DBus.Properties'
DBUS_OBJMGR = 'org.freedesktop.DBus.ObjectManager'
RENDVOL_FILE = '/var/local/www/rendvol.json'
# watch: re-read at least this often, in case an event is missed or the renderer has none for a change
WATCH_FALLBACK = 5

def dbus_props_all(service, path, interface):
	try:
		obj = dbus.SystemBus().get_object(service, path, introspect=False)
		return dbus.Interface(obj, DBUS_PROPS).GetAll(interface)
	except Exception:
		return {}

def dbus_set_prop(service, path, interface, name, value):
	# Introspected: Set needs the variant signature
	try:
		obj = dbus.SystemBus().get_object(service, path)
		dbus.Interface(obj, DBUS_PROPS).Set(interface, name, value)
		return True
	except Exception:
		return False

def dbus_call(service, path, interface, method, *args):
	try:
		obj = dbus.SystemBus().get_object(service, path)
		getattr(dbus.Interface(obj, interface), method)(*args)
		return True
	except Exception:
		return False

def dbus_find(service, interface, root='/'):
	try:
		obj = dbus.SystemBus().get_object(service, root, introspect=False)
		managed = dbus.Interface(obj, DBUS_OBJMGR).GetManagedObjects()
	except Exception:
		return None
	for path, interfaces in managed.items():
		if interface in interfaces:
			return str(path)
	return None

def http_request(url, body=None, method='POST', timeout=5):
	req = urllib.request.Request(url, data=json.dumps(body).encode() if body is not None else (b'' if method == 'POST' else None), method=method)
	if body is not None:
		req.add_header('Content-Type', 'application/json')
	try:
		with urllib.request.urlopen(req, timeout=timeout) as resp:
			raw = resp.read().decode('utf-8', 'replace').strip()
		return json.loads(raw) if raw else {}
	except Exception:
		return None

def clamp(level):
	return max(0, min(100, level))

def follow(cmd, on_line, publish):
	# Run an event stream command, call on_line per line, publish() when it stays quiet. Restarted when it ends
	signal.signal(signal.SIGTERM, lambda signum, frame: sys.exit(0))
	while True:
		proc = subprocess.Popen(cmd, stdout=subprocess.PIPE, stderr=subprocess.DEVNULL, text=True, bufsize=1)
		try:
			while True:
				if not select.select([proc.stdout], [], [], WATCH_FALLBACK)[0]:
					publish()
					continue
				line = proc.stdout.readline()
				if not line:
					break
				on_line(line)
		finally:
			proc.terminate()
			proc.wait()
		publish()
		time.sleep(1)

def dbus_follow(services, keys, publish):
	# Publish on a PropertiesChanged signal carrying one of keys, and every WATCH_FALLBACK seconds
	from gi.repository import GLib
	def changed(interface, props, invalidated):
		if any(key in props for key in keys):
			publish()
	for service in services:
		dbus.SystemBus().add_signal_receiver(changed, signal_name='PropertiesChanged', dbus_interface=DBUS_PROPS, bus_name=service)
	GLib.timeout_add_seconds(WATCH_FALLBACK, lambda: publish() or True)
	publish()
	GLib.MainLoop().run()

def write_json(path, data):
	# Replaced in one step: a reader never sees a partial file
	tmp = path + '.tmp'
	with open(tmp, 'w') as fh:
		fh.write(json.dumps(data))
	os.chmod(tmp, 0o644)
	os.replace(tmp, path)

class Renderer:
	"""volume_state() returns (level 0-100, muted) or None"""
	can_mute = True
	meta_file = None

	def volume_state(self):
		return None

	def can_transport(self):
		return False

	def play_state(self):
		"""play | pause | stop, or None"""
		return None

	def metadata(self):
		return None

	def watch(self, publish):
		# Renderers without events are re-read
		while True:
			publish()
			time.sleep(WATCH_FALLBACK)

	def state(self, level=None, muted=None):
		vol = self.volume_state()
		if vol is not None and level is not None:
			vol = (level, vol[1])
		if vol is not None and muted is not None:
			vol = (vol[0], muted)
		return {
			'volume': None if vol is None else str(vol[0]),
			'muted': 'yes' if vol is not None and vol[1] else 'no',
			'can_volume': vol is not None,
			'can_mute': vol is not None and self.can_mute,
			'can_transport': self.can_transport(),
			'state': self.play_state()
		}

# Qobuz Connect (pibuz HTTP API, 0.0-1.0)
class Qobuz(Renderer):
	API = 'http://127.0.0.1:8182'

	def request(self, path, body=None, method='POST'):
		return http_request(self.API + path, body, method)

	def volume_state(self):
		# now-playing reports the pre-mute level while muted, as vol.sh does
		self.status = self.request('/api/now-playing', method='GET')
		playback = (self.status or {}).get('playback') or {}
		if playback.get('volume') is None:
			return None
		return int(round(playback['volume'] * 100)), bool(playback.get('muted'))

	def can_transport(self):
		# Read by volume_state(), called first in state()
		return self.status is not None

	def play_state(self):
		if self.status is None:
			return None
		return 'play' if ((self.status.get('playback') or {}).get('is_playing')) else 'pause'

	def watch(self, publish):
		# qbzevent.sh sends SIGUSR1 on a pibuz VolumeChanged or PlaybackStateChanged event
		signal.signal(signal.SIGUSR1, lambda signum, frame: None)
		signal.pthread_sigmask(signal.SIG_BLOCK, [signal.SIGUSR1])
		while True:
			publish()
			signal.sigtimedwait([signal.SIGUSR1], WATCH_FALLBACK)

	def set(self, level):
		self.request('/api/playback/volume', {'volume': clamp(level) / 100.0})

	def step(self, amount):
		self.request('/api/playback/volume', {'delta': amount / 100.0})

	def mute(self):
		self.request('/api/playback/volume', {'mute': 'toggle'})

	def transport(self, verb):
		result = self.request('/api/playback/' + verb) or {}
		if verb == 'toggle':
			return {'paused': 'pause', 'playing': 'play', 'loading': 'play'}.get(result.get('state'))

# Bluetooth: BlueALSA mixer (uint16, one byte per channel, bit 7 = mute, level 0-127), BlueZ AVRCP transport
class Bluetooth(Renderer):
	BLUEZ = 'org.bluez'
	BLUEZ_PLAYER = 'org.bluez.MediaPlayer1'
	BLUEALSA = 'org.bluealsa'
	BLUEALSA_PCM = 'org.bluealsa.PCM1'
	MUTE_BIT = 0x80
	LEVEL_MAX = 0x7F
	VERBS = {'play': 'Play', 'pause': 'Pause', 'next': 'Next', 'previous': 'Previous'}

	def __init__(self):
		self.pcm = dbus_find(self.BLUEALSA, self.BLUEALSA_PCM, '/org/bluealsa')
		self.player = dbus_find(self.BLUEZ, self.BLUEZ_PLAYER)

	def raw_volume(self):
		raw = dbus_props_all(self.BLUEALSA, self.pcm, self.BLUEALSA_PCM).get('Volume') if self.pcm else None
		if raw is None:
			return None
		left = (int(raw) >> 8) & 0xFF
		return left & self.LEVEL_MAX, bool(left & self.MUTE_BIT)

	def write_volume(self, level, muted):
		byte = (self.MUTE_BIT if muted else 0) | max(0, min(self.LEVEL_MAX, level))
		dbus_set_prop(self.BLUEALSA, self.pcm, self.BLUEALSA_PCM, 'Volume', dbus.UInt16((byte << 8) | byte))

	def volume_state(self):
		raw = self.raw_volume()
		if raw is None:
			return None
		return int(round(raw[0] * 100.0 / self.LEVEL_MAX)), raw[1]

	def can_transport(self):
		return self.player is not None

	def play_state(self):
		if self.player is None:
			return None
		status = str(dbus_props_all(self.BLUEZ, self.player, self.BLUEZ_PLAYER).get('Status') or '')
		return {'paused': 'pause', 'stopped': 'stop'}.get(status, 'play' if status else None)

	def watch(self, publish):
		def read():
			# The PCM appears once the A2DP stream is set up
			if self.pcm is None:
				self.pcm = dbus_find(self.BLUEALSA, self.BLUEALSA_PCM, '/org/bluealsa')
			if self.player is None:
				self.player = dbus_find(self.BLUEZ, self.BLUEZ_PLAYER)
			publish()
		dbus_follow((self.BLUEALSA, self.BLUEZ), ('Volume', 'Status'), read)

	def set(self, level):
		raw = self.raw_volume()
		if raw is not None:
			self.write_volume(int(round(clamp(level) * self.LEVEL_MAX / 100.0)), raw[1])

	def step(self, amount):
		raw = self.raw_volume()
		if raw is not None:
			self.write_volume(raw[0] + int(round(amount * self.LEVEL_MAX / 100.0)), raw[1])

	def mute(self):
		raw = self.raw_volume()
		if raw is not None:
			self.write_volume(raw[0], not raw[1])

	def transport(self, verb):
		if self.player is None:
			return
		if verb == 'toggle':
			# AVRCP has no toggle
			status = str(dbus_props_all(self.BLUEZ, self.player, self.BLUEZ_PLAYER).get('Status') or '')
			verb = 'pause' if status == 'playing' else 'play'
			return verb if dbus_call(self.BLUEZ, self.player, self.BLUEZ_PLAYER, self.VERBS[verb]) else None
		dbus_call(self.BLUEZ, self.player, self.BLUEZ_PLAYER, self.VERBS[verb])

# AirPlay: shairport-sync, volume set with MPRIS (0.0-1.0) and read from AdvancedRemoteControl (0-100), no mute
class AirPlay(Renderer):
	MPRIS_NAME = 'org.mpris.MediaPlayer2.ShairportSync'
	MPRIS_PATH = '/org/mpris/MediaPlayer2'
	MPRIS_PLAYER = 'org.mpris.MediaPlayer2.Player'
	SPS_NAME = 'org.gnome.ShairportSync'
	SPS_PATH = '/org/gnome/ShairportSync'
	VERBS = {'toggle': 'PlayPause', 'play': 'Play', 'pause': 'Pause', 'next': 'Next', 'previous': 'Previous'}
	can_mute = False

	def active(self):
		return bool(dbus_props_all(self.SPS_NAME, self.SPS_PATH, self.SPS_NAME).get('Active'))

	def volume_state(self):
		# Read from AdvancedRemoteControl, not MPRIS: MPRIS Volume is out of sync with the actual level (one command behind)
		vol = dbus_props_all(self.SPS_NAME, self.SPS_PATH, self.SPS_NAME + '.AdvancedRemoteControl').get('Volume') if self.active() else None
		if vol is None:
			return None
		return int(vol), False

	def can_transport(self):
		return self.active()

	def play_state(self):
		# Playing is also reported while a sender pauses (measured with OwnTone): only trust the other two
		status = str(dbus_props_all(self.MPRIS_NAME, self.MPRIS_PATH, self.MPRIS_PLAYER).get('PlaybackStatus') or '')
		return {'Paused': 'pause', 'Stopped': 'stop'}.get(status)

	def watch(self, publish):
		dbus_follow((self.SPS_NAME, self.MPRIS_NAME), ('Volume', 'Active', 'PlaybackStatus'), publish)

	def set(self, level):
		dbus_call(self.MPRIS_NAME, self.MPRIS_PATH, self.MPRIS_PLAYER, 'SetVolume', dbus.Double(clamp(level) / 100.0))
		# The sender confirms the new level about 1 s later
		return clamp(level)

	def step(self, amount):
		vol = self.volume_state()
		if vol is not None:
			return self.set(vol[0] + amount)

	def transport(self, verb):
		dbus_call(self.MPRIS_NAME, self.MPRIS_PATH, self.MPRIS_PLAYER, self.VERBS[verb])

# Squeezelite: no local interface, driven through the LMS server it is connected to (JSON-RPC, mute = negative volume)
class Squeezelite(Renderer):
	SLIMPROTO_PORT = 3483
	HTTP_PORT = 9000
	CLI_PORT = 9090
	VERBS = {'toggle': ['pause'], 'play': ['pause', '0'], 'pause': ['pause', '1'], 'next': ['playlist', 'index', '+1'], 'previous': ['playlist', 'index', '-1']}
	# Track tags for the metadata, read with the volume in the same request
	STATUS_TAGS = 'tags:algdKroTI'
	CODECS = {'flc': 'FLAC', 'alc': 'ALAC', 'ops': 'Opus', 'aif': 'AIFF', 'mp3': 'MP3', 'aac': 'AAC',
		'ogg': 'Ogg', 'wav': 'WAV', 'wma': 'WMA', 'pcm': 'PCM', 'dsf': 'DSD', 'dff': 'DSD'}
	PLACEHOLDERS = ('No Artist', 'No Album', 'No Genre')
	meta_file = '/var/local/www/slmeta.json'

	def __init__(self):
		self.status = {}
		self.resolve()

	def resolve(self):
		self.host = None
		self.player = None
		found = self.endpoint()
		if found is not None:
			self.host, local_ip = found
			result = self.request(['players', 0, 99]) or {}
			for row in result.get('players_loop') or []:
				if str(row.get('ip', '')).split(':')[0] == local_ip:
					self.player = str(row.get('playerid'))
					break

	@staticmethod
	def hex_ipv4(text):
		packed = int(text, 16)
		return '%d.%d.%d.%d' % (packed & 0xFF, (packed >> 8) & 0xFF, (packed >> 16) & 0xFF, (packed >> 24) & 0xFF)

	def endpoint(self):
		# (server, local) addresses of squeezelite's established slimproto connection
		try:
			with open('/proc/net/tcp') as fh:
				rows = fh.readlines()[1:]
		except OSError:
			return None
		for row in rows:
			fields = row.split()
			if len(fields) < 4 or fields[3] != '01':
				continue
			try:
				remote_ip, remote_port = fields[2].split(':')
				if int(remote_port, 16) == self.SLIMPROTO_PORT:
					return self.hex_ipv4(remote_ip), self.hex_ipv4(fields[1].split(':')[0])
			except ValueError:
				continue
		return None

	def request(self, command, player='-'):
		if self.host is None:
			return None
		result = http_request('http://%s:%d/jsonrpc.js' % (self.host, self.HTTP_PORT),
			{'id': 1, 'method': 'slim.request', 'params': [player, command]}, timeout=2)
		return None if result is None else result.get('result')

	def volume_state(self):
		status = (self.request(['status', '-', 1, self.STATUS_TAGS], self.player) if self.player else None) or {}
		self.status = status
		if 'mixer volume' not in status:
			return None
		try:
			level = int(status['mixer volume'])
		except (TypeError, ValueError):
			return None
		return min(100, abs(level)), level < 0

	def can_transport(self):
		return self.player is not None

	def play_state(self):
		# Read by volume_state(), called first in state()
		return self.status.get('mode') if self.status.get('mode') in ('play', 'pause', 'stop') else None

	def metadata(self):
		# Read by volume_state(), called first in state(). A stream carries its tags in remoteMeta
		entry = (self.status.get('playlist_loop') or [{}])[0]
		track = dict(entry, **{k: v for k, v in (self.status.get('remoteMeta') or {}).items() if v})
		if not track:
			return None
		def text(key):
			value = str(track.get(key, '')).strip()
			return '' if value in self.PLACEHOLDERS else value
		raw_type = text('type')
		codec = self.CODECS.get(raw_type.lower(), raw_type)
		try:
			sformat = '%s %d/%g kHz' % (codec, int(track['samplesize']), int(track['samplerate']) / 1000.0)
		except (KeyError, TypeError, ValueError):
			sformat = codec
		cover = text('artwork_url')
		if cover and not cover.startswith(('http://', 'https://')):
			cover = 'http://%s:%d/%s' % (self.host, self.HTTP_PORT, cover.lstrip('/'))
		try:
			duration = str(round(float(track.get('duration') or 0)))
		except (TypeError, ValueError):
			duration = '0'
		return {'title': text('title'), 'artist': text('artist'), 'album': text('album'), 'genre': text('genre'),
			'duration': duration, 'cover_url': cover, 'sformat': sformat, 'oformat': ''}

	def watch(self, publish):
		# LMS CLI notifications for this player, re-read when quiet or after a reconnect
		while True:
			if self.player is None:
				# Squeezelite reconnects to its server on its own, possibly another one
				self.resolve()
			publish()
			if self.player is None:
				time.sleep(WATCH_FALLBACK)
				continue
			try:
				with socket.create_connection((self.host, self.CLI_PORT), timeout=5) as sock:
					sock.sendall(b'subscribe mixer,play,pause,stop,playlist,client\n')
					while True:
						if not select.select([sock], [], [], WATCH_FALLBACK)[0]:
							publish()
							continue
						if not sock.recv(4096):
							break
						publish()
			except OSError:
				pass
			self.player = None
			time.sleep(1)

	def mixer(self, *args):
		if self.player:
			self.request(['mixer'] + list(args), self.player)

	def set(self, level):
		self.mixer('volume', clamp(level))

	def step(self, amount):
		self.mixer('volume', '%+d' % amount)

	def mute(self):
		# LMS applies muting after it answers, so report the toggled state
		vol = self.volume_state()
		self.mixer('muting', 'toggle')
		return None if vol is None else not vol[1]

	def transport(self, verb):
		if self.player:
			self.request(self.VERBS[verb], self.player)

# Spotify Soloist: soloist ctl, volume 0-100, no mute
class Soloist(Renderer):
	CTL = ['/usr/local/bin/soloist', 'ctl', '-D', '/var/local/www/soloist_data']
	VERBS = {'play': ('play', 'play'), 'pause': ('pause', 'pause'), 'next': ('next', 'skip_next'), 'previous': ('prev', 'skip_prev')}
	can_mute = False

	def __init__(self):
		self.trace = None

	def ctl(self, *args):
		try:
			result = subprocess.run(self.CTL + list(args), capture_output=True, text=True, timeout=5)
		except Exception:
			return None
		return result.stdout if result.returncode == 0 else None

	def read(self):
		# now reports the last session while inactive, is_active tells them apart
		if self.trace is not None:
			now = self.trace
		else:
			try:
				now = json.loads(self.ctl('now', '--json') or '{}')
			except ValueError:
				now = {}
		self.now = now if now.get('is_active') is True else None
		return self.now

	def watch(self, publish):
		# State read once, then kept from the event stream (volume_changed...) instead of a ctl call per read
		try:
			self.trace = json.loads(self.ctl('now', '--json') or '{}')
		except ValueError:
			self.trace = {}
		def on_line(line):
			# '<epoch_ms> <json>'
			try:
				event = json.loads(line.split(' ', 1)[1])
			except (IndexError, ValueError):
				return
			if event.get('type') == 'position_sync':
				return
			self.trace.update({k: event[k] for k in ('is_active', 'volume', 'status', 'available_actions') if k in event})
			publish()
		follow(self.CTL + ['trace'], on_line, publish)

	def volume_state(self):
		if self.read() is None or self.now.get('volume') is None:
			return None
		return int(self.now['volume']), False

	def can_transport(self):
		# Read by volume_state(), called first in state()
		return self.now is not None

	def play_state(self):
		if self.now is None:
			return None
		return {'playing': 'play', 'buffering': 'play', 'paused': 'pause', 'idle': 'stop'}.get(self.now.get('status'))

	def set(self, level):
		if self.read() is not None and self.ctl('volume', str(clamp(level))) is not None:
			return clamp(level)

	def step(self, amount):
		vol = self.volume_state()
		if vol is not None:
			return self.set(vol[0] + amount)

	def transport(self, verb):
		if self.read() is None:
			return
		if verb == 'toggle':
			verb = 'pause' if self.now.get('status') == 'playing' else 'play'
			return verb if self.ctl(verb) is not None else None
		command, action = self.VERBS[verb]
		if action in (self.now.get('available_actions') or {}):
			self.ctl(command)

RENDERERS = {'qobuz': Qobuz, 'bluetooth': Bluetooth, 'airplay': AirPlay, 'squeezelite': Squeezelite, 'soloist': Soloist}
TRANSPORT = ('toggle', 'play', 'pause', 'previous', 'next')

def watch(name):
	# Before the first bus connection, so D-Bus signals reach the GLib main loop
	import dbus.mainloop.glib
	dbus.mainloop.glib.DBusGMainLoop(set_as_default=True)
	renderer = RENDERERS[name]()
	last = {'state': None, 'meta': None}
	def publish():
		state = renderer.state()
		state['source'] = name
		if state != last['state']:
			write_json(RENDVOL_FILE, state)
			last['state'] = state
		meta = renderer.metadata()
		if renderer.meta_file and meta is not None and meta != last['meta']:
			write_json(renderer.meta_file, meta)
			last['meta'] = meta
	renderer.watch(publish)
	return 0

def main(argv):
	if len(argv) < 3 or argv[1] not in RENDERERS:
		print(json.dumps({'error': 'Usage: renderer_ctl.py <renderer> get|set N|up N|dn N|mute|toggle|play|pause|previous|next|watch'}))
		return 1
	command = argv[2]
	if command == 'watch':
		return watch(argv[1])
	amount = None
	if command in ('set', 'up', 'dn'):
		if len(argv) < 4 or not argv[3].isdigit():
			print(json.dumps({'error': 'N must only contain digits 0-9'}))
			return 1
		amount = int(argv[3])
	elif command != 'get' and command != 'mute' and command not in TRANSPORT:
		print(json.dumps({'error': 'Invalid command ' + command}))
		return 1

	renderer = RENDERERS[argv[1]]()
	level = None
	muted = None
	played = None
	if command == 'set':
		level = renderer.set(amount)
	elif command in ('up', 'dn'):
		level = renderer.step(amount if command == 'up' else -amount)
	elif command == 'mute' and renderer.can_mute:
		muted = renderer.mute()
	elif command in TRANSPORT:
		played = renderer.transport(command)

	state = renderer.state(level, muted)
	if command in TRANSPORT:
		# The command's result: play | pause where the renderer reports it on toggle, else the command
		state['state'] = played or command
	print(json.dumps(state))
	return 0

if __name__ == '__main__':
	# A wake-up signal (watch mode) must not end the process before watch() handles it
	signal.signal(signal.SIGUSR1, signal.SIG_IGN)
	sys.exit(main(sys.argv))
