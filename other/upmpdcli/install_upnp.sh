#!/bin/bash
#=========================================================================
# Purpose script: Install the packages required for the moode upnp subsystem.
#
# (C) 2020 @bitlab (@bitkeeper Git)
#
#
# This Program is free software; you can redistribute it and/or modify
# it under the terms of the GNU General Public License as published by
# the Free Software Foundation; either version 3, or (at your option)
# any later version.
#
# This Program is distributed in the hope that it will be useful,
# but WITHOUT ANY WARRANTY; without even the implied warranty of
# MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
# GNU General Public License for more details.
#
# You should have received a copy of the GNU General Public License
# along with this program.  If not, see <http://www.gnu.org/licenses/>.
#
#==========================================================================

VER_LIBNPUPNP=4.1.1
VER_LIBUPNPP=0.21.0
VER_LIBUPNPP_BIND=0.20.1
VER_UPMPDCLI=1.5.11

FILE=./install_upnp.sh
if [[ ! -f "$FILE" ]]; then
  echo "Script is stated from the wrong directory (cd first to the location of this script)!"
  exit 1
fi

sudo systemctl status upmpdcli.service |grep "Active: active (running)" > /dev/null 2>&1
STAT_UPMPDCLI=$?
if [[ $STAT_UPMPDCLI -eq 0 ]]
then
  echo "stop upmpdcli"
  sudo systemctl stop upmpdcli.service
fi

# clean non packages versions of the upnp subsystem, if needed
./clean_upnp_subsystem.sh

sudo apt-get install -y -o Dpkg::Options::="--force-confdef" -o Dpkg::Options::="--force-confold" \
./libnpupnp1_$VER_LIBNPUPNP-1~ppaPPAVERS~SERIES1_armhf.deb \
./libupnpp6_$VER_LIBUPNPP-1~ppaPPAVERS~SERIES1_armhf.deb \
./python3-libupnpp_$VER_LIBUPNPP_BIND-1~ppaPPAVERS~SERIES1_armhf.deb \
./upmpdcli_$VER_UPMPDCLI-1~ppaPPAVERS~SERIES1_armhf.deb

# if service was running restart
if [[ $STAT_UPMPDCLI -eq 0 ]]
then
  echo "start upmpdcli"
  sudo systemctl start upmpdcli.service
fi
