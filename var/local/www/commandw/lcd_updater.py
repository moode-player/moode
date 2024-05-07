#!/usr/bin/python3
#
# SPDX-License-Identifier: GPL-3.0-or-later
# Copyright 2014 The moOde audio player project / Tim Curtis
#

#
# Stub script for lcd-updater.sh daemon
#

import subprocess

homeDir = subprocess.run(['ls', '/home/'], stdout=subprocess.PIPE).stdout.decode('utf-8').strip()
with open("/var/local/www/currentsong.txt") as file1:
    with open("/home/" + homeDir + "/lcd.txt", "w") as file2:
        for line in file1:
            file2.write(line)
