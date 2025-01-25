#!/bin/python3
#
# Upgrade the camilladsp v2.x configuration to v3.x
#
# License : MIT
# Copyright 2024 @bitlab
#

import sys
import yaml

def main():
    pipelinefilename = sys.argv[1]
    pipelinefile = open(pipelinefilename)
    conf = yaml.safe_load(pipelinefile)

    # create copy
    with open(f'{pipelinefilename[:-4]}.v2.yml', 'w') as file:
        yaml.dump(conf, file)
    match conf:
        case {'devices': {'capture': capture}}:
            if 'filename' in capture :
                print('old capture format found, patch it')
                del capture['filename']
                capture['type'] = 'Stdin'
            print(capture)
    remove_entries = []
    for entry in conf['pipeline']:
        if conf[entry] is None:
            remove_entries.append(entry)

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

    # remove empty nodes
    for entry in remove_entries:
        del conf[entry]
    with open(f'{pipelinefilename}', 'w') as file:
        yaml.dump(conf, file)
if __name__ == "__main__":
    main()
