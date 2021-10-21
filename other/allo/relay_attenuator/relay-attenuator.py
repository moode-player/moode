#!/usr/bin/python
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
#	Inspired by code written by @andrewewa
#
#	Allo Relay Attenuator
#	- volume range: 63 - 127 (64 steps)
#	- 63 or less is mute ON
#	- 64 is 0dB
#	- 127 is -63dB
#	- mute is on 6th bit (counted from 0), 0 is mute ON, 1 is mute OFF
#	- overall value for volume is in range of 0 - 127, but mute being off makes it 64 - 127
#
#	Configuration
#	- sudo apt-get -y install python-smbus i2c-tools libi2c-dev
#	- sudo nano /etc/modules
# /etc/modules: kernel modules to load at boot time.
#
# This file contains the names of kernel modules that should be loaded
# at boot time, one per line. Lines beginning with "#" are ignored.
#i2c-dev
#i2c-bcm2708
#
#sudo i2cdetect -y 1
#

import smbus
import sys

program_version = "1.0"

bus_address = 0x21
bus = smbus.SMBus(1)

def main():
	# Parse input args
	if len(sys.argv) > 1:
		if sys.argv[1] == "--version" or sys.argv[1] == "-v":
			print("relay-attenuator.py version " + program_version)
			sys.exit(0)

		if len(sys.argv) > 2:
			print("Too many arguments")
			sys.exit(0)
		else:
			volume = int(sys.argv[1])

			if volume <= 0:
				volume = 63
			elif volume >= 63:
				volume = 64
			else:
				volume = 127 - volume

			update_volume(volume)

def update_volume(volume):
	print(volume)
	bus.write_byte(bus_address, volume)

#
# Script starts here
#
if __name__ == '__main__':
	main()
