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
#   renderer: qobuz | bluetooth | airplay | squeezelite
#   command:  get | set N | up N | dn N | mute | toggle | previous | next
#

import json
import sys
import urllib.error
import urllib.request

import dbus

DBUS_PROPS = 'org.freedesktop.DBus.Properties'
DBUS_OBJMGR = 'org.freedesktop.DBus.ObjectManager'

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

class Renderer:
	"""volume_state() returns (level 0-100, muted) or None"""
	can_mute = True

	def volume_state(self):
		return None

	def can_transport(self):
		return False

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
			'can_transport': self.can_transport()
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
	VERBS = {'toggle': 'PlayPause', 'next': 'Next', 'previous': 'Previous'}
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
	VERBS = {'toggle': ['pause'], 'next': ['playlist', 'index', '+1'], 'previous': ['playlist', 'index', '-1']}

	def __init__(self):
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
		status = (self.request(['status', '-', 1], self.player) if self.player else None) or {}
		if 'mixer volume' not in status:
			return None
		try:
			level = int(status['mixer volume'])
		except (TypeError, ValueError):
			return None
		return min(100, abs(level)), level < 0

	def can_transport(self):
		return self.player is not None

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

RENDERERS = {'qobuz': Qobuz, 'bluetooth': Bluetooth, 'airplay': AirPlay, 'squeezelite': Squeezelite}
TRANSPORT = ('toggle', 'previous', 'next')

def main(argv):
	if len(argv) < 3 or argv[1] not in RENDERERS:
		print(json.dumps({'error': 'Usage: renderer_ctl.py <renderer> get|set N|up N|dn N|mute|toggle|previous|next'}))
		return 1
	command = argv[2]
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
	if command == 'toggle':
		# play | pause where the renderer reports it, toggle otherwise
		state['state'] = played or 'toggle'
	print(json.dumps(state))
	return 0

if __name__ == '__main__':
	sys.exit(main(sys.argv))
