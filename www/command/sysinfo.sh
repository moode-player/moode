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
# 2020-07-09 TC moOde 6.6.0
#

# check for sudo
[[ $EUID -ne 0 ]] && { echo "Use sudo to run the script" ; exit 1 ; } ;

SYSTEM_PARAMETERS() {
	echo -e "\n\c"
	echo -e "S Y S T E M   P A R A M E T E R S"
	echo -e "\nmoOde release\t\t= $moode_rel\c"
	echo -e "\nRaspiOS\t\t\t= $RASPIOS_VER\c"
	echo -e "\nLinux kernel\t\t= $kernelver\c"
	echo -e "\nPlatform\t\t= $hdwrrev\c"
	echo -e "\nArchitecture\t\t= $ARCH ($kernel_architecture)\c"
	echo -e "\nSystem uptime\t\t= $UPTIME\c"
	echo -e "\nTimezone\t\t= $timezone\c"
	echo -e "\nCurrent time\t\t= $NOW\c"
	echo -e "\n\c"
	echo -e "\nHost name\t\t= $HOSTNAME\c"
	echo -e "\nEthernet address\t= $ETH0IP\c"
	echo -e "\nEthernet MAC\t\t= $ETH0MAC\c"
	echo -e "\nWLAN address\t\t= $WLAN0IP\c"
	echo -e "\nWLAN MAC\t\t= $WLAN0MAC\c"
	echo -e "\nWLAN country\t\t= $wlancountry\c"
	echo -e "\n\c"
	echo -e "\nSoC identifier\t\t= $SOC\c"
	echo -e "\nCore count\t\t= $CORES\c"
	echo -e "\nKernel timer freq\t= $HZ Hz\c"
	echo -e "\nSDCard freq\t\t= $SDFREQ MHz\c"
	echo -e "\nUSB boot\t\t= $USBBOOT\c"
	echo -e "\nWarranty\t\t= $WARRANTY\c"
	echo -e "\n\c"
	echo -e "\nRoot size\t\t= $ROOTSIZE\c"
	echo -e "\nRoot used\t\t= $ROOTUSED\c"
	echo -e "\nRoot available\t\t= $ROOTAVAIL\c"
	echo -e "\nRoot expand\t\t= $FSEXPAND\c"
	echo -e "\nMemory free\t\t= $MEMFREE MB\c"
	echo -e "\nMemory used\t\t= $MEMUSED MB\c"
	echo -e "\nSoC temperature\t\t= $TEMP\c"
	echo -e "\n\c"
	echo -e "\nCPU governor\t\t= $GOV\c"
	echo -e "\nOnboard WiFi\t\t= $piwifi\c"
	echo -e "\nOnboard BT\t\t= $pibt\c"
	echo -e "\nHDMI output\t\t= $HDMI\c"
	echo -e "\nLED state\t\t= $led_state\c"
	echo -e "\nEth addr wait\t\t= $eth0chk\c"
	echo -e "\nMax USB current\t\t= $maxusbcurrent\c"
	echo -e "\nUSB (UAC2) fix\t\t= $uac2fix\c"
	echo -e "\nPi-3B+ eth fix\t\t= $eth_port_fix\c"
	echo -e "\nSSH term server\t\t= $shellinabox\c"
	echo -e "\n\c"
	echo -e "\nPHP-FPM version\t\t= $PHPVER\c"
	echo -e "\nNGINX version\t\t= $NGINXVER\c"
	echo -e "\nSQLite3 version\t\t= $SQLITEVER\c"
	echo -e "\nHostapd version\t\t= $HOSTAPDVER\c"
	echo -e "\nWiringPi version\t= $WIRINGPI_VER\c"
	echo -e "\nRPi.GPIO version\t= $RPI_GPIO_VER\n"
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

	echo -e "A U D I O   P A R A M E T E R S"
	echo -e "\nAudio device\t\t= $audiodevname\c"
	echo -e "\nInterface\t\t= $iface\c"
	echo -e "\nMixer name\t\t= $volmixer\c"
	echo -e "\nHardware volume\t\t= $hwvol\c"
	echo -e "\nMax ALSA volume\t\t= $alsavolume_max\c"
	echo -e "\nMax MPD volume\t\t= $volume_mpd_max\c"
	echo -e "\nVolume step limit\t= $volume_step_limit\c"
	echo -e "\nAudio source\t\t= $audioin\c"
	echo -e "\nOutput device\t\t= $audioout\c"
	echo -e "\nResume MPD\t\t= $rsmafterinp\c"
	echo -e "\nVolume knob\t\t= $volknob\c"
	echo -e "\nVolume mute\t\t= $volmute\c"
	echo -e "\nSaved MPD vol\t\t= $volknob_mpd\c"
	echo -e "\nPreamp volume\t\t= $volknob_preamp\c"
	echo -e "\nALSA version\t\t= $ALSAVER\c"
	echo -e "\nSoX version\t\t= $SOXVER\c"
	echo -e "\n\c"
	if [ $(($feat_bitmask & $FEAT_BLUETOOTH)) -ne 0 ]; then
		echo -e "\nBluetooth controller\t= $btsvc\c"
		echo -e "\nPairing agent\t\t= $pairing_agent\c"
	fi
	if [ $(($feat_bitmask & $FEAT_AIRPLAY)) -ne 0 ]; then
		echo -e "\nAirplay receiver\t= $airplaysvc\c"
	fi
	if [ $(($feat_bitmask & $FEAT_SPOTIFY)) -ne 0 ]; then
		echo -e "\nSpotify receiver\t= $spotifysvc\c"
	fi
	if [ $(($feat_bitmask & $FEAT_SQUEEZELITE)) -ne 0 ]; then
		echo -e "\nSqueezelite\t\t= $slsvc\c"
	fi
	if [ $(($feat_bitmask & $FEAT_UPMPDCLI)) -ne 0 ]; then
		echo -e "\nUPnP client\t\t= $upnpsvc\c"
	fi
	if [ $(($feat_bitmask & $FEAT_MINIDLNA)) -ne 0 ]; then
		echo -e "\nDLNA server\t\t= $dlnasvc\c"
	fi
	if [ $(($feat_bitmask & $FEAT_GPIO)) -ne 0 ]; then
		echo -e "\nGPIO button handler\t= $gpio_svc\c"
	fi
	if [ $(($feat_bitmask & $FEAT_DJMOUNT)) -ne 0 ]; then
		echo -e "\nUPnP browser\t\t= $upnp_browser\c"
	fi
	echo -e "\n\c"
	echo -e "\nAuto-shuffle\t\t= $ashufflesvc\c"
	echo -e "\nAutoplay\t\t= $autoplay\c"
	echo -e "\nRotary encoder\t\t= $rotaryenc\c"
	echo -e "\nEncoder params\t\t= $rotenc_params\c"
	echo -e "\nUSB volume knob\t\t= $usb_volknob\c"
	echo -e "\nPolarity inversion\t= $invert_polarity\c"
	echo -e "\nCrossfeed\t\t= $crossfeed\c"
	echo -e "\nCrossfade\t\t= $mpdcrossfade\c"
	echo -e "\nParametric EQ\t\t= $eqfa4p\c"
	echo -e "\nGraphic EQ\t\t= $alsaequal\c"
	echo -e "\nMPD httpd\t\t= $mpd_httpd\n"
}

APPEARANCE_SETTINGS() {
	echo -e "A P P E A R A N C E   S E T T I N G S"
	echo -e "\nThemes and backgrounds\c"
	echo -e "\n----------------------\c"
	echo -e "\nTheme\t\t\t= $themename\c"
	echo -e "\nAccent color\t\t= $accentcolor\c"
	echo -e "\nAlpha blend\t\t= $alphablend\c"
	echo -e "\nAdaptive background\t= $adaptive\c"
	if [ -f //var/local/www/imagesw/bgimage.jpg ]; then bgimage="Yes"; else bgimage="No"; fi
	echo -e "\nBackground image\t= $bgimage\c"
	echo -e "\nCover backdrop\t\t= $cover_backdrop\c"
	echo -e "\nCover blur\t\t= $cover_blur\c"
	echo -e "\nCover scale\t\t= $cover_scale\c"
	echo -e "\n\nLibrary options\c"
	echo -e "\n----------------------\c"
	echo -e "\nInstant play action\t= $library_instant_play\c"
	echo -e "\nShow tagview genres\t= $library_show_genres\c"
	echo -e "\nShow tagview covers\t= $library_tagview_covers\c"
	echo -e "\nShow sample rate\t= $library_encoded_at\c"
	echo -e "\nEllipsis limited text\t= $library_ellipsis_limited_text\c"
	echo -e "\nThumbnail columns\t= $library_thumbnail_columns\c"
	echo -e "\nAlbumview sort order\t= by $library_albumview_sort\c"
	echo -e "\nTagview sort order\t= by $library_tagview_sort\c"
	echo -e "\nCompilation identifier\t= $library_comp_id\c"
	echo -e "\nRecently added\t\t= $library_recently_added\c"
	echo -e "\nIgnore articles\t\t= $ignore_articles\c"
	echo -e "\nUTF8 character filter\t= $library_utf8rep\c"
	echo -e "\nHi-res thumbs\t\t= $library_hiresthm\c"
	echo -e "\nCover search pri\t= $library_covsearchpri\c"
	echo -e "\nPixel ratio\t\t= $library_pixelratio\c"
	echo -e "\n\nCoverview screen saver\c"
	echo -e "\n----------------------\c"
	echo -e "\nCoverView auto-display\t= $scnsaver_timeout\c"
	echo -e "\nCoverView style\t\t= $scnsaver_style\c"
	echo -e "\n\nOther options\c"
	echo -e "\n----------------------\c"
	echo -e "\nFont size\t\t= $font_size\c"
	echo -e "\nAuto-shuffle filter\t= $ashuffle_filter\c"
	echo -e "\nExtra metadata\t\t= $xtagdisp\c"
	echo -e "\nPlayback history\t= $playhist\c"
	echo -e "\nFirst use help\t\t= $first_use_help\n"
}

MPD_SETTINGS() {
	echo -e "M P D   S E T T I N G S"
	echo -e "\nVersion\t\t\t= $mpdver\c"
	echo -e "\nVolume control\t\t= $mixer_type\c"
	echo -e "\nALSA device\t\t= hw:$device\c"
	echo -e "\nSoX resampling\t\t= $audio_output_format\c"
	echo -e "\nSoX quality\t\t= $samplerate_converter\c"
	echo -e "\nSoX multithreading\t= $sox_multithreading\c"
	echo -e "\nDSD over PCM (DoP)\t= $dop\c"
	echo -e "\nReplaygain\t\t= $replaygain\c"
	echo -e "\nReplaygain preamp\t= $replaygain_preamp\c"
	echo -e "\nVolume normalization\t= $volume_normalization\c"
	echo -e "\nAudio buffer\t\t= $audio_buffer_size (kb)\c"
	echo -e "\nOutput buffer size\t= $max_output_buffer_size (kb)\n"
	#echo -e "\nALSA auto-resample\t= $auto_resample\c"
	#echo -e "\nALSA auto-channels\t= $auto_channels\c"
	#echo -e "\nALSA auto-format\t= $auto_format\c"
	#echo -e "\nHardware buffer time\t= $buffer_time\c"
	#echo -e "\nHardware period time\t= $period_time\n"
}
RENDERER_SETTINGS() {
	if [ $(($feat_bitmask & $FEAT_BLUETOOTH)) -ne 0 ]; then
		echo -e "B L U E T O O T H   S E T T I N G S"
		echo -e "\nBluetooth ver\t\t= $BTVER\c"
		echo -e "\nBluealsa ver\t\t= $BAVER\c"
		echo -e "\nSpeaker sharing\t\t= $btmulti\c"
		echo -e "\nResume MPD\t\t= $rsmafterbt\c"
		echo -e "\nPCM buffer time\t\t= $bluez_pcm_buffer ($micro_symbol)\n"
	fi

	if [ $(($feat_bitmask & $FEAT_AIRPLAY)) -ne 0 ]; then
		SPSVER="$(shairport-sync -V | cut -f 1 -d '-')"
		echo -e "A I R P L A Y   S E T T I N G S"
		echo -e "\nVersion\t\t\t= $SPSVER\c"
		echo -e "\nFriendly name\t\t= $airplayname\c"
		echo -e "\nALSA device\t\t= $airplay_device\c"
		echo -e "\nInterpolation\t\t= $interpolation\c"
		echo -e "\nOutput bit depth\t= $output_format\c"
		echo -e "\nOutput sample rate\t= $output_rate\c"
		echo -e "\nSession interruption\t= $allow_session_interruption\c"
		echo -e "\nSession timeout\t\t= $session_timeout (ms)\c"
		echo -e "\nLatency offset\t\t= $audio_backend_latency_offset_in_seconds (secs)\c"
		echo -e "\nAudio buffer\t\t= $audio_backend_buffer_desired_length_in_seconds (secs)\c"
		echo -e "\nResume MPD\t\t= $rsmafterapl\n"
	fi

	if [ $(($feat_bitmask & $FEAT_SPOTIFY)) -ne 0 ]; then
		SPOTDEV="$(aplay -L | grep -w default)"
		echo -e "S P O T I F Y   S E T T I N G S"
		echo -e "\nFriendly name\t\t= $spotifyname\c"
		echo -e "\nALSA device\t\t= $spotify_device\c"
		echo -e "\nBit rate\t\t= $bitrate\c"
		echo -e "\nInitial volume\t\t= $initial_volume\c"
		echo -e "\nVolume curve\t\t= $volume_curve\c"
		echo -e "\nVolume normalization\t= $volume_normalization\c"
		echo -e "\nNormalization pregain\t= $normalization_pregain\c"
		echo -e "\nAutoplay\t\t= $spotify_autoplay\c"
		echo -e "\nResume MPD\t\t= $rsmafterspot\n"
	fi

	if [ $(($feat_bitmask & $FEAT_SQUEEZELITE)) -ne 0 ]; then
		SL=`squeezelite -? | grep "Squeezelite" | cut -f 2 -d "v" | cut -f 1 -d ","`
		squeezelite -? | grep "\-Z" >/dev/null && SLT="\"DSD/SRC enabled\"" || SLT="\"DSD/SRC disabled\""
		echo -e "S Q U E E Z E L I T E   S E T T I N G S"
		echo -e "\nVersion\t\t\t= $SL $SLT\c"
		echo -e "\nFriendly name\t\t= $PLAYERNAME\c"
		echo -e "\nALSA device\t\t= hw:$AUDIODEVICE\c"
		echo -e "\nALSA params\t\t= $ALSAPARAMS\c"
		echo -e "\nOutput buffers\t\t= $OUTPUTBUFFERS\c"
		echo -e "\nTask priority\t\t= $TASKPRIORITY\c"
		echo -e "\nCodec list\t\t= $CODECS\c"
		echo -e "\nOther options\t\t= $OTHEROPTIONS\c" | cut -c 1-45
		echo -e "Resume MPD\t\t= $rsmaftersl\n"
	fi

	if [ $(($feat_bitmask & $FEAT_LOCALUI)) -ne 0 ]; then
		echo -e "L O C A L   D I S P L A Y   S E T T I N G S"
		echo -e "\nLocal UI display\t= $localui\c"
		echo -e "\nMouse cursor\t\t= $touchscn\c"
		echo -e "\nScreen blank\t\t= $scnblank Secs\c"
		echo -e "\nWake display on play\t= $wake_display\c"
		echo -e "\nBrightness\t\t= $scnbrightness\c"
		echo -e "\nPixel aspect ratio\t= $pixel_aspect_ratio\c"
		echo -e "\nRotate screen\t\t= $scnrotate Deg\n"
	fi
}

MOODE_LOG() {
	echo -e "M O O D E   S T A R T U P   L O G"
	echo -e "\n\c"
	cat /var/log/moode.log
	#sed -e 's/^/    /' /var/log/moode.log > /tmp/moode.log
	#cat /tmp/moode.log
	#echo -e "\n\n"
}

#
# Gather data
#

# Features availability bitmask
FEAT_AIRPLAY=2
FEAT_MINIDLNA=4
FEAT_SQUEEZELITE=16
FEAT_UPMPDCLI=32
FEAT_LOCALUI=256
FEAT_SPOTIFY=2048
FEAT_GPIO=4096
FEAT_DJMOUNT=8192
FEAT_BLUETOOTH=16384

HOSTNAME=`uname -n`
RASPIOS_VER=`cat /etc/debian_version`
#KERNEL=`uname -r`
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
if [[ $TMP -gt 3500000 ]]; then
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


PHPVER=$(php -v 2>&1 | awk -F "-" 'NR==1{ print $1 }' | cut -f 2 -d " ")
NGINXVER=$(nginx -v 2>&1 | awk '{ print  $3 }' | cut -c7-)
SQLITEVER=$(sqlite3 -version | awk '{ print  $1 }')
BTVER=$(bluetoothd -v)
BAVER=$(bluealsa -V 2> /dev/null)
if [ "$BAVER" = "" ]; then
	BAVER="Turn BT on for version info"
fi
HOSTAPDVER=$(hostapd -v 2>&1 | awk 'NR==1 { print  $2 }' | cut -c2-)
WIRINGPI_VER=$(gpio -v 2>&1 | awk 'NR==1 { print  $3 }')
RPI_GPIO_VER=$(grep -iRl "RPi.GPIO-" /usr/local/lib/python3.7/dist-packages/ | awk -F "." '{print $3 "." $4 "." $5}' | cut -f 2 -d "-")

# Moode release
moode_rel="$(awk '/Release: /{print $2 " " $3;}' /var/www/footer.php | cut -d"<" -f 1)"

# Moode SQL data
SQLDB=/var/local/www/db/moode-sqlite3.db

# Airplay settings
RESULT=$(sqlite3 $SQLDB "select value from cfg_airplay")
readarray -t arr <<<"$RESULT"
interpolation=${arr[2]}
output_format=${arr[3]}
output_rate=${arr[4]}
allow_session_interruption=${arr[5]}
session_timeout=${arr[6]}
audio_backend_latency_offset_in_seconds=${arr[7]}
audio_backend_buffer_desired_length_in_seconds=${arr[8]}

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
spotify_autoplay=${arr[5]}

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
alsavolume_max=${arr[33]}
alsavolume=${arr[34]}
amixname=${arr[35]}
mpdmixer=${arr[36]}
xtagdisp=${arr[37]}
rsmafterapl=${arr[38]}
lcdup=${arr[39]}
library_show_genres=${arr[40]}
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
library_albumview_sort=${arr[125]}
kernel_architecture=${arr[126]}
[[ "${arr[127]}" = "1" ]] && wake_display="On" || wake_display="Off"
[[ "${arr[128]}" = "1" ]] && usb_volknob="On" || usb_volknob="Off"
led_state=${arr[129]}
library_tagview_covers=${arr[130]}
library_tagview_sort=${arr[131]}
library_ellipsis_limited_text=${arr[132]}
appearance_modal_state=${arr[133]}
font_size=${arr[134]}
volume_step_limit=${arr[135]}
volume_mpd_max=${arr[136]}
library_thumbnail_columns=${arr[137]}
if [[ "${arr[138]}" = "9" ]]; then
	library_encoded_at="No"
elif [[ "${arr[138]}" = "0" ]]; then
	library_encoded_at="No (searchable)"
elif [[ "${arr[138]}" = "1" ]]; then
	library_encoded_at="HD only"
elif [[ "${arr[138]}" = "2" ]]; then
	library_encoded_at="Text"
elif [[ "${arr[138]}" = "3" ]]; then
	library_encoded_at="Badge"
fi
first_use_help=${arr[139]}

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

if [[ "$1" = "html" ]]; then
	micro_symbol="&micro;s"
else
	micro_symbol="\u03bcs"
fi

#
# Generate output
#

SYSTEM_PARAMETERS
AUDIO_PARAMETERS
APPEARANCE_SETTINGS
MPD_SETTINGS
RENDERER_SETTINGS
MOODE_LOG

exit 0
