#
# SPDX-License-Identifier: GPL-3.0-or-later
# Copyright 2014 The moOde audio player project / Tim Curtis
# Copyright 2026 @Gjuju
#
# Driving whatever is playing: one backend per source.
#
# moOde's REST API covers moOde's own player. It covers nothing while a renderer
# plays - command/index.php has no transport command for one, and moOde's WebUI
# offers only "disconnect" there - so a renderer backend talks to that renderer's
# own daemon. It routes around nothing; there is nothing to route around.
#
# A backend implements all six transport verbs or it is not registered. Volume
# and mute are declared separately, because they genuinely come apart: AirPlay
# has a level to move and no mute anyone can read back.

import json
import time
import urllib.error
import urllib.request

import dbus

from mqtt_moode import log, moode_api, volume_scope


# The verbs a backend must implement to be registered at all. Every real
# candidate does (moOde/MPD, pibuz, shairport-sync, Bluetooth AVRCP), so
# advertising them one by one would be machinery for a case that does not exist.
TRANSPORT_VERBS = ('play', 'pause', 'toggle', 'stop', 'next', 'previous')


TRANSPORT_ALIASES = {'prev': 'previous'}


_SYSTEM_BUS = None


# pibuz (Qobuz Connect) control API. Unauthenticated by its own default, and
# reachable by www-data - both measured, not assumed.
DBUS_PROPS = 'org.freedesktop.DBus.Properties'


DBUS_OBJMGR = 'org.freedesktop.DBus.ObjectManager'


def dbus_bus():
    """The system bus, opened on first use.

    Lazily, because a player that never sees a renderer never needs it, and
    because a failure here has to withdraw a control rather than kill the daemon.
    """
    global _SYSTEM_BUS
    if _SYSTEM_BUS is None:
        _SYSTEM_BUS = dbus.SystemBus()
    return _SYSTEM_BUS


def dbus_props_all(service, path, interface):
    """Every property of one interface in a single round trip, or None.

    Measured: a round trip costs about 2 ms whatever comes back - GetAll over an
    interface carrying 25 properties timed the same as Get on one of them. So
    reading properties one at a time buys nothing and costs a round trip each.

    introspect=False, because dbus-python otherwise Introspects each proxy it
    builds: 1.29 ms against 0.69 for the same GetAll, measured. Safe for a read,
    which names its interface explicitly - but NOT for a write or a call, which
    need a signature. See dbus_set_prop.
    """
    try:
        obj = dbus_bus().get_object(service, path, introspect=False)
        return dbus.Interface(obj, DBUS_PROPS).GetAll(interface)
    except Exception:
        return None


def dbus_set_prop(service, path, interface, name, value):
    """Write one property. Introspected, unlike the reads.

    Measured: with introspect=False, Set answers "No such interface
    org.freedesktop.DBus.Properties" on the very object whose GetAll had just
    succeeded - dbus-python cannot work out the signature of the variant Set
    takes without it. Reads are the ones worth the saving anyway: they run every
    cycle, a command does not.
    """
    try:
        obj = dbus_bus().get_object(service, path)
        dbus.Interface(obj, DBUS_PROPS).Set(interface, name, value)
        return True
    except Exception as err:
        log('D-Bus set %s.%s failed: %s' % (interface, name, err))
        return False


def dbus_call(service, path, interface, method, *args):
    """Call one method. Introspected, like the writes and for the same reason:
    a call that carries arguments needs a signature, and a command is rare."""
    try:
        obj = dbus_bus().get_object(service, path)
        getattr(dbus.Interface(obj, interface), method)(*args)
        return True
    except Exception as err:
        log('D-Bus %s.%s failed: %s' % (interface, method, err))
        return False


def dbus_find(service, interface, root='/'):
    """First object under `service` implementing `interface`, or None.

    Looked up rather than configured: the path carries the device address, so it
    changes with every phone that connects.
    """
    try:
        obj = dbus_bus().get_object(service, root, introspect=False)
        managed = dbus.Interface(obj, DBUS_OBJMGR).GetManagedObjects()
    except Exception:
        return None
    for path, interfaces in managed.items():
        if interface in interfaces:
            return str(path)
    return None


# Renderer control backends
#
# One backend serves one source: the cfg_system flag moOde raises while that
# renderer plays, or None for moOde's own player. The bridge picks the backend
# matching what is playing and routes every command to it; a source with no
# backend gets its controls withdrawn in Home Assistant rather than a button that
# moves something else.
#
# Contract: implement all six transport verbs, or do not register. Volume is
# optional and declared, because it genuinely varies - AVRCP has no volume, and
# even moOde's own has none on a fixed 0dB output.


class Backend:
    """What the bridge needs from any source it can drive."""

    flag = None            # cfg_system flag this backend serves; None = moOde/MPD
    label = 'moOde'

    def reachable(self):
        """Is the mechanism answering right now? Checked every publish cycle."""
        return True

    def invalidate(self):
        """Drop anything cached for the current cycle.

        Called once per cycle by the publisher, and by a command before it acts
        on what it reads. A backend that caches nothing has nothing to do.
        """

    def volume_usable(self, cfg_rows):
        """Can this backend move the volume, given moOde's current config?

        Each backend answers from the state it actually depends on, rather than
        the bridge guessing on its behalf: moOde's mixer scope says nothing about
        a renderer's own software volume.
        """
        return False

    def mute_usable(self, cfg_rows):
        """Separate from volume, because they genuinely come apart.

        AirPlay has a volume and no readable mute at all: shairport-sync offers
        `mutetoggle` with nothing to read back, and a switch that toggles blind
        would show a state it does not know. Everything else answers the same as
        its volume.
        """
        return self.volume_usable(cfg_rows)

    def metadata(self):
        """Track information from the source itself, shaped like moOde's caches
        (artist, title, album, genre, duration in seconds, sformat, cover_url),
        or None to fall back to the cache.

        None is the right answer wherever moOde already caches the renderer: that
        file is written by the renderer itself and costs a read to use.
        """
        return None

    def volume_state(self):
        """(level 0-100, muted, scope) as this backend sees it, or None.

        None means the bridge keeps reporting moOde's own knob. A backend that
        moves its own volume MUST answer here, or the number in Home Assistant
        would come from one player while the slider moved another.
        """
        return None

    def transport(self, verb):
        raise NotImplementedError

    def set_volume(self, level):
        raise NotImplementedError

    def step_volume(self, direction, amount):
        raise NotImplementedError

    def set_mute(self, wanted):
        """wanted: True to mute, False to unmute, None to toggle."""
        raise NotImplementedError


class MoodeBackend(Backend):
    """moOde's own player, driven through its REST API."""

    flag = None
    label = 'moOde'

    def volume_usable(self, cfg_rows):
        # A fixed 0dB output has no volume to move: moOde's own volume command
        # changes nothing there.
        return volume_scope(cfg_rows) != 'none'

    def transport(self, verb):
        if verb == 'toggle':
            # moOde already knows to stop a radio and pause anything else
            moode_api('toggle_play_pause')
        else:
            moode_api(verb)

    def set_volume(self, level):
        moode_api('set_volume %d' % level)

    def step_volume(self, direction, amount):
        moode_api('set_volume %s %d' % ('-up' if direction == 'up' else '-dn', amount))

    def set_mute(self, wanted):
        """set_volume -mute is a TOGGLE, so honour an explicit on/off request."""
        if wanted is not None:
            current = moode_api('get_volume') or {}
            if (current.get('muted') == 'yes') == wanted:
                return
        moode_api('set_volume -mute')


PIBUZ_API = 'http://127.0.0.1:8182'


# One local HTTP read per publish cycle, and only while Qobuz plays. Cheap
# against a Rust daemon on loopback - unlike a PHP fork, which is why moOde's
# own state is still read directly.
PIBUZ_POLL_INTERVAL = 1.0


def pibuz_request(path, body=None, method='POST'):
    """One call to pibuz's control API. Parsed JSON, or None if it did not work.

    No Authorization header. pibuz ships with `[server] token` unset, which its
    own source documents as an unauthenticated control plane on loopback and LAN
    alike, and no qbzd.toml exists unless someone writes one. If a token IS set
    the call answers 401, and that is reported as what it is: the bridge keeps no
    copy of anyone's secret, so there is nothing here to leak or to rotate.
    """
    data = json.dumps(body).encode() if body is not None else b''
    req = urllib.request.Request(PIBUZ_API + path, data=data, method=method)
    if body is not None:
        req.add_header('Content-Type', 'application/json')

    try:
        with urllib.request.urlopen(req, timeout=5) as resp:
            raw = resp.read().decode('utf-8', 'replace').strip()
    except urllib.error.HTTPError as err:
        if err.code == 401:
            log('pibuz refused %s: a token is set in its qbzd.toml. The bridge '
                'holds no copy of it, so Qobuz controls stay off.' % path)
        else:
            log('pibuz %s failed: HTTP %s' % (path, err.code))
        return None
    except Exception as err:
        log('pibuz %s failed: %s' % (path, err))
        return None

    try:
        return json.loads(raw) if raw else {}
    except ValueError:
        return {}


class PibuzBackend(Backend):
    """Qobuz Connect, driven through pibuz's own HTTP API.

    Nothing is being routed around here: moOde has no command for this renderer,
    and its WebUI offers only "disconnect" while one plays. pibuz is the only
    thing that can move it.

    Its six transport routes carry exactly our verb names, so no translation
    table earns its keep. pibuz is started by moOde on demand and is simply
    absent the rest of the time, so "not answering" is a normal state, not a
    fault: the controls are withdrawn and that is all.
    """

    flag = 'qbzactive'
    label = 'Qobuz'

    def __init__(self):
        self.status = None
        self.read_at = 0.0

    def poll(self):
        """One /api/status read per publish cycle, shared by every caller.

        It answers "is pibuz there" and "where is its volume" at the same time,
        so knowing whether to offer the controls costs no extra call.
        """
        now = time.monotonic()
        if now - self.read_at >= PIBUZ_POLL_INTERVAL:
            self.read_at = now
            self.status = pibuz_request('/api/status', method='GET')
        return self.status

    def invalidate(self):
        """Re-read on the next cycle instead of serving a level we just moved."""
        self.read_at = 0.0

    def reachable(self):
        return self.poll() is not None

    def volume_usable(self, cfg_rows):
        # pibuz has its own software volume, so moOde's mixer scope does not
        # apply: a fixed 0dB output does not stop it.
        return True

    def volume_state(self):
        playback = (self.poll() or {}).get('playback') or {}
        level = playback.get('volume')
        if level is None:
            return None
        # pibuz works in 0.0-1.0, Home Assistant and moOde in 0-100. Measured:
        # /api/status reports the LIVE level, so it reads 0 while muted and
        # comes back on unmute - pibuz keeps the pre-mute level to itself
        # (nominal_volume is applied on its command routes, not here).
        return int(round(level * 100)), bool(playback.get('muted')), 'renderer'

    def transport(self, verb):
        if pibuz_request('/api/playback/%s' % verb) is None:
            self.invalidate()

    def set_volume(self, level):
        self.volume_cmd({'volume': max(0, min(100, level)) / 100.0})

    def step_volume(self, direction, amount):
        delta = amount / 100.0
        self.volume_cmd({'delta': delta if direction == 'up' else -delta})

    def set_mute(self, wanted):
        # pibuz takes the explicit form, so unlike moOde there is no need to
        # read the current state before deciding.
        self.volume_cmd({'mute': 'toggle' if wanted is None
                         else ('on' if wanted else 'off')})

    def volume_cmd(self, body):
        pibuz_request('/api/playback/volume', body)
        self.invalidate()


class DBusBackend(Backend):
    """A backend that reads its state from D-Bus properties, one snapshot a cycle.

    Every value a cycle publishes then comes from the same instant. Reading
    property by property did not only cost a round trip each - it also let the
    parts of one payload drift milliseconds apart, so a track could be reported
    against a volume read after it changed.

    The publisher clears the snapshot at the top of each cycle; a command clears
    it too, since it must act on what is true now rather than on what was true
    up to a second ago.
    """

    def __init__(self):
        self.snapshot = {}

    def invalidate(self):
        self.snapshot = {}

    def props(self, service, path, interface):
        """One interface's properties, fetched at most once per cycle."""
        if path is None:
            return {}
        key = (service, path, interface)
        if key not in self.snapshot:
            self.snapshot[key] = dbus_props_all(service, path, interface) or {}
        return self.snapshot[key]


# Bluetooth. The phone is the source and this player the sink, so the phone
# exposes the media player and we are the remote.
BLUEZ = 'org.bluez'


BLUEZ_PLAYER = 'org.bluez.MediaPlayer1'


BLUEALSA = 'org.bluealsa'


BLUEALSA_ROOT = '/org/bluealsa'


BLUEALSA_PCM = 'org.bluealsa.PCM1'


# BlueALSA packs both channels into one uint16 - high byte left, low byte right
# - and in each byte bit 7 is mute with the level in bits 0-6. Measured: level
# 34 on both channels reads 0x2222, and muting it reads 0xa2a2, so a mute keeps
# the level rather than zeroing it.
BT_MUTE_BIT = 0x80


BT_LEVEL_MAX = 0x7F


class BluezBackend(DBusBackend):
    """Bluetooth: AVRCP for transport, BlueALSA for the mixer.

    Two services, each owning what it owns. bluez carries the remote-control
    session with the phone; BlueALSA carries the local mixer, whose level is the
    same value bluez publishes on MediaTransport1 - measured equal on both sides
    - plus a real mute flag bluez does not expose at all.

    We are the remote here, not the player, so the phone decides what a command
    does: `Previous` past the first seconds of a track restarts it instead of
    going back. That is the phone's rule, and not something to correct.
    """

    flag = 'btactive'
    label = 'Bluetooth'

    VERBS = {'play': 'Play', 'pause': 'Pause', 'stop': 'Stop',
             'next': 'Next', 'previous': 'Previous'}

    def __init__(self):
        super().__init__()
        self.player = None
        self.pcm = None

    def forget(self):
        """Both paths carry the device address, so a disconnect invalidates them."""
        self.player = None
        self.pcm = None
        self.invalidate()

    def player_props(self):
        return self.props(BLUEZ, self.player_path(), BLUEZ_PLAYER)

    def pcm_props(self):
        return self.props(BLUEALSA, self.pcm_path(), BLUEALSA_PCM)

    def player_path(self):
        if self.player is None:
            self.player = dbus_find(BLUEZ, BLUEZ_PLAYER)
        return self.player

    def pcm_path(self):
        if self.pcm is None:
            self.pcm = dbus_find(BLUEALSA, BLUEALSA_PCM, BLUEALSA_ROOT)
        return self.pcm

    def reachable(self):
        if self.player_path() is None:
            return False
        if 'Status' not in self.player_props():
            self.forget()                  # the device left, the path is stale
            return False
        return True

    def volume_usable(self, cfg_rows):
        return self.raw_volume() is not None

    def raw_volume(self):
        """(level 0-127, muted) exactly as BlueALSA holds it, or None.

        Kept on BlueALSA's own scale so a mute or a step does not round-trip
        through 0-100 and drift.
        """
        raw = self.pcm_props().get('Volume')
        if raw is None:
            self.pcm = None
            return None
        left = (int(raw) >> 8) & 0xFF
        return left & BT_LEVEL_MAX, bool(left & BT_MUTE_BIT)

    def write_volume(self, level, muted):
        byte = (BT_MUTE_BIT if muted else 0) | max(0, min(BT_LEVEL_MAX, level))
        # Both channels together: this bridge has one volume, not a balance.
        return dbus_set_prop(BLUEALSA, self.pcm_path(), BLUEALSA_PCM, 'Volume',
                             dbus.UInt16((byte << 8) | byte))

    def volume_state(self):
        raw = self.raw_volume()
        if raw is None:
            return None
        level, muted = raw
        return int(round(level * 100.0 / BT_LEVEL_MAX)), muted, 'renderer'

    def metadata(self):
        """AVRCP carries what moOde caches for every other renderer and never
        caches for this one - which is why Bluetooth's fields were the only ones
        permanently empty."""
        track = self.player_props().get('Track')
        if track is None:
            return None

        def text(key):
            return str(track.get(key, '')).strip()

        meta = {'artist': text('Artist'), 'title': text('Title'),
                'album': text('Album'), 'genre': text('Genre')}
        try:
            # AVRCP reports milliseconds, as AirPlay and Spotify's caches do.
            meta['duration'] = float(track.get('Duration', 0)) / 1000.0
        except (TypeError, ValueError):
            meta['duration'] = 0.0

        # Source and decoded are two different things, and moOde keeps them
        # apart: the codec alone is the source - aptX-HD is lossy and carries no
        # bit depth of its own - while the depth and rate belong to what came
        # out of the decoder. audioinfo.php reads the same two places.
        pcm = self.pcm_props()
        if pcm:
            codec = pcm.get('Codec')
            if codec:
                meta['sformat'] = str(codec)
            # PCM1.Format is the numeric form of the name bluealsa-cli prints:
            # S24_LE reads 0x8418, whose low byte is the 24. moOde takes the
            # same figure out of the string.
            fmt = pcm.get('Format')
            rate = pcm.get('Sampling')
            channels = pcm.get('Channels')
            if fmt and rate:
                # Shaped like the oformat the other renderers' caches carry, so
                # one key holds one kind of value whoever filled it.
                meta['oformat'] = 'PCM %d/%g kHz, %dch' % (
                    int(fmt) & 0xFF, int(rate) / 1000.0, int(channels or 2))
        return meta

    def transport(self, verb):
        self.invalidate()                  # a command acts on the state now
        path = self.player_path()
        if path is None:
            return
        if verb == 'toggle':
            # AVRCP has no toggle, so ask what it is doing before deciding.
            status = str(self.player_props().get('Status') or '')
            verb = 'pause' if status == 'playing' else 'play'
        if not dbus_call(BLUEZ, path, BLUEZ_PLAYER, self.VERBS[verb]):
            self.forget()

    def set_volume(self, level):
        self.invalidate()
        raw = self.raw_volume()
        if raw is None:
            return
        self.write_volume(int(round(max(0, min(100, level)) * BT_LEVEL_MAX / 100.0)),
                          raw[1])

    def step_volume(self, direction, amount):
        self.invalidate()
        raw = self.raw_volume()
        if raw is None:
            return
        step = int(round(amount * BT_LEVEL_MAX / 100.0))
        self.write_volume(raw[0] + (step if direction == 'up' else -step), raw[1])

    def set_mute(self, wanted):
        self.invalidate()
        raw = self.raw_volume()
        if raw is None:
            return
        level, muted = raw
        wanted = (not muted) if wanted is None else wanted
        if wanted != muted:
            self.write_volume(level, wanted)


# AirPlay. shairport-sync publishes MPRIS on the SYSTEM bus, plus its own
# interface carrying what MPRIS has no room for.
MPRIS_NAME = 'org.mpris.MediaPlayer2.ShairportSync'


MPRIS_PATH = '/org/mpris/MediaPlayer2'


MPRIS_PLAYER = 'org.mpris.MediaPlayer2.Player'


SHAIRPORT_NAME = 'org.gnome.ShairportSync'


SHAIRPORT_PATH = '/org/gnome/ShairportSync'


SHAIRPORT_IFACE = 'org.gnome.ShairportSync'


class AirPlayBackend(DBusBackend):
    """AirPlay, driven over shairport-sync's MPRIS interface.

    MPRIS carries all six verbs, `PlayPause` included, so nothing is composed.

    Two measured traps shape this:

    - `systemctl is-active shairport-sync` reads `inactive` the whole time it is
      playing, and the unit is `disabled`: moOde runs it as a child of php-fpm.
      Its own `Active` property is the honest sign it is there.
    - `PlaybackStatus` does NOT follow the sender - it stayed "Playing" across a
      pause the ALSA substream clearly registered - so it is never read here.
      `state` keeps coming from the substream, as it already did.
    """

    flag = 'aplactive'
    label = 'AirPlay'

    VERBS = {'play': 'Play', 'pause': 'Pause', 'toggle': 'PlayPause',
             'stop': 'Stop', 'next': 'Next', 'previous': 'Previous'}

    def player_props(self):
        return self.props(MPRIS_NAME, MPRIS_PATH, MPRIS_PLAYER)

    def reachable(self):
        # shairport's own Active, not MPRIS CanControl: CanControl stays true
        # with no session at all, and not the systemd unit either, which reads
        # inactive the whole time moOde runs it under php-fpm. A second round
        # trip, because the two live on different interfaces.
        return bool(self.props(SHAIRPORT_NAME, SHAIRPORT_PATH,
                               SHAIRPORT_IFACE).get('Active'))

    def volume_usable(self, cfg_rows):
        return 'Volume' in self.player_props()

    def mute_usable(self, cfg_rows):
        # MPRIS has no mute, and shairport's own `mutetoggle` reports nothing
        # back. A switch has to show a state; this one would be guessing.
        return False

    def __init__(self):
        super().__init__()
        # What we last asked for, and what Volume read at that moment.
        #
        # Volume does not follow a command. Measured with a signal subscription:
        # PropertiesChanged fires only when the NEXT command arrives, and then
        # carries the PREVIOUS one's level - so the delay is not this bridge
        # polling too slowly, the value simply does not exist yet. Between two
        # commands the property is therefore known to be stale, and what was
        # asked for is the better answer. It is handed back as soon as it moves.
        #
        # Whether that is shairport-sync or the sender is not established: the
        # volume belongs to the sender, and this was measured against one sender
        # only (the OwnTone bench, for want of an Apple device).
        self.requested = None

    def volume_state(self):
        vol = self.player_props().get('Volume')
        if vol is None:
            return None
        # MPRIS works in 0.0-1.0.
        level = int(round(float(vol) * 100))
        if self.requested is not None:
            if level != self.requested:
                # The property has not caught up yet. Report what was asked for:
                # the command applies at once, it is only the feedback that is a
                # step behind, so this number is the true one meanwhile.
                return self.requested, False, 'renderer'
            # Caught up. Hand the property back the job - it, not us, knows what
            # the sender finally did.
            self.requested = None
        return level, False, 'renderer'

    def transport(self, verb):
        self.invalidate()
        dbus_call(MPRIS_NAME, MPRIS_PATH, MPRIS_PLAYER, self.VERBS[verb])

    def set_volume(self, level):
        level = max(0, min(100, level))
        if dbus_call(MPRIS_NAME, MPRIS_PATH, MPRIS_PLAYER, 'SetVolume',
                     dbus.Double(level / 100.0)):
            self.requested = level
            self.invalidate()

    def step_volume(self, direction, amount):
        base = self.requested
        if base is None:
            state = self.volume_state()
            if state is None:
                return
            base = state[0]
        self.set_volume(base + (amount if direction == 'up' else -amount))


# Squeezelite. The player itself has no local control interface at all -
# measured: `squeezelite -?` lists slimproto, LIRC and GPIO as its only inputs,
# and moOde passes no -V, so its volume is software inside squeezelite. Every
# control lives on the LMS server it connected to, which makes this the one
# backend that reaches off the box.
LMS_SLIMPROTO_PORT = 3483


# LMS serves JSON-RPC on its web port. Configurable there, but not discoverable:
# squeezelite's connection carries the slimproto port and nothing else. A server
# moved off 9000 simply does not answer, and the controls stay withdrawn rather
# than reaching somewhere else.
LMS_HTTP_PORT = 9000


# Short on purpose. Waiting on a socket costs no CPU - the thread sleeps - but
# the publish cycle still has to end, and this is the only backend whose peer
# can be a machine that went away.
LMS_TIMEOUT = 2.0


LMS_POLL_INTERVAL = 1.0


# LMS puts two different kinds of value in `type`, both measured: a three-letter
# code for a local file ("flc"), and a ready-made label for a stream
# ("MP3 Radio"). The codes are translated here; anything else is kept exactly as
# LMS wrote it, because uppercasing turned "MP3 Radio" into "MP3 RADIO".
LMS_CODECS = {'flc': 'FLAC', 'alc': 'ALAC', 'ops': 'Opus', 'aif': 'AIFF',
              'mp3': 'MP3', 'aac': 'AAC', 'ogg': 'Ogg', 'wav': 'WAV',
              'wma': 'WMA', 'pcm': 'PCM', 'dsf': 'DSD', 'dff': 'DSD'}


def lms_hex_ipv4(text):
    """/proc/net/tcp holds addresses little-endian: 9801A8C0 is 192.168.1.152."""
    packed = int(text, 16)
    return '%d.%d.%d.%d' % (packed & 0xFF, (packed >> 8) & 0xFF,
                            (packed >> 16) & 0xFF, (packed >> 24) & 0xFF)


def lms_endpoint():
    """(server address, our address) taken from squeezelite's own connection.

    Squeezelite finds its server by UDP broadcast and writes the answer nowhere,
    but it holds a TCP connection to it - measured on the box:
        192.168.1.9:54000 -> 192.168.1.152:3483
    Read from /proc/net/tcp rather than from `ss`: the same information with no
    fork and no privileges. The dominant cost this daemon ever carried was a
    forked sudo, so anything running every cycle does not get to fork.

    IPv4 only, because slimproto discovery is an IPv4 broadcast.
    """
    try:
        with open('/proc/net/tcp') as fh:
            rows = fh.readlines()[1:]
    except OSError:
        return None
    for row in rows:
        fields = row.split()
        if len(fields) < 4 or fields[3] != '01':      # 01 = ESTABLISHED
            continue
        try:
            remote_ip, remote_port = fields[2].split(':')
            local_ip = fields[1].split(':')[0]
            if int(remote_port, 16) != LMS_SLIMPROTO_PORT:
                continue
        except ValueError:
            continue
        return lms_hex_ipv4(remote_ip), lms_hex_ipv4(local_ip)
    return None


class SqueezeliteBackend(Backend):
    """Squeezelite, driven through the LMS server it is connected to.

    LMS answers every verb we need and more: transport, an absolute and a
    relative volume, a real mute and full metadata. It also reports a playing
    position, which nothing else here does and which the bridge deliberately
    does not carry.

    Two encodings measured on a real server (Lyrion 9.1.2, squeezelite
    2.0.0-1541), each of which would have produced a wrong reading:

    - a mute is carried as a NEGATIVE volume. Muted at 20, `mixer volume`
      answers -20 - the level is kept in the magnitude, as BlueALSA keeps it
      under its mute bit. So one status read gives both, and `mixer muting`
      never has to be asked.
    - `mixer muting` answers nothing at all until something has set it once:
      the key is absent rather than 0. Deriving the mute from the sign avoids
      that trap entirely.

    `slactive` is NOT a play state: moOde writes it from slpower.sh, which LMS
    drives on power commands, and it was measured at 1 with squeezelite running
    and no server reachable at all. The state published for this source keeps
    coming from the ALSA substream, as it does for every other renderer.
    """

    flag = 'slactive'
    label = 'Squeezelite'

    # Verified on hardware by reading the state back after each one, rather than
    # by trusting the reply: `pause` with no argument is the toggle, `pause 1`
    # the explicit pause, and the two playlist jumps move the index both ways.
    VERBS = {'play': ['play'], 'pause': ['pause', 1], 'stop': ['stop'],
             'toggle': ['pause'], 'next': ['playlist', 'index', '+1'],
             'previous': ['playlist', 'index', '-1']}

    # What LMS writes into a field it has nothing for. Measured on an untagged
    # file: all three appear. One key holds one value here, and an invented
    # placeholder is worse than an empty string - it would reach Home Assistant
    # looking like a real tag.
    PLACEHOLDERS = ('No Artist', 'No Album', 'No Genre')

    # a artist, l album, g genre, d duration, K artwork URL, r bitrate,
    # o content type, T sample rate, I sample size.
    STATUS_TAGS = 'tags:algdKroTI'

    def __init__(self):
        self.host = None
        self.local_ip = None
        self.player = None
        self.status = None
        self.read_at = 0.0
        self.complaint = None

    def forget(self):
        """Drop everything looked up: the server can move and the player id with
        it, so a failed call must not pin us to an address that is gone."""
        self.host = None
        self.player = None
        self.status = None

    def request(self, command, player='-'):
        """One JSON-RPC call to LMS. Its `result`, or None.

        No credentials are sent or held. LMS authentication is optional and off
        by default; with it on the call answers 401, and that is reported as
        what it is rather than worked around - the bridge keeps no copy of
        anyone's password, exactly as with pibuz.
        """
        if self.host is None:
            return None
        body = json.dumps({'id': 1, 'method': 'slim.request',
                           'params': [player, list(command)]}).encode()
        req = urllib.request.Request(
            'http://%s:%d/jsonrpc.js' % (self.host, LMS_HTTP_PORT),
            data=body, method='POST')
        req.add_header('Content-Type', 'application/json')
        try:
            with urllib.request.urlopen(req, timeout=LMS_TIMEOUT) as resp:
                raw = resp.read().decode('utf-8', 'replace')
            self.complaint = None
            return json.loads(raw).get('result')
        except urllib.error.HTTPError as err:
            problem = ('authentication is enabled there. The bridge holds no '
                       'copy of it, so Squeezelite controls stay off.'
                       if err.code == 401 else 'HTTP %s' % err.code)
        except Exception as err:
            problem = str(err)
        # Once per problem, not once per cycle. slactive stays 1 with the server
        # gone, so this path runs every second in an entirely ordinary
        # situation, and a warning that fires in the nominal case teaches
        # everyone to ignore the log.
        if problem != self.complaint:
            log('LMS at %s: %s' % (self.host, problem))
            self.complaint = problem
        return None

    def resolve(self):
        """Find the server and which player we are on it. Both are cached.

        The player is matched on the address squeezelite connects from rather
        than on a MAC we would have to guess: moOde passes no -m, so the id is
        whichever interface squeezelite picked for itself.
        """
        if self.host is None:
            found = lms_endpoint()
            if found is None:
                return False
            self.host, self.local_ip = found
        if self.player is None:
            result = self.request(['players', 0, 99])
            if result is None:
                return False
            for row in result.get('players_loop') or []:
                if str(row.get('ip', '')).split(':')[0] == self.local_ip:
                    self.player = str(row.get('playerid'))
                    break
        return self.player is not None

    def poll(self):
        """One status read per cycle, shared by everything that needs it.

        It carries the state, the volume, the mute and the whole track at once,
        so offering the controls costs no call of its own - the same reason
        DBusBackend takes one snapshot a cycle.
        """
        now = time.monotonic()
        if now - self.read_at < LMS_POLL_INTERVAL:
            return self.status
        self.read_at = now
        self.status = None
        if self.resolve():
            self.status = self.request(['status', '-', 1, self.STATUS_TAGS],
                                       self.player)
        if self.status is None:
            self.forget()
        return self.status

    def invalidate(self):
        """Re-read on the next cycle instead of serving a level we just moved."""
        self.read_at = 0.0

    def reachable(self):
        return self.poll() is not None

    def volume_usable(self, cfg_rows):
        # squeezelite carries its own software volume, so moOde's mixer scope
        # does not apply: a fixed 0dB output does not stop it. moOde sets the
        # hardware mixer to its maximum when it starts the service for exactly
        # that reason.
        return 'mixer volume' in (self.poll() or {})

    def volume_state(self):
        status = self.poll() or {}
        if 'mixer volume' not in status:
            return None
        try:
            level = int(status['mixer volume'])
        except (TypeError, ValueError):
            return None
        # The sign is the mute; the magnitude is the level LMS will come back to.
        return min(100, abs(level)), level < 0, 'renderer'

    def track(self):
        """The current entry, wherever this kind of source keeps it.

        A stream carries its tags in remoteMeta and leaves the playlist entry
        holding the bare URL, so reading only one of the two would report a
        radio as having no artist at all.
        """
        status = self.poll() or {}
        entry = (status.get('playlist_loop') or [{}])[0]
        remote = status.get('remoteMeta') or {}
        return dict(entry, **{k: v for k, v in remote.items() if v})

    def text(self, track, key):
        value = str(track.get(key, '')).strip()
        return '' if value in self.PLACEHOLDERS else value

    def metadata(self):
        track = self.track()
        if not track:
            return None

        meta = {'artist': self.text(track, 'artist'),
                'title': self.text(track, 'title'),
                'album': self.text(track, 'album'),
                'genre': self.text(track, 'genre')}
        try:
            # Seconds already, unlike AVRCP and the AirPlay cache.
            meta['duration'] = float(track.get('duration') or 0)
        except (TypeError, ValueError):
            meta['duration'] = 0.0

        # What the server sent, which is what `sformat` means everywhere else
        # here. What squeezelite decoded it to is not reported by anyone, so
        # `oformat` is left empty rather than filled with a guess.
        raw_type = str(track.get('type') or '').strip()
        codec = LMS_CODECS.get(raw_type.lower(), raw_type)
        size, rate = track.get('samplesize'), track.get('samplerate')
        try:
            if codec and size and rate:
                meta['sformat'] = '%s %d/%g kHz' % (codec, int(size),
                                                    int(rate) / 1000.0)
            elif codec:
                meta['sformat'] = codec
        except (TypeError, ValueError):
            meta['sformat'] = codec

        # Absolute, because artwork lives on the server rather than under
        # moOde's web root - the bridge prefixes anything relative with the
        # player's own base URL, which would point at the wrong machine.
        art = str(track.get('artwork_url') or '').strip()
        if art and self.host and not art.startswith(('http://', 'https://')):
            art = 'http://%s:%d/%s' % (self.host, LMS_HTTP_PORT, art.lstrip('/'))
        meta['cover_url'] = art
        return meta

    def transport(self, verb):
        self.invalidate()                  # a command acts on the state now
        if self.resolve():
            self.request(self.VERBS[verb], self.player)

    def volume_cmd(self, *args):
        if self.resolve():
            self.request(['mixer'] + list(args), self.player)
        self.invalidate()

    def set_volume(self, level):
        self.volume_cmd('volume', max(0, min(100, level)))

    def step_volume(self, direction, amount):
        # LMS takes the relative form itself, so nothing has to be read first.
        self.volume_cmd('volume', '%s%d' % ('+' if direction == 'up' else '-',
                                            amount))

    def set_mute(self, wanted):
        self.volume_cmd('muting', 'toggle' if wanted is None else int(bool(wanted)))
