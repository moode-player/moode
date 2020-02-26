#!/usr/bin/env python2
import urllib, random, requests, json, os, sys


cmd_path        = "/var/www/radio"

pi_path         = "/var/lib/mpd/playlists"
api_path        = ["https://de1.api.radio-browser.info",
                   "https://de2.api.radio-browser.info",
                   "https://fr1.api.radio-browser.info",
                   "https://nl1.api.radio-browser.info"]


f_server        = random.choice(api_path)
p_path          = pi_path    + "/Radio_Play.m3u"
f_tag           = sys.argv[1]

os.system("sudo rm -rf " + p_path)

p_url   = "{0}/m3u/stations/bytag/{1}".format(f_server, f_tag)  
p_file  = requests.get(p_url)



os.system("sudo touch " + p_path)
os.system("sudo chmod 777 " + p_path)
open(p_path, 'wb').write(p_file.content)

os.system("sudo sed -i 's/EXTINF:1/EXTINF:-1/g' " + p_path)             
os.system("mpc update")
os.system("mpc clear")
os.system("mpc load Radio_Play")
os.system("mpc play")