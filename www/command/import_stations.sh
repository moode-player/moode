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
# 2020-07-09 TC moOde 6.6.0
#

# Purge existing station data
rm /var/lib/mpd/music/RADIO/* 2> /dev/null
rm /var/local/www/imagesw/radio-logos/*.jpg 2> /dev/null
rm /var/local/www/imagesw/radio-logos/thumbs/*.jpg 2> /dev/null
sqlite3 /var/local/www/db/moode-sqlite3.db "DELETE FROM cfg_radio"

# Install new station data
unzip -q /var/local/www/station_import.zip -d /tmp
sudo cp /tmp/var/lib/mpd/music/RADIO/*.pls var/lib/mpd/music/RADIO
sudo cp /tmp/var/local/www/imagesw/radio-logos/*.jpg var/local/www/imagesw/radio-logos
sudo cp /tmp/var/local/www/imagesw/radio-logos/thumbs/*.jpg var/local/www/imagesw/radio-logos/thumbs
sqlite3 /var/local/www/db/moode-sqlite3.db -csv ".import /tmp/var/local/www/db/cfg_radio.csv cfg_radio"

# Cleanup temp files
rm /var/local/www/station_import.zip 2> /dev/null
rm -rf /tmp/var 2> /dev/null
