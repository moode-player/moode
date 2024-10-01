#!/usr/bin/python3
#
# SPDX-License-Identifier: GPL-3.0-or-later
# Copyright 2014 The moOde audio player project / Tim Curtis
#

#
#	Inspired by the script posted in moOde Forum by @Cyanoazimin
#

import RPi.GPIO as GPIO
import sys
import time
import datetime
import os
import subprocess
import sqlite3

# Use SoC pin (GPIO channel) numbering
GPIO.setmode(GPIO.BCM)

# Get sleep time arg
if len(sys.argv) > 1:
    sleep_time = int(sys.argv[1])
else:
    sleep_time = 1

# Get the configuration
db = sqlite3.connect('/var/local/www/db/moode-sqlite3.db')
db.row_factory = sqlite3.Row
db.text_factory = str
cursor = db.cursor()

# Get bounce_time
cursor.execute("SELECT value FROM cfg_gpio WHERE param='bounce_time'")
row = cursor.fetchone()
bounce_time = int(row['value'])
print('bounce=' + str(bounce_time) + 'ms')

# Configure the pins
cursor.execute("SELECT * FROM cfg_gpio")
for row in cursor:
    pin_enabled = 'y' if row['enabled'] == '1' else 'n'
    pin_pull = 'up' if row['pull'] == '22' else 'dn'
    if row['id'] != 99:
        print('btn' + str(row['id']) +
            ', enabled ' + pin_enabled +
            ', gpio ' + str(row['pin'].rjust(2, '0')) +
            ', pull-' + pin_pull +
            ', cmd=' + row['command'])

    if str(row['id']) == '1' and row['enabled'] == '1':
        if row['pin'] not in ('2','3'): # Pins 2,3 have fixed pull-up resistors
            GPIO.setup(int(row['pin']), GPIO.IN, pull_up_down=int(row['pull']))
            btn_1_cmd = [x.strip() for x in row['command'].split(',')]
        def btn_1_event(channel):
            time.sleep(0.005) # Edge debounce of 5 ms
            if GPIO.input(channel) == 1: # Only deal with valid edges
                subprocess.call(btn_1_cmd)
        GPIO.add_event_detect(int(row['pin']), GPIO.RISING, callback=btn_1_event, bouncetime=bounce_time)
    elif str(row['id']) == '2' and row['enabled'] == '1':
        if row['pin'] not in ('2','3'):
            GPIO.setup(int(row['pin']), GPIO.IN, pull_up_down=int(row['pull']))
            btn_2_cmd = [x.strip() for x in row['command'].split(',')]
        def btn_2_event(channel):
            time.sleep(0.005)
            if GPIO.input(channel) == 1:
                subprocess.call(btn_2_cmd)
        GPIO.add_event_detect(int(row['pin']), GPIO.RISING, callback=btn_2_event, bouncetime=bounce_time)
    elif str(row['id']) == '3' and row['enabled'] == '1':
        if row['pin'] not in ('2','3'):
            GPIO.setup(int(row['pin']), GPIO.IN, pull_up_down=int(row['pull']))
            btn_3_cmd = [x.strip() for x in row['command'].split(',')]
        def btn_3_event(channel):
            time.sleep(0.005)
            if GPIO.input(channel) == 1:
                subprocess.call(btn_3_cmd)
        GPIO.add_event_detect(int(row['pin']), GPIO.RISING, callback=btn_3_event, bouncetime=bounce_time)
    elif str(row['id']) == '4' and row['enabled'] == '1':
        if row['pin'] not in ('2','3'):
            GPIO.setup(int(row['pin']), GPIO.IN, pull_up_down=int(row['pull']))
            btn_4_cmd = [x.strip() for x in row['command'].split(',')]
        def btn_4_event(channel):
            time.sleep(0.005)
            if GPIO.input(channel) == 1:
                subprocess.call(btn_4_cmd)
        GPIO.add_event_detect(int(row['pin']), GPIO.RISING, callback=btn_4_event, bouncetime=bounce_time)
    elif str(row['id']) == '5' and row['enabled'] == '1':
        if row['pin'] not in ('2','3'):
            GPIO.setup(int(row['pin']), GPIO.IN, pull_up_down=int(row['pull']))
            btn_5_cmd = [x.strip() for x in row['command'].split(',')]
        def btn_5_event(channel):
            time.sleep(0.005)
            if GPIO.input(channel) == 1:
                subprocess.call(btn_5_cmd)
        GPIO.add_event_detect(int(row['pin']), GPIO.RISING, callback=btn_5_event, bouncetime=bounce_time)
    elif str(row['id']) == '6' and row['enabled'] == '1':
        if row['pin'] not in ('2','3'):
            GPIO.setup(int(row['pin']), GPIO.IN, pull_up_down=int(row['pull']))
            btn_6_cmd = [x.strip() for x in row['command'].split(',')]
        def btn_6_event(channel):
            time.sleep(0.005)
            if GPIO.input(channel) == 1:
                subprocess.call(btn_6_cmd)
        GPIO.add_event_detect(int(row['pin']), GPIO.RISING, callback=btn_6_event, bouncetime=bounce_time)
    elif str(row['id']) == '7' and row['enabled'] == '1':
        if row['pin'] not in ('2','3'):
            GPIO.setup(int(row['pin']), GPIO.IN, pull_up_down=int(row['pull']))
            btn_7_cmd = [x.strip() for x in row['command'].split(',')]
        def btn_7_event(channel):
            time.sleep(0.005)
            if GPIO.input(channel) == 1:
                subprocess.call(btn_7_cmd)
        GPIO.add_event_detect(int(row['pin']), GPIO.RISING, callback=btn_7_event, bouncetime=bounce_time)
    elif str(row['id']) == '8' and row['enabled'] == '1':
        if row['pin'] not in ('2','3'):
            GPIO.setup(int(row['pin']), GPIO.IN, pull_up_down=int(row['pull']))
            btn_8_cmd = [x.strip() for x in row['command'].split(',')]
        def btn_8_event(channel):
            time.sleep(0.005)
            if GPIO.input(channel) == 1:
                subprocess.call(btn_8_cmd)
        GPIO.add_event_detect(int(row['pin']), GPIO.RISING, callback=btn_8_event, bouncetime=bounce_time)

# Main
while True:
    time.sleep(sleep_time)
