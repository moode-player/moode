#!/usr/bin/python3
#
# SPDX-License-Identifier: GPL-3.0-or-later
# Copyright 2014 The moOde audio player project / Tim Curtis
#

# Usage:
# rotenc_py <poll_interval in ms> <accel_factor> <volume_step> <pin_a> <pin_b> <print_debug>
# rotenc_py 100 2 3 23 24 1 (print_debug is optional)
#

import RPi.GPIO as GPIO
import threading
import subprocess
import sys
from time import sleep
import sqlite3
import musicpd

program_version = "1.0"

current_pos = 0
last_pos = 0
last_a_state = 1
last_b_state = 1
pin_a = 23
pin_b = 24
poll_interval = 100 # milliseconds
accel_factor = 2
volume_step = 3
print_debug = 0
thread_lock = threading.Lock()

def main():
	global poll_interval, accel_factor, volume_step, pin_a, pin_b, print_debug, db, db_cursor, mpd_cli

	# Parse input args (if any)
	if len(sys.argv) > 1:
		if sys.argv[1] == "--version" or sys.argv[1] == "-v":
			print("rotenc.py version " + program_version)
			sys.exit(0)

		if len(sys.argv) >= 6:
			poll_interval = int(sys.argv[1])
			accel_factor = int(sys.argv[2])
			volume_step = int(sys.argv[3])
			pin_a = int(sys.argv[4])
			pin_b = int(sys.argv[5])

		if len(sys.argv) == 7:
			print_debug = int(sys.argv[6])

		if print_debug:
			print(sys.argv, len(sys.argv))

	# Setup GPIO
	GPIO.setmode(GPIO.BCM) # SoC pin numbering
	GPIO.setwarnings(True)
	GPIO.setup(pin_a, GPIO.IN, pull_up_down=GPIO.PUD_UP)
	GPIO.setup(pin_b, GPIO.IN, pull_up_down=GPIO.PUD_UP)
	GPIO.add_event_detect(pin_a, GPIO.BOTH, callback=encoder_isr) # NOTE: bouncetime= is not specified
	GPIO.add_event_detect(pin_b, GPIO.BOTH, callback=encoder_isr)

	# Setup sqlite
	db = sqlite3.connect('/var/local/www/db/moode-sqlite3.db')
	db.row_factory = sqlite3.Row
	db.text_factory = str
	db_cursor = db.cursor()

	# Setup MPD client
	mpd_cli = musicpd.MPDClient()
	#mpd_cli.connect()

	# Detect encoder changes
	poll_interval = poll_interval / 1000
	poll_encoder()

# Interrupt service routine (ISR)
def encoder_isr(pin):
	global current_pos, last_a_state, last_b_state, thread_lock

	# Read pin states
	pin_a_state = GPIO.input(pin_a)
	pin_b_state = GPIO.input(pin_b)

	# Ignore interrupt if no state change (debounce)
	if last_a_state == pin_a_state and last_b_state == pin_b_state:
		return

	# Store current as last state
	last_a_state = pin_a_state
	last_b_state = pin_b_state

	# Ignore all states except final state where both are 1
	# Use pin returned from the ISR to determine which pin came first before reaching 1-1
	if pin_a_state and pin_b_state:
		thread_lock.acquire()

		if pin == pin_a:
			current_pos -= 1 # CCW
		else:
			current_pos += 1 # CW

		thread_lock.release()

	return

# Polling loop for updating volume
def poll_encoder():
	global current_pos, last_pos, thread_lock
	direction = ""

	while True:
		thread_lock.acquire()

		if current_pos > last_pos:
			direction = "+"
			if (current_pos - last_pos) < accel_factor:
				update_volume(direction, 1)
			else:
				update_volume(direction, volume_step)
		elif current_pos < last_pos:
			direction = "-"
			if (last_pos - current_pos) < accel_factor:
				update_volume(direction, 1)
			else:
				update_volume(direction, volume_step)

		thread_lock.release()

		if (current_pos != last_pos) and print_debug:
			print(abs(current_pos - last_pos), direction)

		last_pos = current_pos

		sleep(poll_interval)

# Update MPD and UI volume
def update_volume(direction, step):
	db_cursor.execute("SELECT value FROM cfg_system WHERE param='volknob' OR param='volume_mpd_max'")
	row = db_cursor.fetchone()
	current_vol = int(row['value'])
	row = db_cursor.fetchone()
	volume_mpd_max = int(row['value'])

	if direction == "+":
		new_volume = current_vol + step
	else:
		new_volume = current_vol - step

	if new_volume > volume_mpd_max:
		new_volume = volume_mpd_max

	if new_volume > 100:
		new_volume = 100
	elif new_volume < 0:
		new_volume = 0

	db_cursor.execute("UPDATE cfg_system SET value='" + str(new_volume) + "' WHERE param='volknob'")
	db.commit()

	mpd_cli.connect()
	mpd_cli.setvol(new_volume)
	mpd_cli.disconnect()

#
# Script starts here
#
if __name__ == '__main__':
	main()
