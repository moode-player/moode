#!/usr/bin/env python2
import os, zipfile

import sqlite3
from sqlite3 import Error




cmd_path        = "/var/www/radio"
img_path        = "/var/www/images/radio-logos"
rdo_path        = "/var/lib/mpd/music/RADIO"
web_path        = cmd_path + "/sources/moode/user"
tmp_path        = cmd_path + "/sources/moode"
web_local       = "/var/local/www"
exp_path        = "/var/lib/mpd/music/SDCARD"
db_file         = web_local + "/db/moode-sqlite3.db"
s_sqlfile       = web_path + "/import.sql"
s_jsonfile      = web_path + "/import.json"


def zipdir(path, ziph):
    # ziph is zipfile handle
    for root, dirs, files in os.walk(path):
        for file in files:
            ziph.write(os.path.join(root, file))

    

def create_connection(db_file):
    conn = None
    try:
        conn = sqlite3.connect(db_file)
    except Error as e:
        print(e)

    return conn



def export_stations(conn):
    sql     = 'SELECT * FROM cfg_radio WHERE type="u"'
    s_sql   = ""
    s_json  = '{"userstations":['
    row     = ""
    c       = 0
    cur     = conn.cursor()
    cur.execute(sql)
    
    rows    = cur.fetchall()
    for row in rows:
        s_url   = row[1]
        s_name  = row[2]
        s_pls   = rdo_path + "/'" + s_name + ".pls'" 
        s_jpl   = img_path + "/'" + s_name + ".jpg'"
        s_jps   = img_path + "/thumbs/" + "'" + s_name + ".jpg'"
        s_sql   = s_sql     + "INSERT INTO cfg_radio (station,name,type,logo) VALUES ('"+s_url+"','"+s_name+"','u','local')\n" 
        s_json  = s_json    + '{"type": "u","station": "'+s_url+'","logo": "local","name": "'+s_name+'"}'
        c       = c +1
        if c < len(rows):
            s_json  = s_json    + ','
        
        
        
        os.system("sudo cp " + s_pls + " " + web_path)
        os.system("sudo cp " + s_jpl + " " + web_path + "/radio-logos")
        os.system("sudo cp " + s_jps + " " + web_path + "/radio-logos/thumbs")
    
    s_json   = s_json + "]}"
    
    
    with open(s_sqlfile, "w") as s_file:
        s_file.write(s_sql)
    
    with open(s_jsonfile, "w") as s_filej:
        s_filej.write(s_json)
    
    # compress to export zip
    zipf = zipfile.ZipFile(exp_path + '/stations.zip', 'w', zipfile.ZIP_DEFLATED)
    zipdir(web_path, zipf)
    zipf.close()
    
    os.system("sudo cp " + exp_path + "/stations.zip " + tmp_path + "/stations.zip")
    
    return

            

    
conn = create_connection(db_file)   
export_stations(conn)    
        
#os.system("mpc update")