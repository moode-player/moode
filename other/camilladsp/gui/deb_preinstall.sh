#!/bin/bash
################################################################
#
# camillagui deb pre install script
#
# This build recipe will generate python and deb packages output.
#
# (C) bitkeeper 2021 http://moodeaudio.org
# License: GPLv3
#
################################################################

# Stop a possible already old running unpacked camillagui
sudo systemctl status camillagui > /dev/null
if [[ $? -eq 0 ]]; then
    echo "Camillaguid running, stop it"
	sudo systemctl stop camillagui
fi

# Cleanup  a possible already old unpacked camillagui
FILE=/opt/camillagui/main.py
if [[ -f "$FILE" ]]; then
  echo "Remove old unpacked version of camillagui"
  sudo rm -rf /opt/camillagui
fi

# instead of agp-get use pip because of newer version
sudo pip3 list | grep aiohttp
if [[ $? -gt 0 ]]; then
  sudo pip3 install -U aiohttp>=3.7
fi
