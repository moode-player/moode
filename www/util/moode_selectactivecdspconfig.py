#!/usr/bin/python3
#
# SPDX-License-Identifier: GPL-3.0-or-later
# Copyright 2024 @bitlab (@bitkeeper Git)
#

import sys
from os import system
import subprocess
from urllib import request
from pathlib import Path

if len(sys.argv) == 1 or len(sys.argv) >=4  or (len(sys.argv)==2 and sys.argv[1] == 'set')  or (len(sys.argv)>2 and sys.argv[1] == 'get'):
    print("moode_selectactivecdspconfig")
    print("")
    print("This tool is used to select the active CamillaDSP configfile for moode from external")
    print("usage:")
    print("  moode_selectactivecdspconfig get")
    print("  moode_selectactivecdspconfig set <configfile>")
    if len(sys.argv) == 1:
       print("missing command: get | set")
    elif len(sys.argv) == 2 and sys.argv[1] == 'set':
        print("ERROR: missing argument with the CamillaDSP configuration file")
    else:
        print("ERROR: unexpected number of arguments")
    exit(1)

cmd = sys.argv[1]

if cmd == 'set':
    cdsp_configuration_filename = Path(sys.argv[2]).name

    print( f'CamillaDSP active config: {cdsp_configuration_filename}')

    # Convert string to byte
    data = f'cdspconfig={cdsp_configuration_filename}'.encode('utf-8')

    # Post Method is invoked if data != None
    req =  request.Request('http://127.0.0.1/command/camilla.php?cmd=cdsp_set_config', data=data)
    req.add_header('Content-Type', 'application/x-www-form-urlencoded; charset=UTF-8')

    # Response
    resp = request.urlopen(req)
elif cmd == 'get':

    args = "/usr/local/bin/moodeutl -q 'select * from cfg_system'|grep 'camilladsp|'"
    result = subprocess.check_output(args, shell=True, text=True)
    active_config = result.split('|')[2]
    if active_config == 'off':
        active_config = ''
    else:
        active_config = f'/usr/share/camilladsp/configs/{active_config}'
    print(active_config)
else:
    print('unexpected command')
    exit(1)
