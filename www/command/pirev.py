#!/usr/bin/python3
#
# moOde audio player (C) 2014 Tim Curtis
#
# (C) 2020 @bitlab (@bitkeeper Git)
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
# Show Pi revision code information
#
# Revision code information taken from:
# https://www.raspberrypi.org/documentation/hardware/raspberrypi/revision-codes/README.md
#

import argparse

OLD_REVISION_CODES = {
# code  mod   rev    mem      manufacure
0x002: ["B", "1.0", "256MB", "Egoman"],
0x003: ["B", "1.0", "256MB", "Egoman"],
0x004: ["B", "2.0", "256MB", "Sony UK"],
0x005: ["B", "2.0", "256MB", "Qisda"],
0x006: ["B", "2.0", "256MB", "Egoman"],
0x007: ["A", "2.0", "256MB", "Egoman"],
0x008: ["A", "2.0", "256MB", "Sony UK"],
0x009: ["A", "2.0", "256MB", "Qisda"],
0x00d: ["B", "2.0", "512MB", "Egoman"],
0x00e: ["B", "2.0", "512MB", "Sony UK"],
0x00f: ["B", "2.0", "512MB", "Egoman"],
0x010: ["B+", "1.2", "512MB", "Sony UK"],
0x011: ["CM1", "1.0", "512MB", "Sony UK"],
0x012: ["A+", "1.1", "256MB", "Sony UK"],
0x013: ["B+", "1.2", "512MB", "Embest"],
0x014: ["CM1", "1.0", "512MB", "Embest"],
0x015: ["A+", "1.1", "256MB/512MB", "Embest"],
}

PI_TYPES = {
    0: "A",
    1: "B",
    2: "A+",
    3: "B+",
    4: "2B",
    5: "Alpha",
    6: "CM1",
    8: "3B",
    9: "Zero",
    0xa: "CM3",
    0xc: "Zero W",
    0xd: "3B+",
    0xe: "3A+",
    0xf: "Internal",
    0x10: "CM3+",
    0x11: "4B",
    0x13: "400",
    0x14: "CM4"
}

PI_MEM = {
    0: "256MB",
    1: "512MB",
    2: "1GB",
    3: "2GB",
    4: "4GB",
    5: "8GB"
}

PI_PROC = {
    0: "BCM2835",
    1: "BCM2836",
    2: "BCM2837",
    3: "BCM2711"
}

PI_MAN = {
    0: "Sony UK",
    1: "Egoman",
    2: "Embest",
    3: "Sony Japan",
    4: "Embest",
    5: "Stadium"
}
def decode_new_style_code(code):
    # Mask: NOQuuuWuFMMMCCCCPPPPTTTTTTTTRRRR
    new_style = (code>>23)&0x1 == 1 # new/old style F

    if new_style == True:
        rev_info = {
            "type": PI_TYPES[(code>>4)&0xff], # model TTTTTTTT
            "rev": "1.%d" %(code&0xf), # rev RRRR
            "mem": PI_MEM[(code>>20)&0x7], # mem MMM
            "man": PI_MAN[(code>>16)&0xf], # manufacture CCCC
            "proc": PI_PROC[(code>>12)&0xf] # proc PPPP
        }
    else:
        old_rev = OLD_REVISION_CODES[code&0x17]
        rev_info = {
            "type": old_rev[0],
            "rev": old_rev[1],
            "mem": old_rev[2],
            "man": old_rev[3],
            "proc": "?"
        }
    return rev_info


def main():
    parser = argparse.ArgumentParser(description='Show Raspberry Pi revision information.')
    parser.add_argument('-t', '--type', action='store_true', help='show type')
    parser.add_argument('-r', '--rev', action='store_true', help='show revision')
    parser.add_argument('-m', '--mem', action='store_true', help='show memory')
    parser.add_argument('-b', '--man', action='store_true', help='show manufacturer')
    parser.add_argument('-p', '--proc', action='store_true', help='show processor')
    parser.add_argument('-c', '--rcode', action='store_true', help='show revision code')
    parser.add_argument('-a', '--all', action='store_true', help='show all')
    parser.add_argument('code', help='revision code (like a02082 or 0xa02082)')
    args = parser.parse_args()

    code = int( args.code if "0x" == args.code[:2] else "0x" + args.code, 16)
    rev_info = decode_new_style_code(code)

    info_text = ''
    if args.rcode or args.all:
        info_text += hex(code) + "\t"
    if args.type or args.all:
        info_text += rev_info['type'] + "\t"
    if args.rev or args.all:
        info_text += rev_info['rev'] + "\t"
    if args.mem or args.all:
        info_text += rev_info['mem'] + "\t"
    if args.man or args.all:
        info_text += rev_info['man'] + "\t"
    if args.proc or args.all:
        info_text += rev_info['proc'] + "\t"
    info_text = info_text.strip()

    print(info_text)

if __name__ == "__main__":
    main()
