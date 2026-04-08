#!/bin/python3
#
# Upgrade the camilladsp v2.x/v3.x configuration to v4.x
#
# License : MIT
# Copyright 2024 @bitlab
# '26 added format update
#

import sys
import yaml

FORMAT_CONVERSION_TABLE = {
    'FLOAT64LE' : 'F64_LE',
    'FLOAT32LE' : 'F32_LE',
    'S32LE' : 'S32_LE',
    'S24LE3' : 'S24_3_LE',
    'S24LE' : 'S24_4_RJ_LE',
    'S16LE' : 'S16_LE'
    }

def patchFormatV4(format):
    return  FORMAT_CONVERSION_TABLE[format] if format in FORMAT_CONVERSION_TABLE else format

def main():
    pipelinefilename = sys.argv[1]
    pipelinefile = open(pipelinefilename)
    conf = yaml.safe_load(pipelinefile)

    print('process')
    # create copy
    with open(f'{pipelinefilename[:-4]}.backup.yml', 'w') as file:
        yaml.dump(conf, file)
    match conf:
        case {'devices': {'capture': capture}}:
            print('hit capture')
            if 'filename' in capture :
                print('old capture format found, patch it')
                del capture['filename']
                capture['type'] = 'Stdin'
            if 'format' in capture:
                capture['format'] = patchFormatV4(capture['format'])
            print(capture) 
    match conf:
        case {'devices': {'playback': playback}}:
            print('hit playback')
            if 'format' in playback:
                playback['format'] = 'S24_4_LE' if playback['format'] == 'S24LE' else patchFormatV4(playback['format'])
            print(playback) 

    for name, filt in conf.get("filters", {}).items():
        match filt:
            case {"type": "Conv", "parameters": params}:
                if 'format' in params:
                    params['format'] = patchFormatV4(params['format'])
                print(f"Conv filter found: {name}")
                print(f"Parameters: {params}")            
    
    # remove_entries = []
    for entry in conf['pipeline']:
        # if conf[entry] is None:
        #     remove_entries.append(entry)

        remove_items = []
        for item in entry:
            if entry[item] is None:
                remove_items.append(item)
        # remove empty nodes
        for item in remove_items:
                del entry[item]

        if 'channel' in entry:
            print('old channel construction present, patch it')
            entry['channels']=[entry['channel']]
            del entry['channel']
        if 'bypassed' in entry and entry['bypassed'] is None:
            entry['bypassed'] = False
        print(entry)

    # for entry in remove_entries:
    #     del conf[entry]
    with open(f'{pipelinefilename}', 'w') as file:
        yaml.dump(conf, file)
if __name__ == "__main__":
    main()
