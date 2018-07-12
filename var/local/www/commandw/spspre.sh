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
# 2018-01-26 TC moOde 4.0
# 2018-07-11 TC moOde 4.2
# - minor format cleanup
#

SQLDB=/var/local/www/db/moode-sqlite3.db

/usr/bin/mpc stop > /dev/null

# allow time for ui update
sleep 1

# set active flag true
$(sqlite3 $SQLDB "update cfg_system set value='1' where param='airplayactv'")
