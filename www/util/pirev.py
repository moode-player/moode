#!/usr/bin/python3
#
# SPDX-License-Identifier: GPL-3.0-or-later
# Copyright 2014 The moOde audio player project / Tim Curtis
# Copyright 2020 @bitlab (@bitkeeper Git)
#

#
# Show Pi revision code information
#
# Revision code information taken from:
# https://www.raspberrypi.org/documentation/hardware/raspberrypi/revision-codes/README.md
#

import argparse
import subprocess
import sys

OLD_REVISION_CODES = {
    # code  mod   rev    mem      manufacturer
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
    0x015: ["A+", "1.1", "256MB/512MB", "Embest"]
}

PI_TYPES = {
    0: "A",
    1: "B",
    2: "A+",
    3: "B+",
    4: "2B",
    5: "Alpha (early prototype)",
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
    0x12: "Zero 2 W",
    0x13: "400",
    0x14: "CM4",
    0x15: "CM4S",
    0x16: "Internal use only",
    0x17: "5B",
    0x18: "CM5",
    0x1a: "CM5 Lite"
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
    3: "BCM2711",
    4: "BCM2712"
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
        try:
            type = PI_TYPES[(code>>4)&0xff] # model TTTTTTTT
        except KeyError:
            type = "Unknown Pi model"
        try:
            mem = PI_MEM[(code>>20)&0x7] # mem MMM
        except KeyError:
            mem = "?GB"
        try:
            man = PI_MAN[(code>>16)&0xf] # manufacture CCCC
        except KeyError:
            man = "Unknown manufacturer"
        try:
            proc = PI_PROC[(code>>12)&0xf] # proc PPPP
        except KeyError:
            proc = "Unknown processor"

        if type == "Unknown Pi model":
            rev = "?.?"
        else:
            rev = "1.%d" %(code&0xf) # rev RRRR

        rev_info = {
            "type": type,
            "rev": rev,
            "mem": mem,
            "man": man,
            "proc": proc
        }
    else:
        # Original was code&0x17 but this returned the entry for 0x004 when code = 0x00e
        old_rev = OLD_REVISION_CODES[code]
        rev_info = {
            "type": old_rev[0],
            "rev": old_rev[1],
            "mem": old_rev[2],
            "man": old_rev[3],
            "proc": "?"
        }
    return rev_info


def main():
    parser = argparse.ArgumentParser(description='Print Pi revision code information. If [code] is not present then the code for this Pi is used.')
    parser.add_argument('-t', '--type', action='store_true', help='Print model type')
    parser.add_argument('-r', '--rev', action='store_true', help='Print model revision')
    parser.add_argument('-m', '--mem', action='store_true', help='Print memory')
    parser.add_argument('-b', '--man', action='store_true', help='Print manufacturer')
    parser.add_argument('-p', '--proc', action='store_true', help='Print processor')
    parser.add_argument('-c', '--rcode', action='store_true', help='Print revision code')
    parser.add_argument('-a', '--all', action='store_true', help='Print all')
    parser.add_argument('code', nargs='?', help='Revision code (like a02082 or 0xa02082)')
    args = parser.parse_args()

    if not len(sys.argv) > 1:
        args.all = True

    if args.code:
        code = int(args.code if "0x" == args.code[:2] else "0x" + args.code, 16)
    else:
        # NOTE: In otp_dump the Pi5 revcode is on line 32 while < Pi5 is on line 30.
        #cmd = "vcgencmd otp_dump | awk -F: '/^30:/{print substr($2,3)}'"
        # Alternate command for obtaining the revision code.
        cmd = "cat /proc/cpuinfo | awk -F': ' '/Revision/ {print $2}'"
        code = int("0x" + subprocess.run(cmd, shell=True, text=True, capture_output=True).stdout.rstrip(), 16)

    rev_info = decode_new_style_code(code)

    info_text = ''
    if args.rcode or args.all or args.code:
        info_text += hex(code) + "\t"
    if args.type or args.all or args.code:
        info_text += rev_info['type'] + "\t"
    if args.rev or args.all or args.code:
        info_text += rev_info['rev'] + "\t"
    if args.mem or args.all or args.code:
        info_text += rev_info['mem'] + "\t"
    if args.man or args.all or args.code:
        info_text += rev_info['man'] + "\t"
    if args.proc or args.all or args.code:
        info_text += rev_info['proc'] + "\t"
    info_text = info_text.strip()

    print(info_text)

if __name__ == "__main__":
    main()
