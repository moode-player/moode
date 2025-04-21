#!/bin/bash
#
# SPDX-License-Identifier: GPL-3.0-or-later
# Copyright 2014 The moOde audio player project / Tim Curtis
#

#
# To enable debug logging
# sudo /var/www/daemon/aplmeta-reader.sh 1 (or 2 for more detail)
#

if [ -z $1 ]; then
	DEBUG=""
else
	DEBUG=$1
fi

cat /tmp/shairport-sync-metadata | shairport-sync-metadata-reader | /var/www/util/aplmeta.py $DEBUG
