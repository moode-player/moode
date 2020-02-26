#!/usr/bin/env python2
import json, os, zipfile

import sqlite3
from sqlite3 import Error




cmd_path        = "/var/www/radio"
img_path        = "/var/www/images/radio-logos"
rdo_path        = "/var/lib/mpd/music/RADIO"
web_path        = cmd_path + "/sources/moode/user"
web_local       = "/var/local/www"
exp_path        = "/var/lib/mpd/music/SDCARD"
db_file         = r"/var/local/www/db/moode-sqlite3.db"
s_sqlfile       = web_path + "/import.sql"
s_jsonfile      = web_path + "/import.json"




def create_connection(db_file):
    conn = None
    try:
        conn = sqlite3.connect(db_file)
    except Error as e:
        print(e)

    return conn


def create_station(conn, station):
    sql = ''' INSERT INTO cfg_radio(station,name,type,logo)
              VALUES(?,?,?,?) '''
    cur = conn.cursor()
    cur.execute(sql, station)
    conn.commit()
    return cur.lastrowid


def update_station(conn, station):
    sql = ''' UPDATE cfg_radio SET station = ? WHERE name = ? AND type = ?'''
    cur = conn.cursor()
    cur.execute(sql, station)
    conn.commit()
    return cur.lastrowid

def update_name(conn, station_name):
    sql = ''' UPDATE cfg_radio SET name = ? WHERE station = ? AND type = ?'''
    cur = conn.cursor()
    cur.execute(sql, station_name)
    conn.commit()
    return cur.lastrowid
    

def import_stations(conn):
    with open(s_jsonfile, "r") as json_file:
        data = json.load(json_file)
        for s in data['userstations']:
            cur     = conn.cursor()
            cur.execute('SELECT id FROM cfg_radio WHERE name="'+s["name"]+'" AND type="u"')
            rows    = cur.fetchall()
            
            if(len(rows) > 0):
                # UPDATE
                station         = (''+s["station"]+'', ''+s["name"]+'', 'u')
                station_name    = (''+s["name"]+'', ''+s["station"]+'', 'u')
                update_station(conn, station)
                update_name(conn, station)
                
                print('Station '+s["name"]+' updated')
                
                
            else:
                # INSERT
                station = (''+s["station"]+'', ''+s["name"]+'', 'u', 'local')
                station_id = create_station(conn, station)
                if station_id:
                    print('Station '+s["name"]+' imported')
                    
    return

            

# uncompress stations.zip
with zipfile.ZipFile(exp_path + "/stations.zip", "r") as zip_ref:
    zip_ref.extractall("/")

# place logos
os.system("sudo cp -rf " + web_path + "/radio-logos/*.jpg " + img_path)
os.system("sudo cp -rf " + web_path + "/radio-logos/thumbs/*.jpg " + img_path + "/thumbs")

# place playlist files
os.system("sudo cp -rf " + web_path + "/*.pls " + rdo_path)

    
    
conn = create_connection(db_file)   
import_stations(conn)
conn.close()
        
os.system("mpc update")