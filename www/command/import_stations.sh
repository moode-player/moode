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
# 2020-12-15 TC moOde 7.0.0
#

# Store working dir
WD=$(pwd)

# Cleanup temp files
cleanup_temp_files() {
    rm /var/local/www/station_import.zip 2> /dev/null
    rm /var/local/www/db/cfg_radio.schema 2> /dev/null
    rm -rf /tmp/var 2> /dev/null
}

log_errors() {
    TIME_STAMP=$(date +'%Y%m%d %H%M%S')
    echo $TIME_STAMP" import_stations: "$LOG_MSG >> /var/log/moode.log
    echo $LOG_MSG > /tmp/station_import_error.txt
}

# Cleanup any leftover files
rm /tmp/station_import_error.txt 2> /dev/null
rm /var/local/www/db/cfg_radio.schema 2> /dev/null
rm -rf /tmp/var 2> /dev/null

# Unzip the package
unzip -q /var/local/www/station_import.zip -d /tmp
if [ $? -ne 0 ] ; then
    LOG_MSG="Unzip failed, import cancelled"
    log_errors
    cleanup_temp_files
    exit 1
fi

# Check for 7 series schema
RESULT=$(fgrep geo_fenced /tmp/var/local/www/db/cfg_radio.schema)
if [ $? -ne 0 ] ; then
    # Cancel import if not 7 series
    LOG_MSG="Schema mismatch, import cancelled"
    log_errors
    cleanup_temp_files
    exit 1
fi

# Basic file sanitizing
dos2unix -q /tmp/var/lib/mpd/music/RADIO/*.pls
if [ $? -ne 0 ] ; then
    LOG_MSG="Dos2unix failed on .pls files, import cancelled"
    log_errors
    cleanup_temp_files
    exit 1
fi

dos2unix -q /tmp/var/local/www/db/cfg_radio.csv
if [ $? -ne 0 ] ; then
    LOG_MSG="Dos2unix failed on .csv file, import cancelled"
    log_errors
    cleanup_temp_files
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
cp /tmp/var/lib/mpd/music/RADIO/*.pls /var/lib/mpd/music/RADIO/
cp /tmp/var/local/www/imagesw/radio-logos/*.jpg /var/local/www/imagesw/radio-logos/
cp /tmp/var/local/www/imagesw/radio-logos/thumbs/*.jpg /var/local/www/imagesw/radio-logos/thumbs/
sqlite3 /var/local/www/db/moode-sqlite3.db -csv ".import /tmp/var/local/www/db/cfg_radio.csv cfg_radio"

cleanup_temp_files
