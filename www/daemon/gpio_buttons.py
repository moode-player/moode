#!/usr/bin/python3
#
#	moOde audio player (C) 2014 Tim Curtis
#	http://moodeaudio.org
#
#   This program is free software: you can redistribute it and/or modify
#   it under the terms of the GNU General Public License version 2 as
#   published by the Free Software Foundation.
#
#   This program is distributed in the hope that it will be useful,
#   but WITHOUT ANY WARRANTY; without even the implied warranty of
#   MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
#   GNU General Public License for more details.
#
#   You should have received a copy of the GNU General Public License
#   along with this program.  If not, see <https://www.gnu.org/licenses/>.
#
#	Inspired by the script posted in moOde Forum by @Cyanoazimin
#

from __future__ import print_function, absolute_import
import RPi.GPIO as GPIO
import sys
import time
import datetime
import os
import subprocess
import sqlite3

# Use SoC pin numbering
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
#print(str(datetime.datetime.now())[:19] + ' bounce_time=' + str(bounce_time))

# Configure the pins
cursor.execute("SELECT * FROM cfg_gpio")
for row in cursor:
    #print(str(datetime.datetime.now())[:19] + ' row id=' + str(row['id']) + ', enabled=' + row['enabled'] + ', command=' + row['command'])
    if str(row['id']) == '1' and row['enabled'] == '1':
        sw_1_pin = int(row['pin'])
        sw_1_cmd = row['command'].split(',')
        sw_1_cmd = [x.strip() for x in sw_1_cmd]
        GPIO.setup(sw_1_pin, GPIO.IN, pull_up_down=GPIO.PUD_UP)

        def sw_1_event(channel):
            time.sleep(0.005) # edge debounce of 5 ms
            # only deal with valid edges
            if GPIO.input(channel) == 1:
                subprocess.call(sw_1_cmd)

        GPIO.add_event_detect(sw_1_pin, GPIO.RISING, callback=sw_1_event, bouncetime=bounce_time)
        print(str(datetime.datetime.now())[:19] + ' sw_1: pin=' +
            str(sw_1_pin) + ', enabled=' + row['enabled'] +
            ', bounce_time=' + str(bounce_time) + ', cmd=' + row['command'])
    elif str(row['id']) == '2' and row['enabled'] == '1':
        sw_2_pin = int(row['pin'])
        sw_2_cmd = row['command'].split(',')
        sw_2_cmd = [x.strip() for x in sw_2_cmd]
        GPIO.setup(sw_2_pin, GPIO.IN, pull_up_down=GPIO.PUD_UP)

        def sw_2_event(channel):
            time.sleep(0.005) # edge debounce of 5 ms
            # only deal with valid edges
            if GPIO.input(channel) == 1:
                subprocess.call(sw_2_cmd)

        GPIO.add_event_detect(sw_2_pin, GPIO.RISING, callback=sw_2_event, bouncetime=bounce_time)
        print(str(datetime.datetime.now())[:19] + ' sw_2: pin=' +
            str(sw_2_pin) + ', enabled=' + row['enabled'] +
            ', bounce_time=' + str(bounce_time) + ', cmd=' + row['command'])
    elif str(row['id']) == '3' and row['enabled'] == '1':
        sw_3_pin = int(row['pin'])
        sw_3_cmd = row['command'].split(',')
        sw_3_cmd = [x.strip() for x in sw_3_cmd]
        GPIO.setup(sw_3_pin, GPIO.IN, pull_up_down=GPIO.PUD_UP)

        def sw_3_event(channel):
            time.sleep(0.005) # edge debounce of 5 ms
            # only deal with valid edges
            if GPIO.input(channel) == 1:
                subprocess.call(sw_3_cmd)

        GPIO.add_event_detect(sw_3_pin, GPIO.RISING, callback=sw_3_event, bouncetime=bounce_time)
        print(str(datetime.datetime.now())[:19] + ' sw_3: pin=' +
            str(sw_3_pin) + ', enabled=' + row['enabled'] +
            ', bounce_time=' + str(bounce_time) + ', cmd=' + row['command'])
    elif str(row['id']) == '4' and row['enabled'] == '1':
        sw_4_pin = int(row['pin'])
        sw_4_cmd = row['command'].split(',')
        sw_4_cmd = [x.strip() for x in sw_4_cmd]
        GPIO.setup(sw_4_pin, GPIO.IN, pull_up_down=GPIO.PUD_UP)

        def sw_4_event(channel):
            time.sleep(0.005) # edge debounce of 5 ms
            # only deal with valid edges
            if GPIO.input(channel) == 1:
                subprocess.call(sw_4_cmd)

        GPIO.add_event_detect(sw_4_pin, GPIO.RISING, callback=sw_4_event, bouncetime=bounce_time)
        print(str(datetime.datetime.now())[:19] + ' sw_4: pin=' +
            str(sw_4_pin) + ', enabled=' + row['enabled'] +
            ', bounce_time=' + str(bounce_time) + ', cmd=' + row['command'])
    elif str(row['id']) == '5' and row['enabled'] == '1':
        sw_5_pin = int(row['pin'])
        sw_5_cmd = row['command'].split(',')
        sw_5_cmd = [x.strip() for x in sw_5_cmd]
        GPIO.setup(sw_5_pin, GPIO.IN, pull_up_down=GPIO.PUD_UP)

        def sw_5_event(channel):
            time.sleep(0.005) # edge debounce of 5 ms
            # only deal with valid edges
            if GPIO.input(channel) == 1:
                subprocess.call(sw_5_cmd)

        GPIO.add_event_detect(sw_5_pin, GPIO.RISING, callback=sw_5_event, bouncetime=bounce_time)
        print(str(datetime.datetime.now())[:19] + ' sw_5: pin=' +
            str(sw_5_pin) + ', enabled=' + row['enabled'] +
            ', bounce_time=' + str(bounce_time) + ', cmd=' + row['command'])
    elif str(row['id']) == '6' and row['enabled'] == '1':
        sw_6_pin = int(row['pin'])
        sw_6_cmd = row['command'].split(',')
        sw_6_cmd = [x.strip() for x in sw_6_cmd]
        GPIO.setup(sw_6_pin, GPIO.IN, pull_up_down=GPIO.PUD_UP)

        def sw_6_event(channel):
            time.sleep(0.005) # edge debounce of 5 ms
            # only deal with valid edges
            if GPIO.input(channel) == 1:
                subprocess.call(sw_6_cmd)

        GPIO.add_event_detect(sw_6_pin, GPIO.RISING, callback=sw_6_event, bouncetime=bounce_time)
        print(str(datetime.datetime.now())[:19] + ' sw_6: pin=' +
            str(sw_6_pin) + ', enabled=' + row['enabled'] +
            ', bounce_time=' + str(bounce_time) + ', cmd=' + row['command'])
    elif str(row['id']) == '7' and row['enabled'] == '1':
        sw_7_pin = int(row['pin'])
        sw_7_cmd = row['command'].split(',')
        sw_7_cmd = [x.strip() for x in sw_7_cmd]
        GPIO.setup(sw_7_pin, GPIO.IN, pull_up_down=GPIO.PUD_UP)

        def sw_7_event(channel):
            time.sleep(0.005) # edge debounce of 5 ms
            # only deal with valid edges
            if GPIO.input(channel) == 1:
                subprocess.call(sw_7_cmd)

        GPIO.add_event_detect(sw_7_pin, GPIO.RISING, callback=sw_7_event, bouncetime=bounce_time)
        print(str(datetime.datetime.now())[:19] + ' sw_7: pin=' +
            str(sw_7_pin) + ', enabled=' + row['enabled'] +
            ', bounce_time=' + str(bounce_time) + ', cmd=' + row['command'])
    elif str(row['id']) == '8' and row['enabled'] == '1':
        sw_8_pin = int(row['pin'])
        sw_8_cmd = row['command'].split(',')
        sw_8_cmd = [x.strip() for x in sw_8_cmd]
        GPIO.setup(sw_8_pin, GPIO.IN, pull_up_down=GPIO.PUD_UP)

        def sw_8_event(channel):
            time.sleep(0.005) # edge debounce of 5 ms
            # only deal with valid edges
            if GPIO.input(channel) == 1:
                subprocess.call(sw_8_cmd)

        GPIO.add_event_detect(sw_8_pin, GPIO.RISING, callback=sw_8_event, bouncetime=bounce_time)
        print(str(datetime.datetime.now())[:19] + ' sw_8: pin=' +
            str(sw_8_pin) + ', enabled=' + row['enabled'] +
            ', bounce_time=' + str(bounce_time) + ', cmd=' + row['command'])

# Main
while True:
    time.sleep(sleep_time)
