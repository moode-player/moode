#!/usr/bin/python3
#
# Script for moOde backups
# (C) 2021 @bitlab (@bitkeeper Git),
#
# This Program is free software; you can redistribute it and/or modify
# it under the terms of the GNU General Public License as published by
# the Free Software Foundation; either version 3, or (at your option)
# any later version.
#
# This Program is distributed in the hope that it will be useful,
# but WITHOUT ANY WARRANTY; without even the implied warranty of
# MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
# GNU General Public License for more details.
#
# You should have received a copy of the GNU General Public License
# along with this program.  If not, see <http://www.gnu.org/licenses/>.
#
# Features:
# - Cmd help with --help
# - Backup with selection what to backup
# - Restore with selection what to backup
# - Info of a backup
# - Contains moodecfg.ini, radio stations, station logos, camilladsp and playlists
#
import argparse

# TC
import logging
import datetime
import glob

from os import system, path, walk
import os
from zipfile import ZipFile

from station_manager import StationManager

class BackupManager(StationManager):
    VERSION = "2.2"

    CDSPCFG_BASE = '/usr/share/camilladsp/'
    MOODECFGINI_TMP = '/tmp/moodecfg.ini'

    OPT_CFG = 'config'
    OPT_CDSP = 'cdsp'
    OPT_PL ='playlists'
    OPT_SEARCHES ='searches'
    OPT_RS_MOODE ='r_moode'
    OPT_RS_OTHER = 'r_other'

    # for real
    MOODECFGINI_RESTORE_PATH = '/boot'
    CDSPCFG_RESTORE_BASE = '/usr/share'
    PLAYLIST_PATH = '/var/lib/mpd'
    PLAYLIST_COVERS_PATH = '/var/local/www/imagesw'
    SEARCHES_PATH = '/var/local/www'
    SEARCHES_PATTERN = 'libsearch_*.json'
    SEARCHES_RESTORE_BASE = '/var/local'


    # for test
    # MOODECFGINI_RESTORE_PATH = '/tmp'
    # CDSPCFG_RESTORE_BASE = '/tmp'

    def __init__(self, db_file, backup_file):
         super().__init__(db_file, backup_file)

    def do_backup(self, what, script_file, wlanpwd):

        scope = None
        if BackupManager.OPT_RS_OTHER in what and BackupManager.OPT_RS_MOODE in what:
            scope = 'all'
        elif BackupManager.OPT_RS_OTHER in what:
            scope = 'other'
        elif BackupManager.OPT_RS_MOODE in what:
            scope = 'moode'

        zipmode = 'w'
        if scope:
            # the stationmanager already creates a zipfile, so make the zipmode append
            self.do_export(scope, None)
            zipmode = 'a'

        if BackupManager.OPT_CFG in what:
            system('moodeutl -e ' + BackupManager.MOODECFGINI_TMP)

            if script_file and os.path.exists(script_file):
                with open(BackupManager.MOODECFGINI_TMP, 'a') as fp:
                    fp.write('[Users]\n')
                    fp.write('script = "'+path.join('/boot','script')+'"\n')
            if wlanpwd:
                system ('sed -i "s/wlanpwd = .*/wlanpwd = \\"{}\\"/g" {}'.format(wlanpwd, BackupManager.MOODECFGINI_TMP))

        with ZipFile(self.backup_file, zipmode) as backup:
            # backup moodecfg.ini
            if BackupManager.OPT_CFG in what:
                if os.path.exists(BackupManager.MOODECFGINI_TMP):
                    backup.write(BackupManager.MOODECFGINI_TMP, 'moodecfg.ini')
                    os.remove(BackupManager.MOODECFGINI_TMP)
                if script_file and os.path.exists(script_file):
                    print('Add script to backup')
                    backup.write(script_file, 'script')
                if os.path.exists('/var/local/www/imagesw/bgimage.jpg'):
                    backup.write('/var/local/www/imagesw/bgimage.jpg', 'bgimage.jpg')

            # backup camilladsp configurations
            if BackupManager.OPT_CDSP in what:
                if path.exists(BackupManager.CDSPCFG_BASE):
                    if path.exists(path.join(BackupManager.CDSPCFG_BASE, 'configs')):
                        print('Backup camilladsp configs')
                        for fpath, subdirs, files in walk(path.join(BackupManager.CDSPCFG_BASE, 'configs')):
                            for name in files:
                                backup.write(path.join(fpath, name), path.join('camilladsp', 'configs', name))
                    if path.exists(path.join(BackupManager.CDSPCFG_BASE, 'coeffs')):
                        print('Backup camilladsp coeffs')
                        for fpath, subdirs, files in walk(path.join(BackupManager.CDSPCFG_BASE, 'coeffs')):
                            for name in files:
                                backup.write(path.join(fpath, name), path.join('camilladsp', 'coeffs', name))
            if BackupManager.OPT_PL in what:
                if path.exists(BackupManager.PLAYLIST_PATH):
                    print('Backup playlists')
                    for fpath, subdirs, files in walk(path.join(BackupManager.PLAYLIST_PATH, 'playlists')):
                        for name in files:
                            backup.write(path.join(fpath, name), path.join('playlists', name))
                    print('Backup playlist covers')
                    for fpath, subdirs, files in walk(path.join(BackupManager.PLAYLIST_COVERS_PATH, 'playlist-covers')):
                        for name in files:
                            backup.write(path.join(fpath, name), path.join('playlist-covers', name))
            if BackupManager.OPT_SEARCHES in what:
                if path.exists(BackupManager.SEARCHES_PATH):
                    print('Backup saved searches')
                    for file in glob.glob(path.join(BackupManager.SEARCHES_PATH, BackupManager.SEARCHES_PATTERN)):
                        backup.write(file, path.join('www', os.path.basename(file)))

    def do_restore(self, what):
        # restore radio stations
        scope = None
        if BackupManager.OPT_RS_OTHER in what and BackupManager.OPT_RS_MOODE in what:
            scope = 'all'
        elif BackupManager.OPT_RS_OTHER in what:
            scope = 'other'
        elif BackupManager.OPT_RS_MOODE in what:
            scope = 'moode'

        if scope:
            self.do_import(scope, 'clear')

        with ZipFile(self.backup_file, 'r') as backup:
            # restore moodecfg.ini
            if BackupManager.OPT_CFG in what:
                try:
                    print('Restore moodecfg.ini (requires reboot afterwards!)')
                    backup.extract('moodecfg.ini', BackupManager.MOODECFGINI_RESTORE_PATH)
                    if 'script' in backup.namelist():
                        backup.extract('script', BackupManager.MOODECFGINI_RESTORE_PATH)

                except KeyError:
                    print("Backup doesn't contain moode configuration file.")
                if 'bgimage.jpg' in backup.namelist():
                    print('Restore bgimage.jpg')
                    backup.extract('bgimage.jpg', '/var/local/www/imagesw')
                    system('chmod a+r /var/local/www/imagesw/bgimage.jpg')

            # restore camilladsp configs
            if BackupManager.OPT_CDSP in what:
                names = [ name  for name in backup.namelist() if 'camilladsp/' in name]
                if len(names) >= 0:
                    print('Restore camilladsp config')
                    backup.extractall (BackupManager.CDSPCFG_RESTORE_BASE, names)

            if BackupManager.OPT_PL in what:
                #names = [ name  for name in backup.namelist() if 'playlists/' in name  and not 'Default Playlist.m3u' in name ]
                plNames = [ name  for name in backup.namelist() if 'playlists/' in name ]
                if len(plNames) >= 0:
                    print('Restore playlists')
                    backup.extractall (BackupManager.PLAYLIST_PATH, plNames)
                plCoverNames = [ name  for name in backup.namelist() if 'playlist-covers/' in name ]
                if len(plCoverNames) >= 0:
                    print('Restore playlist covers')
                    backup.extractall (BackupManager.PLAYLIST_COVERS_PATH, plCoverNames)

            if BackupManager.OPT_SEARCHES in what:
                searchNames = [name for name in backup.namelist() if 'www/' in name]
                if len(searchNames) >= 0:
                    print('Restore saved searches')
                    backup.extractall (BackupManager.SEARCHES_RESTORE_BASE, searchNames)

    def do_info(self):
        configPresent = False
        cdspPresent = False
        rs_moode_present = False
        rs_other_present = False
        playlistsPresent = False
        searchesPresent = False

        if os.path.exists(self.backup_file):
            # fake target db version
            self.db_ver = 7
            rsPresent = self.check_backup(0, False) == 0 # normal db or oldformat

            with ZipFile(self.backup_file, 'r') as backup:
                if rsPresent:
                    data = self.get_stations_from_backup(backup)
                    rs_moode_present = len(self.filter_stations(data['stations'], 'moode')) >= 1
                    rs_other_present = len(self.filter_stations(data['stations'], 'other')) >= 1
                try:
                    info=backup.getinfo('moodecfg.ini')
                    configPresent = True
                except KeyError:
                    pass

                names = [ name  for name in backup.namelist() if 'camilladsp/' in name]
                cdspPresent = len( names) >= 1

                names = [ name  for name in backup.namelist() if 'playlists/' in name]
                playlistsPresent = len( names) >= 1

                names = [ name  for name in backup.namelist() if 'www/' in name]
                searchesPresent = len( names) >= 1

        content = []
        if configPresent:
            print('config')
            content.append('config')
        if rs_moode_present:
            print('r_moode')
            content.append('r_moode')
        if rs_other_present:
            print('r_other')
            content.append('r_other')
        if cdspPresent:
            print('cdsp')
            content.append('cdsp')
        if playlistsPresent:
            print('playlists')
            content.append('playlists')
        if searchesPresent:
            print('searches')
            content.append('searches')

        return content


def get_cmdline_arguments():
    epilog = 'Root privileges required for restore'
    parser = argparse.ArgumentParser(description = 'Manages backup and restore of moOde system', epilog = epilog)
    parser.add_argument('backupfile',  default = None,
                   help = 'Filename of the backup')

    parser.add_argument('--version', action='version', version='%(prog)s {}'.format(BackupManager.VERSION))

    parser.add_argument('--what', dest = 'what', nargs="+",
                   choices = ['config', 'cdsp', 'playlists', 'searches', 'r_moode', 'r_other'], default = None ,
                   help = 'Indicate what to backup/restore (default for backup: config cdsp playlists, searches, r_other, default on restore: auto detect content)')

    group = parser.add_mutually_exclusive_group( required = True)
    group.add_argument('--backup', dest = 'do_backup', action = 'store_const',
                   const = sum,
                   help = 'Create backup')

    group.add_argument('--restore', dest = 'do_restore', action = 'store_const',
                   const = sum,
                   help = 'Restore backup')

    group.add_argument('--info', dest='do_info',  action = 'store_const',  const=sum,
                   help = "Show which were used to create the backup")


    parser.add_argument('--db', default = '/var/local/www/db/moode-sqlite3.db',
                   help = 'File name of the SQL database. (default: /var/local/www/db/moode-sqlite3.db')

    parser.add_argument('--script', dest = 'script', default = None,
                   help = 'Add script file to the backup (executed when restoring the backup)')

    parser.add_argument('--wlanpwd', dest = 'wlanpwd', default = None,
                   help = 'When creating a backup, supply a password for wifi access (applied when restoring the backup)')

    args = parser.parse_args()
    return args


if __name__ == "__main__":
    #logging.basicConfig(filename='/tmp/py.log', level=logging.DEBUG)
    #logging.debug('Start')

    args = get_cmdline_arguments()

    mgnr = BackupManager (args.db, args.backupfile)

    if args.do_restore:
        if os.geteuid() != 0:
            print("ERROR: Root privileges are required for restore, run with sudo.")
            exit(10)
    if args.backupfile == None and (args.do_backup or args.do_restore):
        print("ERROR: No backup file specified. Required for backup or restore.")
        exit(11)

    what =  ['config','cdsp', 'playlists', 'searches', 'r_other']
    if args.what:
        what = args.what

    if args.do_restore and args.what == None:
        print('Backup content:')
        what = mgnr.do_info()
        print()

    check_result = 0
    if args.do_restore or args.do_backup:
        check_radio_backup = ('r_other' in what or 'r_moode' in what ) and args.do_restore
        check_result = mgnr.check_env(check_radio_backup, None, True)

    if check_result == 0:
        if args.do_backup:
             mgnr.do_backup(what, args.script, args.wlanpwd)
        elif args.do_restore:
             mgnr.do_restore(what)
        elif args.do_info:
             mgnr.do_info()

    exit(check_result)
