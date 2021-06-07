#!/bin/bash
#
# Instaal Recipe for special version of libcurl3-gnutls to be side loaded with MPD
#
# The MPD curl plugin isn't stable with version 7.64.0-4+deb10u2.
# Upgrading the system to a new version breaks other tools.
# That is why it repacks libcurl3-gnutls under a different name and location.
# Also the MPD is configurated to set the LD_LIBRARY_PATH to prefer this version
#
# (C) bitkeeper 2021 http://moodeaudio.org
# License: GPLv3
#
################################################################

sudo apt-get install ./mpd-libcurl3-gnutls*.deb
