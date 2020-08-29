#!/bin/bash
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
# 2020-MM-DD TC moOde 7.0.0
#

# Store working dir
WD=pwd

# Unzip the package
unzip -q /var/local/www/station_import.zip -d /tmp
if [ $? -ne 0 ] ; then
    TIME_STAMP=$(date +'%Y%m%d %H%M%S')
    LOG_MSG=" import_stations: Unzip failed, import cancelled"
    echo $TIME_STAMP$LOG_MSG >> /var/log/moode.log
    exit 1
fi

# Basic file sanitizing
dos2unix -q /tmp/var/lib/mpd/music/RADIO/*.pls
if [ $? -ne 0 ] ; then
    TIME_STAMP=$(date +'%Y%m%d %H%M%S')
    LOG_MSG=" import_stations: Dos2unix failed on .pls files, import cancelled"
    echo $TIME_STAMP$LOG_MSG >> /var/log/moode.log
    exit 1
fi
dos2unix -q /tmp/var/local/www/db/cfg_radio.csv
if [ $? -ne 0 ] ; then
    TIME_STAMP=$(date +'%Y%m%d %H%M%S')
    LOG_MSG=" import_stations: Dos2unix failed on .csv file, import cancelled"
    echo $TIME_STAMP$LOG_MSG >> /var/log/moode.log
    exit 1
fi
cd /tmp/var
find . -name "._*" -exec rm -rf {} \; 2> /dev/null
find . -name ".Trashes" -exec rm -rf {} \; 2> /dev/null
find . -name "._.Trashes" -exec rm -rf {} \; 2> /dev/null
find . -name ".Spotlight*" -exec rm -rf {} \; 2> /dev/null
find . -name ".DS_Store" -exec rm -rf {} \; 2> /dev/null
find . -name "._.DS_Store" -exec rm -rf {} \; 2> /dev/null
find . -name ".fseventsd*" -exec rm -rf {} \; 2> /dev/null
find . -name "._.com.apple.timemachine.donotpresent" -exec rm -rf {} \; 2> /dev/null
find . -name ".com.apple.timemachine.donotpresent" -exec rm -rf {} \; 2> /dev/null
find . -name ".TemporaryItems" -exec rm -rf {} \; 2> /dev/null
find . -name "._.TemporaryItems" -exec rm -rf {} \; 2> /dev/null
find . -name "__MACOSX" -exec rm -rf {} \; 2> /dev/null
cd $WD

# Purge existing station data
rm /var/lib/mpd/music/RADIO/* 2> /dev/null
rm /var/local/www/imagesw/radio-logos/*.jpg 2> /dev/null
rm /var/local/www/imagesw/radio-logos/thumbs/*.jpg 2> /dev/null
sqlite3 /var/local/www/db/moode-sqlite3.db "DELETE FROM cfg_radio"

# Install new station data
cp /tmp/var/lib/mpd/music/RADIO/*.pls /var/lib/mpd/music/RADIO
cp /tmp/var/local/www/imagesw/radio-logos/*.jpg /var/local/www/imagesw/radio-logos
cp /tmp/var/local/www/imagesw/radio-logos/thumbs/*.jpg /var/local/www/imagesw/radio-logos/thumbs
sqlite3 /var/local/www/db/moode-sqlite3.db -csv ".import /tmp/var/local/www/db/cfg_radio.csv cfg_radio"

# Cleanup temp files
rm /var/local/www/station_import.zip 2> /dev/null
rm -rf /tmp/var 2> /dev/null
