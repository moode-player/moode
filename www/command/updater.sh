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
# 2019-04-12 TC moOde 5.0
#

# $1 = rXY ex: r26

messageLog () {
	echo "$1"
	TIME=$(date +'%Y%m%d %H%M%S')
	echo "$TIME updater: $1" >> /var/log/moode.log
}

SQLDB=/var/local/www/db/moode-sqlite3.db
URL=$(sqlite3 $SQLDB "select value from cfg_system where param='res_software_upd_url'")

cd /var/local/www

messageLog "Downloading update package $1"
wget -q $URL/update-$1.zip -O update-$1.zip
unzip -q -o update-$1.zip
rm update-$1.zip

chmod -R 0755 update
update/install.sh

if [ $? -ne 0 ] ; then
	messageLog "Update cancelled"
else
	wget -q $URL/update-$1.txt -O update-$1.txt
	messageLog "Update installed, REBOOT required"
fi

rm -rf update

cd ~/
