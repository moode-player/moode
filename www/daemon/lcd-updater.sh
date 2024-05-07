#!/bin/bash
#
# SPDX-License-Identifier: GPL-3.0-or-later
# Copyright 2014 The moOde audio player project / Tim Curtis
#

eval "/var/local/www/commandw/lcd_updater.py"

while true; do
	inotifywait -e close_write /var/local/www/currentsong.txt
	eval "/var/local/www/commandw/lcd_updater.py"
done > /dev/null 2>&1 &
