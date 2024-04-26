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
print(str(datetime.datetime.now())[:19] + ' bounce_time=' + str(bounce_time))

# Configure the pins
cursor.execute("SELECT * FROM cfg_gpio")
for row in cursor:
    if row['id'] != 99:
        print(str(datetime.datetime.now())[:19] +
            ' row' + str(row['id']) + ', pin' + str(row['pin']) + ', enabled=' + row['enabled'] + ', pull=' + row['pull'] + ', command=' + row['command'])
    if str(row['id']) == '1' and row['enabled'] == '1':
        btn_1_pin = int(row['pin'])
        btn_1_pull = row['pull']
        btn_1_cmd = row['command'].split(',')
        btn_1_cmd = [x.strip() for x in btn_1_cmd]
        GPIO.setup(btn_1_pin, GPIO.IN, pull_up_down=btn_1_pull)

        def btn_1_event(channel):
            time.sleep(0.005) # edge debounce of 5 ms
            # only deal with valid edges
            if GPIO.input(channel) == 1:
                subprocess.call(btn_1_cmd)

        GPIO.add_event_detect(btn_1_pin, GPIO.RISING, callback=btn_1_event, bouncetime=bounce_time)
        print(str(datetime.datetime.now())[:19] + ' btn_1: pin=' +
            str(btn_1_pin) + ', enabled=' + row['enabled'] + ', pull=' + str(row['pull']) +
            ', bounce_time=' + str(bounce_time) + ', cmd=' + row['command'])
    elif str(row['id']) == '2' and row['enabled'] == '1':
        btn_2_pin = int(row['pin'])
        btn_2_pull = row['pull']
        btn_2_cmd = row['command'].split(',')
        btn_2_cmd = [x.strip() for x in btn_2_cmd]
        GPIO.setup(btn_2_pin, GPIO.IN, pull_up_down=btn_2_pull)

        def btn_2_event(channel):
            time.sleep(0.005) # edge debounce of 5 ms
            # only deal with valid edges
            if GPIO.input(channel) == 1:
                subprocess.call(btn_2_cmd)

        GPIO.add_event_detect(btn_2_pin, GPIO.RISING, callback=btn_2_event, bouncetime=bounce_time)
        print(str(datetime.datetime.now())[:19] + ' btn_2: pin=' +
            str(btn_2_pin) + ', enabled=' + row['enabled'] + ', pull=' + str(row['pull']) +
            ', bounce_time=' + str(bounce_time) + ', cmd=' + row['command'])
    elif str(row['id']) == '3' and row['enabled'] == '1':
        btn_3_pin = int(row['pin'])
        btn_3_pull = row['pull']
        btn_3_cmd = row['command'].split(',')
        btn_3_cmd = [x.strip() for x in btn_3_cmd]
        GPIO.setup(btn_3_pin, GPIO.IN, pull_up_down=btn_3_pull)

        def btn_3_event(channel):
            time.sleep(0.005) # edge debounce of 5 ms
            # only deal with valid edges
            if GPIO.input(channel) == 1:
                subprocess.call(btn_3_cmd)

        GPIO.add_event_detect(btn_3_pin, GPIO.RISING, callback=btn_3_event, bouncetime=bounce_time)
        print(str(datetime.datetime.now())[:19] + ' btn_3: pin=' +
            str(btn_3_pin) + ', enabled=' + row['enabled'] + ', pull=' + str(row['pull']) +
            ', bounce_time=' + str(bounce_time) + ', cmd=' + row['command'])
    elif str(row['id']) == '4' and row['enabled'] == '1':
        btn_4_pin = int(row['pin'])
        btn_4_pull = row['pull']
        btn_4_cmd = row['command'].split(',')
        btn_4_cmd = [x.strip() for x in btn_4_cmd]
        GPIO.setup(btn_4_pin, GPIO.IN, pull_up_down=btn_4_pull)

        def btn_4_event(channel):
            time.sleep(0.005) # edge debounce of 5 ms
            # only deal with valid edges
            if GPIO.input(channel) == 1:
                subprocess.call(btn_4_cmd)

        GPIO.add_event_detect(btn_4_pin, GPIO.RISING, callback=btn_4_event, bouncetime=bounce_time)
        print(str(datetime.datetime.now())[:19] + ' btn_4: pin=' +
            str(btn_4_pin) + ', enabled=' + row['enabled'] + ', pull=' + str(row['pull']) +
            ', bounce_time=' + str(bounce_time) + ', cmd=' + row['command'])
    elif str(row['id']) == '5' and row['enabled'] == '1':
        btn_5_pin = int(row['pin'])
        btn_5_pull = row['pull']
        btn_5_cmd = row['command'].split(',')
        btn_5_cmd = [x.strip() for x in btn_5_cmd]
        GPIO.setup(btn_5_pin, GPIO.IN, pull_up_down=btn_5_pull)

        def btn_5_event(channel):
            time.sleep(0.005) # edge debounce of 5 ms
            # only deal with valid edges
            if GPIO.input(channel) == 1:
                subprocess.call(btn_5_cmd)

        GPIO.add_event_detect(btn_5_pin, GPIO.RISING, callback=btn_5_event, bouncetime=bounce_time)
        print(str(datetime.datetime.now())[:19] + ' btn_5: pin=' +
            str(btn_5_pin) + ', enabled=' + row['enabled'] + ', pull=' + str(row['pull']) +
            ', bounce_time=' + str(bounce_time) + ', cmd=' + row['command'])
    elif str(row['id']) == '6' and row['enabled'] == '1':
        btn_6_pin = int(row['pin'])
        btn_6_pull = row['pull']
        btn_6_cmd = row['command'].split(',')
        btn_6_cmd = [x.strip() for x in btn_6_cmd]
        GPIO.setup(btn_6_pin, GPIO.IN, pull_up_down=btn_6_pull)

        def btn_6_event(channel):
            time.sleep(0.005) # edge debounce of 5 ms
            # only deal with valid edges
            if GPIO.input(channel) == 1:
                subprocess.call(btn_6_cmd)

        GPIO.add_event_detect(btn_6_pin, GPIO.RISING, callback=btn_6_event, bouncetime=bounce_time)
        print(str(datetime.datetime.now())[:19] + ' btn_6: pin=' +
            str(btn_6_pin) + ', enabled=' + row['enabled'] + ', pull=' + str(row['pull']) +
            ', bounce_time=' + str(bounce_time) + ', cmd=' + row['command'])
    elif str(row['id']) == '7' and row['enabled'] == '1':
        btn_7_pin = int(row['pin'])
        btn_7_pull = row['pull']
        btn_7_cmd = row['command'].split(',')
        btn_7_cmd = [x.strip() for x in btn_7_cmd]
        GPIO.setup(btn_7_pin, GPIO.IN, pull_up_down=btn_7_pull)

        def btn_7_event(channel):
            time.sleep(0.005) # edge debounce of 5 ms
            # only deal with valid edges
            if GPIO.input(channel) == 1:
                subprocess.call(btn_7_cmd)

        GPIO.add_event_detect(btn_7_pin, GPIO.RISING, callback=btn_7_event, bouncetime=bounce_time)
        print(str(datetime.datetime.now())[:19] + ' btn_7: pin=' +
            str(btn_7_pin) + ', enabled=' + row['enabled'] + ', pull=' + str(row['pull']) +
            ', bounce_time=' + str(bounce_time) + ', cmd=' + row['command'])
    elif str(row['id']) == '8' and row['enabled'] == '1':
        btn_8_pin = int(row['pin'])
        btn_8_pull = row['pull']
        btn_8_cmd = row['command'].split(',')
        btn_8_cmd = [x.strip() for x in btn_8_cmd]
        GPIO.setup(btn_8_pin, GPIO.IN, pull_up_down=btn_8_pull)

        def btn_8_event(channel):
            time.sleep(0.005) # edge debounce of 5 ms
            # only deal with valid edges
            if GPIO.input(channel) == 1:
                subprocess.call(btn_8_cmd)

        GPIO.add_event_detect(btn_8_pin, GPIO.RISING, callback=btn_8_event, bouncetime=bounce_time)
        print(str(datetime.datetime.now())[:19] + ' btn_8: pin=' +
            str(btn_8_pin) + ', enabled=' + row['enabled'] + ', pull=' + str(row['pull']) +
            ', bounce_time=' + str(bounce_time) + ', cmd=' + row['command'])

# Main
while True:
    time.sleep(sleep_time)
