#!/bin/bash
#
# moOde audio player (C) 2014 Tim Curtis
# http://moodeaudio.org
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
# NOTE: Input arg $1 represents the release to be updated and correspondes
#       to the suffix of the update zip file for example update-r802.zip
#   EX: /var/www/util/system-updater.sh r802
#

message_log () {
	echo "$1"
	TIME=$(date +'%Y%m%d %H%M%S')
	echo "$TIME updater: $1" >> /var/log/moode.log
}

# Get URL to the update zip
SQLDB=/var/local/www/db/moode-sqlite3.db
UPD_URL=$(sqlite3 $SQLDB "SELECT value FROM cfg_system WHERE param='res_software_upd_url'")

cd /var/local/www

# Download and unzip
message_log "Downloading update package $1"
wget -q $UPD_URL/update-$1.zip -O update-$1.zip
unzip -q -o update-$1.zip
rm update-$1.zip

# Install update
chmod -R 0755 update
update/install.sh

# Report status
# NOTE: inplace_upd_applied var is checked and reset in daemon/worker.php
if [ $? -ne 0 ] ; then
	$(sqlite3 $SQLDB "UPDATE cfg_system SET value='0' WHERE param='inplace_upd_applied'")
	message_log "Update cancelled"
else
	# Download update-$1.txt to mark successful update
	wget -q $UPD_URL/update-$1.txt -O update-$1.txt
	$(sqlite3 $SQLDB "UPDATE cfg_system SET value='1' WHERE param='inplace_upd_applied'")
	message_log "Update installed, restart required"
fi

# Cleanup
rm -rf update

cd ~/
