#!/usr/bin/python3
#
# moOde audio player (C) 2014 Tim Curtis
# http://moodeaudio.org
#
# This program is free software: you can redistribute it and/or modify
# it under the terms of the GNU General Public License version 2 as
# published by the Free Software Foundation.
#
# This program is distributed in the hope that it will be useful,
# but WITHOUT ANY WARRANTY; without even the implied warranty of
# MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
# GNU General Public License for more details.
#
# You should have received a copy of the GNU General Public License
# along with this program.  If not, see <https://www.gnu.org/licenses/>.
#
# Stub script for lcd-updater.sh daemon
#

import subprocess

homeDir = subprocess.run(['ls', '/home/'], stdout=subprocess.PIPE).stdout.decode('utf-8').strip()
with open("/var/local/www/currentsong.txt") as file1:
    with open("/home/" + homeDir + "/lcd.txt", "w") as file2:
        for line in file1:
            file2.write(line)
