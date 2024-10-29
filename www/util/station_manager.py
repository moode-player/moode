#!/usr/bin/python3
#
# SPDX-License-Identifier: GPL-3.0-or-later
# Copyright 2014 The moOde audio player project / Tim Curtis
# Copyright 2020 @bitlab (@bitkeeper Git)
#

# Script to for managing moOde radio stations
# Features:
# - Cmd help with --help
# - Backup
# - Import
# - Compare
# - Clear (all due not used command required backup as argument)
# - Support older version and newer version of moOde. Missing db fields are set to ''. Unsupported fields are ignored.
# - Can set scope for command to all, moode or other station
# - On import merge of clear first. With merge stations are matched on name, not content
# - If not present in backup it can regenerate thumbs
# - Exit code 0 when no problems occured
#

import argparse
import sqlite3
import json
import os
from os import path
from zipfile import ZipFile
import sys
import csv
import re
import hashlib

VERSION = "1.1"

class StationManager:

    IMPORT_CLEAR = 'clear'
    IMPORT_MERGE = 'merge'

    RADIO_LOGO_PATHS = ['/var/local/www/imagesw/radio-logos', # moode >= 6.6
                        '/var/www/images/radio-logos']  # moode < 6.6
    RADIO_PLS_PATH = '/var/lib/mpd/music/RADIO'
    RADIO_OTHER_IDS = 500 # other defined stations start at 500

    STATION_PLS_TEMPLATE = """[playlist]
File1={url}
Title1={name}
Length1=-1
NumberOfEntries=1
Version=2"""

    ARCHIVE_PATH_IMAGES = 'radio-logos'
    ARCHIVE_PATH_IMAGES_LEGACY = 'var/local/www/imagesw/radio-logos'

    def __init__(self, db_file, backup_file):
        # old backup archive format or the new one
        self.backup_is_legacy_format  = False

        # where to find the log in the filesystem
        self.radio_logos_path = None

        # where to find the images in the backup archive (send legacy detected path will be changed)
        self.archive_images_location = StationManager.ARCHIVE_PATH_IMAGES

        # used for the database connection
        self.conn = None

        # name of the database file to use
        self.db_file = db_file

        # name of the backup file to use
        self.backup_file = backup_file

        # used for detecting major scheme break corrections
        self.db_ver = None

    def check_env(self, check_backup = False, logopath = None, verbose = True) :
        return_code = 0
        self.radio_logos_path = None
        radio_logos_paths = StationManager.RADIO_LOGO_PATHS
        if logopath != None:  # Use custom location
            radio_logos_paths = [logopath]

        if os.path.exists(self.db_file):
            try:
                self.conn = sqlite3.connect(self.db_file)
                if verbose:
                    print('SQL database location is \'{}\''.format(self.db_file) )
            except Error as e:
                if verbose:
                    print('Error: Could not open SQL database at \'{}\''.format(self.db_file) )
                    print(e)
                return_code = 5
        else:
            if verbose:
                print('Error: SQL database not found at \'{}\''.format(self.db_file) )
            return_code = 4

        for radio_logo_path in radio_logos_paths:
            if os.path.isdir(radio_logo_path):
                self.radio_logos_path = radio_logo_path
                break

        if self.radio_logos_path != None:
            if verbose:
                print('Station logos location is \'{}\''.format(self.radio_logos_path ))
            if os.path.isdir(os.path.join(self.radio_logos_path, 'thumbs')) == False:
                if verbose:
                    print('Error: Could not find station logo thumbs at \'{}\''.format(os.path.join(self.radio_logos_path, 'thumbs')) )
                return_code = 2
        else:
            return_code = 1
            if verbose:
                print('Error: Could not find station logos, tried {}'.format(", ".join(radio_logos_paths) ))

        self.db_ver = 7 if 'geo_fenced' in self.get_fields() else 6

        if check_backup:
            return_code = self.check_backup(return_code)

        return return_code


    def check_backup(self, code =0, verbose = True ):
        return_code = code
        if os.path.exists(self.backup_file) == False:
            return_code = 6
            if verbose:
                print('Error: Station backup file \'{}\' not found.'.format(self.backup_file) )
        else:
            if verbose:
                print('Using Station backup file \'{}\'.'.format(self.backup_file) )

        with ZipFile(self.backup_file, 'r') as backup:
            try:
                info = backup.getinfo('station_data.json')
            except KeyError:
                try:
                    info = backup.getinfo('var/local/www/db/cfg_radio.csv')
                    if verbose:
                        print('Warning: Station backup is an old format')
                    self.backup_is_legacy_format = True
                    self.archive_images_location = StationManager.ARCHIVE_PATH_IMAGES_LEGACY
                except KeyError:
                    return_code = 12
                    if verbose:
                        print('Error: Station backup file \'{}\' is not a valid format'.format(self.backup_file))

        return return_code

    def get_scope_selector(self, scope):
        query_selector =''
        if scope == 'other':
            query_selector = ' and id>={}'.format(StationManager.RADIO_OTHER_IDS)
        elif scope == 'moode':
            query_selector = ' and id<{}'.format(StationManager.RADIO_OTHER_IDS)
        return query_selector

    def get_fields(self):
        """
            Get available field names of cfg_radio table
            returns string list with field names
        """
        cursor = self.conn.execute('select * from cfg_radio')
        colnames = [field[0] for field in cursor.description]

        return colnames

    def get_stations(self, scope, station_type = None):
        """
            Get all radio station data for the given scope
            returns list with a dictionary objects per radio-station. The keys are the database fields.
        """
        stations = []
        cursor = self.conn.execute('select * from cfg_radio where id!=499{} {}'.format(self.get_scope_selector(scope), ' and station!="OFFLINE" and station!="DELETED"') ) # ignore the separator between system and other stations
        colnames = [field[0] for field in cursor.description]

        for station_record in cursor.fetchall():
            station = {}
            for index, value in enumerate(station_record):
                station[colnames[index]] = value
            stations.append(station)

        if station_type != None:
            stations = self.filter_stations(stations, None, station_type) #already filtered on scope
        return stations

    def do_export(self, scope, station_type):
        with ZipFile(self.backup_file, 'w') as backup:
            self.export_data(backup, scope, station_type)
            self.export_images(backup, scope, station_type)

    def export_data(self, backup, scope, station_type):
        print('Export station data')
        colnames = self.get_fields()
        stations = self.get_stations(scope, station_type)
        for station in stations:
            if station['logo'] != 'local':
                station['logo'] = 'local'
        data = {'fields': colnames, 'stations': stations}
        backup.writestr('station_data.json', json.dumps(data,  indent=4))

    def export_images(self, backup, scope, station_type):
        print('Export station logos')

        stations = self.get_stations(scope, station_type)
        for station in stations:
            local_base_name = station['name']
            # In case of a non local there is a name mismatch between station name and logo.
            # Lets correct that.
            if station['logo'] != 'local':
                local_base_name = os.path.basename(station['logo'])[:-4]

            image_filename = path.join(self.radio_logos_path, local_base_name+'.jpg')
            image_filename_thumb = path.join(self.radio_logos_path, 'thumbs', local_base_name+'.jpg')
            image_filename_thumb_sm = path.join(self.radio_logos_path, 'thumbs', local_base_name+'_sm.jpg')

            if path.exists(image_filename):
                backup.write(image_filename, 'radio-logos/'+station['name']+'.jpg')
            else:
                print('Warning: Station logo not found for \' %s\'' %(image_filename) )

            if path.exists(image_filename_thumb):
                backup.write(image_filename_thumb, 'radio-logos/thumbs/'+station['name']+'.jpg')
                if path.exists(image_filename_thumb_sm):
                    backup.write(image_filename_thumb_sm, 'radio-logos/thumbs/'+station['name']+'_sm.jpg')
            else:
                print('Warning: Station logo thumb not found for \' %s\'' %(image_filename) )


    def filter_stations(self, stations, scope, station_type = None):

        def scope_fn(station):
            use = True
            if scope == 'other' and station['id'] < StationManager.RADIO_OTHER_IDS:
                use = False
            elif scope == 'moode' and station['id'] >= StationManager.RADIO_OTHER_IDS:
                use = False
            elif station_type == 'favorite' and 'f' not in station['type']:
                use = False
            elif station_type == 'regular' and 'r' not in station['type']:
                use = False
            elif station_type == 'hidden' and 'h' not in station['type']:
                use = False
            elif station_type == 'nothidden' and 'h' in station['type']:
                use = False
            return use

        return [station for station in stations if scope_fn(station) ]

    def correct_version_differences(self, data):
        '''
        Is used to :
        - remove flags in the 'type' field in cased target and source version differs.
        - if going to
        '''
        # use the presence of geo_fence to detect ver 7+
        target_ver7 = self.db_ver >= 7
        source_ver7 = 'geo_fenced' in data['fields']

        # if source and target are both 7 or both 6 no need to do something
        if target_ver7 != source_ver7:
            print('Warning: Source and target differ, correcting used types')
            for station in data['stations']:
                if target_ver7:
                    station['type'] = 'r'
                else:
                    station['type'] = 'u' if station['id'] >= 500 else 's'
                # If you run into problems with logo != local uncomment this:
                # if target_ver7:
                #     station['logo'] = 'local'
        return  data

    def get_data_legacy(self, backup):
        '''
            Import from the previous backup format.
            Contains among others:
            schema dumpe with the fields
            csv with the records
        '''
        data = {}

        fields  = []
        try:
            with backup.open('var/local/www/db/cfg_radio.schema', 'r') as schema:
                text = schema.readline().decode()
                text = text[text.find("(")+1:]
                fields_raw = text.split(',')
                for field_raw in fields_raw:
                    fields.append(field_raw.strip().split(' ')[0])
                data['fields'] = fields
        except KeyError:
            print("Warning: no schema information, guessing moOde 6.7.1 station backup format")
            fields = ['id',
                    'station',
                    'name',
                    'type',
                    'logo',
                    'genre',
                    'broadcaster',
                    'language',
                    'country',
                    'region',
                    'bitrate',
                    'format']
            data['fields'] = fields


        stations = []

        with backup.open('var/local/www/db/cfg_radio.csv', 'r') as csvf:
            for line_raw in csvf:
                line = line_raw.decode().strip()
                field_values = re.findall(r'(?:[^\s,"]|"(?:\\.|[^"])*")+', line)
                if len(field_values)!= len(fields):
                    print("Error: number of values ({}) doesn't match the schema({}).".format(len(field_values),len(fields)) )
                    exit(12)


                station = {}
                for field_values in csv.reader([line], skipinitialspace=True):
                    for idx, field_value in enumerate(field_values):
                        station[fields[idx]] = field_value if idx != 0 else int(field_value)

                if station['id']!=499 and station['station'] != 'OFFLINE' and station['station'] != 'DELETED':
                    stations.append(station)

        data['stations']  = stations

        return data

    def get_stations_from_backup(self, backup):
        data = {}
        if self.backup_is_legacy_format:
            data = self.get_data_legacy(backup)
        else:
            data = json.load(backup.open('station_data.json', 'r'))

        data = self.correct_version_differences(data)
        return data

    def do_import(self, scope, how):
        if os.path.isdir(StationManager.RADIO_PLS_PATH):
            # if verbose:
            # print('Station pls file location is \'{}\''.format(StationManager.RADIO_PLS_PATH) )
            pass
        else:
            return_code = 3
            print('Error: Could not find station pls files at \'{}\''.format(StationManager.RADIO_PLS_PATH) )

        print('import')
        with ZipFile(self.backup_file, 'r') as backup:
            data = self.get_stations_from_backup(backup)

            stations_bu = self.filter_stations(data['stations'], scope)
            stations_db = self.get_stations(scope)

            stations_db_delete = None
            if 'stations_deleted' in data:
                stations_db_delete = self.filter_stations(data['stations_deleted'], scope)
                self.clear_stations(stations_db_delete)

            stations_to_import = stations_bu
            difference = []
            fields_db = self.get_fields()
            if how == StationManager.IMPORT_CLEAR:
                self.clear_stations(scope)
            else:
                missing, added, difference, common = self.diff(backup, stations_db, stations_bu, fields_db)
                stations_to_import = [station for station in stations_bu if station['name'] in missing+difference]


            fields_db.remove('id') # use auto id for the id
            station_queries = []
            for station in stations_to_import:
                if station['name'] in difference:
                    print('Update {}'.format(station['name']))
                else:
                    print('Import {}'.format(station['name']))
                try:
                    self.generate_station_pls(station['name'], station['station'])
                    self.generate_station_logo(station['name'], backup)

                    if station['name'] in difference:
                        self.update_query_station_to_db(station, fields_db)
                    else:
                        self.create_query_station_to_db(station, fields_db)
                except Exception as e:
                     print("Error on import: {}".format(e))

            self.conn.commit()

            os.system('chmod 777 {}/*.pls'.format(StationManager.RADIO_PLS_PATH) )
            os.system('chmod 777 {}/*.jpg'.format(self.radio_logos_path) )
            os.system('chmod 777 {}/thumbs/*.jpg'.format(self.radio_logos_path) )

            print('Imported {} radio stations'.format(len(stations_to_import)))


    def create_query_station_to_db(self, station, fields_db):
        values = []
        for field in fields_db:
            if field in station.keys():
                values.append("'{}'".format( self.escape(station[field]) if station[field] != "NULL" else ""))
            else:
                # unavailable fields are given a value of ''
                values.append("''")

        if station['id'] <= 499:
            cursor = self.conn.cursor()
            res = cursor.execute("select id from cfg_radio where id<499 ORDER BY id DESC LIMIT 1;")
            id_new = res.fetchone()
            id_new = id_new[0] +1 if id_new else 1
            values.insert(0, '{}'.format(id_new))
            fields_db = ['id'] + fields_db

        query = 'INSERT INTO cfg_radio ({}) VALUES ({});'.format(', '.join(fields_db), ', '.join(values) )
        cursor = self.conn.cursor()
        cursor.execute(query)

    def update_query_station_to_db(self, station, fields_db):
        values = []
        for field in fields_db:
            if field in station.keys() and field !='type' and field !='monitor':
                values.append("{}='{}'".format( field, self.escape(station[field]) if station[field] != "NULL" else ""))

        query = 'UPDATE cfg_radio SET {} WHERE name="{}";'.format(', '.join(values), station['name'] )
        cursor = self.conn.cursor()
        cursor.execute(query)

    def generate_station_pls(self, station_name, station_url):
        with open(os.path.join(StationManager.RADIO_PLS_PATH, '{}.pls'.format(station_name)), 'w') as pls_file:
            pls_file.write(StationManager.STATION_PLS_TEMPLATE.format(name=station_name, url=station_url))

    def generate_station_logo(self, name, backup):
        try:
            with open(os.path.join(self.radio_logos_path, '{}.jpg'.format(name)), 'wb') as image_file:
                image_file.write(backup.read(os.path.join(self.archive_images_location, '{}.jpg'.format(name)) ) )

            try:
                with open(os.path.join(self.radio_logos_path, 'thumbs', '{}.jpg'.format(name)), 'wb') as image_file:
                    image_file.write( backup.read(os.path.join(self.archive_images_location, 'thumbs', '{}.jpg'.format(name)) ) )
            except KeyError:
                print("Warning: Missing station logo thumb for '{}', generating one".format(name))
                os.system('ffmpeg -v 20 -y -i "{}" -s 200x200 "{}"'.format(os.path.join(self.radio_logos_path, '{}.jpg'.format(name))
                                                                        ,os.path.join(self.radio_logos_path, 'thumbs', '{}.jpg'.format(name)) ) )

            try:
                with open(os.path.join(self.radio_logos_path, 'thumbs', '{}_sm.jpg'.format(name)), 'wb') as image_file:
                    image_file.write( backup.read(os.path.join(self.archive_images_location, 'thumbs', '{}_sm.jpg'.format(name)) ) )
            except KeyError:
                if self.db_ver >= 7:
                    print("Warning: Missing station logo thumb for '{}', generating one".format(name))
                    os.system('ffmpeg -v 20 -y -i "{}" -s 80x80 "{}"'.format(os.path.join(self.radio_logos_path, '{}.jpg'.format(name))
                                                                        ,os.path.join(self.radio_logos_path, 'thumbs', '{}_sm.jpg'.format(name)) ) )


        except KeyError as e:
            print("Error: Missing station logo '{}'".format(name))


    def clear_stations(self, scope):
        print('Clear stations for scope \'{}\''.format(scope if type(scope)!=list else ','.join([station['name'] for station in scope])))

        cursor = self.conn.cursor()

        if type(scope) == list:
            stations = scope
        else:
            stations = self.get_stations(scope)

        for station in stations:
            local_base_name = station['name']
            if station['logo'] != 'local':
                local_base_name = os.path.basename(station['logo'])[:-4]
            pls_file = os.path.join(StationManager.RADIO_PLS_PATH, '{}.pls'.format(local_base_name) )
            logo_file =os.path.join(self.radio_logos_path, '{}.jpg'.format( local_base_name))
            thumb_file = os.path.join(self.radio_logos_path, 'thumbs', '{}.jpg'.format(local_base_name))
            thumb_file_sm = os.path.join(self.radio_logos_path, 'thumbs', '{}_sm.jpg'.format(local_base_name))
            if os.path.exists(pls_file):
                os.remove(pls_file)
            if os.path.exists(logo_file):
                os.remove(logo_file)
            if os.path.exists(thumb_file):
                os.remove(thumb_file)
            if os.path.exists(thumb_file_sm):
                os.remove(thumb_file_sm)
            if type(scope) == list:
                query = 'delete from cfg_radio where name="{}"'.format(station['name'])
                cursor.execute(query)

        if type(scope) != list:
            query = 'delete from cfg_radio where id!=499{}'.format(self.get_scope_selector(scope))
            cursor.execute(query)
        self.conn.commit()

    def diff(self, backup, stations_db, stations_bu, fields):
        stations_db_map = [station['name'] for station in stations_db]
        stations_bu_map = [station['name'] for station in stations_bu]

        missing_stations_db = list(set(stations_bu_map) - set(stations_db_map))
        additional_stations_db = list(set(stations_db_map) - set(stations_bu_map))
        common_stations_ = list(set(stations_bu_map) & set(stations_db_map))

        stations_db_map = {station['name']: station for station in stations_db}
        stations_bu_map = {station['name']: station for station in stations_bu}
        common_stations= []
        stations_with_difference = []
        for station in common_stations_: #[:1]:
            differences = self.compare_station_settings(backup, stations_db_map[station], stations_bu_map[station], fields)
            if len(differences)>=1:
                stations_with_difference.append(station)
            else:
                common_stations.append(station)
        return (missing_stations_db, additional_stations_db, stations_with_difference, common_stations )

    def diff_logo(self, backup, station_db, station_bu):

            try:
                local_base_name = station_db['name']

                if station_db['logo'] != 'local':
                    local_base_name = os.path.basename(station_db['logo'])[:-4]

                image_filename = path.join(self.radio_logos_path, local_base_name+'.jpg')
                image_filename_thumb = path.join(self.radio_logos_path, 'thumbs', local_base_name+'.jpg')
                image_filename_thumb_sm = path.join(self.radio_logos_path, 'thumbs', local_base_name+'_sm.jpg')

                hash_file = hashlib.md5(open(image_filename,'rb').read()).hexdigest().strip()
                hash_backup = hashlib.md5(backup.read(os.path.join(self.archive_images_location, '{}.jpg'.format(local_base_name)))).hexdigest().strip()
                if hash_file != hash_backup:
                    return True

                hash_file = hashlib.md5(open(image_filename_thumb,'rb').read()).hexdigest().strip()
                hash_backup = hashlib.md5(backup.read(os.path.join(self.archive_images_location, 'thumbs','{}.jpg'.format(local_base_name)))).hexdigest().strip()
                if hash_file != hash_backup:
                    return True

                hash_file = hashlib.md5(open(image_filename_thumb_sm,'rb').read()).hexdigest().strip()
                hash_backup = hashlib.md5(backup.read(os.path.join(self.archive_images_location, 'thumbs','{}_sm.jpg'.format(local_base_name)))).hexdigest().strip()
                if hash_file != hash_backup:
                    return True
            except Exception as e:
                print(e)

            return False

    def do_diff(self, scope, diff_output=None):
        print('compare')
        colnames_db = self.get_fields()
        with ZipFile(self.backup_file, 'r') as backup:
            data = self.get_stations_from_backup(backup)
            colnames_bu = data['fields']

            # First check the data schema
            if colnames_db == colnames_bu:
                print('SQL database schema: ok (%d)' %(len(colnames_db)))
            else:
                missing_bu_fields = list(set(colnames_db) - set(colnames_bu))
                additional_bu_fields = list(set(colnames_bu) - set(colnames_db))
                print('SQL database schema: differs')
                if len(additional_bu_fields) >= 1:
                    print('\tStation backup is missing the following fields:')
                    for field in missing_bu_fields:
                        print('\t- {}'.format(field))

                if len(additional_bu_fields) >= 1:
                    print('\tStation backup had the following additional fields:')
                    for field in additional_bu_fields:
                        print('\t- {}'.format(field))

            # Check the radio stations
            stations_db = self.get_stations(scope)
            stations_bu = self.filter_stations(data['stations'], scope)

            stations_db_map = {station['name']: station for station in stations_db}
            stations_bu_map = {station['name']: station for station in stations_bu}

            common_fields = list(set(colnames_db) & set(colnames_bu))
            missing_stations_db, additional_stations_db, stations_with_difference, common_stations = self.diff(backup, stations_db, stations_bu, common_fields)

            if len( missing_stations_db) == 0 and len(additional_stations_db) == 0 and len(stations_with_difference) == 0:
                print('Stations: ok')
            else:
                print('Stations: differ')
                if len(missing_stations_db ) >= 1:
                    print('\tStations only in backup:')
                    for station in missing_stations_db:
                        print('\t- \'{}\''.format(station))

                if len(additional_stations_db) >= 1:
                    print('\tStations only in SQL table:')
                    for station in additional_stations_db :
                        print('\t- \'{}\''.format(station))

                if len(stations_with_difference) >=1:
                    print('\tStations with difference in settings:')
                    for name in stations_with_difference:
                        differences = self.compare_station_settings(backup, stations_db_map[name], stations_bu_map[name], common_fields)
                        print('\t- \'{}\' : {}'.format(name, ", ".join(differences)))

            #TODO: Not coded very nice, but was in hurry. Refactor and remove duplicates existing code.
            if diff_output:
                with ZipFile(diff_output, 'w') as diff_backup:
                    print('Export data')
                    colnames = self.get_fields()
                    stations_deleted = []
                    for name in missing_stations_db:
                        stations_deleted.append(stations_bu_map[name])

                    stations = []
                    for name in stations_with_difference + additional_stations_db:
                        stations.append(stations_db_map[name])
                    data = {'fields': colnames, 'stations': stations, 'stations_deleted': stations_deleted}
                    diff_backup.writestr('station_data.json', json.dumps(data,  indent=4))

                    for station in stations:
                        local_base_name = station['name']
                        # In case of a non local there is a name mismatch between station name and logo.
                        # Lets correct that.
                        if station['logo'] != 'local':
                            local_base_name = os.path.basename(station['logo'])[:-4]

                        image_filename = path.join(self.radio_logos_path, local_base_name+'.jpg')
                        image_filename_thumb = path.join(self.radio_logos_path, 'thumbs', local_base_name+'.jpg')
                        image_filename_thumb_sm = path.join(self.radio_logos_path, 'thumbs', local_base_name+'_sm.jpg')

                        if path.exists(image_filename):
                            diff_backup.write(image_filename, 'radio-logos/'+station['name']+'.jpg')
                        else:
                            print('Warning: Station logo not found for \' %s\'' %(image_filename) )

                        if path.exists(image_filename_thumb):
                            diff_backup.write(image_filename_thumb, 'radio-logos/thumbs/'+station['name']+'.jpg')
                            if path.exists(image_filename_thumb_sm):
                                diff_backup.write(image_filename_thumb_sm, 'radio-logos/thumbs/'+station['name']+'_sm.jpg')

    def compare_station_settings(self, backup, data_db, data_bu, fields):
        differences = self._compare_station_settings(data_db, data_bu, fields)
        if self.diff_logo(backup, data_db,  data_bu):
            differences.append('logo_hash')

        return differences
    def _compare_station_settings(self, data_db, data_bu, fields):
        differs = False
        settings_differs = []
        for field in fields:
            #TODO: should be handle the content of type better ?
            if field not in ['id', 'type', 'monitor']:
                if data_db[field] != data_bu[field]:
                    settings_differs.append(field)
        return settings_differs

    def regenerate_pls(self):
        # stations = self.get_stations(scope, station_type)
        stations = self.get_stations(scope='all', station_type=None)
        for station in stations:
            self.generate_station_pls(station['name'], station['station'])
        print('(Re)generated %d stations pls files' %len(stations))

    def escape(self, value):
        escape_chars = {"'": "''", "\"": "\\\""}
        for ch in escape_chars.keys():
            if ch in value:
                value = value.replace(ch, escape_chars[ch])
        return value




def get_cmdline_arguments():
    epilog = 'Root privileges required for import or clear.'
    parser = argparse.ArgumentParser(description = 'Manages import and export of moOde radiostations.', epilog = epilog)
    parser.add_argument('backupfile',  nargs = '?', default = None,
                   help = 'Filename of the station backup. Required by the import, export and compare.')

    parser.add_argument('--version', action='version', version='%(prog)s {}'.format(VERSION))
    group = parser.add_mutually_exclusive_group( required = True)
    group.add_argument('--import', dest = 'do_import', action = 'store_const',
                   const = sum,
                   help = 'Import radio stations from backup.')

    group.add_argument('--export', dest = 'do_export', action = 'store_const',
                   const = sum,
                   help = 'Export radio stations to backup.')

    group.add_argument('--clear', dest = 'do_clear', action = 'store_const',
                   const = sum,
                   help = 'Clear radio stations. This will delete the contents of the SQL table, logo images and pls files of the selected stations within the specified scope.')

    group.add_argument('--compare', dest = 'do_diff', action = 'store_const',
                   const = sum,
                   help = 'Show difference between SQL table and station backup.')

    group.add_argument('--diff', #dest = 'do_patch', action = 'store_const',
                   # const = sum,
                   help = 'Create a diff backup with the difference between the old (=backup) and new (=db) to this files.')

    group.add_argument('--regeneratepls', dest = 'do_genpls', action = 'store_const',
                   const = sum,
                   help = 'Regenerate the radio .pls files from db.')

    parser.add_argument('--scope', dest = 'scope',
                   choices = ['all', 'moode', 'other'], default = 'other',
                   help = 'Indicate to which stations the specified action applies. (default: other)')

    parser.add_argument('--type', dest = 'station_type',
                   choices = ['favorite', 'regular', 'hidden', 'nothidden'], default = None,
                   help = 'Indicate the type of station to export.')

    parser.add_argument('--how', dest = 'how',
                   choices = ['clear', 'merge'], default = 'merge',
                   help = 'On import, clear stations before action or merge and add. (default: merge)')

    parser.add_argument('--db', default = '/var/local/www/db/moode-sqlite3.db',
                   help = 'File name of the SQL database. (default: /var/local/www/db/moode-sqlite3.db')

    parser.add_argument('--logopath', default = None,
                   help = 'Location of the radio logos. (default: /var/local/www/imagesw/radio-logo')

    args = parser.parse_args()
    return args


if __name__ == "__main__":
    args = get_cmdline_arguments()

    mgnr = StationManager (args.db, args.backupfile)

    if args.do_import or args.do_clear:
        if os.geteuid() != 0:
            print("Error: Root privileges are required for import or clear, run with sudo.")
            exit(10)
    if args.backupfile == None and (args.do_import or args.do_export or args.do_diff):
        print("Error: No station backup file provided. Required for import, export and compare.")
        exit(11)


    check_result = mgnr.check_env(args.do_import or args.do_diff, args.logopath)

    if check_result == 0:
        if args.do_import:
             mgnr.do_import(args.scope, args.how)
        elif args.do_export:
            mgnr.do_export(args.scope, args.station_type)
        elif args.do_diff:
            mgnr.do_diff(args.scope)
        elif args.diff:
            mgnr.do_diff(args.scope, args.diff)
        elif args.do_clear:
            mgnr.clear_stations(args.scope)
        elif args.do_genpls:
            mgnr.regenerate_pls()


    exit(check_result)
