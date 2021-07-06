Setup Guide for moOde audio player
==================================
These instructions are designed for initial configuration
of moOde audio player. Additional configuration help is
provided within the player via the (i) information buttons.

### Updated: 2020-11-15
(c) Tim Curtis 2017 http://moodeaudio.org



## General Reference

**Note**: Use <http://moode>, <http://moode.local> or IP address, whichever works on your network.
Typically Android OS will only work with IP address.

- SSH login user: pi, pwd: moodeaudio
- Preface commands requiring root permission with sudo
- Access Point (AP) mode:
    1. IP address: 172.24.1.1
    2. SSID: Moode
    3. Password: moodeaudio
    4. Channel: 6
- AP mode starts automatically when:
    1. WiFi SSID is set to 'None' in Network config and there is no Ethernet connection
    2. WiFi SSID is defined but no IP address was assigned by DHCP. This typically occurs when connection to the SSID fails.
- Default Samba shares are NAS, Playlists, Radio, and SDCard
- Each USB disk will also have a Samba share named after its disk label


### WiFi Adapters That Are Reported To Support AP Mode

- Raspberry Pi 3, 4 and Zero W integrated WiFi adapters
- Comfast CF-912AC dual-band WiFi adapter
- For all other adapters consult the manufacturer


### In-Place Software Updates

- Updates to moOde software are made available periodically and can be downloaded and installed from by clicking "CHECK for software update" in System Config
- Click `VIEW` to see a list of what is contained in the update package
- Click `INSTALL` to download and install the update package


### Image Writing Utilities
https://www.raspberrypi.org/documentation/installation/installing-images



Player Setup and Configuraton
=============================

1. __Initial Setup__
    1. Insert boot SD card or USB drive
    2. Connect USB or I2S audio device
    3. Connect USB storage devices
    4. Ethernet mode
        1. Insert ethernet cable
        2. Power on
        3. <http://moode>
    5. Access Point (AP) mode
        1. Insert WiFi adapter that supports AP mode
        2. Power on
        3. Join network, SSID= __Moode__, password= __moodeaudio__
        4. <http://moode.local> or <http://172.24.1.1>

2. __Audio Device Setup__

    1. USB Device
        1. `Menu`, `Configure`, `Audio`
        2. Select `None` for I2S audio device then `SET`
        3. Restart
        4. `Menu`, `Configure`, `Audio`
        5. `EDIT` MPD config
        6. Leave Volume control set to "Software"
        7. Set Audio output to the name of the USB audio device
        8. `SAVE`
        9. Restart
    2. I2S Device
        1. `Menu`, `Configure`, `Audio`
        2. Select an I2S audio device then `SET`
        3. Restart
        4. `Menu`, `Configure`, `Audio`
        5. `EDIT` MPD config
        6. Leave Volume control set to "Software"
        7. Verify Audio output is set to the name of the I2S audio device
        8. `SAVE`

3. __Time Zone__
    1. `Menu`, `Configure`, `System`
    2. Select appropriate timezone then `SET`

4. __Add Source/s Containing Music Files__
    - USB Storage Devices
        1. Insert USB storage device
        2. `Menu`, `Update library`
        3. Wait for completion (no spinner)
    - SD Card Storage Devices
        1. `Menu`, `Update library`
        2. Wait for completion (no spinner)
    - NAS Device
        1. `Menu`, `Configure`, `Library`
        2. `CREATE` music source
        3. After `SAVE`, return to Playback or Library
        4. `Menu`, `Update library`
        5. Wait for completion (no spinner)
    - Music Database Utilities
        1. `Menu`, `Configure`, `Library`
        2. Various utilities will be listed in the Music Library section

5. __Verify Audio Playback__
    - Ethernet mode
        1. <http://moode>
        2. Play one of the radio stations
    - AP mode
        1. <http://moode.local>
        2. Browse, SD card, Stereo Test
        1. Menu for "LR Channel And Phase" track
        1. Play

 *At this point a FULLY OPERATIONAL PLAYER exists.*



Custom Configs
==============
Customize the player by using any of the following
procedures.

__Configure a new WiFi Connection__

- __Auto-Configure Before First Boot__

    **Note**: This works only on a fresh image that has never been booted!
    1. Mount the SDCard which will make the boot partition accessible
    2. Copy the file `/boot/moodecfg.ini.default` to your PC, Mac or Linux client
    3. Rename it to `moodecfg.ini`
    4. Edit the settings as needed
    5. Copy moodecfg.ini to /boot/
    6. Insert SD Card in Pi and power up
    7. Join AP SSID if using AP mode, then <http://hostname.local> or http://172.24.1.1

- __From Ethernet mode__
    1. Leave Ethernet cable connected
    2. Insert WiFi adapter (while Pi running)
    3. <http://moode>
    4. `Menu`, `Configure`, `Network`
    5. Configure a WiFi connection
    6. `Menu`, `Power`, `Shutdown`
    7. Unplug Ethernet cable
    8. Power on

- __From Access Point (AP) mode__
    1. Join network SSID= __Moode__, password= __moodeaudio__
    2. <http://moode.local>
    3. `Menu`, `Configure`, `Network`
    4. Configure a WiFi connection
    5. `Menu`, `Power`, `Restart`


__Change Host And Service Names__

1. `Menu`, `Configure`, `System` (and Audio)
2. `SET` after entering appropriate value in each name field
3. Restart is required if changing host name



After Player Setup
==================
Follow these instructions for making certain types of changes

__Switching from USB to I2S audio device__

1. Unplug USB audio device
2. `Menu`, `Power`, `Shutdown`
3. Install I2S audio device
4. Power on
5. `Menu`, `Configure`, `Audio`
6. Select appropriate I2S audio device then `SET`
7. `Menu`, `Power`, `Restart`
8. `Menu`, `Configure`, `Audio`, `EDIT` MPD config
9. Verify Audio output is set to "I2S audio device"
10. `SAVE`


__Switching from I2S to USB audio device__

1. `Menu`, `Configure`, `Audio`
2. Select "None" for I2S audio device then `SET`
3. `Menu`, `Power`, `Shutdown`
4. Optionally unplug I2S audio device
5. Plug in USB audio device
6. Power on
7. `Menu`, `Configure`, `Audio`, `EDIT` MPD config
8. Set Device type to "USB audio device"
9. `SAVE`
10. `Menu`, `Power`, `Restart`


__Switching from WiFi back to Ethernet__

1. Plug in Ethernet cable
2. `Menu`, `Configure`, `Network`
3. `RESET` network configuration to defaults
4. `Menu`, `Power`, `Shutdown`
5. Remove WiFi adapter
6. Power on



Command API
===========
Below are a list of commands that can be submitted to moOde via http or ssh

- Base URL is `http://moode/command/?cmd=`
- MPD commands that are listed in `MPC help`
- Volume commands that are listed in `/var/www/vol.sh --help`
- Library update via the command `libupd-submit`

_HTTP examples:_
#### MPD
`http://moode/command/?cmd=stop`

`http://moode/command/?cmd=play`

#### Volume
`http://moode/command/?cmd=vol.sh up 2`

`http://moode/command/?cmd=vol.sh mute`

#### Library update
`http://moode/command/?cmd=libupd-submit.php`

_SSH examples_
#### MPD
`mpc stop`

`mpc play`

#### Volume
`/var/www/vol.sh up 2`

`/var/www/vol.sh mute`

#### Library update
`sudo /var/www/libupd-submit.php`

