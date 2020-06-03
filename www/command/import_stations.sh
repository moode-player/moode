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
# 2020-MM-DD TC moOde 6.6.0
#

# Purge existing station data
rm /var/lib/mpd/music/RADIO/* 2> /dev/null
rm /var/www/images/radio-logos/*.jpg 2> /dev/null
rm /var/www/images/radio-logos/thumbs/*.jpg 2> /dev/null
sqlite3 /var/local/www/db/moode-sqlite3.db "DELETE FROM cfg_radio"

# Install new station data
unzip -q /var/local/www/station_import.zip -d /
sqlite3 /var/local/www/db/moode-sqlite3.db -csv ".import /var/local/www/db/cfg_radio.csv cfg_radio"
rm /var/local/www/station_import.zip 2> /dev/null
rm /var/local/www/db/cfg_radio.csv 2> /dev/null
