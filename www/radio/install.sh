# Create the organization in RADIO
sudo mkdir -p /var/lib/mpd/music/RADIO/_Stations/tags
sudo mkdir -p /var/lib/mpd/music/RADIO/_Stations/networks

# Copy scripts to localhost
sudo mkdir -p /var/www/radio/sources/rb
sudo mkdir -p /var/www/radio/sources/moode/scripts
sudo mkdir -p /var/www/radio/sources/moode/user/radio-images/thumbs

# Permissions
sudo chmod 777 /var/lib/mpd/playlists/Radio_Play.m3u
sudo chmod 777 /var/www/radio/index.php
sudo chmod 777 /var/www/radio/sources/moode
sudo chmod 777 /var/www/radio/sources/moode/scripts
sudo chmod 777 /var/www/radio/sources/moode/user
sudo chmod 777 /var/www/radio/sources/moode/user/radio-logos
sudo chmod 777 /var/www/radio/sources/moode/user/radio-logos/thumbs
sudo chmod 777 /var/www/radio/sources/config.json
sudo chmod 777 /var/www/radio/sources/rb/tags.json
sudo chmod 755 /var/www/radio/sources/rb/*.py
sudo chmod 755 /var/www/radio/sources/moode/scripts/*.py
sudo chmod 755 /var/www/rdo-config.php
sudo chmod 755 /var/www/rdo-config-rb.php
sudo chmod 755 /var/www/templates/rdo-config.html
sudo chmod 755 /var/www/templates/rdo-config-rb.html