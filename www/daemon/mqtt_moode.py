#
# SPDX-License-Identifier: GPL-3.0-or-later
# Copyright 2014 The moOde audio player project / Tim Curtis
# Copyright 2026 @Gjuju
#
# Reading moOde, and commanding it.
#
# Everything here answers "what does moOde say" or "tell moOde to". The bridge
# duplicates a little of moOde's own logic to read its state without forking PHP
# once a second; each spot carries a pointer to its original in moOde's source.
#
# The database is opened read-only. Writing cfg_system behind moOde's back
# desyncs its PHP session cache and the WebUI then looks stuck.

import json
import os
import sqlite3
import subprocess
import urllib.parse
import urllib.request


def log(msg):
    print(msg, flush=True)


SQLDB = '/var/local/www/db/moode-sqlite3.db'


MOODE_API = 'http://localhost/command/index.php'


# Reading moOde's state


def db_read(params):
    """Read cfg_system rows. Read-only: never write the DB behind moOde's back."""
    try:
        conn = sqlite3.connect('file:%s?mode=ro' % SQLDB, uri=True, timeout=2)
        placeholders = ','.join('?' * len(params))
        rows = conn.execute(
            'SELECT param, value FROM cfg_system WHERE param IN (%s)' % placeholders,
            params).fetchall()
        conn.close()
        return dict(rows)
    except sqlite3.Error as err:
        log('db_read: %s' % err)
        return {}


# How moOde decides something is a radio stream (inc/mpd.php): an http file with
# no duration. The WebUI then shows "Radio station" as the artist and uses that
# label to pick stop-over-pause, but the label only exists in moOde's own PHP
# rendering - MPD itself reports whatever tags the stream carries, often none.
# So test the cause, not the label.
def is_radio_stream(song, status):
    return song.get('file', '').startswith('http') and not status.get('duration')


# Text fields are never published empty. An ICY radio stream carries only
# StreamTitle - MPD reports no artist and no album at all for one - and a local
# file can simply be untagged (measured: a library where two albums carry a
# genre and the third does not). An empty string is a valid HA state, distinct
# from unknown, so it makes automations fire on the blanks between tracks.

def display_source(cfg_rows, is_radio):
    """What is actually feeding the output, using moOde's own labels
    (playerlib.js). This is derived from system state, not guessed from tags.

    A renderer wins over MPD: while AirPlay or Spotify plays, MPD is stopped and
    its currentsong still describes the track before that.
    """
    for flag, label in RENDERER_LABELS:
        if cfg_rows.get(flag) == '1':
            if flag == 'inpactive':
                name = cfg_rows.get('audioin', '').strip()
                return ('%s Input' % name) if name else 'Input'
            return label
    if cfg_rows.get('rxactive') == '1':
        return 'Multiroom Receiver'
    return 'Radio' if is_radio else 'Library'


def volume_scope(cfg_rows):
    """What moOde's volume knob actually attenuates.

    'hardware' - the card's ALSA mixer, downstream of everything, so it applies
                 to renderers too.
    'mpd'      - `mpc volume`, MPD only. A renderer playing is then untouched by
                 the knob, and by our volume commands.
    'none'     - Fixed 0dB: vol.sh exits without changing anything.

    moOde denies renderers the hardware mixer and makes them attenuate in
    software (inc/renderer.php), so with a hardware mixer there are two stages:
    the renderer's own level, then this one. The knob only describes this one.
    """
    mixer = cfg_rows.get('mpdmixer', '')
    if mixer == 'none':
        return 'none'
    return 'hardware' if mixer == 'hardware' else 'mpd'


# cfg_system flags moOde sets while a non-MPD source is playing (common.php,
# chkRendererActive()), each with the label its own WebUI shows (playerlib.js).
RENDERER_LABELS = (
    ('inpactive', 'Input'),
    ('btactive', 'Bluetooth'),
    ('aplactive', 'AirPlay'),
    ('spotactive', 'Spotify'),
    ('qbzactive', 'Qobuz'),
    ('slactive', 'Squeezelite'),
    ('paactive', 'Plexamp'),
    ('rbactive', 'RoonBridge'),
)


RENDERER_FLAGS = tuple(flag for flag, _ in RENDERER_LABELS)


# Each renderer's metadata cache, and the divisor its "duration" needs. They do
# not agree on the unit: AirPlay and Spotify report milliseconds, Qobuz seconds.
# moOde carries the same split in playerlib.js (timeDivisor).
RENDERER_META_FILES = {
    'aplactive': ('/var/local/www/aplmeta.json', 1000),
    'spotactive': ('/var/local/www/spotmeta.json', 1000),
    'qbzactive': ('/var/local/www/qbzmeta.json', 1),
}


def read_renderer_meta(flag):
    """moOde caches each renderer's metadata as JSON with plain keys, written by
    the renderer itself: /var/local/www/{apl,spot,qbz}meta.json. Empty file means
    the renderer is not reporting anything."""
    path, divisor = RENDERER_META_FILES.get(flag, (None, 1))
    if not path:
        return {}
    try:
        with open(path) as fh:
            text = fh.read().strip()
        meta = json.loads(text) if text else {}
    except (OSError, ValueError):
        return {}
    if meta.get('duration'):
        try:
            meta['duration'] = float(meta['duration']) / divisor
        except (TypeError, ValueError):
            meta['duration'] = 0
    return meta


def output_is_open(cfg_rows):
    """Is the output substream open - by anyone: MPD, AirPlay, Qobuz, Bluetooth.

    The one signal that does not depend on who is playing, which is why "audio
    active" and a renderer's play state both come from here rather than from MPD.
    Mirrors moodeutl --hwparams: in multiroom transmitter mode the real output is
    the ALSA Loopback.
    """
    if cfg_rows.get('multiroom_tx') == 'On':
        try:
            with open('/proc/asound/Loopback/pcm0p/info') as fh:
                card = next(line.split(': ')[1].strip()
                            for line in fh if line.startswith('card'))
        except (OSError, StopIteration):
            return False
    else:
        card = cfg_rows.get('cardnum', '0')

    try:
        with open('/proc/asound/card%s/pcm0p/sub0/hw_params' % card) as fh:
            text = fh.read().strip()
    except OSError:
        return False
    # moOde's own parser treats both of these as "nothing playing" (inc/alsa.php,
    # getAlsaHwParams).
    return bool(text) and text not in ('closed', 'no setup')


# Reading the screen state costs a fork, which makes it by far the most
# expensive thing in a cycle - so it runs on a timer, and only where moOde
# configured a display at all.
# Measured on an x86 box with a touch panel: 26 ms of CPU per call through sudo,
# against 3.7 ms for everything else the cycle does put together. The sensor it
# feeds is informational, and deliberately not the amp signal: that is `audio`,
# which keeps the poll interval. moOde's own worker asks the same question every
# 3 s, so there is no point being quicker than the thing being watched.
DISPLAY_RECHECK = 10.0


def display_power():
    """on / standby / unknown.

    Two unrelated mechanisms can blank the screen: worker.php's scn_blank (only
    while Peppy is displayed) and the plain X DPMS timeout armed in .xinitrc
    (input inactivity, no relation to audio). This reports the result of either,
    which is why it must not be used to decide whether the amp should be on.
    """
    # No sudo: moOde runs Xorg as root with no auth file, so www-data reaches it
    # directly. Verified on every box here that has an X server, a stock Pi
    # included. It is not a style preference - the same call through sudo costs
    # 51 ms of CPU on a Pi 3 against 6.3 plain, and on a box with no X at all it
    # is 68 ms to fail rather than 7.5.
    env = dict(os.environ, DISPLAY=':0')
    try:
        out = subprocess.run(['xset', 'q'], env=env, timeout=5,
                             capture_output=True, text=True).stdout
    except (subprocess.SubprocessError, OSError):
        return 'unknown'

    for line in out.splitlines():
        if 'Monitor is ' in line:
            # "Monitor is On" / "in Standby" / "in Suspend" / "Off" - the state
            # is not always one word, so keep everything after the marker. moOde
            # takes the third field here, which yields "in" for a standby.
            state = line.split('Monitor is ', 1)[1].strip()
            return 'on' if state == 'On' else 'standby'
    return 'unknown'


def moode_release():
    try:
        return subprocess.run(['moodeutl', '--mooderel'], timeout=10,
                              capture_output=True, text=True).stdout.strip()
    except (subprocess.SubprocessError, OSError):
        return 'unknown'


# Running commands


def moode_api(cmd):
    """Send a command through moOde's own REST API (www/command/index.php).

    Not straight to MPD or to vol.sh: that endpoint carries moOde's internal
    mechanisms, and bypassing it drops them silently. `set_volume` propagates the
    change to multiroom receivers and refuses while a renderer is active;
    `toggle_play_pause` knows moOde's radio rule; anything else is relayed to
    MPD. It is a REST API by design - its own source notes it handles "CLI based
    REST commands sent for example by curl".

    Commands are rare, so an HTTP call costs nothing. The 1 Hz state read stays
    direct, where a PHP fork per second would not be free.
    """
    url = '%s?cmd=%s' % (MOODE_API, urllib.parse.quote(cmd))
    try:
        with urllib.request.urlopen(url, timeout=10) as resp:
            body = resp.read().decode('utf-8', 'replace').strip()
    except Exception as err:
        log('moOde API %r failed: %s' % (cmd, err))
        return None

    try:
        data = json.loads(body) if body else {}
    except ValueError:
        return body
    # moOde answers a refusal rather than an error status
    if isinstance(data, dict) and data.get('alert'):
        log('moOde refused %r: %s' % (cmd, data['alert']))
    return data
