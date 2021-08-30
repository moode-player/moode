#!/bin/bash
#
# Required for cleaning of unpacked upnp subsystem
#
# (C) 2020 @bitlab (@bitkeeper Git)
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

dpkg -l libnpupnp1 > /dev/null 2>&1
if [[ $? -gt 0 ]]
then
    echo "Cleanup upnp subsytem required."
    sudo rm /lib/libnpupnp.*
    sudo rm /usr/lib/libupnpp.*
    sudo rm /usr/lib/pkgconfig/libupnpp.pc
    sudo rm -r /usr/include/libupnpp
    sudo rm /usr/bin/scctl
    sudo rm /usr/bin/upmpdcli
    sudo rm /usr/share/man/man1/upmpdcli.1
    sudo rm -r /usr/share/upmpdcli
    sudo rm -r /usr/lib/python3/dist-packages/upnpp
else
    echo "Already running on package based version."
fi
