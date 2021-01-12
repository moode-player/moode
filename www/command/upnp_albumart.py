#!/usr/bin/python3
# Copyright (C) 2021 J.F.Dockes. License: MIT

#
# Retrieve the album art URI from an openhome or UPnP/AV renderer.
#

import sys
import os
import upnpp

def usage():
    prog = os.path.basename(__file__)
    debug("Usage: %s devname" % prog)
    os._exit(1)


def artFromMeta(metadata):
    dirc = upnpp.UPnPDirContent()
    dirc.parse(metadata)
    if dirc.m_items.size():
        dirobj = dirc.m_items[0]
        if "upnp:albumArtURI" in dirobj.m_props:
            print("%s" % dirobj.m_props["upnp:albumArtURI"])
            os._exit(0)


def artFromOHInfo(service):
    # Prefer metatext as this will get the dynamic art if a radio is playing
    retdata = upnpp.runaction(service, "Metatext", [])
    if retdata and "Value" in retdata:
        artFromMeta(retdata["Value"])
    # Else try Track which will yield current playlist track or static radio data
    retdata = upnpp.runaction(service, "Track", [])
    if retdata and "Metadata" in retdata:
        artFromMeta(retdata["Metadata"])


def artFromAVTransport(service):
    retdata = upnpp.runaction(service, "GetPositionInfo", ["0"])
    if retdata and "TrackMetaData" in retdata:
        artFromMeta(retdata["TrackMetaData"])
    

if len(sys.argv) != 2:
    usage()
devname = sys.argv[1]

log = upnpp.Logger_getTheLog("stderr")
log.setLogLevel(2)

service = upnpp.findTypedService(devname, "Info", True)
if service:
    artFromOHInfo(service)
service = upnpp.findTypedService(devname, "AVTransport", True)
if service:
    artFromAVTransport(service)

os._exit(0)
    
