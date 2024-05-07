#!/usr/bin/python3
#
# SPDX-License-Identifier: GPL-2.0-only
# Copyright 2019 @kaymes
#

#
# Bluetooth connection agent - @kaymes
# DEPRECATED: Replaced in moode 9 by bt-agent from the bluez-tools package.
#

from __future__ import absolute_import, print_function, unicode_literals

from optparse import OptionParser
import sys
import time
import dbus
import dbus.service
import dbus.mainloop.glib
try:
  from gi.repository import GObject
except ImportError:
  import gobject as GObject

BUS_NAME = 'org.bluez'
AGENT_INTERFACE = 'org.bluez.Agent1'
AGENT_PATH = "/moode/agent"
WELL_KNOWN_NAME = 'org.moodeaudio.bluez.agent'
PAIR_MODE_INTERFACE = 'org.moodeaudio.bluez.agent.PairMode'

mainloop = None

class Rejected(dbus.DBusException):
    _dbus_error_name = "org.bluez.Error.Rejected"

class Cancelled(dbus.DBusException):
    _dbus_error_name = "org.bluez.Error.Canceled"

class Agent(dbus.service.Object):

    def __init__(self, conn, object_path, bus_name):
        super(Agent, self).__init__(conn, object_path, bus_name)
        self.pair_mode_active_until = -float('inf')

    @property
    def pair_mode_active(self):
        return time.time() <= self.pair_mode_active_until

    @dbus.service.method(PAIR_MODE_INTERFACE, in_signature="d", out_signature="")
    def ActivatePairMode(self, timeout):
        print("ActivatePairMode called with %s" % (timeout,))
        if timeout > 0:
            self.pair_mode_active_until = time.time() + timeout
        else:
            self.pair_mode_active_until = -float('inf')

    @dbus.service.method(AGENT_INTERFACE, in_signature="", out_signature="")
    def Release(self):
        print("Release")
        if mainloop is not None:
            mainloop.quit()

    @dbus.service.method(AGENT_INTERFACE, in_signature="os", out_signature="")
    def AuthorizeService(self, device, uuid):
        if self.pair_mode_active:
            print("Authorizing service (%s, %s)" % (device, uuid))
            return
        else:
            raise Rejected("Pair mode not activated.")

    @dbus.service.method(AGENT_INTERFACE, in_signature="o", out_signature="s")
    def RequestPinCode(self, device):
        raise Cancelled("Pin code not supported")

    @dbus.service.method(AGENT_INTERFACE, in_signature="o", out_signature="u")
    def RequestPasskey(self, device):
        raise Cancelled("Passkey code not supported")

    @dbus.service.method(AGENT_INTERFACE, in_signature="ouq", out_signature="")
    def DisplayPasskey(self, device, passkey, entered):
        raise Cancelled("Passkey code not supported")

    @dbus.service.method(AGENT_INTERFACE, in_signature="os", out_signature="")
    def DisplayPinCode(self, device, pincode):
        raise Cancelled("Pin code not supported")

    @dbus.service.method(AGENT_INTERFACE, in_signature="ou", out_signature="")
    def RequestConfirmation(self, device, passkey):
        raise Cancelled("Confirmation not supported")

    @dbus.service.method(AGENT_INTERFACE, in_signature="o", out_signature="")
    def RequestAuthorization(self, device):
        if self.pair_mode_active:
            print("Authorizing device %s" % (device))
            return
        else:
            raise Rejected("Pair mode not activated.")

    @dbus.service.method(AGENT_INTERFACE, in_signature="", out_signature="")
    def Cancel(self):
        print("Cancel")

if __name__ == '__main__':
    parser = OptionParser()
    parser.add_option("-a", "--agent", action="store_true", dest="agent", help="Run as Bluetooth agent. If not given, a running agent is contacted to change the pairing mode.")
    parser.add_option("-w", "--wait_for_bluez", action="store_true", dest="wait_for_bluez", help="During system startup, bluez might not yet be available. this option causes the agent to re-try repeatedly until bluez has started.")
    parser.add_option("-p", "--pair_mode", action="store_true", dest="pair_mode", help="Activate pairing mode.")
    parser.add_option("-t", "--timeout", action="store", type="int", dest="timeout", help="Timeout in seconds for pairing mode. If not given, pairing mode will be active indefinitely.")
    parser.add_option("-d", "--disable_pair_mode", action="store_true", dest="disable_pair_mode", help="Disable pairing mode.")
    parser.add_option("-s", "--disable_pair_mode_switch", action="store_true", dest="disable_pair_mode_switch", help="Don't register a well known name with the dbus. This disables switching pairing mode from another process. Use it if the necessary dbus permissions aren't set up.")

    (options, args) = parser.parse_args()

    if options.pair_mode and options.disable_pair_mode:
        print("The options pair_mode and disable_pair_mode are mutally exclusive.")
        sys.exit()

    if options.agent:
        dbus.mainloop.glib.DBusGMainLoop(set_as_default=True)

        bus = dbus.SystemBus()

        if options.disable_pair_mode_switch:
            bus_name = None
        else:
            bus_name = dbus.service.BusName(WELL_KNOWN_NAME, bus)

        agent = Agent(bus, AGENT_PATH, bus_name)

        if options.pair_mode:
            if options.timeout is not None:
                timeout = options.timeout
            else:
                timeout = float('inf')
            agent.ActivatePairMode(timeout)

        mainloop = GObject.MainLoop()

        while True:
            try:
                obj = bus.get_object(BUS_NAME, "/org/bluez");
            except dbus.exceptions.DBusException:
                if options.wait_for_bluez:
                    time.sleep(1)
                else:
                    raise
            else:
                break

        manager = dbus.Interface(obj, "org.bluez.AgentManager1")

        manager.RegisterAgent(AGENT_PATH, "NoInputNoOutput")
        print("Agent registered")

        manager.RequestDefaultAgent(AGENT_PATH)
        print("Agent registered as default agent.")

        mainloop.run()
    else:
        bus = dbus.SystemBus()
        obj = bus.get_object(WELL_KNOWN_NAME, AGENT_PATH)
        other_agent = dbus.Interface(obj, PAIR_MODE_INTERFACE)

        if options.pair_mode:
            if options.timeout is not None:
                timeout = options.timeout
            else:
                timeout = float('inf')

            other_agent.ActivatePairMode(float(timeout))

        if options.disable_pair_mode:
            other_agent.ActivatePairMode(float('-inf'))
