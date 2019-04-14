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
# 2019-04-12 TC moOde 5.0
#

# check for sudo
[[ $EUID -ne 0 ]] && { echo "Use sudo to run the script" ; exit 1 ; } ;

# r45e deprecate
FREQ() { 
	echo -e "\t  C L O C K    F R E Q U E N C I E S  \n";
	   for src in arm core h264 isp v3d uart pwm emmc pixel vec hdmi dpi ; do     
	   F=$(/opt/vc/bin/vcgencmd measure_clock $src | cut -f 2 -d "=")
	   echo -e "\t$src\t= $((F/1000000)) MHz";   
	   done | pr --indent=5 -r -t -2 -e3 -w 50
	echo
	grep  "actual clock" /sys/kernel/debug/mmc0/ios | awk ' {print "\t" "SD card" "\t= " $3/1000000 " MHz" }'
}

# r45e deprecate
CPULOAD() { 
	echo -e "\t  C P U    L O A D  \n";
	mpstat -P ALL 2 1 | awk  '/Average:/  { print  "\t" $2 "\t" $3 "\t" $5 "\t" $12}'
	echo
}

# r45e deprecate
PROCESSLOAD() { 
	echo -e "\t  P R O C E S S    L O A D  \n";
	ps -eo pri,rtprio,comm,%mem,psr,pcpu --sort=-pcpu | head -n 10 | sed 's/^/\t/g'  
	echo
}

# r45e deprecate
VOLT() {
	echo -e "\n\t  S Y S T E M    V O L T A G E S  \n"
	/opt/vc/bin/vcgencmd measure_volts core|awk -F "=" '{print "\t" "core" "\t" "\t" "\t" "= " $2 }'
	/opt/vc/bin/vcgencmd measure_volts sdram_c|awk -F "=" '{print "\t" "sdram controller" "\t" "= " $2 }'
	/opt/vc/bin/vcgencmd measure_volts sdram_i|awk -F "=" '{print "\t" "sdram I/O" "\t" "\t" "= " $2 }'
	/opt/vc/bin/vcgencmd measure_volts sdram_p|awk -F "=" '{print "\t" "sdram chip" "\t" "\t" "= " $2 "\n"}'
}

AUDIO() {
	BITS="$(cat /proc/asound/card0/pcm0p/sub0/hw_params | grep -w format | cut -f 2 -d " ")"
	RATE="$(cat /proc/asound/card0/pcm0p/sub0/hw_params | grep -w rate | cut -f 2 -d " ")"
	[[ "$BITS" = "" ]] && OUTSTREAM="Closed" || OUTSTREAM="$BITS / $RATE"

	# support arm64
	ALSAVER="$(dpkg -l | awk '/libasound2:/ { print  $3 }')"
 	SOXVER="$(dpkg -l | awk '/libsoxr0:/ { print  $3 }')"

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

	echo -e "\t  L I B R A R Y   S E T T I N G S  \n"
	# music library
	echo -e "\tArtist list order\t= $libartistcol\c"
	echo -e "\n\tIgnore articles\t\t= $ignore_articles\c"
	echo -e "\n\tCompilation rollup\t= $compilation_rollup\c"
	echo -e "\n\tCompilation excludes\t= $compilation_excludes\c"
	echo -e "\n\tUTF8 character filter\t= $library_utf8rep\c"
	echo -e "\n\tCover search pri\t= $library_covsearchpri\c"
	echo -e "\n\tHi-res covers\t\t= $library_hiresthm\c"
	echo -e "\n\tPixel ratio\t\t= $library_pixelratio\n"

	echo -e "\t  A U D I O    P A R A M E T E R S  \n"
	echo -e "\tAudio device\t\t= $audiodevname\c"
	echo -e "\n\tInterface\t\t= $iface\c"
	echo -e "\n\tHardware volume\t\t= $hwvol\c"
	echo -e "\n\tMixer name\t\t= $volmixer\c"
	echo -e "\n\tAudio source\t\t= $audioin\c"
	echo -e "\n\tOutput device\t\t= $audioout\c"
	echo -e "\n\tResume MPD aft src chg\t= $rsmafterinp\c"
	echo -e "\n\tVolume knob\t\t= $volknob\c"
	echo -e "\n\tVolume mute\t\t= $volmute\c"
	#echo -e "\n\tOutput stream\t\t= $OUTSTREAM\c"
	echo -e "\n\tALSA version\t\t= $ALSAVER\c"
	echo -e "\n\tSoX version\t\t= $SOXVER\c"

	echo -e "\n\c"
	echo -e "\n\tBluetooth controller\t= $btsvc\c"
	echo -e "\n\tBluetooth pairing agent\t= $pairing_agent\c"
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
	echo -e "\n\c"
	echo -e "\n\tRotary encoder\t\t= $rotaryenc\c"
	echo -e "\n\tEncoder params\t\t= $rotenc_params\c"
	echo -e "\n\tCrossfeed\t\t= $crossfeed\c"
	echo -e "\n\tParametric EQ\t\t= $eqfa4p\c"
	echo -e "\n\tGraphic EQ\t\t= $alsaequal\c"
	echo -e "\n\tPolarity inversion\t= $invert_polarity\c"
	echo -e "\n\tAuto-shuffle\t\t= $ashufflesvc\c"
	echo -e "\n\tAutoplay\t\t= $autoplay\c"
	echo -e "\n\tMPD crossfade\t\t= $mpdcrossfade\c"
	echo -e "\n\tMPD httpd\t\t= $mpd_httpd\n"

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
	echo -e "\n\tOutput buffer size (kb)\t= $max_output_buffer_size\c"
	echo -e "\n\tALSA auto-resample\t= $auto_resample\c"
	echo -e "\n\tALSA auto-channels\t= $auto_channels\c"
	echo -e "\n\tALSA auto-format\t= $auto_format\c"
	echo -e "\n\tHardware buffer time\t= $buffer_time\c"
	echo -e "\n\tHardware period time\t= $period_time\n"

	echo -e "\t  B L U E T O O T H    S E T T I N G S  \n"
	echo -e "\tBluetooth ver\t\t= $BTVER\c"
	echo -e "\n\tBluealsa ver\t\t= $BAVER\c"
	echo -e "\n\tSpeaker sharing\t\t= $btmulti\c"
	echo -e "\n\tResume MPD\t\t= $rsmafterbt\n"

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

	echo -e "\t  M O O D E    L O G  \n"
	sed -e 's/^/    /' /var/log/moode.log > /tmp/moode.log
	cat /tmp/moode.log
	#echo -e "\n\n"

}

# feature availability bitmasks
FEAT_AIRPLAY=2#0000000000000010
FEAT_MINIDLNA=2#0000000000000100
FEAT_SQUEEZELITE=2#0000000000010000
FEAT_UPMPDCLI=2#0000000000100000
FEAT_SPOTIFY=2#0000100000000000
FEAT_GPIO=2#0001000000000000

HOSTNAME=`uname -n`
RASPBIANVER=`cat /etc/debian_version`
KERNEL=`uname -r`
#CPU=`cat /proc/cpuinfo | grep "Hardware" | cut -f 2 -d ":" | tr -d " "`
# support arm64
if [[ $(uname -m) == "aarch64" ]];then
	CPU=`cat /proc/device-tree/compatible | tr '\0' ' ' | awk -F, '{print $NF}'`
else
	CPU=`cat /proc/cpuinfo | grep "Hardware" | cut -f 2 -d ":" | tr -d " "`
fi
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


PHPVER=$(php -v 2>&1 | awk 'FNR==1{ print $2 }' | cut -c-6)
NGINXVER=$(nginx -v 2>&1 | awk '{ print  $3 }' | cut -c7-)
SQLITEVER=$(sqlite3 -version | awk '{ print  $1 }')
BTVER=$(bluetoothd -v)
BAVER=$(bluealsa -V 2> /dev/null)
if [ "$BAVER" = "" ]; then 
	BAVER="Turn BT on for version info"
fi
HOSTAPDVER=$(hostapd -v 2>&1 | awk 'NR==1 { print  $2 }' | cut -c2-)
WIRINGPI_VER=$(gpio -v 2>&1 | awk 'NR==1 { print  $3 }')

# Moode release
mooderel="$(cat /var/www/footer.php | grep Release: | cut -f 2-3 -d " ")"
moodeupd="$(awk '/worker: Upd  /{print $5;}' /var/log/moode.log | tr -d '()')"

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
wificountry=${arr[33]}
alsavolume=${arr[34]}
amixname=${arr[35]}
mpdmixer=${arr[36]}
xtagdisp=${arr[37]}
rsmafterapl=${arr[38]}
lcdup=${arr[39]}
lcdupscript=${arr[40]}
extmeta=${arr[41]}
maint_interval=${arr[42]}
hdwrrev=${arr[43]}
[[ "${arr[44]}" = "Off" ]] && crossfeed="Off" || crossfeed=${arr[44]}
apdssid=${arr[45]}
apdchan=${arr[46]}
apdpwd=${arr[47]}
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
kvariant=${arr[58]}
toggle_song=${arr[59]}
[[ "${arr[60]}" = "1" ]] && slsvc="On" || slsvc="Off"
hdmiport=${arr[61]}
cpugov=${arr[62]}
[[ "${arr[63]}" = "1" ]] && pairing_agent="On" || pairing_agent="Off"
pkgid=${arr[64]}
lib_pos=${arr[65]}
[[ "${arr[66]}" = "0" ]] && mpdcrossfade="Off" || mpdcrossfade=${arr[66]}
[[ "${arr[67]}" = "1" ]] && eth0chk="On" || eth0chk="Off"
libartistcol=${arr[68]}
[[ "${arr[69]}" = "1" ]] && rsmafterbt="Yes" || rsmafterbt="No"
rotenc_params=${arr[70]}
[[ "${arr[71]}" = "1" ]] && shellinabox="On" || shellinabox="Off"
alsaequal=${arr[72]}
eqfa4p=${arr[73]}
if [[ $hdwrrev = "Pi-3B+ 1GB v1.3" || $hdwrrev = "Pi-3B 1GB v1.2" || $hdwrrev = "Pi-3A+ 512 MB v1.0" || $hdwrrev = "Pi-Zero W 512MB v1.1" ]]; then
	[[ "${arr[74]}" = "1" ]] && p3wifi="On" || p3wifi="Off"
	[[ "${arr[75]}" = "1" ]] && p3bt="On" || p3bt="Off"
else
	p3wifi="None"
	p3bt="None"
fi
cardnum=${arr[76]}
[[ "${arr[77]}" = "1" ]] && btsvc="On" || btsvc="Off"
btname=${arr[78]}
[[ "${arr[79]}" = "1" ]] && btmulti="Yes" || btmulti="No"
feat_bitmask=${arr[80]}
engine_mpd_sock_timeout=${arr[81]}
btactive=${arr[82]}
touchscn=${arr[83]}
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
compilation_excludes=${arr[98]}
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
compilation_rollup=${arr[112]}
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

# renderer devices
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

########################################################
# Output
#
########################################################

echo -e "\n\t  S Y S T E M    P A R A M E T E R S  "

echo "
	Date and time	= $NOW
	System uptime	= $UPTIME
	Timezone	= $timezone
	Release		= moOde $mooderel
	Update		= $moodeupd	

	Host name	= $HOSTNAME
	ETH0  IP	= $ETH0IP
	ETH0  MAC	= $ETH0MAC
	WLAN0 IP	= $WLAN0IP
	WLAN0 MAC	= $WLAN0MAC
	WiFi country	= $wificountry

	HDWR REV	= $hdwrrev
	SoC 		= $CPU
	CORES		= $CORES
	ARCH		= $ARCH
	RASPBIAN	= $RASPBIANVER
	KERNEL		= $KERNEL
	KTIMER FREQ	= $HZ Hz
	USB BOOT	= $USBBOOT
	Warranty	= $WARRANTY

	ROOT size	= $ROOTSIZE
	ROOT used 	= $ROOTUSED
	ROOT avail	= $ROOTAVAIL
	FS expand	= $FSEXPAND
	MEM free 	= $MEMFREE MB
	MEM used 	= $MEMUSED MB
	Temperature 	= $TEMP
 
	CPU GOV		= $GOV
	P3-WIFI		= $p3wifi
	P3-BT		= $p3bt
	HDMI		= $HDMI
	ETH0 CHECK	= $eth0chk
	MAX USB CUR	= $maxusbcurrent
	UAC2 FIX	= $uac2fix
	ETHPORT FIX	= $eth_port_fix
	SSH server	= $shellinabox

	LED0		= $LED0
	LED1		= $LED1
	SD CARD		= $SDFREQ MHz
"

echo -e "\t  C O R E    S E R V I C E S  "

echo "
	PHP-FPM		= $PHPVER
	NGINX		= $NGINXVER
	SQLite		= $SQLITEVER
	HOSTAPD		= $HOSTAPDVER
	WIRINGPI	= $WIRINGPI_VER
"

#CPULOAD
#PROCESSLOAD
#FREQ
#VOLT
AUDIO

exit 0
