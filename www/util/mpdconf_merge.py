#!/usr/bin/python3
#
# SPDX-License-Identifier: GPL-3.0-or-later
# Copyright 2014 The moOde audio player project / Tim Curtis
# Copyright 2020 @bitlab (@bitkeeper Git)
#

#
# If setting exists in both the second conf is leading
# Scripts:
# - First script is /etc/mpd.moode.conf
# - Second script is /etc/mpd.custom.conf
# Output:
# - Output is writen to /etc/mpd.conf
#

import datetime
from os import path
import argparse

COMMENT = "#"
SECTION_BEGIN = "{"
SECTION_END = "}"

class InvalidLine(BaseException):
    def __init__(self, msg):
        BaseException.__init__(self, msg)

def get_section_type(section):
    return list(section.keys())[0]

def get_section_data(section):
    return section[get_section_type(section)]

def entry_to_string(entry):
    return "%s \"%s\"" %(entry[0],entry[1])

def get_setting(setting, source):
    for entry in source:
        if type(entry)==tuple and entry[0]==setting:
            return entry
    return None

def get_section(section, source):
    section_type = get_section_type(section)
    section_data = get_section_data(section)
    section_name = get_setting("name", section_data)
    for entry in source:
        if type(entry)==dict and \
           get_section_type(entry)==section_type \
           and (section_name==None or section_name==get_setting("name", get_section_data(entry)) ):
            return entry
    return None

def read_mpd_conf(filename):
    lines = []
    fsource = open(filename, 'r')
    current_scope = None
    scope_data = None
    dest = None
    for  linenr, linetxt in enumerate(fsource):
        if current_scope:
            dest = scope_data
        else:
            dest = lines
        if linetxt.strip().find(COMMENT) == 0:
            dest.append(linetxt)
        elif linetxt.strip() == '':
            dest.append(linetxt)
        elif linetxt.strip() == SECTION_END:
            lines.append({current_scope: scope_data})
            current_scope = None
            scope_data = []
        elif linetxt.strip()[-1] == SECTION_BEGIN:
            scope = linetxt.strip()[:-1].strip()
            current_scope = scope
            scope_data = []
        elif linetxt.strip()[-1] == '"':
            idx = linetxt.strip().find(' "')
            if idx==-1:
                raise InvalidLine("Invalid line nr %d: '%s'" %(linenr, linetxt))
            setting = linetxt.strip()[ : idx].strip()
            value = linetxt.strip()[idx+2:-1]
            dest.append( (setting, value))
        else:
            InvalidLine("Invalid line nr %d: '%s'" %(linenr, linetxt))
    fsource.close()
    return lines

def write_mpd_conf(filename, conf):
    foutput = open(filename, 'w')
    foutput.write(to_text(conf))
    foutput.close()

def merge(sourcea, sourceb):
    output=[]
    for entry in sourcea:
        if type(entry)==str:
            output.append(entry)
        elif type(entry)==tuple:
            entryb = get_setting(entry[0], sourceb)
            if entryb and entry_to_string(entry) != entry_to_string(entryb):
                output.append("# setting '%s' is replaced by:\n" %entry_to_string(entry))
                output.append(entryb)
                sourceb.remove(entryb)
            elif entryb and entry_to_string(entry) == entry_to_string(entryb):
                sourceb.remove(entryb)
                output.append(entry)
            else:
                output.append(entry)
        elif type(entry)==dict:
            sectiona = entry
            sectiona_type = get_section_type(sectiona)
            section_data = get_section_data(sectiona)
            sectionb = get_section(sectiona, sourceb)
            if sectionb:
                sectionb_data =  get_section_data(sectionb)
                output.append( {sectiona_type: merge(section_data, sectionb_data)})
                sourceb.remove(sectionb)
            else:
                output.append(sectiona)
        else:
             InvalidLine("Hum unexpected")
    for entry in sourceb:
        output.append(entry)
    return output


def to_text(source, depth=0):
    prefix = " "*(depth*3)
    output = ''
    for entry in source:
        if type(entry)==str:
            output+=prefix+entry
        elif type(entry)==tuple:
            output+=prefix+entry_to_string(entry)+"\n"
        else:
            section = entry
            section_type = get_section_type(section)
            output += prefix+section_type+ " "+SECTION_BEGIN+'\n'
            output += prefix+to_text(get_section_data(section), depth=1)
            output += prefix+SECTION_END+'\n'
    return output

def get_cmdline_arguments():
    parser = argparse.ArgumentParser(description='Merge MPD configuration files.')
    parser.add_argument('nameconf1',
                   help='The name of the first configuration file. For example /etc/mpd.moode.conf.')
    parser.add_argument('nameconf2',
                   help='Name of th configuration file to merge in the first one. For example /etc/mpd.custom.conf.')

    parser.add_argument('--dry-run', dest='dryrun', action='store_const',
                   const=sum,
                   help='Perform a test run without writing the files.')
    parser.add_argument('--to-screen', dest='toscreen', action='store_const',
                   const=sum,
                   help='Show merged output on screen.')

    parser.add_argument('--destination', default = "/etc/mpd.conf",
                   help='Name of the merged configuration file.')

    args = parser.parse_args()
    return args

if __name__ == "__main__":
    args = get_cmdline_arguments()
    file1_name = args.nameconf1 #'/etc/mpd.moode.conf'
    file2_name = args.nameconf2 #'/etc/mpd.custom.conf'
    output_file = args.destination #'/etc/mpd.conf'
    confa = read_mpd_conf(file1_name)
    # if not exists just ignore and return the unmodifed as output
    if path.exists(file2_name):
        confb = read_mpd_conf(file2_name)
        output = merge(confa, confb)

        output.insert(0, "##################################################\n")
        output.insert(1, "# automatic mpd conf merge %s\n" %str(datetime.datetime.now()))
        output.insert(2, "# file 1: '%s'\n" %file1_name)
        output.insert(3, "# file 2: '%s'\n" %file2_name)
        output.insert(4, "##################################################\n")
        output.insert(5, "\n")
    else:
        output = confa

    if args.toscreen:
        print(to_text(output))
    if not args.dryrun:
        write_mpd_conf(output_file, output)
