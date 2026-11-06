#!/usr/bin/env python3
#
# SPDX-License-Identifier: GPL-3.0-or-later
# Copyright 2014 The moOde audio player project / Tim Curtis
# Copyright 2026 @Gjuju
#
# moOde -> MQTT bridge with Home Assistant discovery. Started and stopped by the
# worker, which writes its config from cfg_mqtt first.
#
# Publishes what moOde knows (player state, whether audio is actually flowing,
# which app owns the local display) and accepts transport/volume commands back.
#
# Design notes that matter:
#
# - Volume, mute and transport go through moOde's REST API (www/command/index.php)
#   only, whatever is playing: MPD or a renderer it can control. The API carries
#   moOde's rules (multiroom, radio, what a renderer can do) and says which
#   controls the current source offers.
# - get_volume is read when something may have changed, not on a timer: an MPD
#   idle event, a write to moOde's database, or a change of the renderer state
#   file, which renderer_ctl.py watch keeps up to date while MQTT is on.
# - "Audio active" is read from the ALSA substream (hw_params), not from MPD, so
#   AirPlay / Spotify / Bluetooth / line-in count too. touchmon.php uses the same
#   source. MPD closes the device between tracks, hence AUDIO_OFF_DELAY.
# - The database is opened read-only. Writing cfg_system behind moOde's back
#   desyncs its PHP session cache.
# - A single thread publishes. The MPD idle and file watch threads only wake it,
#   so there is no race between the producers.

import configparser
import json
import os
import signal
import socket
import ssl
import sys
import threading
import time

import musicpd
import paho.mqtt.client as mqtt

from mqtt_moode import (DISPLAY_RECHECK, RENDERER_FLAGS, RENDVOL_FILE, SQLDB, db_read,
                        display_power, display_source, is_radio_stream, log,
                        moode_api, moode_release, output_is_open, read_renderer_meta)


CONF_PATH = os.environ.get('MQTT_BRIDGE_CONF', '/etc/mqtt-bridge.conf')

# Payload -> REST API command
TRANSPORT_COMMANDS = {'toggle': 'toggle_play_pause', 'play': 'play', 'pause': 'pause',
                      'stop': 'stop', 'next': 'next', 'previous': 'previous', 'prev': 'previous'}

# get_volume scope -> published volume_scope, the values the bridge always published
VOLUME_SCOPES = {'source': 'renderer', 'dac': 'hardware', 'mpd': 'mpd', 'camilladsp': 'mpd',
                 'fixed': 'none'}

# get_volume once the triggers settle: a slider drag fires one per step
VOLUME_SETTLE = 0.3
# How often the database and the renderer state file are checked for a change
WATCH_INTERVAL = 0.1


# Entities this bridge used to announce. Removing one from the code is not
# enough: its discovery config was published retained, so Home Assistant keeps
# showing it until an empty payload replaces it. Drop an entry once every install
# has run a version that retired it.
#
# `update` came from the standalone add-on, which checked its own releases;
# inside moOde, moOde's own update check covers it.
RETIRED_ENTITIES = (('sensor', 'quality'), ('binary_sensor', 'update'))


running = True


def local_address(host, port):
    """This box's address as seen from the broker.

    Asking the routing table which source address reaches the broker beats
    listing interfaces: a player can have both Ethernet and Wi-Fi up with
    different addresses, and the one that reaches the broker is the one Home
    Assistant will reach too. No packet is sent - connect() on a UDP socket only
    fixes the route.
    """
    try:
        sock = socket.socket(socket.AF_INET, socket.SOCK_DGRAM)
        try:
            sock.connect((host, port))
            return sock.getsockname()[0]
        finally:
            sock.close()
    except OSError:
        return ''


class NamedTLSContext(ssl.SSLContext):
    """Verifies the certificate against `server_name` instead of the host dialled.

    paho has no option for it: it hands its connect host to wrap_socket as the
    name to check. That breaks the usual LAN case of a public certificate - a
    Let's Encrypt one for example - reached by IP, since the certificate names a
    domain and not the address. Measured: dialling the IP while checking the
    certificate's name passes full verification; dialling the name went to the
    public address instead, where the broker is not listening.
    """

    server_name = None

    def wrap_socket(self, sock, *args, server_hostname=None, **kwargs):
        return super().wrap_socket(sock, *args,
                                   server_hostname=self.server_name or server_hostname,
                                   **kwargs)


def tls_context(cfg):
    """Full verification, chain and name, against the system's CAs: a public
    certificate such as Let's Encrypt passes, a self-signed one does not."""
    ctx = NamedTLSContext(ssl.PROTOCOL_TLS_CLIENT)
    ctx.load_default_certs()
    ctx.server_name = cfg['tls_server_name'] or None
    return ctx


def make_client(cfg, client_id):
    """A client carrying this config's credentials and TLS."""
    try:
        # paho-mqtt 2.x requires an explicit callback API version; trixie
        # ships 2.1. Keep 1.x working so the script is not pinned to it.
        client = mqtt.Client(mqtt.CallbackAPIVersion.VERSION2, client_id=client_id)
    except AttributeError:
        client = mqtt.Client(client_id=client_id)
    if cfg['username']:
        client.username_pw_set(cfg['username'], cfg['password'])
    if cfg['tls']:
        client.tls_set_context(tls_context(cfg))
    return client


# Configuration


def load_config():
    if not os.path.exists(CONF_PATH):
        log('FATAL: %s not found (the worker writes it when MQTT is turned on)' % CONF_PATH)
        sys.exit(1)

    # No interpolation: a '%' in the broker password would otherwise stop the
    # bridge from starting.
    parser = configparser.ConfigParser(interpolation=None)
    parser.read(CONF_PATH)
    broker = parser['broker']
    moode = parser['moode'] if parser.has_section('moode') else {}

    cfg = {
        'host': broker.get('host', 'localhost'),
        'port': broker.getint('port', 1883),
        'username': broker.get('username', '') or None,
        'password': broker.get('password', '') or None,
        'client_id': broker.get('client_id', 'mqtt-bridge'),
        'tls': (broker.get('tls', 'no') or 'no').lower() in ('yes', 'true', '1', 'on'),
        'tls_server_name': (broker.get('tls_server_name', '') or '').strip(),
        'instance': moode.get('instance', 'moode'),
        'friendly_name': moode.get('friendly_name', 'moOde'),
        'prefix': moode.get('topic_prefix', 'moode'),
        'discovery_prefix': moode.get('discovery_prefix', 'homeassistant'),
        'poll_interval': float(moode.get('poll_interval', 1.0)),
        'audio_off_delay': float(moode.get('audio_off_delay', 5.0)),
        'volume_step': int(moode.get('volume_step', 5)),
        'mpd_host': moode.get('mpd_host', 'localhost'),
        'mpd_port': int(moode.get('mpd_port', 6600)),
    }
    return cfg


# The bridge


class Bridge:
    def __init__(self, cfg):
        self.cfg = cfg
        self.base = '%s/%s' % (cfg['prefix'], cfg['instance'])
        self.wake = threading.Event()
        self.published = {}
        self.player_sig = None
        self.audio_closed_since = None
        self.audio_state = False
        self.display_power_state = 'unknown'
        self.display_power_checked_at = 0.0
        self.web_base = ''
        self.release = moode_release()
        self.status_cli = musicpd.MPDClient()

        # Last get_volume (or set_volume) answer, and when to read it again
        self.vol = {}
        self.vol_due = 0.0
        # A multiroom receiver plays what another player sends: no control here
        self.receiver = False

        self.client = make_client(cfg, cfg['client_id'])
        self.client.will_set(self.topic('availability'), 'offline', retain=True)
        self.client.on_connect = self.on_connect
        self.client.on_connect_fail = self.on_connect_fail
        self.client.on_message = self.on_message
        self.connect_problem = None

    def resolve_web_base(self):
        """AirPlay artwork is a path under moOde's web root, unlike the absolute
        URLs Spotify and Qobuz hand over, so it needs this box's address to be
        reachable from Home Assistant. Detected, falling back to mDNS."""
        ip = local_address(self.cfg['host'], self.cfg['port'])
        self.web_base = ('http://%s' % ip) if ip else \
                        ('http://%s.local' % socket.gethostname())
        log('artwork base URL: %s (detected)' % self.web_base)

    def refresh_volume(self, delay=VOLUME_SETTLE):
        """Read get_volume once the triggers have been quiet for `delay`."""
        self.vol_due = time.monotonic() + delay
        self.wake.set()

    def topic(self, suffix):
        return '%s/%s' % (self.base, suffix)

    def publish(self, suffix, payload, force=False):
        """Publish retained, but only on change: no flood, no stale retained."""
        if not force and self.published.get(suffix) == payload:
            return
        self.published[suffix] = payload
        self.client.publish(self.topic(suffix), payload, retain=True)

    # MQTT callbacks

    def on_connect(self, client, userdata, flags, rc, properties=None):
        # paho 2.x hands over a ReasonCode, 1.x a plain int.
        failure = getattr(rc, 'is_failure', None)
        if failure if failure is not None else rc != 0:
            self.connect_failed('broker refused: %s' % rc)
            return
        self.connect_problem = None
        log('MQTT connected to %s:%s%s' % (self.cfg['host'], self.cfg['port'],
                                           ' (TLS)' if self.cfg['tls'] else ''))
        client.publish(self.topic('availability'), 'online', retain=True)
        self.resolve_web_base()
        for sub in ('cmd/volume', 'cmd/mute', 'cmd/transport'):
            client.subscribe(self.topic(sub))
        self.publish_discovery()
        # A reconnect starts from an empty broker state as far as we know, so
        # republish everything rather than trusting our change detection.
        self.published.clear()
        self.player_sig = None
        self.refresh_volume(0)

    def on_connect_fail(self, client, userdata):
        """paho retries a failed connection on its own and says nothing: it logs
        at debug level only, without the reason. It calls this from inside the
        except block that caught the failure, so that exception is still the
        one being handled - a certificate refused, TLS against a plain port, a
        broker that is down."""
        err = sys.exc_info()[1]
        self.connect_failed(str(err) if err else 'no reason given')

    def connect_failed(self, problem):
        # Once per problem, not once per retry: a warning repeated every
        # backoff interval teaches everyone to ignore the log.
        if problem != self.connect_problem:
            log('MQTT connection to %s:%s failed: %s' % (
                self.cfg['host'], self.cfg['port'], problem))
            self.connect_problem = problem

    def on_message(self, client, userdata, msg):
        payload = msg.payload.decode('utf-8', 'replace').strip()
        topic = msg.topic

        try:
            if topic.endswith('/cmd/volume'):
                self.cmd_volume(payload)
            elif topic.endswith('/cmd/mute'):
                self.cmd_mute(payload)
            elif topic.endswith('/cmd/transport'):
                self.cmd_transport(payload)
        except Exception as err:                      # never let a bad payload kill the loop
            log('command error on %s (%r): %s' % (topic, payload, err))

        self.wake.set()

    # Commands
    #
    # Parsing the payload is the bridge's job; the API decides what the current
    # source can do and answers a refusal with an alert, logged by moode_api.

    def for_command(self, what):
        if self.receiver:
            log('%s ignored: multiroom receiver active' % what)
            return False
        return True

    def volume_reply(self, reply):
        # set_volume answers with the new state, a refusal does not
        if isinstance(reply, dict) and 'volume' in reply:
            self.vol = reply
            self.wake.set()
        else:
            self.refresh_volume(0)

    def cmd_volume(self, payload):
        parts = payload.split()
        if not parts or not self.for_command('volume command'):
            return

        if parts[0] in ('up', 'dn', 'down'):
            amount = int(parts[1]) if len(parts) > 1 and parts[1].isdigit() \
                else self.cfg['volume_step']
            cmd = 'set_volume -%s %d' % ('up' if parts[0] == 'up' else 'dn', amount)
        elif parts[0].isdigit():
            cmd = 'set_volume %d' % min(100, int(parts[0]))
        else:
            log('unknown volume payload: %r' % payload)
            return
        self.volume_reply(moode_api(cmd))

    def cmd_mute(self, payload):
        wanted = payload.lower()
        if wanted in ('on', 'true', '1', 'mute'):
            state = True
        elif wanted in ('off', 'false', '0', 'unmute'):
            state = False
        else:
            state = None                              # anything else toggles

        if not self.for_command('mute command'):
            return
        # The API only toggles
        if state is not None and state == (self.vol.get('muted') == 'yes'):
            return
        self.volume_reply(moode_api('set_volume -mute'))

    def cmd_transport(self, payload):
        cmd = TRANSPORT_COMMANDS.get(payload.lower())
        if cmd is None:
            log('unknown transport payload: %r' % payload)
            return
        if self.for_command('transport %r' % payload):
            moode_api(cmd)

    # State collection and publishing

    def mpd_connect(self, client):
        try:
            client.disconnect()
        except Exception:
            pass
        client.connect(self.cfg['mpd_host'], self.cfg['mpd_port'])

    def collect_and_publish(self):
        cfg_rows = db_read(list(RENDERER_FLAGS) +
                           ['cardnum', 'multiroom_tx', 'local_display', 'peppy_display',
                            'rxactive', 'audioin'])

        if self.vol_due is not None and time.monotonic() >= self.vol_due:
            self.vol_due = None
            # get_volume carries an alert where the source has no volume: not an error here
            reply = moode_api('get_volume', log_alert=False)
            if isinstance(reply, dict) and 'source' in reply:
                self.vol = reply

        active_flag = next((f for f in RENDERER_FLAGS if cfg_rows.get(f) == '1'), None)
        self.receiver = active_flag is None and cfg_rows.get('rxactive') == '1'
        renderer_active = active_flag is not None or self.receiver
        card_open = output_is_open(cfg_rows)

        # Audio on is immediate; audio off has to survive audio_off_delay, because
        # MPD closes the ALSA device between tracks (touchmon.php does the same
        # with TOUCHMON_CLOSED_COUNT).
        if card_open or renderer_active:
            self.audio_closed_since = None
            self.audio_state = True
        elif self.audio_state:
            now = time.monotonic()
            if self.audio_closed_since is None:
                self.audio_closed_since = now
            elif now - self.audio_closed_since >= self.cfg['audio_off_delay']:
                self.audio_state = False

        self.publish('audio', 'ON' if self.audio_state else 'OFF')

        # Home Assistant offers exactly what the API says the current source can do
        for suffix, key in (('controls', 'can_transport'), ('volume', 'can_volume'),
                            ('mute', 'can_mute')):
            usable = not self.receiver and self.vol.get(key) is True
            self.publish('%s/available' % suffix, 'online' if usable else 'offline')

        if cfg_rows.get('peppy_display') == '1':
            app = 'peppy'
        elif cfg_rows.get('local_display') == '1':
            app = 'webui'
        else:
            app = 'none'
        self.publish('display/app', app)

        # No display configured means moOde started no X server, so there is
        # nothing to ask. Its own worker gates the same call the same way, and
        # both flags are already in hand for display/app above.
        if cfg_rows.get('local_display') == '1' or cfg_rows.get('peppy_display') == '1':
            now = time.monotonic()
            if now - self.display_power_checked_at >= DISPLAY_RECHECK:
                self.display_power_state = display_power()
                self.display_power_checked_at = now
        else:
            self.display_power_state = 'unknown'
        # Publish nothing rather than inventing an OFF: the HA entity then stays
        # "unknown", which is the truth - there is no screen to report on.
        if self.display_power_state != 'unknown':
            self.publish('display/power',
                         'ON' if self.display_power_state == 'on' else 'OFF')

        try:
            status = self.status_cli.status()
            song = self.status_cli.currentsong()
        except Exception:
            try:
                self.mpd_connect(self.status_cli)
                status = self.status_cli.status()
                song = self.status_cli.currentsong()
            except Exception as err:
                log('MPD status unavailable: %s' % err)
                return

        is_radio = is_radio_stream(song, status)
        station = song.get('name', '').strip()

        # A renderer holds the device and MPD is stopped: its currentsong and its
        # state both describe the track from before. Take the metadata from the
        # renderer's own cache, and the play state from the device being open.
        meta = read_renderer_meta(active_flag) if active_flag else {}
        try:
            volume = int(self.vol.get('volume'))
        except (TypeError, ValueError):
            volume = 0
        if renderer_active:
            # The renderer's own state where the API reports it, else whether it holds the device
            state = self.vol.get('state')
            if state not in ('play', 'pause', 'stop'):
                state = 'play' if card_open else 'stop'
        else:
            state = status.get('state', 'unknown')

        player = {
            'state': state,
            # The level of the current source as the API reports it; whether the
            # control can be used right now is carried by its availability topic.
            'volume': volume,
            'volume_scope': VOLUME_SCOPES.get(self.vol.get('scope'), ''),
            'mute': self.vol.get('muted') == 'yes',
            'source': display_source(cfg_rows, is_radio),
            'station': '' if renderer_active else station,
            # Empty rather than invented: one key, one value. No placeholder
            # text and no station name spilling into artist or album. While a
            # renderer plays, these come from its own metadata cache.
            'artist': (meta.get('artist') or '').strip() if renderer_active
                      else song.get('artist', '').strip(),
            'title': (meta.get('title') or '').strip() if renderer_active
                     else song.get('title', '').strip(),
            'album': (meta.get('album') or '').strip() if renderer_active
                     else song.get('album', '').strip(),
            'audio': '' if renderer_active else status.get('audio', ''),
            # What the renderer received, e.g. "FLAC 16/44.1 kHz" - and what came
            # out of its decoder, e.g. "PCM 24/48 kHz, 2ch". Two different
            # things, kept apart as moOde's Audio Information does: `quality`
            # above is a third one again, what the DAC is actually fed.
            'source_format': (meta.get('sformat') or '').strip(),
            'decoded_format': (meta.get('oformat') or '').strip(),
            'file': '' if renderer_active else song.get('file', ''),
            'cover_url': self.absolute_cover(meta.get('cover_url') or ''),
            # Raw extras: useful in templates, deliberately not exposed as
            # entities since they are absent often enough to blink.
            # Blanked with the rest while a renderer plays: MPD is stopped then,
            # and its currentsong still describes the track from before. A
            # renderer that reports a genre fills it, and no renderer reports a
            # release date at all.
            'genre': (meta.get('genre') or '').strip() if renderer_active
                     else song.get('genre', ''),
            'date': '' if renderer_active else song.get('date', ''),
            'is_radio': False if renderer_active else is_radio,
            'duration': float(meta.get('duration') or 0) if renderer_active
                        else float(status.get('duration') or 0),
            'renderer_active': renderer_active,
        }

        # Nothing in here moves on its own any more, so the payload goes out when
        # it actually changes and not otherwise. `elapsed` and `bitrate` ticked
        # every cycle, and carrying them meant republishing every second.
        sig = json.dumps(player, sort_keys=True)
        if sig != self.player_sig:
            self.player_sig = sig
            self.publish('player', json.dumps(player, sort_keys=True), force=True)

    def absolute_cover(self, url):
        """Spotify and Qobuz give absolute URLs; AirPlay gives a path under
        moOde's web root. Anything not already absolute gets the base prefixed."""
        url = url.strip()
        if not url or url.startswith(('http://', 'https://')):
            return url
        return '%s/%s' % (self.web_base, url.lstrip('/'))

    # Home Assistant discovery

    def device_block(self):
        return {
            'identifiers': [self.cfg['instance']],
            'name': self.cfg['friendly_name'],
            'manufacturer': 'moOde audio',
            'model': 'moOde audio player',
            'sw_version': self.release or 'unknown',
        }

    def publish_discovery(self):
        inst = self.cfg['instance']
        dev = self.device_block()
        avail = self.topic('availability')
        player = self.topic('player')

        def announce(platform, object_id, config, extra_availability=None):
            config.update({
                'device': dev,
                'unique_id': '%s_%s' % (inst, object_id),
                'object_id': '%s_%s' % (inst, object_id),
            })
            if extra_availability:
                # availability_mode 'all': available only while every listed
                # topic says so, so this adds to the bridge's own liveness
                # rather than replacing it.
                config['availability'] = [{'topic': avail},
                                          {'topic': extra_availability}]
                config['availability_mode'] = 'all'
            else:
                config['availability_topic'] = avail
            self.client.publish(
                '%s/%s/%s/%s/config' % (self.cfg['discovery_prefix'], platform,
                                        inst, object_id),
                json.dumps(config), retain=True)

        # The amp trigger: on as soon as anything plays, off only after the
        # debounce. Let HA hold the long delay with `for:`.
        announce('binary_sensor', 'audio', {
            'name': 'Audio active',
            'state_topic': self.topic('audio'),
            'device_class': 'running',
            'icon': 'mdi:speaker',
        })
        announce('binary_sensor', 'renderer', {
            'name': 'Renderer',
            'state_topic': player,
            'value_template': "{{ 'ON' if value_json.renderer_active else 'OFF' }}",
            'icon': 'mdi:cast-audio',
        })
        announce('binary_sensor', 'display_power', {
            'name': 'Display',
            'state_topic': self.topic('display/power'),
            'icon': 'mdi:monitor',
        })
        announce('sensor', 'display_app', {
            'name': 'Display app',
            'state_topic': self.topic('display/app'),
            'icon': 'mdi:monitor-dashboard',
        })
        announce('sensor', 'state', {
            'name': 'State',
            'state_topic': player,
            'value_template': '{{ value_json.state }}',
            'icon': 'mdi:play-circle',
        })
        for platform, object_id in RETIRED_ENTITIES:
            self.client.publish(
                '%s/%s/%s/%s/config' % (self.cfg['discovery_prefix'], platform,
                                        inst, object_id), '', retain=True)

        for field, label, icon in (('title', 'Title', 'mdi:music-note'),
                                   ('artist', 'Artist', 'mdi:account-music'),
                                   ('album', 'Album', 'mdi:album'),
                                   ('source', 'Source', 'mdi:import'),
                                   ('station', 'Station', 'mdi:radio')):
            announce('sensor', field, {
                'name': label,
                'state_topic': player,
                'value_template': '{{ value_json.%s }}' % field,
                'icon': icon,
            })
        # Declared but off by default: nothing consumes the artwork yet, and an
        # entity the user can switch on in one click beats one that has to be
        # added later. HA fetches the URL itself, so the box must be reachable
        # from it - see resolve_web_base for the AirPlay case.
        announce('image', 'cover', {
            'name': 'Cover',
            'url_topic': player,
            'url_template': '{{ value_json.cover_url }}',
            'enabled_by_default': False,
        })
        announce('number', 'volume', {
            'name': 'Volume',
            'state_topic': player,
            'value_template': '{{ value_json.volume }}',
            'command_topic': self.topic('cmd/volume'),
            'min': 0, 'max': 100, 'step': 1,
            'icon': 'mdi:volume-high',
        }, extra_availability=self.topic('volume/available'))
        announce('switch', 'mute', {
            'name': 'Mute',
            'state_topic': player,
            'value_template': "{{ 'on' if value_json.mute else 'off' }}",
            'state_on': 'on', 'state_off': 'off',
            'command_topic': self.topic('cmd/mute'),
            'payload_on': 'on', 'payload_off': 'off',
            'icon': 'mdi:volume-off',
            # Its own gate, not the volume's: AirPlay has a level to move and no
            # mute to read.
        }, extra_availability=self.topic('mute/available'))
        for cmd, label, icon in (('toggle', 'Play/Pause', 'mdi:play-pause'),
                                 ('play', 'Play', 'mdi:play'),
                                 ('pause', 'Pause', 'mdi:pause'),
                                 ('stop', 'Stop', 'mdi:stop'),
                                 ('next', 'Next', 'mdi:skip-next'),
                                 ('previous', 'Previous', 'mdi:skip-previous')):
            announce('button', cmd, {
                'name': label,
                'command_topic': self.topic('cmd/transport'),
                'payload_press': cmd,
                'icon': icon,
            }, extra_availability=self.topic('controls/available'))

    # Threads

    def idle_loop(self):
        """Block on MPD idle and wake the publisher: no polling latency."""
        cli = musicpd.MPDClient()
        while running:
            try:
                self.mpd_connect(cli)
                self.wake.set()
                while running:
                    changed = cli.idle('player', 'mixer', 'options', 'playlist')
                    if 'mixer' in changed or 'player' in changed:
                        self.refresh_volume()
                    self.wake.set()
            except Exception as err:
                log('MPD idle lost (%s), retrying' % err)
                time.sleep(5)

    def watch_loop(self):
        """Read get_volume again when moOde's database (volume, renderer flags) or
        the renderer state file changes. A stat costs next to nothing."""
        def mtime(path):
            try:
                return os.stat(path).st_mtime_ns
            except OSError:
                return None
        last = None
        while running:
            now = (mtime(SQLDB), mtime(RENDVOL_FILE))
            if last is not None and now != last:
                # The watcher writes only on change: nothing to settle
                self.refresh_volume(0 if now[1] != last[1] else VOLUME_SETTLE)
            last = now
            time.sleep(WATCH_INTERVAL)

    def run(self):
        self.client.connect_async(self.cfg['host'], self.cfg['port'], keepalive=60)
        self.client.loop_start()

        threading.Thread(target=self.idle_loop, daemon=True).start()
        threading.Thread(target=self.watch_loop, daemon=True).start()

        while running:
            timeout = self.cfg['poll_interval']
            if self.vol_due is not None:
                timeout = max(0.0, min(timeout, self.vol_due - time.monotonic()))
            self.wake.wait(timeout=timeout)
            self.wake.clear()
            try:
                self.collect_and_publish()
            except Exception as err:
                log('publish cycle failed: %s' % err)

        # Acknowledged, then a clean disconnect: the broker discards the will
        # instead of publishing a second offline
        info = self.client.publish(self.topic('availability'), 'offline', retain=True, qos=1)
        if info.rc == mqtt.MQTT_ERR_SUCCESS:
            info.wait_for_publish(5)
        self.client.disconnect()
        self.client.loop_stop()


def remove_published(cfg):
    """--remove: clear every retained topic this player published, so Home
    Assistant drops the device. Run by the worker with the service already
    stopped: a running bridge republishes its discovery on every connect.

    Nothing is listed by hand: whatever the broker holds under this instance's
    topics and discovery configs is cleared, retired entities included.
    """
    filters = ('%s/%s/#' % (cfg['prefix'], cfg['instance']),
               '%s/+/%s/+/config' % (cfg['discovery_prefix'], cfg['instance']))
    found = set()
    answer = {}
    connected = threading.Event()

    def on_connect(client, userdata, flags, rc, properties=None):
        failure = getattr(rc, 'is_failure', None)
        answer['failed'] = failure if failure is not None else rc != 0
        answer['rc'] = rc
        if not answer['failed']:
            for topic in filters:
                client.subscribe(topic)
        connected.set()

    def on_message(client, userdata, msg):
        # Not only retained ones: a message published while we are subscribed
        # arrives without the retain flag, and it means something is still
        # publishing here. Empty payloads - our own clearing - are skipped.
        if msg.payload:
            found.add(msg.topic)

    client = make_client(cfg, cfg['client_id'] + '-remove')
    client.on_connect = on_connect
    client.on_message = on_message

    try:
        client.connect(cfg['host'], cfg['port'], 30)
    except OSError as err:
        log('remove: cannot reach %s:%s (%s), nothing cleared' % (cfg['host'], cfg['port'], err))
        return 1
    client.loop_start()
    if not connected.wait(10) or answer.get('failed'):
        log('remove: broker refused the connection (%s), nothing cleared' % answer.get('rc'))
        client.loop_stop()
        return 1

    def clear():
        # Retained messages are delivered right after the subscription
        time.sleep(3)
        topics = sorted(found)
        # QoS 1: the broker acknowledges each clear once processed; at QoS 0,
        # closing right after the last one can lose it
        unacked = []
        for topic in topics:
            info = client.publish(topic, '', retain=True, qos=1)
            info.wait_for_publish(5)
            if not info.is_published():
                unacked.append(topic)
        if unacked:
            log('remove: not acknowledged by the broker: %s' % ', '.join(unacked))
        return topics

    cleared = clear()
    log('remove: cleared %d retained topics for instance %s' % (len(cleared), cfg['instance']))

    # Subscribing again makes the broker replay what it still retains:
    # anything back is cleared too, and named in the log
    found.clear()
    for topic in filters:
        client.unsubscribe(topic)
        client.subscribe(topic)
    left = clear()
    if left:
        log('remove: %d found again and cleared: %s' % (len(left), ', '.join(left)))
    client.disconnect()
    client.loop_stop()
    return 0


def main():
    def stop(signum, frame):
        global running
        running = False

    signal.signal(signal.SIGTERM, stop)
    signal.signal(signal.SIGINT, stop)

    cfg = load_config()
    if '--remove' in sys.argv[1:]:
        sys.exit(remove_published(cfg))
    log('mqtt-bridge starting: instance=%s broker=%s:%s' %
        (cfg['instance'], cfg['host'], cfg['port']))
    Bridge(cfg).run()


if __name__ == '__main__':
    main()
