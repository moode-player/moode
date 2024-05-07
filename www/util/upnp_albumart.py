#!/usr/bin/python3
#
# SPDX-License-Identifier: MIT-Modern-Variant
# Copyright 2021 J.F.Dockes
# Copyright 2021 The moOde audio player project / Tim Curtis
# Copyright 2021 @bitlab (@bitkeeper Git)
#

#
# Retrieve the album art URI from an openhome or UPnP/AV renderer.
#
# 2021 Tim Curtis:
# Change 0S._exit() to sys.exit()
# 2021 @bitlab:
# Escape album art url path
# To speedup lookup provide type of system with mode(auto|upnpav|openhome) cmd arg
#

import sys
import os
import upnpp
import urllib.parse

def debug(x):
    print("%s" % x, file = sys.stderr)

def usage():
    prog = os.path.basename(__file__)
    debug("Usage: %s devname [mode]" % prog)
    sys.exit(1)


def artFromMeta(metadata):
    dirc = upnpp.UPnPDirContent()
    dirc.parse(metadata)
    if dirc.m_items.size():
        dirobj = dirc.m_items[0]
        if "upnp:albumArtURI" in dirobj.m_props:
            url_orig = dirobj.m_props["upnp:albumArtURI"]
            urls = url_orig.split(', http')
            for idx, url in enumerate(urls):
                if idx > 0:
                    urls[idx] = "http{}".format(url)
                o = urllib.parse.urlsplit(urls[idx])
                url_escaped = urllib.parse.ParseResult(o.scheme, o.netloc, urllib.parse.quote(o.path), "", "", "").geturl()
                print("%s" % url_escaped)
            sys.exit(0)


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


if len(sys.argv) != 2 and len(sys.argv) != 3:
    usage()
devname = sys.argv[1]
mode = 'auto'
if len(sys.argv) == 3:
    mode = sys.argv[2]

log = upnpp.Logger_getTheLog("stderr")
log.setLogLevel(0)

if mode == "auto" or mode == 'openhome':
    service = upnpp.findTypedService(devname, "Info", True)
    if service:
        artFromOHInfo(service)
if mode == "auto" or mode == 'upnpav':
    service = upnpp.findTypedService(devname, "AVTransport", True)
    if service:
        artFromAVTransport(service)

sys.exit(0)
