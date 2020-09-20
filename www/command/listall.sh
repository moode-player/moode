#!/bin/bash
#
# Return list of music files in /mnt and /media directories
# (C) 2020 Kent Reed (@TheOldPresbyope)
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
# NOTES:
# 1. 'find base ... -printf %P' returns file paths relative to the given base directory.
# 2. To match MPD listings, results for /media are prepended with "USB/".
# 3. A music file must match one of the extensions listed below obtained from decoder-plugin values via MPD -V.
#
# 2020-MM-DD TC moOde 7.0.0
#

# Audio file types
TYPES_REGEX="\(3g2\|3g2\|3gp\|4xm\|8svx\|aa3\|aac\|ac3\|adx\|afc\|aif\|aifc\|aiff\|al\|alaw\|amr\|anim\|apc\|ape\|asf\|atrac\|au\|aud\|avi\|avm2\|avs\|bap\|bfi\|c93\|cak\|cin\|cmv\|cpk\|daud\|dct\|dff\|divx\|dsf\|dts\|dv\|dvd\|dxa\|eac3\|film\|flac\|flc\|fli\|fll\|flx\|flv\|g726\|gsm\|gxf\|iss\|m1v\|m2v\|m2t\|m2ts\|m4a\|m4b\|m4v\|mad\|mj2\|mjpeg\|mjpg\|mka\|mkv\|mlp\|mm\|mmf\|mov\|mp+\|mp1\|mp2\|mp3\|mp4\|mpc\|mpeg\|mpg\|mpga\|mpp\|mpu\|mve\|mvi\|mxf\|nc\|nsv\|nut\|nuv\|oga\|ogm\|ogv\|ogx\|oma\|ogg\|omg\|opus\|pcm\|psp\|pva\|qcp\|qt\|r3d\|ra\|ram\|rl2\|rm\|rmvb\|roq\|rpl\|rvc\|shn\|smk\|snd\|sol\|son\|spx\|str\|swf\|tak\|tgi\|tgq\|tgv\|thp\|ts\|tsp\|tta\|xa\|xvid\|uv\|uv2\|vb\|vid\|vob\|voc\|vp6\|vmd\|wav\|webm\|wma\|wmv\|wsaud\|wsvga\|wv\|wve\)"

# Search pre-defined subdirectories under /mnt
find /mnt/NAS -depth -mindepth 1 -type f -iregex ".*\.${TYPES_REGEX}" -printf "file: "NAS/"%P\n"
find /mnt/SDCARD -depth -mindepth 1 -type f -iregex ".*\.${TYPES_REGEX}" -printf "file: "SDCARD/"%P\n"
find /mnt/UPNP -depth -mindepth 1 -type f -iregex ".*\.${TYPES_REGEX}" -printf "file: "UPNP/"%P\n"

# Search all subdirectories under /media
# Have to prepend "USB/" to results to match MPD's structure
find /media -depth -mindepth 1 -type f -iregex ".*\.${TYPES_REGEX}" -printf "file: "USB/"%P\n"
