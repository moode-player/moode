#!/usr/bin/python3
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
# - contains moodecfg.ini, radiostations and camilladsp
#
import argparse

from os import system, path, walk
import os
from zipfile import ZipFile

from stationmanager import StationManager


class BackupManager(StationManager):
    VERSION = "1.0"

    CDSPCFG_BASE = '/usr/share/camilladsp/'
    MOODECFGINI_TMP = '/tmp/moodecfg.ini'

    OPT_CFG = 'config'
    OPT_CDSP = 'cdsp'
    OPT_RS_SYS ='r_sys'
    OPT_RS_OTHER = 'r_other'

    # for real
    MOODECFGINI_RESTORE_PATH = '/boot'
    CDSPCFG_RESTORE_BASE = '/usr/share'

    # for test
    # MOODECFGINI_RESTORE_PATH = '/tmp'
    # CDSPCFG_RESTORE_BASE = '/tmp'

    def __init__(self, db_file, backup_file):
         super().__init__(db_file, backup_file)

    def do_backup(self, what, script_file, wlanpwd):

        scope = None
        if BackupManager.OPT_RS_OTHER in what and BackupManager.OPT_RS_SYS in what:
            scope = 'all'
        elif BackupManager.OPT_RS_OTHER in what:
            scope = 'other'
        elif BackupManager.OPT_RS_SYS in what:
            scope = 'moode'

        zipmode = 'w'
        if scope:
            # the stationmanager already creates a zipfile, so make the zipmode append
            self.do_export(scope, None)
            zipmode = 'a'

        if BackupManager.OPT_CFG in what:
            system('moodeutl -e ' + BackupManager.MOODECFGINI_TMP )
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
                if script_file and os.path.exists(script_file):
                    print('add script to backup')
                    backup.write(script_file, 'script')
                if os.path.exists('/var/local/www/imagesw/bgimage.jpg'):
                    backup.write('/var/local/www/imagesw/bgimage.jpg', 'bgimage.jpg')

            # backup camilladsp configurations
            if BackupManager.OPT_CDSP in what:
                if path.exists(BackupManager.CDSPCFG_BASE ):
                    if path.exists(path.join(BackupManager.CDSPCFG_BASE, 'configs')):
                        print('backup camilladsp configs')
                        for fpath, subdirs, files in walk(path.join(BackupManager.CDSPCFG_BASE, 'configs')):
                            for name in files:
                                backup.write(path.join(fpath, name), path.join('camilladsp', 'configs', name))
                    if path.exists(path.join(BackupManager.CDSPCFG_BASE, 'coeffs')):
                        print('backup camilladsp coeffs')
                        for fpath, subdirs, files in walk(path.join(BackupManager.CDSPCFG_BASE, 'coeffs')):
                            for name in files:
                                backup.write(path.join(fpath, name), path.join('camilladsp', 'coeffs', name))

    def do_restore(self, what):
        # restore radio stations
        scope = None
        if BackupManager.OPT_RS_OTHER in what and BackupManager.OPT_RS_SYS in what:
            scope = 'all'
        elif BackupManager.OPT_RS_OTHER in what:
            scope = 'other'
        elif BackupManager.OPT_RS_SYS in what:
            scope = 'moode'

        if scope:
            self.do_import(scope, 'clear')

        with ZipFile(self.backup_file, 'r') as backup:
            # restore moodecfg.ini
            if BackupManager.OPT_CFG in what:
                try:
                    print('restore moodecfg.ini (requires reboot afterwards!)')
                    backup.extract('moodecfg.ini', BackupManager.MOODECFGINI_RESTORE_PATH)
                except KeyError:
                    print("backup doesn't contain moode configuration file.")
                if 'bgimage.jpg' in backup.namelist():
                    backup.extract('bgimage.jpg', '/var/local/www/imagesw/bgimage.jpg');

            # restore camilladsp configs
            if BackupManager.OPT_CDSP in what:
                names = [ name  for name in backup.namelist() if 'camilladsp/' in name]
                if len(names) >= 0:
                    print('restore camilladsp config')
                    backup.extractall (BackupManager.CDSPCFG_RESTORE_BASE, names)

    def do_info(self):
        configPresent = False
        cdspPresent = False
        rs_sys_present = False
        rs_other_present = False


        if os.path.exists(self.backup_file):
            # fake target db version
            self.db_ver = 7
            rsPresent = self.check_backup(0, False) == 0 # normal db or oldformat

            with ZipFile(self.backup_file, 'r') as backup:
                if rsPresent:
                    data = self.get_stations_from_backup(backup)
                    rs_sys_present = len(self.filter_stations(data['stations'], 'moode')) >= 1
                    rs_other_present = len(self.filter_stations(data['stations'], 'other')) >= 1
                try:
                    info=backup.getinfo('moodecfg.ini')
                    configPresent = True
                except KeyError:
                    pass

                names = [ name  for name in backup.namelist() if 'camilladsp/' in name]
                cdspPresent = len( names) >= 1


        content = []
        if configPresent:
            print('config')
            content.append('config')
        if rs_sys_present:
            print('r_sys')
            content.append('r_sys')
        if rs_other_present:
            print('r_other')
            content.append('r_other')
        if cdspPresent:
            print('cdsp')
            content.append('cdsp')

        return content


def get_cmdline_arguments():
    epilog = 'Root privileges required for restore.'
    parser = argparse.ArgumentParser(description = 'Manages backup and restore of moOde configuration.', epilog = epilog)
    parser.add_argument('backupfile',  default = None,
                   help = 'Filename of the moode backup.')

    parser.add_argument('--version', action='version', version='%(prog)s {}'.format(BackupManager.VERSION))

    parser.add_argument('--what', dest = 'what', nargs="+",
                   choices = ['config', 'cdsp', 'r_sys', 'r_other'], default = None ,
                   help = 'Indicate what to backup/restore. (default for backup: config cdsp r_other, default on restore: auto detect content)')

    group = parser.add_mutually_exclusive_group( required = True)
    group.add_argument('--backup', dest = 'do_backup', action = 'store_const',
                   const = sum,
                   help = 'Create backup.')

    group.add_argument('--restore', dest = 'do_restore', action = 'store_const',
                   const = sum,
                   help = 'Restore backup.')

    group.add_argument('--info', dest='do_info',  action = 'store_const',  const=sum,
                   help = "show information what is included in the backup (detect available 'what')")


    parser.add_argument('--db', default = '/var/local/www/db/moode-sqlite3.db',
                   help = 'File name of the SQL database. (default: /var/local/www/db/moode-sqlite3.db')

    parser.add_argument('--script', dest = 'script', default = None,
                   help = 'Add script file to the backup (is executed when config is restored)')

    parser.add_argument('--wlanpwd', dest = 'wlanpwd', default = None,
                   help = 'When creating a backup, supply a password for wifi access (applied when restoring the backup)')

    args = parser.parse_args()
    return args


if __name__ == "__main__":
    args = get_cmdline_arguments()

    mgnr = BackupManager (args.db, args.backupfile)

    if args.do_restore:
        if os.geteuid() != 0:
            print("ERROR: Root privileges are required for a backup restore, run with sudo.")
            exit(10)
    if args.backupfile == None and (args.do_backup or args.do_restore):
        print("ERROR: No moOde backup file provided. Required for backup or restore.")
        exit(11)

    what =  ['config','cdsp', 'r_other']
    if args.what:
        what = arg.what

    if args.do_restore and args.what == None:
        print('backup content:')
        what = mgnr.do_info()
        print()

    check_result = 0
    if args.do_restore or args.do_backup:
        check_result = mgnr.check_env(args.do_restore, True)

    if check_result == 0:
        if args.do_backup:
             mgnr.do_backup(what, args.script, args.wlanpwd)
        elif args.do_restore:
             mgnr.do_restore(what)
        elif args.do_info:
             mgnr.do_info()

    exit(check_result)
