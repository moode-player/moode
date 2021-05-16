#!/bin/bash
################################################################
#
# camillagui deb after remove script
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

sudo rm -rf /opt/camillagui/backend/__pycache__
sudo rm -f /opt/camillagui/config/gui-config.yml.disabled