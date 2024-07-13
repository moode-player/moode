#!/usr/bin/env python3
#
# SPDX-License-Identifier: GPL-3.0-or-later
# Copyright 2023 @n00b001 https://github.com/n00b001/plexamp_homeassistant
# Copyright 2024 The moOde audio player project / Tim Curtis
#
# 2024 Tim Curtis
# Modifications to support integration into moOde
#
import xml.etree.ElementTree as ET
import sys
import subprocess
import sys
import requests

if len(sys.argv) == 1:
    print(f"Host argument is missing: {sys.argv[0]} <host>")
    sys.exit(0)

host = sys.argv[1]
wait = "0"
includeMetadata = "0"
commandID = "1"
url = f"http://{host}:32500/player/timeline/poll?wait={wait}&includeMetadata={includeMetadata}&commandID={commandID}"

def get_playback_state():
    try:
        resp = requests.get(url)
    except:
        print(f"Could not connect to Plexamp on host {host}")
        sys.exit(0)

    if resp.ok:
        content = resp.content
        root = ET.fromstring(content)

        for type_tag in root.findall('Timeline'):
            item_type = type_tag.get('itemType')
            if item_type == "music":
                state = type_tag.get('state')
                if state == "playing":
                    return "1"
        return "0"
    else:
        #raise Exception(f"API call was not OK: {resp.status_code}")
        print(f"requests.get to Plexamp on host {host} failed, status: {resp.status_code}")
        sys.exit(0)

def main():
    state = get_playback_state()
    print(state)

if __name__ == "__main__":
    main()
