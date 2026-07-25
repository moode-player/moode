#!/usr/bin/env python3
# SPDX-License-Identifier: GPL-3.0-or-later
# Copyright 2014 The moOde audio player project / Tim Curtis
#
# Bluetooth pairing agent.
#
# Replaces `bt-agent` (bluez-tools). Registered with the DisplayYesNo capability
# it drives Secure Simple Pairing "Numeric Comparison": on a pairing request bluez
# hands us the 6-digit code, we show it in the moOde UI and wait for the user to
# confirm it matches the code on their device. Confirming yields an authenticated
# (MITM-protected) link key - the legacy PIN it replaces could not.
#
# The agent is deliberately unaware of the UI: it pushes a small message to the
# front-end (via send-fecmd.php) and waits for a yes/no on a local socket. Any
# other front-end speaking that message contract would work unchanged.
#
# Front-end contract (see command/renderer.php + playerlib.js):
#   push  -> pairreq,<id>,<method>,<code>,<name_b64>,<icon>   (method: confirm|display|input|authorize)
#   reply <- pairresp,<id>,<1|0>[,<code>]                      (on RESPONSE_SOCK)
#   push  -> paircancel,<id>                                   (timed out or device gave up)

import base64
import os
import pwd
import socket
import subprocess
import sys
import uuid

import dbus
import dbus.mainloop.glib
import dbus.service
from gi.repository import GLib

AGENT_PATH = '/org/bluez/moode_agent'
# DisplayYesNo drives Numeric Comparison (authenticated). NoInputNoOutput falls
# back to Just Works, i.e. today's behaviour with no modal. argv wins for testing,
# then the unit's environment, then the default.
CAPABILITY = (len(sys.argv) > 1 and sys.argv[1]) \
    or os.environ.get('BT_AGENT_CAPABILITY') or 'DisplayYesNo'
SEND_FECMD = '/var/www/util/send-fecmd.php'
RESPONSE_SOCK = '/tmp/moode-btagent.sock'
RESPONSE_USER = 'www-data'          # front-end (php-fpm) writes the reply here
# Safety net only. Normal closure is driven by bluez calling Cancel() when the device
# gives up, which keeps the modal in sync with what the phone shows. This long timeout
# just prevents a stuck modal if Cancel() never arrives.
CONFIRM_TIMEOUT = 60


def log(msg):
    print(msg, flush=True)


def push_fe(cmd):
    # Fire-and-forget notify to every connected UI; never let it block the agent.
    try:
        subprocess.Popen(['php', SEND_FECMD, cmd],
                         stdout=subprocess.DEVNULL, stderr=subprocess.DEVNULL)
    except Exception as e:
        log('push_fe failed: %s' % e)


def device_props(path):
    bus = dbus.SystemBus()
    props = dbus.Interface(bus.get_object('org.bluez', path),
                          'org.freedesktop.DBus.Properties')
    def get(name, default=''):
        try:
            return str(props.Get('org.bluez.Device1', name))
        except dbus.DBusException:
            return default
    return get('Name', 'Bluetooth device'), get('Icon', 'bluetooth')


class Rejected(dbus.DBusException):
    _dbus_error_name = 'org.bluez.Error.Rejected'


class PairingAgent(dbus.service.Object):
    def __init__(self, bus, path):
        super().__init__(bus, path)
        self.pending = {}   # id -> {'reply', 'error', 'timeout', 'code'}

    # --- request lifecycle ------------------------------------------------
    def _open(self, method, device, code, reply, error):
        req_id = uuid.uuid4().hex[:8]
        name, icon = device_props(device)
        name_b64 = base64.b64encode(name.encode()).decode()
        timeout = GLib.timeout_add_seconds(CONFIRM_TIMEOUT, self._expire, req_id)
        self.pending[req_id] = {'reply': reply, 'error': error,
                                'timeout': timeout, 'code': code}
        push_fe('pairreq,%s,%s,%s,%s,%s' % (req_id, method, code, name_b64, icon))
        log('%s(%s) code=%s -> req %s' % (method, device, code, req_id))
        return req_id

    def _resolve(self, req_id, accepted, code=None):
        req = self.pending.pop(req_id, None)
        if req is None:
            return  # already resolved (duplicate/late reply): ignore silently
        log('resolve req %s accepted=%s' % (req_id, accepted))
        GLib.source_remove(req['timeout'])
        if accepted:
            if req['code'] == '__input__':
                req['reply'](dbus.UInt32(code))
            else:
                req['reply']()
        else:
            req['error'](Rejected('Rejected by user'))
        # Close the dialog on any other UI that also popped it (browser + local
        # display): the client that answered has already closed its own.
        push_fe('paircancel,%s' % req_id)

    def _expire(self, req_id):
        req = self.pending.pop(req_id, None)
        if req is not None:
            req['error'](Rejected('Timed out'))
            push_fe('paircancel,%s' % req_id)
            log('req %s timed out' % req_id)
        return False

    def _cancel_all(self):
        for req_id in list(self.pending):
            req = self.pending.pop(req_id)
            GLib.source_remove(req['timeout'])
            req['error'](Rejected('Cancelled'))
            push_fe('paircancel,%s' % req_id)

    # --- org.bluez.Agent1 -------------------------------------------------
    @dbus.service.method('org.bluez.Agent1', in_signature='', out_signature='')
    def Release(self):
        log('Release')

    @dbus.service.method('org.bluez.Agent1', in_signature='os', out_signature='',
                        async_callbacks=('reply', 'error'))
    def AuthorizeService(self, device, uuid_, reply, error):
        # A2DP/AVRCP on an already-paired device: accept silently, like today.
        log('AuthorizeService(%s, %s) -> accept' % (device, uuid_))
        reply()

    @dbus.service.method('org.bluez.Agent1', in_signature='ou', out_signature='',
                        async_callbacks=('reply', 'error'))
    def RequestConfirmation(self, device, passkey, reply, error):
        self._open('confirm', device, '%06u' % passkey, reply, error)

    @dbus.service.method('org.bluez.Agent1', in_signature='o', out_signature='',
                        async_callbacks=('reply', 'error'))
    def RequestAuthorization(self, device, reply, error):
        self._open('authorize', device, '', reply, error)

    @dbus.service.method('org.bluez.Agent1', in_signature='o', out_signature='u',
                        async_callbacks=('reply', 'error'))
    def RequestPasskey(self, device, reply, error):
        self._open('input', device, '__input__', reply, error)

    @dbus.service.method('org.bluez.Agent1', in_signature='ouq', out_signature='')
    def DisplayPasskey(self, device, passkey, entered):
        # Informational: show the code the user must type on their device.
        name, icon = device_props(device)
        name_b64 = base64.b64encode(name.encode()).decode()
        push_fe('pairreq,%s,display,%06u,%s,%s' % (uuid.uuid4().hex[:8], passkey, name_b64, icon))
        log('DisplayPasskey(%s, %06u)' % (device, passkey))

    @dbus.service.method('org.bluez.Agent1', in_signature='os', out_signature='')
    def DisplayPinCode(self, device, pincode):
        log('DisplayPinCode(%s, %s) -> ignored (legacy)' % (device, pincode))

    @dbus.service.method('org.bluez.Agent1', in_signature='o', out_signature='s')
    def RequestPinCode(self, device):
        # Legacy PIN pairing is not offered; reject so bluez does not fall back to it.
        log('RequestPinCode(%s) -> reject (legacy not supported)' % device)
        raise Rejected('Legacy PIN not supported')

    @dbus.service.method('org.bluez.Agent1', in_signature='', out_signature='')
    def Cancel(self):
        log('Cancel')
        self._cancel_all()


def on_response(sock, _cond, agent):
    try:
        data = sock.recv(256).decode().strip()
    except OSError:
        return True
    for line in data.splitlines():
        parts = line.split(',')
        if parts[0] == 'pairresp' and len(parts) >= 3:
            req_id, accepted = parts[1], parts[2] == '1'
            code = int(parts[3]) if accepted and len(parts) >= 4 and parts[3].isdigit() else None
            agent._resolve(req_id, accepted, code)
    return True


def make_response_socket():
    if os.path.exists(RESPONSE_SOCK):
        os.unlink(RESPONSE_SOCK)
    sock = socket.socket(socket.AF_UNIX, socket.SOCK_DGRAM)
    sock.bind(RESPONSE_SOCK)
    # php-fpm (www-data) must be able to write the user's answer here.
    ent = pwd.getpwnam(RESPONSE_USER)
    os.chown(RESPONSE_SOCK, ent.pw_uid, ent.pw_gid)
    os.chmod(RESPONSE_SOCK, 0o660)
    return sock


def main():
    dbus.mainloop.glib.DBusGMainLoop(set_as_default=True)
    bus = dbus.SystemBus()

    agent = PairingAgent(bus, AGENT_PATH)
    manager = dbus.Interface(bus.get_object('org.bluez', '/org/bluez'),
                            'org.bluez.AgentManager1')
    manager.RegisterAgent(AGENT_PATH, CAPABILITY)
    manager.RequestDefaultAgent(AGENT_PATH)
    log('registered as default agent, capability=%s' % CAPABILITY)

    sock = make_response_socket()
    GLib.io_add_watch(sock, GLib.IO_IN, lambda s, c: on_response(s, c, agent))

    loop = GLib.MainLoop()
    try:
        loop.run()
    except KeyboardInterrupt:
        pass
    finally:
        try:
            manager.UnregisterAgent(AGENT_PATH)
        except dbus.DBusException:
            pass
        if os.path.exists(RESPONSE_SOCK):
            os.unlink(RESPONSE_SOCK)


if __name__ == '__main__':
    main()
