<?php
/**
 * moOde audio player (C) 2014 Tim Curtis
 * http://moodeaudio.org
 *
 * This Program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 3, or (at your option)
 * any later version.
 *
 * This Program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program.  If not, see <http://www.gnu.org/licenses/>.
 *
 */

// Log files
const MOODE_LOG = '/var/log/moode.log';
const AUTOCFG_LOG = '/var/log/moode_autocfg.log';
const UPDATER_LOG = '/var/log/moode_update.log';
const PLAY_HISTORY_LOG = '/var/log/moode_playhistory.log';
const MOUNTMON_LOG = '/var/log/moode_mountmon.log';
// MPD
const MPD_RESPONSE_ERR = 'ACK';
const MPD_RESPONSE_OK = 'OK';
const MPD_MUSICROOT = '/var/lib/mpd/music/';
const MPD_PLAYLIST_ROOT = '/var/lib/mpd/playlists/';
const MPD_LOG = '/var/log/mpd/log';
// SQLite
const SQLDB = 'sqlite:/var/local/www/db/moode-sqlite3.db';
const SQLDB_PATH = '/var/local/www/db/moode-sqlite3.db';
// Library/Playback
const LIBCACHE_BASE = '/var/local/www/libcache';
const ROOT_DIRECTORIES = array('NAS', 'SDCARD', 'USB');
const DEF_RADIO_TITLE = 'Radio station';
const DEF_RADIO_COVER = 'images/default-cover-v6.svg';
const DEF_COVER = 'images/default-cover-v6.svg';
const PLAYLIST_COVERS_ROOT = '/var/local/www/imagesw/playlist-covers/';
const RADIO_LOGOS_ROOT = '/var/local/www/imagesw/radio-logos/';
const LOGO_ROOT_DIR = 'imagesw/radio-logos/';
const TMP_IMAGE_PREFIX = '__tmp__';
const ALSA_PLUGIN_PATH = '/etc/alsa/conf.d';
const STATION_EXPORT_DIR = '/var/local/www/imagesw';
// Thumbnail generator
const THMCACHE_DIR = '/var/local/www/imagesw/thmcache/';
const THM_SM_W = 80; // Small thumbs
const THM_SM_H = 80;
const THM_SM_Q = 75;
// System files
const PORT_FILE = '/tmp/moode_portfile'; // Command engine
const SESSION_SAVE_PATH = '/var/local/php';
const DEV_ROOTFS_SIZE = 3670016000; // Bytes (3.5GB)
const LOW_DISKSPACE_LIMIT = 524288; // Bytes (512MB)
const BOOT_CONFIG_TXT = '/boot/config.txt';
const BOOT_CONFIG_BKP = '/boot/bootcfg.bkp';

// Features availability bitmask
// NOTE: Updates must also be made to matching code blocks in playerlib.js, sysinfo.sh, moodeutl, and footer.php
// sqlite3 /var/local/www/db/moode-sqlite3.db "SELECT value FROM cfg_system WHERE param='feat_bitmask'"
// sqlite3 /var/local/www/db/moode-sqlite3.db "UPDATE cfg_system SET value='97206' WHERE param='feat_bitmask'"
const FEAT_HTTPS		= 1;		//   HTTPS-Only mode
const FEAT_AIRPLAY		= 2;		// y AirPlay renderer
const FEAT_MINIDLNA 	= 4;		// y DLNA server
const FEAT_RECORDER		= 8; 		//   Stream recorder
const FEAT_SQUEEZELITE	= 16;		// y Squeezelite renderer
const FEAT_UPMPDCLI 	= 32;		// y UPnP client for MPD
const FEAT_SQSHCHK		= 64;		// 	 Require squashfs for software update
const FEAT_ROONBRIDGE	= 128;		// y RoonBridge renderer
const FEAT_LOCALUI		= 256;		// y Local display
const FEAT_INPSOURCE	= 512;		// y Input source select
const FEAT_UPNPSYNC 	= 1024;		//   UPnP volume sync
const FEAT_SPOTIFY		= 2048;		// y Spotify Connect renderer
const FEAT_GPIO 		= 4096;		// y GPIO button handler
const FEAT_RESERVED		= 8192;		// y Reserved for future use
const FEAT_BLUETOOTH	= 16384;	// y Bluetooth renderer
const FEAT_DEVTWEAKS	= 32768;	//   Developer tweaks
const FEAT_MULTIROOM	= 65536;	// y Multiroom audio
//						-------
//						  97206

// Selective resampling bitmask
const SOX_UPSAMPLE_ALL			= 3; // Upsample if source < target rate
const SOX_UPSAMPLE_ONLY_41K		= 1; // Upsample only 44.1K source rate
const SOX_UPSAMPLE_ONLY_4148K	= 2; // Upsample only 44.1K and 48K source rates
const SOX_ADHERE_BASE_FREQ		= 8; // Resample (adhere to base freq)

// Album and Radio HD badge parameters
// NOTE: Mirrored in playerlib.js
const ALBUM_HD_BADGE_TEXT 			= 'HD';
const ALBUM_BIT_DEPTH_THRESHOLD 	= 16;
const ALBUM_SAMPLE_RATE_THRESHOLD 	= 44100;
const RADIO_HD_BADGE_TEXT 			= 'HiRes';
const RADIO_BITRATE_THRESHOLD 		= 128;

// MPD output names
const ALSA_DEFAULT			= 'ALSA Default';
const ALSA_BLUETOOTH		= 'ALSA Bluetooth';
const HTTP_SERVER			= 'HTTP Server';
const STREAM_RECORDER		= 'Stream Recorder';

// ALSA output modes
const ALSA_OUTPUT_MODE_NAME = array('plughw' => 'Default', 'hw' => 'Direct');
// Bluetooth ALSA output modes
const BT_ALSA_OUTPUT_MODE_NAME = array('_audioout' => 'Default', 'plughw' => 'Compatibility');

// Source select devices
const SRC_SELECT_DEVICES = array(
    'HiFiBerry DAC+ ADC',
    'Audiophonics ES9028/9038 DAC',
    'Audiophonics ES9028/9038 DAC (Pre 2019)'
);

// Library Saved Searches
const LIBSEARCH_BASE = '/var/local/www/libsearch_';
const LIB_FULL_LIBRARY = 'Full Library (Default)';

// Recorder plugin (currently n/a)
const RECORDER_RECORDINGS_DIR 	 = '/Recordings';
const RECORDER_DEFAULT_COVER	 = 'Recorded Radio.jpg';
const RECORDER_DEFAULT_ALBUM_TAG = 'Recorded YYYY-MM-DD';
