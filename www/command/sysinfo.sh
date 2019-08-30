#!/bin/bash
#
# moOde audio player (C) 2014 Tim Curtis
# http://moodeaudio.org
#
# sysinfo.sh script (C) 2017 Klaus Shultz
# https://soundcheck-audio.blogspot.de
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
# 2019-MM-DD TC moOde 6.2.0
#

# check for sudo
[[ $EUID -ne 0 ]] && { echo "Use sudo to run the script" ; exit 1 ; } ;

SYSTEM_PARAMETERS() {
	echo -e "\n\t  S Y S T E M    P A R A M E T E R S  "
	echo -e "\n\tmoOde release\t\t= $moode_rel\c"
	echo -e "\n\tRaspbian OS\t\t= $RASPBIANVER\c"
	echo -e "\n\tLinux kernel\t\t= $KERNEL\c"
	echo -e "\n\tPi model\t\t= $hdwrrev\c"
	echo -e "\n\tSystem uptime\t\t= $UPTIME\c"
	echo -e "\n\tTimezone\t\t= $timezone\c"
	echo -e "\n\tCurrent time\t\t= $NOW\c"
	echo -e "\n\c"
	echo -e "\n\tHost name\t\t= $HOSTNAME\c"
	echo -e "\n\tEthernet address\t= $ETH0IP\c"
	echo -e "\n\tEthernet MAC\t\t= $ETH0MAC\c"
	echo -e "\n\tWLAN address\t\t= $WLAN0IP\c"
	echo -e "\n\tWLAN MAC\t\t= $WLAN0MAC\c"
	echo -e "\n\tWLAN country\t\t= $wlancountry\c"
	echo -e "\n\c"
	echo -e "\n\tSoC identifier\t\t= $SOC\c"
	echo -e "\n\tCore count\t\t= $CORES\c"
	echo -e "\n\tArchitecture\t\t= $ARCH\c"
	echo -e "\n\tKernel timer freq\t= $HZ Hz\c"
	echo -e "\n\tSDCard freq\t\t= $SDFREQ MHz\c"
	echo -e "\n\tUSB boot\t\t= $USBBOOT\c"
	echo -e "\n\tWarranty\t\t= $WARRANTY\c"
	echo -e "\n\c"
	echo -e "\n\tRoot size\t\t= $ROOTSIZE\c"
	echo -e "\n\tRoot used\t\t= $ROOTUSED\c"
	echo -e "\n\tRoot available\t\t= $ROOTAVAIL\c"
	echo -e "\n\tRoot expand\t\t= $FSEXPAND\c"
	echo -e "\n\tMemory free\t\t= $MEMFREE MB\c"
	echo -e "\n\tMemory used\t\t= $MEMUSED MB\c"
	echo -e "\n\tSoC temperature\t\t= $TEMP\c"
	echo -e "\n\c"
	echo -e "\n\tCPU governor\t\t= $GOV\c"
	echo -e "\n\tOnboard WiFi\t\t= $piwifi\c"
	echo -e "\n\tOnboard BT\t\t= $pibt\c"
	echo -e "\n\tHDMI output\t\t= $HDMI\c"
	echo -e "\n\tEth addr wait\t\t= $eth0chk\c"
	echo -e "\n\tMax USB current\t\t= $maxusbcurrent\c"
	echo -e "\n\tUSB (UAC2) fix\t\t= $uac2fix\c"
	echo -e "\n\tPi-3B+ eth fix\t\t= $eth_port_fix\c"
	echo -e "\n\tSSH term server\t\t= $shellinabox\c"
	echo -e "\n\c"
	echo -e "\n\tPHP-FPM version\t\t= $PHPVER\c"
	echo -e "\n\tNGINX version\t\t= $NGINXVER\c"
	echo -e "\n\tSQLite3 version\t\t= $SQLITEVER\c"
	echo -e "\n\tHostapd version\t\t= $HOSTAPDVER\c"
	echo -e "\n\tWiringPi version\t= $WIRINGPI_VER\c"
	echo -e "\n\tRPi.GPIO version\t= $RPI_GPIO_VER\n"
}

AUDIO_PARAMETERS() {
	ALSAVER="$(dpkg -l | awk '/libasound2:/ { print  $3 }')"
 	SOXVER="$(dpkg -l | awk '/libsoxr0:/ { print  $3 }')"
	BITS="$(cat /proc/asound/card0/pcm0p/sub0/hw_params | grep -w format | cut -f 2 -d " ")"
	RATE="$(cat /proc/asound/card0/pcm0p/sub0/hw_params | grep -w rate | cut -f 2 -d " ")"
	[[ "$BITS" = "" ]] && OUTSTREAM="Closed" || OUTSTREAM="$BITS / $RATE"

	if [[ $i2sdevice = "none" ]]; then
		[[ $device = "0" ]] && audiodevname="On-board audio device" || audiodevname="USB audio device"
	else
		audiodevname=$adevname
	fi

	if [[ $i2sdevice = "none" ]]; then
		[[ $device = "1" ]] && iface="USB" || iface="SoC"
	else
		iface="I2S"
	fi

	[[ $alsavolume = "none" ]] && hwvol="None" || hwvol="Controller detected"
	[[ "$amixname" = "" ]] && volmixer="None" || volmixer=$amixname

	echo -e "\t  A U D I O    P A R A M E T E R S  \n"
	echo -e "\tAudio device\t\t= $audiodevname\c"
	echo -e "\n\tInterface\t\t= $iface\c"
	echo -e "\n\tHardware volume\t\t= $hwvol\c"
	echo -e "\n\tMixer name\t\t= $volmixer\c"
	echo -e "\n\tAudio source\t\t= $audioin\c"
	echo -e "\n\tOutput device\t\t= $audioout\c"
	echo -e "\n\tResume MPD\t\t= $rsmafterinp\c"
	echo -e "\n\tVolume knob\t\t= $volknob\c"
	echo -e "\n\tVolume mute\t\t= $volmute\c"
	echo -e "\n\tSaved MPD vol\t\t= $volknob_mpd\c"
	echo -e "\n\tPreamp volume\t\t= $volknob_preamp\c"
	echo -e "\n\tALSA version\t\t= $ALSAVER\c"
	echo -e "\n\tSoX version\t\t= $SOXVER\c"
	echo -e "\n\c"
	echo -e "\n\tBluetooth controller\t= $btsvc\c"
	echo -e "\n\tPairing agent\t\t= $pairing_agent\c"
	if [ $(($feat_bitmask & $FEAT_AIRPLAY)) -ne 0 ]; then
		echo -e "\n\tAirplay receiver\t= $airplaysvc\c"
	fi
	if [ $(($feat_bitmask & $FEAT_SPOTIFY)) -ne 0 ]; then
		echo -e "\n\tSpotify receiver\t= $spotifysvc\c"
	fi
	if [ $(($feat_bitmask & $FEAT_SQUEEZELITE)) -ne 0 ]; then
		echo -e "\n\tSqueezelite\t\t= $slsvc\c"
	fi
	if [ $(($feat_bitmask & $FEAT_UPMPDCLI)) -ne 0 ]; then
		echo -e "\n\tUPnP client\t\t= $upnpsvc\c"
	fi
	if [ $(($feat_bitmask & $FEAT_MINIDLNA)) -ne 0 ]; then
		echo -e "\n\tDLNA server\t\t= $dlnasvc\c"
	fi
	if [ $(($feat_bitmask & $FEAT_GPIO)) -ne 0 ]; then
		echo -e "\n\tGPIO button handler\t= $gpio_svc\c"
	fi
	if [ $(($feat_bitmask & $FEAT_DJMOUNT)) -ne 0 ]; then
		echo -e "\n\tUPnP browser\t\t= $upnp_browser\c"
	fi
	echo -e "\n\c"
	echo -e "\n\tAuto-shuffle\t\t= $ashufflesvc\c"
	echo -e "\n\tAutoplay\t\t= $autoplay\c"
	echo -e "\n\tRotary encoder\t\t= $rotaryenc\c"
	echo -e "\n\tEncoder params\t\t= $rotenc_params\c"
	echo -e "\n\tPolarity inversion\t= $invert_polarity\c"
	echo -e "\n\tCrossfeed\t\t= $crossfeed\c"
	echo -e "\n\tCrossfade\t\t= $mpdcrossfade\c"
	echo -e "\n\tParametric EQ\t\t= $eqfa4p\c"
	echo -e "\n\tGraphic EQ\t\t= $alsaequal\c"
	echo -e "\n\tMPD httpd\t\t= $mpd_httpd\n"
}

APPEARANCE_SETTINGS() {
	echo -e "\t  A P P E A R A N C E   S E T T I N G S  \n"
	# themes and backgrounds
	echo -e "\tTheme\t\t\t= $themename\c"
	echo -e "\n\tAccent color\t\t= $accentcolor\c"
	echo -e "\n\tAlpha blend\t\t= $alphablend\c"
	echo -e "\n\tAdaptive background\t= $adaptive\c"
	if [ -f //var/local/www/imagesw/bgimage.jpg ]; then bgimage="Yes"; else bgimage="No"; fi
	echo -e "\n\tBackground image\t= $bgimage\c"
	echo -e "\n\tCover backdrop\t\t= $cover_backdrop\c"
	echo -e "\n\tCover blur\t\t= $cover_blur\c"
	echo -e "\n\tCover scale\t\t= $cover_scale\c"
	# coverview options
	echo -e "\n\tCoverView auto-display\t= $scnsaver_timeout\c"
	echo -e "\n\tCoverView style\t\t= $scnsaver_style\c"
	# other options
	echo -e "\n\tAuto-shuffle filter\t= $ashuffle_filter\c"
	echo -e "\n\tExtra metadata\t\t= $xtagdisp\c"
	echo -e "\n\tPlayback history\t= $playhist\n"
}

LIBRARY_SETTINGS() {
	echo -e "\t  L I B R A R Y   S E T T I N G S  \n"
	echo -e "\tInstant play action\t= $library_instant_play\c"
	echo -e "\n\tShow genres column\t= $show_genres\c"
	echo -e "\n\tCompilation identifier\t= $library_comp_id\c"
	echo -e "\n\tRecently added\t\t= $library_recently_added\c"
	echo -e "\n\tIgnore articles\t\t= $ignore_articles\c"
	echo -e "\n\tUTF8 character filter\t= $library_utf8rep\c"
	echo -e "\n\tHi-res thumbs\t\t= $library_hiresthm\c"
	echo -e "\n\tCover search pri\t= $library_covsearchpri\c"
	echo -e "\n\tPixel ratio\t\t= $library_pixelratio\n"
	#echo -e "\n\tArtist sort tag\t\t= $library_artist_sort\c"
	#echo -e "\n\tAlbum sort tag\t\t= $library_album_sort\n"
}

MPD_SETTINGS() {
	echo -e "\t  M P D    S E T T I N G S  \n"
	echo -e "\tVersion\t\t\t= $mpdver\c"
	echo -e "\n\tVolume control\t\t= $mixer_type\c"
	echo -e "\n\tALSA device\t\t= hw:$device\c"
	echo -e "\n\tSoX resampling\t\t= $audio_output_format\c"
	echo -e "\n\tSoX quality\t\t= $samplerate_converter\c"
	echo -e "\n\tSoX multithreading\t= $sox_multithreading\c"
	echo -e "\n\tDSD over PCM (DoP)\t= $dop\c"
	echo -e "\n\tReplaygain\t\t= $replaygain\c"
	echo -e "\n\tReplaygain preamp\t= $replaygain_preamp\c"
	echo -e "\n\tVolume normalization\t= $volume_normalization\c"
	echo -e "\n\tAudio buffer (kb)\t= $audio_buffer_size\c"
	echo -e "\n\tOutput buffer size (kb)\t= $max_output_buffer_size\n"
	#echo -e "\n\tALSA auto-resample\t= $auto_resample\c"
	#echo -e "\n\tALSA auto-channels\t= $auto_channels\c"
	#echo -e "\n\tALSA auto-format\t= $auto_format\c"
	#echo -e "\n\tHardware buffer time\t= $buffer_time\c"
	#echo -e "\n\tHardware period time\t= $period_time\n"
}
RENDERER_SETTINGS() {
	echo -e "\t  B L U E T O O T H    S E T T I N G S  \n"
	echo -e "\tBluetooth ver\t\t= $BTVER\c"
	echo -e "\n\tBluealsa ver\t\t= $BAVER\c"
	echo -e "\n\tSpeaker sharing\t\t= $btmulti\c"
	echo -e "\n\tResume MPD\t\t= $rsmafterbt\c"
	echo -e "\n\tPCM buffer time\t\t= $bluez_pcm_buffer (microseconds)\n"

	if [ $(($feat_bitmask & $FEAT_AIRPLAY)) -ne 0 ]; then
		SPSVER="$(shairport-sync -V | cut -f 1 -d '-')"
		echo -e "\t  A I R P L A Y    S E T T I N G S  \n"
		echo -e "\tVersion\t\t\t= $SPSVER\c"
		echo -e "\n\tFriendly name\t\t= $airplayname\c"
		echo -e "\n\tALSA device\t\t= $airplay_device\c"
		echo -e "\n\tInterpolation\t\t= $interpolation\c"
		echo -e "\n\tOutput bit depth\t= $output_format\c"
		echo -e "\n\tOutput sample rate\t= $output_rate\c"
		echo -e "\n\tSession interruption\t= $allow_session_interruption\c"
		echo -e "\n\tSession timeout (ms)\t= $session_timeout\c"
		echo -e "\n\tAudio buffer (secs)\t= $audio_backend_buffer_desired_length_in_seconds\c"
		echo -e "\n\tResume MPD\t\t= $rsmafterapl\n"
	fi

	if [ $(($feat_bitmask & $FEAT_SPOTIFY)) -ne 0 ]; then
		SPOTDEV="$(aplay -L | grep -w default)"
		echo -e "\t  S P O T I F Y    S E T T I N G S  \n"
		echo -e "\tFriendly name\t\t= $spotifyname\c"
		echo -e "\n\tALSA device\t\t= $spotify_device\c"
		echo -e "\n\tBit rate\t\t= $bitrate\c"
		echo -e "\n\tInitial volume\t\t= $initial_volume\c"
		echo -e "\n\tVolume curve\t\t= $volume_curve\c"
		echo -e "\n\tVolume normalization\t= $volume_normalization\c"
		echo -e "\n\tNormalization pregain\t= $normalization_pregain\c"
		echo -e "\n\tResume MPD\t\t= $rsmafterspot\n"
	fi

	if [ $(($feat_bitmask & $FEAT_SQUEEZELITE)) -ne 0 ]; then
		SL=`squeezelite -? | grep "Squeezelite" | cut -f 2 -d "v" | cut -f 1 -d ","`
		squeezelite -? | grep "\-Z" >/dev/null && SLT="\"DSD/SRC enabled\"" || SLT="\"DSD/SRC disabled\""
		echo -e "\t  S Q U E E Z E L I T E    S E T T I N G S  \n"
		echo -e "\tVersion\t\t\t= $SL $SLT\c"
		echo -e "\n\tFriendly name\t\t= $PLAYERNAME\c"
		echo -e "\n\tALSA device\t\t= hw:$AUDIODEVICE\c"
		echo -e "\n\tALSA params\t\t= $ALSAPARAMS\c"
		echo -e "\n\tOutput buffers\t\t= $OUTPUTBUFFERS\c"
		echo -e "\n\tTask priority\t\t= $TASKPRIORITY\c"
		echo -e "\n\tCodec list\t\t= $CODECS\c"
		echo -e "\n\tOther options\t\t= $OTHEROPTIONS\c" | cut -c 1-45
		echo -e "\tResume MPD\t\t= $rsmaftersl\n"
	fi

	if [ $(($feat_bitmask & $FEAT_LOCALUI)) -ne 0 ]; then
		echo -e "\t  L O C A L   D I S P L A Y    S E T T I N G S  \n"
		echo -e "\tLocal UI display\t= $localui\c"
		echo -e "\n\tMouse cursor\t\t= $touchscn\c"
		echo -e "\n\tScreen blank\t\t= $scnblank Secs\c"
		echo -e "\n\tBrightness\t\t= $scnbrightness\c"
		echo -e "\n\tPixel aspect ratio\t= $pixel_aspect_ratio\c"
		echo -e "\n\tRotate screen\t\t= $scnrotate Deg\n"
	fi
}

MOODE_LOG() {
	echo -e "\t  M O O D E    S T A R T U P    L O G  \n"
	sed -e 's/^/    /' /var/log/moode.log > /tmp/moode.log
	cat /tmp/moode.log
	#echo -e "\n\n"
}

#
# Gather data
#

# feature availability bitmasks
FEAT_AIRPLAY=2#0000000000000010
FEAT_MINIDLNA=2#0000000000000100
FEAT_SQUEEZELITE=2#0000000000010000
FEAT_LOCALUI=2#0000000100000000
FEAT_UPMPDCLI=2#0000000000100000
FEAT_SPOTIFY=2#0000100000000000
FEAT_GPIO=2#0001000000000000
FEAT_DJMOUNT=2#0010000000000000

HOSTNAME=`uname -n`
RASPBIANVER=`cat /etc/debian_version`
KERNEL=`uname -r`
SOC=`cat /proc/device-tree/compatible | tr '\0' ' ' | awk -F, '{print $NF}'`
CORES=`grep -c ^processor /proc/cpuinfo`
ARCH=`uname -m`
MEMUSED=`free -m | grep "Mem" | awk {'print $3'}`
MEMFREE=`free -m | grep "Mem" | awk {'print $4'}`

if [ -f /sys/devices/system/cpu/cpu0/cpufreq/scaling_governor ] ; then
	GOV=`cat /sys/devices/system/cpu/cpu0/cpufreq/scaling_governor`
else
	GOV="NA - disabled in kernel"
fi

ETH0IP="$(ip addr list eth0 2>&1 | grep "inet " |cut -d' ' -f6|cut -d/ -f1)"
WLAN0IP="$(ip addr list wlan0 2>&1 | grep "inet " |cut -d' ' -f6|cut -d/ -f1)"
if [ "$ETH0IP" = "" ]; then
	ETH0IP="unassigned"
fi
if [ "$WLAN0IP" = "" ]; then
	WLAN0IP="unassigned"
fi
ETH0MAC="$(ip addr list eth0 2>&1 | grep "ether " |cut -d' ' -f6|cut -d/ -f1)"
WLAN0MAC="$(ip addr list wlan0 2>&1 | grep "ether " |cut -d' ' -f6|cut -d/ -f1)"
if [ "$ETH0MAC" = "" ]; then
	ETH0MAC="no adapter"
fi
if [ "$WLAN0MAC" = "" ]; then
	WLAN0MAC="no adapter"
fi

TMP="$(df | grep /dev/root | awk '{print $2}')"
if [[ $TMP -gt 3000000 ]]; then
	FSEXPAND="expanded"
else
	FSEXPAND="not expanded"
fi
ROOTSIZE="$(df -h | grep /dev/root | awk '{print $2}')"
ROOTUSED="$(df | grep /dev/root | awk '{print $5}')"
ROOTAVAIL="$(df -h | grep /dev/root | awk '{print $4}')"

/opt/vc/bin/tvservice -s | grep -q "off" && HDMI="Off" || HDMI="On"

NOW=$(date +"%Y-%m-%d %T")
UPTIME="$(uptime -p)"

[[ $(cat /proc/cpuinfo | grep 'Revision' | cut -f 2 -d " ") == 2* ]] && WARRANTY=void || WARRANTY=OK

grep -q mmc0 /sys/class/leds/led0/trigger && LED0="on" || LED0="off"

if [ $(ls /sys/class/leds | grep led1) ]; then
	grep -q mmc0 /sys/class/leds/led1/trigger && LED1="on" || LED1="off"
else
	LED1="not accessible"
fi

TEMP=`awk '{printf "%3.1f\302\260C\n", $1/1000}' /sys/class/thermal/thermal_zone0/temp`
SDFREQ=$(grep "actual clock" /sys/kernel/debug/mmc0/ios | awk ' {print $3/1000000}')


PHPVER=$(php -v 2>&1 | awk 'FNR==1{ print $2 }' | cut -c-5)
NGINXVER=$(nginx -v 2>&1 | awk '{ print  $3 }' | cut -c7-)
SQLITEVER=$(sqlite3 -version | awk '{ print  $1 }')
BTVER=$(bluetoothd -v)
BAVER=$(bluealsa -V 2> /dev/null)
if [ "$BAVER" = "" ]; then
	BAVER="Turn BT on for version info"
fi
HOSTAPDVER=$(hostapd -v 2>&1 | awk 'NR==1 { print  $2 }' | cut -c2-)
WIRINGPI_VER=$(gpio -v 2>&1 | awk 'NR==1 { print  $3 }')
RPI_GPIO_VER=$(pip3 show RPi-GPIO | grep Version | awk '{ print  $2 }')

# Moode release
moode_rel="$(cat /var/www/footer.php | grep Release: | cut -f 2-3 -d " ")"

# Moode SQL data
SQLDB=/var/local/www/db/moode-sqlite3.db

# Airplay settings
RESULT=$(sqlite3 $SQLDB "select value from cfg_airplay")
readarray -t arr <<<"$RESULT"
#[[ "${arr[0]}" = "1" ]] && airplaymeta2="On" || airplaymeta2="Off" deprecated
#airplayvol=${arr[1]} deprecated
interpolation=${arr[2]}
output_format=${arr[3]}
output_rate=${arr[4]}
allow_session_interruption=${arr[5]}
session_timeout=${arr[6]}
audio_backend_buffer_desired_length_in_seconds=${arr[7]}

# MPD settings, r45b
RESULT=$(sqlite3 $SQLDB "select value from cfg_mpd where param in (
'device',
'mixer_type',
'dop',
'audio_output_format',
'samplerate_converter',
'sox_multithreading',
'replaygain',
'replaygain_preamp',
'volume_normalization',
'audio_buffer_size',
'max_output_buffer_size',
'auto_resample',
'auto_channels',
'auto_format',
'buffer_time',
'period_time'
)")
readarray -t arr <<<"$RESULT"
device=${arr[0]}
mixer_type=${arr[1]}
dop=${arr[2]}
audio_output_format=${arr[3]}
samplerate_converter=${arr[4]}
[[ "${arr[5]}" = "1" ]] && sox_multithreading="off" || sox_multithreading="on"
replaygain=${arr[6]}
replaygain_preamp=${arr[7]}
volume_normalization=${arr[8]}
audio_buffer_size=${arr[9]}
max_output_buffer_size=${arr[10]}
auto_resample=${arr[11]}
auto_channels=${arr[12]}
auto_format=${arr[13]}
buffer_time=${arr[14]}
period_time=${arr[15]}

# Spotify settings
RESULT=$(sqlite3 $SQLDB "select value from cfg_spotify")
readarray -t arr <<<"$RESULT"
bitrate=${arr[0]}
initial_volume=${arr[1]}
volume_curve=${arr[2]}
volume_normalization=${arr[3]}
normalization_pregain=${arr[4]}

# Squeezelite settings
RESULT=$(sqlite3 $SQLDB "select value from cfg_sl")
readarray -t arr <<<"$RESULT"
PLAYERNAME=${arr[0]}
AUDIODEVICE=${arr[1]}
ALSAPARAMS=${arr[2]}
OUTPUTBUFFERS=${arr[3]}
TASKPRIORITY=${arr[4]}
CODECS=${arr[5]}
OTHEROPTIONS=${arr[6]}

# System settings
RESULT=$(sqlite3 $SQLDB "select value from cfg_system")
readarray -t arr <<<"$RESULT"
sessionid=${arr[0]}
timezone=${arr[1]}
i2sdevice=${arr[2]}
host=${arr[3]}
browsertitle=${arr[4]}
airplayname=${arr[5]}
upnpname=${arr[6]}
dlnaname=${arr[7]}
[[ "${arr[8]}" = "1" ]] && airplaysvc="On" || airplaysvc="Off"
[[ "${arr[9]}" = "1" ]] && upnpsvc="On" || upnpsvc="Off"
[[ "${arr[10]}" = "1" ]] && dlnasvc="On" || dlnasvc="Off"
[[ "${arr[11]}" = "1" ]] && maxusbcurrent="On" || maxusbcurrent="Off"
[[ "${arr[12]}" = "1" ]] && rotaryenc="On" || rotaryenc="Off"
[[ "${arr[13]}" = "1" ]] && autoplay="On" || autoplay="Off"
kernelver=${arr[14]}
mpdver=${arr[15]}
procarch=${arr[16]}
adevname=${arr[17]}
clkradio_mode=${arr[18]}
clkradio_item=${arr[19]}
clkradio_name=${arr[20]}
clkradio_start=${arr[21]}
clkradio_stop=${arr[22]}
clkradio_volume=${arr[23]}
clkradio_shutdown=${arr[24]}
playhist=${arr[25]}
phistsong=${arr[26]}
library_utf8rep=${arr[27]}
current_view=${arr[28]}
timecountup=${arr[29]}
accentcolor=${arr[30]}
volknob=${arr[31]}
[[ "${arr[32]}" = "1" ]] && volmute="Muted" || volmute="Unmuted"
RESERVED_34=${arr[33]}
alsavolume=${arr[34]}
amixname=${arr[35]}
mpdmixer=${arr[36]}
xtagdisp=${arr[37]}
rsmafterapl=${arr[38]}
lcdup=${arr[39]}
show_genres=${arr[40]}
extmeta=${arr[41]}
maint_interval=${arr[42]}
hdwrrev=${arr[43]}
[[ "${arr[44]}" = "Off" ]] && crossfeed="Off" || crossfeed=${arr[44]}
bluez_pcm_buffer=${arr[45]}
[[ "${arr[46]}" = "1" ]] && upnp_browser="On" || upnp_browser="Off"
library_instant_play=${arr[47]}
airplaymeta=${arr[48]}
airplayactv=${arr[49]}
[[ "${arr[50]}" = "1" ]] && debuglog="On" || debuglog="Off"
[[ "${arr[51]}" = "1" ]] && ashufflesvc="On" || ashufflesvc="Off"
ashuffle=${arr[52]}
mpdassvc=${arr[53]}
mpdaspwd=${arr[54]}
mpdasuser=${arr[55]}
[[ "${arr[56]}" = "1" ]] && uac2fix="On" || uac2fix="Off"
keyboard=${arr[57]}
[[ "${arr[58]}" = "1" ]] && localui="On" || localui="Off"
toggle_song=${arr[59]}
[[ "${arr[60]}" = "1" ]] && slsvc="On" || slsvc="Off"
hdmiport=${arr[61]}
cpugov=${arr[62]}
[[ "${arr[63]}" = "1" ]] && pairing_agent="On" || pairing_agent="Off"
pkgid_suffix=${arr[64]}
lib_pos=${arr[65]}
[[ "${arr[66]}" = "0" ]] && mpdcrossfade="Off" || mpdcrossfade=${arr[66]}
[[ "${arr[67]}" = "1" ]] && eth0chk="On" || eth0chk="Off"
RESERVED_69=${arr[68]}
[[ "${arr[69]}" = "1" ]] && rsmafterbt="Yes" || rsmafterbt="No"
rotenc_params=${arr[70]}
[[ "${arr[71]}" = "1" ]] && shellinabox="On" || shellinabox="Off"
alsaequal=${arr[72]}
eqfa4p=${arr[73]}
rev=$(echo $hdwrrev | cut -c 4)
if [[ $rev = "3" || $rev = "4" || $hdwrrev = "Pi-Zero W 512MB v1.1" ]]; then
	[[ "${arr[74]}" = "1" ]] && piwifi="On" || piwifi="Off"
	[[ "${arr[75]}" = "1" ]] && pibt="On" || pibt="Off"
else
	piwifi="None"
	pibt="None"
fi
cardnum=${arr[76]}
[[ "${arr[77]}" = "1" ]] && btsvc="On" || btsvc="Off"
btname=${arr[78]}
[[ "${arr[79]}" = "1" ]] && btmulti="Yes" || btmulti="No"
feat_bitmask=${arr[80]}
if [[ "${arr[81]}" = "604800000" ]]; then
	library_recently_added="1 Week"
elif [[ "${arr[81]}" = "2592000000" ]]; then
	library_recently_added="1 Month"
elif [[ "${arr[81]}" = "7776000000" ]]; then
	library_recently_added="3 Months"
elif [[ "${arr[81]}" = "15552000000" ]]; then
	library_recently_added="6 Months"
elif [[ "${arr[81]}" = "31536000000" ]]; then
	library_recently_added="1 Year"
fi
btactive=${arr[82]}
[[ "${arr[83]}" = "1" ]] && touchscn="On" || touchscn="Off"
scnblank=${arr[84]}
scnrotate=${arr[85]}
scnbrightness=${arr[86]}
themename=${arr[87]}
res_software_upd_url=${arr[88]}
alphablend=${arr[89]}
adaptive=${arr[90]}
audioout=${arr[91]}
audioin=${arr[92]}
slactive=${arr[93]}
rsmaftersl=${arr[94]}
mpdmixer_local=${arr[95]}
wrkready=${arr[96]}
scnsaver_timeout=${arr[97]}
pixel_aspect_ratio=${arr[98]}
favorites_name=${arr[99]}
[[ "${arr[100]}" = "1" ]] && spotifysvc="On" || spotifysvc="Off"
spotifyname=${arr[101]}
spotactive=${arr[102]}
rsmafterspot=${arr[103]}
library_covsearchpri=${arr[104]}
library_hiresthm=${arr[105]}
library_pixelratio=${arr[106]}
[[ "${arr[107]}" = "1" ]] && usb_auto_updatedb="On" || usb_auto_updatedb="Off"
cover_backdrop=${arr[108]}
cover_blur=${arr[109]}
cover_scale=${arr[110]}
[[ "${arr[111]}" = "1" ]] && eth_port_fix="On" || eth_port_fix="Off"
library_comp_id=${arr[112]}
scnsaver_style=${arr[113]}
ashuffle_filter=${arr[114]}
[[ "${arr[115]}" = "1" ]] && mpd_httpd="On" || mpd_httpd="Off"
mpd_httpd_port=${arr[116]}
mpd_httpd_encoder=${arr[117]}
[[ "${arr[118]}" = "1" ]] && invert_polarity="On" || invert_polarity="Off"
inpactive=${arr[119]}
rsmafterinp=${arr[120]}
[[ "${arr[121]}" = "1" ]] && gpio_svc="On" || gpio_svc="Off"
ignore_articles=${arr[122]}
volknob_mpd=${arr[123]}
volknob_preamp=${arr[124]}

# Network settings
RESULT=$(sqlite3 $SQLDB "select * from cfg_network")
readarray -t arr <<<"$RESULT"
wlanssid=$(echo ${arr[1]} | cut -f 9 -d "|")
wlansec=$(echo ${arr[1]} | cut -f 10 -d "|")
wlancountry=$(echo ${arr[1]} | cut -f 13 -d "|")
apdssid=$(echo ${arr[2]} | cut -f 9 -d "|")
apdchan=$(echo ${arr[2]} | cut -f 14 -d "|")

# Renderers
if [[ $alsaequal != "Off" ]]; then
	airplay_device="alsaequal"
	spotify_device="alsaequal"
elif [[ $eqfa4p != "Off" ]]; then
	airplay_device="eqfa4p"
	spotify_device="eqfa4p"
else
	airplay_device="hw:"$cardnum
	spotify_device="plughw:"$cardnum
fi

MODEL=${hdwrrev:0:5}
if [ $MODEL = Pi-3B ]; then
	TMP="$(vcgencmd otp_dump | grep 17:)"
	if [ "$TMP" = "17:3020000a" ]; then
		USBBOOT="enabled"
	else
		USBBOOT="not enabled"
	fi
else
	USBBOOT="not available"
fi

modprobe configs
test -f /proc/config.gz && {
	HZ=$(zcat /proc/config.gz | grep "^CONFIG_HZ=" | cut -f 2 -d "=")
} || {
	HZ="No /proc/config.gz"
}
rmmod configs

#
# Generate output
#

SYSTEM_PARAMETERS
AUDIO_PARAMETERS
APPEARANCE_SETTINGS
LIBRARY_SETTINGS
MPD_SETTINGS
RENDERER_SETTINGS
MOODE_LOG

exit 0
