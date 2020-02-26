import urllib, random, requests, json, os


cmd_path        = "/var/www/radio"

pi_path         = "/var/lib/mpd/music/RADIO/_Stations/smart"
web_path        = cmd_path + "/sources/rb"
cfg_file        = cmd_path + "/sources/config.json"
api_path        = ["https://de1.api.radio-browser.info",
                   "https://de2.api.radio-browser.info",
                   "https://fr1.api.radio-browser.info",
                   "https://nl1.api.radio-browser.info"]


f_server        = random.choice(api_path)
f_response      = urllib.urlopen("{0}/json/tags".format(f_server)).read()
f_json          = json.loads(f_response)






# CACHE TAG FILE FOR LOCAL UI RENDER
c_filename = web_path + "/tags.json"

os.system("sudo touch " + c_filename)
os.system("sudo chmod 777 " + c_filename)
with open(c_filename, "w") as c_file:
    c_file.write(f_response)