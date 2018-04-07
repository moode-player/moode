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
# 2017-03-03 KS initial version (rev 4)
# 2018-01-26 TC moOde 4.0
# 2018-04-02 TC moOde 4.1
# - add raspbian version info
# - add 2>&1 and FNR to awk command for PHPVER
# - fix audiodevname not being set correctly
# - change cfg_system id 70 from ktimerfreq to rsmafterbt
# - change cfg_system id 83 from res_boot_config_txt to btactive
# - replace volwarning with wificountry 
#

FREQ() { 
	echo -e "\t  C L O C K    F R E Q U E N C I E S  \n";
	   for src in arm core h264 isp v3d uart pwm emmc pixel vec hdmi dpi ; do     
	   F=$(/opt/vc/bin/vcgencmd measure_clock $src | cut -f 2 -d "=")
	   echo -e "\t$src\t= $((F/1000000)) MHz";   
	   done | pr --indent=5 -r -t -2 -e3 -w 50
	echo
	grep  "actual clock" /sys/kernel/debug/mmc0/ios | awk ' {print "\t" "SD card" "\t= " $3/1000000 " MHz" }'
}

CPULOAD() { 
	echo -e "\t  C P U    L O A D  \n";
	mpstat -P ALL 2 1 | awk  '/Average:/  { print  "\t" $2 "\t" $3 "\t" $5 "\t" $12}'
	echo
}

PROCESSLOAD() { 
	echo -e "\t  P R O C E S S    L O A D  \n";
	ps -eo pri,rtprio,comm,%mem,psr,pcpu --sort=-pcpu | head -n 10 | sed 's/^/\t/g'  
	echo
}

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

	ALSAVER="$(dpkg -l | awk '/libasound2:armhf/ { print  $3 }')"
	SOXVER="$(dpkg -l | awk '/libsoxr0:armhf/ { print  $3 }')"

	if [[ $i2sdevice = "none" ]]; then
		[[ $device = "0" ]] && audiodevname="On-board audio device" || audiodevname="USB audio device"
	else
		audiodevname=$adevname
	fi

	if [[ $i2sdevice = "none" ]]; then
		[[ $device = "1" ]] && iface="USB" || iface="On-board"
	else
		iface="I2S"
	fi

	[[ $alsavolume = "none" ]] && hwvol="None" || hwvol=$alsavolume
	[[ "$amixname" = "" ]] && volmixer="None" || volmixer=$amixname
	
	echo -e "\t  U I  C U S T O M I Z A T I O N S  \n"
	echo -e "\tTheme\t\t\t= $themename\c"
	echo -e "\n\tAccent color\t\t= $themecolor\c"
	echo -e "\n\tAlpha blend\t\t= $alphablend\c"
	echo -e "\n\tAdaptive background\t= $adaptive\c"
	if [ -f //var/local/www/imagesw/bgimage.jpg ] ; then
		bgimage="Yes"
	else
		bgimage="No"
	fi
	echo -e "\n\tBackground image\t= $bgimage\c"
	echo -e "\n\tPlayback history\t= $playhist\c"
	echo -e "\n\tExtra metadata\t\t= $xtagdisp\c"
	echo -e "\n\tLibrary\t\t\t= $libartistcol\n"

	echo -e "\t  A U D I O    P A R A M E T E R S  \n"
	echo -e "\tAudio device\t\t= $audiodevname\c"
	echo -e "\n\tInterface\t\t= $iface\c"
	echo -e "\n\tHdwr volume\t\t= $hwvol\c"
	echo -e "\n\tMixer name\t\t= $volmixer\c"
	echo -e "\n\tOutput stream\t\t= $OUTSTREAM\c"
	echo -e "\n\tALSA version\t\t= $ALSAVER\c"
	echo -e "\n\tSoX version\t\t= $SOXVER\c"
	echo -e "\n\c"
	echo -e "\n\tVolume knob\t\t= $volknob\c"
	echo -e "\n\tVolume mute\t\t= $volmute\c"
	echo -e "\n\c"
	echo -e "\n\tBluetooth controller\t= $btsvc\c"

	if [ $(($feat_bitmask & $FEAT_AIRPLAY)) -ne 0 ]; then
		echo -e "\n\tAirplay receiver\t= $airplaysvc\c"
	fi
	if [ $(($feat_bitmask & $FEAT_SQUEEZELITE)) -ne 0 ]; then
		echo -e "\n\tSqueezelite\t\t= $slsvc\c"
	fi
	if [ $(($feat_bitmask & $FEAT_UPMPDCLI)) -ne 0 ]; then
		echo -e "\n\tUPnP renderer\t\t= $upnpsvc\c"
	fi
	if [ $(($feat_bitmask & $FEAT_MINIDLNA)) -ne 0 ]; then
		echo -e "\n\tDLNA server\t\t= $dlnasvc\c"
	fi

	echo -e "\n\c"
	echo -e "\n\tRotary encoder\t\t= $rotaryenc\c"
	echo -e "\n\tEncoder params\t\t= $rotenc_params\c"
	echo -e "\n\tCrossfeed\t\t= $crossfeed\c"
	echo -e "\n\tParametric EQ\t\t= $eqfa4p\c"
	echo -e "\n\tGraphic EQ\t\t= $alsaequal\c"
	echo -e "\n\tAuto-shuffle\t\t= $ashufflesvc\c"
	echo -e "\n\tAutoplay\t\t= $autoplay\c"
	echo -e "\n\tMPD crossfade\t\t= $mpdcrossfade\n"

	echo -e "\t  M P D    S E T T I N G S  \n"
	echo -e "\tVersion\t\t\t= $mpdver\c"
	echo -e "\n\tVolume control\t\t= $mixer_type\c"
	echo -e "\n\tALSA device\t\t= hw:$device\c"
	echo -e "\n\tSoX resampling\t\t= $audio_output_format\c"
	echo -e "\n\tSoX quality\t\t= $samplerate_converter\c"
	echo -e "\n\tSoX multithreading\t= $sox_multithreading\c"
	echo -e "\n\tAudio buffer (kb)\t= $audio_buffer_size\c"
	echo -e "\n\tBuffer before play\t= $buffer_before_play\c"
	echo -e "\n\tOutput buffer size (kb)\t= $max_output_buffer_size\c"
	echo -e "\n\tVolume normalization\t= $volume_normalization\c"
	echo -e "\n\tDSD over PCM (DoP)\t= $dop\c"
	echo -e "\n\tReplay gain\t\t= $replaygain\n"

	if [ $(($feat_bitmask & $FEAT_AIRPLAY)) -ne 0 ]; then
		SPSVER="$(shairport-sync -V | cut -f 1 -d '-')"
		echo -e "\t  A I R P L A Y    S E T T I N G S  \n"
		echo -e "\tVersion\t\t\t= $SPSVER\c"
		echo -e "\n\tFriendly name\t\t= $airplayname\c"
		echo -e "\n\tALSA device\t\t= hw:$device\c"
		echo -e "\n\tVolume mixer\t\t= $airplayvol\c"
		echo -e "\n\tResume MPD after\t= $rsmaftersps\c"
		echo -e "\n\tOutput bit depth\t= $output_format\c"
		echo -e "\n\tOutput sample rate\t= $output_rate\c"
		echo -e "\n\tSession interruption\t= $allow_session_interruption\c"
		echo -e "\n\tSession timeout (ms)\t= $session_timeout\c"
		echo -e "\n\tAudio buffer (secs)\t= $audio_backend_buffer_desired_length_in_seconds\n"
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
		echo -e "\n\tOther options\t\t= $OTHEROPTIONS\n"
	fi

	echo -e "\t  M O O D E    L O G  \n"
	sed -e 's/^/    /' /var/log/moode.log > /tmp/moode.log
	cat /tmp/moode.log
	echo -e "\n\n"

}

LINE() {
	echo "____________________________________________________________________________"
}
# feature availability bitmasks
FEAT_AIRPLAY=2#00000010
FEAT_MINIDLNA=2#00000100
FEAT_SQUEEZELITE=2#00010000
FEAT_UPMPDCLI=2#00100000

HOSTNAME=`uname -n`
RASPBIANVER=`cat /etc/debian_version`
KERNEL=`uname -r`
CPU=`cat /proc/cpuinfo | grep "Hardware" | cut -f 2 -d ":" | tr -d " "`
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

TMP="$(df | grep root | awk '{print $2}')"
if [[ $TMP -gt 3000000 ]]; then
	FSEXPAND="expanded"
else
	FSEXPAND="not expanded"
fi
ROOTSIZE="$(df -h | grep root | awk '{print $2}')"
ROOTUSED="$(df | grep root | awk '{print $5}')"
ROOTAVAIL="$(df -h | grep root | awk '{print $4}')"

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

PHPVER=$(php -v 2>&1 | awk 'FNR==1{ print $2 }' | cut -c-6)
NGINXVER=$(nginx -v 2>&1 | awk '{ print  $3 }' | cut -c7-)
SQLITEVER=$(sqlite3 -version | awk '{ print  $1 }')
BTVER=$(bluetoothd -v)

# Moode release
mooderel="$(cat /var/www/footer.php | grep Release: | cut -f 2-3 -d " ")"

# Moode SQL data
SQLDB=/var/local/www/db/moode-sqlite3.db

# Airplay settings
RESULT=$(sqlite3 $SQLDB "select value from cfg_airplay")
readarray -t arr <<<"$RESULT"
[[ "${arr[0]}" = "1" ]] && airplaymeta2="On" || airplaymeta2="Off"
airplayvol=${arr[1]}
rsmaftersps=${arr[2]}
output_format=${arr[3]}
output_rate=${arr[4]}
allow_session_interruption=${arr[5]}
session_timeout=${arr[6]}
audio_backend_buffer_desired_length_in_seconds=${arr[7]}

# MPD settings
RESULT=$(sqlite3 $SQLDB "select value_player from cfg_mpd where param in ('device', 'mixer_type', 'audio_output_format', 'samplerate_converter', 'sox_multithreading', 'dop', 'replaygain', 'volume_normalization', 'audio_buffer_size', 'buffer_before_play', 'max_output_buffer_size')")
readarray -t arr <<<"$RESULT"
device=${arr[0]}
mixer_type=${arr[1]}
audio_output_format=${arr[2]}
samplerate_converter=${arr[3]}
[[ "${arr[4]}" = "1" ]] && sox_multithreading="off" || sox_multithreading="on"
dop=${arr[5]}
replaygain=${arr[6]}
volume_normalization=${arr[7]}
audio_buffer_size=${arr[8]}
buffer_before_play=${arr[9]}
max_output_buffer_size=${arr[10]}

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
ckrad=${arr[18]}
ckraditem=${arr[19]}
ckradname=${arr[20]}
ckradstart=${arr[21]}
ckradstop=${arr[22]}
ckradvol=${arr[23]}
ckradshutdn=${arr[24]}
playhist=${arr[25]}
phistsong=${arr[26]}
pldisp=${arr[27]}
autofocus=${arr[28]}
timecountup=${arr[29]}
themecolor=${arr[30]}
volknob=${arr[31]}
[[ "${arr[32]}" = "1" ]] && volmute="Muted" || volmute="Unmuted"
wificountry=${arr[33]}
alsavolume=${arr[34]}
amixname=${arr[35]}
mpdmixer=${arr[36]}
xtagdisp=${arr[37]}
rsmaftersps=${arr[38]}
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
kernel=${arr[59]}
[[ "${arr[60]}" = "1" ]] && slsvc="On" || slsvc="Off"
hdmiport=${arr[61]}
cpugov=${arr[62]}
[[ "${arr[63]}" = "other" ]] && mpdsched="TS" || mpdsched=${arr[67]}
pkgid=${arr[64]}
airplayvol=${arr[65]}
[[ "${arr[66]}" = "0" ]] && mpdcrossfade="Off" || mpdcrossfade=${arr[66]}
[[ "${arr[67]}" = "1" ]] && eth0chk="On" || eth0chk="Off"
libartistcol=${arr[68]}
rsmafterbt=${arr[69]}
rotenc_params=${arr[70]}
[[ "${arr[71]}" = "1" ]] && shellinabox="On" || shellinabox="Off"
alsaequal=${arr[72]}
eqfa4p=${arr[73]}
if [[ $hdwrrev = "Pi-3B 1GB v1.2" || $hdwrrev = "Pi-Zero W 512MB v1.1" ]]; then
	[[ "${arr[74]}" = "1" ]] && p3wifi="On" || p3wifi="Off"
	[[ "${arr[75]}" = "1" ]] && p3bt="On" || p3bt="Off"
else
	p3wifi="None"
	p3bt="None"
fi
cardnum=${arr[76]}
[[ "${arr[77]}" = "1" ]] && btsvc="On" || btsvc="Off"
btname=${arr[78]}
btmulti=${arr[79]}
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

#LINE
echo -e "\n\t  S Y S T E M    P A R A M E T E R S  "

echo "
	Date and time	= $NOW
	System uptime	= $UPTIME
	Timezone	= $timezone
	moOde		= Release $mooderel

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
	MPD SCHDPOL	= $mpdsched
	P3-WIFI		= $p3wifi
	P3-BT		= $p3bt
	HDMI		= $HDMI
	ETH0 CHECK	= $eth0chk
	MAX USB CUR	= $maxusbcurrent
	UAC2 FIX	= $uac2fix
	SSH server	= $shellinabox

	LED0		= $LED0
	LED1		= $LED1
"
#LINE
echo -e "\t  C O R E    S E R V E R S  "

echo "
	PHP-FPM		= $PHPVER
	NGINX		= $NGINXVER
	SQLite		= $SQLITEVER
	Bluetooth	= $BTVER
"

#LINE
CPULOAD
#LINE
PROCESSLOAD
#LINE                                 
FREQ
#LINE
VOLT
#LINE
AUDIO
#LINE

exit 0
