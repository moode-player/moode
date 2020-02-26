#!/usr/bin/env python2
import urllib, random, requests, json, os, re, string, unicodedata, sys

reload(sys)
sys.setdefaultencoding('utf-8')

def is_ascii(text):
    if isinstance(text, unicode):
        try:
            text.encode('ascii')
        except UnicodeEncodeError:
            return False
    else:
        try:
            text.decode('ascii')
        except UnicodeDecodeError:
            return False
    return True



valid_filename_chars = "-_.() %s%s" % (string.ascii_letters, string.digits)
char_limit = 255

def clean_filename(filename, whitelist=valid_filename_chars):
    
    filename = unicode(filename)
    filename = filename.lower().replace("&", "and").replace(" ", "-").replace("'", "-").replace("\"", "").replace("(", "").replace(")", "").replace(".", "_")

    cleaned_filename = unicodedata.normalize('NFKD', filename).encode('ASCII', 'ignore').decode()
    cleaned_filename = ''.join(c for c in cleaned_filename if c in whitelist)
    if len(cleaned_filename)>char_limit:
        print("Warning, filename truncated because it was over {}. Filenames may no longer be unique".format(char_limit))
    
    
    return cleaned_filename[:char_limit]



cmd_path        = "/var/www/radio"

pi_path         = "/var/lib/mpd/music/RADIO/_Stations"
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
if not os.path.exists(c_filename):
    os.system("sudo touch " + c_filename)
    os.system("sudo chmod 777 " + c_filename)
    with open(c_filename, "w") as c_file:
        c_file.write(f_response)
    
else:    
    with open(c_filename, "w") as c_file:
        c_file.write(f_response)
    


os.system("sudo mkdir -p "  + pi_path + "/networks")
os.system("sudo mkdir -p "  + pi_path + "/tags")


with open(cfg_file) as json_file:
    config = json.load(json_file)
    for p in config['radiobrowser']:   
        p_range     = p['range']
        p_tags      = p['tags'].lower().replace("&", "and").replace(" ", "-").replace("'", "-")
        p_stations  = p['stations'].lower()
        p_singles   = p['singles'].lower()


r_range     = p_range.split("-")        
r_tags      = p_tags.split(",")
r_stations  = p_stations.split(",")
f_path      = pi_path   + "/tags/"
s_path      = pi_path   + "/networks/"


# REMOVE STATIONS AND TAGS TO REGEN
os.system("sudo rm -rf "    + f_path)
os.system("sudo rm -rf "    + s_path)

os.system("sudo mkdir -p "  + f_path)
os.system("sudo mkdir -p "  + s_path)

# DO TAGS
for f, f_item in enumerate(f_json): 
    
    f_tag   = f_item['name']
    f_cnt   = f_item['stationcount']
    f_range = range(int(r_range[0]),int(r_range[1]))
    
    
    if f_tag in r_tags:
            
        if f_cnt in f_range and is_ascii(f_tag):
            print("\nTag: {0} ({1}) ...\n".format(f_tag, str(f_cnt))) 

            f_tag_clean = f_tag.lower().replace("&", "and").replace(" ", "-").replace("'", "-")
            f_folder    = f_tag_clean
            
            if p_singles == "0":
                f_path      = pi_path   + "/tags/"
                p_path      = f_path    + f_tag_clean+".m3u"
            
            if p_singles == "1":
                f_path      = pi_path   + "/tags/"+ f_folder
                p_path      = f_path    + "/"+f_tag_clean+".m3u"
                os.system("sudo mkdir -p "  + f_path + "/singles")
                
            
            
            
            
            os.system("sudo chmod 777 " + f_path)
            os.system("sudo rm -rf "    + p_path)

            p_url   = "{0}/m3u/stations/bytag/{1}".format(f_server, f_tag)  
            p_file  = requests.get(p_url)



            os.system("sudo touch " + p_path)
            os.system("sudo chmod 777 " + p_path)
            open(p_path, 'wb').write(p_file.content)
        
            os.system("sudo sed -i 's/EXTINF:1/EXTINF:-1/g' " + p_path)
            
            
            # SCOOP OUT STATION NAMES FOR CONSOLE PRINT
            p_names = re.sub(r'^https?:\/\/.*[\r\n]*', '', p_file.content, flags=re.MULTILINE)
            p_names = p_names.split("#EXTINF:1,")
            p_names.pop(0)
            
            for s_item in p_names:
                print("["+ f_tag.upper() + "] " + s_item.replace('\n', ' ').replace('\r', ''))
                
                
                
                
            
            if(p_singles == "1"):
                # SPLIT EACH URL INTO SEPERATE FILE
                q_file  = open(p_path, 'r')
                q_lines = q_file.readlines()
                q_size  = len(q_lines)
                q_file.close()

                print("\n\nSplitting stations, please wait")
                
                for i in range(1,q_size,3):
                    station_split       = q_lines[i].split("#EXTINF:-1,")
                    station_name        = str(station_split[1])
                    station_url         = str(q_lines[i+1])
                    station_file        = clean_filename(station_name) + ".m3u"
                    station_path        = f_path + "/singles/" + station_file


                    station_content     = "#EXTM3U\n" + q_lines[i] + station_url
                    
                    sys.stdout.write('.')

                    os.system("sudo touch " + station_path)
                    os.system("sudo chmod 777 " + station_path)
                    open(station_path, 'wb').write(station_content)
                
                    
            if(p_singles == "0" and os.path.isdir(pi_path + "/tags/singles")):
                # DELETE ALL SPLIT STATION PLAYLISTS
                os.system("sudo find " + pi_path + "/tags -name 'singles' -exec rm -rf {} \;")
        
        
        
        
        
        
# DO NETWORK NAME LOOKUP - IGNORE STATION RANGE AND STATION SPLITTER
# EXAMPLE - station:bbc,tag:dashradio
for n_item in r_stations:
    n_split = n_item.split(":")
    
    if is_ascii(n_split[1]):
        
        # DO THE API STUFF ...
        if "tag" in n_split[0]:
            # DO KEYWORD LOOKUP ON TAGS
            n_url   = "{0}/json/stations/bytag/{1}".format(f_server, n_split[1])
            n_json  = json.loads(urllib.urlopen(n_url).read())
            n_url   = "{0}/m3u/stations/bytag/{1}".format(f_server, n_split[1])

        else:
            # DO KEYWORD LOOKUP ON STATIONS
            n_url   = "{0}/json/stations/byname/{1}".format(f_server, n_split[1])
            n_json  = json.loads(urllib.urlopen(n_url).read())
            n_url   = "{0}/m3u/stations/byname/{1}".format(f_server, n_split[1])




        # DO THE FILE STUFF ...
        if "url" in str(n_json):
            n_file      = requests.get(n_url)     
            f_path      = pi_path   + "/networks/"
            p_path      = f_path    + n_split[1].lower().replace("&", "and").replace(" ", "-").replace("'", "-") +".m3u"

            print("\n\n\nNetwork: {0} ... \n".format(n_split[1].upper()))
            
            os.system("sudo touch " + p_path)
            os.system("sudo chmod 777 " + p_path)
            open(p_path, 'wb').write(n_file.content)
            os.system("sudo sed -i 's/EXTINF:1/EXTINF:-1/g' " + p_path)
            
            
            
            # SCOOP OUT STATION NAMES FOR CONSOLE PRINT
            n_names = re.sub(r'^https?:\/\/.*[\r\n]*', '', n_file.content, flags=re.MULTILINE)
            n_names = n_names.split("#EXTINF:1,")
            n_names.pop(0)
            
            for s_item in n_names:
                print("["+ n_split[1].upper() + "] " + s_item.replace('\n', ' ').replace('\r', ''))
        
        
    
        
        
        
        
        
        
        
        
        
        
os.system("mpc update")